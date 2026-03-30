Haanpaa Martial Arts — Systems Consolidation Proposal
tl;dr Haanpaa runs 11+ platforms across 2 locations at ~$1,800–$2,100+/mo before ad spend. Massive feature overlap between Spark, GoHighLevel, and Wix. The proposed stack consolidates around WooCommerce + Jetpack CRM + Twilio on Pressable, with AI agents built natively into the WordPress admin via WordPress 7.0’s AI primitives. Eliminates Spark (~$1,037–$1,237/mo across both locations + fees + SMS), GoHighLevel (~$270/mo), Wix ($74), Vimeo ($15), Dropbox ($12), and Adobe. Estimated new stack: $147–$399/mo with dramatically more capability, full e‑commerce, and AI agents embedded in every workflow.

Current state: 2 locations, 11 platforms
Haanpaa operates two locations — Rockford (primary) and Beloit (secondary). Some tooling is shared, some is location‑specific. This multi‑location reality affects billing, scheduling, reporting, and CRM segmentation throughout.
Current platform inventory:

Platform
What it does
Monthly cost
Spark Membership (Rockford)
Billing, CRM, attendance, rank tracking, member app, POS, tasks, SMS, time tracking
$239
Spark Membership (Beloit)
Same as above, secondary location
$199
Spark fees
Payment processing fees
$500–700/mo (confirmed monthly, rate TBD)
Spark SMS
Prepurchased SMS messages
$99/1,000 messages
GoHighLevel
SMS, email, sales lead scheduling, pipeline management, notes, in‑app messaging
~$270 ($97/mo + $40/week)
Wix
Website
$74
QuickBooks
Accounting, bookkeeping
$30–90
Vimeo
Video hosting (likely embedded on Wix site)
$15
Dropbox
Document storage
$12
Adobe
Simple graphics for SMS and social posts
TBD
Google Drive
Document storage (duplicates Dropbox)
Free
Google Calendar
Personal scheduling for some staff
Free
Google Sheets
Staff scheduling (Joy manages), budgeting, cashflow
Free
Spark Member App
Member‑facing mobile app (booking, payments, schedule)
Included in Spark
Social media profiles
Marketing presence
Free
Amazon
Gym supplies purchasing (not resale)
Variable
Combat Corner
Gear and equipment ordering for resale to members
Variable
Google / Facebook Ads
Paid advertising
TBD

Known monthly floor: ~$1,800–$2,100+/mo (before Adobe, ads, and SMS usage spikes)
Spark alone accounts for $938–$1,137/mo across both locations ($239 + $199 + $500–700 processing fees), before SMS charges.
Waivers are currently manual paper forms. Staff scheduling is managed by Joy in Google Sheets.

What’s broken: overlap, fragmentation, and data silos
Feature overlap is extreme. Spark and GoHighLevel both do CRM, SMS/email, lead management. GoHighLevel and Wix both build landing pages. Google Sheets handles staff scheduling separately from any other system. Documents live in both Google Drive and Dropbox with no single source of truth.
GoHighLevel contacts are a mess. Every phone number that calls the gym gets added as a contact. There’s no automatic sync between GHL and Spark, so leads that convert have to be manually tracked across both systems.
No unified reporting. The weekly meeting requires pulling from multiple systems: lead counts from GHL, billing data from Spark, financial data from QuickBooks, class data from scheduling tools. Nobody has a single dashboard.
Two locations complicate everything. Each Spark instance is separate. Reporting, member data, and billing are siloed by location with no cross‑location visibility.
Paper waivers are a liability. Physical fitness waivers are manual paper forms. No digital backup, no searchability, no integration with member records.

What they want (complete list)
From the original audit:
Bookkeeping dashboard for Darby (simplified financial view)
Digital waivers for physical fitness (replacing paper forms)
Digital membership agreements — saved, validated, retrievable
Efficient, cost‑effective membership billing
Task management and assignment across the team
Payment processing evaluation
Full e‑commerce: physical goods (Combat Corner resale), timeslot booking, preorders, digital content
All information accessible to all staff
AI agents heavily featured across operations

