# M2 — GoHighLevel Replacement Implementation Plan

> Replace GoHighLevel CRM + automation + SMS + lead management with Jetpack CRM + AutomateWoo + MailPoet + Twilio. Estimated savings: ~$137/mo (GHL) + gains from better WooCommerce integration.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    WordPress Stack                       │
│                                                         │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │ Jetpack CRM  │  │ AutomateWoo  │  │   MailPoet    │  │
│  │ (contacts,   │  │ (behavioral  │  │ (broadcasts,  │  │
│  │  pipeline,   │←→│  triggers,   │←→│  newsletters, │  │
│  │  activity)   │  │  workflows)  │  │  templates)   │  │
│  └──────┬───────┘  └──────┬───────┘  └───────────────┘  │
│         │                 │                              │
│  ┌──────┴─────────────────┴──────────────────────────┐  │
│  │              WooCommerce Core                      │  │
│  │  (orders, subscriptions, memberships, customers)   │  │
│  └──────┬────────────────────────────────────────────┘  │
│         │                                               │
│  ┌──────┴───────┐  ┌──────────────┐                     │
│  │  gym-core    │  │ hma-ai-chat  │                     │
│  │ (SMS/Twilio, │  │ (Gandalf AI  │                     │
│  │  attendance, │←→│  agents use  │                     │
│  │  ranks)      │  │  gym/v1 API) │                     │
│  └──────────────┘  └──────────────┘                     │
└─────────────────────────────────────────────────────────┘
                         │
                    ┌────┴────┐
                    │ Twilio  │
                    │  (SMS)  │
                    └─────────┘
