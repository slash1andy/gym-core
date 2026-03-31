#!/usr/bin/env python3
"""
clean_ghl_contacts.py — Clean and normalize a GoHighLevel contact CSV export.

Steps:
  1. Normalize phone numbers to E.164 (US default +1)
  2. Deduplicate by email (primary), then phone (secondary)
  3. Remove junk rows (no email AND no phone)
  4. Categorize contacts: member, lead, lapsed, prospect, trial
  5. Output cleaned CSV + summary report

Usage:
    python3 scripts/clean_ghl_contacts.py contacts.csv
    python3 scripts/clean_ghl_contacts.py contacts.csv --output cleaned.csv
    python3 scripts/clean_ghl_contacts.py contacts.csv --dry-run
"""

import argparse
import csv
import os
import re
import sys
from collections import Counter

# ── Category keywords (matched against tags column) ─────────────────
CATEGORY_KEYWORDS = {
    "member":   ["member", "active member", "current member", "enrolled"],
    "trial":    ["trial", "free trial", "intro", "introductory"],
    "lapsed":   ["lapsed", "former", "inactive", "cancelled", "canceled", "expired"],
    "prospect": ["prospect", "inquiry", "interested", "walk-in", "walkin"],
    "lead":     ["lead", "contact", "opt-in", "optin", "facebook", "web form"],
}

DEFAULT_CATEGORY = "lead"


def normalize_phone(raw):
    """Normalize a phone number to E.164 format (assumes US +1 if no country code)."""
    if not raw:
        return ""
    # Strip everything except digits and leading +
    digits = re.sub(r"[^\d+]", "", raw.strip())
    if not digits:
        return ""

    # Remove leading + for digit processing
    if digits.startswith("+"):
        digits = digits[1:]

    # Remove leading 1 if 11 digits (US country code)
    if len(digits) == 11 and digits.startswith("1"):
        digits = digits[1:]

    # Must be 10 digits for a valid US number
    if len(digits) == 10:
        return f"+1{digits}"

    # If it's already an international number (>10 digits), prefix with +
    if len(digits) > 10:
        return f"+{digits}"

    # Too short — return empty (invalid)
    return ""


def normalize_email(raw):
    """Lowercase and strip an email address."""
    if not raw:
        return ""
    email = raw.strip().lower()
    # Basic sanity check
    if "@" in email and "." in email:
        return email
    return ""


def categorize(tags_str):
    """Determine contact category from a tags string."""
    if not tags_str:
        return DEFAULT_CATEGORY

    tags_lower = tags_str.lower()
    for category, keywords in CATEGORY_KEYWORDS.items():
        for kw in keywords:
            if kw in tags_lower:
                return category
    return DEFAULT_CATEGORY


def find_column(headers, candidates):
    """Find the first matching column name (case-insensitive)."""
    headers_lower = [h.lower().strip() for h in headers]
    for c in candidates:
        if c.lower() in headers_lower:
            return headers_lower.index(c.lower())
    return None


def main():
    parser = argparse.ArgumentParser(description="Clean and normalize GHL contact CSV export")
    parser.add_argument("input", help="Path to input CSV from GoHighLevel")
    parser.add_argument("--output", "-o", help="Path for cleaned CSV output (default: <input>_cleaned.csv)")
    parser.add_argument("--dry-run", action="store_true", help="Show summary report without writing output")
    args = parser.parse_args()

    if not os.path.isfile(args.input):
        print(f"ERROR: File not found: {args.input}", file=sys.stderr)
        sys.exit(1)

    if not args.output:
        base, ext = os.path.splitext(args.input)
        args.output = f"{base}_cleaned{ext or '.csv'}"

    # ── Read input CSV ──────────────────────────────────────────────
    with open(args.input, newline="", encoding="utf-8-sig") as f:
        reader = csv.reader(f)
        headers = next(reader)
        rows = list(reader)

    total_input = len(rows)
    print(f"Read {total_input} rows from {args.input}")
    print(f"Columns: {headers}\n")

    # ── Identify columns ────────────────────────────────────────────
    email_col = find_column(headers, [
        "email", "email address", "e-mail", "contact email",
    ])
    phone_col = find_column(headers, [
        "phone", "phone number", "mobile", "cell", "telephone",
        "contact phone",
    ])
    first_name_col = find_column(headers, [
        "first name", "firstname", "first_name", "name",
    ])
    last_name_col = find_column(headers, [
        "last name", "lastname", "last_name", "surname",
    ])
    tags_col = find_column(headers, [
        "tags", "tag", "labels", "label", "contact tags",
    ])

    if email_col is None and phone_col is None:
        print("ERROR: Cannot find email or phone column.", file=sys.stderr)
        sys.exit(1)

    print(f"  email col:      {headers[email_col] if email_col is not None else '(not found)'}")
    print(f"  phone col:      {headers[phone_col] if phone_col is not None else '(not found)'}")
    print(f"  first name col: {headers[first_name_col] if first_name_col is not None else '(not found)'}")
    print(f"  last name col:  {headers[last_name_col] if last_name_col is not None else '(not found)'}")
    print(f"  tags col:       {headers[tags_col] if tags_col is not None else '(not found)'}")
    print()

    # ── Process rows ────────────────────────────────────────────────
    seen_emails = set()
    seen_phones = set()
    cleaned = []
    stats = Counter()

    for row in rows:
        # Pad short rows
        while len(row) < len(headers):
            row.append("")

        email = normalize_email(row[email_col]) if email_col is not None else ""
        phone = normalize_phone(row[phone_col]) if phone_col is not None else ""
        tags = row[tags_col].strip() if tags_col is not None else ""

        # Remove junk: no email AND no phone
        if not email and not phone:
            stats["removed_junk"] += 1
            continue

        # Deduplicate by email (primary)
        if email:
            if email in seen_emails:
                stats["dedup_email"] += 1
                continue
            seen_emails.add(email)
        else:
            # No email — deduplicate by phone (secondary)
            if phone and phone in seen_phones:
                stats["dedup_phone"] += 1
                continue

        if phone:
            seen_phones.add(phone)

        # Categorize
        category = categorize(tags)
        stats[f"cat_{category}"] += 1

        # Build cleaned row
        first_name = row[first_name_col].strip() if first_name_col is not None else ""
        last_name = row[last_name_col].strip() if last_name_col is not None else ""

        cleaned.append({
            "first_name": first_name,
            "last_name": last_name,
            "email": email,
            "phone": phone,
            "category": category,
            "tags": tags,
        })

    # ── Summary report ──────────────────────────────────────────────
    print("=== Cleaning Summary ===")
    print(f"  Input rows:           {total_input}")
    print(f"  Removed (no contact): {stats.get('removed_junk', 0)}")
    print(f"  Deduped (email):      {stats.get('dedup_email', 0)}")
    print(f"  Deduped (phone):      {stats.get('dedup_phone', 0)}")
    print(f"  Output rows:          {len(cleaned)}")
    print()
    print("  Category breakdown:")
    for cat in ["member", "trial", "lead", "prospect", "lapsed"]:
        count = stats.get(f"cat_{cat}", 0)
        if count > 0:
            print(f"    {cat:12s} {count:>5}")
    print()

    # ── Write output ────────────────────────────────────────────────
    if args.dry_run:
        print("[DRY RUN] No output file written.")
        return

    output_headers = ["first_name", "last_name", "email", "phone", "category", "tags"]
    with open(args.output, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=output_headers)
        writer.writeheader()
        writer.writerows(cleaned)

    print(f"Cleaned CSV written to: {args.output}")


if __name__ == "__main__":
    main()