From discovery (weekly meeting + dashboard):
Weekly reporting: lead count, appointments, trial show‑ups, signups, new money (down payments + one‑offs, not renewals), recurring revenue, forecasts vs. actual received, class announcements
Dashboard widgets: tasks, reminders/scheduling, “Past Due” members, “Expiring Memberships” (60‑day window)
Full member roster: join date, renewal due, last seen at class
Belt/rank tracking with stripe awards per class (coaches need this)
Cancellation workflow (currently email‑only, needs streamlining)

From discovery (sales pipeline):
Lead pipeline with defined stages: Raw leads → Call confirmed 24hr → Confirmed day of → Showed + signed up / Started free trial / Didn’t sign up / No show rescheduling / Not sold comeback appt → Archive (dead leads)
Notes on every lead (currently in GHL contact page)
Messaging from within the CRM contact record
Lead status tracking with clear stage progression

From discovery (operations):
Coach time tracking for payroll (currently in Spark, could move to SMS‑based clock in/out)
Staff scheduling (currently Joy manages in Google Sheets)
Multi‑location awareness throughout the stack
Simple graphic creation for SMS and social (currently Adobe — can AI handle this?)

Proposed stack
REPLACE:
Old
New
Why
Spark ($239 + $199 + fees + SMS)
WooCommerce Subscriptions + Jetpack CRM + Gym Builder Pro + Twilio
Full API access, AI‑queryable, multi‑location in one install, no vendor lock‑in, transparent payment processing
GoHighLevel (~$270)
Jetpack CRM pipeline + AutomateWoo + Twilio SMS
Same pipeline stages, same SMS capability, but CRM is the membership system — no data duplication
Wix ($74)
WordPress on Pressable
WooCommerce site is the website. Better SEO, full e‑commerce, ownership of data
Vimeo ($15)
WordPress video embeds or YouTube (free)
If videos are just class promos or website content, no need for a paid host
Dropbox ($12)
Google Drive (consolidate)
One document store, not two
Adobe (TBD)
AI‑generated graphics via WordPress admin AI panel
For simple graphics for SMS and social, the AI agent handles this. Canva free tier as backup.
Paper waivers
Gravity Forms → Google Drive
Digital, searchable, linked to member records, legally defensible

KEEP:
Platform
Role
Monthly cost
QuickBooks
Accounting, bookkeeping, Darby’s financial view
$30–90
Google Workspace
Calendar (staff scheduling), Drive (all documents), Gmail
Free–$42
Google/Facebook Ads
Paid advertising
Variable (unchanged)

ADD:
Platform
Role
Monthly cost
WordPress on Pressable
Website + e‑commerce + CRM + membership + all business data + AI agents
$25–50
WooCommerce extensions
Subscriptions, Bookings, Pre‑Orders
$40–70 (amortized annual)
Jetpack CRM
Contacts, leads, pipeline, tasks, invoices, belt ranks
~$17 (amortized annual)
Gym Builder Pro
Attendance kiosk, QR check‑in, class rosters
~$5–10 (amortized annual)
Twilio
SMS send/receive (automated + bulk)
$20–60 (usage‑based)
Claude API
AI engine powering all agents via WP AI Client
$20–80 (usage‑based)

WooCommerce: the center of gravity
WooCommerce replaces Wix (website), Spark (membership + billing + CRM + attendance), and GoHighLevel (pipeline + SMS + marketing). Everything consolidates here.
Physical goods — Uniforms, gear, merch, equipment. Full product catalog with variants, inventory tracking, shipping, tax. Items currently ordered from Combat Corner for resale can be listed in the store for members to purchase directly.
Timeslot booking — WooCommerce Bookings for private lessons, special events, testing sessions, intro classes for sales leads. Integrates with Google Calendar for staff availability.
Preorders — WooCommerce Pre‑Orders for new gear drops, upcoming events, limited‑edition items. Neither Spark nor GoHighLevel can do this.
Digital content — Downloadable training videos, technique guides, rank testing prep materials. Instant delivery after purchase. Replaces whatever Vimeo was being used for if it was gated content.
Membership billing — WooCommerce Subscriptions handles recurring billing, dunning emails, failed payment auto‑retry, payment method self‑update by members. More payment gateway options than Spark. Transparent per‑transaction rates via WooPayments or Stripe.
Multi‑location — Both Rockford and Beloit run from a single WordPress install. Products, memberships, and bookings are tagged by location. CRM contacts have a location field. Reporting can filter or aggregate across locations. One system, two locations — not two separate Spark instances.

