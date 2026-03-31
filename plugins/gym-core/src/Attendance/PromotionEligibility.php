<?php
/**
 * Promotion eligibility engine.
 *
 * Determines when a student is eligible for belt promotion based on
 * attendance count since last promotion, time at current rank, and
 * coach recommendation status.
 *
 * @package Gym_Core
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Attendance;

use Gym_Core\Rank\RankStore;
use Gym_Core\Rank\RankDefinitions;

/**
 * Evaluates and queries promotion eligibility for members.
 */
final class PromotionEligibility {

	/**
	 * Attendance store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Rank store.
	 *
	 * @var RankStore
	 */
	private RankStore $ranks;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore $attendance Attendance data store.
	 * @param RankStore       $ranks      Rank data store.
	 */
	public function __construct( AttendanceStore $attendance, RankStore $ranks ) {
		$this->attendance = $attendance;
		$this->ranks      = $ranks;
	}

	/**
	 * Returns the default promotion thresholds per program.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{min_attendance: int, min_days: int}>
	 */
	public static function get_default_thresholds(): array {
		$defaults = array(
			'adult-bjj'  => array(
				'min_attendance' => 60,
				'min_days'       => 180,
			),
			'kids-bjj'   => array(
				'min_attendance' => 40,
				'min_days'       => 120,
			),
			'kickboxing' => array(
				'min_attendance' => 30,
				'min_days'       => 90,
			),
		);

		/**
		 * Filters the promotion eligibility thresholds.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, array> $defaults Program slug => thresholds.
		 */
		return apply_filters( 'gym_core_promotion_thresholds', $defaults );
	}

	/**
	 * Checks whether a member is eligible for promotion in a program.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $program Program slug.
	 * @return array{eligible: bool, attendance_count: int, attendance_required: int, days_at_rank: int, days_required: int, has_recommendation: bool, next_belt: string|null}
	 */
	public function check( int $user_id, string $program ): array {
		$rank       = $this->ranks->get_rank( $user_id, $program );
		$thresholds = self::get_default_thresholds();
		$threshold  = $thresholds[ $program ] ?? array( 'min_attendance' => 60, 'min_days' => 180 );

		$result = array(
			'eligible'             => false,
			'attendance_count'     => 0,
			'attendance_required'  => $threshold['min_attendance'],
			'days_at_rank'         => 0,
			'days_required'        => $threshold['min_days'],
			'has_recommendation'   => false,
			'next_belt'            => null,
		);

		if ( ! $rank ) {
			// No current rank — member needs to be assigned an initial rank first.
			return $result;
		}

		// Check if there's a next belt to promote to.
		$next_belt = RankDefinitions::get_next_belt( $program, $rank->belt );
		if ( null === $next_belt && (int) $rank->stripes >= $this->get_max_stripes( $program, $rank->belt ) ) {
			// Already at highest rank with max stripes — not eligible.
			return $result;
		}

		$result['next_belt'] = $next_belt['slug'] ?? $rank->belt . ' (next stripe)';

		// Attendance since last promotion.
		$result['attendance_count'] = $this->attendance->get_count_since( $user_id, $rank->promoted_at );

		// Days at current rank.
		$promoted_time         = strtotime( $rank->promoted_at );
		$result['days_at_rank'] = $promoted_time ? (int) floor( ( time() - $promoted_time ) / DAY_IN_SECONDS ) : 0;

		// Coach recommendation (stored as user meta).
		$recommendation = get_user_meta( $user_id, '_gym_coach_recommendation_' . $program, true );
		$result['has_recommendation'] = ! empty( $recommendation );

		// Determine eligibility.
		$meets_attendance = $result['attendance_count'] >= $result['attendance_required'];
		$meets_time       = $result['days_at_rank'] >= $result['days_required'];

		$require_recommendation = 'yes' === get_option( 'gym_core_require_coach_recommendation', 'yes' );
		$meets_recommendation   = ! $require_recommendation || $result['has_recommendation'];

		$result['eligible'] = $meets_attendance && $meets_time && $meets_recommendation;

		return $result;
	}

