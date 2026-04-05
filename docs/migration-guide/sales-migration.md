# Sales Migration Guide -- Matt and Rachel

> Everything you do in GoHighLevel today, mapped to your new workflow in WordPress.

This guide walks you through every daily task you currently handle in GoHighLevel (and occasionally Spark) and shows you exactly where to do the same thing in the new system. Your WordPress accounts have Shop Manager + CRM access, which gives you everything you need for sales without cluttering your view with settings you don't use.

---

## 1. Your Daily Hub

**Before:** Log into GoHighLevel for leads and pipeline. Maybe open Spark separately to check class info or a member's status.

**Now:** Log into WordPress and open the Gym Dashboard. The Sales Agent is pre-selected in the left panel, ready for questions. The right side of the dashboard shows your key numbers at a glance: new leads, trial bookings, and recent signups. Your admin menu is simplified to just what matters for your role -- Gym, WooCommerce, and Gym CRM. No digging through settings pages or plugin configs.

One login, one screen. Class data, membership info, and lead management are all in the same system.

---

## 2. Viewing and Managing Leads

**Before:** GHL > Contacts or Opportunities. Search or scroll through contact lists.

**Now:** Go to Gym CRM in the left sidebar. Contacts with "Lead" status are your prospects. Click any contact to see their full record: name, email, phone, source, notes, interaction history, and which location they're interested in. Search by name, email, or phone.

**Key difference:** No more duplicate contacts. In GHL, every inbound phone call created a new contact record, so you'd end up with three entries for the same person. Here, contacts are deduplicated and linked directly to their membership data. One person, one record.

---

## 3. Adding a New Lead

**Before:** GHL > Contacts > Add Contact. Fill in details manually.

**Now:** Go to Gym CRM > Add New Contact. Enter their name, email, phone, and location. Set the status to "Lead." Add a note about what they're interested in and how they heard about the gym (referral, Facebook, walk-in, etc.).

Web form submissions from the website auto-create contacts, so if someone fills out the trial request form online, their record is already waiting for you. No manual entry needed for website inquiries.

---

## 4. Pipeline Tracking

**Before:** GHL Opportunities board -- drag and drop cards between pipeline stages.

**Now:** CRM contact statuses serve as your pipeline stages:

- **New Lead** -- Just entered the system
- **Contacted** -- You've reached out
- **Trial Booked** -- They have a trial class scheduled
- **Trial Done (Follow-Up)** -- Trial completed, decision pending
- **Closed Won (Customer)** -- They purchased a membership
- **Closed Lost** -- They passed or went cold

Filter contacts by status to see your pipeline at any stage. The "Closed Won" status is set automatically when a lead makes their first purchase -- you don't have to update it manually.

You can also ask the Sales Agent: "Who are my open leads that need follow-up?" and get an answer based on actual contact data.

---

## 5. Sending SMS and Follow-Ups

**Before:** GHL > Conversations > type and send an SMS directly from the interface.

**Now:** Ask the Sales Agent: "Draft a follow-up message for [name] who tried BJJ on Tuesday." The agent drafts the SMS using real gym data -- the class they attended, current pricing, the next available class on the schedule. The draft goes to the approval queue. You review it, edit if needed, and approve to send via Twilio. All outbound SMS is logged on the CRM contact record automatically.

**Key difference:** Instead of typing every message from scratch or pasting from a template doc, the AI drafts context-aware messages for you. It knows pricing, schedule, and what the lead actually did. You still have full control -- nothing sends without your approval.

---

## 6. Email Automations

**Before:** GHL Workflows -- drip sequences, trigger-based emails, automated follow-ups you built or Darby configured.

**Now:** AutomateWoo handles this (WooCommerce > AutomateWoo). Same concept: define a trigger (new lead, trial completed, membership purchased) and an action (send email, send SMS, update CRM status). Pre-built triggers are already available:

- Gym -- Class Check-In
- Gym -- Belt Promotion
- Gym -- Attendance Milestone

Darby and Amanda handle the setup of these workflows. You benefit from the automated follow-ups running in the background -- trial reminders go out, welcome emails fire on signup, and win-back sequences hit lapsed leads. If you want a new automation, just ask and they'll build it.

---

## 7. Web Forms and Lead Capture

**Before:** GHL Sites > Forms. Contact forms and trial request forms embedded on the Wix site or GHL landing pages.

