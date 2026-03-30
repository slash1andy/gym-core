# HMA AI Chat Plugin Setup Guide

## Overview

HMA AI Chat is a production-ready WordPress plugin that provides an integrated AI chat interface for Haanpaa Martial Arts staff. It connects to WordPress 7.0's WP AI Client to enable Claude-powered agent conversations for sales, coaching, finance, and administrative tasks.

## Project Structure

```
hma-ai-chat/
├── hma-ai-chat.php              # Main plugin bootstrap
├── uninstall.php                # Clean uninstall handler
├── readme.txt                   # WordPress plugin readme
├── composer.json                # PSR-4 autoloading
├── .gitignore                   # Git ignore file
├── src/
│   ├── Plugin.php               # Main plugin class — orchestrates all hooks
│   ├── Activator.php            # Plugin activation handler — creates tables
│   ├── Deactivator.php          # Plugin deactivation handler
│   ├── Admin/
│   │   └── ChatPage.php         # Admin menu page + asset enqueuing
│   ├── API/
│   │   ├── MessageEndpoint.php  # REST endpoint for chat messages
│   │   └── HeartbeatEndpoint.php # REST endpoint for Paperclip webhooks
│   ├── Agents/
│   │   ├── AgentRegistry.php    # Agent persona definitions and registry
│   │   └── AgentPersona.php     # Individual agent persona class
│   ├── Data/
│   │   ├── ConversationStore.php # Conversation CRUD operations
│   │   └── PendingActionStore.php # Pending action approval queue
│   └── Security/
│       └── WebhookValidator.php # Webhook signature and IP validation
└── assets/
    ├── js/
    │   └── chat-app.js          # Chat UI (vanilla JS, no build step)
    └── css/
        └── chat-app.css         # Chat panel styles
```

## Installation

1. Upload the `hma-ai-chat` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory (optional, for dev tools)
3. Activate the plugin in WordPress admin
4. Navigate to Tools > AI Chat to access the chat panel

## Requirements

- WordPress 7.0 or later
- PHP 8.0 or later
- WordPress 7.0 WP AI Client plugin (must be active)

## Database Setup

The plugin automatically creates three custom tables on activation:

### wp_hma_ai_conversations
Stores conversation metadata and history.

```sql
CREATE TABLE wp_hma_ai_conversations (
  id bigint(20) unsigned PRIMARY KEY AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  agent varchar(64) NOT NULL,
  title varchar(255),
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY user_id (user_id),
  KEY agent (agent),
  KEY created_at (created_at)
);
```

### wp_hma_ai_messages
Stores individual chat messages within conversations.

```sql
CREATE TABLE wp_hma_ai_messages (
  id bigint(20) unsigned PRIMARY KEY AUTO_INCREMENT,
  conversation_id bigint(20) unsigned NOT NULL,
  role varchar(20) NOT NULL,                    -- 'user' or 'assistant'
  content longtext NOT NULL,
  tokens_used int(11),
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES wp_hma_ai_conversations(id) ON DELETE CASCADE
);
```

### wp_hma_ai_pending_actions
Stores pending approvals from agents awaiting admin review.

```sql
CREATE TABLE wp_hma_ai_pending_actions (
  id bigint(20) unsigned PRIMARY KEY AUTO_INCREMENT,
  agent varchar(64) NOT NULL,
  action_type varchar(128) NOT NULL,
  action_data longtext NOT NULL,               -- JSON
  status varchar(20) DEFAULT 'pending',        -- pending, approved, rejected
  run_id varchar(255),                         -- Paperclip run ID
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  approved_at datetime,
  approved_by bigint(20) unsigned,
  KEY agent (agent),
  KEY status (status),
  KEY run_id (run_id)
);
```

## Agent Personas

The plugin includes four specialized agent personas:

### 1. Sales Agent
- **Icon**: 💼
- **Capability**: `edit_posts` (editors and higher)
- **Responsibilities**:
  - Membership and pricing inquiries
  - Class schedule information
  - Program recommendations
  - Lead qualification

### 2. Coaching Agent
- **Icon**: 🥋
- **Capability**: `edit_posts` (editors and higher)
- **Responsibilities**:
  - Training advice and technique guidance
  - Form correction
  - Workout planning
  - Student progression tracking

### 3. Finance Agent
- **Icon**: 💰
- **Capability**: `manage_options` (administrators only)
- **Responsibilities**:
  - Billing and invoicing
  - Financial reports
  - Revenue tracking
  - Accounts receivable management

### 4. Admin Agent
- **Icon**: ⚙️
- **Capability**: `manage_options` (administrators only)
- **Responsibilities**:
  - Staff scheduling
  - Policy enforcement
  - Operational coordination
  - Documentation

## Security Architecture

### Input Sanitization
- Text: `sanitize_text_field()`
- HTML: `wp_kses_post()`
- Integers: `absint()`
- URLs: `sanitize_url()`
- Keys: `sanitize_key()`

### Output Escaping
- HTML: `esc_html()`
- Attributes: `esc_attr()`
- URLs: `esc_url()`

### Database Security
- All queries use `$wpdb->prepare()` with placeholders
- Foreign key constraints enforce referential integrity
- Ownership verification before data modification

