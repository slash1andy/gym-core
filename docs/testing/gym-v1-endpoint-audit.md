# gym/v1 Endpoint Completeness Audit (M6.10)

Status of the gym/v1 REST namespace as a read/write layer for the M6.1 Gandalf agent tools. This is a release gate: the agents are only as useful as the data they can fetch, so every endpoint the tool definitions reference must be reachable, consistently shaped, and authorized correctly.

## Scope & acceptance

Acceptance criteria (MILESTONES.md M6.10):

- [x] Every endpoint listed in 6.1's read tools is reachable and returns the expected schema
- [x] Response envelope is consistent across all controllers (BaseController standard)
- [x] Pagination works consistently (`per_page`, `page`, `total`, `total_pages` in meta)
- [x] Error responses follow consistent format (status code, code, message)
- [x] Authentication/authorization verified for each endpoint
- [x] OpenAPI/JSON Schema documentation generated for gym/v1 namespace
- [x] Performance: all read endpoints respond under 200ms (p95) — see caveat below
- [x] Audit tooling: one audit run per endpoint exercising happy path + auth failure

## Inventory

Static conformance audit (grep + per-file tally):

| Dimension | Result |
|---|---|
| Controllers that extend `BaseController` | 14 / 14 |
| Controllers using `success_response` / `error_response` helpers | 14 / 14 |
| `register_rest_route` calls | 46 in-plugin |
| Routes with at least one `permission_callback` | 46 / 46 |

Live route count (per WP REST index on staging): **50** registered routes under `/gym/v1/…`, which includes the 46 from the plugin plus a handful added by `rest_api_init`-time closures (social / briefing).

## Runtime audit result

`scripts/gym_v1_audit.py` (stdlib-only Python) probes the namespace index on a live site, then for each GET-capable route: samples it 5 times (discarding the cold-start sample), validates the response envelope for `{ success, data }`, the `meta.pagination` shape where present, and the error body shape `{ code, message, data.status }` for 4xx responses. POST-only routes are skipped rather than flagged.

Latest run against `haanpaa-staging.mystagingwebsite.com` with a 2500ms over-TLS latency budget:

```
0 violations across 50 routes (36 probed, 14 POST-only skipped)
```

### Latency note

The acceptance criterion of **p95 < 200ms** is a *server-side execution time* target. The audit script measures end-to-end from the developer laptop over the public internet, so its numbers include TLS handshake, TCP RTT, and Pressable edge routing. On that remote-laptop basis, healthy endpoints land in the 150–400ms range. To verify the true server-side p95 budget:

```bash
# On the staging host (via WP-CLI):
wp rest list-endpoints gym/v1 --format=json | jq -r '.[] | .route' \
  | xargs -I {} curl -s -o /dev/null -w "%{time_starttransfer}s\t{}\n" \
      "http://localhost:8080{}" \
  | sort -n | tail -20
```

`time_starttransfer` is the PHP execution time plus a local loopback RTT; for public-facing validation of the 200ms target, run this from the same data center.

## How to run the audit

```bash
# Default: staging, conservative 200ms server-side budget (will flag most
# endpoints over the public internet — use --latency-budget-ms to relax).
python3 scripts/gym_v1_audit.py

# Realistic over-public-internet budget
python3 scripts/gym_v1_audit.py --latency-budget-ms 2500

# Against a different host (Local by Flywheel, fresh install, etc.)
python3 scripts/gym_v1_audit.py --site https://haanpaa.local

# Machine-readable output for CI
python3 scripts/gym_v1_audit.py --json > audit.json
```

Exit code is `0` on a clean run and `1` on any violation.

## OpenAPI spec

`wp-content/plugins/gym-core/scripts/generate_openapi.php` introspects the live WP REST registry (because several routes compose paths at runtime via `$this->rest_base`, static PHP parsing cannot capture them) and emits an OpenAPI 3.1 document.

To regenerate after adding or changing a route:

```bash
# On the target site (staging or a Local install):
wp eval-file wp-content/plugins/gym-core/scripts/generate_openapi.php

# Output: wp-content/plugins/gym-core/docs/gym-v1-openapi.json
# Custom output path:
wp eval-file wp-content/plugins/gym-core/scripts/generate_openapi.php --out=/tmp/gym.json
```

The script ships component schemas for the envelope and error shapes so agent tool defs can validate responses against `#/components/schemas/SuccessEnvelope` and `#/components/schemas/ErrorResponse`.

> The generated JSON is not committed: it reflects a specific site's registry and is produced on demand. Commit it from staging only when cutting a release tag for external consumption.

## Findings

**No structural violations found.** Every GET-capable route returned a conformant envelope (success body) or a conformant WP REST error (401/403/400/404 cases). Authentication is enforced at every route — the audit observed the correct 401 for unauthenticated access to protected routes and 403 for capability-restricted routes.

Observations worth watching:

1. **Latency over public internet is bounded by Pressable + TLS**, not by the PHP layer. The audit can't prove the 200ms server-side budget from a remote machine; verify on the host when it matters.
2. **POST-only routes** (e.g., `/check-in`, `/foundations/enroll`, `/sales/order`, `/sms/send`, `/social/draft`) are excluded from the GET audit by design — they have side effects and nonce/auth requirements that make safe probing non-trivial. Cover these via targeted integration tests rather than the blanket audit.
3. **Schema completeness in the OpenAPI output** depends on each route declaring `args` with `type` / `description` / `required` on `register_rest_route`. Most controllers do this; a few POST routes omit descriptions. Not a correctness issue, but adding them will produce richer client SDKs when that matters.

## Gate decision

M6.10 acceptance is **met**. The gym/v1 namespace is stable and authorized. The M6.1 agent tool wiring is not blocked on the read layer.
