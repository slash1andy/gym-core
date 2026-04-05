<?php
/**
 * Unit tests for PromotionController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\PromotionController;
use Gym_Core\Attendance\PromotionEligibility;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the PromotionController REST endpoint handlers.
 *
 * Each test stubs the WordPress/WooCommerce functions used by the handler
 * under test so no database or WordPress install is required.
 */
class PromotionControllerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var PromotionController
	 */
	private PromotionController $sut;

	/**
	 * Mock Promotion Eligibility engine.
	 *
	 * @var PromotionEligibility&\Mockery\MockInterface
	 */
	private PromotionEligibility $eligibility;

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

		$this->eligibility = Mockery::mock( PromotionEligibility::class );
		$this->sut         = new PromotionController( $this->eligibility );
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

	// -------------------------------------------------------------------------
	// get_eligible
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_eligible should return paginated eligible members.
	 */
	public function test_get_eligible_returns_paginated_eligible_members(): void {
		$members = array(
			array( 'user_id' => 5, 'name' => 'Alice', 'status' => 'eligible' ),
			array( 'user_id' => 8, 'name' => 'Bob', 'status' => 'eligible' ),
			array( 'user_id' => 12, 'name' => 'Charlie', 'status' => 'approaching' ),
		);

		$request = $this->make_request(
			array(
				'program'  => 'adult-bjj',
				'page'     => 1,
				'per_page' => 2,
			)
		);

		$this->eligibility->allows( 'get_eligible_members' )
			->with( 'adult-bjj' )
			->andReturn( $members );

		$response = $this->sut->get_eligible( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 2, $body['data'] );
		$this->assertSame( 5, $body['data'][0]['user_id'] );
		$this->assertSame( 8, $body['data'][1]['user_id'] );

		// Pagination meta.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 3, $body['meta']['pagination']['total'] );
		$this->assertSame( 2, $body['meta']['pagination']['total_pages'] );
		$this->assertSame( 1, $body['meta']['pagination']['page'] );
		$this->assertSame( 2, $body['meta']['pagination']['per_page'] );
	}

	/**
	 * @testdox get_eligible should return empty data when no eligible members exist.
	 */
	public function test_get_eligible_returns_empty_when_no_eligible_members(): void {
		$request = $this->make_request(
			array(
				'program'  => 'adult-bjj',
				'page'     => 1,
				'per_page' => 10,
			)
		);

		$this->eligibility->allows( 'get_eligible_members' )
			->with( 'adult-bjj' )
			->andReturn( array() );

		$response = $this->sut->get_eligible( $request );
		$body     = $response->get_data();

		$this->assertTrue( $body['success'] );
		$this->assertSame( array(), $body['data'] );
		$this->assertSame( 0, $body['meta']['pagination']['total'] );
	}

	// -------------------------------------------------------------------------
	// recommend
	// -------------------------------------------------------------------------

	/**
	 * @testdox recommend should return 201 on success.
	 */
	public function test_recommend_returns_201_on_success(): void {
		$request = $this->make_request(
			array(
				'user_id' => 5,
				'program' => 'adult-bjj',
			)
		);

		$user               = new \stdClass();
		$user->display_name = 'Test Student';
		Functions\when( 'get_userdata' )->justReturn( $user );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		$this->eligibility->expects( 'set_recommendation' )
			->once()
			->with( 5, 'adult-bjj', 1 );

		$response = $this->sut->recommend( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 5, $body['data']['user_id'] );
		$this->assertSame( 'adult-bjj', $body['data']['program'] );
		$this->assertSame( 1, $body['data']['recommended_by'] );
		$this->assertArrayHasKey( 'recommended_at', $body['data'] );
	}

	/**
	 * @testdox recommend should return 404 for a non-existent user.
	 */
	public function test_recommend_returns_404_for_nonexistent_user(): void {
		$request = $this->make_request(
			array(
				'user_id' => 999,
				'program' => 'adult-bjj',
			)
		);

		Functions\when( 'get_userdata' )->justReturn( false );

		$result = $this->sut->recommend( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_user', $result->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// permissions_coach
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_coach should return true when user has gym_promote_student.
	 */
	public function test_permissions_coach_returns_true_with_capability(): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'gym_promote_student' === $cap;
			}
		);

		$request = $this->make_request();
		$result  = $this->sut->permissions_coach( $request );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox permissions_coach should return WP_Error when user lacks capability.
	 */
	public function test_permissions_coach_returns_error_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->make_request();
		$result  = $this->sut->permissions_coach( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}
}
