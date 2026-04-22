# gym-core v1.0.0 — Marketplace-Style Review

**Plugin:** gym-core
**Version:** 1.0.0
**Scope:** Held to WooCommerce marketplace submission standards (security, WP+WC conformance, performance, architecture). Internal plugin, not a submission candidate.
**Environment:** WordPress 7.0 / WooCommerce 10.3 / PHP 8.0+ / HPOS / Cart+Checkout Blocks required.
**Method:** Three parallel reviewer slices (security, standards, performance) with main-session inventory pre-gathered and synthesis here. Raw slices committed under `_slices/gym-core-*.md` for audit.

## Executive Summary

Gym Core is a well-architected plugin that largely meets marketplace standards today. Security posture is solid — all `$wpdb` calls are prepared, every REST route carries a real permission callback, admin templates consistently escape, and the Twilio webhook uses HMAC-SHA1 with `hash_equals()`. Standards conformance is clean: HPOS and Blocks compatibility are declared correctly, no order code reaches for `get_post_meta()`, text domain and prefix discipline hold. The binding constraint is **performance**: four concrete N+1 patterns in REST controllers, almost-zero use of the WP object cache outside `Location\Taxonomy`, and one unprepared (SQL-safe but fragile) pipeline query.

**Severity counts:** blocker=0, major=4, minor=11, nit=7

**Recommendation:** **Address before next release.** No blockers. The MAJOR findings are all perf, concentrated in three controllers and one CRM query; each has a concrete fix with a named remediation pattern already present elsewhere in the plugin. The MINORs and NITs can be tackled opportunistically.

## Findings

### MAJOR — Performance (4)

#### MAJOR-01: N+1 in `ClassRosterController::enrich_roster` via `get_user_history` per student
- **File:** `src/API/ClassRosterController.php:225`
- **Issue:** Per-student `$this->attendance->get_user_history( $user_id, 1, 0 )` inside the enrichment loop. For a 30-student class, 30 additional queries against `{prefix}gym_attendance` on top of the rank-enrichment loop. Scales linearly with roster size.
- **Fix:** Add `AttendanceStore::get_last_attended_for_users( array $user_ids ): array<int,string>` that runs one `SELECT user_id, MAX(checked_in_at) … WHERE user_id IN (…) GROUP BY user_id` and look up inside the loop.

#### MAJOR-02: N+1 in `ProspectFilter::filter_prospects`
- **File:** `src/Sales/ProspectFilter.php:95-115`
- **Issue:** `get_user_by('email')` and `wcs_get_users_subscriptions()` called once per contact. The controller over-fetches `$per_page + 10` rows, amplifying the fan-out. Per-user transient (`gym_prospect_$user_id`) only mitigates warm cache.
- **Fix:** Before `array_filter`, collect emails → one `SELECT ID, user_email FROM {$wpdb->users} WHERE user_email IN (…)` for the email→ID map, then batch-prime the subscription lookups.

#### MAJOR-03: N+1 in `ClassScheduleController::get_schedule` — no cache priming
- **File:** `src/API/ClassScheduleController.php:250-282` (helpers at 315-347)
- **Issue:** `/gym/v1/schedule?location=…` runs `posts_per_page => -1`, loops through each class per day, calls `get_post_meta` 5×, `get_the_terms()` for program + location, and `get_userdata()` for the instructor. Unlike `MemberController::build_upcoming_classes_section` which primes meta/term/user caches (lines 386-403), this controller primes nothing.
- **Fix:** After `$query = new \WP_Query( $args );` call `update_meta_cache('post', $post_ids)`, `update_object_term_cache($post_ids, ClassPostType::POST_TYPE)`, and `cache_users( $instructor_ids )` — the same pattern MemberController already proves. Extract a `prime_class_caches( WP_Query $q )` helper for reuse.

