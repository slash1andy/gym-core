<?php
declare(strict_types=1);
/**
 * Direct Claude API client.
 *
 * Fallback for when WordPress 7.0's wp_ai_client_prompt() is not available.
 * Calls the Anthropic Messages API directly via wp_remote_post().
 *
 * @package HMA_AI_Chat
 * @since   0.2.0
 */

namespace HMA_AI_Chat\API;

use HMA_AI_Chat\Tools\ToolExecutor;

/**
 * Sends messages to the Claude API and returns structured responses.
 *
 * @since 0.2.0
 */
class ClaudeClient {

	/**
	 * Anthropic Messages API endpoint.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.anthropic.com/v1/messages';

	/**
	 * API version header value.
	 *
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * Option key for the Anthropic API key.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'hma_ai_chat_anthropic_api_key';

	/**
	 * Default model to use.
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'claude-sonnet-4-6';

	/**
	 * Maximum number of tool-use round trips before bailing.
	 *
	 * Prevents runaway tool loops if the model keeps requesting tools.
	 *
	 * @var int
	 */
	private const MAX_TOOL_TURNS = 8;

	/**
	 * Send a message to Claude.
	 *
	 * When $tools is non-empty AND $executor is provided, runs the
	 * tool-use loop: any tool_use blocks Claude returns are dispatched
	 * to the executor, results are sent back to Claude, and the loop
	 * continues until Claude returns a final text answer (or the
	 * MAX_TOOL_TURNS budget is exhausted).
	 *
	 * @since 0.2.0
	 *
	 * @param string             $system_prompt System prompt content.
	 * @param array              $messages      Array of {role, content} message objects.
	 * @param string             $model         Optional model override.
	 * @param array              $tools         Optional tool definitions
	 *                                          (each {name, description, input_schema, ...registry meta}).
	 * @param ToolExecutor|null  $executor      Tool executor for running tool calls.
	 * @param int                $user_id       User ID for auth context inside tools.
	 * @param string             $persona       Agent persona slug for the executor.
	 * @return array{response: string, tokens_used: int, tool_calls: array}|\WP_Error
	 */
	public function send(
		string $system_prompt,
		array $messages,
		string $model = '',
		array $tools = array(),
		?ToolExecutor $executor = null,
		int $user_id = 0,
		string $persona = ''
	): array|\WP_Error {
		$api_key = self::resolve_api_key();

		if ( '' === $api_key ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'Anthropic API key is not configured. Set it in Settings > Connectors (WordPress 7.0 AI Connectors), in Gym > Settings, or via the HMA_AI_CHAT_ANTHROPIC_API_KEY constant.', 'hma-ai-chat' ),
				array( 'status' => 500 )
			);
		}

		// Anthropic expects {role, content} where content is either a string
		// or an array of content blocks. Our caller passes plain strings, so
		// normalise to the structured shape so we can append tool_use blocks.
		$claude_messages = array();
		foreach ( $messages as $msg ) {
			$claude_messages[] = array(
				'role'    => $msg['role'],
				'content' => is_string( $msg['content'] )
					? array( array( 'type' => 'text', 'text' => $msg['content'] ) )
					: $msg['content'],
			);
		}

		// Strip registry-internal fields from tool definitions before sending
		// to Anthropic. The API accepts only {name, description, input_schema}.
		$api_tools = array();
		foreach ( $tools as $tool ) {
			if ( empty( $tool['name'] ) ) {
				continue;
			}
			$api_tools[] = array(
				'name'         => $tool['name'],
				'description'  => $tool['description'] ?? '',
				'input_schema' => $tool['input_schema'] ?? array( 'type' => 'object', 'properties' => new \stdClass() ),
			);
		}

		$total_input_tokens  = 0;
		$total_output_tokens = 0;
		$tool_calls_audit    = array();
		$final_text          = '';

		for ( $turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++ ) {
			$body = array(
				'model'      => $model ?: self::DEFAULT_MODEL,
				'max_tokens' => 2048,
				'system'     => $system_prompt,
				'messages'   => $claude_messages,
			);

			if ( ! empty( $api_tools ) ) {
				$body['tools'] = $api_tools;
			}

			$result = $this->post_with_retry( $api_key, $body );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$data                 = $result['data'];
			$total_input_tokens  += (int) ( $data['usage']['input_tokens'] ?? 0 );
			$total_output_tokens += (int) ( $data['usage']['output_tokens'] ?? 0 );
			$content_blocks       = $data['content'] ?? array();
			$stop_reason          = $data['stop_reason'] ?? '';

			// Collect text from any text blocks in this assistant turn so the
			// final visible answer can include narration even when tools ran.
			$text_in_turn = '';
			foreach ( $content_blocks as $block ) {
				if ( 'text' === ( $block['type'] ?? '' ) ) {
					$text_in_turn .= $block['text'] ?? '';
				}
			}

			// If Claude is not asking for tools, this turn's text is the final answer.
			if ( 'tool_use' !== $stop_reason || null === $executor || empty( $api_tools ) ) {
				$final_text = '' !== $text_in_turn ? $text_in_turn : $final_text;
				break;
			}

			// Append the assistant turn (verbatim — Anthropic requires it before
			// we send tool_result blocks) and execute every tool_use block.
			$claude_messages[] = array(
				'role'    => 'assistant',
				'content' => $content_blocks,
			);

			$tool_result_blocks = array();
			foreach ( $content_blocks as $block ) {
				if ( 'tool_use' !== ( $block['type'] ?? '' ) ) {
					continue;
				}

				$tool_name  = (string) ( $block['name'] ?? '' );
				$tool_input = is_array( $block['input'] ?? null ) ? $block['input'] : array();
				$tool_id    = (string) ( $block['id'] ?? '' );

				$exec_result = $executor->execute( $tool_name, $tool_input, $user_id, $persona );

				$is_error      = empty( $exec_result['success'] );
				$result_payload = $is_error
					? array( 'error' => $exec_result['error'] ?? 'Tool execution failed.' )
					: ( $exec_result['data'] ?? null );

				$tool_result_blocks[] = array(
					'type'        => 'tool_result',
					'tool_use_id' => $tool_id,
					'content'     => wp_json_encode( $result_payload ),
					'is_error'    => $is_error,
				);

				$tool_calls_audit[] = array(
					'name'     => $tool_name,
					'input'    => $tool_input,
					'output'   => $result_payload,
					'is_error' => $is_error,
					'pending'  => ! empty( $exec_result['pending'] ),
				);
			}

			// User turn carrying the tool results back to Claude.
			$claude_messages[] = array(
				'role'    => 'user',
				'content' => $tool_result_blocks,
			);

			// Carry text-in-turn forward only if no further turns produce one.
			if ( '' === $final_text && '' !== $text_in_turn ) {
				$final_text = $text_in_turn;
			}
		}

		return array(
			'response'    => $final_text,
			'tokens_used' => $total_input_tokens + $total_output_tokens,
			'tool_calls'  => $tool_calls_audit,
		);
	}

	/**
	 * Resolve the Anthropic API key from any configured source.
	 *
	 * Sources, in order of precedence:
	 *   1. HMA_AI_CHAT_ANTHROPIC_API_KEY constant (plugin-specific override).
	 *   2. WordPress 7.0 Connectors API option `connectors_ai_anthropic_api_key`
	 *      (the canonical site-wide AI provider key — what most users will
	 *      configure via Settings > Connectors).
	 *   3. Legacy plugin option (`hma_ai_chat_anthropic_api_key`) for sites
	 *      configured before WP 7.0 / for non-Anthropic-provider installs.
	 *   4. ANTHROPIC_API_KEY environment variable (matches the AI Provider
	 *      for Anthropic plugin's documented fallback).
	 *
	 * Public so MessageEndpoint can reuse the same lookup for its
	 * has_anthropic_api_key() guard.
	 *
	 * @return string The configured API key, or '' when none is set.
	 */
	public static function resolve_api_key(): string {
		if ( defined( 'HMA_AI_CHAT_ANTHROPIC_API_KEY' ) && '' !== HMA_AI_CHAT_ANTHROPIC_API_KEY ) {
			return (string) HMA_AI_CHAT_ANTHROPIC_API_KEY;
		}

		$connectors_key = (string) get_option( 'connectors_ai_anthropic_api_key', '' );
		if ( '' !== $connectors_key ) {
			return $connectors_key;
		}

		$plugin_key = (string) get_option( self::API_KEY_OPTION, '' );
		if ( '' !== $plugin_key ) {
			return $plugin_key;
		}

		$env_key = getenv( 'ANTHROPIC_API_KEY' );
		if ( is_string( $env_key ) && '' !== $env_key ) {
			return $env_key;
		}

		return '';
	}

	/**
	 * Issue a single POST to Anthropic with retry/backoff.
	 *
	 * @param string $api_key API key.
	 * @param array  $body    Decoded request body.
	 * @return array{data: array}|\WP_Error
	 */
	private function post_with_retry( string $api_key, array $body ) {
		$encoded_body = wp_json_encode( $body );
		$args         = array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => self::API_VERSION,
			),
			'body'    => $encoded_body,
		);

		$max_attempts = 3;
		$response     = null;
		$status       = 0;
		$raw          = '';
		$data         = array();

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$response = wp_remote_post( self::API_URL, $args );

			if ( is_wp_error( $response ) ) {
				if ( $attempt < $max_attempts ) {
					usleep( $this->backoff_microseconds( $attempt, null ) );
					continue;
				}
				return $response;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$raw    = wp_remote_retrieve_body( $response );
			$data   = json_decode( $raw, true ) ?: array();

			if ( $attempt < $max_attempts && $this->is_retryable_status( $status ) ) {
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				usleep( $this->backoff_microseconds( $attempt, $retry_after ) );
				continue;
			}

			break;
		}

		if ( $status < 200 || $status >= 300 ) {
			$error_msg = $data['error']['message'] ?? "HTTP {$status}";
			return new \WP_Error(
				'claude_api_error',
				sprintf( __( 'Claude API error: %s', 'hma-ai-chat' ), $error_msg ),
				array( 'status' => 502 )
			);
		}

		return array( 'data' => $data );
	}

	/**
	 * Whether an HTTP status from Anthropic should trigger a retry.
	 *
	 * @since 0.4.1
	 */
	private function is_retryable_status( int $status ): bool {
		if ( 429 === $status ) {
			return true;
		}
		// Retry transient 5xx; skip 501/505 (server doesn't support; will not improve).
		return $status >= 500 && $status < 600 && 501 !== $status && 505 !== $status;
	}

	/**
	 * Compute backoff in microseconds.
	 *
	 * Honors a Retry-After header when present (capped at 30s); otherwise uses
	 * exponential backoff with jitter so simultaneous workers don't synchronize.
	 *
	 * @since 0.4.1
	 *
	 * @param int               $attempt     1-based attempt number that just failed.
	 * @param string|array|null $retry_after Raw Retry-After header value, if any.
	 */
	private function backoff_microseconds( int $attempt, $retry_after ): int {
		if ( is_array( $retry_after ) ) {
			$retry_after = reset( $retry_after );
		}

		if ( is_string( $retry_after ) && '' !== $retry_after && ctype_digit( $retry_after ) ) {
			$seconds = min( 30, max( 1, (int) $retry_after ) );
			return $seconds * 1_000_000;
		}

		// Exponential: 500ms, 1s, 2s base; +/- 250ms jitter.
		$base   = 500_000 * ( 2 ** ( $attempt - 1 ) );
		$jitter = random_int( -250_000, 250_000 );
		return max( 0, $base + $jitter );
	}
}
