# Bookkeeper Migration Guide -- Joy

This guide maps your current daily workflows to the new system. Everything you
do today in Spark, USAePay, and scattered browser tabs now lives in one place:
the WordPress Gym Dashboard. QuickBooks and Google Sheets stay exactly the same.

---

## 1. Your Daily Hub

**Before:** Log into Spark for billing data, open USAePay for transaction
details, then switch to QuickBooks for accounting. Two separate Spark logins if
you need both locations.

**Now:** Log into WordPress at the site URL. Your landing page is the Gym
Dashboard. The right side shows finance widgets at a glance -- revenue, active
subscriptions, and failed payments. The left panel has Joyous, your Finance AI
Agent, where you can ask financial questions in plain English (more on Joyous
in section 11). QuickBooks stays the same. USAePay is replaced by WooPayments
(powered by Stripe), which is built directly into the same WordPress system --
no separate login required.

---

## 2. Revenue Tracking

**Before:** Navigate to the Spark reports tab, export or screenshot numbers,
then manually compile them.

**Now:** Go to **WooCommerce > Analytics > Revenue**. Use the date range picker
at the top to select daily, weekly, or monthly views. Click the **Download**
button to export a CSV. You can also ask Joyous: "What's this month's revenue?"
or "Compare revenue between Rockford and Beloit for March." The dashboard
finance widgets give you at-a-glance totals without navigating anywhere.

---

## 3. Subscription Management

**Before:** Spark billing section, with two separate logins for Rockford and
Beloit.

**Now:** Go to **WooCommerce > Subscriptions**. Every subscription for both
locations appears in one list. Use the status filter dropdown to narrow by
Active, On Hold, Cancelled, Pending Cancellation, or Expired. Click any
subscription to see the full detail page: billing schedule, payment method on
file, complete renewal history, and all related orders. Both locations are
visible from one screen -- no more switching between accounts.

---

## 4. Handling Failed Payments

**Before:** Wait for Spark dunning notifications, then manually follow up by
checking USAePay for transaction details.

**Now:** You have two places to find failures:

1. **WooCommerce > Subscriptions** -- filter by **On Hold** status. A
   subscription goes On Hold when its renewal payment fails.
2. **WooCommerce > Orders** -- filter by **Failed** status to see the specific
   failed renewal orders.

Click into a failed order to see the error details (e.g., card declined,
expired card, insufficient funds). To resolve: contact the member and ask them
to update their payment method at **My Account > Payment Methods** on the
website. Once they update it, go back to the failed renewal order and click
**Retry Payment**, or open the subscription and click **Process Renewal**.

You can also ask Joyous: "Which subscriptions failed this month?" to get a
quick list without navigating through filters.

---

## 5. Processing Refunds

**Before:** Coordinate between Spark and USAePay to process and verify refunds.

**Now:** Go to **WooCommerce > Orders**, find the order you need to refund,
and click into it. Click the **Refund** button at the bottom of the order
items. Enter the amount (full or partial), add an optional reason note, and
click **Refund via WooPayments**. The money goes back to the original card
automatically. The refund is recorded directly on the order and reflected in
your analytics reports -- no second system to verify against.

---

## 6. Member Billing History

**Before:** Look up the member in Spark's profile billing tab, or search for
their transactions in USAePay.

**Now:** Go to **WooCommerce > Subscriptions** and search for the member's
name. Click their subscription. The **Renewal Orders** section shows every
past payment attempt -- successful and failed -- with dates and amounts. For a
broader view, go to **WooCommerce > Orders** and search by the member's name
to see all their orders across every product type, not just subscriptions.

---

## 7. Financial Exports

**Before:** Run a Spark export or manually transcribe numbers from screen.

**Now:** Go to **WooCommerce > Analytics** and select any report tab (Revenue,
Orders, Subscriptions, Taxes). Each report has a **Download** button that
exports the current view as a CSV file. Available exports include:

- Revenue by period
- Orders by period
- Subscriptions summary
- Tax collected

You can also ask Joyous: "Export this month's revenue data." The CSV files are
formatted for straightforward import into QuickBooks.

---

## 8. Monthly Close Checklist

Use this as your standard end-of-month routine:

1. Go to **WooCommerce > Analytics > Revenue**, set the date range to the
   previous month, and note gross revenue, net revenue, and refund totals.
2. Go to **WooCommerce > Analytics > Taxes** and record tax totals for the
   period.
3. Go to **WooCommerce > Subscriptions** and review any that went On Hold
   during the month to confirm they were addressed.
4. Export all reports to CSV using the Download button on each report page.
5. Ask Joyous: "Summarize last month's financial activity" for a narrative
   overview you can reference or share with Darby.
6. Import the CSV exports into QuickBooks as usual.

---

## 9. Per-Location Revenue

**Before:** Log into Spark Rockford, write down the numbers. Log into Spark
Beloit, write down those numbers. Combine them manually.

**Now:** Use the location toggle on the Gym Dashboard to switch between
Rockford and Beloit views. Or skip the manual work entirely and ask Joyous:
"Compare revenue between Rockford and Beloit for March." One system, both
locations, instant comparison.

---

## 10. What Stays the Same

Not everything is changing. These parts of your workflow are untouched:

- **QuickBooks** -- Your accounting workflow stays exactly as it is. The only
  difference is that your source data now comes from WooCommerce CSV exports
  instead of Spark exports.
- **Google Sheets for staff scheduling** -- No changes. Continue managing
  staff schedules the same way you do today.
- **Your financial responsibilities** -- You still own the same tasks: revenue
  tracking, failed payment follow-up, refunds, monthly close, and reporting.
  The tools are consolidated, but the job is the same.

---

## 11. New Capability: Joyous (Finance AI Agent)

Joyous is a Finance AI Agent built into the Gym Dashboard. It is designed to
save you time by answering financial questions without requiring you to
navigate through multiple report screens.

**How to access it:** On the Gym Dashboard, select **Joyous** from the agent
dropdown in the left panel.

**Example questions you can ask:**

- "What's our monthly recurring revenue?"
- "How many active subscriptions at Rockford?"
- "Which members have failed payments this week?"
- "What was total revenue for March?"
- "Compare Rockford and Beloit revenue for last quarter."

Joyous pulls real data from WooCommerce and the gym-core system to answer your
questions. It is read-only -- Joyous surfaces data and generates summaries but
does not make any changes to orders, subscriptions, or payments. Think of it as
a faster way to get to the numbers you already have access to.

---

## Quick Reference: Where Things Moved

| Task | Old Location | New Location |
|------|-------------|--------------|
| Revenue reports | Spark > Reports | WooCommerce > Analytics > Revenue |
| Subscription list | Spark > Billing (x2 logins) | WooCommerce > Subscriptions |
| Failed payments | Spark dunning + USAePay | WooCommerce > Subscriptions (On Hold) |
| Process refund | Spark + USAePay | WooCommerce > Orders > Refund |
| Member billing | Spark profile + USAePay search | WooCommerce > Subscriptions > search |
| CSV exports | Spark export | WooCommerce > Analytics > Download |
| Payment processing | USAePay | WooPayments (built in) |
| Tax reports | Manual | WooCommerce > Analytics > Taxes |
| Financial questions | Navigate multiple screens | Ask Joyous |

If you run into anything that is not covered here, reach out to Andrew.
