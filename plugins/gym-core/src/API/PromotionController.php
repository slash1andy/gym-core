<?php
/**
 * Promotion eligibility REST controller.
 *
 * @package Gym_Core\API
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Attendance\PromotionEligibility;

/**
 * Handles REST endpoints for promotion eligibility queries.
 *
 * Routes:
 *   GET  /gym/v1/promotions/eligible       List eligible/approaching members
 *   POST /gym/v1/promotions/recommend      Set a coach recommendation
 */
class PromotionController extends BaseController {

	/**
	 * @var PromotionEligibility
	 */
	private PromotionEligibility $eligibility;

	/**
	 * Constructor.
	 *
	 * @param PromotionEligibility $eligibility Promotion eligibility engine.
	 */
	public function __construct( PromotionEligibility $eligibility ) {
		parent::__construct();
		$this->eligibility = $eligibility;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/promotions/eligible',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_eligible' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'program' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/promotions/recommend',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'recommend' ),
				'permission_callback' => array( $this, 'permissions_coach' ),
				'args'                => array(
					'user_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'program' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	/**
	 * Permission: gym_promote_student or manage_woocommerce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_coach( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_promote_student' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'Coach or admin access required.', 'gym-core' ), 403 );
	}

	/**
	 * Returns eligible and approaching members for a program.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_eligible( \WP_REST_Request $request ): \WP_REST_Response {
		$program = $request->get_param( 'program' );
		$members = $this->eligibility->get_eligible_members( $program );

		return $this->success_response(
			$members,
			array( 'total' => count( $members ) )
		);
	}

	/**
	 * Sets a coach recommendation for a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function recommend( \WP_REST_Request $request ) {
		$user_id = $request->get_param( 'user_id' );
		$program = $request->get_param( 'program' );

		if ( ! get_userdata( $user_id ) ) {
			return $this->error_response( 'invalid_user', __( 'Member not found.', 'gym-core' ), 404 );
		}

		$this->eligibility->set_recommendation( $user_id, $program, get_current_user_id() );

		return $this->success_response(
			array(
				'user_id'        => $user_id,
				'program'        => $program,
				'recommended_by' => get_current_user_id(),
				'recommended_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			null,
			201
		);
	}
}
