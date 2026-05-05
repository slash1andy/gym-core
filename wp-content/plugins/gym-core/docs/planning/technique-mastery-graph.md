# Technique / Curriculum Mastery Graph

> Status: Design — not yet built

Phase 3 moat feature. Plugin-scoped spec extracted verbatim from the master playbook §M, with implementation TODOs, file structure, dependencies, and open questions appended for a future implementing agent.

Master plan: [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md) (§M).

---

## Why this matters

Every other gym software platform — Mindbody, Mariana Tek, ABC Glofox, Zen Planner, Trainerize, Pressly, Kicksite, Spark — uses *attendance* as a proxy for skill progression. Belts get awarded on time-in-class, not technique mastery. That works for fitness. For BJJ and kickboxing it's a glaring miss: a student can rack up attendance and still be weak on shrimping or guard retention. Coaches know this in their head; the system has no record. Building a per-technique mastery graph turns "head knowledge" into a structured asset that powers belt-test prep, class recommendations, drill suggestions, churn causation (§O), and (eventually) parent reporting (§N). **No competitor has this.**

## Data model

- New CPT `gym_technique` with hierarchy: program (BJJ / Kickboxing — the two HMA programs) → category (e.g., guard, takedowns, escapes; striking, clinch, defense) → technique (e.g., scissor sweep, hook kick). Stored as a `gym_technique` taxonomy alongside the post, with terms representing categories and the post itself representing the technique.
- New table `{$wpdb->prefix}gym_technique_attempts`:
  - `attempt_id` PK
  - `student_id` (WP user)
  - `technique_id` (post ID)
  - `class_id` (`gym_class` CPT ID)
  - `coach_id` (WP user)
  - `attempt_date`
  - `mastery_signal` (`introduced` | `drilled` | `executed_live` | `taught_to_others`)
  - `notes` (text)
  - `confidence` (1–5 coach-assessed)
  - Indexed on `student_id`, `technique_id`, `attempt_date`.
- New table `{$wpdb->prefix}gym_curriculum_plan`:
  - `plan_id` PK
  - `class_id` FK
  - `technique_id` FK
  - `position`
  - `notes`
  - This is the curriculum-of-the-day data the Coach Briefing (Phase 2 §G) currently lacks.

## Capture surfaces (the flywheel)

- **Post-class voice debrief via Gandalf** (highest-leverage). 60-second prompt after each class: "Coach, what techniques did you cover in tonight's 6pm Adult BJJ?" → Gandalf transcribes (Whisper or equivalent), parses into one or more `gym_technique_attempts` rows, asks for confirmation, writes. New Ability `record_class_techniques` in hma-ai-chat.
- **Pre-class plan via the Coach Briefing.** Coach can pre-set the curriculum (`gym_curriculum_plan`) which the briefing displays, then post-class confirms what actually happened. Discrepancies are normal data, not a bug.
- **Belt-test scoring.** When a student tests for promotion, the testing coach records per-technique pass/fail directly into `gym_technique_attempts` with `mastery_signal = executed_live`.
- **Self-report (light touch, optional).** Member portal lets students log "I drilled X for Y minutes today" but with low confidence weight. Coach data dominates.

## Derived outputs

- **Per-student mastery view** in the member portal: a heat-map of techniques the student has been exposed to / drilled / executed live, grouped by category and weighted by recency.
- **Belt-test readiness score** per student: % of belt-required techniques with at least one `executed_live` signal in the last 12 months.
- **Class recommendation** in the Coaching agent: "Sam should attend Tuesday Adult BJJ — open guard work, last drilled 47 days ago."
- **Coach planning aid:** "Class roster has 8 students who haven't drilled scissor sweep in 60+ days; suggest covering it."

## Implementation notes

Per-plan:

- Module `src/TechniqueGraph/` in gym-core with subfolders `Models/`, `Capture/`, `Reporting/`, `API/`.
- New REST endpoints under `gym/v1/techniques/` and `gym/v1/students/{id}/mastery`.
- The voice-debrief Whisper call is the only external dependency; gate it behind a feature flag so we can test text-first if Whisper integration slips.
- Belt-required technique list is a per-program JSON file under `data/curriculum/{program}.json` (`bjj.json`, `kickboxing.json`) shipped with the plugin; Darby owns the seed content.

Implementation TODOs (expanded):

1. **Schema migration** (gym-core activation hook):
   - Register CPT `gym_technique` with hierarchical taxonomy `gym_technique_category` scoped per program.
   - Create `{$wpdb->prefix}gym_technique_attempts` with the columns and indexes above (use `dbDelta`).
   - Create `{$wpdb->prefix}gym_curriculum_plan` per spec.
   - Bump plugin DB version constant; gate migration behind a version check.
2. **Seed curriculum data:** create `data/curriculum/bjj.json` and `data/curriculum/kickboxing.json` placeholders (Darby fills content). Add a CLI command `wp gym techniques seed` that imports JSON into CPT + taxonomy.
3. **Module scaffold:** create `src/TechniqueGraph/` per the file tree below, registered through gym-core bootstrap with constructor DI per `CLAUDE.md`.
4. **CRUD models** (`Models/Technique.php`, `Models/TechniqueAttempt.php`, `Models/CurriculumPlan.php`):
   - `declare(strict_types=1)`, prepared statements, capability checks.
   - HPOS-friendly (this module touches users + custom tables only — no order meta).
