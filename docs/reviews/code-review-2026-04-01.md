# Code Review — gym-core Plugin (2026-04-01)

## Status: 5 CRITICAL issues fixed, 6 HIGH remain, 7 MEDIUM, 4 LOW

### CRITICAL (all fixed)
1. ~~Missing closing brace in `register_attendance_modules()` — fatal parse error~~ FIXED
2. ~~`AttendanceDashboard` instantiated without required constructor args~~ FIXED
3. ~~`PromotionDashboard` instantiated without required constructor args~~ FIXED
4. ~~`UserProfileRank` instantiated without required constructor args~~ FIXED
5. ~~`PromotionEligibility` constructed without `FoundationsClearance`~~ FIXED

### HIGH (to fix)
6. **Twilio webhook signature bypass via URL manipulation** — `InboundHandler.php:92-95`. Use configurable webhook URL option instead of `$_SERVER['HTTP_HOST']`.
7. **Twilio credentials stored in plain text** — `Settings.php:536`. Use `wp_encrypt()`/`wp_decrypt()` or `wp-config.php` constants.
8. **`FoundationsController::get_active()` raw LIKE on serialized meta** — Use `$wpdb->prepare()` and consider separate indexed meta key.
9. **Race condition in `RankStore::promote()`** — No transaction wrapping. Wrap in `START TRANSACTION`/`COMMIT`.
10. **N+1 queries in `AttendanceController::get_history()`** — Prime post cache in bulk.
11. **N+1 queries in `MemberController::build_upcoming_classes_section()`** — Bulk meta/term cache priming.

### MEDIUM (7 items)
12. Duplicate `StreakTracker`/`BadgeEngine` instances — create once, store as properties
13. `AttendanceStore::get_user_history()` date params not validated
14. `TwilioClient::send()` variable shadowing
15. `InboundHandler::twiml_response()` returns TwiML via WP_REST_Response
16. `get_today_by_location()` hardcodes Rockford/Beloit
17. `FoundationsClearance::record_coach_roll()` no capability check in method
18. `CrmSmsBridge::find_contact_by_phone()` full table scan with LIKE

### LOW (4 items)
19. Rate limiter sliding window reset
20. `RankStore::get_member_counts_by_program()` uses `%i` placeholder (WP 6.2+)
21. Missing return type hints on 2 REST callbacks
22. Inline CSS/JS in admin pages

### Test Coverage Gaps
- No tests for `AttendanceStore`, `RankStore`, `PromotionEligibility`
- No tests for admin dashboard AJAX handlers
- No integration tests for CRM bridge or AutomateWoo triggers
- No smoke test for `Plugin::init()`

### Positives
- Excellent DI pattern throughout
- Thorough PHPDoc and hook documentation
- Strong HPOS and Blocks compliance
- Well-designed table schema with appropriate indexes
