<?php
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
	private const DEFAULT_MODEL = 'claude-sonnet-4-5-20241022';

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
		$api_key = get_option( self::API_KEY_OPTION, '' );

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

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version'  => self::API_VERSION,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

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
}
