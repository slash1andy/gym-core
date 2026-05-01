# HMA AI Chat Plugin — Testing Report

**Plugin:** HMA AI Chat v0.5.1
**Report Generated:** 2026-04-30
**Note:** First PHP tool run against current code. The previous report (2026-03-29) covered v0.1.0 and executed only ESLint — PHP tools were unavailable in that sandbox environment.

---

## Overall Status

| Tool | Status | Details |
|------|--------|---------|
| PHPStan level 7 | PASS | 0 errors across 24 files |
| PHP test suite | N/A | No test suite exists (no `tests/` directory, no phpunit.xml) |
| PHPCS (WordPress-Extra) | FAIL | 158 errors, 119 warnings across 24 files; WooCommerce-Sniffs standard not available in test environment |
| ESLint | FAIL | 79 errors in `assets/js/chat-app.js` (76 prettier/formatting, 3 substantive) |

---

## PHPStan — PASS

**Command:** `composer analyse` (`vendor/bin/phpstan analyse --memory-limit=1G`)
**Level:** 7
**Files analysed:** 24
**Result:** 0 errors

PHPStan ran clean at level 7 against all files in `src/`, `hma-ai-chat.php`, and `uninstall.php`. No suppressions were added during this run. The existing `phpstan.neon` suppressions (`missingType.iterableValue`, `missingType.return`, `method.internal`, `staticMethod.internal`) were already in place and account for intentional design choices documented inline.

---

## PHP Test Suite — N/A

No test suite exists. There is no `tests/` directory, no `phpunit.xml`, no `phpunit.xml.dist`, and `composer.json`'s `test` script is a stub (`echo 'No tests defined for hma-ai-chat.'`). This was true at v0.1.0 and remains true at v0.5.1.

New code added since v0.1.0 includes agent provisioning (`Agents/`), Action Scheduler integration, Paperclip webhook handler, run_id pinning, and audit log — all of which are untested. PHPUnit scaffolding is needed before the plugin reaches production.

---

## PHPCS — FAIL

**Command:** `vendor/bin/phpcs --standard=WordPress-Extra hma-ai-chat.php uninstall.php src/`
**Note:** `phpcs.xml.dist` references the `WooCommerce-Sniffs` standard, which is not installed in the plugin's own `vendor/` (it is not in `require-dev`). This run used only `WordPress-Extra`. PHPCS itself was sourced from the adjacent `gym-core` plugin's vendor directory.

**Result:** 158 errors, 119 warnings across 24 files. 176 violations are auto-fixable by `phpcbf`.

This report captures the violation count only — fixing PHPCS violations is out of scope for this run.

**Highest-violation files:**
- `src/Agents/AgentRegistry.php` — 37 errors, 12 warnings
- `src/Data/PendingActionStore.php` — 7 errors, 13 warnings
- `src/Tools/ToolRegistry.php` — 6 errors, 8 warnings

---

## ESLint — FAIL

**Command:** `node_modules/.bin/eslint assets/js/chat-app.js`
**ESLint version:** 8.57.1
**Config:** `.eslintrc.json` — `@wordpress/eslint-plugin/recommended`, ecmaVersion 2020

**Result:** 79 errors, 0 warnings

Breakdown:
- 76 `prettier/prettier` formatting errors (auto-fixable via `--fix`)
- 1 `jsdoc/check-tag-names` — `@returns` should be `@return` per JSDoc preference
- 2 `no-unused-vars` — assigned values never used
- 1 `no-var` — use `let`/`const` instead of `var`

The previous v0.1.0 report showed ESLint passing clean. The 76 prettier errors indicate new code added since v0.1.0 was written without running the formatter. The 3 substantive errors are genuine issues.

---

## Issues Found

### [FOUND-001] ESLint: 76 prettier formatting violations in new v0.5.x code
- **File:** `assets/js/chat-app.js`
- **Issue:** New code added after v0.1.0 was not run through the prettier formatter before commit. 76 errors are auto-fixable.
- **Action required:** Run `node_modules/.bin/eslint --fix assets/js/chat-app.js` and commit the result.

### [FOUND-002] ESLint: `@returns` JSDoc tag (line 273)
- **File:** `assets/js/chat-app.js`
- **Issue:** `@returns` should be `@return` per the `jsdoc/check-tag-names` rule in the WordPress eslint plugin.
- **Action required:** Manual fix — rename to `@return`.

### [FOUND-003] ESLint: 2 unused variables
- **File:** `assets/js/chat-app.js`
- **Issue:** Two variables are assigned but never read (`no-unused-vars`).
- **Action required:** Remove or use the variables.

### [FOUND-004] ESLint: `var` declaration (lines 835, 860, 868)
- **File:** `assets/js/chat-app.js`
- **Issue:** `var` used instead of `let`/`const`.
- **Action required:** Replace with block-scoped declarations.

### [FOUND-005] PHPCS: `WooCommerce-Sniffs` standard missing from `require-dev`
- **File:** `phpcs.xml.dist`, `composer.json`
- **Issue:** `phpcs.xml.dist` references `WooCommerce-Sniffs` but the package is not declared in `composer.json`'s `require-dev`. PHPCS fails to load the config file entirely. Add `woocommerce/woocommerce-sniffs` to `require-dev` or remove the reference if the sniff is not needed for this non-WooCommerce plugin.
- **Action required:** Either add the package or remove the rule from `phpcs.xml.dist`.

### [FOUND-006] No PHP test suite — significant new code is untested
- **Issue:** Agent provisioning, Action Scheduler jobs, Paperclip webhook, run_id pinning, and audit log were all added with no PHPUnit coverage.
- **Action required:** Create `tests/` directory with PHPUnit scaffolding. Priority coverage targets: `AgentRegistry`, `AgentUserManager`, `WebhookValidator`, `PendingActionStore`.

---

## PHPStan Suppressions (existing, no new suppressions added)

| Identifier | Path | Reason |
|-----------|------|--------|
| `missingType.iterableValue` | src/ | Intentional — iterable value types not enforced by design |
| `missingType.return` | src/ | Intentional — return type phpdoc not required project-wide |
| `Function wp_ai_client_prompt not found` | src/ | WP 7.0 future API; call sites gated by `function_exists()` |
| `method.internal` | `hma-ai-chat.php` | Plugin entry file calls internal bootstrap methods by design |
| `staticMethod.internal` | `hma-ai-chat.php` | Same as above |

---

## Environment

- **PHP:** 8.x (darwin)
- **PHPStan:** 2.1.54
- **PHPCS:** sourced from `gym-core` plugin vendor — no dedicated PHPCS in `hma-ai-chat/require-dev`
- **Node.js:** v22.x
- **ESLint:** 8.57.1 with `@wordpress/eslint-plugin` v17
- **Composer:** no lock file committed — dependencies resolved at install time

---

*Report generated 2026-04-30. Valid for 12 hours from generation timestamp.*
