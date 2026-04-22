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
	 * Send a message to Claude.
	 *
	 * @since 0.2.0
	 *
	 * @param string $system_prompt System prompt content.
	 * @param array  $messages      Array of {role, content} message objects.
	 * @param string $model         Optional model override.
	 * @return array{response: string, tokens_used: int}|\WP_Error
	 */
	public function send( string $system_prompt, array $messages, string $model = '' ): array|\WP_Error {
		if ( defined( 'HMA_AI_CHAT_ANTHROPIC_API_KEY' ) && '' !== HMA_AI_CHAT_ANTHROPIC_API_KEY ) {
			$api_key = (string) HMA_AI_CHAT_ANTHROPIC_API_KEY;
		} else {
			$api_key = get_option( self::API_KEY_OPTION, '' );
		}

		if ( '' === $api_key ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'Anthropic API key is not configured. Go to Gym > Settings to add it.', 'hma-ai-chat' ),
				array( 'status' => 500 )
			);
		}

		$body = array(
			'model'      => $model ?: self::DEFAULT_MODEL,
			'max_tokens' => 2048,
			'system'     => $system_prompt,
			'messages'   => $messages,
		);

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

			// Retry only transient classes (rate limit + 5xx, excluding 501/505).
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

		// Extract text from the response content blocks.
		$text = '';
		foreach ( $data['content'] ?? array() as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= $block['text'];
			}
		}

		$input_tokens  = $data['usage']['input_tokens'] ?? 0;
		$output_tokens = $data['usage']['output_tokens'] ?? 0;

		return array(
			'response'    => $text,
			'tokens_used' => $input_tokens + $output_tokens,
		);
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
