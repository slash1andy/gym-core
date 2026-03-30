=== HMA AI Chat ===
Contributors: andrewwikel
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: ai, chat, agent, claude, haanpaa
Requires at least: 7.0
Requires PHP: 8.0
Tested up to: 7.0
Stable tag: 0.1.0

AI-powered chat interface for Haanpaa Martial Arts staff, built on WordPress 7.0 AI Client.

== Description ==

HMA AI Chat provides an integrated AI chat panel in the WordPress admin dashboard for Haanpaa Martial Arts staff. The plugin connects to WordPress 7.0's WP AI Client to enable Claude-powered agent conversations for sales, coaching, finance, and administrative tasks.

**Features:**

* Four specialized agent personas: Sales, Coaching, Finance, and Admin
* Secure REST API endpoints with capability-based access control
* Conversation history and message persistence
* Pending action approval queue for admin oversight
* Paperclip webhook integration for agent execution
* IP allowlist and webhook signature validation
* Role-based agent availability

== Requirements ==

* WordPress 7.0 or later
* PHP 8.0 or later
* WordPress 7.0 WP AI Client plugin (active)

== Installation ==

1. Upload the `hma-ai-chat` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the Plugins menu in WordPress
3. Navigate to Tools > AI Chat to access the chat panel
4. Select an agent and start chatting

== Configuration ==

**Webhook Setup (for Paperclip integration):**

1. Go to the plugin settings to retrieve the webhook secret
2. Configure your Paperclip agent to POST to: `{site-url}/wp-json/hma-ai-chat/v1/heartbeat`
3. Set the Authorization header to: `Bearer {webhook-secret}`
4. Optionally configure IP allowlist in plugin settings

== Agent Capabilities ==

**Sales Agent** (available to editors and higher)
- Answers membership and pricing inquiries
- Explains class schedules and programs
- Qualifies leads and suggests programs

**Coaching Agent** (available to editors and higher)
- Provides training advice and technique guidance
- Helps with form correction
- Creates personalized workout plans

**Finance Agent** (administrators only)
- Manages billing and invoicing
- Generates financial reports
- Tracks revenue and accounts receivable

**Admin Agent** (administrators only)
- Manages staff scheduling
- Enforces policies and procedures
- Coordinates operations

== Security ==

The plugin implements comprehensive security measures:

* All REST endpoints require `current_user_can()` permission checks
* Input sanitization via `sanitize_text_field()`, `wp_kses_post()`, and `absint()`
* Output escaping via `esc_html()`, `esc_attr()`, `esc_url()`
* Prepared database queries with `$wpdb->prepare()`
* Webhook signature validation with constant-time comparison
* IP allowlist validation for webhook requests
* Capability-based access control for agents
* Nonce verification for admin form submissions

== Database ==

The plugin creates three custom tables:

* `wp_hma_ai_conversations` - Stores conversation metadata
* `wp_hma_ai_messages` - Stores individual chat messages
* `wp_hma_ai_pending_actions` - Stores pending approvals from agents

Tables are automatically created on plugin activation and removed on uninstallation.

== Changelog ==

= 0.1.0 =
* Initial release
* Four agent personas (Sales, Coaching, Finance, Admin)
* Chat interface in WordPress admin
* REST API endpoints for messages and webhooks
* Pending action approval queue
* Conversation persistence

== Support ==

For issues, feature requests, or questions, please contact: andrew@haanpaa.com
