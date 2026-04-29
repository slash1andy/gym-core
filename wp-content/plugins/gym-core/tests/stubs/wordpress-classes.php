<?php
/**
 * Minimal stubs for WordPress and WooCommerce classes used by the API module.
 *
 * These stubs allow the API controllers to be autoloaded and instantiated in
 * PHPUnit unit tests without a full WordPress or WooCommerce installation.
 * They define only the surface area actually referenced by the plugin's code.
 *
 * Brain\Monkey is responsible for stubbing WordPress *functions*; these stubs
 * cover WordPress *classes* that are extended or referenced via instanceof.
 *
 * @package Gym_Core\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

// -------------------------------------------------------------------------
// WordPress REST API classes
// -------------------------------------------------------------------------

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	/**
	 * Minimal stub matching the WP_REST_Controller contract.
	 *
	 * WordPress's real implementation lives in wp-includes/rest-api/endpoints/
	 * class-wp-rest-controller.php. We only stub what the plugin code uses.
	 */
	abstract class WP_REST_Controller { // phpcs:ignore
		/**
		 * REST namespace (e.g. 'gym/v1').
		 *
		 * @var string
		 */
		protected $namespace = ''; // phpcs:ignore

		/**
		 * REST base segment (e.g. 'locations').
		 *
		 * @var string
		 */
		protected $rest_base = ''; // phpcs:ignore

		/**
		 * Registers all routes for this controller.
		 *
		 * @return void
		 */
		abstract public function register_routes(): void;
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Stub providing the HTTP method string constants used in register_rest_route().
	 */
	class WP_REST_Server { // phpcs:ignore
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'PUT, PATCH';
		const DELETABLE = 'DELETE';
		const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Stub for WP_REST_Request — used as a type hint in controller callbacks.
	 *
	 * In unit tests, Mockery creates mock instances; this stub just ensures the
	 * class can be referenced without a WordPress install.
	 */
	class WP_REST_Request { // phpcs:ignore
		/**
		 * Returns a named request parameter.
		 *
		 * @param string $key Parameter name.
		 * @return mixed
		 */
		public function get_param( string $key ): mixed {
			return null;
		}

		/**
		 * Returns all query parameters.
		 *
		 * @return array<string, mixed>
		 */
		public function get_query_params(): array {
			return array();
		}

		/**
		 * Returns all JSON body parameters.
		 *
		 * @return array<string, mixed>
		 */
		public function get_json_params(): array {
			return array();
		}

		/**
		 * Returns a request header value.
		 *
		 * @param string $header Header name.
		 * @return string|null
		 */
		public function get_header( string $header ): ?string {
			return null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Stub for WP_REST_Response — returned by controller callback methods.
	 */
	class WP_REST_Response { // phpcs:ignore
		/**
		 * Response body.
		 *
		 * @var mixed
		 */
		protected mixed $data;

		/**
		 * HTTP status code.
		 *
		 * @var int
		 */
		protected int $status;

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Response payload.
		 * @param int   $status HTTP status code.
		 */
		public function __construct( mixed $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Returns the response payload.
		 *
		 * @return mixed
		 */
		public function get_data(): mixed {
			return $this->data;
		}

		/**
		 * Returns the HTTP status code.
		 *
		 * @return int
		 */
		public function get_status(): int {
			return $this->status;
		}

		/**
		 * Sets response headers (no-op in the stub).
		 *
		 * @param array<string, string> $headers Response headers.
		 * @return void
		 */
		public function set_headers( array $headers ): void {}
	}
}

// -------------------------------------------------------------------------
// WordPress query classes
// -------------------------------------------------------------------------

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Stub for WP_Query — used by controllers that instantiate queries directly.
	 *
	 * Tests set WP_Query::$__test_result before calling the method under test.
	 * The constructor copies those values into the instance properties.
	 */
	class WP_Query { // phpcs:ignore
		/** @var \WP_Post[] */
		public array $posts = array();
		/** @var int */
		public int $found_posts = 0;
		/** @var int */
		public int $max_num_pages = 0;

		/**
		 * Preset result for the next WP_Query instantiation.
		 *
		 * Tests should set this static property with an associative array:
		 *   [ 'posts' => [...], 'found_posts' => N, 'max_num_pages' => N ]
		 *
		 * @var array{posts: array, found_posts: int, max_num_pages: int}|null
		 */
		public static ?array $__test_result = null;

		/**
		 * Constructor — reads from the static test result preset.
		 *
		 * @param array<string, mixed> $args Query arguments (ignored in stub).
		 */
		public function __construct( array $args = array() ) {
			if ( null !== self::$__test_result ) {
				$this->posts         = self::$__test_result['posts'] ?? array();
				$this->found_posts   = self::$__test_result['found_posts'] ?? 0;
				$this->max_num_pages = self::$__test_result['max_num_pages'] ?? 0;
			}
		}

		/**
		 * Resets the test preset. Call in tearDown().
		 *
		 * @return void
		 */
		public static function __test_reset(): void {
			self::$__test_result = null;
		}
	}
}

// -------------------------------------------------------------------------
// WordPress post classes
// -------------------------------------------------------------------------

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Stub for WP_Post — represents a post object returned by get_post() and
	 * WP_Query. Exposes the public properties the controllers read.
	 */
	class WP_Post { // phpcs:ignore
		/** @var int */
		public int $ID = 0;
		/** @var string */
		public string $post_title = '';
		/** @var string */
		public string $post_content = '';
		/** @var string */
		public string $post_type = 'post';
		/** @var string */
		public string $post_status = 'publish';
		/** @var string */
		public string $post_name = '';
		/** @var int */
		public int $post_author = 0;
		/** @var string */
		public string $post_date = '0000-00-00 00:00:00';

		/**
		 * Constructor — populates public properties from an object or array.
		 *
		 * @param object|null $post Source data.
		 */
		public function __construct( ?object $post = null ) {
			if ( null !== $post ) {
				foreach ( (array) $post as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->$key = $value;
					}
				}
			}
		}
	}
}

// WP_Error is defined in tests/stubs/WP_Error.php (loaded first by bootstrap.php).
// Do not redefine it here to avoid fragile load-order dependencies.

// -------------------------------------------------------------------------
// WordPress taxonomy / term classes
// -------------------------------------------------------------------------

if ( ! class_exists( 'WP_Term' ) ) {
	/**
	 * Stub for WP_Term — represents a taxonomy term returned by get_term_by()
	 * and get_terms(). Exposes the public properties the controller reads.
	 */
	class WP_Term { // phpcs:ignore
		/** @var int */
		public int $term_id = 0;
		/** @var string */
		public string $name = '';
		/** @var string */
		public string $slug = '';
		/** @var string */
		public string $taxonomy = '';
		/** @var string */
		public string $description = '';
		/** @var int */
		public int $parent = 0;
		/** @var int */
		public int $count = 0;

		/**
		 * Constructor — populates public properties from an object or array.
		 *
		 * @param object|null $term Source data.
		 */
		public function __construct( ?object $term = null ) {
			if ( null !== $term ) {
				foreach ( (array) $term as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->$key = $value;
					}
				}
			}
		}
	}
}

