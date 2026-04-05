# Admin Quick-Start Guide

**For:** Darby (owner/head instructor) and Amanda (admin)
**Access level:** Full administrator

---

## Logging In

1. Go to your site's login page and sign in with your admin credentials.
2. You land on the WordPress Dashboard. Your main hub is the **Gym** menu in the left sidebar.

---

## Gym Dashboard

**URL:** `admin.php?page=gym-core`

This is your home base. The dashboard has two columns:

- **Left side** -- Gandalf AI chat. Select the **Admin Agent** from the agent dropdown to ask questions about operations, scheduling, or policies.
- **Right side** -- Role-tailored stat widgets showing today's check-ins, upcoming promotions, active memberships, and revenue at a glance.

**Switching locations:** Use the location toggle at the top of the dashboard to switch between Rockford and Beloit. Widgets update to show data for the selected location.

---

## Managing Classes

**URL:** `admin.php` > Gym > Classes (gym_class post type)

### Add a new class
1. Click **Gym > Classes > Add New**.
2. Enter the class name (e.g., "BJJ Fundamentals - Tuesday 6pm").
3. Set the recurrence rules, capacity limit, and instructor.
4. Assign a **Location** (Rockford or Beloit) in the sidebar.
5. Click **Publish**.

### Edit or cancel a class
1. Go to **Gym > Classes** and find the class in the list.
2. Click the class name to edit details, or use **Quick Edit** for fast changes.
3. To cancel a one-off session, move it to Trash or update the schedule.

---

## Announcements

**URL:** `admin.php` > Gym > Announcements (gym_announcement post type)

1. Click **Gym > Announcements > Add New**.
2. Write your announcement (schedule changes, closures, events).
3. Assign it to a location if it only applies to one gym.
4. Click **Publish**. Staff see it on their dashboards.

---

## Promotions and Belt Management

**URL:** `admin.php?page=gym-promotions`

### Review eligible students
1. Navigate to **Gym > Promotions**.
2. The dashboard lists students approaching or eligible for promotion, filtered by location.
3. Each row shows the student's current rank, attendance count, and eligibility status.

### Promote a student
1. Find the student in the list and click **Promote**.
2. Select the new rank (stripe or belt) and confirm.
3. The system records the promotion date, who promoted them, and triggers:
   - SMS notification to the student (if opted in)
   - A Gandalf social celebration post
   - Badge award if applicable

### Set coach recommendation
1. Click the **Recommend** button next to a student's name.
2. Add notes about readiness. This flags the student for Darby's final review.

---

## Attendance

**URL:** `admin.php?page=gym-attendance`

The attendance dashboard has three tabs:

- **Today** -- Live check-in view with per-class breakdowns for the current day.
- **History** -- Searchable, filterable table of all attendance records. Filter by date range, class, student, or location.
- **Trends** -- Week-over-week stats and at-risk member alerts (students whose attendance is dropping).

### Manual check-in
1. On the **Today** tab, find the class and click **Check In** next to a student's name.
2. Or use the kiosk/QR flow for self-service check-in at the front desk.

---

## CRM Contacts (Gym CRM)

**URL:** `admin.php` > Gym CRM (white-labeled Jetpack CRM)

- **View contacts** -- Browse all members, leads, and prospects.
- **Add a contact** -- Click **Add New** and fill in name, email, phone, location, and membership status.
- **Log interactions** -- Add notes, calls, or emails to a contact's timeline.
- Contacts sync automatically from WooCommerce orders and form submissions.

---

## WooCommerce: Orders and Subscriptions

### View orders
1. Go to **WooCommerce > Orders**.
2. Use the search bar or filters to find specific orders by member name or order number.

### Manage subscriptions
1. Go to **WooCommerce > Subscriptions**.
2. Click a subscription to view its status, next payment date, and history.
3. You can **Pause**, **Cancel**, or **Change** a subscription from this screen.

### Create a comp (free) membership
1. Go to **WooCommerce > Orders > Add New**.
2. Add the membership product to the order.
3. Set the price to $0.00 using the edit pencil on the line item.
4. Assign the correct customer.
5. Set the order status to **Completed** and save.
6. The membership activates immediately with no payment required.

---

## Membership Products

**URL:** `admin.php` > WooCommerce > Products

Membership tiers (Basic, Pro, Unlimited) are WooCommerce subscription products.

### Edit a membership tier
1. Go to **WooCommerce > Products** and find the membership.
2. Update the price, description, or access restrictions.
3. Changes apply to new sign-ups; existing subscriptions keep their current terms.

---

## Sales Kiosk Configuration

The Sales Kiosk is a tablet interface at `/sales/` that lets sales staff process membership purchases in person with dynamic pricing.

### Configuring kiosk pricing on a product

Each subscription product can have custom pricing ranges for the kiosk:

