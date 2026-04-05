<?php
/**
 * Rank hierarchy definitions per program.
 *
 * Defines the ordered progression for each martial arts program:
 *   - Adult BJJ: White → Blue → Purple → Brown → Black (4 stripes per belt, 10 degrees at Black)
 *   - Kids BJJ: 13 belt levels (4 stripes each)
 *   - Kickboxing: Level 1, Level 2 (no stripes — simple level progression)
 *
 * Promotion thresholds (min_days, min_classes) are stored in wp_options
 * and editable under WooCommerce > Settings > Gym Core > Ranks.
 *
 * @package Gym_Core
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Rank;

/**
 * Provides rank definitions and ordering for all programs.
 */
final class RankDefinitions {

	/**
	 * Option key for per-rank promotion thresholds.
	 *
	 * Stored as: array<program_slug, array<belt_slug, array{min_days: int, min_classes: int}>>
	 */
	public const THRESHOLDS_OPTION = 'gym_core_rank_thresholds';

	/**
	 * Returns the rank hierarchy for a given program.
	 *
	 * Each rank is an array with:
	 *   - name:        Display name.
	 *   - slug:        Machine identifier.
	 *   - color:       Hex color for UI display.
	 *   - max_stripes: Number of stripes before next rank (0 = no stripe system).
	 *   - type:        'belt', 'degree', or 'level'.
	 *
	 * @since 2.0.0
	 *
	 * @param string $program Program slug (adult-bjj, kids-bjj, kickboxing).
	 * @return array<int, array{name: string, slug: string, color: string, max_stripes: int, type: string}>
	 */
	public static function get_ranks( string $program ): array {
		$definitions = self::get_all_definitions();

		return $definitions[ $program ] ?? array();
	}

	/**
	 * Backward-compatible alias for get_ranks().
	 *
	 * @param string $program Program slug.
	 * @return array
	 */
	public static function get_belts( string $program ): array {
		return self::get_ranks( $program );
	}

	/**
	 * Returns all program definitions.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array>
	 */
	public static function get_all_definitions(): array {
		$definitions = array(
			'adult-bjj'  => self::get_adult_bjj_ranks(),
			'kids-bjj'   => self::get_kids_bjj_ranks(),
			'kickboxing' => self::get_kickboxing_ranks(),
		);

		/**
		 * Filters the rank definitions for all programs.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, array> $definitions Program slug => rank array.
		 */
		return apply_filters( 'gym_core_rank_definitions', $definitions );
	}

