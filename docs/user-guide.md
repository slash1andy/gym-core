# Haanpaa Martial Arts — Gym Management System User Guide

This is the complete reference for the Haanpaa Martial Arts gym management system. The system runs on WordPress + WooCommerce with two custom plugins: **Gym Core** (operational backbone) and **Gandalf AI Chat** (AI assistant). This guide covers every feature available to gym staff.

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Member Management](#2-member-management)
3. [Memberships and Billing](#3-memberships-and-billing)
4. [Class Schedule](#4-class-schedule)
5. [Check-In System](#5-check-in-system)
6. [Belt Ranks and Promotions](#6-belt-ranks-and-promotions)
7. [Foundations Program](#7-foundations-program)
8. [Coach Briefings](#8-coach-briefings)
9. [Gamification](#9-gamification)
10. [Communication](#10-communication)
11. [Social Media](#11-social-media)
12. [AI Assistant (Gandalf)](#12-ai-assistant-gandalf)
13. [Settings Reference](#13-settings-reference)
14. [Troubleshooting](#14-troubleshooting)

---

## 1. Getting Started

### How to Log In

1. Go to **yourgymsite.com/wp-admin** in your browser.
2. Enter your username and password.
3. You will land on the WordPress Dashboard.

Key areas you will use:
- **Classes** (left sidebar) -- manage the class schedule
- **Announcements** (left sidebar) -- create announcements for coach briefings
- **WooCommerce** -- orders, subscriptions, products, settings
- **Jetpack CRM** -- contact management and sales pipeline
- **Users** -- member profiles, rank display, Foundations status
- **Tools > Gandalf** -- AI assistant chat interface

### Overview of the System

The gym runs on a WordPress + WooCommerce stack with these integrated plugins:

| Plugin | Purpose |
|--------|---------|
| **Gym Core** | Locations, classes, check-in kiosk, belt ranks, attendance tracking, gamification, SMS, coach briefings, CRM sync |
| **Gandalf (HMA AI Chat)** | AI assistant with Sales, Coaching, Finance, and Admin personas |
| **WooCommerce Subscriptions** | Recurring membership billing |
| **WooCommerce Memberships** | Content gating and membership plan management |
| **Jetpack CRM** | Contact management, sales pipeline, activity logging |
| **AutomateWoo** | Automated email/SMS workflows triggered by gym events |
| **MailPoet** | Email marketing and newsletters |
| **Jetpack** | Social sharing (Publicize), site security, VideoPress |
| **Twilio** | SMS sending and receiving (via Gym Core integration) |

### User Roles

The system defines two custom roles in addition to the standard WordPress roles:

**Head Coach** (`gym_head_coach`)
- All gym capabilities: promote students, view ranks, check in members, view attendance, send SMS, manage curriculum, manage announcements, view briefings
- Can edit users
- Assign this role to Darby and senior instructors who need full operational access

**Coach** (`gym_coach`)
- Can promote students, view ranks, check in members, view attendance, view briefings
- Cannot send SMS, manage curriculum, or manage announcements
- Assign this role to regular class instructors

**Administrator**
- Has all gym capabilities automatically
- Full access to settings, WooCommerce, and all admin features
- For Darby, Amanda, and anyone managing the business side

**Sales / Shop Manager**
- Standard WooCommerce role with access to orders, products, and reports
- Does not have gym-specific capabilities unless added manually

#### Capability Reference

| Capability | Head Coach | Coach | Admin |
|-----------|:----------:|:-----:|:-----:|
| `gym_promote_student` | Yes | Yes | Yes |
| `gym_view_ranks` | Yes | Yes | Yes |
| `gym_check_in_member` | Yes | Yes | Yes |
| `gym_view_attendance` | Yes | Yes | Yes |
| `gym_send_sms` | Yes | No | Yes |
| `gym_manage_curriculum` | Yes | No | Yes |
| `gym_manage_announcements` | Yes | No | Yes |
| `gym_view_briefing` | Yes | Yes | Yes |

---

## 2. Member Management

### Viewing Members in Jetpack CRM

All gym contacts live in **Jetpack CRM** (left sidebar menu). The CRM is automatically populated when:

- A visitor submits a **Jetpack Form** on the website (trial class inquiry, contact form)
- A new **WooCommerce customer** registers or makes a first purchase
- Staff manually adds a contact

### Custom CRM Fields

Gym Core automatically syncs these fields to each CRM contact record:

| CRM Field | Description | Auto-Updated When |
|-----------|-------------|-------------------|
| `belt_rank` | Current belt and stripes across all programs (e.g., "Adult BJJ -- blue (2 stripes)") | Member is promoted |
| `foundations_status` | Foundations phase or clearance date | Phase progresses or student is cleared |
| `last_checkin` | Date/time of most recent class check-in | Member checks in |
| `total_classes` | Lifetime attendance count | Member checks in |

### Contact Tags and Statuses

The Form-to-CRM integration automatically applies tags:

- **`lead`** -- Applied to all new form submissions (removed on first purchase)
- **`member`** -- Applied when a lead makes their first purchase
- **`source: website-form`** -- Indicates the contact came from a web form
- **Program tags** -- e.g., `adult-bjj`, `kickboxing` based on the program interest field
- **Location tags** -- e.g., `rockford`, `beloit` based on location selection

### Sales Pipeline Stages

New leads flow through these pipeline stages in Jetpack CRM:

| Stage | Description |
|-------|-------------|
| **New Lead** | Just submitted a form or registered. Default for all new contacts. |
| **Contacted** | Staff has reached out (call, text, or email). |
| **Trial Booked** | Trial class is scheduled. |
| **Trial Done** | Trial class completed. |
| **Negotiation** | Discussing membership options. |
| **Closed Won** | Purchased a membership (auto-set on first completed WooCommerce order). |
| **Closed Lost** | Did not convert. |

**Auto-assignment:** When a new lead comes in, they are automatically assigned to the configured sales rep for their location (set under WooCommerce > Settings > Gym Core > CRM).

### CRM Activity Logging

The system automatically logs these activities on CRM contacts:

- **Outbound SMS** -- every SMS sent to a contact is logged with the Twilio SID
- **Inbound SMS** -- every SMS received from a contact is logged
- **Form submissions** -- new submissions from returning contacts are noted
- **Purchases** -- order completions are logged with product names

---

## 3. Memberships and Billing

### Membership Plans

The system supports four membership plans, each linked to a WooCommerce Subscription product:

| Plan | Product Slug | Description |
|------|-------------|-------------|
| **Adult BJJ Member** | `adult-bjj-membership` | Access to Adult BJJ classes |
| **Kids BJJ Member** | `kids-bjj-membership` | Access to Kids BJJ classes |
| **Kickboxing Member** | `kickboxing-membership` | Access to Kickboxing classes |
| **All-Access Member** | `all-access-membership` | Access to all programs at all locations |

Membership plans are automatically created in WooCommerce Memberships and linked to subscription products. The All-Access plan grants access to every program.

### How Subscriptions Work

Memberships are powered by **WooCommerce Subscriptions**:

1. A prospect purchases a subscription product on the website (filtered by their selected location).
2. WooCommerce Subscriptions handles recurring billing automatically.
3. WooCommerce Memberships grants the member access to program-specific content and features.
4. The member can manage their subscription from **My Account > Subscriptions**.

**Duplicate prevention:** If a member already has an active subscription for a product, that product is hidden from purchase (marked un-purchasable) to prevent accidental double-subscriptions.

### Handling Failed Payments

When a subscription payment fails:

1. WooCommerce Subscriptions automatically retries based on its retry rules.
2. The system can send an SMS using the **Payment Failed** template: *"Hi {first_name}, your membership payment didn't go through. Please update your payment method at {site_url}/my-account to keep your access active."*
3. An AutomateWoo workflow can be configured to send this automatically.
4. The member can update their payment method at **My Account > Payment Methods**.

To view failed payments: ask Gandalf's Finance persona to "show failed payments," or check **WooCommerce > Orders** filtered by "Failed" status.

### Upgrading / Downgrading Members

To change a member's plan:

1. Go to **WooCommerce > Subscriptions** and find the member's subscription.
2. Use WooCommerce's built-in subscription switching to change the product.
3. The membership plan will update automatically.

### Trial Classes and Drop-Ins

Trial classes are managed through the sales pipeline:
1. A prospect submits a trial inquiry form.
2. The system creates a CRM contact tagged as `lead` at the "New Lead" stage.
3. Staff follows up (the **Lead Follow-Up** SMS template is available).
4. Once the trial is booked, update the pipeline stage to "Trial Booked."
5. After the trial, move to "Trial Done" and continue the sales process.

---

## 4. Class Schedule

### Managing Classes

Classes are a custom post type (`gym_class`) found under **Classes** in the admin sidebar.

To create or edit a class:

1. Go to **Classes > Add New Class** (or click an existing class).
2. Set the **Title** (e.g., "Adult BJJ Fundamentals").
3. In the **Class Details** sidebar meta box, configure:

| Field | Description | Options |
|-------|-------------|---------|
| **Day of Week** | Which day the class meets | Monday through Sunday |
| **Start Time** | Class start (24-hour format) | e.g., 18:00 |
| **End Time** | Class end (24-hour format) | e.g., 19:30 |
| **Capacity** | Maximum students | Default: 30 (configurable globally) |
| **Instructor** | The assigned coach | Select from admin/editor users |
| **Recurrence** | How often the class repeats | Weekly, Biweekly, Monthly |
| **Status** | Whether the class is running | Active, Cancelled, Suspended |

4. Assign a **Program** (taxonomy: Adult BJJ, Kids BJJ, Kickboxing, etc.)
5. Assign a **Location** (taxonomy: Rockford, Beloit)
6. **Publish** the class.

### Viewing the Schedule

**Admin view:** The Classes list table shows custom columns for Day, Time, Instructor, Capacity, and Status. You can sort and filter by program or location.

**Public view:** Classes are publicly queryable at `/classes/` and can be filtered by program or location.

### iCal Feeds

Members can subscribe to the class schedule in their calendar app using these URLs:

| Feed URL | Description |
|----------|-------------|
| `/gym-calendar.ics` | All locations, all classes |
| `/gym-calendar/rockford.ics` | Rockford classes only |
| `/gym-calendar/beloit.ics` | Beloit classes only |

The feed:
- Includes only **active** classes (cancelled/suspended are excluded)
- Sets the timezone to **America/Chicago**
- Includes the instructor name in the event description
- Includes the physical address in the event location
- Uses iCal RRULE for recurrence (weekly, biweekly, monthly)
- Is cached for 1 hour and automatically refreshed when a class is created, updated, or deleted

**To enable/disable:** WooCommerce > Settings > Gym Core > Schedule > "iCal feed" checkbox.

### Schedule Change Notifications

When a class is cancelled or rescheduled, use the **Schedule Change** SMS template:

*"Heads up {first_name} -- {class_name} at {location} has been {change_type}. Check the updated schedule at {site_url}/classes"*

This can be sent manually or via an AutomateWoo workflow.

---

## 5. Check-In System

### Kiosk Mode

The check-in kiosk is a full-screen, touch-optimized page designed for a tablet at the gym entrance.

**URL:** `/check-in/`

The kiosk is a standalone page (no theme header/footer) that provides:
1. **Search screen** -- member types their name to find themselves
2. **Class selection screen** -- member picks which class they are attending
3. **Success screen** -- confirmation with their name and current rank displayed
4. **Error screen** -- if check-in fails (no membership, already checked in, etc.)

**Requirements:**
- A staff member must be logged in on the tablet for API authentication
- The kiosk auto-resets to the search screen after a configurable timeout (default: 10 seconds)
- The location is determined from the logged-in staff member's `gym_location` user meta, or the `gym_location` cookie, defaulting to "rockford"

### Check-In Methods

Three check-in methods are available (all enabled by default):

| Method | Description |
|--------|-------------|
| **QR Code** | Member scans a personal QR code (future enhancement) |
| **Name Search** | Member types their name and selects from results |
| **Manual (Staff)** | Staff checks in a member from the admin dashboard |

Configure allowed methods under WooCommerce > Settings > Gym Core > Attendance.

### Check-In Validation

Before a check-in is recorded, the system validates:

1. **Member exists** -- the user ID must be valid
2. **Active membership** -- the member must have an active WooCommerce Membership (or any active subscription). If WooCommerce Memberships is not installed, this check is skipped.
3. **No duplicate check-in** -- if enabled, a member cannot check in to the same class twice in one day
4. **Valid class** -- if a specific class is selected, it must exist and have "active" status
5. **Valid location** -- a location must be provided

### Quick Check-In from the Dashboard

The admin Attendance Dashboard provides a quick check-in form for staff to manually check in members without using the kiosk.

### Viewing Attendance History

Attendance records can be viewed in several ways:

- **Per-member:** On the user profile page under the rank section, click "View History"
- **Admin dashboard:** The Attendance Dashboard shows today's check-ins by location, filterable by class
- **REST API:** `GET /gym/v1/attendance/{user_id}` with optional date range filters
- **Weekly trends:** The system tracks attendance grouped by week for trend reporting

### Attendance Trends and At-Risk Members

The system identifies at-risk members through:

- **Weekly trend data:** Attendance counts grouped by week for the last 12 weeks
- **Days since last class:** Calculated for each member and surfaced in coach briefings
- **Absence threshold:** Members absent for 14+ days (configurable) trigger alerts in coach briefings
- **Re-engagement SMS:** Templates for 30-day, 60-day, and 90-day inactivity outreach

---

## 6. Belt Ranks and Promotions

### Rank System Overview

The system tracks three martial arts programs:

**Adult BJJ** (IBJJF graduation system)

| Belt | Color | Stripes | Type |
|------|-------|---------|------|
| White Belt | #ffffff | 4 | Belt |
| Blue Belt | #1e40af | 4 | Belt |
| Purple Belt | #7c3aed | 4 | Belt |
| Brown Belt | #78350f | 4 | Belt |
| Black Belt | #000000 | 10 | Degree |

**Kids BJJ** (IBJJF youth graduation system -- 13 belts, 4 stripes each)

| Belt | Color |
|------|-------|
| White | #ffffff |
| Grey/White | #d1d5db |
| Grey | #9ca3af |
| Grey/Black | #6b7280 |
| Yellow/White | #fef3c7 |
| Yellow | #fbbf24 |
| Yellow/Black | #b45309 |
| Orange/White | #fed7aa |
| Orange | #f97316 |
| Orange/Black | #c2410c |
| Green/White | #bbf7d0 |
| Green | #22c55e |
| Green/Black | #15803d |

At age 16, kids transition to the adult rank system.

**Kickboxing** (simple two-level system, no stripes)

| Level | Color |
|-------|-------|
| Level 1 | #3b82f6 |
| Level 2 | #ef4444 |

### Stripe Progression and Belt Promotion

- **Stripes** are added one at a time within a belt level (e.g., White Belt 0 stripes -> 1 stripe -> 2 stripes -> 3 stripes -> 4 stripes).
- After reaching maximum stripes, the next promotion moves to the next belt at 0 stripes.
- **Black Belt** uses degrees instead of stripes (up to 10 degrees).
- **Kickboxing** has no stripes -- students are simply at Level 1 or Level 2.

To add a stripe or promote a member:

1. Go to **Users** and edit the member's profile.
2. Scroll to the **Belt Rank & Programs** section.
3. Use the Promotion Dashboard (**admin.php?page=gym-core-promotions**) or the REST API.

Every rank change is recorded in the **rank history** table for a complete audit trail including: who was promoted, from what belt/stripe, to what belt/stripe, who performed the promotion, date, and optional notes.

### Promotion Eligibility Criteria

Each belt has configurable thresholds for **minimum days at current rank** and **minimum classes since last promotion**:

**Adult BJJ Default Thresholds:**

| Belt | Min Days | Min Classes |
|------|----------|-------------|
| White | 25 | 17 |
| Blue | 500 | 225 |
| Purple | 700 | 400 |
| Brown | 700 | 400 |
| Black | 700 | 400 |

**Kids BJJ Default Thresholds:**

| Belt | Min Days | Min Classes |
|------|----------|-------------|
| White | 0 | 0 |
| Grey/White | 230 | 48 |
| All others | 340 | 64 |
| Green | 300 | 60 |
| Green/Black | 300 | 60 |

**Kickboxing Default Thresholds:**

| Level | Min Days | Min Classes |
|-------|----------|-------------|
| Level 1 | 0 | 0 |
| Level 2 | 500 | 200 |

All thresholds are editable under **WooCommerce > Settings > Gym Core > Ranks**.

A member is **eligible for promotion** when they meet ALL of these criteria:
1. Days at current rank >= minimum days required
2. Classes since last promotion >= minimum classes required
3. Coach recommendation recorded (if "Require coach recommendation" is enabled)
4. Not currently enrolled in Foundations (Adult BJJ only)

### Coach Recommendation Workflow

1. A coach observes that a student is performing at the next level.
2. The coach sets a recommendation via the Promotion Dashboard or REST API (`POST /gym/v1/promotions/recommend`).
3. The recommendation is stored as user meta (`_gym_coach_recommendation_{program}`).
4. When the head coach or instructor reviews the Promotion Dashboard, recommended students are flagged.
5. After promotion (or rejection), the recommendation is cleared.

**To disable the recommendation requirement:** Uncheck "Require coach recommendation" under WooCommerce > Settings > Gym Core > Ranks.

### Bulk Promotion for Belt Testing Events

The Promotion Dashboard at **admin.php?page=gym-core-promotions** lists all members who are eligible or approaching eligibility for each program. This view is designed for belt testing events where multiple students may be promoted at once.

The list shows:
- Member name, current belt, stripes
- Whether they are fully eligible or approaching (80%+ of thresholds)
- Attendance count vs. required
- Days at rank vs. required
- Whether they have a coach recommendation
- The next belt they would be promoted to

### Viewing Rank on User Profiles

On any user's profile page (Users > Edit User), the **Belt Rank & Programs** section shows:

- A colored circle representing their current belt
- Belt name with stripe indicator dots (filled = earned, empty = remaining)
- Black Belt shows degree count instead of stripe dots
- Date of last promotion with relative time (e.g., "45 days ago")
- Attendance count since last promotion
- Links to "View History" and "View Eligibility"

---

## 7. Foundations Program

### What Foundations Is

Foundations is a **safety gate** for new Adult BJJ students. It ensures that beginners demonstrate basic competence before they are allowed to train with non-coaches in live rolling. Foundations is NOT a belt -- it is an operational status. Time spent in Foundations counts toward White Belt stripe progression.

### Three Phases

| Phase | Requirement | What Happens |
|-------|-------------|-------------|
| **Phase 1: Coached Instruction** | Complete 10 classes (default) | Student attends regular classes with coached instruction only |
| **Phase 2: Coach Rolls** | Complete 2 supervised rolls with coaches (default) | After Phase 1 classes, coaches roll with the student and evaluate their safety |
| **Phase 3: Continued Training** | Reach 25 total classes (default) | Student continues training. All Phase 1 + Phase 3 classes count toward the total |
| **Cleared** | Meets all requirements | Coach clears the student. They can now live train with all partners |

Default values are configurable in Settings (see Section 13).

### Enrolling a Student

New Adult BJJ students are enrolled in Foundations through the REST API or by calling the `FoundationsClearance::enroll()` method. Once enrolled, their status is tracked in user meta (`_gym_foundations_status`).

### Recording Coach Rolls

Coach rolls can be recorded in two ways:

**From the User Profile:**
1. Go to **Users > Edit User** for the Foundations student.
2. In the **Foundations Program** section, click **"Record Coach Roll"**.
3. Enter optional notes about the roll session.
4. Click **"Save Coach Roll"**.

**Via REST API:** `POST /gym/v1/foundations/coach-roll` with `user_id` and optional `notes`.

Each coach roll records: the coach who supervised, the date, and any notes.

### Clearing Students

**From the User Profile:**
1. On the student's profile, the Foundations section shows a **"Clear Student"** button (only visible to users with `gym_promote_student` capability).
2. Click the button, then confirm with **"Yes, Clear"**.
3. The student's status changes to "Cleared" with a green checkmark.

**Via REST API:** `POST /gym/v1/foundations/{user_id}/clear`

After clearance:
- The student receives an email and SMS notification (if enabled)
- Their CRM contact is updated with "Cleared (date)"
- The `gym_core_foundations_cleared` action fires for AutomateWoo workflows

### Foundations in Briefings and Dashboards

- **Coach Briefings:** Foundations students appear as **Priority 1 alerts**. Phase 2 students (needing coach rolls) get the highest alert with specific instructions.
- **User Profile:** Shows an amber-bordered card with phase, class count progress, and coach roll history.
- **Promotion Eligibility:** Students in Foundations are excluded from promotion eligibility reports.
- **Member Dashboard (My Account):** Members see their Foundations progress with phase label, class count, and coach roll count.

---

## 8. Coach Briefings

### What a Briefing Contains

A coach briefing is a pre-class intelligence report generated for a specific class. It contains:

**1. Class Identity**
- Class name, program, location
- Day of week, start/end time
- Assigned instructor

**2. Forecasted Student Roster**
Based on the last 4 weeks of attendance patterns for this specific class. Students who attended at least 2 of the last N instances (50%+ attendance rate) are included. For each student:
- Name and current rank
- Foundations status (if applicable)
- Days since last class
- Total lifetime classes
- Whether this is their first class ever
- Promotion eligibility data (if approaching or eligible)
- Medical/injury notes
- Attendance rate for this class

**3. Prioritized Alerts**

| Priority | Alert Type | Detail |
|----------|-----------|--------|
| 1 | Foundations Coach Roll | Student in Phase 2 needs a supervised roll |
| 1 | Foundations (other) | Student in Foundations with phase and class count |
| 2 | First Timer | First class ever -- welcome, orient, pair with safe partner |
| 3 | Returning After Absence | 14+ days away (configurable) -- may need to ease back in |
| 4 | Medical / Injury | Medical notes from user meta |
| 5 | Promotion Candidate | Eligible or approaching promotion eligibility |

**4. Announcements**
Active announcements matching the class's location and/or program, with pinned announcements listed first.

### How to View Briefings

- **REST API:** `GET /gym/v1/briefings/class/{class_id}` for a single class, or `GET /gym/v1/briefings/today?location=rockford` for all of today's classes
- **Gandalf AI:** Ask the Coaching or Admin persona: "What's my briefing for tonight's class?" or "Show today's briefings for Rockford"
- **Enable/Disable:** The briefing system is on by default. It can be toggled via the `gym_core_briefing_enabled` option.

### Creating Announcements

Announcements are a custom post type (`gym_announcement`) found under **Announcements** in the admin sidebar.

To create an announcement:

1. Go to **Announcements > Add New Announcement**.
2. Set the **Title** and **Content** (the message to communicate).
3. In the **Announcement Details** sidebar:

| Field | Description | Options |
|-------|-------------|---------|
| **Type** | Scope of the announcement | Global (all), Specific location, Specific program |
| **Target Location** | Which location (if type is "location") | Location slug, e.g., "rockford" |
| **Target Program** | Which program (if type is "program") | Program slug, e.g., "adult-bjj" |
| **Start Date** | When the announcement becomes active | Date picker (leave empty for immediate) |
| **End Date** | When the announcement expires | Date picker (leave empty for no expiry) |
| **Pinned** | Sticky at top of briefings | Checkbox |

4. **Publish** the announcement.

The announcement list table shows custom columns: Type, Target, Active Dates, and Pinned status.

---

## 9. Gamification

### Badges and How They Are Earned

Badges are automatically awarded when members meet criteria. Once earned, a badge is never removed.

**Attendance Badges:**

| Badge | Criteria |
|-------|----------|
| First Class | Check in to any class (1 total) |
| 10 Classes | Reach 10 total check-ins |
| 25 Classes | Reach 25 total check-ins |
| 50 Classes | Reach 50 total check-ins |
| Century Club | Reach 100 total check-ins |
| 250 Classes | Reach 250 total check-ins |
| 500 Club | Reach 500 total check-ins |

**Streak Badges:**

| Badge | Criteria |
|-------|----------|
| 4-Week Streak | 4 consecutive weeks with at least 1 check-in |
| 12-Week Streak | 12 consecutive weeks with at least 1 check-in |
| 26-Week Streak | 26 consecutive weeks with at least 1 check-in |

**Rank Badges:**

| Badge | Criteria |
|-------|----------|
| Belt Promotion | Promoted to a new belt (not awarded for stripe additions within the same belt) |

**Special Badges:**

| Badge | Criteria |
|-------|----------|
| Early Bird | 10 check-ins to the first class of the day |
| Cross-Trainer | Attended classes in 2 or more different programs |

Badges are **not** retroactively awarded for imported historical data -- only real-time check-ins trigger badge evaluation.

### Attendance Streaks

A streak counts **consecutive calendar weeks** (Monday-Sunday) with at least one check-in.

- **Current streak:** The number of consecutive weeks ending with the current or previous week
- **Longest streak:** The all-time longest streak for the member
- **Streak status:** Active (currently attending), Frozen (freeze applied), or Broken

**Streak Freezes:**
- Members get a limited number of streak freezes per quarter (default: 1, configurable 0-4).
- A freeze preserves the streak through one missed week.
- Freezes reset at the start of each calendar quarter.
- Applied via REST API (`POST /gym/v1/members/{user_id}/streak/freeze`).

### Milestone Celebrations

Attendance milestones fire a special event when reached:

**Default milestones:** 10, 25, 50, 100, 150, 200, 250, 300, 500, 1000 classes

When a member hits a milestone:
- The `gym_core_attendance_milestone` action fires
- This is registered as an AutomateWoo trigger ("Gym -- Attendance Milestone")
- You can build AutomateWoo workflows to send congratulatory emails or SMS on any milestone

Milestones are tracked per-user and will not fire twice for the same threshold. Imported historical records do not trigger milestones.

**Customizing milestones:** Under WooCommerce > Settings > Gym Core > Attendance, enter a comma-separated list of class counts in the "Attendance milestones" field.

### Targeted Content (Content Gating)

Members see different content based on their membership plan:

- **Technique videos:** Require membership in the matching program (e.g., Adult BJJ technique videos require an Adult BJJ or All-Access membership)
- **Training resources:** Available to any active member regardless of program
- **All-Access members** can see all gated content

---

## 10. Communication

### SMS Templates

The system includes 12 predefined SMS templates with placeholder variables:

| Template | When to Use | Message |
|----------|------------|---------|
| **Lead Follow-Up** | After trial class inquiry | "Hey {first_name}! Thanks for your interest in {location}. Ready to try a free class? Reply YES and we'll get you scheduled." |
| **Class Reminder** | 24 hours before class | "Hey {first_name}, reminder: {class_name} is tomorrow at {time} at {location}. See you on the mats!" |
| **Schedule Change** | Class cancelled/rescheduled | "Heads up {first_name} -- {class_name} at {location} has been {change_type}. Check the updated schedule at {site_url}/classes" |
| **Payment Failed** | Subscription payment fails | "Hi {first_name}, your membership payment didn't go through. Please update your payment method at {site_url}/my-account to keep your access active." |
| **Belt Promotion** | Member promoted | "Congratulations {first_name}! You've been promoted to {belt} in {program}. Keep up the amazing work!" |
| **Birthday** | Member's birthday | "Happy birthday {first_name}! From your Haanpaa family. Come celebrate with a class on us this week!" |
| **Badge Earned** | Badge awarded | "Nice work {first_name}! You just earned the \"{badge_name}\" badge. Check your achievements at {site_url}/my-account" |
| **Streak Reminder** | Weekly for active streaks | "You're on a {streak_count}-week streak {first_name}! Keep it going -- get to class this week." |
| **Streak Broken** | Streak ends | "Hey {first_name}, your {streak_count}-week streak ended. No worries -- come back and start a new one! We miss you at {location}." |
| **Re-Engage (30 days)** | 30 days inactive | "Hey {first_name}, we haven't seen you in a while! Your spot on the mats is waiting. Come train with us this week at {location}." |
| **Re-Engage (60 days)** | 60 days inactive | "{first_name}, it's been 2 months. Your training partners miss you! Reply to chat about getting back on track." |
| **Re-Engage (90 days)** | 90 days inactive | "{first_name}, we'd love to have you back at Haanpaa. Reply for a special offer to restart your training." |

**Available placeholders:** `{first_name}`, `{location}`, `{class_name}`, `{time}`, `{change_type}`, `{belt}`, `{program}`, `{badge_name}`, `{streak_count}`, `{site_url}`, `{milestone_count}`

### Sending SMS to Members

SMS messages can be sent via:

1. **Gandalf Sales persona** -- use the `draft_sms` tool (queued for staff approval before sending)
2. **AutomateWoo workflows** -- use the "Send SMS (Twilio)" custom action
3. **REST API** -- `POST /gym/v1/sms/send` with phone number and message body or template slug
4. **CRM SMS Bridge** -- send templated SMS to a CRM contact by contact ID

**Rate limiting:** Each contact can receive a maximum of 1 SMS per hour (configurable 1-10 under Settings > SMS).

### Inbound SMS Handling

The system receives incoming SMS via a Twilio webhook at `POST /gym/v1/sms/webhook`.

**Automatic handling:**
- **Opt-out keywords** (STOP, UNSUBSCRIBE, CANCEL, QUIT, END): Immediately unsubscribes the contact and replies with confirmation. This is required for TCPA compliance.
- **Opt-in keyword** (START): Re-subscribes the contact.
- **All other messages:** The `gym_core_sms_received` action fires, and the message is logged as a CRM activity on the matched contact.

### Email via MailPoet

Email marketing and newsletters are handled by MailPoet (separate from this system). Gym Core does not manage email campaigns directly.

### AutomateWoo Workflows

Gym Core registers four custom AutomateWoo triggers and one custom action:

**Custom Triggers:**

| Trigger | Fires When | Data Available |
|---------|-----------|----------------|
| **Gym -- Belt Promotion** | A member's belt rank changes (promotion or stripe) | Customer, user_id, program, new_belt, new_stripes |
| **Gym -- Foundations Cleared** | A student completes the Foundations program | Customer, user_id |
| **Gym -- Class Check-In** | A member checks in to a class | Customer, user_id, location, class_id |
| **Gym -- Attendance Milestone** | A member reaches a class count milestone | Customer, user_id, milestone_count |

**Custom Action:**

| Action | Description |
|--------|-------------|
| **Send SMS (Twilio)** | Sends SMS using a predefined template or custom message. Resolves the customer's billing phone number automatically. Supports AutomateWoo variable substitution in custom messages. |

**Example workflows you can build:**

1. Belt promotion -> Send congratulatory email + SMS
2. Foundations cleared -> Send welcome-to-live-training email
3. Check-in -> (with condition: first ever) -> Send welcome SMS
4. Attendance milestone (100 classes) -> Send celebration email with coupon
5. Subscription renewal failed -> Send payment failed SMS after 24 hours
6. Member inactive 30 days -> Send re-engagement SMS
7. Member inactive 60 days -> Send escalated re-engagement SMS

---

## 11. Social Media

### Auto-Sharing Belt Promotion Posts

When a member earns a **new belt** (not just a stripe), the system automatically creates a published blog post in the "Promotions" category. **Jetpack Publicize** then auto-shares this post to all connected social media accounts (Facebook, Instagram, etc.).

The post includes:
- **Title:** "Congratulations to [Name] on earning their [Belt]!"
- **Content:** A celebratory paragraph mentioning the student, belt, program, and gym name

**Controls:**
- Enable/disable under **WooCommerce > Settings > Gym Core > Ranks > "Auto-create promotion posts"**
- Only fires for belt changes (White to Blue, Blue to Purple, etc.), not stripe additions
- Posts are created as "publish" status, triggering immediate Publicize sharing

### Gandalf-Suggested Social Posts

The AI assistant can draft social media posts for review:

1. Ask Gandalf's Admin persona to create a social post (e.g., "Draft a social post about our upcoming belt testing event").
2. Gandalf uses the `draft_social_post` tool to create a **pending** blog post.
3. The post is NOT published or shared -- it sits in "Pending" status.

### Approval Workflow for Social Posts

1. View pending social posts at `GET /gym/v1/social/pending` or in the WordPress Posts admin (filter by Pending status).
2. Review the post content. Edit if needed.
3. **Approve and publish** via `POST /gym/v1/social/{post_id}/approve` or by changing the post status to "Publish" in the editor.
4. Once published, Jetpack Publicize automatically shares to connected social accounts.

Each social post tracks:
- `_gym_social_post` -- marks it as an AI-suggested social post
- `_gym_suggested_by` -- who/what suggested it ("gandalf" or a user ID)
- `_gym_approved_by` -- who approved publication

---

## 12. AI Assistant (Gandalf)

Gandalf is the staff-facing AI assistant, accessible at **Tools > Gandalf** in the admin menu. It uses the WordPress AI API to process requests and can execute actions through the gym/v1 REST API.

### Four Personas

| Persona | Icon | Access Required | Purpose |
|---------|------|----------------|---------|
| **Sales** | Business case | `edit_posts` | Membership inquiries, pricing, schedules, lead follow-up |
| **Coaching** | Martial arts | `edit_posts` | Training advice, rank lookups, briefings, Foundations management |
| **Finance** | Money | `manage_options` | Revenue reports, subscription status, failed payments |
| **Admin** | Gear | `manage_options` | Attendance overview, schedule, promotions, announcements, social posts |

### What Each Persona Can Do

**Sales Agent:**
- Look up pricing and products by location
- Retrieve the class schedule
- List gym locations
- Draft SMS messages (requires approval)
- Get trial-related SMS templates

**Coaching Agent:**
- Look up a member's current rank
- View full promotion history
- Check attendance records
- View earned badges
- Check attendance streaks
- View class schedule
- Recommend a member for promotion (requires approval)
- Generate coach briefings for specific classes
- Check Foundations status
- Record coach rolls (requires approval)

**Finance Agent:**
- Get revenue summaries (by week, month, quarter, year, or custom range)
- List subscriptions (filter by status)
- View failed payment orders
- Access WooCommerce analytics reports

**Admin Agent:**
- View today's attendance (all locations or filtered)
- View class schedule
- List promotion-eligible members by program
- Draft announcements (requires approval)
- Draft social media posts (requires approval)
- Get briefings for all of today's classes

### Tool Reference by Persona

**Sales tools:** `get_pricing`, `get_schedule`, `get_locations`, `draft_sms`, `get_trial_info`

**Coaching tools:** `get_member_rank`, `get_rank_history`, `get_attendance`, `get_badges`, `get_streak`, `get_schedule`, `flag_promotion`, `get_briefing`, `get_foundations_status`, `record_coach_roll`

**Finance tools:** `get_revenue_summary`, `get_subscriptions`, `get_failed_payments`, `get_reports`

**Admin tools:** `get_today_attendance`, `get_schedule`, `get_promotion_eligible`, `draft_announcement`, `draft_social_post`, `get_briefing_today`

### Read vs. Write Tools

- **Read tools** execute immediately via internal REST dispatch (no HTTP overhead). They return data directly to the AI conversation.
- **Write tools** (marked with `write: true`) are NEVER executed immediately. They are queued as **pending actions** that require staff approval.

Write tools include: `draft_sms`, `flag_promotion`, `record_coach_roll`, `draft_announcement`, `draft_social_post`

### Pending Action Approval Queue

When Gandalf calls a write tool:

1. The action is saved to the `hma_ai_pending_actions` database table with status "pending."
2. Gandalf reports back: "Action has been queued for staff approval."
3. Staff reviews pending actions via the Action Endpoint API or admin interface.

**Approval options:**
- **Approve** -- executes the action immediately
- **Approve with Changes** -- staff provides instructions; the agent re-executes with modifications
- **Reject** -- action is cancelled with an optional reason

**Lifecycle statuses:** pending -> approved / approved_with_changes / rejected -> completed

### Conversation Retention

Conversations are stored in a custom database table and automatically purged after 30 days via a daily WP-Cron job.

---

## 13. Settings Reference

All settings are found under **WooCommerce > Settings > Gym Core**. Settings are organized into sections accessible via sub-tabs.

### General

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Gamification | `gym_core_gamification_enabled` | Yes | Enable badges, streaks, and achievement tracking |
| SMS notifications | `gym_core_sms_enabled` | No | Enable Twilio SMS integration |
| REST API | `gym_core_api_enabled` | No | Enable gym/v1 REST API endpoints for AI agents |

### Locations

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Require location selection | `gym_core_require_location` | Yes | Show location selector banner until visitor picks a location |
| Filter products by location | `gym_core_filter_products_by_location` | Yes | Only show products assigned to the selected location |

Locations themselves are managed as taxonomy terms under **Products > Gym Locations**. The system seeds "Rockford" and "Beloit" on activation.

### Schedule

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Default class capacity | `gym_core_default_class_capacity` | 30 | Maximum students per class (overridable per class) |
| Enable waitlist | `gym_core_waitlist_enabled` | Yes | Allow students to join a waitlist when class is full |
| iCal feed | `gym_core_ical_enabled` | Yes | Enable subscribable iCal calendar feeds |

### Ranks

**Promotion Rules:**

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Require coach recommendation | `gym_core_require_coach_recommendation` | Yes | Promotions require a coach recommendation before approval |
| Notify on promotion | `gym_core_notify_on_promotion` | Yes | Send SMS and email when a member is promoted |
| Auto-create promotion posts | `gym_core_auto_promotion_posts` | Yes | Publish a celebratory blog post on belt promotion (shared via Jetpack Publicize) |

**Foundations Clearance (Adult BJJ):**

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Enable foundations gate | `gym_core_foundations_enabled` | Yes | Require Foundations before live training |
| Phase 1 classes | `gym_core_foundations_phase1_classes` | 10 | Classes before first coach roll evaluation |
| Coach rolls required | `gym_core_foundations_coach_rolls_required` | 2 | Supervised rolls needed after Phase 1 |
| Total classes to clear | `gym_core_foundations_total_classes` | 25 | Total classes (Phase 1 + Phase 3) to clear Foundations |

**Adult BJJ Promotion Thresholds:**

| Setting | Option Key | Default |
|---------|-----------|---------|
| White Belt -- Min Days | `gym_core_threshold_adult_bjj_white_days` | 25 |
| White Belt -- Min Classes | `gym_core_threshold_adult_bjj_white_classes` | 17 |
| Blue Belt -- Min Days | `gym_core_threshold_adult_bjj_blue_days` | 500 |
| Blue Belt -- Min Classes | `gym_core_threshold_adult_bjj_blue_classes` | 225 |
| Purple Belt -- Min Days | `gym_core_threshold_adult_bjj_purple_days` | 700 |
| Purple Belt -- Min Classes | `gym_core_threshold_adult_bjj_purple_classes` | 400 |
| Brown Belt -- Min Days | `gym_core_threshold_adult_bjj_brown_days` | 700 |
| Brown Belt -- Min Classes | `gym_core_threshold_adult_bjj_brown_classes` | 400 |

**Kids BJJ Promotion Thresholds:**

| Setting | Option Key | Default |
|---------|-----------|---------|
| Default -- Min Days | `gym_core_threshold_kids_bjj_default_days` | 340 |
| Default -- Min Classes | `gym_core_threshold_kids_bjj_default_classes` | 64 |
| White Belt -- Min Days | `gym_core_threshold_kids_bjj_white_days` | 0 |
| White Belt -- Min Classes | `gym_core_threshold_kids_bjj_white_classes` | 0 |

**Kickboxing Level Thresholds:**

| Setting | Option Key | Default |
|---------|-----------|---------|
| Level 2 -- Min Days | `gym_core_threshold_kickboxing_level2_days` | 500 |
| Level 2 -- Min Classes | `gym_core_threshold_kickboxing_level2_classes` | 200 |

### Attendance

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Check-in methods | `gym_core_checkin_methods` | QR, Search, Manual | Allowed check-in methods on the kiosk (multiselect) |
| Kiosk auto-logout | `gym_core_kiosk_timeout` | 10 seconds | Seconds of inactivity before kiosk resets (5-60) |
| Prevent duplicate check-ins | `gym_core_prevent_duplicate_checkin` | Yes | Block same member from checking into same class twice per day |
| Attendance milestones | `gym_core_attendance_milestones` | (empty = defaults) | Comma-separated class counts triggering milestone events. Default: 10, 25, 50, 100, 150, 200, 250, 300, 500, 1000 |

### Gamification

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Streak freeze allowance | `gym_core_streak_freezes_per_quarter` | 1 | Number of streak freezes per quarter (0-4). Set to 0 to disable freezes. |
| Notify on badge earned | `gym_core_notify_on_badge` | Yes | Send SMS and email when a member earns a badge |

### SMS

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Twilio Account SID | `gym_core_twilio_account_sid` | (empty) | Your Twilio Account SID (starts with AC) |
| Twilio auth token | `gym_core_twilio_auth_token` | (empty) | Your Twilio auth token (hidden after save) |
| Twilio phone number | `gym_core_twilio_phone_number` | (empty) | Your Twilio phone number in E.164 format (+1XXXXXXXXXX) |
| Rate limit | `gym_core_sms_rate_limit` | 1 | Maximum SMS per contact per hour (1-10) |

### CRM

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Enable form-to-CRM | `gym_core_crm_enabled` | No | Auto-create CRM contacts from Jetpack Forms and WooCommerce signups |
| Rockford sales rep | `gym_core_crm_rockford_rep` | Auto (first admin) | User to auto-assign Rockford leads to |
| Beloit sales rep | `gym_core_crm_beloit_rep` | Auto (first admin) | User to auto-assign Beloit leads to |
| Default pipeline stage | `gym_core_crm_default_pipeline_stage` | New Lead | Pipeline stage for new leads |

### API

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Require authentication | `gym_core_api_require_auth` | Yes | Require Application Password or JWT for all API requests |

---

## 14. Troubleshooting

### Common Issues and Solutions

**Kiosk won't load / redirects to login**
- The kiosk at `/check-in/` requires a logged-in staff member. Log in on the tablet browser first, then navigate to `/check-in/`.
- If you get a 404, go to **Settings > Permalinks** and click "Save Changes" to flush rewrite rules.

**iCal feed returns 404**
- Go to **Settings > Permalinks** and click "Save Changes" to flush rewrite rules.
- Verify that the iCal feed is enabled under WooCommerce > Settings > Gym Core > Schedule.

**Member cannot check in -- "No active membership found"**
- The member needs an active WooCommerce Subscription or Membership. Check their subscription status under WooCommerce > Subscriptions.
- If WooCommerce Memberships is not installed, the membership check is skipped entirely.

**SMS not sending**
- Verify SMS is enabled under WooCommerce > Settings > Gym Core (General tab).
- Check that Twilio credentials are configured under the SMS tab.
- Check the rate limit -- the contact may have been sent an SMS within the last hour.
- Verify the phone number is in E.164 format (+1XXXXXXXXXX for US numbers).

**Badges not being awarded**
- Badges are only awarded for real-time check-ins, not imported historical data (check-in method = "imported").
- Verify gamification is enabled under WooCommerce > Settings > Gym Core (General tab).

**Milestones not firing**
- Same as badges -- milestones skip imported records.
- Check that milestone thresholds are configured (or leave empty for defaults).

**Promotion eligibility shows "not eligible" despite meeting thresholds**
- The member may be in Foundations (Adult BJJ). Foundations students are excluded from promotion eligibility.
- Check if coach recommendation is required and whether one has been set.
- Verify the thresholds in Settings match your expectations.

**Coach briefing returns empty roster**
- The briefing uses the last 4 weeks of attendance data for the specific class. A new class with no attendance history will have an empty roster.
- Verify the class has the correct program and location taxonomies assigned.

**CRM contacts not being created from forms**
- Verify CRM integration is enabled: WooCommerce > Settings > Gym Core > CRM > "Enable form-to-CRM."
- Jetpack CRM must be installed and active.
- The form must include an email field (required -- no email means no contact).

**Gandalf AI not available**
- The AI assistant requires the WordPress AI API (`wp_ai_client_prompt` function). If this function is not available, the plugin silently deactivates its features.
- Check that the HMA AI Chat plugin is activated.
- The Finance and Admin personas require `manage_options` capability (Administrators only).

**Location selector not showing / products not filtering**
- Check WooCommerce > Settings > Gym Core > Locations. Both "Require location selection" and "Filter products by location" should be enabled.
- Verify products are assigned to location taxonomy terms (Products > Gym Locations).

**Rank not showing on user profile**
- The member must have at least one rank record in the database. New members who have never been assigned a rank will not show the Belt Rank section.
- Verify the rank was assigned via the Promotion Dashboard or REST API.

**AutomateWoo triggers not firing**
- AutomateWoo must be installed and active.
- The trigger classes guard themselves with `class_exists()` checks -- if AutomateWoo is missing, triggers simply do not register.
- Check that the workflow is active and its conditions match.

**Social promotion posts not being shared**
- Verify "Auto-create promotion posts" is enabled in Settings > Ranks.
- Jetpack Publicize must be connected to your social accounts. Check Jetpack > Sharing > Publicize.
- Posts are only created for belt changes, not stripe additions.

---

*This guide covers Gym Core v3.x and HMA AI Chat v0.2.x. For technical implementation details, see the source code in `plugins/gym-core/src/` and `plugins/hma-ai-chat/src/`.*
