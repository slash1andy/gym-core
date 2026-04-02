<?php
/**
 * Bridges gym-core SMS with Jetpack CRM contact activity logging.
 *
 * Listens for outbound and inbound SMS events, logs them as CRM activities
 * on the matched contact, and provides methods to send templated SMS to
 * CRM contacts by ID.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Integrations;

use Gym_Core\SMS\TwilioClient;
use Gym_Core\SMS\MessageTemplates;

/**
 * CRM SMS bridge — logs SMS activity and enables contact-based sending.
 */
final class CrmSmsBridge {

	/**
	 * Twilio client instance.
	 *
	 * @var TwilioClient
	 */
	private TwilioClient $twilio;

	/**
	 * CRM activity type for outbound SMS.
	 *
	 * @var string
	 */
	private const ACTIVITY_TYPE_SENT = 'sms_sent';

	/**
	 * CRM activity type for inbound SMS.
	 *
	 * @var string
	 */
	private const ACTIVITY_TYPE_RECEIVED = 'sms_received';

	/**
	 * Constructor.
	 *
	 * @param TwilioClient $twilio Twilio API client.
	 */
	public function __construct( TwilioClient $twilio ) {
		$this->twilio = $twilio;
	}

	/**
	 * Registers hooks if Jetpack CRM is active.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! self::is_crm_active() ) {
			return;
		}

		add_action( 'gym_core_sms_sent', array( $this, 'log_outbound_sms' ), 10, 3 );
		add_action( 'gym_core_sms_received', array( $this, 'log_inbound_sms' ), 10, 4 );
	}

	/**
	 * Checks whether Jetpack CRM is active and available.
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public static function is_crm_active(): bool {
		return class_exists( 'ZeroBSCRM' ) || function_exists( 'zeroBS_getContactByEmail' );
	}

	/**
	 * Logs an outbound SMS as a CRM activity on the matched contact.
	 *
	 * Hooked to `gym_core_sms_sent`.
	 *
	 * @since 2.1.0
	 *
	 * @param string $to   Recipient phone number (E.164).
	 * @param string $body Message body.
	 * @param string $sid  Twilio message SID.
	 * @return void
	 */
	public function log_outbound_sms( string $to, string $body, string $sid ): void {
		$contact_id = $this->find_contact_by_phone( $to );

		if ( null === $contact_id ) {
			return;
		}

		$this->add_activity(
			$contact_id,
			self::ACTIVITY_TYPE_SENT,
			sprintf(
				/* translators: 1: Twilio SID */
				__( 'SMS sent (SID: %s)', 'gym-core' ),
				$sid
			),
			$body
		);
	}

	/**
	 * Logs an inbound SMS as a CRM activity on the matched contact.
	 *
	 * Hooked to `gym_core_sms_received`.
	 *
	 * @since 2.1.0
	 *
	 * @param string $from    Sender phone number (E.164).
	 * @param string $body    Message body.
	 * @param string $to      Recipient phone number (our Twilio number).
	 * @param string $sms_sid Twilio message SID.
	 * @return void
	 */
	public function log_inbound_sms( string $from, string $body, string $to, string $sms_sid ): void {
		$contact_id = $this->find_contact_by_phone( $from );

		if ( null === $contact_id ) {
			$this->log_warning(
				sprintf( 'Inbound SMS from unmatched phone: %s (SID: %s)', $from, $sms_sid )
			);
			return;
		}

		$this->add_activity(
			$contact_id,
			self::ACTIVITY_TYPE_RECEIVED,
			sprintf(
				/* translators: 1: Twilio SID */
				__( 'SMS received (SID: %s)', 'gym-core' ),
				$sms_sid
			),
			$body
		);
	}

	/**
	 * Sends a templated SMS to a Jetpack CRM contact.
	 *
	 * Resolves the contact's phone number from CRM, renders the template,
	 * and sends via TwilioClient.
	 *
	 * @since 2.1.0
	 *
	 * @param int                  $contact_id    Jetpack CRM contact ID.
	 * @param string               $template_slug Template slug from MessageTemplates.
	 * @param array<string,string> $variables     Template placeholder variables.
	 * @return array{success: bool, sid: string|null, error: string|null}
	 */
	public function send_to_contact( int $contact_id, string $template_slug, array $variables = array() ): array {
		if ( ! self::is_crm_active() ) {
			return array(
				'success' => false,
				'sid'     => null,
				'error'   => __( 'Jetpack CRM is not active.', 'gym-core' ),
			);
		}

		$phone = $this->get_contact_phone( $contact_id );

		if ( '' === $phone ) {
			return array(
				'success' => false,
				'sid'     => null,
				'error'   => __( 'Contact has no phone number.', 'gym-core' ),
			);
		}

		$body = MessageTemplates::render( $template_slug, $variables );

		if ( null === $body ) {
			return array(
				'success' => false,
				'sid'     => null,
				'error'   => sprintf(
					/* translators: 1: template slug */
					__( 'Unknown SMS template: %s', 'gym-core' ),
					$template_slug
				),
			);
		}

		if ( $this->twilio->is_rate_limited( $contact_id ) ) {
			return array(
				'success' => false,
				'sid'     => null,
				'error'   => __( 'Contact is rate-limited.', 'gym-core' ),
			);
		}

		$result = $this->twilio->send( $phone, $body );

		if ( $result['success'] ) {
			$this->twilio->record_send( $contact_id );
		}

		return $result;
	}

