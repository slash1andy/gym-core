<?php
/**
 * Custom WooCommerce My Account dashboard for gym members.
 *
 * Registers a `gym-dashboard` endpoint under My Account and renders a
 * personalized dashboard with membership info, upcoming classes, rank
 * display, foundations status, and gamification summary.
 *
 * @package Gym_Core\Member
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Member;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Gamification\BadgeEngine;
use Gym_Core\Gamification\StreakTracker;
use Gym_Core\Location\Taxonomy as LocationTaxonomy;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Rank\RankStore;
use Gym_Core\Schedule\ClassPostType;

/**
 * Renders the member portal dashboard inside WooCommerce My Account.
 */
final class MemberDashboard {

	/**
	 * Endpoint slug registered under My Account.
	 *
	 * @var string
	 */
	public const ENDPOINT = 'gym-dashboard';

	/**
	 * Rank store.
	 *
	 * @var RankStore
	 */
	private RankStore $rank_store;

	/**
	 * Attendance store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance_store;

	/**
	 * Badge engine.
	 *
	 * @var BadgeEngine
	 */
	private BadgeEngine $badge_engine;

	/**
	 * Streak tracker.
	 *
	 * @var StreakTracker
	 */
	private StreakTracker $streak_tracker;

	/**
	 * Foundations clearance.
	 *
	 * @var FoundationsClearance
	 */
	private FoundationsClearance $foundations;

	/**
	 * Constructor.
	 *
	 * @param RankStore            $rank_store       Rank data store.
	 * @param AttendanceStore      $attendance_store  Attendance data store.
	 * @param BadgeEngine          $badge_engine      Badge evaluation engine.
	 * @param StreakTracker        $streak_tracker    Streak calculator.
	 * @param FoundationsClearance $foundations       Foundations clearance gate.
	 */
	public function __construct(
		RankStore $rank_store,
		AttendanceStore $attendance_store,
		BadgeEngine $badge_engine,
		StreakTracker $streak_tracker,
		FoundationsClearance $foundations
	) {
		$this->rank_store       = $rank_store;
		$this->attendance_store = $attendance_store;
		$this->badge_engine     = $badge_engine;
		$this->streak_tracker   = $streak_tracker;
		$this->foundations      = $foundations;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 5 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_dashboard' ) );
	}

	/**
	 * Registers the gym-dashboard rewrite endpoint.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Adds the endpoint query var.
	 *
	 * @since 3.0.0
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Adds the Dashboard menu item and reorders it to be first.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, string> $items Existing menu items.
	 * @return array<string, string>
	 */
	public function add_menu_item( array $items ): array {
		$new_items = array(
			self::ENDPOINT => __( 'Dashboard', 'gym-core' ),
		);

		foreach ( $items as $key => $label ) {
			if ( 'dashboard' === $key ) {
				// Replace the default WooCommerce dashboard with ours.
				continue;
			}
			$new_items[ $key ] = $label;
		}

		return $new_items;
	}