**Now:** Jetpack Forms on the WordPress site. The contact form and trial request form auto-create CRM contacts with "Lead" status, the correct location tag, and a program interest tag (BJJ, Muay Thai, Kids, etc.). New form submissions appear in Gym CRM immediately -- no sync delay, no missing leads, no manual import.

---

## 8. Logging Calls and Interactions

**Before:** GHL contact record -- add notes, sometimes call recordings attached automatically.

**Now:** Open the contact in Gym CRM and click Add Note. Record what you discussed, the outcome, and the next follow-up date. For example: "Called, interested in BJJ Fundamentals. Trial booked: Tue 6pm, 4/8. Will follow up Wed."

Outbound and inbound SMS messages are auto-logged on the contact record. Email interactions need manual notes for now -- just jot down the key points when you send or receive something outside the system.

---

## 9. Booking Trial Classes

**Before:** GHL calendar integration, or just coordinating over the phone and hoping you remember to log it.

**Now:** Check class capacity at Gym > Classes. You can see available spots per class. Confirm the trial with the lead, then log it on their CRM contact record: "Trial booked: BJJ Fundamentals, Tue 6pm, 4/8."

After the trial, update their status to "Trial Done (Follow-Up)" and ask the Sales Agent to draft a follow-up message. The agent will reference the specific class they attended and current membership pricing.

---

## 10. Viewing Membership Products and Pricing

**Before:** Check Spark's billing section, or ask Darby what the current pricing is.

**Now:** Go to WooCommerce > Products to see all membership tiers with current pricing for both locations. Or just ask the Sales Agent: "What are our membership tiers and pricing?" The agent pulls live product data -- no outdated price sheets, no guessing.

---

## 11. Checking a Member's Subscription

**Before:** Look up the member in Spark's billing section.

**Now:** Go to WooCommerce > Subscriptions and search by name. You can see their subscription status, plan, and billing details. For changes like pausing, cancelling, or switching plans, ask Darby or Amanda -- you have view access but not edit access on subscriptions. This keeps billing clean and avoids accidental changes.

---

## 12. New Capability: Sales AI Agent

This is something GHL never had. The Sales Agent is your always-available assistant that works with real gym data, not generic templates.

Examples of what you can ask:

- "Give me talking points for a parent asking about kids BJJ."
- "What classes have openings this week at Rockford?"
- "Draft a follow-up SMS for a lead who visited last Tuesday."
- "Summarize our current trial-to-membership conversion."
- "What's the price difference between our adult BJJ and unlimited plans?"

The agent uses live data -- current pricing, the real class schedule, actual enrollment numbers. When it drafts an SMS, the message goes to the approval queue. You review, edit if you want, and approve. Nothing sends without you seeing it first.

---

## 13. Daily and Weekly Checklists

### Daily

1. Open the Gym Dashboard and check for new leads.
2. Ask the Sales Agent: "Any follow-ups due today?"
3. Review Gym CRM for leads in "Follow-Up" status.
4. Log all calls, emails, and conversations as CRM notes.
5. Update lead statuses after each interaction.

### Weekly

1. Review the full pipeline: how many leads at each stage?
2. Ask the Sales Agent: "Summarize this week's lead activity."
3. Follow up with trial attendees who haven't signed up yet.
4. Check class capacity for upcoming trial availability.

---

## Quick Reference

| GHL Task | New Location |
|----------|-------------|
| View leads | Gym CRM > filter by "Lead" status |
| Add a contact | Gym CRM > Add New Contact |
| Pipeline board | Gym CRM > filter by status |
| Send SMS | Ask Sales Agent to draft, approve in queue |
| Email workflows | WooCommerce > AutomateWoo |
| Form submissions | Auto-created in Gym CRM |
| Log a call/note | Gym CRM > Contact > Add Note |
| Check pricing | WooCommerce > Products (or ask the Sales Agent) |
| Check subscription | WooCommerce > Subscriptions |
| Class capacity | Gym > Classes |

---

GHL had some solid features -- the drag-and-drop pipeline was convenient, and inline SMS was fast. But you were also dealing with duplicate contacts, disconnected data, and typing every follow-up from scratch. The new system gives you one place for everything, AI that actually knows your gym's data, and no more juggling between platforms.

If something feels off or you can't find what you need, ask the Sales Agent first. If it can't help, flag it for Andrew or Amanda.
