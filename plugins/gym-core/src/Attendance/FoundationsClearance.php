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
final class FoundationsClearance {

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
	public const OPTION_PHASE1_CLASSES     = 'gym_core_foundations_phase1_classes';
	public const OPTION_COACH_ROLLS        = 'gym_core_foundations_coach_rolls_required';
	public const OPTION_TOTAL_CLASSES       = 'gym_core_foundations_total_classes';
	public const OPTION_ENABLED             = 'gym_core_foundations_enabled';

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
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION_ENABLED, 'yes' );
	}

	/**
	 * Returns the configured requirements.
	 *
	 * @since 2.0.0
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
	 * @since 2.0.0
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

		// Not in Foundations — either cleared or never enrolled.
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return array(
				'in_foundations'          => false,
				'cleared'                => false,
				'phase'                  => 'not_enrolled',
				'classes_completed'      => 0,
				'classes_phase1_required' => 0,
				'classes_total_required'  => 0,
				'coach_rolls_completed'  => 0,
				'coach_rolls_required'   => 0,
				'cleared_at'             => null,
				'live_training_allowed'  => ! self::is_enabled(),
			);
		}

		// Already cleared.
		if ( ! empty( $meta['cleared_at'] ) ) {
			return array(
				'in_foundations'          => false,
				'cleared'                => true,
				'phase'                  => 'cleared',
				'classes_completed'      => (int) ( $meta['classes_at_clearance'] ?? 0 ),
				'classes_phase1_required' => 0,
				'classes_total_required'  => 0,
				'coach_rolls_completed'  => (int) ( $meta['coach_rolls_at_clearance'] ?? 0 ),
				'coach_rolls_required'   => 0,
				'cleared_at'             => $meta['cleared_at'],
				'live_training_allowed'  => true,
			);
		}

		$reqs         = self::get_requirements();
		$enrolled_at  = $meta['enrolled_at'] ?? '';
		$coach_rolls  = $this->get_coach_rolls( $user_id );
		$class_count  = $enrolled_at ? $this->attendance->get_count_since( $user_id, $enrolled_at ) : 0;
		$rolls_done   = count( $coach_rolls );

		// Determine current phase.
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
			'in_foundations'          => true,
			'cleared'                => false,
			'phase'                  => $phase,
			'classes_completed'      => $class_count,
			'classes_phase1_required' => $reqs['phase1_classes'],
			'classes_total_required'  => $reqs['total_classes'],
			'coach_rolls_completed'  => $rolls_done,
			'coach_rolls_required'   => $reqs['coach_rolls_required'],
			'cleared_at'             => null,
			'live_training_allowed'  => false,
		);
	}

	/**
	 * Enrolls a new student in the Foundations program.
	 *
	 * Called when a new Adult BJJ student is first assigned a White Belt.
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function enroll( int $user_id ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		update_user_meta( $user_id, self::META_KEY, array(
			'enrolled_at' => gmdate( 'Y-m-d H:i:s' ),
			'cleared_at'  => null,
		) );

		/**
		 * Fires when a student is enrolled in Foundations.
		 *
		 * @since 2.0.0
		 *
		 * @param int $user_id Student user ID.
		 */
		do_action( 'gym_core_foundations_enrolled', $user_id );
	}

	/**
	 * Records a supervised coach roll for a Foundations student.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id  Student user ID.
	 * @param int    $coach_id Coach user ID who supervised the roll.
	 * @param string $notes    Optional notes about the roll.
	 * @return bool Whether the roll was recorded.
	 */
	public function record_coach_roll( int $user_id, int $coach_id, string $notes = '' ): bool {
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

		/**
		 * Fires when a Foundations coach roll is recorded.
		 *
		 * @since 2.0.0
		 *
		 * @param int   $user_id  Student user ID.
		 * @param int   $coach_id Coach who supervised.
		 * @param array $rolls    All coach rolls so far.
		 */
		do_action( 'gym_core_foundations_coach_roll', $user_id, $coach_id, $rolls );

		return true;
	}

	/**
	 * Returns the coach roll records for a Foundations student.
	 *
	 * @since 2.0.0
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
	 * Should only be called after verifying all requirements are met.
	 * Typically triggered automatically when the student reaches the
	 * 'ready_to_clear' phase, or manually by a coach.
	 *
	 * @since 2.0.0
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

		update_user_meta( $user_id, self::META_KEY, array(
			'enrolled_at'            => get_user_meta( $user_id, self::META_KEY, true )['enrolled_at'] ?? '',
			'cleared_at'             => gmdate( 'Y-m-d H:i:s' ),
			'cleared_by'             => $coach_id,
			'classes_at_clearance'   => $status['classes_completed'],
			'coach_rolls_at_clearance' => $status['coach_rolls_completed'],
		) );

		/**
		 * Fires when a student is cleared from Foundations.
		 *
		 * @since 2.0.0
		 *
		 * @param int   $user_id  Student user ID.
		 * @param int   $coach_id Coach who cleared (0 = auto).
		 * @param array $status   Status at time of clearance.
		 */
		do_action( 'gym_core_foundations_cleared', $user_id, $coach_id, $status );

		return true;
	}

	/**
	 * Checks if a student can live train (not in Foundations, or cleared).
	 *
	 * @since 2.0.0
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
