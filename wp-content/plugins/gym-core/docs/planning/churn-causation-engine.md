# Churn Causation Engine

> Status: Design — not yet built

Phase 3 moat feature. Plugin-scoped spec extracted verbatim from the master playbook §O, with implementation TODOs, file structure, dependencies, and open questions appended.

Master plan: [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md) (§O).

> **Primary surface:** the **MIA Monitor admin dashboard** (`Gym → MIA Monitor`). Chat is a secondary spot-query surface; the dashboard is the daily ritual.

---

## Why this matters

Pressly (PushPress) and a handful of others output a churn *risk score*. Nobody outputs *causation* — why this specific member is at risk and what to do about it. A score is a leaderboard; causation is a script for the staff member who's about to make the save call. **This is where Gandalf earns its keep.**

## Inputs

- Attendance trajectory (existing) — rolling 4-week / 12-week / 26-week comparisons; "rust risk" >14 days is already a Coach Briefing signal.
- Last-class signal — what they drilled, how the coach assessed it (`gym_technique_attempts.confidence`), did they leave early.
- Injury / medical notes (`_gym_medical_notes`).
- Coach assignment — which coach has seen them most recently. (Used to route the save call to the right person.)
- Membership tier change history — downgrades are a leading indicator.
- Promotion delay — a student who's been at the same belt past the average tenure for that belt is at elevated risk.
- Payment health — failed cards, retried payments, recent dunning contact.
- Parent engagement (for kids) per §N.

## Output: causation packets, not scores

Each at-risk member gets a structured packet that Gandalf renders as a save-call script:

```
Member: Sam Chen (Adult BJJ, Rockford, Blue Belt 2 stripes)
Risk: HIGH (no checkin 23 days, prior cadence 3.1/wk)
Likely cause: combination — knee note from January (medical), last class
              Jan 12 was takedowns + drilling, coach Darby noted "left
              early after 30min" (`gym_technique_attempts.confidence` = 2);
              promotion to 3 stripes overdue by 9 weeks (avg tenure 16wk).
Recommended next step: Coach Darby personal call. Acknowledge the knee,
                       offer a no-takedown class plan for 4 weeks, set a
                       3-stripe test for [date] if attendance returns.
Assigned to: Coach Darby (last-meaningful-touch).
Pending Gandalf action: Draft text to Sam? (Y/N)
```

## Where this surfaces

- **MIA Monitor admin dashboard** (primary surface). New top-level admin page `Gym → MIA Monitor`. Three columns: HIGH / MEDIUM / LOW risk, each showing a card per at-risk member (name, rank, days-since, top-line cause). Click a card → full causation packet panel slides out. Filters: location, program, assigned coach. Bulk actions: assign, snooze, dismiss with reason. Brand-aligned per brand-guide §6 cards. This is the dashboard staff actually open in the morning.
- **Daily digest** to Joy + Darby (in-app notification + email): "5 members crossed into HIGH risk yesterday; 3 packets ready in MIA Monitor." Link goes to the dashboard.
- **Gandalf chat surface:** Darby or Joy can ask "show me HIGH risk in Rockford this week" and get the same data conversationally. Chat is for spot queries; the dashboard is for the daily ritual.
- **Per-member packet view** embedded in the member admin row (a "Risk" tab on the user profile screen) so anyone looking up a member sees the current packet without leaving the user view.
- **Pending-action queue:** Gandalf can draft a text or schedule a call task; staff approves; approval is logged on the packet.

## Implementation notes

Per-plan:

- Module `src/Risk/` in gym-core with `SignalCollector/`, `Causation/`, `PacketRenderer/`.
- Daily WP-Cron / Action Scheduler job runs `SignalCollector::run()`, writes to `gym_risk_signals` table, then `Causation::compose()` builds a packet per at-risk member, persists in `gym_risk_packets`.
- Causation prompt is a registered Ability in hma-ai-chat that takes the structured signal bundle and returns a packet with cause + recommended action. Use `wp_ai_client_prompt()` with structured-JSON output.
- All save-call drafts ship through the pending-action queue for the first 90 days; flip a feature flag once accuracy is validated.

Implementation TODOs (expanded):

