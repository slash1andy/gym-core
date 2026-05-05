<?php
/**
 * Coach Briefings admin page (Gym → Coach Briefings).
 *
 * Today / This week views over BriefingGenerator. Reuses BriefingRenderer
 * so the admin and front-end magic-link surfaces always agree visually.
 *
 * Capability gate: `gym_view_briefing` (granted to gym_coach,
 * gym_head_coach, shop_manager, administrator). Coaches see only their
 * own classes (instructor meta == current user); head coaches and admins
 * see all classes.
 *
 * @package Gym_Core\Admin
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

use Gym_Core\Briefing\BriefingGenerator;
use Gym_Core\Briefing\BriefingRenderer;
use Gym_Core\Briefing\MagicLink;
use Gym_Core\Schedule\ClassPostType;

/**
 * Renders the wp-admin Coach Briefings dashboard page.
 */
final class CoachBriefingsPage {

	/**
	 * Submenu slug.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'gym-coach-briefings';

	/**
	 * Briefing generator.
	 *
	 * @var BriefingGenerator
	 */
	private BriefingGenerator $generator;

	/**
	 * Briefing renderer.
	 *
	 * @var BriefingRenderer
	 */
	private BriefingRenderer $renderer;

	/**
	 * Hook suffix returned by add_submenu_page.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param BriefingGenerator $generator Briefing generator.
	 * @param BriefingRenderer  $renderer  Briefing renderer.
	 */
	public function __construct( BriefingGenerator $generator, BriefingRenderer $renderer ) {
		$this->generator = $generator;
		$this->renderer  = $renderer;
	}

	/**
	 * Registers menu + asset hooks.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the submenu page under the Gym top-level menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'gym-core',
			__( 'Coach Briefings', 'gym-core' ),
			__( 'Coach Briefings', 'gym-core' ),
			'gym_view_briefing',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Inline-prints brand CSS on the briefing admin page only.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}
		wp_register_style( 'gym-coach-briefings-inline', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		wp_enqueue_style( 'gym-coach-briefings-inline' );
		wp_add_inline_style( 'gym-coach-briefings-inline', BriefingRenderer::get_inline_css() );
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'gym_view_briefing' ) ) {
			wp_die( esc_html__( 'You do not have permission to view briefings.', 'gym-core' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$view  = isset( $_GET['view'] ) && 'week' === $_GET['view'] ? 'week' : 'today';
		$today = $this->visible_class_ids_for_today();
		$week  = 'week' === $view ? $this->visible_class_ids_for_week() : array();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Coach Briefings', 'gym-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Pre-class intelligence for every class on your schedule.', 'gym-core' ) . '</p>';

		echo '<nav class="nav-tab-wrapper">';
		printf(
			'<a href="%s" class="nav-tab %s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
			'today' === $view ? 'nav-tab-active' : '',
			esc_html__( 'Today', 'gym-core' )
		);
		printf(
			'<a href="%s" class="nav-tab %s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&view=week' ) ),
			'week' === $view ? 'nav-tab-active' : '',
			esc_html__( 'This Week', 'gym-core' )
		);
		echo '</nav>';

		if ( 'today' === $view ) {
			$this->render_class_list( $today, true );
		} else {
			$this->render_week_view( $week );
		}

		echo '</div>';
	}

	/**
	 * Renders a stack of briefing cards for the given class IDs.
	 *
	 * @param array<int, int> $class_ids Class post IDs.
	 * @param bool            $expanded  When true, render the full briefing inline (today view).
	 * @return void
	 */
	private function render_class_list( array $class_ids, bool $expanded ): void {
		if ( empty( $class_ids ) ) {
			echo '<p class="description" style="margin-top:1em;">' . esc_html__( 'No classes found for this view.', 'gym-core' ) . '</p>';
			return;
		}

		foreach ( $class_ids as $class_id ) {
			$briefing = $this->generator->generate( (int) $class_id );
			if ( is_wp_error( $briefing ) ) {
				continue;
			}

			$share_url = MagicLink::url( (int) $class_id, get_current_user_id() );

			echo '<div style="margin-top:1.5em;background:#F4F1EC;padding:1px;border-radius:8px;">';
			if ( $expanded ) {
				echo $this->renderer->render( $briefing, 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				$this->render_briefing_summary( $briefing, $class_id );
			}
			echo '<p style="padding:0 1.5rem 1rem;">';
			echo '<a class="button" href="' . esc_url( $share_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open shareable link', 'gym-core' ) . '</a> ';
			echo '<code style="background:#fff;padding:0.25rem 0.5rem;border-radius:4px;font-size:0.75rem;">' . esc_html( $share_url ) . '</code>';
			echo '</p>';
			echo '</div>';
		}
	}

	/**
	 * Renders a compact one-line briefing summary (used in week view).
	 *
	 * @param array $briefing Briefing DTO.
	 * @param int   $class_id Class ID.
	 * @return void
	 */
	private function render_briefing_summary( array $briefing, int $class_id ): void {
		$class      = $briefing['class'];
		$alerts     = $briefing['alerts'] ?? array();
		$alert_n    = count( $alerts );
		$roster_n   = count( $briefing['roster'] ?? array() );

		echo '<div style="padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">';
		echo '<div><strong>' . esc_html( (string) ( $class['name'] ?? '' ) ) . '</strong>';
		echo ' <span class="description">' . esc_html( ucfirst( (string) ( $class['day_of_week'] ?? '' ) ) ) . ' ' . esc_html( (string) ( $class['start_time'] ?? '' ) ) . ' | ' . esc_html( (string) ( $class['location'] ?? '' ) ) . '</span></div>';
		echo '<div>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&class_id=' . $class_id ) ) . '">' . esc_html(
			sprintf(
				/* translators: 1: roster count, 2: alert count */
				__( 'Roster %1$d / Alerts %2$d', 'gym-core' ),
				$roster_n,
				$alert_n
			)
		) . '</a>';
		echo '</div></div>';
	}

