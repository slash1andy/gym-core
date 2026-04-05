<?php
/**
 * Unit tests for GamificationController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\GamificationController;
use Gym_Core\Gamification\BadgeDefinitions;
use Gym_Core\Gamification\BadgeEngine;
use Gym_Core\Gamification\StreakTracker;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the GamificationController REST endpoint handlers.
 */
class GamificationControllerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var GamificationController
	 */
	private GamificationController $sut;

	/**
	 * Mock BadgeEngine.
	 *
	 * @var BadgeEngine&\Mockery\MockInterface
	 */
	private BadgeEngine $badges;

	/**
	 * Mock StreakTracker.
	 *
	 * @var StreakTracker&\Mockery\MockInterface
	 */
	private StreakTracker $streaks;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);

		$this->badges  = Mockery::mock( BadgeEngine::class );
		$this->streaks = Mockery::mock( StreakTracker::class );
		$this->sut     = new GamificationController( $this->badges, $this->streaks );
	}

	/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a mock WP_REST_Request with the given parameters.
	 *
	 * @param array<string, mixed> $params Query/body parameters.
	 * @return \WP_REST_Request&\Mockery\MockInterface
	 */
	private function make_request( array $params = [] ): \WP_REST_Request {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->allows( 'get_param' )->andReturnUsing(
			static function ( string $key ) use ( $params ): mixed {
				return $params[ $key ] ?? null;
			}
		);

		return $request;
	}

	/**
	 * Builds a mock earned badge object.
	 *
	 * @param string      $slug     Badge slug.
	 * @param string      $earned   Earned-at datetime.
	 * @param string|null $metadata JSON metadata or null.
	 * @return object
	 */
	private function make_earned_badge( string $slug, string $earned, ?string $metadata = null ): object {
		return (object) array(
			'badge_slug' => $slug,
			'earned_at'  => $earned,
			'metadata'   => $metadata,
		);
	}

	// -------------------------------------------------------------------------
	// get_badge_definitions
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_badge_definitions returns all badges for a public (not logged-in) request.
	 */
	public function test_get_badge_definitions_returns_all_for_public_request(): void {
		$real_definitions = BadgeDefinitions::get_all();

		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$request  = $this->make_request();
		$response = $this->sut->get_badge_definitions( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( count( $real_definitions ), $body['data'] );
		$this->assertSame( 'first_class', $body['data'][0]['slug'] );
		$this->assertSame( 'First Class', $body['data'][0]['name'] );

		// Public requests must NOT include earned status.
		$this->assertArrayNotHasKey( 'earned', $body['data'][0] );
		$this->assertArrayNotHasKey( 'earned_at', $body['data'][0] );
	}

	/**
	 * @testdox get_badge_definitions includes earned status for a logged-in user.
	 */
	public function test_get_badge_definitions_includes_earned_for_logged_in_user(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 42 );

		$this->badges->allows( 'get_user_badges' )
			->with( 42 )
			->andReturn( array(
				$this->make_earned_badge( 'first_class', '2026-03-15 14:30:00' ),
			) );

		$request  = $this->make_request();
		$response = $this->sut->get_badge_definitions( $request );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );

		// First badge (first_class) should be marked as earned.
		$this->assertTrue( $body['data'][0]['earned'] );
		$this->assertSame( '2026-03-15 14:30:00', $body['data'][0]['earned_at'] );

		// Second badge should not be earned.
		$this->assertFalse( $body['data'][1]['earned'] );
		$this->assertNull( $body['data'][1]['earned_at'] );
	}

	/**
	 * @testdox get_badge_definitions filters results by category when provided.
	 */
	public function test_get_badge_definitions_filters_by_category(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$request  = $this->make_request( array( 'category' => 'rank' ) );
		$response = $this->sut->get_badge_definitions( $request );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );

		// All returned badges must be in the rank category.
		foreach ( $body['data'] as $badge ) {
			$this->assertSame( 'rank', $badge['category'] );
		}
		$this->assertNotEmpty( $body['data'] );
	}

	// -------------------------------------------------------------------------
	// get_member_badges
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_member_badges returns formatted earned badges with metadata totals.
	 */
	public function test_get_member_badges_returns_formatted_badges_with_meta(): void {
		$real_definitions = BadgeDefinitions::get_all();

		$this->badges->allows( 'get_user_badges' )
			->with( 7 )
			->andReturn( array(
				$this->make_earned_badge( 'first_class', '2026-02-10 09:00:00', '{"class_id":101}' ),
				$this->make_earned_badge( 'ten_classes', '2026-03-20 11:00:00' ),
			) );

		$request  = $this->make_request( array( 'id' => 7 ) );
		$response = $this->sut->get_member_badges( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 2, $body['data'] );

		// First earned badge.
		$this->assertSame( 'first_class', $body['data'][0]['badge']['slug'] );
		$this->assertSame( 'First Class', $body['data'][0]['badge']['name'] );
		$this->assertSame( '2026-02-10 09:00:00', $body['data'][0]['earned_at'] );
		$this->assertSame( array( 'class_id' => 101 ), $body['data'][0]['metadata'] );

		// Second earned badge has null metadata.
		$this->assertNull( $body['data'][1]['metadata'] );

		// Meta totals.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 2, $body['meta']['total_badges_earned'] );
		$this->assertSame( count( $real_definitions ), $body['meta']['total_badges_available'] );
	}

	// -------------------------------------------------------------------------
	// get_member_streak
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_member_streak returns streak data for a member.
	 */
	public function test_get_member_streak_returns_streak_data(): void {
		$streak_data = array(
			'current_streak'     => 3,
			'longest_streak'     => 5,
			'last_training_week' => '2026-03-30',
		);

		$this->streaks->allows( 'get_streak' )
			->with( 15 )
			->andReturn( $streak_data );

		$request  = $this->make_request( array( 'id' => 15 ) );
		$response = $this->sut->get_member_streak( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 3, $body['data']['current_streak'] );
		$this->assertSame( 5, $body['data']['longest_streak'] );
		$this->assertSame( '2026-03-30', $body['data']['last_training_week'] );
	}

	// -------------------------------------------------------------------------
	// permissions_view_badges
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_view_badges allows access to own badge data.
	 */
	public function test_permissions_view_badges_allows_own_data(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 25 );

		$request = $this->make_request( array( 'id' => 25 ) );
		$result  = $this->sut->permissions_view_badges( $request );

		$this->assertTrue( $result );
	}
}
