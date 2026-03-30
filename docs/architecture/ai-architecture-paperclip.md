Haanpaa Martial Arts
Revised AI Agent Architecture
Two-Layer Design: WordPress Native Chat + Paperclip Autonomous Orchestration
Author: Andrew Wikel
Date: March 29, 2026
Status: Draft
Table of Contents


Executive Summary
This document revises the AI agent architecture for the Haanpaa Martial Arts consolidated stack. The previous architecture designated LibreChat as the AI orchestration layer with four named agents (Sales, Coaching, Finance, Admin). The revised architecture replaces LibreChat entirely with two purpose-built layers: a native WordPress admin chat interface built on WordPress 7.0's AI Client and Connectors API (for interactive, staff-initiated queries), and Paperclip (github.com/paperclipai/paperclip) as an open-source autonomous orchestration engine (for scheduled, background operations). This eliminates a separate application server, simplifies infrastructure, and aligns with WordPress 7.0's upgrade pathway shipping April 9, 2026.
Why Replace LibreChat
LibreChat was originally specified as the AI orchestrator managing agent routing, API keys, and model selection
WordPress 7.0 now ships three core APIs that make LibreChat redundant for WordPress-native operations: WP AI Client (wp_ai_client_prompt()), Connectors API (Settings > Connectors for API key management), and Abilities API with MCP Adapter (agent capabilities as registered, discoverable tools)
A full LibreChat install requires: separate Node.js server, MongoDB, separate auth/user management, custom REST integrations to WordPress, separate admin interface for staff to learn
The replacement: a lightweight WP admin panel plugin (zero additional infrastructure) + Paperclip for autonomous scheduling (one small Docker container)
Architecture Overview


