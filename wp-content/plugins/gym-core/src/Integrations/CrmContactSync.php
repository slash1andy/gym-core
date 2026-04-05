<?php
/**
 * Syncs gym-core member data to Jetpack CRM custom fields.
 *
 * Keeps CRM contact records up to date with belt rank, Foundations
 * clearance status, and attendance data by listening to gym-core
 * action hooks and writing to CRM custom fields.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Integrations;

use Gym_Core\Rank\RankStore;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;

/**
 * Syncs gym-core data fields to Jetpack CRM contacts.
 */
final class CrmContactSync {

	/**
	 * CRM custom field key for belt rank.
	 *
	 * @var string
	 */
	private const FIELD_BELT_RANK = 'belt_rank';

	/**
	 * CRM custom field key for Foundations status.
	 *
	 * @var string
	 */
	private const FIELD_FOUNDATIONS_STATUS = 'foundations_status';

	/**
	 * CRM custom field key for last check-in date.
	 *
	 * @var string
	 */
	private const FIELD_LAST_CHECKIN = 'last_checkin';

	/**
	 * CRM custom field key for total attendance count.
	 *
	 * @var string
	 */
	private const FIELD_TOTAL_CLASSES = 'total_classes';

	/**
	 * Rank data store.
	 *
	 * @var RankStore
	 */
	private RankStore $rank_store;

	/**
	 * Attendance data store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance_store;

	/**
	 * Foundations clearance handler.
	 *
	 * @var FoundationsClearance
	 */
	private FoundationsClearance $foundations;

