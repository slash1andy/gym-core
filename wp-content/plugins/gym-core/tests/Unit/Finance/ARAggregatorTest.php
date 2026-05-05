<?php
/**
 * Unit tests for the AR aging aggregator.
 *
 * Pin the bucket boundaries (0-30, 31-60, 61-90, 90+) and the response
 * shape so the dashboard widget never has to guess about an unknown bucket
 * key. The boundary tests are exhaustive: a renewal failed exactly 30
 * days ago must land in 0_30, exactly 31 days in 31_60, etc.
 *
 * @package Gym_Core\Tests\Unit\Finance
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Finance;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Finance\ARAggregator;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests ARAggregator::aggregate() bucket math + response shape.
 */
class ARAggregatorTest extends TestCase {

	/**
	 * Test "now" anchor — chosen so day deltas are easy to read.
	 *
	 * @var int
	 */
	private const NOW_TS = 1714521600; // 2026-05-01T00:00:00Z

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		Functions\when( 'current_time' )->justReturn( self::NOW_TS );
		Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );
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

	/**
	 * Bucket math: 30-day-old = 0_30, 31 = 31_60, 60 = 31_60, 61 = 61_90,
	 * 90 = 61_90, 91 = 90_plus, 180 = 90_plus.
	 *
	 * @return void
	 */
	public function test_bucket_for_days_pins_boundaries(): void {
		$this->assertSame( '0_30', ARAggregator::bucket_for_days( 0 ) );
		$this->assertSame( '0_30', ARAggregator::bucket_for_days( 30 ) );
		$this->assertSame( '31_60', ARAggregator::bucket_for_days( 31 ) );
		$this->assertSame( '31_60', ARAggregator::bucket_for_days( 60 ) );
		$this->assertSame( '61_90', ARAggregator::bucket_for_days( 61 ) );
		$this->assertSame( '61_90', ARAggregator::bucket_for_days( 90 ) );
		$this->assertSame( '90_plus', ARAggregator::bucket_for_days( 91 ) );
		$this->assertSame( '90_plus', ARAggregator::bucket_for_days( 365 ) );
	}

	/**
	 * Negative inputs are clamped — defensive: no negative-bucket lookups.
	 *
	 * @return void
	 */
	public function test_bucket_for_days_clamps_negative(): void {
		$this->assertSame( '0_30', ARAggregator::bucket_for_days( -5 ) );
	}

	/**
	 * Aggregating four orders at staggered days produces correct bucket totals.
	 *
	 * @return void
	 */
	public function test_aggregate_groups_orders_into_correct_buckets(): void {
		$orders = array(
			$this->make_order( 1, 100.00, self::NOW_TS - ( 5 * DAY_IN_SECONDS ) ),     // 5 days  → 0_30
			$this->make_order( 2, 200.00, self::NOW_TS - ( 45 * DAY_IN_SECONDS ) ),    // 45 days → 31_60
			$this->make_order( 3, 300.00, self::NOW_TS - ( 75 * DAY_IN_SECONDS ) ),    // 75 days → 61_90
			$this->make_order( 4, 400.00, self::NOW_TS - ( 200 * DAY_IN_SECONDS ) ),   // 200     → 90_plus
		);

		$sut    = new ARAggregator_TestableForOrders( $orders );
		$result = $sut->aggregate( self::NOW_TS );

		$this->assertSame( 1, $result['totals']['0_30']['count'] );
		$this->assertSame( 100.00, $result['totals']['0_30']['amount'] );

		$this->assertSame( 1, $result['totals']['31_60']['count'] );
		$this->assertSame( 200.00, $result['totals']['31_60']['amount'] );

		$this->assertSame( 1, $result['totals']['61_90']['count'] );
		$this->assertSame( 300.00, $result['totals']['61_90']['amount'] );

		$this->assertSame( 1, $result['totals']['90_plus']['count'] );
		$this->assertSame( 400.00, $result['totals']['90_plus']['amount'] );

		$this->assertCount( 4, $result['rows'] );
	}

	/**
	 * Empty receivables produce a stable shape — no missing bucket keys.
	 *
	 * @return void
	 */
	public function test_aggregate_with_no_orders_returns_zeroed_buckets(): void {
		$sut    = new ARAggregator_TestableForOrders( array() );
		$result = $sut->aggregate( self::NOW_TS );

		foreach ( array( '0_30', '31_60', '61_90', '90_plus' ) as $bucket ) {
			$this->assertSame( 0, $result['totals'][ $bucket ]['count'] );
			$this->assertSame( 0.0, $result['totals'][ $bucket ]['amount'] );
		}
		$this->assertSame( array(), $result['rows'] );
		$this->assertSame( 'USD', $result['currency'] );
	}

	/**
	 * Builds a Mockery WC_Order-shaped double.
	 *
	 * @param int    $id      Order ID.
	 * @param float  $total   Order total.
	 * @param int    $created_ts Created timestamp.
	 * @return mixed
	 */
	private function make_order( int $id, float $total, int $created_ts ) {
		$created = Mockery::mock();
		$created->allows( 'getTimestamp' )->andReturn( $created_ts );
		$created->allows( 'date' )->andReturn( gmdate( 'Y-m-d', $created_ts ) );

		$order = Mockery::mock();
		$order->allows( 'get_id' )->andReturn( $id );
		$order->allows( 'get_customer_id' )->andReturn( 100 + $id );
		$order->allows( 'get_billing_first_name' )->andReturn( 'Test' );
		$order->allows( 'get_billing_last_name' )->andReturn( 'Member ' . $id );
		$order->allows( 'get_billing_email' )->andReturn( 'test@example.com' );
		$order->allows( 'get_total' )->andReturn( $total );
		$order->allows( 'get_currency' )->andReturn( 'USD' );
		$order->allows( 'get_status' )->andReturn( 'failed' );
		$order->allows( 'get_date_created' )->andReturn( $created );

		return $order;
	}
}

/**
 * Test seam: subclass that bypasses wc_get_orders() so tests don't need a
 * Brain\Monkey alias on a function that's defined dynamically by stubs.
 */
class ARAggregator_TestableForOrders extends ARAggregator {

	/**
	 * Pre-canned orders.
	 *
	 * @var array
	 */
	private array $canned;

	/**
	 * Constructor.
	 *
	 * @param array $orders Orders.
	 */
	public function __construct( array $orders ) {
		$this->canned = $orders;
	}

	/**
	 * Override wc_get_orders() with the canned fixture.
	 *
	 * @return array
	 */
	protected function fetch_orders(): array {
		return $this->canned;
	}
}
