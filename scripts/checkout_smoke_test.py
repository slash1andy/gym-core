#!/usr/bin/env python3
"""
checkout_smoke_test.py — M1.8 Checkout Flow Smoke Test

Validates that the WooCommerce checkout infrastructure on Pressable is correctly
configured by querying the WooCommerce Store API, WordPress REST API, and
Pressable management API.

The WC Store API and WP REST API are synchronous and public — no WooCommerce
credentials needed.  Pressable API is used only for plugin list and fire-and-forget
WP-CLI checks (async endpoint — we verify acceptance, not output).

Usage:
    python3 scripts/checkout_smoke_test.py
    python3 scripts/checkout_smoke_test.py --dry-run
    python3 scripts/checkout_smoke_test.py --json
"""

import argparse
import json
import os
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime

# ---------------------------------------------------------------------------
# Site config
# ---------------------------------------------------------------------------
SITE_DOMAIN = "haanpaa-staging.mystagingwebsite.com"
STORE_API_BASE = f"https://{SITE_DOMAIN}/wp-json/wc/store/v1"
WP_API_BASE = f"https://{SITE_DOMAIN}/wp-json/wp/v2"
WP_ROOT = f"https://{SITE_DOMAIN}/wp-json/"

# ---------------------------------------------------------------------------
# Pressable API config (credentials from env vars)
# ---------------------------------------------------------------------------
PRESSABLE_TOKEN_URL = "https://my.pressable.com/auth/token"
PRESSABLE_SITE_ID = "1630891"
PRESSABLE_PLUGINS_URL = f"https://my.pressable.com/v1/sites/{PRESSABLE_SITE_ID}/plugins"
PRESSABLE_WPCLI_URL = f"https://my.pressable.com/v1/sites/{PRESSABLE_SITE_ID}/wordpress/wpcli"

# Expected content pages (11)
EXPECTED_PAGES = [
    "Home",
    "About",
    "Schedule",
    "Fitness Kickboxing",
    "Kids",
    "Personal Training",
    "Contact",
    "Blog",
    "Free Trial",
    "Shop",
    "My Account",
]

# Expected product categories
EXPECTED_CATEGORIES = ["Memberships", "Trials", "Retail", "Class Passes"]


# ---------------------------------------------------------------------------
# HTTP helpers
# ---------------------------------------------------------------------------
_SSL_CTX = ssl.create_default_context()


def _get_json(url, headers=None, timeout=30):
    """GET a URL and return parsed JSON."""
    req = urllib.request.Request(url, headers=headers or {}, method="GET")
    resp = urllib.request.urlopen(req, timeout=timeout, context=_SSL_CTX)
    return json.loads(resp.read())


def _get_json_safe(url, headers=None, timeout=30):
    """GET a URL; return (data, None) on success or (None, error_str) on failure."""
    try:
        data = _get_json(url, headers=headers, timeout=timeout)
        return data, None
    except urllib.error.HTTPError as exc:
        return None, f"HTTP {exc.code}"
    except urllib.error.URLError as exc:
        return None, str(exc.reason)
    except Exception as exc:
        return None, str(exc)


def store_api(path, timeout=30):
    """GET from the WC Store API. Returns (data, error)."""
    url = f"{STORE_API_BASE}/{path.lstrip('/')}"
    return _get_json_safe(url, timeout=timeout)


def wp_api(path, timeout=30):
    """GET from the WP REST API. Returns (data, error)."""
    url = f"{WP_API_BASE}/{path.lstrip('/')}"
    return _get_json_safe(url, timeout=timeout)


# ---------------------------------------------------------------------------
# Pressable helpers
# ---------------------------------------------------------------------------

