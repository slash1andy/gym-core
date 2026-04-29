<?php
/**
 * Media REST controller — generates images via the WordPress AI Client and
 * saves them to the WP Media Library.
 *
 * Backs Gandalf's `generate_image` write tool. Invoked only after the staff
 * approves the queued action (see PendingActionStore + ActionEndpoint).
 *
 * @package Gym_Core\API
 * @since   2.5.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

/**
 * Image generation endpoint.
 *
 * Routes:
 *   POST /gym/v1/media/generate-image    Generate + save an AI image.
 */
class MediaController extends BaseController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'media';

	/**
	 * Per-user daily generation cap. Tunable via the
	 * `gym_core_image_generation_daily_limit` filter.
	 *
	 * @var int
	 */
	private const DEFAULT_DAILY_LIMIT = 10;

	/**
	 * Default request timeout for image generation (seconds).
	 *
	 * @var int
	 */
	private const DEFAULT_TIMEOUT = 90;

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate-image',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_image' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'prompt' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Natural-language description of the image to generate. Encode size or style in the prompt itself; the API has no separate fluent setters for those.', 'gym-core' ),
					),
					'style'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Optional style hint (e.g. "photorealistic", "watercolor"). Appended to the system instruction.', 'gym-core' ),
					),
					'size'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Optional size hint (e.g. "square", "1024x1024", "landscape"). Appended to the system instruction.', 'gym-core' ),
					),
				),
			)
		);
	}

	/**
	 * Generates an image via the WP AI Client and saves it to the Media Library.
	 *
	 * Returns a small payload describing the resulting attachment so the AI
	 * caller can reference the image in a follow-up action (e.g. attaching
	 * to a draft social post).
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_image( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return $this->error_response(
				'wp_ai_client_unavailable',
				__( 'WordPress AI Client is not available on this site.', 'gym-core' ),
				503
			);
		}

		// Daily per-user rate limit. Image generation is the priciest call we
		// expose; a runaway loop should not silently burn credits.
		$user_id = get_current_user_id();
		$limit   = (int) apply_filters( 'gym_core_image_generation_daily_limit', self::DEFAULT_DAILY_LIMIT, $user_id );

		if ( ! $this->check_rate_limit( 'image_gen_' . $user_id, $limit, DAY_IN_SECONDS ) ) {
			return $this->error_response(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: daily limit */
					__( 'Image generation rate limit reached (%d per day).', 'gym-core' ),
					$limit
				),
				429
			);
		}

		$prompt = (string) $request->get_param( 'prompt' );
		$style  = (string) $request->get_param( 'style' );
		$size   = (string) $request->get_param( 'size' );

		if ( '' === trim( $prompt ) ) {
			return $this->error_response( 'missing_prompt', __( 'Prompt cannot be empty.', 'gym-core' ), 400 );
		}

		$builder = $this->build_image_prompt( $prompt, $style, $size );

		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		try {
			$result = $builder->generate_image_result();
		} catch ( \Throwable $e ) {
			return $this->error_response( 'generation_failed', $e->getMessage(), 502 );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$attachment_id = $this->save_result_as_attachment( $result, $prompt );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return $this->success_response(
			array(
				'attachment_id' => $attachment_id,
				'url'           => (string) wp_get_attachment_url( $attachment_id ),
				'prompt_used'   => $prompt,
			),
			null,
			201
		);
	}

	/**
	 * Builds the image-generation prompt builder, returning an error when the
	 * configured providers can't fulfil the request.
	 *
	 * Mirrors WordPress/ai's own Generate_Image ability shape (see
	 * includes/Abilities/Image/Generate_Image.php in that plugin) so the
	 * provider-routing logic is identical.
	 *
	 * @param string $prompt User-supplied description.
	 * @param string $style  Optional style hint.
	 * @param string $size   Optional size hint.
	 * @return mixed PromptBuilder instance, or WP_Error.
	 */
	private function build_image_prompt( string $prompt, string $style, string $size ) {
		$request_options_class = '\\WordPress\\AiClient\\Providers\\Http\\DTO\\RequestOptions';
		$file_type_enum_class  = '\\WordPress\\AiClient\\Files\\Enums\\FileTypeEnum';

		if ( ! class_exists( $request_options_class ) || ! class_exists( $file_type_enum_class ) ) {
			return $this->error_response(
				'wp_ai_client_unavailable',
				__( 'WordPress AI Client classes are not loaded.', 'gym-core' ),
				503
			);
		}

		$request_options = new $request_options_class();
		if ( method_exists( $request_options, 'setTimeout' ) ) {
			$request_options->setTimeout( self::DEFAULT_TIMEOUT );
		}

		$builder = wp_ai_client_prompt( $prompt )
			->using_request_options( $request_options )
			->as_output_file_type( $file_type_enum_class::inline() );

		if ( function_exists( 'WordPress\\AI\\get_preferred_image_models' ) ) {
			$models = call_user_func( 'WordPress\\AI\\get_preferred_image_models' );
			if ( is_array( $models ) && ! empty( $models ) ) {
				$builder = $builder->using_model_preference( ...$models );
			}
		}

		$instruction = $this->compose_system_instruction( $style, $size );
		if ( '' !== $instruction ) {
			$builder = $builder->using_system_instruction( $instruction );
		}

		if ( method_exists( $builder, 'is_supported_for_image_generation' )
			&& ! $builder->is_supported_for_image_generation() ) {
			return $this->error_response(
				'unsupported_image_generation',
				__( 'No configured AI provider supports image generation.', 'gym-core' ),
				503
			);
		}

		return $builder;
	}

	/**
	 * Composes a system instruction line from the optional style + size hints.
	 *
	 * The image-gen builder doesn't expose fluent setters for these, so we
	 * fold them into the system instruction.
	 *
	 * @param string $style Style hint.
	 * @param string $size  Size hint.
	 * @return string
	 */
	private function compose_system_instruction( string $style, string $size ): string {
		$parts = array();

		if ( '' !== $style ) {
			$parts[] = sprintf( 'Render in this style: %s.', $style );
		}

		if ( '' !== $size ) {
			$parts[] = sprintf( 'Use this size or aspect ratio: %s.', $size );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Saves the result's image bytes into the Media Library.
	 *
	 * @param object $result Result object from generate_image_result().
	 * @param string $prompt Original prompt — stored as alt text so the
	 *                       attachment is searchable by content.
	 * @return int|\WP_Error Attachment ID on success.
	 */
	private function save_result_as_attachment( $result, string $prompt ) {
		if ( ! is_object( $result ) || ! method_exists( $result, 'toImageFile' ) ) {
			return $this->error_response( 'invalid_result_shape', __( 'Image generator returned an unexpected payload.', 'gym-core' ), 502 );
		}

		try {
			$image_file = $result->toImageFile();
			$base64     = method_exists( $image_file, 'getBase64Data' ) ? (string) $image_file->getBase64Data() : '';
			$mime       = method_exists( $image_file, 'getMimeType' ) ? (string) $image_file->getMimeType() : 'image/png';
		} catch ( \Throwable $e ) {
			return $this->error_response( 'image_extract_failed', $e->getMessage(), 502 );
		}

		// Some providers wrap the data in a `data:image/png;base64,...` URL.
		if ( str_starts_with( $base64, 'data:' ) ) {
			$comma = strpos( $base64, ',' );
			if ( false !== $comma ) {
				$base64 = substr( $base64, $comma + 1 );
			}
		}

		$bytes = base64_decode( $base64, true );
		if ( false === $bytes || '' === $bytes ) {
			return $this->error_response( 'invalid_image_data', __( 'Generated image data could not be decoded.', 'gym-core' ), 502 );
		}

		$extension = $this->mime_to_extension( $mime );
		$filename  = sprintf(
			'gym-ai-%s.%s',
			gmdate( 'Y-m-d-His' ) . '-' . wp_generate_uuid4(),
			$extension
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return $this->error_response( 'upload_failed', (string) $upload['error'], 500 );
		}

		$attachment    = array(
			'post_mime_type' => $mime,
			'post_title'     => wp_trim_words( $prompt, 12, '...' ),
			'post_content'   => $prompt,
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate sub-sizes + metadata so the image is usable in the editor.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Alt text = prompt makes the asset searchable + accessible.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $prompt );
		update_post_meta( $attachment_id, '_gym_ai_generated', '1' );
		update_post_meta( $attachment_id, '_gym_ai_prompt', $prompt );

		return (int) $attachment_id;
	}

	/**
	 * Maps a mime type to a safe file extension.
	 *
	 * @param string $mime Mime type from the AI provider.
	 * @return string Extension (without leading dot).
	 */
	private function mime_to_extension( string $mime ): string {
		switch ( strtolower( $mime ) ) {
			case 'image/jpeg':
			case 'image/jpg':
				return 'jpg';
			case 'image/webp':
				return 'webp';
			case 'image/gif':
				return 'gif';
			case 'image/png':
			default:
				return 'png';
		}
	}
}