	/**
	 * Finds a Jetpack CRM contact ID by phone number.
	 *
	 * Searches CRM contacts' mobile and home phone fields for a match.
	 *
	 * @since 2.1.0
	 *
	 * @param string $phone Phone number to search (E.164 format).
	 * @return int|null Contact ID, or null if not found.
	 */
	public function find_contact_by_phone( string $phone ): ?int {
		if ( '' === $phone ) {
			return null;
		}

		$phone = TwilioClient::sanitize_phone( $phone );

		if ( '' === $phone ) {
			return null;
		}

		// Try Jetpack CRM's native phone lookup first (index-friendly).
		if ( function_exists( 'zeroBS_getContactByPhone' ) ) {
			$contact = zeroBS_getContactByPhone( $phone ); // @phpstan-ignore-line
			if ( ! empty( $contact ) && isset( $contact['id'] ) ) {
				return (int) $contact['id'];
			}
		}

		global $wpdb;

		// Jetpack CRM stores contacts in a custom table with zbsc_ prefix.
		$table = $wpdb->prefix . 'zbs_contacts';

		if ( ! self::table_exists( $table ) ) {
			$this->log_warning( 'Jetpack CRM contacts table not found.' );
			return null;
		}

		// Use E.164 digits for exact-match first, then fall back to LIKE.
		$phone_digits = ltrim( $phone, '+' );

		// Try exact match on all phone columns (can use indexes).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$contact_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$table} WHERE zbsc_hometel = %s OR zbsc_worktel = %s OR zbsc_mobtel = %s OR zbsc_hometel = %s OR zbsc_worktel = %s OR zbsc_mobtel = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$phone,
				$phone,
				$phone,
				$phone_digits,
				$phone_digits,
				$phone_digits
			)
		);

		if ( null !== $contact_id ) {
			return (int) $contact_id;
		}

		// Last resort: LIKE fallback for non-standard phone formats in CRM.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$contact_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$table} WHERE zbsc_hometel LIKE %s OR zbsc_worktel LIKE %s OR zbsc_mobtel LIKE %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $phone_digits ) . '%',
				'%' . $wpdb->esc_like( $phone_digits ) . '%',
				'%' . $wpdb->esc_like( $phone_digits ) . '%'
			)
		);

		return null !== $contact_id ? (int) $contact_id : null;
	}

	/**
	 * Returns the phone number for a CRM contact.
	 *
	 * Prefers mobile, falls back to home phone.
	 *
	 * @since 2.1.0
	 *
	 * @param int $contact_id Jetpack CRM contact ID.
	 * @return string Phone number in E.164 format, or empty string.
	 */
	private function get_contact_phone( int $contact_id ): string {
		global $wpdb;

		$table = $wpdb->prefix . 'zbs_contacts';

		if ( ! self::table_exists( $table ) ) {
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$contact = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT zbsc_mobtel, zbsc_hometel FROM {$table} WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id
			)
		);

		if ( ! $contact ) {
			return '';
		}

		// Prefer mobile, fall back to home phone.
		$phone = ! empty( $contact->zbsc_mobtel )
			? $contact->zbsc_mobtel
			: ( $contact->zbsc_hometel ?? '' );

		return TwilioClient::sanitize_phone( (string) $phone );
	}

	/**
	 * Adds an activity log entry to a CRM contact.
	 *
	 * Uses Jetpack CRM's activity log API if available, with fallback logging.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $type       Activity type slug.
	 * @param string $title      Short activity title.
	 * @param string $body       Activity detail / message body.
	 * @return void
	 */
	private function add_activity( int $contact_id, string $type, string $title, string $body ): void {
		// Jetpack CRM DAL v3+ activity logging.
		if ( function_exists( 'zeroBSCRM_activity_addToLog' ) ) {
			zeroBSCRM_activity_addToLog( // @phpstan-ignore-line
				$contact_id,
				array(
					'type'      => $type,
					'shortdesc' => $title,
					'longdesc'  => wp_kses( $body, array() ),
				)
			);
			return;
		}

		// Fallback: use the global $zbs DAL if available.
		global $zbs;

		if ( isset( $zbs ) && is_object( $zbs ) && method_exists( $zbs, 'addUpdateLog' ) ) {
			$zbs->addUpdateLog( // @phpstan-ignore-line
				array(
					'objtype' => ZBS_TYPE_CONTACT ?? 1,
					'objid'   => $contact_id,
					'type'    => $type,
					'shortdesc' => $title,
					'longdesc'  => wp_kses( $body, array() ),
				)
			);
			return;
		}

		// Last resort: log to error log so data is not silently lost.
		$this->log_warning(
			sprintf(
				'CRM activity log unavailable. Contact: %d, Type: %s, Title: %s',
				$contact_id,
				$type,
				$title
			)
		);
	}

	/**
	 * Checks whether a database table exists.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return null !== $result;
	}

	/**
	 * Logs a warning message.
	 *
	 * @param string $message Warning message.
	 * @return void
	 */
	private function log_warning( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Gym Core CRM SMS Bridge] ' . $message );
		}
	}
}
