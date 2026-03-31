# CoWork Migration Playbooks

> Instructions for Claude Desktop / Claude CoWork to automate the "manual" data extraction and documentation tasks in the migration. Each playbook is a self-contained prompt that can be handed to a Claude session with browser/computer use capabilities.

**Last updated:** 2026-03-29

---

## How to Use These Playbooks

Each playbook below is designed to be given to **Claude Desktop with computer use** or **Claude CoWork** — an AI agent that can see and interact with a screen. The agent navigates web UIs, captures data, and writes structured output files.

**Setup requirements:**
- Claude Desktop or CoWork session with computer use enabled
- Browser logged into the relevant platform (Spark, GHL, Wix, USAePay)
- Write access to a local directory for output files (e.g., `~/hma-migration/exports/`)

**General instructions to prepend to every playbook:**
```
You are helping migrate a martial arts gym (Haanpaa Martial Arts, 2 locations:
Rockford IL and Beloit WI) from legacy platforms to WordPress/WooCommerce.

Output all captured data as structured files (CSV, JSON, or Markdown) to the
directory: ~/hma-migration/exports/

Be thorough. Capture EVERYTHING. If a page has pagination, go through every page.
If data spans multiple tabs or sections, visit each one. Never skip or summarize —
we need the raw data.

When taking screenshots, save them to ~/hma-migration/screenshots/ with descriptive
filenames.

If you hit a login wall, permission error, or unexpected UI state, stop and describe
what you see so the human can intervene.

IMPORTANT — UPDATE THIS PLAYBOOK FILE WHEN YOU FINISH:
After completing your extraction, you MUST update this playbook file
(docs/planning/cowork-migration-playbooks.md) in the monorepo at:
~/Local Sites/hma-core/  (monorepo root — gym-core plugin is at plugins/gym-core/)

For the playbook you just ran, add a "## Completed Run" section at the end of
that playbook's block with:
1. The date you ran it
2. The EXACT navigation path you used (click-by-click) — the UI may differ
   from what was guessed in the original instructions. Correct the instructions
   above if they were wrong.
3. Any fields, sections, or data you found that were NOT listed in the original
   playbook — add them to the capture list
4. Any fields listed in the original playbook that did NOT exist in the actual UI
   — note these so future agents don't waste time looking for them
5. The exact structure of the output file(s) you produced — column names, data
   types, sample row. This becomes the canonical schema for downstream import
   scripts.
6. Record count (how many items were captured)
7. Any issues, surprises, or blockers encountered

This feedback loop means each playbook gets more accurate after every run.
Later agents and import scripts can rely on the documented schema rather than
guessing at the export format.
```

---

## Playbook 1: Spark Membership Plan Documentation

**When:** Before M1.4 (membership product configuration)
**Why:** Spark has no export for membership plan structure. An agent must navigate the admin UI and capture every detail.
**Estimated time:** 15-30 minutes

```
TASK: Document every membership plan in Spark Membership.

NAVIGATE TO: Spark admin dashboard > General Settings > Memberships
(or Settings > Memberships — the exact path may vary)

FOR EACH MEMBERSHIP PLAN, capture:
- Plan name (exact text)
- Monthly price
- Billing frequency (monthly, quarterly, annual, etc.)
- Signup fee (one-time, if any)
- Trial period (days, if any)
- Contract length (months, if any)
- Which location(s) the plan applies to (Rockford, Beloit, or both)
- What programs/classes are included
- Any restrictions or notes visible on the plan
- Whether the plan is currently active or archived

Also check for:
- Drop-in / single class pricing
- Family plan pricing or discounts
- Student/military discounts
- Any promotional or legacy plans still visible

OUTPUT: Save as ~/hma-migration/exports/spark-membership-plans.csv with columns:
plan_name, price, billing_frequency, signup_fee, trial_days, contract_months,
locations, programs_included, status, notes

Also take a screenshot of each plan's detail screen and save to:
~/hma-migration/screenshots/spark-plan-{plan-name-slug}.png

IMPORTANT: If there are separate plan lists per location, capture BOTH locations.
Navigate between locations if the UI has a location switcher.
```

