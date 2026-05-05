<?php
/**
 * Attendance REST controller.
 *
 * @package Gym_Core\API
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\CheckInValidator;
use Gym_Core\Gamification\StreakTracker;
use Gym_Core\Location\Manager as LocationManager;

/**
 * Handles REST endpoints for check-in and attendance data.
 *
 * Routes:
 *   POST /gym/v1/check-in                Record a member check-in
 *   GET  /gym/v1/attendance/{user_id}    Member attendance history
 *   GET  /gym/v1/attendance/today        Today's check-ins (filterable)
 */
class AttendanceController extends BaseController {

	/**
	 * @var AttendanceStore
	 */
	private AttendanceStore $store;

	/**
	 * @var CheckInValidator
	 */
	private CheckInValidator $validator;

	/**
	 * @var StreakTracker|null
	 */
	private ?StreakTracker $streak_tracker;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore    $store          Attendance data store.
	 * @param CheckInValidator   $validator      Check-in validator.
	 * @param StreakTracker|null $streak_tracker Optional streak tracker for check-in response enrichment.
	 */
	public function __construct( AttendanceStore $store, CheckInValidator $validator, ?StreakTracker $streak_tracker = null ) {
		parent::__construct();
		$this->store          = $store;
		$this->validator      = $validator;
		$this->streak_tracker = $streak_tracker;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/check-in',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_in' ),
				'permission_callback' => $this->with_nonce( array( $this, 'permissions_check_in' ) ),
				'args'                => array(
					'user_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'class_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'method'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
						'enum'              => array( 'qr_scan', 'member_id', 'name_search', 'manual' ),
					),
					'location' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Location slug. Used as fallback when class_id is 0 (Open Mat).', 'gym-core' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/attendance/(?P<user_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'permissions_view_attendance' ),
				'args'                => array_merge(
					$this->pagination_route_args(),
					array(
						'user_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'from'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'to'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
					)
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/attendance/today',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_today' ),
				'permission_callback' => array( $this, 'permissions_view_all_attendance' ),
				'args'                => array(
					'location' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'class_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Permission: gym_check_in_member or manage_options.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_check_in( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_check_in_member' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You cannot check in members.', 'gym-core' ), 403 );
	}

	/**
	 * Permission: view own attendance or gym_view_attendance.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view_attendance( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->permissions_view_own_or_cap( $request, 'user_id', 'gym_view_attendance' );
	}

	/**
	 * Permission: gym_view_attendance (for the today view — coaches/admins only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view_all_attendance( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_view_attendance' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You do not have permission to view attendance.', 'gym-core' ), 403 );
	}

	/**
	 * Records a check-in.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function check_in( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id  = $request->get_param( 'user_id' );
		$class_id = $request->get_param( 'class_id' );
		$method   = $request->get_param( 'method' );

		// Determine location from the class, fall back to request param (kiosk/Open Mat).
		$location = '';
		if ( $class_id > 0 ) {
			$location_terms = get_the_terms( $class_id, 'gym_location' );
			if ( $location_terms && ! is_wp_error( $location_terms ) ) {
				$location = $location_terms[0]->slug;
			}
		}
		if ( '' === $location ) {
			$location = sanitize_text_field( $request->get_param( 'location' ) ?? '' );
		}

		// Validate.
		$validation = $this->validator->validate( $user_id, $class_id, $location );

		if ( is_wp_error( $validation ) ) {
			$code   = (string) $validation->get_error_code();
			$status = 'duplicate_checkin' === $code ? 409 : 403;
			return $this->error_response( $code, $validation->get_error_message(), $status );
		}

		// Record the check-in.
		$record_id = $this->store->record_checkin( $user_id, $location, $class_id, $method );

		if ( false === $record_id ) {
			return $this->error_response( 'checkin_failed', __( 'Failed to record check-in.', 'gym-core' ), 500 );
		}

		$user  = get_userdata( $user_id );
		$class = get_post( $class_id );

		$response_data = array(
			'attendance_id' => $record_id,
			'user'          => array(
				'id'   => $user_id,
				'name' => $user ? $user->display_name : '',
			),
			'class'         => array(
				'id'   => $class_id,
				'name' => $class ? $class->post_title : '',
			),
			'location'      => $location,
			'checked_in_at' => gmdate( 'Y-m-d H:i:s' ),
			'method'        => $method,
		);

		// Include streak data when the tracker is available (used by kiosk success screen).
		if ( $this->streak_tracker ) {
			$streak_data                     = $this->streak_tracker->get_streak( $user_id );
			$response_data['current_streak'] = $streak_data['current_streak'];
		}

		return $this->success_response( $response_data, null, 201 );
	}

	/**
	 * Returns attendance history for a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_history( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id  = $request->get_param( 'user_id' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$from     = $request->get_param( 'from' ) ?: '';
		$to       = $request->get_param( 'to' ) ?: '';
		$offset   = ( $page - 1 ) * $per_page;

		$records = $this->store->get_user_history( $user_id, $per_page, $offset, $from, $to );
		$total   = $this->store->get_total_count( $user_id, $from );

		// Prime the post cache in bulk to avoid N+1 queries in the loop.
		$class_ids = array_unique(
			array_filter(
				array_map(
					static fn( $record ) => (int) $record->class_id,
					$records
				)
			)
		);

		if ( ! empty( $class_ids ) ) {
			_prime_post_caches( $class_ids, false, false );
		}

		$formatted = array_map(
			static function ( $record ) {
				$class = $record->class_id ? get_post( (int) $record->class_id ) : null;

				return array(
					'id'            => (int) $record->id,
					'class'         => $class ? array(
						'id'   => $class->ID,
						'name' => $class->post_title,
					) : null,
					'location'      => $record->location,
					'checked_in_at' => $record->checked_in_at,
					'method'        => $record->method,
				);
			},
			$records
		);

		return $this->success_response(
			$formatted,
			$this->pagination_meta(
				$total,
				(int) ceil( $total / max( 1, $per_page ) ),
				$page,
				$per_page
			)
		);
	}

	/**
	 * Returns today's attendance.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_today( \WP_REST_Request $request ): \WP_REST_Response {
		$location = $request->get_param( 'location' );
		$class_id = $request->get_param( 'class_id' );

		if ( $class_id ) {
			$records = $this->store->get_today_by_class( $class_id );
		} elseif ( $location ) {
			$records = $this->store->get_today_by_location( $location );
		} else {
			// All locations — single batched query instead of one query per location.
			$location_slugs = get_terms(
				array(
					'taxonomy'   => 'gym_location',
					'fields'     => 'slugs',
					'hide_empty' => false,
				)
			);

			$slugs   = ( ! is_wp_error( $location_slugs ) && is_array( $location_slugs ) ) ? $location_slugs : array();
			$grouped = $this->store->get_today_all_locations( $slugs );
			$records = array_merge( ...array_values( $grouped ) );
		}

		$formatted = array_map(
			static function ( $record ) {
				return array(
					'id'            => (int) $record->id,
					'user'          => array(
						'id'   => (int) $record->user_id,
						'name' => $record->display_name ?? '',
					),
					'class_id'      => (int) $record->class_id,
					'location'      => $record->location,
					'checked_in_at' => $record->checked_in_at,
					'method'        => $record->method,
				);
			},
			$records
		);

		return $this->success_response(
			$formatted,
			array( 'total' => count( $formatted ) )
		);
	}
}
