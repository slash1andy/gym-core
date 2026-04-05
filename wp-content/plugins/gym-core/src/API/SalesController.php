<?php
/**
 * Sales kiosk REST controller.
 *
 * Provides endpoints for the tablet-based sales kiosk: product listing,
 * dynamic pricing calculation, customer lookup, order creation, and
 * lead capture.
 *
 * @package Gym_Core\API
 * @since   4.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Location\Taxonomy;
use Gym_Core\Sales\OrderBuilder;
use Gym_Core\Sales\PricingCalculator;
use Gym_Core\Sales\ProductMetaBox;

/**
 * REST endpoints for the sales kiosk.
 *
 * Routes:
 *   GET  /gym/v1/sales/products   — List membership products (including hidden).
 *   POST /gym/v1/sales/calculate  — Dynamic pricing recalculation.
 *   GET  /gym/v1/sales/customer   — Search customers/leads.
 *   POST /gym/v1/sales/order      — Create pending order + get pay URL.
 *   POST /gym/v1/sales/lead       — Save a walk-in as a CRM lead.
 */
class SalesController extends BaseController {

	/**
	 * REST base for sales endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'sales';

	/**
	 * Pricing calculator instance.
	 *
	 * @var PricingCalculator
	 */
	private PricingCalculator $calculator;

	/**
	 * Order builder instance.
	 *
	 * @var OrderBuilder
	 */
	private OrderBuilder $order_builder;

	/**
	 * Constructor.
	 *
	 * @param PricingCalculator $calculator    Pricing calculator.
	 * @param OrderBuilder      $order_builder Order builder.
	 */
	public function __construct( PricingCalculator $calculator, OrderBuilder $order_builder ) {
		parent::__construct();
		$this->calculator    = $calculator;
		$this->order_builder = $order_builder;
	}

