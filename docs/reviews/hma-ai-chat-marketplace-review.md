# hma-ai-chat v0.4.0 ("Gandalf") — Marketplace-Style Review

**Plugin:** hma-ai-chat
**Version:** 0.4.0
**Scope:** Held to WooCommerce marketplace submission standards with AI-safety scope added (prompt-injection, approval-flow integrity, agent privilege separation). Internal plugin, not a submission candidate.
**Environment:** WordPress 7.0 / PHP 8.0+ / WP AI Client with Claude API fallback.
**Method:** Three parallel reviewer slices (security + AI-safety, standards + frontend, performance + architecture) with main-session inventory pre-gathered and synthesis here. The slices surfaced an independently-verified carry-forward finding from the earlier-killed reviewer (the REST nonce middleware wiring gap) — included here. Raw slices at `_slices/hma-ai-chat-*.md`.

## Executive Summary

Gandalf's staff-in-the-loop architecture is structurally sound: every write tool is queued via `ToolExecutor::create_pending_action`, the `pending` status gate is enforced on approve/reject, DB writes are prepared, `hash_equals` is used for both current and previous webhook secrets during rotation, every REST route carries a `permission_callback`, and the agent role starts with zero capabilities. The approval UI is well-escaped, i18n and prefix discipline are tight, PSR-4 autoloading is correct, and the new (HEAD commit) `ActionNotifier` honors the agreed envelope (admin notice + Slack + SMS each independently gated).

