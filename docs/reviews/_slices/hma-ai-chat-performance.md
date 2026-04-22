# hma-ai-chat v0.4.0 — Performance + Architecture Slice

## Summary

- BLOCKER: 0
- MAJOR: 3
- MINOR: 4
- NIT: 2

Scope: performance, caching, DI, error handling, cron, outbound HTTP isolation. Not in scope: security, coding standards, AI-safety, marketplace.

## Findings

### MAJOR: Synchronous SMS fan-out blocks the action-creation request
- **File:** `src/Notifications/ActionNotifier.php:148-158`
- **Category:** SMS-block / HTTP-error
- **Issue:** `send_sms()` loops through every configured admin number and calls `$client->send( $to, $body )` inline. `Gym_Core\SMS\TwilioClient::send()` is a synchronous `wp_remote_post()` with a **15-second timeout** (`gym-core/src/SMS/TwilioClient.php:81`). With 3-5 admin numbers configured, a Twilio outage or slow network can tack on **45-75s of blocking time** to whatever request triggered `hma_ai_chat_pending_action_created` — which fires inline from `PendingActionStore::store_pending_action()` (`PendingActionStore.php:69`). That path is hit from the REST MessageEndpoint (via ToolExecutor), HeartbeatEndpoint (Paperclip webhooks), and the admin bulk-action endpoint. All three get slowed down.
- **Contradiction signal:** The TwilioClient docblock claims "Sending is queued via Action Scheduler for rate limiting and retry" but the code path actually called here is the synchronous `send()`, not a queued wrapper.
- **Fix:** Defer SMS dispatch via Action Scheduler (`as_enqueue_async_action( 'hma_ai_chat_send_sms', [...] )`) or `wp_schedule_single_event( time(), ... )`. At minimum, capture the total loop time and short-circuit if it exceeds a budget (e.g. 5s). The Slack send already uses a tight 5s timeout — apply the same pattern plus async dispatch for SMS.

### MAJOR: N+1 `get_userdata()` lookups in audit log rendering
- **File:** `src/Admin/AuditLogPage.php:146` and `src/API/ActionEndpoint.php:576`
- **Category:** N+1
- **Issue:** Both the admin page and the REST audit log call `get_userdata( (int) $item['approved_by'] )` inside a `foreach` over items (20 per page). WordPress core does cache `WP_User` objects per request, so this is not catastrophic, but on first page load after a long gap it results in up to **20 individual user-meta queries** (core issues 2 queries per uncached user fetch). The list page also has no index hints — `get_all_actions` orders by `created_at DESC` and filters by `status`/`agent`; `created_at` is not obviously indexed on the table.
- **Fix:** Collect distinct `approved_by` IDs first, call `_prime_user_caches( $user_ids )` once, then the per-row `get_userdata()` calls become cache hits. Same pattern in ActionEndpoint::get_audit_log. Separately, add an index on `(status, created_at)` to the `hma_ai_pending_actions` table via the Activator's schema if missing.

### MAJOR: ClaudeClient has no retry or error classification on 429/5xx
- **File:** `src/API/ClaudeClient.php:82-110`
- **Category:** HTTP-error
- **Issue:** The user-facing chat hot path (MessageEndpoint) does a synchronous `wp_remote_post()` to Anthropic with a 60s timeout. On HTTP 429 (rate limit) or 5xx the client wraps the response into a `WP_Error` with status 502 and returns — but the user's message was already persisted to the DB via `save_message()` at line 133 before the Claude call. That means the user sees an error, the message is saved, but there's no assistant reply — the next message the user sends rewalks the history and re-pays the token cost of the un-replied prompt. No exponential backoff for transient 429s either.
- **Fix:** (1) Add one or two retries on 429/503 with jittered backoff (respect `retry-after` header if present). (2) If the Claude call ultimately fails, either roll back the just-saved user message or mark it with a `failed: true` flag so the UI can offer "retry" without re-inserting.

### MINOR: ActionEndpoint creates a new `PendingActionStore` per callback (7 methods)
- **File:** `src/API/ActionEndpoint.php:275, 293, 347, 409, 462, 508, 563`
- **Category:** DI
- **Issue:** Every REST callback instantiates its own `PendingActionStore`. Same pattern in `MessageEndpoint` for `ConversationStore` and `GymContextProvider`, and in `Plugin::run_conversation_purge`. Stores are stateless so this is not a correctness bug, but it reflects the absence of a DI container — `Plugin::register_hooks()` does construct a `PendingActionStore` at line 129 and hands it to `ToolExecutor`, then throws it away for everyone else. Service locator / container would also make the audit log N+1 fix and future caching easier.
- **Fix:** Either cache singletons on `Plugin` (same pattern as `$tool_executor`) or introduce a small service container. Low urgency but improves testability once unit tests are added.

