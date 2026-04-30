<?php
/**
 * Class roster REST controller.
 *
 * Exposes forecasted class rosters based on recent attendance patterns,
 * reusing the same logic as BriefingGenerator::forecast_roster().
 *
 * @package Gym_Core\API
 * @since   2.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Data\TableManager;
use Gym_Core\Rank\RankStore;

/**
 * Class roster endpoint.
 *
 * Routes:
 *   GET /gym/v1/classes/{class_id}/roster   Forecasted roster
 */
class ClassRosterController extends BaseController {

	/**
	 * Default weeks to look back for attendance forecasting.
	 *
	 * @var int
	 */
	private const DEFAULT_FORECAST_WEEKS = 4;

	/**
	 * Attendance store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Rank store.
	 *
	 * @var RankStore
	 */
	private RankStore $ranks;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore $attendance Attendance store.
	 * @param RankStore       $ranks      Rank store.
	 */
	public function __construct( AttendanceStore $attendance, RankStore $ranks ) {
		parent::__construct();
		$this->attendance = $attendance;
		$this->ranks      = $ranks;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/classes/(?P<class_id>[\d]+)/roster',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_roster' ),
				'permission_callback' => array( $this, 'permissions_view_roster' ),
				'args'                => array(
					'class_id' => array(
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
	// Permissions
	// -------------------------------------------------------------------------

	/**
	 * Permission: gym_view_attendance or manage_woocommerce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view_roster( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_view_attendance' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You do not have permission to view class rosters.', 'gym-core' ), 403 );
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/**
	 * Returns forecasted roster for a class.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_roster( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$class_id = (int) $request->get_param( 'class_id' );

		$class_post = get_post( $class_id );

		if ( ! $class_post || 'gym_class' !== $class_post->post_type ) {
			return $this->error_response( 'class_not_found', __( 'Class not found.', 'gym-core' ), 404 );
		}

		$program  = $this->get_class_program( $class_id );
		$capacity = (int) get_post_meta( $class_id, '_gym_class_capacity', true );
		$roster   = $this->forecast_roster( $class_id );

		// Enrich with user details.
		$enriched = $this->enrich_roster( $roster, $program );

		return $this->success_response(
			array(
				'class_id'       => $class_id,
				'class_name'     => $class_post->post_title,
				'program'        => $program,
				'capacity'       => $capacity > 0 ? $capacity : null,
				'expected_count' => count( $enriched ),
				'students'       => $enriched,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Forecasts which students are likely to attend based on recent attendance.
	 *
	 * Same logic as BriefingGenerator::forecast_roster() — students who
	 * attended at least twice in the last N weeks are considered expected.
	 *
	 * @param int $class_id Class post ID.
	 * @return array<int, array{user_id: int, attendance_count: int}>
	 */
	private function forecast_roster( int $class_id ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		$weeks = (int) get_option( 'gym_core_briefing_forecast_weeks', self::DEFAULT_FORECAST_WEEKS );
		$since = gmdate( 'Y-m-d', strtotime( "-{$weeks} weeks" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) AS attend_count
				FROM {$tables['attendance']}
				WHERE class_id = %d AND checked_in_at >= %s
				GROUP BY user_id
				HAVING attend_count >= 2
				ORDER BY attend_count DESC",
				$class_id,
				$since . ' 00:00:00'
			)
		) ?: array();

		$roster = array();
		foreach ( $results as $row ) {
			$roster[] = array(
				'user_id'          => (int) $row->user_id,
				'attendance_count' => (int) $row->attend_count,
			);
		}

		return $roster;
	}

	/**
	 * Enriches forecasted roster with user details.
	 *
	 * Batch-fetches all required data up front to avoid N+1 queries:
	 *   - cache_users() primes WP user objects and user-meta (covers is_foundations_student).
	 *   - get_ranks_for_users() fetches all rank rows in one query.
	 *   - get_last_attended_for_users() fetches the latest check-in per user in one query.
	 *
	 * @param array<int, array{user_id: int, attendance_count: int}> $roster  Forecasted roster.
	 * @param string|null                                            $program Program slug.
	 * @return array<int, array<string, mixed>>
	 */
	private function enrich_roster( array $roster, ?string $program ): array {
		if ( empty( $roster ) ) {
			return array();
		}

		$user_ids = array_map( static fn( array $r ) => $r['user_id'], $roster );

		// Prime WP user objects and user-meta cache (covers is_foundations_student calls below).
		cache_users( $user_ids );

		// Batch-fetch rank rows and last-attended timestamps — one query each.
		$rank_map          = $program ? $this->ranks->get_ranks_for_users( $user_ids, $program ) : array();
		$last_attended_map = $this->attendance->get_last_attended_for_users( $user_ids );

		$enriched = array();

		foreach ( $roster as $entry ) {
			$user_id = $entry['user_id'];
			$user    = get_userdata( $user_id );

			if ( ! $user ) {
				continue;
			}

			$rank_label     = null;
			$is_foundations = false;

			if ( $program ) {
				$rank = $rank_map[ $user_id ] ?? null;
				if ( $rank ) {
					$rank_label = $rank->belt . ( $rank->stripes > 0 ? ' (' . $rank->stripes . ' stripe' . ( $rank->stripes > 1 ? 's' : '' ) . ')' : '' );
				}

				$is_foundations = $this->is_foundations_student( $user_id, $program );
			}

			$enriched[] = array(
				'user_id'          => $user_id,
				'display_name'     => $user->display_name,
				'rank'             => $rank_label,
				'is_foundations'   => $is_foundations,
				'attendance_count' => $entry['attendance_count'],
				'last_attended'    => $last_attended_map[ $user_id ] ?? null,
			);
		}

		return $enriched;
	}

	/**
	 * Gets the program taxonomy term for a class.
	 *
	 * @param int $class_id Class post ID.
	 * @return string|null
	 */
	private function get_class_program( int $class_id ): ?string {
		$terms = wp_get_object_terms( $class_id, 'gym_program', array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0];
	}

	/**
	 * Checks if a user is a Foundations student in a program.
	 *
	 * @param int    $user_id User ID.
	 * @param string $program Program slug.
	 * @return bool
	 */
	private function is_foundations_student( int $user_id, string $program ): bool {
		$status = get_user_meta( $user_id, "gym_foundations_{$program}", true );

		return 'enrolled' === $status;
	}
}
