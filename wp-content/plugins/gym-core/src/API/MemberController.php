<?php
/**
 * Member dashboard REST controller.
 *
 * Provides a single aggregated endpoint for the member portal, returning
 * all data needed to render the member dashboard in one request.
 *
 * @package Gym_Core\API
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Rank\RankStore;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Gamification\StreakTracker;
use Gym_Core\Gamification\BadgeEngine;
use Gym_Core\Schedule\ClassPostType;

/**
 * Handles the aggregated member dashboard endpoint.
 *
 * Routes:
 *   GET /gym/v1/members/me/dashboard  Aggregated member dashboard data
 */
class MemberController extends BaseController {

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
	protected $rest_base = 'members';

	/**
	 * Per-request memo of WC_Payment_Tokens lookups, keyed by user ID.
	 *
	 * @var array<int, array<int, \WC_Payment_Token>>
	 */
	private array $payment_tokens_cache = array();

	/**
	 * Rank data store.
	 *
	 * @var RankStore
	 */
	private RankStore $ranks;

	/**
	 * Attendance data store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Foundations clearance gate.
	 *
	 * @var FoundationsClearance
	 */
	private FoundationsClearance $foundations;

	/**
	 * Streak tracker (nullable — gamification may be disabled).
	 *
	 * @var StreakTracker|null
	 */
	private ?StreakTracker $streaks;

	/**
	 * Badge engine (nullable — gamification may be disabled).
	 *
	 * @var BadgeEngine|null
	 */
	private ?BadgeEngine $badges;

	/**
	 * Constructor.
	 *
	 * @param RankStore            $ranks       Rank data store.
	 * @param AttendanceStore      $attendance  Attendance data store.
	 * @param FoundationsClearance $foundations Foundations clearance gate.
	 * @param StreakTracker|null   $streaks     Streak tracker (null if gamification disabled).
	 * @param BadgeEngine|null     $badges      Badge engine (null if gamification disabled).
	 */
	public function __construct(
		RankStore $ranks,
		AttendanceStore $attendance,
		FoundationsClearance $foundations,
		?StreakTracker $streaks = null,
		?BadgeEngine $badges = null
	) {
		parent::__construct();
		$this->ranks       = $ranks;
		$this->attendance  = $attendance;
		$this->foundations = $foundations;
		$this->streaks     = $streaks;
		$this->badges      = $badges;
	}

