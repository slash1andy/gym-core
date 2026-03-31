# GoHighLevel Decommission Checklist

**Milestone:** M2.7 — GHL Decommission Checklist
**Depends on:** M2.1 through M2.6 complete
**Current GHL cost:** ~$137/mo ($97/mo + $40/week)
**Target savings:** $137/mo ($1,644/yr)

This checklist documents every verification, migration, and cutover step required to
fully decommission GoHighLevel. Do not cancel the GHL subscription until every item
in the Pre-Cutover, Data Verification, Phone Number Porting, and Automation Cutover
sections is complete.

---

## Pre-Cutover Verification

All WordPress replacements must be live and functional before disabling anything in GHL.

- [ ] **All contacts migrated from GHL to Jetpack CRM (with count verification)**
  - GHL export count: ______
  - Duplicates removed: ______
  - Junk contacts removed (auto-added inbound callers, etc.): ______
  - Imported to Jetpack CRM: ______
  - Delta accounted for: ______ (should equal duplicates + junk)
  - Reference: M2.2 acceptance criteria

- [ ] **Email sequences recreated in AutomateWoo + MailPoet**

  | GHL Sequence | AutomateWoo/MailPoet Workflow | Status |
  |---|---|---|
  | New member welcome email | AW: New Member Welcome Sequence (email day 0, day 3, day 7) | [ ] |
  | Trial class follow-up email | AW: Trial Class Follow-Up (email day 1) | [ ] |
  | Failed payment notification | AW: Failed Payment Recovery (email immediate, day 3) | [ ] |
  | Lapsed member win-back | AW: Lapsed Member Win-Back (email day 30, day 60, final offer day 90) | [ ] |
  | Subscription renewal reminder | AW: Renewal Reminder (email 7 days before) | [ ] |
  | Birthday email | AW: Birthday Automation (email on birthday) | [ ] |
  | Review request | AW: Review Request (email 30 days after signup) | [ ] |
  | Schedule change notification | MailPoet: Schedule Change Notification template | [ ] |
  | Belt promotion congratulations | MailPoet: Belt Promotion template | [ ] |
  | Monthly newsletter | MailPoet: Monthly Newsletter template | [ ] |

- [ ] **SMS sequences recreated in AutomateWoo + Twilio**

  | GHL SMS Sequence | AutomateWoo + Twilio Workflow | Status |
  |---|---|---|
  | New form submission welcome SMS | AW: Welcome SMS with booking link (day 1) | [ ] |
  | Trial class booked — confirmation | AW: Trial Confirmation SMS (immediate) | [ ] |
  | Trial class — 24hr reminder | AW: Trial Reminder SMS (24hr before) | [ ] |
  | Trial class — day-of confirmation | AW: Trial Day-Of SMS (morning of) | [ ] |
  | Trial attended — follow-up nudge | AW: Trial Follow-Up SMS (1hr after, day 3 if no conversion) | [ ] |
  | Lead gone cold — automated nudge | AW: Cold Lead Nudge (3 days after no booking) | [ ] |
  | Failed payment — SMS reminder | AW: Failed Payment SMS (day 1, final day 7) | [ ] |
  | No attendance re-engagement | AW: Lapsed Attendance SMS (configurable day threshold) | [ ] |
  | Belt testing reminder | AW: Belt Testing Reminder SMS (to eligible members) | [ ] |
  | Schedule change / event blast | AW: Schedule Change Bulk SMS (segment-targeted) | [ ] |
  | Renewal reminder SMS | AW: Renewal Reminder SMS (3 days before) | [ ] |
  | Birthday SMS | AW: Birthday SMS (on birthday) | [ ] |

- [ ] **Pipeline stages configured in Jetpack CRM**

  | GHL Stage | Jetpack CRM Stage | Verified |
  |---|---|---|
  | Raw leads | New Lead | [ ] |
  | Call confirmed 24hr | Contacted | [ ] |
  | Confirmed day of | Trial Scheduled | [ ] |
  | Showed + signed up | Closed Won (auto-creates subscription) | [ ] |
  | Showed + started free trial | Trial Completed | [ ] |
  | Showed + didn't sign up | Offer Made / Follow-Up | [ ] |
  | No show / rescheduling | Rescheduling | [ ] |
  | Showed + not sold / comeback appt | Comeback | [ ] |
  | Archive (dead leads) | Closed Lost | [ ] |

