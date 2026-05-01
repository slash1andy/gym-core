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

	const OPTION_SLACK_WEBHOOK         = 'hma_ai_chat_slack_webhook_url';
	const OPTION_SMS_ADMIN_LIST        = 'hma_ai_chat_sms_admin_numbers';
	const OPTION_NOTIFY_ENABLED        = 'hma_ai_chat_notify_on_pending';
	const OPTION_INCLUDE_SUMMARY_SLACK = 'hma_ai_chat_notify_include_summary';

	/**
	 * Async dispatch hook for SMS sends. The synchronous dispatch path enqueues
	 * one of these per recipient via Action Scheduler (or wp_schedule_single_event
	 * fallback) so a Twilio outage can't add 15-75s to whatever request created
	 * the pending action.
	 *
	 * @since 0.4.1
	 */
	const ASYNC_SMS_HOOK = 'hma_ai_chat_send_sms_async';

	/**
	 * Register the hook that drives all three channels.
	 */
	public function register(): void {
		add_action( 'hma_ai_chat_pending_action_created', array( $this, 'dispatch' ), 10, 4 );
		add_action( self::ASYNC_SMS_HOOK, array( $this, 'send_sms_to_one' ), 10, 4 );
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
		$this->enqueue_sms( $action_id, $agent, $action_type );
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
	 * Sites that need to redact further should hook the
	 * `hma_ai_chat_notifier_summary` filter.
	 */
	private function build_summary( array $action_data ): string {
		$summary = '';
		if ( ! empty( $action_data['description'] ) && is_string( $action_data['description'] ) ) {
			$summary = wp_trim_words( (string) $action_data['description'], 20 );
		}

		/**
		 * Filter the human-readable action summary used in notifications.
		 *
		 * Use this to scrub PII (member names, phone fragments, refund reasons)
		 * before it reaches Slack. SMS bodies never include the summary — only
		 * Slack does, and only when the include-summary option is enabled.
		 *
		 * @since 0.4.1
		 *
		 * @param string $summary     Default summary (description trimmed to 20 words).
		 * @param array  $action_data Raw action data payload.
		 */
		return (string) apply_filters( 'hma_ai_chat_notifier_summary', $summary, $action_data );
	}

	/**
	 * Whether the Slack post should include the action summary.
	 *
	 * Defaults false so summaries (which can carry member names, refund reasons,
	 * etc.) don't leave the site without an explicit operator opt-in.
	 *
	 * @since 0.4.1
	 */
	private function include_summary_in_slack(): bool {
		return (bool) get_option( self::OPTION_INCLUDE_SUMMARY_SLACK, false );
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

		// Rate limit: max 10 Slack posts per 5 minutes to avoid webhook flooding.
		$rl_key   = 'hma_ai_chat_slack_rl';
		$rl_count = (int) get_transient( $rl_key );
		if ( $rl_count >= 10 ) {
			$this->log_error( 'slack', 'rate limit reached — skipping Slack notification' );
			return;
		}
		set_transient( $rl_key, $rl_count + 1, 5 * MINUTE_IN_SECONDS );

		$audit_url    = admin_url( 'admin.php?page=hma-ai-chat-audit-log&action_status=pending' );
		$summary_line = ( '' !== $summary && $this->include_summary_in_slack() ) ? "\n• " . $summary : '';
		$text         = sprintf(
			"*Gandalf action awaiting approval*\n• Agent: %s\n• Type: %s\n• ID: %d%s\n<%s|Open audit log>",
			$agent,
			$action_type,
			$action_id,
			$summary_line,
			esc_url_raw( $audit_url )
		);

		$body = wp_json_encode( array( 'text' => $text ) );
		if ( false === $body ) {
			$this->log_error( 'slack', 'wp_json_encode failed for Slack payload' );
			return;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => $body,
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
	 * Queue one async SMS per admin number.
	 *
	 * Twilio's HTTP client is synchronous with a 15s per-call timeout, so a
	 * Twilio outage with N recipients adds N × 15s to whatever request created
	 * the pending action. Defer to Action Scheduler when available, falling
	 * back to wp_schedule_single_event so installs without AS still degrade
	 * gracefully (just at single-cron cadence).
	 *
	 * The body is metadata-only on purpose — see {@see build_sms_body()}.
	 *
	 * @since 0.4.1
	 */
	private function enqueue_sms( int $action_id, string $agent, string $action_type ): void {
		$numbers = $this->get_admin_numbers();
		if ( empty( $numbers ) ) {
			return;
		}

		foreach ( $numbers as $to ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action(
					self::ASYNC_SMS_HOOK,
					array( $to, $action_id, $agent, $action_type ),
					'hma-ai-chat'
				);
			} else {
				wp_schedule_single_event(
					time(),
					self::ASYNC_SMS_HOOK,
					array( $to, $action_id, $agent, $action_type )
				);
			}
		}
	}

	/**
	 * Async SMS handler — fires on ASYNC_SMS_HOOK for one recipient at a time.
	 *
	 * Public so Action Scheduler / WP-Cron can call it.
	 *
	 * @since 0.4.1
	 *
	 * @param string $to          E.164 phone number.
	 * @param int    $action_id   Action row ID.
	 * @param string $agent       Agent slug.
	 * @param string $action_type Action type identifier.
	 */
	public function send_sms_to_one( string $to, int $action_id, string $agent, string $action_type ): void {
		$twilio_class = '\\Gym_Core\\SMS\\TwilioClient';
		if ( ! class_exists( $twilio_class ) ) {
			return;
		}

		// Rate limit: one SMS per phone number per minute to prevent duplicate sends.
		$rl_key = 'hma_ai_chat_sms_rl_' . md5( $to );
		if ( false !== get_transient( $rl_key ) ) {
			$this->log_error( 'sms', sprintf( 'rate limit — skipping SMS to %s', $to ) );
			return;
		}
		set_transient( $rl_key, 1, MINUTE_IN_SECONDS );

		$body = $this->build_sms_body( $action_id, $agent, $action_type );

		$client = new $twilio_class();
		$result = $client->send( $to, $body );
		if ( empty( $result['success'] ) ) {
			$this->log_error(
				'sms',
				sprintf( 'to=%s err=%s', $to, (string) ( $result['error'] ?? 'unknown' ) )
			);
		}
	}

	/**
	 * Build the SMS body — metadata-only, never the action description.
	 *
	 * SMS is third-party (Twilio), not end-to-end encrypted, and unrecoverable
	 * once sent. The body intentionally carries only routing metadata so a
	 * member name, phone fragment, or refund reason in the action description
	 * never leaves the site over SMS.
	 *
	 * @since 0.4.1
	 */
	private function build_sms_body( int $action_id, string $agent, string $action_type ): string {
		$body = sprintf(
			'Gandalf: %s queued %s #%d — review in admin',
			$agent,
			$action_type,
			$action_id
		);
		return mb_substr( $body, 0, 320 );
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
