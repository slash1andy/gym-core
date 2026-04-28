<?php
/**
 * Sales kiosk pricing meta fields on subscription products.
 *
 * Adds custom fields to the WooCommerce product edit screen for
 * configuring the sliding-discount pricing model used by the
 * tablet sales kiosk.
 *
 * @package Gym_Core\Sales
 * @since   4.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Sales;

/**
 * Registers and saves sales kiosk pricing meta on subscription products.
 *
 * Meta fields:
 *   _gym_base_total        — Total contract value at minimum down payment.
 *   _gym_min_down_payment  — Lowest allowed down payment.
 *   _gym_max_down_payment  — Highest allowed down payment.
 *   _gym_max_discount      — Maximum discount earned at max down payment.
 */
final class ProductMetaBox {

	/**
	 * Meta key for the base total contract value.
	 */
	public const META_BASE_TOTAL = '_gym_base_total';

	/**
	 * Meta key for the minimum down payment.
	 */
	public const META_MIN_DOWN = '_gym_min_down_payment';

	/**
	 * Meta key for the maximum down payment.
	 */
	public const META_MAX_DOWN = '_gym_max_down_payment';

	/**
	 * Meta key for the maximum discount.
	 */
	public const META_MAX_DISCOUNT = '_gym_max_discount';

	/**
	 * All meta keys managed by this class.
	 *
	 * @var string[]
	 */
	public const ALL_META_KEYS = array(
		self::META_BASE_TOTAL,
		self::META_MIN_DOWN,
		self::META_MAX_DOWN,
		self::META_MAX_DISCOUNT,
	);

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
	}

	/**
	 * Renders the sales kiosk pricing fields on the product General tab.
	 *
	 * Only displayed for subscription product types.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		// Show fields for subscription and variable-subscription types.
		$subscription_types = array( 'subscription', 'variable-subscription' );
		if ( ! in_array( $product->get_type(), $subscription_types, true ) ) {
			echo '<div class="options_group gym-sales-pricing show_if_subscription show_if_variable-subscription" style="display:none;">';
		} else {
			echo '<div class="options_group gym-sales-pricing">';
		}

		echo '<p class="form-field"><strong>' . esc_html__( 'Sales Kiosk Pricing', 'gym-core' ) . '</strong></p>';

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_BASE_TOTAL,
				'label'             => __( 'Base contract total ($)', 'gym-core' ),
				'description'       => __( 'Total contract value at minimum down payment (e.g., 2455.00).', 'gym-core' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_MIN_DOWN,
				'label'             => __( 'Min down payment ($)', 'gym-core' ),
				'description'       => __( 'Lowest allowed down payment amount.', 'gym-core' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_MAX_DOWN,
				'label'             => __( 'Max down payment ($)', 'gym-core' ),
				'description'       => __( 'Highest allowed down payment amount.', 'gym-core' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_MAX_DISCOUNT,
				'label'             => __( 'Max discount ($)', 'gym-core' ),
				'description'       => __( 'Maximum discount applied when customer pays the max down payment.', 'gym-core' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		echo '</div>';

		// Show/hide based on product type via inline JS.
		// wc-admin-meta-boxes is enqueued on the WC product edit screen.
		wp_add_inline_script(
			'wc-admin-meta-boxes',
			"jQuery(function($) {
				function toggleSalesPricing() {
					var type = $('select#product-type').val();
					if (type === 'subscription' || type === 'variable-subscription') {
						$('.gym-sales-pricing').show();
					} else {
						$('.gym-sales-pricing').hide();
					}
				}
				$('select#product-type').on('change', toggleSalesPricing);
				toggleSalesPricing();
			});"
		);
	}

	/**
	 * Saves the sales kiosk pricing meta when a product is saved.
	 *
	 * @param int $post_id The product (post) ID being saved.
	 * @return void
	 */
	public function save_fields( int $post_id ): void {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		foreach ( self::ALL_META_KEYS as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
			if ( isset( $_POST[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, wc_format_decimal( $value ) );
			}
		}

		$product->save();
	}
}
