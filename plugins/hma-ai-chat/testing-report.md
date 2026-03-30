# WooCommerce Plugin Testing Report

**Plugin:** HMA AI Chat v0.1.0
**Report Generated:** 2026-03-29T22:20:28Z
**Overall Status:** ⚠️ PARTIAL — PHP tools unavailable in sandbox; JS lint passes clean

---

## Test Results

| Phase | Tool | Command | Status | Details |
|-------|------|---------|--------|---------|
| 1 | PHP Lint | `php -l` | ⚠️ MANUAL REVIEW | PHP CLI not available. 13 PHP files reviewed manually — 0 syntax errors found |
| 2 | PHPCS | `phpcs --standard=phpcs.xml.dist` | ⚠️ NOT RUN | PHP CLI required. `phpcs.xml.dist` created with WordPress-Extra, WordPress-Docs, WooCommerce-Sniffs rulesets |
| 3 | PHPStan | `phpstan analyse --level 7` | ⚠️ NOT RUN | PHP CLI required. `phpstan.neon` created at level 7 with WordPress stubs |
| 4 | PHPUnit (unit) | `phpunit --testsuite unit` | ⚠️ NOT RUN | PHP CLI required. No test files exist yet |
| 4 | PHPUnit (integration) | `phpunit --testsuite integration` | ⚠️ NOT RUN | PHP CLI required. No test files exist yet |
| 4 | Code Coverage | `--coverage-text` | ⚠️ NOT RUN | PHP CLI required |
| 5 | ESLint | `eslint assets/js/` | ✅ PASS | 0 errors, 0 warnings across 1 JS file |

---

## Issues Found and Fixed (WordPress Standards Audit)

### [FIXED-001] REST routes never registered — hooks added too late
- **Phase:** Manual PHP review (Architecture)
- **Files:** `src/Plugin.php`, `src/API/MessageEndpoint.php`, `src/API/HeartbeatEndpoint.php`
- **Issue:** Both REST endpoint classes registered `rest_api_init` hooks in their constructors, but were instantiated *from* `rest_api_init` in `Plugin::register_rest_routes()`. The hook had already fired, so `register_route()` never ran — zero REST endpoints were actually available.
- **Fix Applied:** Removed `rest_api_init` hook from endpoint constructors. `Plugin::register_rest_routes()` now calls `->register_route()` directly on each endpoint instance during `rest_api_init`.

### [FIXED-002] Duplicate ChatPage instances created on every admin page load
- **Phase:** Manual PHP review (Architecture)
- **File:** `src/Plugin.php`
- **Issue:** `enqueue_admin_scripts()` created `new Admin\ChatPage()` on every call, which re-registered `admin_menu` hooks as a side effect. Each admin page load could add duplicate menu entries.
- **Fix Applied:** Added `$chat_page` property to Plugin class. Instance is created once in `register_hooks()` and reused in `enqueue_admin_scripts()`.

### [FIXED-003] PendingActionStore::reject_action format array mismatch
- **Phase:** Manual PHP review (Security/Data Integrity)
- **File:** `src/Data/PendingActionStore.php`
- **Issue:** When a rejection reason was provided, `$data` gained a 4th key (`action_data`) but the `$format` array was hardcoded to 3 entries (`'%s', '%s', '%d'`). `$wpdb->update()` would silently use wrong format specifiers, potentially causing data corruption.
- **Fix Applied:** Made `$format` a variable array that gets `'%s'` appended when `action_data` is added, so format always matches data.

### [FIXED-004] WebhookValidator double-hashing in token comparison
- **Phase:** Manual PHP review (Security)
- **File:** `src/Security/WebhookValidator.php`
- **Issue:** `validate_request()` used `hash_equals( wp_hash($secret), wp_hash($provided_token) )`. Both values were already hashed secrets, so the additional `wp_hash()` added a pointless layer. Worse, if `wp_hash()` had any collision characteristics, it could weaken the comparison.
- **Fix Applied:** Changed to `hash_equals( $secret, $provided_token )` — still constant-time, comparing the stored and provided tokens directly.

### [FIXED-005] Webhook secret generation used `microtime()` with cryptographic randomness
- **Phase:** Manual PHP review (Security)
- **File:** `src/Security/WebhookValidator.php`
- **Issue:** `generate_secret()` used `hash('sha256', wp_generate_password(32, true, true) . microtime(true))`. Appending `microtime()` to already-cryptographic random bytes adds no entropy and introduces a deterministic component.
- **Fix Applied:** Changed to `bin2hex( random_bytes( 32 ) )` — pure CSPRNG output, 64 hex characters. Also stores with `autoload = false` since secrets shouldn't load on every page.

