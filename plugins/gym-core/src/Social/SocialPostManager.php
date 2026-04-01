<?php
/**
 * Social post manager — AI-suggested content for Jetpack Publicize auto-sharing.
 *
 * Gandalf (hma-ai-chat) drafts social posts via the draft_social_post tool.
 * Posts are created with `pending` status so coaches can review and approve
 * before Jetpack Publicize auto-shares on publish.
 *
 * @package Gym_Core\Social
 * @since   3.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Social;

use Gym_Core\API\BaseController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Manages AI-suggested social posts and the approval-to-publish flow.
 *
 * Responsibilities:
 * - Creates pending posts that Gandalf suggests via the REST API.
 * - Provides an approval method that transitions to `publish`, triggering Publicize.
 * - Exposes a REST endpoint for the draft_social_post AI tool.
 * - Queries pending social posts for the coach approval dashboard.
 *
 * @since 3.1.0
 */
class SocialPostManager extends BaseController {

	/**
	 * Post meta key indicating this is an AI-suggested social post.
	 *
	 * @var string
	 */
	const META_SOCIAL_POST = '_gym_social_post';

	/**
	 * Post meta key storing the AI agent persona or user who suggested the post.
	 *
	 * @var string
	 */
	const META_SUGGESTED_BY = '_gym_suggested_by';

	/**
	 * Post meta key storing the user who approved publication.
	 *
	 * @var string
	 */
	const META_APPROVED_BY = '_gym_approved_by';