- [ ] **Lead capture forms creating CRM contacts**
  - Website contact form (M1.7) creates Jetpack CRM contact + pipeline entry
  - Lead source tracking working (website form, phone call, walk-in, referral, social)
  - Location assignment (Rockford/Beloit) applied correctly

- [ ] **Staff trained on Jetpack CRM, AutomateWoo, MailPoet dashboards**
  - Darby: [ ] Amanda: [ ] Joy: [ ] Matt: [ ] Rachel: [ ]

- [ ] **30-day parallel run completed**
  - Start date: ______
  - End date: ______
  - Both GHL and WordPress workflows running simultaneously
  - No leads lost during parallel period
  - Sign-off from Darby/Amanda: ______

---

## Data Verification

Spot-checks to confirm data integrity after migration.

- [ ] **Spot-check 20 contacts: compare GHL record vs Jetpack CRM record**
  - Name, email, phone match: [ ]
  - Tags/status match: [ ]
  - Location correct: [ ]
  - Notes transferred (or documented as not migrated): [ ]
  - Document any discrepancies and resolution

- [ ] **Verify all active member contacts are present**
  - GHL active member count: ______
  - Jetpack CRM active member count: ______
  - Counts match (or delta explained): [ ]

- [ ] **Verify pipeline entries preserved**
  - GHL active pipeline entries count: ______
  - Jetpack CRM active pipeline entries count: ______
  - Spot-check 5 pipeline entries for correct stage: [ ]

- [ ] **Verify SMS conversation history preserved (or documented as not migrated)**
  - If migrated: spot-check 5 contacts for conversation threads: [ ]
  - If not migrated: document decision and reasoning here: ______
  - Staff informed that historical SMS threads may not be available: [ ]

- [ ] **Verify email open/click history preserved (or documented)**
  - If migrated: spot-check 5 contacts for engagement data: [ ]
  - If not migrated: document decision and reasoning here: ______
  - Note: MailPoet starts tracking fresh from migration date

---

## Phone Number Porting

GHL may own phone numbers used for SMS sending. These must be ported before cancellation
or they will be lost permanently.

- [ ] **Identify if GHL owns any phone numbers used for SMS**
  - List all GHL phone numbers: ______
  - Which numbers are published on marketing materials, website, or member communications: ______

- [ ] **If yes: port numbers to Twilio before cancellation**
  - Port request submitted to Twilio: [ ]
  - Port request confirmed by carrier: [ ]
  - Estimated port completion date: ______
  - Port completed and verified: [ ]

- [ ] **Verify SMS sending works from Twilio number**
  - Send test SMS from ported number: [ ]
  - Receive test SMS to ported number: [ ]
  - Two-way conversation confirmed: [ ]

- [ ] **Update any published materials with the phone number**
  - Website updated: [ ]
  - Google Business profile updated: [ ]
  - Printed materials noted for next reprint: [ ]
  - Social media profiles updated: [ ]

---

## Automation Cutover

The actual switch from GHL to WordPress. Do this during a low-traffic period (e.g.,
Sunday evening).

