<?php
/**
 * Location REST controller.
 *
 * @package Gym_Core\API
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Location\Manager;
use Gym_Core\Location\Taxonomy;

/**
 * Handles REST endpoints for gym locations and user location preferences.
 *
 * Routes registered:
 *   GET  /gym/v1/locations                      List all locations.
 *   GET  /gym/v1/locations/{slug}               Single location detail.
 *   GET  /gym/v1/locations/{slug}/products      Products at a location (paginated).
 *   GET  /gym/v1/user/location                  Authenticated user's saved location.
 *   PUT  /gym/v1/user/location                  Update the user's location preference.
 *
 * Permissions:
 *   - Location reads: public (no auth required).
 *   - User location reads and writes: authenticated users only.
 */
class LocationController extends BaseController {

	/**
	 * REST collection base segment shared by location endpoints.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'locations';

	/**
	 * Location manager — resolves and persists location state.
	 *
	 * @since 1.0.0
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Manager $manager The location manager instance.
	 */
	public function __construct( Manager $manager ) {
		parent::__construct();
		$this->manager = $manager;
	}

	/**
	 * Registers all location REST routes via register_rest_route().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /gym/v1/locations.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_locations' ),
					'permission_callback' => array( $this, 'permissions_public' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// GET /gym/v1/locations/{slug}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_location' ),
					'permission_callback' => array( $this, 'permissions_public' ),
					'args'                => $this->location_slug_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// GET /gym/v1/locations/{slug}/products.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9-]+)/products',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_location_products' ),
					'permission_callback' => array( $this, 'permissions_public' ),
					'args'                => array_merge(
						$this->location_slug_args(),
						$this->pagination_route_args()
					),
				),
			)
		);

		// GET + PUT /gym/v1/user/location.
		register_rest_route(
			$this->namespace,
			'/user/location',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user_location' ),
					'permission_callback' => array( $this, 'permissions_authenticated' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_user_location' ),
					'permission_callback' => $this->with_nonce( array( $this, 'permissions_authenticated' ) ),
					'args'                => $this->user_location_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Endpoint handlers
	// -------------------------------------------------------------------------

	/**
	 * Returns all registered gym locations.
	 *
	 * GET /gym/v1/locations
	 *
	 * Response shape:
	 *   { success: true, data: [ { slug, name, description, count, link }, ... ] }
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_locations( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::SLUG,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $this->error_response(
				'gym_locations_fetch_failed',
				__( 'Unable to retrieve locations.', 'gym-core' ),
				500
			);
		}

		$locations = array_map( array( $this, 'format_term' ), (array) $terms );

		return $this->success_response( array_values( $locations ) );
	}

	/**
	 * Returns a single location by slug.
	 *
	 * GET /gym/v1/locations/{slug}
	 *
	 * Response shape:
	 *   { success: true, data: { slug, name, description, count, link } }
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_location( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$slug = sanitize_key( (string) $request->get_param( 'slug' ) );

		if ( ! Taxonomy::is_valid( $slug ) ) {
			return $this->error_response(
				'gym_location_not_found',
				__( 'Location not found.', 'gym-core' ),
				404
			);
		}

		$term = get_term_by( 'slug', $slug, Taxonomy::SLUG );

		if ( ! $term instanceof \WP_Term ) {
			return $this->error_response(
				'gym_location_not_found',
				__( 'Location not found.', 'gym-core' ),
				404
			);
		}

		return $this->success_response( $this->format_term( $term ) );
	}

	/**
	 * Returns published products assigned to a location, with pagination.
	 *
	 * GET /gym/v1/locations/{slug}/products
	 *
	 * Response shape:
	 *   {
	 *     success: true,
	 *     data: [ { id, name, slug, price, regular_price, status, permalink, image }, ... ],
	 *     meta: { pagination: { total, total_pages, page, per_page } }
	 *   }
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_location_products( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$slug = sanitize_key( (string) $request->get_param( 'slug' ) );

		if ( ! Taxonomy::is_valid( $slug ) ) {
			return $this->error_response(
				'gym_location_not_found',
				__( 'Location not found.', 'gym-core' ),
				404
			);
		}

		$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 10 ) ) );

		$result = wc_get_products(
			array(
				'status'    => 'publish',
				'limit'     => $per_page,
				'page'      => $page,
				'paginate'  => true,
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Taxonomy::SLUG,
						'field'    => 'slug',
						'terms'    => array( $slug ),
					),
				),
			)
		);

		if ( ! is_object( $result ) ) {
			return $this->success_response( array(), $this->pagination_meta( 0, 1, $page, $per_page ) );
		}

		$products = array_map( array( $this, 'format_product' ), $result->products );

		return $this->success_response(
			array_values( $products ),
			$this->pagination_meta(
				(int) $result->total,
				(int) $result->max_num_pages,
				$page,
				$per_page
			)
		);
	}

	/**
	 * Returns the current user's saved location preference.
	 *
	 * GET /gym/v1/user/location
	 *
	 * Response shape when a location is saved:
	 *   { success: true, data: { location: "rockford", label: "Rockford" } }
	 *
	 * Response shape when no preference is set:
	 *   { success: true, data: null }
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_user_location( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		$slug    = $this->manager->get_user_location( $user_id );

		if ( ! Taxonomy::is_valid( $slug ) ) {
			return $this->success_response( null );
		}

		return $this->success_response(
			array(
				'location' => $slug,
				'label'    => $this->location_label( $slug ),
			)
		);
	}

	/**
	 * Updates the current user's location preference.
	 *
	 * PUT /gym/v1/user/location
	 *
	 * Request body: { "location": "rockford" }
	 *
	 * Response shape on success:
	 *   { success: true, data: { location: "rockford", label: "Rockford" } }
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_user_location( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$slug    = sanitize_key( (string) $request->get_param( 'location' ) );
		$user_id = get_current_user_id();

		if ( ! $this->manager->set_user_location( $user_id, $slug ) ) {
			return $this->error_response(
				'gym_invalid_location',
				__( 'Invalid location slug.', 'gym-core' ),
				422
			);
		}

		return $this->success_response(
			array(
				'location' => $slug,
				'label'    => $this->location_label( $slug ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Schema
	// -------------------------------------------------------------------------

	/**
	 * Returns the JSON schema for a single location item.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_public_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'gym-location',
			'type'       => 'object',
			'properties' => array(
				'slug'        => array(
					'description' => __( 'Unique location identifier.', 'gym-core' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'name'        => array(
					'description' => __( 'Human-readable location name.', 'gym-core' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'description' => array(
					'description' => __( 'Optional location description.', 'gym-core' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'count'       => array(
					'description' => __( 'Number of products assigned to this location.', 'gym-core' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'link'        => array(
					'description' => __( 'URL to the location archive page.', 'gym-core' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Formats a WP_Term into the standard location response shape.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term The taxonomy term to format.
	 * @return array<string, mixed>
	 */
	private function format_term( \WP_Term $term ): array {
		$link = get_term_link( $term );

		return array(
			'slug'        => $term->slug,
			'name'        => $term->name,
			'description' => $term->description,
			'count'       => $term->count,
			'link'        => is_wp_error( $link ) ? '' : esc_url_raw( $link ),
		);
	}

