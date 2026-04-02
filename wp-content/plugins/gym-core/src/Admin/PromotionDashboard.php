<?php
/**
 * Promotion Eligibility Dashboard admin page.
 *
 * Provides a WP_List_Table interface for coaches and head coaches to
 * view students approaching or eligible for promotion, set coach
 * recommendations, and execute promotions (stripe or belt).
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Attendance\PromotionEligibility;
use Gym_Core\Location\Taxonomy as LocationTaxonomy;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Rank\RankStore;

/**
 * Registers the Promotion Eligibility submenu page and handles AJAX actions.
 */
final class PromotionDashboard {

	/**
	 * Menu slug for the promotion dashboard.
	 */
	private const MENU_SLUG = 'gym-promotions';

	/**
	 * Parent menu slug (top-level Gym menu registered here).
	 */
	private const PARENT_SLUG = 'gym-core';

	/**
	 * AJAX nonce action.
	 */
	private const NONCE_ACTION = 'gym_promotion_dashboard';

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
	 * Promotion eligibility engine.
	 *
	 * @var PromotionEligibility
	 */
	private PromotionEligibility $eligibility;

	/**
	 * Foundations clearance gate.
	 *
	 * @var FoundationsClearance|null
	 */
	private ?FoundationsClearance $foundations;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore          $attendance  Attendance data store.
	 * @param RankStore                $ranks       Rank data store.
	 * @param PromotionEligibility     $eligibility Promotion eligibility engine.
	 * @param FoundationsClearance|null $foundations Optional Foundations clearance gate.
	 */
	public function __construct(
		AttendanceStore $attendance,
		RankStore $ranks,
		PromotionEligibility $eligibility,
		?FoundationsClearance $foundations = null
	) {
		$this->attendance  = $attendance;
		$this->ranks       = $ranks;
		$this->eligibility = $eligibility;
		$this->foundations = $foundations;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'wp_ajax_gym_recommend_student', array( $this, 'ajax_recommend' ) );
		add_action( 'wp_ajax_gym_promote_student', array( $this, 'ajax_promote' ) );
	}

	/**
	 * Registers the top-level Gym menu and the Promotions submenu page.
	 *
	 * The top-level menu uses a dashicons-awards icon. If another module has
	 * already registered the 'gym-core' menu slug, add_menu_page is a no-op
	 * and this simply adds the submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$hook = add_submenu_page(
			self::PARENT_SLUG,
			__( 'Promotions', 'gym-core' ),
			__( 'Promotions', 'gym-core' ),
			'gym_view_ranks',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, array( $this, 'handle_bulk_actions' ) );
			add_action( 'admin_print_scripts-' . $hook, array( $this, 'enqueue_assets' ) );
		}
	}

	/**
	 * Enqueues the dashboard JavaScript only on this admin page.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_script(
			'gym-admin-promotion',
			GYM_CORE_URL . 'assets/js/admin-promotion.js',
			array( 'jquery' ),
			GYM_CORE_VERSION,
			true
		);

		wp_localize_script( 'gym-admin-promotion', 'gymPromotion', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'i18n'    => array(
				'processing'  => __( 'Processing...', 'gym-core' ),
				'recommended' => __( 'Recommended', 'gym-core' ),
				'error'       => __( 'An error occurred. Please try again.', 'gym-core' ),
			),
		) );
	}

	/**
	 * Renders the promotion eligibility admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Check for bulk confirmation page.
		if ( $this->is_bulk_confirmation_page() ) {
			$this->render_bulk_confirmation();
			return;
		}

		$programs = RankDefinitions::get_programs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_program = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : 'adult-bjj';
		if ( ! isset( $programs[ $current_program ] ) ) {
			$current_program = 'adult-bjj';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_location = isset( $_GET['location'] ) ? sanitize_key( wp_unslash( $_GET['location'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'eligible';

		$table = new PromotionListTable(
			$this->eligibility,
			$this->foundations,
			$current_program,
			$current_location,
			$current_status
		);
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Promotion Eligibility', 'gym-core' ) . '</h1>';
		echo '<hr class="wp-header-end">';

		// Admin notices (after bulk promotions).
		settings_errors( 'gym_promotion_dashboard' );

		// Filter bar.
		$this->render_filters( $programs, $current_program, $current_location, $current_status );

		echo '<form method="post">';
		wp_nonce_field( 'bulk-promotions', '_wpnonce_bulk' );
		$table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Renders the filter bar above the list table.
	 *
	 * @param array<string, string> $programs        All programs.
	 * @param string                $current_program  Currently selected program.
	 * @param string                $current_location Currently selected location.
	 * @param string                $current_status   Currently selected status.
	 * @return void
	 */
	private function render_filters( array $programs, string $current_program, string $current_location, string $current_status ): void {
		$base_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		echo '<div class="tablenav top" style="margin-bottom: 0; padding-bottom: 8px;">';
		echo '<div class="alignleft actions">';

		// Program dropdown.
		echo '<label for="gym-filter-program" class="screen-reader-text">' . esc_html__( 'Filter by program', 'gym-core' ) . '</label>';
		echo '<select id="gym-filter-program" name="program" onchange="this.form.submit();" form="gym-filter-form">';
		foreach ( $programs as $slug => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $slug ),
				selected( $current_program, $slug, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		// Location dropdown.
		$locations = get_terms( array(
			'taxonomy'   => LocationTaxonomy::SLUG,
			'hide_empty' => false,
		) );

		if ( ! is_wp_error( $locations ) && ! empty( $locations ) ) {
			echo '<label for="gym-filter-location" class="screen-reader-text">' . esc_html__( 'Filter by location', 'gym-core' ) . '</label>';
			echo '<select id="gym-filter-location" name="location" onchange="this.form.submit();" form="gym-filter-form">';
			echo '<option value="">' . esc_html__( 'All Locations', 'gym-core' ) . '</option>';
			foreach ( $locations as $location ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $location->slug ),
					selected( $current_location, $location->slug, false ),
					esc_html( $location->name )
				);
			}
			echo '</select>';
		}

		// Status toggle.
		echo '<label for="gym-filter-status" class="screen-reader-text">' . esc_html__( 'Filter by status', 'gym-core' ) . '</label>';
		echo '<select id="gym-filter-status" name="status" onchange="this.form.submit();" form="gym-filter-form">';
		$statuses = array(
			'eligible'    => __( 'Eligible', 'gym-core' ),
			'approaching' => __( 'Approaching', 'gym-core' ),
			'all'         => __( 'All', 'gym-core' ),
		);
		foreach ( $statuses as $slug => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $slug ),
				selected( $current_status, $slug, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		echo '</div>'; // .alignleft.actions
		echo '</div>'; // .tablenav.top

		// Hidden form for the filter dropdowns (GET).
		printf(
			'<form id="gym-filter-form" method="get"><input type="hidden" name="page" value="%s" /></form>',
			esc_attr( self::MENU_SLUG )
		);
	}

	/**
	 * Handles bulk actions from the list table form.
	 *
	 * This runs on the `load-{page}` hook so we can redirect before output.
	 *
	 * @return void
	 */
	public function handle_bulk_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_POST['gym_bulk_action'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce_bulk'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_bulk'] ) ), 'bulk-promotions' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'gym-core' ) );
		}

		if ( ! current_user_can( 'gym_promote_student' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'gym-core' ) );
		}

		$action   = sanitize_key( wp_unslash( $_POST['gym_bulk_action'] ) );
		$user_ids = isset( $_POST['members'] ) ? array_map( 'absint', (array) $_POST['members'] ) : array();
		$program  = isset( $_POST['bulk_program'] ) ? sanitize_key( wp_unslash( $_POST['bulk_program'] ) ) : '';

		if ( empty( $user_ids ) || empty( $program ) ) {
			return;
		}

		// Handle confirmed bulk promotions.
		if ( 'confirm_promote' === $action ) {
			$this->execute_bulk_promotions( $user_ids, $program );
			return;
		}

		// Handle confirmed bulk recommendations.
		if ( 'confirm_recommend' === $action ) {
			$this->execute_bulk_recommendations( $user_ids, $program );
			return;
		}
	}

	/**
	 * Whether we're on the bulk confirmation page.
	 *
	 * @return bool
	 */
	private function is_bulk_confirmation_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['gym_bulk_action'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = sanitize_key( wp_unslash( $_POST['gym_bulk_action'] ) );

		return in_array( $action, array( 'bulk_promote', 'bulk_recommend' ), true );
	}

	/**
	 * Renders the bulk action confirmation page.
	 *
	 * @return void
	 */
	private function render_bulk_confirmation(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce_bulk'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_bulk'] ) ), 'bulk-promotions' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'gym-core' ) );
		}

		if ( ! current_user_can( 'gym_promote_student' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'gym-core' ) );
		}

		$action   = sanitize_key( wp_unslash( $_POST['gym_bulk_action'] ) );
		$user_ids = isset( $_POST['members'] ) ? array_map( 'absint', (array) $_POST['members'] ) : array();
		$program  = isset( $_POST['bulk_program'] ) ? sanitize_key( wp_unslash( $_POST['bulk_program'] ) ) : '';
		$programs = RankDefinitions::get_programs();

		if ( empty( $user_ids ) || empty( $program ) || ! isset( $programs[ $program ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
			exit;
		}

		$is_promote = 'bulk_promote' === $action;
		$confirm_action = $is_promote ? 'confirm_promote' : 'confirm_recommend';

		echo '<div class="wrap">';

		if ( $is_promote ) {
			echo '<h1>' . esc_html__( 'Confirm Bulk Promotions', 'gym-core' ) . '</h1>';
			echo '<p>' . esc_html__( 'The following members will be promoted. Please review before confirming.', 'gym-core' ) . '</p>';
		} else {
			echo '<h1>' . esc_html__( 'Confirm Bulk Recommendations', 'gym-core' ) . '</h1>';
			echo '<p>' . esc_html__( 'The following members will be recommended for promotion. Please review before confirming.', 'gym-core' ) . '</p>';
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'gym-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Current Belt', 'gym-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Target', 'gym-core' ) . '</th>';
		echo '</tr></thead><tbody>';

		$belts   = RankDefinitions::get_belts( $program );
		$belt_map = array();
		foreach ( $belts as $belt ) {
			$belt_map[ $belt['slug'] ] = $belt['name'];
		}

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$rank    = $this->ranks->get_rank( $user_id, $program );
			$current = $rank ? ( $belt_map[ $rank->belt ] ?? $rank->belt ) : __( 'None', 'gym-core' );

			$target = __( 'N/A', 'gym-core' );
			if ( $is_promote && $rank ) {
				$check = $this->eligibility->check( $user_id, $program );
				if ( $check['next_belt'] ) {
					$target = $belt_map[ $check['next_belt'] ] ?? $check['next_belt'];

					// Determine if it's a stripe add or belt promotion.
					$next_belt_def = RankDefinitions::get_next_belt( $program, $rank->belt );
					$max_stripes   = 0;
					foreach ( $belts as $belt ) {
						if ( $belt['slug'] === $rank->belt ) {
							$max_stripes = $belt['max_stripes'];
							break;
						}
					}

					if ( (int) $rank->stripes < $max_stripes ) {
						/* translators: 1: belt name, 2: stripe number */
						$target = sprintf(
							__( '%1$s — Stripe %2$d', 'gym-core' ),
							$current,
							(int) $rank->stripes + 1
						);
					}
				}
			} elseif ( ! $is_promote ) {
				$target = __( 'Coach Recommendation', 'gym-core' );
			}

			echo '<tr>';
			echo '<td>' . esc_html( $user->display_name ) . '</td>';
			echo '<td>' . esc_html( $current ) . '</td>';
			echo '<td>' . esc_html( $target ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Confirmation form.
		echo '<form method="post" style="margin-top: 16px;">';
		wp_nonce_field( 'bulk-promotions', '_wpnonce_bulk' );
		echo '<input type="hidden" name="gym_bulk_action" value="' . esc_attr( $confirm_action ) . '" />';
		echo '<input type="hidden" name="bulk_program" value="' . esc_attr( $program ) . '" />';
		foreach ( $user_ids as $uid ) {
			echo '<input type="hidden" name="members[]" value="' . esc_attr( (string) $uid ) . '" />';
		}

		submit_button(
			$is_promote
				? __( 'Confirm Promotions', 'gym-core' )
				: __( 'Confirm Recommendations', 'gym-core' ),
			'primary',
			'submit',
			true
		);
		echo '</form>';

		printf(
			'<a href="%s" class="button" style="margin-top: 8px;">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&program=' . $program ) ),
			esc_html__( 'Cancel', 'gym-core' )
		);

		echo '</div>';
	}

	/**
	 * Executes confirmed bulk promotions and redirects with a notice.
	 *
	 * @param int[]  $user_ids User IDs to promote.
	 * @param string $program  Program slug.
	 * @return void
	 */
	private function execute_bulk_promotions( array $user_ids, string $program ): void {
		$promoted = 0;
		$failed   = 0;

		foreach ( $user_ids as $user_id ) {
			$rank = $this->ranks->get_rank( $user_id, $program );
			if ( ! $rank ) {
				++$failed;
				continue;
			}

			// Determine whether to add a stripe or promote to next belt.
			$belts       = RankDefinitions::get_belts( $program );
			$max_stripes = 0;
			foreach ( $belts as $belt ) {
				if ( $belt['slug'] === $rank->belt ) {
					$max_stripes = $belt['max_stripes'];
					break;
				}
			}

			if ( (int) $rank->stripes < $max_stripes ) {
				// Add stripe.
				$result = $this->ranks->add_stripe(
					$user_id,
					$program,
					get_current_user_id(),
					__( 'Bulk promotion from dashboard.', 'gym-core' )
				);
			} else {
				// Promote to next belt.
				$next_belt = RankDefinitions::get_next_belt( $program, $rank->belt );
				if ( ! $next_belt ) {
					++$failed;
					continue;
				}
				$result = $this->ranks->promote(
					$user_id,
					$program,
					$next_belt['slug'],
					0,
					get_current_user_id(),
					__( 'Bulk promotion from dashboard.', 'gym-core' )
				);
			}

			if ( $result ) {
				$this->eligibility->clear_recommendation( $user_id, $program );
				++$promoted;
			} else {
				++$failed;
			}
		}

		/* translators: 1: number promoted, 2: number failed */
		$message = sprintf(
			__( '%1$d member(s) promoted successfully. %2$d failed.', 'gym-core' ),
			$promoted,
			$failed
		);

		add_settings_error( 'gym_promotion_dashboard', 'bulk_result', $message, $failed > 0 ? 'warning' : 'success' );
		set_transient( 'settings_errors', get_settings_errors( 'gym_promotion_dashboard' ), 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&program=' . $program . '&settings-updated=true' ) );
		exit;
	}

	/**
	 * Executes confirmed bulk recommendations and redirects with a notice.
	 *
	 * @param int[]  $user_ids User IDs to recommend.
	 * @param string $program  Program slug.
	 * @return void
	 */
	private function execute_bulk_recommendations( array $user_ids, string $program ): void {
		$recommended = 0;

		foreach ( $user_ids as $user_id ) {
			$this->eligibility->set_recommendation( $user_id, $program, get_current_user_id() );
			++$recommended;
		}

		/* translators: %d: number of recommendations set */
		$message = sprintf(
			__( '%d member(s) recommended for promotion.', 'gym-core' ),
			$recommended
		);

		add_settings_error( 'gym_promotion_dashboard', 'bulk_result', $message, 'success' );
		set_transient( 'settings_errors', get_settings_errors( 'gym_promotion_dashboard' ), 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&program=' . $program . '&settings-updated=true' ) );
		exit;
	}

	/**
	 * AJAX handler: set a coach recommendation for a student.
	 *
	 * @return void
	 */
	public function ajax_recommend(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'gym_promote_student' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-core' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$program = isset( $_POST['program'] ) ? sanitize_key( wp_unslash( $_POST['program'] ) ) : '';

		if ( ! $user_id || ! $program ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'gym-core' ) ), 400 );
		}

		$this->eligibility->set_recommendation( $user_id, $program, get_current_user_id() );

		wp_send_json_success( array(
			'message' => __( 'Recommended', 'gym-core' ),
			'user_id' => $user_id,
		) );
	}

	/**
	 * AJAX handler: promote a student (add stripe or promote belt).
	 *
	 * @return void
	 */
	public function ajax_promote(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'gym_promote_student' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-core' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$program = isset( $_POST['program'] ) ? sanitize_key( wp_unslash( $_POST['program'] ) ) : '';

		if ( ! $user_id || ! $program ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'gym-core' ) ), 400 );
		}

		$rank = $this->ranks->get_rank( $user_id, $program );
		if ( ! $rank ) {
			wp_send_json_error( array( 'message' => __( 'No current rank found.', 'gym-core' ) ), 400 );
		}

		// Determine stripe add vs belt promotion.
		$belts       = RankDefinitions::get_belts( $program );
		$max_stripes = 0;
		foreach ( $belts as $belt ) {
			if ( $belt['slug'] === $rank->belt ) {
				$max_stripes = $belt['max_stripes'];
				break;
			}
		}

		if ( (int) $rank->stripes < $max_stripes ) {
			$result = $this->ranks->add_stripe(
				$user_id,
				$program,
				get_current_user_id(),
				__( 'Promoted via dashboard.', 'gym-core' )
			);
			$action_label = __( 'Stripe added', 'gym-core' );
		} else {
			$next_belt = RankDefinitions::get_next_belt( $program, $rank->belt );
			if ( ! $next_belt ) {
				wp_send_json_error( array( 'message' => __( 'Already at highest rank.', 'gym-core' ) ), 400 );
			}
			$result = $this->ranks->promote(
				$user_id,
				$program,
				$next_belt['slug'],
				0,
				get_current_user_id(),
				__( 'Promoted via dashboard.', 'gym-core' )
			);
			$action_label = sprintf(
				/* translators: %s: new belt name */
				__( 'Promoted to %s', 'gym-core' ),
				$next_belt['name']
			);
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Promotion failed.', 'gym-core' ) ), 500 );
		}

		// Clear recommendation after successful promotion.
		$this->eligibility->clear_recommendation( $user_id, $program );

		wp_send_json_success( array(
			'message' => $action_label,
			'user_id' => $user_id,
		) );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom WP_List_Table for promotion eligibility.
 *
 * @since 2.1.0
 */
