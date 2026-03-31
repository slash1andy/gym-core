# Data Migration Guide

> Complete plan for migrating every piece of data from the current stack (Spark Membership, GoHighLevel, Wix, USAePay/Pitbull Processing) to the new WordPress 7.0 / WooCommerce ecosystem. Organized by milestone to show when each migration step executes.

**Last updated:** 2026-03-29

---

## Current Systems Inventory

| System | What It Holds | Export Method | Format |
|--------|--------------|---------------|--------|
| **Spark Membership** | Members, billing, attendance, belt ranks, notes, tasks, POS, SMS history, time tracking | CSV from UI + contact `data@sparkmembership.com` for full dump | CSV / Excel |
| **GoHighLevel** | Contacts, SMS/email threads, pipeline/deals, call recordings, automations, forms | CSV from UI + API + "Export Conversations" marketplace app | CSV / JSON |
| **Wix** | Website content, blog posts, images, forms, contacts, SEO settings | Blog XML export + manual page content + Screaming Frog crawl | XML / manual |
| **USAePay / Pitbull** | Transaction history, customer records, recurring billing profiles, saved card tokens | Merchant console reports + REST API | CSV / JSON |
| **Vimeo** | Training/technique videos | Download from Vimeo dashboard | MP4 |
| **Google Sheets** | Staff scheduling (Joy), budgets, cashflow | Download as CSV | CSV |
| **Dropbox / Google Drive** | Gym documents, waivers, records | Download all files | Various |
| **QuickBooks** | Accounting, bookkeeping | Stays in place (not migrating) | N/A |

---

## Critical Migration Constraints

### 1. Saved Payment Methods — Token Migration IS Possible

USAePay gateway-specific tokens (`tok_xxx`) are not directly transferable, but the underlying card data (PANs) **can be migrated processor-to-processor** via Stripe's PAN Import program. The merchant never touches raw card numbers — the transfer is encrypted (PGP) and goes directly between USAePay and Stripe via SFTP.

**How PAN migration works:**
1. Contact USAePay and request a PCI-compliant card data export (they approve case-by-case; per-card fee applies)
2. Submit Stripe's [data migration request form](https://support.stripe.com/questions/request-a-data-migration) with: previous processor, Stripe account ID, record count, payment method types
3. Create Stripe Customer objects for each member (WooPayments does this on first interaction, or batch-create via API)
4. Provide Stripe a CSV mapping file: `old_customer_id` → `stripe_customer_id` (no sensitive data in this file)
5. USAePay encrypts PAN data with Stripe's PGP key → uploads to Stripe's SFTP
6. Stripe imports, creates PaymentMethod objects, attaches to Customers (~10 business days)
7. Stripe returns a mapping file; you update WooCommerce subscription records with new Stripe PaymentMethod IDs
8. Stripe's Card Account Updater (CAU) auto-refreshes any expired/replaced cards post-import

**Timeline:** ~4 weeks total (USAePay approval + transfer + Stripe import)

**WooPayments consideration:** WooPayments uses a Stripe Express account (limited dashboard access). Coordinate with WooPayments support to confirm PAN import is supported on the Express account, or request a Standard account upgrade if needed.

**ACH / bank accounts:** Stripe also supports ACH migration via the same SFTP mechanism. Proof of original customer authorization must be maintained (Nacha compliance).

**Decision point — PAN import vs. manual re-entry:**

| Factor | PAN Import | Manual Re-Entry |
|--------|-----------|-----------------|
| Member friction | Zero — cards just work | Members must log in and add card |
| Cost | USAePay per-card fee (unknown) + staff time to coordinate | Free (but risk of churn/delay) |
| Timeline | ~4 weeks | Immediate import, 2-4 weeks for member compliance |
| Risk | USAePay may deny; WooPayments Express may complicate | Some members never update → revenue gap |
| Best for | 300+ recurring members | <200 members or if USAePay denies export |

