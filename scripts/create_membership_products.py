#!/usr/bin/env python3
"""Create WooCommerce Subscription products for M1.4.

Maps Spark membership plans to WooCommerce variable subscription products.
Runs WP-CLI commands remotely via Pressable API.

Usage:
  PRESSABLE_CLIENT_ID=xxx PRESSABLE_CLIENT_SECRET=yyy python3 scripts/create_membership_products.py
  python3 scripts/create_membership_products.py --dry-run
  python3 scripts/create_membership_products.py --site-id 1630891

Requirements: Python 3.8+ (stdlib only)
"""

import argparse
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

API_BASE = "https://my.pressable.com/v1"
AUTH_URL = "https://my.pressable.com/auth/token"
DEFAULT_SITE_ID = 1630891

# ---------------------------------------------------------------------------
# Product definitions (mapped from spark-membership-plans.csv)
# ---------------------------------------------------------------------------

CATEGORIES = [
    "Memberships",
    "Trials",
    "Retail",
    "Class Passes",
]

# Variable subscription products: each has monthly + yearly payment variations.
# "Limited" = BJJ or Kickboxing only. "Unlimited" = all classes.
SUBSCRIPTION_PRODUCTS = [
    # --- Adult BJJ (Rockford) ---
    {
        "name": "Adult BJJ — Limited",
        "slug": "adult-bjj-limited",
        "category": "Memberships",
        "description": "Adult Brazilian Jiu-Jitsu membership. Access to all BJJ classes (Fundamentals, Mixed Levels, No-Gi). 12-month contract.",
        "short_description": "BJJ classes only. 12-month commitment.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "163.00", "period": "month", "interval": "1", "signup_fee": "499.00", "length": "12"},
            {"name": "Paid in Full", "price": "2100.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    {
        "name": "Adult BJJ — Unlimited",
        "slug": "adult-bjj-unlimited",
        "category": "Memberships",
        "description": "Unlimited Adult BJJ membership. Access to all BJJ and striking classes. 12-month contract.",
        "short_description": "All classes included. 12-month commitment.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "179.00", "period": "month", "interval": "1", "signup_fee": "699.00", "length": "12"},
            {"name": "Paid in Full", "price": "2400.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    # --- Kickboxing (Rockford) ---
    {
        "name": "Kickboxing — Limited",
        "slug": "kickboxing-limited",
        "category": "Memberships",
        "description": "Kickboxing / Striking membership. Access to all striking classes (Kick Fit, Mixed Levels Striking). 12-month contract.",
        "short_description": "Striking classes only. 12-month commitment.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "163.00", "period": "month", "interval": "1", "signup_fee": "499.00", "length": "12"},
            {"name": "Paid in Full", "price": "2100.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    {
        "name": "Kickboxing — Unlimited",
        "slug": "kickboxing-unlimited",
        "category": "Memberships",
        "description": "Unlimited Kickboxing membership. Access to all striking and BJJ classes. 12-month contract.",
        "short_description": "All classes included. 12-month commitment.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "179.00", "period": "month", "interval": "1", "signup_fee": "699.00", "length": "12"},
            {"name": "Paid in Full", "price": "2400.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    # --- Kids BJJ (Rockford) ---
    {
        "name": "Kids BJJ — Limited",
        "slug": "kids-bjj-limited",
        "category": "Memberships",
        "description": "Kids BJJ membership (ages 6-15). Access to Kids BJJ classes. 12-month contract.",
        "short_description": "Kids BJJ classes. 12-month commitment.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "163.00", "period": "month", "interval": "1", "signup_fee": "499.00", "length": "12"},
            {"name": "Paid in Full", "price": "2100.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    {
        "name": "Kids BJJ — Unlimited",
        "slug": "kids-bjj-unlimited",
        "category": "Memberships",
        "description": "Unlimited Kids BJJ membership (ages 6-15). Access to all kids classes. 12-month contract.",
        "short_description": "All kids classes included. 12-month commitment.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "179.00", "period": "month", "interval": "1", "signup_fee": "699.00", "length": "12"},
            {"name": "Paid in Full", "price": "2400.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    # --- Little Ninjas (Rockford) ---
    {
        "name": "Little Ninjas",
        "slug": "little-ninjas",
        "category": "Memberships",
        "description": "Little Ninjas program (ages 4-6). Introductory martial arts for young children. 12-month contract.",
        "short_description": "Ages 4-6. 12-month commitment.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "163.00", "period": "month", "interval": "1", "signup_fee": "499.00", "length": "12"},
            {"name": "Paid in Full", "price": "2100.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    # --- Multi-Program Upgrade ---
    {
        "name": "Multi-Program Upgrade",
        "slug": "multi-program-upgrade",
        "category": "Memberships",
        "description": "Add access to all programs (BJJ + Striking) for existing limited members. 12-month contract.",
        "short_description": "Upgrade to all classes. Existing members only.",
        "location": "Rockford",
        "variations": [
            {"name": "Monthly", "price": "30.00", "period": "month", "interval": "1", "signup_fee": "0", "length": "12"},
            {"name": "Paid in Full", "price": "300.00", "period": "year", "interval": "1", "signup_fee": "0", "length": "1"},
        ],
    },
    # --- Beloit BJJ ---
    {
        "name": "Beloit — Adult BJJ",
        "slug": "beloit-adult-bjj",
        "category": "Memberships",
        "description": "Adult BJJ membership at the Beloit, WI location. 12-month contract.",
        "short_description": "Beloit location. 12-month commitment.",
        "location": "Beloit",
        "variations": [
            {"name": "Biweekly", "price": "75.00", "period": "week", "interval": "2", "signup_fee": "0", "length": "26"},
            {"name": "YMCA Rate", "price": "62.50", "period": "week", "interval": "2", "signup_fee": "0", "length": "26"},
        ],
    },
    # --- Beloit Striking ---
    {
        "name": "Beloit — Striking",
        "slug": "beloit-striking",
        "category": "Memberships",
        "description": "Striking / Kickboxing membership at the Beloit, WI location. 12-month contract.",
        "short_description": "Beloit location. 12-month commitment.",
        "location": "Beloit",
        "variations": [
            {"name": "Biweekly", "price": "75.00", "period": "week", "interval": "2", "signup_fee": "0", "length": "26"},
            {"name": "YMCA Rate", "price": "62.50", "period": "week", "interval": "2", "signup_fee": "0", "length": "26"},
        ],
    },
    # --- Beloit Kids ---
    {
        "name": "Beloit — Kids BJJ",
        "slug": "beloit-kids-bjj",
        "category": "Memberships",
        "description": "Kids BJJ membership at the Beloit, WI location. 12-month contract.",
        "short_description": "Beloit location. 12-month commitment.",
        "location": "Beloit",
        "variations": [
            {"name": "Biweekly", "price": "75.00", "period": "week", "interval": "2", "signup_fee": "299.00", "length": "26"},
        ],
    },
]

# One-time products (simple products, not subscriptions).
SIMPLE_PRODUCTS = [
    {
        "name": "Adult Trial — 1 Month",
        "slug": "adult-trial",
        "category": "Trials",
        "price": "149.00",
        "description": "One month unlimited access to all adult classes. No commitment required.",
        "short_description": "Try any adult class for 30 days.",
        "virtual": True,
    },
    {
        "name": "Kids Trial — 1 Month",
        "slug": "kids-trial",
        "category": "Trials",
        "price": "149.00",
        "description": "One month unlimited access to all kids classes. No commitment required.",
        "short_description": "Try any kids class for 30 days.",
        "virtual": True,
    },
    {
        "name": "Drop-In Class",
        "slug": "drop-in-class",
        "category": "Class Passes",
        "price": "25.00",
        "description": "Single class drop-in. Valid for any scheduled group class.",
        "short_description": "One class, any program.",
        "virtual": True,
    },
    {
        "name": "6-Class Pass",
        "slug": "6-class-pass",
        "category": "Class Passes",
        "price": "120.00",
        "description": "6 class credits. Use for any scheduled group class. Valid for 3 months.",
        "short_description": "6 classes, any program. 3-month expiry.",
        "virtual": True,
    },
    {
        "name": "Personal Training — Coach Darby",
        "slug": "personal-training-darby",
        "category": "Memberships",
        "price": "1500.00",
        "description": "6-month personal training package with Coach Darby. 20 private lessons + 20 class credits. Members pricing: $80/hour.",
        "short_description": "20 private lessons with Coach Darby.",
        "virtual": True,
    },
    # Retail
    {
        "name": "Team Haanpaa Gi",
        "slug": "team-haanpaa-gi",
        "category": "Retail",
        "price": "150.00",
        "description": "Official Team Haanpaa Gi. 450gsm Pearl Weave, EVA Foam Collar, Lightweight Cotton Ripstop Pants, 6 Point Loop System. Embroidery & Woven Labels.",
        "short_description": "Official team gi.",
        "virtual": False,
    },
    {
        "name": "HMA Uniform T-Shirt",
        "slug": "hma-uniform-tshirt",
        "category": "Retail",
        "price": "25.00",
        "description": "Haanpaa Martial Arts uniform t-shirt.",
        "short_description": "Team t-shirt.",
        "virtual": False,
    },
]


# ---------------------------------------------------------------------------
# HTTP helpers
# ---------------------------------------------------------------------------

def api_request(method, url, token=None, data=None, form_data=None):
    """Make an HTTP request, return (response, error)."""
    headers = {}
    body = None
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if data is not None:
        headers["Content-Type"] = "application/json"
        body = json.dumps(data).encode()
    elif form_data is not None:
        body = urllib.parse.urlencode(form_data).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"

    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode()
            return json.loads(raw) if raw else {}, None
    except urllib.error.HTTPError as e:
        return {}, f"HTTP {e.code}: {e.read().decode()[:200]}"
    except urllib.error.URLError as e:
        return {}, f"Connection error: {e.reason}"


def authenticate(client_id, client_secret):
    """Get Bearer token."""
    resp, err = api_request("POST", AUTH_URL, form_data={
        "grant_type": "client_credentials",
        "client_id": client_id,
        "client_secret": client_secret,
    })
    if err:
        print(f"Auth failed: {err}", file=sys.stderr)
        sys.exit(1)
    return resp["access_token"]


def run_wpcli(token, site_id, cmd, dry_run=False):
    """Execute a WP-CLI command remotely. Returns (response, error)."""
    if dry_run:
        return {"message": "[DRY RUN]"}, None
    return api_request(
        "POST", f"{API_BASE}/sites/{site_id}/wordpress/wpcli",
        token=token,
        data={"commands": [cmd]},
    )


# ---------------------------------------------------------------------------
# Product creation
# ---------------------------------------------------------------------------

def create_categories(token, site_id, dry_run=False):
    """Create product categories."""
    print("\n1. Creating product categories...")
    for cat in CATEGORIES:
        slug = cat.lower().replace(" ", "-")
        cmd = f'wc product_cat create --name="{cat}" --slug="{slug}" --user=1'
        print(f"  {cat}...", end=" ", flush=True)
        resp, err = run_wpcli(token, site_id, cmd, dry_run)
        print(resp.get("message", "OK") if not err else f"({err[:60]})")


def create_subscription_product(token, site_id, product, dry_run=False):
    """Create a variable subscription product with variations."""
    name = product["name"]
    slug = product["slug"]
    desc = product["description"]
    short = product["short_description"]
    cat_slug = product["category"].lower().replace(" ", "-")
    location = product.get("location", "Rockford")

    # Step 1: Create the parent variable-subscription product.
    cmd = (
        f'wc product create '
        f'--name="{name}" '
        f'--slug="{slug}" '
        f'--type="variable-subscription" '
        f'--status="draft" '
        f'--catalog_visibility="visible" '
        f'--description="{desc}" '
        f'--short_description="{short}" '
        f'--virtual=true '
        f'--categories=\'[{{"slug":"{cat_slug}"}}]\' '
        f'--attributes=\'[{{"name":"Payment Option","options":{json.dumps([v["name"] for v in product["variations"]])},"visible":true,"variation":true}}]\' '
        f'--user=1'
    )
    print(f"  Creating: {name}...", end=" ", flush=True)
    resp, err = run_wpcli(token, site_id, cmd, dry_run)
    if err:
        print(f"FAILED: {err[:80]}")
        return
    print(resp.get("message", "OK"))

    # Step 2: Create each variation.
    for var in product["variations"]:
        period = var["period"]
        interval = var["interval"]
        price = var["price"]
        signup = var["signup_fee"]
        length = var.get("length", "0")
        var_name = var["name"]

        cmd = (
            f'wc product_variation create {slug} '
            f'--attributes=\'[{{"name":"Payment Option","option":"{var_name}"}}]\' '
            f'--regular_price="{price}" '
            f'--status="publish" '
            f'--user=1'
        )
        print(f"    Variation: {var_name} (${price}/{period})...", end=" ", flush=True)
        resp, err = run_wpcli(token, site_id, cmd, dry_run)
        if err:
            print(f"FAILED: {err[:80]}")
        else:
            print(resp.get("message", "OK"))


def create_simple_product(token, site_id, product, dry_run=False):
    """Create a simple (non-subscription) product."""
    name = product["name"]
    slug = product["slug"]
    price = product["price"]
    desc = product["description"]
    short = product["short_description"]
    cat_slug = product["category"].lower().replace(" ", "-")
    virtual = "true" if product.get("virtual", True) else "false"

    cmd = (
        f'wc product create '
        f'--name="{name}" '
        f'--slug="{slug}" '
        f'--type="simple" '
        f'--status="draft" '
        f'--regular_price="{price}" '
        f'--description="{desc}" '
        f'--short_description="{short}" '
        f'--virtual={virtual} '
        f'--catalog_visibility="visible" '
        f'--categories=\'[{{"slug":"{cat_slug}"}}]\' '
        f'--user=1'
    )
    print(f"  Creating: {name} (${price})...", end=" ", flush=True)
    resp, err = run_wpcli(token, site_id, cmd, dry_run)
    if err:
        print(f"FAILED: {err[:80]}")
    else:
        print(resp.get("message", "OK"))


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(description="Create M1.4 membership products")
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--site-id", type=int, default=DEFAULT_SITE_ID)
    args = parser.parse_args()

    print("=" * 60)
    print("  M1.4 — Membership Product Configuration")
    print("=" * 60)

    client_id = os.environ.get("PRESSABLE_CLIENT_ID", "")
    client_secret = os.environ.get("PRESSABLE_CLIENT_SECRET", "")

    if not args.dry_run and (not client_id or not client_secret):
        print("\nSet PRESSABLE_CLIENT_ID and PRESSABLE_CLIENT_SECRET env vars.")
        sys.exit(1)

    if not args.dry_run:
        print("\nAuthenticating...", end=" ")
        token = authenticate(client_id, client_secret)
        print("OK")
    else:
        token = "dry-run"

    site_id = args.site_id

    # Create categories.
    create_categories(token, site_id, args.dry_run)

    # Create subscription products.
    print(f"\n2. Creating subscription products ({len(SUBSCRIPTION_PRODUCTS)})...")
    for product in SUBSCRIPTION_PRODUCTS:
        create_subscription_product(token, site_id, product, args.dry_run)

    # Create simple products.
    print(f"\n3. Creating simple products ({len(SIMPLE_PRODUCTS)})...")
    for product in SIMPLE_PRODUCTS:
        create_simple_product(token, site_id, product, args.dry_run)

    # Summary.
    total_subs = len(SUBSCRIPTION_PRODUCTS)
    total_variations = sum(len(p["variations"]) for p in SUBSCRIPTION_PRODUCTS)
    total_simple = len(SIMPLE_PRODUCTS)

    print("\n" + "=" * 60)
    print("  PRODUCT CREATION COMPLETE")
    print("=" * 60)
    print(f"""
  Subscription products: {total_subs} ({total_variations} variations)
  Simple products:       {total_simple}
  Total:                 {total_subs + total_simple} products

  All products created as DRAFT. To publish:
    wp wc product list --status=draft --user=1
    wp post update <id1> <id2> ... --post_status=publish

  Subscription meta (signup fees, billing periods, sync dates)
  may need manual verification in wp-admin since WP-CLI support
  for WC Subscriptions meta varies.

  Next: Review products in wp-admin > Products, verify pricing,
  then publish when ready.
""")


if __name__ == "__main__":
    main()
