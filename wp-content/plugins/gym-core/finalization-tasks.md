# Finalization Tasks — gym-core 1.0.0

> Generated: 2026-04-30  
> Auditor: TAMMIE (woocommerce-finalize skill)  
> Branch: chore/finalization-2026-04-30  
> Previous audit: 2026-04-03 — all 20 prior tasks complete (see git history for archived list)

---

## Testing Gate

| Check | Status | Notes |
|-------|--------|-------|
| Report exists | PASS | `testing-report.md` present |
| Timestamp | ⚠️ STALE | Generated 2026-04-03 — 27 days before this audit; re-run required |
| All tests pass | PASS | 151 tests, 0 failures, 0 skipped |
| PHPStan level | ⚠️ WARN | `phpstan.neon` configures `level: 6`; skill requires level 7 |
| PHPCS | INFO | 645 violations reported (non-blocking; informational only) |

**Gate outcome:** Acknowledged warnings — internal project, finalization proceeds. Re-run testing suite against current HEAD before any release milestone.

---

## Track 1: Code Health

### TASK-OPT-001: ContentGating subscription check fires uncached on every purchasability filter — HIGH

- **File:** `src/Member/ContentGating.php`
- **Lines:** hide_already_subscribed_products() implementation
- **Issue:** `hide_already_subscribed_products()` is hooked on `woocommerce_is_purchasable`, which fires for every product on shop/archive pages. It calls `wcs_get_users_subscriptions( $user_id )` on each invocation with no transient or per-request cache. A 12-product shop page fires 12 subscription queries per logged-in visitor.
- **Fix:** Cache the result in a static property keyed by `$user_id` for the duration of the request:
  ```php
  static $cache = [];
  if ( ! isset( $cache[ $user_id ] ) ) {
      $cache[ $user_id ] = wcs_get_users_subscriptions( $user_id );
  }
  $subscriptions = $cache[ $user_id ];
  ```
- **Severity:** HIGH — measurable page-load regression under normal traffic
- **Status:** [ ] Not started

---

### TASK-OPT-002: ContentGating::has_active_membership() makes 4 external calls per invocation — MEDIUM

- **File:** `src/Member/ContentGating.php`
- **Lines:** ~122–129
- **Issue:** When called without a program argument, the method iterates all 4 `PLANS` constants, calling `wc_memberships_is_user_active_member()` for each. Every call enters WC Memberships query logic. Content-gated pages that check membership status multiple times per request amplify this.
- **Fix:** Short-circuit already occurs on first truthy result. Additionally, add a static per-request cache keyed by `$user_id` so repeat calls within the same request return early.
- **Severity:** MEDIUM — latent; worsens with additional gating calls per request
- **Status:** [ ] Not started

---

### TASK-OPT-003: TwilioClient instantiated twice in Plugin.php — MEDIUM

- **File:** `src/Plugin.php`
- **Lines:** ~291–293 (register_api_modules) and ~432–434 (register_notification_modules)
- **Issue:** Two separate `new SMS\TwilioClient()` instances are created in different module-registration passes. Each independently reads `gym_core_twilio_*` options from the database. The class is stateless so correctness is unaffected, but the duplication creates unnecessary `get_option()` calls and blocks clean unit testing.
- **Fix:** Instantiate once in the plugin bootstrap (alongside the existing shared `$crm_client` pattern) and pass the same instance to both consumers.
- **Severity:** MEDIUM — minor inefficiency; blocks clean unit testing
- **Status:** [ ] Not started

---

### TASK-OPT-004: FormToCrm::get_completed_order_count() uses unbounded wc_get_orders query — MEDIUM

- **File:** `src/Integrations/FormToCrm.php`
- **Lines:** get_completed_order_count implementation
- **Issue:** Passes `'limit' => -1` to `wc_get_orders()`, loading all order IDs for a user into memory. For long-tenured members this means hundreds of IDs fetched on every `woocommerce_order_status_completed` event.
- **Fix:** The method only needs to distinguish first purchase from subsequent ones. Use `'limit' => 2` and check `count( $orders ) === 1`.
- **Severity:** MEDIUM — unbounded query on a high-frequency hook
- **Status:** [ ] Not started

---

### TASK-OPT-005: AttendanceController::get_today() issues one query per location slug — LOW

- **File:** `src/API/AttendanceController.php`
- **Lines:** ~334–347
- **Issue:** `get_today()` loops over location slugs and fires a separate `AttendanceStore::get_today_by_location()` query per slug. With 2 locations this is 2 queries; the pattern mirrors the N+1 shape of marketplace MAJOR-01 and does not scale if locations are added.
- **Fix:** Add `get_today_all_locations()` to `AttendanceStore` that uses `WHERE location IN (...)`, or extend the existing method to accept an array and build a single query.
- **Severity:** LOW — only 2 locations currently; low urgency
- **Status:** [ ] Not started

---

### TASK-OPT-006: Confirm ClassRosterController N+1 status (Marketplace MAJOR-01) — HIGH

- **File:** `src/API/ClassRosterController.php`
- **Lines:** roster-building methods
- **Issue:** Marketplace review MAJOR-01 flagged a per-user meta query inside a loop when building the class roster. No targeted fix commit for this file was identified in the recent git log. Current status is unconfirmed.
- **Fix:** Read the current file. If the N+1 pattern still exists, add a bulk `get_user_meta()` prefetch (or equivalent batch query) before the loop. If already fixed, mark confirmed.
- **Cross-reference:** Marketplace MAJOR-01
- **Severity:** HIGH — blocks release if unresolved
- **Status:** [ ] Not started — requires code read to confirm

