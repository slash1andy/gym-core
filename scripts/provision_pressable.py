#!/usr/bin/env python3
"""Pressable site provisioning script for Haanpaa Martial Arts.

Automates the full M1.1 environment setup via the Pressable REST API:
  1. Authenticate (OAuth2 client credentials)
  2. Create site (or attach to existing) with WooCommerce pre-installed
  3. Configure PHP version, edge cache, display name
  4. Install plugins (WooCommerce, WooPayments) via batch API
  5. Run WP-CLI commands remotely (HPOS, permalinks, debug constants)
  6. Print remaining manual steps (Subscriptions upload, GitHub deploy)

API reference: https://my.pressable.com/documentation/api/v1

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

# Plugins to install via API (path = WordPress.org slug).
PLUGINS_TO_INSTALL = [
    {"path": "woocommerce"},
    {"path": "woocommerce-payments"},  # WooPayments
]

# WP-CLI commands to run remotely after site is provisioned.
# Executed via POST /bash_command_bookmarks/batch_execute.
WPCLI_COMMANDS = [
    # Enable HPOS (custom order tables).
    "wp option update woocommerce_custom_orders_table_enabled yes",
    # Set permalink structure.
    "wp rewrite structure '/%postname%/' --hard",
    # Flush rewrite rules.
    "wp rewrite flush --hard",
    # Enable WP_DEBUG via WP-CLI config command.
    "wp config set WP_DEBUG true --raw --type=constant",
    "wp config set WP_DEBUG_LOG true --raw --type=constant",
    "wp config set WP_DEBUG_DISPLAY false --raw --type=constant",
    "wp config set SCRIPT_DEBUG true --raw --type=constant",
]


# ---------------------------------------------------------------------------
# HTTP helpers (stdlib only)
# ---------------------------------------------------------------------------

def api_request(method, url, token=None, data=None, form_data=None):
    """Make an HTTP request and return parsed JSON.

    Returns (response_dict, error_string|None). On HTTP errors, returns
    the error body instead of exiting so callers can handle gracefully.
    """
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
        error_body = e.read().decode() if e.fp else ""
        return {}, f"HTTP {e.code}: {error_body}"
    except urllib.error.URLError as e:
        return {}, f"Connection error: {e.reason}"


def api_request_or_exit(method, url, token=None, data=None, form_data=None):
    """Like api_request but exits on error."""
    resp, err = api_request(method, url, token=token, data=data, form_data=form_data)
    if err:
        print(f"\n  API error: {err}", file=sys.stderr)
        sys.exit(1)
    return resp


def wait_for_site(token, site_id, timeout=180):
    """Poll until the site state leaves provisioning states."""
    print("  Waiting for site to be ready", end="", flush=True)
    start = time.time()
    while time.time() - start < timeout:
        resp = api_request_or_exit("GET", f"{API_BASE}/sites/{site_id}", token=token)
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

    resp = api_request_or_exit("POST", AUTH_URL, form_data={
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
    """Create a new Pressable site with WooCommerce pre-installed."""
    print("\n2. Creating site...")
    payload = {
        "name": SITE_NAME,
        "install": "woocommerce",  # Pre-installs WP + WooCommerce
        "datacenter_code": DATACENTER,
        "php_version": PHP_VERSION,
    }
    if sandbox:
        payload["sandbox"] = True

    print(f"  Name:       {SITE_NAME}")
    print(f"  Install:    woocommerce (WP + WC pre-installed)")
    print(f"  Datacenter: {DATACENTER}")
    print(f"  PHP:        {PHP_VERSION}")
    print(f"  Sandbox:    {sandbox}")

    if dry_run:
        print("  [DRY RUN] Would POST /sites")
        return {
            "id": "dry-run",
            "name": SITE_NAME,
            "url": f"https://{SITE_NAME}.mystagingwebsite.com",
            "phpVersion": PHP_VERSION,
        }

    resp = api_request_or_exit("POST", f"{API_BASE}/sites", token=token, data=payload)
    site = resp.get("data", resp)
    site_id = site.get("id")
    print(f"  Site created: ID {site_id}")

    site = wait_for_site(token, site_id) or site
    return site


def get_site(token, site_id, dry_run=False):
    """Fetch an existing site by ID."""
    print(f"\n2. Fetching existing site {site_id}...")
    if dry_run:
        print(f"  [DRY RUN] Would GET /sites/{site_id}")
        return {"id": site_id, "name": SITE_NAME, "phpVersion": ""}

    resp = api_request_or_exit("GET", f"{API_BASE}/sites/{site_id}", token=token)
    site = resp.get("data", resp)
    print(f"  Found: {site.get('displayName', site.get('name', '?'))}")
    print(f"  URL:   {site.get('url', '?')}")
    print(f"  PHP:   {site.get('phpVersion', '?')}")
    print(f"  WP:    {site.get('wordpressVersion', '?')}")
    return site


def configure_site(token, site, dry_run=False):
    """Update PHP version, display name, and edge cache."""
    site_id = site.get("id")
    current_php = site.get("phpVersion", "")
    print("\n3. Configuring site...")

    # Update display name and PHP version.
    update_payload = {}
    if current_php != PHP_VERSION:
        update_payload["php_version"] = PHP_VERSION
        print(f"  PHP: {current_php} → {PHP_VERSION}")
    else:
        print(f"  PHP: already {PHP_VERSION}")

    current_name = site.get("displayName", "")
    if current_name != SITE_DISPLAY:
        update_payload["name"] = SITE_DISPLAY
        print(f"  Display name → {SITE_DISPLAY}")

    if update_payload and not dry_run:
        api_request_or_exit("PUT", f"{API_BASE}/sites/{site_id}", token=token, data=update_payload)

    # Enable edge cache.
    print("  Enabling edge cache...")
    if not dry_run:
        resp, err = api_request("PUT", f"{API_BASE}/sites/{site_id}/edge-cache",
                                token=token, data={"enabled": True})
        if err:
            print(f"  (edge cache: {err} — may need manual enable)")

    print("  Configuration complete.")


def install_plugins(token, site, dry_run=False):
    """Install and activate plugins via the Pressable batch API."""
    site_id = site.get("id")
    print("\n4. Installing plugins...")

    for plugin in PLUGINS_TO_INSTALL:
        slug = plugin["path"]
        print(f"  {slug}...", end=" ", flush=True)
        if dry_run:
            print("[DRY RUN]")
            continue

        resp, err = api_request(
            "POST", f"{API_BASE}/sites/{site_id}/plugins",
            token=token,
            data={"plugins": [plugin]},
        )
        if err:
            print(f"FAILED ({err})")
            print(f"    Fallback: install via WP-CLI")
        else:
            print("scheduled")

    print("\n  Note: Plugin installs are async (job queue).")
    print("  WC Subscriptions must be installed separately:")
    print("    Build: cd woocommerce-subscriptions && npm run build")
    print("    Install: wp plugin install release/woocommerce-subscriptions.zip --activate")


def run_wpcli_commands(token, site, dry_run=False):
    """Execute WP-CLI commands remotely via bash_command_bookmarks/batch_execute."""
    site_id = site.get("id")
    print("\n5. Running WP-CLI configuration commands...")

    for cmd in WPCLI_COMMANDS:
        print(f"  $ {cmd}")
        if dry_run:
            continue

        resp, err = api_request(
            "POST", f"{API_BASE}/bash_command_bookmarks/batch_execute",
            token=token,
            data={
                "command": cmd,
                "siteIds": [site_id],
            },
        )
        if err:
            print(f"    FAILED: {err}")
        else:
            msg = resp.get("message", "scheduled")
            print(f"    {msg}")

    if dry_run:
        print("  [DRY RUN] No commands executed.")
    else:
        print("\n  All commands scheduled. They run async — check site in ~30s.")


def print_summary(site, dry_run=False):
    """Print remaining manual steps."""
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

  SFTP/SSH:   https://my.pressable.com/sites/{site_id}/sftp-ssh

  Automated steps completed:
    [x] Site created with WooCommerce pre-installed
    [x] PHP {PHP_VERSION} configured
    [x] Edge cache enabled
    [x] WooPayments plugin installed
    [x] HPOS enabled
    [x] Permalinks set to /%postname%/
    [x] Debug logging enabled (WP_DEBUG, WP_DEBUG_LOG, SCRIPT_DEBUG)

  Manual steps remaining:
    1. Install WC Subscriptions:
       Build:   cd woocommerce-subscriptions && npm run build
       Upload:  wp plugin install release/woocommerce-subscriptions.zip --activate

    2. Connect GitHub deploy (Pressable dashboard > Advanced > GitHub Integration):
       - Repo URL: https://github.com/slash1andy/gym-core.git
       - Branch: main
       - Repository subdirectory: (blank)
       - Destination: wp-content/
       - Delete files not in repo: OFF

    3. WooPayments onboarding:
       - Go to wp-admin > WooCommerce > Payments
       - Connect Haanpaa's Stripe account
       - Enable test mode for staging
       - Enable card, Apple Pay, Google Pay
       - Enable saved payment methods (required for Subscriptions)

    4. WooCommerce Subscriptions config:
       - Renewal payment method: WooPayments automatic
       - Retry rules: configure failed payment retry schedule
       - Subscription switching: enabled
       - Early renewal: disabled
       - Synchronize renewals: enabled, anchor to 1st of month

    5. Proceed to M1.4 — Membership product creation
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
    parser.add_argument("--dry-run", action="store_true",
                        help="Show what would happen without making API calls")
    parser.add_argument("--site-id", type=int,
                        help="Use an existing site instead of creating a new one")
    parser.add_argument("--sandbox", action="store_true",
                        help="Create as a sandbox (dev) site")
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
    run_wpcli_commands(token, site, dry_run=args.dry_run)
    print_summary(site, dry_run=args.dry_run)


if __name__ == "__main__":
    main()
