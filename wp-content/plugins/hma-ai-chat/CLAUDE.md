# HMA AI Chat (Gandalf) — Plugin Agent Context

> Agent context specific to the hma-ai-chat plugin. For monorepo-level context, see the root `CLAUDE.md`.

## Plugin Identity

| Field | Value |
|-------|-------|
| Slug | `hma-ai-chat` |
| Name | HMA AI Chat |
| Staff-facing name | **Gandalf** |
| Namespace | `HMA_AI_Chat` |
| Text Domain | `hma-ai-chat` |
| Constant Prefix | `HMA_AI_CHAT_` |
| Hook Prefix | `hma_ai_chat_` |
| Main File | `hma-ai-chat.php` |
| Version | 0.1.0 |

## What This Plugin Does

**Gandalf** is the AI assistant for Haanpaa Martial Arts staff. Built as a WordPress admin chat interface on WordPress 7.0's WP AI Client (`wp_ai_client_prompt()`). Four agent personas (Sales, Coaching, Finance, Admin) with role-based access control. Staff interact with "Gandalf" — the technical plugin slug `hma-ai-chat` is internal only.

**Two-layer architecture** (see `docs/architecture/ai-architecture-paperclip.md`):
- **Layer 1 (this plugin)**: Interactive chat — staff selects an agent, sends messages, gets AI responses
- **Layer 2 (Paperclip)**: Autonomous scheduling — agents wake on schedules, execute tasks, request approval via webhook

## Architecture

### Source Structure
```
src/
├── Plugin.php               # Singleton orchestrator
├── Activator.php             # Table creation, webhook secret init
├── Deactivator.php           # Cron cleanup
├── Admin/
│   └── ChatPage.php          # Tools > AI Chat menu page
├── API/
│   ├── MessageEndpoint.php   # POST /hma-ai-chat/v1/message
│   ├── HeartbeatEndpoint.php # POST /hma-ai-chat/v1/heartbeat (Paperclip webhook)
│   └── ActionEndpoint.php    # Approval/rejection interface
├── Agents/
│   ├── AgentPersona.php      # Individual agent definition
│   └── AgentRegistry.php     # Singleton registry, 4 default agents
├── Data/
│   ├── ConversationStore.php # Conversation + message CRUD
│   └── PendingActionStore.php# Approval queue
└── Security/
    └── WebhookValidator.php  # Bearer token, IP allowlist, secret rotation
```

### Database Tables
- `wp_hma_ai_conversations` — user_id, agent, title, timestamps
- `wp_hma_ai_messages` — conversation_id (FK), role, content, tokens_used
- `wp_hma_ai_pending_actions` — agent, action_type, action_data (JSON), status, run_id

### Extension Points
- `hma_ai_chat_agents_registered` — add custom agents to the registry
- `hma_ai_chat_action_approved` — fires when an action is approved
- `hma_ai_chat_action_rejected` — fires when an action is rejected
- `hma_ai_chat_execution_complete` — fires when Paperclip run completes

### Agent Personas
| Agent | Capability | Icon |
|-------|-----------|------|
| Sales | `edit_posts` | 💼 |
| Coaching | `edit_posts` | 🥋 |
| Finance | `manage_options` | 💰 |
| Admin | `manage_options` | ⚙️ |

## Current Status

**v0.1.0 — scaffolded and production-ready.** All 12 source files are complete implementations, not stubs. 10 code quality issues were found and fixed (see `testing-report.md`).

**Milestone 6** wires this plugin into real data from gym-core (attendance, ranks, CRM, billing).

## Development

```bash
composer install          # Dev dependencies
composer run analyse      # PHPStan level 7
npm install               # JS dependencies (ESLint only)
```

## Dependencies

- WordPress 7.0+ with WP AI Client plugin active
- PHP 8.0+
- No WooCommerce dependency (standalone admin tool)
