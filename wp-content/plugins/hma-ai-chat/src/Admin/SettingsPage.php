<?php
/**
 * Gandalf settings page.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\Admin;

/**
 * Manages the Gandalf settings admin page — agents, webhook/security, and general options.
 *
 * @since 0.2.0
 */
class SettingsPage {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'hma_ai_chat_settings';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'hma-ai-chat-settings';

	/**
	 * Initialize the settings page.
	 *
	 * Menu and settings hooks are registered by Plugin::init() directly,
	 * not here, because admin_menu fires before admin_init.
	 *
	 * @since 0.2.0
	 */
	public function __construct() {
		// Hooks are wired in Plugin::init().
	}

	/**
	 * Register the top-level Gandalf menu and submenu pages.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function register_menu_pages() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Register the shared top-level Gym menu if gym-core hasn't already.
		// WordPress silently ignores duplicate slugs, so this is safe.
		add_menu_page(
			esc_html__( 'Gym Dashboard', 'hma-ai-chat' ),
			esc_html__( 'Gym', 'hma-ai-chat' ),
			'edit_posts',
			'gym-core',
			'__return_null',
			'dashicons-awards',
			3
		);

		// Submenu: Gandalf Chat.
		add_submenu_page(
			'gym-core',
			esc_html__( 'Chat', 'hma-ai-chat' ),
			esc_html__( 'Gandalf', 'hma-ai-chat' ),
			'edit_posts',
			'hma-ai-chat',
			array( $this, 'render_chat_page' )
		);

		// Submenu: Settings (admin only).
		add_submenu_page(
			'gym-core',
			esc_html__( 'Settings', 'hma-ai-chat' ),
			esc_html__( 'Settings', 'hma-ai-chat' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the chat page.
	 *
	 * Delegates to ChatPage for the actual chat UI.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_chat_page() {
		\HMA_AI_Chat\Plugin::instance()->get_chat_page()->render_page();
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function register_settings() {
		// --- Agents section ---
		add_settings_section(
			'hma_ai_chat_agents',
			esc_html__( 'Agents', 'hma-ai-chat' ),
			array( $this, 'render_agents_section' ),
			self::PAGE_SLUG . '-agents'
		);

		register_setting( self::OPTION_GROUP, 'hma_ai_chat_agent_overrides', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_agent_overrides' ),
			'default'           => array(),
		) );

		// --- Webhook / Security section ---
		add_settings_section(
			'hma_ai_chat_webhook',
			esc_html__( 'Webhook & Security', 'hma-ai-chat' ),
			array( $this, 'render_webhook_section' ),
			self::PAGE_SLUG . '-webhook'
		);

		register_setting( self::OPTION_GROUP, \HMA_AI_Chat\Security\WebhookValidator::IP_ALLOWLIST_KEY, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_ip_allowlist' ),
			'default'           => array(),
		) );

		// --- General section ---
		add_settings_section(
			'hma_ai_chat_general',
			esc_html__( 'General', 'hma-ai-chat' ),
			'__return_null',
			self::PAGE_SLUG . '-general'
		);

		register_setting( self::OPTION_GROUP, 'hma_ai_chat_retention_days', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 30,
		) );

		add_settings_field(
			'hma_ai_chat_retention_days',
			esc_html__( 'Conversation retention (days)', 'hma-ai-chat' ),
			array( $this, 'render_retention_field' ),
			self::PAGE_SLUG . '-general',
			'hma_ai_chat_general'
		);
	}

	/**
	 * Render the settings page with tabs.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'hma-ai-chat' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'agents';
		$tabs       = array(
			'agents'  => esc_html__( 'Agents', 'hma-ai-chat' ),
			'webhook' => esc_html__( 'Webhook & Security', 'hma-ai-chat' ),
			'general' => esc_html__( 'General', 'hma-ai-chat' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gym Dashboard Settings', 'hma-ai-chat' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );

				switch ( $active_tab ) {
					case 'webhook':
						do_settings_sections( self::PAGE_SLUG . '-webhook' );
						$this->render_webhook_fields();
						break;

					case 'general':
						do_settings_sections( self::PAGE_SLUG . '-general' );
						break;

					default: // agents.
						do_settings_sections( self::PAGE_SLUG . '-agents' );
						$this->render_agent_fields();
						break;
				}

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Agents section description.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_agents_section() {
		echo '<p>' . esc_html__( 'Configure which agents are available and customize their display names, descriptions, and required capabilities.', 'hma-ai-chat' ) . '</p>';
	}

	/**
	 * Render agent configuration fields.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_agent_fields() {
		$registry  = \HMA_AI_Chat\Agents\AgentRegistry::instance();
		$overrides = get_option( 'hma_ai_chat_agent_overrides', array() );

		$agents = $registry->get_all_agents();

		if ( empty( $agents ) ) {
			echo '<p>' . esc_html__( 'No agents registered. Agents are registered during admin_init.', 'hma-ai-chat' ) . '</p>';
			return;
		}

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Agent', 'hma-ai-chat' ) . '</th>';
		echo '<th>' . esc_html__( 'Display Name', 'hma-ai-chat' ) . '</th>';
		echo '<th>' . esc_html__( 'Description', 'hma-ai-chat' ) . '</th>';
		echo '<th>' . esc_html__( 'Capability', 'hma-ai-chat' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'hma-ai-chat' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $agents as $slug => $agent ) {
			$override = $overrides[ $slug ] ?? array();
			$enabled  = ! isset( $override['enabled'] ) || $override['enabled'];
			$name     = $override['name'] ?? $agent->get_name();
			$desc     = $override['description'] ?? $agent->get_description();
			$cap      = $override['capability'] ?? $agent->get_required_capability();

			echo '<tr>';
			printf(
				'<td><strong>%s</strong> %s</td>',
				esc_html( $agent->get_icon() ),
				esc_html( $slug )
			);
			printf(
				'<td><input type="text" name="hma_ai_chat_agent_overrides[%s][name]" value="%s" class="regular-text" /></td>',
				esc_attr( $slug ),
				esc_attr( $name )
			);
			printf(
				'<td><input type="text" name="hma_ai_chat_agent_overrides[%s][description]" value="%s" class="regular-text" /></td>',
				esc_attr( $slug ),
				esc_attr( $desc )
			);
			printf(
				'<td><select name="hma_ai_chat_agent_overrides[%s][capability]">
					<option value="edit_posts" %s>%s</option>
					<option value="manage_options" %s>%s</option>
				</select></td>',
				esc_attr( $slug ),
				selected( $cap, 'edit_posts', false ),
				esc_html__( 'edit_posts (Editors+)', 'hma-ai-chat' ),
				selected( $cap, 'manage_options', false ),
				esc_html__( 'manage_options (Admins)', 'hma-ai-chat' )
			);
			printf(
				'<td><input type="checkbox" name="hma_ai_chat_agent_overrides[%s][enabled]" value="1" %s /></td>',
				esc_attr( $slug ),
				checked( $enabled, true, false )
			);
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the Webhook section description.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_webhook_section() {
		echo '<p>' . esc_html__( 'Configure Paperclip webhook integration and IP security.', 'hma-ai-chat' ) . '</p>';
	}

	/**
	 * Render webhook/security fields.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_webhook_fields() {
		$validator = new \HMA_AI_Chat\Security\WebhookValidator();
		$secret    = $validator->get_secret();
		$allowlist = $validator->get_ip_allowlist();
		$ips_value = implode( "\n", $allowlist );

		// Webhook secret (read-only display + rotate button).
		echo '<table class="form-table"><tbody>';

		echo '<tr>';
		echo '<th scope="row"><label>' . esc_html__( 'Webhook Secret', 'hma-ai-chat' ) . '</label></th>';
		echo '<td>';
		if ( $secret ) {
			echo '<code>' . esc_html( substr( $secret, 0, 8 ) ) . str_repeat( '&bull;', 24 ) . '</code>';
		} else {
			echo '<em>' . esc_html__( 'No secret configured.', 'hma-ai-chat' ) . '</em>';
		}
		echo '<p class="description">';
		printf(
			'<a href="%s" class="button button-secondary" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'webhook', 'action' => 'rotate_secret' ), admin_url( 'admin.php' ) ), 'hma_rotate_secret' ) ),
			esc_js( __( 'This will rotate the webhook secret. The old secret remains valid for 5 minutes. Continue?', 'hma-ai-chat' ) ),
			$secret ? esc_html__( 'Rotate Secret', 'hma-ai-chat' ) : esc_html__( 'Generate Secret', 'hma-ai-chat' )
		);
		echo '</p>';
		echo '</td>';
		echo '</tr>';

		// IP Allowlist.
		echo '<tr>';
		echo '<th scope="row"><label for="hma_ai_chat_ip_allowlist">' . esc_html__( 'IP Allowlist', 'hma-ai-chat' ) . '</label></th>';
		printf(
			'<td><textarea id="hma_ai_chat_ip_allowlist" name="%s" rows="5" class="large-text code" placeholder="%s">%s</textarea>',
			esc_attr( \HMA_AI_Chat\Security\WebhookValidator::IP_ALLOWLIST_KEY ),
			esc_attr__( 'One IP per line. Leave empty to allow all.', 'hma-ai-chat' ),
			esc_textarea( $ips_value )
		);
		echo '<p class="description">' . esc_html__( 'Restrict webhook access to these IP addresses. One per line. Leave empty to allow all (not recommended for production).', 'hma-ai-chat' ) . '</p></td>';
		echo '</tr>';

		echo '</tbody></table>';
	}

	/**
	 * Render conversation retention field.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_retention_field() {
		$days = (int) get_option( 'hma_ai_chat_retention_days', 30 );
		printf(
			'<input type="number" id="hma_ai_chat_retention_days" name="hma_ai_chat_retention_days" value="%d" min="1" max="365" class="small-text" />',
			esc_attr( $days )
		);
		echo '<p class="description">' . esc_html__( 'Conversations older than this many days are automatically purged.', 'hma-ai-chat' ) . '</p>';
	}

	/**
	 * Handle the secret rotation action.
	 *
	 * Called early via admin_init when the rotate_secret action is detected.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function handle_secret_rotation() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['action'] ) || 'rotate_secret' !== $_GET['action'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		check_admin_referer( 'hma_rotate_secret' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'hma-ai-chat' ) );
		}

		$validator  = new \HMA_AI_Chat\Security\WebhookValidator();
		$new_secret = $validator->rotate_secret();

		// Redirect back with a transient message.
		set_transient( 'hma_ai_chat_secret_rotated', $new_secret, 60 );

		wp_safe_redirect( add_query_arg(
			array( 'page' => self::PAGE_SLUG, 'tab' => 'webhook', 'rotated' => '1' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Display admin notices for this settings page.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function display_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rotated'] ) ) {
			$new_secret = get_transient( 'hma_ai_chat_secret_rotated' );
			if ( $new_secret ) {
				delete_transient( 'hma_ai_chat_secret_rotated' );
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p><p><code>%s</code></p><p>%s</p></div>',
					esc_html__( 'Webhook secret rotated. Copy the new secret below and update your Paperclip configuration within 5 minutes:', 'hma-ai-chat' ),
					esc_html( $new_secret ),
					esc_html__( 'The previous secret will remain valid for 5 minutes.', 'hma-ai-chat' )
				);
			}
		}
	}

	/**
	 * Sanitize agent override settings.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $input Raw input.
	 * @return array Sanitized overrides.
	 */
	public function sanitize_agent_overrides( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$clean      = array();
		$valid_caps = array( 'edit_posts', 'manage_options' );

		foreach ( $input as $slug => $override ) {
			$slug = sanitize_key( $slug );
			if ( empty( $slug ) || ! is_array( $override ) ) {
				continue;
			}

			$clean[ $slug ] = array(
				'name'        => sanitize_text_field( $override['name'] ?? '' ),
				'description' => sanitize_text_field( $override['description'] ?? '' ),
				'capability'  => in_array( $override['capability'] ?? '', $valid_caps, true )
					? $override['capability']
					: 'edit_posts',
				'enabled'     => ! empty( $override['enabled'] ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize IP allowlist from textarea (newline-separated).
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $input Raw input (string from textarea).
	 * @return array Valid IP addresses.
	 */
	public function sanitize_ip_allowlist( $input ) {
		if ( ! is_string( $input ) ) {
			if ( is_array( $input ) ) {
				return array_values( array_filter( $input, function ( $ip ) {
					return filter_var( sanitize_text_field( $ip ), FILTER_VALIDATE_IP );
				} ) );
			}
			return array();
		}

		$lines = array_filter( array_map( 'trim', explode( "\n", $input ) ) );
		$valid = array();

		foreach ( $lines as $ip ) {
			$ip = sanitize_text_field( $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$valid[] = $ip;
			}
		}

		return $valid;
	}
}
