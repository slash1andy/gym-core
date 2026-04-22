# gym-core v1.0.0 — Performance + Architecture Slice

## Summary

Architecture is sound: a single Plugin singleton composes concrete services once per request and passes them into controllers (genuine constructor DI, not per-request re-instantiation). Route-level p95 of 150–250ms is plausible given the SQL patterns seen. The weak spots are (1) a handful of REST paths that still do per-item DB calls inside loops, (2) almost zero use of the WP object cache outside `Location\Taxonomy`, (3) several WP_Query calls with `posts_per_page => -1`, and (4) one unprepared `$wpdb` query on the CRM pipeline. Severity counts: 0 BLOCKER, 4 MAJOR, 6 MINOR, 3 NIT.

## Findings

### MAJOR: N+1 query in `ClassRosterController::enrich_roster` via `get_user_history` per user
- **File:** `src/API/ClassRosterController.php:225`
- **Category:** N+1
- **Issue:** The roster loop runs `$this->attendance->get_user_history( $user_id, 1, 0 )` for every forecasted student. Each call hits the `{prefix}gym_attendance` table individually. For a 30-student class this is 30 extra queries on top of the enriched-rank loop, and it scales linearly with roster size. `cache_users()` is primed at line 200 but nothing amortizes the last-attended lookup.
- **Fix:** Add an `AttendanceStore::get_last_attended_for_users( array $user_ids ): array<int,string>` method that does one `SELECT user_id, MAX(checked_in_at) … WHERE user_id IN (…) GROUP BY user_id` and look up from the resulting map inside the loop.

### MAJOR: N+1 via `get_user_by('email')` + `wcs_get_users_subscriptions` in `ProspectFilter::filter_prospects`
- **File:** `src/Sales/ProspectFilter.php:95-115`
- **Category:** N+1
- **Issue:** When `CrmController::get_contacts` is called with `prospects_only=true`, every contact in the page is run through `get_user_by( 'email', … )` (one query per contact) and then `wcs_get_users_subscriptions()` (another query per contact that actually resolves). The per-user transient (`gym_prospect_$user_id`) only helps on *repeat* visits — a single request for a page of 20 contacts fans out to ~40 queries for a cold cache.
- **Fix:** Before the `array_filter`, pull all emails, run a single `SELECT ID, user_email FROM {$wpdb->users} WHERE user_email IN (…)` to map email → user_id, then batch the subscription lookups (or at least prime all transients up front). Also, because the controller intentionally over-fetches `$per_page + 10` and then `array_slice`s, the filter can run on throwaway rows — the over-fetch amplifies the N+1.

### MAJOR: N+1 per class in `ClassScheduleController::get_schedule` (no cache priming)
- **File:** `src/API/ClassScheduleController.php:250-282` (plus helpers at 315-347)
- **Category:** N+1
- **Issue:** `/gym/v1/schedule?location=…` runs `posts_per_page => -1`, then for every class instance across 7 days loops again and calls `get_post_meta` (5x) plus `get_the_terms(gym_program)`, `get_the_terms(gym_location)`, and `get_userdata( instructor_id )` per class per matching day. Unlike `MemberController::build_upcoming_classes_section` (which primes `update_meta_cache`, `update_object_term_cache`, and `cache_users`), this controller does no priming. Even with object cache, `get_userdata` on a cold request is one DB hit per instructor.
- **Fix:** After `$query = new \WP_Query( $args );`, call `update_meta_cache('post', $post_ids)`, `update_object_term_cache($post_ids, ClassPostType::POST_TYPE)`, and `cache_users( $instructor_ids )` — the exact pattern already proven in `MemberController.php:386-403`. Extract a small `prime_class_caches( WP_Query $q )` helper so both controllers share it.

### MAJOR: Unprepared, unpaginated `$wpdb` query on CRM pipeline
- **File:** `src/API/CrmController.php:294-300`
- **Category:** Unpaginated
- **Issue:** `get_pipeline()` runs `SELECT zbsc_status, COUNT(*) … GROUP BY zbsc_status` against `zbs_contacts` with no `WHERE`, no `LIMIT`, and no cache. For a fast-growing gym this is fine today, but it is called on every AI-agent pipeline read. Independently, the interpolated SQL passes phpcs ignore comments but skips `$wpdb->prepare()` entirely — acceptable because there is zero user input, but worth a short-lived `wp_cache_set` with a 30–60s TTL since pipeline counts drive the AI agent's hot path. Security slice: not a SQLi, but fragile.
- **Fix:** Wrap in `wp_cache_get/set( 'gym_crm_pipeline', … , 'gym_core', 60 )`. Accept the noise.

