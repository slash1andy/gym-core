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

		// Diagnostic endpoint to check what CRM functions/globals are available.
		register_rest_route(
			'gym/v1',
			'/import/crm-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_check' ),
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
	 * Diagnostic: check what CRM APIs are available.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public static function handle_check( \WP_REST_Request $request ): \WP_REST_Response {
		global $zbs;

		$checks = array(
			'zbs_global_exists'                          => isset( $zbs ),
			'zbs_is_object'                              => isset( $zbs ) && is_object( $zbs ),
			'zbs_DAL_exists'                             => isset( $zbs ) && isset( $zbs->DAL ),
			'zbs_DAL_contacts_exists'                    => isset( $zbs ) && isset( $zbs->DAL ) && isset( $zbs->DAL->contacts ),
			'fn_zeroBS_integrations_addOrUpdateContact'  => function_exists( 'zeroBS_integrations_addOrUpdateContact' ),
			'fn_zeroBS_getCustomerIDWithEmail'           => function_exists( 'zeroBS_getCustomerIDWithEmail' ),
			'fn_zeroBSCRM_getContactIDFromEmail'         => function_exists( 'zeroBSCRM_getContactIDFromEmail' ),
			'fn_zeroBS_getContactByEmail'                => function_exists( 'zeroBS_getContactByEmail' ),
			'class_ZeroBSCRM'                            => class_exists( 'ZeroBSCRM' ),
			'class_zbsDAL'                               => class_exists( 'zbsDAL' ),
		);

		// Check DAL method availability.
		if ( isset( $zbs ) && isset( $zbs->DAL ) && isset( $zbs->DAL->contacts ) ) {
			$checks['DAL_contacts_addUpdateContact'] = method_exists( $zbs->DAL->contacts, 'addUpdateContact' );
			$checks['DAL_contacts_getContact']       = method_exists( $zbs->DAL->contacts, 'getContact' );
		}

		return new \WP_REST_Response( $checks, 200 );
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

		global $zbs;

		// Check for DAL (preferred modern approach).
		$use_dal = isset( $zbs ) && isset( $zbs->DAL ) && isset( $zbs->DAL->contacts )
			&& method_exists( $zbs->DAL->contacts, 'addUpdateContact' );

		// Fallback to legacy function.
		$use_legacy = function_exists( 'zeroBS_integrations_addOrUpdateContact' );

		if ( ! $use_dal && ! $use_legacy ) {
			return new \WP_REST_Response(
				array(
					'error'  => 'No Jetpack CRM contact creation API available.',
					'method' => 'none',
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

			if ( $use_dal ) {
				$contact_data = array(
					'data' => array(
						'email'   => $email,
						'fname'   => $user->first_name ?: '',
						'lname'   => $user->last_name ?: '',
						'hometel' => $phone,
						'status'  => 'Customer',
					),
					'tags' => array(
						array( 'name' => 'member' ),
						array( 'name' => 'source: spark-import' ),
					),
				);

				if ( $existing_id ) {
					$contact_data['id'] = $existing_id;
				}

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$result_id = $zbs->DAL->contacts->addUpdateContact( $contact_data );

				if ( $result_id && (int) $result_id > 0 ) {
					if ( $existing_id ) {
						++$updated;
					} else {
						++$created;
					}
				} else {
					$warnings[] = "User {$user->ID} ({$email}): DAL returned " . var_export( $result_id, true );
					++$skipped;
				}
			} else {
				// Legacy path.
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
					$check_id = self::get_crm_contact_id( $email );
					if ( $check_id ) {
						++( $existing_id ? $updated : $created );
					} else {
						$warnings[] = "User {$user->ID} ({$email}): creation failed.";
						++$skipped;
					}
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'dry_run'  => $dry_run,
				'method'   => $method,
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
		global $zbs;

		// DAL approach.
		if ( isset( $zbs ) && isset( $zbs->DAL ) && isset( $zbs->DAL->contacts )
			&& method_exists( $zbs->DAL->contacts, 'getContact' ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$contact = $zbs->DAL->contacts->getContact( -1, array( 'email' => $email, 'onlyID' => true ) );
			if ( $contact && (int) $contact > 0 ) {
				return (int) $contact;
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

		return false;
	}
}
