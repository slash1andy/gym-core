<?php
/**
 * Push subscription REST endpoint (stub).
 *
 * Accepts a Web Push subscription object from the client and stores it in
 * user meta. Actual push delivery is wired in Phase 3.
 *
 * Route: POST gym/v1/pwa/push-subscribe
 *
 * @package Gym_Core\PWA
 * @since   5.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\PWA;

use Gym_Core\API\BaseController;

/**
 * Handles push-subscription storage for the PWA.
 */
final class PushSubscriptionEndpoint extends BaseController {

	/**
	 * REST route path (relative to the namespace).
	 *
	 * @var string
	 */
	private const ROUTE = 'pwa/push-subscribe';

	/**
	 * User meta key for the stored push subscription.
	 *
	 * @var string
	 */
	private const META_KEY = '_gym_push_subscription';

	/**
	 * Sets the REST base for this endpoint.
	 */
	public function __construct() {
		parent::__construct();
		$this->rest_base = self::ROUTE;
	}

	/**
	 * Registers the REST route.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_subscribe' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'endpoint' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'keys'     => array(
						'type'     => 'object',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Stores the push subscription in user meta and returns 200 OK.
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request The incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle_subscribe( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id      = get_current_user_id();
		$subscription = array(
			'endpoint' => $request->get_param( 'endpoint' ),
			'keys'     => $request->get_param( 'keys' ) ?? array(),
		);

		update_user_meta( $user_id, self::META_KEY, $subscription );

		return $this->success_response( array( 'stored' => true ) );
	}

	/**
	 * Requires the user to be logged in.
	 *
	 * @since 5.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function permissions_check(): bool|\WP_Error {
		return is_user_logged_in()
			? true
			: new \WP_Error(
				'rest_not_authenticated',
				__( 'You must be logged in to subscribe to push notifications.', 'gym-core' ),
				array( 'status' => 401 )
			);
	}

	/**
	 * Returns the full REST route string for this endpoint.
	 *
	 * @since 5.0.0
	 *
	 * @return string
	 */
	public static function get_route(): string {
		return self::REST_NAMESPACE . '/' . self::ROUTE;
	}
}
