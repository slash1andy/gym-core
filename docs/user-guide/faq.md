# Frequently Asked Questions

This document covers common questions for both staff and members of Haanpaa Martial Arts.

---

## Staff FAQ

### 1. How do I check in a member who forgot their phone?

Go to Gym > Attendance > Today, use the Quick Check-In form, search by their name, and select the class they are attending. The check-in is recorded the same as if they scanned their QR code.

### 2. How do I create a free or comp membership?

Go to WooCommerce > Orders > Add New. Add the membership product, set the price to $0, assign the customer, and set the order status to Completed. This enrolls them in the membership plan without charging anything.

### 3. How do I cancel or pause a membership?

Go to WooCommerce > Subscriptions and find the member's subscription. Change the status to On Hold to pause it or Cancelled to end it. On Hold subscriptions can be reactivated later; cancelled subscriptions require a new purchase.

### 4. How do I promote a student?

Go to Gym > Promotions, find the student using search or filters, and click Promote. Select their new rank and confirm. The system automatically handles the SMS notification, awards the belt promotion badge, and queues a social media celebration post.

### 5. What happens when a student hits an attendance milestone?

A badge is automatically awarded and the `gym_core_attendance_milestone` action fires. If AutomateWoo workflows are configured, a congratulatory email or SMS is sent. The badge appears on the member's dashboard immediately.

### 6. How do I use Gandalf?

Open the Gym Dashboard and use the left panel to select an agent: Admin (operations), Coaching (training), Sales (leads and conversions), or Joyous/Finance (billing and payments). Type your question in plain language. Read-only requests (lookups, reports) respond instantly. Write actions (SMS drafts, social posts, promotion flags) are queued for your approval before they execute.

### 7. How do I set up targeted content for a specific belt rank?

There are two ways. First, you can use the Targeted Content block in the block editor and configure the visibility rules in the sidebar controls. Second, you can use the Content Targeting meta box on any post or page to set rank, program, location, or membership requirements for the entire page.

### 8. How do I see attendance trends and at-risk members?

Go to Gym > Attendance > Trends. This tab shows week-over-week attendance statistics and flags students whose attendance has been declining. You can filter by location and program to focus on specific groups.

### 9. How do I handle a failed payment?

Go to WooCommerce > Subscriptions and filter by On Hold status. These are subscriptions where automatic renewal failed. Contact the member and ask them to update their payment method under My Account > Payment Methods. Once updated, retry the charge from the failed renewal order under WooCommerce > Orders.

### 10. What is the Foundations program?

Foundations is a safety gate for new Adult BJJ students before they can participate in live rolling. It has three phases: Phase 1 requires 10 classes of coached instruction, Phase 2 requires 2 supervised coach rolls, and Phase 3 requires reaching 25 total classes. A coach clears the student through the Foundations dashboard when they are ready to advance to full participation.

### 11. How do I draft an SMS through Gandalf?

Ask the Sales or Admin agent to compose a message. For example: "Draft an SMS to John Smith reminding him about tomorrow's class." Gandalf calls the `draft_sms` tool, which queues the message for your approval. Review it in the pending actions list before it sends.

### 12. How do I approve or reject a Gandalf action?

Pending actions appear in the Gym Dashboard sidebar and under Tools > Gandalf > Audit Log. Each pending item shows the agent, the action it wants to take, and a preview. Click Approve to execute it, Approve with Changes to modify it first, or Reject to discard it.

### 13. How do I add a new class?

Go to Gym > Classes > Add New. Enter the class name, day of the week, start time, instructor, capacity, and recurrence pattern. Assign the class to a location. Save, and the class appears on the schedule and in the iCal feed immediately.

### 14. How do I create an announcement?

Go to Gym > Announcements > Add New. Write your message, assign a location if it only applies to one gym, and publish. The announcement appears on staff dashboards. Location-specific announcements only show to staff viewing that location.

### 15. Where do I change settings?

Go to WooCommerce > Settings > Gym Core tab. Settings are organized into sections: General, Locations, Schedule, Ranks, Attendance, Gamification, SMS, CRM, and API. Each section has inline descriptions for its options.

### 16. How do I export data?

Go to WooCommerce > Analytics and navigate to any report (Revenue, Orders, Members, etc.). Click the Download button to export the current report as a CSV file.

