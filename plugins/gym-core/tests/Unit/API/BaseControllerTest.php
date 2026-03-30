<?php
/**
 * Unit tests for BaseController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\BaseController;
use PHPUnit\Framework\TestCase;

/**
 * Concrete implementation used to test the abstract BaseController.
 *
 * Exposes protected methods as public so tests can call them directly,
 * and provides no-op implementations of abstract methods.
 */
class ConcreteController extends BaseController {

	/**
	 * No routes needed for unit testing the base class.
	 *
	 * @return void
	 */
	public function register_routes(): void {}

	/**
	 * Proxy for the protected success_response() method.
	 *
	 * @param mixed                    $data   Response payload.
	 * @param array<string,mixed>|null $meta   Optional metadata.
	 * @param int                      $status HTTP status code.
	 * @return \WP_REST_Response
	 */
	public function call_success_response( mixed $data, ?array $meta = null, int $status = 200 ): \WP_REST_Response {
		return $this->success_response( $data, $meta, $status );
	}

	/**
	 * Proxy for the protected error_response() method.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error
	 */
	public function call_error_response( string $code, string $message, int $status = 400 ): \WP_Error {
		return $this->error_response( $code, $message, $status );
	}

	/**
	 * Proxy for the protected pagination_meta() method.
	 *
	 * @param int $total       Total items.
	 * @param int $total_pages Total pages.
	 * @param int $page        Current page.
	 * @param int $per_page    Items per page.
	 * @return array<string, array<string, int>>
	 */
	public function call_pagination_meta( int $total, int $total_pages, int $page, int $per_page ): array {
		return $this->pagination_meta( $total, $total_pages, $page, $per_page );
	}

	/**
	 * Proxy for the protected check_rate_limit() method.
	 *
	 * @param string $key    Rate limit bucket key.
	 * @param int    $max    Max allowed requests.
	 * @param int    $window Window in seconds.
	 * @return bool
	 */
	public function call_check_rate_limit( string $key, int $max, int $window ): bool {
		return $this->check_rate_limit( $key, $max, $window );
	}

	/**
	 * Proxy for the protected pagination_route_args() method.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function call_pagination_route_args(): array {
		return $this->pagination_route_args();
	}
}

/**
 * Tests for the BaseController class.
 */
class BaseControllerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var ConcreteController
	 */
	private ConcreteController $sut;

