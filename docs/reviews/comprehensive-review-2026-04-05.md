# Comprehensive Site Review — Haanpaa Martial Arts

**Date:** 2026-04-05
**Targets:** gym-core v1.0.0, hma-ai-chat v0.4.0, team-haanpaa v0.2.0
**Reviews run:** 9 of 9 (all complete)

---

## Executive Summary

The codebase is **well above average** for a WordPress/WooCommerce project. Security fundamentals are strong (no critical vulnerabilities, proper $wpdb->prepare() everywhere, constant-time webhook HMAC), HPOS compatibility is properly declared, REST API design is excellent with consistent error envelopes and capability checks, and the AI tool approval queue is a textbook human-in-the-loop pattern.

The most impactful issues to address before go-live:
1. **Anthropic API key in plaintext** in wp_options (credential hygiene)
2. **`get_post_meta()` on products** instead of WC CRUD methods (HPOS forward-compatibility)
3. **No `wc_get_logger()` usage** anywhere (observability gap)
4. **Conversation ownership not verified** in AI chat MessageEndpoint (data leakage)
5. **Cool Gray below 18px** in footer/CTA (WCAG AA violation)

---

## Findings by Severity

### CRITICAL (5 unique issues)

| ID | Issue | Source | Files |
|----|-------|--------|-------|
| C1 | Anthropic API key stored plaintext in wp_options, full value rendered in settings form | Security, Payment, UX | `hma-ai-chat/src/API/ClaudeClient.php:61`, `Admin/SettingsPage.php:372` |
| C2 | `get_post_meta()` on WooCommerce product IDs instead of `$product->get_meta()` | WC Patterns, WP Standards, Payment | `gym-core/src/Sales/PricingCalculator.php:143-157`, `OrderBuilder.php:321-322`, `API/SalesController.php:510-519` |
| C3 | `update_post_meta()` in ProductMetaBox instead of WC CRUD | WC Patterns | `gym-core/src/Sales/ProductMetaBox.php:187` |
| C4 | Unescaped table name in DROP TABLE (hma-ai-chat uninstall) | WP Standards | `hma-ai-chat/uninstall.php:20` |
| C5 | Conversation ownership not verified — any staff can read others' AI chat | Security | `hma-ai-chat/src/API/MessageEndpoint.php:76,111-116` |

### HIGH (12 unique issues)

| ID | Issue | Source | Files |
|----|-------|--------|-------|
| H1 | Twilio auth token settings page shows field even when wp-config constant defined | Security | `gym-core/src/Admin/Settings.php:540-543` |
| H2 | No `wc_get_logger()` usage anywhere — zero WC logging | Payment, WC Patterns | All of gym-core |
| H3 | `error_log()` used instead of `wc_get_logger()` in 11 locations | WC Patterns | `PromotionNotifier.php`, `MemberController.php`, `CrmSmsBridge.php`, `CrmContactSync.php`, `PromotionPost.php` |
| H4 | jQuery in UserProfileRank, PromotionDashboard, AttendanceDashboard (violates "No jQuery" convention) | UX | `Admin/UserProfileRank.php:90`, `PromotionDashboard.php:138`, `AttendanceDashboard.php:152` |
| H5 | No Twilio connection test/status indicator | UX | `Admin/Settings.php:523-569` |
| H6 | No Anthropic API connection test/status indicator | UX | `hma-ai-chat/Admin/SettingsPage.php:367-380` |
| H7 | `declare(strict_types=1)` missing in 15 of 19 hma-ai-chat source files | WP Standards | All hma-ai-chat src/ files except 4 |
| H8 | Plugin header version mismatch: header says 0.1.0, constant says 0.4.0 | WP Standards | `hma-ai-chat/hma-ai-chat.php:5,18` |
| H9 | StaffDashboard loads ALL active subscriptions into memory (no pagination) | UX | `Admin/StaffDashboard.php:471-478` |
| H10 | Inline styles throughout admin rendering (accessibility/maintenance risk) | UX | `UserProfileRank.php`, `StaffDashboard.php` |
| H11 | FoundationsController permissions_view doesn't check is_user_logged_in() first | WP Standards | `API/FoundationsController.php:119` |
| H12 | Theme missing `add_theme_support('woocommerce')` | WC Patterns | `themes/team-haanpaa/functions.php` |

### MEDIUM (18 unique issues)

