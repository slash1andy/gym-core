<?php
/**
 * Unit tests for TwilioController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\TwilioController;
use Gym_Core\SMS\TwilioClient;
use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for the admin-only test-message endpoint.
 */
class TwilioControllerTest extends TestCase {

	/**
	 * @var TwilioController
	 */
	private TwilioController $sut;

	/**
	 * @var TwilioClient&\Mockery\MockInterface
	 */
	private TwilioClient $twilio;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 401 );

		$this->twilio = Mockery::mock( TwilioClient::class );
		$this->sut    = new TwilioController( $this->twilio );
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_request(): \WP_REST_Request {
		return Mockery::mock( \WP_REST_Request::class );
	}

	#[TestDox('Returns 201 when Twilio reports success.')]
	public function test_send_test_message_returns_201_on_success(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_user_meta' )
			->justReturn( '+15551234567' );

		$this->twilio
			->expects( 'send' )
			->once()
			->with( '+15551234567', Mockery::type( 'string' ) )
			->andReturn( array( 'success' => true, 'sid' => 'SMabc' ) );

		$response = $this->sut->send_test_message( $this->make_request() );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 'SMabc', $body['data']['sid'] );
		$this->assertSame( '+15551234567', $body['data']['to'] );
	}

	#[TestDox('Returns 401 when no user is logged in.')]
	public function test_send_test_message_returns_401_when_not_logged_in(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$result = $this->sut->send_test_message( $this->make_request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_not_logged_in', $result->get_error_code() );
		$this->assertSame( array( 'status' => 401 ), $result->get_error_data() );
	}

	#[TestDox('Returns 422 when the user has no billing phone on file.')]
	public function test_send_test_message_returns_422_when_no_phone(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$result = $this->sut->send_test_message( $this->make_request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'no_phone_on_profile', $result->get_error_code() );
		$this->assertSame( array( 'status' => 422 ), $result->get_error_data() );
	}

	#[TestDox('Returns 502 when the Twilio API rejects the request.')]
	public function test_send_test_message_returns_502_on_twilio_failure(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_user_meta' )->justReturn( '+15551234567' );

		$this->twilio
			->expects( 'send' )
			->once()
			->andReturn( array( 'success' => false, 'sid' => null, 'error' => 'Invalid auth' ) );

		$result = $this->sut->send_test_message( $this->make_request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'send_failed', $result->get_error_code() );
		$this->assertSame( 'Invalid auth', $result->get_error_message() );
		$this->assertSame( array( 'status' => 502 ), $result->get_error_data() );
	}

	#[TestDox('Permissions callback denies users without manage_woocommerce.')]
	public function test_permissions_manage_denies_when_capability_missing(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->sut->permissions_manage( $this->make_request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	#[TestDox('Permissions callback allows users with manage_woocommerce.')]
	public function test_permissions_manage_allows_when_capability_present(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->assertTrue( $this->sut->permissions_manage( $this->make_request() ) );
	}
}
