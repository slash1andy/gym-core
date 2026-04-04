<?php
/**
 * Gamification REST controller.
 *
 * @package Gym_Core\API
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Gamification\BadgeDefinitions;
use Gym_Core\Gamification\BadgeEngine;
use Gym_Core\Gamification\StreakTracker;

/**
 * Handles REST endpoints for badges, achievements, and streaks.
 *
 * Routes:
 *   GET /gym/v1/badges                  List all badge definitions
 *   GET /gym/v1/members/{id}/badges     Badges earned by a member
 *   GET /gym/v1/members/{id}/streak     Current and longest streak
 */
class GamificationController extends BaseController {

	/**
	 * @var BadgeEngine
	 */
	private BadgeEngine $badges;

	/**
	 * @var StreakTracker
	 */
	private StreakTracker $streaks;

	/**
	 * Constructor.
	 *
	 * @param BadgeEngine   $badges  Badge engine.
	 * @param StreakTracker  $streaks Streak tracker.
	 */
	public function __construct( BadgeEngine $badges, StreakTracker $streaks ) {
		parent::__construct();
		$this->badges  = $badges;
		$this->streaks = $streaks;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/badges',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_badge_definitions' ),
				'permission_callback' => array( $this, 'permissions_public' ),
				'args'                => array(
					'category' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/members/(?P<id>[\d]+)/badges',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_member_badges' ),
				'permission_callback' => array( $this, 'permissions_view_badges' ),
				'args'                => array_merge(
					$this->pagination_route_args(),
					array(
						'id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					)
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/members/(?P<id>[\d]+)/streak',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_member_streak' ),
				'permission_callback' => array( $this, 'permissions_view_badges' ),
				'args'                => array(
					'id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	/**
	 * Permission: view own badges/streak or gym_view_achievements.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_view_badges( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->permissions_view_own_or_cap( $request, 'id', 'gym_view_achievements' );
	}

	/**
	 * Returns all badge definitions.
	 *
	 * If the user is logged in, each badge includes earned status.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_badge_definitions( \WP_REST_Request $request ): \WP_REST_Response {
		$category    = $request->get_param( 'category' );
		$definitions = BadgeDefinitions::get_all();
		$user_id     = get_current_user_id();

		$result = array();

		// Fetch all earned badges once to avoid N+1 queries.
		$earned_map = array();
		if ( $user_id > 0 ) {
			$user_badges = $this->badges->get_user_badges( $user_id );
			foreach ( $user_badges as $ub ) {
				$earned_map[ $ub->badge_slug ] = $ub->earned_at;
			}
		}

		foreach ( $definitions as $slug => $badge ) {
			if ( $category && $badge['category'] !== $category ) {
				continue;
			}

			$item = array(
				'slug'             => $slug,
				'name'             => $badge['name'],
				'description'      => $badge['description'],
				'icon'             => $badge['icon'],
				'category'         => $badge['category'],
				'criteria_summary' => $badge['criteria_summary'],
			);

			// Include earned state if user is logged in.
			if ( $user_id > 0 ) {
				$item['earned']    = isset( $earned_map[ $slug ] );
				$item['earned_at'] = $earned_map[ $slug ] ?? null;
			}

			$result[] = $item;
		}

		return $this->success_response( $result );
	}

	/**
	 * Returns badges earned by a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_member_badges( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id     = $request->get_param( 'id' );
		$user_badges = $this->badges->get_user_badges( $user_id );
		$all_defs    = BadgeDefinitions::get_all();

		$formatted = array();

		foreach ( $user_badges as $earned ) {
			$def = $all_defs[ $earned->badge_slug ] ?? null;

			$formatted[] = array(
				'badge'     => $def ? array(
					'slug'        => $earned->badge_slug,
					'name'        => $def['name'],
					'description' => $def['description'],
					'icon'        => $def['icon'],
				) : array( 'slug' => $earned->badge_slug, 'name' => $earned->badge_slug, 'description' => '', 'icon' => '' ),
				'earned_at' => $earned->earned_at,
				'metadata'  => $earned->metadata ? json_decode( $earned->metadata, true ) : null,
			);
		}

		return $this->success_response(
			$formatted,
			array(
				'total_badges_earned'    => count( $formatted ),
				'total_badges_available' => count( $all_defs ),
			)
		);
	}

	/**
	 * Returns streak data for a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_member_streak( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = $request->get_param( 'id' );
		$streak  = $this->streaks->get_streak( $user_id );

		return $this->success_response( $streak );
	}
}
