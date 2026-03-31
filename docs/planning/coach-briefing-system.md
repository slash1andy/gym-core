# Coach Briefing System — Design Document

> Pre-class intelligence briefing that gives coaches everything they need to run the best class possible. Surfaced 15-30 minutes before class start time, delivered via push notification / SMS with a link to the briefing page.

## Problem

Coaches currently walk into class with no structured preparation beyond their own memory. They don't know:
- Which students are new and need extra attention
- Which Foundations students are approaching clearance milestones
- What curriculum they're supposed to teach that day
- Whether any students have injury notes or special considerations
- What announcements Darby or Joy want communicated
- Whether their hours from last session are logged

This leads to inconsistent class quality, missed announcements, and Foundations students slipping through without proper supervision.

## Solution: The Coach Briefing

A single-page briefing generated before each scheduled class, accessible via:
- Push notification / SMS link (15-30 min before class)
- Coach dashboard in wp-admin
- Kiosk "Coach Mode" toggle
- Gandalf AI agent (voice query: "What's my briefing for tonight's class?")

---

## Briefing Sections

### 1. Class Identity

Basic class context — what are you teaching, where, and when.

| Field | Source |
|-------|--------|
| Class name | `gym_class` CPT title |
| Program | `gym_program` taxonomy |
| Location | `gym_location` taxonomy |
| Day / Time | Class schedule meta |
| Expected duration | Class meta |
| Room / Mat area | Class meta (if multi-room) |

### 2. Student Roster & Alerts

Who's coming and what the coach needs to know about them. Built from:
- Check-in history patterns (forecasted attendance based on last 4 weeks)
- Pre-registered / reserved spots (if booking system is active)
- Walk-in buffer estimate

**For each expected student:**

| Field | Source | Alert Level |
|-------|--------|-------------|
| Name + rank | `gym_ranks` table | — |
| Foundations status | `FoundationsClearance` | High — coach must pair with them |
| Foundations phase | Phase 1 / 2 / 3 / cleared | High if Phase 2 (coach roll needed) |
| Days since last class | `gym_attendance` table | Warn if >14 days (rust risk) |
| Injury / medical note | User meta `_gym_medical_notes` | High — always surface |
| Promotion approaching | `PromotionEligibility` | Info — coach may want to evaluate |
| Stripe/belt milestone today | Calculated | Info — celebrate if earned this class |
| First class ever | Attendance count = 0 | High — welcome, orient, pair up |
| Birthday within 7 days | User meta / WP profile | Info — acknowledge in class |

**Alert priority in the briefing:**
1. **Foundations students** — who they are, what phase, whether coach rolls are needed today
2. **First-timers** — brand new, need orientation and a safe training partner
3. **Returning after long absence** — may need to ease back in
4. **Injury/medical flags** — what to avoid, which drills to modify
5. **Promotion candidates** — coach should evaluate performance today

### 3. Curriculum Block

What to teach in this class. Sourced from a curriculum plan (if configured) or suggested by the system.

| Field | Source |
|-------|--------|
| Today's technique / topic | Curriculum calendar (admin-managed) |
| Technique video(s) | Linked media (YouTube / VideoPress URL) |
| Drill progression | Curriculum notes |
| Positional focus | Curriculum metadata |
| Competition prep notes | If within 2 weeks of a scheduled comp |

**Curriculum management** — a simple admin interface where Darby sets the weekly/monthly curriculum plan:
- `gym_curriculum` CPT or admin calendar view
- Fields: date, program, technique name, video URL, notes, positional focus
- Can be recurring (e.g., "Monday Fundamentals = guard passing" every Monday)
- Coaches can add class notes after teaching (what was actually covered, what to revisit)

### 4. Announcements

Things the coach needs to communicate to students during or after class.

| Type | Source | Display |
|------|--------|---------|
| Global announcements | Admin option / custom post | Always shown |
| Location-specific | Filtered by `gym_location` | Shown for matching location |
| Time-sensitive | Start/end date on announcement | Auto-expire |
| Pinned by Darby/Joy | Admin flag | Shown until manually cleared |

**Examples:**
- "Reminder: gym closed July 4th weekend"
- "Belt testing event Saturday March 15 — encourage students to sign up"
- "New class time starting next week: Thursday AM BJJ moves to 10:30"
- "Joy needs: remind members to update payment info if card on file expired"
- "Darby says: emphasize takedown defense this week across all BJJ classes"

**Implementation:** `gym_announcement` CPT with:
- Title, body, type (global / location / program)
- Start date, end date (auto-expire)
- Target: all coaches, specific location, specific program
- Pinned flag (sticky until cleared)
- Author (Darby, Joy, Amanda)

### 5. Operational Reminders

System-generated reminders for the coach themselves.

| Reminder | Trigger |
|----------|---------|
| Log your hours | If previous class hours not yet logged |
| Submit class notes | If last class taught has no post-class notes |
| Review promotion candidates | If eligible members list has unreviewed entries |
| Foundations coach roll needed | If a Phase 2 student is in today's roster |
| Equipment check | Weekly reminder (configurable day) |
| First aid kit check | Monthly reminder |

