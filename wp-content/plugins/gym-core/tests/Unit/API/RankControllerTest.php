<?php
/**
 * Unit tests for RankController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\RankController;
use Gym_Core\Rank\RankStore;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Attendance\AttendanceStore;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the RankController REST endpoint handlers.
 *
 * Each test stubs the WordPress/WooCommerce functions used by the handler
 * under test so no database or WordPress install is required.
 */
class RankControllerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var RankController
	 */
	private RankController $sut;

	/**
	 * Mock Rank Store.
	 *
	 * @var RankStore&\Mockery\MockInterface
	 */
	private RankStore $ranks;

	/**
	 * Mock Attendance Store.
	 *
	 * @var AttendanceStore&\Mockery\MockInterface
	 */
	private AttendanceStore $attendance;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Passthrough stubs for common WP utility functions used in every handler.
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);

		$this->ranks      = Mockery::mock( RankStore::class );
		$this->attendance  = Mockery::mock( AttendanceStore::class );
		$this->sut         = new RankController( $this->ranks, $this->attendance );
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
	 * Builds a rank record object with the given field values.
	 *
	 * @param array<string, mixed> $fields Overrides for rank properties.
	 * @return object
	 */
	private function make_rank( array $fields = [] ): object {
		return (object) array_merge(
			array(
				'program'     => 'adult-bjj',
				'belt'        => 'white',
				'stripes'     => 2,
				'promoted_at' => '2025-06-15 10:00:00',
				'promoted_by' => 1,
			),
			$fields
		);
	}

	/**
	 * Builds a rank history record object.
	 *
	 * @param array<string, mixed> $fields Overrides for history properties.
	 * @return object
	 */
	private function make_history_record( array $fields = [] ): object {
		return (object) array_merge(
			array(
				'program'      => 'adult-bjj',
				'from_belt'    => 'white',
				'from_stripes' => 3,
				'to_belt'      => 'blue',
				'to_stripes'   => 0,
				'promoted_at'  => '2025-09-01 14:30:00',
				'promoted_by'  => 1,
				'notes'        => 'Great progress.',
			),
			$fields
		);
	}

	/**
	 * Stubs get_userdata to return a mock user with the given display name.
	 *
	 * @param string $display_name Display name for the mock user.
	 * @return void
	 */
	private function stub_userdata( string $display_name = 'Coach Darby' ): void {
		$user               = new \stdClass();
		$user->display_name = $display_name;

		Functions\when( 'get_userdata' )->justReturn( $user );
	}

	/**
	 * Stubs the WordPress filter functions so the real RankDefinitions static
	 * methods work without a running WordPress environment.
	 *
	 * @return void
	 */
	private function stub_rank_definitions(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	// -------------------------------------------------------------------------
	// get_rank
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_rank should return empty array when no rank exists for the given program.
	 */
	public function test_get_rank_returns_empty_array_when_no_rank_for_program(): void {
		$request = $this->make_request( array( 'id' => 5, 'program' => 'adult-bjj' ) );

		$this->ranks->allows( 'get_rank' )->with( 5, 'adult-bjj' )->andReturn( null );

		$response = $this->sut->get_rank( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( array(), $body['data'] );
	}

	/**
	 * @testdox get_rank should return formatted rank data for a specific program.
	 */
	public function test_get_rank_returns_formatted_rank_for_specific_program(): void {
		$rank    = $this->make_rank( array( 'belt' => 'blue', 'stripes' => 1 ) );
		$request = $this->make_request( array( 'id' => 5, 'program' => 'adult-bjj' ) );

		$this->ranks->allows( 'get_rank' )->with( 5, 'adult-bjj' )->andReturn( $rank );
		$this->attendance->allows( 'get_count_since' )->with( 5, '2025-06-15 10:00:00' )->andReturn( 42 );

		$this->stub_userdata( 'Coach Darby' );
		$this->stub_rank_definitions();

		$response = $this->sut->get_rank( $request );
		$body     = $response->get_data();

		$this->assertTrue( $body['success'] );
		$this->assertCount( 1, $body['data'] );
		$this->assertSame( 'adult-bjj', $body['data'][0]['program'] );
		$this->assertSame( 'blue', $body['data'][0]['belt'] );
		$this->assertSame( 1, $body['data'][0]['stripes'] );
		$this->assertSame( 42, $body['data'][0]['attendance_since_promotion'] );
		$this->assertSame( 'purple', $body['data'][0]['next_belt'] );
		$this->assertSame( 'Coach Darby', $body['data'][0]['promoted_by']['name'] );
	}

	/**
	 * @testdox get_rank should return all ranks when no program is specified.
	 */
	public function test_get_rank_returns_all_ranks_when_no_program_specified(): void {
		$rank_bjj  = $this->make_rank( array( 'program' => 'adult-bjj', 'belt' => 'white', 'stripes' => 3 ) );
		$rank_kids = $this->make_rank( array( 'program' => 'kids-bjj', 'belt' => 'yellow', 'stripes' => 0 ) );

		$request = $this->make_request( array( 'id' => 5 ) );

		$this->ranks->allows( 'get_all_ranks' )->with( 5 )->andReturn( array( $rank_bjj, $rank_kids ) );
		$this->attendance->allows( 'get_count_since' )->andReturn( 10 );

		$this->stub_userdata( 'Coach Darby' );
		$this->stub_rank_definitions();

		$response = $this->sut->get_rank( $request );
		$body     = $response->get_data();

		$this->assertTrue( $body['success'] );
		$this->assertCount( 2, $body['data'] );
		$this->assertSame( 'adult-bjj', $body['data'][0]['program'] );
		$this->assertSame( 'kids-bjj', $body['data'][1]['program'] );
	}

	// -------------------------------------------------------------------------
	// get_rank_history
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_rank_history should return paginated promotion history.
	 */
	public function test_get_rank_history_returns_paginated_history(): void {
		$records = array(
			$this->make_history_record( array( 'to_belt' => 'blue', 'to_stripes' => 0 ) ),
			$this->make_history_record( array( 'to_belt' => 'blue', 'to_stripes' => 1 ) ),
			$this->make_history_record( array( 'to_belt' => 'blue', 'to_stripes' => 2 ) ),
		);

		$request = $this->make_request(
			array(
				'id'       => 5,
				'program'  => 'adult-bjj',
				'page'     => 1,
				'per_page' => 2,
			)
		);

		$this->ranks->allows( 'get_history' )->with( 5, 'adult-bjj' )->andReturn( $records );

		$this->stub_userdata( 'Coach Darby' );

		$response = $this->sut->get_rank_history( $request );
		$body     = $response->get_data();

		$this->assertTrue( $body['success'] );
		$this->assertCount( 2, $body['data'] );
		$this->assertSame( 'blue', $body['data'][0]['to_belt'] );
		$this->assertSame( 0, $body['data'][0]['to_stripes'] );

		// Pagination meta.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 3, $body['meta']['pagination']['total'] );
		$this->assertSame( 2, $body['meta']['pagination']['total_pages'] );
		$this->assertSame( 1, $body['meta']['pagination']['page'] );
		$this->assertSame( 2, $body['meta']['pagination']['per_page'] );
	}

	// -------------------------------------------------------------------------
	// promote
	// -------------------------------------------------------------------------

	/**
	 * @testdox promote should return 404 for a non-existent user.
	 */
	public function test_promote_returns_404_for_nonexistent_user(): void {
		$request = $this->make_request(
			array(
				'user_id' => 999,
				'program' => 'adult-bjj',
				'belt'    => 'blue',
				'stripes' => 0,
				'notes'   => '',
			)
		);

		Functions\when( 'get_userdata' )->justReturn( false );

		$result = $this->sut->promote( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_user', $result->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	/**
	 * @testdox promote should return 400 for an invalid program.
	 */
	public function test_promote_returns_400_for_invalid_program(): void {
		$request = $this->make_request(
			array(
				'user_id' => 5,
				'program' => 'underwater-basket-weaving',
				'belt'    => 'blue',
				'stripes' => 0,
				'notes'   => '',
			)
		);

		$this->stub_userdata( 'Test User' );
		$this->stub_rank_definitions();

		$result = $this->sut->promote( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_program', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	/**
	 * @testdox promote should add a stripe when no belt is specified.
	 */
	public function test_promote_adds_stripe_when_no_belt_specified(): void {
		$request = $this->make_request(
			array(
				'user_id' => 5,
				'program' => 'adult-bjj',
				'belt'    => null,
				'stripes' => null,
				'notes'   => 'Good work',
			)
		);

		$this->stub_userdata( 'Coach Darby' );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		$this->stub_rank_definitions();

		$updated_rank = $this->make_rank( array( 'belt' => 'white', 'stripes' => 3 ) );

		$this->ranks->expects( 'add_stripe' )
			->once()
			->with( 5, 'adult-bjj', 1, 'Good work' )
			->andReturn( true );

		$this->ranks->allows( 'get_rank' )->with( 5, 'adult-bjj' )->andReturn( $updated_rank );
		$this->attendance->allows( 'get_count_since' )->andReturn( 15 );

		$response = $this->sut->promote( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 'white', $body['data']['belt'] );
		$this->assertSame( 3, $body['data']['stripes'] );
	}

	// -------------------------------------------------------------------------
	// permissions_promote
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_promote should return true when user has gym_promote_student.
	 */
	public function test_permissions_promote_returns_true_with_gym_promote_student(): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'gym_promote_student' === $cap;
			}
		);

		$request = $this->make_request();
		$result  = $this->sut->permissions_promote( $request );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox permissions_promote should return WP_Error when user lacks capability.
	 */
	public function test_permissions_promote_returns_error_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->make_request();
		$result  = $this->sut->permissions_promote( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}
}
