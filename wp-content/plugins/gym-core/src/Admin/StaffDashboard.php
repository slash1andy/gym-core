<?php
/**
 * Staff Dashboard — role-aware landing page with AI chat and widgets.
 *
 * Replaces the empty gym-core admin page with a two-column layout:
 * chat (left) + role-tailored stat widgets (right).
 *
 * @package Gym_Core
 * @since   2.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Rank\RankStore;
use Gym_Core\Attendance\PromotionEligibility;

/**
 * Staff Dashboard page controller.
 */
final class StaffDashboard {

	/**
	 * Menu slug (same as top-level gym-core menu).
	 */
	private const MENU_SLUG = 'gym-core';

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
	 * Promotion eligibility.
	 *
	 * @var PromotionEligibility
	 */
	private PromotionEligibility $eligibility;

	/**
	 * Hook suffix for the admin page.
	 *
	 * @var string|false
	 */
	private $hook_suffix = false;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore      $attendance  Attendance store.
	 * @param RankStore            $ranks       Rank store.
	 * @param PromotionEligibility $eligibility Promotion eligibility.
	 */
	public function __construct(
		AttendanceStore $attendance,
		RankStore $ranks,
		PromotionEligibility $eligibility
	) {
		$this->attendance  = $attendance;
		$this->ranks       = $ranks;
		$this->eligibility = $eligibility;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the Dashboard submenu page.
	 *
	 * Runs at priority 20 — after the top-level menu is created at priority 5.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Remove the auto-generated submenu that duplicates the top-level item.
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );

