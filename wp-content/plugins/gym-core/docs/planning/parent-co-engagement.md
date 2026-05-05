# Parent / Guardian Co-Engagement Surface

> Status: Design — not yet built

Phase 3 moat feature. Plugin-scoped spec extracted verbatim from the master playbook §N, with implementation TODOs, file structure, dependencies, and open questions appended.

Master plan: [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md) (§N).

> **Foundation note (load-bearing):** parent/child linking already exists in the codebase via [`src/Member/ContactRelationships.php`](../../src/Member/ContactRelationships.php). It uses two user-meta keys (`gym_core_parents`, `gym_core_children`), bidirectional add/remove, and an admin UI on the user-edit screen. **This feature must extend that class — do not invent new meta keys.**

---

## Why this matters

Roughly half of HMA's revenue is kids. The entire competitive set treats kids as miniature adults — they get a member account, attendance is logged against them, billing is parent-attached, full stop. Parents have no surface designed for them. Result: parents disengage because they have no visibility into progress or community; sibling pricing logic is implicit, not surfaced; age-up transitions (Kids → Teen → Adult tier) are manual and frequently missed; drop-off prediction is run against the kid's attendance instead of the parent's engagement (which is the real signal). **A parent-first surface is differentiated and not technically hard — most data exists.**

## Data model

- **Reuse the existing [`src/Member/ContactRelationships.php`](../../src/Member/ContactRelationships.php)** which already provides bidirectional parent/child linking via `gym_core_parents` and `gym_core_children` user-meta keys, with admin UI on the user-edit screen. Do **not** invent new meta keys; extend this class.
- Extension work needed on `ContactRelationships`:
  - Add a `gym_core_relation_type` per-link meta (`parent` / `guardian` / `sibling`) so the link itself carries semantics, not just direction.
  - Add a `link_relationship()` programmatic API so other modules (intake, age-up, sibling pricing) can create links without going through the user-edit UI.
  - Add a query helper `get_household($user_id)` that returns all linked users (parents + their other children) in one call.
- Extend Jetpack CRM contacts with a `linked_member_ids` array mirrored from `ContactRelationships` so CRM queries can fan out across a household.
- New CPT `gym_age_up_event` to track upcoming pricing transitions (kid_dob + 13 = teen tier; +18 = adult tier; configurable per program).

## Capture surfaces

- **Auto-trigger on Kids BJJ purchase:** when an order containing a Kids program subscription completes, the order-completion handler fires a child-setup wizard (modal in the thank-you page + email) that asks the purchaser for the child's name, date of birth, and any medical notes. The child is created as a WP user, linked via `ContactRelationships::link_relationship($parent_id, $child_id, 'parent')`, and the subscription is reassigned to the child user with the parent retained as billing contact. This is the dominant capture path — it's the moment we already have the parent's attention.
- Intake flow (sales kiosk + free-trial signup): if "this trial is for a child" is checked, run the same wizard inline.
- Manual / Amanda-driven path: existing `ContactRelationships` admin UI on the user-edit screen still works for after-the-fact corrections.
- Member portal: a parent dashboard showing all linked kids in one view (existing portal currently shows one user at a time; add a switcher driven by `get_household()`).

## Member-facing surfaces

- **Parent dashboard:** weekly progress digest per kid (attendance, last technique drilled, upcoming belt promotion eligibility, upcoming events). Dark / brand-aligned card layout per brand-guide §6.
- **Sibling pricing surface:** when a parent has 2+ active kid memberships, the dashboard surfaces the active sibling discount (configurable per location); when a 2nd kid signs up, the discount auto-applies via WC Subscriptions price override.
- **Age-up countdown card:** "Marcus turns 13 in 47 days — he'll move from Kids BJJ ($X/mo) to Teen BJJ ($Y/mo) on his birthday. Tap to confirm." Auto-creates the Subscription switch on confirmation.
- **Weekly SMS digest** to parent: 1 sentence per kid + 1 photo if available + a link to the parent dashboard. AI-composed (Phase 2 §I infra) so it's not a template blast.

## Drop-off model adjustment

