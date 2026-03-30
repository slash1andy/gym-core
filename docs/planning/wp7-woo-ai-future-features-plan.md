
Building on What’s Coming
WordPress 7.0 + WooCommerce AI + Agentic Commerce
A Future Features Plan for Woo Payment Partnerships
Author
Andrew Wikel
Date
March 22, 2026
Status
Research Complete / Draft Plan

Executive Summary
Three converging forces are about to reshape how WooCommerce stores operate and how we build on top of them. WordPress 7.0 (shipping April 9, 2026) brings a provider-agnostic AI layer into core. WooCommerce is shipping native MCP integration that turns every store into an AI-accessible endpoint. And Stripe’s Agentic Commerce Protocol (ACP) is defining how AI agents will discover products, initiate checkout, and process payments without a browser.
This document maps those upstream changes, identifies the features we can build on top of them, and proposes a phased roadmap for the Woo Payment Partnerships team. The goal is to be ready to ship integration features as these platform primitives land, not after the ecosystem has already moved.

1. Platform Changes Landing in 2026
1.1 WordPress 7.0 — AI Becomes a Platform Primitive
WordPress 7.0, releasing April 9, 2026, is the most significant architectural shift since Gutenberg. Three releases are planned for 2026: 7.0 (April), 7.1 (August), and 7.2 (December). The AI-relevant additions:
WP AI Client in Core
The wp_ai_client_prompt() function ships in core, providing a fluent PHP API for any plugin to make AI requests. It supports method chaining for system instructions, file attachments, model preferences, and structured JSON responses. This is not a third-party library; it is a WordPress core function available to every developer on earth.
Connectors API
A new framework for registering and managing connections to external services. The initial focus is AI providers, but the architecture is generic. Admin UI lives at Settings > Connectors. Three official provider plugins ship at launch: OpenAI, Google AI, and Anthropic (Claude). Plugin developers register providers via the ai_provider connector type, and WordPress handles API key storage, provider discovery, and admin UI.
Abilities API + MCP Adapter
The Abilities API is WordPress’s standardized system for registering capabilities. The MCP Adapter converts these abilities into Model Context Protocol tools, making WordPress both an MCP server (agents connect to it) and, in the proposed roadmap, an MCP client (WordPress ingests capabilities from external MCP servers like HubSpot, Salesforce, and analytics platforms).
This dual-direction MCP capability is the most underrated item on the roadmap. It transforms WordPress from a CMS into a central orchestration layer for AI agents across an entire tool stack.
Provider-Agnostic Architecture
The building blocks are designed to remain relevant regardless of where LLM technology evolves. No single vendor is baked in. Any plugin or theme can connect to AI services through the standardized interface. This directly answers the question: “What if OpenAI goes away, or GPT-5 changes everything?”

1.2 WooCommerce — MCP, Agentic Commerce, and Performance
WooCommerce MCP (Developer Preview)
Shipping in WooCommerce 10.3+, the native MCP integration exposes store operations (product management, order management) as discoverable tools for AI clients. Architecture uses a local proxy approach: MCP clients communicate via stdio/JSON-RPC, a local proxy (@automattic/mcp-wordpress-remote) translates to HTTP, and the WordPress MCP server processes through the Abilities system. Authentication uses WooCommerce REST API keys.
OpenAI Product Feed Spec
WooCommerce is building a dedicated extension (woocommerce/OpenAI-Product-Feed) to generate product feeds compliant with OpenAI’s spec for ChatGPT product discovery. The alpha is architected to be extensible for other ACP-enabled platforms. MPN (Manufacturer Part Number) support is being evaluated for inclusion in WooCommerce core as a common attribute across ACP specs.
Agentic Commerce Protocol (ACP) Support
Internal work is underway to get WooCommerce/Stripe/WooPayments ready to support ACP. Remaining work as of Q1 2026 includes payment token handling and feed compliance. Once complete, merchants can apply to OpenAI’s Instant Checkout program. The integration is being designed so the basics ship in WooCommerce core, with extensions hooking in to provide additional integrations.
Commerce in a Box (CIAB)
The next-generation WooCommerce admin experience, built with React and the Interactivity API. CIAB includes a new onboarding flow with AI site generation, WooPayments order page integration (transaction fees, manual capture, fraud/risk, disputes, test mode), and payment method promotions infrastructure. CIAB represents the future of how merchants interact with their stores.
Performance Improvements
REST API caching engine (experimental in 10.5), delivering up to 95% faster admin load times with smarter caching and async data loading. Cost of Goods Sold graduating from beta. Settings import/export via Blueprints.

