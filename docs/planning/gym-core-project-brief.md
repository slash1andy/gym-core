# Project Brief: Gym Core

## Overview

Gym Core is the custom WooCommerce plugin for Haanpaa Martial Arts that extends the WordPress/WooCommerce stack with martial-arts-specific functionality. It provides multi-location support, class scheduling, belt rank tracking, attendance check-in (tablet kiosk), gamification (badges, streaks, targeted content), and Twilio SMS integration. It is the operational backbone that replaces Spark Membership's proprietary features with open, extensible WordPress-native systems.

## Target Market

Single-site deployment for Haanpaa Martial Arts (2 locations: Rockford and Beloit, WI). Not intended for marketplace distribution. Built to be extensible in case other martial arts gyms in Andrew's network want a similar stack, but that's post-MVP.

## Core Functionality

Multi-location taxonomy that threads through products, orders, users, schedules, and content. Class schedule management with a custom post type. Belt rank and stripe tracking for Adult BJJ (5 belts × 4 stripes), Kids BJJ (13 belts × 4 stripes), and Kickboxing (2 levels). Attendance check-in via tablet kiosk with QR code, member ID, or name search. Promotion eligibility engine based on attendance thresholds and coach recommendations. Gamification layer with badges, streak tracking, and targeted content delivery. Twilio SMS integration wired into the CRM and automation stack for lead nurture, retention, and operational notifications.

## Customer-Facing Features

- Member dashboard enhancements (rank display, attendance history, streak counter, badge grid)
- Check-in kiosk (simplified touch UI for tablet at gym entrance)
- Class schedule display (filterable by location and program)
- Belt progress visualization (current rank, stripes, progress toward next promotion)
- Targeted content blocks (content visible only to members at specific belt levels/programs)
- SMS opt-in/opt-out management
- iCal feed for class schedule subscription

## Admin Features

- Location management (Rockford/Beloit taxonomy)
- Class schedule CRUD (add/edit/cancel classes, manage instructors)
- Belt rank management (view all members by rank, promote, bulk promote)
- Promotion eligibility dashboard (students approaching thresholds)
- Attendance dashboard (today's check-ins, trends, per-member history)
- Badge configuration (define badges, view who earned what)
- SMS management (templates, send history, opt-out list, Twilio credentials)
- Kiosk mode settings (auto-logout timer, allowed check-in methods)

## Technical Requirements

- Minimum WordPress version: 7.0
- Minimum WooCommerce version: 10.3
- Minimum PHP version: 8.0
- HPOS compatible: Yes (mandatory)
- Cart & Checkout Blocks compatible: Yes (Store API extended with location context)
- Product Block Editor compatible: Yes (location attribute on products)
- Site Editor compatible: Yes (block theme compatibility)
- Store API extensions needed: Yes — location context in cart/checkout responses
- Custom database tables:
  - `{prefix}gym_ranks` — current belt rank per member per program
  - `{prefix}gym_rank_history` — full promotion audit trail
  - `{prefix}gym_attendance` — check-in records
  - `{prefix}gym_achievements` — earned badges/achievements
- External API integrations: Twilio (SMS send/receive)
- Background processing: Action Scheduler for streak calculation, badge evaluation batch jobs, and SMS queue processing

## Data & Compliance

- **Member data:** Name, email, phone, location, membership type, belt rank, attendance history, badge achievements. All stored in WordPress database (custom tables + user meta).
- **SMS data:** Phone numbers, message content, opt-in/opt-out status. TCPA compliance required — explicit opt-in before sending, immediate opt-out honored, no SMS to numbers that haven't opted in.
- **Payment data:** Handled entirely by WooPayments/Stripe — this plugin never stores card numbers, CVVs, or raw payment credentials. PCI compliance is WooPayments' responsibility.
- **GDPR:** Plugin stores personal data. Must implement data export (wp_privacy_personal_data_exporters) and data erasure (wp_privacy_personal_data_erasers) hooks. Rank history preserved for business records but anonymized on erasure request.
- **Twilio credentials:** API SID and auth token stored in wp_options with the `autoload=false` flag. Never exposed in REST API responses or client-side code.

## Out of Scope

- Payment processing (handled by WooPayments)
- Subscription billing logic (handled by WooCommerce Subscriptions)
- Content gating rules (handled by WooCommerce Memberships)
- CRM contact management (handled by Jetpack CRM)
- Email marketing (handled by MailPoet)
- Automation workflows (handled by AutomateWoo)
- AI agent orchestration (handled by gym-ai-chat plugin)
- Video hosting (handled by Jetpack VideoPress)
- Accounting (QuickBooks retained)