Existing churn prediction (Phase 3 §O) uses kid attendance as the primary signal. Adjust to weight parent-engagement signals (parent dashboard logins, parent reply to weekly digest, parent showing up at events) at least as heavily — for kids, parent disengagement predicts kid drop-off better than kid attendance does (their attendance is forced by the parent until it isn't).

## Implementation notes

Per-plan:

- Module `src/Parent/` in gym-core with `RelationGraph/`, `Dashboard/`, `AgeUp/`, `SiblingPricing/`.
- New REST endpoints `gym/v1/parents/{id}/children` and `gym/v1/age-up-events`.
- Reuses the AI-SMS infrastructure from Phase 2 §I.
- Reuses brand-guide card components for visual parity with the member dashboard.

Implementation TODOs (expanded):

1. **Extend `ContactRelationships`** (do not duplicate):
   - Add a `gym_core_relation_type` storage shape (parallel meta keyed by linked-pair, or widen the existing arrays into tuples). Preserve backward compatibility — `get_parents()` / `get_children()` must still return plain int arrays.
   - Add `public function link_relationship(int $user_id, int $related_id, string $type): void` that wraps `add_relationship()` and writes the relation-type meta in one call.
   - Add `public function get_household(int $user_id): array` returning `[ 'parents' => [...], 'children' => [...], 'siblings' => [...] ]` in one query (use `get_users(['include' => $ids])` to avoid N+1).
   - Adjust the admin UI to show the relation type and round-trip the new field on save.
   - Add unit tests for the new API surfaces.
2. **Order-completion wizard:**
   - Hook `woocommerce_order_status_completed` to detect Kids program subscription orders (match by product category or per-product flag).
   - Modal on the thank-you template + plain-text + HTML email fallback (parent may complete email-side).
   - Endpoint that creates the child WP user, calls `ContactRelationships::link_relationship()`, reassigns the subscription via `WC_Subscription::set_customer_id()`, retains parent as billing contact.
   - Capture child DOB and medical notes (`_gym_medical_notes`) at the same moment.
3. **Intake-flow integration:** sales kiosk and `/free-trial/` form gain a "This trial is for my child" toggle that runs the same wizard inline.
4. **`gym_age_up_event` CPT:** registered with status flow (`scheduled` → `confirmed` → `executed` | `dismissed`). Daily Action Scheduler job scans linked children's DOBs and creates `scheduled` events 60 days before the threshold birthday.
5. **Sibling discount engine** (`src/Parent/SiblingPricing/SiblingDiscountEngine.php`): listens for new Kids subscription creation, checks `get_household()`, applies the location-configured discount via WC Subscriptions price override; rolls back on cancellation.
6. **Parent dashboard** (`src/Parent/Dashboard/`):
   - Page registered under `/my-account/family/`.
   - Component: kid switcher, per-kid card (attendance streak, last technique drilled — pulls from §M `gym_technique_attempts`), upcoming promotion eligibility (existing engine), age-up countdown.
   - Brand-aligned cards per `docs/brand-guide.md` §6.
7. **Weekly SMS digest** (`src/Parent/Dashboard/WeeklyDigestJob.php`):
   - Action Scheduler job, Sundays 17:00 CT.
   - For each parent in `get_household()`-defined households, calls the AI-SMS Ability with a structured kid-context payload.
   - First 30 days routed through Gandalf approval queue (per Phase 2 §I safety rule).
8. **Jetpack CRM mirror:** on every `link_relationship` / `remove_relationship` invocation, write a `linked_member_ids` array to the matching CRM contact record.
9. **Drop-off model wiring:** expose a `Parent\EngagementSignals::for_household($user_id)` API the churn engine (§O) consumes — dashboard logins (last 30/90 days), digest replies, event attendance.
10. **REST endpoints:**
    - `GET /gym/v1/parents/{id}/children` (parent reads own household; coach/admin can read any).
    - `GET/POST /gym/v1/age-up-events` (list + confirm).
11. **Tests:** unit (relationship-type round-trip, household queries, sibling-discount math, age-up date logic); integration (Kids order completion → wizard → user creation → subscription reassignment); REST (ownership/cap).
12. **CI gate:** `composer test-all`, PHPStan level 6, ESLint clean.

## File structure (proposed)

```
wp-content/plugins/gym-core/src/
├── Member/
│   └── ContactRelationships.php   # EXTENDED — relation_type, link_relationship(), get_household()
└── Parent/
    ├── RelationGraph/
    │   ├── HouseholdRepository.php   # high-level household queries; wraps ContactRelationships
    │   └── CrmMirror.php             # Jetpack CRM linked_member_ids sync
    ├── Dashboard/
    │   ├── ParentDashboardController.php
    │   ├── ParentDashboardRenderer.php
    │   └── WeeklyDigestJob.php
    ├── AgeUp/
    │   ├── AgeUpEventCpt.php         # CPT registration
    │   ├── AgeUpScanner.php          # daily DOB scan job
    │   └── AgeUpExecutor.php         # confirm → subscription switch
    ├── SiblingPricing/
    │   └── SiblingDiscountEngine.php
    ├── Intake/
    │   └── ChildSetupWizard.php      # post-purchase + intake wizard
    ├── EngagementSignals.php         # consumed by §O churn engine
    └── API/
        ├── ParentsController.php     # gym/v1/parents/{id}/children
        └── AgeUpEventsController.php # gym/v1/age-up-events
```

## Dependencies

| Dependency | Source | Notes |
|---|---|---|
| `ContactRelationships` | [`src/Member/ContactRelationships.php`](../../src/Member/ContactRelationships.php) | Foundation; extend in place |
| Technique Mastery Graph | §M / [`technique-mastery-graph.md`](./technique-mastery-graph.md) | Provides "last technique drilled" field for digest |
| AI-composed SMS | Phase 2 §I / [`future-features-plan.md`](./future-features-plan.md) | Powers weekly digest |
| Jetpack CRM | Already integrated | Holds `linked_member_ids` mirror |
| WC Subscriptions | Already integrated | Sibling discount + age-up subscription switch |
| `_gym_medical_notes` user meta | Phase 1 §F sequel work | Captured in child-setup wizard |
| Brand guide | [`docs/brand-guide.md`](../brand-guide.md) §6 | Card components |

## Acceptance criteria

- 80%+ of kid memberships have a linked parent contact within 60 days.
- Parent dashboard weekly active rate ≥40% over the first 90 days.
- Age-up transitions execute on time without manual intervention from Joy.

## Open questions

1. **Relation-type storage shape.** Add a parallel meta key (`gym_core_relation_type`) or migrate the existing `gym_core_parents` / `gym_core_children` arrays to tuple form? Backward compatibility for existing reads is mandatory — pick the path that requires the smallest change to the public `get_parents()` / `get_children()` signatures.
2. **Sibling-discount stacking.** Does the sibling discount stack with location-based pricing, with promo coupons, or with both? Decision affects WC Subscriptions price-override layering.
3. **Two-parent / shared-custody households.** When a kid has two linked parents who both opt into the digest, do both receive the SMS, or is there a "primary recipient" flag? Affects opt-out/preference modeling.
4. **Age-up auto-execute vs. confirm-required.** Plan says "auto-creates the Subscription switch on confirmation" — confirmation always required, or auto-execute after N days of no response with a final reminder? Joy's preference drives this; ask before coding.
5. **Photo attachment in weekly digest.** SMS = MMS; per-kid photo attachment requires a curated source. Pull from a parent-facing class-photo collection, latest belt-promotion photo, or skip photos in v1?
6. **Privacy of cross-kid view.** When parent A is linked to kid X (a sibling of kid Y, where kid Y has a different parent B), what does parent A see in their household view? Plan implies one-hop only; verify with Darby.

## Cross-references

- Plan section: §N of [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md).
- Foundation code: [`src/Member/ContactRelationships.php`](../../src/Member/ContactRelationships.php) (extended in place, not replaced).
- Related docs: [`technique-mastery-graph.md`](./technique-mastery-graph.md) (§M — supplies "last technique drilled"), [`churn-causation-engine.md`](./churn-causation-engine.md) (§O — consumes parent-engagement signals), [`future-features-plan.md`](./future-features-plan.md) (Phase 2 §I AI-SMS infra).
- Brand: [`docs/brand-guide.md`](../brand-guide.md) §6 components.
