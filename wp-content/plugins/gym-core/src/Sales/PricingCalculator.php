<?php
/**
 * Sales kiosk pricing calculator.
 *
 * Implements the sliding-discount model: higher down payment earns a
 * proportional discount on the total contract value, reducing the
 * recurring monthly (or biweekly) payment.
 *
 * @package Gym_Core\Sales
 * @since   4.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Sales;

/**
 * Pure calculation class for dynamic membership pricing.
 *
 * Formula:
 *   discount_ratio   = (down_payment - min_down) / (max_down - min_down)
 *   discount         = max_discount * discount_ratio
 *   effective_total  = base_total - discount
 *   recurring        = (effective_total - down_payment) / installments
 *
 * Installments:
 *   - Monthly billing (period=month, interval=1): 12
 *   - Biweekly billing (period=week, interval=2): 26
 */
final class PricingCalculator {

	/**
	 * Installment count for monthly billing (12 months).
	 */
	private const MONTHLY_INSTALLMENTS = 12;

	/**
	 * Installment count for biweekly billing (26 pay periods per year).
	 */
	private const BIWEEKLY_INSTALLMENTS = 26;

	/**
	 * Calculates the pricing breakdown for a given down payment.
	 *
	 * @param float  $base_total      Total contract value at min down payment.
	 * @param float  $min_down        Minimum allowed down payment.
	 * @param float  $max_down        Maximum allowed down payment.
	 * @param float  $max_discount    Maximum discount at max down payment.
	 * @param float  $down_payment    The chosen down payment amount.
	 * @param string $billing_period   Subscription billing period ('month' or 'week').
	 * @param int    $billing_interval Subscription billing interval (1 for monthly, 2 for biweekly).
	 * @return array{
	 *     is_valid: bool,
	 *     down_payment: float,
	 *     recurring_payment: float,
	 *     effective_total: float,
	 *     discount: float,
	 *     savings_label: string,
	 *     installments: int,
	 *     billing_label: string,
	 *     error: string
	 * }
	 */
	public function calculate(
		float $base_total,
		float $min_down,
		float $max_down,
		float $max_discount,
		float $down_payment,
		string $billing_period = 'month',
		int $billing_interval = 1
	): array {
		$error = $this->validate( $base_total, $min_down, $max_down, $max_discount, $down_payment );

		if ( '' !== $error ) {
			return $this->error_result( $down_payment, $error );
		}

		$installments  = $this->get_installments( $billing_period, $billing_interval );
		$billing_label = $this->get_billing_label( $billing_period, $billing_interval );

		// Calculate sliding discount.
		$down_range      = $max_down - $min_down;
		$discount_ratio  = $down_range > 0.0 ? ( $down_payment - $min_down ) / $down_range : 0.0;
		$discount        = round( $max_discount * $discount_ratio, 2 );
		$effective_total = round( $base_total - $discount, 2 );

		// Calculate recurring payment.
		$remaining = $effective_total - $down_payment;
		$recurring = $installments > 0 ? round( $remaining / $installments, 2 ) : 0.0;

		// Validate recurring is positive.
		if ( $recurring <= 0.0 ) {
			return $this->error_result(
				$down_payment,
				__( 'Down payment exceeds or equals the contract total.', 'gym-core' )
			);
		}

		return array(
			'is_valid'          => true,
			'down_payment'      => round( $down_payment, 2 ),
			'recurring_payment' => $recurring,
			'effective_total'   => $effective_total,
			'discount'          => $discount,
			'savings_label'     => $discount > 0.0
				/* translators: %s: formatted discount amount */
				? sprintf( __( 'You save %s', 'gym-core' ), '$' . number_format( $discount, 2 ) )
				: '',
			'installments'      => $installments,
			'billing_label'     => $billing_label,
			'error'             => '',
		);
	}

	/**
	 * Calculates pricing from a WooCommerce product ID.
	 *
	 * Reads the product's sales kiosk meta and subscription settings
	 * to compute the pricing breakdown.
	 *
	 * @param int   $product_id   The WooCommerce product ID.
	 * @param float $down_payment The chosen down payment amount.
	 * @return array{
	 *     is_valid: bool,
	 *     down_payment: float,
	 *     recurring_payment: float,
	 *     effective_total: float,
	 *     discount: float,
	 *     savings_label: string,
	 *     installments: int,
	 *     billing_label: string,
	 *     error: string
	 * }
	 */
	public function calculate_for_product( int $product_id, float $down_payment ): array {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->error_result( $down_payment, __( 'Product not found.', 'gym-core' ) );
		}

