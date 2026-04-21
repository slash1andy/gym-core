<?php
declare(strict_types=1);
/**
 * Notification dispatcher for new pending Gandalf actions.
 *
 * @package HMA_AI_Chat
 * @since   0.3.2
 */

namespace HMA_AI_Chat\Notifications;

/**
 * Fans out new-pending-action events across three channels: a WP admin notice
 * shown on every admin screen, an optional Slack incoming-webhook post, and
 * an optional Twilio SMS (delivered via gym-core's TwilioClient).
 *
 * Each channel is independently gated by a plugin option so a site can enable
 * only what it needs; all failures are logged rather than raised so one bad
 * destination can't block the others.
 *
 * @since 0.3.2
 */
class ActionNotifier {

	const OPTION_SLACK_WEBHOOK   = 'hma_ai_chat_slack_webhook_url';
	const OPTION_SMS_ADMIN_LIST  = 'hma_ai_chat_sms_admin_numbers';
	const OPTION_NOTIFY_ENABLED  = 'hma_ai_chat_notify_on_pending';

	/**
	 * Register the hook that drives all three channels.
	 */
	public function register(): void {
		add_action( 'hma_ai_chat_pending_action_created', array( $this, 'dispatch' ), 10, 4 );
	}

	/**
	 * Dispatch a new-pending-action event to every enabled channel.
	 *
	 * @param int    $action_id   Action row ID.
	 * @param string $agent       Agent slug (sales, coaching, finance, admin).
	 * @param string $action_type Action type identifier.
	 * @param array  $action_data Action data payload.
	 */
	public function dispatch( int $action_id, string $agent, string $action_type, array $action_data ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$summary = $this->build_summary( $action_data );

		$this->send_slack( $action_id, $agent, $action_type, $summary );
		$this->send_sms( $action_id, $agent, $action_type, $summary );
	}

	/**
	 * Whether the notifier is enabled. Defaults to true so sites get notifications
	 * out of the box; an admin can toggle off via settings.
	 */
	private function is_enabled(): bool {
		$value = get_option( self::OPTION_NOTIFY_ENABLED, '1' );
		return '0' !== (string) $value && false !== $value;
	}

	/**
	 * Build a one-line human summary from action data.
	 *
	 * Prefers an explicit 'description' field, then falls back to the action type.
	 */
	private function build_summary( array $action_data ): string {
		if ( ! empty( $action_data['description'] ) && is_string( $action_data['description'] ) ) {
			return wp_trim_words( (string) $action_data['description'], 20 );
		}
		return '';
	}

	/**
	 * POST a message to a Slack incoming webhook if configured.
	 *
	 * Failures are logged and swallowed so a broken webhook never blocks the
	 * other channels or the caller that created the action.
	 */
	private function send_slack( int $action_id, string $agent, string $action_type, string $summary ): void {
		$webhook_url = (string) get_option( self::OPTION_SLACK_WEBHOOK, '' );
		if ( '' === $webhook_url ) {
			return;
		}

		if ( ! wp_http_validate_url( $webhook_url ) ) {
			return;
		}

		$audit_url = admin_url( 'admin.php?page=hma-ai-chat-audit-log&action_status=pending' );
		$text      = sprintf(
			"*Gandalf action awaiting approval*\n• Agent: %s\n• Type: %s\n• ID: %d%s\n<%s|Open audit log>",
			$agent,
			$action_type,
			$action_id,
			'' !== $summary ? "\n• " . $summary : '',
			esc_url_raw( $audit_url )
		);

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'text' => $text ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'slack', $response->get_error_message() );
			return;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			$this->log_error( 'slack', 'HTTP ' . $status );
		}
	}

	/**
	 * Send an SMS via gym-core's TwilioClient to each configured admin number.
	 *
	 * Silently no-ops if gym-core isn't active (TwilioClient missing) or no
	 * recipients are configured. Each recipient is tried independently.
	 */
	private function send_sms( int $action_id, string $agent, string $action_type, string $summary ): void {
		$numbers = $this->get_admin_numbers();
		if ( empty( $numbers ) ) {
			return;
		}

		$twilio_class = '\\Gym_Core\\SMS\\TwilioClient';
		if ( ! class_exists( $twilio_class ) ) {
			return;
		}

		$body = sprintf(
			'Gandalf: %s action from %s agent awaiting approval (#%d)%s',
			$action_type,
			$agent,
			$action_id,
			'' !== $summary ? ' — ' . $summary : ''
		);
		$body = mb_substr( $body, 0, 320 );

		$client = new $twilio_class();
		foreach ( $numbers as $to ) {
			$result = $client->send( $to, $body );
			if ( empty( $result['success'] ) ) {
				$this->log_error(
					'sms',
					sprintf( 'to=%s err=%s', $to, (string) ( $result['error'] ?? 'unknown' ) )
				);
			}
		}
	}

	/**
	 * Read and normalize the admin SMS recipient list.
	 *
	 * Accepts array or newline/comma-separated string and trims blanks.
	 *
	 * @return string[]
	 */
	private function get_admin_numbers(): array {
		$raw = get_option( self::OPTION_SMS_ADMIN_LIST, array() );

		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\r\n,]+/', $raw ) ?: array();
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $number ) {
			$number = trim( (string) $number );
			if ( '' !== $number ) {
				$out[] = $number;
			}
		}
		return $out;
	}

	/**
	 * Log a notifier failure behind WP_DEBUG so errors are surfaced in dev but
	 * don't spam production logs.
	 */
	private function log_error( string $channel, string $detail ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'HMA AI Chat notifier (%s): %s', $channel, $detail ) );
		}
	}
}
