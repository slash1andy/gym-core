#!/usr/bin/env python3
"""
import_crm_contacts.py — Import cleaned contacts into Jetpack CRM via WP-CLI on Pressable.

Reads a cleaned CSV (from clean_ghl_contacts.py) and creates contacts in Jetpack CRM
using WP-CLI eval commands dispatched through the Pressable API.

Usage:
    python3 scripts/import_crm_contacts.py cleaned_contacts.csv
    python3 scripts/import_crm_contacts.py cleaned_contacts.csv --dry-run
    python3 scripts/import_crm_contacts.py cleaned_contacts.csv --limit 20
    python3 scripts/import_crm_contacts.py cleaned_contacts.csv --batch-size 5
"""

import argparse
import csv
import json
import os
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

# ── Batch settings ──────────────────────────────────────────────────
DEFAULT_BATCH_SIZE = 10
BATCH_DELAY_SECONDS = 3  # Delay between batches to avoid rate limits

# ── Category to Jetpack CRM status mapping ──────────────────────────
CATEGORY_TO_STATUS = {
    "member": "Customer",
    "lead": "Lead",
    "lapsed": "Customer",      # Keep as customer, tag differentiates
    "prospect": "Lead",
    "trial": "Lead",
}


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
    """Fire a WP-CLI command on Pressable (async — returns immediately)."""
    if dry_run:
        return {"status": "dry-run"}

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
        return json.loads(resp.read())
    except urllib.error.HTTPError as exc:
        error_body = exc.read().decode()[:300]
        print(f"    API ERROR {exc.code}: {error_body}")
        return None


def escape_php(value):
    """Escape a string for safe inclusion in a PHP single-quoted string."""
    return value.replace("\\", "\\\\").replace("'", "\\'")


def build_create_contact_cmd(contact):
    """Build a WP-CLI eval command to create a Jetpack CRM contact."""
    fname = escape_php(contact.get("first_name", ""))
    lname = escape_php(contact.get("last_name", ""))
    email = escape_php(contact.get("email", ""))
    phone = escape_php(contact.get("phone", ""))
    category = contact.get("category", "lead")
    status = CATEGORY_TO_STATUS.get(category, "Lead")
    tags_str = contact.get("tags", "")

    # Build tag list: always include the category, plus original tags
    tag_list = [category]
    if tags_str:
        for t in tags_str.split(","):
            t = t.strip()
            if t and t.lower() != category:
                tag_list.append(t)

    # Jetpack CRM DAL API to create contact
    # Uses zeroBS_integrations_addOrUpdateContact (available since JPCRM v3+)
    tags_php = ", ".join(f"'{escape_php(t)}'" for t in tag_list)

    php_code = (
        "if (!function_exists('zeroBS_integrations_addOrUpdateContact')) { "
        "  echo 'JPCRM not active'; return; "
        "} "
        "$data = array("
        f"  'fname' => '{fname}',"
        f"  'lname' => '{lname}',"
        f"  'email' => '{email}',"
        f"  'hometel' => '{phone}',"
        f"  'status' => '{status}',"
        "); "
        f"$tags = array({tags_php}); "
        "$id = zeroBS_integrations_addOrUpdateContact("
        "  'import', array(), "
        "  array("
        "    'contact_data' => $data,"
        "    'contact_tags' => $tags,"
        "  )"
        "); "
        "if ($id) { echo 'Created #' . $id; } "
        "else { echo 'Failed or duplicate'; }"
    )

    return f"eval '{php_code}'"


def main():
    parser = argparse.ArgumentParser(
        description="Import cleaned contacts into Jetpack CRM via Pressable WP-CLI"
    )
    parser.add_argument("input", help="Path to cleaned CSV (from clean_ghl_contacts.py)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Print commands without executing")
    parser.add_argument("--limit", type=int, default=0,
                        help="Limit number of contacts to import (0 = all)")
    parser.add_argument("--batch-size", type=int, default=DEFAULT_BATCH_SIZE,
                        help=f"Contacts per batch (default: {DEFAULT_BATCH_SIZE})")
    args = parser.parse_args()

    if not os.path.isfile(args.input):
        print(f"ERROR: File not found: {args.input}", file=sys.stderr)
        sys.exit(1)

    # ── Read cleaned CSV ────────────────────────────────────────────
    with open(args.input, newline="", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        contacts = list(reader)

    total = len(contacts)
    if args.limit > 0:
        contacts = contacts[: args.limit]

    print(f"=== Jetpack CRM Contact Import ===\n")
    print(f"  Source:     {args.input}")
    print(f"  Total rows: {total}")
    print(f"  Importing:  {len(contacts)}")
    print(f"  Batch size: {args.batch_size}")
    if args.dry_run:
        print(f"  Mode:       DRY RUN")
    print()

    # ── Authenticate ────────────────────────────────────────────────
    if args.dry_run:
        token = "dry-run-token"
    else:
        print("Authenticating with Pressable API...")
        try:
            token = get_token()
            print("Authenticated.\n")
        except Exception as exc:
            print(f"ERROR: Could not authenticate: {exc}", file=sys.stderr)
            sys.exit(1)

    # ── Import in batches ───────────────────────────────────────────
    success = 0
    errors = 0

    for i, contact in enumerate(contacts):
        batch_num = (i // args.batch_size) + 1
        pos_in_batch = (i % args.batch_size) + 1

        name = f"{contact.get('first_name', '')} {contact.get('last_name', '')}".strip() or "(unnamed)"
        email = contact.get("email", "")
        category = contact.get("category", "lead")

        print(f"  [{i + 1}/{len(contacts)}] {name} <{email}> [{category}]")

        cmd = build_create_contact_cmd(contact)

        if args.dry_run:
            print(f"    WP-CLI> {cmd[:120]}...")
        else:
            result = run_wpcli(token, cmd, dry_run=False)
            if result is not None:
                success += 1
            else:
                errors += 1

        # Batch delay
        if pos_in_batch == args.batch_size and i < len(contacts) - 1:
            if not args.dry_run:
                print(f"\n  -- Batch {batch_num} complete, waiting {BATCH_DELAY_SECONDS}s --\n")
                time.sleep(BATCH_DELAY_SECONDS)
            else:
                print(f"\n  -- Batch {batch_num} complete --\n")

    # ── Summary ─────────────────────────────────────────────────────
    print(f"\n=== Import Complete ===")
    print(f"  Dispatched: {success}")
    print(f"  Errors:     {errors}")
    print(f"  Skipped:    {total - len(contacts)}")
    if not args.dry_run:
        print("\nNote: WP-CLI commands are async. Check Jetpack CRM > Contacts to verify.")
    else:
        print("\n[DRY RUN] No contacts were imported.")


if __name__ == "__main__":
    main()
