# mcp-adapter Spike — Operator Runbook

## What this is

[WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter) is a separate
WordPress plugin that bridges the Abilities API to the Model Context Protocol.
With it installed, any MCP-capable client (Claude Code, Cursor, ChatGPT
desktop, the `mcp-wordpress-remote` proxy) can discover and invoke Gandalf's
tool surface as MCP tools — discovery, schemas, permission checks, and write
approval all flow through the same pipeline the in-plugin chat already uses.

This is a **spike**, not a production deploy. It exists to answer:

1. Do all 50+ Gandalf abilities round-trip cleanly through the adapter?
2. Does the per-tool `permission_callback` actually run (and gate sensitive
   tools), or does the adapter's transport-level auth bypass it?
3. Do write tools (e.g. `issue_refund`, `draft_announcement`) still queue for
   staff approval, or do they execute immediately?
4. What's the auth UX for an external MCP client — Application Passwords,
   session cookie, OAuth?

## Pre-flight (no production touched)

| What | Where |
|---|---|
| mcp-adapter source clone | `/Users/andrewwikel/Local Sites/mcp-adapter` (v0.5.0 tagged) |
| mcp-adapter floor | WP 6.8+, PHP 7.4+ |
| hma-ai-chat floor | WP 7.0+, PHP 8.0+ — comfortably above |
| Gandalf MCP opt-in | Shipped — `meta['mcp']['public'] = true` set by [src/MCP/AbilitiesRegistrar.php](../src/MCP/AbilitiesRegistrar.php) |
| Filter for per-tool suppression | `hma_ai_chat_mcp_public_ability` — return false to hide a tool from MCP |

The hma-ai-chat side is wired up. The next step is operational: install the
adapter on a WordPress install and verify round-trip behaviour.

## Where to run the spike

There is **no local dev site for this project**. Three options, in order of
recommended caution:

| Option | Risk | Speed |
|---|---|---|
| WordPress Playground (browser-based throwaway) | None — wiped on close | 5 min to stand up |
| Pressable / WP.com scratch site | None | 15 min |
| Production | High — installs an unreleased plugin | Don't |

**Pick Playground unless there's a specific reason it can't work.** The HMA
plugins import a lot of CRM/billing data; Playground won't have that, but
that's fine for the spike — the goal is to verify *the integration shape*,
not to invoke real tools.

## Sandbox setup (Playground path)

```bash
# From a checkout of WordPress Playground or via wp-cli on a fresh WP install
wp core install --url=http://localhost --title="MCP Spike" \
    --admin_user=admin --admin_password=admin --admin_email=admin@example.test

# Install + activate mcp-adapter (v0.5.0 tag)
cd wp-content/plugins
git clone --branch v0.5.0 https://github.com/WordPress/mcp-adapter.git
cd mcp-adapter && composer install --no-dev
wp plugin activate mcp-adapter

# Install the gym-core monorepo plugins
# (symlink or copy the `wp-content/plugins/gym-core` and
# `wp-content/plugins/hma-ai-chat` directories from your gym-core checkout
# into wp-content/plugins/)
wp plugin activate gym-core hma-ai-chat
```

Then walk the verification checklist below.

## Verification checklist

Run each step in order. Each `[ ]` is a hard gate — if it fails, stop and
record the failure mode in the "Findings" section at the bottom of this doc.

### 1. Discovery — abilities are registered and visible

```bash
# Confirm gandalf/* abilities exist
wp eval 'print_r( array_keys( wp_get_abilities() ) );' | grep gandalf | head
# Expected: ~50 entries like gandalf/get-mrr, gandalf/get-classes, etc.

# Confirm category exists
wp eval 'print_r( array_keys( wp_get_ability_categories() ) );'
# Expected: gym-operations among the keys.
```

- [ ] All Gandalf abilities present (count matches `ToolRegistry::get_all_tool_names()`)
- [ ] `gym-operations` category registered

### 2. MCP exposure — the adapter sees them

