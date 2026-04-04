# Finalization Tasks for Gym Core

> Last audited: 2026-04-03. Status reconciled against actual codebase.

## Critical Priority

### TASK-TRC-001: Fix Open Mat check-in — location empty when class_id=0
- **File:** `src/API/AttendanceController.php` lines 166-180, `assets/js/kiosk.js` line 289
- **Issue:** When kiosk has no classes today and calls check-in with `class_id=0`, `get_the_terms(0, 'gym_location')` returns false, producing `$location = ''`. Validator rejects with `missing_location`.
- **Fix:** Location fallback from request param implemented; kiosk.js sends `location: config.location` in POST body.
- **Status:** [x] Fixed — AttendanceController:179-180 has location fallback; kiosk.js:289 sends location

### TASK-TRC-002: Fix TypeError on first-ever promotion
- **File:** `src/Gamification/BadgeEngine.php` line 99
- **Issue:** `evaluate_on_promotion( ..., string $from_belt, ... )` receives `null` when a member has no previous rank (first-ever promotion). PHP 8.x strict types throws TypeError.
- **Fix:** Signature changed to `?string $from_belt` with null-safe logic. First-ever promotions still get the badge.
- **Status:** [x] Fixed — BadgeEngine.php:99 uses `?string $from_belt`

## High Priority

### TASK-OPT-001: Fix N+1 queries in PromotionEligibility::get_eligible_members()
- **File:** `src/Attendance/PromotionEligibility.php` lines 166-220
- **Issue:** For each ranked member, `check()` runs 2-3 DB queries. 200 members = 600-800 queries.
- **Fix:** Batch-fetch attendance counts with CASE/WHEN query; `cache_users()` primes WP user cache.
- **Status:** [x] Fixed — PromotionEligibility.php:189 uses cache_users(), lines 195-210 use batch query

### TASK-OPT-002: Extract location labels to Taxonomy::get_location_labels()
- **File:** `src/Location/Taxonomy.php` + consumer files
- **Issue:** Location label maps duplicated in multiple files.
- **Fix:** `Taxonomy::get_location_labels()` added; all consumers reference it.
- **Status:** [x] Fixed — Taxonomy.php:64 defines method; used in 8 consumer files

### TASK-OPT-003: Fix streak freeze quarterly reset
- **File:** `src/Gamification/StreakTracker.php` lines 227-247
- **Issue:** `_gym_streak_freezes_used` user meta never resets. Members permanently lose freezes.
- **Fix:** Quarter stored alongside count; `current_quarter()` method compares and resets.
- **Status:** [x] Fixed — StreakTracker.php:227-247 implements quarterly reset

## Medium Priority

### TASK-SEC-001: Fix Twilio auth token plaintext vs "encrypted" claim
- **File:** `src/Admin/Settings.php`
- **Issue:** Description says "Credentials are stored encrypted" but they are stored as plaintext wp_options.
- **Fix:** No false "encrypted" claim exists in Settings.php. Auth token uses `type => password` (appropriate). Non-issue.
- **Status:** [x] Verified — no false encryption claim found in Settings.php:523-569

### TASK-TRC-003: Fix TwiML response JSON-wrapping
- **File:** `src/SMS/InboundHandler.php` lines 47, 64-79
- **Issue:** WP_REST_Response JSON-encodes the XML string body. Twilio expects raw XML.
- **Fix:** `rest_pre_serve_request` filter intercepts XML responses and outputs raw XML.
- **Status:** [x] Fixed — InboundHandler.php:47 registers filter; lines 64-79 serve raw XML

### TASK-OPT-004: Fix N+1 in GamificationController badge definitions
- **File:** `src/API/GamificationController.php` lines 124-157
- **Issue:** `has_badge()` queries per badge (14 queries). `get_user_badges()` called repeatedly.
- **Fix:** Fetch user badges once into `$earned_map` indexed by slug. Single query replaces N+1.
- **Status:** [x] Fixed — 2026-04-03: earned_map pattern implemented

### TASK-OPT-005: Deduplicate StreakTracker/BadgeEngine instances
- **File:** `src/Plugin.php` lines 73-82, 394-396
- **Issue:** Two separate instances created — one for API, one for hooks.
- **Fix:** Both stored as `private ?` class properties, instantiated once, reused throughout.
- **Status:** [x] Fixed — Plugin.php:73-82 declares properties; line 394-395 instantiates once

