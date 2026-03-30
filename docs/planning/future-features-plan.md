
Building on What’s Coming
How WordPress 7.0 + WooCommerce AI Changes
Reshape the Haanpaa Martial Arts System Redesign
Future Features Plan for the Consolidated Stack
Author
Andrew Wikel
Date
March 23, 2026
Status
Draft — Research Complete

Executive Summary
The proposed Haanpaa Martial Arts consolidated stack (WordPress/WooCommerce on Pressable, WooPayments, Jetpack CRM, AutomateWoo, LibreChat with four named AI agents) was designed to replace a fragmented collection of Spark Membership, GoHighLevel, Wix, and USAePay/Pitbull Processing. That architecture was already strong. What’s changed in the last 90 days makes it significantly stronger.
WordPress 7.0 ships April 9, 2026 with a provider-agnostic AI layer baked into core: a PHP AI Client any plugin can call, a Connectors API for managing external services, and an Abilities API that turns WordPress capabilities into tools AI agents can use via the Model Context Protocol (MCP). WooCommerce is shipping native MCP integration that lets AI clients manage products and orders directly. And Stripe’s Agentic Commerce Protocol (ACP) is defining how AI agents discover products and process payments without a browser.
This document maps how each of these upstream changes impacts the Haanpaa stack, what new features they unlock that weren’t possible before, and a phased plan for building them. The bottom line: several components we planned to build custom (AI orchestration, provider management, capability registration) are about to ship as WordPress platform primitives. That simplifies the architecture, reduces maintenance burden, and opens doors to features we hadn’t considered.

1. What’s Changing Upstream
1.1 WordPress 7.0 (April 9, 2026)
Three releases are planned for 2026: 7.0 (April), 7.1 (August), 7.2 (December). The AI-relevant additions that affect our stack:
WP AI Client in Core: wp_ai_client_prompt() is a fluent PHP API for making AI requests from any plugin or theme. Supports system instructions, file attachments, model preferences, and structured JSON responses. This means our AI agents no longer need LibreChat as a middleman for WordPress-native operations. The four Haanpaa agents (Sales, Coaching, Finance, Admin) can call AI directly from PHP.
Connectors API: A standardized framework for registering connections to external services, with built-in admin UI at Settings > Connectors. Ships with OpenAI, Google AI, and Anthropic provider plugins. This replaces the custom API key management we’d have needed for LibreChat’s AI provider connections.
Abilities API + MCP Adapter: Abilities are WordPress’s way of registering typed capabilities with permissions and schemas. The MCP Adapter converts these into Model Context Protocol tools. WordPress becomes both an MCP server (AI agents connect to it) and, in the roadmap, an MCP client (WordPress ingests capabilities from external services like CRMs, analytics, and payment platforms). This is the architectural shift that matters most for Haanpaa.
Provider-Agnostic Design: The AI layer works with any model provider. If we start with OpenAI and later want Claude or a local model, we swap the connector, not the code. James LePage (leading Applied AI at Automattic) has stated these primitives are designed to “remain relevant regardless of where Transformers-based LLMs point in the future.”

1.2 WooCommerce MCP + Agentic Commerce
Native MCP (Developer Preview): WooCommerce 10.3+ exposes product and order management as MCP tools. Architecture: MCP client → local proxy (@automattic/mcp-wordpress-remote) → WordPress REST endpoint → Abilities system. Authenticated via WooCommerce REST API keys. Extensible via wp_register_ability().
OpenAI Product Feed: A WooCommerce extension generates product feeds compliant with OpenAI’s spec for ChatGPT product discovery. Already in alpha, architected for extensibility to other ACP platforms.
Stripe ACP + Shared Payment Tokens: WooCommerce/WooPayments is actively building ACP support (payment token handling + feed compliance, targeting Q1 2026). Once live, merchants can apply to OpenAI’s Instant Checkout so AI assistants can sell their products.
REST API Caching (WC 10.5+): Up to 95% faster admin loads with smarter caching and async data loading. Directly benefits any real-time dashboard we build.

1.3 Automattic AI Direction
Automattic acquired WPAI (AgentWP’s parent) in December 2024. The Applied AI team under James LePage is building these platform primitives to power Automattic’s own products. Key signal: these aren’t community experiments—they’re business-critical infrastructure that WooCommerce, Jetpack, and WordPress.com depend on. We ride that development flywheel.

