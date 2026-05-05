<?php
/**
 * Unit tests for the monthly close orchestrator.
 *
 * Pin three behaviours that the Joy + Darby workflow relies on:
 *  1. Invalid YYYY-MM input never crashes — it returns a structured error.
 *  2. A successful run is cached, and a re-run without `force` returns the
 *     same payload with `from_cache => true` (idempotent sign-off).
 *  3. The four steps execute in the documented order and produce a
 *     `request_signoff` envelope referencing prior step outputs.
 *
 * @package Gym_Core\Tests\Unit\Finance
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Finance;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Finance\MonthlyClose;
use PHPUnit\Framework\TestCase;

/**
 * Tests MonthlyClose::run() and ::get_status().
 */
class MonthlyCloseTest extends TestCase {

	/**
	 * In-memory option store so we don't touch a real WP options table.
	 *
	 * @var array<string, mixed>
	 */
	private array $options;

	/**
	 * Set up the test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options = array();

		Functions\when( 'get_option' )->alias(
			function ( $key, $default = null ) {
				return $this->options[ $key ] ?? $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) {
				$this->options[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'do_action' )->justReturn( null );

		Functions\when( 'wp_upload_dir' )->justReturn(
			array( 'basedir' => sys_get_temp_dir() . '/gym-finance-test' )
		);

		Functions\when( 'wp_mkdir_p' )->alias(
			static function ( $dir ) {
				if ( ! is_dir( $dir ) ) {
					return mkdir( $dir, 0777, true );
				}
				return true;
			}
		);

		Functions\when( 'trailingslashit' )->alias(
			static fn( $str ) => rtrim( (string) $str, '/' ) . '/'
		);

		Functions\when( 'sanitize_file_name' )->alias(
			static fn( $str ) => preg_replace( '/[^a-zA-Z0-9._-]/', '-', (string) $str )
		);
	}

	/**
	 * Tear down the test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A garbage month string never blows up.
	 *
	 * @return void
	 */
	public function test_invalid_month_returns_error_envelope(): void {
		$close  = new MonthlyClose();
		$result = $close->run( 'not-a-month' );

		$this->assertSame( false, $result['success'] );
		$this->assertSame( 'invalid_month', $result['code'] );
	}

	/**
	 * 13-month input is rejected (regex enforces 01-12).
	 *
	 * @return void
	 */
	public function test_month_out_of_range_is_rejected(): void {
		$close  = new MonthlyClose();
		$result = $close->run( '2026-13' );

		$this->assertSame( false, $result['success'] );
	}

	/**
	 * A valid run returns a payload with all four steps and a sign-off envelope.
	 *
	 * @return void
	 */
	public function test_valid_run_executes_four_steps_in_order(): void {
		$close  = new MonthlyClose();
		$result = $close->run( '2026-04' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '2026-04', $result['month'] );
		$this->assertFalse( $result['from_cache'] );

		$expected_steps = array(
			'reconcile_payouts',
			'flag_refunded_subscriptions',
			'export_payroll_attendance',
			'request_signoff',
		);
		$this->assertSame( $expected_steps, array_keys( $result['steps'] ) );

		$signoff = $result['steps']['request_signoff'];
		$this->assertSame( 'ok', $signoff['status'] );
		$this->assertTrue( $signoff['queued'] );
		$this->assertSame( '2026-04', $signoff['summary']['month'] );
	}

	/**
	 * Re-running for the same month returns the cached payload with the
	 * `from_cache` flag flipped.
	 *
	 * @return void
	 */
	public function test_rerun_returns_cached_payload(): void {
		$close = new MonthlyClose();

		$first = $close->run( '2026-04' );
		$this->assertFalse( $first['from_cache'] );

		$second = $close->run( '2026-04' );
		$this->assertTrue( $second['from_cache'] );
		$this->assertSame( $first['steps'], $second['steps'] );
	}

	/**
	 * `force = true` bypasses the cache and recomputes.
	 *
	 * @return void
	 */
	public function test_force_bypasses_cache(): void {
		$close = new MonthlyClose();

		$close->run( '2026-04' );
		$forced = $close->run( '2026-04', true );

		$this->assertFalse( $forced['from_cache'] );
	}

	/**
	 * get_status() returns null for an un-run month and the payload for a
	 * completed one.
	 *
	 * @return void
	 */
	public function test_get_status_returns_null_until_run(): void {
		$close = new MonthlyClose();

		$this->assertNull( $close->get_status( '2026-04' ) );

		$close->run( '2026-04' );
		$status = $close->get_status( '2026-04' );

		$this->assertIsArray( $status );
		$this->assertSame( '2026-04', $status['month'] );
	}

	/**
	 * get_status() with a malformed month is null, not an exception.
	 *
	 * @return void
	 */
	public function test_get_status_invalid_month_returns_null(): void {
		$close = new MonthlyClose();
		$this->assertNull( $close->get_status( 'nope' ) );
	}
}
