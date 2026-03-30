<?php
/**
 * Admin chat page.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\Admin;

/**
 * Manages the AI Chat admin page.
 *
 * @since 0.1.0
 */
class ChatPage {

	/**
	 * Initialize the chat page.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
	}

	/**
	 * Register the admin menu page.
	 *
	 * @internal
	 */
	public function register_menu_page() {
		// Check user capability first.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		add_submenu_page(
			'tools.php',
			esc_html__( 'AI Chat', 'hma-ai-chat' ),
			esc_html__( 'AI Chat', 'hma-ai-chat' ),
			'edit_posts',
			'hma-ai-chat',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the chat page.
	 *
	 * @internal
	 */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'hma-ai-chat' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'HMA AI Chat', 'hma-ai-chat' ); ?></h1>
			<div id="hma-ai-chat-container" class="hma-ai-chat-panel">
				<!-- Chat app will be rendered here -->
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue chat assets.
	 *
	 * @internal
	 */
	public function enqueue_assets() {
		// Enqueue styles.
		wp_enqueue_style(
			'hma-ai-chat-styles',
			HMA_AI_CHAT_URL . 'assets/css/chat-app.css',
			array(),
			HMA_AI_CHAT_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'hma-ai-chat-app',
			HMA_AI_CHAT_URL . 'assets/js/chat-app.js',
			array( 'wp-api-fetch' ),
			HMA_AI_CHAT_VERSION,
			true
		);

		// Localize script data.
		wp_localize_script(
			'hma-ai-chat-app',
			'hmaAiChat',
			array(
				'apiUrl'       => rest_url( 'hma-ai-chat/v1/' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'userRole'     => $this->get_user_role(),
				'agents'       => $this->get_available_agents(),
				'currentUserId' => get_current_user_id(),
				'strings'      => $this->get_strings(),
			)
		);
	}

	/**
	 * Get current user's role.
	 *
	 * @return string
	 */
	private function get_user_role() {
		$user = wp_get_current_user();
		return ! empty( $user->roles ) ? $user->roles[0] : 'subscriber';
	}

	/**
	 * Get available agents for current user.
	 *
	 * @return array
	 */
	private function get_available_agents() {
		$registry = \HMA_AI_Chat\Agents\AgentRegistry::instance();
		$agents   = $registry->get_available_agents( get_current_user_id() );

		$available = array();
		foreach ( $agents as $agent ) {
			$available[] = array(
				'slug'        => $agent->get_slug(),
				'name'        => $agent->get_name(),
				'description' => $agent->get_description(),
				'icon'        => $agent->get_icon(),
			);
		}

		return $available;
	}

	/**
	 * Get translatable strings for the UI.
	 *
	 * @return array
	 */
	private function get_strings() {
		return array(
			'selectAgent'      => esc_html__( 'Select an agent', 'hma-ai-chat' ),
			'typingPlaceholder' => esc_html__( 'Type your message...', 'hma-ai-chat' ),
			'sendButton'        => esc_html__( 'Send', 'hma-ai-chat' ),
			'loadingMessage'    => esc_html__( 'Loading...', 'hma-ai-chat' ),
			'errorMessage'      => esc_html__( 'An error occurred. Please try again.', 'hma-ai-chat' ),
			'pendingActions'       => esc_html__( 'Pending Actions', 'hma-ai-chat' ),
			'approve'              => esc_html__( 'Approve', 'hma-ai-chat' ),
			'approveWithChanges'   => esc_html__( 'Approve with Changes', 'hma-ai-chat' ),
			'reject'               => esc_html__( 'Reject', 'hma-ai-chat' ),
			'changesPlaceholder'   => esc_html__( 'Describe the changes you want the agent to make...', 'hma-ai-chat' ),
			'rejectReasonPlaceholder' => esc_html__( 'Reason for rejection (optional)...', 'hma-ai-chat' ),
			'submitChanges'        => esc_html__( 'Submit Changes', 'hma-ai-chat' ),
			'submitRejection'      => esc_html__( 'Confirm Rejection', 'hma-ai-chat' ),
			'cancel'               => esc_html__( 'Cancel', 'hma-ai-chat' ),
			'actionApproved'       => esc_html__( 'Action approved. Executing now.', 'hma-ai-chat' ),
			'actionApprovedChanges' => esc_html__( 'Action approved with changes. Agent will revise and execute.', 'hma-ai-chat' ),
			'actionRejected'       => esc_html__( 'Action rejected and discarded.', 'hma-ai-chat' ),
		);
	}
}
