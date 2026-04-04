<?php
/**
 * REST API message endpoint.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Handles chat message processing via REST API.
 *
 * @since 0.1.0
 */
class MessageEndpoint {

	const ROUTE      = 'hma-ai-chat/v1';
	const ENDPOINT   = '/message';
	const CAPABILITY = 'edit_posts';

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
				'callback'            => array( $this, 'handle_message' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_args_schema(),
			)
		);
	}

	/**
	 * Check user permission.
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
				esc_html__( 'You do not have permission to use the AI Chat.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle incoming message request.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_message( WP_REST_Request $request ) {
		$agent_slug = sanitize_text_field( $request->get_param( 'agent' ) );
		$message    = wp_kses_post( $request->get_param( 'message' ) );
		$conversation_id = absint( $request->get_param( 'conversation_id' ) ?? 0 );

		// Validate required fields.
		if ( empty( $agent_slug ) || empty( $message ) ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'Agent and message are required.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}

		// Get agent.
		$agent_registry = \HMA_AI_Chat\Agents\AgentRegistry::instance();
		$agent          = $agent_registry->get_agent( $agent_slug );

		if ( ! $agent ) {
			return new WP_Error(
				'invalid_agent',
				esc_html__( 'Invalid agent selected.', 'hma-ai-chat' ),
				array( 'status' => 400 )
			);
		}

		// Check agent capability.
		if ( ! current_user_can( $agent->get_required_capability() ) ) {
			return new WP_Error(
				'insufficient_capability',
				esc_html__( 'You do not have permission to use this agent.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		try {
			// Get or create conversation.
			$conversation_store = new \HMA_AI_Chat\Data\ConversationStore();
			if ( ! $conversation_id ) {
				$conversation_id = $conversation_store->create_conversation(
					get_current_user_id(),
					$agent_slug
				);
			}

			// Store user message.
			$conversation_store->save_message(
				$conversation_id,
				'user',
				$message
			);

			// Get conversation history.
			$history = $conversation_store->get_conversation( $conversation_id );

			// Build system prompt.
			$system_prompt = $agent->get_system_prompt();

			// Convert history to conversation format for wp_ai_client_prompt.
			$messages = array();
			foreach ( $history as $msg ) {
				$messages[] = array(
					'role'    => $msg['role'],
					'content' => $msg['content'],
				);
			}

			// Call Claude via direct API client.
			// Note: WP AI Client (wp_ai_client_prompt) builder API does not yet
			// support the request/response pattern needed here; use ClaudeClient.
			$client = new ClaudeClient();
			$result = $client->send( $system_prompt, $messages );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$response_text = $result['response'];
			$tokens_used   = $result['tokens_used'];

			// Store assistant message.
			$conversation_store->save_message(
				$conversation_id,
				'assistant',
				$response_text,
				$tokens_used
			);

			return rest_ensure_response(
				array(
					'success'          => true,
					'response'         => $response_text,
					'conversation_id'  => $conversation_id,
					'tokens_used'      => $tokens_used,
				)
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'processing_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get endpoint argument schema.
	 *
	 * @return array
	 */
	private function get_args_schema() {
		return array(
			'agent'           => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'message'         => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'wp_kses_post',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'conversation_id' => array(
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
