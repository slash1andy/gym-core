# M6.10 gym/v1 Endpoint Completeness Audit — 2026-05-04

**Method:** Static code review of all REST controllers in `src/API/` against MILESTONES M6.1 expected endpoint table.

## Executive Summary

**CONDITIONAL PASS — not yet ready for AI agent wiring.** All 20 expected endpoints are implemented and reachable. BaseController envelope (`{ success, data, meta }`), pagination params, auth, and error format are consistent across the board. Two blockers prevent agent wiring; one non-blocker defers OpenAPI doc generation.

**Counts:** 18 PASS · 1 ISSUE (blocker) · 1 MISS (blocker) · 1 NON-BLOCKER

---

## Endpoint Checklist

| Milestone | Method | Route | Status | Notes |
|---|---|---|---|---|
| M1.10 | GET | /locations | ✅ PASS | Public, envelope correct |
| M1.10 | GET | /locations/{slug} | ✅ PASS | |
| M1.10 | GET | /locations/{slug}/products | ✅ PASS | Pagination meta present |
| M1.10 | GET | /user/location | ✅ PASS | Auth enforced |
| M1.10 | PUT | /user/location | ✅ PASS | |
| M2.10 | POST | /sms/send | ✅ PASS | |
| M2.10 | POST | /sms/webhook | ✅ PASS | `__return_true` is intentional — Twilio HMAC is the auth gate; returns TwiML XML (not JSON envelope), by design |
| M2.10 | GET | /sms/conversations/{contact_id} | ⚠️ ISSUE | Pagination args registered, offset paging applied in query, but **no meta returned** — `success_response()` called with no second arg. Fix: add `COUNT` query + pass `pagination_meta()` as second arg. |
| M3.10 | GET | /classes | ✅ PASS | |
| M3.10 | GET | /classes/{id} | ✅ PASS | |
| M3.10 | GET | /schedule | ✅ PASS | |
| M3.11 | GET | /members/me/dashboard | ✅ PASS | All sub-sections resilient via try/catch |
| M4.10 | GET | /members/{id}/rank | ✅ PASS | Self-access + coach cap |
| M4.10 | GET | /members/{id}/rank-history | ✅ PASS | Pagination meta present |
| M4.10 | POST | /ranks/promote | ✅ PASS | `gym_promote_student` cap enforced |
| M4.11 | POST | /check-in | ✅ PASS | 201 success, 409 duplicate |
| M4.11 | GET | /attendance/{user_id} | ✅ PASS | Pagination meta present |
| M4.11 | GET | /attendance/today | ✅ PASS | Returns `{ total: N }` meta (intentionally unpaged) |
| M4.12 | GET | /promotions/eligible | ✅ PASS | Pagination meta present |
| M4.12 | POST | /promotions/promote | ❌ MISS | **Route does not exist.** Implemented route is `POST /promotions/recommend` (sets coach flag only; does not execute belt change). M6.1 tool `flag_promotion_ready` is described as "creates pending promotion flag" — verify: does it want /recommend (flag) or should the route be renamed to /promote? |
| M5.10 | GET | /badges | ✅ PASS | Earned state returned when logged in |
| M5.10 | GET | /members/{id}/badges | ✅ PASS | Custom meta `{ total_badges_earned, total_badges_available }` — acceptable divergence per M5.10 spec |
| M5.10 | GET | /members/{id}/streak | ✅ PASS | |
| M8.1 | GET | /sales/products | ✅ PASS | |
| M8.1 | POST | /sales/calculate | ✅ PASS | |
| M8.1 | GET | /sales/customer | ✅ PASS | |
| M8.1 | POST | /sales/order | ✅ PASS | |
| M8.1 | POST | /sales/lead | ✅ PASS | |

---

## Blockers

### Blocker 1 — POST /promotions/promote missing (spec/code mismatch)

**File:** `src/API/PromotionController.php`

MILESTONES M4.12 specifies `POST /promotions/promote` (execute a belt promotion). The implemented route is `POST /promotions/recommend` (set coach recommendation flag). M6.1 describes the agent tool `flag_promotion_ready` as "creates pending promotion flag" — which is semantically closer to `/recommend`.

**Resolution options:**
- A) If agent tool means flag-only: update MILESTONES M4.12 table to `/recommend`; no code change
- B) If route naming matters for tooling: rename `/recommend` → `/promote` in controller + tests

Verify intent before wiring M6 agent tools.

### Blocker 2 — GET /sms/conversations/{contact_id} missing pagination meta

**File:** `src/API/SMSController.php` — `get_conversation_history()` (approx line 280)

Pagination args are registered (`pagination_route_args()`) and the query applies `$per_page`/`$offset`, but `success_response()` is called with no `$meta` argument. AI tools expecting `total`/`total_pages` will not find them.

**Fix:** Add a `COUNT(*)` query over the same `WHERE user_id = %d` clause, compute `$total_pages = ceil( $total / $per_page )`, and pass `$this->pagination_meta( $total, $total_pages, $page, $per_page )` as the second arg to `success_response()`.

---

## Non-Blocker

### `get_item_schema()` missing on 8 of 9 controllers

Only `LocationController` implements `get_public_item_schema()`. M6.10 acceptance criteria include "OpenAPI/JSON Schema documentation generated for gym/v1 namespace" — that generation requires schema methods on every controller. Does not affect runtime reads; agent tools will work without it. Address in a separate pass after Blockers 1–2 are resolved.

---

## Extra Routes (not in expected table — fine)

- `POST /classes/{id}/program` (`ClassScheduleController`) — assign program; protected with `permissions_assign_program`
- `GET /sms/templates` (`SMSController`) — correctly protected

---

## Recommendation

1. **Resolve Blocker 1** by deciding whether `/promotions/recommend` should be renamed or if the MILESTONES spec should be updated. One-line change either way.
2. **Fix Blocker 2** (1 COUNT query + thread into pagination_meta in `SMSController::get_conversation_history`).
3. After both: gym/v1 read layer is ready for M6 AI agent wiring.
4. Schedule `get_item_schema()` additions as a follow-up cleanup PR.
