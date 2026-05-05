# Weekly Visual Regression Cadence

> Status: Design — not yet built

Ongoing operational ritual. Plugin-scoped spec extracted verbatim from the master playbook §R1, with operational TODOs, file structure, dependencies, and open questions appended.

Master plan: [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md) (§R1).

> **Distribution (load-bearing):** HMA does **not** use Slack. Reports go via **email + git-tracked Markdown archive** under `wp-content/plugins/gym-core/docs/reviews/visual-regression/<date>.md`. No Slack, no third-party chat tools.

---

## Why this matters

The WP block theme is composable; any plugin update can shift typography or color on a page nobody is watching. The brand guide protects in code; the scan protects in production.

## Cadence

Every Monday 06:00 CT.

## Tool

[`scripts/crossfit_scan.py`](../../../../../../payment-tams-ai-brain/.claude/worktrees/quizzical-poincare-7b16d3/scripts/crossfit_scan.py) from the **payment-tams-ai-brain vault** (external to this repo). The scanner wraps Cross-Fit (Playwright + WordPress Playground) and is invoked from the vault, not from gym-core. gym-core owns only the *cadence document, coverage manifest, and archive folder* — not the scanner itself.

## Targets

- Staging site (the public theme).
- Member portal admin pages (logged-in screenshots).

## What runs

1. `crossfit_scan.py haanpaa@<latest-tag> --capture` (baseline) — only on first run after a release.
2. `crossfit_scan.py haanpaa@<latest-deploy>` (compare) — every Monday.
3. Output diff report emailed to Darby + Andrew (HMA does not use Slack; email is the canonical channel). Report is also archived as a Markdown summary under `wp-content/plugins/gym-core/docs/reviews/visual-regression/<date>.md` so it's git-tracked.

## Action on diff

- Visual diff > 1% on a covered route → open issue in `slash1andy/gym-core` with screenshot attachment, assign to Andrew.
- Diff < 1% → no action; report archived.

## Coverage required

Home, about, contact, schedule, locations (×2), program-page (one per program), free-trial, member portal home, coach briefing, finance dashboard. Add new pages to coverage as they ship.

## Implementation notes

The implementation here is operational, not code in gym-core — `crossfit_scan.py` lives in the payment-tams-ai-brain vault. gym-core's responsibilities:

1. **Coverage manifest** — `docs/reviews/visual-regression/coverage.json` lists every route in scope so the scanner has a single source of truth. Update this file whenever a new template ships.
2. **Archive folder** — `docs/reviews/visual-regression/` is git-tracked. Each Monday's report is committed to `main` from a one-shot PR by the agent driving the scan; folder must exist with a `.gitkeep` until the first report lands.
3. **Issue template** — `.github/ISSUE_TEMPLATE/visual-regression-diff.md` standardizes the bug report when diff > 1% (route, before/after screenshots, deploy SHA, assignee = Andrew).
4. **Run-book** — this document. Future agents reference it when wiring the scan into the vault's scheduling layer.

Scheduling lives vault-side (`payment-tams-ai-brain` cron / Action Scheduler). gym-core does not run the scanner.

Operational TODOs (expanded):

1. **Create archive folder** with `.gitkeep` — `wp-content/plugins/gym-core/docs/reviews/visual-regression/.gitkeep`.
2. **Author coverage manifest** at the same location — JSON listing every page-template URL in scope, keyed by route name.
3. **Author the issue template** under `.github/ISSUE_TEMPLATE/visual-regression-diff.md` (repo-root, not plugin-scoped, since `gh` reads templates from the root). Include pre-filled fields for route, deploy SHA, diff percentage.
4. **Vault-side scheduling** (out-of-scope for this PR, tracked here for continuity): the cron entry that runs `crossfit_scan.py haanpaa@<latest-deploy>` Mondays 06:00 CT lives in `payment-tams-ai-brain`. The job emits the email, then opens a PR against `slash1andy/gym-core` to commit the Markdown summary.
5. **Email distribution list** — hard-coded to Darby + Andrew on the vault side. Do not source from a settings file.
6. **Diff-threshold tuning** — start at 1%; review after the first 4 weeks; lower if too many false negatives, raise if too many false positives.
7. **First-baseline checklist** — when a new template ships, the agent that merges the PR is responsible for running `crossfit_scan.py haanpaa@<tag> --capture` against the new route before the next Monday compare.