1. Go to **WooCommerce > Products** and edit a subscription product.
2. Scroll to the **General** tab. If the product type is "Simple subscription" or "Variable subscription", you will see a **Sales Kiosk Pricing** section.
3. Fill in the four fields:
   - **Base contract total ($)** -- The full contract value at the minimum down payment (e.g., $2,455 for a 12-month Adult BJJ membership).
   - **Min down payment ($)** -- The lowest amount a customer can put down (e.g., $99).
   - **Max down payment ($)** -- The highest amount (e.g., $999).
   - **Max discount ($)** -- The maximum savings earned when the customer pays the max down payment (e.g., $200).
4. Click **Update** to save.

The kiosk calculates the monthly payment automatically:
- At minimum down payment: no discount, highest monthly payment.
- At maximum down payment: full discount, lowest monthly payment.
- In between: proportional discount based on where the slider falls.

### Making products visible to the kiosk

Products with catalog visibility set to **Hidden** will still appear in the sales kiosk. This is by design -- membership products stay hidden from the public shop but are available for in-person sales.

### Viewing kiosk orders

All orders created through the sales kiosk are tagged with metadata:
- `_gym_sales_kiosk` = 1 (marks the order as kiosk-originated)
- `_gym_down_payment` = the down payment amount
- `_gym_recurring_payment` = the calculated monthly amount
- `_gym_sales_staff_id` = the staff member who processed the sale

To find kiosk orders: go to **WooCommerce > Orders** and look for orders with the note "Sales kiosk order" or filter by the kiosk meta key.

### Access control

Only users with the `gym_process_sale` capability can access the kiosk. This is granted to:
- **Administrators** (automatic)
- **Head Coach** role (automatic)

Regular coaches and other roles cannot access the kiosk. To grant access to a specific user, assign them the Head Coach role or add the capability manually.

---

## Using Gandalf (AI Chat)

**URL:** `admin.php?page=gym-core` (left panel)

1. Select an agent from the dropdown:
   - **Admin Agent** -- Operations, scheduling, policy questions
   - **Coaching Agent** -- Student assessments, curriculum help
   - **Joyous** -- Financial questions and reports
   - **Sales Agent** -- Lead and enrollment questions
2. Type your question and press Enter.
3. Gandalf responds using real gym data (attendance, ranks, memberships, revenue).
4. If Gandalf proposes an action (e.g., sending an SMS blast), it enters the **approval queue**. Review pending actions and approve or reject them.

### Admin Agent -- what it can do
The Admin Agent is your go-to for day-to-day operations. Example prompts:
- "How many check-ins did we have today at each location?"
- "Who is eligible for promotion this month?"
- "Draft an announcement about the holiday schedule change."
- "Send an SMS to all active members at Rockford about Saturday's open mat."
- "Look up member [name] -- what's their attendance and rank?"
- "Draft a social post celebrating this week's promotions."

The Admin Agent has access to attendance data, promotion eligibility, member records, SMS (via Twilio), announcements, and social post drafting. Any action it proposes (sending SMS, publishing a post) goes through the approval queue first.

### Approval queue
When Gandalf or its background agents propose actions, they appear in the pending actions list. Review each one, then click **Approve** or **Reject**.

---

## Social Post Approval Workflow

Gandalf drafts celebration posts (promotions, milestones, events) and saves them as **pending** posts. The flow:

1. **Gandalf drafts** -- A promotion happens or you ask Gandalf to create a social post. It generates the copy and saves it as a pending post.
2. **Staff reviews** -- Go to **Posts** in the admin sidebar and filter by **Pending** status. Open the draft to review text and images.
3. **Edit if needed** -- Adjust the wording, add photos, or tweak the message.
4. **Publish** -- Click **Publish**. Jetpack Publicize automatically shares the post to connected social accounts (Facebook, Instagram, etc.).

You can also ask the Admin Agent: "Show me pending social posts" to see what is waiting for review.

---

## Foundations Management

Foundations is the safety gate for new adult BJJ students. New students cannot live-train with non-coaches until they complete the Foundations program.

### How Foundations works
- **Phase 1:** Complete the required number of coached-only classes.
- **Phase 2:** Complete supervised rolls with coaches (recorded by the coaching staff).
- **Phase 3:** Complete additional classes to reach the total class threshold.
- **Cleared:** Student can now train with all partners.

### Admin tasks
- View Foundations status on any member's profile under **Gym > Members** or by asking Gandalf.
- Foundations thresholds (class counts, required rolls) are configurable at **Settings > Gym Core > Ranks**.
- Time in Foundations counts toward White Belt stripe progression -- it is not a separate belt.

---

## Daily Checklist

- [ ] Check the **Gym Dashboard** for today's stats and any pending approvals
- [ ] Review the **Attendance > Today** tab for class check-ins
- [ ] Glance at **WooCommerce > Subscriptions** for failed payments
- [ ] Check **Gym CRM** for new leads or follow-up reminders
- [ ] Review **Promotions** dashboard for students nearing eligibility
