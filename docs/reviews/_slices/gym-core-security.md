# gym-core v1.0.0 — Security Slice

## Summary

Security posture is solid. All 17 `$wpdb` callers use `prepare()` correctly, every REST route validated has a real `permission_callback`, the Twilio webhook does HMAC-SHA1 signature validation with `hash_equals()`, and admin dashboards consistently escape output. No SQL injection, no CSRF, no secret leakage observed.

Counts: blocker=0, major=0, minor=2, nit=3.

## Findings

### MINOR: TwiML webhook response echoes XML body without final escape validation
- **File:** src/SMS/InboundHandler.php:77
- **Category:** XSS / Webhook
- **Issue:** `rest_pre_serve_request` handler emits `echo $result->get_data();` with a phpcs ignore comment asserting the TwiML is "already escaped via esc_xml()". That claim is true for the `twiml_response()` helper path (line 243 escapes via `esc_xml()`). However, this filter is registered globally on `rest_pre_serve_request` and matches any REST response whose `Content-Type` header starts with `application/xml`. Any future plugin route returning XML would be emitted raw through this echo. Low exploitability today (only InboundHandler emits XML via REST), but the filter is more permissive than its docblock claims.
- **Fix:** Narrow the filter to match only the Twilio webhook route (e.g., check `$request->get_route() === '/gym/v1/sms/webhook'`) before bypassing JSON encoding. Or call `esc_xml()` on the output unconditionally as a defense-in-depth backstop.

### MINOR: Location label interpolation unescaped in OrderLocation admin
- **File:** src/Location/OrderLocation.php:254-259
- **Category:** XSS
- **Issue:** `$label = $labels[$location] ?? esc_html($location);` — the fallback path escapes but the primary path does not. The `%s` in `printf('<p class="gym-order-location"><strong>%s</strong> %s</p>', ..., esc_html(__), esc_html($label))` — wait, looking again, the second `%s` is wrapped in `esc_html($label)`, so this is actually safe. This finding is withdrawn on re-read.
- **Status:** Withdrawn after second look. Both `%s` placeholders are escaped.

### MINOR: `gym_location` cookie fallback in KioskEndpoint trusts client input for location without validation
- **File:** src/Sales/KioskEndpoint.php:396-401
- **Category:** Auth / Validation
- **Issue:** `get_kiosk_location()` reads `$_COOKIE['gym_location']` and passes it through `sanitize_text_field()` only — it is not validated against `Taxonomy::is_valid()` before being written to the kiosk data attribute (`data-location`) and used for product filtering. A manipulated cookie could set an invalid location string. `sanitize_text_field` does not enforce the taxonomy allowlist, so bogus location values could end up in the rendered body attribute and JS config. Impact is limited because `SalesController::get_products` re-validates with `Taxonomy::is_valid()` before using the value in a query, and the data attribute is `esc_attr()`-escaped. Low severity but worth tightening.
- **Fix:** After sanitizing the cookie value, validate with `Taxonomy::is_valid( $cookie_location )` and fall back to the default location if it does not match.

### NIT: `zbsc_status` string from user input is used as a column filter without allowlist
- **File:** src/API/CrmController.php:341-344
- **Category:** SQL / Auth
- **Issue:** The `/gym/v1/crm/contacts` endpoint accepts an arbitrary `status` query param and passes it straight to `zbsc_status = %s` in the WHERE clause. `%s` placeholder makes it SQL-safe, and the endpoint is gated by `gym_process_sale` capability. Risk is limited to an authenticated staff user being able to probe the CRM for arbitrary status strings (information leak at most). Not exploitable, but status values are a known, small set — they could be allowlisted.
- **Fix:** Optional hardening — introduce a `sanitize_callback` that maps input to a known enum of Jetpack CRM status values, or leave as-is since SQL injection is not possible through `%s`.

### NIT: SMSController inline SQL uses interpolated table name with phpcs suppression comment shape
- **File:** src/API/SMSController.php:237-249
- **Category:** SQL (defense-in-depth)
- **Issue:** The `get_conversation_history` query interpolates `{$table}` into the SQL string before calling `$wpdb->prepare()`. Because `$table` is derived from `$wpdb->prefix . 'zbs_logs'` (trusted source), this is safe. However, the pattern of building the SQL string with interpolation first, then calling prepare with `%d`, is brittle — a future edit that accidentally adds a `$user_input` interpolation into the same string would break. The phpcs `InterpolatedNotPrepared` ignore comment visually masks this risk.
- **Fix:** Not required. If hardening is desired, use a query builder helper that separates table/column names from value placeholders.