	/**
	 * Renders the personalized member dashboard.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return;
		}

		$user_id    = $user->ID;
		$first_name = $user->first_name ?: $user->display_name;
		$location   = get_user_meta( $user_id, '_gym_location', true );

		// Gather data — all calls are null-safe.
		$ranks              = $this->rank_store->get_all_ranks( $user_id );
		$foundations_status = $this->foundations->get_status( $user_id );
		$streak_data        = $this->streak_tracker->get_streak( $user_id );
		$badges             = $this->badge_engine->get_user_badges( $user_id );
		$total_classes      = $this->attendance_store->get_total_count( $user_id );
		$subscription       = $this->get_active_subscription( $user_id );
		$upcoming_classes   = $this->get_upcoming_classes( $location );
		$location_labels    = LocationTaxonomy::get_location_labels();
		$location_label     = $location_labels[ $location ] ?? '';

		$this->render_styles();
		?>
		<div class="gym-dashboard">

			<?php // --- Welcome & Location --- ?>
			<div class="gym-dashboard__header">
				<h2 class="gym-dashboard__welcome">
					<?php
					printf(
						/* translators: %s: member's first name */
						esc_html__( 'Welcome back, %s!', 'gym-core' ),
						esc_html( $first_name )
					);
					?>
				</h2>
				<?php if ( $location_label ) : ?>
					<span class="gym-dashboard__location-badge">
						<?php echo esc_html( $location_label ); ?>
					</span>
				<?php endif; ?>
			</div>

			<?php // --- Membership Card --- ?>
			<div class="gym-dashboard__card gym-dashboard__membership">
				<h3><?php esc_html_e( 'Membership', 'gym-core' ); ?></h3>
				<?php if ( $subscription ) : ?>
					<div class="gym-dashboard__membership-details">
						<p class="gym-dashboard__plan-name">
							<?php echo esc_html( $subscription['plan_name'] ); ?>
						</p>
						<span class="gym-dashboard__status-badge gym-dashboard__status-badge--<?php echo esc_attr( $subscription['status'] ); ?>">
							<?php echo esc_html( ucfirst( $subscription['status'] ) ); ?>
						</span>
						<?php if ( $subscription['next_payment_date'] ) : ?>
							<p class="gym-dashboard__billing">
								<?php
								printf(
									/* translators: 1: billing amount, 2: next billing date */
									esc_html__( 'Next billing: %1$s on %2$s', 'gym-core' ),
									esc_html( $subscription['amount'] ),
									esc_html( $subscription['next_payment_date'] )
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<p class="gym-dashboard__empty-state">
						<?php esc_html_e( 'No active membership found.', 'gym-core' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Fires after the membership card in the member dashboard.
			 *
			 * @since 3.0.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'gym_core_dashboard_after_membership', $user_id );
			?>

			<?php // --- Quick Links --- ?>
			<div class="gym-dashboard__card gym-dashboard__quick-links">
				<h3><?php esc_html_e( 'Quick Links', 'gym-core' ); ?></h3>
				<ul>
					<li>
						<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'payment-methods' ) ); ?>">
							<?php esc_html_e( 'Update Payment Method', 'gym-core' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
							<?php esc_html_e( 'Billing History', 'gym-core' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( get_post_type_archive_link( ClassPostType::POST_TYPE ) ?: '#' ); ?>">
							<?php esc_html_e( 'View Schedule', 'gym-core' ); ?>
						</a>
					</li>
				</ul>
			</div>

			<?php // --- Upcoming Classes --- ?>
			<div class="gym-dashboard__card gym-dashboard__schedule">
				<h3><?php esc_html_e( 'Upcoming Classes', 'gym-core' ); ?></h3>
				<?php if ( ! empty( $upcoming_classes ) ) : ?>
					<ul class="gym-dashboard__class-list">
						<?php foreach ( $upcoming_classes as $class ) : ?>
							<li class="gym-dashboard__class-item">
								<strong><?php echo esc_html( $class['title'] ); ?></strong>
								<span class="gym-dashboard__class-meta">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: day of week, 2: start time, 3: end time */
											__( '%1$s %2$s – %3$s', 'gym-core' ),
											$class['day'],
											$class['start_time'],
											$class['end_time']
										)
									);
									?>
								</span>
								<?php if ( $class['instructor'] ) : ?>
									<span class="gym-dashboard__class-instructor">
										<?php echo esc_html( $class['instructor'] ); ?>
									</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="gym-dashboard__empty-state">
						<?php esc_html_e( 'No upcoming classes found for your location.', 'gym-core' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Fires after the schedule section in the member dashboard.
			 *
			 * @since 3.0.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'gym_core_dashboard_after_schedule', $user_id );
			?>

			<?php // --- Belt Rank Display --- ?>
			<div class="gym-dashboard__card gym-dashboard__ranks">
				<h3><?php esc_html_e( 'Belt Rank', 'gym-core' ); ?></h3>
				<?php if ( ! empty( $ranks ) ) : ?>
					<?php
					$programs = RankDefinitions::get_programs();
					foreach ( $ranks as $rank ) :
						$belt_defs   = RankDefinitions::get_ranks( $rank->program );
						$current_def = null;
						foreach ( $belt_defs as $def ) {
							if ( $def['slug'] === $rank->belt ) {
								$current_def = $def;
								break;
							}
						}
						if ( ! $current_def ) {
							continue;
						}
						$program_label = $programs[ $rank->program ] ?? ucfirst( $rank->program );
						?>
						<div class="gym-dashboard__rank-item">
							<span class="gym-dashboard__belt-circle" style="background-color: <?php echo esc_attr( $current_def['color'] ); ?>; <?php echo '#ffffff' === $current_def['color'] ? 'border: 2px solid #d1d5db;' : ''; ?>"></span>
							<div class="gym-dashboard__rank-details">
								<strong><?php echo esc_html( $current_def['name'] ); ?></strong>
								<span class="gym-dashboard__rank-program"><?php echo esc_html( $program_label ); ?></span>
								<?php if ( (int) $rank->stripes > 0 ) : ?>
									<span class="gym-dashboard__stripes">
										<?php
										$stripe_count = (int) $rank->stripes;
										echo esc_html(
											sprintf(
												/* translators: %d: number of stripes */
												_n( '%d stripe', '%d stripes', $stripe_count, 'gym-core' ),
												$stripe_count
											)
										);
										?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<?php // Show all belt colors for context. ?>
					<div class="gym-dashboard__belt-progression">
						<?php
						// Show progression for the first ranked program.
						$primary_program = $ranks[0]->program ?? '';
						$belt_defs       = RankDefinitions::get_ranks( $primary_program );

						// Abbreviated labels for belt dots (color-blind accessible).
						$belt_abbreviations = array(
							'white'  => 'W',
							'blue'   => 'B',
							'purple' => 'P',
							'brown'  => 'Br',
							'black'  => 'Bk',
							'grey'   => 'G',
							'yellow' => 'Y',
							'orange' => 'O',
							'green'  => 'Gn',
							'red'    => 'R',
						);

						foreach ( $belt_defs as $def ) :
							$is_current = false;
							foreach ( $ranks as $rank ) {
								if ( $rank->program === $primary_program && $rank->belt === $def['slug'] ) {
									$is_current = true;
									break;
								}
							}
							$abbr = $belt_abbreviations[ $def['slug'] ] ?? mb_strtoupper( mb_substr( $def['name'], 0, 1 ) );
							?>
							<span
								class="gym-dashboard__progression-dot <?php echo $is_current ? 'gym-dashboard__progression-dot--current' : ''; ?>"
								style="background-color: <?php echo esc_attr( $def['color'] ); ?>; <?php echo '#ffffff' === $def['color'] ? 'border: 2px solid #d1d5db;' : ''; ?>"
								title="<?php echo esc_attr( $def['name'] ); ?>"
								aria-label="<?php echo esc_attr( $def['name'] ); ?>"
							>
								<span class="gym-dashboard__progression-abbr"><?php echo esc_html( $abbr ); ?></span>
							</span>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="gym-dashboard__empty-state">
						<?php esc_html_e( 'Start your journey — your first belt awaits!', 'gym-core' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Fires after the rank display in the member dashboard.
			 *
			 * @since 3.0.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'gym_core_dashboard_after_ranks', $user_id );
			?>

			<?php // --- Foundations Status (if applicable) --- ?>
			<?php if ( $foundations_status['in_foundations'] ) : ?>
				<div class="gym-dashboard__card gym-dashboard__foundations">
					<h3><?php esc_html_e( 'Foundations Progress', 'gym-core' ); ?></h3>
					<div class="gym-dashboard__foundations-phase">
						<span class="gym-dashboard__phase-label">
							<?php
							$phase_labels = array(
								'phase1'             => __( 'Phase 1: Coached Instruction', 'gym-core' ),
								'phase2_coach_rolls' => __( 'Phase 2: Supervised Rolls', 'gym-core' ),
								'phase3'             => __( 'Phase 3: Final Classes', 'gym-core' ),
								'ready_to_clear'     => __( 'Ready for Clearance!', 'gym-core' ),
							);
							echo esc_html( $phase_labels[ $foundations_status['phase'] ] ?? $foundations_status['phase'] );
							?>
						</span>
					</div>
					<div class="gym-dashboard__foundations-stats">
						<div class="gym-dashboard__stat">
							<span class="gym-dashboard__stat-value">
								<?php echo esc_html( (string) $foundations_status['classes_completed'] ); ?>
							</span>
							<span class="gym-dashboard__stat-label">
								<?php
								printf(
									/* translators: %d: total classes required */
									esc_html__( 'of %d classes', 'gym-core' ),
									(int) $foundations_status['classes_total_required']
								);
								?>
							</span>
						</div>
						<div class="gym-dashboard__stat">
							<span class="gym-dashboard__stat-value">
								<?php echo esc_html( (string) $foundations_status['coach_rolls_completed'] ); ?>
							</span>
							<span class="gym-dashboard__stat-label">
								<?php
								printf(
									/* translators: %d: coach rolls required */
									esc_html__( 'of %d coach rolls', 'gym-core' ),
									(int) $foundations_status['coach_rolls_required']
								);
								?>
							</span>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<?php
			/**
			 * Fires after the foundations section in the member dashboard.
			 *
			 * @since 3.0.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'gym_core_dashboard_after_foundations', $user_id );
			?>

			<?php // --- Gamification Summary --- ?>
			<div class="gym-dashboard__card gym-dashboard__gamification">
				<h3><?php esc_html_e( 'Your Progress', 'gym-core' ); ?></h3>
				<div class="gym-dashboard__gamification-grid">
					<div class="gym-dashboard__stat">
						<span class="gym-dashboard__stat-value">
							<?php echo esc_html( (string) $total_classes ); ?>
						</span>
						<span class="gym-dashboard__stat-label">
							<?php esc_html_e( 'Total Classes', 'gym-core' ); ?>
						</span>
					</div>
					<div class="gym-dashboard__stat">
						<span class="gym-dashboard__stat-value">
							<?php echo esc_html( (string) $streak_data['current_streak'] ); ?>
						</span>
						<span class="gym-dashboard__stat-label">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: streak status (active, frozen, broken) */
									__( 'Week Streak (%s)', 'gym-core' ),
									$streak_data['streak_status']
								)
							);
							?>
						</span>
					</div>
					<div class="gym-dashboard__stat">
						<span class="gym-dashboard__stat-value">
							<?php echo esc_html( (string) count( $badges ) ); ?>
						</span>
						<span class="gym-dashboard__stat-label">
							<?php esc_html_e( 'Badges Earned', 'gym-core' ); ?>
						</span>
					</div>
				</div>

				<?php if ( empty( $badges ) ) : ?>
					<p class="gym-dashboard__empty-state">
						<?php esc_html_e( 'Start your journey — check in to your first class to earn badges!', 'gym-core' ); ?>
					</p>
				<?php endif; ?>

				<?php if ( 0 === $streak_data['current_streak'] && empty( $badges ) ) : ?>
					<p class="gym-dashboard__cta">
						<a href="<?php echo esc_url( get_post_type_archive_link( ClassPostType::POST_TYPE ) ?: '#' ); ?>" class="button">
							<?php esc_html_e( 'View Class Schedule', 'gym-core' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Fires after the gamification section in the member dashboard.
			 *
			 * @since 3.0.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'gym_core_dashboard_after_gamification', $user_id );
			?>

		</div>
		<?php
	}

	/**
	 * Returns the member's active WooCommerce Subscription.
	 *
	 * @param int $user_id User ID.
	 * @return array{plan_name: string, status: string, next_payment_date: string, amount: string}|null
	 */
	private function get_active_subscription( int $user_id ): ?array {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return null;
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			if ( 'active' === $subscription->get_status() || 'pending-cancel' === $subscription->get_status() ) {
				$next_payment = $subscription->get_date( 'next_payment' );
				$total        = $subscription->get_total();

				$ts = $next_payment ? strtotime( $next_payment ) : false;
				return array(
					'plan_name'         => $this->get_subscription_product_name( $subscription ),
					'status'            => $subscription->get_status(),
					'next_payment_date' => ( $next_payment && false !== $ts )
						? (string) wp_date( get_option( 'date_format' ), $ts )
						: '',
					'amount'            => wp_strip_all_tags( wc_price( (float) $total ) ),
				);
			}
		}

		return null;
	}

	/**
	 * Returns the product name from a subscription's line items.
	 *
	 * @param \WC_Subscription $subscription Subscription object.
	 * @return string
	 */
	private function get_subscription_product_name( $subscription ): string {
		$items = $subscription->get_items();
		$item  = current( $items );

		if ( $item ) {
			return $item->get_name();
		}

		return __( 'Membership', 'gym-core' );
	}

	/**
	 * Returns upcoming classes for the next 7 days at a given location.
	 *
	 * @param string $location Location slug.
	 * @return array<int, array{title: string, day: string, start_time: string, end_time: string, instructor: string}>
	 */
	private function get_upcoming_classes( string $location ): array {
		$args = array(
			'post_type'      => ClassPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gym_class_status',
					'value' => 'active',
				),
			),
		);

		// Filter by location taxonomy if set.
		if ( $location && LocationTaxonomy::is_valid( $location ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => LocationTaxonomy::SLUG,
					'field'    => 'slug',
					'terms'    => $location,
				),
			);
		}

		$query   = new \WP_Query( $args );
		$classes = array();
		$today   = strtolower( gmdate( 'l' ) );
		$days    = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

		// Build a map of day indexes for ordering.
		$today_index = array_search( $today, $days, true );
		if ( false === $today_index ) {
			$today_index = 0;
		}

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$day_of_week   = get_post_meta( $post->ID, '_gym_class_day_of_week', true );
			$start_time    = get_post_meta( $post->ID, '_gym_class_start_time', true );
			$end_time      = get_post_meta( $post->ID, '_gym_class_end_time', true );
			$instructor_id = (int) get_post_meta( $post->ID, '_gym_class_instructor', true );
			$recurrence    = get_post_meta( $post->ID, '_gym_class_recurrence', true ) ?: 'weekly';

			if ( ! $day_of_week || ! $start_time ) {
				continue;
			}

			// Only include weekly classes for simplicity (biweekly needs date math).
			if ( 'weekly' !== $recurrence ) {
				continue;
			}

			$day_index = array_search( $day_of_week, $days, true );
			if ( false === $day_index ) {
				continue;
			}

			// Calculate days until this class (0 = today, 6 = 6 days from now).
			$days_until = ( $day_index - $today_index + 7 ) % 7;

			// Only include classes within the next 7 days.
			if ( $days_until > 6 ) {
				continue;
			}

			$instructor_name = '';
			if ( $instructor_id ) {
				$instructor = get_userdata( $instructor_id );
				if ( $instructor ) {
					$instructor_name = $instructor->display_name;
				}
			}

			$classes[] = array(
				'title'      => get_the_title( $post ),
				'day'        => ucfirst( $day_of_week ),
				'start_time' => $start_time,
				'end_time'   => $end_time ?: '',
				'instructor' => $instructor_name,
				'sort_key'   => $days_until . '-' . $start_time,
			);
		}

		// Sort by day (relative to today), then by time.
		usort(
			$classes,
			function ( array $a, array $b ): int {
				return strcmp( $a['sort_key'], $b['sort_key'] );
			}
		);

		return $classes;
	}

	/**
	 * Enqueues the external CSS for the member dashboard.
	 *
	 * @return void
	 */
	private function render_styles(): void {
		wp_enqueue_style(
			'gym-member-dashboard',
			GYM_CORE_URL . 'assets/css/member-dashboard.css',
			array(),
			GYM_CORE_VERSION
		);
	}
}