1. **Schema:**
   - `{$wpdb->prefix}gym_risk_signals` — wide table keyed by `(member_id, signal_date)` storing raw signal values (attendance gap, last-class signal, payment health, promotion delay, parent engagement).
   - `{$wpdb->prefix}gym_risk_packets` — `(packet_id, member_id, generated_at, risk_tier, cause_summary, recommended_action, assigned_coach_id, status)` where status flows `open` → `assigned` → `actioned` → `resolved` | `dismissed`.
   - Indexes on `member_id`, `risk_tier`, `status`, `generated_at`.
2. **Module scaffold** `src/Risk/`:
   - `SignalCollector/` — one collector per input class, each implementing a common interface.
   - `Causation/` — composes the structured prompt, calls `wp_ai_client_prompt()` with a JSON schema for cause + action, persists packet.
   - `PacketRenderer/` — turns a packet row into the card / panel / digest / chat payload.
   - `API/` — REST + admin AJAX for MIA Monitor.
3. **Signal collectors:**
   - `AttendanceTrajectoryCollector` — reuses existing rolling-window logic.
   - `LastClassSignalCollector` — joins `gym_attendance` + `gym_technique_attempts` (§M).
   - `MedicalNotesCollector` — reads `_gym_medical_notes`.
   - `CoachAssignmentCollector` — last-meaningful-touch derived from `gym_technique_attempts.coach_id` + attendance.
   - `TierChangeCollector` — WC Subscription switch history.
   - `PromotionDelayCollector` — uses existing PromotionEligibility engine.
   - `PaymentHealthCollector` — WooPayments failed-charge / retry / dunning state.
   - `ParentEngagementCollector` — pulls from `Parent\EngagementSignals` (§N).
4. **`compose_risk_packet` Ability** in hma-ai-chat: accepts the bundle, returns `{ risk_tier, cause_summary, recommended_action, suggested_next_step, assigned_coach_id }`.
5. **MIA Monitor admin page** (`src/Risk/Admin/MiaMonitorPage.php`):
   - Three-column layout (HIGH / MEDIUM / LOW).
   - Card uses brand-guide §6 component tokens.
   - Slide-out panel renders the full packet.
   - Filters: location, program, assigned coach.
   - Bulk actions: assign, snooze (with duration), dismiss (with reason — required).
6. **User-profile "Risk" tab** (`src/Risk/Admin/UserProfileTab.php`): shows current packet inline.
7. **Daily digest job** (`src/Risk/Jobs/DailyDigestJob.php`): morning Action Scheduler job; emails + in-app notifies Joy + Darby with the count + a deep-link.
8. **Gandalf chat surface:** registered query Ability `query_risk_dashboard(filters)` returning the same data conversationally.
9. **Pending-action integration:** outreach drafts (SMS via §I AI-SMS, scheduled task via existing scheduler) flow through the existing Gandalf approval queue. First 90 days = always required; feature flag `GYM_RISK_AUTO_OUTREACH` for cohorts proven safe.
10. **Tests:** unit (each collector returns expected shape; tier thresholding); integration (full packet generation with mocked AI client); REST (cap-gated assign / snooze / dismiss); admin (page renders for admin / coach roles only).
11. **CI gate:** `composer test-all`, PHPStan level 6, ESLint clean.
12. **Feedback loop:** packets stamped `dismissed (reason: not actually at risk)` feed back as labeled training data for prompt-tuning at the monthly Joy/Darby check-in (R3).

## File structure (proposed)

```
wp-content/plugins/gym-core/src/Risk/
├── SignalCollector/
│   ├── CollectorInterface.php
│   ├── AttendanceTrajectoryCollector.php
│   ├── LastClassSignalCollector.php
│   ├── MedicalNotesCollector.php
│   ├── CoachAssignmentCollector.php
│   ├── TierChangeCollector.php
│   ├── PromotionDelayCollector.php
│   ├── PaymentHealthCollector.php
│   └── ParentEngagementCollector.php
├── Causation/
│   ├── PacketComposer.php          # wp_ai_client_prompt() wrapper
│   └── TierClassifier.php          # bundle → HIGH | MEDIUM | LOW
├── PacketRenderer/
│   ├── CardRenderer.php
│   ├── PanelRenderer.php
│   ├── DigestRenderer.php
│   └── ChatRenderer.php
├── Admin/
│   ├── MiaMonitorPage.php          # Gym → MIA Monitor top-level page
│   └── UserProfileTab.php          # "Risk" tab on user-edit screen
├── Jobs/
│   ├── DailySweepJob.php           # collectors + composer orchestrator
│   └── DailyDigestJob.php          # email + in-app notify
├── Repositories/
│   ├── RiskSignalsRepository.php
│   └── RiskPacketsRepository.php
└── API/
    ├── PacketsController.php       # gym/v1/risk/packets
    └── ActionsController.php       # assign / snooze / dismiss endpoints
```