```bash
# The adapter exposes a default server at this route.
curl -s -u admin:admin \
  "http://localhost/wp-json/mcp/mcp-adapter-default-server" \
  -H "Mcp-Session-Id: spike-$(date +%s)" \
  -H "Accept: application/json" | jq '.result.tools[] | select(.name | startswith("gandalf/")) | .name'
```

- [ ] Output includes `gandalf/get-mrr`, `gandalf/get-classes`, `gandalf/get-member-orders`, and other read tools
- [ ] Output also includes write tools like `gandalf/issue-refund`, `gandalf/draft-announcement` (they should appear — they queue, they don't execute)
- [ ] Tool count matches `wp_get_abilities()` count (the `mcp.public = true` flag worked)

If any read tool is missing, check `meta['mcp']['public']` is set on its registration:

```bash
wp eval 'print_r( wp_get_ability( "gandalf/get-mrr" )->get_meta() );'
```

### 3. Read-tool round-trip — `get_subscriptions_summary`

```bash
SESSION_ID="spike-$(date +%s)"

# Step 1: tools/list (discovery)
curl -s -u admin:admin \
  "http://localhost/wp-json/mcp/mcp-adapter-default-server" \
  -H "Mcp-Session-Id: $SESSION_ID" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}' | jq '.result.tools | length'

# Step 2: invoke gandalf/get-subscriptions-summary
curl -s -u admin:admin \
  "http://localhost/wp-json/mcp/mcp-adapter-default-server" \
  -H "Mcp-Session-Id: $SESSION_ID" \
  -H "Content-Type: application/json" \
  -d '{
        "jsonrpc":"2.0",
        "method":"tools/call",
        "params":{
          "name":"gandalf/get-subscriptions-summary",
          "arguments":{}
        },
        "id":2
      }' | jq
```

- [ ] tools/list returns abilities with `inputSchema` (and `outputSchema` if PR #43 is merged)
- [ ] tools/call returns a payload matching `subscription_summary_output_schema()` shape
- [ ] If WC Subscriptions isn't active in the sandbox, expect a `wcs_unavailable` error — that's the documented 503 behaviour, not a regression

### 4. Permission check — non-admin user is blocked

```bash
wp user create spike-coach coach@example.test \
  --role=subscriber --user_pass=coach
```

```bash
SESSION_ID="spike-coach-$(date +%s)"

# Coach lacks manage_woocommerce, so get_mrr should refuse
curl -s -u spike-coach:coach \
  "http://localhost/wp-json/mcp/mcp-adapter-default-server" \
  -H "Mcp-Session-Id: $SESSION_ID" \
  -H "Content-Type: application/json" \
  -d '{
        "jsonrpc":"2.0",
        "method":"tools/call",
        "params":{"name":"gandalf/get-mrr","arguments":{}},
        "id":1
      }' | jq '.error // .result'
```

- [ ] Response is an error referencing the missing capability — NOT a successful payload. If a coach gets MRR data, the per-tool `permission_callback` is being bypassed and the spike has FAILED. Stop and investigate.

### 5. Write tool — queues, doesn't execute

```bash
SESSION_ID="spike-$(date +%s)"

# Pick a low-stakes write tool
curl -s -u admin:admin \
  "http://localhost/wp-json/mcp/mcp-adapter-default-server" \
  -H "Mcp-Session-Id: $SESSION_ID" \
  -H "Content-Type: application/json" \
  -d '{
        "jsonrpc":"2.0",
        "method":"tools/call",
        "params":{
          "name":"gandalf/draft-announcement",
          "arguments":{
            "title":"MCP spike test",
            "content":"This should queue, not publish."
          }
        },
        "id":1
      }' | jq
```

```bash
# Confirm a pending action was created, NOT a published post
wp post list --post_type=gym_announcement --post_status=any \
    --search="MCP spike test" --format=count
# Expected: 0

wp eval 'global $wpdb; print_r( $wpdb->get_results(
    "SELECT id, agent, action_type, status FROM {$wpdb->prefix}hma_ai_pending_actions
     WHERE action_type LIKE \"%draft_announcement%\"
     ORDER BY id DESC LIMIT 1"
) );'
# Expected: a pending row with status=pending.
```

- [ ] Response payload includes an `action_id` and a "queued for staff approval" message
- [ ] `wp_hma_ai_pending_actions` table has a new pending row
- [ ] No `gym_announcement` post was published
- [ ] If a post was published, write tools are bypassing approval — STOP, do not run more write-tool tests, file an issue

### 6. Per-tool suppression — the filter works

Drop a tiny mu-plugin to verify operators can hide specific tools:

```bash
cat > wp-content/mu-plugins/spike-hide-mrr.php <<'PHP'
<?php
add_filter( 'hma_ai_chat_mcp_public_ability',
    static function ( $public, $tool_name ) {
        return 'get_mrr' === $tool_name ? false : $public;
    }, 10, 2 );
PHP

# Force a re-registration by reactivating
wp plugin deactivate hma-ai-chat && wp plugin activate hma-ai-chat
```

```bash
curl -s -u admin:admin \
  "http://localhost/wp-json/mcp/mcp-adapter-default-server" \
  -H "Mcp-Session-Id: spike-filter-$(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}' \
  | jq '.result.tools[] | select(.name == "gandalf/get-mrr") | .name'
```

- [ ] No output (`get_mrr` is suppressed) — but `get_subscriptions_summary` still appears
- [ ] Remove the mu-plugin: `rm wp-content/mu-plugins/spike-hide-mrr.php`

### 7. STDIO transport (optional)

For Claude Code / Cursor integration:

```bash
wp mcp-adapter serve --server=mcp-adapter-default-server
# Then point your client's MCP config at this stdio process.
```

Skip if you only care about the HTTP path.

## Findings (fill in after running)

- **Adapter version tested:**
- **WP version:**
- **PHP version:**
- **Auth mode that worked:** (Basic? Application Password? cookie?)
- **Tools that round-tripped cleanly:**
- **Tools that failed (and why):**
- **Write-tool approval flow verified:** Yes / No
- **Permission gate enforced:** Yes / No
- **Recommendation:**
  - [ ] Ship — proceed to production install
  - [ ] Hold — issues to resolve first (list them)
  - [ ] Don't ship — fundamental incompatibility (explain)

## If "ship" — production deployment notes

1. Install `mcp-adapter` on the production HMA site behind a backup.
2. Decide auth model:
   - **Application Passwords** are the cleanest path for external clients.
   Each TAM creates one for their MCP client; revocation is per-app.
   - Restrict the dedicated adapter user to a custom capability (e.g.
   `gandalf_mcp_client`) and audit which abilities require what — Gandalf
   tools each have their own capability gate, so this is defence in depth.
3. Document the MCP server URL + auth flow for TAMs in the team
   onboarding-guide agent.
4. Add a short paragraph to [CLAUDE.md](../CLAUDE.md) describing the
   integration so future agents know it exists and how it's scoped.

## Rollback

```bash
wp plugin deactivate mcp-adapter
# Optional: also delete it
wp plugin delete mcp-adapter
```

The adapter has no schema changes; deactivating fully removes the MCP
endpoint without affecting hma-ai-chat or gym-core data.

## References

- [Adapter README](https://github.com/WordPress/mcp-adapter#readme)
- [Adapter source — McpTool permission flow](https://github.com/WordPress/mcp-adapter/blob/v0.5.0/includes/Domain/Tools/McpTool.php#L370)
- [Abilities API — `meta` arg docs](https://github.com/WordPress/abilities-api/blob/trunk/includes/abilities-api.php) (now in WP core 6.9.0)
- [Adapter open issues](https://github.com/WordPress/mcp-adapter/issues) — track #176 for AI-chat approval-flow gaps
