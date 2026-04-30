<?php
/**
 * Sales kiosk endpoint.
 *
 * Registers the /sales/ URL with a standalone full-screen template
 * designed for tablet use by sales staff. The template loads kiosk-specific
 * CSS and JS that handle product selection, dynamic pricing, customer
 * lookup, and checkout handoff via the gym/v1/sales REST endpoints.
 *
 * @package Gym_Core\Sales
 * @since   4.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Sales;

use Gym_Core\Location\Taxonomy;

/**
 * Registers the sales kiosk page and its assets.
 */
final class KioskEndpoint {

	/**
	 * Rewrite endpoint slug.
	 *
	 * @var string
	 */
	public const SLUG = 'sales';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'render_kiosk' ) );
		add_filter( 'body_class', array( $this, 'add_checkout_body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_styles' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'redirect_kiosk_thankyou' ) );
		add_filter( 'user_has_cap', array( $this, 'allow_staff_pay_for_kiosk_orders' ), 99, 3 );
		add_action( 'template_redirect', array( $this, 'render_kiosk_pay_page' ), 1 );
	}

	/**
	 * Adds rewrite rule for /sales/.
	 *
	 * @return void
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^' . self::SLUG . '/?$',
			'index.php?gym_sales_kiosk=1',
			'top'
		);
	}

	/**
	 * Registers the query var.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = 'gym_sales_kiosk';
		return $vars;
	}

	/**
	 * Renders the sales kiosk template when the query var is set.
	 *
	 * @return void
	 */
	public function render_kiosk(): void {
		if ( ! get_query_var( 'gym_sales_kiosk' ) ) {
			return;
		}

		// Require login with sales capability.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/' . self::SLUG . '/' ) ) );
			exit;
		}

		if ( ! current_user_can( 'gym_process_sale' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		$location     = $this->get_kiosk_location();
		$checkout_url = wc_get_checkout_url();

		// Enqueue assets.
		wp_enqueue_style(
			'gym-sales-kiosk',
			GYM_CORE_URL . 'assets/css/sales-kiosk.css',
			array(),
			GYM_CORE_VERSION
		);

		wp_enqueue_script(
			'gym-sales-kiosk',
			GYM_CORE_URL . 'assets/js/sales-kiosk.js',
			array(),
			GYM_CORE_VERSION,
			true
		);

		wp_localize_script(
			'gym-sales-kiosk',
			'gymSalesKiosk',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'gym/v1/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'location'    => $location,
				'checkoutUrl' => esc_url_raw( $checkout_url ),
				'kioskUrl'    => esc_url_raw( home_url( '/' . self::SLUG . '/' ) ),
				'timeout'     => (int) get_option( 'gym_core_kiosk_timeout', 10 ),
				'strings'     => array(
					'title'              => \Gym_Core\Utilities\Brand::name(),
					'subtitle'           => __( 'Membership Sales', 'gym-core' ),
					'selectMembership'   => __( 'Select a Membership', 'gym-core' ),
					'downPayment'        => __( 'Down Payment', 'gym-core' ),
					'monthly'            => __( 'Monthly', 'gym-core' ),
					'biweekly'           => __( 'Every 2 Weeks', 'gym-core' ),
					'total'              => __( 'Total Contract', 'gym-core' ),
					'savings'            => __( 'Savings', 'gym-core' ),
					'customerInfo'       => __( 'Customer Information', 'gym-core' ),
					'searchCustomer'     => __( 'Search by name or email...', 'gym-core' ),
					'newCustomer'        => __( 'New Customer', 'gym-core' ),
					'reviewSale'         => __( 'Review Sale', 'gym-core' ),
					'processPayment'     => __( 'Process Payment', 'gym-core' ),
					'saveAsLead'         => __( 'Save as Lead', 'gym-core' ),
					'back'               => __( 'Back', 'gym-core' ),
					'continue'           => __( 'Continue', 'gym-core' ),
					'success'            => __( 'Sale Complete!', 'gym-core' ),
					'leadSaved'          => __( 'Lead Saved', 'gym-core' ),
					'error'              => __( 'Something went wrong', 'gym-core' ),
					'tryAgain'           => __( 'Tap to try again', 'gym-core' ),
					'noProducts'         => __( 'No membership products found.', 'gym-core' ),
					'noResults'          => __( 'No matching customers found.', 'gym-core' ),
					'loading'            => __( 'Loading...', 'gym-core' ),
					'firstName'          => __( 'First Name', 'gym-core' ),
					'lastName'           => __( 'Last Name', 'gym-core' ),
					'email'              => __( 'Email', 'gym-core' ),
					'phone'              => __( 'Phone', 'gym-core' ),
					'address'            => __( 'Street Address', 'gym-core' ),
					'city'               => __( 'City', 'gym-core' ),
					'state'              => __( 'State', 'gym-core' ),
					'zip'                => __( 'ZIP Code', 'gym-core' ),
					'notes'              => __( 'Notes', 'gym-core' ),
					'perMonth'           => __( '/mo', 'gym-core' ),
					'perTwoWeeks'        => __( '/2wk', 'gym-core' ),
					'enrolledIn'         => __( 'is now enrolled in', 'gym-core' ),
					'redirectingPayment' => __( 'Redirecting to payment...', 'gym-core' ),
				),
			)
		);

		// Output standalone HTML.
		$this->render_template( $location );
		exit;
	}

	/**
	 * Adds body class on WooCommerce checkout/pay pages when accessed from kiosk.
	 *
	 * @param array<int, string> $classes Existing body classes.
	 * @return array<int, string>
	 */
	public function add_checkout_body_class( array $classes ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['gym_sales_kiosk'] ) ) {
			$classes[] = 'sales-kiosk-checkout';
		}

		return $classes;
	}

	/**
	 * Enqueues the checkout page stylesheet when accessed from the kiosk.
	 *
	 * @return void
	 */
	public function enqueue_checkout_styles(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['gym_sales_kiosk'] ) ) {
			wp_enqueue_style(
				'gym-sales-kiosk-checkout',
				GYM_CORE_URL . 'assets/css/sales-kiosk-checkout.css',
				array(),
				GYM_CORE_VERSION
			);

			// Dequeue block checkout scripts at wp_footer (they enqueue late).
			add_action(
				'wp_print_footer_scripts',
				static function (): void {
					wp_dequeue_script( 'wc-checkout-block-frontend' );
					wp_dequeue_script( 'wc-checkout-block' );
					wp_dequeue_script( 'wc-blocks-checkout' );
					wp_dequeue_script( 'wc-cart-block-frontend' );
					wp_dequeue_script( 'wc-cart-block' );
					wp_dequeue_script( 'wc-blocks-cart' );
				},
				1
			);
		}
	}

	/**
	 * Redirects back to the sales kiosk after successful payment.
	 *
	 * Only fires for orders that originated from the sales kiosk.
	 *
	 * @param int $order_id The completed order ID.
	 * @return void
	 */
	public function redirect_kiosk_thankyou( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( '1' !== $order->get_meta( OrderBuilder::META_KIOSK_ORIGIN ) ) {
			return;
		}

		$redirect_url = add_query_arg(
			'completed',
			$order_id,
			home_url( '/' . self::SLUG . '/' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Renders a standalone pay page for kiosk orders.
	 *
	 * Intercepts the checkout order-pay page before WooCommerce Blocks can
	 * redirect, and renders the classic payment form in a minimal kiosk-styled
	 * template. This bypasses Block Checkout's Store API ownership checks.
	 *
	 * @return void
	 */
	public function render_kiosk_pay_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['gym_sales_kiosk'] ) || empty( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		global $wp;
		$order_id = isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_order_key() !== $order_key ) {
			return;
		}

		if ( '1' !== $order->get_meta( OrderBuilder::META_KIOSK_ORIGIN ) ) {
			return;
		}

		if ( ! current_user_can( 'pay_for_order', $order_id ) ) {
			return;
		}

		// Enqueue WooCommerce scripts needed for the pay form.
		wp_enqueue_script( 'wc-checkout' );

		// Render standalone pay page.
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: brand name */
				__( 'Payment — %s', 'gym-core' ),
				\Gym_Core\Utilities\Brand::name()
			)
		);
		?>
	</title>
		<?php wp_head(); ?>
	<style>
		body { margin: 0; padding: 0; background: #F5F5F7; font-family: "Inter", "Helvetica Neue", Arial, sans-serif; }
		body #wpadminbar { display: none !important; }
		html { margin-top: 0 !important; }
		.kiosk-pay-wrapper { max-width: 600px; margin: 0 auto; padding: 32px 24px; }
		.kiosk-pay-header { background: #1A1A1A; text-align: center; padding: 28px 24px; border-radius: 0 0 16px 16px; margin: 0 -24px 32px; }
		.kiosk-pay-header h1 { font-family: "Barlow Condensed", "Arial Narrow", sans-serif; color: #fff; font-size: 1.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; margin: 0 0 4px; }
		.kiosk-pay-header p { color: #75787B; font-size: 0.875rem; margin: 0; }
		.kiosk-pay-summary { background: #fff; border: 1px solid #E5E5E7; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
		.kiosk-pay-summary h2 { font-family: "Barlow Condensed", "Arial Narrow", sans-serif; font-size: 0.75rem; color: #0032A0; text-transform: uppercase; letter-spacing: 0.12em; margin: 0 0 12px; }
		.kiosk-pay-summary .total { font-size: 1.5rem; font-weight: 700; }
		.kiosk-pay-form { background: #fff; border: 1px solid #E5E5E7; border-radius: 8px; padding: 24px; }
		.kiosk-pay-form .button, .kiosk-pay-form #place_order { background: #0032A0 !important; color: #fff !important; border: none !important; border-radius: 4px !important; padding: 16px 32px !important; font-family: "Barlow Condensed", "Arial Narrow", sans-serif; font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; width: 100%; min-height: 44px; }
		.kiosk-pay-form .button:hover, .kiosk-pay-form #place_order:hover { background: #0041CC !important; }
		.kiosk-pay-back { display: block; text-align: center; margin-top: 16px; color: #75787B; text-decoration: none; font-size: 0.875rem; }
		.kiosk-pay-back:hover { color: #0032A0; }
	</style>
</head>
<body class="gym-sales-kiosk-pay">
	<div class="kiosk-pay-wrapper">
		<div class="kiosk-pay-header">
			<h1><?php echo esc_html( \Gym_Core\Utilities\Brand::name() ); ?></h1>
			<p><?php esc_html_e( 'Complete Payment', 'gym-core' ); ?></p>
		</div>

		<div class="kiosk-pay-form">
			<?php
			// Render the classic WooCommerce order-pay form directly.
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			wc_get_template(
				'checkout/form-pay.php',
				array(
					'order'              => $order,
					'available_gateways' => $available_gateways,
					'order_button_text'  => __( 'Pay Now', 'gym-core' ),
				)
			);
			?>
		</div>

		<a href="<?php echo esc_url( home_url( '/' . self::SLUG . '/' ) ); ?>" class="kiosk-pay-back">
			<?php esc_html_e( 'Cancel and return to sales', 'gym-core' ); ?>
		</a>
	</div>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Allows staff with gym_process_sale capability to pay for kiosk orders.
	 *
	 * WooCommerce's order-pay page checks current_user_can('pay_for_order', $order_id),
	 * which normally requires the logged-in user to be the order owner. Since kiosk
	 * orders are created for the customer but paid by staff, we grant this capability
	 * to users with gym_process_sale when the order originated from the kiosk.
	 *
	 * @param array<string, bool> $allcaps All capabilities of the user.
	 * @param array<int, string>  $caps    Required capabilities.
	 * @param array<int, mixed>   $args    Arguments: [0] = requested cap, [1] = user ID, [2] = order ID.
	 * @return array<string, bool>
	 */
	public function allow_staff_pay_for_kiosk_orders( array $allcaps, array $caps, array $args ): array {
		if ( ! isset( $args[0] ) || 'pay_for_order' !== $args[0] ) {
			return $allcaps;
		}

		if ( empty( $allcaps['gym_process_sale'] ) && empty( $allcaps['manage_woocommerce'] ) ) {
			return $allcaps;
		}

		if ( ! isset( $args[2] ) ) {
			return $allcaps;
		}

		$order = wc_get_order( $args[2] );
		if ( ! $order ) {
			return $allcaps;
		}

		if ( '1' === $order->get_meta( OrderBuilder::META_KIOSK_ORIGIN ) ) {
			$allcaps['pay_for_order'] = true;
		}

		return $allcaps;
	}

	/**
	 * Determines the kiosk location from user meta or cookie.
	 *
	 * @return string Location slug (defaults to 'rockford').
	 */
	private function get_kiosk_location(): string {
		$user_location = get_user_meta( get_current_user_id(), 'gym_location', true );
		if ( $user_location ) {
			return $user_location;
		}

		$cookie_location = isset( $_COOKIE['gym_location'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['gym_location'] ) ) : '';
		if ( '' !== $cookie_location && ! Taxonomy::is_valid( $cookie_location ) ) {
			$cookie_location = '';
		}
		if ( '' === $cookie_location ) {
			$locations       = Taxonomy::get_location_labels();
			$cookie_location = ! empty( $locations ) ? array_key_first( $locations ) : '';
		}
		return $cookie_location;
	}

	/**
	 * Renders the sales kiosk HTML template.
	 *
	 * @param string $location Location slug.
	 * @return void
	 */
	private function render_template( string $location ): void {
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
	<title>
		<?php
		/* translators: %s: brand name */
		echo esc_html( sprintf( __( 'Sales — %s', 'gym-core' ), \Gym_Core\Utilities\Brand::name() ) );
		?>
	</title>
		<?php wp_head(); ?>
</head>
<body class="gym-sales-kiosk" data-location="<?php echo esc_attr( $location ); ?>">

	<div id="gym-sales-app">
		<!-- Screen: Product Selection -->
		<div class="sales-screen sales-screen--products active" id="sales-products">
			<div class="sales-header">
				<h1 class="sales-title"><?php echo esc_html( \Gym_Core\Utilities\Brand::name() ); ?></h1>
				<p class="sales-subtitle"><?php echo esc_html( ucfirst( $location ) ); ?> &mdash; <?php esc_html_e( 'Membership Sales', 'gym-core' ); ?></p>
			</div>
			<div id="sales-product-grid" class="sales-product-grid"></div>
		</div>

		<!-- Screen: Pricing -->
		<div class="sales-screen sales-screen--pricing" id="sales-pricing">
			<div class="sales-header">
				<h2 class="sales-title" id="sales-product-name"></h2>
				<p class="sales-subtitle"><?php esc_html_e( 'Customize Pricing', 'gym-core' ); ?></p>
			</div>
			<div class="sales-pricing-card">
				<div class="sales-slider-group">
					<label for="sales-down-slider" class="sales-label"><?php esc_html_e( 'Down Payment', 'gym-core' ); ?></label>
					<div class="sales-slider-value" id="sales-down-display">$0</div>
					<input type="range" id="sales-down-slider" class="sales-slider" min="0" max="1000" step="1" value="0">
				</div>
				<div class="sales-pricing-breakdown">
					<div class="sales-pricing-row">
						<span class="sales-pricing-label"><?php esc_html_e( 'Down Payment', 'gym-core' ); ?></span>
						<span class="sales-pricing-value" id="sales-price-down">$0.00</span>
					</div>
					<div class="sales-pricing-row">
						<span class="sales-pricing-label"><?php esc_html_e( 'Recurring', 'gym-core' ); ?></span>
						<span class="sales-pricing-value" id="sales-price-recurring">$0.00</span>
					</div>
					<div class="sales-pricing-row sales-pricing-row--total">
						<span class="sales-pricing-label"><?php esc_html_e( 'Total Contract', 'gym-core' ); ?></span>
						<span class="sales-pricing-value" id="sales-price-total">$0.00</span>
					</div>
					<div class="sales-pricing-row sales-pricing-row--savings" id="sales-savings-row" style="display:none;">
						<span class="sales-pricing-label"><?php esc_html_e( 'You Save', 'gym-core' ); ?></span>
						<span class="sales-pricing-value sales-pricing-value--savings" id="sales-price-savings">$0.00</span>
					</div>
				</div>
			</div>
			<div class="sales-actions">
				<button type="button" class="sales-btn sales-btn--back" id="pricing-back"><?php esc_html_e( 'Back', 'gym-core' ); ?></button>
				<button type="button" class="sales-btn sales-btn--primary" id="pricing-continue"><?php esc_html_e( 'Continue', 'gym-core' ); ?></button>
			</div>
		</div>

		<!-- Screen: Customer Info -->
		<div class="sales-screen sales-screen--customer" id="sales-customer">
			<div class="sales-header">
				<h2 class="sales-title"><?php esc_html_e( 'Customer Information', 'gym-core' ); ?></h2>
				<p class="sales-subtitle"><?php esc_html_e( 'Search for existing customer or enter new details', 'gym-core' ); ?></p>
			</div>
			<div class="sales-customer-search">
				<label for="sales-customer-search-input" class="screen-reader-text"><?php esc_html_e( 'Search customers', 'gym-core' ); ?></label>
				<input
					type="text"
					id="sales-customer-search-input"
					class="sales-input"
					placeholder="<?php esc_attr_e( 'Search by name or email...', 'gym-core' ); ?>"
					autocomplete="off"
				>
				<div id="sales-customer-results" class="sales-customer-results" role="listbox" aria-label="<?php esc_attr_e( 'Customer search results', 'gym-core' ); ?>"></div>
			</div>
			<form id="sales-customer-form" class="sales-form">
				<div class="sales-form-row">
					<div class="sales-form-field">
						<label for="sales-first-name"><?php esc_html_e( 'First Name', 'gym-core' ); ?></label>
						<input type="text" id="sales-first-name" name="first_name" class="sales-input" required>
					</div>
					<div class="sales-form-field">
						<label for="sales-last-name"><?php esc_html_e( 'Last Name', 'gym-core' ); ?></label>
						<input type="text" id="sales-last-name" name="last_name" class="sales-input" required>
					</div>
				</div>
				<div class="sales-form-row">
					<div class="sales-form-field">
						<label for="sales-email"><?php esc_html_e( 'Email', 'gym-core' ); ?></label>
						<input type="email" id="sales-email" name="email" class="sales-input" required>
					</div>
					<div class="sales-form-field">
						<label for="sales-phone"><?php esc_html_e( 'Phone', 'gym-core' ); ?></label>
						<input type="tel" id="sales-phone" name="phone" class="sales-input">
					</div>
				</div>
				<div class="sales-form-row">
					<div class="sales-form-field sales-form-field--wide">
						<label for="sales-address"><?php esc_html_e( 'Street Address', 'gym-core' ); ?></label>
						<input type="text" id="sales-address" name="address_1" class="sales-input">
					</div>
				</div>
				<div class="sales-form-row">
					<div class="sales-form-field">
						<label for="sales-city"><?php esc_html_e( 'City', 'gym-core' ); ?></label>
						<input type="text" id="sales-city" name="city" class="sales-input">
					</div>
					<div class="sales-form-field sales-form-field--small">
						<label for="sales-state"><?php esc_html_e( 'State', 'gym-core' ); ?></label>
						<input type="text" id="sales-state" name="state" class="sales-input" maxlength="2" placeholder="IL">
					</div>
					<div class="sales-form-field sales-form-field--small">
						<label for="sales-zip"><?php esc_html_e( 'ZIP', 'gym-core' ); ?></label>
						<input type="text" id="sales-zip" name="postcode" class="sales-input" maxlength="10">
					</div>
				</div>
			</form>
			<div class="sales-actions">
				<button type="button" class="sales-btn sales-btn--back" id="customer-back"><?php esc_html_e( 'Back', 'gym-core' ); ?></button>
				<button type="button" class="sales-btn sales-btn--secondary" id="customer-save-lead"><?php esc_html_e( 'Save as Lead', 'gym-core' ); ?></button>
				<button type="button" class="sales-btn sales-btn--primary" id="customer-continue"><?php esc_html_e( 'Review Sale', 'gym-core' ); ?></button>
			</div>
		</div>

		<!-- Screen: Review -->
		<div class="sales-screen sales-screen--review" id="sales-review">
			<div class="sales-header">
				<h2 class="sales-title"><?php esc_html_e( 'Review Sale', 'gym-core' ); ?></h2>
			</div>
			<div class="sales-review-card">
				<div class="sales-review-section">
					<h3><?php esc_html_e( 'Membership', 'gym-core' ); ?></h3>
					<p id="review-product-name"></p>
				</div>
				<div class="sales-review-section">
					<h3><?php esc_html_e( 'Pricing', 'gym-core' ); ?></h3>
					<div id="review-pricing"></div>
				</div>
				<div class="sales-review-section">
					<h3><?php esc_html_e( 'Customer', 'gym-core' ); ?></h3>
					<div id="review-customer"></div>
				</div>
			</div>
			<div class="sales-actions">
				<button type="button" class="sales-btn sales-btn--back" id="review-back"><?php esc_html_e( 'Back', 'gym-core' ); ?></button>
				<button type="button" class="sales-btn sales-btn--primary sales-btn--large" id="review-process"><?php esc_html_e( 'Process Payment', 'gym-core' ); ?></button>
			</div>
		</div>

		<!-- Screen: Success -->
		<div class="sales-screen sales-screen--success" id="sales-success">
			<div class="sales-success-icon" aria-hidden="true">&#10003;</div>
			<h2 class="sales-title" id="sales-success-title"><?php esc_html_e( 'Sale Complete!', 'gym-core' ); ?></h2>
			<p class="sales-success-msg" id="sales-success-msg"></p>
		</div>

		<!-- Screen: Lead Saved -->
		<div class="sales-screen sales-screen--lead-saved" id="sales-lead-saved">
			<div class="sales-success-icon sales-success-icon--lead" aria-hidden="true">&#128221;</div>
			<h2 class="sales-title"><?php esc_html_e( 'Lead Saved', 'gym-core' ); ?></h2>
			<p class="sales-success-msg" id="sales-lead-msg"></p>
		</div>

		<!-- Screen: Error -->
		<div class="sales-screen sales-screen--error" id="sales-error">
			<div class="sales-error-icon" aria-hidden="true">&#10007;</div>
			<h2 class="sales-title"><?php esc_html_e( 'Something went wrong', 'gym-core' ); ?></h2>
			<p class="sales-error-msg" id="sales-error-msg"></p>
			<button type="button" class="sales-btn sales-btn--primary" id="sales-retry"><?php esc_html_e( 'Tap to try again', 'gym-core' ); ?></button>
		</div>

		<!-- Loading overlay -->
		<div class="sales-loading" id="sales-loading">
			<div class="sales-spinner" aria-label="<?php esc_attr_e( 'Loading', 'gym-core' ); ?>"></div>
		</div>
	</div>

		<?php wp_footer(); ?>
</body>
</html>
		<?php
	}
}
