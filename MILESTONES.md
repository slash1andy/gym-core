# Haanpaa Martial Arts — Tech Stack Build Plan

**tl;dr** — Seven milestones to migrate Haanpaa from the current fragmented stack (Spark + Wix + GHL + USAePay) onto a consolidated WordPress 7.0 / WooCommerce 10.3 ecosystem. Two custom plugins: `gym-core` (membership engine, belt tracking, check-in, gamification) and `gym-ai-chat` (already scaffolded). Milestone 1 gets revenue flowing through WooPayments and kills the Wix site. Milestone 2 replaces GoHighLevel entirely. Everything after that builds the member-facing and operational layers in dependency order.

**Platform:** WordPress 7.0 beta · WooCommerce 10.3 beta · PHP 8.0+ · Pressable hosting
**Custom Plugins:** `gym-core` (new) · `gym-ai-chat` (existing, v0.1.0)
**Commercial Extensions:** WooPayments · WooCommerce Subscriptions · WooCommerce Memberships · Jetpack CRM · AutomateWoo · MailPoet · Jetpack VideoPress

**REST API Namespace:** `gym/v1`
**Base Controller:** `src/API/BaseController.php` (extends `WP_REST_Controller`)
**Foundation Controllers (built in M1):** `src/API/LocationController.php`

---

## Milestone 1: Billing Engine + Site Replacement

**Goal:** New memberships and retail sales flow through WooCommerce + WooPayments. The Wix site ($74/mo) is decommissioned. This is the revenue foundation everything else builds on.

**Replaces:** Wix website · USAePay/Pitbull Processing (~3.23% blended rate)
**Estimated savings:** ~$74/mo (Wix) + ~$47/mo (processing delta) = ~$121/mo

### For Andrew (what gets done)

