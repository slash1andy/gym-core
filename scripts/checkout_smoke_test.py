#!/usr/bin/env python3
"""
checkout_smoke_test.py — M1.8 Checkout Flow Smoke Test

Validates that the WooCommerce checkout infrastructure on Pressable is correctly
configured by querying the WooCommerce REST API and running WP-CLI commands
via the Pressable management API.

Usage:
    python3 scripts/checkout_smoke_test.py
    python3 scripts/checkout_smoke_test.py --dry-run
    python3 scripts/checkout_smoke_test.py --json
"""

import argparse
import json
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime

# ---------------------------------------------------------------------------
# Pressable API auth
# ---------------------------------------------------------------------------
PRESSABLE_TOKEN_URL = "https://my.pressable.com/auth/token"
PRESSABLE_CLIENT_ID = "HenB445AN-Fxqstk8EZnyA58UWaaPV_HtvpIiGMpGcw"
PRESSABLE_CLIENT_SECRET = "3bB1iFxF_vf1jJ4mHIpuguV7cCJ0vP7UfaiJx1fjepI"
PRESSABLE_SITE_ID = "1630891"
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
# Helpers
# ---------------------------------------------------------------------------

def get_pressable_token():
    """Obtain a Pressable API bearer token."""
    req = urllib.request.Request(
        PRESSABLE_TOKEN_URL,
        data=urllib.parse.urlencode({
            "grant_type": "client_credentials",
            "client_id": PRESSABLE_CLIENT_ID,
            "client_secret": PRESSABLE_CLIENT_SECRET,
        }).encode(),
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    resp = urllib.request.urlopen(req, timeout=30)
    return json.loads(resp.read())["access_token"]


def wpcli(token, command, *, timeout=60):
    """Run a WP-CLI command on Pressable and return the parsed output.

    The Pressable API expects the command *without* the leading ``wp`` prefix.
    We request ``--format=json`` where appropriate so results are machine-parseable.
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
    resp = urllib.request.urlopen(req, timeout=timeout)
    body = json.loads(resp.read())
    # Pressable returns {"data": [{"output": "...", ...}]}  or similar
    # Normalise to the raw stdout string
    if isinstance(body, dict):
        data = body.get("data", body)
        if isinstance(data, list) and len(data) > 0:
            return data[0].get("output", data[0].get("stdout", str(data[0])))
        if isinstance(data, str):
            return data
        return str(data)
    return str(body)


def wpcli_json(token, command, **kwargs):
    """Run a WP-CLI command and parse the JSON output."""
    raw = wpcli(token, command, **kwargs)
    try:
        return json.loads(raw)
    except (json.JSONDecodeError, TypeError):
        return raw


def wpcli_option(token, option_name):
    """Shortcut: wp option get <name>."""
    return wpcli(token, f"option get {option_name}").strip()


# ---------------------------------------------------------------------------
# Individual check functions
# ---------------------------------------------------------------------------
# Each returns (passed: bool, detail: str)

def check_published_products(token):
    """1. Published products exist (count > 0)."""
    out = wpcli(token, "post list --post_type=product --post_status=publish --format=count")
    count = int(out.strip())
    return count > 0, f"{count} published products"


def check_subscription_products(token):
    """2. Subscription products exist (variable-subscription type)."""
    out = wpcli(token, "post list --post_type=product --post_status=publish --format=json --fields=ID")
    products = json.loads(out.strip()) if out.strip() else []
    # Check product types via term taxonomy
    sub_count = 0
    if products:
        ids = ",".join(str(p["ID"]) for p in products)
        type_out = wpcli(token,
            f"eval 'foreach(explode(\",\",\"{ids}\") as $id){{ $t=wp_get_object_terms((int)$id,\"product_type\",array(\"fields\"=>\"slugs\")); if(in_array(\"variable-subscription\",$t)) echo $id.\"\\n\"; }}'")
        sub_count = len([l for l in type_out.strip().splitlines() if l.strip()])
    return sub_count > 0, f"{sub_count} variable-subscription products"


def check_simple_products(token):
    """3. Simple products exist (trials, drop-ins)."""
    out = wpcli(token, "post list --post_type=product --post_status=publish --format=json --fields=ID")
    products = json.loads(out.strip()) if out.strip() else []
    simple_count = 0
    if products:
        ids = ",".join(str(p["ID"]) for p in products)
        type_out = wpcli(token,
            f"eval 'foreach(explode(\",\",\"{ids}\") as $id){{ $t=wp_get_object_terms((int)$id,\"product_type\",array(\"fields\"=>\"slugs\")); if(in_array(\"simple\",$t)) echo $id.\"\\n\"; }}'")
        simple_count = len([l for l in type_out.strip().splitlines() if l.strip()])
    return simple_count > 0, f"{simple_count} simple products"


def check_product_categories(token):
    """4. Product categories exist (Memberships, Trials, Retail, Class Passes)."""
    out = wpcli(token, "term list product_cat --format=json --fields=name")
    terms = json.loads(out.strip()) if out.strip() else []
    found = {t["name"] for t in terms}
    missing = [c for c in EXPECTED_CATEGORIES if c not in found]
    if missing:
        return False, f"missing categories: {', '.join(missing)}"
    return True, f"all {len(EXPECTED_CATEGORIES)} categories present"


def check_shop_page(token):
    """5. Shop page is set."""
    val = wpcli_option(token, "woocommerce_shop_page_id")
    ok = val.isdigit() and int(val) > 0
    return ok, f"shop page ID = {val}"


def check_cart_page(token):
    """6. Cart page is set."""
    val = wpcli_option(token, "woocommerce_cart_page_id")
    ok = val.isdigit() and int(val) > 0
    return ok, f"cart page ID = {val}"


def check_checkout_page(token):
    """7. Checkout page is set."""
    val = wpcli_option(token, "woocommerce_checkout_page_id")
    ok = val.isdigit() and int(val) > 0
    return ok, f"checkout page ID = {val}"


def check_myaccount_page(token):
    """8. My Account page is set."""
    val = wpcli_option(token, "woocommerce_myaccount_page_id")
    ok = val.isdigit() and int(val) > 0
    return ok, f"myaccount page ID = {val}"


def check_currency_usd(token):
    """9. Currency is USD."""
    val = wpcli_option(token, "woocommerce_currency")
    return val == "USD", f"currency = {val}"


def check_hpos_enabled(token):
    """10. HPOS enabled."""
    out = wpcli(token,
        "eval 'echo class_exists(\"Automattic\\\\WooCommerce\\\\Internal\\\\DataStores\\\\Orders\\\\CustomOrdersTableController\") "
        "? (new Automattic\\\\WooCommerce\\\\Internal\\\\DataStores\\\\Orders\\\\CustomOrdersTableController(wc_get_container()->get(Automattic\\\\WooCommerce\\\\Internal\\\\DataStores\\\\Orders\\\\DataSynchronizer::class),wc_get_container()->get(Automattic\\\\WooCommerce\\\\Internal\\\\BatchProcessing\\\\BatchProcessingController::class),wc_get_container()->get(Automattic\\\\WooCommerce\\\\Internal\\\\Features\\\\FeaturesController::class),wc_get_container()->get(Automattic\\\\WooCommerce\\\\Internal\\\\DataStores\\\\Orders\\\\OrdersTableDataStore::class)))->custom_orders_table_usage_is_enabled() ? \"yes\" : \"no\" : \"no\";'"
    ).strip()
    # Simpler fallback: check the feature option directly
    if out not in ("yes", "no"):
        out = wpcli_option(token, "woocommerce_custom_orders_table_enabled")
    return out == "yes", f"HPOS = {out}"


def check_woopayments_active(token):
    """11. WooPayments is active."""
    out = wpcli(token, "plugin list --status=active --format=json --fields=name")
    plugins = json.loads(out.strip()) if out.strip() else []
    names = {p["name"] for p in plugins}
    active = "woocommerce-payments" in names
    return active, "woocommerce-payments " + ("active" if active else "NOT active")


def check_woopayments_test_mode(token):
    """12. WooPayments is in test/sandbox mode."""
    val = wpcli_option(token, "woocommerce_woocommerce_payments_settings")
    try:
        settings = json.loads(val) if isinstance(val, str) else val
    except json.JSONDecodeError:
        # May be a serialized PHP array — try option pluck
        val2 = wpcli(token, "option pluck woocommerce_woocommerce_payments_settings test_mode").strip()
        return val2 == "yes", f"test_mode = {val2}"
    test_mode = settings.get("test_mode", "unknown") if isinstance(settings, dict) else "unknown"
    return test_mode == "yes", f"test_mode = {test_mode}"


def check_subscriptions_active(token):
    """13. WC Subscriptions is active."""
    out = wpcli(token, "plugin list --status=active --format=json --fields=name")
    plugins = json.loads(out.strip()) if out.strip() else []
    names = {p["name"] for p in plugins}
    active = "woocommerce-subscriptions" in names
    return active, "woocommerce-subscriptions " + ("active" if active else "NOT active")


def check_switching_enabled(token):
    """14. Switching enabled."""
    val = wpcli_option(token, "woocommerce_subscriptions_allow_switching")
    ok = val and val != "no" and val != ""
    return ok, f"switching = {val}"


def check_early_renewal_disabled(token):
    """15. Early renewal disabled."""
    val = wpcli_option(token, "woocommerce_subscriptions_enable_early_renewal")
    return val == "no", f"early_renewal = {val}"


def check_sync_renewals(token):
    """16. Sync renewals enabled (day 1)."""
    sync = wpcli_option(token, "woocommerce_subscriptions_sync_payments")
    day = wpcli_option(token, "woocommerce_subscriptions_sync_payments_day")
    ok = sync not in ("", "no", "0") and day == "1"
    return ok, f"sync = {sync}, day = {day}"


def check_retry_enabled(token):
    """17. Retry enabled."""
    val = wpcli_option(token, "woocommerce_subscriptions_enable_retry")
    return val == "yes", f"retry = {val}"


def check_manual_renewals(token):
    """18. Manual renewals accepted."""
    val = wpcli_option(token, "woocommerce_subscriptions_accept_manual_renewals")
    return val == "yes", f"manual_renewals = {val}"


def check_front_page(token):
    """19. Home page is set as front page."""
    show = wpcli_option(token, "show_on_front")
    page_id = wpcli_option(token, "page_on_front")
    ok = show == "page" and page_id.isdigit() and int(page_id) > 0
    return ok, f"show_on_front = {show}, page_on_front = {page_id}"


def check_content_pages(token):
    """20. All 11 content pages exist and are published."""
    out = wpcli(token, "post list --post_type=page --post_status=publish --format=json --fields=post_title")
    pages = json.loads(out.strip()) if out.strip() else []
    titles = {p["post_title"] for p in pages}
    missing = [p for p in EXPECTED_PAGES if p not in titles]
    if missing:
        return False, f"missing pages: {', '.join(missing)}"
    return True, f"all {len(EXPECTED_PAGES)} pages present"


def check_active_theme(token):
    """21. Active theme is not twentytwentyfive."""
    out = wpcli(token, "theme list --status=active --format=json --fields=name")
    themes = json.loads(out.strip()) if out.strip() else []
    name = themes[0]["name"] if themes else "unknown"
    ok = name != "twentytwentyfive"
    return ok, f"active theme = {name}"


def check_email_types(token):
    """22. WooCommerce email types are configured."""
    out = wpcli(token,
        "eval '"
        "$mailer = WC()->mailer(); "
        "$emails = $mailer->get_emails(); "
        "echo count($emails);'"
    ).strip()
    try:
        count = int(out)
    except ValueError:
        return False, f"could not parse email count: {out}"
    return count > 0, f"{count} email types configured"


# ---------------------------------------------------------------------------
# Registry: ordered list of (label, function)
# ---------------------------------------------------------------------------
CHECKS = [
    ("Published products exist", check_published_products),
    ("Subscription products exist (variable-subscription)", check_subscription_products),
    ("Simple products exist (trials, drop-ins)", check_simple_products),
    ("Product categories exist", check_product_categories),
    ("Shop page is set", check_shop_page),
    ("Cart page is set", check_cart_page),
    ("Checkout page is set", check_checkout_page),
    ("My Account page is set", check_myaccount_page),
    ("Currency is USD", check_currency_usd),
    ("HPOS enabled", check_hpos_enabled),
    ("WooPayments is active", check_woopayments_active),
    ("WooPayments in test mode", check_woopayments_test_mode),
    ("WC Subscriptions is active", check_subscriptions_active),
    ("Switching enabled", check_switching_enabled),
    ("Early renewal disabled", check_early_renewal_disabled),
    ("Sync renewals enabled (day 1)", check_sync_renewals),
    ("Retry enabled", check_retry_enabled),
    ("Manual renewals accepted", check_manual_renewals),
    ("Home page is set as front page", check_front_page),
    ("All 11 content pages published", check_content_pages),
    ("Active theme is not twentytwentyfive", check_active_theme),
    ("WooCommerce email types configured", check_email_types),
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

    # Authenticate
    print("Authenticating with Pressable API...")
    try:
        token = get_pressable_token()
    except (urllib.error.URLError, KeyError) as exc:
        print(f"FATAL: Could not obtain Pressable token: {exc}", file=sys.stderr)
        sys.exit(2)
    print("Authenticated.\n")

    results = []
    passed = 0
    failed_labels = []

    for i, (label, fn) in enumerate(CHECKS, 1):
        try:
            ok, detail = fn(token)
        except Exception as exc:
            ok, detail = False, f"ERROR: {exc}"
        status = "PASS" if ok else "FAIL"
        results.append({
            "number": i,
            "label": label,
            "passed": ok,
            "detail": detail,
        })
        if ok:
            passed += 1
        else:
            failed_labels.append(f"  #{i} {label}: {detail}")

        if not args.json_output:
            marker = "\033[32mPASS\033[0m" if ok else "\033[31mFAIL\033[0m"
            print(f"  [{i:>2}/{TOTAL}] {marker}  {label}  ({detail})")

    # JSON output
    if args.json_output:
        output = {
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "total": TOTAL,
            "passed": passed,
            "failed": TOTAL - passed,
            "results": results,
        }
        print(json.dumps(output, indent=2))
        sys.exit(0 if passed == TOTAL else 1)

    # Summary
    print(f"\n{'=' * 50}")
    print(f"  {passed}/{TOTAL} checks passed")
    if failed_labels:
        print(f"\n  Failures:")
        for line in failed_labels:
            print(f"    {line}")
    print(f"{'=' * 50}")
    sys.exit(0 if passed == TOTAL else 1)


if __name__ == "__main__":
    main()