#### MAJOR-04: Unprepared, unpaginated `$wpdb` query on CRM pipeline aggregation
- **File:** `src/API/CrmController.php:294-300`
- **Issue:** `SELECT zbsc_status, COUNT(*) … GROUP BY zbsc_status` with no WHERE, no LIMIT, no cache. Called on every AI-agent pipeline read. The interpolated SQL skips `$wpdb->prepare()` entirely — acceptable because there's zero user input, but fragile and uncached. SQL injection not possible; scan cost grows with `zbs_contacts` row count.
- **Fix:** Wrap in `wp_cache_get/set( 'gym_crm_pipeline', …, 'gym_core', 60 )`. Very low effort, substantial payoff because AI agents read pipeline counts repeatedly.

### MINOR (11)

#### Security (2)
- **MINOR-05: `rest_pre_serve_request` XML bypass is broader than the Twilio webhook route.** `src/SMS/InboundHandler.php:77` matches any REST response whose `Content-Type` is XML — any future plugin route returning XML would be emitted raw through this filter. Low exploitability today; narrow the filter to `$request->get_route() === '/gym/v1/sms/webhook'` as defense-in-depth.
- **MINOR-06: Kiosk cookie-trusted location not validated against taxonomy allowlist.** `src/Sales/KioskEndpoint.php:396-401` reads `$_COOKIE['gym_location']` through `sanitize_text_field()` only, without `Taxonomy::is_valid()`. `SalesController::get_products` re-validates downstream so no exploit exists, but the invalid value ends up in rendered `data-location`. Add allowlist check immediately after sanitization, fall back to default on mismatch.

#### Standards (3)
- **MINOR-07: Unspaced `declare(strict_types=1);` in two files.** `src/Admin/CrmWhiteLabel.php:8`, `src/Admin/MenuManager.php:8` — the rest of the codebase uses the WPCS-preferred spaced form (`declare( strict_types=1 );`). Purely a formatting inconsistency.
- **MINOR-08: Nonce action names mix `gym_*` and `gym_core_*` prefixes.** Hook names are uniformly `gym_core_*` but several nonces (`gym_class_meta`, `gym_announcement_meta`, `gym_quick_checkin`, etc.) use the shorter form. No collision risk, but marketplace reviewers prefer a single prefix for grep-ability.
- **MINOR-09: `bulk-promotions` nonce action is unprefixed.** `src/Admin/PromotionDashboard.php:302,355` — mirrors WP core's `bulk-{plural}` pattern, but this isn't hooked into a core list table, so it should be `gym_core_bulk_promotions`.

#### Performance (6)
- **MINOR-10: `MemberController::build_upcoming_classes_section` calls `get_the_terms()` inside a nested day/class loop.** `src/API/MemberController.php:422` — term cache is primed at line 389 so the DB round-trip is saved, but the PHP work still repeats per iteration. Build a `$post_id => $program_slug` map once before nesting.
- **MINOR-11: `MemberController::build_billing_section` re-fetches customer tokens per subscription.** `src/API/MemberController.php:316-325` — `WC_Payment_Tokens::get_customer_tokens( $user_id )` returns the same set each call; memoize per `$user_id` within the method.
- **MINOR-12: `posts_per_page => -1` in public-facing endpoints.** `ClassScheduleController`, `MemberController`, `BriefingGenerator`, `ICalFeed` (public, unauthenticated), `StaffDashboard`, `AnnouncementPostType`. Cap at a defensive bound (500, 200 for ICal) so a hot-path cliff surfaces as an error instead of silent slowdown.
- **MINOR-13: `MilestoneTracker::check_milestones` synchronous on every check-in.** `src/Attendance/MilestoneTracker.php:63-95` — mirrors the work `BadgeEngine` correctly defers via `as_enqueue_async_action`. Apply the same async pattern.
- **MINOR-14: `PromotionNotifier` always constructs `TwilioClient` even when SMS is disabled.** `src/Plugin.php:421-425`. Short-circuit on `'yes' !== get_option( 'gym_core_sms_enabled' )` or register one shared `TwilioClient` on the Plugin singleton.
- **MINOR-15: Missing object-cache layer on `RankStore::get_rank` and `AttendanceStore::get_total_count`.** These are called 3–5× per dashboard request. Wrap with `wp_cache_get/set` in group `gym_core_ranks` / `gym_core_attendance`, invalidate from `RankStore::promote()` and `record_checkin()`. **Single highest-ROI cache addition in the plugin.**