	/**
	 * Returns all members who are eligible or approaching eligibility for a program.
	 *
	 * @since 1.2.0
	 *
	 * @param string $program  Program slug.
	 * @param float  $approach Threshold multiplier for "approaching" (e.g., 0.8 = 80% of requirement). Default 0.8.
	 * @return array<int, array{user_id: int, display_name: string, belt: string, stripes: int, eligible: bool, attendance_count: int, attendance_required: int, days_at_rank: int, days_required: int, has_recommendation: bool}>
	 */
	public function get_eligible_members( string $program, float $approach = 0.8 ): array {
		global $wpdb;
		$tables = \Gym_Core\Data\TableManager::get_table_names();

		// Single query: all ranked members for this program.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ranked_members = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, belt, stripes, promoted_at, promoted_by
				FROM {$tables['ranks']}
				WHERE program = %s",
				$program
			)
		) ?: array();

		if ( empty( $ranked_members ) ) {
			return array();
		}

		// Batch optimization: collect all user IDs for bulk operations.
		$user_ids = array_map( static fn( $m ) => (int) $m->user_id, $ranked_members );

		// Prime the WP user object cache in a single query.
		cache_users( $user_ids );

		// Batch-fetch attendance counts since each member's promotion date.
		// Build a single query with CASE/WHEN for per-member date thresholds.
		$attendance_counts = array();
		if ( ! empty( $user_ids ) ) {
			$placeholders = array();
			$values       = array();
			foreach ( $ranked_members as $member ) {
				$placeholders[] = '(user_id = %d AND checked_in_at >= %s)';
				$values[]       = (int) $member->user_id;
				$values[]       = $member->promoted_at;
			}

			$where = implode( ' OR ', $placeholders );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$count_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, COUNT(*) as cnt FROM {$tables['attendance']} WHERE {$where} GROUP BY user_id",
					...$values
				)
			) ?: array();

			foreach ( $count_results as $row ) {
				$attendance_counts[ (int) $row->user_id ] = (int) $row->cnt;
			}
		}

		$thresholds  = self::get_default_thresholds();
		$threshold   = $thresholds[ $program ] ?? array( 'min_attendance' => 60, 'min_days' => 180 );
		$require_rec = 'yes' === get_option( 'gym_core_require_coach_recommendation', 'yes' );
		$eligible    = array();

		foreach ( $ranked_members as $member ) {
			$user_id          = (int) $member->user_id;
			$attendance_count = $attendance_counts[ $user_id ] ?? 0;
			$promoted_time    = strtotime( $member->promoted_at );
			$days_at_rank     = $promoted_time ? (int) floor( ( time() - $promoted_time ) / DAY_IN_SECONDS ) : 0;

			// Check if approaching or eligible.
			$meets_attendance = $attendance_count >= $threshold['min_attendance'];
			$meets_time       = $days_at_rank >= $threshold['min_days'];
			$approaching      = $attendance_count >= ( $threshold['min_attendance'] * $approach )
				|| $days_at_rank >= ( $threshold['min_days'] * $approach );

			if ( ! $meets_attendance && ! $meets_time && ! $approaching ) {
				continue; // Not close enough — skip.
			}

			$recommendation     = get_user_meta( $user_id, '_gym_coach_recommendation_' . $program, true );
			$has_recommendation = ! empty( $recommendation );
			$meets_rec          = ! $require_rec || $has_recommendation;

			$next_belt = \Gym_Core\Rank\RankDefinitions::get_next_belt( $program, $member->belt );
			$is_eligible = $meets_attendance && $meets_time && $meets_rec;

			$user = get_userdata( $user_id ); // Served from cache (primed above).
			$eligible[] = array(
				'user_id'              => $user_id,
				'display_name'         => $user ? $user->display_name : "User #{$user_id}",
				'belt'                 => $member->belt,
				'stripes'              => (int) $member->stripes,
				'eligible'             => $is_eligible,
				'attendance_count'     => $attendance_count,
				'attendance_required'  => $threshold['min_attendance'],
				'days_at_rank'         => $days_at_rank,
				'days_required'        => $threshold['min_days'],
				'has_recommendation'   => $has_recommendation,
				'next_belt'            => $next_belt ? $next_belt['slug'] : null,
			);
		}

		// Sort: eligible first, then by attendance count descending.
		usort(
			$eligible,
			static function ( array $a, array $b ): int {
				if ( $a['eligible'] !== $b['eligible'] ) {
					return $a['eligible'] ? -1 : 1;
				}
				return $b['attendance_count'] <=> $a['attendance_count'];
			}
		);

		return $eligible;
	}

	/**
	 * Sets a coach recommendation for a member in a program.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id  Member user ID.
	 * @param string $program  Program slug.
	 * @param int    $coach_id Coach user ID setting the recommendation.
	 * @return void
	 */
	public function set_recommendation( int $user_id, string $program, int $coach_id ): void {
		update_user_meta(
			$user_id,
			'_gym_coach_recommendation_' . $program,
			array(
				'coach_id'       => $coach_id,
				'recommended_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		/**
		 * Fires when a coach recommends a member for promotion.
		 *
		 * @since 1.2.0
		 *
		 * @param int    $user_id  Member user ID.
		 * @param string $program  Program slug.
		 * @param int    $coach_id Coach who recommended.
		 */
		do_action( 'gym_core_promotion_recommended', $user_id, $program, $coach_id );
	}

	/**
	 * Clears a coach recommendation (after promotion or rejection).
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $program Program slug.
	 * @return void
	 */
	public function clear_recommendation( int $user_id, string $program ): void {
		delete_user_meta( $user_id, '_gym_coach_recommendation_' . $program );
	}

	/**
	 * Gets the max stripes for a belt in a program.
	 *
	 * @param string $program   Program slug.
	 * @param string $belt_slug Belt slug.
	 * @return int Max stripes.
	 */
	private function get_max_stripes( string $program, string $belt_slug ): int {
		$belts = RankDefinitions::get_belts( $program );

		foreach ( $belts as $belt ) {
			if ( $belt['slug'] === $belt_slug ) {
				return $belt['max_stripes'];
			}
		}

		return 0;
	}
}
