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

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stub for WP_Error — returned by controller error helpers.
	 */
	class WP_Error { // phpcs:ignore
		/**
		 * Machine-readable error code.
		 *
		 * @var string
		 */
		private string $code;

		/**
		 * Human-readable error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Additional error data (typically contains 'status' key for REST).
		 *
		 * @var mixed
		 */
		private mixed $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Additional data.
		 */
		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Returns the error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Returns the error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * Returns the additional error data.
		 *
		 * @return mixed
		 */
		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

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
