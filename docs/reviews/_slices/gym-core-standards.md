# gym-core v1.0.0 — WP + WC Standards Slice

## Summary

Gym Core demonstrates strong conformance with WordPress + WooCommerce authoring standards: HPOS-safe order handling, proper feature declarations for both `custom_order_tables` and `cart_checkout_blocks`, a well-architected Store API extension, consistent `gym_core_*` hook prefixes, correct `gym-core` text domain on every i18n call spot-checked, PSR-4 autoloading, and a phpcs ruleset built on `WordPress-Extra` with an enforced text-domain property. Findings are limited to minor style/consistency nits. Severity counts: blocker=0, major=0, minor=3, nit=2.

## Findings

### MINOR: Two admin files use unspaced `declare(strict_types=1);` while 70 of 72 source files use the WPCS-preferred spaced form
- **File:** `src/Admin/CrmWhiteLabel.php:8`, `src/Admin/MenuManager.php:8`
- **Category:** Typing
- **Issue:** The rest of the codebase uniformly uses `declare( strict_types=1 );` (WPCS-compliant spacing inside parens), but these two files use the unspaced `declare(strict_types=1);`. The strict-types directive itself is present and functional; this is purely a formatting inconsistency that `WordPress-Extra` rules will flag.
- **Fix:** Replace with `declare( strict_types=1 );` to match the rest of the plugin and pass WPCS `Generic.WhiteSpace.DisallowSpaceIndent` / spacing sniffs.

### MINOR: Nonce action names mix `gym_` and `gym_core_` prefixes
- **File:** `src/Schedule/ClassPostType.php:311` (`gym_class_meta`), `src/Briefing/AnnouncementPostType.php:241` (`gym_announcement_meta`), `src/Admin/AttendanceDashboard.php:160-162` (`gym_quick_checkin`, `gym_member_search`, `gym_set_location`), `src/Admin/UserProfileRank.php:464-465` (`gym_record_coach_roll_*`, `gym_clear_foundations_*`), `src/Location/BlockIntegration.php:109` (`gym_location_nonce`)
- **Category:** Prefix
- **Issue:** Hook names correctly use the `gym_core_*` prefix everywhere (`gym_core_loaded`, `gym_core_attendance_recorded`, etc.), but nonce action identifiers use the shorter `gym_*` prefix. Both are namespaced to the plugin, so there's no collision risk, but marketplace reviewers typically prefer a single consistent prefix everywhere for grep-ability.
- **Fix:** Standardize all new nonce actions on `gym_core_*`. Existing names can be migrated opportunistically (nonces are transient so a change is safe after the next request cycle).

### MINOR: `bulk-promotions` nonce action is unprefixed
- **File:** `src/Admin/PromotionDashboard.php:302`, `src/Admin/PromotionDashboard.php:355`
- **Category:** Prefix
- **Issue:** `bulk-promotions` has no plugin prefix at all. While this mirrors WordPress core's `bulk-{plural}` list-table convention, custom bulk actions outside core list tables should still be namespaced to avoid colliding with another plugin that registers the same action name.
- **Fix:** Rename to `gym_core_bulk_promotions` unless this is intentionally hooked into a WP core list table bulk handler (in which case, add an inline comment documenting the reason).

### NIT: `gym-core` text domain on `__()` / `_e()` calls is correct in every spot-checked file, and enforced by phpcs
- **File:** `phpcs.xml.dist:39-45`, plus spot checks across `src/API/*`, `src/Admin/*`, `src/Notifications/PromotionNotifier.php`, `gym-core.php`
- **Category:** i18n
- **Issue:** No wrong-text-domain calls were found. `phpcs.xml.dist` explicitly pins the `WordPress.WP.I18n` sniff's `text_domain` property to `gym-core`, which will prevent regressions.
- **Fix:** None required. Mentioned here so TAMs don't need to re-verify.

### NIT: phpcs ruleset's `minimum_supported_wp_version` is set to 7.0
- **File:** `phpcs.xml.dist:51`
- **Category:** Hooks / housekeeping
- **Issue:** Plugin header says `Requires at least: 7.0`, but WordPress has no public 7.0 release at time of review (WP is still on 6.x). This is probably a forward-looking placeholder or a typo for `6.0`. PHPCS compatibility sniffs can produce confusing results when the target version does not exist yet.
- **Fix:** Set both the plugin header `Requires at least` and `phpcs.xml.dist minimum_supported_wp_version` to a real, shipped WP minimum (e.g. `6.6`) until WP 7.0 actually ships.

## Verified-Clean

