# Jetpack & Jetpack CRM — Evaluation for Haanpaa Martial Arts

> Complete evaluation of every Jetpack module and Jetpack CRM extension for the gym.
> Evaluated 2026-03-31. Site: haanpaa-staging.mystagingwebsite.com

---

## Part 1: Jetpack CRM Extensions

### Enabled (8 extensions)

| Extension | Purpose | Why |
|-----------|---------|-----|
| **WooSync** (free) | Syncs WC customers → CRM contacts | Foundation — auto-creates contacts from subscriptions/orders |
| **Advanced Segments** | Dynamic contact groups | Target members by rank, location, program, attendance patterns |
| **Sales Dashboard** | MRR + revenue visualization | Quick view of membership revenue trends for Darby |
| **Bulk Tagger** | Mass-tag contacts | Initial organization of imported contacts |
| **CSV Importer** | Bulk contact import | GHL contact migration |
| **Funnels** | Acquisition pipeline | Track lead → trial → member conversion |
| **API Connector** | REST API access | gym-core ↔ CRM data sync |
| **WP Utilities** | WP registration → CRM | Captures site registrations as contacts |

### Skipped (better alternatives in stack)

| Extension | Why Skipped | Alternative |
|-----------|-------------|-------------|
| **Mail Campaigns** | Basic SMTP email | MailPoet (visual editor, analytics, deliverability) |
| **Twilio Connect** | Outbound-only, no templates | gym-core TwilioClient (12 templates, inbound, AutomateWoo action) |
| **Client Portal Pro** | Generic invoice/quote portal | Custom MemberDashboard (ranks, attendance, gamification) |
| **Invoicing Pro** | Project-based invoicing | WooCommerce Subscriptions (recurring billing) |
| **Automations** | Limited triggers (new records only) | AutomateWoo (subscription lifecycle, custom gym-core triggers) |
| **Stripe/PayPal Connect** | Direct payment sync | WooSync already syncs WC orders (prevents duplicates) |

### Configuration Applied

- **Custom fields**: Gym Location (select), Primary Program (select), Belt Rank (text), Foundations Status (select), Last Check-In (date), Total Classes (numeric)
- **Tags**: member, lead, lapsed, prospect, trial, bjj, kickboxing, kids-bjj, little-ninjas, rockford, beloit, website-form, phone-call, walk-in, referral, social
- **Statuses**: Lead, Customer, Active Member, Lapsed, Prospect, Trial
- **B2B mode**: Disabled (B2C gym)
- **WooSync**: Auto-create contacts, tag with product names, default status "Customer"
- **Funnel**: 7-stage member acquisition pipeline

### Integration with gym-core

| gym-core Module | CRM Integration |
|----------------|-----------------|
| CrmContactSync | Pushes rank, attendance, Foundations to CRM custom fields |
| CrmSmsBridge | Logs SMS sent/received as CRM activity |
| FormToCrm | Creates CRM contacts from Jetpack Forms + pipeline entries |
| AutomateWooTriggers | Fires CRM-aware workflows on gym events |

### Inspiration for gym-core features

- **Advanced Segments → Coach Briefing**: Segment "at-risk members" (no check-in 14+ days) could feed into coach briefings automatically
- **Funnels → Sales Reporting**: Funnel conversion data could power the Gandalf Finance agent's reports
- **API Connector → Gandalf**: AI agents query CRM contacts via the API for personalized interactions

---

## Part 2: Jetpack Plugin Modules

### Enabled (15 modules + Boost plugin)