	/**
	 * Renders the week view (today + next 6 days).
	 *
	 * @param array<int, int> $class_ids Class IDs (unused — week renders day-by-day).
	 * @return void
	 */
	private function render_week_view( array $class_ids ): void {
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		echo '<p class="description" style="margin-top:1em;">' . esc_html__( 'Compact summary of every class scheduled this week. Click a class for the full briefing.', 'gym-core' ) . '</p>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;

		if ( $selected > 0 && in_array( $selected, $class_ids, true ) ) {
			$briefing = $this->generator->generate( $selected );
			if ( ! is_wp_error( $briefing ) ) {
				echo '<div style="margin-top:1em;">';
				echo $this->renderer->render( $briefing, 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</div>';
			}
		}

		$this->render_class_list( $class_ids, false );
	}

	/**
	 * Returns class IDs scheduled today, filtered for the current user.
	 *
	 * @return array<int, int>
	 */
	private function visible_class_ids_for_today(): array {
		$ids = $this->generator->get_todays_classes();
		return $this->filter_by_visibility( $ids );
	}

	/**
	 * Returns class IDs scheduled this week (today + 6 days).
	 *
	 * @return array<int, int>
	 */
	private function visible_class_ids_for_week(): array {
		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$days[] = strtolower( gmdate( 'l', strtotime( "+{$i} days" ) ?: time() ) );
		}
		$days = array_unique( $days );

		$query_args = array(
			'post_type'      => ClassPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_gym_class_day_of_week',
					'value'   => $days,
					'compare' => 'IN',
				),
				array(
					'key'   => '_gym_class_status',
					'value' => 'active',
				),
			),
		);

		$query = new \WP_Query( $query_args );
		$ids   = array_map( static fn( $p ) => $p instanceof \WP_Post ? $p->ID : (int) $p, $query->posts );

		return $this->filter_by_visibility( $ids );
	}

	/**
	 * Restricts the class list to the current user's classes when they are
	 * a coach (not a head coach or admin).
	 *
	 * @param array<int, int> $class_ids Class IDs.
	 * @return array<int, int>
	 */
	private function filter_by_visibility( array $class_ids ): array {
		$user = wp_get_current_user();
		if ( ! $user || 0 === $user->ID ) {
			return array();
		}

		// Admins, shop managers, and head coaches see everything.
		if (
			user_can( $user, 'manage_options' )
			|| user_can( $user, 'manage_woocommerce' )
			|| in_array( 'gym_head_coach', (array) $user->roles, true )
		) {
			return $class_ids;
		}

		$visible = array();
		foreach ( $class_ids as $class_id ) {
			$instructor_id = (int) get_post_meta( $class_id, '_gym_class_instructor', true );
			if ( $instructor_id === (int) $user->ID ) {
				$visible[] = $class_id;
			}
		}
		return $visible;
	}
}
