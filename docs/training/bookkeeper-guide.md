# Bookkeeper Quick-Start Guide

**For:** Joy
**Access level:** Administrator (finance-focused workflows)

---

## Logging In

1. Go to your site's login page and sign in with your credentials.
2. You land on the WordPress Dashboard. Your primary tools are under **WooCommerce** and **Gym** in the left sidebar.

---

## Gym Dashboard and Joyous

**URL:** `admin.php?page=gym-core`

Your dashboard shows finance-relevant widgets on the right side (revenue, active subscriptions, failed payments). On the left side is the **Gandalf AI chat**.

### Using Joyous (Finance AI Agent)
1. On the dashboard, select **Joyous** from the agent dropdown.
2. Ask questions in plain language, for example:
   - "What's this month's revenue for Rockford?"
   - "How many subscriptions failed payment this week?"
   - "Show me a breakdown of membership tiers by location."
   - "Compare revenue between both locations for last month."
   - "Give me a subscription overview -- how many active, on hold, cancelled?"
   - "Which members have failed payments right now?"
3. Joyous pulls real data from WooCommerce and gym-core to answer.
4. If Joyous suggests an action (like retrying a batch of failed payments), it goes to the approval queue for review before executing.

### What Joyous knows
Joyous has access to:
- WooCommerce revenue, orders, and subscription data
- Failed payment details and retry status
- Membership tier breakdowns by location
- Active member counts and churn trends

Use Joyous for quick answers instead of navigating through multiple WooCommerce report screens. It is especially useful for comparing locations or getting a fast end-of-day summary.

---

## Viewing Financial Reports

### WooCommerce Analytics
1. Go to **WooCommerce > Analytics > Revenue** for revenue over time.
2. Use **WooCommerce > Analytics > Orders** for order volume and averages.
3. Use the date picker to set custom date ranges.
4. Export data to CSV using the **Download** button at the top of any report.

### Key reports to check regularly
- **Revenue** -- Daily, weekly, and monthly totals
- **Subscriptions** -- Active, paused, cancelled, and pending cancellation counts
- **Taxes** -- Tax collected by period (if applicable)

---

## Subscription Management

**URL:** `admin.php` > WooCommerce > Subscriptions

### View all subscriptions
1. Go to **WooCommerce > Subscriptions**.
2. The list shows status, customer name, total, next payment date, and membership tier.
3. Use the status filter tabs (Active, On Hold, Cancelled, etc.) to narrow results.

### Check a specific subscription
1. Click the subscription number to open it.
2. Review: billing schedule, payment method, renewal history, and related orders.
3. The **Renewal Orders** section shows every past payment attempt.

### Pause or cancel a subscription
1. Open the subscription.
2. Change the status dropdown to **On Hold** (pause) or **Cancelled**.
3. Click **Update**. The member's access adjusts automatically.

---

## Handling Failed Payments

Failed payments appear in two places:

### From the Subscriptions list
1. Go to **WooCommerce > Subscriptions**.
2. Filter by **On Hold** status -- these are typically failed payment subscriptions.
3. Click into each one to see the failed renewal order.

### From the Orders list
1. Go to **WooCommerce > Orders**.
2. Filter by **Failed** status.
3. Click the order to see error details.

### Resolving a failed payment
1. Contact the member to update their payment method (they can do this from their My Account page).
2. Once updated, you can manually retry by clicking **Retry Payment** on the failed renewal order.
3. Or create a manual renewal: open the subscription and click **Process Renewal**.

---

## Processing Refunds

1. Go to **WooCommerce > Orders** and find the order to refund.
2. Click the order number to open it.
3. Click the **Refund** button below the order items.
4. Enter the refund amount (full or partial).
5. Click **Refund via WooPayments** to process it back to the original payment method.
6. The refund is recorded on the order and reflected in analytics.

---

## CRM Contact Management

**URL:** `admin.php` > Gym CRM

- **View member records** -- Each contact shows their membership status, payment history, and contact info.
- **Add notes** -- Log payment-related conversations or arrangements (e.g., "Agreed to payment plan starting 4/15").
- **Search contacts** -- Use the search bar to find members by name, email, or phone.

---

## Revenue Reporting Tips

### Monthly close checklist
1. Go to **WooCommerce > Analytics > Revenue** and set the date range to the previous month.
2. Note the gross revenue, refunds, and net revenue.
3. Check **WooCommerce > Analytics > Taxes** for tax totals.
4. Review **Subscriptions** for any that went on hold during the month.
5. Export all reports to CSV for your records.

### Comparing locations
1. Use the location toggle on the Gym Dashboard to view per-location stats.
2. Ask Joyous: "Compare revenue between Rockford and Beloit for March."

---

## Attendance Reports (Operational KPI)

Attendance data lives in the gym-core plugin, not WooCommerce. To access it:

### From the admin dashboard
1. Go to **Gym > Attendance** (`admin.php?page=gym-attendance`).
2. Use the **History** tab to filter by date range, class, or location.
3. The **Trends** tab shows week-over-week stats and at-risk member alerts.

### From Joyous
Ask Joyous for attendance summaries when preparing operational reports:
- "How many total check-ins did we have last month at each location?"
- "What's the average attendance per class this week?"

Attendance numbers are a useful KPI alongside revenue -- declining attendance often predicts future subscription cancellations.

---

## Daily Checklist

- [ ] Check the **Gym Dashboard** for revenue widgets and any alerts
- [ ] Review **WooCommerce > Subscriptions** filtered by On Hold for failed payments
- [ ] Check **WooCommerce > Orders** filtered by Failed for payment issues
- [ ] Ask Joyous for a quick financial summary if needed
- [ ] Log any member payment conversations in **Gym CRM**

## Weekly Checklist

- [ ] Review **WooCommerce > Analytics > Revenue** for the week
- [ ] Follow up on any unresolved failed payments
- [ ] Export subscription status report
