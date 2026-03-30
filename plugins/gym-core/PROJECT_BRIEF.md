# Project Brief: Gym Core

## Overview

Gym Core is the foundational WooCommerce plugin for **Haanpaa Martial Arts** (HMA) — a
martial arts gym with two physical locations in Rockford, IL and Beloit, WI. The plugin
extends WooCommerce to provide a complete gym management system: multi-location taxonomy,
membership tiers, class scheduling, belt rank tracking, attendance/check-in,
gamification (badges and streaks), Twilio SMS notifications, and authenticated REST API
endpoints designed for consumption by AI agents.

---

## Business Context

| Field | Value |
|---|---|
| Business | Haanpaa Martial Arts ("HMA" / "Haanpaa Fight House") |
| Locations | Rockford, IL and Beloit, WI |
| Distribution | Private — internal use only |
| WooCommerce dependency | Yes — memberships, payments, and e-commerce are WC-powered |

---

## Target Architecture

This plugin follows a **modular monolith** approach: all features live in a single plugin
with clearly separated namespaced modules under `src/`. Feature flags in settings allow
individual modules to be toggled without deactivating the whole plugin. As complexity
grows, individual modules may be extracted into their own plugins.

---

## Core Functionality

### 1. Multi-Location Support
- Custom taxonomy `gym_location` with terms for Rockford and Beloit
- Locations attached to products, classes, instructors, and members
- Admin UI for per-location configuration

### 2. Membership Integration
- WooCommerce-based membership products (tiered: Basic, Pro, Unlimited)
- Access control: restrict classes, content, or features by membership tier
- Membership status synced to user meta for fast capability checks

### 3. Class Schedule System
- Custom post type `gym_class` with recurrence rules
- Per-location, per-instructor class listings
- Student enrollment/RSVP with capacity limits
- Waitlist support

### 4. Belt Rank Tracking
- Rank hierarchy CPT or taxonomy per martial arts style (BJJ, Kickboxing, etc.)
- Promotion records stored per user: date, rank, promoted-by instructor
- Rank display on member profiles

### 5. Attendance & Check-In
- Per-class attendance log (custom DB table: `gym_attendance`)
- Check-in flow: QR code or admin manual check-in
- Attendance history visible on member dashboard and admin user screen

### 6. Gamification (Badges & Streaks)
- Badge definitions (CPT or option) with award conditions
- Streak tracking: consecutive class attendance
- Badge display on My Account and leaderboard widget

### 7. Twilio SMS Integration
- Opt-in SMS notifications stored in user meta
- Notification types: class reminders, belt promotion, schedule changes
- Twilio credentials stored encrypted in WP options (never plaintext)
- Rate-limited via Action Scheduler to avoid Twilio throttling

### 8. REST API Endpoints for AI Agents
- Namespace: `gym/v1`
- Authentication: Application Passwords (WP built-in) + optional JWT
- Endpoints: member lookup, class schedule, attendance log, rank history
- Designed for consumption by external AI agent workflows

---

## Customer-Facing Features

- My Account tabs: Class Schedule, Belt History, Attendance, Badges
- Class RSVP / enrollment on class listing pages
- SMS opt-in/opt-out preference on My Account
- Badge and streak display widgets

---

## Admin / Merchant Features

- Per-location class and instructor management
- Manual check-in interface (admin bar quick action)
- Membership tier management (extends WooCommerce products)
- Belt promotion form on user profile page
- Attendance reports per class and per member
- SMS notification log and send history
- Settings page: `WooCommerce > Gym Core` with tabbed sections per module

---

## Technical Requirements

| Requirement | Value |
|---|---|
| Minimum WordPress version | 7.0 |
| Minimum WooCommerce version | 10.3 |
| Minimum PHP version | 8.0 |
| HPOS compatible | Yes (mandatory) |
| Cart & Checkout Blocks compatible | Yes |
| Product Block Editor compatible | TBD — evaluate when product panels are added |
| Store API extensions needed | TBD — evaluate for membership/class products |
| Custom database tables | `gym_attendance`, `gym_belt_promotions` (planned) |
| External API integrations | Twilio (SMS) |
| Background processing | Yes — Action Scheduler for SMS queuing |
| PSR-4 autoloading | Yes — `Gym_Core\\` → `src/` |

---

## Data & Compliance

| Data | Storage | Notes |
|---|---|---|
| Member contact info | WP user meta | Standard WP/WC data |
| Phone numbers (SMS) | User meta, encrypted | Opt-in only; TCPA compliant |
| Twilio API credentials | WP options, encrypted | Never stored in plaintext or version control |
| Attendance records | Custom DB table | No PII beyond user ID + class ID |
| Belt promotion records | Custom DB table | Internal records only |
| Payment data | WooCommerce / gateway | Never handled directly by this plugin |

- **GDPR**: Member data export/erasure hooks into WP's built-in privacy tools.
- **TCPA**: SMS opt-in is explicit, double-confirmed, with opt-out on every message.
- **PCI-DSS**: No payment card data is processed or stored by this plugin.

---

## Module Milestones

| Milestone | Scope |
|---|---|
| **1.0 — Foundation** | Plugin scaffold, HPOS/block compat, activation/deactivation, settings stub |
| **1.1 — Locations** | `gym_location` taxonomy, admin UI, location meta on WC products |
| **1.2 — Classes** | `gym_class` CPT, schedule display, enrollment/RSVP |
| **1.3 — Members** | Membership tiers (WC products), My Account integration |
| **1.4 — Attendance** | Check-in flow, attendance DB table, history views |
| **1.5 — Belt Ranks** | Rank taxonomy, promotion records, profile display |
| **1.6 — Gamification** | Badge definitions, streak engine, leaderboard widget |
| **1.7 — SMS** | Twilio integration, opt-in UI, Action Scheduler queuing |
| **1.8 — REST API** | `gym/v1` endpoints, Application Password auth, AI agent docs |

---

## Out of Scope

- Payment gateway implementation (handled by WooCommerce core + extensions)
- Video streaming or on-demand class content
- In-app messaging between members
- Native mobile app (REST API provides the integration surface)
- Multi-site / network activation

---

## Related Documentation

All paths relative to the **monorepo root** (two levels up from this file):

- **`MILESTONES.md`** — Full 7-milestone build plan with agent task lists, acceptance criteria, and dependency graph. This is the master roadmap.
- **`docs/planning/gym-core-project-brief.md`** — Expanded project brief with detailed feature specs (supercedes the summary above when more detail is needed).
- **`docs/planning/systems-consolidation-proposal.md`** — Business case for migrating from Spark/GHL/Wix to the WordPress stack.
- **`docs/planning/future-features-plan.md`** — How WordPress 7.0 + WooCommerce AI changes impact the Haanpaa stack.
- **`docs/architecture/ai-architecture-paperclip.md`** — AI agent architecture: WordPress native chat + Paperclip autonomous orchestration.
- **`plugins/gym-ai-chat/`** — Companion plugin for AI chat in wp-admin. See its `SETUP_GUIDE.md`.