---

## Playbook 2: Spark POS Product Catalog

**When:** Before M1.4
**Why:** No export available for POS products/inventory in Spark.
**Estimated time:** 10-20 minutes

```
TASK: Document every product in Spark's Point of Sale system.

NAVIGATE TO: Spark admin > Point of Sale Settings > Products
(or POS > Product Management — exact path may vary)

FOR EACH PRODUCT, capture:
- Product name
- SKU (if visible)
- Price
- Category (gear, apparel, supplements, etc.)
- Current stock quantity (if inventory tracking is on)
- Description (if any)
- Whether it's active or discontinued
- Any variants (sizes, colors)
- Product image URL (if visible)

OUTPUT: Save as ~/hma-migration/exports/spark-pos-products.csv with columns:
product_name, sku, price, category, stock_qty, description, status, variants, image_url

Take a screenshot of the full product list and any product detail pages.
Save to ~/hma-migration/screenshots/spark-pos-*.png
```

---

## Playbook 3: Spark Class Schedule Structure

**When:** Before M3.3
**Why:** Class schedule must be documented to recreate as `gym_class` CPT entries.
**Estimated time:** 15-25 minutes

```
TASK: Document the complete class schedule from Spark Membership for BOTH locations.

NAVIGATE TO: Spark admin > Schedule (or Class Schedule section)

FOR EACH CLASS, capture:
- Class name (e.g., "Adult BJJ", "Kids Kickboxing")
- Program / martial arts style (BJJ, Kickboxing, MMA, etc.)
- Day(s) of the week
- Start time
- End time / duration
- Location (Rockford or Beloit)
- Instructor name
- Capacity limit (max students, if shown)
- Is it recurring? (weekly, specific dates, etc.)
- Any notes (e.g., "no-gi", "sparring", "beginners only")

Check BOTH locations — switch the location filter/selector if the UI has one.
Also check for:
- Private lesson slots
- Open mat / open gym times
- Special events or workshops
- Cancelled or suspended classes

OUTPUT: Save as ~/hma-migration/exports/spark-class-schedule.csv with columns:
class_name, program, day_of_week, start_time, end_time, location, instructor,
capacity, recurrence, notes, status

Take screenshots of the full weekly schedule view for each location:
~/hma-migration/screenshots/spark-schedule-rockford.png
~/hma-migration/screenshots/spark-schedule-beloit.png
```

---

## Playbook 4: Spark Belt Rank Definitions

**When:** Before M4.1
**Why:** Exact belt names (especially Kids BJJ with 13 belt levels) and promotion thresholds must be captured.
**Estimated time:** 10-20 minutes

```
TASK: Document the complete belt/rank system from Spark for all programs.

NAVIGATE TO: Spark admin > Rank Management (or Settings > Ranks / Belt Levels)

FOR EACH PROGRAM (Adult BJJ, Kids BJJ, Kickboxing, and any others):

1. List every rank level IN ORDER from lowest to highest:
   - Rank/belt name (exact text and color)
   - Number of stripes at each belt level
   - Position/order number

2. For each rank, capture promotion requirements (if configured in Spark):
   - Minimum attendance count since last promotion
   - Minimum time at current rank (days/months)
   - Whether coach recommendation is required
   - Any other requirements

3. Capture any rank-related settings:
   - How ranks are displayed to members
   - Rank color codes (if used)
   - Whether rank history is shown to members

CRITICAL: Kids BJJ has 13 belt levels. Get the EXACT names and order from the
system. If the names aren't clear from the UI, flag this — Andrew will confirm
with Darby.

OUTPUT: Save as ~/hma-migration/exports/spark-belt-ranks.json with structure:
{
  "programs": [
    {
      "name": "Adult BJJ",
      "ranks": [
        {"order": 1, "name": "White Belt", "color": "white", "stripes": 4,
         "min_attendance": null, "min_time_days": null}
      ]
    }
  ]
}

Take screenshots of the rank configuration screens:
~/hma-migration/screenshots/spark-ranks-*.png
```

