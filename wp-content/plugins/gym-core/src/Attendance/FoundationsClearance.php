<?php
/**
 * Foundations clearance gate for new Adult BJJ students.
 *
 * New students start in "Foundations" status — a safety gate that prevents
 * them from live training with non-coaches until they demonstrate competence.
 *
 * Clearance requirements (all configurable in Settings > Gym Core > Ranks):
 *   Phase 1: Complete 10 classes (coached instruction only)
 *   Phase 2: Complete 2 supervised rolls with coaches
 *   Phase 3: Complete 15 more classes (25 total)
 *   Cleared: After 25 classes + 2 coach rolls, student can live train with all partners
 *
 * Time spent in Foundations counts toward White Belt stripe progression.
 * Foundations is NOT a belt — it's an operational safety status stored as user meta.
 *
 * @package Gym_Core
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Attendance;

/**
 * Tracks and evaluates Foundations clearance status for new students.
 */
class FoundationsClearance {

	/**
	 * User meta key for foundations status.
	 */
	private const META_KEY = '_gym_foundations_status';

	/**
	 * User meta key for coach roll records.
	 */
	private const COACH_ROLLS_KEY = '_gym_foundations_coach_rolls';

	/**
	 * Settings option keys.
	 */
	public const OPTION_PHASE1_CLASSES = 'gym_core_foundations_phase1_classes';
	public const OPTION_COACH_ROLLS    = 'gym_core_foundations_coach_rolls_required';
	public const OPTION_TOTAL_CLASSES  = 'gym_core_foundations_total_classes';
	public const OPTION_ENABLED        = 'gym_core_foundations_enabled';

	/**
	 * Attendance store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore $attendance Attendance data store.
	 */
	public function __construct( AttendanceStore $attendance ) {
		$this->attendance = $attendance;
	}

	/**
	 * Returns whether the Foundations gate is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION_ENABLED, 'yes' );
	}

	/**
	 * Returns the configured requirements.
	 *
	 * @return array{phase1_classes: int, coach_rolls_required: int, total_classes: int}
	 */
	public static function get_requirements(): array {
		return array(
			'phase1_classes'       => (int) get_option( self::OPTION_PHASE1_CLASSES, 10 ),
			'coach_rolls_required' => (int) get_option( self::OPTION_COACH_ROLLS, 2 ),
			'total_classes'        => (int) get_option( self::OPTION_TOTAL_CLASSES, 25 ),
		);
	}

	/**
	 * Returns the full Foundations status for a student.
	 *
	 * @param int $user_id User ID.
	 * @return array{
	 *   in_foundations: bool,
	 *   cleared: bool,
	 *   phase: string,
	 *   classes_completed: int,
	 *   classes_phase1_required: int,
	 *   classes_total_required: int,
	 *   coach_rolls_completed: int,
	 *   coach_rolls_required: int,
	 *   cleared_at: string|null,
	 *   live_training_allowed: bool
	 * }
	 */
	public function get_status( int $user_id ): array {
		$meta = get_user_meta( $user_id, self::META_KEY, true );

		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return array(
				'in_foundations'           => false,
				'cleared'                 => false,
				'phase'                   => 'not_enrolled',
				'classes_completed'       => 0,
				'classes_phase1_required' => 0,
				'classes_total_required'  => 0,
				'coach_rolls_completed'   => 0,
				'coach_rolls_required'    => 0,
				'cleared_at'              => null,
				'live_training_allowed'   => ! self::is_enabled(),
			);
		}

		if ( ! empty( $meta['cleared_at'] ) ) {
			return array(
				'in_foundations'           => false,
				'cleared'                 => true,
				'phase'                   => 'cleared',
				'classes_completed'       => (int) ( $meta['classes_at_clearance'] ?? 0 ),
				'classes_phase1_required' => 0,
				'classes_total_required'  => 0,
				'coach_rolls_completed'   => (int) ( $meta['coach_rolls_at_clearance'] ?? 0 ),
				'coach_rolls_required'    => 0,
				'cleared_at'              => $meta['cleared_at'],
				'live_training_allowed'   => true,
			);
		}

		$reqs        = self::get_requirements();
		$enrolled_at = $meta['enrolled_at'] ?? '';
		$coach_rolls = $this->get_coach_rolls( $user_id );
		$class_count = $enrolled_at ? $this->attendance->get_count_since( $user_id, $enrolled_at ) : 0;
		$rolls_done  = count( $coach_rolls );