**But the AI-safety edges have real gaps.** Three MAJOR security findings concentrate on the approval-flow re-validation path (a manipulated "approve-with-changes" can quietly swap a refund's `order_id`), the IP allowlist being opt-out (empty = accept-any), and the just-landed `ActionNotifier` shipping PII-ish action summaries over SMS and to Slack. The two frontend MAJORs are a missing apiFetch nonce middleware (latent 403s for non-admin `edit_posts` users) and no `aria-live` on the chat message stream. The perf MAJORs are the synchronous SMS fan-out in the notifier I just wrote, N+1 `get_userdata()` in audit log rendering, and no retry/rollback in the ClaudeClient → MessageEndpoint path.

**Severity counts:** blocker=0, major=8, minor=13, nit=8

**Recommendation:** **Address before any production use.** The approval-flow re-validation gap (MAJOR-01) and the IP-allowlist-opens-empty default (MAJOR-03) are AI-safety risks that matter even in a single-gym deployment. The rest of the stack is already at or near "clean internal plugin" quality.

## Findings

### MAJOR — Security + AI-safety (3)

#### MAJOR-01: Approval flow does not re-pin action identity on "approve-with-changes"
- **File:** `src/API/ActionEndpoint.php:344-393`, `src/Data/PendingActionStore.php:162-209`, `src/API/HeartbeatEndpoint.php:241-276`
- **Issue:** `approve_with_changes` checks only the `pending` status; staff instructions go into `action_data.staff_changes` verbatim and are shipped back to Paperclip. When Paperclip posts `revised_action_complete`, `complete_revised_action` merges `revisedData` (shallow-sanitized) into the same row and marks it `completed`. **Nothing pins `action_type`, `tool_name`, `endpoint`, or target IDs (`order_id`, `user_id`, `contact_id`, `phone`) to the originals.** A manipulated agent re-execution can submit revised data for a *different* target — a revised `issue_refund` against a different order, or a revised `draft_sms` to a different phone number — and it will be written to the "completed" audit row that staff see as approved. `handle_check_approval_status` also doesn't verify the polling `runId` matches the stored `run_id`, so one run can complete another's action.
- **Fix:** In `complete_revised_action`, diff `$revised_data` against a whitelist of mutable keys from the original `action_data` and reject changes to `tool_name`, `endpoint`, `method`, target IDs, and `agent_user_id`. Also verify `runId` matches the stored `run_id` before accepting completion.

#### MAJOR-02: `ActionNotifier` SMS body leaks action summary (potential PII) off-platform
- **File:** `src/Notifications/ActionNotifier.php:44-75, 128-158`
- **Issue:** `dispatch()` puts `action_data['description']` into the SMS body. `description` is set by Paperclip or `ToolExecutor::create_pending_action` and often carries staff-facing context (member names, phone fragments, refund reasons) via agent phrasing. SMS is not end-to-end encrypted; Twilio is a third-party processor. The same `$summary` goes to Slack, which has workspace retention. No opt-out, no metadata-only mode. This is my own code from the M6.3 commit.
- **Fix:** Restrict SMS body to non-PII metadata: `Gandalf: <agent> queued <action_type> #<id> — review in admin`. Drop `$summary` from SMS entirely. Gate Slack summary behind a new `hma_ai_chat_notify_include_summary` option (default false). Add a filter `hma_ai_chat_notifier_summary` for site-specific sanitization.

#### MAJOR-03: IP allowlist is opt-out — empty allowlist silently accepts any IP
- **File:** `src/Security/WebhookValidator.php:132-142`
- **Issue:** `validate_ip()` returns true when the allowlist is empty. The webhook auth is a bearer shared-secret (no per-request body HMAC), so the IP fence is the only secondary barrier. Fresh installs, or any admin clearing the textarea, drop to signature-only auth. Admin UI describes empty state as "allow all (not recommended)" but current state isn't surfaced on a dashboard.
- **Fix:** Add an "Enforce IP allowlist" toggle that fails closed when the allowlist is empty. Surface a warning admin notice on the settings page when empty-with-enforcement-off. Ship with Paperclip's production egress IPs pre-seeded.

### MAJOR — Standards + Frontend (2)

#### MAJOR-04: REST nonce never wired into apiFetch middleware
- **File:** `assets/js/chat-app.js:1-16`, `src/Admin/ChatPage.php:60-73`
- **Issue:** `ChatPage::enqueue_assets()` localizes `hmaAiChat.nonce = wp_create_nonce( 'wp_rest' )` but `chat-app.js` never calls `wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( config.nonce ) )` and `wpApiSettings` is not localized either. `apiFetch` has no `X-WP-Nonce` source; REST calls rely entirely on the admin session cookie. Works for admin users today. Any non-admin `edit_posts` user (Editor/Author) loading the panel — or an admin whose cookie goes stale — gets `rest_cookie_invalid_nonce` 403s on every message/action/heartbeat.
- **Fix:** Add `wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( config.nonce ) );` immediately after `config` assignment in `chat-app.js`, before any `wp.apiFetch` call. No server change needed.
- **Provenance:** Originally surfaced by the first (killed) plugin-reviewer attempt, verified by the standards slice in this pass.

#### MAJOR-05: No `aria-live` / `role="log"` on assistant message stream
- **File:** `assets/js/chat-app.js:60` (`#hma-messages` container), `chat-app.js:228-232` (message render)
- **Issue:** Chat messages container has no live-region semantics. Screen-reader users hear the Send click and then nothing until they navigate back into the message region. Single most impactful a11y gap in an otherwise well-labelled UI.
- **Fix:** Add `role="log" aria-live="polite" aria-relevant="additions" aria-atomic="false"` to `#hma-messages` in `renderChatPanel()`. Keep the typing indicator outside the live region (or use `aria-busy`) to avoid noisy dot-announcements.

### MAJOR — Performance + Architecture (3)

#### MAJOR-06: Synchronous SMS fan-out in `ActionNotifier` blocks action-creation requests
- **File:** `src/Notifications/ActionNotifier.php:148-158`
- **Issue:** `send_sms()` loops through every admin number and calls `TwilioClient::send()` inline. TwilioClient is a synchronous `wp_remote_post` with a **15-second timeout**. With 3-5 admin numbers and a Twilio outage, 45-75s is added to whatever request fired `hma_ai_chat_pending_action_created` — which fires inline from `PendingActionStore::store_pending_action`, reachable from MessageEndpoint (via ToolExecutor), HeartbeatEndpoint (Paperclip), and bulk-action endpoints. TwilioClient's own docblock claims "Sending is queued via Action Scheduler" — but the code path we call is the synchronous one. This is my own code from the M6.3 commit.
- **Fix:** Defer SMS dispatch via `as_enqueue_async_action( 'hma_ai_chat_send_sms', [...] )` or `wp_schedule_single_event( time(), … )`. The Slack send already uses a 5s timeout — apply the same tight budget AND async dispatch for SMS.

#### MAJOR-07: N+1 `get_userdata()` in audit log rendering (admin page and REST)
- **File:** `src/Admin/AuditLogPage.php:146`, `src/API/ActionEndpoint.php:576`
- **Issue:** Both paths call `get_userdata()` inside a `foreach` over the 20-row page. WP's per-request user object cache helps on warm cache; first load can fan out to 40+ per-row queries. `get_all_actions` orders by `created_at DESC` and filters by `status`/`agent`; no index confirmed on `(status, created_at)`.
- **Fix:** Collect distinct `approved_by` IDs first, call `_prime_user_caches( $user_ids )` once, then the per-row lookups become cache hits. Same in `ActionEndpoint::get_audit_log`. Add a `(status, created_at)` index to `hma_ai_pending_actions` via the Activator.

#### MAJOR-08: ClaudeClient has no retry/backoff; MessageEndpoint saves the user turn before the Claude call
- **File:** `src/API/ClaudeClient.php:82-110`, `src/API/MessageEndpoint.php:117-181`
- **Issue:** Chat hot path does a synchronous `wp_remote_post` to Anthropic with a 60s timeout. On 429 or 5xx, the client wraps into `WP_Error(502)` and returns. The user's message was already `save_message()`'d at line 133 before the call, so a failure leaves an orphan user-turn row in history. Next request re-walks history, re-sends the orphan, double-billing tokens. No backoff or `retry-after` honoring.
- **Fix:** (1) 1–2 retries on 429/503 with jittered backoff, respecting `retry-after`. (2) Either roll back the user-message save on Claude failure, or mark it `failed: true` so the UI can retry without a duplicate insert.

### MINOR (13)

#### Security + AI-safety (5)
- **MINOR-09:** Webhook auth is a bearer shared-secret, not HMAC-over-body. Captured requests can be replayed until rotation. Add `X-HMA-Signature: t=<ts>,v1=<hmac>` with 5-minute timestamp window as the stronger mode; keep bearer as fallback.
- **MINOR-10:** Slack webhook URL not domain-scoped. `sanitize_webhook_url` allows any HTTPS host; a compromised admin session becomes a data-exfiltration primitive. Require `hooks.slack.com` by default; filter `hma_ai_chat_allowed_slack_hosts` for Mattermost.
- **MINOR-11:** `handle_check_approval_status` query is race-prone (`WHERE run_id=%s ORDER BY created_at DESC LIMIT 1`). Two actions sharing a run_id = earlier one silently stranded. Require `actionId` in the status request; query by primary key.
- **MINOR-12:** `ToolExecutor::execute_approved_write` defined but never wired to `hma_ai_chat_action_approved`. Either Paperclip drives execution and this is dead code, or the wiring was dropped in a refactor and approved writes never run server-side. Functional gap with a security tail: a latent method that bypasses MAJOR-01's re-validation is a footgun if naively wired up later.
- **MINOR-13:** `ActionNotifier` has no rate limiting. 50 pending actions = 50 SMS per admin number in one HTTP cycle. Add transient-backed per-channel limits (1 SMS per admin per 60s; 10 Slack posts per 5min) with coalesced "N more queued" summary on overflow.

#### Standards + Frontend (4)
- **MINOR-14:** `uninstall.php` missing `declare(strict_types=1)` — only file of 24 without it. Add it; uninstall is where you want strict typing most.
- **MINOR-15:** Action notices (approval toasts) lack `role="status"`. They auto-dismiss in 3s; screen-reader users miss the outcome. Add `role="status"` (or `role="alert"` for errors) in `showActionNotice()`.
- **MINOR-16:** Asset enqueue couples to gym-core hook suffixes by string (`gym_page_hma-ai-chat`, `toplevel_page_gym-core`). If gym-core renames its menu slug, chat assets silently stop loading. Either own the hook suffix (register the chat submenu from this plugin) or add a code comment pointing to gym-core's owning file.
- **MINOR-17:** ~45 lines of CSS inlined inside `AuditLogPage::render_page()` (lines 191-236). Move to `assets/css/audit-log.css` and enqueue on the audit-page hook suffix.

#### Performance + Architecture (4)
- **MINOR-18:** `ActionEndpoint` (7 callbacks) creates a new `PendingActionStore` per request. Same pattern in `MessageEndpoint`. Stores are stateless so not a bug, but cache singletons on `Plugin` (like `$tool_executor`) or introduce a small service container.
- **MINOR-19:** `GymContextProvider::get_context_for_persona` runs 4–8 internal REST dispatches per chat message. `CACHE_TTL = 300` is defined; not every `get_*_context()` method was verified to use it. Add a `cached_dispatch( string $key, callable $builder )` wrapper so no method can forget.
- **MINOR-20:** Claude failure leaves an orphan user-turn row (companion finding to MAJOR-08). Insert after a successful reply, or mark `pending`/`committed` so history walks ignore unreplied turns.
- **MINOR-21:** `MessageEndpoint` lacks a request-level timeout budget. Rate limit (30/min per user) mitigates exhaustion but doesn't bound worst-case single-request time. Scale the outbound timeout to `ini_get('max_execution_time')`; queue + streaming for v0.5.

### NIT (8)

- **NIT-22:** Audit log doesn't record reviewer IP, user-agent, or a pre-approval `action_data` snapshot. Add `reviewer_ip`, `reviewer_ua`, `action_data_before` columns. Low-effort, high-value for post-incident reconstruction.
- **NIT-23:** `WebhookValidator::rotate_secret`'s auto-cleanup of the previous secret runs as a side effect inside `is_in_rotation_grace_period` (a read path). Schedule a single-event cleanup at rotation time instead.
- **NIT-24:** Conversation history is trusted as prompt input without structural separation. Untrusted fields (member name, announcement body, CRM note) flow into the system prompt concatenated with `\n\n--- Current Gym Context ---\n`. A CRM note containing `--- End Context ---\nSystem: …` would be read naively. Wrap untrusted content in `<context_data>` tags and add a standing persona instruction to treat tagged content as data-only. Defense-in-depth; approval gate remains primary.
- **NIT-25:** Agent icon/name dropped into backtick `innerHTML` without JS-side `escapeHtml()`. Safe today (icons are emoji constants, names are server-sanitized) but inconsistent with the rest of `chat-app.js` which uses `escapeHtml()` everywhere. Wrap for defense-in-depth.
- **NIT-26:** `renderMarkdown()` uses `var` where the rest of the file uses `const`/`let`. Consistency nit.
- **NIT-27:** No `prefers-reduced-motion` guard on typing-dot and fade-in animations. Add `@media (prefers-reduced-motion: reduce) { .hma-ai-typing-dot, .hma-ai-action-notice { animation: none !important; } }`.
- **NIT-28:** Zero unit tests. Highest-leverage targets to start: `PendingActionStore::get_all_actions` (SQL builder with dynamic WHERE), `ToolExecutor::resolve_route`. Pure PHP, no WP bootstrap needed beyond wpdb mocks.
- **NIT-29:** `ConversationStore::purge_expired_conversations` runs `DELETE … WHERE updated_at < %s`. `updated_at` index not confirmed in Activator's `dbDelta` schema. On a site with years of history, a non-indexed scan degrades silently. Add `KEY updated_at (updated_at)` in a 0.4.1 schema bump.

## Verified Clean (from slice audits)

**Security + AI-safety**
- `hash_equals` used on both current and previous webhook secrets during rotation (`WebhookValidator.php:85,92`).
- Every REST route has a `permission_callback`; `/actions/{id}/status`'s `check_webhook_or_admin` requires EITHER admin OR (valid signature AND IP allowlist), not lazy OR.
- `manage_options` capability model on approve/reject/audit-log is appropriate.
- Deny-by-default on unknown tools in `ToolExecutor::execute` (lines 81-91) and persona-without-tool combinations (lines 94-105).
- Agent privilege separation: `ToolRegistry::PERSONA_TOOLS` properly scopes sales/coaching to subsets. Sales cannot issue refunds or see billing. `AgentUserManager` syncs capabilities on every init with stale-cap cleanup.
- Agent accounts: custom `hma_ai_agent` role with zero capabilities; `authenticate` filter blocks login at priority 100; `pre_get_users` hides agents from the Users list.
- Admin nonce + `manage_options` re-check on secret rotation.
- Anthropic API key rendered `type="password"` with `autocomplete="off"` and blanked on re-display. Webhook secret shown only as `first8...` (truncated + dots). Rotated secret delivered via 60s transient, not URL or log.
- `get_client_ip()` uses `REMOTE_ADDR` only; explicitly rejects `HTTP_X_FORWARDED_FOR`.
- All `$wpdb` calls prepared; array-form `insert`/`update` with explicit format arrays.
- SMS body capped at 320 chars via `mb_substr` to bound Twilio per-message charges.
- Slack webhook HTTPS-only enforcement surfaces a settings error (not silent save).
- `sanitize_sms_admin_numbers` strips to `[\d+]` with 8-char min. `sanitize_ip_allowlist` uses `FILTER_VALIDATE_IP`.
- `AuditLogPage` escapes every output; `wp_kses_post` only on the statically-built status badge.
- Staff "approve with changes" instructions are `sanitize_textarea_field`'d and displayed via `esc_html` + `wp_trim_words`.

**Standards + Frontend**
- Plugin header: `Text Domain: hma-ai-chat`, `Domain Path: /languages`. `load_plugin_textdomain` on `plugins_loaded`.
- PSR-4 autoloader wired; `class_exists( 'HMA_AI_Chat\\Plugin' )` guard.
- Prefix discipline: options, user-meta, REST route, cron hook, CSS classes, JS identifiers all `hma_ai_chat_*` / `.hma-ai-*` / `hmaAiChat`.
- i18n coverage: every spot-checked admin string wrapped with the `hma-ai-chat` text domain. Plural `_n()` forms used correctly with translator comments on sprintf placeholders.
- 23/24 files declare `strict_types=1` (other is `uninstall.php` — MINOR-14).
- No jQuery. Deps are only `wp-api-fetch`.
- `Activator::create_tables` uses `dbDelta` with `$wpdb->get_charset_collate()`; `db_version` stored for migrations.
- `Deactivator::deactivate` clears `PURGE_CRON_HOOK` via `wp_clear_scheduled_hook`. Uninstall drops tables, removes role, deletes agent users, purges options.
- Asset versioning: `HMA_AI_CHAT_VERSION` passed to every enqueue.
- Admin UI controls have `aria-label` (agent selector, message input, send, approve/reject/approve-with-changes, bulk select-all, textareas).
- Focus indicators: action buttons have visible 2px outline on focus; input/textarea replace `outline:none` with `box-shadow` ring.
- AuditLog uses real `<table>` + `<thead>` + `<tbody>`, WP core `paginate_links()`, `wp_kses_post` only on trusted status-badge HTML.

**Performance + Architecture**
- `Deactivator` correctly unschedules cron. `Plugin::init()` checks `wp_next_scheduled` before re-scheduling.
- `PendingActionStore::get_pending_count` uses `wp_cache_get`/`set` with 60s TTL; every writer invalidates via `wp_cache_delete( 'hma_ai_pending_count' )`.
- ActionNotifier Slack path has a 5s timeout, logged-and-swallowed failures, `wp_http_validate_url`. Error-isolation from SMS path is correct.
- `ConversationStore` retention purge is a single `DELETE` with `CASCADE` on messages — no load-into-memory pattern.
- `ToolExecutor::execute_read` uses `rest_do_request()` (not real HTTP) and wraps `wp_set_current_user` with save/restore. Clean internal dispatch.
- `MessageEndpoint` rate limit: 30 messages per user per minute via transient. Simple and correct.

## Adversarial challenge

Self-critique where the synthesis is weakest or might be overcalling severity.

- **MAJOR-02 severity is contingent on how descriptions are composed.** If `description` in practice is always generic ("Draft SMS for member" with no PII), then SMS leakage is theoretical. If it's "Refund order #1234 for member John Smith, $200 for late cancellation" then it's concrete. The reviewer flagged it as MAJOR without seeing real descriptions. Would be worth sampling 10-20 real pending actions to calibrate. I'm keeping it MAJOR because (a) the default behavior is "send whatever the description says", (b) there's no opt-out, and (c) SMS is non-recoverable once sent.
- **MAJOR-06 (sync SMS fan-out) and MAJOR-02 (SMS PII) are both against `ActionNotifier`.** These are independent — one is *what* goes over SMS, one is *how*. But the mitigation could be combined: one refactor of `send_sms` to async + metadata-only dispatch kills both.
- **MAJOR-01 (approve-with-changes re-pinning) relies on a specific threat model:** either Paperclip is compromised, or the agent's revised_data is manipulated in transit, or a staff click smuggles a different target. A bearer-secret replay-with-new-body attack matches this. The finding is correct; the exploitation path isn't trivial but it's not exotic either.
- **MAJOR-04 (apiFetch nonce) is only a latent failure for non-admin users and stale cookies.** For this project (admin-only "Gandalf" staff UI), the exploitation window is narrow. I'm keeping it MAJOR because the fix is one line and the failure mode is confusing ("works on my machine" → "breaks in prod") when it does hit.
- **MAJOR-07 (N+1 in audit log) has a mitigating factor the reviewer noted:** WP's per-request user cache makes first-page warm loads fast. Sustained scrolling re-hits warm cache. The "up to 20 queries" figure is a cold-cache worst case. I'm keeping MAJOR because it's a one-line fix with `_prime_user_caches()`.
- **Where I'd be open to downgrade:**
  - NIT-24 (prompt injection via context data) could easily be called MINOR. Conversely, in a future where Gandalf gets additional personas or tool types, it would creep to MAJOR.
  - MAJOR-05 (aria-live) is severity-debatable — it's the most impactful a11y gap in this plugin, but for a "staff only admin UI" with zero external users, the impact is bounded. I kept it MAJOR because the fix is three attributes on one element.
- **Findings I have highest confidence in:** MAJOR-01 (approve-with-changes re-pinning), MAJOR-03 (IP allowlist opt-out), MAJOR-04 (nonce middleware), MAJOR-06 (sync SMS), MINOR-12 (dead `execute_approved_write`). These have explicit file:line pointers and short, concrete fixes.

## Recommendation

**Address before any production use.** The two structural AI-safety gaps (MAJOR-01 and MAJOR-03) and the one latent frontend failure mode (MAJOR-04) are the critical pre-production remediations.

Suggested priority order:

1. **MAJOR-01** — re-pin action identity in `complete_revised_action`; verify `runId`. Foundational AI-safety.
2. **MAJOR-03** — "Enforce IP allowlist" toggle + pre-seed Paperclip egress IPs. Eliminate the silent-accept default.
3. **MAJOR-04** — one line: add `apiFetch.createNonceMiddleware(config.nonce)`.
4. **MAJOR-02 + MAJOR-06** combined — refactor `ActionNotifier::send_sms` to async-via-Action-Scheduler AND metadata-only. Fixes both in one change.
5. **MAJOR-07** — one-line `_prime_user_caches` + optional index bump.
6. **MAJOR-08** — ClaudeClient retry + user-message save ordering. Touches the chat hot path but is bounded.
7. **MAJOR-05** — three aria attributes. Land it alongside the other small frontend polish in MINOR-15/17, NIT-27.
8. The rest as a 0.5 polish wave.

With all eight MAJORs addressed, this plugin would be at clean-internal-plugin quality and reasonably close to marketplace-submittable for a hypothetical public AI-ops extension (though the Anthropic API-key storage model and gym-core coupling would need product decisions before any public ship).

---

*Slice reports retained at `_slices/hma-ai-chat-{security,standards,performance}.md` for audit.*
