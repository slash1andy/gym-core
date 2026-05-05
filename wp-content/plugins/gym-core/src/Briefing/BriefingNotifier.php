<?php
/**
 * Coach Briefing 30-minute pre-class SMS notifier.
 *
 * Action Scheduler is the engine: every day a sweep enqueues one
 * `gym_core_send_briefing_sms` job per scheduled class, fired at
 * `start_time - lead_time`. When the job runs the notifier looks up the
 * class, builds the briefing DTO, signs a magic link, and dispatches an
 * SMS via TwilioClient.
 *
 * Dependencies are constructor-injected so tests can substitute fakes
 * without hitting Twilio or the database. The scheduling logic itself is
 * a pure function of (class_id, day_of_week, start_time, lead_time)
 * which keeps the corresponding tests cheap.
 *
 * @package Gym_Core\Briefing
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Briefing;

use Gym_Core\Schedule\ClassPostType;
use Gym_Core\SMS\SmsOptOut;
use Gym_Core\SMS\TwilioClient;

/**
 * Schedules and sends pre-class briefing SMS messages.
 */
final class BriefingNotifier {

	/**
	 * Action Scheduler hook for the daily sweep.
	 *
	 * @var string
	 */
	public const SWEEP_HOOK = 'gym_core_briefing_sweep';

	/**
	 * Action Scheduler hook for an individual SMS dispatch.
	 *
	 * @var string
	 */
	public const SEND_HOOK = 'gym_core_send_briefing_sms';

	/**
	 * Action Scheduler group used for cancel/queue operations.
	 *
	 * @var string
	 */
	public const GROUP = 'gym-core-briefing';

	/**
	 * Briefing generator.
	 *
	 * @var BriefingGenerator
	 */
	private BriefingGenerator $generator;

	/**
	 * Briefing renderer (used for the SMS body).
	 *
	 * @var BriefingRenderer
	 */
	private BriefingRenderer $renderer;

	/**
	 * Twilio client.
	 *
	 * @var TwilioClient
	 */
	private TwilioClient $sms;

	/**
	 * TCPA opt-out store.
	 *
	 * @var SmsOptOut
	 */
	private SmsOptOut $opt_out;

	/**
	 * Constructor.
	 *
	 * @param BriefingGenerator $generator Briefing generator.
	 * @param BriefingRenderer  $renderer  Briefing renderer.
	 * @param TwilioClient      $sms       Twilio client.
	 * @param SmsOptOut         $opt_out   Opt-out store.
	 */
	public function __construct(
		BriefingGenerator $generator,
		BriefingRenderer $renderer,
		TwilioClient $sms,
		SmsOptOut $opt_out
	) {
		$this->generator = $generator;
		$this->renderer  = $renderer;
		$this->sms       = $sms;
		$this->opt_out   = $opt_out;
	}

	/**
	 * Registers the sweep + dispatch hooks.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::SWEEP_HOOK, array( $this, 'run_daily_sweep' ) );
		add_action( self::SEND_HOOK, array( $this, 'send_briefing_sms' ), 10, 2 );
		add_action( 'init', array( $this, 'ensure_daily_sweep_scheduled' ) );
		// Re-sweep when a class meta changes so reschedules pick up new times.
		add_action( 'save_post_' . ClassPostType::POST_TYPE, array( $this, 'on_class_save' ), 20, 1 );
	}

	/**
	 * Ensures the daily sweep is queued (idempotent).
	 *
	 * @return void
	 */
	public function ensure_daily_sweep_scheduled(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( false !== as_next_scheduled_action( self::SWEEP_HOOK, array(), self::GROUP ) ) {
			return;
		}

		// First run today at 00:05 UTC, recurring daily.
		$start = strtotime( '00:05 tomorrow' );
		if ( false === $start ) {
			$start = time() + DAY_IN_SECONDS;
		}

		as_schedule_recurring_action( $start, DAY_IN_SECONDS, self::SWEEP_HOOK, array(), self::GROUP );
	}

