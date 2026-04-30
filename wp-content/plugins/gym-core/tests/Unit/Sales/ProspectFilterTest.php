<?php
/**
 * Unit tests for ProspectFilter.
 *
 * @package Gym_Core\Tests\Unit\Sales
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Sales;

use Gym_Core\Sales\ProspectFilter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Tests for the prospect filter — verifies batched lookups (MAJOR-02 fix).
 */
class ProspectFilterTest extends TestCase {

	/**
	 * Prepared SQL strings captured from $wpdb->prepare() calls.
	 *
	 * @var list<string>
	 */
	private array $prepare_calls = array();

	/**
	 * In-memory transient store shared between get_transient/set_transient stubs.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->prepare_calls = array();
		$this->transients    = array();

		// In-memory transient store so set_transient/get_transient round-trip
		// like production. Without this the priming pass is a no-op in tests.
		$store = &$this->transients;

		Functions\stubs(
			array(
				'__'            => static fn( string $text ): string => $text,
				'get_transient' => static function ( string $key ) use ( &$store ) {
					return $store[ $key ] ?? false;
				},
				'set_transient' => static function ( string $key, $value, int $ttl = 0 ) use ( &$store ): bool {
					$store[ $key ] = $value;
					return true;
				},
			)
		);

		// wcs_get_subscriptions and wcs_get_users_subscriptions are declared as
		// real global no-ops in tests/stubs/wc-subscriptions-functions.php so
		// function_exists() returns true. Tests override return values via
		// Functions\expect() below.

		// Default $wpdb mock — individual tests override get_results expectations.
		$this->reset_wpdb();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Replaces the global $wpdb with a fresh Mockery mock that captures
	 * prepare() calls and lets tests stub get_results() per case.
	 */
	private function reset_wpdb(): void {
		global $wpdb;

		$captured = &$this->prepare_calls;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb        = Mockery::mock( \stdClass::class );
		$wpdb->users = 'wp_users';

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				static function ( string $sql ) use ( &$captured ): string {
					$captured[] = $sql;
					return $sql;
				}
			);

		// Default: no users matched. Tests override via shouldReceive('get_results').
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() )->byDefault();
	}

	/**
	 * Builds a real subscription-shaped object whose get_user_id() returns $user_id.
	 *
	 * Anonymous class (not Mockery) so the SUT's `method_exists` defensive check
	 * passes — Mockery declares methods via __call, which method_exists ignores.
	 */
	private function make_subscription( int $user_id ): object {
		return new class( $user_id ) {
			public function __construct( private int $uid ) {}
			public function get_user_id(): int {
				return $this->uid;
			}
		};
	}

	#[Test]
	public function it_returns_empty_array_for_empty_input(): void {
		Functions\expect( 'get_user_by' )->never();

		$result = ProspectFilter::filter_prospects( array() );

		$this->assertSame( array(), $result );
		$this->assertSame(
			array(),
			$this->prepare_calls,
			'No batch query should be issued for an empty input.'
		);
	}

	#[Test]
	public function it_returns_all_when_no_emails_resolve_to_users(): void {
		global $wpdb;
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );
		Functions\expect( 'get_user_by' )->never();

		$contacts = array(
			array( 'email' => 'alice@example.com' ),
			array( 'email' => 'bob@example.com' ),
			array( 'email' => 'carol@example.com' ),
		);

		$result = ProspectFilter::filter_prospects( $contacts );

		$this->assertSame( $contacts, $result );
		$this->assertCount(
			1,
			$this->prepare_calls,
			'Exactly one batch user query should be issued.'
		);
		$this->assertStringContainsString( 'IN (', $this->prepare_calls[0] );
		$this->assertStringContainsString( 'wp_users', $this->prepare_calls[0] );
	}

	#[Test]
	public function it_returns_empty_when_all_emails_belong_to_active_members(): void {
		global $wpdb;
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			array(
				array(
					'ID'         => 11,
					'user_email' => 'alice@example.com',
				),
				array(
					'ID'         => 22,
					'user_email' => 'bob@example.com',
				),
				array(
					'ID'         => 33,
					'user_email' => 'carol@example.com',
				),
			)
		);

		Functions\expect( 'wcs_get_subscriptions' )->once()->andReturn(
			array(
				$this->make_subscription( 11 ),
				$this->make_subscription( 22 ),
				$this->make_subscription( 33 ),
			)
		);

		Functions\expect( 'wcs_get_users_subscriptions' )->never();
		Functions\expect( 'get_user_by' )->never();

		$contacts = array(
			array( 'email' => 'alice@example.com' ),
			array( 'email' => 'bob@example.com' ),
			array( 'email' => 'carol@example.com' ),
		);

		$result = ProspectFilter::filter_prospects( $contacts );

		$this->assertSame( array(), $result );
	}

	#[Test]
	public function it_returns_only_prospects_in_mixed_case(): void {
		global $wpdb;
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			array(
				// Alice and Dan are members.
				array(
					'ID'         => 11,
					'user_email' => 'alice@example.com',
				),
				array(
					'ID'         => 44,
					'user_email' => 'dan@example.com',
				),
			)
		);

		Functions\expect( 'wcs_get_subscriptions' )->once()->andReturn(
			array(
				$this->make_subscription( 11 ), // Alice has active sub.
				$this->make_subscription( 44 ), // Dan has active sub.
			)
		);

		Functions\expect( 'wcs_get_users_subscriptions' )->never();
		Functions\expect( 'get_user_by' )->never();

		$contacts = array(
			array(
				'email' => 'alice@example.com',
				'name'  => 'Alice',
			),                                               // Member -> excluded.
			array(
				'email' => 'bob@example.com',
				'name'  => 'Bob',
			),                                               // Email but no WP user -> prospect.
			array(
				'email' => '',
				'name'  => 'Carol',
			),                                               // No email -> prospect.
			array(
				'email' => 'dan@example.com',
				'name'  => 'Dan',
			),                                               // Member -> excluded.
		);

		$result = ProspectFilter::filter_prospects( $contacts );

		$this->assertCount( 2, $result );
		$this->assertSame( 'Bob', $result[0]['name'] );
		$this->assertSame( 'Carol', $result[1]['name'] );
	}

	#[Test]
	public function it_makes_exactly_one_batch_user_query_for_n_contacts(): void {
		$contacts = array();
		for ( $i = 1; $i <= 50; $i++ ) {
			$contacts[] = array( 'email' => "person{$i}@example.com" );
		}

		global $wpdb;
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		// Regression guard: the per-row N+1 path must never run.
		Functions\expect( 'get_user_by' )->never();
		Functions\expect( 'wcs_get_users_subscriptions' )->never();

		$result = ProspectFilter::filter_prospects( $contacts );

		$this->assertCount( 50, $result );
		$this->assertCount(
			1,
			$this->prepare_calls,
			'Exactly one batch user query should be issued for N contacts.'
		);

		$placeholder_count = substr_count( $this->prepare_calls[0], '%s' );
		$this->assertSame(
			50,
			$placeholder_count,
			'The IN (...) clause should bind every email as %s placeholder.'
		);
	}
}
