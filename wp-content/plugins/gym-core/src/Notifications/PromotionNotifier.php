<?php
/**
 * Promotion and Foundations clearance notifications.
 *
 * Sends email and SMS when a student is promoted or cleared from Foundations.
 * Gated by gym_core_notify_on_promotion and gym_core_sms_enabled options.
 *
 * @package Gym_Core
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Notifications;

use Gym_Core\SMS\TwilioClient;
use Gym_Core\Rank\RankDefinitions;

/**
 * Hooks into rank and Foundations events to send notifications.
 */
final class PromotionNotifier {

	/**
	 * Twilio SMS client instance.
	 *
	 * @var TwilioClient
	 */
	private TwilioClient $sms;

	/**
	 * Constructor.
	 *
	 * @param TwilioClient $sms Twilio SMS client.
	 */
	public function __construct( TwilioClient $sms ) {
		$this->sms = $sms;
	}

	/**
	 * Registers action hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'gym_core_rank_changed', array( $this, 'handle_rank_changed' ), 10, 6 );
		add_action( 'gym_core_foundations_cleared', array( $this, 'handle_foundations_cleared' ), 10, 3 );
	}

	/**
	 * Handles the gym_core_rank_changed action.
	 *
	 * @param int    $user_id     Promoted user ID.
	 * @param string $program     Program slug.
	 * @param string $new_belt    New belt slug.
	 * @param int    $new_stripes New stripe/degree count.
	 * @param string $from_belt   Previous belt slug.
	 * @param int    $promoted_by Coach user ID.
	 */
	public function handle_rank_changed( int $user_id, string $program, string $new_belt, int $new_stripes, string $from_belt, int $promoted_by ): void {
		if ( 'yes' !== get_option( 'gym_core_notify_on_promotion', 'yes' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$rank_label    = $this->get_rank_label( $program, $new_belt );
		$program_label = $this->get_program_label( $program );
		$progress_text = $this->format_progress( $program, $new_belt, $new_stripes );

		// Email.
		$subject = sprintf(
			/* translators: %s: program name */
			__( 'Congratulations on your %s promotion!', 'gym-core' ),
			$program_label
		);

		$body = sprintf(
			'<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">'
			. '<h2 style="color:#1a1a1a;">%s</h2>'
			. '<p>%s,</p>'
			. '<p>%s</p>'
			. '<p><strong>%s</strong> — %s</p>'
			. '<p>%s</p>'
			. '<p style="margin-top:2em;color:#666;">— %s</p>'
			. '</div>',
			esc_html( $subject ),
			esc_html( $user->display_name ),
			esc_html__( "We're proud to announce your promotion!", 'gym-core' ),
			esc_html( $rank_label ),
			esc_html( $progress_text ),
			esc_html(
				sprintf(
				/* translators: %s: date */
					__( 'Promoted on %s.', 'gym-core' ),
					wp_date( get_option( 'date_format' ) )
				)
			),
			esc_html( \Gym_Core\Utilities\Brand::name() )
		);

		$this->send_email( $user->user_email, $subject, $body );

		// SMS.
		if ( 'yes' === get_option( 'gym_core_sms_enabled', 'no' ) ) {
			$sms_body = sprintf(
				/* translators: 1: name, 2: rank, 3: program, 4: brand name */
				__( 'Congratulations %1$s! You\'ve been promoted to %2$s in %3$s at %4$s!', 'gym-core' ),
				$user->display_name,
				$rank_label,
				$program_label,
				\Gym_Core\Utilities\Brand::name()
			);

			$this->send_sms( $user_id, $sms_body );
		}
	}

	/**
	 * Handles the gym_core_foundations_cleared action.
	 *
	 * @param int   $user_id  Student user ID.
	 * @param int   $coach_id Coach who cleared.
	 * @param array $status   Status at clearance.
	 */
	public function handle_foundations_cleared( int $user_id, int $coach_id, array $status ): void {
		if ( 'yes' !== get_option( 'gym_core_notify_on_promotion', 'yes' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Email.
		$subject = __( "You've been cleared for live training!", 'gym-core' );
		$body    = sprintf(
			'<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">'
			. '<h2 style="color:#1a1a1a;">%s</h2>'
			. '<p>%s,</p>'
			. '<p>%s</p>'
			. '<p>%s</p>'
			. '<p style="margin-top:2em;color:#666;">— %s</p>'
			. '</div>',
			esc_html( $subject ),
			esc_html( $user->display_name ),
			esc_html__( "Great news! You've completed the Foundations program and are now cleared for live training with all training partners.", 'gym-core' ),
			esc_html(
				sprintf(
				/* translators: %d: class count */
					__( 'You completed %d classes and passed your coach evaluations. Welcome to the mat!', 'gym-core' ),
					$status['classes_completed'] ?? 0
				)
			),
			esc_html( \Gym_Core\Utilities\Brand::name() )
		);

		$this->send_email( $user->user_email, $subject, $body );

		// SMS.
		if ( 'yes' === get_option( 'gym_core_sms_enabled', 'no' ) ) {
			$sms_body = sprintf(
				/* translators: 1: name, 2: brand name */
				__( 'Great news %1$s! You\'ve completed Foundations and are cleared for live training at %2$s!', 'gym-core' ),
				$user->display_name,
				\Gym_Core\Utilities\Brand::name()
			);

			$this->send_sms( $user_id, $sms_body );
		}
	}

	/**
	 * Sends an HTML email.
	 *
	 * @param string $to      Email address.
	 * @param string $subject Subject line.
	 * @param string $body    HTML body.
	 */
	private function send_email( string $to, string $subject, string $body ): void {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			wc_get_logger()->warning(
				sprintf( 'Failed to send promotion email to %s', $to ),
				array( 'source' => 'gym-core' )
			);
		}
	}

	/**
	 * Sends an SMS via Twilio.
	 *
	 * @param int    $user_id User ID (phone from billing_phone meta).
	 * @param string $body    Message body.
	 */
	private function send_sms( int $user_id, string $body ): void {
		$phone = get_user_meta( $user_id, 'billing_phone', true );

		if ( empty( $phone ) ) {
			return;
		}

		$phone = TwilioClient::sanitize_phone( $phone );

		if ( empty( $phone ) ) {
			return;
		}

		$result = $this->sms->send( $phone, $body );

		if ( ! $result['success'] ) {
			wc_get_logger()->error(
				sprintf( 'Failed to send promotion SMS to user %d: %s', $user_id, $result['error'] ?? 'unknown' ),
				array( 'source' => 'gym-core' )
			);
		}
	}

	/**
	 * Gets the display label for a rank.
	 *
	 * @param string $program   Program slug.
	 * @param string $rank_slug Rank slug.
	 * @return string
	 */
	private function get_rank_label( string $program, string $rank_slug ): string {
		$ranks = RankDefinitions::get_ranks( $program );

		foreach ( $ranks as $rank ) {
			if ( $rank['slug'] === $rank_slug ) {
				return $rank['name'];
			}
		}

		return ucwords( str_replace( '-', ' ', $rank_slug ) );
	}

	/**
	 * Gets the display label for a program.
	 *
	 * @param string $program Program slug.
	 * @return string
	 */
	private function get_program_label( string $program ): string {
		$programs = RankDefinitions::get_programs();
		return $programs[ $program ] ?? ucwords( str_replace( '-', ' ', $program ) );
	}

	/**
	 * Formats the stripe/degree progress text.
	 *
	 * @param string $program    Program slug.
	 * @param string $rank_slug  Rank slug.
	 * @param int    $count      Stripe or degree count.
	 * @return string
	 */
	private function format_progress( string $program, string $rank_slug, int $count ): string {
		$ranks = RankDefinitions::get_ranks( $program );

		foreach ( $ranks as $rank ) {
			if ( $rank['slug'] === $rank_slug ) {
				if ( 'degree' === ( $rank['type'] ?? 'belt' ) ) {
					return sprintf(
						/* translators: %d: degree number */
						_n( '%d degree', '%d degrees', $count, 'gym-core' ),
						$count
					);
				}

				if ( $rank['max_stripes'] > 0 ) {
					return sprintf(
						/* translators: 1: current stripes, 2: max stripes */
						__( '%1$d of %2$d stripes', 'gym-core' ),
						$count,
						$rank['max_stripes']
					);
				}

				return '';
			}
		}

		return '';
	}
}