### Completed Run — 2026-03-30

**Method:** Manual input from gym owner (Andrew Wikel), not UI extraction.
Belt ranks were provided directly rather than scraped from Spark, as Andrew knows
the exact rank structure. IBJJF promotion rules enriched from web search.

**Schema produced (`spark-belt-ranks.json`):**
| Field | Type | Example |
|-------|------|---------|
| programs[].name | string | "Adult BJJ" |
| programs[].stripes_per_belt | int/null | 4 |
| programs[].age_requirement | string/null | "16+" |
| programs[].transition_rule | string/null | "At age 16, eligible for adult White, Blue, or Purple belt..." |
| programs[].ranks[].order | int | 1 |
| programs[].ranks[].name | string | "White Belt" |
| programs[].ranks[].color | string/null | "white" |
| programs[].ranks[].min_time_months_before_next | int/null | 24 |

**Programs found:** Adult BJJ, Kids BJJ, Muay Thai / Kickboxing

**Record counts:**
- Adult BJJ: 5 ranks (White → Black), 4 stripes per belt
- Kids BJJ: 13 ranks (White → Green-Black), 4 stripes per belt
- Muay Thai / Kickboxing: 2 levels (Level 1, Level 2), no stripes

**Additional context captured:**
- IBJJF minimum time requirements per adult belt (blue: 24mo, purple: 18mo, brown: 12mo)
- Kids transition rule: at age 16, eligible for adult White, Blue, or Purple based on experience
- Black belt minimum age: 19
- World Champion exception: no minimum time at blue/purple/brown for adult World Champions

**Fields not found (not applicable — manual input):** N/A

**Gotchas:**
- Spark may store rank data differently than the IBJJF standard — the extension prompt
  (PB4 in spark-extraction-prompt.md) will cross-reference what Spark actually has configured
- Andrew confirmed kids belts are called "Grey-White" not "Gray/White" — use hyphenated format

**Output file:** `Gym Revamp/spark-belt-ranks.json`

---

## Playbook 5: Spark Member Notes Extraction

**When:** Before M2.2
**Why:** Member notes have no bulk export. Must be captured per-member or via data dump request.
**Estimated time:** 30-90 minutes (depends on member count)

```
TASK: Extract notes from member profiles in Spark Membership.

STRATEGY: First, check if Spark's data team has already provided a dump that
includes notes (from the data@sparkmembership.com request). If yes, skip this
playbook.

If notes are NOT in the data dump, proceed:

NAVIGATE TO: Spark admin > Members > All Members

FOR EACH MEMBER with notes:
1. Open the member profile
2. Find the Notes section (may be a tab or panel)
3. Copy ALL notes — date, author, and full text
4. Move to the next member

OPTIMIZATION: Start with active members only. If the member list is sorted,
work through it systematically. Skip members with no notes.

OUTPUT: Save as ~/hma-migration/exports/spark-member-notes.csv with columns:
member_email, member_name, note_date, note_author, note_text

If there are hundreds of members with notes, save progress incrementally
(append to the CSV as you go, don't wait until the end).

NOTE: This is the most time-intensive playbook. If the member count is very
large (500+), prioritize active members and flag the rest for manual review.
```

---

## Playbook 6: GoHighLevel Automation Documentation

**When:** Before M2.5
**Why:** GHL automations cannot be exported. Each workflow must be documented in detail for recreation in AutomateWoo.
**Estimated time:** 30-60 minutes

