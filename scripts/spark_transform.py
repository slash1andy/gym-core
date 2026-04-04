#!/usr/bin/env python3
"""
Transform Spark Membership CSV exports into gym-core import format.

Reads:
  - Contact_Export.csv           → members with contact info
  - Report_Attendance_Count.csv  → current rank + attendance counts
  - Attendance_Rosters_Contacts.csv → class roster enrollments (optional)

Outputs:
  - spark_users.csv       → for `wp gym import users`
  - spark_belt_ranks.csv  → for `wp gym import belt-ranks`
  - spark_transform_report.txt → validation and mapping summary

Usage:
    python3 scripts/spark_transform.py \
        --contacts /path/to/Contact_Export.csv \
        --ranks /path/to/Report_Attendance_Count.csv \
        [--rosters /path/to/Attendance_Rosters_Contacts.csv] \
        [--output-dir ./spark-import]

Then import on the WordPress site:
    wp gym import users --file=spark_users.csv --dry-run
    wp gym import users --file=spark_users.csv --skip-existing
    wp gym import belt-ranks --file=spark_belt_ranks.csv --dry-run
    wp gym import belt-ranks --file=spark_belt_ranks.csv --skip-existing
"""

import argparse
import csv
import os
import re
import sys
from collections import defaultdict
from datetime import datetime

# ---------------------------------------------------------------------------
# Spark rank string → gym-core (program, belt) mapping
# ---------------------------------------------------------------------------

# Spark uses combined strings like "Adult BJJ White Belt", "Kids BJJ Grey Black",
# "Striking Level 1". We need to split into program slug + belt slug.

ADULT_BJJ_BELTS = {
    'white belt': 'white',
    'white': 'white',
    'blue': 'blue',
    'blue belt': 'blue',
    'purple': 'purple',
    'purple belt': 'purple',
    'brown': 'brown',
    'brown belt': 'brown',
    'black': 'black',
    'black belt': 'black',
    'foundations eval': 'white',  # Foundations students start at white
    'foundations': 'white',
}

KIDS_BJJ_BELTS = {
    'white belt': 'white',
    'white': 'white',
    'white grey': 'grey-white',
    'grey white': 'grey-white',
    'grey': 'grey',
    'grey belt': 'grey',
    'grey black': 'grey-black',
    'yellow white': 'yellow-white',
    'yellow': 'yellow',
    'yellow black': 'yellow-black',
    'orange white': 'orange-white',
    'orange': 'orange',
    'orange black': 'orange-black',
    'green white': 'green-white',
    'green': 'green',
    'green black': 'green-black',
}

STRIKING_BELTS = {
    'level 1': 'level-1',
    'level 2': 'level-2',
}


def parse_spark_rank(rank_string):
    """Parse a Spark rank string into (program_slug, belt_slug, is_foundations).

    Returns (None, None, False) if the rank cannot be parsed.
    """
    if not rank_string or not rank_string.strip():
        return None, None, False

    rank = rank_string.strip()
    rank_lower = rank.lower()
    is_foundations = 'foundations' in rank_lower

    # Adult BJJ
    if rank_lower.startswith('adult bjj '):
        belt_part = rank_lower.replace('adult bjj ', '').strip()
        belt_slug = ADULT_BJJ_BELTS.get(belt_part)
        if belt_slug:
            return 'adult-bjj', belt_slug, is_foundations
        # Try partial match
        for key, slug in ADULT_BJJ_BELTS.items():
            if key in belt_part:
                return 'adult-bjj', slug, is_foundations

    # Kids BJJ
    if rank_lower.startswith('kids bjj '):
        belt_part = rank_lower.replace('kids bjj ', '').strip()
        belt_slug = KIDS_BJJ_BELTS.get(belt_part)
        if belt_slug:
            return 'kids-bjj', belt_slug, is_foundations
        for key, slug in KIDS_BJJ_BELTS.items():
            if key in belt_part:
                return 'kids-bjj', slug, is_foundations

    # Striking / Kickboxing
    if rank_lower.startswith('striking '):
        belt_part = rank_lower.replace('striking ', '').strip()
        belt_slug = STRIKING_BELTS.get(belt_part)
        if belt_slug:
            return 'kickboxing', belt_slug, is_foundations

    return None, None, False


