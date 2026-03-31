<?php
/**
 * Unit tests for TwilioClient.
 *
 * @package Gym_Core\Tests\Unit\SMS
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\SMS;

use Gym_Core\SMS\TwilioClient;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Tests for TwilioClient phone sanitization and signature validation.
 */
class TwilioClientTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- Phone sanitization tests ---

	public function test_sanitize_us_10_digit(): void {
		$this->assertSame( '+15551234567', TwilioClient::sanitize_phone( '5551234567' ) );
	}

	public function test_sanitize_us_11_digit_with_leading_1(): void {
		$this->assertSame( '+15551234567', TwilioClient::sanitize_phone( '15551234567' ) );
	}

	public function test_sanitize_e164_passthrough(): void {
		$this->assertSame( '+15551234567', TwilioClient::sanitize_phone( '+15551234567' ) );
	}

	public function test_sanitize_strips_formatting(): void {
		$this->assertSame( '+15551234567', TwilioClient::sanitize_phone( '(555) 123-4567' ) );
	}

	public function test_sanitize_strips_dots(): void {
		$this->assertSame( '+15551234567', TwilioClient::sanitize_phone( '555.123.4567' ) );
	}

	public function test_sanitize_strips_spaces(): void {
		$this->assertSame( '+15551234567', TwilioClient::sanitize_phone( '555 123 4567' ) );
	}

	public function test_sanitize_international_number(): void {
		$this->assertSame( '+447911123456', TwilioClient::sanitize_phone( '+447911123456' ) );
	}

	public function test_sanitize_empty_returns_empty(): void {
		$this->assertSame( '', TwilioClient::sanitize_phone( '' ) );
	}

	public function test_sanitize_too_short_returns_empty(): void {
		$this->assertSame( '', TwilioClient::sanitize_phone( '12345' ) );
	}

	public function test_sanitize_letters_returns_empty(): void {
		$this->assertSame( '', TwilioClient::sanitize_phone( 'notaphone' ) );
	}

	// --- Signature validation tests ---

	public function test_validate_signature_correct(): void {
		$client = new TwilioClient();

		// Manually compute expected signature.
		$auth_token = 'test_token_123';
		$url        = 'https://example.com/webhook';
		$params     = array( 'Body' => 'Hello', 'From' => '+15551234567' );

		// Build data string per Twilio's algorithm.
		ksort( $params );
		$data = $url;
		foreach ( $params as $key => $value ) {
			$data .= $key . $value;
		}

		$expected_sig = base64_encode( hash_hmac( 'sha1', $data, $auth_token, true ) );

		// Mock get_option to return our test auth token.
		Monkey\Functions\expect( 'get_option' )
			->with( 'gym_core_twilio_auth_token', '' )
			->andReturn( $auth_token );

		$this->assertTrue( $client->validate_webhook_signature( $url, $params, $expected_sig ) );
	}

	public function test_validate_signature_wrong_token_fails(): void {
		$client = new TwilioClient();

		Monkey\Functions\expect( 'get_option' )
			->with( 'gym_core_twilio_auth_token', '' )
			->andReturn( 'wrong_token' );

		$this->assertFalse(
			$client->validate_webhook_signature(
				'https://example.com/webhook',
				array( 'Body' => 'Hello' ),
				'invalid_signature'
			)
		);
	}

	public function test_validate_signature_empty_token_fails(): void {
		$client = new TwilioClient();

		Monkey\Functions\expect( 'get_option' )
			->with( 'gym_core_twilio_auth_token', '' )
			->andReturn( '' );

		$this->assertFalse(
			$client->validate_webhook_signature(
				'https://example.com/webhook',
				array(),
				'any_signature'
			)
		);
	}
}
