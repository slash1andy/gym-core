<?php
declare(strict_types=1);
/**
 * REST API webhook/heartbeat endpoint for Paperclip integration.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Handles Paperclip webhook callbacks and agent execution.
 *
 * @since 0.1.0
 */
class HeartbeatEndpoint {

	const ROUTE    = 'hma-ai-chat/v1';
	const ENDPOINT = '/heartbeat';

	/**
	 * Register the REST route.
	 *
	 * Must be called during rest_api_init.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public function register_route() {
		register_rest_route(
			self::ROUTE,
			self::ENDPOINT,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_heartbeat' ),
				'permission_callback' => array( $this, 'check_webhook_signature' ),
				'args'                => $this->get_args_schema(),
			)
		);
	}

	/**
	 * Verify webhook signature and IP allowlist.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_webhook_signature( WP_REST_Request $request ) {
		$validator = new \HMA_AI_Chat\Security\WebhookValidator();

		// Check IP allowlist.
		if ( ! $validator->validate_ip() ) {
			return new WP_Error(
				'invalid_ip',
				esc_html__( 'IP address not in allowlist.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		// Check webhook signature — prefer HMAC-over-body, fall back to Bearer.
		$sig_header = $request->get_header( 'x-hma-signature' );

		if ( ! empty( $sig_header ) ) {
			if ( ! $validator->validate_hmac_signature( $request->get_body(), $sig_header ) ) {
				return new WP_Error(
					'invalid_signature',
					esc_html__( 'Invalid webhook signature.', 'hma-ai-chat' ),
					array( 'status' => 401 )
				);
			}
		} else {
			$auth_header = $request->get_header( 'Authorization' );
			if ( ! $validator->validate_request( $auth_header ) ) {
				return new WP_Error(
					'invalid_signature',
					esc_html__( 'Invalid webhook signature.', 'hma-ai-chat' ),
					array( 'status' => 401 )
				);
			}
		}

		return true;
	}

	/**
	 * Handle heartbeat/webhook request.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_heartbeat( WP_REST_Request $request ) {
		$run_id       = sanitize_text_field( $request->get_param( 'runId' ) );
		$agent_id     = sanitize_text_field( $request->get_param( 'agentId' ) );
		$task_id      = sanitize_text_field( $request->get_param( 'taskId' ) );
		$wake_reason  = sanitize_text_field( $request->get_param( 'wakeReason' ) );

		// Validate required fields.
		if ( empty( $run_id ) || empty( $agent_id ) ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'runId and agentId are required.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}

		try {
			// Map agent ID to slug.
			$agent_registry = \HMA_AI_Chat\Agents\AgentRegistry::instance();
			$agent          = $agent_registry->get_agent_by_id( $agent_id );

			if ( ! $agent ) {
				return new WP_Error(
					'invalid_agent',
					esc_html__( 'Invalid agent ID.', 'hma-ai-chat' ),
					array( 'status' => 400 )
				);
			}

			// Handle different wake reasons.
			switch ( $wake_reason ) {
				case 'approval_needed':
					return $this->handle_approval_request( $run_id, $agent, $task_id, $request );

				case 'execution_complete':
					return $this->handle_execution_complete( $run_id, $agent, $task_id );

				case 'revised_action_complete':
					return $this->handle_revised_action_complete( $run_id, $agent, $task_id, $request );

				case 'check_approval_status':
					return $this->handle_check_approval_status( $run_id, $agent, $task_id, absint( $request->get_param( 'actionId' ) ?? 0 ) );

				default:
					return rest_ensure_response(
						array(
							'status' => 'acknowledged',
							'runId'  => $run_id,
						)
					);
			}
		} catch ( \Exception $e ) {
			return new WP_Error(
				'processing_error',
				esc_html__( 'Error processing webhook.', 'hma-ai-chat' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Handle approval request from Paperclip.
	 *
	 * Creates a pending action that staff can approve, approve with changes,
	 * or reject from the admin dashboard.
	 *
	 * @since 0.1.0
	 *
	 * @param string                            $run_id  The run ID.
	 * @param \HMA_AI_Chat\Agents\AgentPersona $agent   The agent object.
	 * @param string                            $task_id The task ID.
	 * @param WP_REST_Request                   $request The full request (for action_data).
	 * @return WP_REST_Response
	 */
	private function handle_approval_request( $run_id, $agent, $task_id, WP_REST_Request $request ) {
		$action_store = new \HMA_AI_Chat\Data\PendingActionStore();

		// Extract action details from the request payload.
		$action_type = sanitize_text_field( $request->get_param( 'actionType' ) ?? 'approval_required' );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
		$action_data = $request->get_param( 'actionData' );

		// Build the action data payload.
		$stored_data = array(
			'run_id'      => $run_id,
			'task_id'     => $task_id,
			'agent_id'    => $agent->get_slug(),
			'description' => $description,
		);

		// Merge any additional action data from Paperclip (sanitized).
		$action_data = is_array( $action_data ) ? map_deep( $action_data, 'sanitize_text_field' ) : array();
		if ( ! empty( $action_data ) ) {
			$stored_data = array_merge( $stored_data, $action_data );
		}

		$action_id = $action_store->store_pending_action(
			$agent->get_slug(),
			$action_type,
			$stored_data,
			'pending',
			$run_id
		);

		return rest_ensure_response(
			array(
				'status'    => 'pending_approval',
				'runId'     => $run_id,
				'actionId'  => $action_id,
				'message'   => 'Action queued for staff approval. Poll /actions/{actionId}/status for result.',
			)
		);
	}

