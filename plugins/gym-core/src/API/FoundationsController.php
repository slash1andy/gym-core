<?php
/**
 * Foundations clearance REST controller.
 *
 * @package Gym_Core\API
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Attendance\FoundationsClearance;

/**
 * Handles REST endpoints for the Foundations clearance system.
 *
 * Routes:
 *   GET  /gym/v1/foundations/{user_id}  Status for a student
 *   POST /gym/v1/foundations/enroll     Enroll a student
 *   POST /gym/v1/foundations/coach-roll Record a supervised roll
 *   POST /gym/v1/foundations/clear      Clear a student for live training
 *   GET  /gym/v1/foundations/active     List all in-Foundations students
 */
class FoundationsController extends BaseController {

	/**
	 * @var FoundationsClearance
	 */
	private FoundationsClearance $foundations;

	/**
	 * Constructor.
	 *
	 * @param FoundationsClearance $foundations Foundations system.
	 */
	public function __construct( FoundationsClearance $foundations ) {
		parent::__construct();
		$this->foundations = $foundations;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/foundations/(?P<user_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permissions_view' ),
				'args'                => array(
					'user_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/foundations/enroll',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'enroll' ),
				'permission_callback' => array( $this, 'permissions_coach' ),
				'args'                => array(
					'user_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/foundations/coach-roll',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_coach_roll' ),
				'permission_callback' => array( $this, 'permissions_coach' ),
				'args'                => array(
					'user_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'notes'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/foundations/clear',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clear' ),
				'permission_callback' => array( $this, 'permissions_coach' ),
				'args'                => array(
					'user_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/foundations/active',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_active' ),
				'permission_callback' => array( $this, 'permissions_view_all' ),
			)
		);
	}

	/**
	 * Permission: own data or gym_view_ranks.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view( \WP_REST_Request $request ): bool|\WP_Error {
		$user_id = (int) $request->get_param( 'user_id' );

		if ( get_current_user_id() === $user_id ) {
			return true;
		}

		if ( current_user_can( 'gym_view_ranks' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'forbidden', __( 'You do not have permission to view this data.', 'gym-core' ), 403 );
	}

	/**
	 * Permission: gym_promote_student.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_coach( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_promote_student' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'forbidden', __( 'Coach or admin access required.', 'gym-core' ), 403 );
	}

	/**
	 * Permission: gym_view_ranks.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view_all( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_view_ranks' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'forbidden', __( 'Permission denied.', 'gym-core' ), 403 );
	}

	/**
	 * GET /foundations/{user_id} — full status.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_status( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = (int) $request->get_param( 'user_id' );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return $this->error_response( 'not_found', __( 'User not found.', 'gym-core' ), 404 );
		}

		$status = $this->foundations->get_status( $user_id );

		return $this->success_response( array_merge(
			array(
				'user_id'      => $user_id,
				'display_name' => $user->display_name,
			),
			$status
		) );
	}

	/**
	 * POST /foundations/enroll — enroll a student.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function enroll( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = (int) $request->get_param( 'user_id' );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return $this->error_response( 'not_found', __( 'User not found.', 'gym-core' ), 404 );
		}

		if ( ! FoundationsClearance::is_enabled() ) {
			return $this->error_response( 'disabled', __( 'Foundations gate is not enabled.', 'gym-core' ), 400 );
		}

		$current = $this->foundations->get_status( $user_id );
		if ( $current['in_foundations'] || $current['cleared'] ) {
			return $this->error_response( 'already_enrolled', __( 'Student is already enrolled or cleared.', 'gym-core' ), 409 );
		}

		$this->foundations->enroll( $user_id );

		return $this->success_response(
			array_merge( array( 'user_id' => $user_id ), $this->foundations->get_status( $user_id ) ),
			null,
			201
		);
	}

	/**
	 * POST /foundations/coach-roll — record a supervised roll.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function record_coach_roll( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = (int) $request->get_param( 'user_id' );
		$notes   = $request->get_param( 'notes' ) ?? '';
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return $this->error_response( 'not_found', __( 'User not found.', 'gym-core' ), 404 );
		}

		$recorded = $this->foundations->record_coach_roll( $user_id, get_current_user_id(), $notes );

		if ( ! $recorded ) {
			return $this->error_response( 'not_in_foundations', __( 'Student is not currently in Foundations.', 'gym-core' ), 400 );
		}

		return $this->success_response(
			array_merge( array( 'user_id' => $user_id ), $this->foundations->get_status( $user_id ) )
		);
	}

	/**
	 * POST /foundations/clear — clear a student for live training.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function clear( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = (int) $request->get_param( 'user_id' );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return $this->error_response( 'not_found', __( 'User not found.', 'gym-core' ), 404 );
		}

		$cleared = $this->foundations->clear( $user_id, get_current_user_id() );

		if ( ! $cleared ) {
			return $this->error_response( 'not_in_foundations', __( 'Student is not currently in Foundations.', 'gym-core' ), 400 );
		}

		return $this->success_response(
			array_merge( array( 'user_id' => $user_id ), $this->foundations->get_status( $user_id ) )
		);
	}

	/**
	 * GET /foundations/active — list all students in Foundations.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_active( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		// Query all users with Foundations meta that don't have a cleared_at value.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_col(
			"SELECT user_id FROM {$wpdb->usermeta}
			WHERE meta_key = '_gym_foundations_status'
			AND meta_value LIKE '%\"cleared_at\";N;%'"
		);

		$students = array();
		foreach ( $results as $user_id ) {
			$user_id = (int) $user_id;
			$user    = get_userdata( $user_id );

			if ( ! $user ) {
				continue;
			}

			$status = $this->foundations->get_status( $user_id );

			if ( ! $status['in_foundations'] ) {
				continue;
			}

			$students[] = array(
				'user_id'      => $user_id,
				'display_name' => $user->display_name,
				'status'       => $status,
			);
		}

		return $this->success_response( $students );
	}
}