In `wp-content/plugins/hma-ai-chat/`:

```
src/
└── Abilities/
    ├── ComposeRiskPacket.php       # registers compose_risk_packet
    └── QueryRiskDashboard.php      # registers query_risk_dashboard
```

## Dependencies

| Dependency | Source | Notes |
|---|---|---|
| Technique Mastery Graph | §M / [`technique-mastery-graph.md`](./technique-mastery-graph.md) | Provides last-class signal, coach confidence |
| Parent Co-Engagement | §N / [`parent-co-engagement.md`](./parent-co-engagement.md) | `Parent\EngagementSignals` for kids' causation accuracy |
| AI-composed SMS | Phase 2 §I | Outreach drafts for save-call texts |
| Coach Briefing | Phase 2 §G / [`coach-briefing-system.md`](./coach-briefing-system.md) | Existing "rust risk" signal source |
| WooPayments + WC Subscriptions | Already integrated | Tier change + payment-health signals |
| WP 7.0 AI Client | Already live | `wp_ai_client_prompt()` |
| Gandalf Abilities + pending-action queue | hma-ai-chat (live) | Compose + approval gating |
| Attendance + rank data | gym-core M1 (shipped) | Foundation signals |

## Acceptance criteria

- 90% of save-call packets read as "useful, not generic" to Darby in a blind review of 30 packets.
- Save-call → reactivation conversion ≥20% over the first 90 days (we don't have a baseline; we're establishing one).
- Zero generic "haven't seen you in a while" messages reach members; everything is contextualized.

## Open questions

1. **Risk-tier thresholds — Darby-defined or computed.** HIGH / MEDIUM / LOW boundaries: hard-coded (e.g., HIGH = no checkin > 14 days AND prior cadence > 2/wk), computed dynamically per program, or AI-classified directly from the signal bundle? Trade-off: explainability vs. adaptiveness.
2. **Snooze semantics.** When a coach snoozes a HIGH packet for 7 days, does the daily sweep regenerate the packet on day 8 (new cause, possibly new tier), or restore the snoozed one? First option is simpler; second is closer to a save-call notepad.
3. **"Last meaningful touch" coach assignment.** Defined by most-recent `gym_technique_attempts.coach_id`, most-recent attendance with the same coach on roster, or a configurable lookback? Affects whose queue the packet lands in.
4. **PII in the packet.** Causation summaries reference medical notes by content (e.g., "knee note from January"). Are those sentences safe to render in a daily-digest email, or should the email link to the dashboard and keep medical context behind an auth wall? Default to the cautious option until Joy weighs in.
5. **Auto-outreach graduation criteria.** What metric flips `GYM_RISK_AUTO_OUTREACH` on for a cohort — N consecutive weeks with zero off-brand approvals required, ≥X% reply rate, or Darby's manual sign-off? Define before merging the feature flag.

## Cross-references

- Plan section: §O of [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md).
- Related docs: [`technique-mastery-graph.md`](./technique-mastery-graph.md) (§M — last-class signal source), [`parent-co-engagement.md`](./parent-co-engagement.md) (§N — kids' causation), [`coach-briefing-system.md`](./coach-briefing-system.md) (§G — existing rust-risk signal), [`future-features-plan.md`](./future-features-plan.md) (Phase 2 §I AI-SMS), [`joy-darby-monthly-checkin.md`](./joy-darby-monthly-checkin.md) (R3 — feedback-loop ritual).
- Brand: [`docs/brand-guide.md`](../brand-guide.md) §6 cards for MIA Monitor.