```

## Tool Responsibilities

| Function | GoHighLevel (current) | New Stack |
|----------|----------------------|-----------|
| Contact database | GHL Contacts | **Jetpack CRM** |
| Sales pipeline | GHL Pipelines | **Jetpack CRM** pipeline feature |
| Email automation | GHL Workflows | **AutomateWoo** (behavioral) + **MailPoet** (broadcasts) |
| SMS automation | GHL Workflows | **AutomateWoo** → **gym-core Twilio** (already built) |
| Newsletter/blasts | GHL Email Campaigns | **MailPoet** |
| Lead capture forms | GHL Forms | **Jetpack Forms** (M1.7) → CRM contact |
| Contact assignment | GHL Workflow Rules | **Jetpack CRM** ownership + AutomateWoo rules |
| Reporting | GHL Dashboard | **Jetpack CRM** activity log + WC Analytics |

## Implementation Sequence

### Phase 1: Foundation (2.1 + 2.3) — No dependencies, parallel
- Install Jetpack CRM + configure WooCommerce sync
- Install MailPoet + configure sending + create templates
- Both can happen independently

### Phase 2: Data Migration (2.2) — Depends on Phase 1
- Export GHL contacts
- Clean, deduplicate, categorize
- Import into Jetpack CRM with tags

### Phase 3: Automation (2.4 + 2.5) — Depends on Phase 1 + 2
- Wire Twilio SMS into Jetpack CRM (contact activity logging)
- Configure AutomateWoo workflows (all 7 sequences)
- Register custom AutomateWoo triggers from gym-core hooks

### Phase 4: Sales Pipeline (2.6) — Depends on Phase 2
- Configure pipeline stages in Jetpack CRM
- Set up lead assignment rules
- Connect contact form submissions to CRM

### Phase 5: Cutover (2.7) — Depends on all above
- Feature parity verification
- 30-day parallel run
- GHL decommission

---

## Task Details

### 2.1 — Jetpack CRM Installation + Configuration

**What to install:**
- Jetpack CRM core (free from WordPress.org)
- WooCommerce Connect extension (syncs WC customers → CRM contacts)
- Custom field definitions for gym-specific data

**Configuration script via WP-CLI:**
```
plugin install jetpack-crm --activate
# Enable WooCommerce integration module
# Configure custom fields
# Set up contact tags
# Configure user access roles
```

**Custom fields to add:**
| Field | Type | Source |
|-------|------|--------|
| Gym Location | Select (Rockford/Beloit) | Set on contact creation, synced from order |
| Primary Program | Select (Adult BJJ/Kids BJJ/Kickboxing) | Set manually or from product purchase |
| Belt Rank | Text | Synced from gym_ranks table via hook |
| Foundations Status | Select (Not enrolled/Phase 1/Phase 2/Phase 3/Cleared) | Synced from user meta |
| Member Since | Date | From first subscription order |
| Last Check-In | Date | Updated by attendance hook |

**Contact tags:**
- Status: `member`, `lead`, `lapsed`, `prospect`, `trial`
- Program: `bjj`, `kickboxing`, `kids-bjj`, `little-ninjas`
- Location: `rockford`, `beloit`
- Source: `website-form`, `phone-call`, `walk-in`, `referral`, `social`

**User access:**
| Person | Role | Scope |
|--------|------|-------|
| Darby | Admin | Full CRM access |
| Amanda | Admin | Full CRM access |
| Joy | Admin | Full CRM access (finance focus) |
| Matt Clark | Sales | Own contacts + Rockford leads only |
| Rachel | Sales | Own contacts + assigned leads only |

### 2.2 — GHL Contact Export + Cleanup + Import

**Export process:**
1. Export all contacts from GHL as CSV
2. Expected fields: name, email, phone, tags, source, created date, notes

**Cleanup script (Python, stdlib):**
1. Load CSV, normalize phone numbers (E.164)
2. Deduplicate by email (primary) and phone (secondary)
3. Remove junk: contacts with no email AND no phone, or tagged as spam
4. Categorize based on tags/activity:
   - Has active Spark membership → `member`
   - Had membership, now cancelled → `lapsed`
   - Inquired but never purchased → `lead`
   - Scheduled trial but didn't convert → `prospect`
   - Purchased trial → `trial`
5. Map to Jetpack CRM import format
6. Output: clean CSV + report (totals, duplicates removed, junk removed)

**Import:**
- Use Jetpack CRM's CSV import with field mapping
- Or WP-CLI script for programmatic import with tags

### 2.3 — MailPoet Setup + Email Templates

**Installation:**
```
plugin install mailpoet --activate
```

**Sending configuration:**
- Option A (free): Configure Amazon SES SMTP (~$0.10/1,000 emails)
- Option B (paid): MailPoet Sending Service (~$15/mo for <1,000 subscribers)
- Verify sending domain: haanpaamartialarts.com
- Add DNS records: SPF, DKIM, DMARC

**Email templates to create (8):**

| Template | Trigger | Content |
|----------|---------|---------|
| Welcome Series | New member signup | 3-part: welcome → what to expect → class reminder |
| Trial Follow-Up | Trial class completed | 3-part: thank you → offer → last chance |
| Renewal Reminder | 7 days before subscription renewal | Payment upcoming, update card if needed |
| Failed Payment | Subscription payment failed | Update payment method link, urgency |
| Lapsed Win-Back | 30/60/90 days inactive | We miss you, special offer to return |
| Schedule Change | Admin triggers manually | Class time/day changed notification |
| Belt Promotion | gym_core_rank_changed hook | Congratulations + next steps |
| Monthly Newsletter | Manual send by Amanda | Gym news, events, member spotlights |

**Segments to create:**
- All active members
- Active members by location (Rockford / Beloit)
- Active members by program (BJJ / Kickboxing / Kids)
- Lapsed members (no check-in 30+ days)
- Trial members (purchased trial, not yet converted)
- Leads (form submission, no purchase)

### 2.4 — Twilio SMS Wiring to CRM

**Already built:** TwilioClient, MessageTemplates (12 templates), InboundHandler, SMSController API

**Still needed:**
- Bridge between Jetpack CRM and gym-core SMS: when SMS is sent/received, log it as a CRM activity on the contact record
- AutomateWoo SMS action: register a custom AutomateWoo action that calls gym-core's TwilioClient
- CRM contact → phone number resolution: lookup Jetpack CRM contact phone for SMS sending

**New file:** `src/Integrations/CrmSmsBridge.php`
- Hooks into `gym_core_sms_sent` → creates CRM activity log entry
- Hooks into `gym_core_sms_received` → creates CRM activity log entry + matches phone to contact
- Provides `send_to_contact($contact_id, $template_slug, $variables)` method

### 2.5 — AutomateWoo Workflows

**Installation:**
```
plugin install automatewoo --activate
```

**Custom triggers to register from gym-core:**

| Trigger | Hook | Data |
|---------|------|------|
| Belt Promotion | `gym_core_rank_changed` | user, program, new belt, stripes |
| Foundations Cleared | `gym_core_foundations_cleared` | user, coach, status |
| Attendance Recorded | `gym_core_attendance_recorded` | user, class, location |
| Attendance Milestone | `gym_core_attendance_milestone` | user, count (25, 50, 100, etc.) |

**New file:** `src/Integrations/AutomateWooTriggers.php`
- Registers custom triggers extending `AutomateWoo\Trigger`
- Each trigger defines: title, description, supplied data items, when it fires

**Custom action to register:**

| Action | What it does |
|--------|-------------|
| Send SMS | Calls TwilioClient::send() with template + variables |

**New file:** `src/Integrations/AutomateWooSmsAction.php`
- Extends `AutomateWoo\Action`
- Template selector field, variable substitution, sends via TwilioClient

**7 workflows to configure (via WP-CLI or admin UI):**

1. **New Member Welcome**
   - Trigger: WC Subscription status → active (first time)
   - Day 0: Email (welcome template)
   - Day 1: SMS (lead_followup template customized for new member)
   - Day 3: Email (what to expect at your first class)
   - Day 7: Email (how to book your next class)

2. **Trial Class Follow-Up**
   - Trigger: Product purchased (trial product)
   - +1 hour: SMS (thanks for trying, how was it?)
   - Day 1: Email (trial follow-up template)
   - Day 3: SMS (ready to join? special offer)
   - Condition: Stop if subscription purchased

3. **Failed Payment Recovery**
   - Trigger: WC Subscription payment failed
   - Immediate: Email (payment failed template)
   - Day 1: SMS (payment_failed template)
   - Day 3: Email (second notice with update payment link)
   - Day 7: SMS (final notice before suspension)

4. **Lapsed Member Win-Back**
   - Trigger: No check-in for 30 days (custom trigger from attendance)
   - Day 30: Email (reengage template)
   - Day 45: SMS (reengage_30 template)
   - Day 60: Email (special offer to return)
   - Day 90: SMS (reengage_90 final template)

5. **Renewal Reminder**
   - Trigger: WC Subscription renewal in 7 days
   - -7 days: Email (renewal reminder)
   - -3 days: SMS (payment coming up, update card if needed)

6. **Birthday**
   - Trigger: Date match on birthday custom field
   - Day of: Email + SMS (birthday templates)

7. **Review Request**
   - Trigger: WC Subscription active for 30 days
   - Day 30: Email (how are you liking HMA? Leave a review)

### 2.6 — Lead Pipeline + Sales Tracking

**Pipeline stages in Jetpack CRM:**
1. New Lead (auto-created from form submission)
2. Contacted (sales rep made first contact)
3. Trial Scheduled (trial class booked)
4. Trial Completed (attended trial class)
5. Offer Made (membership pricing presented)
6. Closed Won (subscription purchased)
7. Closed Lost (declined or went silent)

**Lead source tracking via hidden form fields:**
- Website form → `source: website-form`
- Phone call (manual entry) → `source: phone-call`
- Walk-in (manual entry) → `source: walk-in`
- Referral → `source: referral`
- Social media → `source: social`

**Auto-assignment rules:**
- Rockford leads → Matt Clark
- Beloit leads → Rachel (or configurable)

**Form integration:**
- Jetpack Forms submission → hook into `grunion_after_message_sent` or form submission action
- Create Jetpack CRM contact with lead tag
- Create pipeline entry at "New Lead" stage
- Assign to sales rep based on location field

### 2.7 — GHL Decommission

**Feature parity checklist:**
- [ ] All contacts migrated and verified
- [ ] Email sequences replaced by AutomateWoo workflows
- [ ] SMS sequences replaced by AutomateWoo + Twilio
- [ ] Pipeline tracking active in Jetpack CRM
- [ ] Lead capture forms creating CRM contacts
- [ ] Reporting available (CRM dashboard + WC Analytics)
- [ ] Staff trained on new tools

**Cutover plan:**
1. Run both systems in parallel for 30 days
2. Disable GHL automations (but keep accounts active for reference)
3. Verify no leads or communications are being missed
4. Port any phone numbers from GHL to Twilio
5. Cancel GHL subscription
6. Document "where to find things" guide for staff

---

## Scripts to Build

| Script | Purpose |
|--------|---------|
| `scripts/setup_jetpack_crm.py` | Install + configure Jetpack CRM via Pressable WP-CLI |
| `scripts/setup_mailpoet.py` | Install + configure MailPoet via Pressable WP-CLI |
| `scripts/setup_automatewoo.py` | Install + configure AutomateWoo via Pressable WP-CLI |
| `scripts/clean_ghl_contacts.py` | Clean and deduplicate GHL CSV export |
| `scripts/import_contacts.py` | Import cleaned contacts into Jetpack CRM |

## Plugin Code to Build

| File | Purpose |
|------|---------|
| `src/Integrations/CrmSmsBridge.php` | Bridge gym-core SMS ↔ Jetpack CRM activity logging |
| `src/Integrations/AutomateWooTriggers.php` | Custom AW triggers from gym-core hooks |
| `src/Integrations/AutomateWooSmsAction.php` | Custom AW action to send SMS via Twilio |
| `src/Integrations/CrmContactSync.php` | Sync gym-core data (rank, attendance) to CRM fields |
| `src/Integrations/FormToCrm.php` | Convert form submissions to CRM contacts + pipeline |

---

## Open Questions

1. **Jetpack CRM extensions** — which paid extensions are needed? WooCommerce Connect may be free, but Automations and Mail Campaigns extensions have separate pricing. Need to verify.
2. **GHL data export** — has the CSV export from GHL been done? If not, Andrew needs to do this from the GHL admin.
3. **Twilio phone number** — does the gym already have a Twilio number, or does GHL own the SMS number? If GHL owns it, porting is needed.
4. **AutomateWoo licensing** — is it already purchased as part of the WooCommerce extensions bundle? Or separate purchase needed?
5. **Amazon SES** — does Andrew have an AWS account for SES, or should we use MailPoet Sending Service instead?