### NIT: Twilio webhook URL reconstruction relies on an un-pinned `X-Forwarded-Proto` header
- **File:** src/SMS/InboundHandler.php:125-138
- **Category:** Webhook / Auth
- **Issue:** When `gym_core_twilio_webhook_url` option is not set, the code reconstructs the URL from `rest_url()` and then upgrades the scheme to `https` if `$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'`. On hosts that do not strip client-set forwarded headers, a malicious client could manipulate this value to cause the computed signature URL to mismatch Twilio's signed URL, resulting in all webhook requests being rejected (DoS of inbound SMS, not RCE/spoof). The positive case — constructing an `http://…` URL that Twilio signed as `https://…` — cannot be used to forge a signature because the HMAC input would not match.
- **Fix:** Recommend setting `gym_core_twilio_webhook_url` explicitly (already supported). Consider failing closed and logging when the option is empty rather than relying on header reconstruction.

## Verified-Clean

- **SQL injection:** All 17 `$wpdb` callers reviewed (AttendanceStore, AttendanceDashboard, PromotionDashboard, FoundationsController, ClassRosterController, CrmController, SMSController, OrderController, OrderLocation, CrmContactSync, TableManager) use `$wpdb->prepare()` with proper `%d`/`%s` placeholders. Table names come from `$wpdb->prefix` or constant strings, never from user input.
- **Attendance_List_Table (AttendanceDashboard.php:1140-1168):** The dynamic `ORDER BY $orderby $order` construction is gated by a hard allowlist (`in_array($req_orderby, $allowed_orderby, true)`) and `in_array($req_order, array('ASC','DESC'), true)` — safe.
- **Twilio signature validation:** `TwilioClient::validate_webhook_signature()` implements HMAC-SHA1 of sorted POST params, uses `hash_equals()` for timing-safe comparison, and rejects missing-token/missing-signature cases with 403. Correct implementation of Twilio's documented algorithm.
- **Twilio credentials:** Auth token, account SID, and from number are read with constant precedence over option (`GYM_CORE_TWILIO_*` defined in wp-config preferred). Credentials are never echoed to admin HTML; error messages only expose Twilio API's own error messages, not auth data. Settings page uses `type => 'password'` for the token field.
- **REST permission callbacks (spot-checked 5/46):**
  - `SMSController::permissions_send_sms` — checks `manage_options || gym_send_sms`, real enforcement.
  - `OrderController::permissions_subscription_status` — tiered cap check with fallback error.
  - `FoundationsController::permissions_view` — own-data-or-capability pattern, correct.
  - `SalesController::permissions_sales` — logged-in + `gym_process_sale || manage_woocommerce`.
  - `CrmController::permissions_crm` — `gym_process_sale || manage_woocommerce`.
  - None rely on `__return_true` for authenticated routes. Only InboundHandler uses `__return_true` and that is correct (it auth's via Twilio signature, which is validated inside the callback).
- **CSRF on admin forms:**
  - `PromotionDashboard::handle_bulk_actions` — verifies `_wpnonce_bulk` with `wp_verify_nonce` and capability `gym_promote_student`; `wp_die` on failure.
  - `PromotionDashboard::render_bulk_confirmation` — nonce re-verified before render; cap re-checked.
  - `AttendanceDashboard::ajax_quick_checkin` — `check_ajax_referer` + `gym_check_in_member` cap.
  - `AttendanceDashboard::ajax_member_search` — `check_ajax_referer` + capability.
  - `PromotionDashboard::ajax_recommend` / `ajax_promote` — `check_ajax_referer(self::NONCE_ACTION)` + `gym_promote_student` cap.
- **Capability gates on destructive actions:** Bulk promotion, single-user promotion, Foundations clear, staff pay-for-order, and refund issuance all require a specific capability (`gym_promote_student`, `gym_process_sale`, or `manage_woocommerce`). No destructive action is gated only by "logged in".
- **Pay-for-order capability escalation (KioskEndpoint:360-383):** The `user_has_cap` filter grants `pay_for_order` only when (a) the user has `gym_process_sale` or `manage_woocommerce`, AND (b) the target order has the `gym_kiosk_origin` meta set to `'1'`. Scope is appropriately narrow — it cannot escalate pay-for-order on arbitrary customer orders.
- **Admin template escaping:** AttendanceDashboard, PromotionDashboard, StaffDashboard, OrderLocation, Settings, and KioskEndpoint's render methods consistently use `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, or `selected()` for dynamic output. Inline JS in AttendanceDashboard's typeahead does its own DOM-safe text insertion via `$('<span>').text(m.name).html()`.
- **Inbound SMS body handling:** `wp_kses( $params['Body'] ?? '', array() )` strips all HTML before storing, preventing stored-XSS via inbound Twilio payloads. Phone numbers go through `sanitize_text_field`.
- **Opt-out handling (TCPA):** `handle_opt_out` fires a documented action hook rather than performing destructive DB ops directly — consumers are responsible for writing preferences. Low risk from the webhook itself.
- **Foundations `get_active` query:** Uses `$wpdb->usermeta` with prepared `meta_key` and `meta_value` placeholders.