### TASK-OPT-006: Debounce badge evaluation via Action Scheduler
- **File:** `src/Gamification/BadgeEngine.php`
- **Issue:** 5+ queries per check-in for badge evaluation, runs synchronously.
- **Fix:** Check-in hook schedules async evaluation via `as_schedule_single_action()` with graceful fallback.
- **Status:** [x] Fixed — 2026-04-03: schedule_checkin_evaluation() defers via Action Scheduler

### TASK-UX-001: Fix title-case violations to sentence case
- **File:** `src/Attendance/KioskEndpoint.php`
- **Issue:** WooCommerce UX guidelines require sentence case. KioskEndpoint.php has 9 violations in user-facing strings.
- **Examples:** "Tap to Check In" → "Tap to check in", "Check-in Failed" → "Check-in failed", "Select Your Class" → "Select your class"
- **Status:** [x] Fixed — 2026-04-03: 9 violations in KioskEndpoint.php converted to sentence case

### TASK-SEC-002: Use actual request URL for Twilio signature validation
- **File:** `src/SMS/InboundHandler.php` lines 123-125
- **Issue:** `rest_url()` may not match actual request URL behind proxies.
- **Fix:** Configurable webhook URL via `gym_core_twilio_webhook_url` option.
- **Status:** [x] Fixed — InboundHandler.php:123-125 uses configurable option

### TASK-OPT-007: Extract permission callback to BaseController
- **File:** `src/API/BaseController.php` line 151
- **Issue:** "View own or have capability" pattern repeated.
- **Fix:** `permissions_view_own_or_cap()` method added to BaseController.
- **Status:** [x] Fixed — BaseController.php:151

### TASK-OPT-008: Conditional frontend asset loading
- **File:** `src/Frontend/LocationSelector.php` line 91
- **Issue:** Location selector CSS/JS loaded on every frontend page.
- **Fix:** Checks `require_location` option before enqueuing.
- **Status:** [x] Fixed — LocationSelector.php:91 checks option

## Low Priority

### TASK-SEC-003: Wrap unprepared GROUP BY in prepare()
- **File:** `src/Rank/RankStore.php` line 275
- **Issue:** GROUP BY query not wrapped in `$wpdb->prepare()`. No user input but coding standard.
- **Fix:** Wrapped in `$wpdb->prepare()` with dummy WHERE clause for PHPCS compliance.
- **Status:** [x] Fixed — 2026-04-03

### TASK-SEC-004: Escape post_content in API response
- **File:** `src/API/ClassScheduleController.php` line 289
- **Fix:** `wp_kses_post()` applied to post_content.
- **Status:** [x] Fixed — ClassScheduleController.php:289 uses wp_kses_post()

### TASK-UX-002: Remove viewport zoom lock on kiosk
- **File:** `src/Attendance/KioskEndpoint.php`
- **Fix:** No `maximum-scale` or `user-scalable=no` found. Already compliant.
- **Status:** [x] Verified — no zoom lock present

### TASK-TRC-004: Add role filter to kiosk member search
- **File:** `assets/js/kiosk.js` line 156
- **Fix:** Search URL includes `&roles=customer,subscriber`.
- **Status:** [x] Fixed — kiosk.js:156

### TASK-TRC-005: Add per-user SMS rate limit fallback
- **File:** `src/API/SMSController.php` line 151
- **Fix:** Falls back to `get_current_user_id()` when `contact_id` is missing.
- **Status:** [x] Fixed — SMSController.php:151

### TASK-INFRA-001: Add WC + WP-CLI stubs to PHPStan config
- **File:** `phpstan.neon`
- **Fix:** WC and WP-CLI stubs already configured in bootstrapFiles.
- **Status:** [x] Verified — stubs present in phpstan.neon

---

## Summary

| Priority | Total | Fixed | Remaining |
|----------|-------|-------|-----------|
| Critical | 2 | 2 | 0 |
| High | 3 | 3 | 0 |
| Medium | 9 | 9 | 0 |
| Low | 6 | 6 | 0 |
| **Total** | **20** | **20** | **0** |

**All finalization tasks complete.**