---

## Track 2: Traceability

### TASK-TRC-001: Membership enrollment — ✅ VERIFIED

- **Path:** WC checkout submit → `woocommerce_order_status_completed` → `FormToCrm::handle_order_completed()` → CRM pipeline update → lead→member tag swap → `do_action( 'gym_core_member_enrolled' )` → AutomateWoo trigger
- **Entry:** `src/Integrations/FormToCrm.php` — `handle_order_completed()`
- **Exit:** `gym_core_member_enrolled` action; WC Memberships handles role/access assignment
- **Gap/Finding:** Chain is intact. Role assignment correctly delegated to WC Memberships. See TASK-OPT-004 for unbounded order query in this path.
- **Status:** [ ] Verified — no blocking gap; TASK-OPT-004 is a performance concern

---

### TASK-TRC-002: Check-in flow — ✅ VERIFIED

- **Path:** Kiosk UI → `POST /gym/v1/checkin` → `AttendanceController::create()` → `CheckInValidator::validate()` → `AttendanceStore::record_checkin()` → `gym_core_attendance_recorded` → `MilestoneTracker::check_milestones()` (priority 20) → Action Scheduler → `evaluate_milestones()`
- **Entry:** `src/API/AttendanceController.php` — `create()`
- **Exit:** `MilestoneTracker::award_milestone()` → `do_action( 'gym_core_attendance_milestone' )`
- **Gap/Finding:** All layers connect. Action Scheduler dedup check prevents queuing duplicate evaluations. Synchronous fallback when AS unavailable. Permission callbacks verified (TASK-TRC-005). See TASK-OPT-005 for N+1 concern in `get_today()`.
- **Status:** [ ] Verified — no blocking gap

---

### TASK-TRC-003: SMS dispatch — ⚠️ SUSPICIOUS

- **Path:** Enrollment/milestone trigger → `SmsModule::send()` → TCPA opt-out check → `TwilioClient::send()` → Twilio API → delivery record
- **Entry:** `src/SMS/TwilioClient.php` — `send()`
- **Exit:** Twilio API response; `do_action( 'gym_core_sms_sent' )`
- **Gap/Finding:** `TwilioClient::send()` implements per-contact rate limiting via transients but contains **no explicit TCPA opt-out gate** in the class itself. `SMSController.php` and `InboundHandler.php` were not read in this audit. It is unknown whether opt-out enforcement exists in a caller layer. TCPA non-compliance on unsolicited texts is a legal risk.
- **Action required:** Read `src/SMS/SMSController.php`. Confirm there is an explicit opt-out status check before `TwilioClient::send()` is reached. If absent, add opt-out meta check before dispatch. Note: TASK-TRC-005 (prior audit) confirmed `SMSController.php:151` has a contact_id fallback — the opt-out gate may be nearby but was not confirmed.
- **Status:** [ ] Suspicious — must confirm opt-out enforcement before release

---

### TASK-TRC-004: Sales kiosk — ✅ VERIFIED

- **Path:** POS UI → REST handler → `SalesModule` → sliding discount calculation → `OrderBuilder::build()` → `wc_create_order()` / `wcs_create_subscription()` → order confirmation
- **Entry:** `src/Sales/OrderBuilder.php` — `build()`
- **Exit:** `WC_Order` returned to controller; HPOS-compatible meta written via `$order->update_meta_data()`
- **Gap/Finding:** HPOS compatibility confirmed. `wcs_create_subscription()` gated on function existence. Error handling throughout. No blocking gaps.
- **Status:** [ ] Verified — no blocking gap

---

### TASK-TRC-005: REST API auth — ✅ VERIFIED

- **Path:** Request → `permission_callback` → `BaseController` capability/nonce check → controller dispatch → response sanitization
- **Entry:** `src/API/BaseController.php` — `permissions_authenticated()`, `permissions_manage()`, `permissions_view_own_or_cap()`
- **Exit:** Controller method returns sanitized `WP_REST_Response`
- **Gap/Finding:** All three permission helpers return `WP_Error` (with HTTP 401/403) rather than bare `false` — correct per WP REST API best practices. No gaps detected.
- **Status:** [ ] Verified — no blocking gap

---

## Summary

| Category | Count | Blocking? |
|----------|-------|-----------|
| Gate warnings | 2 (stale report, PHPStan level 6) | No — internal project |
| Track 1 HIGH | 2 (ContentGating cache + ClassRosterController N+1 unconfirmed) | MAJOR-01 blocks release if unresolved |
| Track 1 MEDIUM | 3 | No |
| Track 1 LOW | 1 | No |
| Track 2 VERIFIED | 4 of 5 | — |
| Track 2 SUSPICIOUS | 1 (SMS TCPA opt-out unconfirmed) | Recommend confirm before release |

**Recommended pre-release actions (priority order):**
1. Confirm ClassRosterController N+1 status — MAJOR-01 (TASK-OPT-006)
2. Confirm TCPA opt-out enforcement in SMS dispatch path (TASK-TRC-003)
3. Fix ContentGating subscription cache regression (TASK-OPT-001)
4. Re-run full test suite + PHPStan level 7 against current HEAD