```
TASK: Document every automation workflow in GoHighLevel for recreation in AutomateWoo.

NAVIGATE TO: GHL > Automation (or Workflows section)

FOR EACH WORKFLOW:

1. Capture the workflow name and status (active/draft/inactive)

2. Document the TRIGGER:
   - Trigger type (contact created, tag added, form submitted, appointment booked,
     pipeline stage changed, date/time, etc.)
   - Trigger conditions/filters

3. Document EVERY STEP in order:
   - Step type (send email, send SMS, wait/delay, if/else condition, add tag,
     remove tag, move pipeline stage, webhook, internal notification, etc.)
   - Step configuration:
     - For emails: subject line, body text (full content), from name
     - For SMS: message text (full content)
     - For waits: duration (minutes/hours/days)
     - For conditions: what's being checked and both branches
     - For tag operations: which tag
     - For pipeline changes: which stage
     - For webhooks: URL and payload
   - Step position in the sequence

4. Note any branching logic (if/else paths) and how they reconnect

5. Note the overall purpose of the workflow (e.g., "New lead follow-up sequence",
   "Failed payment recovery", "Win-back campaign")

OUTPUT: Save as ~/hma-migration/exports/ghl-automations.json with structure:
[
  {
    "name": "New Lead Follow-Up",
    "status": "active",
    "purpose": "Nurture new leads with email + SMS sequence over 7 days",
    "trigger": {
      "type": "contact_created",
      "conditions": ["tag = 'new-lead'"]
    },
    "steps": [
      {"order": 1, "type": "send_sms", "delay": "1 hour",
       "content": "Hey {first_name}! Thanks for reaching out..."},
      {"order": 2, "type": "wait", "duration": "1 day"},
      {"order": 3, "type": "send_email", "subject": "Welcome to Haanpaa!",
       "body": "Full email HTML here...", "from": "Haanpaa Martial Arts"}
    ]
  }
]

Take a screenshot of each workflow's visual builder:
~/hma-migration/screenshots/ghl-automation-{workflow-name-slug}.png

IMPORTANT: Capture the FULL text of every email and SMS template. Do not
summarize or truncate — we need the exact copy to recreate in AutomateWoo/MailPoet.
```

---

## Playbook 7: GoHighLevel Forms Documentation

**When:** Before M2.7
**Why:** GHL forms cannot be exported. Must be documented for recreation in WordPress.
**Estimated time:** 10-20 minutes

```
TASK: Document every form and survey in GoHighLevel.

NAVIGATE TO: GHL > Sites > Forms (or Forms/Surveys section)

FOR EACH FORM:
1. Form name and type (form, survey, quiz)
2. Every field in order:
   - Field label
   - Field type (text, email, phone, dropdown, checkbox, textarea, etc.)
   - Whether it's required
   - Placeholder text
   - Options (for dropdowns, checkboxes, radio buttons)
   - Validation rules (if visible)
3. Form settings:
   - Redirect URL after submission
   - Notification email(s)
   - Tags applied on submission
   - Pipeline actions on submission
4. Current embed location (which page/funnel is it on?)
5. Historical submission count (if visible)

OUTPUT: Save as ~/hma-migration/exports/ghl-forms.json with structure:
[
  {
    "name": "Trial Class Signup",
    "type": "form",
    "fields": [
      {"label": "Full Name", "type": "text", "required": true},
      {"label": "Email", "type": "email", "required": true},
      {"label": "Phone", "type": "phone", "required": true},
      {"label": "Which program?", "type": "dropdown",
       "options": ["Adult BJJ", "Kids BJJ", "Kickboxing"]}
    ],
    "settings": {
      "redirect": "/thank-you",
      "notification_emails": ["darby@haanpaa.com"],
      "tags_on_submit": ["trial-inquiry"],
      "pipeline_action": "New Lead stage"
    },
    "embed_location": "Trial Class landing page",
    "submission_count": 234
  }
]

Take screenshots of each form's builder view:
~/hma-migration/screenshots/ghl-form-{form-name-slug}.png
```

