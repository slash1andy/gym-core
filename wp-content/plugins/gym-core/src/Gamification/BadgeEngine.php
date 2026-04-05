<?php
/**
 * Badge evaluation engine.
 *
 * Listens to gym_core_attendance_recorded and gym_core_rank_changed hooks
 * to evaluate badge award conditions. Awards badges by inserting into the
 * gym_achievements table and fires gym_core_badge_earned for downstream
 * consumers (notifications, dashboard updates).
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Gamification;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Data\TableManager;

/**
 * Evaluates and awards badges based on member activity.
 */
class BadgeEngine {

	/**
	 * Attendance store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Streak tracker.
	 *
	 * @var StreakTracker
	 */
	private StreakTracker $streaks;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore $attendance Attendance data store.
	 * @param StreakTracker   $streaks    Streak tracker.
	 */
	public function __construct( AttendanceStore $attendance, StreakTracker $streaks ) {
		$this->attendance = $attendance;
		$this->streaks    = $streaks;
	}

	/**
	 * Action Scheduler hook name for async badge evaluation.
	 *
	 * @var string
	 */
	private const ASYNC_CHECKIN_HOOK = 'gym_core_async_evaluate_badges';

	/**
	 * Registers hooks for automatic badge evaluation.
	 *
	 * Check-in evaluation is deferred via Action Scheduler to avoid
	 * running 5+ queries synchronously during the check-in request.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'gym_core_attendance_recorded', array( $this, 'schedule_checkin_evaluation' ), 10, 5 );
		add_action( self::ASYNC_CHECKIN_HOOK, array( $this, 'evaluate_on_checkin' ), 10, 1 );
		add_action( 'gym_core_rank_changed', array( $this, 'evaluate_on_promotion' ), 10, 6 );
	}

	/**
	 * Schedules async badge evaluation after a check-in event.
	 *
	 * @since 1.4.0
	 *
	 * @param int    $record_id Attendance record ID.
	 * @param int    $user_id   Member user ID.
	 * @param int    $class_id  Class post ID.
	 * @param string $location  Location slug.
	 * @param string $method    Check-in method.
	 * @return void
	 */
	public function schedule_checkin_evaluation( int $record_id, int $user_id, int $class_id, string $location, string $method ): void {
		// Skip imported records — don't retroactively award badges for historical data.
		if ( 'imported' === $method ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			// Deduplicate: skip if an evaluation is already pending for this user.
			if ( ! as_has_scheduled_action( self::ASYNC_CHECKIN_HOOK, array( $user_id ), 'gym-core' ) ) {
				as_enqueue_async_action( self::ASYNC_CHECKIN_HOOK, array( $user_id ), 'gym-core' );
			}
		} else {
			// Fallback to synchronous if Action Scheduler is unavailable.
			$this->evaluate_on_checkin( $user_id );
		}
	}

	/**
	 * Evaluates badges for a user (runs asynchronously via Action Scheduler).
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id Member user ID.
	 * @return void
	 */
	public function evaluate_on_checkin( int $user_id ): void {
		$this->check_attendance_milestones( $user_id );
		$this->check_streak_milestones( $user_id );
		$this->check_multi_program( $user_id );
	}

	/**
	 * Awards the belt_promotion badge when a member is promoted.
	 *
	 * @since 1.3.0
	 *
	 * @param int         $user_id     Member user ID.
	 * @param string      $program     Program slug.
	 * @param string      $new_belt    New belt slug.
	 * @param int         $new_stripes New stripe count.
	 * @param string|null $from_belt   Previous belt slug (null on first-ever rank).
	 * @param int         $promoted_by Promoter user ID.
	 * @return void
	 */
	public function evaluate_on_promotion( int $user_id, string $program, string $new_belt, int $new_stripes, ?string $from_belt, int $promoted_by ): void {
		// Only skip for stripe additions (same belt). First-ever promotion ($from_belt=null) gets the badge.
		if ( null !== $from_belt && $new_belt === $from_belt ) {
			return;
		}

		$this->award_badge(
			$user_id,
			'belt_promotion',
			wp_json_encode(
				array(
					'program'     => $program,
					'belt'        => $new_belt,
					'from'        => $from_belt,
					'promoted_by' => $promoted_by,
				)
			)
		);
	}

