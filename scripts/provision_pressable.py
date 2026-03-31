#!/usr/bin/env python3
"""Pressable site provisioning script for Haanpaa Martial Arts.

Automates M1.1 environment setup:
  1. Authenticate with Pressable API (OAuth2 client credentials)
  2. Create a new site (or use existing)
  3. Set PHP version, edge cache, and debug constants
  4. Install and activate required plugins via API
  5. Print SSH/SFTP credentials and next steps

Usage:
  # Interactive — prompts for credentials:
  python3 scripts/provision_pressable.py

  # With environment variables:
  PRESSABLE_CLIENT_ID=xxx PRESSABLE_CLIENT_SECRET=yyy python3 scripts/provision_pressable.py

  # Dry run — shows what would happen without making API calls:
  python3 scripts/provision_pressable.py --dry-run

  # Use an existing site instead of creating a new one:
  python3 scripts/provision_pressable.py --site-id 12345

  # Create as sandbox (dev environment):
  python3 scripts/provision_pressable.py --sandbox

Requirements: Python 3.8+ (stdlib only, no pip installs)
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

SITE_NAME = "haanpaa-staging"
SITE_DISPLAY = "Haanpaa Martial Arts — Staging"
DATACENTER = "DFW"  # Dallas-Fort Worth (closest to Rockford, IL)
PHP_VERSION = "8.3"

# Plugins to install after provisioning.
# WooCommerce Subscriptions is premium — must be uploaded manually.
PLUGINS_TO_INSTALL = [
    "woocommerce",
    "woocommerce-payments",  # WooPayments
]

PLUGINS_MANUAL = [
    "woocommerce-subscriptions  (build from woocommerce/woocommerce-subscriptions repo, install via WP-CLI)",
]


# ---------------------------------------------------------------------------
# HTTP helpers (stdlib only)
# ---------------------------------------------------------------------------

def api_request(method, url, token=None, data=None, form_data=None):
    """Make an HTTP request and return parsed JSON."""
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
        with urllib.request.urlopen(req, timeout=30) as resp:
            raw = resp.read().decode()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        error_body = e.read().decode() if e.fp else ""
        print(f"\n  API error {e.code}: {error_body}", file=sys.stderr)
        sys.exit(1)


def wait_for_site(token, site_id, timeout=120):
    """Poll until the site is ready (state != 'deploying')."""
    print("  Waiting for site to be ready", end="", flush=True)
    start = time.time()
    while time.time() - start < timeout:
        resp = api_request("GET", f"{API_BASE}/sites/{site_id}", token=token)
        site = resp.get("data", resp)
        state = site.get("state", "unknown")
        if state not in ("deploying", "creating", "pending"):
            print(f" — {state}")
            return site
        print(".", end="", flush=True)
        time.sleep(5)
    print(" — timed out")
    return None


# ---------------------------------------------------------------------------
# Steps
# ---------------------------------------------------------------------------

def authenticate(client_id, client_secret, dry_run=False):
    """Get a Bearer token via OAuth2 client credentials."""
    print("\n1. Authenticating with Pressable API...")
    if dry_run:
        print("  [DRY RUN] Would POST to /auth/token")
        return "dry-run-token"

    resp = api_request("POST", AUTH_URL, form_data={
        "grant_type": "client_credentials",
        "client_id": client_id,
        "client_secret": client_secret,
    })
    token = resp.get("access_token")
    if not token:
        print(f"  Auth failed: {resp}", file=sys.stderr)
        sys.exit(1)
    expires = resp.get("expires_in", "?")
    print(f"  Authenticated (token expires in {expires}s)")
    return token


def create_site(token, sandbox=False, dry_run=False):
    """Create a new Pressable site."""
    print("\n2. Creating site...")
    payload = {
        "name": SITE_NAME,
        "install": "wordpress",
        "datacenter_code": DATACENTER,
        "php_version": PHP_VERSION,
    }
    if sandbox:
        payload["sandbox"] = True

    print(f"  Name:       {SITE_NAME}")
    print(f"  Datacenter: {DATACENTER}")
    print(f"  PHP:        {PHP_VERSION}")
    print(f"  Sandbox:    {sandbox}")

    if dry_run:
        print("  [DRY RUN] Would POST to /sites")
        return {"id": "dry-run", "name": SITE_NAME, "url": f"https://{SITE_NAME}.mystagingwebsite.com"}

    resp = api_request("POST", f"{API_BASE}/sites", token=token, data=payload)
    site = resp.get("data", resp)
    site_id = site.get("id")
    print(f"  Site created: ID {site_id}")

    # Wait for provisioning to complete.
    site = wait_for_site(token, site_id) or site
    return site


def get_site(token, site_id, dry_run=False):
    """Fetch an existing site by ID."""
    print(f"\n2. Fetching existing site {site_id}...")
    if dry_run:
        print("  [DRY RUN] Would GET /sites/{site_id}")
        return {"id": site_id, "name": SITE_NAME}

    resp = api_request("GET", f"{API_BASE}/sites/{site_id}", token=token)
    site = resp.get("data", resp)
    print(f"  Found: {site.get('displayName', site.get('name', '?'))}")
    return site


def configure_site(token, site, dry_run=False):
    """Update site settings if needed."""
    site_id = site.get("id")
    current_php = site.get("phpVersion", "")
    print("\n3. Configuring site...")

    if current_php != PHP_VERSION:
        print(f"  Updating PHP {current_php} → {PHP_VERSION}")
        if not dry_run:
            api_request("PUT", f"{API_BASE}/sites/{site_id}", token=token, data={
                "php_version": PHP_VERSION,
            })
    else:
        print(f"  PHP already {PHP_VERSION}")

    # Enable edge cache.
    print("  Enabling edge cache...")
    if not dry_run:
        try:
            api_request("PUT", f"{API_BASE}/sites/{site_id}/edge-cache", token=token, data={
                "enabled": True,
            })
        except SystemExit:
            print("  (edge cache endpoint may not be available — skipping)")

    print("  Done.")


def install_plugins(token, site, dry_run=False):
    """Install and activate plugins via the Pressable API."""
    site_id = site.get("id")
    print("\n4. Installing plugins...")

    for slug in PLUGINS_TO_INSTALL:
        print(f"  Installing: {slug}")
        if not dry_run:
            try:
                api_request("POST", f"{API_BASE}/sites/{site_id}/plugins", token=token, data={
                    "slug": slug,
                    "status": "active",
                })
                print(f"    ✓ {slug} installed and activated")
            except SystemExit:
                print(f"    ⚠ Failed to install {slug} via API — install manually via WP-CLI")

    if PLUGINS_MANUAL:
        print("\n  Manual installs needed:")
        for note in PLUGINS_MANUAL:
            print(f"    • {note}")


def print_summary(site, dry_run=False):
    """Print next steps and credentials info."""
    site_url = site.get("url", f"https://{SITE_NAME}.mystagingwebsite.com")
    site_id = site.get("id", "?")

    print("\n" + "=" * 60)
    print("  PROVISIONING COMPLETE")
    print("=" * 60)
    print(f"""
  Site ID:    {site_id}
  URL:        {site_url}
  wp-admin:   {site_url}/wp-admin
  Dashboard:  https://my.pressable.com/sites/{site_id}

  SSH/SFTP credentials are in the Pressable dashboard:
    https://my.pressable.com/sites/{site_id}/sftp-ssh

  Next steps (wp-admin or WP-CLI over SSH):
    1. Verify WooCommerce + WooPayments are active
    2. Install WC Subscriptions (build zip from woocommerce/woocommerce-subscriptions):
       cd /path/to/woocommerce-subscriptions && npm run build
       wp plugin install release/woocommerce-subscriptions.zip --activate
    3. Connect GitHub deploy in Pressable dashboard:
       - Repo URL: <this repo's HTTPS clone URL>
       - Branch: main
       - Subdirectory: (blank)
       - Destination: wp-content/
       - Delete extra files: OFF
    4. Enable HPOS:
       wp option update woocommerce_custom_orders_table_enabled yes
    5. Enable block-based checkout:
       wp option update woocommerce_cart_page_id <page_id>
    6. Set permalinks:
       wp rewrite structure '/%postname%/' --hard
    7. Enable debug logging:
       Add to wp-config.php:
         define( 'WP_DEBUG', true );
         define( 'WP_DEBUG_LOG', true );
         define( 'WP_DEBUG_DISPLAY', false );
         define( 'SCRIPT_DEBUG', true );
    8. Proceed to M1.2 — WooPayments onboarding in wp-admin