---

## Playbook 8: GoHighLevel Pipeline Documentation

**When:** Before M2.6
**Why:** Pipeline stage definitions and settings must be documented for Jetpack CRM recreation.
**Estimated time:** 5-10 minutes

```
TASK: Document all pipeline/opportunity stages in GoHighLevel.

NAVIGATE TO: GHL > Opportunities > Pipeline Settings (or Pipeline Management)

FOR EACH PIPELINE:
1. Pipeline name
2. Every stage in order:
   - Stage name
   - Stage position/order
   - Stage color (if visible)
   - Win probability (if configured)
   - Any automation triggers tied to this stage
3. Default pipeline (if multiple exist)
4. Current opportunity count per stage (if visible from the board view)

OUTPUT: Save as ~/hma-migration/exports/ghl-pipelines.json with structure:
[
  {
    "name": "Sales Pipeline",
    "is_default": true,
    "stages": [
      {"order": 1, "name": "New Lead", "color": "#blue"},
      {"order": 2, "name": "Contacted", "color": "#yellow"},
      {"order": 3, "name": "Trial Scheduled", "color": "#orange"},
      {"order": 4, "name": "Trial Completed", "color": "#purple"},
      {"order": 5, "name": "Offer Made", "color": "#cyan"},
      {"order": 6, "name": "Closed Won", "color": "#green"},
      {"order": 7, "name": "Closed Lost", "color": "#red"}
    ]
  }
]
```

---

## Playbook 9: Wix Page Content Extraction

**When:** Before M1.7
**Why:** Wix has no bulk page export. Each page must be scraped.
**Estimated time:** 30-60 minutes

```
TASK: Extract all page content from the current Wix website (www.teamhaanpaa.com
or whatever the current domain is).

NAVIGATE TO: The live Wix website (public-facing pages, not Wix editor)

FOR EACH PAGE on the site:

1. Record the URL path
2. Extract the full text content (all headings, paragraphs, lists, CTAs)
3. Note the page structure (sections, layout, what goes where)
4. Identify all images — for each image:
   - What it shows (description)
   - Its current URL (will be on static.wixstatic.com)
   - Its position on the page
5. Note any embedded widgets (Google Maps, calendars, social feeds, forms)
6. Record the page's meta title and meta description (view page source or
   use browser dev tools)
7. Note any CTAs and where they link to

PAGES TO CHECK (at minimum):
- Home page
- About / Our Story
- Programs (Adult BJJ, Kids BJJ, Kickboxing — may be separate pages)
- Schedule / Class Schedule
- Pricing / Membership
- Contact
- Instructors / Coaches
- Gallery / Photos
- Blog (if any — note: blog posts export separately via XML)
- Trial Class / Free Class page
- Any location-specific pages (Rockford, Beloit)
- Footer content (hours, address, phone, social links)
- Privacy Policy, Terms of Service

OUTPUT: Save as ~/hma-migration/exports/wix-page-content/ directory with
one markdown file per page:
- home.md
- about.md
- programs-adult-bjj.md
- schedule.md
- pricing.md
- contact.md
- etc.

Each file should have frontmatter:
---
source_url: https://www.teamhaanpaa.com/about
meta_title: "About Haanpaa Martial Arts"
meta_description: "Learn about our gym..."
images:
  - url: https://static.wixstatic.com/media/abc123.jpg
    description: "Gym interior photo"
    position: "Hero section"
---

# Page content as markdown here...

Also save a site map (list of all URLs found):
~/hma-migration/exports/wix-sitemap.csv with columns:
url, page_title, meta_title, meta_description

Download all images to ~/hma-migration/exports/wix-images/ using their
original filenames or descriptive names.
```

---

## Playbook 10: Wix Media Download