1.3 Stripe — Agentic Commerce Suite
Stripe has launched the Agentic Commerce Suite, co-developed with OpenAI, which includes:
Agentic Commerce Protocol (ACP): An open-source RESTful specification enabling AI agents to discover products, initiate checkout, and process payments. Implementable as a RESTful interface or MCP server.
Shared Payment Tokens (SPT): A new payment primitive that lets AI agents initiate payments using a buyer’s permission and preferred payment method without exposing credentials.
Instant Checkout: Already live in ChatGPT. Major brands (URBN, Etsy, Coach, Revolve) are onboarding.
MCP Integration: Gives agents structured, machine-readable access to inventory, prices, and checkout logic instead of scraping web pages.
OpenAI charges a 4% merchant fee on transactions through ChatGPT. Internal discussion is actively evaluating how WooPayments captures or offsets this fee.

1.4 Automattic AI Strategy
Automattic acquired WPAI (creators of CodeWP, WP.Chat, AgentWP) in December 2024. The founding team, led by James LePage, is now leading Applied AI at Automattic. Key internal signals:
LePage’s directive: “Do what makes sense to make sure WordPress is still a major player in an agentic web era, but align directly to our business requirements and interests.”
The AI primitives power Automattic’s own products: the AI assistant, WooCommerce agents, and Commerce in a Box.
Features API, Universal Registry, and MCP Adapter are the active workstreams under Big Ocean.
AI-driven release processes are already operational across WooCommerce Subscriptions, Braintree, and other payment products.
Company-wide AI enablement training launched February 2026.

2. Technology Map: What We Can Build On
Platform Primitive
What It Enables
Available When
Our Opportunity
WP AI Client
Any plugin can make AI requests via wp_ai_client_prompt()
WordPress 7.0 (Apr 9, 2026)
AI-powered payment insights, smart recommendations
Connectors API
Standardized external service registration with admin UI
WordPress 7.0 (Apr 9, 2026)
Payment provider connectors, PSP migration discovery
Abilities API
Register typed capabilities with permissions + schemas
WordPress 7.0 (Apr 9, 2026)
Payment abilities, subscription management, fraud alerts
MCP Adapter
Abilities become MCP tools accessible to AI agents
WordPress 7.0 (Apr 9, 2026)
AI agent store management, automated operations
WooCommerce MCP
Product/order operations as MCP tools with REST API auth
WooCommerce 10.3+ (now)
AI-driven order management, payment reconciliation
Stripe ACP + SPT
AI agents discover products, checkout, and pay via tokens
Q1-Q2 2026 (active)
Agentic checkout for WooPayments merchants
OpenAI Product Feed
Structured product catalog for ChatGPT discovery
Alpha available now
Product discoverability for payment-enabled stores
CIAB Admin
Next-gen React admin with WooPayments deep integration
In development
Payment partnerships features in modern admin
REST API Caching
95% faster admin loads, async data loading
WooCommerce 10.5+
Real-time payment dashboards, faster reporting

3. Proposed Features Roadmap
Phase 1: Foundation (April–June 2026)
Immediate builds leveraging WordPress 7.0 launch and existing WooCommerce MCP.
3.1 Payment Gateway Abilities for MCP
Register WooPayments and partner payment gateways as WordPress Abilities, exposed via the MCP Adapter. This lets AI agents query payment status, initiate refunds (with merchant confirmation), check transaction details, and surface fraud alerts. Build on the existing WooCommerce MCP capabilities with payment-specific tools.
Technical basis: Abilities API + wp_register_ability() with payment-specific schemas, permission_callback tied to manage_woocommerce capability.
Depends on: WordPress 7.0 (Apr 9), WooCommerce MCP feature flag.
Scope: Read-only payment queries first (transaction lookup, payout status, dispute alerts), write operations (refunds, captures) in Phase 2 with confirmation flows.
3.2 AI-Powered PSP Migration Advisor
Extend the PSP Migration plugin with an AI advisor that uses wp_ai_client_prompt() to analyze a merchant’s current payment setup and recommend optimal migration paths. The advisor can compare processing rates, estimate savings, and generate migration readiness reports.
Technical basis: WP AI Client + Connectors API for AI provider selection. Uses existing PSP migration adapters for source/destination analysis.
User story: Merchant installs the migration tool, the AI advisor scans their current gateway config and transaction history, then presents a data-backed recommendation with projected savings.
3.3 Connectors API Payment Provider Registration
Implement payment service providers as Connectors API providers. This puts payment gateway credentials and configuration in the same admin screen as AI providers, creating a unified connector management experience. Also enables discovery: when a merchant connects a new payment provider, the system can surface AI-driven optimization recommendations.
Technical basis: Register custom connector types (payment_provider) via the Connectors API framework. Credentials stored using WordPress’s centralized credential management.

