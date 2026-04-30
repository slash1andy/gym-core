<?php
/**
 * SMS confirmation for free trial submissions.
 *
 * Hooks onto haanpaa/trial_submitted (fired by class-jetpack-crm.php after
 * a successful form submission) and sends a confirmation text via gym-core's
 * TwilioClient. No-ops gracefully when gym-core is not active.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SMS {

	public static function init() {
		add_action( 'haanpaa/trial_submitted', [ __CLASS__, 'send_confirmation' ], 10, 2 );
	}

	/**
	 * @param int|string $id      Jetpack CRM contact ID or fallback post ID.
	 * @param array      $payload Submission payload (fname, phone, program, …).
	 */
	public static function send_confirmation( $id, array $payload ): void {
		if ( ! class_exists( 'Gym_Core\SMS\TwilioClient' ) ) {
			return;
		}

		$phone = $payload['phone'] ?? '';
		if ( '' === $phone ) {
			return;
		}

		$name    = ! empty( $payload['fname'] ) ? $payload['fname'] : 'there';
		$message = sprintf(
			"Hi %s! Thanks for signing up for your free trial at Haanpaa Martial Arts. We'll be in touch shortly to confirm your class time. Questions? Call or text us anytime.",
			$name
		);

		( new \Gym_Core\SMS\TwilioClient() )->send( $phone, $message );
	}
}