### MINOR: `MemberController::build_upcoming_classes_section` still does N+1 `get_the_terms( gym_program )` inside day/class nested loop
- **File:** `src/API/MemberController.php:422`
- **Category:** N+1
- **Issue:** The code primes `update_object_term_cache` on line 389, so `get_the_terms` should be served from cache — but `get_the_terms` returns a different memoized structure than `update_object_term_cache`, and for posts that have the term cache primed this still issues one lookup per post per iteration (the outer `foreach $days` × inner `foreach $query->posts`). Because a class matches one day, the terms are effectively fetched N times (once per day loop iteration). The priming call saves the DB round-trip but still costs PHP work.
- **Fix:** Move `get_the_terms` out of the inner loop. Build a `$post_id => $program_slug` map once before the day/class nesting.

### MINOR: `MemberController::build_billing_section` issues one query per token via `WC_Payment_Tokens::get_customer_tokens` inside an `if ( token_id )` loop
- **File:** `src/API/MemberController.php:316-325`
- **Category:** N+1 / Cache
- **Issue:** For each subscription with a `_payment_tokens` meta, the code calls `WC_Payment_Tokens::get_customer_tokens( $user_id )` and iterates to find a match. The method is called once per subscription in the same request even though it returns the same per-user token set each time. For a single active subscription this is fine; for accounts with multiple subs it compounds.
- **Fix:** Cache the tokens array in a local variable keyed by `$user_id` within the method, or call it once before the loop.

### MINOR: `posts_per_page => -1` in user-facing REST endpoints
- **File:** `src/API/ClassScheduleController.php:220`, `src/API/MemberController.php:364`, `src/Briefing/BriefingGenerator.php:143`, `src/Schedule/ICalFeed.php:215`, `src/Member/MemberDashboard.php` (`get_upcoming_classes` uses `20` which is safe), `src/Admin/StaffDashboard.php:517`, `src/Briefing/AnnouncementPostType.php:374`
- **Category:** Unpaginated
- **Issue:** Each of these assumes a bounded class/announcement universe (one location, one week, one program). That is true today for a two-location dojo. It stops being true the first time a franchise owner stands up a third or fourth location, or a long-running announcement CPT accumulates a few hundred rows. For `ICalFeed` in particular, public consumers can hit this without auth.
- **Fix:** Cap at a defensive `posts_per_page => 500` (or 200 for ICal feeds). If a real store hits the cap, it's a signal to redesign rather than a quiet perf cliff.

### MINOR: `MilestoneTracker::check_milestones` runs synchronously on every `gym_core_attendance_recorded`
- **File:** `src/Attendance/MilestoneTracker.php:63-95`
- **Category:** Async
- **Issue:** `BadgeEngine` was specifically moved off the synchronous check-in path via `as_enqueue_async_action` (see `BadgeEngine::schedule_checkin_evaluation`). `MilestoneTracker` does the same kind of work — `get_total_count()` (a `COUNT(*)` against `gym_attendance`) plus a user_meta read — on the same hook, but synchronously at priority 20. That partially defeats the point of deferring badge evaluation.
- **Fix:** Mirror the BadgeEngine pattern. Schedule an async action with `as_enqueue_async_action( 'gym_core_async_check_milestones', [ $user_id ], 'gym-core' )` guarded by `as_has_scheduled_action` for dedup. Fall back to sync if Action Scheduler is not present.

### MINOR: `PromotionNotifier` always constructs a `TwilioClient` even when SMS is disabled
- **File:** `src/Plugin.php:421-425`
- **Category:** DI
- **Issue:** `register_notification_modules()` runs on every admin/frontend request and unconditionally does `new SMS\TwilioClient()`. `register_api_modules()` also constructs a `TwilioClient` behind the `gym_core_sms_enabled` option. When the option is off, the notifier's TwilioClient is dead weight (plus it means every `get_option( 'gym_core_twilio_*' )` call it might trigger still happens). Additionally, two separate `TwilioClient` instances exist per request — fine because the class is stateless, but a missed DI opportunity.
- **Fix:** Register one shared `TwilioClient` as a Plugin property (like `$attendance_store`), pass it to both `PromotionNotifier` and `SMSController`. Skip instantiation entirely when `'yes' !== get_option( 'gym_core_sms_enabled', 'no' )` — or short-circuit `PromotionNotifier::handle_rank_changed` on the same option.

### MINOR: Missing object-cache layer around `RankStore::get_rank` and `AttendanceStore::get_total_count`
- **File:** `src/Rank/RankStore.php:33`, `src/Attendance/AttendanceStore.php:155`
- **Category:** Cache
- **Issue:** Per CLAUDE.md's audit, only `Location\Taxonomy` uses `wp_cache_*`. In a typical dashboard request, `get_rank( $user_id, 'adult-bjj' )` is called 3–5 times (MemberController, TargetedContent shortcode, ClassRosterController, BriefingGenerator). Each call is a prepared `SELECT … WHERE user_id=%d AND program=%s`. Same for `get_total_count` which is called by MilestoneTracker, BadgeEngine, and MemberController on a single check-in. With persistent object cache (Redis / Memcached in production), these are free; without it (default), they add up.
- **Fix:** Wrap `get_rank` and `get_all_ranks` with `wp_cache_get/set` in group `gym_core_ranks`, invalidated in `promote()` (add `wp_cache_delete` after the transaction commits). Same pattern for `get_total_count()`, invalidated in `record_checkin()`. TTLs can be generous (ranks change rarely). This is the single highest-ROI cache addition in the plugin.

