<?php
declare(strict_types=1);
/**
 * Unit tests for WebhookValidator.
 *
 * @package HMA_AI_Chat\Tests\Unit\Security
 */

namespace HMA_AI_Chat\Tests\Unit\Security;

use HMA_AI_Chat\Security\WebhookValidator;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass( \HMA_AI_Chat\Security\WebhookValidator::class )]
class WebhookValidatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Stub get_option to return the current secret and no rotation state.
	 *
	 * @param string $secret The current secret value.
	 */
	private function stub_no_rotation( string $secret ): void {
		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( string $key, mixed $default = '' ) use ( $secret ): mixed {
					if ( WebhookValidator::SECRET_KEY === $key ) {
						return $secret;
					}
					// No rotation: timestamp key returns 0.
					if ( WebhookValidator::ROTATION_TIMESTAMP_KEY === $key ) {
						return 0;
					}
					return $default;
				}
			);
	}

	// -----------------------------------------------------------------------
	// validate_request — happy path
	// -----------------------------------------------------------------------

	public function test_validate_request_accepts_valid_bearer_token(): void {
		$secret = 'super-secret-key-123';
		$this->stub_no_rotation( $secret );

		$validator = new WebhookValidator();
		$this->assertTrue( $validator->validate_request( 'Bearer ' . $secret ) );
	}

	public function test_validate_request_rejects_wrong_token(): void {
		$this->stub_no_rotation( 'correct-secret' );

		$validator = new WebhookValidator();
		$this->assertFalse( $validator->validate_request( 'Bearer wrong-secret' ) );
	}

	public function test_validate_request_rejects_missing_bearer_prefix(): void {
		$secret = 'my-secret';
		$this->stub_no_rotation( $secret );

		$validator = new WebhookValidator();
		$this->assertFalse( $validator->validate_request( $secret ) );
	}

	public function test_validate_request_rejects_empty_header(): void {
		$this->stub_no_rotation( 'my-secret' );

		$validator = new WebhookValidator();
		$this->assertFalse( $validator->validate_request( '' ) );
	}

	public function test_validate_request_rejects_when_no_secret_configured(): void {
		// get_option returns empty string (no secret configured).
		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->andReturn( '' );

		$validator = new WebhookValidator();
		$this->assertFalse( $validator->validate_request( 'Bearer anything' ) );
	}

	// -----------------------------------------------------------------------
	// validate_request — rotation grace period
	// -----------------------------------------------------------------------

	public function test_validate_request_accepts_previous_secret_within_grace_period(): void {
		$new_secret  = 'new-secret';
		$prev_secret = 'old-secret';

		// Rotation happened 10s ago — within 300s grace period.
		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( string $key, mixed $default = '' ) use ( $new_secret, $prev_secret ): mixed {
					if ( WebhookValidator::SECRET_KEY === $key ) {
						return $new_secret;
					}
					if ( WebhookValidator::ROTATION_TIMESTAMP_KEY === $key ) {
						return (string) ( time() - 10 );
					}
					if ( WebhookValidator::PREVIOUS_SECRET_KEY === $key ) {
						return $prev_secret;
					}
					return $default;
				}
			);

		$validator = new WebhookValidator();
		$this->assertTrue( $validator->validate_request( 'Bearer ' . $prev_secret ) );
	}

	public function test_validate_request_rejects_previous_secret_after_grace_period(): void {
		$new_secret  = 'new-secret';
		$prev_secret = 'old-secret';

		// Rotation happened 400s ago — grace period expired.
		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( string $key, mixed $default = 0 ) use ( $new_secret, $prev_secret ): mixed {
					if ( WebhookValidator::SECRET_KEY === $key ) {
						return $new_secret;
					}
					if ( WebhookValidator::ROTATION_TIMESTAMP_KEY === $key ) {
						return (string) ( time() - 400 );
					}
					if ( WebhookValidator::PREVIOUS_SECRET_KEY === $key ) {
						return $prev_secret;
					}
					return $default;
				}
			);

		// Cleanup calls after grace period expires.
		Functions\expect( 'delete_option' )->zeroOrMoreTimes();

		$validator = new WebhookValidator();
		$this->assertFalse( $validator->validate_request( 'Bearer ' . $prev_secret ) );
	}

	// -----------------------------------------------------------------------
	// validate_ip — enforcement modes
	// -----------------------------------------------------------------------

	public function test_validate_ip_open_when_empty_and_not_enforced(): void {
		// Empty allowlist + enforce=false => legacy open.
		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( string $key, mixed $default = '' ): mixed {
					if ( WebhookValidator::IP_ALLOWLIST_KEY === $key ) {
						return array(); // Empty allowlist.
					}
					if ( WebhookValidator::IP_ALLOWLIST_ENFORCE_KEY === $key ) {
						return false; // Not enforced.
					}
					return $default;
				}
			);
		Functions\expect( 'do_action' )->once(); // hma_ai_chat_webhook_no_ip_allowlist

		$validator = new WebhookValidator();
		$this->assertTrue( $validator->validate_ip() );
	}

	public function test_validate_ip_fail_closed_when_empty_and_enforced(): void {
		// Empty allowlist + enforce=true => deny.
		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( string $key, mixed $default = '' ): mixed {
					if ( WebhookValidator::IP_ALLOWLIST_KEY === $key ) {
						return array(); // Empty allowlist.
					}
					if ( WebhookValidator::IP_ALLOWLIST_ENFORCE_KEY === $key ) {
						return true; // Enforced.
					}
					return $default;
				}
			);
		Functions\expect( 'do_action' )->once(); // hma_ai_chat_webhook_ip_denied_empty_allowlist

		$validator = new WebhookValidator();
		$this->assertFalse( $validator->validate_ip() );
	}
}