### REST API Security
- All endpoints require `permission_callback()`
- Never use `__return_true` for authenticated endpoints
- Nonce verification for state-changing operations

### Webhook Security
- Shared secret stored with `wp_hash()` comparison
- Constant-time comparison prevents timing attacks
- IP allowlist validation per environment
- Bearer token authentication

## REST API Endpoints

### POST /wp-json/hma-ai-chat/v1/message
Send a chat message and receive AI response.

**Authentication**: User must have `edit_posts` capability

**Request**:
```json
{
  "agent": "sales",
  "message": "What memberships do you offer?",
  "conversation_id": 123
}
```

**Response**:
```json
{
  "success": true,
  "response": "We offer three membership tiers...",
  "conversation_id": 123,
  "tokens_used": 245
}
```

### POST /wp-json/hma-ai-chat/v1/heartbeat
Webhook endpoint for Paperclip agent execution notifications.

**Authentication**: Webhook signature and IP allowlist validation

**Request**:
```json
{
  "runId": "run_123abc",
  "agentId": "sales",
  "taskId": "task_xyz",
  "wakeReason": "approval_needed"
}
```

**Response**:
```json
{
  "status": "pending_approval",
  "runId": "run_123abc"
}
```

## Paperclip Webhook Configuration

1. **Get the webhook secret**:
   - Check WordPress options table: `hma_ai_chat_webhook_secret`
   - Or rotate via admin settings to generate a new one

2. **Configure webhook in Paperclip**:
   - **URL**: `{your-site}/wp-json/hma-ai-chat/v1/heartbeat`
   - **Authorization Header**: `Bearer {webhook-secret}`
   - **IP Allowlist** (optional): Configure in `hma_ai_chat_ip_allowlist` option

3. **Webhook Payload**:
   - `runId`: Unique identifier for the agent execution
   - `agentId`: Maps to agent slug (sales, coaching, finance, admin)
   - `wakeReason`: approval_needed | execution_complete
   - `taskId`: Optional task identifier

## Hooks and Filters

### Actions

**hma_ai_chat_agents_registered**
Fires after default agents are registered. Use to add custom agents.

```php
add_action( 'hma_ai_chat_agents_registered', function ( $registry ) {
    $registry->register_agent( 'custom', new AgentPersona( ... ) );
} );
```

**hma_ai_chat_action_approved**
Fires when a pending action is approved.

```php
do_action( 'hma_ai_chat_action_approved', $action_id, $user_id );
```

**hma_ai_chat_action_rejected**
Fires when a pending action is rejected.

```php
do_action( 'hma_ai_chat_action_rejected', $action_id, $user_id, $reason );
```

**hma_ai_chat_execution_complete**
Fires when agent execution is marked complete.

```php
do_action( 'hma_ai_chat_execution_complete', $run_id, $agent, $task_id );
```

**hma_ai_chat_uninstall**
Fires when the plugin is uninstalled.

## Code Standards

This plugin adheres to:
- **WordPress Coding Standards** (as defined in wordpress-standards skill)
- **Security Best Practices** (input sanitization, output escaping, nonces)
- **PHP 8.0+** conventions
- **PSR-4 Autoloading** via Composer

## Development Workflow

### Local Development

1. Install dependencies:
   ```bash
   composer install
   ```

2. Run code analysis:
   ```bash
   composer run-script analyse
   ```

3. Follow WordPress standards for all changes

### File Naming Conventions
- **Classes**: `Upper_Snake_Case` (e.g., `Plugin.php`)
- **Functions**: `snake_case` (e.g., `hma_ai_chat_init()`)
- **Constants**: `UPPER_SNAKE_CASE` (e.g., `HMA_AI_CHAT_VERSION`)
- **Hooks**: `plugin_slug_hook_name` (e.g., `hma_ai_chat_agents_registered`)

## Extending the Plugin

### Adding a Custom Agent

```php
add_action( 'hma_ai_chat_agents_registered', function ( $registry ) {
    $registry->register_agent(
        'custom',
        new AgentPersona(
            'custom',
            'Custom Agent',
            'Description',
            'System prompt...',
            'edit_posts',
            '🎯'
        )
    );
} );
```

### Adding Custom API Endpoints

```php
add_action( 'rest_api_init', function () {
    register_rest_route( 'hma-ai-chat/v1', '/custom', array(
        'methods'             => 'POST',
        'callback'            => 'my_callback',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );
} );
```

## Troubleshooting

### WP AI Client Not Found
Ensure WordPress 7.0 is installed and the WP AI Client plugin is active.

### Webhook Not Receiving Events
1. Check webhook secret matches in Paperclip configuration
2. Verify IP allowlist includes Paperclip's IP address
3. Check WordPress error logs for failed requests

### Messages Not Saving
Check database tables exist and user has proper capabilities.

## Performance Considerations

- Conversations and messages are indexed on user_id, agent, and created_at
- Foreign key constraint ensures message deletion when conversation is deleted
- Message pagination recommended for conversations with many messages
- Webhook responses are async-friendly (202 Accepted for async tasks)

## License

GPL-2.0-or-later

## Contact

For questions or support: andrew@haanpaa.com
