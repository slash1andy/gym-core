<?php
/**
 * Unit tests for BriefingGenerator.
 *
 * We focus on the two guard-clause paths that return WP_Error without
 * touching the database — passing a non-existent post ID and passing a
 * post of the wrong type. Full happy-path coverage belongs in integration
 * tests (requires $wpdb + WP_Query).
 *
 * @package Gym_Core\Tests\Unit\Briefing
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Briefing;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Attendance\PromotionEligibility;
use Gym_Core\Briefing\BriefingGenerator;
use Gym_Core\Rank\RankStore;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Tests for BriefingGenerator guard clauses.
 */
class BriefingGeneratorTest extends TestCase {

	private BriefingGenerator $generator;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'__' => static function ( string $text ): string {
					return $text;
				},
			)
		);

		$this->generator = new BriefingGenerator(
			Mockery::mock( AttendanceStore::class ),
			Mockery::mock( RankStore::class ),
			Mockery::mock( FoundationsClearance::class ),
			Mockery::mock( PromotionEligibility::class )
		);
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Passing an ID whose get_post() returns null yields a WP_Error.
	 */
	public function test_generate_invalid_id_returns_wp_error(): void {
		Functions\expect( 'get_post' )
			->once()
			->with( 9999 )
			->andReturn( null );

		$result = $this->generator->generate( 9999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_class', $result->get_error_code() );
	}

	/**
	 * Passing a post whose post_type is not gym_class yields a WP_Error.
	 */
	public function test_generate_wrong_post_type_returns_wp_error(): void {
		$post            = new \stdClass();
		$post->ID        = 123;
		$post->post_type = 'post'; // Not gym_class.

		Functions\expect( 'get_post' )
			->once()
			->with( 123 )
			->andReturn( $post );

		$result = $this->generator->generate( 123 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_class', $result->get_error_code() );
	}
}
