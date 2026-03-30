# Haanpaa Martial Arts — Claude Context

> Persistent context for AI agents working in this monorepo.

## What This Is

The **Haanpaa Martial Arts monorepo** — everything needed to migrate Haanpaa Martial Arts (2 locations: Rockford, IL and Beloit, WI) from a fragmented stack (Spark Membership, GoHighLevel, Wix, USAePay) onto a consolidated WordPress 7.0 / WooCommerce 10.3 ecosystem.

This is a **private, internal project** — not marketplace software.

## Owner

- **Andrew Wikel** (@andrewwikel / @slashandy) — builder, Payment Partnerships TAM at Automattic

## Repository Structure

```
haanpaa/                           # Monorepo root
├── CLAUDE.md                      # This file — agent context
├── README.md                      # Project overview for humans
├── MILESTONES.md                  # Implementation tracker (7 milestones)
├── plugins/                       # WordPress plugins
│   ├── gym-core/                  # Gym management plugin (WooCommerce extension)
│   │   ├── gym-core.php           # Main plugin file
│   │   ├── PROJECT_BRIEF.md       # Plugin-level feature specs
│   │   ├── src/                   # PHP source (PSR-4: Gym_Core\)
│   │   ├── assets/                # CSS + JS
│   │   ├── tests/                 # PHPUnit
│   │   ├── composer.json          # Plugin dependencies
│   │   └── ...
│   └── hma-ai-chat/               # AI chat interface plugin (WP 7.0 AI Client)
│       ├── hma-ai-chat.php        # Main plugin file
│       ├── SETUP_GUIDE.md         # Full setup + API docs
│       ├── src/                   # PHP source (PSR-4: HMA_AI_Chat\)
│       ├── assets/                # CSS + JS
│       └── ...
└── docs/                          # Planning & architecture (not plugin code)
    ├── planning/                  # Business cases, feature plans
    │   ├── gym-core-project-brief.md
    │   ├── systems-consolidation-proposal.md
    │   ├── data-migration-guide.md
    │   ├── cowork-migration-playbooks.md
    │   ├── future-features-plan.md
    │   └── wp7-woo-ai-future-features-plan.md
    └── architecture/              # Technical architecture decisions
        └── ai-architecture-paperclip.md
```

## Current State

**Milestone 1.1 (Locations) complete.** Everything else is scaffolded or planned.

### gym-core plugin — what's built
- Plugin scaffold (activation, deactivation, uninstall, PSR-4 autoloading)
- HPOS + Cart/Checkout Blocks compatibility
- `gym_location` taxonomy with Rockford/Beloit terms
- Location state management (cookies + user meta)
- Product query filtering, order location recording
- WooCommerce Blocks + Store API integration
- Frontend location selector banner
- CI pipeline (PHPCS, PHPStan level 6, PHPUnit, ESLint)

### hma-ai-chat plugin — what's built
- Plugin scaffold (v0.1.0, production-ready)
- Four agent personas (Sales, Coaching, Finance, Admin)
- REST API endpoints (message + Paperclip heartbeat webhook)
- Conversation persistence (custom DB tables)
- Pending action approval queue
- Webhook security (constant-time comparison, IP allowlist)
- 10 code quality issues found and fixed (see `testing-report.md`)

### What's next
See `MILESTONES.md` at repo root. In dependency order:
1. **M1.2–1.9**: Billing engine (WooPayments, Subscriptions, site design, go-live)
2. **M2**: Replace GoHighLevel (Jetpack CRM, Twilio SMS, AutomateWoo)
3. **M3**: Member portal + content gating
4. **M4**: Belt rank + attendance tracking
5. **M5**: Gamification (badges, streaks)
6. **M6**: AI operations layer (wire hma-ai-chat into real data)
7. **M7**: Media migration + Spark decommission

## Key Documents

| Document | What it answers |
|----------|-----------------|
| `MILESTONES.md` | What do I build next? (master roadmap with acceptance criteria) |
| `plugins/gym-core/PROJECT_BRIEF.md` | What does the gym plugin do? |
| `plugins/hma-ai-chat/SETUP_GUIDE.md` | How does the AI chat plugin work? |
| `docs/planning/data-migration-guide.md` | How do we migrate data from Spark/GHL/Wix/USAePay? |
| `docs/planning/cowork-migration-playbooks.md` | AI agent prompts for extracting data from legacy platform UIs |
| `docs/planning/systems-consolidation-proposal.md` | Why is this migration happening? |
| `docs/architecture/ai-architecture-paperclip.md` | How do the AI agents work? |
| `docs/planning/future-features-plan.md` | What does WP 7.0 unlock for this project? |

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

All commands run from within the plugin directory (e.g., `cd plugins/gym-core`):

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
