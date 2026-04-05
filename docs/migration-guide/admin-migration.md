# Admin Migration Guide -- Darby and Amanda

A hands-on reference for the two of you as you transition from Spark, GoHighLevel,
and Wix to the new WordPress/WooCommerce system. Every section follows the same
pattern: what you used to do, what you do now, and exactly where to find it.

This is not a training manual you need to memorize. Keep it bookmarked and pull it
up when you need a reminder. The workflows will feel natural within a week or two.

---

## 1. Your Daily Hub

**Before:** Open Spark to check attendance and billing. Switch to GoHighLevel for
leads. Maybe log into Wix if the website needs an edit. Three logins, three tabs,
three mental contexts.

**Now:** Log into WordPress and go to **Gym Dashboard** (admin.php?page=gym-core).
Everything lives on one screen:

- **Left panel:** Gandalf AI chat. Select the Admin Agent to ask questions, look
  up members, or draft messages without leaving the dashboard.
- **Right panel:** Stat widgets showing today's check-ins, active member count,
  pending approvals, failed payments, and new signups.
- **Location toggle** at the top of the page switches between Rockford and Beloit
  instantly. No separate accounts, no separate logins.

One screen replaces three logins. If you need to go deeper into any area, the left
sidebar has direct links to every section described below.

---

## 2. Looking Up a Member

**Before:** Spark > Members > search by name.

**Now:** Click **Gym CRM** in the left sidebar and search by name, email, or phone
number. The contact record shows everything in one place:

- Membership status and plan
- Belt rank (auto-synced from the rank system -- no manual entry)
- Foundations program status (for BJJ students)
- Last check-in date and total class count
- All interaction notes and history

You can also skip the search entirely and ask the Admin Agent: "Look up John
Smith" or "Show me Jane Doe's membership." It pulls the same data and summarizes
it in the chat panel.

---

## 3. Managing Attendance

**Before:** Spark check-in tab or the member-facing app.

**Now:** Go to **Gym > Attendance**. Three tabs organize everything:

1. **Today** -- Live check-ins for each class with a quick check-in form for
   walk-ups or manual entries. This is what you glance at during class time.
2. **History** -- A searchable table you can filter by date range, class,
   student name, or location. Use this when you need to verify someone's
   attendance count or pull records for a specific period.
3. **Trends** -- Week-over-week stats and at-risk member alerts. Members whose
   attendance has dropped significantly get flagged here automatically.

The front desk kiosk handles self-service check-in via QR code or name search,
so you do not need to manually check in every student who walks through the door.

---

## 4. Belt Promotions

**Before:** Spark ranks section. Manually check how long someone has been at their
current belt, count their classes, then update the rank by hand.

**Now:** Go to **Gym > Promotions**. The system does the counting for you:

- The dashboard auto-calculates eligibility based on attendance count and days at
  current rank. No more spreadsheets or mental math.
- Filter the list by program, location, or status: **Eligible** (ready now),
  **Approaching** (close but not yet), or **All** (everyone).
- Click **Promote** for an instant promotion, or **Recommend** to flag someone
  for review before finalizing.
- **Bulk actions** let you promote multiple students at once after a test day.

When you promote someone, three things happen automatically:

1. An SMS is sent to the student congratulating them.
2. A badge is awarded in the gamification system.
3. A celebration post is auto-published to social media.

You do not need to do any of those steps manually.

---

## 5. Billing and Subscriptions

**Before:** Spark billing section for membership management, USAePay for payment
processing. Two systems that do not always agree with each other.

**Now:** Go to **WooCommerce > Subscriptions**. The list shows status, customer
name, plan, next payment date, and amount. Click into any subscription to see:

- Full renewal history (every payment, successful or failed)
- Payment method on file
- Billing schedule and next renewal date

Common tasks:

- **Pause a membership:** Change the subscription status to **On Hold**.
- **Cancel a membership:** Change the status to **Cancelled**.
- **Failed payment:** The subscription automatically moves to On Hold. Contact the
  member to update their card, then click **Retry** to process the payment.
- **Change a plan:** Edit the subscription and swap the product line item.

All payment processing goes through WooPayments. There is no separate gateway
dashboard to check.

---

## 6. Creating a New Membership

**Before:** Spark > Add member, select a plan, enter payment info.

**Now:** Two paths depending on the situation:

**Path 1 -- Member self-serves (most common):**
The member goes to the website, browses membership options filtered by their
location, and checks out with WooPayments. The subscription, customer record, and
payment method are all created automatically. You do not need to touch anything.

**Path 2 -- Staff-created (comp memberships, special cases):**
Go to **WooCommerce > Orders > Add New**. Add the membership product as a line
item, assign or create the customer, and process payment. For a comp membership,
set the total to $0. The subscription is created from the order just like it would
be from the website checkout.

---

## 7. Revenue and Reports

**Before:** Spark reports for membership revenue. Pull numbers from multiple
dashboards for the weekly meeting. Export CSVs and combine them manually.

