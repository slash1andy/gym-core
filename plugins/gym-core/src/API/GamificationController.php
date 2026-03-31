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
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'rest_not_logged_in', __( 'Authentication required.', 'gym-core' ), 401 );
		}

		$target_id = $request->get_param( 'id' );

		if ( (int) $target_id === get_current_user_id() ) {
			return true;
		}

		if ( current_user_can( 'gym_view_achievements' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You cannot view this member\'s achievements.', 'gym-core' ), 403 );
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
				$item['earned']    = $this->badges->has_badge( $user_id, $slug );
				$item['earned_at'] = null;

				if ( $item['earned'] ) {
					$user_badges = $this->badges->get_user_badges( $user_id );
					foreach ( $user_badges as $ub ) {
						if ( $ub->badge_slug === $slug ) {
							$item['earned_at'] = $ub->earned_at;
							break;
						}
					}
				}
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
