<?php
declare(strict_types=1);
/**
 * REST API endpoint for pending action approvals.
 *
 * Handles the three-path approval flow:
 * 1. Approve — action executes immediately, Paperclip receives confirmation.
 * 2. Approve with changes — agent re-executes with staff instructions, then executes.
 * 3. Reject — action discarded, Paperclip logs the rejection.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * REST endpoint for managing pending action approvals.
 *
 * @since 0.1.0
 */
class ActionEndpoint {

	const ROUTE      = 'hma-ai-chat/v1';
	const CAPABILITY = 'manage_options';

	/**
	 * Register all action-related REST routes.
	 *
	 * Must be called during rest_api_init.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public function register_routes() {
		// GET: List pending actions.
		register_rest_route(
			self::ROUTE,
			'/pending-actions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pending_actions' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST: Approve an action (immediate execution).
		register_rest_route(
			self::ROUTE,
			'/actions/(?P<id>[\d]+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_action' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// POST: Approve an action with staff-directed changes.
		register_rest_route(
			self::ROUTE,
			'/actions/(?P<id>[\d]+)/approve-with-changes',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_with_changes' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'           => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'instructions' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// POST: Reject an action.
		register_rest_route(
			self::ROUTE,
			'/actions/(?P<id>[\d]+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject_action' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'     => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'reason' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// POST: Bulk approve or reject actions.
		register_rest_route(
			self::ROUTE,
			'/actions/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_action' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'action_ids' => array(
						'type'              => 'array',
						'required'          => true,
						'items'             => array( 'type' => 'integer' ),
						'sanitize_callback' => static function ( $ids ) {
							return array_map( 'absint', (array) $ids );
						},
						'validate_callback' => 'rest_validate_request_arg',
					),
					'operation' => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array( 'approve', 'reject' ),
						'validate_callback' => 'rest_validate_request_arg',
					),
					'reason'    => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// GET: Audit log — all actions with filters.
		register_rest_route(
			self::ROUTE,
			'/actions/log',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_audit_log' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'status'   => array(
						'type'              => 'string',
						'required'          => false,
						'enum'              => array( '', 'pending', 'approved', 'approved_with_changes', 'completed', 'rejected' ),
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'agent'    => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'per_page' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 20,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'page'     => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// GET: Get a single action's current status (for Paperclip polling).
		register_rest_route(
			self::ROUTE,
			'/actions/(?P<id>[\d]+)/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_action_status' ),
				'permission_callback' => array( $this, 'check_webhook_or_admin' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Check that user has admin capability.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to manage actions.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check for admin capability OR valid webhook signature.
	 *
	 * The status endpoint is used by both admins (dashboard) and Paperclip
	 * (polling for approval results).
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_webhook_or_admin( WP_REST_Request $request ) {
		// Admin users always have access.
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}

		// Fall back to webhook signature validation for Paperclip.
		$validator   = new \HMA_AI_Chat\Security\WebhookValidator();
		$auth_header = $request->get_header( 'Authorization' );

		if ( $validator->validate_request( $auth_header ) && $validator->validate_ip() ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'Unauthorized.', 'hma-ai-chat' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Get all pending actions.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_pending_actions( WP_REST_Request $request ) {
		$store   = new \HMA_AI_Chat\Data\PendingActionStore();
		$actions = $store->get_pending_actions();

		return rest_ensure_response( $actions );
	}

	/**
	 * Approve an action for immediate execution.
	 *
	 * Flow: Staff approves -> action executes -> Paperclip receives confirmation.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_action( WP_REST_Request $request ) {
		$action_id = absint( $request->get_param( 'id' ) );
		$store     = new \HMA_AI_Chat\Data\PendingActionStore();

		// Verify the action exists and is pending.
		$action = $store->get_action( $action_id );
		if ( ! $action ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Action not found.', 'hma-ai-chat' ),
				array( 'status' => 404 )
			);
		}

		if ( 'pending' !== $action['status'] ) {
			return new WP_Error(
				'invalid_status',
				esc_html__( 'Action is not in a pending state.', 'hma-ai-chat' ),
				array( 'status' => 409 )
			);
		}

		$result = $store->approve_action( $action_id, get_current_user_id() );

		if ( ! $result ) {
			return new WP_Error(
				'update_failed',
				esc_html__( 'Failed to approve action.', 'hma-ai-chat' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'action_id' => $action_id,
				'status'    => 'approved',
				'message'   => esc_html__( 'Action approved. Executing now.', 'hma-ai-chat' ),
			)
		);
	}

	/**
	 * Approve an action with staff-directed changes.
	 *
	 * Flow: Staff approves with instructions -> agent re-executes incorporating
	 * changes -> action executes -> Paperclip receives confirmation.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_with_changes( WP_REST_Request $request ) {
		$action_id    = absint( $request->get_param( 'id' ) );
		$instructions = sanitize_textarea_field( $request->get_param( 'instructions' ) );
		$store        = new \HMA_AI_Chat\Data\PendingActionStore();

		if ( empty( $instructions ) ) {
			return new WP_Error(
				'missing_instructions',
				esc_html__( 'Instructions are required when approving with changes.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}

		// Verify the action exists and is pending.
		$action = $store->get_action( $action_id );
		if ( ! $action ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Action not found.', 'hma-ai-chat' ),
				array( 'status' => 404 )
			);
		}

		if ( 'pending' !== $action['status'] ) {
			return new WP_Error(
				'invalid_status',
				esc_html__( 'Action is not in a pending state.', 'hma-ai-chat' ),
				array( 'status' => 409 )
			);
		}

		$result = $store->approve_with_changes( $action_id, get_current_user_id(), $instructions );

		if ( ! $result ) {
			return new WP_Error(
				'update_failed',
				esc_html__( 'Failed to approve action with changes.', 'hma-ai-chat' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'action_id'    => $action_id,
				'status'       => 'approved_with_changes',
				'instructions' => $instructions,
				'message'      => esc_html__( 'Action approved with changes. Agent will revise and execute.', 'hma-ai-chat' ),
			)
		);
	}

	/**
	 * Reject an action.
	 *
	 * Flow: Staff rejects -> action discarded -> Paperclip logs the rejection.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject_action( WP_REST_Request $request ) {
		$action_id = absint( $request->get_param( 'id' ) );
		$reason    = sanitize_textarea_field( $request->get_param( 'reason' ) ?? '' );
		$store     = new \HMA_AI_Chat\Data\PendingActionStore();

		// Verify the action exists and is pending.
		$action = $store->get_action( $action_id );
		if ( ! $action ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Action not found.', 'hma-ai-chat' ),
				array( 'status' => 404 )
			);
		}

		if ( 'pending' !== $action['status'] ) {
			return new WP_Error(
				'invalid_status',
				esc_html__( 'Action is not in a pending state.', 'hma-ai-chat' ),
				array( 'status' => 409 )
			);
		}

		$result = $store->reject_action( $action_id, get_current_user_id(), $reason );

		if ( ! $result ) {
			return new WP_Error(
				'update_failed',
				esc_html__( 'Failed to reject action.', 'hma-ai-chat' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'action_id' => $action_id,
				'status'    => 'rejected',
				'message'   => esc_html__( 'Action rejected and discarded.', 'hma-ai-chat' ),
			)
		);
	}

	/**
	 * Get a single action's current status.
	 *
	 * Used by Paperclip to poll for approval results and by the admin
	 * dashboard to refresh action state.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_action_status( WP_REST_Request $request ) {
		$action_id = absint( $request->get_param( 'id' ) );
		$store     = new \HMA_AI_Chat\Data\PendingActionStore();

		$action = $store->get_action( $action_id );
		if ( ! $action ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Action not found.', 'hma-ai-chat' ),
				array( 'status' => 404 )
			);
		}

		$response_data = array(
			'action_id'   => $action_id,
			'status'      => $action['status'],
			'agent'       => $action['agent'],
			'action_type' => $action['action_type'],
			'created_at'  => $action['created_at'],
			'approved_at' => $action['approved_at'],
			'approved_by' => $action['approved_by'],
		);

		// Include staff instructions if approved with changes.
		if ( 'approved_with_changes' === $action['status'] && isset( $action['action_data']['staff_changes'] ) ) {
			$response_data['staff_instructions'] = $action['action_data']['staff_changes'];
		}

		// Include rejection reason if rejected.
		if ( 'rejected' === $action['status'] && isset( $action['action_data']['rejection_reason'] ) ) {
			$response_data['rejection_reason'] = $action['action_data']['rejection_reason'];
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * Bulk approve or reject actions.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_action( WP_REST_Request $request ) {
		$action_ids = $request->get_param( 'action_ids' );
		$operation  = $request->get_param( 'operation' );
		$reason     = $request->get_param( 'reason' ) ?? '';
		$store      = new \HMA_AI_Chat\Data\PendingActionStore();
		$user_id    = get_current_user_id();

		if ( empty( $action_ids ) ) {
			return new WP_Error(
				'empty_ids',
				esc_html__( 'No action IDs provided.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}

		if ( 'approve' === $operation ) {
			$result = $store->bulk_approve( $action_ids, $user_id );

			return rest_ensure_response(
				array(
					'success'  => true,
					'approved' => $result['approved'],
					'failed'   => $result['failed'],
					'message'  => sprintf(
						/* translators: 1: approved count, 2: failed count */
						esc_html__( '%1$d approved, %2$d failed.', 'hma-ai-chat' ),
						count( $result['approved'] ),
						count( $result['failed'] )
					),
				)
			);
		}

		$result = $store->bulk_reject( $action_ids, $user_id, $reason );

		return rest_ensure_response(
			array(
				'success'  => true,
				'rejected' => $result['rejected'],
				'failed'   => $result['failed'],
				'message'  => sprintf(
					/* translators: 1: rejected count, 2: failed count */
					esc_html__( '%1$d rejected, %2$d failed.', 'hma-ai-chat' ),
					count( $result['rejected'] ),
					count( $result['failed'] )
				),
			)
		);
	}

	/**
	 * Get the audit log of all actions.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_audit_log( WP_REST_Request $request ) {
		$store  = new \HMA_AI_Chat\Data\PendingActionStore();
		$result = $store->get_all_actions(
			array(
				'status'   => $request->get_param( 'status' ) ?? '',
				'agent'    => $request->get_param( 'agent' ) ?? '',
				'per_page' => $request->get_param( 'per_page' ),
				'page'     => $request->get_param( 'page' ),
			)
		);

		// Enrich items with approver display names.
		foreach ( $result['items'] as &$item ) {
			if ( ! empty( $item['approved_by'] ) ) {
				$user = get_userdata( (int) $item['approved_by'] );
				$item['approved_by_name'] = $user ? $user->display_name : __( 'Unknown', 'hma-ai-chat' );
			}
		}

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', $result['total_pages'] );

		return $response;
	}
}