2. Impact on the Proposed Haanpaa Stack
Here’s how each component of the proposed stack is affected by what’s coming:
Stack Component
Original Plan
What Changes with 7.0 / WC MCP
AI Agents (Sales, Coaching, Finance, Admin)
LibreChat as orchestrator, custom API integrations for each agent to talk to WordPress
Agents can call wp_ai_client_prompt() natively from PHP. LibreChat shifts from orchestrator to conversation UI. Agent logic lives in WordPress as registered Abilities, accessible via MCP.
AI Provider Management
Manual API key config in LibreChat for each provider
Connectors API handles provider registration, key storage, and admin UI. One screen to manage all AI providers. Swap providers without touching agent code.
WooPayments (replacing USAePay/Pitbull)
Standard WooPayments integration for subscription billing
Payment operations become MCP abilities. Finance agent can query transactions, flag failed payments, surface payout status. ACP support means classes/merch discoverable via ChatGPT.
Jetpack CRM (replacing GoHighLevel)
CRM with custom Twilio SMS integration
CRM operations can register as Abilities, exposed via MCP. Sales agent queries/updates contacts natively. When WP ships MCP Client, CRM can ingest external service data.
AutomateWoo (workflows)
Triggered automations for retention, reminders, nurture sequences
Workflows can trigger wp_ai_client_prompt() for AI-generated content: personalized retention messages, smart SMS copy, dynamic offer generation based on member behavior.
Member Portal + Gamification
Membership-gated dashboard with belt progress, badges, streaks, milestones
AI-generated personalized training recommendations based on attendance. Coaching agent uses AI to create individualized feedback tied to belt progress and check-in patterns.
SMS (Twilio backbone)
Twilio wired into Jetpack CRM and AutomateWoo for lead nurture, retention, reminders
AI-composed SMS via wp_ai_client_prompt(). Context-aware messages: the Sales agent drafts follow-ups knowing the lead’s inquiry history, trial status, and location.
Check-in System (tablet kiosk)
Attendance tracking with WordPress hooks for sub-500ms notifications
Check-in events register as Abilities. Coaching agent receives real-time attendance data via MCP, enabling AI-driven streak tracking and automated encouragement.

The net effect: LibreChat’s role narrows from full AI orchestrator to conversation interface. The heavy lifting—AI model calls, provider management, capability registration, agent-to-WordPress communication—moves into WordPress core primitives. This reduces custom code, maintenance surface, and vendor lock-in.

3. New Features Unlocked
These are features that weren’t in the original Haanpaa proposal because the platform primitives didn’t exist yet. Now they’re buildable.
Phase 1: Ship with Launch (April–June 2026)
Features that build on WordPress 7.0 launch and can be included in the initial Haanpaa deployment.
3.1 Native AI Agents via Abilities API
Register each of the four Haanpaa agents (Sales, Coaching, Finance, Admin) as WordPress Abilities with proper permission scoping. Each agent’s capabilities are defined as wp_register_ability() calls with input/output schemas and permission callbacks. The MCP Adapter automatically exposes them to any MCP-compatible AI client.
What this replaces: Custom LibreChat-to-WordPress API integrations for each agent. Instead of building bespoke REST endpoints, each capability is a registered Ability with built-in auth and discoverability.
Example: The Finance agent’s “get-payment-status” ability is registered with manage_woocommerce permission, accepts an order ID, returns transaction details from WooPayments. Any MCP client (LibreChat, Claude Code, future Automattic AI assistant) can call it.
3.2 AI-Composed SMS and Email
AutomateWoo workflows call wp_ai_client_prompt() to generate personalized message content before sending via Twilio (SMS) or MailPoet (email). The AI has access to member context: name, belt rank, attendance streak, last visit, program, location.
Retention example: Member hasn’t checked in for 7 days → AutomateWoo triggers → wp_ai_client_prompt() generates a personalized SMS referencing their current belt, streak status, and upcoming class schedule → Twilio sends. No template stiffness, every message is contextual.
Lead nurture example: New trial signup → Sales agent generates a follow-up sequence where each message builds on the previous conversation, adapting tone based on engagement signals.
3.3 Smart Onboarding with Connectors API
Use the Connectors API to create a unified setup experience for Haanpaa’s staff. Instead of configuring API keys across LibreChat, Twilio, WooPayments, and AI providers separately, everything lives under Settings > Connectors. Darby, Amanda, and Joy get one admin screen to manage all external service connections.