### [FIXED-006] IP validation trusted spoofable HTTP headers
- **Phase:** Manual PHP review (Security)
- **File:** `src/Security/WebhookValidator.php`
- **Issue:** `get_client_ip()` checked `HTTP_CLIENT_IP` and `HTTP_X_FORWARDED_FOR` before `REMOTE_ADDR`. These headers are trivially spoofable by any HTTP client, allowing IP allowlist bypass.
- **Fix Applied:** Changed to use `REMOTE_ADDR` only. Added docblock explaining the security rationale and that proxy environments should configure REMOTE_ADDR at the proxy level.

### [FIXED-007] REST method strings instead of WP_REST_Server constants
- **Phase:** Manual PHP review (Standards)
- **Files:** `src/API/MessageEndpoint.php`, `src/API/HeartbeatEndpoint.php`
- **Issue:** Used string `'POST'` for methods parameter. WordPress standards require `WP_REST_Server::CREATABLE` / `READABLE` / etc.
- **Fix Applied:** Changed to `WP_REST_Server::CREATABLE` and added `use WP_REST_Server` import.

### [FIXED-008] Missing `validate_callback` on HeartbeatEndpoint args
- **Phase:** Manual PHP review (Standards)
- **File:** `src/API/HeartbeatEndpoint.php`
- **Issue:** All 4 endpoint arguments had `sanitize_callback` but no `validate_callback`. WordPress REST API best practice is to include both.
- **Fix Applied:** Added `'validate_callback' => 'rest_validate_request_arg'` to all args.

### [FIXED-009] Missing `@since` tags across all classes and methods
- **Phase:** Manual PHP review (Standards)
- **Files:** All PHP files in `src/`
- **Issue:** WordPress Docs standards require `@since` tags on all classes, methods, and hook documentation.
- **Fix Applied:** Added `@since 0.1.0` to all class docblocks and public method docblocks.

### [FIXED-010] ESLint — 118 errors in chat-app.js
- **Phase:** ESLint (Phase 5)
- **File:** `assets/js/chat-app.js`
- **Issue:** 110 prettier/formatting errors (WordPress JS uses tabs, specific spacing conventions), 5 missing JSDoc `@param` type annotations, `no-console` violations without justification, `no-alert` violations, and 1 `@wordpress/no-unused-vars-before-return` violation.
- **Fix Applied:** Auto-fixed 110 formatting issues via `--fix`. Added JSDoc `{type}` annotations to all `@param` tags. Added inline `eslint-disable-next-line` with justification comments for intentional `console.error`/`console.debug`/`alert` usage. Moved `sendBtn` assignment below early return to satisfy `no-unused-vars-before-return`.

---

## PHPStan Suppressions

None present.

## PHPCS Suppressions

None present. (PHPCS has not been run yet — config file created for future use.)

## ESLint Suppressions

| File | Line | Rule | Justification |
|------|------|------|---------------|
| `chat-app.js` | 14 | `no-console` | Required for debugging missing localized config |
| `chat-app.js` | 168 | `no-console` | User-facing error logging for failed API calls |
| `chat-app.js` | 232 | `no-console` | Debug-level logging for non-critical pending actions feature |
| `chat-app.js` | 300 | `no-console` | User-facing error logging for approve action failure |
| `chat-app.js` | 301 | `no-alert` | Fallback notification for approval failure (temporary until toast system) |
| `chat-app.js` | 319 | `no-console` | User-facing error logging for reject action failure |
| `chat-app.js` | 320 | `no-alert` | Fallback notification for rejection failure (temporary until toast system) |

---

## Tooling Config Files Created

- `phpcs.xml.dist` — WordPress-Extra + WordPress-Docs + WooCommerce-Sniffs, text domain `hma-ai-chat`, prefixes `hma_ai_chat`/`HMA_AI_Chat`, PHP 8.0+ testVersion
- `phpstan.neon` — Level 7, WordPress stubs via `szepeviktor/phpstan-wordpress`
- `.eslintrc.json` — `@wordpress/eslint-plugin/recommended`, globals for `hmaAiChat` and `wp`

---

## Notes

- **PHP runtime unavailable** in this sandbox environment. Phases 1-4 (PHP Lint, PHPCS, PHPStan, PHPUnit) could not be executed. A comprehensive manual code review was performed instead against the `wordpress-standards` skill checklist.
- **Node.js version:** v22.22.0
- **ESLint version:** 8.x with `@wordpress/eslint-plugin` v17
- **No tests directory exists yet.** PHPUnit test scaffolding should be created before the plugin reaches beta. Coverage targets: 80%+ on business logic, 100% on `WebhookValidator` and `PendingActionStore` (security/data paths).
- **Next steps to achieve full pass:** Install PHP 8.0+ runtime, run `composer install`, then execute `vendor/bin/phpcs`, `vendor/bin/phpstan`, and `vendor/bin/phpunit` against the configs created in this report.

---

*This report is valid for 12 hours from the timestamp above.*
*The woocommerce-finalize skill will reject reports older than 12 hours.*