		$base_total   = (float) $product->get_meta( ProductMetaBox::META_BASE_TOTAL, true );
		$min_down     = (float) $product->get_meta( ProductMetaBox::META_MIN_DOWN, true );
		$max_down     = (float) $product->get_meta( ProductMetaBox::META_MAX_DOWN, true );
		$max_discount = (float) $product->get_meta( ProductMetaBox::META_MAX_DISCOUNT, true );

		if ( $base_total <= 0.0 ) {
			return $this->error_result(
				$down_payment,
				__( 'Product does not have sales kiosk pricing configured.', 'gym-core' )
			);
		}

		// Read subscription billing settings.
		$billing_period   = (string) $product->get_meta( '_subscription_period', true );
		$billing_interval = (int) $product->get_meta( '_subscription_period_interval', true );

		if ( '' === $billing_period ) {
			$billing_period = 'month';
		}
		if ( 0 === $billing_interval ) {
			$billing_interval = 1;
		}

		return $this->calculate(
			$base_total,
			$min_down,
			$max_down,
			$max_discount,
			$down_payment,
			$billing_period,
			$billing_interval
		);
	}

	/**
	 * Validates the input parameters.
	 *
	 * @param float $base_total   Base contract total.
	 * @param float $min_down     Minimum down payment.
	 * @param float $max_down     Maximum down payment.
	 * @param float $max_discount Maximum discount.
	 * @param float $down_payment Chosen down payment.
	 * @return string Error message, or empty string if valid.
	 */
	private function validate(
		float $base_total,
		float $min_down,
		float $max_down,
		float $max_discount,
		float $down_payment
	): string {
		if ( $base_total <= 0.0 ) {
			return __( 'Base total must be greater than zero.', 'gym-core' );
		}

		if ( $min_down < 0.0 || $max_down < 0.0 || $max_discount < 0.0 ) {
			return __( 'Pricing parameters must not be negative.', 'gym-core' );
		}

		if ( $max_down < $min_down ) {
			return __( 'Maximum down payment must be at least the minimum.', 'gym-core' );
		}

		if ( $down_payment < $min_down ) {
			return sprintf(
				/* translators: %s: minimum down payment amount */
				__( 'Down payment must be at least %s.', 'gym-core' ),
				'$' . number_format( $min_down, 2 )
			);
		}

		if ( $down_payment > $max_down ) {
			return sprintf(
				/* translators: %s: maximum down payment amount */
				__( 'Down payment cannot exceed %s.', 'gym-core' ),
				'$' . number_format( $max_down, 2 )
			);
		}

		if ( $max_discount >= $base_total ) {
			return __( 'Maximum discount must be less than the base total.', 'gym-core' );
		}

		return '';
	}

	/**
	 * Determines the number of installments from the billing schedule.
	 *
	 * @param string $period   Billing period ('month', 'week', etc.).
	 * @param int    $interval Billing interval.
	 * @return int
	 */
	private function get_installments( string $period, int $interval ): int {
		if ( 'week' === $period && 2 === $interval ) {
			return self::BIWEEKLY_INSTALLMENTS;
		}

		return self::MONTHLY_INSTALLMENTS;
	}

	/**
	 * Returns a human-readable billing frequency label.
	 *
	 * @param string $period   Billing period.
	 * @param int    $interval Billing interval.
	 * @return string
	 */
	private function get_billing_label( string $period, int $interval ): string {
		if ( 'week' === $period && 2 === $interval ) {
			return __( 'every 2 weeks', 'gym-core' );
		}

		return __( 'per month', 'gym-core' );
	}

	/**
	 * Returns a standardised error result array.
	 *
	 * @param float  $down_payment The requested down payment.
	 * @param string $error        Error message.
	 * @return array{
	 *     is_valid: bool,
	 *     down_payment: float,
	 *     recurring_payment: float,
	 *     effective_total: float,
	 *     discount: float,
	 *     savings_label: string,
	 *     installments: int,
	 *     billing_label: string,
	 *     error: string
	 * }
	 */
	private function error_result( float $down_payment, string $error ): array {
		return array(
			'is_valid'          => false,
			'down_payment'      => round( $down_payment, 2 ),
			'recurring_payment' => 0.0,
			'effective_total'   => 0.0,
			'discount'          => 0.0,
			'savings_label'     => '',
			'installments'      => 0,
			'billing_label'     => '',
			'error'             => $error,
		);
	}
}
