<?php
/**
 * Unit tests for MagicLink.
 *
 * Token signing and verification are pure (no DB, no HTTP). We test:
 *   - Round-trip create → verify returns the expected payload.
 *   - A token with a tampered signature is rejected.
 *   - A token with no dot separator is rejected as malformed.
 *   - A token whose expiry is in the past is rejected as expired.
 *
 * @package Gym_Core\Tests\Unit\Briefing
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Briefing;

use Gym_Core\Briefing\MagicLink;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for MagicLink signing and verification.
 */
class MagicLinkTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stub i18n functions to return the text unchanged.
		Functions\stubs(
			array(
				'__' => static function ( string $text ): string {
					return $text;
				},
			)
		);

		// MagicLink::get_secret() uses NONCE_SALT constant or falls back to
		// get_option('siteurl'). Define a deterministic salt so tokens are
		// reproducible within the test suite without WordPress constants.
		if ( ! defined( 'NONCE_SALT' ) ) {
			define( 'NONCE_SALT', 'test-nonce-salt-for-gym-core-unit-tests' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A token created for class 42 / user 7 round-trips correctly.
	 */
	public function test_roundtrip_returns_correct_payload(): void {
		// wp_json_encode is used inside create(); stub it to the native encoder.
		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( static function ( $value ) {
				return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			} );

		$token = MagicLink::create( 42, 7, 3600 );
		$this->assertIsString( $token );
		$this->assertStringContainsString( '.', $token );

		$result = MagicLink::verify( $token );
		$this->assertIsArray( $result, 'verify() should return an array for a valid token' );
		$this->assertSame( 42, $result['class_id'] );
		$this->assertSame( 7, $result['user_id'] );
		$this->assertGreaterThan( time(), $result['expires'] );
	}

	/**
	 * A token with its signature portion replaced by garbage is rejected.
	 */
	public function test_tampered_signature_returns_wp_error(): void {
		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( static function ( $value ) {
				return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			} );

		$token  = MagicLink::create( 1, 0, 600 );
		$parts  = explode( '.', $token, 2 );
		$forged = $parts[0] . '.AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

		$result = MagicLink::verify( $forged );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_signature', $result->get_error_code() );
	}

	/**
	 * A token with no dot separator is rejected immediately as malformed.
	 */
	public function test_token_without_dot_is_malformed(): void {
		$result = MagicLink::verify( 'nodotinhere' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	/**
	 * An empty string token is rejected as malformed.
	 */
	public function test_empty_token_is_malformed(): void {
		$result = MagicLink::verify( '' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	/**
	 * A token whose expiry has already passed is rejected as expired.
	 *
	 * We hand-craft a payload with an expiry in the past and sign it with the
	 * real secret so the signature check passes, then verify it hits the expiry
	 * check.
	 */
	public function test_expired_token_returns_wp_error(): void {
		// Build an expired payload manually.
		$payload = json_encode( array( 'c' => 5, 'u' => 0, 'e' => time() - 10 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		// MagicLink uses its private base64url encoder; replicate it.
		$payload_b64 = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		// Derive the same secret MagicLink::get_secret() returns.
		$salt    = defined( 'NONCE_SALT' ) ? NONCE_SALT : 'gym-core-briefing';
		$secret  = hash( 'sha256', 'gym_briefing|' . $salt, true );
		$sig     = hash_hmac( 'sha256', $payload_b64, $secret, true );
		$sig_b64 = rtrim( strtr( base64_encode( $sig ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$token  = $payload_b64 . '.' . $sig_b64;
		$result = MagicLink::verify( $token );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'token_expired', $result->get_error_code() );
	}
}
