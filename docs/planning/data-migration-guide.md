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

### 1. Saved Payment Methods Do NOT Transfer
USAePay card tokens are gateway-specific and cannot be migrated to Stripe/WooPayments. **Every member must re-enter their card.** This is a PCI-DSS requirement — there is no workaround.

**Mitigation plan:**
- Import subscriptions as **manual renewal** initially
- Send members an email campaign with a link to add their payment method on the new WooCommerce site
- Follow up with SMS (via Twilio) for members who haven't updated within 7 days
- Once card is on file, flip subscription from manual to automatic renewal
- Offer a small incentive (waived late fee, free class) for prompt updates

### 2. Membership Plan Details Don't Export from Spark
Spark has no export for plan structure (names, prices, billing cycles, terms). These must be manually documented before cancellation.

### 3. GHL Automations Don't Export
GoHighLevel workflows/automations cannot be exported as JSON/XML. They must be manually documented (screenshot each workflow) and rebuilt in AutomateWoo.

### 4. Wix Pages Don't Bulk Export
Static page content must be manually copied or scraped. Blog posts export as XML.

### 5. Passwords Don't Transfer
Members from Spark/GHL will need to set new passwords on the WordPress site. Send a "Welcome to the new site" email with a password reset link.

---

## Migration by Milestone

### Milestone 1: Billing Engine + Site Replacement

#### M1 Exports (do these BEFORE building)

| Source | Data | How to Export | When |
|--------|------|---------------|------|
| **Wix** | All page content (text, images) | Manual copy + download media from Wix media manager | Before M1.7 |
| **Wix** | Blog posts | Wix Settings > Blog > Export (XML) | Before M1.7 |
| **Wix** | All URLs + meta titles + descriptions | Crawl with Screaming Frog SEO Spider (free <500 URLs) | Before M1.7 |
| **Wix** | Form submissions | Wix Dashboard > Contacts > Forms > Export CSV | Before M1.7 |
| **Wix** | Contacts/members | Wix Dashboard > Contacts > Export CSV | Before M1.7 |
| **Spark** | Membership plan details | **Manual documentation** — screenshot every plan: name, price, billing cycle, signup fee, trial period | Before M1.4 |
| **Spark** | Member profiles | Members > All Members > Export as CSV | Before M1.4 |
| **USAePay** | Full transaction history | Merchant console > Reports > Transaction Report > Export CSV (set date range to "all") | Before M1.9 |
| **USAePay** | Customer database | Merchant console > Customer Database > Export CSV | Before M1.9 |
| **USAePay** | Recurring billing profiles | Merchant console > Recurring Billing > Export (document: customer, amount, frequency, next bill date, card last-4) | Before M1.9 |
| **USAePay** | Active subscription list | Cross-reference with Spark member list to build the subscription import CSV | Before M1.9 |
| **Pitbull** | Contract terms + ETF details | Review merchant services agreement; check early termination fees | Before M1.2 |

#### M1 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **WordPress** | Blog posts from Wix | WordPress Importer (native XML import) | M1.7 |
| **WordPress** | Page content | Manual recreation in block editor | M1.7 |
| **WordPress** | Media (images) | Upload to WordPress media library | M1.7 |
| **WordPress** | 301 redirects (Wix URL → WP URL) | Redirection plugin or Rank Math redirect manager; build map from Screaming Frog data | M1.7 |
| **WooCommerce** | Membership subscription products | Manual creation in WooCommerce admin (small number of products) | M1.4 |
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
| **GHL** | Automation workflows | **Manual documentation** — screenshot every workflow, document triggers + actions + delays + conditions | Before M2.5 |
| **GHL** | Forms | **Manual documentation** — screenshot form fields and settings | Before M2.7 |
| **GHL** | Pipeline stage definitions | **Manual documentation** — note stage names, order, and assignment rules | Before M2.6 |
| **GHL** | Phone numbers | Port to Twilio via GHL Support ticket (provide Twilio Account SID). Set up A2P 10DLC registration in Twilio FIRST. Timeline: 1-2 business days. | Before M2.4 |
| **Spark** | SMS/email send history | Request from `data@sparkmembership.com` (no self-service export) | Before M2.4 |
| **Spark** | Member notes | Request from `data@sparkmembership.com` or manually copy per-member | Before M2.2 |

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
| **Jetpack CRM** | Pipeline stages | Manual configuration in Jetpack CRM admin | M2.6 |
| **Jetpack CRM** | Pipeline deals | CSV import or programmatic creation | M2.6 |
| **Twilio** | Phone number(s) | Port from GHL/LeadConnector via support ticket | M2.4 |
| **AutomateWoo** | Workflow recreations | Manual rebuild from documented GHL automations | M2.5 |
| **MailPoet** | Email templates | Manual recreation (branded, mobile-responsive) | M2.3 |
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
| **Spark** | Class schedule structure | **Manual documentation** — class names, times, instructors, programs, locations, capacity | Before M3.3 |
| **Spark** | Content/page structure for member area | **Manual documentation** — what content exists behind Spark's member portal | Before M3.2 |

