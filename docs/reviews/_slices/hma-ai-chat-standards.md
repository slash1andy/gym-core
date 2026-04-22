# hma-ai-chat v0.4.0 — WP Standards + Frontend Slice

Scope: coding standards, WP conventions, and frontend only. Security, performance, and AI-safety are out of scope for this slice.

## Summary

- BLOCKER: 0
- MAJOR: 2
- MINOR: 4
- NIT: 3

The plugin is in good overall shape for WP standards. Prefixing, textdomain discipline, i18n, PSR-4 autoloader wiring, `dbDelta` usage, and deactivation cleanup are all correct. The two MAJOR items are both in the admin chat app frontend: (1) the REST nonce is localized but never wired into `wp.apiFetch`, so any non-admin user with `edit_posts` would hit 403s as soon as cookie auth alone isn't enough; and (2) the assistant message region has no `aria-live`, so screen-reader users get no announcement when a reply arrives.

## Findings

### MAJOR: REST nonce never wired into apiFetch middleware
- **File:** `assets/js/chat-app.js:1-16`, `src/Admin/ChatPage.php:60-73`
- **Category:** Nonce
- **Issue:** `ChatPage::enqueue_assets()` localizes `hmaAiChat.nonce = wp_create_nonce( 'wp_rest' )`, but the JS never calls `wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( config.nonce ) )` and `wpApiSettings` is not localized either. Because `apiFetch` has no `X-WP-Nonce` source, REST calls rely entirely on the admin session cookie. That works for the current admin-only use case, but the moment a non-admin `edit_posts` user (Editor, Author) loads the panel — or a logged-in session goes stale — every message/action/heartbeat request will 403 with `rest_cookie_invalid_nonce`. This is a latent failure, not a theoretical one.
- **Fix:** Add `wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( config.nonce ) );` immediately after the `config` assignment in `chat-app.js` (before any `wp.apiFetch` call). Optionally also call `createRootURLMiddleware` so endpoints can be passed as relative paths. No server-side change needed.

### MAJOR: No aria-live region on assistant message stream
- **File:** `assets/js/chat-app.js:60` (`#hma-messages` container), `assets/js/chat-app.js:228-232` (message render)
- **Category:** a11y
- **Issue:** The chat messages container has no `aria-live` or `role="log"` attribute, and individual message nodes are appended silently. Screen-reader users will hear the "Send" button click and then get nothing until they navigate back into the messages region — they have no indication that an assistant reply has arrived. This is the single most impactful a11y gap in an otherwise well-labelled UI.
- **Fix:** Add `role="log" aria-live="polite" aria-relevant="additions" aria-atomic="false"` to the `#hma-messages` element in `renderChatPanel()`. The typing indicator should stay outside the live region (or use `aria-busy`) to avoid noisy announcements of the dots.

### MINOR: uninstall.php missing `declare(strict_types=1)`
- **File:** `uninstall.php:1-8`
- **Category:** Typing
- **Issue:** Every other PHP file in the plugin — 23 of 24 — declares `strict_types=1`. `uninstall.php` is the only outlier. Uninstall already does dangerous things (dropping tables, deleting users, removing roles); strict typing is exactly where you want it.
- **Fix:** Add `declare(strict_types=1);` directly below the opening `<?php`, before the `WP_UNINSTALL_PLUGIN` guard.

### MINOR: No `aria-live` / `role="status"` on action notices
- **File:** `assets/js/chat-app.js:538-552` (`showActionNotice`)
- **Category:** a11y
- **Issue:** Approval/rejection result toasts ("Action approved. Executing now.", "Action rejected and discarded.") are appended to the DOM as plain `<div>`s with no live-region semantics. They auto-dismiss after 3 seconds, so a screen-reader user has no way to know the outcome of an approval click.
- **Fix:** Add `role="status"` (or `role="alert"` for errors) to the notice element in `showActionNotice()`. For error notices, also consider `aria-live="assertive"`.

### MINOR: Asset enqueue couples to gym-core hook suffixes by string
- **File:** `src/Plugin.php:216-220`
- **Category:** Enqueue
- **Issue:** `enqueue_admin_scripts()` gates on `array( 'gym_page_hma-ai-chat', 'toplevel_page_gym-core' )`. These hook suffixes are owned by gym-core's `add_menu_page`/`add_submenu_page` calls, not by this plugin. If gym-core renames its menu slug from `gym-core` to something else (or moves chat off `gym-core`), this plugin silently stops loading its chat assets with no failure signal. SettingsPage registers its submenus with `add_submenu_page( 'gym-core', ... )` (line 69, 79) — same tight coupling.
- **Fix:** Prefer gating on a hook suffix this plugin owns (e.g., register the chat submenu here and store the returned hook). If the gym-core coupling must stay, add a brief code comment pointing to the gym-core file that defines those slugs so future maintainers don't chase phantom bugs.

### MINOR: Inline `<style>` block inside `AuditLogPage::render_page()`
- **File:** `src/Admin/AuditLogPage.php:191-236`
- **Category:** Enqueue
- **Issue:** ~45 lines of CSS are printed inline inside the page render. That's not registered via `wp_enqueue_style`, so it's uncacheable, it won't be processed by any RTL/minification pipeline, and it can't be overridden via `wp_styles`. It also can't be reused on other audit-adjacent screens.
- **Fix:** Move into `assets/css/audit-log.css`, enqueue from a dedicated `AuditLogPage::enqueue_assets()` method triggered by the audit page hook suffix returned from `add_submenu_page()`.

