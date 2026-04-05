<?php
/**
 * Sales kiosk order builder.
 *
 * Creates WooCommerce orders and subscriptions programmatically
 * for the tablet-based sales kiosk, with custom pricing overrides.
 *
 * @package Gym_Core\Sales
 * @since   4.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Sales;

/**
 * Builds WooCommerce orders with subscription products and custom pricing.
 *
 * The builder:
 * 1. Finds or creates a WordPress user for the customer.
 * 2. Creates a WC_Order with the subscription product.
 * 3. Overrides the signup fee (down payment) and recurring amount.
 * 4. Returns the order pay URL for immediate payment collection.
 */
final class OrderBuilder {

	/**
	 * Order meta key indicating the order originated from the sales kiosk.
	 */
	public const META_KIOSK_ORIGIN = '_gym_sales_kiosk';

	/**
	 * Order meta key for the down payment amount.
	 */
	public const META_DOWN_PAYMENT = '_gym_down_payment';

	/**
	 * Order meta key for the calculated recurring amount.
	 */
	public const META_RECURRING = '_gym_recurring_payment';

	/**
	 * Order meta key for the staff member who processed the sale.
	 */
	public const META_STAFF_ID = '_gym_sales_staff_id';

	/**
	 * Creates a pending order with subscription for a membership product.
	 *
	 * @param int                   $product_id   WooCommerce product ID.
	 * @param float                 $down_payment Down payment amount.
	 * @param array<string, mixed>  $pricing      Validated pricing from PricingCalculator.
	 * @param array<string, string> $customer     Customer details.
	 * @param string                $location     Gym location slug.
	 * @param int                   $staff_id     Staff user ID who processed the sale.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create(
		int $product_id,
		float $down_payment,
		array $pricing,
		array $customer,
		string $location,
		int $staff_id
	): array|\WP_Error {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new \WP_Error(
				'gym_product_not_found',
				__( 'Product not found.', 'gym-core' ),
				array( 'status' => 404 )
			);
		}

		// Ensure it's a subscription product.
		if ( ! in_array( $product->get_type(), array( 'subscription', 'variable-subscription' ), true ) ) {
			return new \WP_Error(
				'gym_not_subscription',
				__( 'Product is not a subscription.', 'gym-core' ),
				array( 'status' => 422 )
			);
		}

		// Find or create customer user.
		$user_id = $this->find_or_create_user( $customer );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Create the order.
		try {
			$order = $this->build_order( $product, $user_id, $down_payment, $pricing, $customer, $location, $staff_id );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'gym_order_creation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}

		// Build pay URL with kiosk flag.
		$pay_url = $order->get_checkout_payment_url();
		$pay_url = add_query_arg( 'gym_sales_kiosk', '1', $pay_url );

		/**
		 * Fires after a sales kiosk order is created.
		 *
		 * @since 4.0.0
		 *
		 * @param \WC_Order $order      The created order.
		 * @param int       $product_id The subscription product ID.
		 * @param int       $staff_id   The staff member who processed the sale.
		 */
		do_action( 'gym_core_sales_order_created', $order, $product_id, $staff_id );

