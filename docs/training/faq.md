# Frequently Asked Questions

Common questions for all staff at both locations.

---

## Check-In and Attendance

### How do I check in a member?

**From the front desk (kiosk/QR):**
Members scan their QR code at the kiosk terminal, and the check-in is recorded automatically.

**Manual check-in:**
1. Go to **Gym > Attendance** (`admin.php?page=gym-attendance`).
2. On the **Today** tab, find the class.
3. Click **Check In** next to the member's name.
4. The check-in is recorded immediately.

### How do I look up a member's attendance history?

1. Go to **Gym > Attendance** and open the **History** tab.
2. Search by the member's name, or filter by date range, class, or location.
3. You can also ask any Gandalf agent: "Show me [member name]'s attendance history."

### What counts as an attendance streak?

A streak tracks consecutive weeks with at least one check-in. Streaks reset at the end of each quarter (quarterly freeze reset), so a new quarter starts a fresh streak for everyone.

---

## Belt Ranks and Promotions

### How do I look up a member's belt rank?

**Option 1 -- Member profile:**
Go to **Users** in the admin sidebar, find the member, and view their profile. Their current rank is displayed in the Gym Core section.

**Option 2 -- Promotions dashboard:**
Go to **Gym > Promotions** (`admin.php?page=gym-promotions`). Search for the member by name. Their current rank and promotion history are shown.

**Option 3 -- Ask Gandalf:**
Ask any agent: "What rank is [member name]?" or "Show me [member name]'s rank history."

### How does promotion eligibility work?

The system tracks classes attended since the last promotion and compares them against the requirements for the next rank. When a member meets the criteria, they appear as "Eligible" on the Promotions dashboard. Coaches can also submit a recommendation before a member hits the threshold if they believe the student is ready.

### Who can promote a student?

Only the head instructor or users with Head Coach access can execute a promotion. All coaches can submit recommendations, which flag the student for review.

---

## Gandalf (AI Chat)

### How do I use Gandalf?

1. Go to the **Gym Dashboard** (`admin.php?page=gym-core`).
2. The chat panel is on the left side of the screen.
3. Select an agent from the dropdown at the top of the chat.
4. Type your question in plain English and press Enter.
5. Gandalf responds using real gym data -- attendance, ranks, memberships, and more.

### What is the difference between the Gandalf personas?

| Agent | Best for | Who uses it |
|-------|----------|-------------|
| **Admin Agent** | Operations, scheduling, announcements, SMS blasts, social posts, member lookup | Owners and admin staff |
| **Coaching Agent** | Student assessments, class briefings, Foundations management, promotion checks | Coaches and instructors |
| **Joyous** (Finance) | Revenue reports, subscription overviews, failed payment tracking | Bookkeeping staff |
| **Sales Agent** | Pricing questions, lead follow-ups, SMS drafts, schedule info, social proof stats | Sales staff |

All agents have access to gym data, but each is tuned for its role. You can switch between agents at any time using the dropdown.

### Does Gandalf take actions automatically?

No. When Gandalf proposes an action (sending an SMS, publishing a social post, retrying a payment), it enters the **approval queue**. A staff member must review and explicitly approve or reject the action before anything happens.

---

## Social Posts

### How do I approve a social post?

1. Gandalf drafts social posts and saves them with **Pending** status.
2. Go to **Posts** in the admin sidebar and filter by **Pending** status.
3. Open the draft to review the text.
4. Edit if needed, then click **Publish**.
5. Jetpack Publicize automatically shares the published post to connected social accounts.

You can also ask the Admin Agent: "Show me pending social posts" for a quick list.

### Can I edit a social post before publishing?

Yes. Pending posts are fully editable. Change the wording, add images, or rewrite entirely before clicking Publish.

---

## SMS and Communication

### How do I send an SMS to a member?

SMS is sent through Twilio and always goes through the approval workflow:

1. Ask a Gandalf agent: "Send an SMS to [member name] about [topic]."
2. Gandalf drafts the message and submits it to the approval queue.
3. Review the draft on the Gym Dashboard under pending actions.
4. Click **Approve** to send, or **Reject** to discard.

You cannot send SMS directly from the admin panel -- all messages go through Gandalf's draft-and-approve flow to maintain TCPA compliance.

### Can I send a bulk SMS?

Yes. Ask Gandalf: "Send an SMS to all active members at [location] about [topic]." The agent drafts the message and submits it for approval. Once approved, it sends to all matching members who have opted in to SMS.

---

## Payments and Subscriptions

### What happens if a payment fails?

1. The member's subscription moves to **On Hold** status.
2. The failed renewal order appears under **WooCommerce > Orders** with a **Failed** status.
3. WooCommerce automatically retries the payment according to its retry schedule.
4. The member can also update their payment method from their My Account page.
5. Once the payment succeeds (automatically or after the member updates their method), the subscription reactivates.

Bookkeeping staff can also ask Joyous: "Which members have failed payments right now?" for a quick list.

### How do I manually retry a failed payment?

1. Go to **WooCommerce > Orders** and find the failed renewal order.
2. Click into the order.
3. Click **Retry Payment** to attempt the charge again.
4. Or open the subscription and click **Process Renewal** to create a new renewal attempt.

---

## Announcements

### How do I create an announcement?

1. Go to **Gym > Announcements > Add New**.
2. Write the announcement (schedule changes, closures, events, etc.).
3. Assign it to a specific location if it only applies to one gym, or leave it for all locations.
4. Click **Publish**. The announcement appears on staff dashboards.

You can also ask the Admin Agent: "Draft an announcement about [topic]" and it will create one for you to review.

---

## Foundations Program

### What is Foundations?

Foundations is the safety onboarding program for new adult BJJ students. Students in Foundations cannot live-train with non-coaches until they complete all phases and are cleared.

### What are the Foundations phases?

1. **Phase 1** -- Coached instruction only. The student attends classes but only drills with coaches.
2. **Phase 2** -- Supervised rolls with coaches, recorded by coaching staff.
3. **Phase 3** -- Additional classes to reach the total class threshold.
4. **Cleared** -- The student can train with all partners.

The required class counts and roll counts are configurable by the admin in **Settings > Gym Core > Ranks**.

### How do I check a student's Foundations status?

- View their member profile under **Users** in the admin sidebar.
- Ask the Coaching Agent: "What's [student name]'s Foundations status?"
- Class briefings from the Coaching Agent include Foundations info for enrolled students.

---

## Locations

### How do I switch between locations?

Use the **location toggle** at the top of the Gym Dashboard. Widgets, attendance data, and promotions update to show data for the selected location. Most admin screens that show gym data support location filtering.

### Do I need to switch locations to see everything?

Each location's data is shown separately by default. To see combined data, ask Gandalf for a cross-location summary (e.g., "How many total check-ins across both locations today?").
