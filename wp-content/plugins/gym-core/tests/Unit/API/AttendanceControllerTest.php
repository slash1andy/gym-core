<?php
/**
 * Unit tests for AttendanceController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\AttendanceController;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\CheckInValidator;
use Gym_Core\Gamification\StreakTracker;
use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for the AttendanceController REST endpoint handlers.
 *
 * Each test stubs the WordPress functions used by the handler
 * under test so no database or WordPress install is required.
 */
class AttendanceControllerTest extends TestCase {

/**
	 * The System Under Test.
	 *
	 * @var AttendanceController
	 */
	private AttendanceController $sut;

	/**
	 * Mock attendance store.
	 *
	 * @var AttendanceStore&\Mockery\MockInterface
	 */
	private AttendanceStore $store;

	/**
	 * Mock check-in validator.
	 *
	 * @var CheckInValidator&\Mockery\MockInterface
	 */
	private CheckInValidator $validator;

	/**
	 * Mock streak tracker.
	 *
	 * @var StreakTracker&\Mockery\MockInterface
	 */
	private StreakTracker $streak_tracker;

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

		$this->store          = Mockery::mock( AttendanceStore::class );
		$this->validator      = Mockery::mock( CheckInValidator::class );
		$this->streak_tracker = Mockery::mock( StreakTracker::class );

		$this->sut = new AttendanceController( $this->store, $this->validator, $this->streak_tracker );
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
	 * Builds a mock attendance record as returned by AttendanceStore.
	 *
	 * @param array<string, mixed> $fields Overrides for record properties.
	 * @return \stdClass
	 */
	private function make_record( array $fields = [] ): \stdClass {
		$defaults = array(
			'id'            => 1,
			'user_id'       => 7,
			'class_id'      => 10,
			'location'      => 'rockford',
			'checked_in_at' => '2026-04-04 18:00:00',
			'method'        => 'qr_scan',
			'display_name'  => 'John Doe',
		);

		return (object) array_merge( $defaults, $fields );
	}

/**
	 * Stubs common functions needed for a successful check_in call.
	 *
	 * @return void
	 */
	private function stub_checkin_helpers(): void {
		$user                = new \stdClass();
		$user->display_name  = 'John Doe';
		Functions\when( 'get_userdata' )->justReturn( $user );

		$class              = new \stdClass();
		$class->post_title  = 'Adult BJJ';
		Functions\when( 'get_post' )->justReturn( $class );
	}

	// -------------------------------------------------------------------------
	// check_in
	// -------------------------------------------------------------------------

	#[TestDox('check_in should return 201 on successful check-in.')]
	public function test_check_in_returns_201_on_success(): void {
		// Location from class terms.
		$location_term       = new \stdClass();
		$location_term->slug = 'rockford';

		Functions\when( 'get_the_terms' )->justReturn( array( $location_term ) );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$this->validator->allows( 'validate' )->with( 7, 10, 'rockford' )->andReturn( true );
		$this->store->allows( 'record_checkin' )->with( 7, 'rockford', 10, 'qr_scan' )->andReturn( 42 );
		$this->streak_tracker->allows( 'get_streak' )->with( 7 )->andReturn( array( 'current_streak' => 5 ) );

		$this->stub_checkin_helpers();

		$request  = $this->make_request(
			array(
				'user_id'  => 7,
				'class_id' => 10,
				'method'   => 'qr_scan',
				'location' => 'rockford',
			)
		);
		$response = $this->sut->check_in( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 42, $body['data']['attendance_id'] );
		$this->assertSame( 7, $body['data']['user']['id'] );
		$this->assertSame( 'John Doe', $body['data']['user']['name'] );
		$this->assertSame( 10, $body['data']['class']['id'] );
		$this->assertSame( 'Adult BJJ', $body['data']['class']['name'] );
		$this->assertSame( 'rockford', $body['data']['location'] );
		$this->assertSame( 'qr_scan', $body['data']['method'] );
	}