5. **Capture handlers:**
   - `VoiceDebriefHandler` — accepts a transcript, calls `wp_ai_client_prompt()` with structured-JSON schema returning attempt rows; persists after coach confirmation. Behind feature flag `GYM_TECHNIQUE_VOICE_ENABLED`.
   - `BeltTestScorer` — admin form posting per-technique pass/fail; writes attempts with `mastery_signal = executed_live`.
   - `SelfReportHandler` — member-portal endpoint capped at `confidence = 2`.
6. **`record_class_techniques` Ability** in hma-ai-chat — Gandalf-callable wrapper around `VoiceDebriefHandler`.
7. **Reporting layer:**
   - `MasteryHeatmap` aggregator (per student, per category, recency-weighted).
   - `ReadinessScore` (per student, per belt) reading from `data/curriculum/*.json`.
   - `ClassRecommender` (suggested classes given mastery gaps).
8. **REST API:**
   - `GET/POST /gym/v1/techniques` (list, create — admin/coach cap).
   - `GET /gym/v1/students/{id}/mastery` (member can read own, coach/admin can read any).
   - Nonce middleware on state-changing routes (per Phase 1 §A.5).
9. **Coach Briefing integration:** curriculum-of-the-day card pulling from `gym_curriculum_plan`.
10. **Member portal heatmap UI:** brand-aligned per `docs/brand-guide.md` §6; vanilla JS only.
11. **Tests:** unit (CRUD, readiness math, recency weighting); integration (voice-debrief end-to-end with mocked AI client; belt-test scoring); REST (ownership/cap).
12. **CI gate:** `composer test-all`, PHPStan level 6, ESLint clean.
13. **Telemetry:** small admin widget counting `gym_technique_attempts` inserts/day so we can verify progress toward the 1,000-row / 60-day target.

## File structure (proposed)

```
wp-content/plugins/gym-core/
├── data/
│   └── curriculum/
│       ├── bjj.json
│       └── kickboxing.json
└── src/
    └── TechniqueGraph/
        ├── Models/
        │   ├── Technique.php          # CPT + taxonomy registration
        │   ├── TechniqueAttempt.php   # CRUD over gym_technique_attempts
        │   └── CurriculumPlan.php     # CRUD over gym_curriculum_plan
        ├── Capture/
        │   ├── VoiceDebriefHandler.php
        │   ├── BeltTestScorer.php
        │   └── SelfReportHandler.php
        ├── Reporting/
        │   ├── MasteryHeatmap.php
        │   ├── ReadinessScore.php
        │   └── ClassRecommender.php
        └── API/
            ├── TechniquesController.php   # gym/v1/techniques/*
            └── MasteryController.php      # gym/v1/students/{id}/mastery
```

In `wp-content/plugins/hma-ai-chat/`:

```
src/
└── Abilities/
    └── RecordClassTechniques.php   # registers `record_class_techniques`
```

## Dependencies

| Dependency | Source | Notes |
|---|---|---|
| Coach Briefing System | Phase 2 §G — [`coach-briefing-system.md`](./coach-briefing-system.md) | Ships first; the post-class debrief surface is added to it |
| Belt-by-belt curriculum codified by Darby | Phase 2 staff task | Seed content for `data/curriculum/{bjj,kickboxing}.json` |
| WP 7.0 AI Client | Already live | `wp_ai_client_prompt()` for parsing voice debriefs |
| `hma-ai-chat` Abilities registry | Already live | Hosts `record_class_techniques` |
| Whisper (or equivalent) STT | External | Feature-flagged; text-first fallback path required |
| `gym_class` CPT + attendance | gym-core M1 (shipped) | FK target for `attempt_date` / `class_id` |

## Acceptance criteria

- 1,000+ `gym_technique_attempts` rows captured in the first 60 days post-launch (sanity check that the flywheel is spinning).
- A coach can answer "what did Sam drill last month?" from the dashboard in <10 seconds.
- Belt-test readiness score correlates with actual promotion outcomes (validate over 6 months).

## Open questions

1. **STT provider.** Is Whisper called directly (OpenAI / self-hosted), or routed through the WP 7.0 AI Client abstraction the same way text generation is? Affects feature-flag scope and cost-per-debrief modeling.
2. **Recency weighting curve.** Is the heat-map weight an exponential decay (half-life N days) or a stepped bucket (this-week / this-month / older)? Darby's intuition should drive this; needs a 1:1 before coding.
3. **Belt-required technique granularity.** Does `data/curriculum/bjj.json` enumerate every technique by belt, or list category-level "must demo X of Y in category Z" requirements? The readiness-score math differs.
4. **Self-report confidence cap.** Is `confidence = 2` the right ceiling for member self-reports, or should self-report rows live in a separate `mastery_signal` (e.g., `self_drilled`) so they never blend with coach observations?
5. **Multi-coach class confirmation.** When two coaches teach a class together, who owns the post-class debrief — first to respond, primary coach on the schedule, or a merged record? Ability needs a deterministic answer.

## Cross-references

- Plan section: §M of [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md).
- Related code: gym-core M1 attendance + `gym_class` CPT (already shipped).
- Related docs: [`coach-briefing-system.md`](./coach-briefing-system.md) (§G — depended-upon surface), [`future-features-plan.md`](./future-features-plan.md) (WP 7.0 AI Client roadmap).
- Downstream consumers: [`churn-causation-engine.md`](./churn-causation-engine.md) (uses `gym_technique_attempts.confidence` as a risk signal), [`parent-co-engagement.md`](./parent-co-engagement.md) (parent digest references "last technique drilled").
- Brand: [`docs/brand-guide.md`](../brand-guide.md) §6 components for member-portal heatmap UI.