| Module | Purpose | Gym Value |
|--------|---------|-----------|
| **Forms** (auto) | Trial signup + contact forms | Hooks into FormToCrm + MailPoet auto-subscribe |
| **Stats** (auto) | Page analytics | Track which pages/programs get traffic |
| **WC Analytics** (auto) | Checkout funnel tracking | Monitor membership signup conversion |
| **Publicize / Social** | Auto-share posts to social | Belt promotion posts → Facebook/Instagram automatically |
| **Image CDN / Photon** | Image optimization + CDN | Faster loading for gym photos, especially on mobile kiosk |
| **Asset CDN** | Static file CDN | Free performance boost |
| **SEO Tools** | Meta descriptions, OG tags | Local SEO for "martial arts near me" in Rockford/Beloit |
| **Sitemaps** | XML sitemap | Google indexing of all location + class pages |
| **Downtime Monitor** | 5-minute uptime pings | Alert if check-in kiosk or signup flow goes down |
| **Firewall / WAF** | Malicious traffic blocking | Security for payment-handling site |
| **Brute Force Protection** (auto) | Login attack blocking | Protect member + admin accounts |
| **Sharing Buttons** | Social share on pages | Members share class schedule, blog posts |
| **Carousel** | Full-screen photo gallery | Tournament photos, belt promotion ceremonies |
| **Tiled Galleries** | Mosaic photo layouts | Gym life, class photos |
| **Copy Post** | Duplicate pages | Clone event pages between locations |
| **Google Fonts** | Typography in Site Editor | Brand customization |
| **Jetpack Boost** (plugin) | Critical CSS, page cache, prefetch, minification | Instant-feeling kiosk + member portal |

### Skipped (not relevant)

| Module | Why |
|--------|-----|
| **Newsletter / Subscriptions** | MailPoet handles email marketing |
| **Search** | 11-page site doesn't need Elasticsearch |
| **VideoPress** | Revisit when curriculum video library is built (M3+) |
| **AI Chat block** | Gandalf is a superior custom AI agent |
| **WordAds** | Gym site should never show ads |
| **Likes / Comment Likes** | Not a social platform |
| **SSO** | Optional for admin, not needed for members |
| **Blaze** | Revisit post-launch for paid advertising |
| **Simple Payments / Memberships** | WooCommerce handles all payments |

### Jetpack AI Assistant

**Status**: Available in the block editor (type `/AI`). Uses WordPress.com AI backend.

**Use for**: Writing blog posts, class descriptions, marketing copy, SEO meta descriptions, featured image generation.

**Does NOT replace Gandalf**: Jetpack AI only works inside the editor. Gandalf works via REST API + frontend chat for operational queries (member data, schedules, briefings).

**Coexistence**: Both can run simultaneously. Jetpack AI for content creation, Gandalf for gym operations.

### Inspiration for gym-core features

1. **Publicize + PromotionNotifier**: Auto-create a celebratory blog post when a student earns a new belt → Publicize shares to all social channels. Could use Jetpack AI to generate the post text.

2. **Forms Webhooks → Gandalf**: Jetpack Forms webhook sends new trial signups to a Gandalf endpoint. Gandalf generates a personalized welcome message + suggests best class based on interests.

3. **MailPoet Forms Integration**: Built-in `MailPoet_Integration` in Jetpack Forms auto-subscribes form submitters to MailPoet lists. Segment by "Trial Leads - Rockford" and "Trial Leads - Beloit".

4. **Boost Speculation Rules for Kiosk**: Prefetches the check-in confirmation page while the member selects their class → sub-100ms perceived check-in time.

5. **WC Analytics for Funnel Optimization**: Track where membership signups drop off (product view → add to cart → checkout) to optimize the pricing/checkout flow.

6. **VideoPress for Curriculum (future)**: When the curriculum video library ships, VideoPress with JWT privacy controls could gate technique videos by membership plan + belt rank. Members only see videos appropriate for their level.

---

## Cost Summary

| Item | Annual Cost |
|------|-------------|
| Jetpack (included with Pressable) | $0 |
| Jetpack Boost (free tier) | $0 |
| Jetpack CRM Entrepreneur Bundle | $199/yr |
| **Total Jetpack ecosystem** | **$199/yr** |