	/**
	 * Checks attendance milestone badges (first_class, classes_10, etc.).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function check_attendance_milestones( int $user_id ): void {
		$total      = $this->attendance->get_total_count( $user_id );
		$thresholds = BadgeDefinitions::get_attendance_thresholds();

		foreach ( $thresholds as $count => $badge_slug ) {
			if ( $total >= $count && ! $this->has_badge( $user_id, $badge_slug ) ) {
				$this->award_badge( $user_id, $badge_slug );
			}
		}
	}

	/**
	 * Checks streak milestone badges (streak_4, streak_12, streak_26).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function check_streak_milestones( int $user_id ): void {
		$streak     = $this->streaks->get_streak( $user_id );
		$current    = $streak['current_streak'];
		$thresholds = BadgeDefinitions::get_streak_thresholds();

		foreach ( $thresholds as $weeks => $badge_slug ) {
			if ( $current >= $weeks && ! $this->has_badge( $user_id, $badge_slug ) ) {
				$this->award_badge( $user_id, $badge_slug );
			}
		}
	}

	/**
	 * Checks multi_program badge (attended 2+ different programs).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function check_multi_program( int $user_id ): void {
		if ( $this->has_badge( $user_id, 'multi_program' ) ) {
			return;
		}

		global $wpdb;
		$tables = TableManager::get_table_names();

		// Count distinct programs the member has attended (via class post's gym_program taxonomy).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$program_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT tt.term_taxonomy_id)
				FROM {$tables['attendance']} a
				INNER JOIN {$wpdb->term_relationships} tr ON a.class_id = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE a.user_id = %d AND tt.taxonomy = 'gym_program' AND a.class_id > 0",
				$user_id
			)
		);

		if ( $program_count >= 2 ) {
			$this->award_badge( $user_id, 'multi_program' );
		}
	}

	/**
	 * Awards a badge to a user.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $user_id  User ID.
	 * @param string $slug     Badge slug.
	 * @param string $metadata Optional JSON metadata.
	 * @return bool True if badge was newly awarded.
	 */
	public function award_badge( int $user_id, string $slug, string $metadata = '' ): bool {
		if ( $this->has_badge( $user_id, $slug ) ) {
			return false;
		}

		global $wpdb;
		$tables = TableManager::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$tables['achievements'],
			array(
				'user_id'    => $user_id,
				'badge_slug' => $slug,
				'earned_at'  => gmdate( 'Y-m-d H:i:s' ),
				'metadata'   => $metadata ?: null,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$badge_def = BadgeDefinitions::get( $slug );

		/**
		 * Fires when a member earns a badge.
		 *
		 * @since 1.3.0
		 *
		 * @param int    $user_id  Member who earned the badge.
		 * @param string $slug     Badge slug.
		 * @param array  $badge    Badge definition (name, description, etc.).
		 */
		do_action( 'gym_core_badge_earned', $user_id, $slug, $badge_def );

		return true;
	}

	/**
	 * Checks whether a user already has a specific badge.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $slug    Badge slug.
	 * @return bool
	 */
	public function has_badge( int $user_id, string $slug ): bool {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['achievements']} WHERE user_id = %d AND badge_slug = %s",
				$user_id,
				$slug
			)
		);

		return $count > 0;
	}

	/**
	 * Returns all badges earned by a user.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id User ID.
	 * @return array<int, object> Array of achievement row objects.
	 */
	public function get_user_badges( int $user_id ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT badge_slug, earned_at, metadata FROM {$tables['achievements']} WHERE user_id = %d ORDER BY earned_at DESC",
				$user_id
			)
		) ?: array();
	}
}