- **HPOS feature declaration** is present and correctly hooked on `before_woocommerce_init` in `gym-core.php:47-66`, with `FeaturesUtil::declare_compatibility( 'custom_order_tables', GYM_CORE_FILE, true )`.
- **Cart & Checkout Blocks feature declaration** is present alongside HPOS in `gym-core.php:59-63`.
- **No HPOS violations in order-touching code.** `src/Location/OrderLocation.php` uses `$order->update_meta_data()` / `$order->save_meta_data()` / `$order->get_meta()` (lines 98-111) rather than `update_post_meta()`. `OrderController`, `StaffDashboard`, `FormToCrm`, and `Sales/KioskEndpoint` do not call `get_post_meta`/`update_post_meta` on order IDs at all — they use `wc_get_orders()`, `wc_get_order()`, and `wc_create_refund()`. Existing `get_post_meta()` calls across the codebase target custom `shop_class`/`shop_announcement`/page post types, which are post-backed and correct.
- **Admin order filter covers both HPOS and legacy.** `OrderLocation::register_hooks()` hooks both `restrict_manage_posts` + `woocommerce_order_list_table_restrict_manage_orders` and applies filters via both `request` and `woocommerce_orders_table_query_clauses` (lines 67-74).
- **Store API extension** in `src/Location/StoreApiExtension.php` uses the public `woocommerce_store_api_register_endpoint_data()` helper with a `function_exists()` guard and extends both `cart` and `checkout` endpoints with a namespaced `gym-core` key and proper JSON schema.
- **Block checkout integration** in `src/Location/BlockIntegration.php` implements `IntegrationInterface`, is registered inside `woocommerce_blocks_loaded` → `woocommerce_blocks_checkout_block_registration` (Plugin.php:548-562), and uses a unique `gym-location` integration name.
- **Strict typing:** 70 of 72 source files declare `strict_types=1` (the remaining two also declare it but with slightly different formatting — see MINOR above). `gym-core.php` and `uninstall.php` also declare it.
- **PSR-4 autoloader:** `composer.json` maps `Gym_Core\\ => src/` and `gym-core.php:37-39` requires `vendor/autoload.php` before any namespaced code is touched. Class path conventions (PascalCase filenames) are explicitly whitelisted in `phpcs.xml.dist:22-24`.
- **Activation / Deactivation hygiene.** `Activator` runs `current_user_can( 'activate_plugins' )` guard, creates DB tables, seeds taxonomy terms, schedules `gym_core_daily_maintenance` cron, and records version. `Deactivator` unschedules all plugin crons and flushes rewrite rules. `uninstall.php` has a `WP_UNINSTALL_PLUGIN` guard, deletes all plugin options, clears cron events, and drops custom tables.
- **Feature declarations use direct static calls** to `FeaturesUtil::declare_compatibility()` inside a `before_woocommerce_init` callback, not wrapped in a deferred class method — which is the correct pattern because the hook fires before the plugin instance is fully loaded.
- **WC version constraints.** Plugin header declares `WC requires at least: 10.3`, and `Activator::check_requirements()` enforces the same 10.3 minimum via `WC_VERSION` compare with a `deactivate_plugins()` + `wp_die()` fallback.
- **Hook prefix discipline.** Every `do_action()` call in `src/` uses a `gym_core_*` prefix (15 of 15 verified): `gym_core_loaded`, `gym_core_attendance_recorded`, `gym_core_foundations_cleared`, `gym_core_rank_changed`-adjacent events, `gym_core_badge_earned`, etc.
- **phpcs.xml.dist configuration** uses `WordPress-Extra` + `WordPress-Docs` as baseline, adds `PHPCompatibilityWP` implicitly via composer, pins PHP target to 8.0+, and enforces the `gym-core` text domain.
- **PHPStan configured at level 6** with `szepeviktor/phpstan-wordpress` extension, proper WC + WP-CLI stubs, and a targeted ignore list (commercial extensions without public stubs are gated by `class_exists()` / `function_exists()` at runtime).
- **Option / meta key prefixes.** All options use `gym_core_*` (`gym_core_settings`, `gym_core_version`, `gym_core_activated`, `gym_core_db_version`, `gym_core_sms_enabled`, `gym_core_briefing_enabled`, `gym_core_gamification_enabled`). Meta keys use `_gym_*` leading-underscore convention (hidden from admin UI) for order, class, and announcement post types.
- **REST namespace** is `gym/v1`, centralized as `BaseController::REST_NAMESPACE` and set on every controller via the abstract constructor — no drift across 14 controllers.