| ID | Issue | Files |
|----|-------|-------|
| M1 | `revisedData` in HeartbeatEndpoint not deeply sanitized | `hma-ai-chat/API/HeartbeatEndpoint.php:241` |
| M2 | `actionData` only flat-sanitized (nested arrays bypass) | `hma-ai-chat/API/HeartbeatEndpoint.php:174` |
| M3 | Hardcoded 'rockford' location fallback in both kiosk endpoints | `Attendance/KioskEndpoint.php:181`, `Sales/KioskEndpoint.php:236` |
| M4 | SMS `variables` param lacks sanitize_callback for nested data | `API/SMSController.php:132` |
| M5 | iCal feed missing `X-Content-Type-Options: nosniff` header | `Schedule/ICalFeed.php:184` |
| M6 | Sales `down_payment` route arg missing sanitize_callback | `API/SalesController.php:569-572` |
| M7 | GymContextProvider queries capped at 100 orders without pagination | `hma-ai-chat/Context/GymContextProvider.php:539-562` |
| M8 | In-memory pagination in RankController and PromotionController | `API/RankController.php:169-196`, `PromotionController.php:106-110` |
| M9 | Duplicate `wcs_get_users_subscriptions()` calls in MemberController | `API/MemberController.php:196-199,294-298` |
| M10 | ClassScheduleController has no post meta cache priming | `API/ClassScheduleController.php:246-282` |
| M11 | Header "Join Now" button has no href destination | `themes/team-haanpaa/parts/header.html:15` |
| M12 | Footer social links are placeholder `#` hrefs | `themes/team-haanpaa/parts/footer.html:44-57` |
| M13 | `styles.php` uses wrong handle name `telex-theme-style` | `themes/team-haanpaa/styles.php:14` |
| M14 | CRM white-labeling hardcodes "Gym CRM" string | `Admin/CrmWhiteLabel.php:41` |
| M15 | Chat page title shows "HMA AI Chat" instead of "Gandalf" | `hma-ai-chat/Admin/ChatPage.php:28` |
| M16 | Settings page title says "Gym Dashboard Settings" (should be "AI Agent Settings") | `hma-ai-chat/Admin/SettingsPage.php:181` |
| M17 | Footer copyright Cool Gray at 0.8rem fails WCAG AA | `themes/team-haanpaa/parts/footer.html:81-86` |
| M18 | CTA section Cool Gray body text at 1.05rem on black fails WCAG AA | `themes/team-haanpaa/templates/index.html:293-294` |

### LOW (12 unique issues)

| ID | Issue |
|----|-------|
| L1 | Rate limiting uses transients (non-atomic without persistent cache) |
| L2 | jQuery `.html()` in admin-promotion.js with sanitized data |
| L3 | ICalFeed hardcodes location addresses |
| L4 | No rate limiting on AI chat MessageEndpoint |
| L5 | Google Fonts loaded from CDN without SRI |
| L6 | `artefact.xml` contains entire old brand — should be removed or excluded from deploy |
| L7 | Value cards missing resting box-shadow per brand guide |
| L8 | Hero H1 font-size max (5rem) exceeds brand guide token (4rem) |
| L9 | Location selector error uses `window.alert()` |
| L10 | Hero image alt text contains AI generation prompt, not real description |
| L11 | Missing `screenshot.png` for theme |
| L12 | Footer copyright hardcoded year "2026" |

---

## Agentic Commerce Readiness

| Area | Rating |
|------|--------|
| Headless/API Payment Flow | **Conditional** — order creation is API-ready; payment requires browser redirect |
| 3D Secure / SCA | **Delegated** to WooPayments |
| Structured Error Responses | **Ready** — consistent WP_Error envelope with machine-readable codes |
| Idempotency | **Conditional** — action queue is idempotent; order creation lacks idempotency key |
| Rate Limiting | **Ready** — proper 429 responses, per-user buckets |
| MCP Tool Compatibility | **Conditional** — strong tool registry; missing order creation tool |
| Stripe ACP | **Not Ready** (expected — delegated to WooPayments) |
| Product Discoverability | **Ready** — rich REST API for products, pricing, locations |

**Key recommendations:** Add idempotency keys to order creation, enable programmatic payment method assignment, register a `create_membership_order` tool in the AI ToolRegistry.

---

## UX Review Score: 7.5 / 10

**Strengths:** WooCommerce settings conventions followed, assets properly scoped, role-based access well-implemented, kiosk UX is excellent with proper touch targets and accessibility.

**Gaps:** jQuery convention violations, no credential validation UI, inline styles in admin, placeholder content in theme (links, alt text).

---

## Brand Guide Compliance

**theme.json:** All 9 colors, both font families, all spacing tokens match brand guide exactly.
**Templates:** All old brand references (coral, navy, Playfair) fully removed.
**Issues:** Cool Gray accessibility violations at small sizes, missing resting card shadow, `artefact.xml` cleanup needed.

