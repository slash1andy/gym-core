#!/usr/bin/env python3
"""
setup_memberships.py — M3: WooCommerce Memberships setup on Pressable.

Installs WooCommerce Memberships, creates membership plans linked to
subscription products, configures content restriction rules, and sets up
auto-enrollment from subscription purchases.

Usage:
    python3 scripts/setup_memberships.py
    python3 scripts/setup_memberships.py --dry-run

Requirements: Python 3.8+ (stdlib only)
"""

import argparse
import json
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

# ── Pressable config ────────────────────────────────────────────────
SITE_ID = "1630891"
TOKEN_URL = "https://my.pressable.com/auth/token"
CLIENT_ID = "HenB445AN-Fxqstk8EZnyA58UWaaPV_HtvpIiGMpGcw"
CLIENT_SECRET = "3bB1iFxF_vf1jJ4mHIpuguV7cCJ0vP7UfaiJx1fjepI"
WPCLI_URL = f"https://my.pressable.com/v1/sites/{SITE_ID}/wordpress/wpcli"

# ── Membership Plans ───────────────────────────────────────────────
# Each plan maps to subscription product slugs from create_membership_products.py
MEMBERSHIP_PLANS = [
    {
        "name": "Adult BJJ Member",
        "slug": "adult-bjj-member",
        "description": "Full access to all Adult BJJ classes and members-only BJJ resources.",
        "product_slugs": ["adult-bjj-limited", "adult-bjj-unlimited"],
    },
    {
        "name": "Kids BJJ Member",
        "slug": "kids-bjj-member",
        "description": "Full access to all Kids BJJ classes and members-only kids resources.",
        "product_slugs": ["kids-bjj-limited", "kids-bjj-unlimited"],
    },
    {
        "name": "Kickboxing Member",
        "slug": "kickboxing-member",
        "description": "Full access to all Kickboxing/Striking classes and members-only striking resources.",
        "product_slugs": ["kickboxing-limited", "kickboxing-unlimited"],
    },
    {
        "name": "All-Access Member",
        "slug": "all-access-member",
        "description": "Unlimited access to all classes, all programs, and all members-only content.",
        "product_slugs": [
            "adult-bjj-unlimited",
            "kickboxing-unlimited",
            "kids-bjj-unlimited",
            "multi-program-upgrade",
        ],
    },
    {
        "name": "Little Ninjas Member",
        "slug": "little-ninjas-member",
        "description": "Access to Little Ninjas program classes and members-only kids resources.",
        "product_slugs": ["little-ninjas"],
    },
]

# ── Content restriction rules ─────────────────────────────────────
# Pages that should be restricted to members only.
RESTRICTED_PAGE_SLUGS = [
    "technique-videos",
    "training-resources",
    "members-area",
    "curriculum",
]

# Product categories to restrict visibility (members-only products).
RESTRICTED_PRODUCT_CATEGORIES = [
    "class-passes",   # Hide class passes from non-members where applicable
]


# ── Helpers ────────────────────────────────────────────────────────

