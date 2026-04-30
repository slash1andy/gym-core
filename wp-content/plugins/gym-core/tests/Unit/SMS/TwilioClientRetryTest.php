<?php
/**
 * Unit tests for TwilioClient::send retry behavior on 429 / 503.
 *
 * @package Gym_Core\Tests\Unit\SMS
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\SMS;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\SMS\TwilioClient;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the 2-attempt retry-with-backoff behavior on transient Twilio
 * statuses (429 rate-limit, 503 service-unavailable).
 *
 * Note: each retry case sleeps ~500ms via TwilioClient::RETRY_BACKOFF_USEC.
 */
class TwilioClientRetryTest extends TestCase {

	private TwilioClient $client;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'get_option' => static function ( string $key, $default = '' ) {
					return match ( $key ) {
						'gym_core_twilio_account_sid'  => 'AC_TEST_SID',
						'gym_core_twilio_auth_token'   => 'test_token',
						'gym_core_twilio_phone_number' => '+15550000000',
						default                        => $default,
					};
				},
				'__'                            => static fn ( string $text ): string => $text,
				'wp_remote_retrieve_body'       => static fn ( $response ) => $response['body'] ?? '',
				'is_wp_error'                   => static fn ( $value ): bool => false,
			)
		);

		$this->client = new TwilioClient();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	#[TestDox( 'Calls wp_remote_post once when the first attempt succeeds.' )]
	public function test_no_retry_on_success(): void {
		$this->stub_response_sequence(
			array(
				array( 'code' => 200, 'body' => '{"sid":"SM_OK"}' ),
			)
		);

		Functions\expect( 'do_action' )->atLeast()->once();

		$result = $this->client->send( '+15551234567', 'Hello' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'SM_OK', $result['sid'] );
	}

	#[TestDox( 'Retries once on 429 and returns success when retry succeeds.' )]
	public function test_retries_on_429_then_succeeds(): void {
		$this->stub_response_sequence(
			array(
				array( 'code' => 429, 'body' => '{"message":"Too many"}' ),
				array( 'code' => 200, 'body' => '{"sid":"SM_OK"}' ),
			)
		);

		Functions\expect( 'do_action' )->atLeast()->once();

		$result = $this->client->send( '+15551234567', 'Hello' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'SM_OK', $result['sid'] );
	}

	#[TestDox( 'Retries once on 503 and returns the failure when both attempts fail.' )]
	public function test_retries_on_503_returns_error_when_both_fail(): void {
		$this->stub_response_sequence(
			array(
				array( 'code' => 503, 'body' => '{"message":"Try again"}' ),
				array( 'code' => 503, 'body' => '{"message":"Still down"}' ),
			)
		);

		$result = $this->client->send( '+15551234567', 'Hello' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Still down', $result['error'] );
	}

	#[TestDox( 'Does not retry on a non-transient 4xx (e.g. 400 Bad Request).' )]
	public function test_no_retry_on_non_transient_4xx(): void {
		$this->stub_response_sequence(
			array(
				array( 'code' => 400, 'body' => '{"message":"Bad number"}' ),
			)
		);

		$result = $this->client->send( '+15551234567', 'Hello' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Bad number', $result['error'] );
	}

	/**
	 * Stubs wp_remote_post + wp_remote_retrieve_response_code so successive
	 * calls return successive responses from the given sequence.
	 *
	 * @param array<int, array{code:int, body:string}> $responses Ordered fake responses.
	 */
	private function stub_response_sequence( array $responses ): void {
		$call_index = 0;

		Functions\when( 'wp_remote_post' )->alias(
			static function () use ( &$call_index, $responses ) {
				$expected = count( $responses );
				if ( $call_index >= $expected ) {
					throw new \RuntimeException(
						sprintf( 'Unexpected wp_remote_post call #%d (expected %d).', $call_index + 1, $expected )
					);
				}

				return $responses[ $call_index++ ];
			}
		);

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn ( $response ): int => (int) ( $response['code'] ?? 0 )
		);
	}
}