The new haanpaamartialarts.com goes live on Pressable running WordPress 7.0 and WooCommerce 10.3. WooPayments handles all card processing (lower blended rate than USAePay/Pitbull). WooCommerce Subscriptions powers recurring membership billing. The block-based checkout is configured for membership signups and retail (gear, apparel). The site design is clean, mobile-first, and martial-arts-appropriate — schedule pages, program descriptions, pricing, trial class signup, and a basic contact form. No member portal yet (that's M3), but new members can purchase memberships and their billing recurs automatically.

### Agent Task List

#### 1.1 — Environment Setup
```
STATUS: COMPLETE
DESCRIPTION: Provision and configure the WordPress 7.0 + WooCommerce 10.3 development environment.
DEPENDS_ON: None
ACCEPTANCE:
  - WordPress 7.0 beta installed on Pressable staging
  - WooCommerce 10.3 beta installed and activated
  - PHP 8.0+ confirmed
  - SSL certificate active
  - HPOS enabled (WooCommerce > Settings > Advanced > Features)
  - Block-based Cart and Checkout enabled
  - Permalink structure set to /%postname%/
  - Debug logging enabled for staging (WP_DEBUG + WP_DEBUG_LOG)
```

#### 1.2 — WooPayments Configuration
```
STATUS: COMPLETE
DESCRIPTION: Install and configure WooPayments as the sole payment gateway.
DEPENDS_ON: 1.1
ACCEPTANCE:
  - WooPayments installed, activated, onboarding completed
  - Stripe account connected (Haanpaa's Stripe account)
  - Live mode disabled during staging (test mode active)
  - Card payments enabled
  - Apple Pay / Google Pay enabled
  - Saved payment methods enabled (required for Subscriptions)
  - Currency: USD
  - Fraud protection rules configured
  - Webhook endpoint verified
NOTE: Current blended rate is ~3.23% via USAePay/Pitbull with ~$147.76 avg ticket.
      WooPayments rate expected to be lower. Document actual rate for comparison.
```

#### 1.3 — WooCommerce Subscriptions Setup
```
STATUS: COMPLETE
DESCRIPTION: Install and configure WooCommerce Subscriptions for recurring membership billing.
DEPENDS_ON: 1.2
ACCEPTANCE:
  - WooCommerce Subscriptions installed and activated
  - Subscription renewal payment method: WooPayments automatic
  - Retry rules configured (failed payment retry schedule)
  - Subscription switching enabled (upgrade/downgrade between tiers)
  - Early renewal disabled (prevents billing confusion)
  - Synchronize renewals: enabled, anchor to 1st of month
  - Drip content: disabled (M3 handles this via WooCommerce Memberships)
```

#### 1.4 — Membership Product Configuration
```
STATUS: COMPLETE
DESCRIPTION: Create subscription products for each membership tier at both locations.
DEPENDS_ON: 1.3
ACCEPTANCE:
  - Products created for each active membership tier:
    - Adult BJJ (Rockford)
    - Adult BJJ (Beloit)
    - Kids BJJ (Rockford)
    - Kids BJJ (Beloit)
    - Kickboxing (Rockford)
    - Kickboxing (Beloit)
    - Family plans (if applicable — confirm with Darby)
    - Drop-in / trial class (one-time purchase, not subscription)
  - Each product has: name, description, monthly price, signup fee (if any),
    free trial period (if any), product image
  - Location stored as product attribute or taxonomy term (for filtering)
  - Variable subscriptions used where tiers have multiple pricing options
  - Products assigned to a "Memberships" category
OPEN_QUESTION: Get current pricing tiers from Spark. Darby to provide.
```

#### 1.5 — gym-core Plugin Scaffold
```
STATUS: COMPLETE
DESCRIPTION: Scaffold the gym-core custom plugin that will hold all gym-specific
             WooCommerce extensions across all milestones.
DEPENDS_ON: 1.1
SKILL: woocommerce-plugin-starting
ACCEPTANCE:
  - Plugin slug: gym-core
  - Plugin name: Gym Core
  - Namespace: Gym_Core
  - Text domain: gym-core
  - File structure per woocommerce-plugin-dev skill
  - HPOS compatibility declared
  - Cart & Checkout Blocks compatibility declared
  - Requires WP 7.0, WC 10.3, PHP 8.0
  - Composer autoloading (PSR-4)
  - phpcs.xml.dist, phpstan.neon, .eslintrc.json configured
  - Activation/deactivation/uninstall handlers
  - PROJECT_BRIEF.md in plugin root
OUTPUT_PATH: /Gym Revamp/gym-core/
```

#### 1.6 — Multi-Location Architecture (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the multi-location taxonomy and logic into gym-core.
             This is foundational — almost everything downstream filters by location.
DEPENDS_ON: 1.5
ACCEPTANCE:
  - Custom taxonomy: gym_location (registered on products, orders, users)
  - Terms: "rockford", "beloit"
  - Location selector on frontend (cookie + user meta persistence)
  - WooCommerce product filtering by location
  - Order meta records which location the purchase is associated with
  - Admin can filter orders/products by location
  - Store API extended to include location context in cart/checkout
  - Unit tests for location assignment and filtering
```

#### 1.7 — Site Design + Content
```
STATUS: COMPLETE
DESCRIPTION: Design and build the public-facing site that replaces Wix.
DEPENDS_ON: 1.1, 1.4
ACCEPTANCE:
  - Block theme (flavor TBD — likely flavor of flavor like flavor flavor flavor flavor)
  - Actually: use a WooCommerce-compatible block theme (flavor TBD — Flavor flavor)
  - OK let me be specific: Use a clean block theme compatible with WooCommerce 10.3
    and the Site Editor. Flavor suggestions: flavor flavor flavor
  - ACTUALLY: Pick a WooCommerce-compatible FSE block theme. Flavor flavor is fine.
  - Pages required:
    - Home (hero, programs overview, CTAs, testimonials)
    - Programs (Adult BJJ, Kids BJJ, Kickboxing — each with description, schedule, CTA)
    - Schedule (embedded or block-based class schedule — both locations)
    - Pricing (membership tiers, trial class, comparison)
    - About (gym story, coaches, facility photos)
    - Contact (form, map with both locations, hours)
    - Trial Class signup (linked to drop-in product)
    - Shop (membership products + retail if applicable)
    - Cart, Checkout, My Account (WooCommerce defaults, block-based)
  - Mobile-first responsive design
  - SEO basics: meta descriptions, OG tags, XML sitemap
  - Google Analytics / Jetpack Stats configured
  - Contact form: Jetpack Forms or similar (no GHL dependency)
NOTE: Content (copy, photos, testimonials) needs to come from Darby/Amanda.
      Placeholder content acceptable for staging.
```

#### 1.8 — Checkout Flow Testing
```
STATUS: READY
DESCRIPTION: End-to-end testing of the membership purchase and subscription flow.
DEPENDS_ON: 1.2, 1.3, 1.4, 1.7
ACCEPTANCE:
  - New customer can browse programs → select membership → checkout → pay
  - Subscription created with correct billing schedule
  - Renewal payment processes automatically (test with WooPayments test mode)
  - Failed payment retry works correctly
  - Subscription cancellation works from My Account
  - Subscription upgrade/downgrade between tiers works
  - Email notifications fire: new order, subscription renewal, failed payment,
    subscription cancelled
  - Checkout works on mobile
  - All prices include correct tax calculation (if applicable)
  - Drop-in / trial class purchase works as one-time payment
SKILL: woocommerce-testing
```

#### 1.9 — DNS + Go-Live Cutover Plan
```
STATUS: BLOCKED
DESCRIPTION: Plan the DNS cutover from Wix to Pressable and document the rollback plan.
DEPENDS_ON: 1.8
ACCEPTANCE:
  - DNS migration plan documented (current registrar, TTL strategy)
  - Wix → Pressable cutover checklist written
  - Rollback plan documented (point DNS back to Wix if critical failure)
  - SSL verified on new host
  - 301 redirects for any Wix URLs that have external links/SEO value
  - Email delivery verified (transactional emails from WordPress)
  - WooPayments switched from test mode to live mode
  - Go/no-go checklist for Darby's sign-off
NOTE: Do NOT cut over until Darby and Amanda have reviewed staging and signed off.
```

#### 1.10 — REST API Foundation (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the base REST API controller and the LocationController that all
             downstream milestones depend on. Namespace: gym/v1.
DEPENDS_ON: 1.5, 1.6
ACCEPTANCE:
  - src/API/BaseController.php created:
    - Extends WP_REST_Controller
    - Provides shared helpers: permission checks, pagination, sanitization,
      error response formatting, nonce verification
    - Standard JSON envelope: { success, data, meta }
    - Rate-limiting helper (X-RateLimit headers)
  - src/API/LocationController.php created:
    - Extends Gym_Core\API\BaseController
    - Endpoints:
      | Method | Route                             | Description                                | Auth         |
      |--------|-----------------------------------|--------------------------------------------|--------------|
      | GET    | /gym/v1/locations                 | List all gym locations                     | Public       |
      | GET    | /gym/v1/locations/{slug}          | Single location details                    | Public       |
      | GET    | /gym/v1/locations/{slug}/products | Products filtered to a location            | Public       |
      | GET    | /gym/v1/user/location             | Current user's preferred location          | Logged-in    |
      | PUT    | /gym/v1/user/location             | Set current user's preferred location      | Logged-in    |
  - All endpoints registered via register_rest_routes()
  - Permission callbacks enforce auth requirements
  - Schema defined per WP REST API spec (get_item_schema)
  - Unit tests for each endpoint
  - Integration test: location CRUD round-trip
SCHEMA_NOTES:
  Location object: { slug, name, address, phone, hours, coordinates }
  User location: { slug, name, set_at }
```

---

## Milestone 2: Replace GoHighLevel

**Goal:** GoHighLevel ($137/mo) is fully decommissioned. All CRM, SMS, email automation, and lead management functions run on the WordPress stack.

**Replaces:** GoHighLevel CRM + automation + SMS + lead management
**Estimated savings:** ~$137/mo

### For Andrew (what gets done)

Jetpack CRM becomes the single source of truth for all contacts — members, leads, prospects, and lapsed students. GHL's contacts get cleaned up (every inbound caller was added, creating a mess) and imported. AutomateWoo handles triggered workflows (welcome sequences, win-back campaigns, renewal reminders). MailPoet sends all email marketing. Twilio handles SMS — the killer feature — deeply integrated into the CRM for lead nurture, retention reminders, and schedule change notifications. Sales pipeline tracking moves into Jetpack CRM.

### Agent Task List

#### 2.1 — Jetpack CRM Installation + Configuration
```
STATUS: NOT STARTED
DESCRIPTION: Install and configure Jetpack CRM as the primary CRM.
DEPENDS_ON: M1 complete
ACCEPTANCE:
  - Jetpack CRM installed and activated
  - WooCommerce integration enabled (syncs customers from WC orders)
  - Contact fields mapped: name, email, phone, location (Rockford/Beloit),
    membership type, signup date, belt rank (custom field)
  - Tags taxonomy configured: lead, member, lapsed, prospect, trial
  - Contact ownership: assign contacts to staff members
  - User roles: Darby + Amanda + Joy = full access,
    Matt Clark = scoped (sales contacts only),
    Rachel = scoped (sales contacts only)
  - Company/Organization support disabled (B2C use case)
```

#### 2.2 — GHL Contact Export + Cleanup + Import
```
STATUS: NOT STARTED
DESCRIPTION: Export all contacts from GoHighLevel, clean duplicates/junk, and import.
DEPENDS_ON: 2.1
ACCEPTANCE:
  - Full contact export from GHL (CSV)
  - Deduplication pass (phone + email matching)
  - Remove junk contacts (every inbound caller was auto-added)
  - Categorize: active member, lead, lapsed, prospect
  - Map fields to Jetpack CRM schema
  - Import into Jetpack CRM with correct tags and location
  - Spot-check 20 contacts post-import for data accuracy
  - Document import count: total exported, duplicates removed, junk removed, imported
NOTE: GHL contacts are known to be disorganized. Budget time for cleanup.
      This is an opportunity to start clean, not just a migration.
```

#### 2.3 — MailPoet Setup + Email Templates
```
STATUS: NOT STARTED
DESCRIPTION: Install MailPoet and create email templates for all automated workflows.
DEPENDS_ON: 2.1
ACCEPTANCE:
  - MailPoet installed and activated
  - Sending domain verified (haanpaamartialarts.com)
  - DKIM/SPF/DMARC configured
  - Email templates created (branded, mobile-responsive):
    - Welcome series (new member)
    - Trial class follow-up
    - Membership renewal reminder
    - Failed payment notification
    - Lapsed member win-back
    - Schedule change notification
    - Belt promotion congratulations
    - Monthly newsletter template
  - Unsubscribe handling compliant with CAN-SPAM
  - List segmentation: by location, membership type, status
```

#### 2.4 — Twilio SMS Integration (gym-core)
```
STATUS: IN PROGRESS
DESCRIPTION: Build Twilio SMS integration into gym-core, deeply wired into Jetpack CRM
             and AutomateWoo. This is the "killer feature" of the consolidated stack.
DEPENDS_ON: 1.5, 2.1
ACCEPTANCE:
  - Twilio account configured (API credentials stored in wp_options, encrypted)
  - gym-core module: src/SMS/TwilioClient.php — handles send/receive
  - gym-core module: src/SMS/MessageTemplates.php — templated messages
  - gym-core module: src/SMS/InboundHandler.php — webhook for incoming SMS
  - Two-way SMS: staff can send from CRM, member replies come back
  - SMS templates:
    - Lead follow-up (automated after trial class inquiry)
    - Appointment/class reminder (24hr before)
    - Schedule change notification
    - Payment failed reminder
    - Belt promotion notification
    - Birthday/milestone message
    - Re-engagement (lapsed 30/60/90 days)
  - Opt-in/opt-out compliance (TCPA)
  - SMS conversation history stored on CRM contact record
  - Rate limiting to prevent accidental mass-send
  - Sending costs tracked and logged
  - REST API endpoint for sending SMS (internal use by AI agents)
  - Unit tests for message templating and send logic
  - Integration tests for Twilio webhook handling
SECURITY:
  - Twilio credentials: stored with encryption, never exposed in REST responses
  - Inbound webhook: validated via Twilio request signature
  - Phone numbers: sanitized and validated before storage
  - Rate limit: max 1 SMS per contact per hour (configurable)
```

#### 2.5 — AutomateWoo Workflow Configuration
```
STATUS: NOT STARTED
DESCRIPTION: Install AutomateWoo and configure all automation workflows that replace GHL sequences.
DEPENDS_ON: 2.3, 2.4
ACCEPTANCE:
  - AutomateWoo installed and activated
  - Workflows configured:
    - New member welcome sequence (email day 0, SMS day 1, email day 3, email day 7)
    - Trial class follow-up (SMS 1hr after, email day 1, SMS day 3 if no conversion)
    - Failed payment recovery (email immediate, SMS day 1, email day 3, final SMS day 7)
    - Lapsed member win-back (email day 30, SMS day 45, email day 60, final offer day 90)
    - Subscription renewal reminder (email 7 days before, SMS 3 days before)
    - Birthday automation (email + SMS on birthday)
    - Review request (email 30 days after signup)
  - All workflows segmented by location (Rockford/Beloit)
  - Workflow analytics: open rates, click rates, conversion tracking
  - Staff can pause/resume workflows per contact
```

#### 2.6 — Lead Pipeline + Sales Tracking
```
STATUS: NOT STARTED
DESCRIPTION: Configure Jetpack CRM sales pipeline to replace GHL's pipeline management.
DEPENDS_ON: 2.1, 2.2
ACCEPTANCE:
  - Pipeline stages defined: New Lead → Contacted → Trial Scheduled → Trial Completed →
    Offer Made → Closed Won → Closed Lost
  - Pipeline dashboard visible to sales staff (Matt, Rachel)
  - Lead source tracking: website form, phone call, walk-in, referral, social
  - Activity logging: calls, emails, SMS, notes
  - Lead assignment rules: by location
  - Weekly pipeline report (automated or manual trigger)
  - Integration with contact form (M1.7) — new submissions create CRM contacts + pipeline entry
```

#### 2.7 — GHL Decommission Checklist
```
STATUS: NOT STARTED
DESCRIPTION: Verify all GHL functions are replaced, then document the decommission plan.
DEPENDS_ON: 2.1 through 2.6
ACCEPTANCE:
  - Feature parity checklist: every GHL workflow has a WordPress equivalent
  - No active GHL automations still running
  - All contact data migrated and verified
  - Phone numbers ported to Twilio (if GHL owned any numbers)
  - GHL subscription cancellation scheduled (after 30-day parallel run)
  - Staff trained on new tools (Jetpack CRM, AutomateWoo dashboards)
```

#### 2.10 — SMS REST API Controller (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the SMSController exposing REST endpoints for sending SMS,
             receiving Twilio webhooks, and retrieving conversation history.
             These endpoints are consumed by AutomateWoo triggers, the AI Sales
             agent (M6), and the staff CRM interface.
DEPENDS_ON: 2.4, 1.10
CONTROLLER: src/API/SMSController.php (extends Gym_Core\API\BaseController)
ACCEPTANCE:
  - Endpoints registered under gym/v1 namespace:
    | Method | Route                                  | Description                                       | Auth                          |
    |--------|----------------------------------------|---------------------------------------------------|-------------------------------|
    | POST   | /gym/v1/sms/send                       | Send an SMS to a contact                          | manage_options OR gym_send_sms |
    | POST   | /gym/v1/sms/webhook                    | Twilio inbound webhook (receives incoming SMS)    | Twilio signature validation   |
    | GET    | /gym/v1/sms/conversations/{contact_id} | Retrieve SMS conversation history for a contact   | manage_options OR gym_send_sms |
  - POST /sms/send parameters:
    - contact_id (int, required) — Jetpack CRM contact ID
    - message (string, required) — message body (max 1600 chars)
    - template_slug (string, optional) — use a predefined MessageTemplate
  - POST /sms/webhook:
    - Validates X-Twilio-Signature header against configured auth token
    - Parses From, To, Body from Twilio POST payload
    - Matches inbound phone to CRM contact, stores message
    - Fires action hook: gym_core_sms_received
    - Returns TwiML response
  - GET /sms/conversations/{contact_id}:
    - Paginated (per_page, page params)
    - Returns array of { direction, body, sent_at, status, sid }
    - Sorted newest-first by default (order param available)
  - Rate limiting enforced on POST /sms/send (max 1 per contact per hour)
  - All endpoints return standard JSON envelope from BaseController
  - Permission callbacks use current_user_can() checks
  - Schema defined per WP REST API spec
  - Unit tests for each endpoint
  - Integration test: send → webhook round-trip
SCHEMA_NOTES:
  SMS message object: { id, contact_id, direction (inbound|outbound), body,
    sent_at, status (queued|sent|delivered|failed), twilio_sid }
  Send request: { contact_id, message, template_slug? }
  Webhook payload: Twilio standard POST fields (validated server-side)
SECURITY:
  - Twilio credentials never returned in any REST response
  - Webhook endpoint validates Twilio request signature; rejects invalid requests with 403
  - contact_id validated against CRM — cannot send to arbitrary phone numbers
  - Message body sanitized with wp_kses() before storage
```

---

## Milestone 3: Member Portal + Content Gating

**Goal:** Members log in and see a personalized dashboard — their membership status, upcoming classes, and gated content. This is the first step toward the member experience that differentiates Haanpaa from competitors.

**Depends on:** M1 (billing), M2 (CRM for contact data enrichment)

### For Andrew (what gets done)

WooCommerce Memberships gates content and products by membership tier. The My Account page gets a custom dashboard that shows membership status, billing info, upcoming classes, and a personalized welcome. Class schedule becomes interactive — members can see which classes they're eligible for based on their membership and location. Content like technique videos and training resources becomes gated by membership level.

### Agent Task List

#### 3.1 — WooCommerce Memberships Setup
```
STATUS: NOT STARTED
DESCRIPTION: Install WooCommerce Memberships and connect to Subscription products.
DEPENDS_ON: M1 complete
ACCEPTANCE:
  - WooCommerce Memberships installed and activated
  - Membership plans created (linked to Subscription products from M1.4):
    - Adult BJJ Member
    - Kids BJJ Member
    - Kickboxing Member
    - All-Access Member (if applicable)
  - Content restriction rules configured
  - Membership-gated product visibility working
  - Drip content schedule configurable per plan
  - Members auto-enrolled on subscription purchase
  - Members lose access on subscription cancellation/expiry
```

#### 3.2 — Custom Member Dashboard (gym-core)
```
STATUS: NOT STARTED
DESCRIPTION: Build a custom My Account dashboard that replaces the default WooCommerce one.
DEPENDS_ON: 3.1, 1.5
ACCEPTANCE:
  - Custom My Account endpoint: /my-account/dashboard/
  - Dashboard shows:
    - Welcome message with member's name
    - Active membership(s) with status badge
    - Next billing date and amount
    - Quick links: update payment method, view billing history
    - Location badge (Rockford or Beloit)
    - Class schedule for their location (next 7 days)
  - Built as WooCommerce block-compatible templates
  - Mobile-responsive
  - Accessible (WCAG 2.1 AA)
  - Hooks for M4 (belt rank display) and M5 (gamification widgets)
```

#### 3.3 — Class Schedule System (gym-core)
```
STATUS: IN PROGRESS
DESCRIPTION: Build a class schedule management system into gym-core.
DEPENDS_ON: 1.6
ACCEPTANCE:
  - Custom post type: gym_class (name, description, instructor, program, time, location, capacity)
  - Recurring schedule support (e.g., "Adult BJJ - Mon/Wed/Fri 6pm")
  - Schedule display block (filterable by location and program)
  - Schedule visible on public site (M1.7 page)
  - Enhanced schedule on member dashboard (shows eligibility)
  - Admin UI for managing schedule (add/edit/cancel classes)
  - Schedule change triggers notification (SMS + email via M2.4/M2.5)
  - iCal feed for members to subscribe
  - REST API endpoints for schedule data (consumed by AI agents)
```

#### 3.10 — Class Schedule REST API Controller (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the ClassScheduleController exposing REST endpoints for
             retrieving class definitions and weekly schedule views. These
             endpoints power the public schedule page, member dashboard,
             and are consumed by the AI Coaching agent (M6).
DEPENDS_ON: 3.3, 1.10
CONTROLLER: src/API/ClassScheduleController.php (extends Gym_Core\API\BaseController)
ACCEPTANCE:
  - Endpoints registered under gym/v1 namespace:
    | Method | Route                  | Description                                          | Auth       |
    |--------|------------------------|------------------------------------------------------|------------|
    | GET    | /gym/v1/classes        | List all class definitions (filterable)              | Public     |
    | GET    | /gym/v1/classes/{id}   | Single class definition with full details            | Public     |
    | GET    | /gym/v1/schedule       | Weekly schedule view (classes expanded by recurrence)| Public     |
  - GET /classes query parameters:
    - location (string, optional) — filter by gym_location slug
    - program (string, optional) — filter by program (bjj, kickboxing, kids-bjj)
    - instructor (int, optional) — filter by instructor user ID
    - per_page, page — pagination
  - GET /classes/{id}:
    - Returns full gym_class post data: name, description, instructor (with name),
      program, recurring_schedule, location, capacity, next_occurrence
  - GET /schedule query parameters:
    - location (string, required) — gym_location slug
    - week_of (string, optional, ISO 8601 date) — defaults to current week
    - program (string, optional) — filter by program
  - GET /schedule returns:
    - Array of day objects, each containing scheduled class instances:
      { date, day_name, classes: [{ id, name, program, instructor, start_time,
        end_time, location, spots_remaining }] }
  - All endpoints return standard JSON envelope from BaseController
  - Schema defined per WP REST API spec
  - Unit tests for each endpoint
  - Integration test: class created → appears in schedule response
SCHEMA_NOTES:
  Class object: { id, name, description, program, instructor: { id, name },
    location: { slug, name }, capacity, recurring_schedule: [{ day, start_time,
    end_time }] }
  Schedule day: { date (Y-m-d), day_name, classes: [ ClassInstance ] }
  ClassInstance: { class_id, name, program, instructor, start_time, end_time,
    location, spots_remaining }
```

#### 3.11 — Member Dashboard REST API Controller (gym-core)
```
STATUS: NOT STARTED
DESCRIPTION: Build the MemberController exposing the /members/me/dashboard endpoint
             that aggregates membership status, billing info, schedule, and
             personalization data into a single response for the My Account dashboard.
DEPENDS_ON: 3.2, 1.10
CONTROLLER: src/API/MemberController.php (extends Gym_Core\API\BaseController)
ACCEPTANCE:
  - Endpoints registered under gym/v1 namespace:
    | Method | Route                          | Description                                           | Auth      |
    |--------|--------------------------------|-------------------------------------------------------|-----------|
    | GET    | /gym/v1/members/me/dashboard   | Aggregated dashboard data for the current logged-in member | Logged-in |
  - GET /members/me/dashboard returns:
    - member: { id, display_name, email, location: { slug, name } }
    - memberships: [{ plan_name, status, start_date, end_date }]
    - billing: { next_payment_date, next_payment_amount, payment_method_summary }
    - upcoming_classes: array of next 7 days of classes at member's location
      (reuses ClassScheduleController data format)
    - rank: { program, belt, stripes, last_promoted_at } (null until M4 populates)
    - gamification: { current_streak, badges_earned_count } (null until M5 populates)
    - quick_links: { update_payment_url, billing_history_url, schedule_url }
  - Response designed for a single fetch on dashboard load (avoids waterfall requests)
  - rank and gamification fields return null gracefully when M4/M5 not yet active
  - Logged-in user determined via get_current_user_id(); returns 401 if not authenticated
  - Schema defined per WP REST API spec
  - Unit tests for dashboard aggregation
  - Integration test: member with active subscription sees correct dashboard data
SCHEMA_NOTES:
  Dashboard object: { member, memberships[], billing, upcoming_classes[],
    rank (nullable), gamification (nullable), quick_links }
  Designed to be extended by M4 and M5 without breaking changes — rank and
  gamification are nullable objects that get populated when those milestones ship.
```

---

## Milestone 4: Belt Rank + Attendance Tracking

**Goal:** The core operational systems that Spark Membership currently handles — belt/rank progression and attendance check-in — are rebuilt natively in WordPress.

**Depends on:** M3 (member portal for displaying progress)
**Replaces:** Spark Membership attendance + rank tracking functions

### For Andrew (what gets done)

Every member has a rank record: which belt, how many stripes, attendance count, and eligibility for next promotion. The tablet kiosk at each location lets members check in by scanning a QR code or entering their member ID. Check-in data feeds into belt promotion eligibility. Coaches can view attendance reports and promotion candidates. The entire belt rank system — Adult BJJ (5 belts, 4 stripes each), Kids BJJ (13 belts, 4 stripes each), and Kickboxing (2 levels) — is modeled in the database.

### Agent Task List

#### 4.1 — Belt Rank Data Model (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Design and implement the belt rank system as custom tables in gym-core.
DEPENDS_ON: 1.5
ACCEPTANCE:
  - Custom tables:
    - {prefix}gym_ranks (id, user_id, program, belt, stripes, promoted_at, promoted_by)
    - {prefix}gym_rank_history (id, user_id, from_belt, from_stripes, to_belt, to_stripes,
                                 promoted_at, promoted_by, notes)
  - Belt definitions:
    - Adult BJJ: White → Blue → Purple → Brown → Black (4 stripes each)
    - Kids BJJ: 13 belt levels (4 stripes each) — get exact belt names from Darby
    - Kickboxing: Level 1, Level 2
  - Rank CRUD class: src/Data/RankStore.php
  - Rank displayed on member dashboard (M3.2 hook)
  - Rank displayed in admin user profile
  - Rank change fires action hook: gym_core_rank_changed
  - Rank change triggers notification (SMS + email congratulations)
  - Coach role can promote students (custom capability: gym_promote_student)
  - Full rank history preserved (never deleted, audit trail)
  - REST API endpoints for rank data
  - Unit tests for rank progression logic
  - Integration tests for promotion flow
```

#### 4.2 — Attendance Check-in System (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the attendance tracking and tablet kiosk check-in system.
DEPENDS_ON: 4.1, 3.3
ACCEPTANCE:
  - Custom table: {prefix}gym_attendance (id, user_id, class_id, location, checked_in_at, method)
  - Check-in methods: QR code scan, member ID entry, name search
  - Kiosk mode: /check-in/ endpoint with simplified UI
    - Large touch-friendly buttons
    - QR code scanner (camera access)
    - Member search with autocomplete
    - Success confirmation with member name + rank display
    - Auto-logout after 10 seconds of inactivity
  - Check-in validation:
    - Member must have active membership
    - Member must be eligible for this class (by program + location)
    - Duplicate check-in prevention (same class, same day)
  - Check-in fires action hook: gym_core_attendance_recorded
  - Attendance dashboard for coaches:
    - Today's attendance by class
    - Member attendance history
    - Attendance trends (weekly/monthly)
  - Attendance count feeds into belt promotion eligibility
  - REST API endpoints for check-in (consumed by kiosk + AI agents)
  - Performance: check-in-to-confirmation under 500ms
```

#### 4.3 — Promotion Eligibility Engine (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the logic that determines when a student is eligible for belt promotion.
DEPENDS_ON: 4.1, 4.2
ACCEPTANCE:
  - Eligibility rules (configurable per program):
    - Minimum attendance count since last promotion
    - Minimum time at current rank
    - Coach recommendation flag
  - Eligibility dashboard for coaches:
    - List of students approaching eligibility
    - One-click promotion with notes field
  - Promotion workflow:
    1. Student meets attendance threshold → appears on eligibility list
    2. Coach reviews and recommends → sets recommendation flag
    3. Head instructor (Darby) approves → promotion recorded
    4. Notification fires (SMS + email congratulations)
    5. Rank updated on member dashboard
  - Bulk promotion support (for belt testing events)
  - REST API: GET /promotions/eligible, POST /promotions/promote
```

#### 4.10 — Rank REST API Controller (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the RankController exposing REST endpoints for retrieving
             member rank data and rank history. These endpoints power the member
             dashboard rank display, coach admin views, and are consumed by
             the AI Coaching agent (M6).
DEPENDS_ON: 4.1, 1.10
CONTROLLER: src/API/RankController.php (extends Gym_Core\API\BaseController)
ACCEPTANCE:
  - Endpoints registered under gym/v1 namespace:
    | Method | Route                              | Description                                    | Auth                             |
    |--------|------------------------------------|------------------------------------------------|----------------------------------|
    | GET    | /gym/v1/members/{id}/rank          | Current rank for a member (by program)         | Logged-in (own) OR gym_view_ranks |
    | GET    | /gym/v1/members/{id}/rank-history  | Full promotion history for a member            | Logged-in (own) OR gym_view_ranks |
    | POST   | /gym/v1/ranks/promote              | Promote a member to the next rank/stripe       | gym_promote_student              |
  - GET /members/{id}/rank query parameters:
    - program (string, optional) — filter by program slug (bjj, kids-bjj, kickboxing)
    - If omitted, returns ranks for all programs the member participates in
  - GET /members/{id}/rank returns:
    - Array of { program, belt, stripes, promoted_at, promoted_by: { id, name },
      attendance_since_promotion, next_belt, next_stripes }
  - GET /members/{id}/rank-history query parameters:
    - program (string, optional) — filter by program
    - per_page, page — pagination
  - GET /members/{id}/rank-history returns:
    - Paginated array of { from_belt, from_stripes, to_belt, to_stripes,
      promoted_at, promoted_by: { id, name }, notes }
    - Sorted newest-first
  - POST /ranks/promote request body:
    - user_id (int, required)
    - program (string, required)
    - to_belt (string, optional — defaults to next in sequence)
    - to_stripes (int, optional — defaults to next in sequence)
    - notes (string, optional)
  - POST /ranks/promote:
    - Validates promotion is a legal progression (no skipping belts)
    - Records in gym_ranks + gym_rank_history
    - Fires gym_core_rank_changed action hook
    - Returns the updated rank object
  - Self-access: members can GET their own rank with just logged-in status
  - Coach/admin access: gym_view_ranks cap required to view other members
  - Schema defined per WP REST API spec
  - Unit tests for each endpoint
  - Integration test: promote → rank-history reflects change
SCHEMA_NOTES:
  Rank object: { program, belt, stripes, promoted_at, promoted_by: { id, name },
    attendance_since_promotion, next_belt, next_stripes }
  RankHistory entry: { from_belt, from_stripes, to_belt, to_stripes,
    promoted_at, promoted_by: { id, name }, notes }
  Promote request: { user_id, program, to_belt?, to_stripes?, notes? }
```

#### 4.11 — Attendance REST API Controller (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the AttendanceController exposing REST endpoints for check-in,
             attendance retrieval, and today's attendance view. These endpoints
             power the kiosk check-in app, coach attendance dashboard, and are
             consumed by the AI Coaching and Admin agents (M6).
DEPENDS_ON: 4.2, 1.10
CONTROLLER: src/API/AttendanceController.php (extends Gym_Core\API\BaseController)
ACCEPTANCE:
  - Endpoints registered under gym/v1 namespace:
    | Method | Route                         | Description                                      | Auth                                |
    |--------|-------------------------------|--------------------------------------------------|-------------------------------------|
    | POST   | /gym/v1/check-in              | Record a member check-in for a class             | gym_check_in_member OR manage_options |
    | GET    | /gym/v1/attendance/{user_id}  | Attendance history for a specific member         | Logged-in (own) OR gym_view_attendance |
    | GET    | /gym/v1/attendance/today      | All check-ins for today (filterable by location) | gym_view_attendance                 |
  - POST /check-in request body:
    - user_id (int, required) — member being checked in
    - class_id (int, required) — gym_class post ID
    - method (string, required) — one of: qr_scan, member_id, name_search
  - POST /check-in validation:
    - Member has active WooCommerce Membership
    - Member is eligible for this class (program + location match)
    - No duplicate check-in for same class on same day
    - Returns 409 Conflict on duplicate, 403 on ineligible
  - POST /check-in response:
    - { attendance_id, user: { id, name, rank }, class: { id, name }, checked_in_at }
    - Fires gym_core_attendance_recorded action hook
    - Target: check-in-to-response under 500ms
  - GET /attendance/{user_id} query parameters:
    - from (ISO 8601 date, optional) — start of date range
    - to (ISO 8601 date, optional) — end of date range
    - program (string, optional) — filter by program
    - per_page, page — pagination
  - GET /attendance/{user_id} returns:
    - Paginated array of { id, class: { id, name, program }, location,
      checked_in_at, method }
    - Meta includes total_count for the filtered range
  - GET /attendance/today query parameters:
    - location (string, optional) — filter by gym_location slug
    - class_id (int, optional) — filter to a specific class
  - GET /attendance/today returns:
    - Array of { user: { id, name, rank }, class: { id, name },
      checked_in_at, method }
    - Grouped by class if no class_id filter provided
  - Self-access: members can GET their own attendance with logged-in status
  - Coach/admin access: gym_view_attendance cap required for other members / today view
  - Schema defined per WP REST API spec
  - Unit tests for each endpoint (including validation edge cases)
  - Integration test: check-in → appears in attendance/{user_id} and attendance/today
SCHEMA_NOTES:
  Attendance record: { id, user: { id, name }, class: { id, name, program },
    location: { slug, name }, checked_in_at, method }
  Check-in request: { user_id, class_id, method }
  Today view groups: { class: { id, name, start_time }, attendees: [ AttendanceRecord ] }
```

#### 4.12 — Promotion REST API Controller (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the PromotionController exposing REST endpoints for querying
             promotion eligibility and executing promotions. These endpoints power
             the coach promotion dashboard and are consumed by the AI Coaching
             agent (M6) for flagging promotion-ready students.
DEPENDS_ON: 4.3, 1.10
CONTROLLER: src/API/PromotionController.php (extends Gym_Core\API\BaseController)
ACCEPTANCE:
  - Endpoints registered under gym/v1 namespace:
    | Method | Route                        | Description                                         | Auth                |
    |--------|------------------------------|-----------------------------------------------------|---------------------|
    | GET    | /gym/v1/promotions/eligible   | List all members currently eligible for promotion   | gym_promote_student |
    | POST   | /gym/v1/promotions/promote    | Execute a belt promotion for a member               | gym_promote_student |
  - GET /promotions/eligible query parameters:
    - location (string, optional) — filter by gym_location slug
    - program (string, optional) — filter by program (bjj, kids-bjj, kickboxing)
    - per_page, page — pagination
  - GET /promotions/eligible returns:
    - Paginated array of { user: { id, name, email }, program, current_belt,
      current_stripes, attendance_since_promotion, time_at_current_rank,
      meets_attendance_threshold (bool), meets_time_threshold (bool),
      coach_recommended (bool), next_belt, next_stripes }
    - Sorted by readiness (all three criteria met first, then two, then one)
  - POST /promotions/promote request body:
    - user_id (int, required)
    - program (string, required)
    - to_belt (string, optional — defaults to next in sequence)
    - to_stripes (int, optional — defaults to next in sequence)
    - notes (string, optional)
    - bulk (bool, optional, default false) — if true, accepts user_ids[] array
  - POST /promotions/promote:
    - Delegates to RankController POST /ranks/promote internally
    - Fires gym_core_rank_changed action hook
    - Triggers notification (SMS + email congratulations via M2.4)
    - Supports bulk: accepts user_ids[] for belt testing events
    - Returns array of updated rank objects
  - All endpoints require gym_promote_student capability (coaches + admins)
  - Schema defined per WP REST API spec
  - Unit tests for eligibility calculation and promotion execution
  - Integration test: attendance threshold met → appears in eligible → promote → rank updated
SCHEMA_NOTES:
  Eligible member: { user, program, current_belt, current_stripes,
    attendance_since_promotion, time_at_current_rank, meets_attendance_threshold,
    meets_time_threshold, coach_recommended, next_belt, next_stripes }
  Promote request: { user_id, program, to_belt?, to_stripes?, notes?, bulk? }
  Bulk promote request: { user_ids[], program, to_belt?, to_stripes?, notes?, bulk: true }
NOTE: POST /promotions/promote and POST /ranks/promote (4.10) share the same
      underlying RankStore logic. PromotionController adds the eligibility check
      and bulk support layer on top. The AI Coaching agent uses /promotions/eligible
      to flag ready students and /ranks/promote (via approval gate) to execute.
```

---

## Milestone 5: Gamification Engine

**Goal:** Drive engagement and retention through badges, streaks, milestone notifications, and targeted content — all powered by the check-in and rank data from M4.

**Depends on:** M4 (attendance + rank data to gamify)

### For Andrew (what gets done)

Members earn badges for achievements (first class, 10-class streak, 100 total classes, belt promotions). Streak tracking shows consecutive-week attendance. Milestone notifications fire via SMS and email at key moments. The member dashboard becomes a motivational tool — progress bars toward next badge, current streak counter, recent achievements. Content can be targeted by belt level, program, and location (e.g., "Blue belt technique of the week" only shows to blue belts and above).

### Agent Task List

#### 5.1 — Badge + Achievement System (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the badge/achievement tracking system.
DEPENDS_ON: 4.2
ACCEPTANCE:
  - Custom table: {prefix}gym_achievements (id, user_id, badge_slug, earned_at, metadata)
  - Badge definitions (filterable via hook):
    - first_class: First check-in ever
    - streak_4: 4-week attendance streak
    - streak_12: 12-week attendance streak
    - streak_26: 26-week attendance streak (6 months)
    - classes_10, classes_25, classes_50, classes_100, classes_250, classes_500
    - belt_promotion: Earned on each promotion
    - perfect_week: Attended every scheduled class in a week
    - early_bird: Checked in to the first class of the day 10 times
    - multi_program: Attended classes in 2+ programs
  - Badge evaluation runs on gym_core_attendance_recorded hook
  - Badge earned fires gym_core_badge_earned action
  - Badge display on member dashboard (grid with earned/locked states)
  - Notification on badge earn (SMS + email)
  - REST API: GET /members/{id}/badges
```

#### 5.2 — Streak Tracking (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Track consecutive-week attendance streaks.
DEPENDS_ON: 4.2
ACCEPTANCE:
  - Streak calculation: number of consecutive calendar weeks with at least 1 check-in
  - Current streak displayed on member dashboard (prominent counter)
  - Longest streak tracked and displayed
  - Streak freeze: 1 per quarter (member can "freeze" a missed week — preserve streak)
  - Streak milestones trigger badges (M5.1)
  - Weekly streak reminder (SMS): "You're on a 7-week streak! Keep it going this week."
  - Streak break notification: "Your 12-week streak ended. Come back and start a new one!"
```

#### 5.3 — Targeted Content Delivery (gym-core)
```
STATUS: NOT STARTED
DESCRIPTION: Content targeting engine that filters content by belt, program, and location.
DEPENDS_ON: 4.1, 3.1
ACCEPTANCE:
  - Block: gym/targeted-content — renders inner blocks only if viewer matches criteria
  - Criteria: minimum belt rank, specific program(s), specific location(s)
  - Use case: "Blue Belt Technique of the Week" post visible only to blue+ belts
  - Use case: "Kids Program Parent Update" visible only to parents of Kids BJJ members
  - Integrates with WooCommerce Memberships content restriction
  - Check-in-to-notification targeting pipeline under 500ms
    (hook chain: attendance_recorded → evaluate_badges → fire_notification)
  - Admin UI: simple meta box on posts/pages to set targeting rules
```

#### 5.10 — Gamification REST API Controller (gym-core)
```
STATUS: COMPLETE
DESCRIPTION: Build the GamificationController exposing REST endpoints for badges,
             badge definitions, and streak data. These endpoints power the member
             dashboard gamification widgets and are consumed by the AI Coaching
             agent (M6) for motivational recommendations.
DEPENDS_ON: 5.1, 5.2, 1.10
CONTROLLER: src/API/GamificationController.php (extends Gym_Core\API\BaseController)
ACCEPTANCE:
  - Endpoints registered under gym/v1 namespace:
    | Method | Route                        | Description                                          | Auth                                    |
    |--------|------------------------------|------------------------------------------------------|-----------------------------------------|
    | GET    | /gym/v1/badges               | List all badge definitions (earned/locked states)    | Public (definitions) / Logged-in (states)|
    | GET    | /gym/v1/members/{id}/badges  | Badges earned by a specific member                   | Logged-in (own) OR gym_view_achievements |
    | GET    | /gym/v1/members/{id}/streak  | Current and longest streak for a member              | Logged-in (own) OR gym_view_achievements |
  - GET /badges query parameters:
    - category (string, optional) — filter by badge category (attendance, rank, special)
  - GET /badges returns:
    - Array of all badge definitions: { slug, name, description, icon_url,
      category, criteria_summary }
    - If user is logged in, each badge includes: { earned (bool), earned_at (nullable) }
    - If not logged in, earned/earned_at fields are omitted
  - GET /members/{id}/badges query parameters:
    - per_page, page — pagination
  - GET /members/{id}/badges returns:
    - Paginated array of { badge: { slug, name, description, icon_url },
      earned_at, metadata }
    - Sorted by earned_at descending (most recent first)
    - Meta includes total_badges_earned, total_badges_available
  - GET /members/{id}/streak returns:
    - { current_streak (int, weeks), longest_streak (int, weeks),
      streak_started_at (date), freezes_remaining (int),
      last_check_in_date, streak_status (active|frozen|broken) }
  - Self-access: members can GET their own badges/streak with just logged-in status
  - Coach/admin access: gym_view_achievements cap required for other members
  - Schema defined per WP REST API spec
  - Unit tests for each endpoint
  - Integration test: badge earned → appears in /members/{id}/badges
  - Integration test: streak increments → reflected in /members/{id}/streak
SCHEMA_NOTES:
  Badge definition: { slug, name, description, icon_url, category, criteria_summary }
  Earned badge: { badge: BadgeDefinition, earned_at, metadata (JSON) }
  Streak object: { current_streak, longest_streak, streak_started_at,
    freezes_remaining, last_check_in_date, streak_status }
```

---

## Milestone 6: AI Operations Layer

**Goal:** The four AI agents (Sales, Coaching, Finance, Admin) are fully operational with real tool access, staff approval gates, and conversation history.

**Depends on:** M2 (CRM data for agents to query), M4 (attendance/rank data for coaching agent)
**Plugin:** `gym-ai-chat` (already scaffolded at v0.1.0)

**REST API NOTE:** The AI agent tool endpoints live in `gym-ai-chat`, NOT in `gym-core`.
However, the gym-core REST API (gym/v1) is the primary **read layer** that all AI agents
call through their MCP tool definitions. M6 therefore depends on all gym/v1 endpoints
from M2-M5 being complete and stable:

- **Sales Agent reads:** `/gym/v1/locations`, `/gym/v1/classes`, `/gym/v1/sms/conversations/{contact_id}`
- **Coaching Agent reads:** `/gym/v1/members/{id}/rank`, `/gym/v1/members/{id}/rank-history`, `/gym/v1/attendance/{user_id}`, `/gym/v1/members/{id}/badges`, `/gym/v1/members/{id}/streak`, `/gym/v1/schedule`
- **Finance Agent reads:** WooCommerce REST API (orders, subscriptions) — no gym/v1 endpoints needed
- **Admin Agent reads:** `/gym/v1/attendance/today`, `/gym/v1/schedule`, `/gym/v1/promotions/eligible`

### For Andrew (what gets done)

The gym-ai-chat plugin gets wired into real data. The Sales agent can look up pricing, check lead status in Jetpack CRM, and draft follow-up SMS messages (with staff approval). The Coaching agent can pull a student's attendance history and rank, and recommend training plans. The Finance agent can query billing data from WooCommerce and generate reports. The Admin agent can check schedules and draft staff communications. All write operations require staff confirmation before executing. LibreChat provides the self-hosted AI orchestration layer with MCP support.

### Agent Task List

#### 6.1 — AI Agent Tool Definitions (gym-ai-chat)
```
STATUS: NOT STARTED
DESCRIPTION: Define the MCP-compatible tool schemas that each agent can call.
DEPENDS_ON: M2, M4, 2.10, 3.10, 3.11, 4.10, 4.11, 4.12, 5.10
ACCEPTANCE:
  - Sales Agent tools:
    - lookup_pricing (read: product catalog)
    - search_contacts (read: Jetpack CRM)
    - get_lead_pipeline (read: CRM pipeline)
    - draft_sms (write: creates pending SMS for approval)
    - draft_email (write: creates pending email for approval)
    - schedule_trial_class (write: creates pending booking)
  - Coaching Agent tools:
    - get_student_rank (read: rank data — calls GET /gym/v1/members/{id}/rank)
    - get_attendance_history (read: attendance records — calls GET /gym/v1/attendance/{user_id})
    - get_class_schedule (read: schedule — calls GET /gym/v1/schedule)
    - get_badges (read: badges — calls GET /gym/v1/members/{id}/badges)
    - get_streak (read: streak — calls GET /gym/v1/members/{id}/streak)
    - recommend_training_plan (write: creates pending plan for coach review)
    - flag_promotion_ready (write: creates pending promotion flag)
  - Finance Agent tools:
    - get_revenue_summary (read: WooCommerce order data)
    - get_subscription_status (read: subscription data)
    - get_failed_payments (read: failed renewal list)
    - generate_report (write: creates pending report)
  - Admin Agent tools:
    - get_staff_schedule (read: schedule data — calls GET /gym/v1/schedule)
    - get_attendance_summary (read: attendance aggregates — calls GET /gym/v1/attendance/today)
    - get_promotion_eligible (read: calls GET /gym/v1/promotions/eligible)
    - draft_announcement (write: creates pending staff message)
  - All tools registered as REST API endpoints in gym-ai-chat
  - All write tools create PendingAction records (existing approval flow)
  - Tool schemas follow MCP tool definition format
  - Read tools are thin wrappers that authenticate to gym/v1 endpoints
    using WordPress application passwords or internal REST dispatch
```

#### 6.2 — LibreChat Integration
```
STATUS: NOT STARTED
DESCRIPTION: Deploy LibreChat as the AI orchestration layer with MCP support.
DEPENDS_ON: 6.1
ACCEPTANCE:
  - LibreChat self-hosted instance deployed
  - MCP server configured pointing to gym-ai-chat REST endpoints
  - Four named agents configured in LibreChat:
    - Sales Agent (tool access: sales tools only)
    - Coaching Agent (tool access: coaching tools only)
    - Finance Agent (tool access: finance tools only, requires manage_options)
    - Admin Agent (tool access: admin tools only, requires manage_options)
  - Authentication: LibreChat → WordPress via application passwords or OAuth
  - Multi-user RBAC: staff members mapped to their WordPress roles
  - Conversation history persisted in gym-ai-chat database
  - Artifact rendering enabled (for reports, plans)
```

#### 6.3 — Staff Approval Flow Polish
```
STATUS: NOT STARTED
DESCRIPTION: Complete the three-path approval flow UI and add notification delivery.
DEPENDS_ON: 6.1
ACCEPTANCE:
  - Approval UI in WP admin (already scaffolded, needs polish):
    - Approve → immediate execution
    - Approve with Changes → staff edits, then agent re-executes
    - Reject → with optional reason
  - Real-time notification when new action needs approval
    (admin notice, optional Slack webhook, optional SMS to admin)
  - Audit log: who approved/rejected what, when, with what changes
  - Bulk approve/reject for batch operations
  - Mobile-friendly approval (works on phone)
```

#### 6.10 — gym/v1 Endpoint Completeness Audit
```
STATUS: NOT STARTED
DESCRIPTION: Verify that all gym/v1 REST API endpoints required by the AI agent tool
             definitions (6.1) are complete, documented, and return consistent schemas.
             This is a gate task — M6 agent tools cannot be wired up until the read
             layer is confirmed stable.
DEPENDS_ON: 2.10, 3.10, 3.11, 4.10, 4.11, 4.12, 5.10
ACCEPTANCE:
  - Every endpoint listed in 6.1's read tools is reachable and returns expected schema
  - Response envelope is consistent across all controllers (BaseController standard)
  - Pagination works consistently (per_page, page, total, total_pages in meta)
  - Error responses follow consistent format (status code, code, message)
  - Authentication/authorization verified for each endpoint
  - OpenAPI/JSON Schema documentation generated for gym/v1 namespace
  - Performance: all read endpoints respond under 200ms (p95) with representative data
  - Integration test suite: one test per endpoint exercising happy path + auth failure
NOTE: This task exists because M6 agents are only as good as the data they can read.
      If any gym/v1 endpoint is broken, inconsistent, or slow, the agents will fail.
      Treat this as a release gate — do not proceed to 6.1 wiring until this passes.
```

---

## Milestone 7: Media, Migration + Go-Live

**Goal:** Replace remaining paid tools (Vimeo), complete the Spark migration, train staff, and fully launch.

**Replaces:** Vimeo ($20+/mo) · Spark Membership (dominant cost driver)
**Depends on:** All previous milestones

**REST API NOTE:** M7 does not introduce new REST controllers in gym-core. If a migration
status dashboard is needed during the parallel run (7.4), it can be built as a WP admin
page reading directly from the database — no public API required.

### For Andrew (what gets done)

Jetpack VideoPress replaces Vimeo for hosting training videos and technique libraries. All remaining Spark data (historical attendance, billing records) gets migrated or archived. Staff get trained on every piece of the new stack. A parallel run period confirms everything works before Spark is cancelled. The launch is a controlled cutover with rollback capability.

### Agent Task List

#### 7.1 — Jetpack VideoPress Setup
```
STATUS: NOT STARTED
DESCRIPTION: Replace Vimeo with Jetpack VideoPress for video hosting.
DEPENDS_ON: M1 (WordPress site live)
ACCEPTANCE:
  - Jetpack VideoPress activated
  - Existing training videos migrated from Vimeo
  - Video library organized by program and belt level
  - Videos gated by membership (via WooCommerce Memberships)
  - Embedded in member dashboard and technique pages
  - Mobile-optimized playback
```

#### 7.2 — Spark Data Migration
```
STATUS: NOT STARTED
DESCRIPTION: Migrate historical data from Spark Membership.
DEPENDS_ON: M4 (rank + attendance tables exist)
ACCEPTANCE:
  - Historical attendance records imported (or archived as reference)
  - Historical billing records archived (PDF statements or CSV)
  - Belt rank history imported into gym_rank_history
  - Member notes/comments migrated to Jetpack CRM contact records
  - Data integrity verified: spot-check 20 members across both locations
NOTE: Requires Spark dashboard access (read-only credentials from Darby).
      Some data may need manual entry if Spark export capabilities are limited.
```

#### 7.3 — Staff Training
```
STATUS: NOT STARTED
DESCRIPTION: Train all 5 staff members on the new system.
DEPENDS_ON: All features complete
ACCEPTANCE:
  - Training materials created:
    - Quick-start guide for each role (Darby/Amanda, Joy, Matt, Rachel)
    - Video walkthroughs of daily tasks
    - FAQ document
  - Training sessions completed:
    - Darby + Amanda: Full admin (all systems)
    - Joy: Admin + bookkeeper workflows (CRM, billing, reports)
    - Matt: Sales workflows (CRM, pipeline, SMS)
    - Rachel: Sales workflows (CRM, pipeline, SMS)
  - Each person can independently perform their daily tasks on the new system
  - Support channel established (how staff gets help during transition)
```

#### 7.4 — Parallel Run + Cutover
```
STATUS: NOT STARTED
DESCRIPTION: Run both systems in parallel, then decommission old stack.
DEPENDS_ON: 7.1, 7.2, 7.3
ACCEPTANCE:
  - 30-day parallel run: new signups go through WooCommerce,
    existing members continue on Spark until their next billing cycle
  - Daily reconciliation check: new system matches expected billing
  - Member communication: email + SMS announcing the transition
  - Gradual member migration: batch-move members to WooCommerce billing
    over 2-4 weeks (aligned with billing cycle dates)
  - Final decommission checklist:
    - [ ] All members billing through WooCommerce
    - [ ] All contacts in Jetpack CRM
    - [ ] All automations running in AutomateWoo
    - [ ] SMS working through Twilio
    - [ ] Check-in kiosks using new system
    - [ ] Historical data archived
    - [ ] Spark subscription cancelled
    - [ ] GHL subscription cancelled (if not already in M2)
    - [ ] Wix subscription cancelled (if not already in M1)
    - [ ] Vimeo subscription cancelled (if not already in M7.1)
  - Post-launch monitoring: 2 weeks of daily check-ins with staff
```

---

## Summary: Cost Impact

| System | Current Monthly | After Migration | Savings |
|--------|----------------|-----------------|---------|
| Wix | ~$74 | $0 (Pressable hosting included) | $74/mo |
| GoHighLevel | ~$137 | $0 (Jetpack CRM + AutomateWoo + MailPoet) | $137/mo |
| USAePay/Pitbull Processing | ~$616 | ~$569 (WooPayments) | ~$47/mo |
| Vimeo | ~$20+ | $0 (Jetpack VideoPress) | $20/mo |
| Spark Membership | TBD (dominant cost) | $0 | TBD |
| **Twilio SMS (new cost)** | $0 | ~$20-50/mo (usage-based) | -$35/mo |
| **WooCommerce Extensions** | $0 | ~$50-80/mo (Subscriptions + Memberships + AutomateWoo) | -$65/mo |
| | | **Net platform savings** | **~$178-278/mo** |
| | | **Before Spark cancellation** | **($2,136-$3,336/yr)** |

*Spark cancellation savings are additive and likely the largest single line item.*

---

## Plugin Architecture Overview

```
gym-core/                          gym-ai-chat/
├── src/                           ├── src/
│   ├── Plugin.php                 │   ├── Plugin.php
│   ├── Location/                  │   ├── API/
│   │   └── LocationTaxonomy.php   │   │   ├── MessageEndpoint.php
│   ├── Membership/                │   │   ├── HeartbeatEndpoint.php
│   │   └── MembershipIntegration  │   │   └── ActionEndpoint.php
│   ├── Schedule/                  │   ├── Agents/
│   │   ├── ClassPostType.php      │   │   ├── AgentPersona.php
│   │   └── ScheduleBlock.php      │   │   ├── AgentRegistry.php
│   ├── Rank/                      │   │   └── Tools/ (M6)
│   │   ├── RankStore.php          │   ├── Data/
│   │   ├── RankHistory.php        │   │   ├── ConversationStore.php
│   │   └── PromotionEngine.php    │   │   └── PendingActionStore.php
│   ├── Attendance/                │   └── Security/
│   │   ├── AttendanceStore.php    │       └── WebhookValidator.php
│   │   ├── CheckInKiosk.php       └── assets/
│   │   └── CheckInBlock.php
│   ├── Gamification/
│   │   ├── BadgeEngine.php
│   │   ├── StreakTracker.php
│   │   └── TargetedContent.php
│   ├── SMS/
│   │   ├── TwilioClient.php
│   │   ├── MessageTemplates.php
│   │   └── InboundHandler.php
│   └── API/
│       ├── BaseController.php          ← M1.10 (foundation)
│       ├── LocationController.php      ← M1.10
│       ├── SMSController.php           ← M2.10
│       ├── ClassScheduleController.php ← M3.10
│       ├── MemberController.php        ← M3.11
│       ├── RankController.php          ← M4.10
│       ├── AttendanceController.php    ← M4.11
│       ├── PromotionController.php     ← M4.12
│       └── GamificationController.php  ← M5.10
```

---

## REST API Endpoint Summary (gym/v1)

All endpoints use the `gym/v1` namespace. Controllers extend `Gym_Core\API\BaseController`.

| Controller | Task | Route | Methods | Auth |
|---|---|---|---|---|
| **LocationController** | 1.10 | `/locations` | GET | Public |
| | | `/locations/{slug}` | GET | Public |
| | | `/locations/{slug}/products` | GET | Public |
| | | `/user/location` | GET, PUT | Logged-in |
| **SMSController** | 2.10 | `/sms/send` | POST | gym_send_sms |
| | | `/sms/webhook` | POST | Twilio signature |
| | | `/sms/conversations/{contact_id}` | GET | gym_send_sms |
| **ClassScheduleController** | 3.10 | `/classes` | GET | Public |
| | | `/classes/{id}` | GET | Public |
| | | `/schedule` | GET | Public |
| **MemberController** | 3.11 | `/members/me/dashboard` | GET | Logged-in |
| **RankController** | 4.10 | `/members/{id}/rank` | GET | Logged-in (own) / gym_view_ranks |
| | | `/members/{id}/rank-history` | GET | Logged-in (own) / gym_view_ranks |
| | | `/ranks/promote` | POST | gym_promote_student |
| **AttendanceController** | 4.11 | `/check-in` | POST | gym_check_in_member |
| | | `/attendance/{user_id}` | GET | Logged-in (own) / gym_view_attendance |
| | | `/attendance/today` | GET | gym_view_attendance |
| **PromotionController** | 4.12 | `/promotions/eligible` | GET | gym_promote_student |
| | | `/promotions/promote` | POST | gym_promote_student |
| **GamificationController** | 5.10 | `/badges` | GET | Public / Logged-in |
| | | `/members/{id}/badges` | GET | Logged-in (own) / gym_view_achievements |
| | | `/members/{id}/streak` | GET | Logged-in (own) / gym_view_achievements |

---

## Milestone Dependencies (visual)

```
M1 Billing ──────────────────────┐
  │                               │
  ├── 1.10 REST Foundation        │
  │     │                         │
  ├── M2 Replace GHL ────────┐   │
  │     │                     │   │
  │     ├── 2.10 SMS API      │   │
  │     │                     │   │
  │     ├── M3 Member Portal ─┤   │
  │     │     │                │   │
  │     │     ├── 3.10 Class   │   │
  │     │     │   Schedule API │   │
  │     │     ├── 3.11 Member  │   │
  │     │     │   Dashboard API│   │
  │     │     │                │   │
  │     │     ├── M4 Rank +   │   │
  │     │     │   Attendance   │   │
  │     │     │     │          │   │
  │     │     │     ├── 4.10 Rank API
  │     │     │     ├── 4.11 Attendance API
  │     │     │     ├── 4.12 Promotion API
  │     │     │     │          │   │
  │     │     │     ├── M5    │   │
  │     │     │     │  Gamify  │   │
  │     │     │     │  │       │   │
  │     │     │     │  ├── 5.10 Gamification API
  │     │     │     │          │   │
  │     ├─────┴─────┤          │   │
  │     │           │          │   │
  │     └── 6.10 API Audit ───┘   │
  │           │                    │
  │     └── M6 AI Agents ─────┘   │
  │                                │
  └── M7 Media + Cutover ─────────┘
```
