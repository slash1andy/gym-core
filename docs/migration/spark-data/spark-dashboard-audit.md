# Spark Membership Dashboard Audit

**Captured:** 2026-03-30 from screenshot while logged in as Andrew Wikel
**Account:** Haanpaa Martial Arts — Account ID: 3208
**Platform:** Spark Basic

## Dashboard Alerts

1. **Attendance Barcode Labels** — "You have attendance barcode labels to print." (Print Now button)
2. **On Hold Memberships** — "You have 5 memberships that are on hold and not being collected on." (View Now button)

## Available SMS Credits: 992

## Left Sidebar Navigation (Spark Admin)

These are the menu items visible in the left sidebar, which maps the full Spark feature set:

| Menu Item | Has Submenu? | Relevant Playbook |
|-----------|-------------|-------------------|
| Dashboard | No | — |
| ADD A NEW CONTACT | No | — |
| Contacts | Yes (dropdown) | PB5 (member notes) |
| Calendar | Yes (dropdown) | PB3 (class schedule) |
| Attendance | Yes (dropdown) | Data dump request |
| Ranks | Yes (dropdown) | PB4 (belt ranks) — DONE via Andrew |
| Tasks | No | — |
| Point of Sale | No | — |
| Point of Sale Settings | Yes (dropdown) | PB2 (POS products) |
| Communication Center | Yes (dropdown) | — |
| (more items below fold) | — | Need to scroll |

## Top Navigation Bar Icons (left to right)

1. Add contact
2. Groups/contacts
3. Bank/billing
4. Calendar
5. Video/media
6. Documents
7. Messaging/chat
8. Reports/grid
9. Settings/admin
10. Alert bell (has notification)
11. Clock/history

## Dashboard Widgets

1. **Today's Schedule** — Calendar showing March 2026, "show only my appointments"
2. **Tasks** — Checkbox widget, "show only my tasks"
3. **New Contacts** — Shows recent prospect additions (4 visible: added yesterday through 4 days ago, all tagged "Pro")

## Key Observations for Migration

- Spark plan is "Basic" tier — may limit available features/exports
- 5 memberships on hold = potential billing issues to resolve before migration
- 992 SMS credits remaining = active SMS usage (important for Twilio migration sizing)
- New contacts widget shows active lead flow — CRM migration (M2) should capture these
- The sidebar confirms Spark has: Contacts, Calendar, Attendance, Ranks, POS, Communication Center — all areas we need to extract from
- Need to scroll sidebar to see if there are additional menu items (Settings, Reports, etc.)