**When:** Before M1.7
**Why:** All images need to move to WordPress media library. Wix has no bulk download.
**Estimated time:** 15-30 minutes

```
TASK: Download all media files from the Wix site.

OPTION A (preferred): If you have access to the Wix Editor/Dashboard:
1. Go to Wix Dashboard > Media Manager (or Site > Media)
2. Download all files — Wix may allow selecting multiple files and downloading
3. Save everything to ~/hma-migration/exports/wix-media/

OPTION B: If only the public site is accessible:
1. Use the sitemap from Playbook 9
2. Visit each page and download every image
3. Check the page source for any images not visible (background images, etc.)
4. Organize by page: ~/hma-migration/exports/wix-media/{page-name}/

FOR EACH IMAGE, create an entry in:
~/hma-migration/exports/wix-media-manifest.csv with columns:
filename, original_url, page_used_on, description, alt_text

IMPORTANT: Get the highest resolution version available. Wix often serves
resized versions — check the URL for resize parameters and remove them
to get the original.
```

---

## Playbook 11: Spark Member Portal Content

**When:** Before M3.2
**Why:** Need to know what content exists behind Spark's member-facing portal to recreate in WooCommerce Memberships.
**Estimated time:** 10-20 minutes

```
TASK: Document all content and features visible in Spark's member-facing portal/app.

NAVIGATE TO: Spark Member Portal (may be a web app or you may need to describe
the mobile app screens — ask the human to show you)

DOCUMENT:
1. What tabs/sections exist in the member dashboard?
2. What information does a member see?
   - Profile info
   - Membership status
   - Billing info / payment history
   - Class schedule
   - Attendance history
   - Belt rank / progress
   - Upcoming classes / reservations
   - Notifications
   - Documents (waivers, contracts)
3. What actions can a member take?
   - Book a class / RSVP
   - Cancel a reservation
   - Update profile
   - Update payment method
   - View billing history
   - Contact the gym
   - Opt in/out of SMS
4. Any gamification elements (streaks, badges, leaderboards)?
5. Any content areas (blog posts, technique videos, announcements)?

OUTPUT: Save as ~/hma-migration/exports/spark-member-portal-audit.md

This is a feature audit, not a data extraction. The goal is to know what the
members currently see so the new WordPress My Account dashboard provides
feature parity or better.
```

---

## Playbook 12: USAePay Recurring Billing Profiles

**When:** Before M1.9
**Why:** Need every active subscription mapped for WooCommerce Subscriptions import.
**Estimated time:** 15-30 minutes

```
TASK: Export all recurring billing profiles from USAePay.

NAVIGATE TO: USAePay Merchant Console (login at usaepay.com or the merchant portal URL)

1. Go to Recurring Billing (or Customers > Recurring)
2. Export or document EVERY active recurring profile:
   - Customer name
   - Customer email
   - Amount charged
   - Billing frequency (monthly, weekly, etc.)
   - Next billing date
   - Last successful charge date
   - Card type and last 4 digits
   - Status (active, paused, failed)
   - Start date / creation date
   - Number of successful charges
   - Any notes

3. Also export the full Customer Database:
   - Go to Customers > Customer Database
   - Export as CSV

4. Also export the Transaction History:
   - Go to Reports > Transaction Report
   - Set date range to the FULL history (earliest available to today)
   - Export as CSV

OUTPUT: Save all exports to:
~/hma-migration/exports/usaepay-recurring-profiles.csv
~/hma-migration/exports/usaepay-customer-database.csv
~/hma-migration/exports/usaepay-transaction-history.csv

If the console doesn't offer CSV export for recurring profiles,
document each one manually in the CSV format above.
```

---

## Data Source Hierarchy: Dump First, Scrape the Gaps

**Do NOT scrape what you can export or request in bulk.**

### Step 1 — Bulk exports (self-service CSV from each platform's UI)
Spark, GHL, and USAePay all have CSV export buttons for record data (members, contacts, transactions, attendance). Use those first — they're faster and more complete than scraping.