Phase 2: Agentic Commerce (July–September 2026)
Build on ACP readiness and the OpenAI Product Feed to enable AI-driven commerce.
3.4 WooPayments ACP Integration
Ship the WooPayments integration with Stripe’s Agentic Commerce Protocol and Shared Payment Tokens. This allows AI agents (ChatGPT, Google Gemini, etc.) to discover WooPayments-enabled products and complete checkout without a browser. The integration should be a natural WooPayments feature, not a separate extension.
Technical basis: Stripe ACP endpoint implementation, SPT handling in WooPayments payment flow, OpenAI Product Feed extension for catalog exposure.
Revenue angle: Position WooPayments as the required gateway for ACP-enabled checkout, creating a strong incentive for merchants to adopt or stay on WooPayments.
Open question: How WooPayments handles the 4% OpenAI merchant fee on ChatGPT transactions. Needs product/business decision.
3.5 AI Agent Store Management Tools
Extend WooCommerce MCP with payment-specific write operations: AI agents can process refunds, capture authorized payments, update payment methods, and manage subscriptions. All write operations require merchant confirmation via a confirmation flow in the CIAB admin or via a notification system.
Technical basis: WooCommerce MCP custom abilities with execute_callback handlers for payment operations. woocommerce_mcp_include_ability filter for registration.
Safety: All financial write operations require explicit merchant approval. Implement a queue-and-confirm pattern: agent proposes action, merchant reviews and approves in admin.
3.6 Smart Payment Analytics via WP AI Client
Build an AI-powered payment analytics dashboard that uses wp_ai_client_prompt() to generate natural-language insights from transaction data. Merchants ask questions like “Why did my revenue drop last week?” or “Which payment methods have the highest abandonment?” and get contextual, data-driven answers.
Technical basis: WP AI Client with structured JSON responses, WooCommerce analytics data as context, Connectors API for AI provider selection.
Performance: Leverage REST API caching engine (WooCommerce 10.5+) for real-time data access without admin slowdown.

Phase 3: Orchestration (October–December 2026)
Leverage the MCP Client capability to make WordPress the central orchestration hub.
3.7 WordPress as Payment Orchestration Hub
When WordPress ships MCP Client capability, WordPress can ingest capabilities from external MCP servers. Build a payment orchestration layer where WordPress connects to payment provider MCP servers (Stripe, PayPal, Square, etc.) and exposes unified payment abilities. This turns WordPress into the central control plane for multi-gateway payment operations.
Technical basis: WordPress MCP Client (proposed for 7.1 or 7.2), payment provider MCP server adapters, unified ability namespace.
Strategic value: This is the architectural role change. WordPress goes from hosting a payment plugin to orchestrating the entire payment stack via AI agents.
3.8 Cross-Platform PSP Migration with AI
Extend the PSP Migration plugin to use the MCP Client capability for direct, API-level migration between payment providers. Instead of manual export/import, an AI agent orchestrates the migration: reads from the source provider’s MCP server, transforms data, and writes to the destination. Merchant reviews and approves each batch.
Technical basis: MCP Client for source/destination provider communication, existing PSP migration adapters as fallback, AI advisor for migration planning.
3.9 Fraud Detection AI Integration
Connect the WooCommerce Fraud Protection infrastructure (currently building in core) with AI-powered risk scoring. Use the WP AI Client to analyze transaction patterns and flag anomalies. Integrate with the CIAB admin’s fraud/risk display (currently shipping in WooPayments order page integration) to surface AI-generated risk assessments.
Technical basis: WP AI Client for pattern analysis, WooCommerce Fraud Protection infrastructure (Chronos team), CIAB order management integration.
Depends on: Core Payments Fraud Prevention MVP (PRD published Dec 2025), Jetpack connection integration.

Phase 4: Intelligence (2027+)
Longer-horizon features that build on a mature AI platform layer.
3.10 Autonomous Payment Optimization
An AI agent that continuously optimizes payment configuration: routing transactions to the lowest-cost gateway, dynamically enabling/disabling payment methods by geography, and adjusting fraud thresholds based on real-time risk signals. Operates within merchant-defined guardrails.
3.11 Predictive Revenue Intelligence
Use transaction history and AI to forecast revenue, predict churn risk for subscribers, and recommend pricing changes. Surfaces insights proactively in the admin dashboard and via AI agent conversations.
3.12 Multi-Store AI Agent Coordination
For merchants running multiple WooCommerce stores (or agencies managing many stores), enable a single AI agent to coordinate payment operations across all stores via MCP. Consolidated reporting, cross-store fraud detection, and unified reconciliation.