Jetpack CRM: replacing Spark’s CRM + GoHighLevel’s pipeline
Jetpack CRM (formerly ZBS CRM, acquired by Automattic in 2019) lives inside WordPress and syncs natively with WooCommerce. Every order, subscription, and booking automatically creates or updates a CRM contact record.
Sales pipeline (replicating GHL):
The current GoHighLevel pipeline maps directly to Jetpack CRM pipeline stages:
GHL stage
Jetpack CRM equivalent
Raw leads
New Lead
Call confirmed 24hr
Contacted
Confirmed day of
Confirmed
Showed + signed up
Converted (auto‑creates subscription)
Showed + started free trial
Trial
Showed + didn’t sign up
Follow‑up
No show / rescheduling
Rescheduling
Showed + not sold / comeback appt
Comeback
Archive (dead leads)
Archived

Notes, messaging history, and lead status all live on the contact record — same as GHL, but now the contact record is also the membership record. When a lead converts, there’s no manual data entry. The WooCommerce subscription creates the billing, and the CRM contact updates automatically.
Contact cleanup: Unlike GHL where every phone call creates a contact, the new system only creates contacts from intentional actions (form submission, booking, purchase, manual add). Clean data from day one.

Dashboard widgets:
“Past Due” members → WooCommerce Subscriptions filtered by status = “on‑hold” or “past‑due”
“Expiring Memberships” → Subscriptions with end date within 60 days
Tasks with reminders → Jetpack CRM task system
Weekly meeting metrics → AI‑generated report from WooCommerce + CRM data (lead count, trial show‑ups, signups, new money, recurring revenue, forecasts)

Belt rank system
Three programs tracked via Jetpack CRM custom fields on each contact:
Adult BJJ: 5 belts (White → Blue → Purple → Brown → Black), 4 stripes per belt, minimum class attendance per promotion.
Kids BJJ: 13 belts, 4 stripes per belt, minimum class attendance per promotion.
Kickboxing: 2 levels (not formal belt system).
Custom fields per contact:
bjj_adult_belt, bjj_adult_stripes, bjj_adult_attendance_count, bjj_adult_last_promotion
bjj_kids_belt, bjj_kids_stripes, bjj_kids_attendance_count, bjj_kids_last_promotion
kickboxing_level

Minimum attendance requirements per belt stored as a reference table. AI agent can query: “Who’s eligible for their next belt?” → CRM filter on attendance count ≥ requirement for current rank.
Attendance: Tablet kiosk at each location using Gym Builder Pro (QR check‑in). Attendance records feed into belt rank counters automatically.

SMS: deep integration via Twilio
SMS is critical — not optional. Current usage spans lead nurture, appointment scheduling, reminders, retention, schedule changes, and bulk announcements.
Architecture:
WooCommerce (subscriptions, payments, trial bookings) → customer data flows automatically → Jetpack CRM (contact records, lead pipeline, conversation history) → segments + triggers → AutomateWoo (automated workflows) + MailPoet (bulk email) → Twilio SMS (automated sends + bulk blasts)

Automated sequences (replacing GHL + Spark SMS):
New form submission → welcome SMS with booking link
Trial class booked → confirmation SMS + reminder 24hr before + day‑of confirmation
Trial attended → follow‑up SMS sequence (sign‑up nudge)
Lead goes cold (no booking within 3 days) → automated nudge
Payment failed → dunning SMS with update‑payment link
No attendance in X days → re‑engagement message
Belt testing upcoming → reminder to eligible members
Schedule changes / event announcements → bulk SMS to relevant segments

Two‑way SMS: Members reply to texts. Inbound messages log to the CRM contact record and surface in the WordPress admin for staff to triage.
Cost: ~$20–60/mo via Twilio at their volume, replacing GHL (~$270) + Spark SMS ($99/batch).

