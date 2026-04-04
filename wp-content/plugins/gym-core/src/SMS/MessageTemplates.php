<?php
/**
 * SMS message templates.
 *
 * Provides predefined message templates with placeholder substitution
 * for common gym notifications. Templates support {first_name}, {location},
 * {class_name}, {belt}, {date}, and other dynamic fields.
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\SMS;

/**
 * Manages SMS message templates for automated notifications.
 */
final class MessageTemplates {

	/**
	 * Returns all available templates.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, array{name: string, body: string, description: string}>
	 */
	public static function get_all(): array {
		$templates = array(
			'lead_followup'          => array(
				'name'        => __( 'Lead Follow-Up', 'gym-core' ),
				'body'        => __( 'Hey {first_name}! Thanks for your interest in {location}. Ready to try a free class? Reply YES and we\'ll get you scheduled.', 'gym-core' ),
				'description' => __( 'Sent after trial class inquiry', 'gym-core' ),
			),
			'class_reminder'         => array(
				'name'        => __( 'Class Reminder', 'gym-core' ),
				'body'        => __( 'Hey {first_name}, reminder: {class_name} is tomorrow at {time} at {location}. See you on the mats!', 'gym-core' ),
				'description' => __( 'Sent 24 hours before a scheduled class', 'gym-core' ),
			),
			'schedule_change'        => array(
				'name'        => __( 'Schedule Change', 'gym-core' ),
				'body'        => __( 'Heads up {first_name} — {class_name} at {location} has been {change_type}. Check the updated schedule at {site_url}/classes', 'gym-core' ),
				'description' => __( 'Sent when a class is cancelled or rescheduled', 'gym-core' ),
			),
			'payment_failed'         => array(
				'name'        => __( 'Payment Failed', 'gym-core' ),
				'body'        => __( 'Hi {first_name}, your membership payment didn\'t go through. Please update your payment method at {site_url}/my-account to keep your access active.', 'gym-core' ),
				'description' => __( 'Sent when a subscription payment fails', 'gym-core' ),
			),
			'belt_promotion'         => array(
				'name'        => __( 'Belt Promotion', 'gym-core' ),
				'body'        => __( 'Congratulations {first_name}! You\'ve been promoted to {belt} in {program}. Keep up the amazing work!', 'gym-core' ),
				'description' => __( 'Sent when a member is promoted to a new belt', 'gym-core' ),
			),
			'birthday'               => array(
				'name'        => __( 'Birthday', 'gym-core' ),
				'body'        => sprintf(
					/* translators: %s: brand name */
					__( 'Happy birthday {first_name}! From your %s family. Come celebrate with a class on us this week!', 'gym-core' ),
					\Gym_Core\Utilities\Brand::name()
				),
				'description' => __( 'Sent on the member\'s birthday', 'gym-core' ),
			),
			'badge_earned'           => array(
				'name'        => __( 'Badge Earned', 'gym-core' ),
				'body'        => __( 'Nice work {first_name}! You just earned the "{badge_name}" badge. Check your achievements at {site_url}/my-account', 'gym-core' ),
				'description' => __( 'Sent when a member earns a new badge', 'gym-core' ),
			),
			'streak_reminder'        => array(
				'name'        => __( 'Streak Reminder', 'gym-core' ),
				'body'        => __( 'You\'re on a {streak_count}-week streak {first_name}! Keep it going — get to class this week.', 'gym-core' ),
				'description' => __( 'Weekly reminder for members with an active streak', 'gym-core' ),
			),
			'streak_broken'          => array(
				'name'        => __( 'Streak Broken', 'gym-core' ),
				'body'        => __( 'Hey {first_name}, your {streak_count}-week streak ended. No worries — come back and start a new one! We miss you at {location}.', 'gym-core' ),
				'description' => __( 'Sent when a member\'s streak breaks', 'gym-core' ),
			),
			'reengage_30'            => array(
				'name'        => __( 'Re-Engage (30 days)', 'gym-core' ),
				'body'        => __( 'Hey {first_name}, we haven\'t seen you in a while! Your spot on the mats is waiting. Come train with us this week at {location}.', 'gym-core' ),
				'description' => __( 'Sent after 30 days of inactivity', 'gym-core' ),
			),
			'reengage_60'            => array(
				'name'        => __( 'Re-Engage (60 days)', 'gym-core' ),
				'body'        => __( '{first_name}, it\'s been 2 months. Your training partners miss you! Reply to chat about getting back on track.', 'gym-core' ),
				'description' => __( 'Sent after 60 days of inactivity', 'gym-core' ),
			),
			'reengage_90'            => array(
				'name'        => __( 'Re-Engage (90 days)', 'gym-core' ),
				'body'        => sprintf(
					/* translators: %s: brand name */
					__( '{first_name}, we\'d love to have you back at %s. Reply for a special offer to restart your training.', 'gym-core' ),
					\Gym_Core\Utilities\Brand::name()
				),
				'description' => __( 'Sent after 90 days of inactivity', 'gym-core' ),
			),
		);

		/**
		 * Filters the SMS message templates.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string, array> $templates Template slug => definition.
		 */
		return apply_filters( 'gym_core_sms_templates', $templates );
	}

	/**
	 * Returns a single template definition.
	 *
	 * @since 1.3.0
	 *
	 * @param string $slug Template slug.
	 * @return array{name: string, body: string, description: string}|null
	 */
	public static function get( string $slug ): ?array {
		$templates = self::get_all();
		return $templates[ $slug ] ?? null;
	}

	/**
	 * Renders a template with variable substitution.
	 *
	 * @since 1.3.0
	 *
	 * @param string               $slug      Template slug.
	 * @param array<string,string> $variables Placeholder key => value (without braces).
	 * @return string|null Rendered message body, or null if template not found.
	 */
	public static function render( string $slug, array $variables = array() ): ?string {
		$template = self::get( $slug );

		if ( null === $template ) {
			return null;
		}

		$body = $template['body'];

		// Add site_url as a default variable.
		if ( ! isset( $variables['site_url'] ) ) {
			$variables['site_url'] = home_url();
		}

		foreach ( $variables as $key => $value ) {
			$body = str_replace( '{' . $key . '}', sanitize_text_field( $value ), $body );
		}

		return $body;
	}

	/**
	 * Returns all available template slugs.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, string>
	 */
	public static function get_slugs(): array {
		return array_keys( self::get_all() );
	}
}
