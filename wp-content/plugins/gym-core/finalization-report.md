# WooCommerce Plugin Finalization Report

## Plugin: Gym Core v1.0.0
## Date: 2026-03-31

---

### Executive Summary

Gym Core is a well-architected WooCommerce extension with strong security fundamentals — zero critical or high security findings across 36 source files. The plugin correctly declares HPOS and Block Checkout compatibility, uses WooCommerce CRUD patterns throughout, and never accesses order data via post functions.

The three areas requiring attention before production deployment are: **(1)** a broken check-in path for Open Mat sessions where `class_id=0` produces an empty location that fails validation, **(2)** a PHP TypeError that will crash badge evaluation on a member's first-ever belt promotion (null passed where string expected), and **(3)** N+1 query patterns in the promotion eligibility endpoint that will degrade as membership grows. All are straightforward fixes.

UX compliance is strong — settings correctly live under WooCommerce > Settings as a tab, no activation redirects, no branded admin styling. The main UX debt is 26 title-case violations in translatable strings that should use WooCommerce's sentence case convention.

### Testing Gate Status

| Check | Status | Details |
|-------|--------|---------|
| Testing Report | **FAIL** | No `testing-report.md` exists for gym-core |
| PHPStan Level | **PARTIAL** | Level 6 configured; 43 errors (mostly missing WC/CLI stubs) |
| Unit Tests | **PARTIAL** | 60/60 custom tests pass (247 assertions); 12 pre-existing Location/API test failures from scaffold |
| Overall | **BLOCKED** | Generate testing report before release |

---

### Security Audit

| Severity | Count | Summary |
|----------|-------|---------|
| Critical | **0** | No SQL injection, XSS, or auth bypass vectors found |
| High | **0** | All routes have permission_callback; all forms verify nonces |
| Medium | **4** | Twilio token plaintext storage, unprepared GROUP BY query, unescaped post_content in API, webhook URL mismatch risk |
| Low | **4** | No webhook rate limiting, hardcoded location slugs, streak freeze never resets, SMS variables not deeply sanitized |

**Notable positives:** All `$wpdb` queries use `prepare()`, all REST routes have explicit `permission_callback`, AJAX handler uses `check_ajax_referer()`, cookie set with httponly/samesite/secure, HPOS CRUD used exclusively for orders, Twilio signature validation uses `hash_equals()`.

---

### UX Compliance

**Violations (must fix):**
- 26 title-case strings in Settings.php, ClassPostType.php, KioskEndpoint.php need conversion to sentence case
- Kiosk viewport meta disables user zoom (WCAG 2.1 AA failure)

**Suggestions (should consider):**
- Classes CPT could nest under WooCommerce menu instead of top-level
- Kiosk search placeholder contrast (3.7:1) could improve to 4.5:1
- Add keyboard dismiss handler on kiosk success/error screens
- Replace meta box inline styles with CSS classes

**Compliant:**
- Settings under WooCommerce > Settings tab (correct placement)
- Smart defaults on activation (gamification on, SMS off, API off)
- No activation redirect, no branded admin styling, no promotional notices
- Excellent frontend accessibility (ARIA labels, focus-visible, prefers-reduced-motion)

---

### Code Optimization

| Category | High | Medium | Low |
|----------|------|--------|-----|
| Dead Code | 0 | 0 | 3 |
| Duplication | 1 | 2 | 0 |
| Structure | 0 | 0 | 4 |
| Performance | 1 | 5 | 0 |
| WP/WC Patterns | 0 | 0 | 1 |

**Top 3 issues:**
1. **N+1 queries in `PromotionEligibility::get_eligible_members()`** — 600-800 queries for 200 members
2. **Location labels duplicated in 5 files** — adding a third location requires editing 5 files
3. **Streak freeze counter never resets quarterly** — functional bug, members permanently lose freezes

---

### Traceability Analysis

| Path | Status | Issues |
|------|--------|--------|
| 1. Kiosk Check-In | **Partially broken** | Open Mat (class_id=0) fails: empty location rejected. No role filter on member search. |
| 2. Location Selection | **Fully verified** | Clean path, no issues. |
| 3. Belt Rank Promotion | **Type safety risk** | `$from_belt` can be null but BadgeEngine declares `string` — crashes on first-ever promotion. |
| 4. Badge Evaluation | **Verified** | Minor race condition mitigated by DB unique key. |
| 5. SMS Send | **Verified** | Rate limit bypassed when contact_id omitted. |
| 6. Twilio Webhook | **Needs testing** | TwiML response may be JSON-encoded by WP REST instead of raw XML. |

---

### Prioritized Action Items

| # | Priority | Category | Issue | File | Effort |
|---|----------|----------|-------|------|--------|
| 1 | **Critical** | Traceability | Open Mat check-in broken — location empty when class_id=0 | AttendanceController.php, kiosk.js | 30 min |
| 2 | **Critical** | Traceability | TypeError on first-ever promotion — null $from_belt passed to string param | BadgeEngine.php:99 | 5 min |
| 3 | **High** | Performance | N+1 queries in PromotionEligibility::get_eligible_members() | PromotionEligibility.php:153 | 1 hr |
| 4 | **High** | Optimization | Location labels duplicated in 5 files | Taxonomy.php + 4 consumers | 30 min |
| 5 | **High** | Functional | Streak freeze counter never resets quarterly | StreakTracker.php:218 | 30 min |
| 6 | **Medium** | Security | Twilio auth token stored plaintext (UI says "encrypted") | Settings.php:420, TwilioClient.php | 30 min |
| 7 | **Medium** | Traceability | TwiML response may be JSON-wrapped by WP REST | InboundHandler.php:191 | 30 min |
| 8 | **Medium** | Performance | N+1 in GamificationController badge definitions | GamificationController.php:133 | 30 min |
| 9 | **Medium** | Performance | Duplicate StreakTracker/BadgeEngine instances in Plugin.php | Plugin.php:191,255 | 15 min |
| 10 | **Medium** | Performance | Badge evaluation synchronous on every check-in | BadgeEngine.php:75 | 1 hr |
| 11 | **Medium** | UX | 26 title-case violations need sentence case | Settings.php, ClassPostType.php, KioskEndpoint.php | 30 min |
| 12 | **Medium** | Security | Webhook URL mismatch risk with proxies | InboundHandler.php:90 | 15 min |
| 13 | **Medium** | Optimization | Permission callback duplication across 4 controllers | 4 API controllers | 30 min |
| 14 | **Medium** | Optimization | Frontend assets loaded on every page | LocationSelector.php:85 | 15 min |
| 15 | **Low** | Security | Unprepared GROUP BY query in RankStore | RankStore.php:259 | 5 min |
| 16 | **Low** | Security | Unescaped post_content in API response | ClassScheduleController.php:289 | 5 min |
| 17 | **Low** | UX | Kiosk viewport disables zoom (WCAG AA) | KioskEndpoint.php:185 | 5 min |
| 18 | **Low** | Traceability | Kiosk member search shows admin users | kiosk.js:126 | 5 min |
| 19 | **Low** | Traceability | SMS rate limit bypassed without contact_id | SMSController.php:151 | 15 min |
| 20 | **Low** | Infra | PHPStan needs WC + WP-CLI stubs | phpstan.neon | 15 min |
