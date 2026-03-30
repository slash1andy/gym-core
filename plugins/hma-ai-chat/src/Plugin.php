<?php
/**
 * Main plugin class.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat;

/**
 * Plugin class — orchestrates all plugin hooks and initialization.
 *
 * @since 0.1.0
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin chat page instance.
	 *
	 * @var Admin\ChatPage|null
	 */
	private $chat_page = null;

	/**
	 * Get plugin instance (singleton pattern for compatibility).
	 *
	 * @since 0.1.0
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cron hook name for conversation purge.
	 *
	 * @since 0.1.0
	 */
	const PURGE_CRON_HOOK = 'hma_ai_chat_purge_conversations';

	/**
	 * Initialize the plugin.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_hooks' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Schedule conversation retention purge (daily).
		add_action( self::PURGE_CRON_HOOK, array( $this, 'run_conversation_purge' ) );
		if ( ! wp_next_scheduled( self::PURGE_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::PURGE_CRON_HOOK );
		}
	}

	/**
	 * Register all plugin hooks.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public function register_hooks() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}

		// Initialize admin pages (single instance, reused in enqueue_admin_scripts).
		if ( is_admin() && null === $this->chat_page ) {
			$this->chat_page = new Admin\ChatPage();
		}

		// Initialize agent registry.
		$agent_registry = Agents\AgentRegistry::instance();
		$agent_registry->register_all_agents();
	}

	/**
	 * Register REST API routes.
	 *
	 * Called during rest_api_init — routes are registered directly here,
	 * not deferred to another hook.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public function register_rest_routes() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}

		$message_endpoint   = new API\MessageEndpoint();
		$heartbeat_endpoint = new API\HeartbeatEndpoint();
		$action_endpoint    = new API\ActionEndpoint();

		$message_endpoint->register_route();
		$heartbeat_endpoint->register_route();
		$action_endpoint->register_routes();
	}

	/**
	 * Run the conversation retention purge.
	 *
	 * Deletes conversations older than 30 days. Retention period is
	 * filterable via 'hma_ai_chat_retention_days'.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public function run_conversation_purge() {
		$store   = new Data\ConversationStore();
		$deleted = $store->purge_expired_conversations( 30 );

		if ( $deleted > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'HMA AI Chat: Purged %d conversations older than 30 days.', $deleted ) );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @internal
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'tools_page_hma-ai-chat' !== $hook_suffix ) {
			return;
		}

		if ( null === $this->chat_page ) {
			$this->chat_page = new Admin\ChatPage();
		}

		$this->chat_page->enqueue_assets();
	}
}