Layer 1 — WordPress Native Chat (Interactive)
A custom WordPress plugin renders a chat interface in the wp-admin dashboard
Uses wp_ai_client_prompt() to send prompts to Claude via the Connectors API
Agent personas defined as system prompts — staff selects Sales/Coaching/Finance/Admin from a dropdown
Role-scoped access via WordPress user roles: Administrators (Darby, Amanda, Joy) see all agents; Shop Manager (Matt, Rachel) sees only Sales + Coaching
Conversation history stored in WordPress options/custom tables
Zero additional infrastructure — runs on the same Pressable hosting
Layer 2 — Paperclip Autonomous Orchestration (Scheduled)
Paperclip (MIT-licensed, open-source) runs as a separate service (Node.js + PostgreSQL, small Docker container)
Manages four agents as "employees" in an org chart with defined roles and reporting lines
Heartbeat scheduling: each agent wakes on a configurable schedule, checks its work queue, executes tasks
HTTP Webhook Adapter sends standardized payloads to custom WP REST endpoints
WordPress plugin receives payloads, executes actions via WooCommerce/CRM APIs with nonce verification and capability checks
Built-in approval gates enforce "staff confirmation required for writes"
Per-agent monthly budget caps with auto-pause at 100% — critical for small business cost control
Complete audit trail: every tool call, decision, and reasoning logged immutably
Agent Schedule Matrix
Agent
Autonomous Schedule (Paperclip)
Interactive Tasks
Budget Cap
Sales
Every 30min (business hours): check new leads, trigger SMS sequences, update pipeline
Lead queries, SMS drafting, pipeline status, billing questions
$30/mo
Coaching
After each class window: process check-ins, update belt progress, flag anomalies
Belt rank queries, attendance lookup, class scheduling, member progress
$20/mo
Finance
Nightly: reconcile WooPayments, flag failed renewals, generate daily summary
Revenue questions, P&L queries, subscription metrics, weekly meeting prep
$15/mo
Admin
Daily: monitor plugin updates, verify backups, flag expiring memberships
Drive access, content management, task assignment, site health
$15/mo
Note: Budget caps are initial estimates. Paperclip provides 80% warning + auto-pause at 100%. Adjust based on actual usage in first 30 days.
WordPress Admin Chat Plugin — Technical Design
Core Architecture
Main plugin file registers admin menu page, enqueues React-based chat UI
REST API endpoint (hma-ai-chat/v1/message) receives chat messages, routes to wp_ai_client_prompt()
Agent persona system prompts stored as WordPress options, editable by administrators
Conversation history stored in a custom table (hma_ai_conversations) with user_id, agent, messages JSON, timestamps
Role-based agent access controlled via WordPress capabilities
Key Files
hma-ai-chat.php — bootstrap, hooks, capability declarations
src/Admin/ChatPage.php — admin menu page registration, script enqueue
src/API/MessageEndpoint.php — REST API for chat messages
src/Agents/AgentRegistry.php — agent persona definitions and system prompts
src/Data/ConversationStore.php — conversation persistence
assets/js/chat-app.jsx — React chat interface
WP 7.0 Integration Points
wp_ai_client_prompt() for all AI calls — no direct Anthropic SDK dependency
Connectors API for provider management — plugin doesn't touch API keys
Abilities API: each agent's capabilities registered as abilities, discoverable via MCP
Paperclip Integration — Technical Design
Paperclip HTTP Adapter Configuration
Each agent configured as an HTTP-type agent in Paperclip
Webhook URL points to WP REST endpoint: https://haanpaa.com/wp-json/hma-ai-chat/v1/heartbeat
Standardized payload: { runId, agentId, companyId, taskId, wakeReason }
WordPress REST endpoint validates request (shared secret in Authorization header), identifies agent, executes scheduled tasks
WordPress REST Endpoint for Paperclip
Route: hma-ai-chat/v1/heartbeat
Method: POST
Auth: shared secret validated against wp_options, plus IP allowlist
Response: execution results, token usage, task status
Async support: can return 202 Accepted with executionId for long-running tasks
Approval Flow for Write Actions (Three-Path)
All write actions from autonomous Paperclip agents require staff confirmation before execution. The flow supports three resolution paths:
Paperclip agent proposes a write action (e.g., “Send follow-up SMS to 5 cold leads”) via the heartbeat webhook with wakeReason: approval_needed
WordPress stores the proposed action in the hma_ai_pending_actions table with status ‘pending’ and returns the action ID to Paperclip
Admin dashboard shows pending actions with three buttons: Approve, Approve with Changes, and Reject
Path 1 — Approve: Staff approves → action executes immediately → Paperclip receives confirmation via status poll
Path 2 — Approve with Changes: Staff enters change instructions in a textarea → status set to ‘approved_with_changes’ with instructions stored in action_data → Paperclip polls, receives staff_instructions → agent re-executes incorporating changes → agent calls revised_action_complete webhook → status set to ‘completed’
Path 3 — Reject: Staff enters optional rejection reason → action discarded (status: ‘rejected’) → Paperclip polls, receives rejection_reason → logs the rejection in its audit trail
Paperclip polls for approval results via the check_approval_status heartbeat wake reason or the /actions/{id}/status REST endpoint. Both return the current status plus any staff instructions or rejection reasons.
What This Replaces — Comparison Table
Component
Previous (LibreChat)
Revised (WP Chat + Paperclip)
Chat interface
LibreChat web UI (separate URL)
WP Admin AI Panel (same dashboard)
AI orchestration
LibreChat routes to models
wp_ai_client_prompt() in WP core
API key management
LibreChat admin panel
Settings > Connectors (WP 7.0)
Agent capabilities
Custom REST endpoints
wp_register_ability() + MCP Adapter
Background automation
Not included (manual triggers only)
Paperclip heartbeat scheduler
Cost controls
None built-in
Paperclip per-agent budget caps
Audit trail
LibreChat conversation logs
Paperclip immutable audit + WP logs
Infrastructure
Node.js + MongoDB + separate auth
WP plugin (zero infra) + small Docker
User management
Separate LibreChat users
WordPress roles (already configured)
Maintenance
Separate updates, separate monitoring
WP plugin auto-updates + Docker
Implementation Notes
The WP admin chat plugin can begin development immediately on a WordPress 7.0 beta environment
Paperclip requires a Docker host — can share the Pressable VPS or run on a separate $5-10/mo container
The Paperclip HTTP adapter means WordPress never gives Paperclip direct database access — all interactions go through the WordPress REST API security layer
Both layers share the same agent persona definitions — system prompts defined once in WordPress, consumed by both the chat plugin and Paperclip
The WordPress Abilities API registration is shared: interactive chat and Paperclip heartbeats both invoke the same registered abilities
Alignment with WordPress 7.0 Upgrade Pathway
This architecture is built entirely on WordPress 7.0 platform primitives:
WP AI Client replaces custom AI orchestration
Connectors API replaces per-service API key management
Abilities API + MCP Adapter replaces custom REST endpoints for agent capabilities
When WordPress ships MCP Client (7.1 or 7.2), external services like QuickBooks, Twilio, and Google Workspace become native WordPress abilities — further reducing custom integration code
When WooCommerce MCP matures beyond developer preview, agent capabilities expand with zero custom build
Resolved Decisions
Paperclip hosting: Shared VPS with Pressable. Paperclip runs as a Docker container on the same infrastructure, minimizing cost and latency.
Webhook secret rotation: Dual-secret rotation with 5-minute grace period. When rotated, both old and new secrets are accepted during the window, allowing Paperclip config to be updated without downtime. Implemented in WebhookValidator. Manual rotation via admin screen; automated monthly rotation available via wp_cron if desired.
Conversation retention: 30-day retention. Daily wp_cron job purges conversations older than 30 days. Period is filterable via ‘hma_ai_chat_retention_days’ hook. Actual business actions (CRM updates, SMS sends, subscription changes) persist in their respective systems regardless.
Paperclip org chart: Hierarchical. Admin agent serves as the “company CEO” and orchestrator. Sales, Coaching, and Finance agents report to Admin. For autonomous operations, Admin dispatches and coordinates cross-agent work (e.g., Finance flags failed payment → Admin routes to Sales for member follow-up). Admin is also the orchestrator for all human-assigned tasks from the WP dashboard. Staff interact with individual agents for domain-specific queries, but Admin handles escalations and multi-agent workflows.
Customer-facing chatbot: Separate frontend plugin with distinct security boundary. Two frontend audiences: (1) Members see a self-service bot scoped to class schedule, belt progress, payment method updates, booking, and waiver status. (2) Coaches see a coach-scoped view for class rosters, attendance, and promotion eligibility. Coaches are essentially students who teach and should not see pipeline data, revenue, or other members’ billing. The backend hma-ai-chat plugin remains staff-only (Darby, Amanda, Joy, Matt, Rachel). Both plugins share wp_ai_client_prompt() infrastructure but with completely separate system prompts, capabilities, and data access layers.
Approval flow: Three-path approval for all write actions: (1) Staff approves → action executes → Paperclip receives confirmation. (2) Staff approves with changes → agent re-executes incorporating staff’s modifications → action executes → Paperclip receives confirmation. (3) Staff rejects → action discarded → Paperclip logs the rejection. Implemented in PendingActionStore with ‘approved_with_changes’ status and a complete_revised_action() method for the agent callback.
Remaining Open Item
Paperclip community stability: Project is relatively new and evolving. Monitor for breaking changes, pin to a specific release tag in Docker, and maintain a fallback plan where the autonomous scheduling could be replaced with WordPress Action Scheduler if Paperclip development stalls.