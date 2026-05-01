<?php
/**
 * Badge definitions and registry.
 *
 * Defines all available badges with their award criteria. Filterable
 * via gym_core_badge_definitions hook for custom badges.
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Gamification;

/**
 * Provides badge definitions and metadata.
 */
final class BadgeDefinitions {

	/**
	 * Badge categories.
	 */
	public const CATEGORY_ATTENDANCE = 'attendance';
	public const CATEGORY_RANK       = 'rank';
	public const CATEGORY_SPECIAL    = 'special';

	/**
	 * Returns all badge definitions.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, array{name: string, description: string, category: string, criteria_summary: string, icon: string}>
	 */
	public static function get_all(): array {
		$badges = array(
			// Attendance milestone badges.
			'first_class'    => array(
				'name'             => __( 'First Class', 'gym-core' ),
				'description'      => __( 'Completed your first class.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( 'Check in to any class', 'gym-core' ),
				'icon'             => 'star',
			),
			'classes_10'     => array(
				'name'             => __( '10 Classes', 'gym-core' ),
				'description'      => __( 'Attended 10 classes.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( 'Reach 10 total check-ins', 'gym-core' ),
				'icon'             => 'award',
			),
			'classes_25'     => array(
				'name'             => __( '25 Classes', 'gym-core' ),
				'description'      => __( 'Attended 25 classes.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( 'Reach 25 total check-ins', 'gym-core' ),
				'icon'             => 'award',
			),
			'classes_50'     => array(
				'name'             => __( '50 Classes', 'gym-core' ),
				'description'      => __( 'Attended 50 classes. Halfway to the century!', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( 'Reach 50 total check-ins', 'gym-core' ),
				'icon'             => 'trophy',
			),
			'classes_100'    => array(
				'name'             => __( 'Century Club', 'gym-core' ),
				'description'      => __( 'Attended 100 classes.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( 'Reach 100 total check-ins', 'gym-core' ),
				'icon'             => 'trophy',
			),
			'classes_250'    => array(
				'name'             => __( '250 Classes', 'gym-core' ),
				'description'      => __( 'Attended 250 classes. Serious dedication.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( 'Reach 250 total check-ins', 'gym-core' ),
				'icon'             => 'medal',
			),
			'classes_500'    => array(
				'name'             => __( '500 Club', 'gym-core' ),
				'description'      => __( 'Attended 500 classes. Legend status.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( 'Reach 500 total check-ins', 'gym-core' ),
				'icon'             => 'medal',
			),

			// Streak badges.
			'streak_4'       => array(
				'name'             => __( '4-Week Streak', 'gym-core' ),
				'description'      => __( 'Trained every week for a month straight.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( '4 consecutive weeks with at least 1 check-in', 'gym-core' ),
				'icon'             => 'flame',
			),
			'streak_12'      => array(
				'name'             => __( '12-Week Streak', 'gym-core' ),
				'description'      => __( 'Three months without missing a week.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( '12 consecutive weeks with at least 1 check-in', 'gym-core' ),
				'icon'             => 'flame',
			),
			'streak_26'      => array(
				'name'             => __( '26-Week Streak', 'gym-core' ),
				'description'      => __( 'Six months of consistency. Unstoppable.', 'gym-core' ),
				'category'         => self::CATEGORY_ATTENDANCE,
				'criteria_summary' => __( '26 consecutive weeks with at least 1 check-in', 'gym-core' ),
				'icon'             => 'flame',
			),

			// Rank badges.
			'belt_promotion' => array(
				'name'             => __( 'Belt Promotion', 'gym-core' ),
				'description'      => __( 'Earned a new belt rank.', 'gym-core' ),
				'category'         => self::CATEGORY_RANK,
				'criteria_summary' => __( 'Promoted to a new belt', 'gym-core' ),
				'icon'             => 'belt',
			),

			// Special badges.
			'early_bird'     => array(
				'name'             => __( 'Early Bird', 'gym-core' ),
				'description'      => __( 'Checked in to the first class of the day 10 times.', 'gym-core' ),
				'category'         => self::CATEGORY_SPECIAL,
				'criteria_summary' => __( '10 check-ins to the first class of the day', 'gym-core' ),
				'icon'             => 'sunrise',
			),
			'multi_program'  => array(
				'name'             => __( 'Cross-Trainer', 'gym-core' ),
				'description'      => __( 'Attended classes in 2 or more programs.', 'gym-core' ),
				'category'         => self::CATEGORY_SPECIAL,
				'criteria_summary' => __( 'Check in to classes in 2+ different programs', 'gym-core' ),
				'icon'             => 'crosshair',
			),
		);

		/**
		 * Filters the badge definitions.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string, array{name: string, description: string, category: string, criteria_summary: string, icon: string}> $badges Badge slug => definition.
		 */
		$filtered = apply_filters( 'gym_core_badge_definitions', $badges );
		return is_array( $filtered ) ? $filtered : $badges;
	}

	/**
	 * Returns a single badge definition.
	 *
	 * @since 1.3.0
	 *
	 * @param string $slug Badge slug.
	 * @return array{name: string, description: string, category: string, criteria_summary: string, icon: string}|null
	 */
	public static function get( string $slug ): ?array {
		$badges = self::get_all();
		return $badges[ $slug ] ?? null;
	}

	/**
	 * Returns the attendance milestone thresholds mapped to badge slugs.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, string> Threshold => badge slug.
	 */
	public static function get_attendance_thresholds(): array {
		return array(
			1   => 'first_class',
			10  => 'classes_10',
			25  => 'classes_25',
			50  => 'classes_50',
			100 => 'classes_100',
			250 => 'classes_250',
			500 => 'classes_500',
		);
	}

	/**
	 * Returns the streak milestone thresholds mapped to badge slugs.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, string> Weeks => badge slug.
	 */
	public static function get_streak_thresholds(): array {
		return array(
			4  => 'streak_4',
			12 => 'streak_12',
			26 => 'streak_26',
		);
	}
}
