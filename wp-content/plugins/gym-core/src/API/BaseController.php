<?php
/**
 * Abstract base REST API controller.
 *
 * @package Gym_Core\API
 */

declare( strict_types=1 );

namespace Gym_Core\API;

/**
 * Shared foundation for all Gym Core REST controllers.
 *
 * Provides:
 * - Hook registration (rest_api_init → register_routes)
 * - Reusable permission callbacks (public, authenticated, manage_woocommerce)
 * - Consistent JSON envelope: { success, data, meta? }
 * - Pagination meta builder and route arg definitions
 * - Transient-based rate limiting utility (for future SMS endpoints)
 *
 * Subclasses must implement register_routes() and set $this->rest_base.
 */
abstract class BaseController extends \WP_REST_Controller {

	/**
	 * REST namespace shared by all Gym Core endpoints.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REST_NAMESPACE = 'gym/v1';

	/**
	 * Default maximum requests allowed within a single rate-limit window.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RATE_LIMIT_MAX = 60;

	/**
	 * Default rate-limit window in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Sets the REST namespace used by all subclasses.
	 */
	public function __construct() {
		$this->namespace = self::REST_NAMESPACE;
	}

	/**
	 * Registers the rest_api_init hook so routes are declared at the correct
	 * point in the WordPress boot sequence.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers all REST routes for this controller.
	 *
	 * Overrides the parent concrete method. Subclasses must implement this.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Intentionally empty — subclasses must override.
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Permission callback for publicly accessible endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return true
	 */
	public function permissions_public( \WP_REST_Request $request ): bool {
		return true;
	}

	/**
	 * Permission callback for endpoints that require a logged-in user.
	 *
	 * Returns a WP_Error with status 401 rather than bare false so that REST
	 * clients receive the semantically correct HTTP status code.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return true|\WP_Error True when authenticated; WP_Error(401) otherwise.
	 */
	public function permissions_authenticated( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required.', 'gym-core' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for endpoints that require WooCommerce management access.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return true|\WP_Error True when the user has manage_woocommerce; WP_Error otherwise.
	 */
	public function permissions_manage( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'gym-core' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Permission callback: view own data or require a specific capability.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request    The REST request.
	 * @param string           $id_param   Request parameter name for the target user ID.
	 * @param string           $capability Required capability for other users' data.
	 * @return true|\WP_Error
	 */
	protected function permissions_view_own_or_cap( \WP_REST_Request $request, string $id_param, string $capability ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_not_logged_in', __( 'Authentication required.', 'gym-core' ), array( 'status' => 401 ) );
		}

		if ( (int) $request->get_param( $id_param ) === get_current_user_id() ) {
			return true;
		}

		if ( current_user_can( $capability ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to access this data.', 'gym-core' ), array( 'status' => 403 ) );
	}

	/**
	 * Wrap a permission callback with REST nonce verification.
	 *
	 * Use for state-changing routes (POST/PUT/PATCH/DELETE). Read-only
	 * verbs are exempt at the middleware layer.
	 *
	 * @since 1.1.0
	 *
	 * @param callable $callback The underlying permission callback.
	 * @return callable
	 */
	protected function with_nonce( callable $callback ): callable {
		return \Gym_Core\Security\RestNonceMiddleware::wrap( $callback );
	}

	// -------------------------------------------------------------------------
	// Response formatting
	// -------------------------------------------------------------------------

	/**
	 * Builds a standardised success response.
	 *
	 * Envelope: { "success": true, "data": <mixed>[, "meta": { ... }] }
	 *
	 * The meta key is omitted entirely when null to keep responses lean.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed                    $data   Response payload.
	 * @param array<string,mixed>|null $meta   Optional metadata (e.g. pagination).
	 * @param int                      $status HTTP status code (default 200).
	 * @return \WP_REST_Response
	 */
	protected function success_response( mixed $data, ?array $meta = null, int $status = 200 ): \WP_REST_Response {
		$body = array(
			'success' => true,
			'data'    => $data,
		);

		if ( null !== $meta ) {
			$body['meta'] = $meta;
		}

		return new \WP_REST_Response( $body, $status );
	}

	/**
	 * Builds a WP_Error for REST error responses.
	 *
	 * The error data array must carry a `status` key so WordPress maps the
	 * error to the correct HTTP response code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code    Machine-readable error code (snake_case).
	 * @param string $message Human-readable error message (translatable).
	 * @param int    $status  HTTP status code (default 400).
	 * @return \WP_Error
	 */
	protected function error_response( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	// -------------------------------------------------------------------------
	// Pagination helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a pagination meta array for use with success_response().
	 *
	 * @since 1.0.0
	 *
	 * @param int $total       Total number of matching items across all pages.
	 * @param int $total_pages Total number of pages.
	 * @param int $page        Current page number (1-based).
	 * @param int $per_page    Number of items per page.
	 * @return array<string, array<string, int>>
	 */
	protected function pagination_meta( int $total, int $total_pages, int $page, int $per_page ): array {
		return array(
			'pagination' => array(
				'total'       => $total,
				'total_pages' => $total_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			),
		);
	}

	/**
	 * Returns standard page/per_page route argument definitions.
	 *
	 * Merge these into your register_rest_route() args array on any endpoint
	 * that returns a paged collection.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function pagination_route_args(): array {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'gym-core' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to return per page.', 'gym-core' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	// -------------------------------------------------------------------------
	// Rate limiting
	// -------------------------------------------------------------------------

	/**
	 * Checks and increments a transient-based rate-limit counter.
	 *
	 * Returns true when the caller is within the allowed budget, false when the
	 * limit has been reached. The counter resets after $window seconds.
	 *
	 * Intended for use before high-cost or side-effecting operations such as
	 * outbound SMS messages. For single-server or object-cache deployments this
	 * is sufficient; for multi-process high-frequency endpoints prefer an atomic
	 * Redis INCR counter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    Unique bucket identifier (e.g. 'sms_' . $user_id).
	 * @param int    $max    Maximum requests allowed within the window.
	 * @param int    $window Time window in seconds.
	 * @return bool True if within limit; false if the limit is exceeded.
	 */
	protected function check_rate_limit(
		string $key,
		int $max = self::RATE_LIMIT_MAX,
		int $window = self::RATE_LIMIT_WINDOW
	): bool {
		$transient_key = 'gym_rl_' . substr( md5( $key ), 0, 16 );
		$data          = get_transient( $transient_key );

		if ( false !== $data && is_array( $data ) ) {
			$count        = (int) $data['count'];
			$window_start = (int) $data['start'];

			if ( $count >= $max ) {
				return false;
			}

			// Preserve the original window expiry (fixed window, not sliding).
			$elapsed   = time() - $window_start;
			$remaining = max( 1, $window - $elapsed );

			set_transient(
				$transient_key,
				array(
					'count' => $count + 1,
					'start' => $window_start,
				),
				$remaining
			);
		} else {
			// First hit: create the transient so the window starts now.
			set_transient(
				$transient_key,
				array(
					'count' => 1,
					'start' => time(),
				),
				$window
			);
		}

		return true;
	}
}