	/**
	 * Formats a WC_Product into the standard product response shape.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Product $product The WooCommerce product to format.
	 * @return array<string, mixed>
	 */
	private function format_product( \WC_Product $product ): array {
		$image_id = $product->get_image_id();

		return array(
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'slug'          => $product->get_slug(),
			'price'         => $product->get_price(),
			'regular_price' => $product->get_regular_price(),
			'status'        => $product->get_status(),
			'permalink'     => esc_url_raw( (string) get_permalink( $product->get_id() ) ),
			'image'         => $image_id ? esc_url_raw( (string) wp_get_attachment_url( $image_id ) ) : '',
		);
	}

	/**
	 * Returns the human-readable label for a location slug.
	 *
	 * Uses translated strings to match what's stored in the taxonomy terms.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug A valid location slug.
	 * @return string
	 */
	private function location_label( string $slug ): string {
		$labels = Taxonomy::get_location_labels();
		return $labels[ $slug ] ?? $slug;
	}

	/**
	 * Returns the route args definition for the {slug} path parameter.
	 *
	 * Validates that the provided slug is a known location before the
	 * callback is invoked, so callbacks can assume a valid slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function location_slug_args(): array {
		return array(
			'slug' => array(
				'description'       => __( 'Location slug.', 'gym-core' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => static function ( string $value ): bool {
					return Taxonomy::is_valid( $value );
				},
			),
		);
	}

	/**
	 * Returns the route args definition for the user location PUT body.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function user_location_args(): array {
		return array(
			'location' => array(
				'description'       => __( "Location slug to set as the user's preference.", 'gym-core' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => static function ( string $value ): bool {
					return Taxonomy::is_valid( $value );
				},
			),
		);
	}
}