### NIT (7)

- **NIT-16: `AttendanceStore::get_user_history` builds `$where` by concatenating pre-prepared fragments.** Safe today; future edits could regress into direct interpolation. Refactor to a single `$wpdb->prepare( $sql_with_placeholders, $args )` pass.
- **NIT-17: Twilio `TwilioClient` has a hardcoded 15s timeout and no 429/5xx retry classification.** Inside an Action Scheduler worker, a 15s hang on a failed send ties up the worker. Drop to 10s, branch on response code, and add 2-attempt backoff on 429/503.
- **NIT-18: `composer.json` has no `classmap`.** PSR-4 is clean across spot-checked files; verify new `src/CLI/` additions stay namespaced.
- **NIT-19: CRM `status` query param accepted as an arbitrary string (SQL-safe via `%s`).** Optional: allowlist to the known Jetpack CRM status set via `sanitize_callback`.
- **NIT-20: `SMSController::get_conversation_history` uses interpolated-then-prepared SQL pattern.** Brittle for future edits; refactor to pure placeholder-style.
- **NIT-21: Twilio webhook URL reconstruction trusts `X-Forwarded-Proto`.** DoS-only edge case (can force signature mismatch, not forge). Fix: set `gym_core_twilio_webhook_url` explicitly in prod.
- **NIT-22: Audit trails acknowledge Twilio signature validation, destructive action capability gates, and the `pay_for_order` capability escalation are all correctly scoped** — see Verified-Clean below.

## Verified Clean (from slice audits)

**Security**
- All 17 `$wpdb` callers use `prepare()` with proper placeholders. Table names from `$wpdb->prefix`.
- `AttendanceDashboard::prepare_query` allowlists `ORDER BY` columns and directions before interpolating — safe.
- Twilio webhook: HMAC-SHA1, sorted params, `hash_equals()`, 403 on missing token/signature.
- Twilio credentials prefer `GYM_CORE_TWILIO_*` constants over options; settings field is `type="password"`.
- REST permission callbacks spot-checked (5/46) all perform real capability enforcement.
- All destructive actions (bulk promote, refund, Foundations clear, staff pay-for-order) gated by specific capability, not "logged-in".
- `pay_for_order` capability filter is narrowly scoped to orders carrying `gym_kiosk_origin` meta.
- Admin template escaping consistent across AttendanceDashboard, PromotionDashboard, StaffDashboard, OrderLocation, Settings.
- Inbound SMS body stripped via `wp_kses( …, array() )` before storage — no stored-XSS.

**Standards**
- HPOS feature declaration: `FeaturesUtil::declare_compatibility( 'custom_order_tables', GYM_CORE_FILE, true )` on `before_woocommerce_init` (gym-core.php:47-66).
- Blocks feature declaration: `'cart_checkout_blocks'` in the same block.
- No `get_post_meta()` on order IDs anywhere. `OrderLocation` uses `$order->update_meta_data()` / `get_meta()` / `save_meta_data()`. `OrderController`, `StaffDashboard`, `FormToCrm`, `Sales/KioskEndpoint` use `wc_get_orders()`, `wc_get_order()`, `wc_create_refund()`.
- Order-list admin filter hooks both legacy (`restrict_manage_posts` + `request`) AND HPOS (`woocommerce_order_list_table_restrict_manage_orders` + `woocommerce_orders_table_query_clauses`).
- `StoreApiExtension` uses the public `woocommerce_store_api_register_endpoint_data` helper with a `function_exists()` guard; extends cart + checkout with a namespaced `gym-core` key.
- `BlockIntegration` implements `IntegrationInterface` under `woocommerce_blocks_checkout_block_registration`.
- 70 of 72 source files declare `strict_types=1` (other 2 differ only in spacing — MINOR-07).
- PSR-4 autoloader maps `Gym_Core\\` → `src/`; `vendor/autoload.php` required in `gym-core.php:37-39`.
- Activator guards on `activate_plugins`, seeds taxonomy, schedules crons. Deactivator unschedules crons. Uninstall.php guards on `WP_UNINSTALL_PLUGIN`, drops custom tables.
- `phpcs.xml.dist` extends `WordPress-Extra` + `WordPress-Docs`, pins text domain to `gym-core`, targets PHP 8.0+. PHPStan level 6 configured via `phpstan-wordpress` extension.
- Option keys prefixed `gym_core_*`, order meta keys prefixed `_gym_*`. REST namespace `gym/v1` centralized in `BaseController::REST_NAMESPACE`.