---

## Positive Highlights

1. **Zero SQL injection vectors** — `$wpdb->prepare()` used consistently everywhere
2. **Excellent webhook security** — constant-time HMAC, IP allowlisting, secret rotation with grace period
3. **AI tool approval queue** — all write operations require human approval before execution
4. **Granular custom capabilities** — `gym_check_in_member`, `gym_promote_student`, etc.
5. **Proper HPOS declarations** — both `custom_order_tables` and `cart_checkout_blocks` declared
6. **Consistent REST API design** — permission callbacks on every route, structured error/success envelopes
7. **No credentials in source code** — Twilio supports wp-config constants
8. **Strong kiosk accessibility** — touch targets, dark theme, screen reader text, reduced motion

---

## Priority Action Plan

### Before Go-Live (Critical + High)
1. **[BUG]** Fix announcement meta key prefix mismatch — GymContextProvider uses `_announcement_end_date` but BriefingController uses `_gym_announcement_end_date`. Announcements never appear in AI context.
2. **[BUG]** Fix REST response envelope not unwrapped in GymContextProvider — parses `$rank_data['program']` but response is `{ success: true, data: [...] }` envelope. Context returns empty strings.
3. **[BUG]** `gym_view_achievements` capability referenced in GamificationController but never registered in Capabilities::ALL_CAPS — only admins can view badges.
4. Fix conversation ownership check in MessageEndpoint
5. Add wp-config constant support for Anthropic API key + mask in settings form
6. Replace all `get_post_meta()` on products with `$product->get_meta()`
7. Replace `error_log()` with `wc_get_logger()` across gym-core
8. Add `declare(strict_types=1)` to all hma-ai-chat files
9. Fix hma-ai-chat version mismatch (header vs constant)
10. Add `add_theme_support('woocommerce')` to theme
11. Fix Cool Gray WCAG violations in footer and CTA section

### During Testing Gate (Medium)
9. Deep-sanitize nested webhook data (map_deep)
10. Remove hardcoded 'rockford' fallbacks
11. Fix `telex-theme-style` handle
12. Add real hrefs to header CTA and footer links
13. Replace AI generation prompt alt text with real descriptions
14. Rename "HMA AI Chat" to "Gandalf" in admin UI
15. Bound StaffDashboard subscription queries

### Post-Launch (Low + Agentic)
16. Remove/exclude `artefact.xml` from deploy
17. Add idempotency keys to order creation
18. Register `create_membership_order` in ToolRegistry
19. Self-host Google Fonts for GDPR compliance
20. Add Twilio/Anthropic connection test buttons
21. Remove deprecated `PromotionEligibility::get_default_thresholds()`
22. Refactor duplicated targeting rule evaluation in TargetedContent
23. Fix SMS rate limiting bypass when `contact_id=0`
24. Add rate limiting to AI chat MessageEndpoint

---

## Finalization Review — Additional Findings

### Traceability Analysis (Track 4)

**Verified Paths (all connected correctly):**
- Check-in flow: kiosk.js → AttendanceController → CheckInValidator → AttendanceStore → Action Scheduler (badge eval)
- Rank promotion: RankController → RankStore (MySQL transaction) → hooks → SMS/email/blog post
- AI chat message: ChatPage JS → MessageEndpoint → AgentRegistry → GymContextProvider → ClaudeClient
- Paperclip webhook: IP allowlist → Bearer token (constant-time) → HeartbeatEndpoint → PendingActionStore
- Sales kiosk: SalesController → PricingCalculator → OrderBuilder → wc_create_order/wcs_create_subscription

**Broken Paths Found:**
1. `GymContextProvider` announcement meta keys missing `_gym_` prefix — announcements never appear in AI context
2. `GymContextProvider` REST response envelope not unwrapped — rank/streak/badge data silently empty
3. `gym_view_achievements` capability referenced but never granted to any role

### Code Optimization (Track 3)
- Targeting rule evaluation duplicated between shortcode handler and `evaluate_rules()` — should delegate
- `TargetedContent::get_member_context()` creates fresh store instances bypassing DI singletons
- `Plugin.php` approaching 606 lines — consider ServiceProvider extraction (not urgent)

### Positive Highlights from Finalization
- Badge evaluation deferred via Action Scheduler for fast check-in response times
- `PromotionEligibility` uses batched SQL with CASE/WHEN for per-member attendance (no N+1)
- Post caches primed in bulk via `_prime_post_caches()` and `cache_users()` before loops
- Every state change fires documented action hooks with filterable definition arrays
- `RankStore::promote()` wraps read-then-write in MySQL transaction to prevent race conditions
