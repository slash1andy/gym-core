# Haanpaa Martial Arts — Claude Context

> Persistent context for AI agents working in this monorepo.

## What This Is

The **Haanpaa Martial Arts monorepo** — everything needed to migrate Haanpaa Martial Arts (2 locations: Rockford, IL and Beloit, WI) from a fragmented stack (Spark Membership, GoHighLevel, Wix, USAePay) onto a consolidated WordPress 7.0 / WooCommerce 10.3 ecosystem.

This is a **private, internal project** — not marketplace software.

## Owner

- **Andrew Wikel** (@andrewwikel / @slashandy) — builder, Payment Partnerships TAM at Automattic

## Repository Structure

The repo contains a `wp-content/` directory at its root for Pressable legacy Git deploy.
Pressable rsyncs `wp-content/plugins/` and `wp-content/themes/` to the site. Non-deployable
files (docs, scripts, config) are excluded via `.deployignore`.

```
haanpaa/                           # Repo root
├── wp-content/                    # Synced to wp-content/ on Pressable
│   ├── plugins/
│   │   ├── gym-core/              # Gym management plugin (WooCommerce extension)
│   │   │   ├── gym-core.php       # Main plugin file
│   │   │   ├── PROJECT_BRIEF.md   # Plugin-level feature specs
│   │   │   ├── src/               # PHP source (PSR-4: Gym_Core\)
│   │   │   ├── assets/            # CSS + JS
│   │   │   ├── tests/             # PHPUnit
│   │   │   ├── composer.json      # Plugin dependencies
│   │   │   └── ...
│   │   └── hma-ai-chat/           # AI chat interface plugin (WP 7.0 AI Client)
│   │       ├── hma-ai-chat.php    # Main plugin file
│   │       ├── SETUP_GUIDE.md     # Full setup + API docs
│   │       ├── src/               # PHP source (PSR-4: HMA_AI_Chat\)
│   │       ├── assets/            # CSS + JS
│   │       └── ...
│   └── themes/
│       └── team-haanpaa/          # Custom theme
├── .deployignore                  # Excludes non-deployable files from Pressable sync
├── CLAUDE.md                      # Agent context (excluded from deploy)
├── README.md                      # Project overview (excluded from deploy)
├── MILESTONES.md                  # Implementation tracker (excluded from deploy)
├── scripts/                       # Provisioning & automation (excluded from deploy)
│   └── provision_pressable.py     # Pressable API provisioning script
└── docs/                          # Planning & architecture (excluded from deploy)
    ├── planning/                  # Business cases, feature plans
    │   ├── gym-core-project-brief.md
    │   ├── systems-consolidation-proposal.md
    │   ├── data-migration-guide.md
    │   ├── cowork-migration-playbooks.md
    │   ├── future-features-plan.md
    │   └── wp7-woo-ai-future-features-plan.md
    ├── architecture/              # Technical architecture decisions
    │   └── ai-architecture-paperclip.md
    └── migration/                 # Content & data from legacy platforms
        └── wix-content/           # Scraped Wix page content (see README.md inside)
```

## Deployment

**Pressable Legacy Git Deploy** — Pressable rsyncs `wp-content/` from the repo root to the site.

| What | How |
|------|-----|
| `gym-core` + `hma-ai-chat` | Auto-deployed via GitHub push → Pressable rsync |
| WooCommerce + WooPayments | Installed via WP-CLI or wp-admin |
| WooCommerce Subscriptions | Installed via WP-CLI (built from `woocommerce/woocommerce-subscriptions`) |
| Non-deploy files | Excluded by `.deployignore` |

**Pressable dashboard config:**
- Repository URL: this repo's HTTPS clone URL
- Branch: `main`
- Delete files not in repo: **OFF** (preserves WooCommerce, WooPayments, Subscriptions)

**Provisioning script:** `python3 scripts/provision_pressable.py` (see M1.1)

## Current State

**Milestones 1–6 code-complete.** All 20 finalization tasks resolved. Testing gate in progress.

### gym-core plugin (v1.0.0) — what's built
- **8 core modules**: Locations, Schedule, Members, Attendance, Ranks, Gamification, SMS, Integrations
- **11 REST controllers** under `gym/v1` namespace (20+ endpoints)
- Multi-location architecture (Rockford/Beloit) with taxonomy, product filtering, Store API
- Belt rank system with promotion eligibility engine and admin dashboards
- Attendance check-in with kiosk endpoint, QR support, milestone tracking
- Gamification: badges, streaks (quarterly freeze reset), targeted content shortcodes
- Twilio SMS integration with 2-way messaging and TCPA compliance
- CRM bridges: Jetpack CRM, AutomateWoo, MailPoet
- iCal feed for class schedules
- 60+ unit tests, PHPStan level 6, PHPCS, ESLint CI pipeline
- Badge evaluation deferred via Action Scheduler for check-in performance
- HPOS + Cart/Checkout Blocks compatibility declared

