<?php
/**
 * Rank REST controller.
 *
 * @package Gym_Core\API
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Rank\RankStore;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Attendance\AttendanceStore;

/**
 * Handles REST endpoints for belt rank data and promotions.
 *
 * Routes:
 *   GET  /gym/v1/members/{id}/rank          Current rank (all programs or filtered)
 *   GET  /gym/v1/members/{id}/rank-history  Promotion audit trail
 *   POST /gym/v1/ranks/promote              Promote a member
 */
class RankController extends BaseController {

	/**
	 * @var RankStore
	 */
	private RankStore $ranks;

	/**
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Constructor.
	 *
	 * @param RankStore       $ranks      Rank data store.
	 * @param AttendanceStore $attendance Attendance data store.
	 */
	public function __construct( RankStore $ranks, AttendanceStore $attendance ) {
		parent::__construct();
		$this->ranks      = $ranks;
		$this->attendance = $attendance;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/members/(?P<id>[\d]+)/rank',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rank' ),
				'permission_callback' => array( $this, 'permissions_view_rank' ),
				'args'                => array(
					'id'      => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'program' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/members/(?P<id>[\d]+)/rank-history',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rank_history' ),
				'permission_callback' => array( $this, 'permissions_view_rank' ),
				'args'                => array_merge(
					$this->pagination_route_args(),
					array(
						'id'      => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
						'program' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					)
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/ranks/promote',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'promote' ),
				'permission_callback' => array( $this, 'permissions_promote' ),
				'args'                => array(
					'user_id'  => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'program'  => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'belt'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'stripes'  => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'notes'    => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	/**
	 * Permission: view own rank or gym_view_ranks capability.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view_rank( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'rest_not_logged_in', __( 'Authentication required.', 'gym-core' ), 401 );
		}

		$target_id = $request->get_param( 'id' );

		// Members can view their own rank.
		if ( (int) $target_id === get_current_user_id() ) {
			return true;
		}

		// Coaches/admins can view anyone's rank.
		if ( current_user_can( 'gym_view_ranks' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You cannot view this member\'s rank.', 'gym-core' ), 403 );
	}

	/**
	 * Permission: gym_promote_student capability.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_promote( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_promote_student' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You do not have permission to promote members.', 'gym-core' ), 403 );
	}

	/**
	 * Returns current rank for a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_rank( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = $request->get_param( 'id' );
		$program = $request->get_param( 'program' );

		if ( $program ) {
			$rank = $this->ranks->get_rank( $user_id, $program );

			if ( ! $rank ) {
				return $this->success_response( array() );
			}

			return $this->success_response( array( $this->format_rank( $rank, $program, $user_id ) ) );
		}

		$all_ranks = $this->ranks->get_all_ranks( $user_id );
		$formatted = array_map(
			fn( $rank ) => $this->format_rank( $rank, $rank->program, $user_id ),
			$all_ranks
		);

		return $this->success_response( $formatted );
	}

	/**
	 * Returns promotion history for a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_rank_history( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = $request->get_param( 'id' );
		$program = $request->get_param( 'program' ) ?: '';

		$history = $this->ranks->get_history( $user_id, $program );

		$formatted = array_map(
			static function ( $record ) {
				$promoter = $record->promoted_by ? get_userdata( (int) $record->promoted_by ) : null;

				return array(
					'program'      => $record->program,
					'from_belt'    => $record->from_belt,
					'from_stripes' => $record->from_stripes !== null ? (int) $record->from_stripes : null,
					'to_belt'      => $record->to_belt,
					'to_stripes'   => (int) $record->to_stripes,
					'promoted_at'  => $record->promoted_at,
					'promoted_by'  => $promoter ? array( 'id' => (int) $record->promoted_by, 'name' => $promoter->display_name ) : null,
					'notes'        => $record->notes,
				);
			},
			$history
		);

		return $this->success_response( $formatted );
	}

	/**
	 * Promotes a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function promote( \WP_REST_Request $request ) {
		$user_id  = $request->get_param( 'user_id' );
		$program  = $request->get_param( 'program' );
		$belt     = $request->get_param( 'belt' );
		$stripes  = $request->get_param( 'stripes' );
		$notes    = $request->get_param( 'notes' );

		if ( ! get_userdata( $user_id ) ) {
			return $this->error_response( 'invalid_user', __( 'Member not found.', 'gym-core' ), 404 );
		}

		$programs = RankDefinitions::get_programs();
		if ( ! isset( $programs[ $program ] ) ) {
			return $this->error_response( 'invalid_program', __( 'Invalid program.', 'gym-core' ), 400 );
		}

		// If no belt specified, add a stripe to the current belt.
		if ( empty( $belt ) ) {
			$rank_store = $this->ranks;
			$result     = $rank_store->add_stripe( $user_id, $program, get_current_user_id(), $notes );

			if ( ! $result ) {
				return $this->error_response( 'stripe_failed', __( 'Cannot add stripe. Member may be at max stripes or have no current rank.', 'gym-core' ), 400 );
			}

			$updated = $this->ranks->get_rank( $user_id, $program );

			return $this->success_response(
				$this->format_rank( $updated, $program, $user_id ),
				null,
				200
			);
		}

		// Promote to specified belt.
		$this->ranks->promote( $user_id, $program, $belt, $stripes ?? 0, get_current_user_id(), $notes );
		$updated = $this->ranks->get_rank( $user_id, $program );

		return $this->success_response(
			$this->format_rank( $updated, $program, $user_id ),
			null,
			200
		);
	}

	/**
	 * Formats a rank record for API response.
	 *
	 * @param object $rank    Rank row object.
	 * @param string $program Program slug.
	 * @param int    $user_id User ID.
	 * @return array<string, mixed>
	 */
	private function format_rank( object $rank, string $program, int $user_id ): array {
		$promoter  = $rank->promoted_by ? get_userdata( (int) $rank->promoted_by ) : null;
		$next_belt = RankDefinitions::get_next_belt( $program, $rank->belt );
		$attendance_since = $this->attendance->get_count_since( $user_id, $rank->promoted_at );

		return array(
			'program'                    => $program,
			'belt'                       => $rank->belt,
			'stripes'                    => (int) $rank->stripes,
			'promoted_at'                => $rank->promoted_at,
			'promoted_by'                => $promoter ? array( 'id' => (int) $rank->promoted_by, 'name' => $promoter->display_name ) : null,
			'attendance_since_promotion' => $attendance_since,
			'next_belt'                  => $next_belt ? $next_belt['slug'] : null,
		);
	}
}
