<?php
declare(strict_types=1);
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

		// Chat is now served by StaffDashboard on the gym-core page.

		// Submenu: Audit Log (admin only). Show a pending-count bubble in the
		// menu label so staff can spot new approval asks without clicking in.
		$audit_label = esc_html__( 'Audit Log', 'hma-ai-chat' );
		$pending     = 0;
		if ( current_user_can( 'manage_options' ) ) {
			$pending = ( new \HMA_AI_Chat\Data\PendingActionStore() )->get_pending_count();
		}
		if ( $pending > 0 ) {
			$audit_label .= ' <span class="awaiting-mod">' . esc_html( (string) $pending ) . '</span>';
		}

		add_submenu_page(
			'gym-core',
			esc_html__( 'Audit Log', 'hma-ai-chat' ),
			$audit_label,
			'manage_options',
			AuditLogPage::PAGE_SLUG,
			array( new AuditLogPage(), 'render_page' )
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

		register_setting( self::OPTION_GROUP, \HMA_AI_Chat\Security\WebhookValidator::IP_ALLOWLIST_ENFORCE_KEY, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => false,
		) );

		// --- General section ---
		add_settings_section(
			'hma_ai_chat_general',
			esc_html__( 'General', 'hma-ai-chat' ),
			'__return_null',
			self::PAGE_SLUG . '-general'
		);

		register_setting( self::OPTION_GROUP, \HMA_AI_Chat\API\ClaudeClient::API_KEY_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		add_settings_field(
			'hma_ai_chat_anthropic_api_key',
			esc_html__( 'Anthropic API Key', 'hma-ai-chat' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG . '-general',
			'hma_ai_chat_general'
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

		// --- Notifications (new pending actions) ---
		register_setting(
			self::OPTION_GROUP,
			\HMA_AI_Chat\Notifications\ActionNotifier::OPTION_NOTIFY_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			\HMA_AI_Chat\Notifications\ActionNotifier::OPTION_SLACK_WEBHOOK,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_webhook_url' ),
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			\HMA_AI_Chat\Notifications\ActionNotifier::OPTION_SMS_ADMIN_LIST,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_sms_admin_numbers' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			\HMA_AI_Chat\Notifications\ActionNotifier::OPTION_INCLUDE_SUMMARY_SLACK,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_field(
			'hma_ai_chat_notify_enabled',
			esc_html__( 'Notify on pending action', 'hma-ai-chat' ),
			array( $this, 'render_notify_enabled_field' ),
			self::PAGE_SLUG . '-general',
			'hma_ai_chat_general'
		);

		add_settings_field(
			'hma_ai_chat_slack_webhook_url',
			esc_html__( 'Slack incoming webhook URL', 'hma-ai-chat' ),
			array( $this, 'render_slack_webhook_field' ),
			self::PAGE_SLUG . '-general',
			'hma_ai_chat_general'
		);

		add_settings_field(
			'hma_ai_chat_sms_admin_numbers',
			esc_html__( 'SMS admin numbers (one per line)', 'hma-ai-chat' ),
			array( $this, 'render_sms_admin_numbers_field' ),
			self::PAGE_SLUG . '-general',
			'hma_ai_chat_general'
		);

		add_settings_field(
			'hma_ai_chat_notify_include_summary',
			esc_html__( 'Include action summary in Slack', 'hma-ai-chat' ),
			array( $this, 'render_include_summary_field' ),
			self::PAGE_SLUG . '-general',
			'hma_ai_chat_general'
		);
	}

	/**
	 * Render the "include summary in Slack" toggle.
	 *
	 * Action descriptions can carry PII (member names, refund reasons, phone
	 * fragments). Default off — operator opts in only when their Slack
	 * workspace is appropriately scoped.
	 *
	 * @since 0.4.1
	 * @internal
	 */
	public function render_include_summary_field() {
		$enabled = (bool) get_option( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_INCLUDE_SUMMARY_SLACK, false );
		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_INCLUDE_SUMMARY_SLACK ),
			checked( $enabled, true, false ),
			esc_html__( 'Append the action description to Slack notifications.', 'hma-ai-chat' )
		);
		echo '<p class="description">' . esc_html__( 'Off by default. Action descriptions can include member names, refund reasons, or other PII. SMS bodies are always metadata-only regardless of this setting.', 'hma-ai-chat' ) . '</p>';
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
			<h1><?php esc_html_e( 'Gandalf Settings', 'hma-ai-chat' ); ?></h1>

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
		$enforce   = $validator->is_ip_allowlist_enforced();
		$ips_value = implode( "\n", $allowlist );

		// Warn when the current configuration falls open.
		if ( empty( $allowlist ) && ! $enforce ) {
			echo '<div class="notice notice-warning inline"><p>';
			echo '<strong>' . esc_html__( 'Heads up:', 'hma-ai-chat' ) . '</strong> ';
			esc_html_e( 'The IP allowlist is empty and enforcement is off, so the webhook accepts any IP. Add Paperclip\'s egress IPs below and turn on "Enforce IP allowlist", or just turn enforcement on if you intend to deny all webhook traffic.', 'hma-ai-chat' );
			echo '</p></div>';
		}

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
			esc_attr__( 'One IP per line. Pre-seed Paperclip\'s production egress IPs here.', 'hma-ai-chat' ),
			esc_textarea( $ips_value )
		);
		echo '<p class="description">' . esc_html__( 'Restrict webhook access to these IP addresses. One per line. With enforcement on (below), an empty list denies all webhook traffic.', 'hma-ai-chat' ) . '</p></td>';
		echo '</tr>';

		// Enforce IP allowlist toggle (fail closed when empty).
		echo '<tr>';
		echo '<th scope="row"><label for="hma_ai_chat_ip_allowlist_enforce">' . esc_html__( 'Enforce IP allowlist', 'hma-ai-chat' ) . '</label></th>';
		printf(
			'<td><label><input type="checkbox" id="hma_ai_chat_ip_allowlist_enforce" name="%s" value="1" %s /> %s</label>',
			esc_attr( \HMA_AI_Chat\Security\WebhookValidator::IP_ALLOWLIST_ENFORCE_KEY ),
			checked( $enforce, true, false ),
			esc_html__( 'Fail closed when the allowlist is empty (recommended).', 'hma-ai-chat' )
		);
		echo '<p class="description">' . esc_html__( 'When on, an empty allowlist blocks all webhook requests. When off, an empty allowlist accepts any IP (signature-only auth).', 'hma-ai-chat' ) . '</p></td>';
		echo '</tr>';

		echo '</tbody></table>';
	}

	/**
	 * Render Anthropic API key field.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function render_api_key_field() {
		$has_constant = defined( 'HMA_AI_CHAT_ANTHROPIC_API_KEY' ) && '' !== HMA_AI_CHAT_ANTHROPIC_API_KEY;
		$has_wp_ai    = function_exists( 'wp_ai_client_prompt' );

		if ( $has_constant ) {
			echo '<input type="text" class="regular-text" value="' . esc_attr__( 'Configured via wp-config.php', 'hma-ai-chat' ) . '" disabled="disabled" />';
			echo '<p class="description">' . esc_html__( 'The API key is defined as a constant and cannot be changed here.', 'hma-ai-chat' ) . '</p>';
			return;
		}

		$key = get_option( \HMA_AI_Chat\API\ClaudeClient::API_KEY_OPTION, '' );

		if ( '' !== $key ) {
			printf(
				'<input type="password" id="hma_ai_chat_anthropic_api_key" name="%s" value="" class="regular-text" autocomplete="off" placeholder="%s" />',
				esc_attr( \HMA_AI_Chat\API\ClaudeClient::API_KEY_OPTION ),
				esc_attr__( 'Key is saved (enter new value to change)', 'hma-ai-chat' )
			);
		} else {
			printf(
				'<input type="password" id="hma_ai_chat_anthropic_api_key" name="%s" value="" class="regular-text" autocomplete="off" />',
				esc_attr( \HMA_AI_Chat\API\ClaudeClient::API_KEY_OPTION )
			);
		}

		if ( $has_wp_ai ) {
			echo '<p class="description">' . esc_html__( 'WordPress AI Client is active. This key is only used as a fallback.', 'hma-ai-chat' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Required. Used to call the Claude API directly. Get a key at console.anthropic.com.', 'hma-ai-chat' ) . '</p>';
		}
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
			esc_attr( (string) $days )
		);
		echo '<p class="description">' . esc_html__( 'Conversations older than this many days are automatically purged.', 'hma-ai-chat' ) . '</p>';
	}

	/**
	 * Render the notify-on-pending toggle.
	 *
	 * @since 0.3.2
	 * @internal
	 */
	public function render_notify_enabled_field() {
		$enabled = (bool) get_option( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_NOTIFY_ENABLED, true );
		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_NOTIFY_ENABLED ),
			checked( $enabled, true, false ),
			esc_html__( 'Send Slack / SMS notifications when Gandalf queues an action', 'hma-ai-chat' )
		);
		echo '<p class="description">' . esc_html__( 'The in-admin notice is always shown to admins; this toggle only controls the external channels below.', 'hma-ai-chat' ) . '</p>';
	}

	/**
	 * Render the Slack webhook URL field.
	 *
	 * @since 0.3.2
	 * @internal
	 */
	public function render_slack_webhook_field() {
		$value = (string) get_option( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_SLACK_WEBHOOK, '' );
		printf(
			'<input type="url" name="%s" value="%s" class="regular-text" placeholder="https://hooks.slack.com/services/..." />',
			esc_attr( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_SLACK_WEBHOOK ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Leave blank to disable Slack notifications. Paste the incoming-webhook URL from your Slack app.', 'hma-ai-chat' ) . '</p>';
	}

	/**
	 * Render the SMS admin numbers field.
	 *
	 * @since 0.3.2
	 * @internal
	 */
	public function render_sms_admin_numbers_field() {
		$value = get_option( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_SMS_ADMIN_LIST, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}
		printf(
			'<textarea name="%s" rows="4" cols="40" class="large-text code" placeholder="+13125551234&#10;+13125555678">%s</textarea>',
			esc_attr( \HMA_AI_Chat\Notifications\ActionNotifier::OPTION_SMS_ADMIN_LIST ),
			esc_textarea( implode( "\n", $value ) )
		);
		echo '<p class="description">' . esc_html__( 'One E.164 number per line (e.g., +13125551234). Requires gym-core\'s Twilio credentials to be configured. Leave blank to disable SMS.', 'hma-ai-chat' ) . '</p>';
	}

	/**
	 * Normalize a checkbox submission to boolean.
	 *
	 * @since 0.3.2
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public function sanitize_checkbox( $value ): bool {
		return ! empty( $value );
	}

	/**
	 * Sanitize a Slack incoming webhook URL. Rejects non-HTTPS or non-Slack-looking
	 * URLs rather than silently accepting them, so staff get immediate feedback.
	 *
	 * @since 0.3.2
	 *
	 * @param mixed $value Submitted URL.
	 * @return string
	 */
	public function sanitize_webhook_url( $value ): string {
		$url = esc_url_raw( trim( (string) $value ) );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 !== stripos( $url, 'https://' ) ) {
			add_settings_error(
				\HMA_AI_Chat\Notifications\ActionNotifier::OPTION_SLACK_WEBHOOK,
				'hma_slack_webhook_insecure',
				__( 'Slack webhook URL must use HTTPS.', 'hma-ai-chat' )
			);
			return '';
		}
		return $url;
	}

	/**
	 * Parse SMS admin numbers from a textarea (newline or comma delimited) or an
	 * array. Strips formatting and keeps only digits + leading '+'.
	 *
	 * @since 0.3.2
	 *
	 * @param mixed $value Submitted value.
	 * @return string[]
	 */
	public function sanitize_sms_admin_numbers( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n,]+/', $value ) ?: array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			// Keep leading '+' and digits only.
			$clean = preg_replace( '/[^\d+]/', '', $raw );
			if ( null === $clean || '' === $clean ) {
				continue;
			}
			if ( strlen( $clean ) < 8 ) {
				continue;
			}
			$out[] = $clean;
		}
		return array_values( array_unique( $out ) );
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
	 * Shows pending action alerts on all admin pages and
	 * settings-specific notices on the settings page.
	 *
	 * @since 0.2.0
	 * @internal
	 */
	public function display_admin_notices() {
		// Pending actions notice — shown on all admin pages for admins.
		if ( current_user_can( 'manage_options' ) ) {
			$store         = new \HMA_AI_Chat\Data\PendingActionStore();
			$pending_count = $store->get_pending_count();

			if ( $pending_count > 0 ) {
				$chat_url = admin_url( 'admin.php?page=gym-core' );
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					sprintf(
						/* translators: 1: pending count, 2: link open tag, 3: link close tag */
						esc_html( _n(
							'Gandalf has %1$d action awaiting your approval. %2$sReview now%3$s',
							'Gandalf has %1$d actions awaiting your approval. %2$sReview now%3$s',
							$pending_count,
							'hma-ai-chat'
						) ),
						$pending_count,
						'<a href="' . esc_url( $chat_url ) . '">',
						'</a>'
					)
				);
			}
		}

		// Settings page-specific notices below.
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
					return false !== filter_var( sanitize_text_field( $ip ), FILTER_VALIDATE_IP );
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