		return array(
			'order_id' => $order->get_id(),
			'pay_url'  => esc_url_raw( $pay_url ),
		);
	}

	/**
	 * Finds an existing WordPress user by email or creates a new one.
	 *
	 * @param array<string, string> $customer Customer data with at least 'email'.
	 * @return int|\WP_Error User ID or error.
	 */
	private function find_or_create_user( array $customer ): int|\WP_Error {
		$email = $customer['email'] ?? '';

		if ( empty( $email ) ) {
			return new \WP_Error(
				'gym_missing_email',
				__( 'Customer email is required.', 'gym-core' ),
				array( 'status' => 422 )
			);
		}

		// Check for existing user.
		$existing = get_user_by( 'email', $email );

		if ( $existing ) {
			// Update billing details if not already set.
			$this->update_user_billing( $existing->ID, $customer );
			return $existing->ID;
		}

		// Create new customer via WooCommerce.
		$username = wc_create_new_customer_username(
			$email,
			array(
				'first_name' => $customer['first_name'] ?? '',
				'last_name'  => $customer['last_name'] ?? '',
			)
		);

		$password = wp_generate_password();

		$user_id = wc_create_new_customer(
			$email,
			$username,
			$password,
			array(
				'first_name' => $customer['first_name'] ?? '',
				'last_name'  => $customer['last_name'] ?? '',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error(
				'gym_user_creation_failed',
				$user_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$this->update_user_billing( $user_id, $customer );

		return $user_id;
	}

	/**
	 * Updates user billing meta from customer data.
	 *
	 * @param int                   $user_id  WordPress user ID.
	 * @param array<string, string> $customer Customer data.
	 * @return void
	 */
	private function update_user_billing( int $user_id, array $customer ): void {
		$billing_fields = array(
			'billing_first_name' => $customer['first_name'] ?? '',
			'billing_last_name'  => $customer['last_name'] ?? '',
			'billing_email'      => $customer['email'] ?? '',
			'billing_phone'      => $customer['phone'] ?? '',
			'billing_address_1'  => $customer['address_1'] ?? '',
			'billing_city'       => $customer['city'] ?? '',
			'billing_state'      => $customer['state'] ?? '',
			'billing_postcode'   => $customer['postcode'] ?? '',
			'billing_country'    => 'US',
		);

		foreach ( $billing_fields as $key => $value ) {
			if ( ! empty( $value ) ) {
				update_user_meta( $user_id, $key, $value );
			}
		}
	}

	/**
	 * Builds the WC_Order with the subscription product and custom pricing.
	 *
	 * @param \WC_Product           $product      The subscription product.
	 * @param int                   $user_id      Customer user ID.
	 * @param float                 $down_payment Down payment amount.
	 * @param array<string, mixed>  $pricing      Pricing breakdown.
	 * @param array<string, string> $customer     Customer details.
	 * @param string                $location     Gym location slug.
	 * @param int                   $staff_id     Staff user ID.
	 * @return \WC_Order
	 *
	 * @throws \Exception On order creation failure.
	 */
	private function build_order(
		\WC_Product $product,
		int $user_id,
		float $down_payment,
		array $pricing,
		array $customer,
		string $location,
		int $staff_id
	): \WC_Order {
		$order = wc_create_order(
			array(
				'customer_id' => $user_id,
				'status'      => 'pending',
			)
		);

		if ( is_wp_error( $order ) ) {
			throw new \Exception( esc_html( $order->get_error_message() ) );
		}

		// Add subscription product as line item.
		$item_id = $order->add_product(
			$product,
			1,
			array(
				'subtotal' => $down_payment,
				'total'    => $down_payment,
			)
		);

		if ( ! $item_id ) {
			throw new \Exception( esc_html__( 'Failed to add product to order.', 'gym-core' ) );
		}

		// Set billing address on the order.
		$order->set_billing_first_name( $customer['first_name'] ?? '' );
		$order->set_billing_last_name( $customer['last_name'] ?? '' );
		$order->set_billing_email( $customer['email'] ?? '' );
		$order->set_billing_phone( $customer['phone'] ?? '' );
		$order->set_billing_address_1( $customer['address_1'] ?? '' );
		$order->set_billing_city( $customer['city'] ?? '' );
		$order->set_billing_state( $customer['state'] ?? '' );
		$order->set_billing_postcode( $customer['postcode'] ?? '' );
		$order->set_billing_country( 'US' );

		// Set order total to the down payment (initial payment).
		$order->set_total( (string) $down_payment );

		// Add kiosk-specific meta (HPOS-compatible).
		$order->update_meta_data( self::META_KIOSK_ORIGIN, '1' );
		$order->update_meta_data( self::META_DOWN_PAYMENT, (string) $down_payment );
		$order->update_meta_data( self::META_RECURRING, (string) $pricing['recurring_payment'] );
		$order->update_meta_data( self::META_STAFF_ID, (string) $staff_id );
		$order->update_meta_data( '_gym_location', $location );

		// Store pricing breakdown for reference.
		$order->update_meta_data( '_gym_effective_total', (string) $pricing['effective_total'] );
		$order->update_meta_data( '_gym_discount', (string) $pricing['discount'] );

		// Add order note for staff context.
		$order->add_order_note(
			sprintf(
				/* translators: 1: down payment amount, 2: recurring amount, 3: billing label */
				__( 'Sales kiosk order — Down payment: $%1$s, Recurring: $%2$s %3$s', 'gym-core' ),
				number_format( $down_payment, 2 ),
				number_format( (float) $pricing['recurring_payment'], 2 ),
				$pricing['billing_label'] ?? ''
			)
		);

		$order->save();

		// Create the subscription if WC Subscriptions is active.
		if ( function_exists( 'wcs_create_subscription' ) ) {
			$this->create_subscription( $order, $product, $user_id, $pricing );
		}

		return $order;
	}

	/**
	 * Creates a WC_Subscription linked to the parent order.
	 *
	 * @param \WC_Order            $order   Parent order.
	 * @param \WC_Product          $product Subscription product.
	 * @param int                  $user_id Customer user ID.
	 * @param array<string, mixed> $pricing Pricing breakdown.
	 * @return void
	 */
	private function create_subscription(
		\WC_Order $order,
		\WC_Product $product,
		int $user_id,
		array $pricing
	): void {
		$billing_period   = (string) $product->get_meta( '_subscription_period', true );
		$billing_period   = '' !== $billing_period ? $billing_period : 'month';
		$billing_interval = (int) $product->get_meta( '_subscription_period_interval', true );
		$billing_interval = $billing_interval > 0 ? $billing_interval : 1;

		$subscription = wcs_create_subscription( // @phpstan-ignore-line
			array(
				'order_id'         => $order->get_id(),
				'customer_id'      => $user_id,
				'billing_period'   => $billing_period,
				'billing_interval' => $billing_interval,
				'status'           => 'pending',
			)
		);

		if ( is_wp_error( $subscription ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Subscription creation failed: %s', 'gym-core' ),
					$subscription->get_error_message()
				)
			);
			return;
		}

		// Add the product as a subscription line item with the recurring price.
		$subscription->add_product(
			$product,
			1,
			array(
				'subtotal' => $pricing['recurring_payment'],
				'total'    => $pricing['recurring_payment'],
			)
		);

		// Set subscription total to the recurring amount.
		$subscription->set_total( (string) $pricing['recurring_payment'] );

		// Copy billing address from order.
		$subscription->set_billing_first_name( $order->get_billing_first_name() );
		$subscription->set_billing_last_name( $order->get_billing_last_name() );
		$subscription->set_billing_email( $order->get_billing_email() );
		$subscription->set_billing_phone( $order->get_billing_phone() );
		$subscription->set_billing_address_1( $order->get_billing_address_1() );
		$subscription->set_billing_city( $order->get_billing_city() );
		$subscription->set_billing_state( $order->get_billing_state() );
		$subscription->set_billing_postcode( $order->get_billing_postcode() );
		$subscription->set_billing_country( $order->get_billing_country() );

		// Add kiosk meta to subscription too.
		$subscription->update_meta_data( self::META_KIOSK_ORIGIN, '1' );
		$subscription->update_meta_data( '_gym_location', $order->get_meta( '_gym_location' ) );

		$subscription->save();
	}
}
