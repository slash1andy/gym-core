<?php
/**
 * Unit tests for SMSController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\SMSController;
use Gym_Core\SMS\TwilioClient;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SMSController REST endpoint handlers.
 *
 * Static methods TwilioClient::sanitize_phone() and MessageTemplates::render()/get_all()
 * are called as real implementations since they are pure functions with no external
 * side effects beyond WP utility functions that Brain\Monkey stubs.
 */
class SMSControllerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var SMSController
	 */
	private SMSController $sut;

	/**
	 * Mock TwilioClient.
	 *
	 * @var TwilioClient&\Mockery\MockInterface
	 */
	private TwilioClient $twilio;

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
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );

		$this->twilio = Mockery::mock( TwilioClient::class );
		$this->sut    = new SMSController( $this->twilio );
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
	// send_sms
	// -------------------------------------------------------------------------

	/**
	 * @testdox send_sms should return 201 on successful send.
	 */
	public function test_send_sms_returns_201_on_successful_send(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		// TwilioClient::sanitize_phone() is called as the real static method.
		// A valid E.164 number passes through.
		$this->twilio
			->expects( 'is_rate_limited' )
			->once()
			->with( 5 )
			->andReturn( false );

		$this->twilio
			->expects( 'send' )
			->once()
			->with( '+15551234567', 'Hello from the gym!' )
			->andReturn( array( 'success' => true, 'sid' => 'SM123' ) );

		$this->twilio
			->expects( 'record_send' )
			->once()
			->with( 5 );

		$request  = $this->make_request(
			array(
				'phone'         => '+15551234567',
				'message'       => 'Hello from the gym!',
				'template_slug' => null,
				'variables'     => null,
				'contact_id'    => 5,
			)
		);
		$response = $this->sut->send_sms( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 'SM123', $body['data']['sid'] );
		$this->assertSame( '+15551234567', $body['data']['to'] );
		$this->assertSame( 'Hello from the gym!', $body['data']['body'] );
		$this->assertArrayHasKey( 'sent_at', $body['data'] );
	}

	/**
	 * @testdox send_sms should return 400 for invalid template slug.
	 */
	public function test_send_sms_returns_400_for_invalid_template(): void {
		// MessageTemplates::render() returns null for unknown slugs (real call).
		$request = $this->make_request(
			array(
				'phone'         => '+15551234567',
				'message'       => null,
				'template_slug' => 'nonexistent-template',
				'variables'     => null,
				'contact_id'    => null,
			)
		);
		$result  = $this->sut->send_sms( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_template', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	/**
	 * @testdox send_sms should return 400 when no message or template provided.
	 */
	public function test_send_sms_returns_400_when_no_message_or_template(): void {
		$request = $this->make_request(
			array(
				'phone'         => '+15551234567',
				'message'       => null,
				'template_slug' => null,
				'variables'     => null,
				'contact_id'    => null,
			)
		);
		$result  = $this->sut->send_sms( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_message', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	/**
	 * @testdox send_sms should return 400 for invalid phone number.
	 */
	public function test_send_sms_returns_400_for_invalid_phone(): void {
		// TwilioClient::sanitize_phone('not-a-phone') returns '' (real call).
		$request = $this->make_request(
			array(
				'phone'         => 'not-a-phone',
				'message'       => 'Hello!',
				'template_slug' => null,
				'variables'     => null,
				'contact_id'    => null,
			)
		);
		$result  = $this->sut->send_sms( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_phone', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	/**
	 * @testdox send_sms should return 429 when rate limited.
	 */
	public function test_send_sms_returns_429_when_rate_limited(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		$this->twilio
			->expects( 'is_rate_limited' )
			->once()
			->with( 1 )
			->andReturn( true );

		$request = $this->make_request(
			array(
				'phone'         => '+15551234567',
				'message'       => 'Hello!',
				'template_slug' => null,
				'variables'     => null,
				'contact_id'    => null,
			)
		);
		$result  = $this->sut->send_sms( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rate_limited', $result->get_error_code() );
		$this->assertSame( array( 'status' => 429 ), $result->get_error_data() );
	}

	/**
	 * @testdox send_sms should return 502 when Twilio send fails.
	 */
	public function test_send_sms_returns_502_when_send_fails(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		$this->twilio
			->expects( 'is_rate_limited' )
			->once()
			->with( 1 )
			->andReturn( false );

		$this->twilio
			->expects( 'send' )
			->once()
			->with( '+15551234567', 'Hello!' )
			->andReturn( array( 'success' => false, 'error' => 'Twilio API error' ) );

		$request = $this->make_request(
			array(
				'phone'         => '+15551234567',
				'message'       => 'Hello!',
				'template_slug' => null,
				'variables'     => null,
				'contact_id'    => null,
			)
		);
		$result  = $this->sut->send_sms( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'send_failed', $result->get_error_code() );
		$this->assertSame( array( 'status' => 502 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// get_templates
	// -------------------------------------------------------------------------

	/**
	 * @testdox get_templates should return formatted template list.
	 */
	public function test_get_templates_returns_formatted_template_list(): void {
		// MessageTemplates::get_all() is called as the real static method.
		// Brain\Monkey stubs __() to passthrough, so template names and bodies are
		// returned as their raw English strings.
		$request  = $this->make_request();
		$response = $this->sut->get_templates( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertIsArray( $body['data'] );
		$this->assertNotEmpty( $body['data'] );

		// Verify the envelope structure of each template entry.
		$first = $body['data'][0];
		$this->assertArrayHasKey( 'slug', $first );
		$this->assertArrayHasKey( 'name', $first );
		$this->assertArrayHasKey( 'body', $first );
		$this->assertArrayHasKey( 'description', $first );
	}

	// -------------------------------------------------------------------------
	// permissions_send_sms
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_send_sms should return true with manage_options capability.
	 */
	public function test_permissions_send_sms_returns_true_with_manage_options(): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'manage_options' === $cap;
			}
		);

		$request = $this->make_request();
		$result  = $this->sut->permissions_send_sms( $request );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox permissions_send_sms should return WP_Error without capability.
	 */
	public function test_permissions_send_sms_returns_error_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->make_request();
		$result  = $this->sut->permissions_send_sms( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}
}
