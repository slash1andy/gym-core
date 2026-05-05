<?php
declare(strict_types=1);
/**
 * REST nonce middleware.
 *
 * Wraps a permission_callback so state-changing REST routes also require
 * a valid `wp_rest` nonce in the `X-WP-Nonce` header. Read-only routes
 * (GET/HEAD/OPTIONS) skip the nonce check.
 *
 * @package HMA_AI_Chat
 * @since   0.4.0
 */

namespace HMA_AI_Chat\Security;

use WP_Error;
use WP_REST_Request;

/**
 * Adds wp_rest nonce verification to state-changing REST routes.
 *
 * @since 0.4.0
 */
class RestNonceMiddleware {

	/**
	 * Verify the wp_rest nonce on state-changing requests.
	 *
	 * Returns true when the request is read-only OR carries a valid
	 * `X-WP-Nonce` header for the `wp_rest` action. Returns a WP_Error
	 * (HTTP 403) otherwise.
	 *
	 * Webhook-style routes (Paperclip heartbeat) use HMAC signing, not
	 * cookies, so they do not run through this middleware.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return true|WP_Error True when the nonce is valid (or not required); WP_Error otherwise.
	 */
	public static function verify( WP_REST_Request $request ) {
		$method = strtoupper( $request->get_method() );

		// Read-only verbs are exempt per security P0 sweep §A.5.
		if ( in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return true;
		}

		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
		}
		if ( empty( $nonce ) ) {
			$nonce = (string) ( $request->get_param( '_wpnonce' ) ?? '' );
		}

		if ( empty( $nonce ) ) {
			return new WP_Error(
				'rest_missing_nonce',
				esc_html__( 'A valid wp_rest nonce (X-WP-Nonce header) is required for this request.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		if ( ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_invalid_nonce',
				esc_html__( 'Invalid wp_rest nonce. Refresh the page and try again.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Wrap an existing permission_callback so the nonce check runs first.
	 *
	 * Usage:
	 *
	 *     'permission_callback' => RestNonceMiddleware::wrap( array( $this, 'check_permission' ) ),
	 *
	 * The wrapper runs the nonce check first; on success it delegates to
	 * the underlying callback. This keeps existing permission logic intact
	 * and adds the nonce gate as an outer middleware.
	 *
	 * @since 0.4.0
	 *
	 * @param callable $inner The original permission_callback.
	 * @return callable The wrapped callback.
	 */
	public static function wrap( callable $inner ): callable {
		return static function ( WP_REST_Request $request ) use ( $inner ) {
			$check = self::verify( $request );
			if ( true !== $check ) {
				return $check;
			}
			return $inner( $request );
		};
	}
}
