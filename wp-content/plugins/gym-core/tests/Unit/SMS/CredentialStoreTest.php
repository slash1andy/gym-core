<?php
/**
 * Unit tests for CredentialStore.
 *
 * @package Gym_Core\Tests\Unit\SMS
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\SMS;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\SMS\CredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * Tests encrypt/decrypt round-trip + masking + form round-trip preservation.
 */
class CredentialStoreTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_salt' )->justReturn( 'unit-test-static-salt-do-not-use-in-prod' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_encrypt_decrypt_round_trip(): void {
		$plaintext = 'super_secret_twilio_auth_token_42';
		$cipher    = CredentialStore::encrypt( $plaintext );

		$this->assertNotSame( $plaintext, $cipher );
		$this->assertStringStartsWith( 'gymenc:v1:', $cipher );
		$this->assertSame( $plaintext, CredentialStore::decrypt( $cipher ) );
	}

	public function test_decrypt_returns_null_for_garbage(): void {
		$this->assertNull( CredentialStore::decrypt( 'not-prefixed' ) );
		$this->assertNull( CredentialStore::decrypt( 'gymenc:v1:sb:!!!not-base64!!!' ) );
	}

	public function test_mask_shows_last_four(): void {
		$this->assertSame( '********wxyz', CredentialStore::mask( 'abcdefghijklwxyz' ) );
	}

	public function test_mask_returns_empty_for_empty(): void {
		$this->assertSame( '', CredentialStore::mask( '' ) );
	}

	public function test_mask_short_token_uses_full_value(): void {
		// Short tokens (< 4 chars) shouldn't be possible for Twilio, but the
		// helper must still degrade gracefully.
		$this->assertSame( '********ab', CredentialStore::mask( 'ab' ) );
	}

	public function test_pre_update_filter_encrypts_plaintext(): void {
		$store     = new CredentialStore();
		$plaintext = 'fresh_token_value';

		$stored = $store->filter_pre_update_option( $plaintext, '' );

		$this->assertIsString( $stored );
		$this->assertStringStartsWith( 'gymenc:v1:', $stored );
		$this->assertSame( $plaintext, CredentialStore::decrypt( $stored ) );
	}

	public function test_pre_update_filter_preserves_old_value_when_mask_posted(): void {
		$store        = new CredentialStore();
		$old_cipher   = CredentialStore::encrypt( 'real_existing_token' );
		$mask_payload = '********oken'; // What the form re-renders.

		$result = $store->filter_pre_update_option( $mask_payload, $old_cipher );

		// Old ciphertext must be preserved unchanged so the real token isn't
		// overwritten by the mask placeholder.
		$this->assertSame( $old_cipher, $result );
	}

	public function test_pre_update_filter_passes_through_empty_string(): void {
		$store = new CredentialStore();
		$this->assertSame( '', $store->filter_pre_update_option( '', 'old' ) );
	}

	public function test_pre_update_filter_passes_through_already_encrypted(): void {
		$store     = new CredentialStore();
		$encrypted = CredentialStore::encrypt( 'tok' );

		$this->assertSame( $encrypted, $store->filter_pre_update_option( $encrypted, 'old' ) );
	}

	public function test_option_read_filter_decrypts(): void {
		$store     = new CredentialStore();
		$plaintext = 'decrypt_me';
		$cipher    = CredentialStore::encrypt( $plaintext );

		$this->assertSame( $plaintext, $store->filter_option_read( $cipher ) );
	}

	public function test_option_read_filter_passes_legacy_plaintext_through(): void {
		$store = new CredentialStore();

		// Legacy un-prefixed value should round-trip unchanged so existing
		// installs keep working until the next save re-encrypts it.
		$this->assertSame( 'legacy_plain_value', $store->filter_option_read( 'legacy_plain_value' ) );
	}

	public function test_option_read_filter_returns_empty_for_unreadable_cipher(): void {
		$store = new CredentialStore();

		// Prefixed but corrupt — decrypt() returns null, filter returns ''.
		$this->assertSame( '', $store->filter_option_read( 'gymenc:v1:sb:!!!' ) );
	}

	public function test_get_returns_option_when_present(): void {
		Functions\when( 'get_option' )->justReturn( 'plaintext-from-option' );

		$this->assertSame( 'plaintext-from-option', CredentialStore::get() );
	}

	public function test_get_returns_empty_when_neither_option_nor_constant_set(): void {
		// Skip if the constant has already been defined by another test in
		// this process — PHP constants are immutable for the process lifetime.
		if ( defined( 'GYM_CORE_TWILIO_AUTH_TOKEN' ) && '' !== (string) GYM_CORE_TWILIO_AUTH_TOKEN ) {
			$this->markTestSkipped( 'Constant already defined by another test in this process.' );
		}

		Functions\when( 'get_option' )->justReturn( '' );

		$this->assertSame( '', CredentialStore::get() );
	}

	public function test_get_falls_back_to_constant_when_option_empty(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		// Define the constant once for this test process; ignored if it's
		// already defined by a sibling test (constants are immutable).
		if ( ! defined( 'GYM_CORE_TWILIO_AUTH_TOKEN' ) ) {
			define( 'GYM_CORE_TWILIO_AUTH_TOKEN', 'wp-config-fallback-token' );
		}

		$this->assertSame( 'wp-config-fallback-token', CredentialStore::get() );
	}

	public function test_set_writes_via_update_option(): void {
		$captured_key   = null;
		$captured_value = null;

		Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$captured_key, &$captured_value ) {
				$captured_key   = $key;
				$captured_value = $value;
				return true;
			}
		);

		$this->assertTrue( CredentialStore::set( 'plaintext-to-store' ) );
		$this->assertSame( 'gym_core_twilio_auth_token', $captured_key );
		$this->assertSame( 'plaintext-to-store', $captured_value );
	}
}
