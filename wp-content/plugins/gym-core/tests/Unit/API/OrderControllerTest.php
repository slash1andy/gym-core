<?php
/**
 * Unit tests for OrderController::get_subscriptions_summary().
 *
 * Locks in the regression: when WooCommerce Subscriptions reports N active
 * subscriptions, the endpoint returns active_count = N. This is the bug that
 * shipped Pippin's "subscriptions: 0" injected context — fixing the source
 * query but leaving no test would invite the silent regression to come back.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\OrderController;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OrderController::get_subscriptions_summary().
 */
class OrderControllerTest extends TestCase {

/**
	 * The System Under Test.
	 *
	 * @var OrderController
	 */
	private OrderController $sut;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);

		$this->sut = new OrderController();
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
	 * Builds a mock WC_Subscription configured with the supplied financials.
	 *
	 * @param string $total    Recurring total per billing cycle.
	 * @param string $period   Billing period: day / week / month / year.
	 * @param int    $interval Billing interval (e.g. 3 for "every 3 months").
	 * @param string $currency Currency code.
	 * @return \WC_Subscription&\Mockery\MockInterface
	 */
	private function make_subscription( string $total, string $period = 'month', int $interval = 1, string $currency = 'USD' ): \WC_Subscription {
		$sub = Mockery::mock( \WC_Subscription::class );
		$sub->allows( 'get_total' )->andReturn( $total );
		$sub->allows( 'get_billing_period' )->andReturn( $period );
		$sub->allows( 'get_billing_interval' )->andReturn( $interval );
		$sub->allows( 'get_currency' )->andReturn( $currency );

		return $sub;
	}

/**
	 * Builds a mock WP_REST_Request — endpoint takes no params, kept for
	 * symmetry with the controller signature.
	 *
	 * @return \WP_REST_Request&\Mockery\MockInterface
	 */
	private function make_request(): \WP_REST_Request {
		return Mockery::mock( \WP_REST_Request::class );
	}

	// -------------------------------------------------------------------------
	// get_subscriptions_summary
	// -------------------------------------------------------------------------

/**
	 * Regression: a store with N active subscriptions reports active_count = N.
	 *
	 * The original Pippin context bug returned 0 because the upstream query
	 * was paginated to per_page=1, then count()'d. This test pins the
	 * non-zero contract — adding a single test that mirrors a "store has
	 * customers" reality.
	 *
	 * @return void
	 */
	public function test_get_subscriptions_summary_counts_all_active_subscriptions(): void {
		$active = array(
			$this->make_subscription( '50.00' ),
			$this->make_subscription( '50.00' ),
			$this->make_subscription( '120.00' ),
		);

		Functions\expect( 'wcs_get_subscriptions' )
			->twice()
			->andReturnUsing(
				static function ( array $args ) use ( $active ) {
					$status = $args['subscription_status'] ?? array();
					if ( in_array( 'active', $status, true ) ) {
						return $active;
					}
					return array();
				}
			);

		$response = $this->sut->get_subscriptions_summary( $this->make_request() );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$body = $response->get_data();

		$this->assertTrue( $body['success'] );
		$this->assertSame( 3, $body['data']['active_count'] );
		$this->assertSame( 0, $body['data']['on_hold_count'] );
	}

/**
	 * MRR is the sum of monthly-equivalent recurring totals across active subs.
	 *
	 * Verifies period normalisation: a $1200/year sub contributes $100/mo;
	 * a $50/month sub contributes $50/mo. Both rolled into a single MRR
	 * figure in the primary currency.
	 *
	 * @return void
	 */
	public function test_get_subscriptions_summary_normalises_periods_into_mrr(): void {
		$active = array(
			$this->make_subscription( '50.00', 'month', 1 ),
			$this->make_subscription( '1200.00', 'year', 1 ),
		);

		Functions\expect( 'wcs_get_subscriptions' )
			->twice()
			->andReturnUsing(
				static function ( array $args ) use ( $active ) {
					$status = $args['subscription_status'] ?? array();
					return in_array( 'active', $status, true ) ? $active : array();
				}
			);

		$response = $this->sut->get_subscriptions_summary( $this->make_request() );
		$body     = $response->get_data();

		$this->assertSame( 150.00, $body['data']['mrr'] );
		$this->assertSame( 1800.00, $body['data']['arr'] );
		$this->assertSame( 'USD', $body['data']['currency'] );
		$this->assertSame( array( 'USD' => 150.00 ), $body['data']['mrr_by_currency'] );
	}

/**
	 * Multi-currency stores see a per-currency breakdown rather than a
	 * silently-aggregated single number.
	 *
	 * @return void
	 */
	public function test_get_subscriptions_summary_splits_multi_currency_mrr(): void {
		$active = array(
			$this->make_subscription( '100.00', 'month', 1, 'USD' ),
			$this->make_subscription( '50.00', 'month', 1, 'EUR' ),
			$this->make_subscription( '75.00', 'month', 1, 'USD' ),
		);

		Functions\expect( 'wcs_get_subscriptions' )
			->twice()
			->andReturnUsing(
				static function ( array $args ) use ( $active ) {
					$status = $args['subscription_status'] ?? array();
					return in_array( 'active', $status, true ) ? $active : array();
				}
			);

		$response = $this->sut->get_subscriptions_summary( $this->make_request() );
		$body     = $response->get_data();

		$this->assertSame( 175.00, $body['data']['mrr'], 'Primary MRR should be the largest currency bucket.' );
		$this->assertSame( 'USD', $body['data']['currency'] );
		$this->assertEqualsCanonicalizing(
			array( 'USD' => 175.00, 'EUR' => 50.00 ),
			$body['data']['mrr_by_currency']
		);
	}

/**
	 * On-hold and pending-cancel subs are counted separately and excluded
	 * from MRR — they aren't currently billing.
	 *
	 * @return void
	 */
	public function test_get_subscriptions_summary_separates_on_hold_from_mrr(): void {
		$active  = array( $this->make_subscription( '100.00' ) );
		$on_hold = array(
			$this->make_subscription( '100.00' ),
			$this->make_subscription( '100.00' ),
		);

		Functions\expect( 'wcs_get_subscriptions' )
			->twice()
			->andReturnUsing(
				static function ( array $args ) use ( $active, $on_hold ) {
					$status = $args['subscription_status'] ?? array();
					if ( in_array( 'active', $status, true ) ) {
						return $active;
					}
					if ( in_array( 'on-hold', $status, true ) ) {
						return $on_hold;
					}
					return array();
				}
			);

		$response = $this->sut->get_subscriptions_summary( $this->make_request() );
		$body     = $response->get_data();

		$this->assertSame( 1, $body['data']['active_count'] );
		$this->assertSame( 2, $body['data']['on_hold_count'] );
		$this->assertSame( 100.00, $body['data']['mrr'] );
	}

}