	/**
	 * Registers all sales REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /gym/v1/sales/products.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_products' ),
					'permission_callback' => array( $this, 'permissions_sales' ),
					'args'                => array(
						'location' => array(
							'description'       => __( 'Location slug to filter products.', 'gym-core' ),
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		// POST /gym/v1/sales/calculate.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/calculate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'calculate_pricing' ),
					'permission_callback' => array( $this, 'permissions_sales' ),
					'args'                => array(
						'product_id'   => array(
							'description'       => __( 'WooCommerce product ID.', 'gym-core' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'down_payment' => array(
							'description'       => __( 'Down payment amount.', 'gym-core' ),
							'type'              => 'number',
							'required'          => true,
							'sanitize_callback' => static function ( $value ): float {
								return round( (float) $value, 2 );
							},
						),
					),
				),
			)
		);

		// GET /gym/v1/sales/customer.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/customer',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_customer' ),
					'permission_callback' => array( $this, 'permissions_sales' ),
					'args'                => array(
						'search' => array(
							'description'       => __( 'Search query (name or email).', 'gym-core' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /gym/v1/sales/order.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/order',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_order' ),
					'permission_callback' => array( $this, 'permissions_sales' ),
					'args'                => $this->order_args(),
				),
			)
		);

		// POST /gym/v1/sales/lead.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lead',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_lead' ),
					'permission_callback' => array( $this, 'permissions_sales' ),
					'args'                => $this->lead_args(),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission callback
	// -------------------------------------------------------------------------

	/**
	 * Permission callback requiring the gym_process_sale capability.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return true|\WP_Error
	 */
	public function permissions_sales( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required.', 'gym-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'gym_process_sale' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access the sales kiosk.', 'gym-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Endpoint handlers
	// -------------------------------------------------------------------------

	/**
	 * Lists membership subscription products (including catalog-hidden).
	 *
	 * GET /gym/v1/sales/products
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_products( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$location = sanitize_key( (string) $request->get_param( 'location' ) );

		$query_args = array(
			'status'  => 'publish',
			'type'    => array( 'subscription', 'variable-subscription' ),
			'limit'   => 50,
			'orderby' => 'menu_order',
			'order'   => 'ASC',
		);

		// Include hidden products by removing the default catalog visibility filter.
		add_filter( 'woocommerce_product_is_visible', '__return_true', 999 );

		if ( $location && Taxonomy::is_valid( $location ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Taxonomy::SLUG,
					'field'    => 'slug',
					'terms'    => array( $location ),
				),
			);
		}

		$products = wc_get_products( $query_args );

		remove_filter( 'woocommerce_product_is_visible', '__return_true', 999 );

		$data = array_map( array( $this, 'format_sales_product' ), $products );

		return $this->success_response( array_values( $data ) );
	}

	/**
	 * Calculates dynamic pricing for a product and down payment.
	 *
	 * POST /gym/v1/sales/calculate
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function calculate_pricing( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id   = absint( $request->get_param( 'product_id' ) );
		$down_payment = round( (float) $request->get_param( 'down_payment' ), 2 );

		$result = $this->calculator->calculate_for_product( $product_id, $down_payment );

		if ( ! $result['is_valid'] ) {
			return $this->error_response(
				'gym_pricing_invalid',
				$result['error'],
				422
			);
		}

		return $this->success_response( $result );
	}

	/**
	 * Searches for existing customers/leads by name or email.
	 *
	 * GET /gym/v1/sales/customer
	 *
	 * Checks WordPress users first, then Jetpack CRM contacts.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search_customer( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );

		if ( strlen( $search ) < 2 ) {
			return $this->error_response(
				'gym_search_too_short',
				__( 'Search query must be at least 2 characters.', 'gym-core' ),
				422
			);
		}

		$results     = array();
		$seen_emails = array();

		// Search WordPress users.
		$wp_users = get_users(
			array(
				'search'  => '*' . $search . '*',
				'number'  => 10,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => 'all',
			)
		);

		foreach ( $wp_users as $user ) {
			$email = strtolower( $user->user_email );
			if ( isset( $seen_emails[ $email ] ) ) {
				continue;
			}
			$seen_emails[ $email ] = true;

			$billing_phone = get_user_meta( $user->ID, 'billing_phone', true );
			$billing_addr  = get_user_meta( $user->ID, 'billing_address_1', true );
			$billing_city  = get_user_meta( $user->ID, 'billing_city', true );
			$billing_state = get_user_meta( $user->ID, 'billing_state', true );
			$billing_zip   = get_user_meta( $user->ID, 'billing_postcode', true );

			$results[] = array(
				'source'     => 'wordpress',
				'id'         => $user->ID,
				'email'      => $user->user_email,
				'first_name' => $user->first_name ? $user->first_name : '',
				'last_name'  => $user->last_name ? $user->last_name : '',
				'phone'      => $billing_phone ? $billing_phone : '',
				'address_1'  => $billing_addr ? $billing_addr : '',
				'city'       => $billing_city ? $billing_city : '',
				'state'      => $billing_state ? $billing_state : '',
				'postcode'   => $billing_zip ? $billing_zip : '',
			);
		}

		// Search Jetpack CRM contacts if available.
		if ( function_exists( 'zeroBS_searchContacts' ) ) {
			$crm_results = zeroBS_searchContacts( $search, 10 ); // @phpstan-ignore-line

			if ( is_array( $crm_results ) ) {
				foreach ( $crm_results as $contact ) {
					$email = strtolower( (string) ( $contact['email'] ?? '' ) );
					if ( empty( $email ) || isset( $seen_emails[ $email ] ) ) {
						continue;
					}
					$seen_emails[ $email ] = true;

					$results[] = array(
						'source'     => 'crm',
						'id'         => (int) ( $contact['id'] ?? 0 ),
						'email'      => $contact['email'] ?? '',
						'first_name' => $contact['fname'] ?? '',
						'last_name'  => $contact['lname'] ?? '',
						'phone'      => ! empty( $contact['hometel'] ) ? $contact['hometel'] : ( $contact['worktel'] ?? '' ),
						'address_1'  => $contact['addr1'] ?? '',
						'city'       => $contact['city'] ?? '',
						'state'      => $contact['county'] ?? '',
						'postcode'   => $contact['postcode'] ?? '',
					);
				}
			}
		}

		return $this->success_response( $results );
	}

	/**
	 * Creates a pending WooCommerce order with subscription.
	 *
	 * POST /gym/v1/sales/order
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_order( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id   = absint( $request->get_param( 'product_id' ) );
		$down_payment = round( (float) $request->get_param( 'down_payment' ), 2 );
		$location     = sanitize_key( (string) $request->get_param( 'location' ) );

		$customer = array(
			'email'      => sanitize_email( (string) $request->get_param( 'email' ) ),
			'first_name' => sanitize_text_field( (string) $request->get_param( 'first_name' ) ),
			'last_name'  => sanitize_text_field( (string) $request->get_param( 'last_name' ) ),
			'phone'      => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
			'address_1'  => sanitize_text_field( (string) $request->get_param( 'address_1' ) ),
			'city'       => sanitize_text_field( (string) $request->get_param( 'city' ) ),
			'state'      => sanitize_text_field( (string) $request->get_param( 'state' ) ),
			'postcode'   => sanitize_text_field( (string) $request->get_param( 'postcode' ) ),
		);

		// Validate pricing first.
		$pricing = $this->calculator->calculate_for_product( $product_id, $down_payment );

		if ( ! $pricing['is_valid'] ) {
			return $this->error_response( 'gym_pricing_invalid', $pricing['error'], 422 );
		}

		$result = $this->order_builder->create(
			$product_id,
			$down_payment,
			$pricing,
			$customer,
			$location,
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				(int) ( $result->get_error_data()['status'] ?? 500 )
			);
		}

		return $this->success_response( $result, null, 201 );
	}

	/**
	 * Saves a walk-in as a CRM lead.
	 *
	 * POST /gym/v1/sales/lead
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_lead( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$fields = array(
			'first_name' => sanitize_text_field( (string) $request->get_param( 'first_name' ) ),
			'last_name'  => sanitize_text_field( (string) $request->get_param( 'last_name' ) ),
			'email'      => sanitize_email( (string) $request->get_param( 'email' ) ),
			'phone'      => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
			'location'   => sanitize_key( (string) $request->get_param( 'location' ) ),
			'notes'      => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
		);

		if ( empty( $fields['email'] ) && empty( $fields['phone'] ) ) {
			return $this->error_response(
				'gym_lead_missing_contact',
				__( 'At least an email or phone number is required.', 'gym-core' ),
				422
			);
		}

		// Create CRM contact if Jetpack CRM is available.
		if ( function_exists( 'zeroBS_integrations_addOrUpdateContact' ) ) {
			$tags = array( 'lead', 'source: sales-kiosk' );

			if ( ! empty( $fields['location'] ) ) {
				$tags[] = sanitize_title( $fields['location'] );
			}

			$contact_data = array(
				'email'   => $fields['email'],
				'fname'   => $fields['first_name'],
				'lname'   => $fields['last_name'],
				'hometel' => $fields['phone'],
				'tags'    => $tags,
			);

			$contact_id = zeroBS_integrations_addOrUpdateContact( // @phpstan-ignore-line
				'gym-core-sales-kiosk',
				! empty( $fields['email'] ) ? $fields['email'] : $fields['phone'],
				$contact_data
			);

			// Add notes if provided.
			if ( $contact_id && ! empty( $fields['notes'] ) && function_exists( 'zeroBS_addNote' ) ) {
				zeroBS_addNote( // @phpstan-ignore-line
					$contact_id,
					-1,
					-1,
					array(
						'type'   => 'note',
						'body'   => $fields['notes'],
						'author' => get_current_user_id(),
					)
				);
			}

			return $this->success_response(
				array(
					'saved'      => true,
					'contact_id' => $contact_id ? $contact_id : null,
				),
				null,
				201
			);
		}

		// Fallback: no CRM available. Still return success but flag it.
		return $this->success_response(
			array(
				'saved'      => false,
				'contact_id' => null,
				'message'    => __( 'Lead info noted but no CRM is available to store it.', 'gym-core' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Formatters
	// -------------------------------------------------------------------------

	/**
	 * Formats a WC_Product for the sales kiosk product listing.
	 *
	 * Includes pricing meta and subscription details.
	 *
	 * @param \WC_Product $product The product to format.
	 * @return array<string, mixed>
	 */
	private function format_sales_product( \WC_Product $product ): array {
		$id       = $product->get_id();
		$image_id = $product->get_image_id();

		// Read subscription meta.
		$signup_fee         = (float) $product->get_meta( '_subscription_sign_up_fee', true );
		$subscription_price = (float) $product->get_meta( '_subscription_price', true );
		$billing_period     = (string) $product->get_meta( '_subscription_period', true );
		$billing_interval   = (int) $product->get_meta( '_subscription_period_interval', true );

		// Read sales kiosk pricing meta.
		$base_total   = (float) $product->get_meta( ProductMetaBox::META_BASE_TOTAL, true );
		$min_down     = (float) $product->get_meta( ProductMetaBox::META_MIN_DOWN, true );
		$max_down     = (float) $product->get_meta( ProductMetaBox::META_MAX_DOWN, true );
		$max_discount = (float) $product->get_meta( ProductMetaBox::META_MAX_DISCOUNT, true );

		// Get location terms.
		$location_terms = wp_get_object_terms( $id, Taxonomy::SLUG, array( 'fields' => 'slugs' ) );
		$locations      = is_wp_error( $location_terms ) ? array() : $location_terms;

		// Get product categories for grouping.
		$cat_terms  = wp_get_object_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
		$categories = is_wp_error( $cat_terms ) ? array() : $cat_terms;

		return array(
			'id'                 => $id,
			'name'               => $product->get_name(),
			'slug'               => $product->get_slug(),
			'description'        => $product->get_short_description(),
			'image'              => $image_id ? esc_url_raw( (string) wp_get_attachment_url( (int) $image_id ) ) : '',
			'categories'         => $categories,
			'locations'          => $locations,
			'subscription_price' => $subscription_price,
			'signup_fee'         => $signup_fee,
			'billing_period'     => '' !== $billing_period ? $billing_period : 'month',
			'billing_interval'   => $billing_interval > 0 ? $billing_interval : 1,
			'pricing'            => array(
				'base_total'   => $base_total,
				'min_down'     => $min_down,
				'max_down'     => $max_down,
				'max_discount' => $max_discount,
				'configured'   => $base_total > 0.0,
			),
		);
	}

	// -------------------------------------------------------------------------
	// Route arg definitions
	// -------------------------------------------------------------------------

	/**
	 * Returns the argument definitions for the order creation endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function order_args(): array {
		return array(
			'product_id'   => array(
				'description'       => __( 'WooCommerce product ID.', 'gym-core' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'down_payment' => array(
				'description'       => __( 'Down payment amount.', 'gym-core' ),
				'type'              => 'number',
				'required'          => true,
				'sanitize_callback' => 'floatval',
			),
			'email'        => array(
				'description'       => __( 'Customer email address.', 'gym-core' ),
				'type'              => 'string',
				'required'          => true,
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
			),
			'first_name'   => array(
				'description'       => __( 'Customer first name.', 'gym-core' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'    => array(
				'description'       => __( 'Customer last name.', 'gym-core' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'phone'        => array(
				'description'       => __( 'Customer phone number.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'address_1'    => array(
				'description'       => __( 'Billing street address.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'city'         => array(
				'description'       => __( 'Billing city.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'state'        => array(
				'description'       => __( 'Billing state.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'postcode'     => array(
				'description'       => __( 'Billing postal code.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'location'     => array(
				'description'       => __( 'Gym location slug.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Returns the argument definitions for the lead save endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function lead_args(): array {
		return array(
			'first_name' => array(
				'description'       => __( 'Lead first name.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'  => array(
				'description'       => __( 'Lead last name.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email'      => array(
				'description'       => __( 'Lead email address.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_email',
			),
			'phone'      => array(
				'description'       => __( 'Lead phone number.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'location'   => array(
				'description'       => __( 'Gym location slug.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
			'notes'      => array(
				'description'       => __( 'Staff notes about the lead.', 'gym-core' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}
}