#### M3 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **WordPress users** | All members as WP users | "Import Users from CSV with Meta" plugin or WP-CLI script | M3.1 |
| **WooCommerce Memberships** | Membership plan assignments | Programmatic — link WP users to membership plans based on Spark membership tier | M3.1 |
| **WooCommerce Subscriptions** | Active recurring billing | CSV importer (`woocommerce-subscriptions-importer-exporter`) — see format below | M3.1 |
| **hma-core** | Class schedule data | Manual creation in `gym_class` CPT admin UI | M3.3 |

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
| **Spark** | Belt rank definitions (names, order, programs) | **Manual documentation** — get exact belt names for Kids BJJ (13 belts) from Darby | Before M4.1 |
| **Spark** | Promotion eligibility thresholds | **Manual documentation** — min attendance, min time at rank, per program | Before M4.3 |

#### M4 Imports

| Destination | Data | Method | Milestone Task |
|-------------|------|--------|----------------|
| **`{prefix}gym_ranks`** | Current rank per member | WP-CLI bulk import script (`wp hma import belt-ranks`) | M4.1 |
| **`{prefix}gym_rank_history`** | Promotion audit trail | WP-CLI bulk import script | M4.1 |
| **`{prefix}gym_attendance`** | Historical check-in records | WP-CLI bulk import script (`wp hma import attendance`) | M4.2 |
| **WordPress user meta** | Current belt rank (for fast lookups) | Set during belt rank import | M4.1 |

#### Bulk Import Script Architecture

```bash
# Recommended WP-CLI commands for hma-core
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
| **hma-core custom tables** | Any remaining Spark data (historical attendance, notes) | WP-CLI import scripts | M7.2 |

---

## Migration Timeline & Sequencing

```
PHASE 1: EXPORT EVERYTHING (do this first, before building anything)
├── Week 1: Export Spark (members, attendance, ranks, billing, notes)
├── Week 1: Export GHL (contacts, conversations, deals, document automations)
├── Week 1: Crawl Wix site (Screaming Frog) + export blog/contacts
├── Week 1: Export USAePay (transactions, customers, recurring profiles)
├── Week 1: Download Vimeo videos
├── Week 1: Contact data@sparkmembership.com for full data dump
└── Week 1: Review Pitbull Processing contract for ETF

PHASE 2: CLEAN & PREPARE (while building M1)
├── Week 2-3: Deduplicate + clean GHL contacts
├── Week 2-3: Merge Spark + GHL into single canonical contact list
├── Week 2-3: Normalize phone numbers (E.164), dates (UTC), locations
├── Week 2-3: Map Spark membership tiers → WooCommerce product IDs
├── Week 2-3: Build Wix URL → WordPress URL redirect map
├── Week 2-3: Document all GHL automations for AutomateWoo rebuild
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
| Saved credit cards / ACH payment methods | PCI-DSS: tokens are gateway-specific, non-transferable | Members re-enter payment info on new site |
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
- [ ] Membership plan details manually documented
- [ ] POS product catalog manually documented
- [ ] Member notes captured (from data dump or manual copy)
- [ ] SMS send history captured
- [ ] Class schedule structure documented
- [ ] Belt rank definitions documented (including Kids BJJ 13 belts — confirm with Darby)
- [ ] Promotion thresholds documented

### GoHighLevel
- [ ] Contacts CSV exported
- [ ] Opportunities/deals CSV exported
- [ ] SMS/email conversation history exported (via "Export Conversations" app)
- [ ] Call recordings downloaded (critical ones)
- [ ] All automation workflows documented (screenshots + trigger/action notes)
- [ ] Forms documented
- [ ] Pipeline stages documented
- [ ] Phone number(s) ported to Twilio (confirmed working)
- [ ] A2P 10DLC registered in Twilio
- [ ] 30-day parallel run completed

### Wix
- [ ] Blog posts exported as XML
- [ ] All page content manually copied
- [ ] All media/images downloaded
- [ ] Form submissions exported as CSV
- [ ] Contacts exported as CSV
- [ ] Full URL crawl completed (Screaming Frog)
- [ ] 301 redirects implemented and tested
- [ ] DNS cutover completed
- [ ] New WordPress site live and verified

### USAePay / Pitbull Processing
- [ ] Full transaction history CSV exported
- [ ] Customer database CSV exported
- [ ] Recurring billing profiles documented
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
| Members don't update payment info promptly | High | Revenue gap | Multi-channel campaign (email + SMS + in-person), grace period, incentive |
| Spark data dump is incomplete | Medium | Missing notes/SMS history | Request early; follow up with specifics; manual capture as fallback |
| SEO ranking dip after Wix migration | Medium | Temporary traffic loss | Proper 301 redirects, keep Wix live 2-4 weeks, submit sitemap to Google |
| GHL phone number port fails | Low | SMS downtime | Port well before GHL cancellation; test thoroughly |
| Double-billing during parallel run | Medium | Member complaints | Coordinate Spark billing disable with WC billing start; reconcile daily |
| Pitbull Processing ETF | Medium | Unexpected cost | Review contract before initiating switch |
| Spark cancels access before export is complete | Low | Data loss | Export everything in Phase 1 before notifying Spark of cancellation |
