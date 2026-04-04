<?php
/**
 * TEMPORARY: One-time REST endpoint to bulk-sync WP users → Jetpack CRM contacts.
 *
 * DELETE THIS FILE after the sync is complete.
 *
 * Trigger via:
 *   curl -X POST https://haanpaa-staging.mystagingwebsite.com/wp-json/gym/v1/import/crm-sync \
 *     -H "X-Import-Token: <token>"
 *
 * Add ?dry_run=1 for a dry run.
 *
 * @package Gym_Core
 */

declare( strict_types=1 );

namespace Gym_Core\API;

/**
 * Bulk-syncs WordPress customer/subscriber users into Jetpack CRM as contacts.
 */
final class TempCrmSyncController {

	/**
	 * One-time secret token.
	 */
	private const TOKEN = 'b629f866424b8273ae5f21b387074130a06c59aa90891d588304a64e872fc51f';

	/**
	 * Register the temporary route.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'gym/v1',
			'/import/crm-sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_sync' ),
				'permission_callback' => array( self::class, 'check_token' ),
			)
		);
	}

	/**
	 * Verify the import token.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public static function check_token( \WP_REST_Request $request ) {
		$token = $request->get_header( 'X-Import-Token' );
		if ( ! $token || ! hash_equals( self::TOKEN, $token ) ) {
			return new \WP_Error( 'forbidden', 'Invalid import token.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Sync all customer/subscriber WP users to Jetpack CRM contacts.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public static function handle_sync( \WP_REST_Request $request ): \WP_REST_Response {
		// phpcs:ignore WordPress.PHP.IniSet.Risky
		set_time_limit( 300 );

		$dry_run = (bool) $request->get_param( 'dry_run' );

		// Check Jetpack CRM is available.
		if ( ! function_exists( 'zeroBS_integrations_addOrUpdateContact' ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Jetpack CRM function zeroBS_integrations_addOrUpdateContact not available.' ),
				500
			);
		}

		// Get all customer and subscriber users.
		$users = get_users(
			array(
				'role__in' => array( 'customer', 'subscriber' ),
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'number'   => -1,
			)
		);

		$created  = 0;
		$updated  = 0;
		$skipped  = 0;
		$warnings = array();

		foreach ( $users as $user ) {
			$email = $user->user_email;

			if ( empty( $email ) || ! is_email( $email ) ) {
				$warnings[] = "User {$user->ID}: invalid email, skipped.";
				++$skipped;
				continue;
			}

			// Check if contact already exists in CRM.
			$existing_id = self::get_crm_contact_id( $email );

			if ( $dry_run ) {
				if ( $existing_id ) {
					++$updated;
				} else {
					++$created;
				}
				continue;
			}

			$phone = get_user_meta( $user->ID, 'phone', true )
				?: get_user_meta( $user->ID, 'billing_phone', true )
				?: '';

			$contact_data = array(
				'email'   => $email,
				'fname'   => $user->first_name ?: '',
				'lname'   => $user->last_name ?: '',
				'hometel' => $phone,
				'tags'    => array( 'member', 'source: spark-import' ),
			);

			$result = zeroBS_integrations_addOrUpdateContact(
				'gym-core-spark-import',
				$email,
				$contact_data
			);

			if ( is_array( $result ) && ! empty( $result['id'] ) ) {
				if ( $existing_id ) {
					++$updated;
				} else {
					++$created;
				}
			} else {
				// Verify it was created by looking it up.
				$check_id = self::get_crm_contact_id( $email );
				if ( $check_id ) {
					if ( $existing_id ) {
						++$updated;
					} else {
						++$created;
					}
				} else {
					$warnings[] = "User {$user->ID} ({$email}): CRM contact creation returned unexpected result.";
					++$skipped;
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'dry_run'  => $dry_run,
				'total'    => count( $users ),
				'created'  => $created,
				'updated'  => $updated,
				'skipped'  => $skipped,
				'warnings' => $warnings,
			),
			200
		);
	}

	/**
	 * Look up a CRM contact ID by email.
	 *
	 * @param string $email Email address.
	 * @return int|false
	 */
	private static function get_crm_contact_id( string $email ) {
		if ( function_exists( 'zeroBS_getCustomerIDWithEmail' ) ) {
			$id = zeroBS_getCustomerIDWithEmail( $email );
			return $id ? (int) $id : false;
		}

		if ( function_exists( 'zeroBSCRM_getContactIDFromEmail' ) ) {
			$id = zeroBSCRM_getContactIDFromEmail( $email );
			return $id ? (int) $id : false;
		}

		return false;
	}
}
