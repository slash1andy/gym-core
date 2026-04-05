<?php
/**
 * Unit tests for FoundationsController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\FoundationsController;
use Gym_Core\Attendance\FoundationsClearance;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the FoundationsController REST endpoint handlers.
 */
class FoundationsControllerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var FoundationsController
	 */
	private FoundationsController $sut;

	/**
	 * Mock FoundationsClearance.
	 *
	 * @var FoundationsClearance&\Mockery\MockInterface
	 */
	private FoundationsClearance $foundations;

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
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);

		$this->foundations = Mockery::mock( FoundationsClearance::class );
		$this->sut         = new FoundationsController( $this->foundations );
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
	 * Builds a mock WP_User with the given properties.
	 *
	 * @param int    $id           User ID.
	 * @param string $display_name Display name.
	 * @return object
	 */
	private function make_user( int $id, string $display_name = 'Test User' ): object {
		return (object) array(
			'ID'           => $id,
			'display_name' => $display_name,
		);
	}

	/**
	 * Returns a standard foundations status array.
	 *
	 * @param array<string, mixed> $overrides Override individual fields.
	 * @return array<string, mixed>
	 */
	private function foundations_status( array $overrides = [] ): array {
		return array_merge(
			array(
				'in_foundations'  => true,
				'cleared'        => false,
				'enrolled_at'    => '2026-01-15',
				'coach_rolls'    => 2,
				'required_rolls' => 5,
			),
			$overrides
		);
	}

	// -------------------------------------------------------------------------
	// get_status
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_status returns 404 for a non-existent user.
	 */
	public function test_get_status_returns_404_for_nonexistent_user(): void {
		Functions\when( 'get_userdata' )->justReturn( false );

		$request = $this->make_request( array( 'user_id' => 999 ) );
		$result  = $this->sut->get_status( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	/**
	 * @testdox get_status returns success with merged user and foundations data.
	 */
	public function test_get_status_returns_merged_user_and_foundations_data(): void {
		$user   = $this->make_user( 10, 'Jane Doe' );
		$status = $this->foundations_status();

		Functions\when( 'get_userdata' )->justReturn( $user );

		$this->foundations->allows( 'get_status' )
			->with( 10 )
			->andReturn( $status );

		$request  = $this->make_request( array( 'user_id' => 10 ) );
		$response = $this->sut->get_status( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 10, $body['data']['user_id'] );
		$this->assertSame( 'Jane Doe', $body['data']['display_name'] );
		$this->assertTrue( $body['data']['in_foundations'] );
		$this->assertFalse( $body['data']['cleared'] );
		$this->assertSame( '2026-01-15', $body['data']['enrolled_at'] );
		$this->assertSame( 2, $body['data']['coach_rolls'] );
		$this->assertSame( 5, $body['data']['required_rolls'] );
	}

	// -------------------------------------------------------------------------
	// enroll
	// -------------------------------------------------------------------------

	/**
	 * @testdox enroll returns 201 on successful enrollment.
	 */
	public function test_enroll_returns_201_on_success(): void {
		$user = $this->make_user( 8, 'New Student' );

		Functions\when( 'get_userdata' )->justReturn( $user );

		// FoundationsClearance::is_enabled() calls get_option().
		Functions\when( 'get_option' )->justReturn( 'yes' );

		// First call: before enrollment — not enrolled.
		// Second call: after enrollment — enrolled.
		$this->foundations->allows( 'get_status' )
			->with( 8 )
			->andReturn(
				$this->foundations_status( array( 'in_foundations' => false, 'cleared' => false ) ),
				$this->foundations_status( array( 'in_foundations' => true, 'cleared' => false ) )
			);

		$this->foundations->expects( 'enroll' )
			->once()
			->with( 8 );

		$request  = $this->make_request( array( 'user_id' => 8 ) );
		$response = $this->sut->enroll( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 8, $body['data']['user_id'] );
		$this->assertTrue( $body['data']['in_foundations'] );
	}

	/**
	 * @testdox enroll returns 409 when student is already enrolled.
	 */
	public function test_enroll_returns_409_when_already_enrolled(): void {
		$user = $this->make_user( 12, 'Existing Student' );

		Functions\when( 'get_userdata' )->justReturn( $user );

		// FoundationsClearance::is_enabled() calls get_option().
		Functions\when( 'get_option' )->justReturn( 'yes' );

		$this->foundations->allows( 'get_status' )
			->with( 12 )
			->andReturn( $this->foundations_status( array( 'in_foundations' => true ) ) );

		$request = $this->make_request( array( 'user_id' => 12 ) );
		$result  = $this->sut->enroll( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'already_enrolled', $result->get_error_code() );
		$this->assertSame( array( 'status' => 409 ), $result->get_error_data() );
	}

	/**
	 * @testdox enroll returns 400 when foundations gate is disabled.
	 */
	public function test_enroll_returns_400_when_foundations_disabled(): void {
		$user = $this->make_user( 5 );

		Functions\when( 'get_userdata' )->justReturn( $user );

		// FoundationsClearance::is_enabled() calls get_option() — return 'no' to disable.
		Functions\when( 'get_option' )->justReturn( 'no' );

		$request = $this->make_request( array( 'user_id' => 5 ) );
		$result  = $this->sut->enroll( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'disabled', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// record_coach_roll
	// -------------------------------------------------------------------------

	/**
	 * @testdox record_coach_roll returns 400 when student is not in foundations.
	 */
	public function test_record_coach_roll_returns_400_when_not_in_foundations(): void {
		$user = $this->make_user( 20 );

		Functions\when( 'get_userdata' )->justReturn( $user );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		$this->foundations->allows( 'record_coach_roll' )
			->with( 20, 1, 'Good technique' )
			->andReturn( false );

		$request = $this->make_request( array( 'user_id' => 20, 'notes' => 'Good technique' ) );
		$result  = $this->sut->record_coach_roll( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_in_foundations', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	/**
	 * @testdox record_coach_roll returns success when roll is recorded.
	 */
	public function test_record_coach_roll_returns_success(): void {
		$user = $this->make_user( 20 );

		Functions\when( 'get_userdata' )->justReturn( $user );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		$this->foundations->allows( 'record_coach_roll' )
			->with( 20, 1, 'Solid mount escapes' )
			->andReturn( true );

		$status_after = $this->foundations_status( array( 'coach_rolls' => 3 ) );
		$this->foundations->allows( 'get_status' )
			->with( 20 )
			->andReturn( $status_after );

		$request  = $this->make_request( array( 'user_id' => 20, 'notes' => 'Solid mount escapes' ) );
		$response = $this->sut->record_coach_roll( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 20, $body['data']['user_id'] );
		$this->assertSame( 3, $body['data']['coach_rolls'] );
	}

	// -------------------------------------------------------------------------
	// clear
	// -------------------------------------------------------------------------

	/**
	 * @testdox clear returns success on clearance.
	 */
	public function test_clear_returns_success(): void {
		$user = $this->make_user( 30 );

		Functions\when( 'get_userdata' )->justReturn( $user );
		Functions\when( 'get_current_user_id' )->justReturn( 2 );

		$this->foundations->allows( 'clear' )
			->with( 30, 2 )
			->andReturn( true );

		$status_after = $this->foundations_status( array(
			'in_foundations' => false,
			'cleared'        => true,
		) );
		$this->foundations->allows( 'get_status' )
			->with( 30 )
			->andReturn( $status_after );

		$request  = $this->make_request( array( 'user_id' => 30 ) );
		$response = $this->sut->clear( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 30, $body['data']['user_id'] );
		$this->assertTrue( $body['data']['cleared'] );
		$this->assertFalse( $body['data']['in_foundations'] );
	}

	// -------------------------------------------------------------------------
	// permissions_view
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_view allows access to own data.
	 */
	public function test_permissions_view_allows_own_data(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 50 );

		$request = $this->make_request( array( 'user_id' => 50 ) );
		$result  = $this->sut->permissions_view( $request );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox permissions_view denies other users without capability.
	 */
	public function test_permissions_view_denies_without_capability(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 50 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->make_request( array( 'user_id' => 99 ) );
		$result  = $this->sut->permissions_view( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// permissions_coach
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_coach returns true with gym_promote_student capability.
	 */
	public function test_permissions_coach_allows_with_promote_cap(): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'gym_promote_student' === $cap;
			}
		);

		$request = $this->make_request();
		$result  = $this->sut->permissions_coach( $request );

		$this->assertTrue( $result );
	}
}