	/**
	 * Registers REST routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me/dashboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'permissions_authenticated' ),
			)
		);
	}

	/**
	 * Returns the aggregated member dashboard payload.
	 *
	 * Each sub-section is wrapped in a try/catch so a single failing module
	 * never takes down the entire dashboard response.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_dashboard( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return $this->error_response( 'rest_not_logged_in', __( 'Authentication required.', 'gym-core' ), 401 );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return $this->error_response( 'invalid_user', __( 'Member not found.', 'gym-core' ), 404 );
		}

		$data = array(
			'member'           => $this->build_member_section( $user ),
			'memberships'      => $this->build_memberships_section( $user_id ),
			'billing'          => $this->build_billing_section( $user_id ),
			'upcoming_classes' => $this->build_upcoming_classes_section( $user ),
			'rank'             => $this->build_rank_section( $user_id ),
			'foundations'      => $this->build_foundations_section( $user_id ),
			'gamification'     => $this->build_gamification_section( $user_id ),
			'quick_links'      => $this->build_quick_links(),
		);

		return $this->success_response( $data );
	}

	// -------------------------------------------------------------------------
	// Section builders — each wrapped in try/catch for resilience.
	// -------------------------------------------------------------------------

	/**
	 * Builds the member profile section.
	 *
	 * @param \WP_User $user WordPress user object.
	 * @return array<string, mixed>
	 */
	private function build_member_section( \WP_User $user ): array {
		$location_slug = get_user_meta( $user->ID, 'gym_location', true ) ?: '';
		$location      = null;

		if ( '' !== $location_slug ) {
			$term = get_term_by( 'slug', $location_slug, 'gym_location' );
			if ( $term && ! is_wp_error( $term ) ) {
				$location = array(
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
		}

		return array(
			'id'           => $user->ID,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'location'     => $location,
		);
	}

	/**
	 * Builds the memberships section from WC Subscriptions or WC Memberships.
	 *
	 * Gracefully returns an empty array if neither plugin is active.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_memberships_section( int $user_id ): array {
		try {
			// Prefer WC Subscriptions (wcs_get_users_subscriptions).
			if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
				return $this->get_memberships_from_subscriptions( $user_id );
			}

			// Fallback to WC Memberships (wc_memberships_get_user_active_memberships).
			if ( function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
				return $this->get_memberships_from_wc_memberships( $user_id );
			}
		} catch ( \Throwable $e ) {
			// Log but do not break the dashboard.
			wc_get_logger()->warning(
				'MemberController: memberships error — ' . $e->getMessage(),
				array( 'source' => 'gym-core' )
			);
		}

		return array();
	}

	/**
	 * Extracts membership data from WooCommerce Subscriptions.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_memberships_from_subscriptions( int $user_id ): array {
		$subscriptions = wcs_get_users_subscriptions( $user_id );
		$items         = array();

		foreach ( $subscriptions as $subscription ) {
			$status = $subscription->get_status();

			// Include active, on-hold, and pending-cancel subscriptions.
			if ( ! in_array( $status, array( 'active', 'on-hold', 'pending-cancel' ), true ) ) {
				continue;
			}

			$items[] = array(
				'plan_name'  => $this->get_subscription_plan_name( $subscription ),
				'status'     => $status,
				'start_date' => $subscription->get_date( 'start' ) ? gmdate( 'Y-m-d', $subscription->get_date( 'start' )->getTimestamp() ) : null,
				'end_date'   => $subscription->get_date( 'end' ) ? gmdate( 'Y-m-d', $subscription->get_date( 'end' )->getTimestamp() ) : null,
			);
		}

		return $items;
	}

	/**
	 * Derives a human-readable plan name from a subscription's line items.
	 *
	 * @param \WC_Subscription $subscription WC Subscription object.
	 * @return string
	 */
	private function get_subscription_plan_name( $subscription ): string {
		$items = $subscription->get_items();

		if ( ! empty( $items ) ) {
			$first = reset( $items );
			return $first->get_name();
		}

		return __( 'Membership', 'gym-core' );
	}

	/**
	 * Extracts membership data from WooCommerce Memberships.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_memberships_from_wc_memberships( int $user_id ): array {
		$memberships = wc_memberships_get_user_active_memberships( $user_id );
		$items       = array();

		foreach ( $memberships as $membership ) {
			$plan = $membership->get_plan();

			$items[] = array(
				'plan_name'  => $plan ? $plan->get_name() : __( 'Membership', 'gym-core' ),
				'status'     => $membership->get_status(),
				'start_date' => $membership->get_start_date( 'Y-m-d' ),
				'end_date'   => $membership->get_end_date( 'Y-m-d' ) ?: null,
			);
		}

		return $items;
	}

	/**
	 * Builds the billing section from the next renewal subscription.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null Null if no billing data available.
	 */
	private function build_billing_section( int $user_id ): ?array {
		try {
			if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
				return null;
			}

			$subscriptions = wcs_get_users_subscriptions( $user_id );

			// Find the first active subscription with a next payment date.
			foreach ( $subscriptions as $subscription ) {
				if ( 'active' !== $subscription->get_status() ) {
					continue;
				}

				$next_payment = $subscription->get_date( 'next_payment' );
				if ( ! $next_payment ) {
					continue;
				}

				$payment_method = $subscription->get_payment_method_title();

				// Try to get a card summary (e.g., "Visa ending in 4242").
				$payment_summary = $payment_method;
				$token_id        = $subscription->get_meta( '_payment_tokens' );
				if ( $token_id && class_exists( 'WC_Payment_Tokens' ) ) {
					if ( ! isset( $this->payment_tokens_cache[ $user_id ] ) ) {
						$this->payment_tokens_cache[ $user_id ] = \WC_Payment_Tokens::get_customer_tokens( $user_id );
					}
					$tokens = $this->payment_tokens_cache[ $user_id ];
					foreach ( $tokens as $token ) {
						if ( $token->get_id() === (int) $token_id ) {
							$payment_summary = $token->get_display_name();
							break;
						}
					}
				}

				return array(
					'next_payment_date'      => gmdate( 'Y-m-d', $next_payment->getTimestamp() ),
					'next_payment_amount'    => $subscription->get_total(),
					'payment_method_summary' => $payment_summary ?: null,
				);
			}
		} catch ( \Throwable $e ) {
			wc_get_logger()->warning(
				'MemberController: billing error — ' . $e->getMessage(),
				array( 'source' => 'gym-core' )
			);
		}

		return null;
	}

	/**
	 * Builds the upcoming classes section for the current week.
	 *
	 * Uses the member's stored location to filter relevant classes.
	 *
	 * @param \WP_User $user WordPress user object.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_upcoming_classes_section( \WP_User $user ): array {
		try {
			$location = get_user_meta( $user->ID, 'gym_location', true );

			if ( empty( $location ) ) {
				return array();
			}

			$monday = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
			$today  = gmdate( 'Y-m-d' );

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

			$query    = new \WP_Query( $args );
			$days     = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
			$schedule = array();

			// Prime caches in bulk to avoid N+1 queries in the nested loop.
			$program_map = array();
			if ( ! empty( $query->posts ) ) {
				$post_ids = wp_list_pluck( $query->posts, 'ID' );
				update_meta_cache( 'post', $post_ids );
				update_object_term_cache( $post_ids, ClassPostType::POST_TYPE );

				// Pre-build a post_id => program_slug map so the nested day x class
				// loop below doesn't call get_the_terms() 7 times per class.
				foreach ( $query->posts as $post ) {
					$terms                      = get_the_terms( $post->ID, ClassPostType::PROGRAM_TAXONOMY );
					$program_map[ $post->ID ] = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : null;
				}

				// Collect and cache all instructor user data in one query.
				$instructor_ids = array_unique(
					array_filter(
						array_map(
							static fn( $post ) => (int) get_post_meta( $post->ID, '_gym_class_instructor', true ),
							$query->posts
						)
					)
				);

				if ( ! empty( $instructor_ids ) ) {
					cache_users( $instructor_ids );
				}
			}

			foreach ( $days as $i => $day_name ) {
				$date = gmdate( 'Y-m-d', strtotime( $monday . " +{$i} days" ) );

				// Only include today and future days.
				if ( $date < $today ) {
					continue;
				}

				$classes = array();

				foreach ( $query->posts as $post ) {
					$class_day = get_post_meta( $post->ID, '_gym_class_day_of_week', true );
					if ( $class_day !== $day_name ) {
						continue;
					}

					$program = $program_map[ $post->ID ] ?? null;

					$instructor_id = (int) get_post_meta( $post->ID, '_gym_class_instructor', true );
					$instructor    = $instructor_id ? get_userdata( $instructor_id ) : null;

					$classes[] = array(
						'id'         => $post->ID,
						'name'       => $post->post_title,
						'program'    => $program,
						'instructor' => $instructor ? $instructor->display_name : null,
						'start_time' => get_post_meta( $post->ID, '_gym_class_start_time', true ),
						'end_time'   => get_post_meta( $post->ID, '_gym_class_end_time', true ),
					);
				}

				if ( empty( $classes ) ) {
					continue;
				}

				// Sort by start time.
				usort( $classes, static fn( $a, $b ) => strcmp( $a['start_time'], $b['start_time'] ) );

				$schedule[] = array(
					'date'     => $date,
					'day_name' => ucfirst( $day_name ),
					'classes'  => $classes,
				);
			}

			return $schedule;
		} catch ( \Throwable $e ) {
			wc_get_logger()->warning(
				'MemberController: upcoming_classes error — ' . $e->getMessage(),
				array( 'source' => 'gym-core' )
			);

			return array();
		}
	}

	/**
	 * Builds the rank section enriched with display data from RankDefinitions.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_rank_section( int $user_id ): array {
		try {
			$all_ranks   = $this->ranks->get_all_ranks( $user_id );
			$definitions = RankDefinitions::get_all_definitions();
			$formatted   = array();

			foreach ( $all_ranks as $rank ) {
				$program   = $rank->program;
				$belt_defs = $definitions[ $program ] ?? array();
				$color     = null;
				$type      = null;

				foreach ( $belt_defs as $def ) {
					if ( $def['slug'] === $rank->belt ) {
						$color = $def['color'];
						$type  = $def['type'];
						break;
					}
				}

				$formatted[] = array(
					'program'          => $program,
					'belt'             => $rank->belt,
					'stripes'          => (int) $rank->stripes,
					'color'            => $color,
					'type'             => $type,
					'last_promoted_at' => $rank->promoted_at,
				);
			}

			return $formatted;
		} catch ( \Throwable $e ) {
			wc_get_logger()->warning(
				'MemberController: rank error — ' . $e->getMessage(),
				array( 'source' => 'gym-core' )
			);

			return array();
		}
	}

	/**
	 * Builds the foundations section.
	 *
	 * Returns null unless the member is currently in foundations for adult-bjj.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	private function build_foundations_section( int $user_id ): ?array {
		try {
			if ( ! FoundationsClearance::is_enabled() ) {
				return null;
			}

			$status = $this->foundations->get_status( $user_id );

			// Only return foundations data if the member is actively in foundations.
			if ( ! $status['in_foundations'] ) {
				return null;
			}

			return $status;
		} catch ( \Throwable $e ) {
			wc_get_logger()->warning(
				'MemberController: foundations error — ' . $e->getMessage(),
				array( 'source' => 'gym-core' )
			);

			return null;
		}
	}

	/**
	 * Builds the gamification section.
	 *
	 * Returns null if gamification modules are not available.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	private function build_gamification_section( int $user_id ): ?array {
		try {
			if ( null === $this->streaks && null === $this->badges ) {
				return null;
			}

			$streak_weeks  = 0;
			$badges_count  = 0;
			$total_classes = $this->attendance->get_total_count( $user_id );

			if ( null !== $this->streaks ) {
				$streak_data  = $this->streaks->get_streak( $user_id );
				$streak_weeks = $streak_data['current_streak'];
			}

			if ( null !== $this->badges ) {
				$user_badges  = $this->badges->get_user_badges( $user_id );
				$badges_count = count( $user_badges );
			}

			return array(
				'current_streak_weeks' => $streak_weeks,
				'badges_earned_count'  => $badges_count,
				'total_classes'        => $total_classes,
			);
		} catch ( \Throwable $e ) {
			wc_get_logger()->warning(
				'MemberController: gamification error — ' . $e->getMessage(),
				array( 'source' => 'gym-core' )
			);

			return null;
		}
	}

	/**
	 * Builds the quick links section with static URLs.
	 *
	 * @return array<string, string>
	 */
	private function build_quick_links(): array {
		return array(
			'update_payment_url'  => '/my-account/payment-methods/',
			'billing_history_url' => '/my-account/orders/',
			'schedule_url'        => '/classes/',
			'shop_url'            => '/shop/',
		);
	}
}
