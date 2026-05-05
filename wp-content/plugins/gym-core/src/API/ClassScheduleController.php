<?php
/**
 * Class schedule REST controller.
 *
 * @package Gym_Core\API
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Schedule\ClassPostType;
use Gym_Core\Schedule\ScheduleCachePrimer;

/**
 * Handles REST endpoints for class schedule data.
 *
 * Routes:
 *   GET /gym/v1/classes          List classes (filterable by location, program, instructor)
 *   GET /gym/v1/classes/{id}     Single class detail
 *   GET /gym/v1/schedule         Weekly schedule view (classes expanded by day)
 */
class ClassScheduleController extends BaseController {

	/**
	 * Defensive upper bound for unbounded WP_Query result sets in this controller.
	 *
	 * @var int
	 */
	private const MAX_QUERY_RESULTS = 500;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'classes';

	/**
	 * Registers REST routes.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_classes' ),
				'permission_callback' => array( $this, 'permissions_public' ),
				'args'                => array_merge(
					$this->pagination_route_args(),
					array(
						'location'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'program'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'instructor' => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					)
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_class' ),
				'permission_callback' => array( $this, 'permissions_public' ),
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

		register_rest_route(
			$this->namespace,
			'/schedule',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_schedule' ),
				'permission_callback' => array( $this, 'permissions_public' ),
				'args'                => array(
					'location' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'week_of'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'program'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/program',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'assign_program' ),
				'permission_callback' => $this->with_nonce( array( $this, 'permissions_assign_program' ) ),
				'args'                => array(
					'id'      => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'program' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for program assignment.
	 *
	 * Class config is an admin-level concern — gym_promote_student covers
	 * coaches who manage curriculum, manage_woocommerce covers owners.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_assign_program( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'gym_promote_student' ) ) {
			return true;
		}
		return $this->error_response( 'rest_forbidden', __( 'You do not have permission to assign class programs.', 'gym-core' ), 403 );
	}

	/**
	 * Replace a class's program assignment with the supplied program slug.
	 *
	 * Validates that the class exists, the program slug corresponds to a real
	 * term in the gym_program taxonomy, and only then mutates. Returns the
	 * updated class payload so the caller can echo back what changed.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function assign_program( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$slug    = (string) $request->get_param( 'program' );

		$post = get_post( $post_id );
		if ( ! $post || ClassPostType::POST_TYPE !== $post->post_type ) {
			return $this->error_response( 'class_not_found', __( 'Class not found.', 'gym-core' ), 404 );
		}

		$term = get_term_by( 'slug', $slug, ClassPostType::PROGRAM_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return $this->error_response(
				'program_not_found',
				sprintf(
					/* translators: %s: program slug */
					__( 'Program "%s" does not exist. Create the program term first or use an existing slug.', 'gym-core' ),
					$slug
				),
				400
			);
		}

		$result = wp_set_object_terms( $post_id, array( (int) $term->term_id ), ClassPostType::PROGRAM_TAXONOMY, false );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( 'program_assign_failed', $result->get_error_message(), 500 );
		}

		clean_post_cache( $post_id );

		return $this->success_response( $this->format_class( $post ) );
	}

	/**
	 * Returns a paginated list of classes.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_classes( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$args = array(
			'post_type'      => ClassPostType::POST_TYPE,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => 'publish',
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		$location = $request->get_param( 'location' );
		if ( $location ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'gym_location',
					'field'    => 'slug',
					'terms'    => $location,
				),
			);
		}

		$program = $request->get_param( 'program' );
		if ( $program ) {
			$args['tax_query']   = $args['tax_query'] ?? array();
			$args['tax_query'][] = array(
				'taxonomy' => ClassPostType::PROGRAM_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $program,
			);
		}

		$instructor = $request->get_param( 'instructor' );
		if ( $instructor ) {
			$args['meta_query'][] = array(
				'key'   => '_gym_class_instructor',
				'value' => $instructor,
				'type'  => 'NUMERIC',
			);
		}

		// Only show active classes.
		$args['meta_query'][] = array(
			'key'     => '_gym_class_status',
			'value'   => 'active',
			'compare' => '=',
		);

		$query = new \WP_Query( $args );
		ScheduleCachePrimer::prime( $query );
		$items = array_map( array( $this, 'format_class' ), $query->posts );

		return $this->success_response(
			$items,
			$this->pagination_meta(
				$query->found_posts,
				$query->max_num_pages,
				$page,
				$per_page
			)
		);
	}

	/**
	 * Returns a single class detail.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_class( \WP_REST_Request $request ) {
		$post = get_post( $request->get_param( 'id' ) );

		if ( ! $post || ClassPostType::POST_TYPE !== $post->post_type ) {
			return $this->error_response( 'class_not_found', __( 'Class not found.', 'gym-core' ), 404 );
		}

		return $this->success_response( $this->format_class( $post ) );
	}

	/**
	 * Returns a weekly schedule view.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_schedule( \WP_REST_Request $request ): \WP_REST_Response {
		$location = $request->get_param( 'location' );
		$week_of  = $request->get_param( 'week_of' );
		$program  = $request->get_param( 'program' );

		$monday = '' !== $week_of
			? date( 'Y-m-d', strtotime( 'monday this week', strtotime( $week_of ) ) )
			: date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );

		$args = array(
			'post_type'      => ClassPostType::POST_TYPE,
			'posts_per_page' => self::MAX_QUERY_RESULTS,
			'post_status'    => 'publish',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'gym_location',
					'field'    => 'slug',
					'terms'    => $location,
				),
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gym_class_status',
					'value' => 'active',
				),
			),
		);

		if ( $program ) {
			$args['tax_query'][] = array(
				'taxonomy' => ClassPostType::PROGRAM_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $program,
			);
		}

		$query = new \WP_Query( $args );
		ScheduleCachePrimer::prime( $query );

		$days     = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$schedule = array();

		foreach ( $days as $i => $day_name ) {
			$date    = gmdate( 'Y-m-d', (int) strtotime( $monday . " +{$i} days" ) );
			$classes = array();

			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				$class_day = get_post_meta( $post->ID, '_gym_class_day_of_week', true );
				if ( $class_day !== $day_name ) {
					continue;
				}

				$capacity  = (int) get_post_meta( $post->ID, '_gym_class_capacity', true ) ?: 30;
				$classes[] = array(
					'id'         => $post->ID,
					'name'       => $post->post_title,
					'program'    => $this->get_class_program( $post->ID ),
					'instructor' => $this->get_instructor_name( $post->ID ),
					'start_time' => get_post_meta( $post->ID, '_gym_class_start_time', true ),
					'end_time'   => get_post_meta( $post->ID, '_gym_class_end_time', true ),
					'location'   => $location,
					'capacity'   => $capacity,
				);
			}

			// Sort by start time.
			usort( $classes, static fn( $a, $b ) => strcmp( $a['start_time'], $b['start_time'] ) );

			$schedule[] = array(
				'date'     => $date,
				'day_name' => ucfirst( $day_name ),
				'classes'  => $classes,
			);
		}

		return $this->success_response( $schedule );
	}

	/**
	 * Formats a class post into API response shape.
	 *
	 * @param \WP_Post $post Class post.
	 * @return array<string, mixed>
	 */
	/**
	 * Public accessor for format_class() used by non-REST callers (e.g. KioskEndpoint).
	 *
	 * @since 3.3.0
	 *
	 * @param \WP_Post $post The class post.
	 * @return array<string, mixed>
	 */
	public function format_class_public( \WP_Post $post ): array {
		return $this->format_class( $post );
	}

	private function format_class( \WP_Post $post ): array {
		return array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'description' => wp_kses_post( $post->post_content ),
			'program'     => $this->get_class_program( $post->ID ),
			'instructor'  => $this->get_instructor_data( $post->ID ),
			'day_of_week' => get_post_meta( $post->ID, '_gym_class_day_of_week', true ),
			'start_time'  => get_post_meta( $post->ID, '_gym_class_start_time', true ),
			'end_time'    => get_post_meta( $post->ID, '_gym_class_end_time', true ),
			'capacity'    => (int) get_post_meta( $post->ID, '_gym_class_capacity', true ) ?: 30,
			'recurrence'  => get_post_meta( $post->ID, '_gym_class_recurrence', true ) ?: 'weekly',
			'status'      => get_post_meta( $post->ID, '_gym_class_status', true ) ?: 'active',
			'location'    => $this->get_class_location( $post->ID ),
		);
	}

	/**
	 * Gets the program name for a class.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	private function get_class_program( int $post_id ): ?string {
		$terms = get_the_terms( $post_id, ClassPostType::PROGRAM_TAXONOMY );
		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : null;
	}

	/**
	 * Gets the location slug for a class.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	private function get_class_location( int $post_id ): ?string {
		$terms = get_the_terms( $post_id, 'gym_location' );
		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : null;
	}

	/**
	 * Gets instructor user data for a class.
	 *
	 * @param int $post_id Post ID.
	 * @return array{id: int, name: string}|null
	 */
	private function get_instructor_data( int $post_id ): ?array {
		$user_id = (int) get_post_meta( $post_id, '_gym_class_instructor', true );
		if ( ! $user_id ) {
			return null;
		}
		$user = get_userdata( $user_id );
		return $user ? array(
			'id'   => $user_id,
			'name' => $user->display_name,
		) : null;
	}

	/**
	 * Gets instructor name for a class (short form for schedule view).
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	private function get_instructor_name( int $post_id ): ?string {
		$data = $this->get_instructor_data( $post_id );
		return $data ? $data['name'] : null;
	}
}
