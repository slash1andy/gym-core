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
	 * Shared pending-action store instance, injected at construction time.
	 *
	 * @since 0.5.2
	 *
	 * @var \HMA_AI_Chat\Data\PendingActionStore
	 */
	private \HMA_AI_Chat\Data\PendingActionStore $pending_store;

	/**
	 * Constructor.
	 *
	 * @since 0.5.2
	 *
	 * @param \HMA_AI_Chat\Data\PendingActionStore $pending_store Shared store instance.
	 */
	public function __construct( \HMA_AI_Chat\Data\PendingActionStore $pending_store ) {
		$this->pending_store = $pending_store;
	}

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
				'permission_callback' => \HMA_AI_Chat\Security\RestNonceMiddleware::wrap( array( $this, 'check_permission' ) ),
				'args'                => array(
					'id'           => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'action_token' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
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
				'permission_callback' => \HMA_AI_Chat\Security\RestNonceMiddleware::wrap( array( $this, 'check_permission' ) ),
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
					'action_token' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
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
				'permission_callback' => \HMA_AI_Chat\Security\RestNonceMiddleware::wrap( array( $this, 'check_permission' ) ),
				'args'                => array(
					'id'           => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'reason'       => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'action_token' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
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
				'permission_callback' => \HMA_AI_Chat\Security\RestNonceMiddleware::wrap( array( $this, 'check_permission' ) ),
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
					'action_tokens' => array(
						'type'        => 'object',
						'required'    => true,
						'description' => 'Map of action_id => action_token, one per row in action_ids.',
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
		$validator  = new \HMA_AI_Chat\Security\WebhookValidator();
		$sig_header = $request->get_header( 'x-hma-signature' );

		if ( ! empty( $sig_header ) ) {
			// Prefer HMAC-over-body when the stronger header is present — not replayable.
			if ( $validator->validate_hmac_signature( $request->get_body(), $sig_header ) && $validator->validate_ip() ) {
				return true;
			}
		} elseif ( $validator->validate_request( $request->get_header( 'Authorization' ) ) && $validator->validate_ip() ) {
			// Bearer token fallback for backward compatibility.
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
	 * Each row is enriched with an `action_token` — an HMAC of
	 * (action_id + current_user_id + per-action nonce). The dashboard must
	 * include this token when calling approve/approve-with-changes/reject.
	 * It re-pins which action the user is acting on so a swap-on-double-click
	 * cannot succeed against a different row.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_pending_actions( WP_REST_Request $request ) {
		$store   = $this->pending_store;
		$actions = $store->get_pending_actions();
		$user_id = get_current_user_id();

		foreach ( $actions as &$action ) {
			$action_id = isset( $action['id'] ) ? (int) $action['id'] : 0;
			$nonce     = $this->action_nonce( $action );
			if ( $action_id > 0 ) {
				$action['action_token'] = \HMA_AI_Chat\Security\ActionTokens::issue( $action_id, $user_id, $nonce );
			}
		}
		unset( $action );

		return rest_ensure_response( $actions );
	}

	/**
	 * Per-action nonce used as the HMAC salt.
	 *
	 * Stable for the lifetime of the action: the run_id (when present) is
	 * the strongest disambiguator; we fall back to created_at + id so a
	 * row without a run_id still gets a row-unique nonce.
	 *
	 * @since 0.4.0
	 *
	 * @param array $action The decoded action row.
	 * @return string
	 */
	private function action_nonce( array $action ): string {
		$run_id     = isset( $action['run_id'] ) ? (string) $action['run_id'] : '';
		$created_at = isset( $action['created_at'] ) ? (string) $action['created_at'] : '';
		$id         = isset( $action['id'] ) ? (int) $action['id'] : 0;
		if ( '' !== $run_id ) {
			return $run_id;
		}
		return sprintf( 'created=%s|id=%d', $created_at, $id );
	}

	/**
	 * Verify the action_token from the request against the loaded action.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @param array           $action  The action row from PendingActionStore::get_action().
	 * @return true|WP_Error
	 */
	private function verify_action_token( WP_REST_Request $request, array $action ) {
		$token     = (string) ( $request->get_param( 'action_token' ) ?? '' );
		$action_id = isset( $action['id'] ) ? (int) $action['id'] : 0;
		$user_id   = get_current_user_id();
		$nonce     = $this->action_nonce( $action );

		if ( ! \HMA_AI_Chat\Security\ActionTokens::verify( $token, $action_id, $user_id, $nonce ) ) {
			return new WP_Error(
				'invalid_action_token',
				esc_html__( 'The action token does not match this action. Refresh the dashboard and try again.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		return true;
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
		$store     = $this->pending_store;

		// Verify the action exists and is pending.
		$action = $store->get_action( $action_id );
		if ( ! $action ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Action not found.', 'hma-ai-chat' ),
				array( 'status' => 404 )
			);
		}

		// Re-pin action identity: the action_token is HMAC of
		// (action_id + user_id + per-action nonce). A stale DOM node that
		// thinks it's approving Action A cannot succeed if the request
		// reaches us with Action B's id — the token will not match.
		$token_check = $this->verify_action_token( $request, $action );
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
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

		// Dispatch the approved write immediately. Without this the action
		// sits forever at 'approved' and never mutates anything (the original
		// design assumed an external "Paperclip" runner; on this site the
		// approval IS the trigger).
		$dispatch = $this->dispatch_approved( $action );

		return rest_ensure_response(
			array(
				'success'   => $dispatch['success'],
				'action_id' => $action_id,
				'status'    => $dispatch['success'] ? 'completed' : 'approved',
				'message'   => $dispatch['success']
					? esc_html__( 'Action approved and executed.', 'hma-ai-chat' )
					: sprintf(
						/* translators: %s: error message from the tool execution. */
						esc_html__( 'Action approved but execution failed: %s', 'hma-ai-chat' ),
						$dispatch['error'] ?? __( 'unknown error', 'hma-ai-chat' )
					),
				'executed'  => $dispatch['success'],
				'error'     => $dispatch['error'] ?? null,
				'result'    => $dispatch['result'] ?? null,
			)
		);
	}

	/**
	 * Run an approved action through ToolExecutor and persist the outcome.
	 *
	 * Looks up the shared ToolExecutor (capability checks + write-tool routing
	 * already live there). On success marks the row 'completed' and stores
	 * the execution payload; on failure keeps it at 'approved' but records
	 * the error so the audit log can surface what went wrong.
	 *
	 * @since 0.5.1
	 *
	 * @param array $action The pending action row, including parsed action_data.
	 * @return array{success: bool, error?: string, result?: mixed}
	 */
	private function dispatch_approved( array $action ): array {
		$executor = \HMA_AI_Chat\Plugin::instance()->get_tool_executor();
		$store    = $this->pending_store;

		if ( null === $executor ) {
			$store->record_execution_error( (int) $action['id'], __( 'Tool executor unavailable.', 'hma-ai-chat' ) );
			return array(
				'success' => false,
				'error'   => __( 'Tool executor unavailable.', 'hma-ai-chat' ),
			);
		}

		$action_data = is_array( $action['action_data'] ) ? $action['action_data'] : array();
		$result      = $executor->execute_approved_write( $action_data );

		if ( ! empty( $result['success'] ) ) {
			$store->mark_completed( (int) $action['id'], is_array( $result['data'] ?? null ) ? $result['data'] : array() );
			return array(
				'success' => true,
				'result'  => $result['data'] ?? null,
			);
		}

		$error = $result['error'] ?? __( 'Execution failed.', 'hma-ai-chat' );
		$store->record_execution_error( (int) $action['id'], (string) $error );
		return array(
			'success' => false,
			'error'   => (string) $error,
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
		$store        = $this->pending_store;

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

		$token_check = $this->verify_action_token( $request, $action );
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
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
		$store     = $this->pending_store;

		// Verify the action exists and is pending.
		$action = $store->get_action( $action_id );
		if ( ! $action ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Action not found.', 'hma-ai-chat' ),
				array( 'status' => 404 )
			);
		}

		$token_check = $this->verify_action_token( $request, $action );
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
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
		$store     = $this->pending_store;

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
		$action_ids    = $request->get_param( 'action_ids' );
		$operation     = $request->get_param( 'operation' );
		$reason        = $request->get_param( 'reason' ) ?? '';
		$action_tokens = $request->get_param( 'action_tokens' );
		$store         = $this->pending_store;
		$user_id       = get_current_user_id();

		if ( empty( $action_ids ) ) {
			return new WP_Error(
				'empty_ids',
				esc_html__( 'No action IDs provided.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}

		// Bulk operations must carry a token per action — same re-pin rule
		// as the single-row endpoints. A request missing tokens for any
		// row is rejected wholesale; partial bulk auth is too easy to
		// reason about wrong.
		if ( ! is_array( $action_tokens ) || empty( $action_tokens ) ) {
			return new WP_Error(
				'missing_action_tokens',
				esc_html__( 'A token map (action_tokens) is required for bulk operations.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}
		foreach ( $action_ids as $aid ) {
			$aid    = (int) $aid;
			$action = $store->get_action( $aid );
			if ( ! $action ) {
				return new WP_Error(
					'not_found',
					sprintf(
						/* translators: %d: action id */
						esc_html__( 'Action %d not found.', 'hma-ai-chat' ),
						$aid
					),
					array( 'status' => 404 )
				);
			}
			$token = isset( $action_tokens[ (string) $aid ] ) ? (string) $action_tokens[ (string) $aid ] : '';
			if ( '' === $token ) {
				$token = isset( $action_tokens[ $aid ] ) ? (string) $action_tokens[ $aid ] : '';
			}
			$nonce = $this->action_nonce( $action );
			if ( ! \HMA_AI_Chat\Security\ActionTokens::verify( $token, $aid, $user_id, $nonce ) ) {
				return new WP_Error(
					'invalid_action_token',
					sprintf(
						/* translators: %d: action id */
						esc_html__( 'Action token mismatch for action %d. Refresh the dashboard and try again.', 'hma-ai-chat' ),
						$aid
					),
					array( 'status' => 403 )
				);
			}
		}

		if ( 'approve' === $operation ) {
			$result = $store->bulk_approve( $action_ids, $user_id );

			// Dispatch each approved action through the executor immediately.
			// Same rationale as the single-action approve path: without this
			// the rows stay at 'approved' forever and never mutate anything.
			$executed = array();
			$exec_failed = array();
			foreach ( $result['approved'] as $action_id ) {
				$action = $store->get_action( (int) $action_id );
				if ( ! $action ) {
					$exec_failed[] = array( 'id' => (int) $action_id, 'error' => __( 'Action vanished after approval.', 'hma-ai-chat' ) );
					continue;
				}
				$dispatch = $this->dispatch_approved( $action );
				if ( $dispatch['success'] ) {
					$executed[] = (int) $action_id;
				} else {
					$exec_failed[] = array( 'id' => (int) $action_id, 'error' => $dispatch['error'] ?? __( 'unknown error', 'hma-ai-chat' ) );
				}
			}

			return rest_ensure_response(
				array(
					'success'         => true,
					'approved'        => $result['approved'],
					'failed'          => $result['failed'],
					'executed'        => $executed,
					'execution_failed' => $exec_failed,
					'message'         => sprintf(
						/* translators: 1: completed count, 2: approval-failed count, 3: execution-failed count */
						esc_html__( '%1$d completed, %2$d failed to approve, %3$d failed to execute.', 'hma-ai-chat' ),
						count( $executed ),
						count( $result['failed'] ),
						count( $exec_failed )
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
		$store  = $this->pending_store;
		$result = $store->get_all_actions(
			array(
				'status'   => $request->get_param( 'status' ) ?? '',
				'agent'    => $request->get_param( 'agent' ) ?? '',
				'per_page' => $request->get_param( 'per_page' ),
				'page'     => $request->get_param( 'page' ),
			)
		);

		// Warm the user-object cache once for all distinct reviewers so the
		// per-row get_userdata() lookups below stay O(1) cache hits.
		$reviewer_ids = array();
		foreach ( $result['items'] as $item ) {
			if ( ! empty( $item['approved_by'] ) ) {
				$reviewer_ids[] = (int) $item['approved_by'];
			}
		}
		$reviewer_ids = array_values( array_unique( array_filter( $reviewer_ids ) ) );
		if ( ! empty( $reviewer_ids ) ) {
			cache_users( $reviewer_ids );
		}

		// Enrich items with approver display names and pending-action tokens.
		$current_user_id = get_current_user_id();
		foreach ( $result['items'] as &$item ) {
			if ( ! empty( $item['approved_by'] ) ) {
				$user = get_userdata( (int) $item['approved_by'] );
				$item['approved_by_name'] = $user ? $user->display_name : __( 'Unknown', 'hma-ai-chat' );
			}
			if ( isset( $item['status'] ) && 'pending' === $item['status'] && ! empty( $item['id'] ) ) {
				$nonce               = $this->action_nonce( $item );
				$item['action_token'] = \HMA_AI_Chat\Security\ActionTokens::issue( (int) $item['id'], $current_user_id, $nonce );
			}
		}
		unset( $item );

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['total_pages'] );

		return $response;
	}
}