def get_token():
    """Obtain a Pressable API bearer token."""
    data = urllib.parse.urlencode({
        "grant_type": "client_credentials",
        "client_id": CLIENT_ID,
        "client_secret": CLIENT_SECRET,
    }).encode()
    req = urllib.request.Request(
        TOKEN_URL,
        data=data,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    ctx = ssl.create_default_context()
    resp = urllib.request.urlopen(req, timeout=30, context=ctx)
    body = json.loads(resp.read())
    return body["access_token"]


def run_wpcli(token, command, dry_run=False):
    """Fire a WP-CLI command on Pressable (async/fire-and-forget)."""
    print(f"  WP-CLI> {command}")
    if dry_run:
        print("  [dry-run] Skipped.")
        return None

    payload = json.dumps({"commands": [command]}).encode()
    req = urllib.request.Request(
        WPCLI_URL,
        data=payload,
        headers={
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
        },
        method="POST",
    )
    ctx = ssl.create_default_context()
    try:
        resp = urllib.request.urlopen(req, timeout=60, context=ctx)
        body = json.loads(resp.read())
        print(f"  -> Dispatched (status {resp.status})")
        return body
    except urllib.error.HTTPError as exc:
        print(f"  -> ERROR {exc.code}: {exc.read().decode()[:300]}")
        return None


# ── Step functions ─────────────────────────────────────────────────

def step_install_plugin(token, dry_run):
    """Step 1: Attempt to install WooCommerce Memberships."""
    print("--- Step 1: Install WooCommerce Memberships ---\n")

    # WooCommerce Memberships is a premium extension sold on woocommerce.com.
    # It is NOT available on WordPress.org. Attempt install from wp.org to
    # confirm, then print manual instructions.
    print("[woocommerce-memberships] Checking WordPress.org availability...")
    print("  WooCommerce Memberships is a PREMIUM plugin (woocommerce.com).")
    print("  It is NOT available via WordPress.org or `wp plugin install`.\n")

    # Try anyway in case it was previously uploaded or available via
    # WooCommerce.com connected store.
    print("[woocommerce-memberships] Attempting activation (if already uploaded)...")
    run_wpcli(token, "plugin activate woocommerce-memberships", dry_run=dry_run)
    print()

    print("  MANUAL INSTALL INSTRUCTIONS (if not already installed):")
    print("  -------------------------------------------------------")
    print("  1. Purchase/download from: https://woocommerce.com/products/woocommerce-memberships/")
    print("  2. Go to WP Admin > Plugins > Add New > Upload Plugin")
    print("  3. Upload the woocommerce-memberships.zip file")
    print("  4. Activate the plugin")
    print("  5. Re-run this script to configure plans and rules")
    print()
    print("  Alternatively, if the site is connected to WooCommerce.com:")
    print("  WP Admin > WooCommerce > Extensions > My Subscriptions > Download")
    print()


def step_create_plans(token, dry_run):
    """Step 2: Create membership plans and link to subscription products."""
    print("--- Step 2: Create Membership Plans ---\n")

    for plan in MEMBERSHIP_PLANS:
        name = plan["name"]
        slug = plan["slug"]
        desc = plan["description"]
        product_slugs = plan["product_slugs"]

        print(f"[{name}]")

        # Create the membership plan as a custom post type (wc_membership_plan).
        # WooCommerce Memberships stores plans as 'wc_membership_plan' post type.
        create_plan_php = (
            f"$existing = get_posts(['post_type' => 'wc_membership_plan', "
            f"'name' => '{slug}', 'post_status' => 'any', 'numberposts' => 1]); "
            f"if (!empty($existing)) {{ "
            f"  echo 'Plan already exists: {name} (ID ' . $existing[0]->ID . ')'; "
            f"}} else {{ "
            f"  $plan_id = wp_insert_post(["
            f"    'post_type' => 'wc_membership_plan', "
            f"    'post_title' => '{name}', "
            f"    'post_name' => '{slug}', "
            f"    'post_status' => 'publish', "
            f"    'post_content' => '{desc}', "
            f"  ]); "
            f"  if (is_wp_error($plan_id)) {{ "
            f"    echo 'ERROR creating plan: ' . $plan_id->get_error_message(); "
            f"  }} else {{ "
            f"    echo 'Created plan: {name} (ID ' . $plan_id . ')'; "
            f"  }} "
            f"}}"
        )
        run_wpcli(token, f"eval '{create_plan_php}'", dry_run=dry_run)

        # Link subscription products to the plan.
        # WooCommerce Memberships uses _product_ids post meta to link products.
        link_products_php = (
            f"$plans = get_posts(['post_type' => 'wc_membership_plan', "
            f"'name' => '{slug}', 'post_status' => 'any', 'numberposts' => 1]); "
            f"if (empty($plans)) {{ echo 'Plan not found: {slug}'; }} "
            f"else {{ "
            f"  $plan_id = $plans[0]->ID; "
            f"  $product_ids = []; "
            f"  $slugs = {json.dumps(product_slugs)}; "
            f"  foreach ($slugs as $pslug) {{ "
            f"    $product = get_page_by_path($pslug, OBJECT, 'product'); "
            f"    if ($product) {{ $product_ids[] = $product->ID; }} "
            f"    else {{ echo 'Warning: product not found: ' . $pslug . \"\\n\"; }} "
            f"  }} "
            f"  update_post_meta($plan_id, '_product_ids', $product_ids); "
            f"  echo 'Linked ' . count($product_ids) . ' products to plan {name}'; "
            f"}}"
        )
        print(f"  Linking products: {', '.join(product_slugs)}")
        run_wpcli(token, f"eval '{link_products_php}'", dry_run=dry_run)

        # Set the access method to "purchase" for subscription-based enrollment.
        access_method_php = (
            f"$plans = get_posts(['post_type' => 'wc_membership_plan', "
            f"'name' => '{slug}', 'post_status' => 'any', 'numberposts' => 1]); "
            f"if (!empty($plans)) {{ "
            f"  update_post_meta($plans[0]->ID, '_access_method', 'purchase'); "
            f"  echo 'Access method set to purchase for {name}'; "
            f"}}"
        )
        run_wpcli(token, f"eval '{access_method_php}'", dry_run=dry_run)
        print()


def step_content_restrictions(token, dry_run):
    """Step 3: Configure content restriction rules."""
    print("--- Step 3: Configure Content Restriction Rules ---\n")

    # 3a. Set default restriction mode to "hide completely" for non-members.
    print("[restriction-mode] Setting default content restriction mode...")
    restriction_mode_php = (
        "update_option('wc_memberships_restriction_mode', 'hide'); "
        "echo 'Default restriction mode set to: hide';"
    )
    run_wpcli(token, f"eval '{restriction_mode_php}'", dry_run=dry_run)
    print()

    # 3b. Restrict specific pages to any membership plan.
    print("[page-restrictions] Setting up members-only page restrictions...")
    for page_slug in RESTRICTED_PAGE_SLUGS:
        restrict_page_php = (
            f"$page = get_page_by_path('{page_slug}'); "
            f"if (!$page) {{ "
            f"  echo 'Page not found: {page_slug} (will need to create page first)'; "
            f"}} else {{ "
            f"  $plans = get_posts(['post_type' => 'wc_membership_plan', "
            f"  'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']); "
            f"  foreach ($plans as $plan_id) {{ "
            f"    $rule_id = wp_insert_post(["
            f"      'post_type' => 'wc_membership_plan_rule', "
            f"      'post_status' => 'publish', "
            f"      'post_parent' => $plan_id, "
            f"    ]); "
            f"    if (!is_wp_error($rule_id)) {{ "
            f"      update_post_meta($rule_id, '_rule_type', 'content_restriction'); "
            f"      update_post_meta($rule_id, '_content_type', 'post_type'); "
            f"      update_post_meta($rule_id, '_content_type_name', 'page'); "
            f"      update_post_meta($rule_id, '_object_ids', [$page->ID]); "
            f"      update_post_meta($rule_id, '_access_type', 'immediate'); "
            f"    }} "
            f"  }} "
            f"  echo 'Restricted page: {page_slug} (ID ' . $page->ID . ')'; "
            f"}}"
        )
        print(f"  [{page_slug}]")
        run_wpcli(token, f"eval '{restrict_page_php}'", dry_run=dry_run)
    print()

    # 3c. Restrict product categories (hide from non-eligible members).
    print("[product-restrictions] Configuring product visibility restrictions...")
    for cat_slug in RESTRICTED_PRODUCT_CATEGORIES:
        restrict_cat_php = (
            f"$term = get_term_by('slug', '{cat_slug}', 'product_cat'); "
            f"if (!$term) {{ "
            f"  echo 'Product category not found: {cat_slug}'; "
            f"}} else {{ "
            f"  $plans = get_posts(['post_type' => 'wc_membership_plan', "
            f"  'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']); "
            f"  foreach ($plans as $plan_id) {{ "
            f"    $rule_id = wp_insert_post(["
            f"      'post_type' => 'wc_membership_plan_rule', "
            f"      'post_status' => 'publish', "
            f"      'post_parent' => $plan_id, "
            f"    ]); "
            f"    if (!is_wp_error($rule_id)) {{ "
            f"      update_post_meta($rule_id, '_rule_type', 'product_restriction'); "
            f"      update_post_meta($rule_id, '_content_type', 'taxonomy'); "
            f"      update_post_meta($rule_id, '_content_type_name', 'product_cat'); "
            f"      update_post_meta($rule_id, '_object_ids', [$term->term_id]); "
            f"      update_post_meta($rule_id, '_access_type', 'immediate'); "
            f"    }} "
            f"  }} "
            f"  echo 'Restricted product category: {cat_slug}'; "
            f"}}"
        )
        print(f"  [{cat_slug}]")
        run_wpcli(token, f"eval '{restrict_cat_php}'", dry_run=dry_run)
    print()


def step_auto_enrollment(token, dry_run):
    """Step 4: Configure subscription-to-membership auto-enrollment."""
    print("--- Step 4: Configure Auto-Enrollment ---\n")

    # WooCommerce Memberships + Subscriptions integration:
    # When a customer purchases a subscription product linked to a plan,
    # they automatically receive an active membership. This is the default
    # behavior when products are linked to plans, but we ensure the
    # integration settings are correct.

    # 4a. Ensure Subscriptions integration is enabled.
    print("[subscriptions-integration] Enabling Memberships + Subscriptions integration...")
    integration_php = (
        "$settings = get_option('wc_memberships_subscriptions_integration', []); "
        "$settings['enable'] = 'yes'; "
        "update_option('wc_memberships_subscriptions_integration', $settings); "
        "echo 'Subscriptions integration enabled.';"
    )
    run_wpcli(token, f"eval '{integration_php}'", dry_run=dry_run)
    print()

    # 4b. Set membership to follow subscription status (active/paused/cancelled).
    print("[membership-sync] Linking membership status to subscription lifecycle...")
    sync_php = (
        "update_option('wc_memberships_subscription_membership_end_action', 'cancel'); "
        "echo 'Membership cancels when subscription is cancelled.';"
    )
    run_wpcli(token, f"eval '{sync_php}'", dry_run=dry_run)
    print()

    # 4c. Configure each plan for subscription-tied enrollment.
    print("[plan-enrollment] Setting subscription-based enrollment on each plan...")
    for plan in MEMBERSHIP_PLANS:
        slug = plan["slug"]
        name = plan["name"]
        enrollment_php = (
            f"$plans = get_posts(['post_type' => 'wc_membership_plan', "
            f"'name' => '{slug}', 'post_status' => 'any', 'numberposts' => 1]); "
            f"if (!empty($plans)) {{ "
            f"  $plan_id = $plans[0]->ID; "
            f"  update_post_meta($plan_id, '_access_method', 'purchase'); "
            f"  update_post_meta($plan_id, '_subscription_access', 'yes'); "
            f"  echo '{name}: subscription enrollment configured'; "
            f"}} else {{ "
            f"  echo '{name}: plan not found (run step 2 first)'; "
            f"}}"
        )
        print(f"  [{name}]")
        run_wpcli(token, f"eval '{enrollment_php}'", dry_run=dry_run)
    print()


def print_summary(dry_run):
    """Step 5: Print summary of what was configured."""
    print("=" * 60)
    print("  M3 — WOOCOMMERCE MEMBERSHIPS SETUP SUMMARY")
    print("=" * 60)

    print(f"""
  Plugin:
    WooCommerce Memberships — premium (manual install if needed)

  Membership Plans Created ({len(MEMBERSHIP_PLANS)}):""")

    for plan in MEMBERSHIP_PLANS:
        products = ", ".join(plan["product_slugs"])
        print(f"    - {plan['name']}")
        print(f"      Linked products: {products}")

    print(f"""
  Content Restrictions:
    Restriction mode: hide (non-members see nothing)
    Restricted pages: {', '.join(RESTRICTED_PAGE_SLUGS)}
    Restricted product categories: {', '.join(RESTRICTED_PRODUCT_CATEGORIES)}

  Auto-Enrollment:
    Subscription purchase -> membership activation (automatic)
    Membership status follows subscription lifecycle
    Cancellation behavior: membership cancelled when subscription cancelled
""")

    if dry_run:
        print("  [DRY RUN] No changes were made.\n")
    else:
        print("  NOTE: WP-CLI commands are async (fire-and-forget).")
        print("  Verify configuration in WP Admin:\n")
        print("  Manual verification checklist:")
        print("  [ ] WooCommerce > Memberships > Membership Plans — 5 plans exist")
        print("  [ ] Each plan shows linked subscription products")
        print("  [ ] WooCommerce > Memberships > Settings > Restriction mode = Hide")
        print("  [ ] Members-only pages return 403/redirect for logged-out users")
        print("  [ ] Test purchase: subscription -> auto-creates membership")
        print("  [ ] Test cancel: subscription cancel -> membership cancelled")
        print()
        print("  If WooCommerce Memberships is not installed yet:")
        print("  1. Upload & activate the plugin (see Step 1 instructions)")
        print("  2. Re-run: python3 scripts/setup_memberships.py")
        print()


# ── Main ───────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="M3 — WooCommerce Memberships setup on Pressable"
    )
    parser.add_argument(
        "--dry-run", action="store_true",
        help="Print commands without executing"
    )
    args = parser.parse_args()

    print("=" * 60)
    print("  M3 — WooCommerce Memberships Setup")
    print("=" * 60)
    print()

    # ── Authenticate ────────────────────────────────────────────────
    if args.dry_run:
        print("[DRY RUN MODE]\n")
        token = "dry-run-token"
    else:
        print("Authenticating with Pressable API...")
        try:
            token = get_token()
            print("Authenticated successfully.\n")
        except Exception as exc:
            print(f"ERROR: Could not authenticate: {exc}", file=sys.stderr)
            sys.exit(1)

    # ── Step 1: Install plugin ──────────────────────────────────────
    step_install_plugin(token, args.dry_run)

    if not args.dry_run:
        print("Waiting 10s for plugin activation to complete...\n")
        time.sleep(10)

    # ── Step 2: Create membership plans ─────────────────────────────
    step_create_plans(token, args.dry_run)

    if not args.dry_run:
        print("Waiting 10s for plan creation to complete...\n")
        time.sleep(10)

    # ── Step 3: Content restrictions ────────────────────────────────
    step_content_restrictions(token, args.dry_run)

    # ── Step 4: Auto-enrollment ─────────────────────────────────────
    step_auto_enrollment(token, args.dry_run)

    # ── Step 5: Summary ─────────────────────────────────────────────
    print_summary(args.dry_run)


if __name__ == "__main__":
    main()