### NIT: Agent icon/name interpolated into `innerHTML` without JS-side escape
- **File:** `assets/js/chat-app.js:50-53` (agent dropdown option template)
- **Category:** a11y / defense-in-depth
- **Issue:** `${agent.icon} ${agent.name}` is dropped directly into a backtick template literal that assigns to `innerHTML`. Today the icon is an emoji constant set by the agent class and the name is run through `sanitize_text_field` server-side, so it's effectively safe. But the rest of this file carefully uses `escapeHtml()` for every user-controlled string, so this is an inconsistency worth normalizing — a future change that lets staff edit icon via settings would immediately become XSS.
- **Fix:** Wrap `agent.icon` and `agent.name` in `escapeHtml()` at the template site. Same in `addMessage()` for timestamps (belt-and-braces; `toLocaleTimeString` output is already safe).

### NIT: `var` used inside `renderMarkdown()` while the rest of the file uses `const`/`let`
- **File:** `assets/js/chat-app.js:643` (and inner functions in the same block)
- **Category:** Coding standards
- **Issue:** The `renderMarkdown` function uses `var s = escapeHtml(text);` and `var items = ...` inside the block-scoped replacers. The rest of the file (and the no-jQuery modernization stance) consistently uses `const`/`let`.
- **Fix:** Replace `var` with `let`/`const`. No behavior change; just consistency.

### NIT: No `prefers-reduced-motion` guard on typing-dot animation
- **File:** `assets/css/chat-app.css` (typing-dot keyframes + bubble fade-in at line 489)
- **Category:** a11y
- **Issue:** The typing indicator runs a continuous dot animation and notice toasts run a `hma-fade-in` keyframe. Users with `prefers-reduced-motion: reduce` aren't accommodated.
- **Fix:** Add `@media (prefers-reduced-motion: reduce) { .hma-ai-typing-dot, .hma-ai-action-notice { animation: none !important; } }`.

## Verified-Clean

- **Plugin header + text domain:** `Text Domain: hma-ai-chat`, `Domain Path: /languages` are both present in `hma-ai-chat.php:10-11`. `load_plugin_textdomain( 'hma-ai-chat', false, … )` fires on `plugins_loaded` via `hma_ai_chat_init()` (line 71).
- **PSR-4 autoloader wiring:** `composer.json` maps `HMA_AI_Chat\\` → `src/`. `hma-ai-chat.php:28-30` conditionally requires `vendor/autoload.php`. `class_exists( 'HMA_AI_Chat\\Plugin' )` guard prevents fatals if vendor is missing.
- **Prefix discipline:** All options (`hma_ai_chat_*`), user-meta keys, REST route (`hma-ai-chat/v1`), cron hook (`hma_ai_chat_purge_conversations`), CSS classes (`.hma-ai-*`), and JS identifiers (`hmaAiChat`, `hma-*` element IDs) are consistently prefixed. `phpcs.xml.dist` enforces this via `WordPress.NamingConventions.PrefixAllGlobals`.
- **i18n in admin pages:** Every admin-facing string in `ChatPage`, `SettingsPage`, and `AuditLogPage` is wrapped in `esc_html__` / `esc_attr__` / `__` with the `hma-ai-chat` text domain. Plural forms use `_n()` correctly (e.g., `SettingsPage.php:665-669`, `AuditLogPage.php:105`). Translator comments are present for sprintf placeholders.
- **Strict typing (23/24 files):** Only `uninstall.php` is missing `declare(strict_types=1)` — see MINOR above. Note: some files use `declare( strict_types=1 )` with interior spaces (e.g., `src/Tools/ToolRegistry.php:13`); this is valid PHP syntax, just a stylistic variance.
- **No jQuery:** `assets/js/chat-app.js` uses only vanilla DOM APIs (`document.getElementById`, `addEventListener`, `querySelectorAll`, etc.). No `$()` or `jQuery()` calls anywhere in the plugin. Deps array for the script is `array( 'wp-api-fetch' )` — no jQuery dep.
- **DB schema + versioning:** `Activator::create_tables()` creates all three tables (`hma_ai_conversations`, `hma_ai_messages`, `hma_ai_pending_actions`) using `dbDelta` with `$wpdb->get_charset_collate()` and stores `hma_ai_chat_db_version` for future migrations. Table names use `$wpdb->prefix`.
- **Deactivation:** `Deactivator::deactivate()` clears the `PURGE_CRON_HOOK` scheduled event via `wp_clear_scheduled_hook`. Uninstall additionally drops tables, removes the `hma_ai_agent` role, deletes agent users, and purges all plugin options.
- **Conditional asset load:** `enqueue_admin_scripts()` returns early if the hook suffix isn't in the allowlist, so chat JS/CSS only loads on the Gandalf admin screens (see MINOR note about coupling fragility).
- **Asset versioning:** Both `wp_enqueue_style` and `wp_enqueue_script` pass `HMA_AI_CHAT_VERSION` for cache-busting.
- **Admin UI interactive controls have `aria-label`:** Agent selector, message input, send button, approve/reject/approve-with-changes buttons, per-action checkboxes, bulk select-all, and the two expand-form textareas all have descriptive `aria-label` attributes.
- **Focus indicators:** Action buttons (`assets/css/chat-app.css:443-446`) have a visible 2px outline on focus. Input/textarea/send-button use `outline: none` but replace with a `box-shadow` ring — acceptable WCAG alternative.
- **AuditLog semantic HTML:** Uses real `<table>` + `<thead>` + `<tbody>`, native `<select>` for filters, WP core `paginate_links()` for pagination, and `wp_kses_post()` when echoing the status-badge HTML. No divitis.
- **Nonce on secret rotation:** `SettingsPage::handle_secret_rotation()` uses `check_admin_referer( 'hma_rotate_secret' )` and `current_user_can( 'manage_options' )` before touching the webhook secret.
