#!/usr/bin/env python3
"""Seed a complete test environment on the Pressable staging site.

Creates dummy members, belt ranks, attendance records, achievements,
enables WooPayments test mode, sets Twilio test credentials, and
configures all gym-core settings.

Usage:
  python3 scripts/seed_test_data.py
  python3 scripts/seed_test_data.py --dry-run
  python3 scripts/seed_test_data.py --clean          # Remove seeded data first
  python3 scripts/seed_test_data.py --skip-products --skip-pages

Requirements: Python 3.8+ (stdlib only). Pressable API creds in .env or env vars.
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
TEST_EMAIL_DOMAIN = "test.gym-core.dev"
TEST_PASSWORD = "TestPass123!"

# ---------------------------------------------------------------------------
# Test member definitions
# ---------------------------------------------------------------------------

MEMBERS = [
    # --- Head Coaches (2) ---
    {
        "first": "Dave", "last": "Haanpaa", "login": "dave.haanpaa",
        "email": f"dave.haanpaa@{TEST_EMAIL_DOMAIN}",
        "role": "gym_head_coach", "location": "rockford",
        "ranks": {"adult-bjj": ("black", 2, "2015-06-01 10:00:00")},
        "attendance": 500, "badges_extra": ["streak_26"],
    },
    {
        "first": "Lisa", "last": "Mendez", "login": "lisa.mendez",
        "email": f"lisa.mendez@{TEST_EMAIL_DOMAIN}",
        "role": "gym_head_coach", "location": "beloit",
        "ranks": {"adult-bjj": ("black", 1, "2018-03-15 10:00:00"), "kickboxing": ("level-2", 0, "2017-01-10 10:00:00")},
        "attendance": 450, "badges_extra": ["streak_26", "early_bird"],
    },
    # --- Coaches (3) ---
    {
        "first": "Marcus", "last": "Rivera", "login": "marcus.rivera",
        "email": f"marcus.rivera@{TEST_EMAIL_DOMAIN}",
        "role": "gym_coach", "location": "rockford",
        "ranks": {"adult-bjj": ("brown", 3, "2023-09-20 10:00:00")},
        "attendance": 380, "badges_extra": ["streak_12"],
    },
    {
        "first": "Sarah", "last": "Kim", "login": "sarah.kim",
        "email": f"sarah.kim@{TEST_EMAIL_DOMAIN}",
        "role": "gym_coach", "location": "rockford",
        "ranks": {"adult-bjj": ("purple", 4, "2024-01-10 10:00:00"), "kickboxing": ("level-2", 0, "2023-06-01 10:00:00")},
        "attendance": 290, "badges_extra": ["streak_12"],
    },
    {
        "first": "Tyler", "last": "Brooks", "login": "tyler.brooks",
        "email": f"tyler.brooks@{TEST_EMAIL_DOMAIN}",
        "role": "gym_coach", "location": "beloit",
        "ranks": {"kickboxing": ("level-2", 0, "2024-05-15 10:00:00"), "adult-bjj": ("blue", 4, "2024-08-01 10:00:00")},
        "attendance": 200, "badges_extra": [],
    },
    # --- Experienced Adults (5) ---
    {
        "first": "Jake", "last": "Torres", "login": "jake.torres",
        "email": f"jake.torres@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"adult-bjj": ("blue", 2, "2025-06-15 10:00:00")},
        "attendance": 85, "badges_extra": [],
    },
    {
        "first": "Maria", "last": "Santos", "login": "maria.santos",
        "email": f"maria.santos@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"adult-bjj": ("purple", 1, "2024-11-20 10:00:00"), "kickboxing": ("level-2", 0, "2024-03-10 10:00:00")},
        "attendance": 210, "badges_extra": ["streak_12"],
    },
    {
        "first": "Connor", "last": "Walsh", "login": "connor.walsh",
        "email": f"connor.walsh@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "beloit",
        "ranks": {"adult-bjj": ("blue", 0, "2025-09-01 10:00:00")},
        "attendance": 55, "badges_extra": ["streak_4"],
    },
    {
        "first": "Priya", "last": "Patel", "login": "priya.patel",
        "email": f"priya.patel@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"kickboxing": ("level-2", 0, "2025-01-20 10:00:00")},
        "attendance": 120, "badges_extra": [],
    },
    {
        "first": "Darnell", "last": "Jackson", "login": "darnell.jackson",
        "email": f"darnell.jackson@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"adult-bjj": ("brown", 1, "2024-06-10 10:00:00")},
        "attendance": 310, "badges_extra": ["streak_12", "early_bird"],
    },
    # --- Newer White Belts (3) ---
    {
        "first": "Emma", "last": "Larson", "login": "emma.larson",
        "email": f"emma.larson@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"adult-bjj": ("white", 2, "2026-01-15 10:00:00")},
        "attendance": 22, "badges_extra": [],
    },
    {
        "first": "Ricky", "last": "Nguyen", "login": "ricky.nguyen",
        "email": f"ricky.nguyen@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "beloit",
        "ranks": {"adult-bjj": ("white", 0, "2026-02-01 10:00:00"), "kickboxing": ("level-1", 0, "2026-02-01 10:00:00")},
        "attendance": 8, "badges_extra": [],
    },
    {
        "first": "Olivia", "last": "Chen", "login": "olivia.chen",
        "email": f"olivia.chen@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"kickboxing": ("level-1", 0, "2026-03-10 10:00:00")},
        "attendance": 3, "badges_extra": [],
    },
    # --- Kids (5) ---
    {
        "first": "Aiden", "last": "Torres", "login": "aiden.torres",
        "email": f"aiden.torres@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"kids-bjj": ("yellow", 3, "2025-08-20 10:00:00")},
        "attendance": 95, "badges_extra": ["streak_4"],
    },
    {
        "first": "Mia", "last": "Brooks", "login": "mia.brooks",
        "email": f"mia.brooks@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "beloit",
        "ranks": {"kids-bjj": ("grey", 1, "2025-12-05 10:00:00")},
        "attendance": 40, "badges_extra": [],
    },
    {
        "first": "Jayden", "last": "Williams", "login": "jayden.williams",
        "email": f"jayden.williams@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"kids-bjj": ("orange-white", 2, "2025-10-15 10:00:00")},
        "attendance": 70, "badges_extra": ["streak_4"],
    },
    {
        "first": "Sophie", "last": "Martinez", "login": "sophie.martinez",
        "email": f"sophie.martinez@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"kids-bjj": ("grey-white", 0, "2026-01-20 10:00:00")},
        "attendance": 18, "badges_extra": [],
    },
    {
        "first": "Ethan", "last": "Patel", "login": "ethan.patel",
        "email": f"ethan.patel@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"kids-bjj": ("green", 2, "2025-03-01 10:00:00")},
        "attendance": 260, "badges_extra": ["streak_12"],
    },
    # --- Inactive / Minimal (2) ---
    {
        "first": "Brandon", "last": "Scott", "login": "brandon.scott",
        "email": f"brandon.scott@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "rockford",
        "ranks": {"adult-bjj": ("white", 1, "2025-11-01 10:00:00")},
        "attendance": 12, "badges_extra": [],
    },
    {
        "first": "Nicole", "last": "Davis", "login": "nicole.davis",
        "email": f"nicole.davis@{TEST_EMAIL_DOMAIN}",
        "role": "subscriber", "location": "beloit",
        "ranks": {"adult-bjj": ("white", 0, "2026-03-01 10:00:00")},
        "attendance": 1, "badges_extra": [],
    },
]

# Attendance badge thresholds (from BadgeDefinitions::get_attendance_thresholds)
ATTENDANCE_BADGES = [
    (1, "first_class"),
    (10, "classes_10"),
    (25, "classes_25"),
    (50, "classes_50"),
    (100, "classes_100"),
    (250, "classes_250"),
    (500, "classes_500"),
]

# Starting belts that don't qualify for belt_promotion badge
STARTING_BELTS = {"white", "level-1"}


# ---------------------------------------------------------------------------
# HTTP helpers (same pattern as create_membership_products.py)
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
        print(f"    [DRY RUN] {cmd[:120]}...")
        return {"message": "[DRY RUN]"}, None
    return api_request(
        "POST", f"{API_BASE}/bash_command_bookmarks/batch_execute",
        token=token,
        data={"command": cmd, "siteIds": [site_id]},
    )


def load_env():
    """Load .env file from repo root if present."""
    env_path = os.path.join(os.path.dirname(__file__), "..", ".env")
    if os.path.exists(env_path):
        with open(env_path) as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    key, val = line.split("=", 1)
                    os.environ.setdefault(key.strip(), val.strip())


# ---------------------------------------------------------------------------
# PHP code builders
# ---------------------------------------------------------------------------

def build_member_php(member):
    """Build a PHP eval block that creates a member + all their data."""
    email = member["email"]
    login = member["login"]
    first = member["first"]
    last = member["last"]
    role = member["role"]
    location = member["location"]
    attendance_count = member["attendance"]

    lines = [
        "global $wpdb;",
        f"$existing = get_user_by('email', '{email}');",
        "if ($existing) { $uid = $existing->ID; } else {",
        f"  $uid = wp_insert_user([",
        f"    'user_login'   => '{login}',",
        f"    'user_email'   => '{email}',",
        f"    'first_name'   => '{first}',",
        f"    'last_name'    => '{last}',",
        f"    'display_name' => '{first} {last}',",
        f"    'user_pass'    => '{TEST_PASSWORD}',",
        f"    'role'         => '{role}',",
        "  ]);",
        "  if (is_wp_error($uid)) { echo 'ERR:' . $uid->get_error_message(); return; }",
        "}",
        "",
    ]

    # Rank inserts
    for program, (belt, stripes, promoted_at) in member["ranks"].items():
        lines.append(f"$wpdb->replace($wpdb->prefix . 'gym_ranks', [")
        lines.append(f"  'user_id' => $uid, 'program' => '{program}', 'belt' => '{belt}',")
        lines.append(f"  'stripes' => {stripes}, 'promoted_at' => '{promoted_at}', 'promoted_by' => 1,")
        lines.append("]);")
        lines.append(f"$wpdb->insert($wpdb->prefix . 'gym_rank_history', [")
        lines.append(f"  'user_id' => $uid, 'program' => '{program}',")
        lines.append(f"  'from_belt' => null, 'from_stripes' => null,")
        lines.append(f"  'to_belt' => '{belt}', 'to_stripes' => {stripes},")
        lines.append(f"  'promoted_at' => '{promoted_at}', 'promoted_by' => 1,")
        lines.append(f"  'notes' => 'Seeded by test data script',")
        lines.append("]);")

    # Attendance inserts (batch)
    if attendance_count > 0:
        lines.append("")
        lines.append(f"$wpdb->delete($wpdb->prefix . 'gym_attendance', ['user_id' => $uid, 'method' => 'imported']);")
        lines.append("$vals = [];")
        lines.append(f"for ($i = 0; $i < {attendance_count}; $i++) {{")
        lines.append("  $offset = rand(0, 89);")
        lines.append("  $h = rand(6, 20); $m = rand(0, 59);")
        lines.append("  $ts = strtotime(date('Y-m-d')) - ($offset * 86400) + ($h * 3600) + ($m * 60);")
        lines.append("  $dt = date('Y-m-d H:i:s', $ts);")
        lines.append(f"  $vals[] = $wpdb->prepare('(%d, 0, %s, %s, %s)', $uid, '{location}', $dt, 'imported');")
        lines.append("}")
        lines.append("if ($vals) {")
        lines.append("  $sql = 'INSERT INTO ' . $wpdb->prefix . 'gym_attendance (user_id, class_id, location, checked_in_at, method) VALUES ' . implode(',', $vals);")
        lines.append("  $wpdb->query($sql);")
        lines.append("}")

    # Badge inserts
    lines.append("")
    badges = compute_badges(member)
    for badge_slug in badges:
        lines.append(f"$wpdb->replace($wpdb->prefix . 'gym_achievements', [")
        lines.append(f"  'user_id' => $uid, 'badge_slug' => '{badge_slug}',")
        lines.append(f"  'earned_at' => date('Y-m-d H:i:s'), 'metadata' => null,")
        lines.append("]);")

    lines.append("echo 'OK:' . $uid;")
    return " ".join(lines)


def compute_badges(member):
    """Determine which badges a member qualifies for."""
    badges = set()
    count = member["attendance"]

    # Attendance milestones
    for threshold, slug in ATTENDANCE_BADGES:
        if count >= threshold:
            badges.add(slug)

    # Belt promotion badge
    for program, (belt, stripes, _) in member["ranks"].items():
        if belt not in STARTING_BELTS:
            badges.add("belt_promotion")

    # Multi-program badge
    if len(member["ranks"]) >= 2:
        badges.add("multi_program")

    # Extra badges defined per member
    for slug in member.get("badges_extra", []):
        badges.add(slug)

    return sorted(badges)


# ---------------------------------------------------------------------------
# Seeding steps
# ---------------------------------------------------------------------------

def enable_woopayments_test_mode(token, site_id, dry_run):
    """Step 1: Enable WooPayments test mode."""
    print("\n1. Enabling WooPayments test mode...")
    cmd = "wp option patch update woocommerce_woocommerce_payments_settings test_mode yes"
    resp, err = run_wpcli(token, site_id, cmd, dry_run)
    if err:
        print(f"  Warning: {err[:80]}")
    else:
        print("  Scheduled.")

    cmd = "wp option patch update woocommerce_woocommerce_payments_settings enabled yes"
    resp, err = run_wpcli(token, site_id, cmd, dry_run)


def set_twilio_test_creds(token, site_id, dry_run):
    """Step 2: Set Twilio test credentials (magic test account)."""
    print("\n2. Setting Twilio test credentials...")
    # Twilio magic test credentials — messages are accepted but never sent.
    commands = [
        "wp option update gym_core_twilio_account_sid 'AC00000000000000000000000000000000'",
        "wp option update gym_core_twilio_auth_token '00000000000000000000000000000000'",
        "wp option update gym_core_twilio_phone_number '+15005550006'",
        "wp option update gym_core_sms_enabled 'yes'",
    ]
    for cmd in commands:
        resp, err = run_wpcli(token, site_id, cmd, dry_run)
        if err:
            print(f"  Warning: {err[:80]}")
    print("  Scheduled.")


def enable_gym_core_settings(token, site_id, dry_run):
    """Step 3: Enable all gym-core feature toggles."""
    print("\n3. Enabling gym-core settings...")
    commands = [
        "wp option update gym_core_gamification_enabled 'yes'",
        "wp option update gym_core_api_enabled 'yes'",
    ]
    for cmd in commands:
        resp, err = run_wpcli(token, site_id, cmd, dry_run)
        if err:
            print(f"  Warning: {err[:80]}")
    print("  Scheduled.")


def seed_members(token, site_id, dry_run):
    """Step 4: Create members with ranks, attendance, and badges."""
    print(f"\n4. Seeding {len(MEMBERS)} test members...")
    for i, member in enumerate(MEMBERS):
        name = f"{member['first']} {member['last']}"
        badges = compute_badges(member)
        programs = ", ".join(member["ranks"].keys())
        print(f"  [{i+1:2d}/{len(MEMBERS)}] {name:<22s} ({member['role']}) — {programs} — {member['attendance']} classes, {len(badges)} badges")

        php = build_member_php(member)
        cmd = f"wp eval '{php}'"
        resp, err = run_wpcli(token, site_id, cmd, dry_run)
        if err:
            print(f"         Warning: {err[:80]}")

        # Throttle to avoid overwhelming the Pressable job queue.
        if not dry_run:
            time.sleep(1.5)

    print(f"\n  All {len(MEMBERS)} members scheduled.")
    print(f"  Test credentials: any email @{TEST_EMAIL_DOMAIN} / {TEST_PASSWORD}")


def run_prerequisite_scripts(site_id, dry_run, skip_products, skip_pages):
    """Step 5: Run existing product/page creation scripts if needed."""
    scripts_dir = os.path.dirname(__file__)

    if not skip_products:
        print("\n5a. Running create_membership_products.py...")
        script = os.path.join(scripts_dir, "create_membership_products.py")
        if os.path.exists(script):
            args = [sys.executable, script, "--site-id", str(site_id)]
            if dry_run:
                args.append("--dry-run")
            os.system(" ".join(args))
        else:
            print(f"  Script not found: {script}")

    if not skip_pages:
        print("\n5b. Running create_site_pages.py...")
        script = os.path.join(scripts_dir, "create_site_pages.py")
        if os.path.exists(script):
            args = [sys.executable, script, "--site-id", str(site_id)]
            if dry_run:
                args.append("--dry-run")
            os.system(" ".join(args))
        else:
            print(f"  Script not found: {script}")


def clean_test_data(token, site_id, dry_run):
    """Remove all previously seeded test data."""
    print("\nCleaning seeded test data...")

    # Delete test users and their data
    php = f"""