## File structure (proposed)

```
wp-content/plugins/gym-core/docs/reviews/visual-regression/
├── .gitkeep                  # placeholder until first report
├── coverage.json             # routes in scope (single source of truth)
└── <YYYY-MM-DD>.md           # one Markdown summary per Monday run
```

```
.github/ISSUE_TEMPLATE/
└── visual-regression-diff.md  # standardized issue when diff > 1%
```

External (not in this repo, listed for context):

```
payment-tams-ai-brain/
└── scripts/crossfit_scan.py   # the actual scanner
```

## Dependencies

| Dependency | Source | Notes |
|---|---|---|
| `crossfit_scan.py` | payment-tams-ai-brain vault | External; gym-core does not own it |
| Cross-Fit + Playwright + WordPress Playground | scanner runtime | Set up via `crossfit_scan.py --setup` in the vault |
| Brand guide | [`docs/brand-guide.md`](../brand-guide.md) | What the scan defends against |
| Pressable staging | [`docs/planning/dns-cutover-plan.md`](./dns-cutover-plan.md) | Scan target |
| Member-portal logged-in capture | gym-core `MemberDashboard` (shipped) | Requires session cookie injection (handled by Cross-Fit) |
| GitHub issues | `slash1andy/gym-core` | Diff > 1% creates an issue |

## Acceptance criteria

- Weekly Monday 06:00 CT run completes with a Markdown summary committed to the archive.
- Diff > 1% reliably opens a labeled issue assigned to Andrew within the same business day.
- Coverage manifest stays current — no live route is silently uncovered.
- Distribution stays HMA-internal: email recipients = Darby + Andrew. No Slack, no chat platforms.

## Open questions

1. **Tag-vs-deploy comparison strategy.** Compare each Monday against the most recent tag (last release) or against the prior Monday's snapshot? First catches release-time regressions; second catches drift from background plugin updates. Plan implies the second; confirm.
2. **Logged-in capture credentials.** How does the scanner authenticate to capture the member portal — long-lived session cookie, application-password, or a dedicated scanner user? Affects the security review.
3. **Diff visualization in the email.** Inline screenshots, links to the artifact archive, or both? Email size budget matters if we attach.
4. **First-baseline orchestration when a new template ships.** Manually triggered by the merging agent, or automatic post-merge hook? Manual is simpler; automatic prevents misses.
5. **Failure-mode reporting.** If the scanner crashes or staging is down, does the email still go (with a "scan failed" notice), or stay silent? Stay-silent risks the ritual rotting unnoticed; default to send-with-failure-notice.

## Cross-references

- Plan section: §R1 of [`/Users/andrewwikel/.claude/plans/let-s-deep-dive-into-linked-galaxy.md`](../../../../../../../../.claude/plans/let-s-deep-dive-into-linked-galaxy.md).
- External tool: `scripts/crossfit_scan.py` in [payment-tams-ai-brain vault](../../../../../../payment-tams-ai-brain/) (not in this repo).
- Related docs: [`learning-capture-loop.md`](./learning-capture-loop.md) (R2 — companion ritual), [`joy-darby-monthly-checkin.md`](./joy-darby-monthly-checkin.md) (R3 — companion ritual), [`dns-cutover-plan.md`](./dns-cutover-plan.md) (defines staging target).
- Brand: [`docs/brand-guide.md`](../brand-guide.md) (the source of truth this scan defends).
