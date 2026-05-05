<?php
/**
 * Funnel event logger for CRO instrumentation.
 *
 * Writes one row per event into {prefix}gym_funnel_log so we can attribute
 * leads back to their first page-view, source, and step path. Also forwards
 * to Jetpack Stats `record_event()` when available.
 *
 * Hook surface:
 *   - REST: POST /wp-json/gym/v1/funnel-event   (page-view, form-start, form-submit, confirmation)
 *   - PHP:  do_action( 'haanpaa/trial_submitted' ) → records `form-submit` server-side
 *
 * @package Gym_Core
 * @since   5.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Funnel;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Records funnel events into a custom table for CRO attribution.
 */
final class FunnelLogger {

	/**
	 * Allowed event names. Order matters — they describe the funnel.
	 *
	 * @var array<int,string>
	 */
	public const EVENTS = array(
		'page-view',
		'free-trial-page-view',
		'form-start',
		'form-submit',
		'confirmation',
	);

	/**
	 * Maximum length stored per text column (matches DDL).
	 *
	 * @var int
	 */
	private const MAX_TEXT_LEN = 191;

	/**
	 * Per-session event rate limit (events per minute).
	 *
	 * Public, no-auth endpoint, so capped to mitigate write amplification.
	 * 60/min is generous for legitimate funnel telemetry — a real user fires
	 * at most ~5–10 events on a single visit.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_PER_MIN = 60;

	/**
	 * Cookie name carrying the persistent funnel session id.
	 *
	 * @var string
	 */
	public const SESSION_COOKIE = 'gym_funnel_session';

	/**
	 * Cookie name written by Phase 1 §F (lead-source intake).
	 *
	 * Read tolerantly — if Phase 1 §F is not yet merged, the cookie won't
	 * exist and we record events with an empty lead_source.
	 *
	 * @var string
	 */
	public const LEAD_SOURCE_COOKIE = 'gym_core_lead_sources';

	/**
	 * Returns the funnel-log table name with the current site prefix.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'gym_funnel_log';
	}

	/**
	 * Registers REST and action hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'haanpaa/trial_submitted', array( $this, 'on_trial_submitted' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );
	}

	/**
	 * Enqueues the funnel-tracker browser script on every front-end page.
	 *
	 * The tracker self-fires page-view + free-trial-page-view on
	 * DOMContentLoaded and wires form-start / form-submit on the trial
	 * wizard if present. Sitewide enqueue is intentional so the homepage
	 * (front-page.html) gets telemetry without any template change.
	 *
	 * @return void
	 */
	public function enqueue_tracker(): void {
		if ( is_admin() ) {
			return;
		}

		// Skip on the kiosk page — it's not part of the public marketing funnel.
		if ( get_query_var( 'gym_kiosk' ) ) {
			return;
		}

		$handle = 'gym-funnel-tracker';

		wp_enqueue_script(
			$handle,
			GYM_CORE_URL . 'assets/js/funnel-tracker.js',
			array(),
			GYM_CORE_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'gymFunnel',
			array(
				'endpoint' => esc_url_raw( rest_url( 'gym/v1/funnel-event' ) ),
			)
		);
	}

	/**
	 * Registers the funnel-event REST route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'gym/v1',
			'/funnel-event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_event' ),
				'permission_callback' => array( $this, 'rate_limit' ),
				'args'                => array(
					'event'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session_id'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_url'    => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'lead_source' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'metadata'    => array(
						'required' => false,
						'type'     => 'object',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the funnel-event REST route.
	 *
	 * Endpoint is public (`__return_true` would be a write amplifier), but we
	 * cap each session_id at RATE_LIMIT_PER_MIN events per rolling 60s window
	 * via a transient counter. Excess requests are rejected with 429.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return true|WP_Error
	 */
	public function rate_limit( WP_REST_Request $request ) {
		$session_id = (string) $request->get_param( 'session_id' );
		$session_id = sanitize_text_field( $session_id );

		if ( '' === $session_id ) {
			return new WP_Error(
				'gym_funnel_missing_session',
				__( 'Missing session id.', 'gym-core' ),
				array( 'status' => 400 )
			);
		}

		$key   = 'gym_funnel_rl_' . md5( $session_id );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_PER_MIN ) {
			return new WP_Error(
				'gym_funnel_rate_limited',
				__( 'Too many funnel events from this session.', 'gym-core' ),
				array( 'status' => 429 )
			);
		}

		// Bucket resets after 60s of inactivity. We re-set on every increment
		// so a steady stream rolls forward, but stale sessions are gc'd.
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * REST handler for funnel events posted from the browser.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_event( WP_REST_Request $request ) {
		$event = (string) $request->get_param( 'event' );

		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return new WP_Error(
				'gym_funnel_invalid_event',
				__( 'Unknown funnel event.', 'gym-core' ),
				array( 'status' => 400 )
			);
		}