	#[TestDox('check_in should return error when validator fails.')]
	public function test_check_in_returns_error_when_validation_fails(): void {
		$location_term       = new \stdClass();
		$location_term->slug = 'rockford';

		Functions\when( 'get_the_terms' )->justReturn( array( $location_term ) );

		$validation_error = new \WP_Error( 'no_active_membership', 'Member does not have an active membership.' );

		Functions\when( 'is_wp_error' )->alias(
			static function ( mixed $val ): bool {
				return $val instanceof \WP_Error;
			}
		);

		$this->validator->allows( 'validate' )->with( 7, 10, 'rockford' )->andReturn( $validation_error );

		$request = $this->make_request(
			array(
				'user_id'  => 7,
				'class_id' => 10,
				'method'   => 'qr_scan',
				'location' => 'rockford',
			)
		);
		$result = $this->sut->check_in( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'no_active_membership', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}

	#[TestDox('check_in should return 500 when store record_checkin returns false.')]
	public function test_check_in_returns_500_when_store_fails(): void {
		$location_term       = new \stdClass();
		$location_term->slug = 'rockford';

		Functions\when( 'get_the_terms' )->justReturn( array( $location_term ) );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$this->validator->allows( 'validate' )->andReturn( true );
		$this->store->allows( 'record_checkin' )->andReturn( false );

		$request = $this->make_request(
			array(
				'user_id'  => 7,
				'class_id' => 10,
				'method'   => 'manual',
				'location' => 'rockford',
			)
		);
		$result = $this->sut->check_in( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'checkin_failed', $result->get_error_code() );
		$this->assertSame( array( 'status' => 500 ), $result->get_error_data() );
	}

	#[TestDox('check_in should include streak data when streak_tracker is provided.')]
	public function test_check_in_includes_streak_data(): void {
		$location_term       = new \stdClass();
		$location_term->slug = 'beloit';

		Functions\when( 'get_the_terms' )->justReturn( array( $location_term ) );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$this->validator->allows( 'validate' )->andReturn( true );
		$this->store->allows( 'record_checkin' )->andReturn( 99 );
		$this->streak_tracker->expects( 'get_streak' )->with( 12 )->andReturn( array( 'current_streak' => 8 ) );

		$this->stub_checkin_helpers();

		$request  = $this->make_request(
			array(
				'user_id'  => 12,
				'class_id' => 5,
				'method'   => 'member_id',
				'location' => 'beloit',
			)
		);
		$response = $this->sut->check_in( $request );

		$body = $response->get_data();
		$this->assertArrayHasKey( 'current_streak', $body['data'] );
		$this->assertSame( 8, $body['data']['current_streak'] );
	}

	// -------------------------------------------------------------------------
	// get_history
	// -------------------------------------------------------------------------

	#[TestDox('get_history should return paginated attendance records.')]
	public function test_get_history_returns_paginated_records(): void {
		$record = $this->make_record( array( 'id' => 5, 'class_id' => 10 ) );

		$this->store->allows( 'get_user_history' )->with( 7, 10, 0, '', '' )->andReturn( array( $record ) );
		$this->store->allows( 'get_total_count' )->with( 7, '' )->andReturn( 1 );

		$class              = new \stdClass();
		$class->ID          = 10;
		$class->post_title  = 'Adult BJJ';
		Functions\when( 'get_post' )->justReturn( $class );
		Functions\when( '_prime_post_caches' )->justReturn( null );

		$request  = $this->make_request(
			array(
				'user_id'  => 7,
				'per_page' => 10,
				'page'     => 1,
				'from'     => null,
				'to'       => null,
			)
		);
		$response = $this->sut->get_history( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 1, $body['data'] );
		$this->assertSame( 5, $body['data'][0]['id'] );
		$this->assertSame( 10, $body['data'][0]['class']['id'] );
		$this->assertSame( 'Adult BJJ', $body['data'][0]['class']['name'] );
		$this->assertSame( 'rockford', $body['data'][0]['location'] );
		$this->assertSame( 'qr_scan', $body['data'][0]['method'] );

		// Pagination meta.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 1, $body['meta']['pagination']['total'] );
		$this->assertSame( 1, $body['meta']['pagination']['total_pages'] );
		$this->assertSame( 1, $body['meta']['pagination']['page'] );
		$this->assertSame( 10, $body['meta']['pagination']['per_page'] );
	}

	// -------------------------------------------------------------------------
	// get_today
	// -------------------------------------------------------------------------

	#[TestDox('get_today should return formatted records filtered by location.')]
	public function test_get_today_returns_records_filtered_by_location(): void {
		$record = $this->make_record(
			array(
				'id'            => 3,
				'user_id'       => 15,
				'class_id'      => 10,
				'location'      => 'rockford',
				'checked_in_at' => '2026-04-04 09:30:00',
				'method'        => 'name_search',
				'display_name'  => 'Jane Smith',
			)
		);

		$this->store->allows( 'get_today_by_location' )->with( 'rockford' )->andReturn( array( $record ) );

		$request  = $this->make_request( array( 'location' => 'rockford', 'class_id' => null ) );
		$response = $this->sut->get_today( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 1, $body['data'] );
		$this->assertSame( 3, $body['data'][0]['id'] );
		$this->assertSame( 15, $body['data'][0]['user']['id'] );
		$this->assertSame( 'Jane Smith', $body['data'][0]['user']['name'] );
		$this->assertSame( 10, $body['data'][0]['class_id'] );
		$this->assertSame( 'rockford', $body['data'][0]['location'] );
		$this->assertSame( 'name_search', $body['data'][0]['method'] );

		// Meta should contain total count.
		$this->assertSame( 1, $body['meta']['total'] );
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	#[TestDox('permissions_check_in should return true when user has gym_check_in_member capability.')]
	public function test_permissions_check_in_returns_true_with_capability(): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'gym_check_in_member' === $cap;
			}
		);

		$request = $this->make_request();
		$result  = $this->sut->permissions_check_in( $request );

		$this->assertTrue( $result );
	}

	#[TestDox('permissions_check_in should return WP_Error when user lacks capability.')]
	public function test_permissions_check_in_returns_error_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->make_request();
		$result  = $this->sut->permissions_check_in( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}
}