- [ ] **Disable all GHL workflows (but don't delete)**
  - List of GHL workflows disabled:
    - [ ] New lead follow-up sequence
    - [ ] Trial class booking confirmation
    - [ ] Trial class reminder (24hr)
    - [ ] Trial follow-up (post-visit)
    - [ ] Cold lead nurture
    - [ ] Failed payment sequence
    - [ ] Re-engagement sequence
    - [ ] Schedule change notifications
    - [ ] Bulk SMS campaigns
    - [ ] (add any others): ______
  - All disabled, none deleted (in case rollback needed): [ ]

- [ ] **Enable all AutomateWoo workflows**
  - All workflows listed in Pre-Cutover section activated: [ ]
  - Workflow trigger conditions verified (not double-firing): [ ]

- [ ] **Verify email delivery (test each template)**
  - Send test of each MailPoet/AutomateWoo email template to staff inbox: [ ]
  - Check deliverability (not going to spam): [ ]
  - Check formatting on mobile: [ ]
  - DKIM/SPF/DMARC passing: [ ]

- [ ] **Verify SMS delivery (test each template)**
  - Send test SMS for each template to a staff phone number: [ ]
  - Verify message content and merge tags render correctly: [ ]
  - Verify opt-out instructions present (TCPA compliance): [ ]

- [ ] **Monitor for 72 hours for any missed triggers**
  - Day 1 check: ______ (date) — Issues found: ______
  - Day 2 check: ______ (date) — Issues found: ______
  - Day 3 check: ______ (date) — Issues found: ______
  - All triggers firing correctly: [ ]

---

## Staff Transition

- [ ] **Training session completed for Darby, Amanda, Joy**
  - Jetpack CRM: contact management, viewing/editing records, adding notes: [ ]
  - AutomateWoo: viewing workflow status, pausing workflows per contact: [ ]
  - MailPoet: sending newsletters, viewing email analytics: [ ]
  - SMS: sending messages from CRM contact record, viewing conversations: [ ]
  - Date completed: ______

- [ ] **Training session completed for Matt, Rachel (sales)**
  - Jetpack CRM: pipeline view, moving leads between stages: [ ]
  - Jetpack CRM: adding notes and activity to contact records: [ ]
  - SMS: sending lead follow-up messages from CRM: [ ]
  - Lead assignment and source tracking: [ ]
  - Date completed: ______

- [ ] **"Where to find things" quick reference card created**
  - One-page PDF or printed card covering:
    - How to view/edit a contact
    - How to send an SMS
    - How to check the sales pipeline
    - How to view email campaign results
    - How to pause an automation for a specific contact
    - How to add a new lead manually
  - Distributed to all staff: [ ]

- [ ] **Support contact documented (who to ask for help)**
  - Primary contact: Andrew
  - How to reach: ______
  - Documented and shared with staff: [ ]

---

## Cancellation

Do not execute until all sections above are complete and signed off.

- [ ] **GHL subscription cancellation scheduled (not immediate -- allow 30-day buffer)**
  - Cutover date: ______
  - Buffer end date (cutover + 30 days): ______
  - Cancellation effective date: ______
  - Cancellation submitted: [ ]

- [ ] **Final GHL data export archived (backup)**
  - Full contact export (CSV): [ ]
  - Pipeline data export: [ ]
  - Workflow/automation configurations (screenshots or export): [ ]
  - SMS conversation history export (if available): [ ]
  - Archived to Google Drive location: ______

- [ ] **GHL login credentials documented and archived**
  - Account email: ______
  - Stored in: ______ (e.g., password manager, secure doc)
  - Note: retain for 90 days post-cancellation in case reactivation needed

- [ ] **Estimated monthly savings documented**
  - GHL subscription: $137/mo
  - Twilio cost (new): -$____/mo (estimated $20-50 based on volume)
  - **Net monthly savings: $____/mo**
  - **Annualized savings: $____/yr**

---

## Post-Cutover Monitoring (first 30 days)

Weekly checks for the first month after GHL is disabled. Assign a responsible person
for each week.

### Week 1 (dates: ______)
- [ ] No leads falling through cracks (compare form submissions to CRM entries)
- [ ] All automated emails sending (check AutomateWoo logs)
- [ ] All automated SMS sending (check Twilio delivery logs)
- [ ] Pipeline progression working (leads moving through stages)
- [ ] Staff friction/confusion documented and addressed
- [ ] Reviewer: ______

### Week 2 (dates: ______)
- [ ] No leads falling through cracks
- [ ] All automated emails sending
- [ ] All automated SMS sending
- [ ] Pipeline progression working
- [ ] Staff friction/confusion documented and addressed
- [ ] Reviewer: ______

### Week 3 (dates: ______)
- [ ] No leads falling through cracks
- [ ] All automated emails sending
- [ ] All automated SMS sending
- [ ] Pipeline progression working
- [ ] Staff friction/confusion documented and addressed
- [ ] Reviewer: ______

### Week 4 (dates: ______)
- [ ] No leads falling through cracks
- [ ] All automated emails sending
- [ ] All automated SMS sending
- [ ] Pipeline progression working
- [ ] Staff friction/confusion documented and addressed
- [ ] Reviewer: ______

### 30-Day Review
- [ ] All monitoring weeks passed without critical issues
- [ ] Staff comfortable with new tools (verbal confirmation from each)
- [ ] GHL subscription cancellation confirmed (if buffer period still active)
- [ ] Decommission declared complete
- [ ] Date: ______ Signed off by: ______
