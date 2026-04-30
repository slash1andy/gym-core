# Testing Report: Gym Core v1.0.0

**Date:** 2026-04-30
**Branch:** chore/gym-core-phpstan-level7
**PHP Version:** 8.5.4
**PHPUnit Version:** 11.5.55

---

## Unit Tests

```
Tests: 276, Assertions: 911
Errors: 0, Failures: 0, Risky: 0
```

**Result: PASS**

### Test Suites

| Suite | Status |
|-------|--------|
| API/AttendanceController | Pass |
| API/BaseController | Pass |
| API/BriefingController | Pass |
| API/ClassScheduleController | Pass |
| API/CrmControllerCache | Pass |
| API/FoundationsController | Pass |
| API/GamificationController | Pass |
| API/LocationController | Pass |
| API/MemberController | Pass |
| API/OrderController | Pass |
| API/PromotionController | Pass |
| API/RankController | Pass |
| API/SMSController | Pass |
| API/SocialPostManager | Pass |
| Attendance/AttendanceStoreCache | Pass |
| Attendance/CheckInValidator | Pass |
| Attendance/MilestoneTrackerAsync | Pass |
| Data/TableManager | Pass |
| Gamification/BadgeDefinitions | Pass |
| Gamification/BadgeEngine | Pass |
| Gamification/StreakTracker | Pass |
| Gamification/TargetedContentBlock | Pass |
| Location/Manager | Pass |
| Location/OrderLocation | Pass |
| Location/ProductFilter | Pass |
| Location/Taxonomy | Pass |
| Rank/RankDefinitions | Pass |
| Rank/RankStoreCache | Pass |
| Sales/KioskEndpointLocation | Pass |
| Sales/PricingCalculator | Pass |
| Sales/ProspectFilter | Pass |
| Schedule/ScheduleCachePrimer | Pass |
| SMS/MessageTemplates | Pass |
| SMS/TwilioClient | Pass |
| SMS/TwilioClientRetry | Pass |

---

## Static Analysis (PHPStan)

**Level:** 7 (bumped from 6)
**Result: PASS (0 errors)**

96 type errors fixed across 20 source files. Patterns addressed:

- `property.nonObject` — added `is_object()` guards on `WP_Query->posts` iteration (the stub types `posts` as `WP_Post[]|int[]`)
- `property.notFound` — changed `object` parameter types to `\stdClass` in DB-result methods (`RankController`, `AttendanceDashboard`, `UserProfileRank`)
- `argument.type` — cast `strtotime()` results to `int` before passing to `gmdate()`/`wp_date()`; cast `WP_Error::get_error_code()` to `string`; added `is_array()` guards on `wc_get_orders()`/`wc_get_products()` which can return `stdClass` when paginated
- `return.type` — tightened `apply_filters()` returns in `BadgeDefinitions::get_all()` and `MessageTemplates::get_all()` with `is_array()` fallback; fixed shape-typed returns in `RankDefinitions::get_promotion_threshold()` and `MemberDashboard::get_active_subscription()`
- `method.notFound` — narrowed `wc_get_order()` result to `WC_Order` via `instanceof` before calling `get_order_key()` (not available on `WC_Order_Refund`)
- `foreach.nonIterable` — wrapped `wc_get_orders()` in `is_array()` guards before iterating
- `assign.propertyType` — coalesced `add_submenu_page()` `string|false` return with `?: ''`
- `offsetAccess.nonOffsetAccessible` — switched array-style property access (`$rank['belt']`) to object access (`$rank->belt`) for `stdClass` DB results
- `function.notFound` / WordPress AI Client — added `function_exists('wp_ai_client_prompt')` guard with proper early return; used direct namespace call within `function_exists` branch for `get_preferred_image_models()`

PHPStan config (`phpstan.neon`) updated to `maximumNumberOfProcesses: 1` to avoid OOM crashes from parallel workers loading the WooCommerce stubs.

---

## Code Style (PHPCS)

**Standard:** WordPress-Extra (via phpcs.xml.dist)
**Result:** 0 errors, 1 warning (1 file)

The single warning is a pre-existing `base64_decode()` notice in `src/API/MediaController.php` (line 284) — the function is used legitimately to decode AI-generated image payloads and was present before this branch.

---

## Testing Gate

| Check | Status |
|-------|--------|
| Unit Tests | **PASS** — 276 tests, 911 assertions, 0 failures |
| PHPStan Level 7 | **PASS** — 0 errors |
| PHPCS | **PASS** — 0 errors (1 pre-existing warning, non-blocking) |
| Testing Report | **PASS** — this document |

**Overall: PASS**