def parse_date(date_str):
    """Parse MM/DD/YYYY date to YYYY-MM-DD HH:MM:SS."""
    if not date_str or not date_str.strip():
        return ''
    try:
        dt = datetime.strptime(date_str.strip(), '%m/%d/%Y')
        return dt.strftime('%Y-%m-%d 00:00:00')
    except ValueError:
        return ''


def clean_phone(phone_str):
    """Normalize phone number to digits only."""
    if not phone_str:
        return ''
    digits = re.sub(r'\D', '', phone_str.strip())
    if len(digits) == 10:
        return f'+1{digits}'
    if len(digits) == 11 and digits.startswith('1'):
        return f'+{digits}'
    return digits


def clean_spark_id(spark_id):
    """Remove commas from Spark numeric IDs like '5,782,495'."""
    if not spark_id:
        return ''
    return spark_id.replace(',', '').strip()


def read_csv_skip_header_rows(filepath, skip_until_header=None):
    """Read a CSV, optionally skipping preamble rows until the header is found."""
    rows = []
    with open(filepath, 'r', encoding='utf-8-sig') as f:
        reader = csv.reader(f)
        header = None
        for row in reader:
            if header is None:
                if skip_until_header:
                    # Look for the row containing the expected header field
                    if any(skip_until_header.lower() in cell.lower() for cell in row):
                        header = [c.strip() for c in row]
                        continue
                    continue
                else:
                    header = [c.strip() for c in row]
                    continue
            if len(row) == len(header):
                rows.append(dict(zip(header, [c.strip() for c in row])))
    return header, rows


def transform_contacts(contacts_path):
    """Transform Contact_Export.csv into users import format."""
    _, rows = read_csv_skip_header_rows(contacts_path)

    users = []
    warnings = []

    for i, row in enumerate(rows):
        email = row.get('Email', '').strip().lower()
        first = row.get('First Name', '').strip()
        last = row.get('Last Name', '').strip()

        if not first and not last:
            warnings.append(f"Row {i+2}: no name, skipping")
            continue

        # Use mobile as primary phone, fall back to Phone
        phone = row.get('Mobile', '').strip() or row.get('Phone', '').strip()

        spark_id = clean_spark_id(row.get('ID', ''))
        alt_id = row.get('Alt ID', '').strip()
        entered = parse_date(row.get('Entered', ''))
        birthday = parse_date(row.get('Birthday', ''))
        gender = row.get('Gender', '').strip()

        # Address
        addr1 = row.get('Address 1', '').strip()
        city = row.get('City', '').strip()
        state = row.get('State', '').strip()
        postal = row.get('Postal Code', '').strip()

        # Emergency contacts
        ec1_name = row.get('EmergencyContactName1', '').strip()
        ec1_phone = row.get('EmergencyContactPhone1', '').strip()

        # Parent names (for kids)
        mom = row.get('Mom Name', '').strip()
        dad = row.get('Dad Name', '').strip()

        # Last activity
        last_seen_days = row.get('Last Seen (Days Ago)', '').strip()
        became_former = row.get('When Became Former', '').strip()
        contact_type = row.get('Contact Type', '').strip()

        user = {
            'email': email,
            'first_name': first,
            'last_name': last,
            'display_name': f'{first} {last}'.strip(),
            'role': 'customer',
            # Extra columns become user meta via ImportCommand
            'phone': clean_phone(phone),
            'spark_member_id': spark_id,
            'spark_alt_id': alt_id,
            'billing_address_1': addr1,
            'billing_city': city,
            'billing_state': state,
            'billing_postcode': postal,
            'billing_country': 'US',
            'birthday': birthday,
            'gender': gender,
            'emergency_contact_name': ec1_name,
            'emergency_contact_phone': clean_phone(ec1_phone),
            'parent_mom': mom,
            'parent_dad': dad,
            'spark_entered': entered,
            'spark_last_seen_days': last_seen_days,
            'spark_became_former': became_former,
            'spark_contact_type': contact_type,
        }

        users.append(user)

        if not email:
            warnings.append(f"Row {i+2}: {first} {last} has no email — will be created with placeholder")
            # Generate a placeholder email so import doesn't skip
            slug = re.sub(r'[^a-z0-9]', '', f'{first}{last}'.lower())
            user['email'] = f'{slug}.noemail@haanpaamartialarts.com'

    return users, warnings


