# Gym Core — Plugin Agent Context

> Agent context specific to the gym-core plugin. For monorepo-level context, see the root `CLAUDE.md`.

## Plugin Identity

| Field | Value |
|-------|-------|
| Slug | `gym-core` |
| Name | Gym Core |
| Namespace | `Gym_Core` |
| Text Domain | `gym-core` |
| Constant Prefix | `GYM_CORE_` |
| Hook Prefix | `gym_core_` |
| Option Prefix | `gym_core_` |
| Main File | `gym-core.php` |

## Architecture

**Modular monolith** — all features in one plugin, clearly separated by namespace under `src/`. Each module has its own directory and registers hooks via a `register_hooks()` method called by `Plugin::init()`.

### Bootstrap Flow
1. `gym-core.php` → loads autoloader, declares WC compat, registers activation hooks
2. `plugins_loaded` → verifies WooCommerce active → calls `Plugin::instance()->init()`
3. `Plugin::init()` → loads textdomain, instantiates modules, fires `gym_core_loaded`

### Current Modules

| Module | Directory | Status |
|--------|-----------|--------|
| Location | `src/Location/` | **Complete** — taxonomy, filtering, orders, Blocks, Store API |
| Frontend | `src/Frontend/` | **Complete** — location selector banner |
| Attendance | `src/Attendance/` | **Complete** — check-in kiosk, attendance DB table, milestones |
| Rank | `src/Rank/` | **Complete** — belt definitions, rank store, promotion eligibility |
| Gamification | `src/Gamification/` | **Complete** — badges, streaks, targeted content |
| Sales | `src/Sales/` | **Complete** — tablet kiosk at `/sales/`, sliding-discount pricing, order builder, lead capture |
| SMS | `src/SMS/` | **Complete** — Twilio client, inbound handler, message templates |
| Member | `src/Member/` | **Complete** — content gating, dashboard, contact relationships |
| Integrations | `src/Integrations/` | **Complete** — FormToCrm, CRM sync, AutomateWoo triggers |
| Admin | `src/Admin/` | **Complete** — settings, staff dashboard, promotion dashboard, attendance dashboard |
| API | `src/API/` | **Complete** — 12 REST controllers under `gym/v1` |
| Data | `src/Data/` | **Complete** — table manager for custom attendance/rank tables |
| Utilities | `src/Utilities/` | **Complete** — Brand helper |

### Naming Patterns

- **Taxonomy**: `gym_location`
- **CPT** (future): `gym_class`
- **Custom tables** (future): `{prefix}gym_attendance`, `{prefix}gym_ranks`, etc.
- **REST namespace**: `gym/v1`
- **Meta keys**: `_gym_location`, `_gym_sales_kiosk`, `_gym_base_total`, `_gym_*`
- **Cookies**: `gym_location`
- **User meta**: `gym_location`
- **AJAX actions**: `gym_set_location`
- **Cron hooks**: `gym_core_daily_maintenance`
- **Options**: `gym_core_settings`, `gym_core_version`, `gym_core_activated`
- **CSS classes**: `gym-*` (e.g., `gym-location-selector`, `gym-sales-kiosk`)
- **JS globals**: `gymLocation`, `gymKiosk`, `gymSalesKiosk`
- **Script/style handles**: `gym-location-selector`, `gym-kiosk`, `gym-sales-kiosk`
- **Rewrite endpoints**: `/check-in/` (attendance kiosk), `/sales/` (sales kiosk)
- **Custom capabilities**: `gym_promote_student`, `gym_process_sale`, etc. (see `Capabilities.php`)

## Development

```bash
composer install          # Install dev dependencies
composer lint             # PHPCS
composer lint-fix         # PHPCBF auto-fix
composer phpstan          # PHPStan level 6
composer test-unit        # Unit tests
composer test-all         # Lint + PHPStan + unit tests
npm install               # JS dev dependencies
npm run build             # Bundle assets
npm run lint:js           # ESLint
```

## Key Files

| File | Purpose |
|------|---------|
| `gym-core.php` | Plugin header, constants, WC compat declarations, bootstrap |
| `src/Plugin.php` | Singleton, module wiring, text domain |
| `src/Activator.php` | Requirements check, default options, cron, taxonomy seeding |
| `src/Deactivator.php` | Cron cleanup, rewrite flush |
| `uninstall.php` | Option deletion, cron cleanup, future table drops |

## Conventions

1. **HPOS-only** — `$order->get_meta()` / `$order->update_meta_data()`, never `get_post_meta()` for orders
2. **No jQuery** — vanilla JS only
3. **Constructor injection** — dependencies injected, no service container
4. **`register_hooks()`** — each module class exposes this, called by Plugin
5. **Strict types** — `declare(strict_types=1)` in every PHP file
6. **Tests** — Brain\Monkey for WP function mocking, Mockery for objects
