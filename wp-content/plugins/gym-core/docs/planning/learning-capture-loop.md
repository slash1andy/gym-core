# Learning Capture Loop — Coach Debriefs → Curriculum Graph

> Status: Design — not yet built

Ongoing operational ritual. Plugin-scoped spec extracted verbatim from the master playbook §R2, with implementation TODOs, file structure, dependencies, and open questions appended.

Master plan: [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md) (§R2).

---

## Why this matters

The curriculum graph must be a *living* model; if it can only contain pre-seeded terms it will rot the moment a coach drills something Darby hadn't formalized. The approval gate keeps the taxonomy clean without demanding Darby anticipate every variation.

## Cadence

Continuous — every post-class debrief (per §M).

## Loop

1. Coach completes 60-second debrief via Gandalf.
2. `record_class_techniques` Ability writes to `gym_technique_attempts`.
3. Nightly job aggregates "novel techniques mentioned but not in `gym_technique` taxonomy" → drafts new term suggestions for Darby's review.
4. Darby approves/edits/rejects in a one-screen admin view (next-day, ~2 min).
5. Approved terms feed back into the curriculum graph and become available for future debriefs.

## Implementation notes

Operational anchor: this loop is *part of* the Technique Mastery Graph (§M) feature; it is not a separate code module. The work below extends §M with a "novel-term review" surface.

Implementation TODOs (expanded):

1. **Schema addition** to §M's data model: `{$wpdb->prefix}gym_technique_suggestion` table — `(suggestion_id, raw_text, normalized_slug, first_mentioned_in_class_id, first_mentioned_by_coach_id, first_mentioned_at, mention_count, status, reviewed_by, reviewed_at, decision_notes, mapped_technique_id)`. Status flow: `pending` → `approved` (creates new `gym_technique` post) | `merged` (mapped to existing technique) | `rejected`.
2. **Capture-side hook** (`src/TechniqueGraph/Capture/VoiceDebriefHandler.php` per §M): when the AI-parsed transcript proposes a technique that does not match an existing `gym_technique` post or known synonym, the handler:
   - Inserts (or increments `mention_count` on) a row in `gym_technique_suggestion`.
   - Persists the attempt with `technique_id = NULL` and a `pending_suggestion_id` reference, so attribution is preserved even before Darby reviews.
3. **Nightly aggregation job** (`src/TechniqueGraph/Jobs/SuggestionAggregatorJob.php`):
   - Action Scheduler, daily 02:00 CT.
   - Normalizes raw text via slug + lightweight similarity check (Levenshtein) to merge near-duplicates ("scissor sweep" / "scissor-sweep" / "scissor swp").
   - Optionally calls `wp_ai_client_prompt()` to propose the most likely existing technique match for Darby to confirm-merge.
4. **Darby's review surface** (`src/TechniqueGraph/Admin/SuggestionReviewPage.php`):
   - One-screen admin page under `Gym → Curriculum Suggestions`.
   - Each row: raw text, mention count, last-mentioned coach + class, AI-suggested match (if any), three buttons: **Approve as new** (creates `gym_technique`), **Merge into existing** (autocomplete picker), **Reject** (with reason).
   - Brand-aligned per `docs/brand-guide.md` §6.
   - Designed for a 2-minute daily review session.
5. **Backfill of pending attempts** when a suggestion is approved/merged:
   - All `gym_technique_attempts` rows pointing at that `pending_suggestion_id` get rewritten to the new/mapped `technique_id` in a single batched UPDATE.
   - Audit log entry recorded.
6. **Coach feedback loop:** when Darby approves or merges, the originating coach receives an in-app notification ("Your debrief on '%s' was added to the curriculum") so coaches see their input shape the system.
7. **Tests:** unit (similarity normalization, approval / merge / reject state transitions, batched backfill); integration (debrief mentioning a novel term creates a suggestion, Darby approval rewrites pending attempts).
8. **CI gate:** `composer test-all`, PHPStan level 6, ESLint clean.
9. **Telemetry:** small admin widget — count of pending suggestions; alert at >50 to flag review backlog.
10. **Monthly review** at the Joy/Darby check-in (R3): how many suggestions approved vs. rejected; any that shouldn't have been approved (graph rot signal).

## File structure (proposed)

This ritual extends §M's module rather than introducing a new top-level one:

```
wp-content/plugins/gym-core/src/TechniqueGraph/
├── Capture/
│   └── VoiceDebriefHandler.php        # extended — emits suggestions for novel terms
├── Jobs/
│   └── SuggestionAggregatorJob.php    # NEW — nightly
├── Admin/
│   └── SuggestionReviewPage.php       # NEW — Darby's daily 2-minute view
└── Repositories/
    └── SuggestionRepository.php        # NEW — CRUD over gym_technique_suggestion
```

## Dependencies

| Dependency | Source | Notes |
|---|---|---|
| Technique Mastery Graph | §M / [`technique-mastery-graph.md`](./technique-mastery-graph.md) | Foundation; this loop extends §M's module |
| Coach Briefing | Phase 2 §G / [`coach-briefing-system.md`](./coach-briefing-system.md) | Hosts the post-class debrief surface |
| `record_class_techniques` Ability | hma-ai-chat | Source of capture events |
| WP 7.0 AI Client | Already live | `wp_ai_client_prompt()` for synonym suggestions |
| Action Scheduler | Already live | Nightly aggregation job |
| Brand guide | [`docs/brand-guide.md`](../brand-guide.md) §6 | Review-page UI |

## Acceptance criteria

- Suggestion review takes Darby <2 minutes/day on average over a 30-day window.
- New techniques mentioned in debriefs land on the review queue within 24 hours.
- Approved suggestions feed back into the curriculum graph and become recognizable in the next debrief without manual reseed.
- Pending-attempt backfill rewrites all referencing rows atomically with no orphans.
- Monthly review (R3) shows approved vs. rejected ratio is healthy (Darby's qualitative read).

## Open questions

1. **Similarity threshold.** What edit-distance / token-ratio threshold counts as a duplicate of an existing pending suggestion? Too tight = duplicates clutter the queue; too loose = distinct techniques get merged.
2. **AI-suggested match confidence display.** When `wp_ai_client_prompt()` returns a likely existing-technique match, what confidence level surfaces it as a default action versus an advisory? Affects review speed.
3. **Bulk approve / reject.** Allow Darby to bulk-approve trivially obvious additions and bulk-reject obvious noise (e.g., transcription artifacts), or force per-row review? Bulk is faster but loses the per-row decision-notes audit trail.
4. **Synonym handling post-approval.** When a suggestion is merged into an existing technique, do we store the raw text as a synonym so future transcripts auto-resolve, or rely on Darby to add it to the technique's alias list manually?
5. **Coach feedback channel.** In-app notification, email, or a "your debriefs this month" weekly summary? Plan implies in-app; confirm Darby's preference.
6. **Backlog threshold for alerts.** Plan-implementation note suggests an alert at >50 pending. Is 50 the right number, or program-specific (BJJ has more techniques than kickboxing)?

## Cross-references

- Plan section: §R2 of [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md).
- Foundation feature: [`technique-mastery-graph.md`](./technique-mastery-graph.md) (§M — this ritual extends that module).
- Related docs: [`coach-briefing-system.md`](./coach-briefing-system.md) (§G — hosts the debrief surface), [`joy-darby-monthly-checkin.md`](./joy-darby-monthly-checkin.md) (R3 — monthly graph-rot review), [`visual-regression-cadence.md`](./visual-regression-cadence.md) (R1 — companion ongoing ritual).
- Brand: [`docs/brand-guide.md`](../brand-guide.md) §6 components.