def get_pressable_token():
    """Obtain a Pressable API bearer token from env-var credentials."""
    client_id = os.environ.get("PRESSABLE_CLIENT_ID", "")
    client_secret = os.environ.get("PRESSABLE_CLIENT_SECRET", "")
    if not client_id or not client_secret:
        raise RuntimeError(
            "Set PRESSABLE_CLIENT_ID and PRESSABLE_CLIENT_SECRET env vars"
        )
    req = urllib.request.Request(
        PRESSABLE_TOKEN_URL,
        data=urllib.parse.urlencode({
            "grant_type": "client_credentials",
            "client_id": client_id,
            "client_secret": client_secret,
        }).encode(),
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    resp = urllib.request.urlopen(req, timeout=30, context=_SSL_CTX)
    return json.loads(resp.read())["access_token"]


def pressable_get_plugins(token):
    """GET /v1/sites/{id}/plugins — returns list of plugin dicts."""
    data = _get_json(
        PRESSABLE_PLUGINS_URL,
        headers={"Authorization": f"Bearer {token}"},
        timeout=30,
    )
    # Pressable wraps in {"data": [...]} typically
    if isinstance(data, dict) and "data" in data:
        return data["data"]
    if isinstance(data, list):
        return data
    return []


def pressable_wpcli_fire(token, command):
    """Fire a WP-CLI command via Pressable. The endpoint is async — it returns
    a job ID, not the result. We check only that the request was accepted
    (HTTP 200/202). Returns (accepted: bool, detail: str).
    """
    payload = json.dumps({"commands": [command]}).encode()
    req = urllib.request.Request(
        PRESSABLE_WPCLI_URL,
        data=payload,
        headers={
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
        },
        method="POST",
    )
    try:
        resp = urllib.request.urlopen(req, timeout=30, context=_SSL_CTX)
        code = resp.getcode()
        return True, f"accepted (HTTP {code})"
    except urllib.error.HTTPError as exc:
        return False, f"HTTP {exc.code}"
    except Exception as exc:
        return False, str(exc)


# ---------------------------------------------------------------------------
# Individual check functions
# ---------------------------------------------------------------------------
# Each returns (status: str, detail: str)
#   status is one of "PASS", "FAIL", "WARN"


def check_site_info(_token):
    """1. Site root reachable and WooCommerce namespace present."""
    data, err = _get_json_safe(WP_ROOT)
    if err:
        return "FAIL", f"could not reach site root: {err}"
    namespaces = data.get("namespaces", [])
    has_wc = any(ns.startswith("wc/") for ns in namespaces)
    has_store = "wc/store/v1" in namespaces
    name = data.get("name", "unknown")
    if not has_wc:
        return "FAIL", f"site '{name}' has no wc/ namespace"
    detail = f"site '{name}', wc/store/v1={'yes' if has_store else 'no'}"
    return "PASS", detail


def check_published_products(_token):
    """2. Published products exist (count > 0)."""
    data, err = store_api("products?per_page=1")
    if err:
        return "FAIL", f"Store API error: {err}"
    # The store API returns an array; if at least one exists, we're good.
    # We can't easily get total count without headers, but a non-empty list suffices.
    if isinstance(data, list) and len(data) > 0:
        return "PASS", "at least 1 published product"
    return "FAIL", "no published products found"


def check_subscription_products(_token):
    """3. Subscription products exist (variable-subscription type)."""
    # Store API returns product type in each item
    data, err = store_api("products?per_page=100")
    if err:
        return "FAIL", f"Store API error: {err}"
    sub_count = 0
    for p in (data if isinstance(data, list) else []):
        ptype = p.get("type", "")
        if "subscription" in ptype:
            sub_count += 1
    if sub_count > 0:
        return "PASS", f"{sub_count} subscription product(s)"
    return "FAIL", "no subscription products found"


def check_simple_products(_token):
    """4. Simple products exist (trials, drop-ins)."""
    data, err = store_api("products?per_page=100")
    if err:
        return "FAIL", f"Store API error: {err}"
    simple_count = 0
    for p in (data if isinstance(data, list) else []):
        if p.get("type", "") == "simple":
            simple_count += 1
    if simple_count > 0:
        return "PASS", f"{simple_count} simple product(s)"
    return "FAIL", "no simple products found"


def check_product_categories(_token):
    """5. Product categories exist (Memberships, Trials, Retail, Class Passes)."""
    data, err = store_api("products/categories?per_page=100")
    if err:
        return "FAIL", f"Store API error: {err}"
    found = set()
    for cat in (data if isinstance(data, list) else []):
        found.add(cat.get("name", ""))
    missing = [c for c in EXPECTED_CATEGORIES if c not in found]
    if missing:
        return "FAIL", f"missing categories: {', '.join(missing)}"
    return "PASS", f"all {len(EXPECTED_CATEGORIES)} categories present"


def check_cart_endpoint(_token):
    """6. Cart endpoint is reachable."""
    data, err = store_api("cart")
    if err:
        return "FAIL", f"cart endpoint error: {err}"
    # A valid cart response has items, totals, etc.
    if isinstance(data, dict) and "totals" in data:
        item_count = len(data.get("items", []))
        return "PASS", f"cart reachable, {item_count} item(s)"
    return "WARN", "cart endpoint returned unexpected shape"


def check_checkout_endpoint(_token):
    """7. Checkout endpoint exists."""
    # GET /checkout typically returns 401 or the checkout data — either confirms it exists
    url = f"{STORE_API_BASE}/checkout"
    req = urllib.request.Request(url, method="GET")
    try:
        resp = urllib.request.urlopen(req, timeout=30, context=_SSL_CTX)
        return "PASS", f"checkout endpoint reachable (HTTP {resp.getcode()})"
    except urllib.error.HTTPError as exc:
        # 401/403 means the endpoint exists but needs auth — that's fine
        if exc.code in (401, 403):
            return "PASS", f"checkout endpoint exists (HTTP {exc.code}, auth required)"
        return "FAIL", f"checkout endpoint error: HTTP {exc.code}"
    except Exception as exc:
        return "FAIL", f"checkout endpoint error: {exc}"


def check_content_pages(_token):
    """8. All 11 content pages exist and are published."""
    data, err = wp_api("pages?per_page=50&status=publish")
    if err:
        return "FAIL", f"WP pages API error: {err}"
    titles = set()
    for page in (data if isinstance(data, list) else []):
        title = page.get("title", {})
        rendered = title.get("rendered", "") if isinstance(title, dict) else str(title)
        titles.add(rendered)
    missing = [p for p in EXPECTED_PAGES if p not in titles]
    if missing:
        return "FAIL", f"missing pages: {', '.join(missing)}"
    return "PASS", f"all {len(EXPECTED_PAGES)} pages present"


def check_shop_page(_token):
    """9. Shop page exists among published pages."""
    data, err = wp_api("pages?per_page=50&status=publish")
    if err:
        return "FAIL", f"WP pages API error: {err}"
    for page in (data if isinstance(data, list) else []):
        title = page.get("title", {})
        rendered = title.get("rendered", "") if isinstance(title, dict) else str(title)
        if rendered == "Shop":
            return "PASS", f"Shop page found (ID {page.get('id')})"
    return "FAIL", "Shop page not found"


def check_checkout_page(_token):
    """10. Checkout page exists among published pages."""
    data, err = wp_api("pages?per_page=50&status=publish")
    if err:
        return "FAIL", f"WP pages API error: {err}"
    for page in (data if isinstance(data, list) else []):
        title = page.get("title", {})
        rendered = title.get("rendered", "") if isinstance(title, dict) else str(title)
        slug = page.get("slug", "")
        if rendered == "Checkout" or slug == "checkout":
            return "PASS", f"Checkout page found (ID {page.get('id')})"
    return "FAIL", "Checkout page not found"


def check_myaccount_page(_token):
    """11. My Account page exists among published pages."""
    data, err = wp_api("pages?per_page=50&status=publish")
    if err:
        return "FAIL", f"WP pages API error: {err}"
    for page in (data if isinstance(data, list) else []):
        title = page.get("title", {})
        rendered = title.get("rendered", "") if isinstance(title, dict) else str(title)
        slug = page.get("slug", "")
        if rendered == "My Account" or slug in ("my-account", "my_account"):
            return "PASS", f"My Account page found (ID {page.get('id')})"
    return "FAIL", "My Account page not found"


def check_front_page(_token):
    """12. Home page is set as static front page."""
    # WP REST root includes site-level info; we check pages for a 'Home' page
    # The reading settings aren't exposed in public REST API, so check via WP-CLI
    # as a fire-and-forget, and verify Home page exists.
    data, err = wp_api("pages?per_page=50&status=publish")
    if err:
        return "FAIL", f"WP pages API error: {err}"
    for page in (data if isinstance(data, list) else []):
        title = page.get("title", {})
        rendered = title.get("rendered", "") if isinstance(title, dict) else str(title)
        if rendered == "Home":
            return "PASS", f"Home page found (ID {page.get('id')})"
    return "FAIL", "Home page not found"


def check_currency_usd(_token):
    """13. Store currency is USD (from Store API cart)."""
    data, err = store_api("cart")
    if err:
        return "FAIL", f"Store API error: {err}"
    totals = data.get("totals", {}) if isinstance(data, dict) else {}
    currency = totals.get("currency_code", "unknown")
    if currency == "USD":
        return "PASS", "currency = USD"
    return "FAIL", f"currency = {currency}"


def check_woopayments_active(token):
    """14. WooPayments is active (via Pressable plugins API)."""
    plugins = pressable_get_plugins(token)
    for p in plugins:
        name = p.get("name", "") or p.get("slug", "")
        if "woocommerce-payments" in name:
            status = p.get("status", "unknown")
            if status == "active":
                return "PASS", "woocommerce-payments active"
            return "FAIL", f"woocommerce-payments status={status}"
    return "FAIL", "woocommerce-payments not found"


def check_subscriptions_active(token):
    """15. WC Subscriptions is active (via Pressable plugins API)."""
    plugins = pressable_get_plugins(token)
    for p in plugins:
        name = p.get("name", "") or p.get("slug", "")
        if "woocommerce-subscriptions" in name:
            status = p.get("status", "unknown")
            if status == "active":
                return "PASS", "woocommerce-subscriptions active"
            return "FAIL", f"woocommerce-subscriptions status={status}"
    return "FAIL", "woocommerce-subscriptions not found"


def check_woocommerce_active(token):
    """16. WooCommerce core is active (via Pressable plugins API)."""
    plugins = pressable_get_plugins(token)
    for p in plugins:
        name = p.get("name", "") or p.get("slug", "")
        # Match 'woocommerce' but not 'woocommerce-payments' etc.
        if name == "woocommerce" or name == "woocommerce/woocommerce.php":
            status = p.get("status", "unknown")
            if status == "active":
                return "PASS", "woocommerce active"
            return "FAIL", f"woocommerce status={status}"
    return "FAIL", "woocommerce not found in plugins"


def check_woopayments_test_mode(token):
    """17. WooPayments is in test mode (WP-CLI fire-and-forget)."""
    accepted, detail = pressable_wpcli_fire(
        token, "option pluck woocommerce_woocommerce_payments_settings test_mode"
    )
    if accepted:
        return "WARN", f"WP-CLI accepted (async — verify test_mode manually); {detail}"
    return "WARN", f"WP-CLI not accepted: {detail}"


def check_hpos_enabled(token):
    """18. HPOS enabled (WP-CLI fire-and-forget)."""
    accepted, detail = pressable_wpcli_fire(
        token, "option get woocommerce_custom_orders_table_enabled"
    )
    if accepted:
        return "WARN", f"WP-CLI accepted (async — verify HPOS manually); {detail}"
    return "WARN", f"WP-CLI not accepted: {detail}"


def check_switching_enabled(token):
    """19. Subscription switching enabled (WP-CLI fire-and-forget)."""
    accepted, detail = pressable_wpcli_fire(
        token, "option get woocommerce_subscriptions_allow_switching"
    )
    if accepted:
        return "WARN", f"WP-CLI accepted (async — verify switching manually); {detail}"
    return "WARN", f"WP-CLI not accepted: {detail}"


def check_sync_renewals(token):
    """20. Sync renewals enabled (WP-CLI fire-and-forget)."""
    accepted, detail = pressable_wpcli_fire(
        token, "option get woocommerce_subscriptions_sync_payments"
    )
    if accepted:
        return "WARN", f"WP-CLI accepted (async — verify sync manually); {detail}"
    return "WARN", f"WP-CLI not accepted: {detail}"


def check_retry_enabled(token):
    """21. Retry enabled (WP-CLI fire-and-forget)."""
    accepted, detail = pressable_wpcli_fire(
        token, "option get woocommerce_subscriptions_enable_retry"
    )
    if accepted:
        return "WARN", f"WP-CLI accepted (async — verify retry manually); {detail}"
    return "WARN", f"WP-CLI not accepted: {detail}"


def check_active_theme(_token):
    """22. Active theme check via WP REST API."""
    # /wp/v2/themes?status=active may need auth; try it, fall back to site root
    data, err = wp_api("themes?status=active")
    if err:
        # Fallback: site root includes 'name' but not theme — use site root
        root, root_err = _get_json_safe(WP_ROOT)
        if root_err:
            return "WARN", f"could not determine theme: {err}"
        # Try stylesheet from the HTML as last resort — not available in JSON
        return "WARN", "themes endpoint requires auth; verify theme manually"
    if isinstance(data, list) and len(data) > 0:
        theme_name = data[0].get("name", {})
        if isinstance(theme_name, dict):
            theme_name = theme_name.get("rendered", "unknown")
        stylesheet = data[0].get("stylesheet", "unknown")
        ok = stylesheet != "twentytwentyfive"
        status = "PASS" if ok else "FAIL"
        return status, f"active theme = {stylesheet} ({theme_name})"
    # Dict response (keyed by stylesheet)
    if isinstance(data, dict):
        for stylesheet, info in data.items():
            status_val = info.get("status", "")
            if status_val == "active":
                ok = stylesheet != "twentytwentyfive"
                return ("PASS" if ok else "FAIL"), f"active theme = {stylesheet}"
    return "WARN", "could not parse theme response"


# ---------------------------------------------------------------------------
# Registry: ordered list of (label, function)
# ---------------------------------------------------------------------------
CHECKS = [
    ("Site reachable, WooCommerce namespace present", check_site_info),
    ("Published products exist", check_published_products),
    ("Subscription products exist", check_subscription_products),
    ("Simple products exist (trials, drop-ins)", check_simple_products),
    ("Product categories present", check_product_categories),
    ("Cart endpoint reachable", check_cart_endpoint),
    ("Checkout endpoint exists", check_checkout_endpoint),
    ("All 11 content pages published", check_content_pages),
    ("Shop page exists", check_shop_page),
    ("Checkout page exists", check_checkout_page),
    ("My Account page exists", check_myaccount_page),
    ("Home page exists", check_front_page),
    ("Currency is USD", check_currency_usd),
    ("WooPayments is active", check_woopayments_active),
    ("WC Subscriptions is active", check_subscriptions_active),
    ("WooCommerce core is active", check_woocommerce_active),
    ("WooPayments in test mode", check_woopayments_test_mode),
    ("HPOS enabled", check_hpos_enabled),
    ("Subscription switching enabled", check_switching_enabled),
    ("Sync renewals enabled", check_sync_renewals),
    ("Retry enabled", check_retry_enabled),
    ("Active theme is not twentytwentyfive", check_active_theme),
]

TOTAL = len(CHECKS)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="M1.8 Checkout Flow Smoke Test — validates WooCommerce config on Pressable",
    )
    parser.add_argument("--dry-run", action="store_true",
                        help="Print the checks that would run without executing them")
    parser.add_argument("--json", action="store_true", dest="json_output",
                        help="Output results as machine-readable JSON")
    args = parser.parse_args()

    # --dry-run: just list checks
    if args.dry_run:
        print(f"checkout_smoke_test.py — {TOTAL} checks (dry run)\n")
        for i, (label, _) in enumerate(CHECKS, 1):
            print(f"  [{i:>2}/{TOTAL}] {label}")
        print(f"\nNo API calls made. Pass without --dry-run to execute.")
        return

    # Authenticate with Pressable (needed for plugin + WP-CLI checks)
    print("Authenticating with Pressable API...")
    token = None
    try:
        token = get_pressable_token()
        print("Authenticated.\n")
    except Exception as exc:
        print(f"WARNING: Pressable auth failed ({exc})")
        print("Pressable-dependent checks will be skipped.\n")

    results = []
    passed = 0
    failed_labels = []
    warned = 0

    for i, (label, fn) in enumerate(CHECKS, 1):
        try:
            # Checks that need a token get it; if token is None they'll fail gracefully
            status, detail = fn(token)
        except Exception as exc:
            status, detail = "FAIL", f"ERROR: {exc}"

        results.append({
            "number": i,
            "label": label,
            "status": status,
            "detail": detail,
        })

        if status == "PASS":
            passed += 1
        elif status == "WARN":
            warned += 1
        else:
            failed_labels.append(f"  #{i} {label}: {detail}")

        if not args.json_output:
            if status == "PASS":
                marker = "\033[32mPASS\033[0m"
            elif status == "WARN":
                marker = "\033[33mWARN\033[0m"
            else:
                marker = "\033[31mFAIL\033[0m"
            print(f"  [{i:>2}/{TOTAL}] {marker}  {label}  ({detail})")

    # JSON output
    if args.json_output:
        output = {
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "site": SITE_DOMAIN,
            "total": TOTAL,
            "passed": passed,
            "warned": warned,
            "failed": TOTAL - passed - warned,
            "results": results,
        }
        print(json.dumps(output, indent=2))
        sys.exit(0 if not failed_labels else 1)

    # Summary
    print(f"\n{'=' * 55}")
    print(f"  {passed}/{TOTAL} passed, {warned} warned, {TOTAL - passed - warned} failed")
    if failed_labels:
        print(f"\n  Failures:")
        for line in failed_labels:
            print(f"    {line}")
    print(f"{'=' * 55}")
    sys.exit(0 if not failed_labels else 1)


if __name__ == "__main__":
    main()
