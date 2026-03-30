<?php
/**
 * Belt rank hierarchy definitions per program.
 *
 * Defines the ordered belt progression for each martial arts program.
 * Adult BJJ: White → Blue → Purple → Brown → Black (4 stripes each).
 * Kids BJJ: 13 belt levels (4 stripes each) — exact names TBD from Darby.
 * Kickboxing: Level 1, Level 2.
 *
 * @package Gym_Core
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Rank;

/**
 * Provides belt rank definitions and ordering for all programs.
 */
final class RankDefinitions {

	/**
	 * Returns the belt hierarchy for a given program.
	 *
	 * Each belt is an array with 'name', 'slug', 'color', and 'max_stripes'.
	 * Belts are ordered from lowest to highest.
	 *
	 * @since 1.2.0
	 *
	 * @param string $program Program slug (adult-bjj, kids-bjj, kickboxing).
	 * @return array<int, array{name: string, slug: string, color: string, max_stripes: int}>
	 */
	public static function get_belts( string $program ): array {
		$definitions = self::get_all_definitions();

		return $definitions[ $program ] ?? array();
	}

	/**
	 * Returns all program definitions.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array<int, array{name: string, slug: string, color: string, max_stripes: int}>>
	 */
	public static function get_all_definitions(): array {
		$definitions = array(
			'adult-bjj'  => self::get_adult_bjj_belts(),
			'kids-bjj'   => self::get_kids_bjj_belts(),
			'kickboxing' => self::get_kickboxing_belts(),
		);

		/**
		 * Filters the belt rank definitions for all programs.
		 *
		 * Use this to add custom programs or modify belt hierarchies.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, array> $definitions Program slug => belt array.
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

		/**
		 * Filters the available programs.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, string> $programs Slug => label.
		 */
		return apply_filters( 'gym_core_programs', $programs );
	}

	/**
	 * Returns the ordinal position of a belt within its program (0-based).
	 *
	 * @since 1.2.0
	 *
	 * @param string $program   Program slug.
	 * @param string $belt_slug Belt slug.
	 * @return int|null Position, or null if not found.
	 */
	public static function get_belt_position( string $program, string $belt_slug ): ?int {
		$belts = self::get_belts( $program );

		foreach ( $belts as $index => $belt ) {
			if ( $belt['slug'] === $belt_slug ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Returns the next belt after the given belt in the hierarchy.
	 *
	 * @since 1.2.0
	 *
	 * @param string $program   Program slug.
	 * @param string $belt_slug Current belt slug.
	 * @return array{name: string, slug: string, color: string, max_stripes: int}|null Next belt, or null if at highest.
	 */
	public static function get_next_belt( string $program, string $belt_slug ): ?array {
		$belts    = self::get_belts( $program );
		$position = self::get_belt_position( $program, $belt_slug );

		if ( null === $position || $position >= count( $belts ) - 1 ) {
			return null;
		}

		return $belts[ $position + 1 ];
	}

	/**
	 * Adult BJJ belt hierarchy.
	 *
	 * @return array<int, array{name: string, slug: string, color: string, max_stripes: int}>
	 */
	private static function get_adult_bjj_belts(): array {
		return array(
			array(
				'name'        => __( 'White Belt', 'gym-core' ),
				'slug'        => 'white',
				'color'       => '#ffffff',
				'max_stripes' => 4,
			),
			array(
				'name'        => __( 'Blue Belt', 'gym-core' ),
				'slug'        => 'blue',
				'color'       => '#1e40af',
				'max_stripes' => 4,
			),
			array(
				'name'        => __( 'Purple Belt', 'gym-core' ),
				'slug'        => 'purple',
				'color'       => '#7c3aed',
				'max_stripes' => 4,
			),
			array(
				'name'        => __( 'Brown Belt', 'gym-core' ),
				'slug'        => 'brown',
				'color'       => '#78350f',
				'max_stripes' => 4,
			),
			array(
				'name'        => __( 'Black Belt', 'gym-core' ),
				'slug'        => 'black',
				'color'       => '#000000',
				'max_stripes' => 4,
			),
		);
	}

	/**
	 * Kids BJJ belt hierarchy.
	 *
	 * Placeholder names — exact belt names to be confirmed by Darby via
	 * CoWork Playbook 4 (Spark Belt Rank Definitions).
	 *
	 * @return array<int, array{name: string, slug: string, color: string, max_stripes: int}>
	 */
	private static function get_kids_bjj_belts(): array {
		return array(
			array( 'name' => __( 'White Belt', 'gym-core' ), 'slug' => 'white', 'color' => '#ffffff', 'max_stripes' => 4 ),
			array( 'name' => __( 'Grey/White Belt', 'gym-core' ), 'slug' => 'grey-white', 'color' => '#d1d5db', 'max_stripes' => 4 ),
			array( 'name' => __( 'Grey Belt', 'gym-core' ), 'slug' => 'grey', 'color' => '#9ca3af', 'max_stripes' => 4 ),
			array( 'name' => __( 'Grey/Black Belt', 'gym-core' ), 'slug' => 'grey-black', 'color' => '#6b7280', 'max_stripes' => 4 ),
			array( 'name' => __( 'Yellow/White Belt', 'gym-core' ), 'slug' => 'yellow-white', 'color' => '#fef3c7', 'max_stripes' => 4 ),
			array( 'name' => __( 'Yellow Belt', 'gym-core' ), 'slug' => 'yellow', 'color' => '#fbbf24', 'max_stripes' => 4 ),
			array( 'name' => __( 'Yellow/Black Belt', 'gym-core' ), 'slug' => 'yellow-black', 'color' => '#b45309', 'max_stripes' => 4 ),
			array( 'name' => __( 'Orange/White Belt', 'gym-core' ), 'slug' => 'orange-white', 'color' => '#fed7aa', 'max_stripes' => 4 ),
			array( 'name' => __( 'Orange Belt', 'gym-core' ), 'slug' => 'orange', 'color' => '#f97316', 'max_stripes' => 4 ),
			array( 'name' => __( 'Orange/Black Belt', 'gym-core' ), 'slug' => 'orange-black', 'color' => '#c2410c', 'max_stripes' => 4 ),
			array( 'name' => __( 'Green/White Belt', 'gym-core' ), 'slug' => 'green-white', 'color' => '#bbf7d0', 'max_stripes' => 4 ),
			array( 'name' => __( 'Green Belt', 'gym-core' ), 'slug' => 'green', 'color' => '#22c55e', 'max_stripes' => 4 ),
			array( 'name' => __( 'Green/Black Belt', 'gym-core' ), 'slug' => 'green-black', 'color' => '#15803d', 'max_stripes' => 4 ),
		);
	}

	/**
	 * Kickboxing level hierarchy.
	 *
	 * @return array<int, array{name: string, slug: string, color: string, max_stripes: int}>
	 */
	private static function get_kickboxing_belts(): array {
		return array(
			array(
				'name'        => __( 'Level 1', 'gym-core' ),
				'slug'        => 'level-1',
				'color'       => '#3b82f6',
				'max_stripes' => 0,
			),
			array(
				'name'        => __( 'Level 2', 'gym-core' ),
				'slug'        => 'level-2',
				'color'       => '#ef4444',
				'max_stripes' => 0,
			),
		);
	}
}