Coach time tracking / payroll
Currently uses Spark for time tracking. In the new stack, options:
SMS‑based clock in/out via Twilio — Coach texts “in” or “out” to a dedicated number. Timestamps log to a WordPress custom table. AI agent can generate weekly timesheet reports for payroll.
Simple WordPress time clock plugin — Web‑based punch clock accessible from the tablet kiosk or any browser. Records stored in the WordPress database.
Integration with payroll provider — If they use a payroll service (Gusto, ADP, etc.), connect via API.
Option 1 is the simplest and most AI‑friendly. The AI agent can answer “How many hours did Matt work this week?” without anyone pulling a report.

AI agents: built into WordPress admin
WordPress 7.0 (shipping April 9, 2026) includes a provider‑agnostic AI layer in core: the WP AI Client, Connectors API, and Abilities API with MCP Adapter. This eliminates the need for a separate AI orchestration platform. The four Haanpaa agents live directly inside WordPress as registered Abilities, accessible from the admin dashboard.
Why WordPress admin instead of a separate tool
Zero additional infrastructure — no VPS, no separate app, no SSO configuration
Staff interact with AI agents in the same place they manage everything else
User role scoping uses existing WordPress roles: Administrator for Darby/Amanda/Joy, Shop Manager + CRM for Matt/Rachel
Provider‑agnostic — start with Claude, swap to any model by changing the connector, not the code
Connectors API provides a single admin screen (Settings > Connectors) for managing AI providers, so staff don’t need to configure API keys in a separate system
Every agent capability is a registered Ability with permissions and schemas — automatically discoverable by any MCP client

Staff roles:
Person
Role
AI agent access
WP admin access
Darby
Owner
Full — all agents
Administrator
Amanda
Owner
Full — all agents
Administrator
Joy
Admin / Bookkeeper / Coach
Full — all agents
Administrator
Matt Clark
Coach / Sales
Sales + coaching agents
Shop Manager + CRM
Rachel
Sales
Sales agent
Shop Manager (limited) + CRM

Four named agents:
Sales agent: Leads, SMS drafting, appointment scheduling, pipeline management, billing queries
Coaching agent: Attendance, belt ranks, class scheduling, curriculum, member records
Finance agent: QuickBooks data, P&L, revenue reports, subscription metrics, weekly meeting numbers
Admin agent: All of the above + Drive access + content management + task management

AI‑generated graphics: For simple SMS and social graphics (currently done in Adobe), the AI agent can generate images, write copy, and format social posts directly from the WordPress admin. Eliminates the Adobe subscription.
Write actions from day one, confirmed by staff. Every write action: AI drafts → presents in the admin panel → staff approves → executes.
Weekly meeting automation: Before each weekly meeting, the AI agent generates a complete report: lead count, appointments, trial show‑ups, signups, new money, recurring revenue, forecast vs. actual, expiring memberships, past‑due members, class announcements. Delivered in the admin dashboard or as an email. Nobody has to pull numbers from five different tools.

AI agent architecture
(See visual diagram — haanpaa‑ai‑architecture‑saturated)

Layer 1 — Human interfaces: WordPress admin AI panel (staff), WooCommerce site chatbot (customers)
Layer 2 — AI engine: Claude via WP AI Client (wp_ai_client_prompt()), provider managed through Connectors API
Layer 3 — Abilities + MCP:
Capability
Status
What it connects
Google (Calendar, Gmail, Drive)
Production‑ready MCP server
Staff scheduling, email, documents/waivers
WooCommerce
Native MCP (Developer Preview in WC 10.3+)
Orders, products, inventory, subscriptions, bookings
Jetpack CRM
Custom Abilities (wp_register_ability)
Contacts, pipeline, tasks, belt ranks, activity
QuickBooks
Custom build (Intuit API) → MCP Client later
Financial reports, invoices, P&L, cash flow
Twilio SMS
Custom build (Twilio API) → MCP Client later
Send/receive SMS with staff confirmation

Layer 4 — Sources of truth: Google Workspace, WordPress/WooCommerce on Pressable, QuickBooks, Twilio
Note: WooCommerce MCP ships natively in WC 10.3+, reducing custom build scope. When WordPress ships MCP Client capability (expected 7.1 or 7.2), QuickBooks and Twilio integrations may also simplify — WordPress would ingest their capabilities as native Abilities rather than requiring custom MCP servers.

