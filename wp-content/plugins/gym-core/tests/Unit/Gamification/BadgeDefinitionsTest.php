<?php
/**
 * Unit tests for BadgeDefinitions.
 *
 * @package Gym_Core\Tests\Unit\Gamification
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Gamification;

use Gym_Core\Gamification\BadgeDefinitions;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for badge definitions.
 */
class BadgeDefinitionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'__' => static function ( string $text ): string {
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

	public function test_get_all_returns_all_badges(): void {
		$badges = BadgeDefinitions::get_all();

		$this->assertCount( 13, $badges );
	}

	public function test_every_badge_has_required_fields(): void {
		$badges   = BadgeDefinitions::get_all();
		$required = array( 'name', 'description', 'category', 'criteria_summary', 'icon' );

		foreach ( $badges as $slug => $badge ) {
			foreach ( $required as $field ) {
				$this->assertArrayHasKey( $field, $badge, "Badge '{$slug}' missing field '{$field}'" );
			}
		}
	}

	public function test_get_single_badge(): void {
		$badge = BadgeDefinitions::get( 'first_class' );

		$this->assertNotNull( $badge );
		$this->assertSame( 'First Class', $badge['name'] );
		$this->assertSame( BadgeDefinitions::CATEGORY_ATTENDANCE, $badge['category'] );
	}

	public function test_get_unknown_badge_returns_null(): void {
		$this->assertNull( BadgeDefinitions::get( 'nonexistent' ) );
	}

	public function test_attendance_thresholds_are_ascending(): void {
		$thresholds = BadgeDefinitions::get_attendance_thresholds();
		$counts     = array_keys( $thresholds );

		$sorted = $counts;
		sort( $sorted );

		$this->assertSame( $sorted, $counts );
	}

	public function test_attendance_thresholds_start_at_one(): void {
		$thresholds = BadgeDefinitions::get_attendance_thresholds();

		$this->assertArrayHasKey( 1, $thresholds );
		$this->assertSame( 'first_class', $thresholds[1] );
	}

	public function test_streak_thresholds_map_to_valid_badges(): void {
		$streaks = BadgeDefinitions::get_streak_thresholds();
		$badges  = BadgeDefinitions::get_all();

		foreach ( $streaks as $weeks => $slug ) {
			$this->assertArrayHasKey( $slug, $badges, "Streak badge '{$slug}' not in definitions" );
		}
	}

	public function test_all_categories_are_valid(): void {
		$badges = BadgeDefinitions::get_all();
		$valid  = array(
			BadgeDefinitions::CATEGORY_ATTENDANCE,
			BadgeDefinitions::CATEGORY_RANK,
			BadgeDefinitions::CATEGORY_SPECIAL,
		);

		foreach ( $badges as $slug => $badge ) {
			$this->assertContains( $badge['category'], $valid, "Badge '{$slug}' has invalid category '{$badge['category']}'" );
		}
	}

	public function test_belt_promotion_badge_exists(): void {
		$badge = BadgeDefinitions::get( 'belt_promotion' );

		$this->assertNotNull( $badge );
		$this->assertSame( BadgeDefinitions::CATEGORY_RANK, $badge['category'] );
	}

	public function test_special_badges_exist(): void {
		$this->assertNotNull( BadgeDefinitions::get( 'early_bird' ) );
		$this->assertNotNull( BadgeDefinitions::get( 'multi_program' ) );
	}
}