		$lead_source = (string) $request->get_param( 'lead_source' );
		if ( '' === $lead_source ) {
			// Phase 1 §F lead-source cookie fallback. Tolerate absence.
			$lead_source = isset( $_COOKIE[ self::LEAD_SOURCE_COOKIE ] )
				? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::LEAD_SOURCE_COOKIE ] ) )
				: '';
		}

		$row_id = $this->record(
			$event,
			(string) $request->get_param( 'session_id' ),
			array(
				'page_url'    => (string) $request->get_param( 'page_url' ),
				'lead_source' => $lead_source,
				'metadata'    => $request->get_param( 'metadata' ),
				'user_id'     => get_current_user_id(),
			)
		);

		if ( false === $row_id ) {
			return new WP_Error(
				'gym_funnel_write_failed',
				__( 'Could not record funnel event.', 'gym-core' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'ok' => true,
				'id' => $row_id,
			),
			200
		);
	}

	/**
	 * Server-side hook: when haanpaa-site-kit's trial endpoint succeeds,
	 * record a confirmation event linked to the lead.
	 *
	 * @param int|string|mixed     $contact_id Jetpack CRM contact id (or fallback post id).
	 * @param array<string, mixed> $payload    Submitted form payload (lead_source, etc).
	 *
	 * @return void
	 */
	public function on_trial_submitted( $contact_id, $payload ): void {
		if ( ! is_array( $payload ) ) {
			return;
		}

		$session_id = isset( $payload['session_id'] ) && is_string( $payload['session_id'] )
			? $payload['session_id']
			: 'server-' . wp_generate_uuid4();

		$this->record(
			'confirmation',
			$session_id,
			array(
				'lead_source' => isset( $payload['lead_source'] ) && is_string( $payload['lead_source'] )
					? $payload['lead_source']
					: '',
				'metadata'    => array(
					'contact_id' => $contact_id,
					'program'    => $payload['program'] ?? '',
					'location'   => $payload['location'] ?? '',
				),
			)
		);
	}

	/**
	 * Inserts one funnel-log row. Returns insert id or false on failure.
	 *
	 * @param string               $event   Event name (must be in EVENTS).
	 * @param string               $session Browser-issued session id.
	 * @param array<string, mixed> $extras  Optional fields: page_url, lead_source, metadata, user_id.
	 *
	 * @return int|false
	 */
	public function record( string $event, string $session, array $extras = array() ) {
		global $wpdb;

		if ( ! in_array( $event, self::EVENTS, true ) || '' === $session ) {
			return false;
		}

		$page_url    = isset( $extras['page_url'] ) ? (string) $extras['page_url'] : '';
		$lead_source = isset( $extras['lead_source'] ) ? (string) $extras['lead_source'] : '';
		$metadata    = isset( $extras['metadata'] ) ? $extras['metadata'] : null;
		$user_id     = isset( $extras['user_id'] ) ? (int) $extras['user_id'] : 0;

		$row = array(
			'event'       => substr( $event, 0, self::MAX_TEXT_LEN ),
			'session_id'  => substr( $session, 0, self::MAX_TEXT_LEN ),
			'page_url'    => substr( $page_url, 0, 2000 ),
			'lead_source' => substr( $lead_source, 0, self::MAX_TEXT_LEN ),
			'user_id'     => $user_id > 0 ? $user_id : null,
			'metadata'    => null === $metadata ? null : wp_json_encode( $metadata ),
			'created_at'  => current_time( 'mysql', true ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( self::table_name(), $row, $formats );

		if ( false === $result ) {
			return false;
		}

		// Forward to Jetpack Stats if available — best-effort, never throws.
		if ( function_exists( 'stats_record_event' ) ) {
			stats_record_event( 'gym_funnel', array( 'event' => $event ) );
		}

		/**
		 * Fires after a funnel event is recorded.
		 *
		 * @since 5.0.0
		 *
		 * @param string $event   Event name.
		 * @param string $session Session id.
		 * @param array  $row     Stored row (excluding id).
		 */
		do_action( 'gym_core_funnel_event', $event, $session, $row );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Counts rows by event, optionally filtered by lead_source. Used by reports.
	 *
	 * @param string $event       Event name.
	 * @param string $lead_source Optional filter.
	 *
	 * @return int
	 */
	public function count_event( string $event, string $lead_source = '' ): int {
		global $wpdb;

		$table = self::table_name();

		if ( '' !== $lead_source ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE event = %s AND lead_source = %s",
					$event,
					$lead_source
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event = %s", $event )
		);
	}
}
