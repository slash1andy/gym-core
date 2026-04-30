<?php
declare(strict_types=1);
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
		$user_id   = get_current_user_id();
		$rate_key  = 'hma_ai_chat_rate_' . $user_id;
		$count     = (int) get_transient( $rate_key );
		if ( $count >= 30 ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Message rate limit exceeded. Please try again later.', 'hma-ai-chat' ), array( 'status' => 429 ) );
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$agent_slug = sanitize_text_field( $request->get_param( 'agent' ) );
		$message    = (string) $request->get_param( 'message' ); // Sanitization handled by args schema sanitize_callback above.
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

		$conversation_store = new \HMA_AI_Chat\Data\ConversationStore();
		$user_message_id    = null;

		try {
			// Get or create conversation.
			if ( ! $conversation_id ) {
				$created_id = $conversation_store->create_conversation(
					get_current_user_id(),
					$agent_slug
				);
				if ( false === $created_id ) {
					return new \WP_Error(
						'hma_ai_chat_conversation_create_failed',
						__( 'Failed to create conversation.', 'hma-ai-chat' ),
						array( 'status' => 500 )
					);
				}
				$conversation_id = $created_id;
			} else {
				$conversation = $conversation_store->get_conversation_record( $conversation_id );
				if ( $conversation && (int) $conversation['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
					return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to access this conversation.', 'hma-ai-chat' ), array( 'status' => 403 ) );
				}
			}

			// Store user message. Capture the row ID so we can roll back if the
			// Claude call fails — otherwise the orphan stays in history and
			// gets re-sent on the next request, double-billing tokens.
			$user_message_id = $conversation_store->save_message(
				$conversation_id,
				'user',
				$message
			);

			// Get conversation history.
			$history = $conversation_store->get_conversation( $conversation_id );

			// Build system prompt with real-time gym context.
			$system_prompt = $agent->get_system_prompt();

			$gym_context_provider = new \HMA_AI_Chat\Context\GymContextProvider();
			$system_prompt       .= $gym_context_provider->get_context_for_persona( $agent_slug, get_current_user_id() );

			// Call AI. Prefer ClaudeClient when an API key is configured so the
			// tool-use loop runs (wp_ai_client_prompt does not currently
			// auto-include registered abilities as Anthropic tools, so taking
			// that path leaves Gandalf without a live tool surface).
			$tool_calls = array();

			if ( $this->has_anthropic_api_key() ) {
				$messages = array();
				foreach ( $history as $msg ) {
					$messages[] = array(
						'role'    => $msg['role'],
						'content' => $msg['content'],
					);
				}

				$tool_executor = \HMA_AI_Chat\Plugin::instance()->get_tool_executor();
				$tool_registry = \HMA_AI_Chat\Tools\ToolRegistry::instance();
				$persona_tools = $tool_registry->get_tools_for_persona( $agent_slug );

				$client = new ClaudeClient();
				$result = $client->send(
					$system_prompt,
					$messages,
					'',
					$persona_tools,
					$tool_executor,
					get_current_user_id(),
					$agent_slug
				);

				if ( is_wp_error( $result ) ) {
					if ( $user_message_id ) {
						$conversation_store->delete_message( (int) $user_message_id );
					}
					return $result;
				}

				$response_text = $result['response'];
				$tokens_used   = $result['tokens_used'];
				$tool_calls    = $result['tool_calls'] ?? array();
			} elseif ( function_exists( 'wp_ai_client_prompt' ) ) {
				$response_text = $this->call_wp_ai_client( $system_prompt, $history, $message );
				$tokens_used   = 0;
			} else {
				if ( $user_message_id ) {
					$conversation_store->delete_message( (int) $user_message_id );
				}
				return new WP_Error(
					'no_ai_client',
					esc_html__( 'No AI client is configured. Set the Anthropic API key in Gym > Settings or enable the WP AI Client.', 'hma-ai-chat' ),
					array( 'status' => 500 )
				);
			}

			if ( is_wp_error( $response_text ) ) {
				if ( $user_message_id ) {
					$conversation_store->delete_message( (int) $user_message_id );
				}
				return $response_text;
			}

			// Store assistant message with the tool-call audit trail.
			$conversation_store->save_message(
				$conversation_id,
				'assistant',
				$response_text,
				$tokens_used,
				$tool_calls
			);

			return rest_ensure_response(
				array(
					'success'          => true,
					'response'         => $response_text,
					'conversation_id'  => $conversation_id,
					'tokens_used'      => $tokens_used,
					'tool_calls'       => $tool_calls,
				)
			);
		} catch ( \Exception $e ) {
			if ( $user_message_id ) {
				$conversation_store->delete_message( (int) $user_message_id );
			}
			return new WP_Error(
				'processing_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Whether an Anthropic API key is configured anywhere ClaudeClient can find it.
	 *
	 * Delegates to ClaudeClient::resolve_api_key() so this guard stays in
	 * lockstep with what the client will actually use — including the WP 7.0
	 * Connectors option (`connectors_ai_anthropic_api_key`), which is where
	 * most users configure the key via Settings > Connectors.
	 */
	private function has_anthropic_api_key(): bool {
		return '' !== ClaudeClient::resolve_api_key();
	}

	/**
	 * Call the WP AI Client with conversation history.
	 *
	 * Uses the WordPress 7.0 WP AI Client PromptBuilder API:
	 * - using_system_instruction() for system prompt
	 * - with_history() with Message DTOs for prior messages
	 * - with_text() for the current user message
	 * - generate_text() to get a plain string response
	 *
	 * @param string               $system_prompt System prompt content.
	 * @param array<array<string>> $history       Conversation history from ConversationStore.
	 * @param string               $current_message The latest user message.
	 * @return string|\WP_Error Response text or WP_Error on failure.
	 */
	private function call_wp_ai_client( string $system_prompt, array $history, string $current_message ) {
		$message_class = 'WordPress\\AiClient\\Messages\\DTO\\Message';

		$builder = wp_ai_client_prompt()
			->using_system_instruction( $system_prompt )
			->using_model_preference( 'claude-sonnet-4-6' );

		// Convert conversation history (excluding the latest user message we just
		// stored) into Message DTOs. The WP AI Client uses "model" for assistant.
		$history_messages = array();
		foreach ( $history as $msg ) {
			// Skip the latest message — it goes via with_text().
			if ( end( $history ) === $msg && 'user' === $msg['role'] ) {
				continue;
			}

			$role = 'assistant' === $msg['role'] ? 'model' : $msg['role'];

			$history_messages[] = $message_class::fromArray(
				array(
					'role'  => $role,
					'parts' => array( array( 'text' => $msg['content'] ) ),
				)
			);
		}

		if ( ! empty( $history_messages ) ) {
			$builder = $builder->with_history( ...$history_messages );
		}

		$response = $builder
			->with_text( $current_message )
			->generate_text();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (string) $response;
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
			// wp_kses_post runs before the route callback fires; no re-sanitization needed in the handler.
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