Cost comparison
Current (known monthly floor):
Platform
Monthly
Spark (Rockford)
$239
Spark (Beloit)
$199
Spark processing fees
$500–700 (confirmed monthly)
Spark SMS
$99 per 1,000 msgs
GoHighLevel
~$270 ($97/mo + $40/wk)
Wix
$74
QuickBooks
$30–90
Vimeo
$15
Dropbox
$12
Adobe
TBD
Google Workspace
Free–$42
Total (before ads)
~$1,800–$2,100+

Spark alone: $938–$1,137/mo across both locations before SMS. That’s where the money is.

Proposed:
Platform
Monthly
Pressable (WordPress hosting)
$25–50
WooCommerce extensions (Subscriptions, Bookings, Pre‑Orders)
$40–70 (amortized)
Jetpack CRM (Entrepreneur bundle)
~$17 (amortized)
Gym Builder Pro / attendance
~$5–10 (amortized)
Twilio SMS
$20–60 (usage‑based)
Claude API
$20–80 (usage‑based)
QuickBooks
$30–90
Google Workspace
Free–$42
WooPayments/Stripe
Per‑transaction (transparent)
Total
~$147–$399

Savings: ~$1,400–$1,700+/mo — and the proposed stack does significantly more. Full e‑commerce, full AI integration, multi‑location in one system, no data silos, no API limitations, robust SMS, automated reporting, and complete data ownership. No separate AI orchestration infrastructure to maintain.
Even without knowing Spark’s exact processing rate, the processing fee line item alone ($500–700/mo) is more than the entire proposed stack. WooPayments/Stripe processing fees will still exist but at transparent, competitive per‑transaction rates — typically 2.9% + $0.30 for cards. The delta depends on Spark’s rate, which we still need.

Implementation phases
Phase 1: Foundation (weeks 1–3)
Stand up WordPress + WooCommerce on Pressable
Install Jetpack CRM with WooSync, configure custom fields for belt ranks
Configure WooCommerce Subscriptions for membership billing (both locations)
Configure WooCommerce Bookings for class scheduling + intro class appointments
Install attendance plugin with tablet kiosk mode (both locations)
Set up Twilio account and WordPress SMS integration
Set up digital waivers (Gravity Forms → Google Drive), replacing paper forms
Consolidate Dropbox → Google Drive with standardized folder structure
Configure WooPayments or Stripe
Enable WP AI Client and configure Connectors API with Claude provider

Phase 2: Migration + e‑commerce (weeks 3–5)
Build product catalog: physical goods (Combat Corner resale), digital content, bookable timeslots, preorders
Migrate member data from Spark (both locations) → Jetpack CRM
Migrate billing data to WooCommerce Subscriptions
Migrate belt rank data to CRM custom fields
Replicate GHL pipeline stages in Jetpack CRM
Set up AutomateWoo + Twilio for all SMS automation sequences
Set up MailPoet for email marketing
Set up QuickBooks sync with WooCommerce
Build dashboard widgets: Past Due, Expiring Memberships, Tasks
Build customer‑facing site design, migrate Wix content
Cut Wix + Vimeo + Dropbox

Phase 3: AI deployment (weeks 4–7)
Register four named agents as WordPress Abilities with permission scoping
Build WP admin AI panel with agent selector and conversation interface
Enable WooCommerce native MCP integration
Register Jetpack CRM operations as custom Abilities (contacts, pipeline, belt queries)
Connect Google MCP server for Calendar, Gmail, Drive access
Build QuickBooks integration (custom API, converts to MCP Client when available)
Build Twilio SMS integration (custom API, converts to MCP Client when available)
Set up automated weekly meeting report
Deploy customer‑facing AI chatbot on WooCommerce site
Set up coach time tracking (SMS‑based or web‑based)

Phase 4: Consolidation (weeks 6–9)
Cancel GoHighLevel
Cancel Spark (both locations, after confirming all data migrated)
Cancel Adobe
Train all 5 staff on WordPress admin + AI panel
Iterate on AI agents based on real usage
Build advanced automations: lead nurture sequences, retention campaigns, AI‑composed content