**Now:** Go to **WooCommerce > Analytics > Revenue**. You get:

- Daily, weekly, and monthly revenue totals
- Date range picker for custom periods
- CSV export for anything you need in a spreadsheet
- Breakdowns by product, coupon, or category

For quick answers without digging through reports, ask the Finance Agent (Joyous):

- "What's this month's revenue?"
- "Compare Rockford vs Beloit for March."
- "How many subscriptions failed this week?"
- "What's our average membership value?"

The dashboard stat widgets also show key numbers at a glance so you can get a
pulse check without navigating away from the main screen.

---

## 8. Class Schedule Management

**Before:** Spark schedule section.

**Now:** Go to **Gym > Classes**. Each class is a post with fields for:

- Class name, day, and time
- Instructor
- Capacity and waitlist settings
- Recurrence pattern
- Location assignment

To add a class, click **Add New** and fill in the fields. To modify or cancel a
class, find it in the list and click **Edit** or **Trash**.

Members can subscribe to the class schedule as an iCal feed in their calendar
apps (Google Calendar, Apple Calendar, Outlook). When you update the schedule,
their calendars update automatically.

---

## 9. Announcements and Communication

**Before:** Spark messaging, email, or word of mouth. No single channel that
reaches everyone reliably.

**Now:** Go to **Gym > Announcements > Add New**. Write your message, assign it
to a specific location or leave it unassigned to reach both locations, then
publish. Staff see announcements on their dashboards immediately.

For SMS and social media, use the Admin Agent:

- **SMS:** Ask the Admin Agent to draft a message to a member or group. The draft
  goes through the approval queue before sending -- you review it, approve or
  edit, and it sends. No accidental messages.
- **Social media:** Ask the Admin Agent to draft a social post. Approved posts
  auto-share via Jetpack Publicize to your connected social accounts.

This keeps communication centralized and auditable. Everything sent from the
system is logged.

---

## 10. Website Edits

**Before:** Log into the Wix editor. Learn Wix's interface. Hope the formatting
holds together.

**Now:** Go to **Pages** in the WordPress sidebar, find the page you want to
edit, and click **Edit**. The block editor lets you update text, images, videos,
and layout visually. The same URL (haanpaamartialarts.com) carries over -- just a
new platform behind it.

You do not need to learn a completely new editing paradigm. The block editor works
like a document: click on a block of text to edit it, drag blocks to rearrange,
and use the toolbar to format. If you can use Google Docs, you can use this.

---

## 11. Settings and Configuration

**Before:** Spark settings, scattered across multiple tabs and sometimes multiple
Spark accounts.

**Now:** Go to **WooCommerce > Settings > Gym Core** tab. Everything is organized
into sections:

| Section | What it controls |
|---------|-----------------|
| General | Feature toggles (turn modules on/off) |
| Locations | Location names, addresses, taxonomy settings |
| Schedule | Class capacity defaults, waitlist behavior, iCal feed |
| Ranks | Promotion thresholds per belt, Foundations phase config |
| Attendance | Check-in methods, kiosk timeout, QR settings |
| Gamification | Streak rules, badge definitions, targeted content |
| SMS | Twilio credentials, rate limits, opt-in settings |
| CRM | Jetpack CRM and AutomateWoo integration |
| API | REST API keys and external integrations |

Most of these are set-and-forget. Once configured during setup, you will rarely
need to revisit them unless you are adding a new belt rank threshold or adjusting
class capacity.

---

## 12. New Capabilities You Didn't Have Before

The new system is not just a lateral move. Here is what you gain that Spark,
GoHighLevel, and Wix could not do:

- **Gandalf AI assistant.** Ask operational questions, get class briefings, draft
  messages, and look up member data -- all using real gym data, not generic
  responses. Four specialized agents (Sales, Coaching, Finance, Admin) each
  understand their domain.

- **Automated gamification.** Badges and streaks are tracked without any manual
  work. Students earn recognition for consistency and milestones automatically.

- **Foundations program.** A structured safety gate for new BJJ students with
  phase tracking built in. No more informal "has this person done enough
  fundamentals" conversations.

- **Targeted content.** Show different website content based on belt rank,
  program, or location. A white belt and a purple belt can see different
  resources on the same page.

- **Automatic promotion eligibility.** The system calculates who is eligible for
  promotion based on your configured thresholds. You review and approve instead
  of counting classes manually.

- **Both locations in one dashboard.** Toggle between Rockford and Beloit
  instantly. Cross-location reporting is built in. No more maintaining two
  separate Spark accounts.

- **Social media auto-posting.** When a student gets promoted, a celebration post
  can go out automatically. No extra steps for you.

---

## A Note on the Transition

This is a significant change, and it is okay if the first few days feel slower
than usual. You have years of muscle memory in Spark, and building new habits
takes time. But the goal here is not change for its own sake -- it is fewer
logins, less duplicate work, better data, and tools that actually talk to each
other.

If something feels wrong or missing, say so. This system was built for how you
actually work, and it can be adjusted. Andrew is a message away.