global $wpdb;
$users = get_users(['search' => '*@{TEST_EMAIL_DOMAIN}', 'search_columns' => ['user_email']]);
$count = 0;
foreach ($users as $u) {{
    $wpdb->delete($wpdb->prefix . 'gym_ranks', ['user_id' => $u->ID]);
    $wpdb->delete($wpdb->prefix . 'gym_rank_history', ['user_id' => $u->ID]);
    $wpdb->delete($wpdb->prefix . 'gym_attendance', ['user_id' => $u->ID]);
    $wpdb->delete($wpdb->prefix . 'gym_achievements', ['user_id' => $u->ID]);
    wp_delete_user($u->ID);
    $count++;
}}
echo "Deleted $count test users and their data.";
""".strip().replace("\n", " ")

    cmd = f"wp eval '{php}'"
    resp, err = run_wpcli(token, site_id, cmd, dry_run)
    if err:
        print(f"  Warning: {err[:80]}")
    else:
        print("  Cleanup scheduled.")


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Seed test data on the Pressable staging site."
    )
    parser.add_argument("--dry-run", action="store_true", help="Print commands without executing")
    parser.add_argument("--site-id", type=int, default=DEFAULT_SITE_ID, help="Pressable site ID")
    parser.add_argument("--skip-products", action="store_true", help="Skip running create_membership_products.py")
    parser.add_argument("--skip-pages", action="store_true", help="Skip running create_site_pages.py")
    parser.add_argument("--clean", action="store_true", help="Remove seeded test data before re-seeding")
    args = parser.parse_args()

    load_env()

    client_id = os.environ.get("PRESSABLE_CLIENT_ID", "")
    client_secret = os.environ.get("PRESSABLE_CLIENT_SECRET", "")
    if not client_id or not client_secret:
        print("Error: Set PRESSABLE_CLIENT_ID and PRESSABLE_CLIENT_SECRET in .env or environment.", file=sys.stderr)
        sys.exit(1)

    print("=" * 60)
    print("  Gym Core — Test Data Seeder")
    print("=" * 60)
    print(f"  Site ID:  {args.site_id}")
    print(f"  Dry run:  {args.dry_run}")
    if args.clean:
        print(f"  Mode:     CLEAN + RESEED")

    token = authenticate(client_id, client_secret)
    print("  Auth:     OK")

    if args.clean:
        clean_test_data(token, args.site_id, args.dry_run)
        if not args.dry_run:
            print("  Waiting 5s for cleanup to complete...")
            time.sleep(5)

    enable_woopayments_test_mode(token, args.site_id, args.dry_run)
    set_twilio_test_creds(token, args.site_id, args.dry_run)
    enable_gym_core_settings(token, args.site_id, args.dry_run)
    seed_members(token, args.site_id, args.dry_run)
    run_prerequisite_scripts(args.site_id, args.dry_run, args.skip_products, args.skip_pages)

    print("\n" + "=" * 60)
    print("  SEEDING COMPLETE")
    print("=" * 60)
    print(f"""
  All commands are async — data will appear within ~60 seconds.

  Test accounts:
    Login:    any email @{TEST_EMAIL_DOMAIN}
    Password: {TEST_PASSWORD}

  Payment gateway:
    WooPayments is in TEST MODE — use Stripe test cards:
    4242 4242 4242 4242  (success)
    4000 0000 0000 0002  (decline)

  Twilio SMS:
    Using magic test credentials — messages accepted but never sent.

  To verify:
    python3 scripts/checkout_smoke_test.py --site-id {args.site_id}

  To clean up:
    python3 scripts/seed_test_data.py --clean --skip-products --skip-pages
""")


if __name__ == "__main__":
    main()