	/**
	 * Registers REST routes and WordPress hooks.
	 *
	 * @since 3.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		parent::register_hooks();
	}

	/**
	 * Registers REST routes for social post management.
	 *
	 * @since 3.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/social/draft',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_draft' ),
				'permission_callback' => array( $this, 'permissions_manage_announcements' ),
				'args'                => array(
					'title'    => array(
						'description'       => __( 'Social post title.', 'gym-core' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'content'  => array(
						'description'       => __( 'Social post body content (supports HTML).', 'gym-core' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'category' => array(
						'description'       => __( 'Post category slug (e.g. "general", "event", "promo").', 'gym-core' ),
						'type'              => 'string',
						'default'           => 'general',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/social/pending',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_pending' ),
				'permission_callback' => array( $this, 'permissions_manage_announcements' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/social/(?P<post_id>[\d]+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_approve' ),
				'permission_callback' => array( $this, 'permissions_manage_announcements' ),
				'args'                => array(
					'post_id' => array(
						'description'       => __( 'Post ID to approve.', 'gym-core' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Core methods
	// -------------------------------------------------------------------------

	/**
	 * Creates a draft social post with pending status for coach approval.
	 *
	 * The post is created as `pending` so Jetpack Publicize will not share it
	 * until a coach explicitly approves and publishes via approve_and_publish().
	 *
	 * @since 3.1.0
	 *
	 * @param string $title        Post title.
	 * @param string $content      Post body content.
	 * @param string $category     Category slug (default 'general').
	 * @param int    $suggested_by User ID or 0 for AI agent persona.
	 * @return int Post ID on success.
	 *
	 * @throws \RuntimeException When wp_insert_post fails.
	 */
	public function create_draft_post( string $title, string $content, string $category = 'general', int $suggested_by = 0 ): int {
		$post_data = array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( $content ),
			'post_status'  => 'pending',
			'post_type'    => 'post',
			'post_author'  => $suggested_by > 0 ? $suggested_by : get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to create social post: %s', 'gym-core' ),
					$post_id->get_error_message()
				)
			);
		}

		// Mark as AI-suggested social post.
		update_post_meta( $post_id, self::META_SOCIAL_POST, true );
		update_post_meta( $post_id, self::META_SUGGESTED_BY, $suggested_by > 0 ? $suggested_by : 'gandalf' );

		// Assign category if it exists.
		if ( ! empty( $category ) ) {
			$term = get_term_by( 'slug', $category, 'category' );

			if ( $term ) {
				wp_set_post_categories( $post_id, array( $term->term_id ) );
			}
		}

		return $post_id;
	}

	/**
	 * Approves a pending social post and publishes it.
	 *
	 * Changing the status to `publish` triggers Jetpack Publicize to auto-share
	 * to connected social media accounts.
	 *
	 * @since 3.1.0
	 *
	 * @param int $post_id     Post ID to approve.
	 * @param int $approved_by User ID of the coach approving.
	 * @return bool True on success, false on failure.
	 */
	public function approve_and_publish( int $post_id, int $approved_by ): bool {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		// Verify this is a pending social post.
		if ( 'pending' !== $post->post_status ) {
			return false;
		}

		$is_social = get_post_meta( $post_id, self::META_SOCIAL_POST, true );

		if ( ! $is_social ) {
			return false;
		}

		// Record who approved.
		update_post_meta( $post_id, self::META_APPROVED_BY, $approved_by );

		// Transition to publish — this triggers Jetpack Publicize auto-share.
		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		/**
		 * Fires after a social post is approved and published.
		 *
		 * Jetpack Publicize hooks into `transition_post_status` to handle
		 * auto-sharing, so by the time this action fires the post is live
		 * and queued for social distribution.
		 *
		 * @param int $post_id     Published post ID.
		 * @param int $approved_by User ID who approved.
		 *
		 * @since 3.1.0
		 */
		do_action( 'gym_core_social_post_published', $post_id, $approved_by );

		return true;
	}

	/**
	 * Returns all pending social posts for the approval dashboard.
	 *
	 * @since 3.1.0
	 *
	 * @return array Array of post data arrays.
	 */
	public function get_pending_posts(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'pending',
				'meta_key'       => self::META_SOCIAL_POST,
				'meta_value'     => '1',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$posts = array();

		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'content'      => $post->post_content,
				'date'         => $post->post_date,
				'suggested_by' => get_post_meta( $post->ID, self::META_SUGGESTED_BY, true ),
				'edit_url'     => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		return $posts;
	}

	// -------------------------------------------------------------------------
	// REST endpoint callbacks
	// -------------------------------------------------------------------------

	/**
	 * Handles POST /gym/v1/social/draft — creates a pending social post.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_draft( WP_REST_Request $request ) {
		$title    = $request->get_param( 'title' );
		$content  = $request->get_param( 'content' );
		$category = $request->get_param( 'category' ) ?? 'general';

		try {
			$post_id = $this->create_draft_post(
				$title,
				$content,
				$category,
				get_current_user_id()
			);
		} catch ( \RuntimeException $e ) {
			return $this->error_response( 'social_draft_failed', $e->getMessage(), 500 );
		}

		return $this->success_response(
			array(
				'post_id'  => $post_id,
				'status'   => 'pending',
				'message'  => __( 'Social post drafted and awaiting coach approval.', 'gym-core' ),
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			),
			null,
			201
		);
	}

	/**
	 * Handles GET /gym/v1/social/pending — returns pending social posts.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_pending( WP_REST_Request $request ): WP_REST_Response {
		$posts = $this->get_pending_posts();

		return $this->success_response( $posts );
	}

	/**
	 * Handles POST /gym/v1/social/{post_id}/approve — approves and publishes.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_approve( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		$result = $this->approve_and_publish( $post_id, get_current_user_id() );

		if ( ! $result ) {
			return $this->error_response(
				'social_approve_failed',
				__( 'Could not approve this post. It may not exist, may not be pending, or may not be a social post.', 'gym-core' ),
				400
			);
		}

		return $this->success_response(
			array(
				'post_id' => $post_id,
				'status'  => 'publish',
				'message' => __( 'Social post approved and published. Jetpack Publicize will handle sharing.', 'gym-core' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Permission callback requiring gym_manage_announcements capability.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function permissions_manage_announcements( WP_REST_Request $request ): bool|WP_Error {
		if ( ! current_user_can( 'gym_manage_announcements' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage social posts.', 'gym-core' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}
}