**Recommended approach — pursue BOTH paths in parallel:**
1. Contact USAePay early (Phase 1) to request PAN export and get a fee quote
2. Contact Stripe/WooPayments support to confirm Express account compatibility
3. If approved: proceed with PAN import (zero member friction)
4. If denied or delayed: fall back to manual re-entry with multi-channel campaign (email + SMS + in-person ask)
5. Either way, import subscriptions initially with `next_payment_date` set correctly so billing continuity is maintained

### 2. Membership Plan Details Don't Export from Spark
Spark has no export for plan structure (names, prices, billing cycles, terms). Use **CoWork Playbook 1** to have an AI agent navigate Spark's admin UI and capture every plan detail.

### 3. GHL Automations Don't Export
GoHighLevel workflows/automations cannot be exported as JSON/XML. Use **CoWork Playbook 6** to have an AI agent document every workflow step-by-step for recreation in AutomateWoo.

### 4. Wix Pages Don't Bulk Export
Static page content must be scraped from the live site. Blog posts export as XML. Use **CoWork Playbook 9** to have an AI agent extract all page content as structured markdown files.

### 5. Passwords Don't Transfer
Members from Spark/GHL will need to set new passwords on the WordPress site. Send a "Welcome to the new site" email with a password reset link.

### 6. CoWork Playbooks for "Manual" Tasks
Every task previously labeled "manual" now has an AI agent playbook in [`docs/planning/cowork-migration-playbooks.md`](cowork-migration-playbooks.md). These are ready-to-use prompts for Claude Desktop / Claude CoWork with computer use — the agent navigates the platform UIs, captures data, and writes structured output files (CSV, JSON, Markdown).

---

## Migration by Milestone

### Milestone 1: Billing Engine + Site Replacement

#### M1 Exports (do these BEFORE building)

