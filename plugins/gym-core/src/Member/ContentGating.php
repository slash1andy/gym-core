<?php
/**
 * Content gating integration with WooCommerce Memberships.
 *
 * Links WooCommerce Memberships plans to subscription products for
 * automatic enrollment, adds gym-specific content restriction rules,
 * and provides helper methods for membership status checks used by
 * CheckInValidator, PromotionEligibility, and the member dashboard.
 *
 * Only activates when WooCommerce Memberships is detected.
 *
 * @package Gym_Core\Member
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Member;

/**
 * Manages content gating and membership plan integration.
 */
final class ContentGating {

	/**
	 * Membership plan slugs mapped to their configuration.
	 *
	 * @var array<string, array{name: string, product_slug: string}>
	 */
	private const PLANS = array(
		'adult-bjj-member'   => array(
			'name'         => 'Adult BJJ Member',
			'product_slug' => 'adult-bjj-membership',
		),
		'kids-bjj-member'    => array(
			'name'         => 'Kids BJJ Member',
			'product_slug' => 'kids-bjj-membership',
		),
		'kickboxing-member'  => array(
			'name'         => 'Kickboxing Member',
			'product_slug' => 'kickboxing-membership',
		),
		'all-access-member'  => array(
			'name'         => 'All-Access Member',
			'product_slug' => 'all-access-membership',
		),
	);

	/**
	 * Content restriction rule types.
	 *
	 * @var string
	 */
	private const RULE_TYPE_TECHNIQUE_VIDEO = 'technique_video';
	private const RULE_TYPE_TRAINING_RESOURCE = 'training_resource';

	/**
	 * Registers WordPress hooks.
	 *
	 * Only hooks in when WooCommerce Memberships is active.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! self::is_memberships_active() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'maybe_create_plans' ) );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'hide_already_subscribed_products' ), 10, 2 );
		add_filter( 'gym_core_content_access', array( $this, 'check_content_access' ), 10, 3 );
	}

	/**
	 * Creates membership plans linked to subscription products on activation.
	 *
	 * Idempotent — skips plans that already exist. Runs on admin_init
	 * and checks a version flag to avoid running on every page load.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function maybe_create_plans(): void {
		$version_key = 'gym_core_membership_plans_version';
		$current     = get_option( $version_key, '' );

		if ( '3.0.0' === $current ) {
			return;
		}

		foreach ( self::PLANS as $slug => $config ) {
			$this->ensure_plan_exists( $slug, $config['name'], $config['product_slug'] );
		}

		update_option( $version_key, '3.0.0' );
	}

	/**
	 * Checks whether a user has an active membership, optionally for a specific program.
	 *
	 * Checks WooCommerce Memberships first, then falls back to checking
	 * WooCommerce Subscriptions status directly. This ensures the method
	 * works even if Memberships plugin is deactivated.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $program Optional program slug (e.g., 'adult-bjj'). Empty = any program.
	 * @return bool True if the user has an active membership.
	 */
	public static function has_active_membership( int $user_id, string $program = '' ): bool {
		if ( 0 === $user_id ) {
			return false;
		}

		// Strategy 1: Check WooCommerce Memberships if available.
		if ( function_exists( 'wc_memberships_is_user_active_member' ) ) {
			if ( '' === $program ) {
				// Check if user is an active member of any plan.
				foreach ( array_keys( self::PLANS ) as $plan_slug ) {
					if ( wc_memberships_is_user_active_member( $user_id, $plan_slug ) ) {
						return true;
					}
				}

				return false;
			}

			// Map program slug to plan slug.
			$plan_slug = self::program_to_plan_slug( $program );
			if ( $plan_slug ) {
				if ( wc_memberships_is_user_active_member( $user_id, $plan_slug ) ) {
					return true;
				}
			}

			// All-access covers every program.
			if ( wc_memberships_is_user_active_member( $user_id, 'all-access-member' ) ) {
				return true;
			}

			return false;
		}

		// Strategy 2: Fall back to WC Subscriptions status.
		return self::has_active_subscription( $user_id, $program );
	}