### 6. Post-Class Debrief (optional feedback loop)

After class ends, prompt the coach to record:
- Actual technique taught (confirm or override curriculum)
- Student performance notes (especially Foundations and promotion candidates)
- Any incidents or concerns
- Coach roll completed? (for Foundations Phase 2 students)
- Hours logged?

This feeds back into the next briefing — if a coach noted "Student X struggled with guard passing," the next briefing can flag it.

---

## Delivery Channels

| Channel | When | Format |
|---------|------|--------|
| SMS (Twilio) | 30 min before class | Short summary + link to full briefing |
| Push notification | 30 min before class | Via WordPress app or browser push |
| wp-admin Dashboard | Always available | Full briefing page |
| Kiosk "Coach Mode" | When coach authenticates at kiosk | Briefing displayed before check-in mode activates |
| Gandalf AI | On demand | Voice/chat query: "What's my briefing?" |
| Email digest | Daily AM (configurable) | All classes for that day, combined |

**SMS format example:**
```
Coach Briefing — 6:00 PM Fundamentals BJJ (Rockford)

⚠ 2 Foundations students (Jake M. — Phase 2, needs coach roll)
👋 1 first-timer (Sarah K.)
📋 Curriculum: Guard passing series (video: [link])
📢 Announcement: Belt testing sign-up closes Friday

Full briefing: [link]
```

---

## Data Model

### New CPTs / Tables

| Entity | Type | Purpose |
|--------|------|---------|
| `gym_announcement` | CPT | Admin/staff announcements with targeting |
| `gym_curriculum` | CPT | Daily/weekly curriculum plan per program |
| `{prefix}gym_class_notes` | Custom table | Post-class debrief notes by coaches |
| `{prefix}gym_coach_hours` | Custom table | Coach hour logging |

### New User Meta

| Key | Type | Purpose |
|-----|------|---------|
| `_gym_medical_notes` | text | Injury/medical flags for briefing alerts |
| `_gym_coach_briefing_prefs` | array | Delivery preferences (SMS, email, push) |

### New Options

| Key | Default | Purpose |
|-----|---------|---------|
| `gym_core_briefing_enabled` | yes | Master toggle |
| `gym_core_briefing_lead_time` | 30 | Minutes before class to send briefing |
| `gym_core_briefing_sms_enabled` | yes | Send SMS briefings |
| `gym_core_briefing_email_digest` | yes | Daily AM email digest |
| `gym_core_briefing_debrief_prompt` | yes | Prompt for post-class notes |

---

## Settings (WC > Settings > Gym Core > Briefings)

New settings section in the Admin Settings tab:

- **Enable Coach Briefings** — master toggle
- **Lead Time** — minutes before class (default: 30)
- **SMS Briefings** — send SMS summary (requires Twilio configured)
- **Email Digest** — daily AM digest of all classes
- **Post-Class Debrief** — prompt coaches for notes after class
- **Attendance Forecast Window** — weeks of history for forecasting (default: 4)
- **Absence Alert Threshold** — days since last class to flag (default: 14)
- **Birthday Alert Window** — days before birthday to surface (default: 7)

---

## Integration Points

| System | How Briefing Uses It |
|--------|---------------------|
| `FoundationsClearance` | Surfaces Foundations students, phase, coach roll needs |
| `PromotionEligibility` | Flags approaching/eligible promotions |
| `AttendanceStore` | Forecasts roster, detects absences, counts classes |
| `RankDefinitions` | Shows student rank context |
| `TwilioClient` / SMS | Sends briefing notifications |
| `gym_class` CPT / Schedule | Determines which class, when, where |
| Gandalf AI agents | Coaching agent can query briefing data |
| Gamification / BadgeEngine | Milestone celebrations to acknowledge in class |

---

## Implementation Plan

### Phase 1 — Core Briefing (M4 scope)
- `gym_announcement` CPT with location/program targeting
- Briefing generator: roster forecast + Foundations alerts + announcements
- wp-admin briefing page (coach dashboard)
- Settings section

### Phase 2 — Curriculum + Delivery (M4/M6 scope)
- `gym_curriculum` CPT with video links
- SMS briefing delivery via Twilio
- Daily email digest
- Post-class debrief prompt and storage

### Phase 3 — AI Integration (M6 scope)
- Gandalf Coaching agent can query/summarize briefings
- Natural language: "Who's in my 6pm class tonight?"
- Auto-generated curriculum suggestions based on class history
- Post-class notes fed into student progression tracking

---

## Open Questions

1. **Curriculum ownership** — Does Darby set curriculum centrally, or do individual coaches plan their own classes? (Affects whether curriculum is prescribed or suggested.)
2. **Hour logging** — Is this for payroll, or just operational tracking? Determines required precision.
3. **Video hosting** — YouTube links, or should we use Jetpack VideoPress for private technique videos?
4. **Coach roles** — Are all coaches equal, or is there a head coach / assistant coach distinction that affects briefing content?
5. **Multi-coach classes** — Can a class have multiple coaches? If so, does each get a briefing or is it shared?