Platform evolution: WordPress 7.0 + WooCommerce AI
Three upstream changes landing in 2026 significantly strengthen this stack and unlock features that weren’t possible when this proposal was first drafted.
What’s shipping upstream
WordPress 7.0 (April 9, 2026)
Three releases planned for 2026: 7.0 (April), 7.1 (August), 7.2 (December). The AI‑relevant additions that power this stack:
WP AI Client in core: wp_ai_client_prompt() is a fluent PHP API for any plugin to make AI requests. The four Haanpaa agents call AI directly from PHP. Supports system instructions, file attachments, model preferences, and structured JSON responses.
Connectors API: Standardized framework for external service connections with built‑in admin UI. Ships with OpenAI, Google AI, and Anthropic (Claude) providers. One screen to manage all connections.
Abilities API + MCP Adapter: Register typed capabilities with permissions and schemas. The MCP Adapter converts these into Model Context Protocol tools. WordPress becomes both an MCP server and, in the roadmap, an MCP client that ingests capabilities from external services.
Provider‑agnostic architecture: The AI layer works with any model provider. Swap providers by changing the connector, not the code.
WooCommerce MCP (Developer Preview)
WooCommerce 10.3+ ships native MCP integration exposing product and order management as AI‑accessible tools. This replaces the need to build a custom WooCommerce MCP server from scratch. We extend with Haanpaa‑specific abilities (belt rank queries, attendance operations, membership management).
Stripe Agentic Commerce Protocol (ACP)
WooCommerce is building ACP support for WooPayments. Once live, AI assistants like ChatGPT can discover Haanpaa’s products (class packages, memberships, merchandise) and complete purchases directly. For a local martial arts school, showing up in AI‑powered product search is a meaningful acquisition channel no competitor will have.
New features unlocked
Available at launch (April–June 2026)
AI‑composed SMS and email: AutomateWoo workflows call wp_ai_client_prompt() to generate personalized messages. Member hasn’t checked in for 7 days? AI generates a message referencing their belt, streak status, and next class. No template stiffness.
Unified admin via Connectors API: One screen for all external service connections instead of configuring keys across multiple systems.
Post‑launch (July–September 2026)
AI coaching assistant: Coaching agent uses attendance history and belt data to generate personalized training recommendations. Members ask “What should I focus on before my next belt test?” and get contextual answers.
Intelligent gamification: AI‑generated milestone messages referencing a member’s specific journey. Streak notifications adapt urgency based on history.
Natural‑language financial reports: Finance agent answers Darby and Joy’s questions directly: “How did membership revenue compare month over month?” Replaces manual spreadsheet analysis.
AI lead scoring: Sales agent scores leads based on engagement patterns. Surfaces a priority pipeline for Matt and Rachel.
Platform evolution (October 2026+)
WordPress as MCP Client: When WordPress ships MCP Client (7.1 or 7.2), it ingests capabilities from external MCP servers. QuickBooks, Twilio, and Google Workspace become native WordPress abilities. The Finance agent reconciles WooPayments with QuickBooks without custom integration code.
Agentic commerce: Classes and merchandise discoverable via ChatGPT and other AI assistants. Someone asking “Best martial arts classes near Rockford?” could see Haanpaa’s offerings and purchase directly.

Open items
Spark processing rate — Fees are confirmed monthly ($500–700), but we need the actual per‑transaction rate to calculate the delta against WooPayments/Stripe (typically 2.9% + $0.30). This tells us how much of that $500–700 line item actually goes away vs. transfers to the new processor.
Adobe subscription tier — What plan and monthly cost? Confirms savings from switching to AI‑generated graphics.
Ad spend — Ballpark monthly Google + Facebook spend? Doesn’t change the stack recommendation but affects total cost picture.
Coach payroll — Do they use a payroll service (Gusto, ADP, etc.) or handle it manually? Affects time tracking integration.
Vimeo content — What’s hosted there? If it’s just a few promo videos, YouTube or WordPress native video handles it. If it’s gated member content, WooCommerce digital products replace it.
Member count — Approximate total active members across both locations? Helps size Twilio usage estimates and Pressable hosting tier.

Next steps
Share with Haanpaa team for validation on open items.
Get Spark payment processing rates.
Begin Pressable setup and WooCommerce site build.
Set up Twilio account and test SMS integration.
Set up a WordPress 7.0 beta environment to test WP AI Client, Connectors API, and Abilities API with Haanpaa‑specific agent use cases.
Build a demo: “Who’s eligible for blue belt?” answered live from CRM data via the WP admin AI panel. That’s the moment it clicks.