# Joy / Darby Monthly Check-in on Financial NLP Outputs

> Status: Design — not yet built

Ongoing operational ritual. Plugin-scoped spec extracted verbatim from the master playbook §R3, with implementation TODOs, file structure, dependencies, and open questions appended.

Master plan: [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md) (§R3).

---

## Why this matters

The AI surfaces will drift. Without a structured monthly sit-down with the people who use them most, accuracy regressions compound silently and trust erodes.

## Cadence

First Tuesday of the month, 30 min, after Joy's monthly close.

## Agenda (carved into a Gandalf workflow)

1. Joy reviews the previous month's NLP financial dashboard answers and rates accuracy on the three top recurring questions (revenue MoM, failed-payment list, payout reconciliation).
2. Any answer rated below 4/5 — Andrew investigates the underlying query / prompt and patches.
3. Darby reviews any AI-composed SMS sent to members in the prior month with reply-rate or save-call outcomes attached. Flag any that read off-brand.
4. Decision point: are there any AI-composed message types ready to graduate from "approval-required" to "direct-send for cohort X"?
5. Output: action items captured in the gym-core admin → Tasks page.

## Implementation notes

The check-in itself is a 30-minute meeting. The supporting code is a Gandalf-driven workflow that pre-builds the agenda, surfaces last month's data, and captures action items into the existing admin → Tasks page.

Implementation TODOs (expanded):

1. **Workflow registration** in hma-ai-chat: a registered `monthly_ai_review` workflow that orchestrates the agenda steps as Abilities. Calling the workflow auto-builds the meeting brief.
2. **Data prep Abilities** (new in hma-ai-chat, reuse Phase 2 §H Finance agent + §I AI-SMS infra):
   - `summarize_finance_questions_last_month` — pulls the prior month's NLP dashboard query log with answers; emits a list ready for 1–5 rating.
   - `summarize_ai_sms_last_month` — pulls AI-composed SMS sends with reply-rate / save-call outcome where known; flags any with brand-voice anomalies surfaced by an automated voice-tone check.
   - `list_pending_message_graduations` — for each cohort × message-type, computes time-in-approval, approval rate, off-brand rate; recommends graduation candidates (still requires Darby's manual sign-off).
3. **Rating capture surface** (`src/Risk/Admin/MonthlyReviewPage.php` — new) under `Gym → Monthly AI Review`:
   - Brand-aligned per `docs/brand-guide.md` §6.
   - Each finance-dashboard question rendered as a row with a 1–5 rating scale; comment field optional.
   - Persists ratings into a new `{$wpdb->prefix}gym_ai_review_ratings` table.
   - Same surface lists prior-month AI SMS sends with thumbs-up / thumbs-down + free-text "off-brand" flag.
   - "Promote to direct-send" buttons per cohort × message-type — flips the relevant feature flag with a logged audit entry.
4. **Action-items integration** with the existing admin → Tasks page:
   - Each below-4 rating auto-creates a Task assigned to Andrew with a deep-link back to the offending question + answer.
   - Each off-brand SMS flag auto-creates a Task to review the prompt template.
   - Each cohort graduation creates a Task to monitor outcomes for 30 days.
5. **Calendar integration** (deferred to v2): the meeting itself stays on Joy's calendar today; future v2 may wire a Gandalf calendar Ability.
6. **Persistence:** ratings, flags, graduation decisions, and action items stored so accuracy trend over multiple months is queryable. Surface a small "12-month trend" widget on the review page.
7. **Tests:** unit (rating CRUD, action-item creation, graduation-flip audit logging); integration (workflow end-to-end with mocked Finance + AI-SMS data).
8. **CI gate:** `composer test-all`, PHPStan level 6, ESLint clean.

## File structure (proposed)

```
wp-content/plugins/gym-core/src/MonthlyReview/
├── Admin/
│   └── MonthlyReviewPage.php          # Gym → Monthly AI Review
├── Models/
│   └── ReviewRating.php                # CRUD over gym_ai_review_ratings
├── GraduationEngine/
│   └── DirectSendPromoter.php          # cohort × message-type feature-flag flips
└── Repositories/
    └── ReviewRatingsRepository.php
```

In `wp-content/plugins/hma-ai-chat/`:

```
src/
├── Workflows/
│   └── MonthlyAiReviewWorkflow.php     # registers monthly_ai_review
└── Abilities/
    ├── SummarizeFinanceQuestions.php
    ├── SummarizeAiSms.php
    └── ListPendingGraduations.php
```

## Dependencies

| Dependency | Source | Notes |
|---|---|---|
| Joy NLP financial dashboard | Phase 2 §H / [`future-features-plan.md`](./future-features-plan.md) §3.6 | Source of finance-question log |
| AI-composed SMS | Phase 2 §I | Source of SMS send log + reply-rate data |
| Churn Causation Engine | §O / [`churn-causation-engine.md`](./churn-causation-engine.md) | Save-call outcomes feed Darby's review |
| Gandalf workflow + Abilities registry | hma-ai-chat (live) | Hosts the orchestration |
| Admin → Tasks page | gym-core (existing) | Action-item destination |
| Brand guide | [`docs/brand-guide.md`](../brand-guide.md) §6 | Review-page UI |

## Acceptance criteria

- Meeting consistently runs the first Tuesday of the month with the brief auto-prepared by Gandalf.
- Below-4 ratings convert to Andrew-assigned tasks with deep-links the same day.
- Cohort graduation decisions are logged with audit trail and outcomes monitored for 30 days post-flip.
- 12-month accuracy trend visible on the review page; regressions are caught within one month rather than one quarter.
- Off-brand SMS flags trigger prompt-template review tasks within the same business day.

## Open questions

1. **Rating scale granularity.** 1–5 or 1–10? Plan says "below 4/5" so the scale is 1–5 — confirm it's not too coarse to detect drift over multiple months.
2. **Which "three top recurring questions" anchor the rating?** Plan lists revenue MoM, failed-payment list, payout reconciliation. Are these fixed, or rotated as Joy's question mix evolves? If fixed, build them as canonical query templates; if rotated, rank by frequency.
3. **Off-brand detection.** Plan implies manual flagging by Darby. Should we additionally run an automated brand-voice check (per `docs/brand-guide.md` §8) over each AI-composed SMS as a pre-meeting summary? Reduces Darby's load but risks false positives.
4. **Graduation threshold criteria.** What metrics make a cohort × message-type "ready to graduate"? Suggested floor: ≥4 weeks in approval, zero off-brand flags, ≥X% reply rate. Define before the first graduation decision.
5. **Action-item ownership when Andrew is on PTO.** Each below-4 rating defaults to Andrew. Fallback assignee when Andrew is unavailable — Darby? Queue without an assignee? Affects task-page UX.
6. **Meeting cancellation handling.** When a month's meeting is skipped (PTO, holiday), does the auto-built brief carry over, get appended to the next month, or get archived? Default to carry-over so nothing slips.

## Cross-references

- Plan section: §R3 of [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md).
- Related docs: [`future-features-plan.md`](./future-features-plan.md) (§H Finance dashboard — primary input), [`churn-causation-engine.md`](./churn-causation-engine.md) (§O — save-call outcomes), [`learning-capture-loop.md`](./learning-capture-loop.md) (R2 — companion ritual; reviews curriculum-graph rot here too), [`visual-regression-cadence.md`](./visual-regression-cadence.md) (R1 — companion ritual).
- Brand: [`docs/brand-guide.md`](../brand-guide.md) §6 (review-page UI), §8 (voice-and-tone reference for off-brand SMS detection).