		// Re-add it with the dashboard render callback and "Dashboard" label.
		$this->hook_suffix = add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'gym-core' ),
			__( 'Dashboard', 'gym-core' ),
			'read',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			0 // Position: first submenu item.
		);
	}

	// ─── Asset enqueueing ───────────────────────────���───────────────

	/**
	 * Enqueues CSS and JS only on this dashboard page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'gym-staff-dashboard',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/staff-dashboard.css',
			array(),
			'2.3.0'
		);

		wp_register_script( 'gym-staff-dashboard', false, array(), '2.3.0', true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'gym-staff-dashboard' );

		wp_localize_script(
			'gym-staff-dashboard',
			'gymStaffDashboard',
			array(
				'defaultAgent'  => $this->get_default_agent(),
				'allowedAgents' => $this->get_allowed_agent_slugs(),
			)
		);

		// Print the inline JS that pre-selects the agent and filters the dropdown.
		add_action( 'admin_footer', array( $this, 'print_inline_script' ) );
	}

	/**
	 * Prints the inline JS for agent pre-selection.
	 *
	 * @return void
	 */
	public function print_inline_script(): void {
		?>
		<script>
		(function() {
			'use strict';
			var config = window.gymStaffDashboard || {};

			function init() {
				var selector = document.getElementById('hma-agent-selector');
				if (!selector) return;

				// Filter dropdown to only show allowed agents.
				if (config.allowedAgents && config.allowedAgents.length) {
					var options = selector.querySelectorAll('option');
					for (var i = 0; i < options.length; i++) {
						if (options[i].value && config.allowedAgents.indexOf(options[i].value) === -1) {
							options[i].style.display = 'none';
							options[i].disabled = true;
						}
					}
				}

				// Pre-select the default agent.
				if (config.defaultAgent) {
					selector.value = config.defaultAgent;
					selector.dispatchEvent(new Event('change'));
				}
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
		</script>
		<?php
	}

	// ─── Role detection ─────────────────────────────────────────────

	/**
	 * Returns the role context for the current user.
	 *
	 * @return string One of 'admin', 'coach', 'finance', 'sales'.
	 */
	private function get_role_context(): string {
		$user = wp_get_current_user();

		if ( current_user_can( 'manage_options' ) ) {
			return 'admin';
		}

		if ( in_array( 'gym_head_coach', $user->roles, true ) || in_array( 'gym_coach', $user->roles, true ) ) {
			return 'coach';
		}

		if ( current_user_can( 'manage_woocommerce' ) || in_array( 'shop_manager', $user->roles, true ) ) {
			return 'finance';
		}

		return 'sales';
	}

	/**
	 * Returns the default agent slug for the current user.
	 *
	 * @return string Agent slug.
	 */
	private function get_default_agent(): string {
		$context = $this->get_role_context();

		$map = array(
			'admin'   => 'admin',
			'coach'   => 'coaching',
			'finance' => 'finance',
			'sales'   => 'sales',
		);

		return $map[ $context ] ?? 'sales';
	}

	/**
	 * Returns the agent slugs the current user may access.
	 *
	 * @return array<string>
	 */
	private function get_allowed_agent_slugs(): array {
		$context = $this->get_role_context();

		$map = array(
			'admin'   => array( 'admin', 'coaching', 'finance', 'sales' ),
			'coach'   => array( 'coaching' ),
			'finance' => array( 'finance' ),
			'sales'   => array( 'sales' ),
		);

		return $map[ $context ] ?? array( 'sales' );
	}

	// ─── Page rendering ─────────────────────────────────────────────

	/**
	 * Renders the dashboard page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$context       = $this->get_role_context();
		$has_chat      = defined( 'HMA_AI_CHAT_VERSION' );
		$wrapper_class = $has_chat ? 'gym-staff-dashboard' : 'gym-staff-dashboard gym-staff-dashboard--no-chat';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Gym Dashboard', 'gym-core' ) . '</h1>';

		echo '<div class="' . esc_attr( $wrapper_class ) . '">';

		// Chat column.
		if ( $has_chat ) {
			echo '<div class="gym-dashboard-chat">';
			echo '<div id="hma-ai-chat-container" class="hma-ai-chat-panel">';
			echo '<!-- Chat app rendered by hma-ai-chat JS -->';
			echo '</div>';
			echo '</div>';
		} else {
			echo '<div class="gym-dashboard-notice">';
			echo '<p>' . esc_html( sprintf( __( 'AI Chat is not available. Install and activate the %s AI Chat plugin.', 'gym-core' ), \Gym_Core\Utilities\Brand::name() ) ) . '</p>';
			echo '</div>';
		}

		// Widgets column.
		echo '<div class="gym-dashboard-widgets">';
		$this->render_widgets( $context );
		echo '</div>';

		echo '</div>'; // .gym-staff-dashboard
		echo '</div>'; // .wrap
	}

	/**
	 * Dispatches to the correct widget renderer.
	 *
	 * @param string $context Role context.
	 * @return void
	 */
	private function render_widgets( string $context ): void {
		switch ( $context ) {
			case 'admin':
				$this->render_admin_widgets();
				break;
			case 'coach':
				$this->render_coach_widgets();
				break;
			case 'finance':
				$this->render_finance_widgets();
				break;
			default:
				$this->render_sales_widgets();
				break;
		}
	}

	// ─── Widget renderers ───────────────────────────────────────────

	/**
	 * Admin widgets: attendance totals, active subs, pending actions, failed payments, new signups.
	 *
	 * @return void
	 */
	private function render_admin_widgets(): void {
		echo '<h2 class="gym-widget-heading">' . esc_html__( 'Overview', 'gym-core' ) . '</h2>';
		echo '<div class="gym-stat-cards">';

		// Today's attendance.
		$locations       = \Gym_Core\Location\Taxonomy::get_location_labels();
		$total           = 0;
		$location_counts = array();
		foreach ( $locations as $slug => $label ) {
			$records                    = $this->attendance->get_today_by_location( $slug );
			$location_counts[ $label ] = count( $records );
			$total                     += count( $records );
		}
		$this->render_stat_card( (string) $total, __( 'Check-ins Today', 'gym-core' ) );

		// Location breakdown.
		foreach ( $location_counts as $label => $count ) {
			$this->render_stat_card( (string) $count, $label );
		}

		// Active subscriptions.
		$subs_count = $this->get_active_subscriptions_count();
		$this->render_stat_card( (string) $subs_count, __( 'Active Members', 'gym-core' ) );

		// Pending actions (cross-plugin).
		$pending = 0;
		if ( class_exists( '\HMA_AI_Chat\Data\PendingActionStore' ) ) {
			$store   = new \HMA_AI_Chat\Data\PendingActionStore();
			$pending = $store->get_pending_count();
		}
		$this->render_stat_card( (string) $pending, __( 'Pending Actions', 'gym-core' ) );

		// Failed payments this month.
		$failed = $this->get_orders_count_this_month( 'failed' );
		$this->render_stat_card( (string) $failed, __( 'Failed Payments', 'gym-core' ) );

		echo '</div>';
	}

	/**
	 * Coach widgets: attendance by location, promotion candidates, my classes, recent promotions.
	 *
	 * @return void
	 */
	private function render_coach_widgets(): void {
		echo '<h2 class="gym-widget-heading">' . esc_html__( 'Coaching', 'gym-core' ) . '</h2>';
		echo '<div class="gym-stat-cards">';

		// Attendance by location.
		$locations = \Gym_Core\Location\Taxonomy::get_location_labels();
		foreach ( $locations as $slug => $label ) {
			$records = $this->attendance->get_today_by_location( $slug );
			$this->render_stat_card( (string) count( $records ), $label . ' ' . __( 'Today', 'gym-core' ) );
		}

		// Promotion candidates.
		$programs  = array( 'adult-bjj', 'kids-bjj', 'kickboxing' );
		$eligible  = 0;
		foreach ( $programs as $program ) {
			$candidates = $this->eligibility->get_eligible_members( $program );
			$eligible  += count( $candidates );
		}
		$this->render_stat_card( (string) $eligible, __( 'Promotion Eligible', 'gym-core' ) );

		// My classes today.
		$my_classes = $this->get_instructor_classes_today( get_current_user_id() );
		$this->render_stat_card( (string) count( $my_classes ), __( 'My Classes Today', 'gym-core' ) );

		// Member counts by program.
		$counts = $this->ranks->get_member_counts_by_program();
		foreach ( $counts as $program => $count ) {
			$label = ucfirst( str_replace( '-', ' ', $program ) );
			$this->render_stat_card( (string) $count, $label );
		}

		echo '</div>';
	}

	/**
	 * Finance widgets: MRR, active subs, failed payments, new signups.
	 *
	 * @return void
	 */
	private function render_finance_widgets(): void {
		echo '<h2 class="gym-widget-heading">' . esc_html__( 'Finance', 'gym-core' ) . '</h2>';
		echo '<div class="gym-stat-cards">';

		// Active subscriptions.
		$subs_count = $this->get_active_subscriptions_count();
		$this->render_stat_card( (string) $subs_count, __( 'Active Subscriptions', 'gym-core' ) );

		// Failed payments.
		$failed = $this->get_orders_count_this_month( 'failed' );
		$this->render_stat_card( (string) $failed, __( 'Failed Payments', 'gym-core' ) );

		// New signups this month.
		$new = $this->get_orders_count_this_month( 'completed' );
		$this->render_stat_card( (string) $new, __( 'New Orders (Month)', 'gym-core' ) );

		echo '</div>';
	}

	/**
	 * Sales widgets: trial bookings, new leads.
	 *
	 * @return void
	 */
	private function render_sales_widgets(): void {
		echo '<h2 class="gym-widget-heading">' . esc_html__( 'Sales', 'gym-core' ) . '</h2>';
		echo '<div class="gym-stat-cards">';

		// New orders this month (proxy for leads converting).
		$new = $this->get_orders_count_this_month( 'processing' );
		$this->render_stat_card( (string) $new, __( 'New Orders (Month)', 'gym-core' ) );

		// Today's attendance (sales context: foot traffic).
		$locations = \Gym_Core\Location\Taxonomy::get_location_labels();
		$total     = 0;
		foreach ( $locations as $slug => $label ) {
			$records = $this->attendance->get_today_by_location( $slug );
			$total  += count( $records );
		}
		$this->render_stat_card( (string) $total, __( 'Visits Today', 'gym-core' ) );

		echo '</div>';
	}

	// ─── Helpers ────────────────────────────────────────────────────

	/**
	 * Renders a single stat card.
	 *
	 * @param string $value Display value.
	 * @param string $label Display label.
	 * @return void
	 */
	private function render_stat_card( string $value, string $label ): void {
		echo '<div class="gym-stat-card">';
		echo '<div class="stat-value">' . esc_html( $value ) . '</div>';
		echo '<div class="stat-label">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	/**
	 * Gets the count of active WooCommerce subscriptions.
	 *
	 * @return int
	 */
	private function get_active_subscriptions_count(): int {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return 0;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status' => 'active',
				'subscriptions_per_page' => -1,
			)
		);

		return count( $subscriptions );
	}

	/**
	 * Gets WooCommerce order count for a status this month.
	 *
	 * @param string $status Order status (e.g., 'failed', 'completed', 'processing').
	 * @return int
	 */
	private function get_orders_count_this_month( string $status ): int {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$orders = wc_get_orders(
			array(
				'status'     => $status,
				'date_after' => gmdate( 'Y-m-01' ),
				'return'     => 'ids',
				'limit'      => -1,
			)
		);

		return count( $orders );
	}

	/**
	 * Gets classes today where the given user is the instructor.
	 *
	 * @param int $user_id Instructor user ID.
	 * @return array<\WP_Post>
	 */
	private function get_instructor_classes_today( int $user_id ): array {
		$today = strtolower( gmdate( 'l' ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'gym_class',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_gym_class_instructor',
						'value' => $user_id,
						'type'  => 'NUMERIC',
					),
					array(
						'key'   => '_gym_class_day_of_week',
						'value' => $today,
					),
					array(
						'key'   => '_gym_class_status',
						'value' => 'active',
					),
				),
			)
		);

		return $query->posts ?: array();
	}
}