""")

    if dry_run:
        print("  [DRY RUN] No actual changes were made.\n")


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Provision a Pressable site for Haanpaa Martial Arts (M1.1)"
    )
    parser.add_argument("--dry-run", action="store_true", help="Show what would happen without making API calls")
    parser.add_argument("--site-id", type=int, help="Use an existing site instead of creating a new one")
    parser.add_argument("--sandbox", action="store_true", help="Create as a sandbox (dev) site")
    args = parser.parse_args()

    print("=" * 60)
    print("  Haanpaa Martial Arts — Pressable Provisioning (M1.1)")
    print("=" * 60)

    # Get credentials.
    client_id = os.environ.get("PRESSABLE_CLIENT_ID", "")
    client_secret = os.environ.get("PRESSABLE_CLIENT_SECRET", "")

    if not args.dry_run and (not client_id or not client_secret):
        print("\nPressable API credentials needed.")
        print("Get them from: my.pressable.com > User Settings > API Applications\n")
        client_id = input("  Client ID: ").strip()
        client_secret = input("  Client Secret: ").strip()
        if not client_id or not client_secret:
            print("  Credentials required. Exiting.", file=sys.stderr)
            sys.exit(1)

    # Run steps.
    token = authenticate(client_id, client_secret, dry_run=args.dry_run)

    if args.site_id:
        site = get_site(token, args.site_id, dry_run=args.dry_run)
    else:
        site = create_site(token, sandbox=args.sandbox, dry_run=args.dry_run)

    configure_site(token, site, dry_run=args.dry_run)
    install_plugins(token, site, dry_run=args.dry_run)
    print_summary(site, dry_run=args.dry_run)


if __name__ == "__main__":
    main()