Phase 2: Post-Launch Enhancements (July–September 2026)
Features that build on the running system and require a few months of operational data.
3.4 AI Coaching Assistant
The Coaching agent uses wp_ai_client_prompt() with member attendance history, belt rank, and program data to generate personalized training recommendations. Surfaces in the member portal dashboard alongside belt progress bars. The agent can answer questions like “What should I focus on before my next belt test?” or “How does my attendance compare to others at my rank?”
Technical basis: WP AI Client + WooCommerce Memberships data + check-in Abilities. Coaching agent registered as an Ability, accessible via the member portal’s front-end.
3.5 Intelligent Gamification Engine
Extend the gamification system (badges, streaks, milestones) with AI-generated content. When a member hits a milestone, wp_ai_client_prompt() generates a personalized congratulations message and training tip. Streak notifications adapt their urgency and tone based on the member’s history. Badge descriptions are dynamic, not static templates.
Example: Member earns their third stripe on blue belt → AI generates a message acknowledging their specific journey (time at this belt, classes attended, improvement trajectory) → surfaces as a notification in the member portal and optionally via SMS.
3.6 Natural-Language Financial Reports
The Finance agent uses wp_ai_client_prompt() with WooPayments transaction data to answer natural-language questions from Darby and Joy: “How did membership revenue compare month over month?” “Which payment methods have the most failures?” “What’s our effective processing rate this month?” Replaces manual spreadsheet analysis with conversational insights.
Technical basis: WP AI Client + WooCommerce analytics data + WooPayments transaction API. Finance agent Ability registered with manage_woocommerce permission.

Phase 3: Platform Evolution (October 2026+)
Features that depend on WordPress 7.1/7.2 capabilities, especially the MCP Client.
3.7 WordPress as the Gym’s Central Hub via MCP Client
When WordPress ships MCP Client capability (proposed for 7.1 or 7.2), the Haanpaa WordPress installation can ingest capabilities from external MCP servers. This means:
QuickBooks via MCP: WordPress connects to a QuickBooks MCP server and exposes accounting operations as native Abilities. The Finance agent reconciles WooPayments revenue with QuickBooks entries without leaving WordPress.
Twilio via MCP: Instead of custom webhook integrations, Twilio’s capabilities become WordPress Abilities. The Sales agent sends SMS, checks delivery status, and manages conversations through the standard Abilities/MCP interface.
Google Workspace via MCP: Calendar scheduling, document sharing, and email become native WordPress operations. The Admin agent can schedule a coaches’ meeting or share a class schedule change without switching tools.
This is the “architectural role change”: WordPress goes from hosting the gym’s website to orchestrating the gym’s entire operational stack through AI agents.
3.8 AI-Powered Lead Scoring and Pipeline
With several months of CRM data in Jetpack CRM, the Sales agent can use wp_ai_client_prompt() to score leads based on engagement patterns: website visits, trial class attendance, SMS response rates, referral source. Surfaces a priority-ranked pipeline in the admin dashboard for Matt and Rachel. Replaces the manual lead management that GoHighLevel currently handles.
3.9 Agentic Commerce for Classes and Merchandise
Once WooPayments ACP support lands, Haanpaa’s products (class packages, memberships, merchandise, private lessons) become discoverable by AI assistants like ChatGPT. A potential member asking ChatGPT “What are the best martial arts classes near Rockford?” could see Haanpaa’s offerings, pricing, and trial class availability—and purchase directly through the AI assistant. For a local business, this is a meaningful new acquisition channel.

