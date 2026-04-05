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
   - "Look up [student name] -- what's their rank, attendance, and Foundations status?"
   - "Who in the Foundations program needs coach rolls?"

### What the Coaching Agent knows
The Coaching Agent has access to:
- Member records: belt rank, attendance history, streaks, badges, and milestones
- Promotion eligibility: who meets criteria, who is close, and time at current rank
- Foundations status: which students are in each phase and what they need to progress
- Class rosters: enrolled students for any upcoming class
- At-risk alerts: students with declining attendance

### Class briefings (pre-class intelligence)
Before each class, ask the Coaching Agent for a briefing. This gives you a snapshot of who to expect and what to watch for.

1. Ask: "Give me a briefing for tonight's 6pm BJJ class."
2. The agent returns:
   - **Enrolled students** for this session
   - **Returning-from-absence students** -- members who have not trained recently
   - **Milestone watch** -- students close to attendance milestones (e.g., 50th or 100th class)
   - **Promotion candidates** -- students who are eligible or nearly eligible
   - **Foundations students** -- who is in Foundations and what phase they are in
3. Use this to personalize your coaching: welcome back absent students, acknowledge milestones, and keep an eye on promotion-ready practitioners.

### Assessment help
When evaluating a student for promotion:
1. Ask: "Help me assess [student name] for [next rank]."
2. The agent reviews their attendance, time at current rank, and any notes.
3. Use the response as a starting point for your evaluation -- you make the final call.

### Member lookup
To quickly check on any student:
1. Ask: "Show me [student name]'s full profile."
2. The agent returns: current rank, total classes, current streak, badges earned, Foundations status (if applicable), and time since last promotion.

---

## Foundations Program (New Student Safety Gate)

Foundations is the onboarding program for new adult BJJ students. Students in Foundations cannot live-train with non-coaches until they are cleared. This protects both the new student and existing members.

### The Foundations lifecycle

**Phase 1: Coached instruction only**
- The student attends classes but only drills and practices with coaches.
- After completing the required number of classes (configured in settings), they advance to Phase 2.

**Phase 2: Supervised rolls with coaches**
- The student completes supervised rolls with coaches to demonstrate control and safety.
- To record a coach roll: go to the student's profile or ask the Coaching Agent: "Record a coach roll for [student name]."
- After completing the required number of coach rolls, they advance to Phase 3.

**Phase 3: Additional classes**
- The student continues attending classes until they reach the total class threshold.
- Once the threshold is met, they are automatically cleared.

**Cleared**
- The student can now train with all partners in all classes.
- Their Foundations status changes to "Cleared" on their profile.

### Managing Foundations as a coach
- **Check status:** Ask the Coaching Agent: "What's [student name]'s Foundations status?" or view it on their member profile.
- **Record coach rolls:** After a supervised roll, record it promptly so the student's progress updates.
- **Class briefings include Foundations info:** When you request a class briefing, the agent tells you which students are in Foundations and what phase they are in, so you can pair them appropriately.
- **Thresholds are configurable:** The head instructor or admin sets the required class counts and roll counts in **Settings > Gym Core > Ranks**. If you think thresholds need adjusting, talk to the head instructor.

Note: Time spent in Foundations counts toward White Belt stripe progression. Foundations is a safety status, not a belt rank.

---

## Viewing Promotion Eligible Lists

The Promotions dashboard shows all students who meet or are approaching promotion criteria.

### Quick access
1. Go to **Gym > Promotions** (`admin.php?page=gym-promotions`).
2. Use the location filter to show students at your location.
3. Each row shows: student name, current rank, classes since last promotion, eligibility status, and any existing coach recommendations.

### Using the Coaching Agent
For a faster overview, ask:
- "Who is eligible for promotion at [location]?"
- "Show me all students within 5 classes of promotion eligibility."
- "Which white belts are closest to their first stripe?"

### Submitting recommendations
When you identify a student ready for promotion:
1. Click **Recommend** next to their name on the Promotions dashboard.
2. Add your assessment notes: technique readiness, areas of strength, anything to watch.
3. The recommendation flags the student for the head instructor's final review.
4. You can also ask the Coaching Agent: "Recommend [student name] for promotion" and it will walk you through the process.

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