| Source | Data | How to Export | When |
|--------|------|---------------|------|
| **Wix** | All page content (text, images) | **CoWork Playbook 9 + 10** — AI agent extracts page content as markdown + downloads media | Before M1.7 |
| **Wix** | Blog posts | Wix Settings > Blog > Export (XML) | Before M1.7 |
| **Wix** | All URLs + meta titles + descriptions | Crawl with Screaming Frog SEO Spider (free <500 URLs) | Before M1.7 |
| **Wix** | Form submissions | Wix Dashboard > Contacts > Forms > Export CSV | Before M1.7 |
| **Wix** | Contacts/members | Wix Dashboard > Contacts > Export CSV | Before M1.7 |
| **Spark** | Membership plan details | **CoWork Playbook 1** — AI agent navigates Spark UI and captures every plan detail | Before M1.4 |
| **Spark** | Member profiles | Members > All Members > Export as CSV | Before M1.4 |
| **USAePay** | Full transaction history | Merchant console > Reports > Transaction Report > Export CSV (set date range to "all") | Before M1.9 |
| **USAePay** | Customer database | Merchant console > Customer Database > Export CSV | Before M1.9 |
| **USAePay** | Recurring billing profiles | Merchant console > Recurring Billing > Export (document: customer, amount, frequency, next bill date, card last-4) | Before M1.9 |
| **USAePay** | Active subscription list | Cross-reference with Spark member list to build the subscription import CSV | Before M1.9 |
| **USAePay** | PAN export request | Contact USAePay, request PCI-compliant card data export; get fee quote and approval | Before M1.2 (start early — 4-week lead time) |
| **Stripe** | Data migration intake | Submit [migration request form](https://support.stripe.com/questions/request-a-data-migration); confirm WooPayments Express account compatibility | Before M1.2 |
| **Pitbull** | Contract terms + ETF details | Review merchant services agreement; check early termination fees | Before M1.2 |

#### M1 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **WordPress** | Blog posts from Wix | WordPress Importer (native XML import) | M1.7 |
| **WordPress** | Page content | Recreate from CoWork Playbook 9 markdown output in block editor | M1.7 |
| **WordPress** | Media (images) | Upload to WordPress media library | M1.7 |
| **WordPress** | 301 redirects (Wix URL → WP URL) | Redirection plugin or Rank Math redirect manager; build map from Screaming Frog data | M1.7 |
| **WooCommerce** | Membership subscription products | Create from CoWork Playbook 1 output (small number of products) | M1.4 |
| **WooPayments** | Stripe account connection | WooPayments onboarding wizard (apply 1-2 weeks before go-live, MCC 7941) | M1.2 |

#### M1 Data Preservation

| Data | Action | Storage |
|------|--------|---------|
| USAePay transaction history CSV | Archive for tax/accounting (12-18 months minimum) | Google Drive or repo `data/archives/` |
| Wix form submissions CSV | Archive as historical record | Google Drive |
| Pitbull/USAePay 1099-K | Keep for tax filing (they issue for partial year) | QuickBooks |

---

### Milestone 2: Replace GoHighLevel

#### M2 Exports (do these BEFORE canceling GHL)

| Source | Data | How to Export | When |
|--------|------|---------------|------|
| **GHL** | All contacts | Contacts > Export CSV | Before M2.2 |
| **GHL** | Opportunities/deals | Opportunities > Export CSV | Before M2.6 |
| **GHL** | SMS/email conversation history | Install **"Export Conversations" marketplace app** (by Innexum Technologies) — exports all channels to CSV/JSON | Before M2.7 |
| **GHL** | Call recordings | Download individually from conversation view, or build API script for bulk download | Before M2.7 |
| **GHL** | Automation workflows | **CoWork Playbook 6** — AI agent documents every workflow trigger, step, and template | Before M2.5 |
| **GHL** | Forms | **CoWork Playbook 7** — AI agent captures every form field, type, and setting | Before M2.7 |
| **GHL** | Pipeline stage definitions | **CoWork Playbook 8** — AI agent captures pipeline stages, colors, and rules | Before M2.6 |
| **GHL** | Phone numbers | Port to Twilio via GHL Support ticket (provide Twilio Account SID). Set up A2P 10DLC registration in Twilio FIRST. Timeline: 1-2 business days. | Before M2.4 |
| **Spark** | SMS/email send history | Request from `data@sparkmembership.com` (no self-service export) | Before M2.4 |
| **Spark** | Member notes | Request from `data@sparkmembership.com`; fallback: **CoWork Playbook 5** (AI agent extracts notes per-member) | Before M2.2 |

#### M2 Data Cleaning (between export and import)

The GHL contact database is known to be messy. Before importing:

1. **Deduplicate** — Match on phone number + email across GHL and Spark exports
2. **Remove junk contacts** — Every inbound caller was auto-added in GHL. Remove numbers with no name, no email, no engagement
3. **Categorize** — Tag each contact: `active-member`, `lead`, `lapsed`, `prospect`, `trial`, `junk`
4. **Normalize phone numbers** — E.164 format (`+1XXXXXXXXXX`) for Twilio compatibility
5. **Normalize dates** — Convert all dates to `Y-m-d H:i:s` UTC
6. **Map locations** — Add a `location` column (rockford / beloit) based on Spark data
7. **Map membership tiers** — Add a `membership_tier` column mapped to WooCommerce product IDs
8. **Merge records** — Create a single canonical record per person from Spark + GHL data. Spark is the source of truth for membership data; GHL is the source of truth for lead/prospect data.

**Expected outcome:** One clean CSV with columns: `email, first_name, last_name, phone, location, status, membership_tier, spark_member_id, tags, notes`

#### M2 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **Jetpack CRM** | Cleaned contacts | PHP import script using DAL (`$zbs->DAL->contacts->addUpdateContact()`) with `silentInsert => true` | M2.2 |
| **Jetpack CRM** | Contact tags | Include in DAL import (`'tags' => array('active-member', 'rockford')`) | M2.2 |
| **Jetpack CRM** | Contact notes (from Spark) | DAL `addUpdateLog()` with type `note` | M2.2 |
| **Jetpack CRM** | SMS conversation history (from GHL) | DAL `addUpdateLog()` with type `other_contact`, full thread in `longdesc` | M2.2 |
| **Jetpack CRM** | Pipeline stages | Configure from CoWork Playbook 8 output | M2.6 |
| **Jetpack CRM** | Pipeline deals | CSV import or programmatic creation | M2.6 |
| **Twilio** | Phone number(s) | Port from GHL/LeadConnector via support ticket | M2.4 |
| **AutomateWoo** | Workflow recreations | Rebuild from CoWork Playbook 6 JSON output (full trigger/step/template data) | M2.5 |
| **MailPoet** | Email templates | Recreate from CoWork Playbook 6 email content (full HTML captured) | M2.3 |
| **WordPress** | Contact form submissions (historical) | Archive as CSV; not imported into CRM unless needed | M2.7 |

#### Jetpack CRM Import Script (recommended architecture)

```php
// wp-cli command: wp hma import contacts --file=cleaned-contacts.csv
// Uses Jetpack CRM DAL directly for maximum control

$zbs = zeroBSCRM();

foreach ( $contacts as $contact ) {
    // Create/update contact (email is unique key)
    $contact_id = $zbs->DAL->contacts->addUpdateContact( array(
        'data' => array(
            'email'     => $contact['email'],
            'fname'     => $contact['first_name'],
            'lname'     => $contact['last_name'],
            'mobtel'    => $contact['phone'],
            'status'    => $contact['status'] === 'active-member' ? 'Customer' : 'Lead',
            'tags'      => explode( ',', $contact['tags'] ),
            'tag_mode'  => 'append',
            'created'   => strtotime( $contact['join_date'] ),
            // Custom fields (create these in CRM Settings first)
            'belt_rank'       => $contact['belt_rank'],
            'membership_tier' => $contact['membership_tier'],
            'home_location'   => $contact['location'],
            'spark_member_id' => $contact['spark_member_id'],
        ),
        'silentInsert' => true, // skip automation triggers during migration
    ) );

    // Import notes as activity logs
    if ( ! empty( $contact['notes'] ) ) {
        $zbs->DAL->logs->addUpdateLog( array(
            'data' => array(
                'objtype'   => ZBS_TYPE_CONTACT,
                'objid'     => $contact_id,
                'type'      => 'note',
                'shortdesc' => 'Imported from Spark Membership',
                'longdesc'  => $contact['notes'],
                'created'   => strtotime( $contact['join_date'] ),
            ),
        ) );
    }
}
```

---

### Milestone 3: Member Portal + Content Gating

#### M3 Exports

| Source | Data | How to Export | When |
|--------|------|---------------|------|
| **Spark** | Class schedule structure | **CoWork Playbook 3** — AI agent captures full schedule for both locations | Before M3.3 |
| **Spark** | Content/page structure for member area | **CoWork Playbook 11** — AI agent audits the member portal features and content | Before M3.2 |

#### M3 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **WordPress users** | All members as WP users | "Import Users from CSV with Meta" plugin or WP-CLI script | M3.1 |
| **WooCommerce Memberships** | Membership plan assignments | Programmatic — link WP users to membership plans based on Spark membership tier | M3.1 |
| **WooCommerce Subscriptions** | Active recurring billing | CSV importer (`woocommerce-subscriptions-importer-exporter`) — see format below | M3.1 |
| **gym-core** | Class schedule data | Create from CoWork Playbook 3 CSV output (or script import into `gym_class` CPT) | M3.3 |

#### WooCommerce Subscriptions CSV Format

```csv
customer_email,subscription_status,start_date,next_payment_date,billing_period,billing_interval,order_items,order_total,payment_method,payment_method_title
john@example.com,wc-active,2024-06-15 00:00:00,2026-05-01 00:00:00,month,1,product_id:123|quantity:1|total:99.00,99.00,,Manual Renewal
jane@example.com,wc-active,2025-01-10 00:00:00,2026-04-10 00:00:00,month,1,product_id:124|quantity:1|total:79.00,79.00,,Manual Renewal
```

**Key fields:**
- `start_date` — Original membership start date from Spark (must be in the past)
- `next_payment_date` — Calculate from Spark's billing cycle (must be in the future)
- `payment_method` — Leave blank initially = manual renewal. Switch to `woocommerce_payments` after member adds card.
- `order_items` — Map Spark membership tier to WooCommerce product ID

---

### Milestone 4: Belt Rank + Attendance Tracking

#### M4 Exports

| Source | Data | How to Export | When |
|--------|------|---------------|------|
| **Spark** | Belt rank records (current rank per member) | Rank management section > Export CSV | Before M4.1 |
| **Spark** | Belt promotion history | Request from `data@sparkmembership.com` for full per-member history | Before M4.1 |
| **Spark** | Attendance records | Attendance > Attendance Report > Export CSV | Before M4.2 |
| **Spark** | Belt rank definitions (names, order, programs) | **CoWork Playbook 4** — AI agent captures rank hierarchy from Spark UI (confirm Kids BJJ names with Darby) | Before M4.1 |
| **Spark** | Promotion eligibility thresholds | **CoWork Playbook 4** (included in belt rank extraction) — captures promotion thresholds per program | Before M4.3 |

#### M4 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **`{prefix}gym_ranks`** | Current rank per member | WP-CLI bulk import script (`wp hma import belt-ranks`) | M4.1 |
| **`{prefix}gym_rank_history`** | Promotion audit trail | WP-CLI bulk import script | M4.1 |
| **`{prefix}gym_attendance`** | Historical check-in records | WP-CLI bulk import script (`wp hma import attendance`) | M4.2 |
| **WordPress user meta** | Current belt rank (for fast lookups) | Set during belt rank import | M4.1 |

#### Bulk Import Script Architecture

```bash
# Recommended WP-CLI commands for gym-core
wp hma import users         --file=members.csv          # WP users + meta
wp hma import belt-ranks    --file=belt-ranks.csv       # gym_ranks + gym_rank_history
wp hma import attendance    --file=attendance.csv        # gym_attendance
wp hma import achievements  --file=badges.csv           # gym_achievements (M5)

# Flags
--dry-run          # Validate without writing
--batch-size=500   # Records per batch (default 500)
--skip-existing    # Skip records that match on spark_member_id
--verbose          # Show per-record status
```

**Performance:** Use bulk `INSERT` with `$wpdb->prepare()` in chunks of 500 rows. Wrap each chunk in a transaction. For a gym with ~hundreds of members and ~thousands of attendance records, the full import should complete in under 30 seconds.

**Deduplication key:** Use `spark_member_id` or `email` to map records to WordPress user IDs. Build the mapping table first, then use it for all subsequent imports.

---

### Milestone 5: Gamification Engine

#### M5 Data

No historical data to migrate unless Spark tracks badges/achievements (unlikely). This milestone builds new functionality.

If the gym has any informal achievement tracking (e.g., Google Sheet of "100 class club" members), import as seed data for the `gym_achievements` table.

---

### Milestone 6: AI Operations Layer

#### M6 Data

No data migration needed. This milestone wires `hma-ai-chat` into live WooCommerce/CRM data.

---

### Milestone 7: Media, Migration + Go-Live

#### M7 Exports

| Source | Data | How to Export | When |
|--------|------|---------------|------|
| **Vimeo** | All training/technique videos | Download from Vimeo dashboard (Settings > Video file > Download) | Before M7.1 |
| **Spark** | Any remaining data not yet exported | Final comprehensive export — `data@sparkmembership.com` | Before M7.2 |
| **Spark** | Historical billing/payment records | Reports > Financial Transactions > Export CSV (full date range) | Before M7.2 |
| **Dropbox** | All gym documents | Download all to local + re-upload to WordPress media or Google Drive | Before M7.4 |
| **Google Sheets** | Staff schedules, budgets | Download as CSV / keep in Google (not migrating to WordPress) | Before M7.4 |

#### M7 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **Jetpack VideoPress** | Training videos | Upload via WordPress media library (VideoPress handles hosting) | M7.1 |
| **WooCommerce Memberships** | Video content gating | Assign membership restriction rules to video posts/pages | M7.1 |
| **gym-core custom tables** | Any remaining Spark data (historical attendance, notes) | WP-CLI import scripts | M7.2 |

---

## Migration Timeline & Sequencing

```
PHASE 1: BULK EXPORTS + DATA DUMP REQUEST (Week 1 — do before building)
├── Day 1: Email data@sparkmembership.com — request full dump (notes, SMS, ranks, attendance)
├── Day 1: Request USAePay PAN export for Stripe migration (~4 week lead time)
├── Day 1: Review Pitbull Processing contract for ETF
├── Day 1-2: Self-service CSV exports from Spark UI (members, attendance, ranks, billing)
├── Day 1-2: Self-service CSV exports from GHL (contacts, deals)
├── Day 1-2: Install GHL "Export Conversations" marketplace app — export SMS/email history
├── Day 1-2: Export USAePay (transactions, customers, recurring profiles) from merchant console
├── Day 2-3: Wix blog XML export + Screaming Frog URL crawl
└── Day 3: Download Vimeo videos

PHASE 1B: COWORK SCRAPING (fills gaps that CSV exports can't cover)
├── PB1: Spark membership plan structure (config data, no export exists)
├── PB2: Spark POS product catalog (no export exists)
├── PB3: Spark class schedule structure (config data)
├── PB4: Spark belt rank definitions (config data)
├── PB6: GHL automation workflows (no export exists — document every step)
├── PB7: GHL forms (no export exists)
├── PB8: GHL pipelines (no export exists)
├── PB9: Wix page content (no bulk export — scrape from live site)
├── PB10: Wix media (no bulk download)
├── PB5: Spark member notes (ONLY if data dump doesn't include them)
└── PB11: Spark member portal feature audit

PHASE 2: CLEAN & PREPARE (while building M1)
├── Week 2-3: Deduplicate + clean GHL contacts
├── Week 2-3: Merge Spark + GHL into single canonical contact list
├── Week 2-3: Normalize phone numbers (E.164), dates (UTC), locations
├── Week 2-3: Map Spark membership tiers → WooCommerce product IDs
├── Week 2-3: Build Wix URL → WordPress URL redirect map
├── Week 2-3: Rebuild GHL automations in AutomateWoo (from PB6 JSON)
└── Week 2-3: Set up Twilio account + A2P 10DLC registration

PHASE 3: IMPORT (aligned to milestones)
├── M1: Import Wix content, create WC products, set up WooPayments
├── M2: Import contacts → Jetpack CRM, port phone numbers → Twilio
├── M3: Import WP users, WC Subscriptions (manual renewal), Memberships
├── M4: Import belt ranks + attendance → custom tables
├── M5: (No migration — new functionality)
├── M6: (No migration — wiring AI to live data)
└── M7: Import videos → VideoPress, final Spark data, parallel run

PHASE 4: CUTOVER
├── Week N: Email + SMS all members about payment method update
├── Week N+1: Members add cards on new site
├── Week N+2: Flip subscriptions manual → automatic as cards come in
├── Week N+3: Disable Spark billing (before next cycle)
├── Week N+4: 30-day parallel run (new signups on WC, existing on Spark)
├── Week N+6: Batch-migrate remaining members to WC billing
├── Week N+8: Final decommission checklist
└── Week N+8: Cancel Spark, GHL, Wix, Vimeo, USAePay
```

---

## Data That Cannot Be Migrated

| Data | Why | Mitigation |
|------|-----|------------|
| Spark member app access | Proprietary app tied to Spark platform | WordPress My Account replaces this |
| GHL workflow execution history | No export path | Rebuild workflows in AutomateWoo; history starts fresh |
| GHL form definitions | No export path | Rebuild forms manually |
| GHL calendar configurations | No export path | Rebuild in WordPress |
| Spark POS product catalog | No export in Spark UI | Manually recreate in WooCommerce |
| Wix analytics data | Stays in Wix | Google Analytics data persists if GA was connected |
| Member passwords | Cannot extract hashes from Spark/GHL | Password reset emails on first login |

---

## Pre-Cancellation Checklist

**Do NOT cancel any service until ALL of these are verified:**

### Spark Membership
- [ ] Members CSV exported (all contacts, all locations)
- [ ] Attendance records CSV exported (full history)
- [ ] Belt rank/promotion CSV exported
- [ ] Financial transaction CSV exported (full history)
- [ ] Full data dump requested from `data@sparkmembership.com` and received
- [x] Membership plan details captured (CoWork Playbook 1) — `spark-membership-plans.csv` (50 plans)
- [x] POS product catalog captured (CoWork Playbook 2) — `spark-pos-products.csv` (10 products)
- [ ] Member notes captured (from data dump or CoWork Playbook 5)
- [ ] SMS send history captured
- [x] Class schedule structure documented — `spark-class-schedule.csv` (19 classes)
- [x] Belt rank definitions documented — `spark-belt-ranks.json` (3 programs, Kids BJJ 13 belts confirmed)
- [ ] Promotion thresholds documented
- [x] Spark dashboard/portal audit — `spark-dashboard-audit.md`

### GoHighLevel
- [ ] Contacts CSV exported
- [ ] Opportunities/deals CSV exported
- [ ] SMS/email conversation history exported (via "Export Conversations" app)
- [ ] Call recordings downloaded (critical ones)
- [ ] All automation workflows documented (CoWork Playbook 6)
- [ ] Forms documented
- [ ] Pipeline stages documented
- [ ] Phone number(s) ported to Twilio (confirmed working)
- [ ] A2P 10DLC registered in Twilio
- [ ] 30-day parallel run completed

### Wix
- [ ] Blog posts exported as XML
- [x] All page content extracted (CoWork Playbook 9) — `wix-page-content/` (11 pages as markdown)
- [ ] All media/images downloaded
- [ ] Form submissions exported as CSV
- [ ] Contacts exported as CSV
- [x] Full URL crawl completed — `wix-sitemap.csv` (11 URLs)
- [ ] 301 redirects implemented and tested
- [ ] DNS cutover completed
- [ ] New WordPress site live and verified

### USAePay / Pitbull Processing
- [ ] Full transaction history CSV exported
- [ ] Customer database CSV exported
- [ ] Recurring billing profiles documented
- [ ] PAN export requested from USAePay (fee quote received, approved)
- [ ] Stripe data migration intake form submitted
- [ ] PAN import completed OR manual re-entry campaign sent
- [ ] Contract ETF reviewed and accounted for
- [ ] All pending batches settled
- [ ] 1099-K timing documented (partial year)
- [ ] WooPayments/Stripe account live and processing
- [ ] All active members billing through WooPayments

### Vimeo
- [ ] All videos downloaded locally
- [ ] Videos uploaded to Jetpack VideoPress
- [ ] Video content gating configured in WooCommerce Memberships
- [ ] Embedded video links updated on all pages

---

## Tools & Resources

| Tool | Purpose | Cost |
|------|---------|------|
| **Screaming Frog SEO Spider** | Crawl Wix site for URL/SEO mapping | Free (<500 URLs) |
| **"Export Conversations" GHL app** | Export SMS/email history from GoHighLevel | Pay-per-export |
| **Import Users from CSV with Meta** | WordPress user import plugin | Free |
| **WC Customer/Order CSV Import Suite** | Import historical WC orders | Paid (official WC extension) |
| **WC Subscriptions Importer/Exporter** | Import subscriptions from CSV | Free (GitHub beta) |
| **Jetpack CRM CSV Importer PRO** | Import transactions into CRM | Paid |
| **Redirection plugin** | Manage 301 redirects (Wix → WP) | Free |
| **WP-CLI** | Bulk import scripts for custom tables | Free |

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| PAN import denied by USAePay | Medium | Members must re-enter cards | Request early; if denied, fall back to multi-channel re-entry campaign (email + SMS + in-person) with incentive |
| Spark data dump is incomplete | Medium | Missing notes/SMS history | Request early; follow up with specifics; CoWork Playbook 5 as fallback |
| SEO ranking dip after Wix migration | Medium | Temporary traffic loss | Proper 301 redirects, keep Wix live 2-4 weeks, submit sitemap to Google |
| GHL phone number port fails | Low | SMS downtime | Port well before GHL cancellation; test thoroughly |
| Double-billing during parallel run | Medium | Member complaints | Coordinate Spark billing disable with WC billing start; reconcile daily |
| Pitbull Processing ETF | Medium | Unexpected cost | Review contract before initiating switch |
| Spark cancels access before export is complete | Low | Data loss | Export everything in Phase 1 before notifying Spark of cancellation |