4. Revised Architecture Summary
The original proposed stack remains correct. What changes is how the components connect and where AI logic lives:
Layer
Original Architecture
Revised with WP 7.0
AI Orchestration
LibreChat (full orchestrator, API keys, agent routing, model selection)
WordPress core (WP AI Client + Connectors + Abilities). LibreChat becomes conversation UI only.
Agent Capabilities
Custom REST endpoints per agent, bespoke auth
Registered Abilities with schemas + permissions, auto-exposed via MCP Adapter
Provider Management
Per-service API key config across multiple admin screens
Unified Connectors API admin screen for all external services
AI Content Generation
LibreChat generates, custom webhook pushes to AutomateWoo/Twilio
wp_ai_client_prompt() called directly in AutomateWoo workflows and agent Abilities
External Tool Integration
Custom API integrations per service (QuickBooks, Twilio, Google)
MCP Client ingests external MCP servers as native Abilities (WP 7.1/7.2)
Product Discoverability
SEO + Google Business Profile + traditional web presence
Add: OpenAI Product Feed + ACP for AI assistant discoverability

5. Implementation Timeline
Phase
Deliverables
Depends On
Phase 1
Apr–Jun 2026
Register 4 agents as Abilities. AI-composed SMS/email in AutomateWoo. Connectors API setup for unified admin. WP AI Client replaces custom AI orchestration.
WordPress 7.0 launch (Apr 9). WooCommerce MCP feature flag enabled.
Phase 2
Jul–Sep 2026
AI Coaching assistant in member portal. Intelligent gamification with AI-generated content. Natural-language financial reports for Finance agent.
3+ months of operational data. WooCommerce 10.5 REST API caching.
Phase 3
Oct 2026+
MCP Client for QuickBooks/Twilio/Google integration. AI lead scoring pipeline. ACP for class/merch discoverability via ChatGPT.
WordPress 7.1/7.2 MCP Client. WooPayments ACP readiness.

6. What This Means for the Haanpaa Proposal
The cost-of-ownership comparison document (haanpaa-systems-comparison.docx) should be updated to reflect these changes. Specifically:
The AI story is no longer speculative. WordPress core ships with AI primitives in three weeks. The four named agents aren’t a custom build on an experimental platform—they’re registered capabilities on the world’s most widely deployed CMS.
LibreChat’s scope shrinks, reducing complexity. AI orchestration, provider management, and capability registration move to WordPress core. LibreChat handles what it’s best at: multi-user conversation interface with RBAC. Less custom integration = lower maintenance cost.
Agentic commerce is a new acquisition channel. Haanpaa’s classes and memberships discoverable via AI assistants is a competitive advantage no other martial arts school in the Rockford/Beloit area will have. Worth calling out in the proposal.
The GoHighLevel replacement gets stronger. The largest open architectural question—CRM/marketing replacement—is significantly de-risked. Jetpack CRM + AutomateWoo + wp_ai_client_prompt() delivers the AI-powered lead nurture and retention that GoHighLevel provides, but natively integrated with the rest of the WordPress stack instead of as a siloed SaaS.
The platform investment case is clearer. Every feature built on WordPress Abilities and WP AI Client benefits from Automattic’s ongoing investment in these primitives. Haanpaa gets platform improvements for free. That’s the opposite of the current stack, where Spark Membership, GoHighLevel, and Wix evolve independently on their own timelines.

7. Sources
External
WordPress.org Roadmap — wordpress.org/about/roadmap/
Proposal for merging WP AI Client into WordPress 7.0 — make.wordpress.org/core/2026/02/03/
Introducing the WordPress AI Client SDK — make.wordpress.org/ai/2025/11/21/
From Abilities to AI Agents: Introducing the WordPress MCP Adapter — developer.wordpress.org/news/2026/02/
WooCommerce AI & Agentic Commerce Roadmap — developer.woocommerce.com/2025/10/03/
WooCommerce MCP Integration Docs — developer.woocommerce.com/docs/features/mcp/
Stripe Agentic Commerce Suite — stripe.com/blog/agentic-commerce-suite
Stripe ACP Documentation — docs.stripe.com/agentic-commerce/protocol
Internal (Slack)
#woo-agentic-commerce — ACP status, OpenAI Product Feed alpha, payment token handling timeline
#big-ocean — James LePage on Features API, MCP Adapter, Universal Registry priorities
#vip-gtm-media — Analysis of MCP Client as orchestration layer, WP 7.0 AI significance
A8C and Core AI Update P2 — aip2.wordpress.com/2026/02/25/