	/**
	 * Fake WP_REST_Request passed to permission callbacks.
	 *
	 * @var \WP_REST_Request
	 */
	private \WP_REST_Request $request;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );

		$this->sut     = new ConcreteController();
		$this->request = new \WP_REST_Request();
	}

	/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Constructor / namespace
	// -------------------------------------------------------------------------

	/**
	 * @testdox Namespace should be set to gym/v1 on construction.
	 */
	public function test_namespace_is_set_on_construction(): void {
		$this->assertSame( 'gym/v1', BaseController::REST_NAMESPACE );
	}

	// -------------------------------------------------------------------------
	// register_hooks
	// -------------------------------------------------------------------------

	/**
	 * @testdox register_hooks should add register_routes to rest_api_init.
	 */
	public function test_register_hooks_adds_rest_api_init_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', array( $this->sut, 'register_routes' ) );

		$this->sut->register_hooks();

		// Brain\Monkey expectation counts as the assertion.
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// permissions_public
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_public should always return true.
	 */
	public function test_permissions_public_returns_true(): void {
		$result = $this->sut->permissions_public( $this->request );

		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// permissions_authenticated
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_authenticated should return true for a logged-in user.
	 */
	public function test_permissions_authenticated_returns_true_when_logged_in(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		$result = $this->sut->permissions_authenticated( $this->request );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox permissions_authenticated should return WP_Error(401) when not logged in.
	 */
	public function test_permissions_authenticated_returns_401_error_when_not_logged_in(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$result = $this->sut->permissions_authenticated( $this->request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_not_logged_in', $result->get_error_code() );
		$this->assertSame( array( 'status' => 401 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// permissions_manage
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_manage should return true when user has manage_woocommerce.
	 */
	public function test_permissions_manage_returns_true_with_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = $this->sut->permissions_manage( $this->request );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox permissions_manage should return WP_Error when capability is absent.
	 */
	public function test_permissions_manage_returns_error_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );

		$result = $this->sut->permissions_manage( $this->request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// success_response
	// -------------------------------------------------------------------------

	/**
	 * @testdox success_response should set success=true and wrap data in the envelope.
	 */
	public function test_success_response_wraps_data_in_envelope(): void {
		$data     = array( 'slug' => 'rockford' );
		$response = $this->sut->call_success_response( $data );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( $data, $body['data'] );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @testdox success_response should include meta when provided.
	 */
	public function test_success_response_includes_meta_when_provided(): void {
		$meta     = array( 'pagination' => array( 'total' => 5 ) );
		$response = $this->sut->call_success_response( array(), $meta );

		$body = $response->get_data();
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( $meta, $body['meta'] );
	}

	/**
	 * @testdox success_response should omit the meta key when meta is null.
	 */
	public function test_success_response_omits_meta_key_when_null(): void {
		$response = $this->sut->call_success_response( 'value', null );

		$body = $response->get_data();
		$this->assertArrayNotHasKey( 'meta', $body );
	}

	/**
	 * @testdox success_response should use the provided HTTP status code.
	 */
	public function test_success_response_uses_provided_status_code(): void {
		$response = $this->sut->call_success_response( null, null, 201 );

		$this->assertSame( 201, $response->get_status() );
	}

	/**
	 * @testdox success_response should accept null as data payload.
	 */
	public function test_success_response_accepts_null_data(): void {
		$response = $this->sut->call_success_response( null );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertNull( $body['data'] );
	}

	// -------------------------------------------------------------------------
	// error_response
	// -------------------------------------------------------------------------

	/**
	 * @testdox error_response should return a WP_Error with the given code and status.
	 */
	public function test_error_response_returns_wp_error_with_code_and_status(): void {
		$error = $this->sut->call_error_response( 'my_error', 'Something went wrong.', 422 );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'my_error', $error->get_error_code() );
		$this->assertSame( 'Something went wrong.', $error->get_error_message() );
		$this->assertSame( array( 'status' => 422 ), $error->get_error_data() );
	}

	/**
	 * @testdox error_response should default to HTTP 400.
	 */
	public function test_error_response_defaults_to_400(): void {
		$error = $this->sut->call_error_response( 'code', 'msg' );

		$this->assertSame( array( 'status' => 400 ), $error->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// pagination_meta
	// -------------------------------------------------------------------------

	/**
	 * @testdox pagination_meta should build the correct nested structure.
	 */
	public function test_pagination_meta_returns_correct_structure(): void {
		$meta = $this->sut->call_pagination_meta( 50, 5, 2, 10 );

		$this->assertSame(
			array(
				'pagination' => array(
					'total'       => 50,
					'total_pages' => 5,
					'page'        => 2,
					'per_page'    => 10,
				),
			),
			$meta
		);
	}

	// -------------------------------------------------------------------------
	// pagination_route_args
	// -------------------------------------------------------------------------

	/**
	 * @testdox pagination_route_args should define page and per_page keys.
	 */
	public function test_pagination_route_args_has_page_and_per_page(): void {
		Functions\when( '__' )->returnArg( 1 );

		$args = $this->sut->call_pagination_route_args();

		$this->assertArrayHasKey( 'page', $args );
		$this->assertArrayHasKey( 'per_page', $args );
	}

	/**
	 * @testdox pagination_route_args should set sensible defaults and limits.
	 */
	public function test_pagination_route_args_has_correct_defaults_and_limits(): void {
		Functions\when( '__' )->returnArg( 1 );

		$args = $this->sut->call_pagination_route_args();

		$this->assertSame( 1, $args['page']['default'] );
		$this->assertSame( 1, $args['page']['minimum'] );
		$this->assertSame( 10, $args['per_page']['default'] );
		$this->assertSame( 1, $args['per_page']['minimum'] );
		$this->assertSame( 100, $args['per_page']['maximum'] );
	}

	// -------------------------------------------------------------------------
	// check_rate_limit
	// -------------------------------------------------------------------------

	/**
	 * @testdox check_rate_limit should return true when under the limit.
	 */
	public function test_check_rate_limit_returns_true_under_limit(): void {
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = $this->sut->call_check_rate_limit( 'test_key', 5, 60 );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox check_rate_limit should return false when count equals the max.
	 */
	public function test_check_rate_limit_returns_false_when_limit_reached(): void {
		Functions\when( 'get_transient' )->justReturn( 5 );

		$result = $this->sut->call_check_rate_limit( 'test_key', 5, 60 );

		$this->assertFalse( $result );
	}

	/**
	 * @testdox check_rate_limit should return false when count exceeds the max.
	 */
	public function test_check_rate_limit_returns_false_when_limit_exceeded(): void {
		Functions\when( 'get_transient' )->justReturn( 10 );

		$result = $this->sut->call_check_rate_limit( 'test_key', 5, 60 );

		$this->assertFalse( $result );
	}

	/**
	 * @testdox check_rate_limit should increment the transient counter on each allowed call.
	 */
	public function test_check_rate_limit_increments_counter(): void {
		Functions\when( 'get_transient' )->justReturn( 3 );

		Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing(
				function ( string $key, int $value, int $window ): bool {
					// Counter should be incremented from 3 to 4.
					TestCase::assertSame( 4, $value );
					return true;
				}
			);

		$this->sut->call_check_rate_limit( 'test_key', 10, 60 );
	}
}
