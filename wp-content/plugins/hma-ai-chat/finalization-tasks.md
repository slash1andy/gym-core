# Finalization Tasks — hma-ai-chat 0.4.0 ("Gandalf")

> Generated: 2026-04-30  
> Auditor: TAMMIE (woocommerce-finalize skill)  
> Branch: chore/finalization-2026-04-30

---

## Testing Gate

| Check | Status | Notes |
|-------|--------|-------|
| Report exists | ⚠️ WARN | `testing-report.md` present but covers **v0.1.0** — three major versions behind |
| Timestamp | ❌ FAIL | Generated 2026-03-29 for v0.1.0; current plugin is v0.4.0 |
| All tests pass | ❌ UNKNOWN | PHP tools **not run** in the existing report; ESLint PASS only |
| PHPStan level | ⚠️ WARN | `phpstan.neon` configures `level: 7` (meets requirement) but has **never been executed** against v0.4.x code |

**Gate outcome:** Critical gap — the testing report does not cover current code. The entire v0.2.0–v0.4.0 delta (agent provisioning, Action Scheduler integration, Paperclip webhook, run_id pinning, audit log) has never been validated by the PHP test suite. A fresh test run is mandatory before any release.

---

## Track 1: Code Health

### TASK-OPT-001: HeartbeatEndpoint::handle_check_approval_status() bypasses PendingActionStore with raw $wpdb query — MEDIUM

- **File:** `src/API/HeartbeatEndpoint.php`
- **Lines:** ~317–329
- **Issue:** The method queries the `hma_ai_pending_actions` table directly via `$wpdb->get_row()` with a hardcoded `$wpdb->prefix . 'hma_ai_pending_actions'` string instead of going through `PendingActionStore`. This bypasses the store's column abstraction, duplicates table-name logic, and breaks if the table is renamed or schema-versioned. The bypass exists because `PendingActionStore` has no `get_action_by_run_id()` method.
  ```php
  // Current (bypasses store):
  $action = $wpdb->get_row(
      $wpdb->prepare(
          "SELECT id, status, action_data FROM $table WHERE run_id = %s ORDER BY created_at DESC LIMIT 1",
          $run_id
      ),
      ARRAY_A
  );
  ```
- **Fix:** Add `get_action_by_run_id( string $run_id ): ?array` to `PendingActionStore`, then call it from `handle_check_approval_status()`. The raw query moves into the store where it belongs.
- **Severity:** MEDIUM — architectural inconsistency; hardcoded table name creates maintenance risk
- **Status:** [ ] Not started

---

### TASK-OPT-002: Plugin::register_hooks() double-registered on admin_init and rest_api_init — MEDIUM

- **File:** `src/Plugin.php`
- **Lines:** 94–95
- **Issue:** `register_hooks()` is added to both `admin_init` and `rest_api_init`. On admin REST requests (e.g., Gutenberg block API calls from the WP admin), both hooks fire in the same request, causing `AgentUserManager::provision()` to run twice. Provisioning is idempotent for existing users, but the double `register_all_agents()` call rebuilds the `AgentRegistry` twice per request.
  ```php
  add_action( 'admin_init', array( $this, 'register_hooks' ) );
  add_action( 'rest_api_init', array( $this, 'register_hooks' ) );
  ```
- **Fix:** Gate on context before double-registration, or use a static `$registered` flag in `register_hooks()` to bail early on a second call. Alternatively, use a dedicated `rest_api_init`-only hook for the REST-specific initialization needs and avoid the overlap.
- **Severity:** MEDIUM — double work on every admin REST request; harmless today but fragile
- **Status:** [ ] Not started

---

### TASK-OPT-003: MessageEndpoint applies wp_kses_post() twice on the same input — LOW

- **File:** `src/API/MessageEndpoint.php`
- **Lines:** ~84 (manual call in callback), and args schema `'sanitize_callback' => 'wp_kses_post'`
- **Issue:** The REST API args schema specifies `sanitize_callback`, which WordPress runs automatically before the route callback fires. Line 84 then calls `wp_kses_post()` again on the already-sanitized value. This is redundant but harmless — the second pass is a no-op on clean input.
- **Fix:** Remove the manual `wp_kses_post()` call on line 84 and rely solely on the schema's `sanitize_callback`. Add a comment confirming the schema handles sanitization.
- **Severity:** LOW — redundant, not harmful
- **Status:** [ ] Not started

---

### TASK-OPT-004: ActionEndpoint instantiates new PendingActionStore() in every method — LOW

- **File:** `src/API/ActionEndpoint.php`
- **Lines:** Multiple (5+ methods each with `new PendingActionStore()`)
- **Issue:** `PendingActionStore` is re-instantiated in every `ActionEndpoint` method. The store is stateless so this is functionally correct, but it creates multiple objects per request and makes injection harder for testing.
- **Fix:** Accept `PendingActionStore` as a constructor parameter and store it as `$this->pending_store`. Match the pattern used by `MessageEndpoint` which receives `ToolExecutor` via the plugin bootstrap.
- **Severity:** LOW — minor; blocking for testability improvement
- **Status:** [ ] Not started

---

### TASK-OPT-005: Plugin::maybe_upgrade_db() @since annotation contradicts version migration block — LOW

