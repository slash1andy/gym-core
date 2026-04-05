# Sales Staff Quick-Start Guide

**For:** Matt and Rachel
**Access level:** Shop Manager (limited admin)

---

## Logging In

1. Go to your site's login page and sign in with your credentials.
2. You land on the WordPress Dashboard. Your main tools are the **Gym** menu and **WooCommerce** in the left sidebar.
3. Your menu is simplified -- you only see what you need for sales work.

---

## Gym Dashboard

**URL:** `admin.php?page=gym-core`

Your dashboard shows:

- **Left side** -- Gandalf AI chat with the Sales Agent pre-selected.
- **Right side** -- Widgets showing new leads, trial bookings, active memberships, and recent sign-ups.

Use the location toggle at the top to switch between Rockford and Beloit data.

---

## Using the Sales AI Agent

The Sales Agent is your main tool for lead conversations and follow-ups.

### Getting started
1. On the Gym Dashboard, select **Sales Agent** from the agent dropdown (if not already selected).
2. Ask questions in plain English. Examples:
   - "What talking points should I use for a parent asking about kids BJJ?"
   - "Summarize our membership tiers and pricing."
   - "Draft a follow-up message for a lead who visited last Tuesday."
   - "What's our current trial-to-membership conversion rate?"

### What the Sales Agent knows
- Current membership tiers, pricing, and benefits
- Class schedules for both locations
- Recent lead and enrollment activity
- Gym programs and age groups
- Common objection-handling approaches
- Current attendance numbers (useful for social proof: "We had 45 check-ins yesterday!")
- Active announcements and upcoming events

### Getting talking points before a call
1. Ask: "Give me talking points for [situation]."
2. The agent tailors its response using real gym data (class availability, current promotions, membership openings).

### Drafting SMS messages
The Sales Agent can draft SMS messages for leads and members via Twilio:
1. Ask: "Draft an SMS to [lead name] following up on their trial class."
2. The agent writes the message and submits it for approval.
3. The draft appears in the **approval queue** on the Gym Dashboard.
4. Review the message, edit if needed, then click **Approve** to send via Twilio.
5. Or click **Reject** to discard it and write your own.

You can also ask the agent to draft bulk messages:
- "Draft a follow-up SMS for all leads who visited this week but haven't signed up."

All SMS messages require your approval before sending -- nothing goes out automatically.

### Announcements and social proof
Ask the Sales Agent for data you can use in conversations with leads:
- "What's our class attendance been like this week?" -- Great for showing a busy, active gym.
- "What promotions happened recently?" -- Shows student progress and community.
- "Draft an announcement about our new member special." -- Creates an announcement post for the admin to review.

---

## Lead Management

### Viewing leads in Gym CRM
1. Go to **Gym CRM** in the left sidebar.
2. Leads appear as contacts with a "Lead" status.
3. Click a lead to see their full record: source, notes, and interaction history.

### Adding a new lead
1. Go to **Gym CRM > Add New Contact**.
2. Fill in: name, email, phone, and which location they are interested in.
3. Set the status to **Lead**.
4. Add a note about how they heard about the gym and what they are interested in.

### Logging follow-ups
1. Open the lead's contact record.
2. Click **Add Note** (or the activity log section).
3. Record what you discussed, the outcome, and when to follow up next.

---

## Trial Class Bookings

### Booking a trial for a lead
1. Find the lead in **Gym CRM** and note their preferred class type and time.
2. Check the class schedule at **Gym > Classes** to confirm availability and capacity.
3. If the class has open spots, let the lead know the details.
4. Log the trial booking as a note on their contact record: "Trial booked: BJJ Fundamentals, Tue 6pm, 4/8."

### After the trial
1. Update the contact record with the trial outcome.
2. Ask the Sales Agent: "Draft a follow-up message for [name] who tried [class] on [date]."
3. If they want to sign up, direct them to the membership sign-up page or help them in person.

---

## Pipeline Tracking

### Checking your pipeline
1. Go to **Gym CRM** and filter contacts by status: Lead, Trial Booked, Follow-Up Needed.
2. Sort by date to see who needs attention.
3. Or ask the Sales Agent: "Who are my open leads that need follow-up?"

### Moving leads through stages
Update the contact status as leads progress:
1. **Lead** -- Initial inquiry, not yet visited
2. **Trial Booked** -- Scheduled for a trial class
3. **Follow-Up** -- Attended trial, needs follow-up
4. **Customer** -- Signed up for a membership (status updates automatically on purchase)

---

## Viewing Memberships and Products

### Check membership options
1. Go to **WooCommerce > Products** to see current membership tiers and pricing.
2. Each product shows the tier name, monthly price, and what is included.

### Check a member's subscription
1. Go to **WooCommerce > Subscriptions** and search by the member's name.
2. You can see their status and plan but cannot modify subscriptions (ask Amanda or Darby for changes).

---

## Follow-Up Reminders

### Setting reminders
1. After logging a follow-up note in Gym CRM, include the follow-up date in your note (e.g., "Follow up 4/10").
2. Ask the Sales Agent daily: "What follow-ups do I have coming up?"
3. The agent checks CRM notes and reminds you of pending outreach.

---

## Daily Checklist

- [ ] Open the **Gym Dashboard** and check for new leads
- [ ] Ask the Sales Agent: "Any follow-ups due today?"
- [ ] Review **Gym CRM** for leads in "Follow-Up" status
- [ ] Log all calls, emails, and conversations as CRM notes
- [ ] Update lead statuses after each interaction

## Weekly Checklist

- [ ] Review the full pipeline: how many leads at each stage?
- [ ] Ask the Sales Agent: "Summarize this week's lead activity."
- [ ] Follow up with any trial attendees who have not signed up
- [ ] Check upcoming class capacity for trial availability