final class PromotionListTable extends \WP_List_Table {

	/**
	 * Promotion eligibility engine.
	 *
	 * @var PromotionEligibility
	 */
	private PromotionEligibility $eligibility;

	/**
	 * Foundations clearance gate.
	 *
	 * @var FoundationsClearance|null
	 */
	private ?FoundationsClearance $foundations;

	/**
	 * Current program filter.
	 *
	 * @var string
	 */
	private string $program;

	/**
	 * Current location filter.
	 *
	 * @var string
	 */
	private string $location;

	/**
	 * Current status filter: 'eligible', 'approaching', or 'all'.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Belt definitions for the current program (cached).
	 *
	 * @var array<string, array{name: string, slug: string, color: string, max_stripes: int, type: string}>
	 */
	private array $belt_map = array();

	/**
	 * Constructor.
	 *
	 * @param PromotionEligibility     $eligibility Promotion eligibility engine.
	 * @param FoundationsClearance|null $foundations Foundations clearance gate.
	 * @param string                   $program     Program slug filter.
	 * @param string                   $location    Location slug filter.
	 * @param string                   $status      Status filter.
	 */
	public function __construct(
		PromotionEligibility $eligibility,
		?FoundationsClearance $foundations,
		string $program,
		string $location,
		string $status
	) {
		parent::__construct( array(
			'singular' => 'member',
			'plural'   => 'members',
			'ajax'     => false,
		) );

		$this->eligibility = $eligibility;
		$this->foundations = $foundations;
		$this->program     = $program;
		$this->location    = $location;
		$this->status      = $status;

		// Build belt map for display.
		$belts = RankDefinitions::get_belts( $program );
		foreach ( $belts as $belt ) {
			$this->belt_map[ $belt['slug'] ] = $belt;
		}
	}

