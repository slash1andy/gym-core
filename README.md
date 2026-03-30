# Haanpaa Martial Arts — Monorepo

Custom WordPress/WooCommerce stack for [Haanpaa Martial Arts](https://haanpaafighthouse.com) (Rockford, IL + Beloit, WI). Replaces Spark Membership, GoHighLevel, Wix, and USAePay with a consolidated WordPress 7.0 ecosystem.

## Plugins

### [`plugins/gym-core/`](plugins/gym-core/)
The operational backbone — multi-location support, membership integration, class scheduling, belt rank tracking, attendance/check-in, gamification, Twilio SMS, and REST API for AI agents.

**Status:** Milestone 1.1 (Locations) complete. See [MILESTONES.md](MILESTONES.md) for the full roadmap.

### [`plugins/hma-ai-chat/`](plugins/hma-ai-chat/)
AI chat interface for the WordPress admin dashboard. Four agent personas (Sales, Coaching, Finance, Admin) powered by WordPress 7.0's WP AI Client. Paperclip webhook integration for autonomous agent scheduling.

**Status:** v0.1.0 scaffolded and production-ready. Wires into real data in Milestone 6.

## Documentation

| Document | Description |
|----------|-------------|
| [MILESTONES.md](MILESTONES.md) | Implementation tracker — 7 milestones with acceptance criteria |
| [docs/planning/](docs/planning/) | Business cases, feature plans, project briefs |
| [docs/planning/data-migration-guide.md](docs/planning/data-migration-guide.md) | Complete data migration plan (Spark, GHL, Wix, USAePay) |
| [docs/planning/cowork-migration-playbooks.md](docs/planning/cowork-migration-playbooks.md) | AI agent playbooks for extracting data from legacy UIs |
| [docs/architecture/](docs/architecture/) | AI architecture decisions |

## Requirements

- WordPress 7.0+
- WooCommerce 10.3+
- PHP 8.0+
- HPOS enabled
- Block-based Cart & Checkout

## Getting Started

```bash
# Install gym-core dependencies
cd plugins/gym-core && composer install

# Run quality checks
composer test-all
```

## License

GPL-2.0-or-later
