<?php
/**
 * Streak tracking engine.
 *
 * Calculates consecutive-week attendance streaks from check-in data.
 * A streak is the number of consecutive calendar weeks (Monday–Sunday)
 * with at least one check-in. Supports streak freezes (configurable
 * per quarter via settings).
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Gamification;

use Gym_Core\Attendance\AttendanceStore;

/**
 * Calculates and manages attendance streaks.
 */
final class StreakTracker {

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
	 * Returns the current and longest streak for a user.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id User ID.
	 * @return array{current_streak: int, longest_streak: int, streak_started_at: string|null, freezes_remaining: int, freezes_used: int, last_check_in_date: string|null, streak_status: string}
	 */
	public function get_streak( int $user_id ): array {
		$weeks = $this->attendance->get_attended_weeks( $user_id );

		$result = array(
			'current_streak'    => 0,
			'longest_streak'    => 0,
			'streak_started_at' => null,
			'freezes_remaining' => $this->get_freezes_remaining( $user_id ),
			'freezes_used'      => $this->get_freezes_used( $user_id ),
			'last_check_in_date' => null,
			'streak_status'     => 'broken',
		);

		if ( empty( $weeks ) ) {
			return $result;
		}

		// weeks are sorted DESC (most recent first).
		$result['last_check_in_date'] = $weeks[0];

		// Calculate current streak from most recent week backwards.
		$current_streak  = 0;
		$streak_start    = null;
		$freezes_allowed = $this->get_total_freezes_per_quarter();
		$freezes_used    = 0;

		// Check if the most recent attended week is the current week.
		$current_week = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
		$last_week    = gmdate( 'Y-m-d', strtotime( 'monday last week' ) );

		// If last attended week is neither this week nor last week, streak is broken.
		if ( $weeks[0] !== $current_week && $weeks[0] !== $last_week ) {
			// Check if frozen.
			$frozen = $this->is_streak_frozen( $user_id );
			if ( $frozen ) {
				$result['streak_status'] = 'frozen';
			}
			// Still calculate longest streak below.
		}

		// Calculate all streaks to find current and longest.
		$all_streaks = $this->calculate_all_streaks( $weeks );

		if ( ! empty( $all_streaks ) ) {
			$result['longest_streak'] = max( array_column( $all_streaks, 'length' ) );

			// The first streak in the list is the most recent.
			$most_recent = $all_streaks[0];

			// Check if the most recent streak is still active.
			$streak_end_week = $most_recent['end'];
			if ( $streak_end_week === $current_week || $streak_end_week === $last_week ) {
				$result['current_streak']    = $most_recent['length'];
				$result['streak_started_at'] = $most_recent['start'];
				$result['streak_status']     = 'active';
			}
		}

		return $result;
	}

	/**
	 * Calculates all streaks from a list of attended week-start dates.
	 *
	 * @since 1.3.0
	 *
	 * @param array<int, string> $weeks Week-start dates sorted DESC.
	 * @return array<int, array{start: string, end: string, length: int}>
	 */
	private function calculate_all_streaks( array $weeks ): array {
		if ( empty( $weeks ) ) {
			return array();
		}

		// Reverse to ascending order for streak calculation.
		$weeks = array_reverse( $weeks );
		$weeks = array_values( array_unique( $weeks ) );

		$streaks        = array();
		$streak_start   = $weeks[0];
		$streak_length  = 1;
		$previous_week  = $weeks[0];

		for ( $i = 1, $count = count( $weeks ); $i < $count; $i++ ) {
			$expected_next = gmdate( 'Y-m-d', strtotime( $previous_week . ' +7 days' ) );

			if ( $weeks[ $i ] === $expected_next ) {
				// Consecutive week — extend streak.
				++$streak_length;
			} else {
				// Gap — save current streak, start new one.
				$streaks[] = array(
					'start'  => $streak_start,
					'end'    => $previous_week,
					'length' => $streak_length,
				);
				$streak_start  = $weeks[ $i ];
				$streak_length = 1;
			}

			$previous_week = $weeks[ $i ];
		}

		// Save the final streak.
		$streaks[] = array(
			'start'  => $streak_start,
			'end'    => $previous_week,
			'length' => $streak_length,
		);

		// Return in DESC order (most recent first).
		return array_reverse( $streaks );
	}

	/**
	 * Freezes the current streak for a user (preserves it through a missed week).
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id User ID.
	 * @return bool True if freeze was applied, false if no freezes remaining.
	 */
	public function freeze_streak( int $user_id ): bool {
		$remaining = $this->get_freezes_remaining( $user_id );

		if ( $remaining <= 0 ) {
			return false;
		}

		$used = $this->get_freezes_used( $user_id );
		update_user_meta( $user_id, '_gym_streak_freezes_used', $used + 1 );
		update_user_meta( $user_id, '_gym_streak_frozen_at', gmdate( 'Y-m-d H:i:s' ) );

		/**
		 * Fires when a member freezes their attendance streak.
		 *
		 * @since 1.3.0
		 *
		 * @param int $user_id User who froze their streak.
		 * @param int $remaining Freezes remaining after this one.
		 */
		do_action( 'gym_core_streak_frozen', $user_id, $remaining - 1 );

		return true;
	}

	/**
	 * Whether a user's streak is currently frozen.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function is_streak_frozen( int $user_id ): bool {
		$frozen_at = get_user_meta( $user_id, '_gym_streak_frozen_at', true );
		if ( empty( $frozen_at ) ) {
			return false;
		}

		// Freeze lasts 1 week from when it was applied.
		$frozen_time = strtotime( $frozen_at );
		return $frozen_time && ( time() - $frozen_time ) < WEEK_IN_SECONDS;
	}

	/**
	 * Returns the number of freezes used this quarter.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_freezes_used( int $user_id ): int {
		return (int) get_user_meta( $user_id, '_gym_streak_freezes_used', true );
	}

	/**
	 * Returns the number of freezes remaining this quarter.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_freezes_remaining( int $user_id ): int {
		$max  = $this->get_total_freezes_per_quarter();
		$used = $this->get_freezes_used( $user_id );
		return max( 0, $max - $used );
	}

	/**
	 * Returns the configured max freezes per quarter.
	 *
	 * @return int
	 */
	private function get_total_freezes_per_quarter(): int {
		return (int) get_option( 'gym_core_streak_freezes_per_quarter', 1 );
	}
}
