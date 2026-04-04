# Coach Quick-Start Guide

**For:** All coaches and instructors
**Access level:** Coach (gym_coach) or Head Coach (gym_head_coach)

---

## Logging In

1. Go to your site's login page and sign in with your credentials.
2. You land on the WordPress Dashboard. Your menu is simplified to just the tools you need: **Gym** and **Profile**.
3. Head coaches also see the **Users** menu for managing student accounts.

---

## Gym Dashboard

**URL:** `admin.php?page=gym-core`

Your dashboard shows:

- **Left side** -- Gandalf AI chat with the **Coaching Agent** available.
- **Right side** -- Widgets showing today's class check-ins, students nearing promotion, attendance streaks, and at-risk students.

Use the location toggle at the top to switch between Rockford and Beloit.

---

## Checking Attendance

**URL:** `admin.php?page=gym-attendance`

The attendance dashboard has three tabs:

### Today tab
- Shows a live view of today's classes and who has checked in.
- Each class is listed with its time, enrolled count, and checked-in count.
- Use this during or after class to verify attendance.

### Manual check-in
1. On the **Today** tab, find your class.
2. Click **Check In** next to any student who did not use the QR/kiosk.
3. The check-in is recorded immediately.

### History tab
- Searchable table of all past attendance records.
- Filter by: date range, class, student name, or location.
- Use this to look up a specific student's attendance record.

### Trends tab
- Week-over-week attendance stats for your location.
- **At-risk alerts** -- Students whose attendance has been dropping are flagged here. Reach out to them or mention it to Darby.

---

## Reviewing Student Progress

### Check a student's record
1. Ask the Coaching Agent: "Show me [student name]'s progress."
2. Or go to **Gym > Attendance** and search for the student in the History tab.
3. You can see: total classes attended, current streak, belt rank, and time since last promotion.

### Attendance milestones
The system tracks milestones automatically (e.g., 50 classes, 100 classes). When a student hits one, they earn a badge. You can see badges on their profile.

---

## Promotion Eligibility

**URL:** `admin.php?page=gym-promotions`

### Viewing eligible students
1. Go to **Gym > Promotions**.
2. The dashboard lists students who meet or are approaching promotion criteria.
3. Each row shows: student name, current rank, classes since last promotion, and eligibility status.
4. Filter by location using the dropdown at the top.

### Setting a coach recommendation
1. Find the student in the promotions list.
2. Click **Recommend** next to their name.
3. Add your assessment notes (technique readiness, areas to improve, overall recommendation).
4. This flags the student for Darby's final promotion review.

### Executing a promotion (Head Coach / Darby only)
1. Find the recommended student in the promotions list.
2. Click **Promote**.
3. Select the new rank (stripe or belt).
4. Confirm. The system handles the rest:
   - Updates the student's rank record
   - Sends an SMS notification (if the student opted in)
   - Awards any applicable badges
   - Posts a celebration via Gandalf

---

## Using the Coaching AI Agent

### Getting started
1. On the Gym Dashboard, select **Coaching Agent** from the agent dropdown.
2. Ask questions in plain English. Examples:
   - "Who is eligible for promotion at Rockford?"
   - "Show me attendance trends for the Tuesday evening BJJ class."
   - "What should I focus on for [student name]'s next assessment?"
   - "Which students have missed more than 2 weeks?"

### Class briefings
Before class, ask the Coaching Agent:
- "Give me a briefing for tonight's 6pm BJJ class."
- The agent returns: enrolled students, any returning-from-absence students, students nearing milestones, and promotion candidates in the class.

### Assessment help
When evaluating a student for promotion:
1. Ask: "Help me assess [student name] for [next rank]."
2. The agent reviews their attendance, time at current rank, and any notes.
3. Use the response as a starting point for your evaluation -- you make the final call.

---

## Announcements

**URL:** `admin.php` > Gym > Announcements

- View current announcements from the admin team (schedule changes, events, closures).
- Head coaches can create announcements: click **Add New**, write the message, assign a location, and publish.

---

## Daily Checklist

- [ ] Check the **Gym Dashboard** for today's widgets and any alerts
- [ ] Review the **Attendance > Today** tab before or after your classes
- [ ] Verify all students are checked in for your classes
- [ ] Ask the Coaching Agent for a class briefing before each session
- [ ] Note any students who seem ready for promotion

## Weekly Checklist

- [ ] Review **Attendance > Trends** for your location
- [ ] Check the **Promotions** dashboard for new eligibility
- [ ] Submit coach recommendations for promotion-ready students
- [ ] Follow up with at-risk students flagged in Trends