	/**
	 * Handle execution complete notification.
	 *
	 * @since 0.1.0
	 *
	 * @param string                            $run_id  The run ID.
	 * @param \HMA_AI_Chat\Agents\AgentPersona $agent   The agent object.
	 * @param string                            $task_id The task ID.
	 * @return WP_REST_Response
	 */
	private function handle_execution_complete( $run_id, $agent, $task_id ) {
		/**
		 * Fires when a Paperclip agent execution completes.
		 *
		 * @param string                            $run_id  The Paperclip run ID.
		 * @param \HMA_AI_Chat\Agents\AgentPersona $agent   The agent persona.
		 * @param string                            $task_id The Paperclip task ID.
		 *
		 * @since 0.1.0
		 */
		do_action( 'hma_ai_chat_execution_complete', $run_id, $agent, $task_id );

		return rest_ensure_response(
			array(
				'status' => 'acknowledged',
				'runId'  => $run_id,
			)
		);
	}

	/**
	 * Handle revised action completion from Paperclip.
	 *
	 * Called when Paperclip's agent has incorporated staff changes and
	 * completed the revised action.
	 *
	 * @since 0.1.0
	 *
	 * @param string                            $run_id  The run ID.
	 * @param \HMA_AI_Chat\Agents\AgentPersona $agent   The agent object.
	 * @param string                            $task_id The task ID.
	 * @param WP_REST_Request                   $request The full request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function handle_revised_action_complete( $run_id, $agent, $task_id, WP_REST_Request $request ) {
		$action_id    = absint( $request->get_param( 'actionId' ) ?? 0 );
		$revised_data = $request->get_param( 'revisedData' );
		$revised_data = is_array( $revised_data ) ? map_deep( $revised_data, 'sanitize_text_field' ) : array();

		if ( ! $action_id ) {
			return new WP_Error(
				'missing_action_id',
				esc_html__( 'actionId is required for revised_action_complete.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}

		$action_store = new \HMA_AI_Chat\Data\PendingActionStore();

		// Pin the completion to the run that originally created the action so a
		// stale or hijacked runId can't complete a different run's action.
		$existing = $action_store->get_action( $action_id );
		if ( ! $existing ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Action not found.', 'hma-ai-chat' ),
				array( 'status' => 404 )
			);
		}

		$stored_run_id = isset( $existing['run_id'] ) ? (string) $existing['run_id'] : '';
		if ( '' === $stored_run_id || ! hash_equals( $stored_run_id, (string) $run_id ) ) {
			return new WP_Error(
				'run_id_mismatch',
				esc_html__( 'runId does not match the action that staff approved.', 'hma-ai-chat' ),
				array( 'status' => 409 )
			);
		}

		$result = $action_store->complete_revised_action(
			$action_id,
			is_array( $revised_data ) ? $revised_data : array()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new WP_Error(
				'completion_failed',
				esc_html__( 'Could not complete revised action. Check that the action is in approved_with_changes status.', 'hma-ai-chat' ),
				array( 'status' => 409 )
			);
		}

		return rest_ensure_response(
			array(
				'status'   => 'completed',
				'runId'    => $run_id,
				'actionId' => $action_id,
				'message'  => 'Revised action marked as completed.',
			)
		);
	}

	/**
	 * Handle approval status check from Paperclip.
	 *
	 * Paperclip polls this after submitting an approval request to find out
	 * whether staff approved, approved with changes, or rejected.
	 *
	 * When Paperclip supplies the actionId returned from the first poll it
	 * queries by primary key, eliminating the race condition in
	 * get_action_by_run_id() where two actions sharing a run_id caused the
	 * older one to be silently stranded.
	 *
	 * @since 0.1.0
	 *
	 * @param string                            $run_id         The run ID.
	 * @param \HMA_AI_Chat\Agents\AgentPersona $agent          The agent object.
	 * @param string                            $task_id        The task ID.
	 * @param int                               $action_id_hint Optional action ID from a prior poll response.
	 * @return WP_REST_Response
	 */
	private function handle_check_approval_status( $run_id, $agent, $task_id, int $action_id_hint = 0 ) {
		$store = new \HMA_AI_Chat\Data\PendingActionStore();

		// Prefer primary-key lookup when Paperclip supplies the actionId from
		// a previous poll — eliminates the ORDER BY created_at DESC race.
		$action = $action_id_hint > 0
			? $store->get_action( $action_id_hint )
			: $store->get_action_by_run_id( $run_id );

		if ( ! $action ) {
			return rest_ensure_response(
				array(
					'status'  => 'not_found',
					'runId'   => $run_id,
					'message' => 'No action found for this run ID.',
				)
			);
		}

		$action_data = $action['action_data'];
		$response    = array(
			'status'   => $action['status'],
			'runId'    => $run_id,
			'actionId' => (int) $action['id'],
		);

		// If approved with changes, include the staff instructions
		// so Paperclip can incorporate them.
		if ( 'approved_with_changes' === $action['status'] && isset( $action_data['staff_changes'] ) ) {
			$response['staff_instructions'] = $action_data['staff_changes'];
			$response['original_proposal']  = $action_data['original_proposal'] ?? '';
		}

		// If rejected, include the reason.
		if ( 'rejected' === $action['status'] && isset( $action_data['rejection_reason'] ) ) {
			$response['rejection_reason'] = $action_data['rejection_reason'];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get endpoint argument schema.
	 *
	 * @return array
	 */
	private function get_args_schema() {
		return array(
			'runId'      => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'agentId'    => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'taskId'     => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'wakeReason' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			// Returned in check_approval_status responses so Paperclip can pass it
			// back on subsequent polls for a primary-key lookup (eliminates race).
			'actionId'   => array(
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
