<?php
/**
 * Coach Briefing REST controller.
 *
 * @package Gym_Core\API
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Briefing\AnnouncementPostType;
use Gym_Core\Briefing\BriefingGenerator;

/**
 * Handles REST endpoints for coach briefings and announcements.
 *
 * Routes:
 *   GET  /gym/v1/briefings/class/{class_id}  Generate briefing for a specific class
 *   GET  /gym/v1/briefings/today              All briefings for today's classes
 *   GET  /gym/v1/announcements                List active announcements
 *   POST /gym/v1/announcements                Create an announcement
 */
class BriefingController extends BaseController {

	/**
	 * Briefing generator engine.
	 *
	 * @var BriefingGenerator
	 */
	private BriefingGenerator $generator;

	/**
	 * Constructor.
	 *
	 * @param BriefingGenerator $generator Briefing generator engine.
	 */
	public function __construct( BriefingGenerator $generator ) {
		parent::__construct();
		$this->generator = $generator;
	}

	/**
	 * Registers REST routes.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/briefings/class/(?P<class_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_class_briefing' ),
				'permission_callback' => array( $this, 'permissions_view_briefing' ),
				'args'                => array(
					'class_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/briefings/today',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_today_briefings' ),
				'permission_callback' => array( $this, 'permissions_view_briefing' ),
				'args'                => array(
					'location' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Filter by location slug.', 'gym-core' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/announcements',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_announcements' ),
					'permission_callback' => array( $this, 'permissions_view_briefing' ),
					'args'                => array(
						'location' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Filter by location slug.', 'gym-core' ),
						),
						'program'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Filter by program slug.', 'gym-core' ),
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_announcement' ),
					'permission_callback' => array( $this, 'permissions_manage_announcements' ),
					'args'                => array(
						'title'           => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'         => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'wp_kses_post',
						),
						'type'            => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'global',
							'sanitize_callback' => 'sanitize_text_field',
							'enum'              => array( 'global', 'location', 'program' ),
						),
						'target_location' => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'target_program'  => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'start_date'      => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Start date in Y-m-d format.', 'gym-core' ),
						),
						'end_date'        => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'End date in Y-m-d format.', 'gym-core' ),
						),
						'pinned'          => array(
							'type'              => 'boolean',
							'required'          => false,
							'default'           => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission: gym_view_briefing or manage_woocommerce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view_briefing( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_view_briefing' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response(
			'rest_forbidden',
			__( 'You do not have permission to view briefings.', 'gym-core' ),
			403
		);
	}

	/**
	 * Permission: gym_manage_announcements or manage_woocommerce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_manage_announcements( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_manage_announcements' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response(
			'rest_forbidden',
			__( 'You do not have permission to manage announcements.', 'gym-core' ),
			403
		);
	}

	/**
	 * Generates a briefing for a specific class.
	 *
	 * @since 2.1.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_class_briefing( \WP_REST_Request $request ) {
		$class_id = (int) $request->get_param( 'class_id' );
		$briefing = $this->generator->generate( $class_id );

		if ( is_wp_error( $briefing ) ) {
			return $briefing;
		}

		return $this->success_response( $briefing );
	}

	/**
	 * Returns briefings for all of today's classes.
	 *
	 * @since 2.1.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_today_briefings( \WP_REST_Request $request ): \WP_REST_Response {
		$location  = $request->get_param( 'location' ) ?: '';
		$class_ids = $this->generator->get_todays_classes( $location );

		$briefings = array();

		foreach ( $class_ids as $class_id ) {
			$briefing = $this->generator->generate( $class_id );

			if ( ! is_wp_error( $briefing ) ) {
				$briefings[] = $briefing;
			}
		}

		// Sort by class start time.
		usort(
			$briefings,
			static function ( array $a, array $b ): int {
				return strcmp( $a['class']['start_time'] ?? '', $b['class']['start_time'] ?? '' );
			}
		);

		return $this->success_response(
			$briefings,
			array( 'total' => count( $briefings ) )
		);
	}

	/**
	 * Returns active announcements, optionally filtered by location and program.
	 *
	 * @since 2.1.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_announcements( \WP_REST_Request $request ): \WP_REST_Response {
		$location = $request->get_param( 'location' ) ?: '';
		$program  = $request->get_param( 'program' ) ?: '';

		$announcements = AnnouncementPostType::get_active_announcements( $location, $program );

		return $this->success_response(
			$announcements,
			array( 'total' => count( $announcements ) )
		);
	}

	/**
	 * Creates a new announcement.
	 *
	 * @since 2.1.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_announcement( \WP_REST_Request $request ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => AnnouncementPostType::POST_TYPE,
				'post_title'   => $request->get_param( 'title' ),
				'post_content' => $request->get_param( 'content' ),
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $this->error_response(
				'announcement_create_failed',
				$post_id->get_error_message(),
				500
			);
		}

		// Set meta fields.
		$meta_map = array(
			'type'            => '_gym_announcement_type',
			'target_location' => '_gym_announcement_target_location',
			'target_program'  => '_gym_announcement_target_program',
			'start_date'      => '_gym_announcement_start_date',
			'end_date'        => '_gym_announcement_end_date',
		);

		foreach ( $meta_map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		$pinned = $request->get_param( 'pinned' );
		update_post_meta( $post_id, '_gym_announcement_pinned', $pinned ? 'yes' : 'no' );

		$post   = get_post( $post_id );
		$author = get_userdata( (int) $post->post_author );

		return $this->success_response(
			array(
				'id'              => $post_id,
				'title'           => $post->post_title,
				'content'         => $post->post_content,
				'type'            => $request->get_param( 'type' ),
				'target_location' => $request->get_param( 'target_location' ),
				'target_program'  => $request->get_param( 'target_program' ),
				'start_date'      => $request->get_param( 'start_date' ),
				'end_date'        => $request->get_param( 'end_date' ),
				'pinned'          => (bool) $pinned,
				'author'          => $author ? $author->display_name : '',
			),
			null,
			201
		);
	}
}
