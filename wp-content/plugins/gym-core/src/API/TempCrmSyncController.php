<?php
/**
 * TEMPORARY: One-time REST endpoint to bulk-sync WP users → Jetpack CRM contacts.
 *
 * DELETE THIS FILE after the sync is complete.
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
	 * Discovers the available CRM API at runtime and uses whichever works.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public static function handle_sync( \WP_REST_Request $request ): \WP_REST_Response {
		// phpcs:ignore WordPress.PHP.IniSet.Risky
		set_time_limit( 300 );

		$dry_run = (bool) $request->get_param( 'dry_run' );

		// Discover which CRM API is available.
		global $zbs;

		$api_info = array(
			'zbs_global'           => isset( $zbs ) && is_object( $zbs ),
			'fn_addOrUpdate'       => function_exists( 'zeroBS_integrations_addOrUpdateContact' ),
			'fn_getCustomerByEmail' => function_exists( 'zeroBS_getCustomerIDWithEmail' ),
		);

		$has_dal = false;
		if ( isset( $zbs ) && is_object( $zbs ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$has_dal = isset( $zbs->DAL ) && is_object( $zbs->DAL );
			$api_info['has_dal'] = $has_dal;

			if ( $has_dal ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$has_contacts = isset( $zbs->DAL->contacts ) && is_object( $zbs->DAL->contacts );
				$api_info['has_dal_contacts'] = $has_contacts;
			}
		}

		// Determine best creation method.
		$use_dal    = $has_dal && isset( $zbs->DAL->contacts ) && method_exists( $zbs->DAL->contacts, 'addUpdateContact' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$use_legacy = function_exists( 'zeroBS_integrations_addOrUpdateContact' );

		if ( ! $use_dal && ! $use_legacy ) {
			return new \WP_REST_Response(
				array(
					'error'    => 'No Jetpack CRM contact creation API available.',
					'api_info' => $api_info,
				),
				500
			);
		}

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
		$method   = $use_dal ? 'DAL' : 'legacy';

		foreach ( $users as $user ) {
			$email = $user->user_email;

			if ( empty( $email ) || ! is_email( $email ) ) {
				$warnings[] = "User {$user->ID}: invalid email, skipped.";
				++$skipped;
				continue;
			}

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

			$success = false;

			if ( $use_dal ) {
				$contact_args = array(
					'data' => array(
						'email'   => $email,
						'fname'   => $user->first_name ?: '',
						'lname'   => $user->last_name ?: '',
						'hometel' => $phone,
						'status'  => 'Customer',
					),
				);

				if ( $existing_id ) {
					$contact_args['id'] = $existing_id;
				}

				try {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$result_id = $zbs->DAL->contacts->addUpdateContact( $contact_args );
					$success   = $result_id && (int) $result_id > 0;
				} catch ( \Throwable $e ) {
					$warnings[] = "User {$user->ID} ({$email}): DAL error: " . $e->getMessage();
					++$skipped;
					continue;
				}
			} else {
				$contact_data = array(
					'email'   => $email,
					'fname'   => $user->first_name ?: '',
					'lname'   => $user->last_name ?: '',
					'hometel' => $phone,
				);

				$result  = zeroBS_integrations_addOrUpdateContact( 'gym-core-spark-import', $email, $contact_data );
				$success = is_array( $result ) && ! empty( $result['id'] );

				if ( ! $success ) {
					// Check if it was still created.
					$success = (bool) self::get_crm_contact_id( $email );
				}
			}

			if ( $success ) {
				if ( $existing_id ) {
					++$updated;
				} else {
					++$created;
				}
			} else {
				$warnings[] = "User {$user->ID} ({$email}): creation failed.";
				++$skipped;
			}
		}

		return new \WP_REST_Response(
			array(
				'dry_run'  => $dry_run,
				'method'   => $method,
				'api_info' => $api_info,
				'total'    => count( $users ),
				'created'  => $created,
				'updated'  => $updated,
				'skipped'  => $skipped,
				'warnings' => array_slice( $warnings, 0, 50 ),
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
		global $zbs;

		// DAL approach.
		if ( isset( $zbs ) && is_object( $zbs ) && isset( $zbs->DAL ) && is_object( $zbs->DAL ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			try {
				if ( isset( $zbs->DAL->contacts ) && method_exists( $zbs->DAL->contacts, 'getContact' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$contact = $zbs->DAL->contacts->getContact(
						-1,
						array(
							'email'  => $email,
							'onlyID' => true,
						)
					);
					if ( $contact && (int) $contact > 0 ) {
						return (int) $contact;
					}
					return false;
				}
			} catch ( \Throwable $e ) {
				// Fall through to legacy.
			}
		}

		// Legacy functions.
		if ( function_exists( 'zeroBS_getCustomerIDWithEmail' ) ) {
			$id = zeroBS_getCustomerIDWithEmail( $email );
			return $id ? (int) $id : false;
		}

		if ( function_exists( 'zeroBSCRM_getContactIDFromEmail' ) ) {
			$id = zeroBSCRM_getContactIDFromEmail( $email );
			return $id ? (int) $id : false;
		}

		// Direct DB fallback.
		global $wpdb;
		$table = $wpdb->prefix . 'zbs_contacts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$contact_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$table} WHERE zbsc_email = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email
			)
		);

		return null !== $contact_id ? (int) $contact_id : false;
	}
}