// -------------------------------------------------------------------------
// WooCommerce product classes
// -------------------------------------------------------------------------

if ( ! class_exists( 'WC_Subscription' ) ) {
	/**
	 * Stub for WC_Subscription — used by OrderController for site-wide
	 * subscription totals and MRR computation, and by MemberController for
	 * the member dashboard billing section.
	 *
	 * Tests use Mockery to override the getter return values.
	 */
	class WC_Subscription { // phpcs:ignore
		/**
		 * Returns the recurring total per billing cycle.
		 *
		 * @return string
		 */
		public function get_total(): string {
			return '0';
		}

		/**
		 * Returns the billing period: 'day', 'week', 'month', or 'year'.
		 *
		 * @return string
		 */
		public function get_billing_period(): string {
			return 'month';
		}

		/**
		 * Returns the billing interval (e.g. 3 for "every 3 months").
		 *
		 * @return int
		 */
		public function get_billing_interval(): int {
			return 1;
		}

		/**
		 * Returns the subscription's currency code.
		 *
		 * @return string
		 */
		public function get_currency(): string {
			return 'USD';
		}

		/**
		 * Returns the subscription status (e.g. 'active', 'on-hold').
		 *
		 * @return string
		 */
		public function get_status(): string {
			return '';
		}

		/**
		 * Returns a named subscription date (e.g. 'next_payment'). The real
		 * implementation returns a DateTime-ish object or empty string.
		 *
		 * @param string $date_type Date key.
		 * @return mixed
		 */
		public function get_date( string $date_type ): mixed {
			return '';
		}

		/**
		 * Returns the human-readable payment method title.
		 *
		 * @return string
		 */
		public function get_payment_method_title(): string {
			return '';
		}

		/**
		 * Returns a meta value by key.
		 *
		 * @param string $key Meta key.
		 * @return mixed
		 */
		public function get_meta( string $key ): mixed {
			return '';
		}
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Stub for WC_Product — used as a type hint in format_product().
	 *
	 * Provides only the getter methods called by LocationController::format_product().
	 * In unit tests, Mockery overrides these with configured return values.
	 */
	abstract class WC_Product { // phpcs:ignore
		/**
		 * Returns the product ID.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return 0;
		}

		/**
		 * Returns the product name.
		 *
		 * @return string
		 */
		public function get_name(): string {
			return '';
		}

		/**
		 * Returns the product slug.
		 *
		 * @return string
		 */
		public function get_slug(): string {
			return '';
		}

		/**
		 * Returns the active price.
		 *
		 * @return string
		 */
		public function get_price(): string {
			return '';
		}

		/**
		 * Returns the regular (non-sale) price.
		 *
		 * @return string
		 */
		public function get_regular_price(): string {
			return '';
		}

		/**
		 * Returns the product status.
		 *
		 * @return string
		 */
		public function get_status(): string {
			return 'publish';
		}

		/**
		 * Returns the featured image attachment ID.
		 *
		 * @return int|string
		 */
		public function get_image_id(): int|string {
			return 0;
		}
	}
}

// -------------------------------------------------------------------------
// WooCommerce payment-token classes
// -------------------------------------------------------------------------

if ( ! class_exists( 'WC_Payment_Token' ) ) {
	/**
	 * Stub for WC_Payment_Token — returned by WC_Payment_Tokens::get_customer_tokens().
	 *
	 * Tests construct instances directly with a token id and display name.
	 */
	class WC_Payment_Token { // phpcs:ignore
		/** @var int */
		private int $id;
		/** @var string */
		private string $display_name;

		/**
		 * Constructor.
		 *
		 * @param int    $id           Token id.
		 * @param string $display_name Human-readable summary (e.g. "Visa ending in 4242").
		 */
		public function __construct( int $id = 0, string $display_name = '' ) {
			$this->id           = $id;
			$this->display_name = $display_name;
		}

		/**
		 * Returns the token id.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return $this->id;
		}

		/**
		 * Returns the human-readable token summary.
		 *
		 * @return string
		 */
		public function get_display_name(): string {
			return $this->display_name;
		}
	}
}

if ( ! class_exists( 'WC_Payment_Tokens' ) ) {
	/**
	 * Stub for WC_Payment_Tokens — used by MemberController billing section.
	 *
	 * Tests preset tokens via the static $__customer_tokens map, keyed by user id.
	 */
	class WC_Payment_Tokens { // phpcs:ignore
		/** @var array<int, WC_Payment_Token[]> */
		public static array $__customer_tokens = array();

		/**
		 * Returns the saved tokens for a customer.
		 *
		 * @param int $user_id Customer user id.
		 * @return WC_Payment_Token[]
		 */
		public static function get_customer_tokens( int $user_id ): array {
			return self::$__customer_tokens[ $user_id ] ?? array();
		}

		/**
		 * Resets the test preset. Call in tearDown().
		 *
		 * @return void
		 */
		public static function __test_reset(): void {
			self::$__customer_tokens = array();
		}
	}
}