	/**
	 * Constructor.
	 *
	 * @param RankStore            $rank_store       Rank data store.
	 * @param AttendanceStore      $attendance_store  Attendance data store.
	 * @param FoundationsClearance $foundations       Foundations clearance handler.
	 */
	public function __construct(
		RankStore $rank_store,
		AttendanceStore $attendance_store,
		FoundationsClearance $foundations
	) {
		$this->rank_store       = $rank_store;
		$this->attendance_store = $attendance_store;
		$this->foundations      = $foundations;
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

		add_action( 'gym_core_rank_changed', array( $this, 'on_rank_changed' ), 10, 6 );
		add_action( 'gym_core_foundations_cleared', array( $this, 'on_foundations_cleared' ), 10, 3 );
		add_action( 'gym_core_attendance_recorded', array( $this, 'on_attendance_recorded' ), 10, 5 );
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
	 * Handles belt rank changes by updating the CRM contact field.
	 *
	 * Hooked to `gym_core_rank_changed`.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $user_id     The member who was promoted.
	 * @param string $program     Program slug.
	 * @param string $new_belt    New belt slug.
	 * @param int    $new_stripes New stripe count.
	 * @param string $from_belt   Previous belt slug (null if first rank).
	 * @param int    $promoted_by User ID of the promoter.
	 * @return void
	 */
	public function on_rank_changed( int $user_id, string $program, string $new_belt, int $new_stripes, ?string $from_belt, int $promoted_by ): void {
		$contact_id = $this->get_contact_id_for_user( $user_id );

		if ( null === $contact_id ) {
			return;
		}

		$stripe_display = $new_stripes > 0
			? sprintf( '%s (%d stripe%s)', $new_belt, $new_stripes, $new_stripes > 1 ? 's' : '' )
			: $new_belt;

		$value = sprintf( '%s — %s', ucfirst( str_replace( '-', ' ', $program ) ), $stripe_display );

		$this->update_contact_field( $contact_id, self::FIELD_BELT_RANK, $value );
	}

	/**
	 * Handles Foundations clearance by updating the CRM contact field.
	 *
	 * Hooked to `gym_core_foundations_cleared`.
	 *
	 * @since 2.1.0
	 *
	 * @param int   $user_id  Student user ID.
	 * @param int   $coach_id Coach who cleared the student.
	 * @param array $status   Foundations status array at time of clearance.
	 * @return void
	 */
	public function on_foundations_cleared( int $user_id, int $coach_id, array $status ): void {
		$contact_id = $this->get_contact_id_for_user( $user_id );

		if ( null === $contact_id ) {
			return;
		}

		$value = sprintf(
			/* translators: 1: clearance date */
			__( 'Cleared (%s)', 'gym-core' ),
			gmdate( 'Y-m-d' )
		);

		$this->update_contact_field( $contact_id, self::FIELD_FOUNDATIONS_STATUS, $value );
	}

	/**
	 * Handles attendance recording by updating the CRM contact's last check-in.
	 *
	 * Hooked to `gym_core_attendance_recorded`.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $record_id Attendance record ID.
	 * @param int    $user_id   Member user ID.
	 * @param int    $class_id  Class post ID.
	 * @param string $location  Location slug.
	 * @param string $method    Check-in method.
	 * @return void
	 */
	public function on_attendance_recorded( int $record_id, int $user_id, int $class_id, string $location, string $method ): void {
		$contact_id = $this->get_contact_id_for_user( $user_id );

		if ( null === $contact_id ) {
			return;
		}

		$this->update_contact_field(
			$contact_id,
			self::FIELD_LAST_CHECKIN,
			gmdate( 'Y-m-d H:i:s' )
		);

		// Also update total class count.
		$total = $this->attendance_store->get_total_count( $user_id );
		$this->update_contact_field(
			$contact_id,
			self::FIELD_TOTAL_CLASSES,
			(string) $total
		);
	}

	/**
	 * Bulk-syncs all gym-core fields for a user to their CRM contact.
	 *
	 * @since 2.1.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if sync was performed, false if CRM inactive or no contact.
	 */
	public function sync_all_fields( int $user_id ): bool {
		if ( ! self::is_crm_active() ) {
			return false;
		}

		$contact_id = $this->get_contact_id_for_user( $user_id );

		if ( null === $contact_id ) {
			return false;
		}

		// Sync belt rank (use primary program: adult-bjj).
		$ranks = $this->rank_store->get_all_ranks( $user_id );

		if ( ! empty( $ranks ) ) {
			$parts = array();
			foreach ( $ranks as $rank ) {
				$stripe_display = (int) $rank->stripes > 0
					? sprintf( '%s (%d stripe%s)', $rank->belt, (int) $rank->stripes, (int) $rank->stripes > 1 ? 's' : '' )
					: $rank->belt;

				$parts[] = sprintf( '%s — %s', ucfirst( str_replace( '-', ' ', $rank->program ) ), $stripe_display );
			}
			$this->update_contact_field( $contact_id, self::FIELD_BELT_RANK, implode( '; ', $parts ) );
		}

		// Sync Foundations status.
		$foundations_status = $this->foundations->get_status( $user_id );

		if ( $foundations_status['cleared'] ) {
			$value = sprintf(
				/* translators: 1: clearance date */
				__( 'Cleared (%s)', 'gym-core' ),
				$foundations_status['cleared_at'] ?? ''
			);
		} elseif ( $foundations_status['in_foundations'] ) {
			$value = sprintf(
				/* translators: 1: current phase, 2: classes completed, 3: classes required */
				__( '%1$s — %2$d/%3$d classes', 'gym-core' ),
				ucfirst( str_replace( '_', ' ', $foundations_status['phase'] ) ),
				$foundations_status['classes_completed'],
				$foundations_status['classes_total_required']
			);
		} else {
			$value = __( 'Not enrolled', 'gym-core' );
		}
		$this->update_contact_field( $contact_id, self::FIELD_FOUNDATIONS_STATUS, $value );

		// Sync attendance stats.
		$history = $this->attendance_store->get_user_history( $user_id, 1 );

		if ( ! empty( $history ) ) {
			$this->update_contact_field(
				$contact_id,
				self::FIELD_LAST_CHECKIN,
				$history[0]->checked_in_at ?? ''
			);
		}

		$total = $this->attendance_store->get_total_count( $user_id );
		$this->update_contact_field( $contact_id, self::FIELD_TOTAL_CLASSES, (string) $total );

		return true;
	}

	/**
	 * Maps a WordPress user ID to a Jetpack CRM contact ID via email.
	 *
	 * @since 2.1.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int|null CRM contact ID, or null if not found.
	 */
	private function get_contact_id_for_user( int $user_id ): ?int {
		$user = get_userdata( $user_id );

		if ( ! $user || empty( $user->user_email ) ) {
			return null;
		}

		return $this->find_contact_by_email( $user->user_email );
	}

	/**
	 * Finds a Jetpack CRM contact by email address.
	 *
	 * @since 2.1.0
	 *
	 * @param string $email Email address.
	 * @return int|null Contact ID, or null if not found.
	 */
	private function find_contact_by_email( string $email ): ?int {
		// Use Jetpack CRM's built-in lookup if available.
		if ( function_exists( 'zeroBS_getContactByEmail' ) ) {
			$contact = zeroBS_getContactByEmail( $email ); // @phpstan-ignore-line

			if ( ! empty( $contact ) && isset( $contact['id'] ) ) {
				return (int) $contact['id'];
			}

			return null;
		}

		// Fallback: direct DB query against the CRM contacts table.
		global $wpdb;

		$table = $wpdb->prefix . 'zbs_contacts';

		if ( ! self::table_exists( $table ) ) {
			$this->log_warning( 'Jetpack CRM contacts table not found.' );
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$contact_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$table} WHERE zbsc_email = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email
			)
		);

		return null !== $contact_id ? (int) $contact_id : null;
	}

	/**
	 * Updates a custom field on a CRM contact.
	 *
	 * Uses Jetpack CRM's API if available, with fallback to direct meta update.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $key        Custom field key.
	 * @param string $value      Field value.
	 * @return void
	 */
	private function update_contact_field( int $contact_id, string $key, string $value ): void {
		// Preferred: Jetpack CRM contact meta API.
		if ( function_exists( 'zeroBSCRM_addUpdateContactMeta' ) ) {
			zeroBSCRM_addUpdateContactMeta( $contact_id, $key, $value ); // @phpstan-ignore-line
			return;
		}

		// Fallback: use the global $zbs DAL if available.
		global $zbs;

		if ( isset( $zbs ) && is_object( $zbs ) && method_exists( $zbs, 'updateMeta' ) ) {
			$zbs->updateMeta( ZBS_TYPE_CONTACT ?? 1, $contact_id, $key, $value ); // @phpstan-ignore-line
			return;
		}

		// Last resort: log so the sync failure is visible.
		$this->log_warning(
			sprintf(
				'CRM field update unavailable. Contact: %d, Field: %s, Value: %s',
				$contact_id,
				$key,
				$value
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
		wc_get_logger()->warning(
			$message,
			array( 'source' => 'gym-core' )
		);
	}
}