	/**
	 * Returns all available program slugs and labels.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function get_programs(): array {
		$programs = array(
			'adult-bjj'  => __( 'Adult BJJ', 'gym-core' ),
			'kids-bjj'   => __( 'Kids BJJ', 'gym-core' ),
			'kickboxing' => __( 'Kickboxing', 'gym-core' ),
		);

		/** @since 1.2.0 */
		return apply_filters( 'gym_core_programs', $programs );
	}

	/**
	 * Returns the ordinal position of a rank within its program (0-based).
	 *
	 * @param string $program   Program slug.
	 * @param string $rank_slug Rank slug.
	 * @return int|null Position, or null if not found.
	 */
	public static function get_belt_position( string $program, string $rank_slug ): ?int {
		$ranks = self::get_ranks( $program );

		foreach ( $ranks as $index => $rank ) {
			if ( $rank['slug'] === $rank_slug ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Returns the next rank after the given rank in the hierarchy.
	 *
	 * @param string $program   Program slug.
	 * @param string $rank_slug Current rank slug.
	 * @return array|null Next rank, or null if at highest.
	 */
	public static function get_next_belt( string $program, string $rank_slug ): ?array {
		$ranks    = self::get_ranks( $program );
		$position = self::get_belt_position( $program, $rank_slug );

		if ( null === $position || $position >= count( $ranks ) - 1 ) {
			return null;
		}

		return $ranks[ $position + 1 ];
	}

	/**
	 * Returns the promotion thresholds for a specific rank in a program.
	 *
	 * Falls back to the default thresholds if no per-rank override is stored.
	 *
	 * @since 2.0.0
	 *
	 * @param string $program   Program slug.
	 * @param string $rank_slug Rank slug (the rank the student is currently at).
	 * @return array{min_days: int, min_classes: int}
	 */
	public static function get_promotion_threshold( string $program, string $rank_slug ): array {
		$all_thresholds = get_option( self::THRESHOLDS_OPTION, array() );

		if ( isset( $all_thresholds[ $program ][ $rank_slug ] ) ) {
			return wp_parse_args(
				$all_thresholds[ $program ][ $rank_slug ],
				array(
					'min_days'    => 0,
					'min_classes' => 0,
				)
			);
		}

		$defaults = self::get_default_thresholds();
		if ( isset( $defaults[ $program ][ $rank_slug ] ) ) {
			return $defaults[ $program ][ $rank_slug ];
		}

		return array(
			'min_days'    => 0,
			'min_classes' => 0,
		);
	}

	/**
	 * Returns the default promotion thresholds for all programs and ranks.
	 *
	 * Sourced from Spark Membership extraction (2026-03-30).
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, array{min_days: int, min_classes: int}>>
	 */
	public static function get_default_thresholds(): array {
		return array(
			'adult-bjj'  => array(
				'white'  => array(
					'min_days'    => 25,
					'min_classes' => 17,
				),
				'blue'   => array(
					'min_days'    => 500,
					'min_classes' => 225,
				),
				'purple' => array(
					'min_days'    => 700,
					'min_classes' => 400,
				),
				'brown'  => array(
					'min_days'    => 700,
					'min_classes' => 400,
				),
				'black'  => array(
					'min_days'    => 700,
					'min_classes' => 400,
				),
			),
			'kids-bjj'   => array(
				'white'        => array(
					'min_days'    => 0,
					'min_classes' => 0,
				),
				'grey-white'   => array(
					'min_days'    => 230,
					'min_classes' => 48,
				),
				'grey'         => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'grey-black'   => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'yellow-white' => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'yellow'       => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'yellow-black' => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'orange-white' => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'orange'       => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'orange-black' => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'green-white'  => array(
					'min_days'    => 340,
					'min_classes' => 64,
				),
				'green'        => array(
					'min_days'    => 300,
					'min_classes' => 60,
				),
				'green-black'  => array(
					'min_days'    => 300,
					'min_classes' => 60,
				),
			),
			'kickboxing' => array(
				'level-1' => array(
					'min_days'    => 0,
					'min_classes' => 0,
				),
				'level-2' => array(
					'min_days'    => 500,
					'min_classes' => 200,
				),
			),
		);
	}

	/**
	 * Adult BJJ rank hierarchy.
	 *
	 * White through Brown have 4 stripes each. Black Belt uses degrees (up to 10).
	 * Follows IBJJF graduation system rules.
	 *
	 * @return array
	 */
	private static function get_adult_bjj_ranks(): array {
		return array(
			array(
				'name'        => __( 'White Belt', 'gym-core' ),
				'slug'        => 'white',
				'color'       => '#ffffff',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Blue Belt', 'gym-core' ),
				'slug'        => 'blue',
				'color'       => '#1e40af',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Purple Belt', 'gym-core' ),
				'slug'        => 'purple',
				'color'       => '#7c3aed',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Brown Belt', 'gym-core' ),
				'slug'        => 'brown',
				'color'       => '#78350f',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Black Belt', 'gym-core' ),
				'slug'        => 'black',
				'color'       => '#000000',
				'max_stripes' => 10,
				'type'        => 'degree',
			),
		);
	}

	/**
	 * Kids BJJ belt hierarchy (13 belts, 4 stripes each).
	 *
	 * Follows IBJJF youth graduation system. At age 16, students transition
	 * to adult ranks (eligible for White, Blue, or Purple based on experience).
	 *
	 * @return array
	 */
	private static function get_kids_bjj_ranks(): array {
		return array(
			array(
				'name'        => __( 'White Belt', 'gym-core' ),
				'slug'        => 'white',
				'color'       => '#ffffff',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Grey/White Belt', 'gym-core' ),
				'slug'        => 'grey-white',
				'color'       => '#d1d5db',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Grey Belt', 'gym-core' ),
				'slug'        => 'grey',
				'color'       => '#9ca3af',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Grey/Black Belt', 'gym-core' ),
				'slug'        => 'grey-black',
				'color'       => '#6b7280',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Yellow/White Belt', 'gym-core' ),
				'slug'        => 'yellow-white',
				'color'       => '#fef3c7',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Yellow Belt', 'gym-core' ),
				'slug'        => 'yellow',
				'color'       => '#fbbf24',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Yellow/Black Belt', 'gym-core' ),
				'slug'        => 'yellow-black',
				'color'       => '#b45309',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Orange/White Belt', 'gym-core' ),
				'slug'        => 'orange-white',
				'color'       => '#fed7aa',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Orange Belt', 'gym-core' ),
				'slug'        => 'orange',
				'color'       => '#f97316',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Orange/Black Belt', 'gym-core' ),
				'slug'        => 'orange-black',
				'color'       => '#c2410c',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Green/White Belt', 'gym-core' ),
				'slug'        => 'green-white',
				'color'       => '#bbf7d0',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Green Belt', 'gym-core' ),
				'slug'        => 'green',
				'color'       => '#22c55e',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
			array(
				'name'        => __( 'Green/Black Belt', 'gym-core' ),
				'slug'        => 'green-black',
				'color'       => '#15803d',
				'max_stripes' => 4,
				'type'        => 'belt',
			),
		);
	}

	/**
	 * Kickboxing level hierarchy.
	 *
	 * Simple two-level progression. No stripes — students are at Level 1 or Level 2.
	 * This is not a belt system; levels indicate progression milestones.
	 *
	 * @return array
	 */
	private static function get_kickboxing_ranks(): array {
		return array(
			array(
				'name'        => __( 'Level 1', 'gym-core' ),
				'slug'        => 'level-1',
				'color'       => '#3b82f6',
				'max_stripes' => 0,
				'type'        => 'level',
			),
			array(
				'name'        => __( 'Level 2', 'gym-core' ),
				'slug'        => 'level-2',
				'color'       => '#ef4444',
				'max_stripes' => 0,
				'type'        => 'level',
			),
		);
	}
}