**Performance / architecture**
- DI composition root in `Plugin.php:263-349` constructs each service once and injects into consumers — not per-request new-up.
- BadgeEngine async boundary via `as_enqueue_async_action` with `as_has_scheduled_action` dedup and sync fallback.
- AttendanceController primes post caches via `_prime_post_caches( $class_ids, false, false )`.
- MemberController dashboard primes meta + term + user caches — textbook, and the pattern MAJOR-03 needs.
- `Location\Taxonomy` label cache: 300s TTL, `gym_core` group, fallback on miss. Good model.
- `RankStore::promote()` wraps replace + insert in an explicit `START TRANSACTION` / `COMMIT` / `ROLLBACK`.
- 24 unit-test files covering every REST controller, both stores' adjacent validators, gamification, SMS templates, location module.

## Adversarial challenge

Self-critique of the findings above — where the synthesis is weakest or where findings might be false positives.

- **MAJOR-03 / MAJOR-04 double-count.** Both hit the schedule + pipeline paths. They're independent (one is N+1 term/meta lookups; one is an aggregate `COUNT(*)`), but a single fix — adding `update_meta_cache` + `wp_cache_*` broadly — would knock out multiple findings at once. The numbered severity shouldn't mislead on unique-fix count.
- **MAJOR N+1 findings rest on assumed row counts.** A roster of 6 students makes MAJOR-01 negligible in absolute terms; a roster of 60 makes it painful. For a 2-location dojo with small classes today, the perf ceiling is high. The findings remain valid because the fixes are cheap; the rationale for severity might be "future-proofing" rather than "prod is on fire".
- **MINOR-08 / MINOR-09 (nonce prefix consistency) could be dropped** if you prefer to keep the existing naming. The existing names don't collide and don't leak. This is purely stylistic and marketplace-reviewer-aesthetic.
- **Standards slice flagged "WP 7.0 is probably a typo" as a nit** — that is a false positive. This project's CLAUDE.md explicitly requires WP 7.0. I dropped that nit from the synthesis.
- **The security slice's withdrawn finding (OrderLocation label not escaped) was a good catch on re-read** — the agent initially flagged it, then verified on second read that both `%s` are properly `esc_html()`-wrapped. That self-correction is the right pattern.
- **Sharp findings I have high confidence in:** all four MAJORs, the cookie-trusted location (MINOR-06), and the XML bypass filter scope (MINOR-05). These have specific file:line pointers and concrete, local fixes.
- **Where to be cautious:** I did not independently re-verify each nit. The slice reviewers are careful but a fresh look at NIT-17 (TwilioClient timeout + retry) might be better treated as MINOR given Twilio flaps are real and a 15s hang ties up Action Scheduler workers.

## Recommendation

**Address before next release.** No blockers; no majors that threaten correctness or security. Priority order:

1. **MAJOR-04** (CRM pipeline cache) — fastest, highest-value.
2. **MINOR-15** (Rank + attendance cache layer) — same pattern, high ROI, the best single structural change.
3. **MAJOR-03** (schedule controller cache priming) — mirrors MemberController pattern already in-plugin.
4. **MAJOR-01 / MAJOR-02** (roster + prospect N+1) — require new batch methods.
5. Everything else can land in the 1.1 polish wave.

The plugin is close to marketplace-ready by structural measures. If Andrew ever wanted to ship parts of this as a partner-facing product, the remediation list is tractable.

---

*Slice reports retained at `_slices/gym-core-{security,standards,performance}.md` for audit.*