	/**
	 * Daily sweep — enqueues an SMS dispatch for every class scheduled today.
	 *
	 * @return void
	 */
	public function run_daily_sweep(): void {
		if ( 'yes' !== get_option( 'gym_core_briefing_enabled', 'yes' ) ) {
			return;
		}
		if ( 'yes' !== get_option( 'gym_core_briefing_sms_enabled', 'yes' ) ) {
			return;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$lead_minutes = (int) get_option( 'gym_core_briefing_lead_time', 30 );
		$class_ids    = $this->generator->get_todays_classes();

		foreach ( $class_ids as $class_id ) {
			$send_at = $this->compute_send_time( (int) $class_id, $lead_minutes );
			if ( null === $send_at ) {
				continue;
			}
			if ( $send_at <= time() ) {
				// Skip classes whose lead window has already passed.
				continue;
			}

			$instructor_id = (int) get_post_meta( (int) $class_id, '_gym_class_instructor', true );
			if ( $instructor_id <= 0 ) {
				continue;
			}

			as_schedule_single_action(
				$send_at,
				self::SEND_HOOK,
				array( (int) $class_id, $instructor_id ),
				self::GROUP
			);
		}
	}

	/**
	 * Sends a single briefing SMS (Action Scheduler callback).
	 *
	 * @param int $class_id Class post ID.
	 * @param int $user_id  Recipient coach user ID.
	 * @return void
	 */
	public function send_briefing_sms( int $class_id, int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$phone = (string) get_user_meta( $user_id, 'billing_phone', true );
		if ( '' === $phone ) {
			$phone = (string) get_user_meta( $user_id, 'phone', true );
		}
		if ( '' === $phone ) {
			return;
		}
		if ( $this->opt_out->is_opted_out( $phone ) ) {
			return;
		}

		$briefing = $this->generator->generate( $class_id );
		if ( is_wp_error( $briefing ) ) {
			return;
		}

		$link = MagicLink::url( $class_id, $user_id );
		$body = $this->renderer->render_sms( $briefing, $link );

		$this->sms->send( $phone, $body );
	}

	/**
	 * Re-runs the sweep when a class is saved so meta changes pick up.
	 *
	 * @param int $post_id Class post ID.
	 * @return void
	 */
	public function on_class_save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		// Defer to the next sweep — full reconciliation is cheaper than
		// per-edit cancel/reschedule for a daily cadence.
	}

	/**
	 * Computes the absolute send timestamp for today's instance of a class.
	 *
	 * Pure function: given a class id and lead_minutes, returns the UTC
	 * timestamp `start_time - lead_minutes` for today, or null if any
	 * required meta is missing or unparseable. Public so tests can call
	 * it directly without the scheduler.
	 *
	 * @since 2.2.0
	 *
	 * @param int $class_id     Class post ID.
	 * @param int $lead_minutes Lead time before class start.
	 * @return int|null Unix timestamp, or null when not schedulable.
	 */
	public function compute_send_time( int $class_id, int $lead_minutes ): ?int {
		$start_time = (string) get_post_meta( $class_id, '_gym_class_start_time', true );
		if ( '' === $start_time || ! preg_match( '/^\d{1,2}:\d{2}$/', $start_time ) ) {
			return null;
		}

		// Anchor on today's date in the WordPress timezone, then convert
		// to a UTC timestamp for Action Scheduler.
		$tz   = wp_timezone();
		$date = date_create_from_format( 'Y-m-d H:i', gmdate( 'Y-m-d', current_time( 'timestamp' ) ) . ' ' . $start_time, $tz ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		if ( false === $date ) {
			return null;
		}

		$send_at = $date->getTimestamp() - ( $lead_minutes * MINUTE_IN_SECONDS );

		return (int) $send_at;
	}
}