### hma-ai-chat plugin (v0.3.1) — what's built
- Four agent personas (Sales, Coaching, Finance, Admin) with tool registry
- WordPress 7.0 WP AI Client integration + direct Claude API fallback
- GymContextProvider injects real gym-core data into agent conversations
- Conversation memory with persistent history per agent
- Pending action approval queue (approve / approve-with-changes / reject)
- REST API: message, heartbeat (Paperclip webhook), action endpoints
- Webhook security (constant-time signature comparison)
- Gandalf social post tool for promotion celebrations

### What's next
See `MILESTONES.md` at repo root. Remaining work:
1. **M1.8**: Checkout flow E2E testing (READY)
2. **M1.9**: DNS + go-live cutover (BLOCKED — awaiting client sign-off)
3. **M6.3**: Staff approval flow polish (READY)
4. **M6.10**: gym/v1 endpoint completeness audit (READY)
5. **M6.2**: ~~LibreChat~~ SKIPPED — WP AI Client + ClaudeClient fallback is sufficient
6. **M7.1–7.4**: Media migration, Spark data import, staff training, parallel run (NOT STARTED)

## Key Documents

| Document | What it answers |
|----------|-----------------|
| `MILESTONES.md` | What do I build next? (master roadmap with acceptance criteria) |
| `wp-content/plugins/gym-core/PROJECT_BRIEF.md` | What does the gym plugin do? |
| `wp-content/plugins/hma-ai-chat/SETUP_GUIDE.md` | How does the AI chat plugin work? |
| `docs/planning/data-migration-guide.md` | How do we migrate data from Spark/GHL/Wix/USAePay? |
| `docs/planning/cowork-migration-playbooks.md` | AI agent prompts for extracting data from legacy platform UIs |
| `docs/planning/systems-consolidation-proposal.md` | Why is this migration happening? |
| `docs/architecture/ai-architecture-paperclip.md` | How do the AI agents work? |
| `docs/planning/future-features-plan.md` | What does WP 7.0 unlock for this project? |
| `docs/migration/wix-content/README.md` | What Wix content has been scraped? (index + status + agent guide) |

## Technical Requirements

| Requirement | Value |
|-------------|-------|
| WordPress | 7.0+ |
| WooCommerce | 10.3+ |
| PHP | 8.0+ |
| HPOS | Required |
| Cart/Checkout Blocks | Required |

## Conventions (all plugins)

1. **WordPress Coding Standards** — PHPCS with WordPress-Extra rules
2. **Strict typing** — `declare(strict_types=1)` in all PHP files
3. **HPOS-only** — Never use `get_post_meta()` for orders. Use CRUD methods.
4. **No jQuery** — Vanilla JS for frontend assets
5. **Dependency injection** — Constructor injection
6. **Security first** — Sanitize input, escape output, `$wpdb->prepare()`, capability checks
7. **Prefixes** — `gym_` / `GYM_CORE_` for gym-core; `hma_ai_chat_` / `HMA_AI_CHAT_` for hma-ai-chat

## Commands

All commands run from within the plugin directory (e.g., `cd wp-content/plugins/gym-core`):

```bash
# gym-core
composer lint          # PHPCS
composer lint-fix      # PHPCBF auto-fix
composer phpstan       # PHPStan level 6
composer test-unit     # Unit tests
composer test-all      # Lint + PHPStan + unit tests
```

## Business Context

- **Current stack**: Spark (~$1,037/mo), GoHighLevel (~$270/mo), Wix ($74/mo), USAePay, Vimeo, Dropbox, etc. Total: ~$2,100/mo
- **Target stack**: WordPress 7.0 + WooCommerce + WooPayments + Jetpack CRM + AutomateWoo + Twilio
- **Estimated savings**: $178–278/mo before Spark cancellation (the biggest line item)
- **Staff**: Darby (owner/head instructor), Amanda (admin), Joy (bookkeeper), Matt & Rachel (sales)
- **Locations**: Rockford, IL (primary) and Beloit, WI (secondary)