- **File:** `src/Plugin.php`
- **Lines:** `maybe_upgrade_db()` docblock and body
- **Issue:** The method is documented as `@since 0.4.1` but its body contains a migration block explicitly labeled `0.5.0 → 0.5.1`. This is either a forward-looking placeholder for an upcoming 0.5.x release (legitimate) or a version numbering error. Either way it is confusing to any developer reading the file.
- **Fix:** If the 0.5.x migration is intentional future code, add a comment explaining it is a pre-staged migration. If the version numbering is wrong, correct the `@since` tag and the migration label to match actual released versions.
- **Severity:** LOW — documentation/clarity only
- **Status:** [ ] Not started

---

## Track 2: Traceability

### TASK-TRC-001: Chat message flow — ✅ VERIFIED

- **Path:** React frontend → `POST /hma-ai-chat/v1/message` → rate-limit check → `AgentRegistry::get_agent()` → `ClaudeClient::send()` or `call_wp_ai_client()` → assistant message persisted → streamed response to UI
- **Entry:** `src/API/MessageEndpoint.php` — `handle_request()`, route `POST /hma-ai-chat/v1/message`
- **Exit:** `WP_REST_Response` with assistant reply; message saved to `ConversationStore`
- **Gap/Finding:** Rate limit (30 req/min) enforced before agent dispatch. Capability check confirms `manage_options` or equivalent. User message write is rolled back if LLM call fails. `call_wp_ai_client()` fallback deduplicates the last history message to avoid double-appending. See TASK-OPT-003 for redundant sanitization in this path.
- **Status:** [ ] Verified — no blocking gap

---

### TASK-TRC-002: Action approval flow — ✅ VERIFIED

- **Path:** Agent proposes action → `PendingActionStore::create()` → staff `POST /hma-ai-chat/v1/actions/{id}/approve` → status validated (reject if not `pending`) → `ToolExecutor::execute_approved_write()` → result returned → audit log entry written
- **Entry:** `src/API/ActionEndpoint.php` — `handle_approve()`, route `POST /hma-ai-chat/v1/actions/{id}/approve`
- **Exit:** `AuditLogger::log()` called after execution; `WP_REST_Response` with execution result
- **Gap/Finding:** Status guard prevents double-approval (returns 409 if not `pending`). `execute_approved_write()` runs synchronously in the request. Admin audit log endpoint uses `cache_users()` to avoid N+1 on user display name resolution. See TASK-OPT-004 for `PendingActionStore` instantiation pattern.
- **Status:** [ ] Verified — no blocking gap

---

### TASK-TRC-003: Paperclip webhook — ✅ VERIFIED

- **Path:** Incoming `POST /hma-ai-chat/v1/heartbeat` → IP allowlist check → Bearer token validation (`WebhookValidator`) → route by `wakeReason` → handler dispatch
- **Entry:** `src/API/HeartbeatEndpoint.php` — `handle()`, route `POST /hma-ai-chat/v1/heartbeat`
- **Exit:** Handler result returned; `run_id` pinning via `hash_equals()` in `handle_revised_action_complete()`
- **Gap/Finding:** `WebhookValidator` uses `REMOTE_ADDR` only (spoofable proxy headers rejected). Constant-time comparison via `hash_equals()` for token. 5-minute rotation grace period for secret rollover. IP enforcement toggle defaults false on legacy installs (documented behavior). `run_id` pinning prevents cross-run action completion. See TASK-OPT-001 for the `handle_check_approval_status()` $wpdb bypass within this path.
- **Status:** [ ] Verified (with TASK-OPT-001 caveat) — no blocking gap in security; architectural gap in store usage

---

### TASK-TRC-004: Audit trail — ⚠️ SUSPICIOUS

- **Path:** Write action executed → `AuditLogger::log()` → `hma_ai_audit_log` table → admin audit query / display
- **Entry:** `AuditLogger::log()` called from `ToolExecutor` after approved write
- **Exit:** `ActionEndpoint` audit list endpoint with `cache_users()` prefetch
- **Gap/Finding:** The primary audit write path (`AuditLogger::log()` from `ToolExecutor`) appears intact. However, `HeartbeatEndpoint::handle_check_approval_status()` (TASK-OPT-001) reads action status via raw `$wpdb` rather than through the store. If the heartbeat path ever writes audit entries independently, those would also bypass the store abstraction. The audit trail for Paperclip-initiated status checks is not fully verified because `handle_check_approval_status()` was read as a read-only path — but the $wpdb bypass pattern should be resolved to reduce risk surface.
- **Status:** [ ] Suspicious — resolve TASK-OPT-001 and re-verify that all action-status writes pass through the store

---

## Summary

| Category | Count | Blocking? |
|----------|-------|-----------|
| Gate failures | 2 (stale report v0.1.0, PHP tools never run) | Yes — fresh test run mandatory before release |
| Track 1 MEDIUM | 2 | Recommended before release |
| Track 1 LOW | 3 | Non-blocking |
| Track 2 VERIFIED | 3 of 4 | — |
| Track 2 SUSPICIOUS | 1 (audit trail / $wpdb bypass) | Resolve with TASK-OPT-001 |

**Recommended pre-release actions (priority order):**
1. Run full PHP test suite and PHPStan level 7 against current v0.4.0 code — generate a fresh `testing-report.md`
2. Add `PendingActionStore::get_action_by_run_id()` and update `HeartbeatEndpoint` (TASK-OPT-001)
3. Fix double hook registration in `Plugin.php` (TASK-OPT-002)
4. Re-verify audit trail path after TASK-OPT-001 is resolved (TASK-TRC-004)