### 17. How do I view a member's full history?

Open their profile in Gym CRM. It shows their belt rank, Foundations status, last check-in date, and total class count — all synced automatically. For detailed attendance records, go to Gym > Attendance > History and filter by their name.

### 18. What are streak freezes?

Streak freezes preserve a member's consecutive-week streak through one missed week. The number of freezes per quarter is configurable (0 to 4) under Gym Core settings. Unused freezes do not carry over — they reset at the start of each calendar quarter.

### 19. How do I subscribe to the class schedule in my calendar?

Share the iCal feed URL with members. The URL is available under Gym Core settings or on the public schedule page. Members paste the URL into Google Calendar (Add by URL), Apple Calendar (Subscribe), or Outlook (Add internet calendar) for auto-updating class times.

### 20. How does multi-location work?

Everything in the system filters by location: products, classes, attendance records, and dashboard widgets. Members select a home location that determines what they see by default. Staff can toggle between Rockford and Beloit on any dashboard using the location switcher.

### 21. How do I see who is eligible for promotion?

Go to Gym > Promotions. The dashboard lists all students who are approaching or have met the promotion thresholds for their current rank. You can filter by program and location. Each row shows classes at rank, days at rank, and whether thresholds are met.

### 22. Can I bulk-promote students?

Yes. On the Promotions dashboard, use the checkboxes to select multiple students. Then use the bulk actions dropdown to choose Promote or Recommend. Promote executes immediately; Recommend flags them for review without changing their rank.

### 23. How do I record a Foundations coach roll?

You can use the Coaching Agent in Gandalf: "Record a coach roll for [student name]." Alternatively, use the Foundations dashboard to find the student and log the roll directly. The system tracks completion automatically.

### 24. How do I view the approval audit log?

Go to Tools > Gandalf > Audit Log. You can filter by status (Approved, Rejected, Pending), by agent (Admin, Coaching, Sales, Finance), or by date range. Each entry shows who approved or rejected the action and when.

### 25. How do I change a member's location?

Edit their user profile in WordPress and update the gym_location field. Members can also change their own location using the location selector on the website, which updates their account automatically.

---

## Member FAQ

### 1. How do I check in to class?

At the front desk kiosk, scan your QR code or search for your name. Staff can also check you in manually if needed. Make sure to check in every time you attend — it counts toward your badges, streaks, and promotion eligibility.

### 2. How do I view my belt rank and progress?

Log in and go to My Account. Your dashboard shows your belt rank, stripes, classes since your last promotion, and days at your current rank. You can also see the thresholds required for your next promotion.

### 3. How do I update my payment method?

Go to My Account > Payment Methods. Add a new card or update your existing one. Your subscription will automatically use the updated payment method for future charges.

### 4. How do I cancel my membership?

Go to My Account > Subscriptions, click on your active subscription, and select Cancel. You can also ask the front desk staff to handle it for you.

### 5. How do I subscribe to the class schedule?

Ask the front desk for the iCal feed link for your location. Paste it into your calendar app (Google Calendar, Apple Calendar, or Outlook) as a subscription. The schedule updates automatically whenever classes change.

### 6. What are badges and how do I earn them?

Badges are awarded automatically when you reach milestones — attending 10 classes, maintaining a 4-week streak, earning a belt promotion, and more. Check your dashboard to see all badges you have earned. Badges are permanent and never removed.

### 7. What is a streak and what happens if I miss a week?

A streak counts the number of consecutive weeks you attend at least one class. Missing an entire week (Monday through Sunday) breaks the streak and resets the count, unless you have a streak freeze available that preserves it through the missed week.

### 8. What is the Foundations program?

New Adult BJJ students start in Foundations, a structured safety program with three phases. You progress through coached instruction, supervised rolling, and a minimum class count before joining live training. Your progress is tracked on your dashboard.

### 9. Why can I not see certain content on the website?

Some content is restricted by belt rank, program, location, or membership status. As you progress in your training and meet the criteria, that content becomes available to you automatically.

### 10. How do I see my billing history?

Go to My Account > Orders to see all past payments and receipts. Go to My Account > Subscriptions to see your recurring plan details, including your next payment date and amount.