def transform_ranks(ranks_path):
    """Transform Report_Attendance_Count.csv into belt-ranks import format."""
    _, rows = read_csv_skip_header_rows(ranks_path)

    ranks = []
    warnings = []

    for i, row in enumerate(rows):
        first = row.get('First Name', '').strip()
        last = row.get('Last Name', '').strip()
        rank_str = row.get('Current Rank', '').strip()
        date_earned = row.get('Date Earned', '').strip()
        attendance_count = row.get('Attendance Count', '').strip()

        program, belt, is_foundations = parse_spark_rank(rank_str)

        if not program or not belt:
            warnings.append(f"Row {i+2}: cannot parse rank '{rank_str}' for {first} {last}")
            continue

        ranks.append({
            'first_name': first,
            'last_name': last,
            'program': program,
            'belt': belt,
            'stripes': '0',
            'promoted_at': parse_date(date_earned),
            'notes': f'Imported from Spark. Original rank: {rank_str}. Attendance count: {attendance_count}.',
            'is_foundations': is_foundations,
            'spark_attendance_count': attendance_count,
        })

    return ranks, warnings


def match_ranks_to_users(users, ranks):
    """Match rank records to user records by name, returning belt-ranks CSV rows.

    Since the rank CSV doesn't have email or Spark ID, we match by normalized
    first+last name. Returns (matched_ranks, unmatched_warnings).
    """
    # Build name → email lookup from users
    name_to_email = {}
    for u in users:
        key = f"{u['first_name'].lower().strip()} {u['last_name'].lower().strip()}"
        name_to_email[key] = u['email']

    matched = []
    warnings = []

    for r in ranks:
        key = f"{r['first_name'].lower().strip()} {r['last_name'].lower().strip()}"
        email = name_to_email.get(key)

        if not email:
            warnings.append(f"No user match for rank: {r['first_name']} {r['last_name']} ({r['program']}/{r['belt']})")
            continue

        matched.append({
            'email': email,
            'program': r['program'],
            'belt': r['belt'],
            'stripes': r['stripes'],
            'promoted_at': r['promoted_at'],
            'notes': r['notes'],
        })

    return matched, warnings


def write_csv(filepath, rows, fieldnames):
    """Write rows to a CSV file."""
    with open(filepath, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames, extrasaction='ignore')
        writer.writeheader()
        writer.writerows(rows)
    print(f"  Wrote {len(rows)} rows → {filepath}")


