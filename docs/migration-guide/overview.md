# Migration Overview -- What's Changing and Why

A plain-language guide for Haanpaa Martial Arts staff on the move from our
current toolset to the new WordPress/WooCommerce system.

---

## Why We're Making This Change

Right now the business runs on 11+ separate platforms that don't talk to each
other. Here is what that costs us every day:

- **Cost.** The current stack runs roughly $1,800-2,100+ per month. Spark
  Membership alone is over $1,000/mo when you add both locations plus payment
  processing fees and batch SMS charges.
- **Duplicate tools.** Spark and GoHighLevel both handle CRM, SMS, and lead
  management. We pay for the same capability twice and still have to pick which
  system is "the real one."
- **Data silos.** Nothing syncs automatically between GoHighLevel and Spark.
  Member info updated in one place doesn't show up in the other. Attendance,
  billing, and lead status live in completely separate dashboards.
- **No unified reporting.** Pulling numbers for the weekly meeting means logging
  into multiple dashboards, exporting CSVs, and stitching things together
  manually.
- **Two Spark instances.** Rockford and Beloit each have their own Spark
  account. There is no cross-location view of membership, attendance, or
  revenue.

The new system puts everything -- membership billing, CRM, attendance, belt
ranks, SMS, website, and reporting -- into one place with AI assistants built in
to help every role on staff.

---

## What's Being Replaced

| Old Tool | Approx. Monthly Cost | Replaced By |
|---|---|---|
| Spark Membership (Rockford $239 + Beloit $199 + $500-700 processing + $99/batch SMS) | ~$1,037-1,137 | WooCommerce Subscriptions + Gym Core plugin + WooPayments + Twilio |
| GoHighLevel | ~$270 | Jetpack CRM + AutomateWoo + Gandalf AI |
| Wix | $74 | WordPress on Pressable |
| USAePay / Pitbull Processing (included in Spark fees) | included above | WooPayments (Stripe) |
| Vimeo | ~$15 | Jetpack VideoPress |
| Dropbox | $12 | Google Drive (consolidate with existing Workspace) |

---

## What Stays the Same

These tools are not changing. Keep using them exactly as you do today.

- **QuickBooks** for accounting (Joy's primary tool)
- **Google Workspace** -- Gmail, Drive, Calendar
- **Google Sheets** for staff scheduling
- **Social media profiles** (Facebook, Instagram, etc.)
- **Combat Corner / Amazon** for equipment and supply purchasing

---

## What's New (Didn't Exist Before)

The new system doesn't just replace the old tools -- it adds capabilities we
have never had:

- **Gandalf AI assistant** with four personas: Sales, Coaching, Finance, and
  Admin. Each one knows the gym data and can draft messages, pull reports, and
  suggest actions.
- **Automated badge and streak tracking** for members -- attendance milestones,
  consecutive-week streaks, and special achievements are tracked automatically.
- **Foundations safety program** for new Adult BJJ students, with phased
  progression before they join the general class.
- **Coach briefings** before each class -- the Coaching agent summarizes who is
  attending, flags new students, and notes anyone returning from absence.
- **Targeted content** that changes based on a member's belt rank, program, and
  location, so they only see what is relevant to them.
- **Promotion eligibility dashboard** with auto-calculated requirements (classes
  attended, time at rank, stripe minimums) and bulk promote capability.
- **Social media auto-posting** when a student gets promoted -- celebration
  posts go out through Jetpack Publicize without manual effort.
- **One dashboard for both locations.** Rockford and Beloit data lives in the
  same system with location filters.

---

## The Transition Plan

We are not flipping a switch overnight. The migration follows a careful sequence
designed to minimize disruption:

1. **Parallel run (30 days).** Both the old and new systems will run at the same
   time. Nothing gets turned off until we are confident the new system is solid.
2. **New signups go through the new system immediately.** Anyone who signs up
   after go-live uses the WordPress site and WooPayments from day one.
3. **Existing members migrate in batches.** We will align migrations with
   billing cycles so nobody gets double-charged or has a gap in membership.
4. **Staff training before go-live.** Each role gets a quick-start guide
   covering exactly what changes for them. Training sessions will walk through
   the new workflows hands-on.
5. **Support channel during transition.** Questions and issues get a dedicated
   channel so nothing falls through the cracks.
6. **Rollback capability.** If something critical breaks, DNS can point back to
   the Wix site while we fix the issue. The safety net stays in place until the
   parallel run is complete.

---

## Estimated Savings

| | Monthly Cost |
|---|---|
| **Before** (current stack) | ~$1,800-2,100+ |
| **After** (Pressable hosting + Twilio SMS + minor plugin costs) | ~$147-399 |
| **Net savings** | **$1,400-1,900+/mo** |

The exact number depends on SMS volume and final plugin licensing, but even the
conservative estimate puts annual savings above $16,000.

---

Questions? Bring them to the next team meeting or drop them in the support
channel. Nobody is expected to figure this out alone.
