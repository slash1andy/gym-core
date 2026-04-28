<?php
/**
 * Unit tests for PricingCalculator.
 *
 * @package Gym_Core\Tests\Unit\Sales
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Sales;

use Gym_Core\Sales\PricingCalculator;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the sliding-discount pricing calculator.
 */
class PricingCalculatorTest extends TestCase {

/**
	 * @var PricingCalculator
	 */
	private PricingCalculator $calculator;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'__' => static function ( string $text ): string {
					return $text;
				},
			)
		);

		$this->calculator = new PricingCalculator();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 */
	#[Test]
	public function it_calculates_monthly_at_min_down_payment(): void {
		$result = $this->calculator->calculate(
			base_total: 2455.0,
			min_down: 99.0,
			max_down: 999.0,
			max_discount: 200.0,
			down_payment: 99.0,
			billing_period: 'month',
			billing_interval: 1
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( 99.0, $result['down_payment'] );
		$this->assertSame( 0.0, $result['discount'] );
		$this->assertSame( 2455.0, $result['effective_total'] );
		// (2455 - 99) / 12 = 196.33333... → 196.33
		$this->assertSame( 196.33, $result['recurring_payment'] );
		$this->assertSame( 12, $result['installments'] );
		$this->assertSame( '', $result['savings_label'] );
		$this->assertSame( '', $result['error'] );
	}

	#[Test]
	public function it_calculates_monthly_at_max_down_payment(): void {
		$result = $this->calculator->calculate(
			base_total: 2455.0,
			min_down: 99.0,
			max_down: 999.0,
			max_discount: 200.0,
			down_payment: 999.0
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( 999.0, $result['down_payment'] );
		$this->assertSame( 200.0, $result['discount'] );
		$this->assertSame( 2255.0, $result['effective_total'] );
		// (2255 - 999) / 12 = 104.66666... → 104.67
		$this->assertSame( 104.67, $result['recurring_payment'] );
		$this->assertStringContainsString( '$200.00', $result['savings_label'] );
	}

	#[Test]
	public function it_calculates_proportional_discount_at_midpoint(): void {
		$result = $this->calculator->calculate(
			base_total: 2455.0,
			min_down: 99.0,
			max_down: 999.0,
			max_discount: 200.0,
			down_payment: 549.0  // Midpoint of 99-999 range.
		);

		$this->assertTrue( $result['is_valid'] );
		// ratio = (549 - 99) / (999 - 99) = 450 / 900 = 0.5
		// discount = 200 * 0.5 = 100
		$this->assertSame( 100.0, $result['discount'] );
		$this->assertSame( 2355.0, $result['effective_total'] );
		// (2355 - 549) / 12 = 150.5
		$this->assertSame( 150.5, $result['recurring_payment'] );
	}

	#[Test]
	public function it_calculates_biweekly_installments(): void {
		$result = $this->calculator->calculate(
			base_total: 2249.0,
			min_down: 99.0,
			max_down: 599.0,
			max_discount: 150.0,
			down_payment: 299.0,
			billing_period: 'week',
			billing_interval: 2
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( 26, $result['installments'] );
		$this->assertSame( 'every 2 weeks', $result['billing_label'] );

		// ratio = (299 - 99) / (599 - 99) = 200 / 500 = 0.4
		// discount = 150 * 0.4 = 60
		$this->assertSame( 60.0, $result['discount'] );
		// effective_total = 2249 - 60 = 2189
		// recurring = (2189 - 299) / 26 = 72.69230... → 72.69
		$this->assertSame( 72.69, $result['recurring_payment'] );
	}

	#[Test]
	public function it_rejects_down_payment_below_minimum(): void {
		$result = $this->calculator->calculate(
			base_total: 2455.0,
			min_down: 99.0,
			max_down: 999.0,
			max_discount: 200.0,
			down_payment: 50.0
		);

		$this->assertFalse( $result['is_valid'] );
		$this->assertNotEmpty( $result['error'] );
		$this->assertSame( 0.0, $result['recurring_payment'] );
	}

	#[Test]
	public function it_rejects_down_payment_above_maximum(): void {
		$result = $this->calculator->calculate(
			base_total: 2455.0,
			min_down: 99.0,
			max_down: 999.0,
			max_discount: 200.0,
			down_payment: 1500.0
		);

		$this->assertFalse( $result['is_valid'] );
		$this->assertStringContainsString( '$999.00', $result['error'] );
	}

	#[Test]
	public function it_rejects_zero_base_total(): void {
		$result = $this->calculator->calculate(
			base_total: 0.0,
			min_down: 0.0,
			max_down: 100.0,
			max_discount: 10.0,
			down_payment: 50.0
		);

		$this->assertFalse( $result['is_valid'] );
	}

	#[Test]
	public function it_rejects_down_payment_exceeding_effective_total(): void {
		// With a very high down payment and discount, the remaining could be <= 0.
		$result = $this->calculator->calculate(
			base_total: 500.0,
			min_down: 100.0,
			max_down: 500.0,
			max_discount: 50.0,
			down_payment: 500.0
		);

		// effective_total = 500 - 50 = 450, remaining = 450 - 500 = -50 → invalid.
		$this->assertFalse( $result['is_valid'] );
	}

	#[Test]
	public function it_handles_equal_min_and_max_down_payment(): void {
		$result = $this->calculator->calculate(
			base_total: 2000.0,
			min_down: 500.0,
			max_down: 500.0,
			max_discount: 100.0,
			down_payment: 500.0
		);

		$this->assertTrue( $result['is_valid'] );
		// When range is 0, discount_ratio is 0, so no discount.
		$this->assertSame( 0.0, $result['discount'] );
		$this->assertSame( 2000.0, $result['effective_total'] );
		// (2000 - 500) / 12 = 125.0
		$this->assertSame( 125.0, $result['recurring_payment'] );
	}

	#[Test]
	public function it_defaults_to_monthly_billing(): void {
		$result = $this->calculator->calculate(
			base_total: 1200.0,
			min_down: 0.0,
			max_down: 600.0,
			max_discount: 0.0,
			down_payment: 0.0
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( 12, $result['installments'] );
		$this->assertSame( 'per month', $result['billing_label'] );
		$this->assertSame( 100.0, $result['recurring_payment'] );
	}
}