def main():
    parser = argparse.ArgumentParser(description='Transform Spark CSV exports for gym-core import')
    parser.add_argument('--contacts', required=True, help='Path to Contact_Export.csv')
    parser.add_argument('--ranks', required=True, help='Path to Report_Attendance_Count.csv')
    parser.add_argument('--rosters', help='Path to Attendance_Rosters_Contacts.csv (optional)')
    parser.add_argument('--output-dir', default='./spark-import', help='Output directory')
    args = parser.parse_args()

    os.makedirs(args.output_dir, exist_ok=True)

    print("=" * 60)
    print("Spark → gym-core Transform")
    print("=" * 60)

    # --- Step 1: Transform contacts → users ---
    print("\n[1/3] Transforming contacts...")
    users, user_warnings = transform_contacts(args.contacts)
    print(f"  {len(users)} members found, {len(user_warnings)} warnings")

    user_fields = [
        'email', 'first_name', 'last_name', 'display_name', 'role',
        'phone', 'spark_member_id', 'spark_alt_id',
        'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country',
        'birthday', 'gender',
        'emergency_contact_name', 'emergency_contact_phone',
        'parent_mom', 'parent_dad',
        'spark_entered', 'spark_last_seen_days', 'spark_became_former', 'spark_contact_type',
    ]
    write_csv(os.path.join(args.output_dir, 'spark_users.csv'), users, user_fields)

    # --- Step 2: Transform ranks ---
    print("\n[2/3] Transforming ranks...")
    ranks, rank_warnings = transform_ranks(args.ranks)
    print(f"  {len(ranks)} rank records found, {len(rank_warnings)} warnings")

    # Match ranks to users by name
    matched_ranks, match_warnings = match_ranks_to_users(users, ranks)
    print(f"  {len(matched_ranks)} matched to users, {len(match_warnings)} unmatched")

    # The belt-ranks importer needs user_id, but we don't have WP IDs yet.
    # Write with email as the key — the import workflow is:
    #   1. Import users first (creates WP users)
    #   2. Run a second pass to resolve email→user_id
    #   3. Then import belt-ranks
    # So we output a "pre-import" CSV with email, and a helper script to resolve IDs.
    rank_fields = ['email', 'program', 'belt', 'stripes', 'promoted_at', 'notes']
    write_csv(os.path.join(args.output_dir, 'spark_belt_ranks_by_email.csv'), matched_ranks, rank_fields)

    # --- Step 3: Summary report ---
    print("\n[3/3] Generating report...")
    report_path = os.path.join(args.output_dir, 'spark_transform_report.txt')

    # Stats
    programs = defaultdict(lambda: defaultdict(int))
    foundations_count = 0
    for r in ranks:
        programs[r['program']][r['belt']] += 1
        if r.get('is_foundations'):
            foundations_count += 1

    emails_present = sum(1 for u in users if '@haanpaamartialarts.com' not in u['email'])
    emails_missing = len(users) - emails_present

    with open(report_path, 'w') as f:
        f.write("Spark → gym-core Transform Report\n")
        f.write(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write("=" * 60 + "\n\n")

        f.write("MEMBER SUMMARY\n")
        f.write(f"  Total members: {len(users)}\n")
        f.write(f"  With email: {emails_present}\n")
        f.write(f"  Missing email (placeholder assigned): {emails_missing}\n\n")

        f.write("RANK SUMMARY\n")
        for prog in sorted(programs.keys()):
            f.write(f"\n  {prog}:\n")
            for belt in sorted(programs[prog].keys()):
                f.write(f"    {belt}: {programs[prog][belt]}\n")
        f.write(f"\n  Foundations students: {foundations_count}\n")
        f.write(f"  Total rank records: {len(ranks)}\n")
        f.write(f"  Matched to users: {len(matched_ranks)}\n")
        f.write(f"  Unmatched: {len(match_warnings)}\n\n")

        if user_warnings:
            f.write("USER WARNINGS\n")
            for w in user_warnings:
                f.write(f"  {w}\n")
            f.write("\n")

        if rank_warnings:
            f.write("RANK PARSE WARNINGS\n")
            for w in rank_warnings:
                f.write(f"  {w}\n")
            f.write("\n")

        if match_warnings:
            f.write("RANK MATCH WARNINGS (no user found by name)\n")
            for w in match_warnings:
                f.write(f"  {w}\n")
            f.write("\n")

        f.write("IMPORT WORKFLOW\n")
        f.write("  1. Review this report for warnings\n")
        f.write("  2. Dry-run user import:\n")
        f.write("     wp gym import users --file=spark_users.csv --dry-run\n")
        f.write("  3. Import users:\n")
        f.write("     wp gym import users --file=spark_users.csv --skip-existing\n")
        f.write("  4. Resolve email→user_id:\n")
        f.write("     wp eval-file scripts/spark_resolve_ids.php spark_belt_ranks_by_email.csv spark_belt_ranks.csv\n")
        f.write("  5. Dry-run rank import:\n")
        f.write("     wp gym import belt-ranks --file=spark_belt_ranks.csv --dry-run\n")
        f.write("  6. Import ranks:\n")
        f.write("     wp gym import belt-ranks --file=spark_belt_ranks.csv --skip-existing\n")
        f.write("  7. Spot-check 20 members in wp-admin\n")

    print(f"  Report → {report_path}")

    print("\n" + "=" * 60)
    print("DONE. Next steps:")
    print(f"  1. Review: {report_path}")
    print(f"  2. Import users:  wp gym import users --file={os.path.join(args.output_dir, 'spark_users.csv')} --dry-run")
    print(f"  3. Import ranks after resolving user IDs (see report)")
    print("=" * 60)


if __name__ == '__main__':
    main()