### Step 2 — Request a full data dump from Spark
Email `data@sparkmembership.com` and request everything: members, notes, SMS history, belt rank history per member, attendance, billing. This may take days, so **send this request immediately**.

### Step 3 — CoWork playbooks fill the gaps
These playbooks exist for data that has **no export path** — configuration and structure data that only exists in the admin UI:

| Playbook | Why it can't be exported | Always needed? |
|----------|------------------------|----------------|
| **PB1: Membership Plan Structure** | Spark has no plan export (names, prices, billing cycles) | **Yes — always** |
| **PB2: POS Product Catalog** | No export function in Spark POS settings | **Yes — always** |
| **PB3: Class Schedule Structure** | Configuration data, not exportable records | **Yes — always** |
| **PB4: Belt Rank Definitions** | Configuration data (rank names, order, thresholds) | **Yes — always** |
| **PB5: Member Notes** | No bulk export — **only if data dump doesn't include notes** | **Fallback only** |
| **PB6: GHL Automations** | No export at all (126-vote feature request still open) | **Yes — always** |
| **PB7: GHL Forms** | No export | **Yes — always** |
| **PB8: GHL Pipelines** | No export | **Yes — always** |
| **PB9: Wix Page Content** | No bulk page export (blog XML only) | **Yes — always** |
| **PB10: Wix Media** | No bulk download | **Yes — always** |
| **PB11: Spark Member Portal** | Feature audit, not data extraction | **Yes — always** |
| **PB12: USAePay Recurring Profiles** | May have CSV export — scrape only if console lacks it | **Check first** |

### When to skip a playbook
- **PB5 (Member Notes)**: Skip if the Spark data dump includes notes
- **PB12 (USAePay)**: Skip the scraping if the merchant console offers CSV export for recurring profiles (try the export button first)
- Any playbook where the data dump or CSV export covers the same data

---

## Playbook Execution Order

Recommended sequence — run these in the order data is needed by the milestones:

| Priority | Playbook | Milestone | Status | Output |
|----------|----------|-----------|--------|--------|
| 1 | **PB1: Spark Membership Plans** | M1.4 | **DONE** (2026-03-30) | `spark-membership-plans.csv` (50 plans) |
| 2 | **PB2: Spark POS Products** | M1.4 | **DONE** (2026-03-30) | `spark-pos-products.csv` (10 products) |
| 3 | **PB9: Wix Page Content** | M1.7 | **DONE** (2026-03-30) | `wix-page-content/` (11 pages as markdown) |
| 4 | **PB10: Wix Media Download** | M1.7 | **DONE** (2026-03-30) | `wix-sitemap.csv` (11 URLs) |
| 5 | **PB12: USAePay Recurring Profiles** | M1.9 | Pending | — |
| 6 | **PB6: GHL Automations** | M2.5 | Pending | — |
| 7 | **PB7: GHL Forms** | M2.7 | Pending | — |
| 8 | **PB8: GHL Pipelines** | M2.6 | Pending | — |
| 9 | **PB3: Spark Class Schedule** | M3.3 | **DONE** (2026-03-30) | `spark-class-schedule.csv` (19 classes) |
| 10 | **PB11: Spark Member Portal** | M3.2 | **DONE** (2026-03-30) | `spark-dashboard-audit.md` |
| 11 | **PB4: Spark Belt Rank Defs** | M4.1 | **DONE** (2026-03-30) | `spark-belt-ranks.json` (3 programs) |
| 12 | **PB5: Spark Member Notes** | M2.2 | Pending (awaiting data dump) | — |

**Data files location:** `~/Documents/Claude/Projects/Gym Revamp/`

---

## Tips for Running These Playbooks

1. **Batch sessions by platform.** Log into Spark, run PB1 + PB2 + PB3 + PB4 + PB11 in one session. Log into GHL, run PB6 + PB7 + PB8. Then Wix (PB9 + PB10), then USAePay (PB12).