	/**
	 * Returns the columns for the table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'name'           => __( 'Name', 'gym-core' ),
			'program'        => __( 'Program', 'gym-core' ),
			'current_belt'   => __( 'Current Belt', 'gym-core' ),
			'stripes'        => __( 'Stripes', 'gym-core' ),
			'attendance'     => __( 'Attendance', 'gym-core' ),
			'days_at_rank'   => __( 'Days at Rank', 'gym-core' ),
			'recommendation' => '<abbr title="' . esc_attr__( 'Coach Recommendation', 'gym-core' ) . '">' . esc_html__( "Rec'd", 'gym-core' ) . '</abbr>',
			'actions'        => __( 'Actions', 'gym-core' ),
		);
	}

	/**
	 * Returns sortable columns definition.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'         => array( 'name', false ),
			'attendance'   => array( 'attendance', true ),
			'days_at_rank' => array( 'days_at_rank', false ),
		);
	}

	/**
	 * Returns the list of bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		$actions = array();

		if ( current_user_can( 'gym_promote_student' ) ) {
			$actions['bulk_promote']   = __( 'Promote Selected', 'gym-core' );
			$actions['bulk_recommend'] = __( 'Recommend Selected', 'gym-core' );
		}

		return $actions;
	}

	/**
	 * Renders the bulk actions dropdown and hidden fields.
	 *
	 * Overrides parent to add program hidden field.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function bulk_actions( $which = '' ): void {
		// Only render on top.
		if ( 'top' !== $which ) {
			return;
		}

		$actions = $this->get_bulk_actions();
		if ( empty( $actions ) ) {
			return;
		}

		echo '<label for="gym-bulk-action" class="screen-reader-text">' . esc_html__( 'Bulk actions', 'gym-core' ) . '</label>';
		echo '<select name="gym_bulk_action" id="gym-bulk-action">';
		echo '<option value="">' . esc_html__( 'Bulk Actions', 'gym-core' ) . '</option>';
		foreach ( $actions as $value => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<input type="hidden" name="bulk_program" value="' . esc_attr( $this->program ) . '" />';

		submit_button( __( 'Apply', 'gym-core' ), 'action', '', false );
	}

	/**
	 * Prepares items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns.
			$this->get_sortable_columns(),
		);

		$members = $this->eligibility->get_eligible_members( $this->program );

		// Merge Foundations students for "approaching" and "all" views.
		if ( in_array( $this->status, array( 'approaching', 'all' ), true ) && 'adult-bjj' === $this->program && null !== $this->foundations ) {
			$members = $this->merge_foundations_students( $members );
		}

		// Filter by status.
		if ( 'eligible' === $this->status ) {
			$members = array_filter( $members, static fn( array $m ): bool => ! empty( $m['eligible'] ) );
		}

		// Filter by location (user meta).
		if ( '' !== $this->location ) {
			$members = array_filter(
				$members,
				function ( array $m ): bool {
					$user_location = get_user_meta( $m['user_id'], 'gym_location', true );
					return $user_location === $this->location;
				}
			);
		}

		// Sort.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'attendance';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'asc' : 'desc';

		usort(
			$members,
			static function ( array $a, array $b ) use ( $orderby, $order ): int {
				$cmp = 0;
				switch ( $orderby ) {
					case 'name':
						$cmp = strcasecmp( $a['display_name'], $b['display_name'] );
						break;
					case 'days_at_rank':
						$cmp = $a['days_at_rank'] <=> $b['days_at_rank'];
						break;
					case 'attendance':
					default:
						$cmp = $a['attendance_count'] <=> $b['attendance_count'];
						break;
				}
				return 'asc' === $order ? $cmp : -$cmp;
			}
		);

		// Pagination.
		$per_page = 25;
		$total    = count( $members );
		$page     = $this->get_pagenum();
		$offset   = ( $page - 1 ) * $per_page;

		$this->items = array_slice( array_values( $members ), $offset, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );
	}

	/**
	 * Merges Foundations-enrolled students into the member list.
	 *
	 * Foundations students are Adult BJJ white belts who are still in
	 * Foundations. They appear with an in_foundations flag so the row
	 * renderer can apply amber highlighting and Foundations status text.
	 *
	 * @param array<int, array> $members Existing eligible/approaching members.
	 * @return array<int, array> Members with Foundations students merged.
	 */
	private function merge_foundations_students( array $members ): array {
		global $wpdb;
		$tables = \Gym_Core\Data\TableManager::get_table_names();

		// Find all Adult BJJ white belts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$white_belts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, belt, stripes, promoted_at FROM {$tables['ranks']} WHERE program = %s AND belt = %s",
				'adult-bjj',
				'white'
			)
		) ?: array();

		$existing_ids = array_column( $members, 'user_id' );

		foreach ( $white_belts as $wb ) {
			$uid = (int) $wb->user_id;
			if ( in_array( $uid, $existing_ids, true ) ) {
				continue;
			}

			if ( null === $this->foundations ) {
				continue;
			}

			$status = $this->foundations->get_status( $uid );
			if ( ! $status['in_foundations'] || $status['cleared'] ) {
				continue;
			}

			$user     = get_userdata( $uid );
			$members[] = array(
				'user_id'              => $uid,
				'display_name'         => $user ? $user->display_name : "User #{$uid}",
				'belt'                 => 'white',
				'stripes'              => (int) $wb->stripes,
				'eligible'             => false,
				'attendance_count'     => 0,
				'attendance_required'  => 0,
				'days_at_rank'         => 0,
				'days_required'        => 0,
				'has_recommendation'   => false,
				'next_belt'            => null,
				'in_foundations'       => true,
				'foundations_phase'    => $status['phase'],
			);
		}

		return $members;
	}

	/**
	 * Renders the checkbox column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		// Foundations students cannot be selected for bulk actions.
		if ( ! empty( $item['in_foundations'] ) ) {
			return '';
		}

		return sprintf(
			'<input type="checkbox" name="members[]" value="%d" />',
			$item['user_id']
		);
	}

	/**
	 * Renders the Name column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_name( $item ): string {
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_user_link( $item['user_id'] ) ),
			esc_html( $item['display_name'] )
		);
	}

	/**
	 * Renders the Program column with a belt color circle.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_program( $item ): string {
		$programs = RankDefinitions::get_programs();
		$label    = $programs[ $this->program ] ?? $this->program;
		$color    = $this->get_belt_color( $item['belt'] );

		$border = '#ffffff' === strtolower( $color ) ? '1px solid #ccc' : 'none';

		return sprintf(
			'<span style="display:inline-block;width:16px;height:16px;border-radius:50%%;background:%s;border:%s;vertical-align:middle;margin-right:6px;" aria-hidden="true"></span>%s',
			esc_attr( $color ),
			esc_attr( $border ),
			esc_html( $label )
		);
	}

	/**
	 * Renders the Current Belt column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_current_belt( $item ): string {
		$belt = $this->belt_map[ $item['belt'] ] ?? null;
		return esc_html( $belt ? $belt['name'] : $item['belt'] );
	}

	/**
	 * Renders the Stripes column as filled/empty dots.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_stripes( $item ): string {
		$belt = $this->belt_map[ $item['belt'] ] ?? null;
		if ( ! $belt || 0 === $belt['max_stripes'] ) {
			return '<span aria-label="' . esc_attr__( 'No stripe system', 'gym-core' ) . '">&mdash;</span>';
		}

		$current = (int) $item['stripes'];
		$max     = $belt['max_stripes'];
		$html    = '';

		for ( $i = 1; $i <= $max; $i++ ) {
			if ( $i <= $current ) {
				$html .= '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#333;margin-right:3px;" aria-hidden="true"></span>';
			} else {
				$html .= '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ddd;margin-right:3px;" aria-hidden="true"></span>';
			}
		}

		/* translators: 1: current stripes, 2: max stripes */
		$label = sprintf(
			__( '%1$d of %2$d stripes', 'gym-core' ),
			$current,
			$max
		);

		return '<span aria-label="' . esc_attr( $label ) . '">' . $html . '</span>';
	}

	/**
	 * Renders the Attendance column with a progress element.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_attendance( $item ): string {
		$count    = (int) $item['attendance_count'];
		$required = (int) $item['attendance_required'];

		if ( 0 === $required ) {
			return esc_html( (string) $count );
		}

		return sprintf(
			'<progress value="%d" max="%d" style="width:80px;vertical-align:middle;"></progress> <span>%d/%d</span>',
			min( $count, $required ),
			$required,
			$count,
			$required
		);
	}

	/**
	 * Renders the Days at Rank column with a progress element.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_days_at_rank( $item ): string {
		$days     = (int) $item['days_at_rank'];
		$required = (int) $item['days_required'];

		if ( 0 === $required ) {
			return esc_html( (string) $days );
		}

		return sprintf(
			'<progress value="%d" max="%d" style="width:80px;vertical-align:middle;"></progress> <span>%d/%d</span>',
			min( $days, $required ),
			$required,
			$days,
			$required
		);
	}

	/**
	 * Renders the Recommendation column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_recommendation( $item ): string {
		if ( ! empty( $item['has_recommendation'] ) ) {
			return '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" aria-label="' . esc_attr__( 'Recommended', 'gym-core' ) . '"></span>';
		}

		return '';
	}

	/**
	 * Renders the Actions column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_actions( $item ): string {
		// Foundations students get status text instead of buttons.
		if ( ! empty( $item['in_foundations'] ) ) {
			$phase_label = $this->get_foundations_phase_label( $item['foundations_phase'] ?? '' );
			return '<em style="color:#b45309;">' . esc_html(
				sprintf(
					/* translators: %s: Foundations phase label */
					__( 'In Foundations -- %s', 'gym-core' ),
					$phase_label
				)
			) . '</em>';
		}

		$html = '';

		// Recommend button (coaches and head coaches).
		if ( current_user_can( 'gym_promote_student' ) && empty( $item['has_recommendation'] ) ) {
			$html .= sprintf(
				'<button type="button" class="button button-small gym-recommend-btn" data-user-id="%d" data-program="%s">%s</button> ',
				$item['user_id'],
				esc_attr( $this->program ),
				esc_html__( 'Recommend', 'gym-core' )
			);
		}

		// Promote button (head coaches and admins).
		if ( current_user_can( 'gym_promote_student' ) && ! empty( $item['eligible'] ) ) {
			$html .= sprintf(
				'<button type="button" class="button button-primary button-small gym-promote-btn" data-user-id="%d" data-program="%s">%s</button>',
				$item['user_id'],
				esc_attr( $this->program ),
				esc_html__( 'Promote', 'gym-core' )
			);
		}

		return $html;
	}

	/**
	 * Returns the default column value.
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column identifier.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	/**
	 * Generates row HTML with Foundations amber highlight.
	 *
	 * @param array $item Row data.
	 * @return void
	 */
	public function single_row( $item ): void {
		$classes = '';
		$style   = '';

		if ( ! empty( $item['in_foundations'] ) ) {
			$style = 'background-color: #fff3cd;';
		}

		printf( '<tr%s%s>', $style ? ' style="' . esc_attr( $style ) . '"' : '', $classes ? ' class="' . esc_attr( $classes ) . '"' : '' );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Returns the hex color for a belt slug.
	 *
	 * @param string $belt_slug Belt slug.
	 * @return string Hex color code.
	 */
	private function get_belt_color( string $belt_slug ): string {
		return $this->belt_map[ $belt_slug ]['color'] ?? '#cccccc';
	}

	/**
	 * Returns a human-readable label for a Foundations phase.
	 *
	 * @param string $phase Phase identifier.
	 * @return string
	 */
	private function get_foundations_phase_label( string $phase ): string {
		switch ( $phase ) {
			case 'phase1':
				return __( 'Phase 1', 'gym-core' );
			case 'phase2_coach_rolls':
				return __( 'Phase 2', 'gym-core' );
			case 'phase3':
				return __( 'Phase 3', 'gym-core' );
			case 'ready_to_clear':
				return __( 'Ready to Clear', 'gym-core' );
			default:
				return __( 'Enrolled', 'gym-core' );
		}
	}

	/**
	 * Message displayed when no items are available.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No members match the current filters.', 'gym-core' );
	}
}