	/**
	 * Returns all active membership plans for a given user.
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array{plan_slug: string, plan_name: string, status: string, start_date: string}>
	 */
	public static function get_member_plans( int $user_id ): array {
		$plans = array();

		if ( 0 === $user_id ) {
			return $plans;
		}

		// Use WC Memberships if available.
		if ( function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
			$memberships = wc_memberships_get_user_active_memberships( $user_id );

			foreach ( $memberships as $membership ) {
				$plan = $membership->get_plan();
				if ( ! $plan ) {
					continue;
				}

				$plans[] = array(
					'plan_slug'  => $plan->get_slug(),
					'plan_name'  => $plan->get_name(),
					'status'     => $membership->get_status(),
					'start_date' => $membership->get_start_date( 'Y-m-d' ),
				);
			}

			return $plans;
		}

		// Fall back to subscription-based plan detection.
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return $plans;
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			$status = $subscription->get_status();
			if ( 'active' !== $status && 'pending-cancel' !== $status ) {
				continue;
			}

			foreach ( $subscription->get_items() as $item ) {
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}

				$product_slug = $product->get_slug();
				$matched_plan = self::product_slug_to_plan( $product_slug );

				if ( $matched_plan ) {
					$plans[] = array(
						'plan_slug'  => $matched_plan,
						'plan_name'  => self::PLANS[ $matched_plan ]['name'] ?? $item->get_name(),
						'status'     => $status,
						'start_date' => $subscription->get_date( 'start' ) ?: '',
					);
				}
			}
		}

		return $plans;
	}

	/**
	 * Filters product purchasability to hide products the member already subscribes to.
	 *
	 * Prevents duplicate subscriptions by making the product un-purchasable
	 * when the current user already has an active subscription for it.
	 *
	 * @since 3.0.0
	 *
	 * @param bool        $purchasable Whether the product is purchasable.
	 * @param \WC_Product $product     Product object.
	 * @return bool
	 */
	public function hide_already_subscribed_products( bool $purchasable, \WC_Product $product ): bool {
		if ( ! $purchasable || ! is_user_logged_in() ) {
			return $purchasable;
		}

		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return $purchasable;
		}

		$user_id       = get_current_user_id();
		$product_id    = $product->get_id();
		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			$status = $subscription->get_status();
			if ( 'active' !== $status && 'pending-cancel' !== $status ) {
				continue;
			}

			foreach ( $subscription->get_items() as $item ) {
				if ( $item->get_product_id() === $product_id || $item->get_variation_id() === $product_id ) {
					return false;
				}
			}
		}

		return $purchasable;
	}

	/**
	 * Checks content access based on gym-specific restriction rules.
	 *
	 * Hooked into gym_core_content_access filter. Returns true if the
	 * user can access content, false otherwise.
	 *
	 * @since 3.0.0
	 *
	 * @param bool   $has_access Whether the user has access.
	 * @param int    $user_id   User ID.
	 * @param string $rule_type Content type (technique_video, training_resource).
	 * @return bool
	 */
	public function check_content_access( bool $has_access, int $user_id, string $rule_type ): bool {
		// Already granted by another filter — respect it.
		if ( $has_access ) {
			return true;
		}

		switch ( $rule_type ) {
			case self::RULE_TYPE_TECHNIQUE_VIDEO:
				// Technique videos require membership in the matching program.
				// The program is passed via the gym_core_content_program filter.
				$program = apply_filters( 'gym_core_content_program', '', $user_id );
				return self::has_active_membership( $user_id, $program );

			case self::RULE_TYPE_TRAINING_RESOURCE:
				// Training resources are available to any active member.
				return self::has_active_membership( $user_id );

			default:
				return $has_access;
		}
	}

	/**
	 * Whether WooCommerce Memberships plugin is active.
	 *
	 * @return bool
	 */
	private static function is_memberships_active(): bool {
		return function_exists( 'wc_memberships' ) || class_exists( \WC_Memberships::class );
	}

	/**
	 * Ensures a membership plan exists, creating it if necessary.
	 *
	 * @param string $slug         Plan slug.
	 * @param string $name         Plan name.
	 * @param string $product_slug Associated subscription product slug.
	 * @return void
	 */
	private function ensure_plan_exists( string $slug, string $name, string $product_slug ): void {
		// Check if plan post already exists.
		$existing = get_posts(
			array(
				'post_type'   => 'wc_membership_plan',
				'post_status' => 'any',
				'name'        => $slug,
				'numberposts' => 1,
			)
		);

		if ( ! empty( $existing ) ) {
			return;
		}

		$plan_id = wp_insert_post(
			array(
				'post_type'   => 'wc_membership_plan',
				'post_title'  => $name,
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $plan_id ) || 0 === $plan_id ) {
			return;
		}

		// Link to the subscription product for auto-enrollment.
		$product = $this->get_product_by_slug( $product_slug );
		if ( $product ) {
			update_post_meta( $plan_id, '_product_ids', array( $product->get_id() ) );
			update_post_meta( $plan_id, '_access_method', 'purchase' );
		}
	}

	/**
	 * Finds a WooCommerce product by slug.
	 *
	 * @param string $slug Product slug.
	 * @return \WC_Product|null
	 */
	private function get_product_by_slug( string $slug ): ?\WC_Product {
		$posts = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'name'        => $slug,
				'numberposts' => 1,
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$product = wc_get_product( $posts[0]->ID );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Maps a program slug to a membership plan slug.
	 *
	 * @param string $program Program slug (e.g., 'adult-bjj').
	 * @return string|null Plan slug, or null if not mapped.
	 */
	private static function program_to_plan_slug( string $program ): ?string {
		$map = array(
			'adult-bjj'  => 'adult-bjj-member',
			'kids-bjj'   => 'kids-bjj-member',
			'kickboxing' => 'kickboxing-member',
		);

		return $map[ $program ] ?? null;
	}

	/**
	 * Maps a product slug to a membership plan slug.
	 *
	 * @param string $product_slug Product slug.
	 * @return string|null Plan slug, or null if not mapped.
	 */
	private static function product_slug_to_plan( string $product_slug ): ?string {
		foreach ( self::PLANS as $plan_slug => $config ) {
			if ( $config['product_slug'] === $product_slug ) {
				return $plan_slug;
			}
		}

		return null;
	}

	/**
	 * Checks whether a user has an active WooCommerce Subscription for a program.
	 *
	 * Fallback when WC Memberships is not available.
	 *
	 * @param int    $user_id User ID.
	 * @param string $program Optional program slug to filter by.
	 * @return bool
	 */
	private static function has_active_subscription( int $user_id, string $program = '' ): bool {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return false;
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			$status = $subscription->get_status();
			if ( 'active' !== $status && 'pending-cancel' !== $status ) {
				continue;
			}

			// If no specific program, any active subscription counts.
			if ( '' === $program ) {
				return true;
			}

			// Check if the subscription's product matches the program.
			$plan_slug = self::program_to_plan_slug( $program );
			if ( ! $plan_slug ) {
				continue;
			}

			$target_product_slug = self::PLANS[ $plan_slug ]['product_slug'] ?? '';
			$all_access_slug     = self::PLANS['all-access-member']['product_slug'];

			foreach ( $subscription->get_items() as $item ) {
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}

				$slug = $product->get_slug();
				if ( $slug === $target_product_slug || $slug === $all_access_slug ) {
					return true;
				}
			}
		}

		return false;
	}
}