2. **Export CSVs first, scrape second.** Before running a CoWork playbook, check if the platform has a self-service CSV export for that data. Only scrape what can't be exported.

3. **Verify outputs.** After each playbook, spot-check the output file. Open the CSV/JSON, confirm the data looks right, count the records.

4. **Save screenshots as backup.** Even if the structured data capture works perfectly, screenshots are insurance against missed fields.

5. **Keep the platforms alive.** Do NOT cancel any subscription until all playbooks are complete and verified. Export files from GHL expire in 30 days.

6. **Run playbooks early.** Phase 1 (export everything) should happen in the first week, even if you won't need the data for months. Platforms can change their UI, lock accounts, or deprecate features.

---

## The Feedback Loop: How Playbooks Evolve

This file is a **living document**. Every time a CoWork agent runs a playbook, it updates this file with what it actually found. This creates a compounding accuracy loop:

### What the agent adds after each run

Each completed playbook gets a `### Completed Run — {date}` section appended with:

1. **Corrected navigation path** — The exact clicks used (the original instructions are best-guesses based on research; the real UI may differ). The agent corrects the `NAVIGATE TO` instructions for the next run.
2. **Schema discovery** — The exact columns, data types, and sample values from the output files. This becomes the **canonical schema** that downstream import scripts (WP-CLI `wp hma import`, Jetpack CRM DAL scripts, WC Subscriptions CSV importer) use directly. No guessing at column names or formats.
3. **Fields added** — Things found in the UI that weren't listed in the original playbook.
4. **Fields removed** — Things listed in the original playbook that don't exist in the actual UI.
5. **Gotchas** — Rate limits, pagination quirks, session timeouts, fields that render differently than expected.
6. **Record counts** — How much data was captured (used for validation in the import step).

### How downstream agents use the feedback

When a later agent (e.g., one building the `wp hma import belt-ranks` CLI command) reads this file, it finds:
- The **exact CSV column names** from the completed Playbook 4 run
- The **data types and sample values** so it can write the correct `$wpdb->prepare()` format strings
- The **record count** so it knows expected input size for batch sizing
- Any **data quirks** (e.g., "Spark exports dates as MM/DD/YYYY, not ISO 8601") that inform cleaning logic

This means the import scripts can be written with confidence rather than speculation about what the export data looks like.

### Example of a completed run annotation

```markdown
### Completed Run — 2026-04-15

**Navigation path (corrected):**
Spark Dashboard > Settings (gear icon, top-right) > Membership Plans > View All

**Schema produced (`spark-membership-plans.csv`):**
| Column | Type | Example |
|--------|------|---------|
| plan_name | string | "Adult BJJ Monthly" |
| price | decimal | 149.00 |
| billing_frequency | string | "monthly" |
| signup_fee | decimal | 50.00 |
| trial_days | int | 7 |
| contract_months | int | 12 |
| locations | string | "rockford,beloit" |
| programs_included | string | "Adult BJJ,Open Mat" |
| status | string | "active" |
| notes | string | "Includes gi rental first month" |

**Fields added (not in original instructions):**
- `auto_renew` (boolean) — whether the plan auto-renews after contract term
- `cancellation_fee` (decimal) — early cancellation penalty amount
- `family_discount_pct` (int) — percentage discount for additional family members

**Fields not found:**
- `trial_days` — Spark doesn't have a trial period field per plan; trials are
  handled as a separate "Trial Membership" plan type

**Record count:** 14 plans (8 active, 6 archived)

**Gotchas:**
- The plan list paginates at 10 items; had to click "Next" to see the remaining 4
- Archived plans are hidden by default; toggled "Show Archived" filter to reveal them
- Location assignment is not on the plan detail page — it's under a separate
  "Location Access" tab within each plan
```