### MINOR: GymContextProvider hot-path cost on every chat message
- **File:** `src/API/MessageEndpoint.php:145-146`, `src/Context/GymContextProvider.php:53`
- **Category:** Cache
- **Issue:** On every chat message, `GymContextProvider::get_context_for_persona()` runs 4-8 internal REST dispatches (pricing, schedule, pipeline, recent leads, rosters, etc.). The class defines `CACHE_TTL = 300` but I only spot-checked the dispatch path; without reading every `get_*_context()` method I cannot confirm each one honours the cache. If a single method skips caching, every user message pays that DB cost. The context is also built before the rate-limit check returns successfully, so even a user who gets rate-limited doesn't avoid the work (wait — re-read: rate limit check is at line 77-81 before context builds at 145. That's actually fine.).
- **Fix:** Verify each `get_*_context()` method writes through `wp_cache_set()` with `CACHE_GROUP`/`CACHE_TTL`. Add a wrapper helper `cached_dispatch( string $key, callable $builder )` so no individual method can forget. Consider persona-level memoization keyed by `(persona, user_id)` covering a whole turn.

### MINOR: Claude API failure mode saves user message but not assistant response
- **File:** `src/API/MessageEndpoint.php:117-181`
- **Category:** HTTP-error
- **Issue:** See the ClaudeClient MAJOR above — the user-message insert happens before the Claude call, and there's no rollback on failure. This produces an orphan user-turn row in the conversation. The next request re-walks history and re-sends the orphan to Claude, double-billing tokens.
- **Fix:** Either insert the user message after a successful Claude reply (with the assistant response in the same transaction), or mark it as `pending` and only flip to `committed` once the assistant reply lands.

### MINOR: MessageEndpoint lacks request-level timeout/budget
- **File:** `src/API/MessageEndpoint.php:74-199`
- **Category:** DI / error isolation
- **Issue:** The handler is bounded only by ClaudeClient's 60s timeout plus PHP `max_execution_time`. A user could queue several simultaneous messages and each worker stays busy up to 60s — default PHP-FPM pool gets exhausted quickly. Rate limit of 30/minute per user mitigates but doesn't eliminate.
- **Fix:** Wrap the outbound call in a `wp_remote_post` timeout that scales with `ini_get('max_execution_time')`. Consider a queue + streaming response pattern for v0.5.

### NIT: No unit tests, critical untested paths
- **File:** repo-wide
- **Category:** Tests
- **Issue:** Zero unit tests confirmed (`phpunit.xml.dist` absent). Critical paths with no coverage: `ToolExecutor::execute_read` (internal REST dispatch + user switching), `PendingActionStore::get_all_actions` (SQL builder with dynamic WHERE), `ActionNotifier::send_sms` (loop + error isolation), `ConversationStore::purge_expired_conversations` (cascading delete), `ClaudeClient::send` (API error mapping).
- **Fix:** Start with `tests/unit/` covering PendingActionStore SQL and ToolExecutor::resolve_route. These are the highest-leverage targets: pure PHP, no WP bootstrap needed beyond wpdb mocks.

### NIT: ConversationStore purge cutoff uses string `updated_at < %s` without index guarantee
- **File:** `src/Data/ConversationStore.php:207-212`
- **Category:** Purge
- **Issue:** The daily purge does `DELETE FROM ... WHERE updated_at < %s`. Correctness is fine and the CASCADE on messages is elegant. But `updated_at` is not confirmed indexed on `hma_ai_conversations` (Activator not inspected in this slice). On a site with years of conversation history, a non-indexed scan is the kind of thing that silently degrades.
- **Fix:** Confirm `KEY updated_at (updated_at)` is in the Activator's `dbDelta` schema. If not, add it in a 0.4.1 schema bump.

## Verified-Clean

- **Deactivator unschedules cron** — `Deactivator::clear_scheduled_events()` calls `wp_clear_scheduled_hook( Plugin::PURGE_CRON_HOOK )`. Correctly wired via `register_deactivation_hook` in `hma-ai-chat.php:108`. Cron registration is also idempotent: `Plugin::init()` checks `wp_next_scheduled()` before scheduling.
- **PendingActionStore::get_pending_count caching** — Correctly uses `wp_cache_get`/`wp_cache_set` with a 60s TTL, and all writers (`store_pending_action`, `approve_action`, `approve_with_changes`, `reject_action`) invalidate via `wp_cache_delete( 'hma_ai_pending_count' )`. Verified clean.
- **ActionNotifier Slack send** — tight 5s timeout, failure logged and swallowed, uses `wp_http_validate_url()` on the webhook URL, independent of SMS path. Error isolation correct.
- **ConversationStore retention purge scope** — single `DELETE` with `CASCADE` on messages; no load-into-memory-then-loop pattern. Clean.
- **ToolExecutor read-path overhead** — uses `rest_do_request()` instead of real HTTP, correctly wraps `wp_set_current_user` with save/restore. Clean internal dispatch.
- **MessageEndpoint rate limit** — 30 messages per user per minute via transient. Simple and correct; prevents a single user from exhausting the Anthropic budget.