4. Timeline Overview
Phase
Timeline
Key Deliverables
Dependencies
Phase 1: Foundation
Apr–Jun 2026
Payment Abilities, PSP AI Advisor, Connectors integration
WordPress 7.0 launch (Apr 9)
Phase 2: Agentic Commerce
Jul–Sep 2026
WooPayments ACP, AI store management, smart analytics
ACP readiness, WC 10.5 caching
Phase 3: Orchestration
Oct–Dec 2026
Payment orchestration hub, AI migration, fraud AI
WP 7.1/7.2 MCP Client, Fraud MVP
Phase 4: Intelligence
2027+
Auto-optimization, predictive revenue, multi-store
Mature AI platform layer

5. Application: Haanpaa Martial Arts Stack
The proposed Haanpaa stack (WooCommerce + WooPayments + Jetpack CRM + AutomateWoo + LibreChat) is uniquely positioned to benefit from these platform changes. Specific opportunities:
AI-Powered Member Portal: The WP AI Client enables the LibreChat AI agents (Sales, Coaching, Finance, Admin) to call wp_ai_client_prompt() natively, eliminating the need for a separate AI orchestration layer for WordPress-specific operations.
WooPayments + ACP: If Haanpaa sells merchandise, classes, or memberships, ACP integration means their products become discoverable via ChatGPT and other AI assistants. A local martial arts school showing up in AI-powered product search is a significant competitive advantage.
Payment Abilities for Agents: The named AI agents (Finance, Admin) can use payment abilities via MCP to query transaction status, generate financial reports, and flag subscription payment failures, all through natural language.
Connectors API for CRM: Jetpack CRM and AutomateWoo can register as Connectors, creating a unified admin experience for managing all external service connections alongside payment providers.
Gamification + AI: The gamification engine (badges, streaks, milestone notifications) can use wp_ai_client_prompt() to generate personalized encouragement messages and training recommendations based on attendance patterns.

6. Immediate Recommendations
Start building on the WooCommerce MCP today. It’s in developer preview and the API may shift, but the architecture is stable enough to prototype payment-specific abilities. Use the woocommerce/wc-mcp-ability demo plugin as a starting point.
Get a WordPress 7.0 beta environment running. Beta 2 is already out (Feb 2026). Test the Connectors API, WP AI Client, and Abilities API with payment-specific use cases before launch.
Engage with #woo-agentic-commerce. The ACP integration decisions (WooPayments as required gateway, 4% fee handling, product feed architecture) are actively being shaped. Payment Partnerships should have a voice in those decisions.
Track the MCP Client proposal. The shift from “WordPress as MCP server” to “WordPress as MCP client” is the biggest architectural opportunity for payment orchestration. When this lands (likely 7.1 or 7.2), we should be ready with payment provider MCP server adapters.
Coordinate with Chronos team on Fraud Protection. The Core Payments Fraud Prevention MVP is building infrastructure we’ll need for AI-powered fraud detection. Ensure the architecture supports AI model integration from the start.
Update the Haanpaa proposal. The platform changes strengthen the case for a WooCommerce-anchored stack significantly. The AI integration story is no longer speculative; it’s shipping in WordPress core in three weeks.

7. Sources
External
WordPress.org Roadmap: wordpress.org/about/roadmap/
WordPress 7.0 Beta 2: wordpress.org/news/2026/02/wordpress-7-0-beta-2/
Connectors API in WordPress 7.0: make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
WP AI Client SDK: make.wordpress.org/ai/2025/11/21/introducing-the-wordpress-ai-client-sdk/
WordPress MCP Adapter: developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/
WooCommerce AI & Agentic Commerce Roadmap: developer.woocommerce.com/2025/10/03/ai-agentic-commerce-in-woocommerce/
WooCommerce MCP Docs: developer.woocommerce.com/docs/features/mcp/
Stripe Agentic Commerce Suite: stripe.com/blog/agentic-commerce-suite
Stripe ACP Specification: docs.stripe.com/agentic-commerce/protocol
Automattic Acquires WPAI: automattic.com/2024/12/09/automattic-welcomes-wpai/
Automattic AI Enablement: automattic.com/2026/02/25/ai-enablement-wordpress/
Internal
A8C and Core AI Update P2: aip2.wordpress.com/2026/02/25/a8c-and-core-ai-update-open-questions-and-guidance/
#woo-agentic-commerce Slack: ACP status updates, OpenAI Product Feed alpha, payment token handling
#big-ocean Slack: James LePage on Features API, MCP Adapter, Universal Registry priorities
#vip-gtm-media Slack: Analysis of MCP Client as orchestration layer
Payments Engineering Summary (Dec 2025): CIAB, Fraud Protection, Stripe/Braintree releases
#commerce-in-a-box Slack: WooPayments order page integration, onboarding with AI research
Core Payments Fraud Prevention MVP PRD: woocommercep2.wordpress.com/2025/12/05/prd-core-payments-fraud-prevention-mvp/