### NIT: `$wpdb->get_results( $sql )` with interpolated `$where` in `AttendanceStore::get_user_history`
- **File:** `src/Attendance/AttendanceStore.php:115-143`
- **Category:** N+1 (tangential)
- **Issue:** The method builds `$where` by concatenating pre-prepared fragments, which works but is brittle — each `$wpdb->prepare()` call returns a string that's then passed into another `$wpdb->get_results( $sql )` not wrapped in `prepare`. This is safe here because every fragment is already prepared, but PHPCS disables `WordPress.DB.PreparedSQL.NotPrepared` to allow it. Future contributors are one mistake away from an injection. Functionally: fine.
- **Fix:** Refactor to build an `$args` array and a placeholder list, then single `$wpdb->prepare( $sql_with_placeholders, $args )`. No perf change.

### NIT: No shared `TwilioClient` timeout constant / no explicit retry policy
- **File:** `src/SMS/TwilioClient.php:70-83`
- **Category:** (Outbound HTTP)
- **Issue:** `wp_remote_post` has `'timeout' => 15` — reasonable but hardcoded. No retry on 429 / 5xx. No circuit breaker. Failures surface to the caller as a single `{ success: false, error: … }` with no visibility into whether Twilio rejected or the request timed out. Since the send is enqueued via Action Scheduler per the docblock ("Sending is queued via Action Scheduler"), a retry policy lives in AS — but a 15s timeout on a blocking HTTP call inside an AS worker still ties up a worker for 15s on every failed send.
- **Fix:** Drop timeout to `10`, add a `wp_remote_retrieve_response_code` branch that returns a distinct `{ error_code: 'twilio_rate_limited' | 'twilio_auth' | 'twilio_server' }` so AS can decide whether to retry. Consider a 2-attempt retry loop on 429/503 with exponential backoff before reporting failure.

### NIT: `composer.json` does not declare `classmap`; autoloader relies solely on PSR-4
- **File:** `composer.json:22-31`
- **Category:** Autoloader
- **Issue:** Spot-checked ~25 `src/` files — all have `namespace Gym_Core\…` matching directory, so PSR-4 maps cleanly. No stray classes outside the namespace root. This is clean and correct. Noting it only because reviewers sometimes expect a classmap for templates/ and CLI/. `src/CLI/` files need to be spot-verified: they should be conditionally loaded (WP-CLI context only) rather than mapped.
- **Fix:** None required. Verify `src/CLI/` classes namespace-match when you add more.

## Verified-Clean

- **DI composition root** (`src/Plugin.php:263-349`). Singleton creates each service once and injects it into every consumer. Controllers receive `AttendanceStore` / `RankStore` / `StreakTracker` by constructor, not by re-instantiation. Hook registration is correctly deferred to `gym_core_loaded` and `rest_api_init` so route registration doesn't fire on every non-REST request. This is exactly the pattern WordPress plugin perf reviews want to see.
- **BadgeEngine async boundary** (`src/Gamification/BadgeEngine.php:86-101`). Check-in-path badge evaluation is correctly deferred via `as_enqueue_async_action` with `as_has_scheduled_action` dedup and a graceful sync fallback. Promotion badges stay synchronous (correct — they're rare).
- **AttendanceController history endpoint primes post caches** (`src/API/AttendanceController.php:276-288`). Uses `_prime_post_caches( $class_ids, false, false )` before the `array_map`. Textbook.
- **MemberController dashboard primes meta + terms + users** (`src/API/MemberController.php:386-403`). The very pattern other controllers are missing.
- **Location\Taxonomy label cache** (`src/Location/Taxonomy.php:64-85`). The one `wp_cache_*` path in the plugin is correctly scoped: short TTL (300s), sensible cache group (`gym_core`), fallback to a direct `get_terms` call on miss. Good model for the rest of the codebase.
- **RankStore::promote uses an explicit SQL transaction** with START TRANSACTION / ROLLBACK / COMMIT around the `replace` + `insert` pair, so partial writes cannot leave rank and rank_history inconsistent (`src/Rank/RankStore.php:91-142`). Correct architecture.
- **Test coverage footprint is reasonable** for a v1.0: 24 unit-test files covering every REST controller, both stores' adjacent validators, gamification, SMS template logic, and location module. Gaps (noted for info, not as findings): no integration tests for `CrmController` / `OrderController` (hard to mock WCS + Jetpack CRM), no test of `ProspectFilter::filter_prospects` with >1 contact (would have caught the N+1), no `TwilioClient::send` retry/timeout tests.
- **No unbounded `$wpdb->get_results` without LIMIT** outside the pipeline aggregation noted above and the rank/attendance aggregates (which are correctly bounded by user_id or date range).