		if ( $class_count < $reqs['phase1_classes'] ) {
			$phase = 'phase1';
		} elseif ( $rolls_done < $reqs['coach_rolls_required'] ) {
			$phase = 'phase2_coach_rolls';
		} elseif ( $class_count < $reqs['total_classes'] ) {
			$phase = 'phase3';
		} else {
			$phase = 'ready_to_clear';
		}

		return array(
			'in_foundations'           => true,
			'cleared'                 => false,
			'phase'                   => $phase,
			'classes_completed'       => $class_count,
			'classes_phase1_required' => $reqs['phase1_classes'],
			'classes_total_required'  => $reqs['total_classes'],
			'coach_rolls_completed'   => $rolls_done,
			'coach_rolls_required'    => $reqs['coach_rolls_required'],
			'cleared_at'              => null,
			'live_training_allowed'   => false,
		);
	}

	/**
	 * Enrolls a new student in the Foundations program.
	 *
	 * @param int $user_id User ID.
	 */
	public function enroll( int $user_id ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		update_user_meta( $user_id, self::META_KEY, array(
			'enrolled_at' => gmdate( 'Y-m-d H:i:s' ),
			'cleared_at'  => null,
		) );

		// Indexed flag for efficient active-foundations queries.
		update_user_meta( $user_id, '_gym_foundations_active', '1' );

		/** @since 2.0.0 */
		do_action( 'gym_core_foundations_enrolled', $user_id );
	}

	/**
	 * Records a supervised coach roll for a Foundations student.
	 *
	 * @param int    $user_id    Student user ID.
	 * @param int    $coach_id   Coach user ID who supervised the roll.
	 * @param string $notes      Optional notes about the roll.
	 * @param bool   $verify_cap Whether to verify gym_promote_student capability. Default true.
	 *                           Pass false when the caller (e.g. REST controller) has already checked caps.
	 * @return bool Whether the roll was recorded.
	 */
	public function record_coach_roll( int $user_id, int $coach_id, string $notes = '', bool $verify_cap = true ): bool {
		if ( $verify_cap && ! current_user_can( 'gym_promote_student' ) ) {
			return false;
		}

		$status = $this->get_status( $user_id );

		if ( ! $status['in_foundations'] ) {
			return false;
		}

		$rolls   = get_user_meta( $user_id, self::COACH_ROLLS_KEY, true ) ?: array();
		$rolls[] = array(
			'coach_id' => $coach_id,
			'date'     => gmdate( 'Y-m-d H:i:s' ),
			'notes'    => sanitize_text_field( $notes ),
		);

		update_user_meta( $user_id, self::COACH_ROLLS_KEY, $rolls );

		/** @since 2.0.0 */
		do_action( 'gym_core_foundations_coach_roll', $user_id, $coach_id, $rolls );

		return true;
	}

	/**
	 * Returns the coach roll records for a Foundations student.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array{coach_id: int, date: string, notes: string}>
	 */
	public function get_coach_rolls( int $user_id ): array {
		$rolls = get_user_meta( $user_id, self::COACH_ROLLS_KEY, true );
		return is_array( $rolls ) ? $rolls : array();
	}

	/**
	 * Clears a student from Foundations, allowing live training.
	 *
	 * @param int $user_id  Student user ID.
	 * @param int $coach_id Coach clearing the student (0 for auto-clear).
	 * @return bool Whether clearance was granted.
	 */
	public function clear( int $user_id, int $coach_id = 0 ): bool {
		$status = $this->get_status( $user_id );

		if ( ! $status['in_foundations'] ) {
			return false;
		}

		$existing = get_user_meta( $user_id, self::META_KEY, true );

		update_user_meta( $user_id, self::META_KEY, array(
			'enrolled_at'              => $existing['enrolled_at'] ?? '',
			'cleared_at'               => gmdate( 'Y-m-d H:i:s' ),
			'cleared_by'               => $coach_id,
			'classes_at_clearance'     => $status['classes_completed'],
			'coach_rolls_at_clearance' => $status['coach_rolls_completed'],
		) );

		// Remove indexed flag so active-foundations queries no longer match.
		delete_user_meta( $user_id, '_gym_foundations_active' );

		/** @since 2.0.0 */
		do_action( 'gym_core_foundations_cleared', $user_id, $coach_id, $status );

		return true;
	}

	/**
	 * Checks if a student can live train (not in Foundations, or cleared).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_live_train( int $user_id ): bool {
		if ( ! self::is_enabled() ) {
			return true;
		}

		$status = $this->get_status( $user_id );
		return $status['live_training_allowed'];
	}
}
