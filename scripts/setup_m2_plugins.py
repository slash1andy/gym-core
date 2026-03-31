#!/usr/bin/env python3
"""
setup_m2_plugins.py — Install and configure M2 plugins on Pressable via API.

Installs: jetpack-crm, mailpoet, automatewoo (premium — handled gracefully).
Configures: MailPoet sender settings, Jetpack CRM WooCommerce module.

Usage:
    python3 scripts/setup_m2_plugins.py
    python3 scripts/setup_m2_plugins.py --dry-run
"""

import argparse
import json
import ssl
import sys
import time
import urllib.parse
import urllib.request

# ── Pressable config ────────────────────────────────────────────────
SITE_ID = "1630891"
TOKEN_URL = "https://my.pressable.com/auth/token"
CLIENT_ID = "HenB445AN-Fxqstk8EZnyA58UWaaPV_HtvpIiGMpGcw"
CLIENT_SECRET = "3bB1iFxF_vf1jJ4mHIpuguV7cCJ0vP7UfaiJx1fjepI"
WPCLI_URL = f"https://my.pressable.com/v1/sites/{SITE_ID}/wordpress/wpcli"

# ── Plugin list ─────────────────────────────────────────────────────
PLUGINS = [
    {"slug": "jetpack-crm", "source": "wordpress.org"},
    {"slug": "mailpoet", "source": "wordpress.org"},
    {"slug": "automatewoo", "source": "premium",
     "note": "AutomateWoo is a premium WooCommerce plugin. "
             "It must be uploaded manually or installed via WooCommerce.com subscription."},
]

# ── MailPoet config ─────────────────────────────────────────────────
MAILPOET_SENDER_NAME = "Haanpaa Martial Arts"
MAILPOET_SENDER_EMAIL = "info@haanpaa.com"
MAILPOET_REPLY_TO = "info@haanpaa.com"


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
    """Fire a WP-CLI command on Pressable (async — returns job ID)."""
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


def main():
    parser = argparse.ArgumentParser(description="Install & configure M2 plugins on Pressable")
    parser.add_argument("--dry-run", action="store_true", help="Print commands without executing")
    args = parser.parse_args()

    # ── Authenticate ────────────────────────────────────────────────
    print("=== M2 Plugin Setup ===\n")
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

    # ── Step 1: Install & activate plugins ──────────────────────────
    print("--- Step 1: Install & Activate Plugins ---\n")
    for plugin in PLUGINS:
        slug = plugin["slug"]
        source = plugin["source"]

        if source == "premium":
            print(f"[{slug}] SKIPPED — {plugin['note']}")
            print(f"  To install manually: upload the ZIP via WP Admin > Plugins > Add New > Upload.\n")
            continue

        print(f"[{slug}] Installing from {source}...")
        run_wpcli(token, f"plugin install {slug} --activate", dry_run=args.dry_run)
        print()

    # Small delay between install and config steps (async commands)
    if not args.dry_run:
        print("Waiting 10s for installs to complete...\n")
        time.sleep(10)

    # ── Step 2: Configure MailPoet ──────────────────────────────────
    print("--- Step 2: Configure MailPoet ---\n")
    mailpoet_settings = [
        (
            "MailPoet sender name",
            f"option update mailpoet_settings --format=json "
            f"'{{\"sender\":{{\"name\":\"{MAILPOET_SENDER_NAME}\"}}}}'",
        ),
        (
            "MailPoet sender email",
            f"option update mailpoet_settings --format=json "
            f"'{{\"sender\":{{\"address\":\"{MAILPOET_SENDER_EMAIL}\"}}}}'",
        ),
        (
            "MailPoet reply-to",
            f"option update mailpoet_settings --format=json "
            f"'{{\"reply_to\":{{\"address\":\"{MAILPOET_REPLY_TO}\"}}}}'",
        ),
    ]

    # MailPoet stores settings as a serialized array in wp_options.
    # Use eval to set individual keys safely.
    mailpoet_eval = (
        "eval '$s = get_option(\"mailpoet_settings\", []); "
        f"$s[\"sender\"][\"name\"] = \"{MAILPOET_SENDER_NAME}\"; "
        f"$s[\"sender\"][\"address\"] = \"{MAILPOET_SENDER_EMAIL}\"; "
        f"$s[\"reply_to\"][\"address\"] = \"{MAILPOET_REPLY_TO}\"; "
        "update_option(\"mailpoet_settings\", $s); "
        "echo \"MailPoet settings updated.\";'"
    )
    print("[mailpoet] Configuring sender settings...")
    run_wpcli(token, mailpoet_eval, dry_run=args.dry_run)
    print()

    # ── Step 3: Configure Jetpack CRM ───────────────────────────────
    print("--- Step 3: Configure Jetpack CRM ---\n")

    # Enable the WooCommerce Sync module in Jetpack CRM
    jpcrm_eval = (
        "eval '$mods = get_option(\"jpcrm_activeext\", []); "
        "if (!in_array(\"woo\", $mods)) { $mods[] = \"woo\"; "
        "update_option(\"jpcrm_activeext\", $mods); "
        "echo \"WooCommerce module enabled.\"; } "
        "else { echo \"WooCommerce module already active.\"; }'"
    )
    print("[jetpack-crm] Enabling WooCommerce integration module...")
    run_wpcli(token, jpcrm_eval, dry_run=args.dry_run)
    print()

    # ── Done ────────────────────────────────────────────────────────
    print("=== Setup complete ===")
    if args.dry_run:
        print("(No changes were made — dry run.)")
    else:
        print("Note: WP-CLI commands are async. Check WP Admin to verify.")
        print("Manual steps remaining:")
        print("  - Upload & activate AutomateWoo (premium plugin)")
        print("  - Verify MailPoet sender settings in MailPoet > Settings")
        print("  - Verify Jetpack CRM > Modules > WooCommerce is enabled")


if __name__ == "__main__":
    main()
