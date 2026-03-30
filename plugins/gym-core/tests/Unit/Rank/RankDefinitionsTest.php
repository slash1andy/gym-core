<?php
/**
 * Unit tests for RankDefinitions.
 *
 * @package Gym_Core\Tests\Unit\Rank
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Rank;

use Gym_Core\Rank\RankDefinitions;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for belt rank definitions.
 */
class RankDefinitionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stub WordPress functions.
		Functions\stubs(
			array(
				'__'  => static function ( string $text ): string {
					return $text;
				},
				'_x'  => static function ( string $text ): string {
					return $text;
				},
				'apply_filters' => static function ( string $hook, $value ) {
					return $value;
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_adult_bjj_has_five_belts(): void {
		$belts = RankDefinitions::get_belts( 'adult-bjj' );

		$this->assertCount( 5, $belts );
	}

	public function test_adult_bjj_belt_order(): void {
		$belts = RankDefinitions::get_belts( 'adult-bjj' );
		$slugs = array_column( $belts, 'slug' );

		$this->assertSame( array( 'white', 'blue', 'purple', 'brown', 'black' ), $slugs );
	}

	public function test_kids_bjj_has_thirteen_belts(): void {
		$belts = RankDefinitions::get_belts( 'kids-bjj' );

		$this->assertCount( 13, $belts );
	}

	public function test_kickboxing_has_two_levels(): void {
		$belts = RankDefinitions::get_belts( 'kickboxing' );

		$this->assertCount( 2, $belts );
	}

	public function test_all_belts_have_max_stripes(): void {
		$belts = RankDefinitions::get_belts( 'adult-bjj' );

		foreach ( $belts as $belt ) {
			$this->assertArrayHasKey( 'max_stripes', $belt );
			$this->assertSame( 4, $belt['max_stripes'] );
		}
	}

	public function test_kickboxing_has_zero_stripes(): void {
		$belts = RankDefinitions::get_belts( 'kickboxing' );

		foreach ( $belts as $belt ) {
			$this->assertSame( 0, $belt['max_stripes'] );
		}
	}

	public function test_get_belt_position_returns_correct_index(): void {
		$this->assertSame( 0, RankDefinitions::get_belt_position( 'adult-bjj', 'white' ) );
		$this->assertSame( 2, RankDefinitions::get_belt_position( 'adult-bjj', 'purple' ) );
		$this->assertSame( 4, RankDefinitions::get_belt_position( 'adult-bjj', 'black' ) );
	}

	public function test_get_belt_position_returns_null_for_unknown(): void {
		$this->assertNull( RankDefinitions::get_belt_position( 'adult-bjj', 'red' ) );
	}

	public function test_get_next_belt_returns_blue_after_white(): void {
		$next = RankDefinitions::get_next_belt( 'adult-bjj', 'white' );

		$this->assertNotNull( $next );
		$this->assertSame( 'blue', $next['slug'] );
	}

	public function test_get_next_belt_returns_null_at_highest(): void {
		$next = RankDefinitions::get_next_belt( 'adult-bjj', 'black' );

		$this->assertNull( $next );
	}

	public function test_unknown_program_returns_empty_array(): void {
		$belts = RankDefinitions::get_belts( 'nonexistent' );

		$this->assertSame( array(), $belts );
	}

	public function test_get_programs_returns_three_programs(): void {
		$programs = RankDefinitions::get_programs();

		$this->assertCount( 3, $programs );
		$this->assertArrayHasKey( 'adult-bjj', $programs );
		$this->assertArrayHasKey( 'kids-bjj', $programs );
		$this->assertArrayHasKey( 'kickboxing', $programs );
	}
}
