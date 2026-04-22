<?php
declare(strict_types=1);
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
	 * Settings page instance.
	 *
	 * @var Admin\SettingsPage|null
	 */
	private $settings_page = null;

	/**
	 * Tool executor instance.
	 *
	 * @var Tools\ToolExecutor|null
	 */
	private $tool_executor = null;

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
		// Admin pages must hook into admin_menu (fires before admin_init).
		if ( is_admin() ) {
			$this->settings_page = new Admin\SettingsPage();
			$this->chat_page     = new Admin\ChatPage();

			add_action( 'admin_menu', array( $this->settings_page, 'register_menu_pages' ) );
			add_action( 'admin_init', array( $this->settings_page, 'register_settings' ) );
		}

		// Block login for agent user accounts.
		add_filter( 'authenticate', array( Agents\AgentUserManager::class, 'block_agent_login' ), 100, 2 );

		// Hide agent users from wp-admin Users list.
		add_action( 'pre_get_users', array( Agents\AgentUserManager::class, 'hide_from_user_list' ) );

		// Register agents on both admin and REST requests.
		add_action( 'admin_init', array( $this, 'register_hooks' ) );
		add_action( 'rest_api_init', array( $this, 'register_hooks' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Register tools as WordPress abilities for MCP discoverability.
		if ( function_exists( 'wp_register_ability' ) ) {
			add_action( 'wp_abilities_api_categories_init', array( MCP\AbilitiesRegistrar::class, 'register_category' ) );
			add_action( 'wp_abilities_api_init', array( MCP\AbilitiesRegistrar::class, 'register' ) );
		}

		// Schedule conversation retention purge (daily).
		add_action( self::PURGE_CRON_HOOK, array( $this, 'run_conversation_purge' ) );
		if ( ! wp_next_scheduled( self::PURGE_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::PURGE_CRON_HOOK );
		}

		// Wire the new-pending-action notifier (Slack + SMS channels).
		( new Notifications\ActionNotifier() )->register();
	}

	/**
	 * Register all plugin hooks.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public function register_hooks() {
		// Initialize agent registry.
		$agent_registry = Agents\AgentRegistry::instance();
		$agent_registry->register_all_agents();

		// Ensure agent user accounts exist and capabilities are current.
		Agents\AgentUserManager::provision();

		// Initialize tool layer.
		$tool_registry      = Tools\ToolRegistry::instance();
		$pending_store      = new Data\PendingActionStore();
		$this->tool_executor = new Tools\ToolExecutor( $tool_registry, $pending_store );
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
		$store          = new Data\ConversationStore();
		$retention_days = (int) get_option( 'hma_ai_chat_retention_days', 30 );
		$deleted        = $store->purge_expired_conversations( $retention_days );

		if ( $deleted > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'HMA AI Chat: Purged %d conversations older than %d days.', $deleted, $retention_days ) );
		}
	}

	/**
	 * Get the chat page instance.
	 *
	 * @since 0.2.0
	 *
	 * @return Admin\ChatPage|null
	 */
	public function get_chat_page(): ?Admin\ChatPage {
		return $this->chat_page;
	}

	/**
	 * Get the tool executor instance.
	 *
	 * Available after register_hooks() has run. Returns null if the AI
	 * client dependency is missing or hooks have not fired yet.
	 *
	 * @since 0.2.0
	 *
	 * @return Tools\ToolExecutor|null
	 */
	public function get_tool_executor(): ?Tools\ToolExecutor {
		return $this->tool_executor;
	}

	/**
	 * Get the tool registry instance.
	 *
	 * @since 0.2.0
	 *
	 * @return Tools\ToolRegistry
	 */
	public function get_tool_registry(): Tools\ToolRegistry {
		return Tools\ToolRegistry::instance();
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
		$allowed_pages = array( 'gym_page_hma-ai-chat', 'toplevel_page_gym-core' );
		if ( ! in_array( $hook_suffix, $allowed_pages, true ) ) {
			return;
		}

		if ( null === $this->chat_page ) {
			$this->chat_page = new Admin\ChatPage();
		}

		$this->chat_page->enqueue_assets();
	}

	/**
	 * Handle admin actions (e.g., secret rotation).
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function handle_admin_actions() {
		if ( null === $this->settings_page ) {
			$this->settings_page = new Admin\SettingsPage();
		}
		$this->settings_page->handle_secret_rotation();
	}

	/**
	 * Display admin notices from settings actions.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function display_admin_notices() {
		if ( null === $this->settings_page ) {
			$this->settings_page = new Admin\SettingsPage();
		}
		$this->settings_page->display_admin_notices();
	}
}
