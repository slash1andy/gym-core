<?php
/**
 * Coach Attendance Dashboard — wp-admin page.
 *
 * Provides a full-screen dashboard for coaches with three tabs:
 *   Today  — live check-in view with per-class breakdowns
 *   History — filterable WP_List_Table of all attendance records
 *   Trends  — week-over-week stats and at-risk member alerts
 *
 * Registered under a top-level "Gym" menu with dashicons-awards.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\CheckInValidator;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Data\TableManager;
use Gym_Core\Schedule\ClassPostType;

/**
 * Registers the Gym admin menu and renders the Attendance Dashboard.
 */
final class AttendanceDashboard {

	/**
	 * Menu slug for the top-level Gym page.
	 *
	 * @var string
	 */
	private const MENU_SLUG = 'gym-attendance';

	/**
	 * User meta key for persisted location preference.
	 *
	 * @var string
	 */
	private const LOCATION_META = '_gym_dashboard_location';

	/**
	 * Available locations.
	 *
	 * @var array<string, string>
	 */
	private const LOCATIONS = array(
		'rockford' => 'Rockford',
		'beloit'   => 'Beloit',
	);

	/**
	 * Attendance data store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $store;

	/**
	 * Check-in validator.
	 *
	 * @var CheckInValidator
	 */
	private CheckInValidator $validator;

	/**
	 * Hook suffix returned by add_menu_page, used for enqueue targeting.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Hook suffix for the submenu page.
	 *
	 * @var string
	 */
	private string $submenu_hook = '';

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore  $store     Attendance data store.
	 * @param CheckInValidator $validator Check-in validator.
	 */
	public function __construct( AttendanceStore $store, CheckInValidator $validator ) {
		$this->store     = $store;
		$this->validator = $validator;
	}

	/**
	 * Registers all hooks for the dashboard.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_gym_quick_checkin', array( $this, 'ajax_quick_checkin' ) );
		add_action( 'wp_ajax_gym_member_search', array( $this, 'ajax_member_search' ) );
	}

	// ─── Menu registration ──────────────────────────────────────────

	/**
	 * Registers the top-level Gym menu and Attendance submenu.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Register the shared top-level Gym menu (first registrant wins).
		add_menu_page(
			__( 'Gym Dashboard', 'gym-core' ),
			__( 'Gym', 'gym-core' ),
			'gym_check_in_member',
			'gym-core',
			'__return_null',
			'dashicons-awards',
			3
		);

		$this->hook_suffix = add_submenu_page(
			'gym-core',
			__( 'Attendance', 'gym-core' ),
			__( 'Attendance', 'gym-core' ),
			'gym_check_in_member',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);

		$this->submenu_hook = $this->hook_suffix;
	}

	// ─── Asset enqueueing ───────────────────────────────────────────

	/**
	 * Enqueues CSS and JS only on this dashboard page.
	 *
	 * @since 2.1.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix && $hook_suffix !== $this->submenu_hook ) {
			return;
		}

		wp_enqueue_style(
			'gym-admin-dashboard',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin-gym.css',
			array(),
			'2.1.0'
		);

		wp_enqueue_script(
			'gym-admin-dashboard',
			'', // Inline script — no external file needed.
			array( 'jquery' ),
			'2.1.0',
			true
		);

		wp_localize_script(
			'gym-admin-dashboard',
			'gymDashboard',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'checkinNonce'  => wp_create_nonce( 'gym_quick_checkin' ),
				'searchNonce'   => wp_create_nonce( 'gym_member_search' ),
				'locationNonce' => wp_create_nonce( 'gym_set_location' ),
			)
		);

		$this->print_inline_script();
	}

	/**
	 * Prints the inline JavaScript for typeahead search and Quick Check-In.
	 *
	 * @return void
	 */
	private function print_inline_script(): void {
		add_action(
			'admin_footer',
			static function (): void {
				?>
				<script>
				// TODO: Move this inline JS to an external file (e.g., assets/js/admin-attendance.js) — see Code-22.
				(function($) {
					'use strict';

					/* ── Typeahead member search ────────────────────────────── */
					var $input   = $('#gym-member-search'),
						$results = $('#gym-member-results'),
						$hidden  = $('#gym-member-id'),
						timer    = null,
						idx      = -1;

					function setExpanded(expanded) {
						$input.attr('aria-expanded', expanded ? 'true' : 'false');
					}

					$input.on('input', function() {
						clearTimeout(timer);
						var q = $.trim($(this).val());
						if (q.length < 2) { $results.removeClass('visible').empty(); setExpanded(false); return; }
						timer = setTimeout(function() {
							$.post(gymDashboard.ajaxUrl, {
								action: 'gym_member_search',
								_ajax_nonce: gymDashboard.searchNonce,
								q: q
							}, function(res) {
								if (!res.success || !res.data.length) {
									$results.html('<div class="typeahead-no-results">No members found</div>').addClass('visible');
									setExpanded(true);
									return;
								}
								var html = '';
								$.each(res.data, function(i, m) {
									html += '<div class="typeahead-item" role="option" data-id="' + m.id + '">' + $('<span>').text(m.name).html() + '</div>';
								});
								$results.html(html).addClass('visible');
								setExpanded(true);
								idx = -1;
							});
						}, 250);
					});

					/* Keyboard nav */
					$input.on('keydown', function(e) {
						var $items = $results.find('.typeahead-item');
						if (!$items.length) return;
						if (e.keyCode === 40) { idx = Math.min(idx + 1, $items.length - 1); }
						else if (e.keyCode === 38) { idx = Math.max(idx - 1, 0); }
						else if (e.keyCode === 13 && idx >= 0) { e.preventDefault(); $items.eq(idx).trigger('click'); return; }
						else { return; }
						e.preventDefault();
						$items.removeClass('selected').eq(idx).addClass('selected');
					});

					$(document).on('click', '.typeahead-item', function() {
						$hidden.val($(this).data('id'));
						$input.val($(this).text());
						$results.removeClass('visible').empty();
						setExpanded(false);
					});

					$(document).on('click', function(e) {
						if (!$(e.target).closest('.gym-typeahead-wrap').length) {
							$results.removeClass('visible');
							setExpanded(false);
						}
					});

					/* ── Quick Check-In AJAX ────────────────────────────────── */
					$('#gym-quick-checkin-form').on('submit', function(e) {
						e.preventDefault();
						var $msg = $(this).find('.gym-checkin-msg'),
							userId  = $hidden.val(),
							classId = $(this).find('[name="class_id"]').val(),
							loc     = $(this).find('[name="location"]').val();

						if (!userId) { $msg.addClass('error').text('Select a member first.'); return; }

						$msg.removeClass('error').text('Checking in...');

						$.post(gymDashboard.ajaxUrl, {
							action:      'gym_quick_checkin',
							_ajax_nonce: gymDashboard.checkinNonce,
							user_id:     userId,
							class_id:    classId,
							location:    loc
						}, function(res) {
							if (res.success) {
								$msg.removeClass('error').text(res.data.message);
								$hidden.val('');
								$input.val('');
								/* Reload after short delay so coach sees the updated list */
								setTimeout(function() { location.reload(); }, 1200);
							} else {
								$msg.addClass('error').text(res.data.message || 'Check-in failed.');
							}
						}).fail(function() {
							$msg.addClass('error').text('Network error — try again.');
						});
					});

					/* ── Location toggle ─────────────────────────────────────── */
					$('.gym-loc-btn').on('click', function() {
						var loc = $(this).data('location'),
							url = new URL(window.location.href);
						url.searchParams.set('location', loc);
						window.location.href = url.toString();
					});
				})(jQuery);
				</script>
				<?php
			}
		);
	}

	// ─── AJAX: Quick Check-In ───────────────────────────────────────

	/**
	 * Handles the Quick Check-In AJAX request.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function ajax_quick_checkin(): void {
		check_ajax_referer( 'gym_quick_checkin' );

		if ( ! current_user_can( 'gym_check_in_member' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-core' ) ), 403 );
		}

		$user_id  = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( wp_unslash( $_POST['class_id'] ) ) : 0;
		$location = isset( $_POST['location'] ) ? sanitize_key( wp_unslash( $_POST['location'] ) ) : '';

		if ( 0 === $user_id || '' === $location ) {
			wp_send_json_error( array( 'message' => __( 'Member and location are required.', 'gym-core' ) ) );
		}

		$valid = $this->validator->validate( $user_id, $class_id, $location );

		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $valid->get_error_messages() ) ) );
		}

		$result = $this->store->record_checkin( $user_id, $location, $class_id, 'manual' );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Database error — check-in not recorded.', 'gym-core' ) ) );
		}

		$user = get_userdata( $user_id );
		wp_send_json_success(
			array(
				/* translators: %s: member display name */
				'message' => sprintf( __( '%s checked in successfully.', 'gym-core' ), $user ? $user->display_name : '#' . $user_id ),
			)
		);
	}

	// ─── AJAX: Member search (typeahead) ────────────────────────────

	/**
	 * Returns matching members for the typeahead search input.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function ajax_member_search(): void {
		check_ajax_referer( 'gym_member_search' );

		if ( ! current_user_can( 'gym_check_in_member' ) ) {
			wp_send_json_error( array(), 403 );
		}

		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';

		if ( strlen( $q ) < 2 ) {
			wp_send_json_success( array() );
		}

		$users = get_users(
			array(
				'search'  => '*' . $q . '*',
				'number'  => 10,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => (int) $user->ID,
				'name' => $user->display_name,
			);
		}

		wp_send_json_success( $results );
	}

	// ─── Page rendering ─────────────────────────────────────────────

	/**
	 * Renders the full dashboard page.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Persist location preference.
		$location = $this->get_current_location();

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'today'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap gym-dashboard-wrap">';
		echo '<h1>' . esc_html__( 'Attendance Dashboard', 'gym-core' ) . '</h1>';

		$this->render_tabs( $tab );

		switch ( $tab ) {
			case 'history':
				$this->render_history_tab();
				break;
			case 'trends':
				$this->render_trends_tab();
				break;
			default:
				$this->render_today_tab( $location );
				break;
		}

		echo '</div>';
	}

	/**
	 * Renders the tab navigation.
	 *
	 * @param string $active Current tab slug.
	 * @return void
	 */
	private function render_tabs( string $active ): void {
		$tabs = array(
			'today'   => __( 'Today', 'gym-core' ),
			'history' => __( 'History', 'gym-core' ),
			'trends'  => __( 'Trends', 'gym-core' ),
		);

		$base = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		echo '<nav class="gym-tabs">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg( 'tab', $slug, $base );
			$class = ( $slug === $active ) ? 'active' : '';
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	// ─── Today tab ──────────────────────────────────────────────────

	/**
	 * Renders the Today tab content.
	 *
	 * @param string $location Current location slug.
	 * @return void
	 */
	private function render_today_tab( string $location ): void {
		$records = $this->store->get_today_by_location( $location );

		// Group records by class.
		$by_class = array();
		$walk_ins = array();

		foreach ( $records as $record ) {
			$cid = (int) $record->class_id;
			if ( 0 === $cid ) {
				$walk_ins[] = $record;
			} else {
				if ( ! isset( $by_class[ $cid ] ) ) {
					$by_class[ $cid ] = array();
				}
				$by_class[ $cid ][] = $record;
			}
		}

		// Count today's distinct classes with check-ins.
		$classes_today = count( $by_class );

		// Location toggle.
		$this->render_location_toggle( $location );

		// Stat cards.
		echo '<div class="gym-stat-cards">';
		$this->render_stat_card( (string) count( $records ), __( 'Checked In Today', 'gym-core' ) );
		$this->render_stat_card( (string) $classes_today, __( 'Classes Today', 'gym-core' ) );
		$this->render_stat_card( (string) count( $walk_ins ), __( 'Walk-ins', 'gym-core' ) );
		echo '</div>';

		// Get all active classes for this location & day.
		$today_day = strtolower( gmdate( 'l' ) );
		$classes   = $this->get_classes_for_day( $today_day, $location );

		// Render each class section.
		foreach ( $classes as $class ) {
			$class_id   = (int) $class->ID;
			$checkins   = $by_class[ $class_id ] ?? array();
			$start_time = get_post_meta( $class_id, '_gym_class_start_time', true );
			$end_time   = get_post_meta( $class_id, '_gym_class_end_time', true );

			$this->render_class_section( $class, $checkins, $start_time, $end_time );

			// Remove from by_class so we can show orphan check-ins later.
			unset( $by_class[ $class_id ] );
		}

		// Render any check-ins for classes not in today's schedule (edge case).
		foreach ( $by_class as $class_id => $checkins ) {
			$class_post = get_post( $class_id );
			if ( $class_post ) {
				$start_time = get_post_meta( $class_id, '_gym_class_start_time', true );
				$end_time   = get_post_meta( $class_id, '_gym_class_end_time', true );
				$this->render_class_section( $class_post, $checkins, $start_time, $end_time );
			}
		}

		// Walk-ins section.
		if ( ! empty( $walk_ins ) ) {
			echo '<details class="gym-class-section" open>';
			echo '<summary>';
			echo esc_html__( 'Walk-ins (No Class)', 'gym-core' );
			echo '<span class="class-count">' . esc_html( (string) count( $walk_ins ) ) . '</span>';
			echo '</summary>';
			$this->render_checkin_table( $walk_ins );
			echo '</details>';
		}

		// Quick Check-In bar.
		$this->render_quick_checkin( $location, $classes );
	}

	/**
	 * Renders the location toggle buttons.
	 *
	 * @param string $active Active location slug.
	 * @return void
	 */
	private function render_location_toggle( string $active ): void {
		echo '<div class="gym-location-toggle">';
		foreach ( self::LOCATIONS as $slug => $label ) {
			$class = ( $slug === $active ) ? 'gym-loc-btn active' : 'gym-loc-btn';
			printf(
				'<button type="button" class="%s" data-location="%s">%s</button>',
				esc_attr( $class ),
				esc_attr( $slug ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

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
	 * Renders a collapsible per-class section with check-in table.
	 *
	 * @param \WP_Post       $class      Class post object.
	 * @param array<object>  $checkins   Check-in records.
	 * @param string         $start_time Class start time.
	 * @param string         $end_time   Class end time.
	 * @return void
	 */
	private function render_class_section( \WP_Post $class, array $checkins, string $start_time, string $end_time ): void {
		$time_display = '';
		if ( $start_time && $end_time ) {
			$time_display = esc_html( $start_time . ' - ' . $end_time );
		}

		$count = count( $checkins );
		$open  = $count > 0 ? ' open' : '';

		echo '<details class="gym-class-section"' . $open . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<summary>';
		echo esc_html( $class->post_title );
		if ( $time_display ) {
			echo '<span class="class-meta">' . esc_html( $time_display ) . '</span>';
		}
		echo '<span class="class-count">' . esc_html( (string) $count ) . '</span>';
		echo '</summary>';

		if ( 0 === $count ) {
			echo '<div class="empty-class">' . esc_html__( 'No check-ins yet.', 'gym-core' ) . '</div>';
		} else {
			$this->render_checkin_table( $checkins );
		}

		echo '</details>';
	}

	/**
	 * Renders the check-in members table, including Foundations badges.
	 *
	 * @param array<object> $checkins Check-in records with display_name.
	 * @return void
	 */
	private function render_checkin_table( array $checkins ): void {
		$foundations = null;
		if ( FoundationsClearance::is_enabled() ) {
			$foundations = new FoundationsClearance( $this->store );
		}

		echo '<table class="gym-checkin-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Member', 'gym-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Time', 'gym-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Method', 'gym-core' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $checkins as $record ) {
			$user_id = (int) $record->user_id;
			$name    = isset( $record->display_name ) ? $record->display_name : '#' . $user_id;
			$time    = gmdate( 'g:i A', strtotime( $record->checked_in_at ) );
			$method  = ucfirst( $record->method );

			echo '<tr>';

			// Name + Foundations badge.
			echo '<td>' . esc_html( $name );
			if ( $foundations ) {
				$status = $foundations->get_status( $user_id );
				if ( $status['in_foundations'] ) {
					$phase   = str_replace( array( 'phase', '_coach_rolls' ), array( 'Phase ', '' ), $status['phase'] );
					$done    = $status['classes_completed'];
					$total   = $status['classes_total_required'];
					$badge   = sprintf( 'Foundations: %s (%d/%d)', ucfirst( $phase ), $done, $total );
					echo '<span class="gym-badge-foundations">' . esc_html( $badge ) . '</span>';
				}
			}
			echo '</td>';

			echo '<td>' . esc_html( $time ) . '</td>';
			echo '<td>' . esc_html( $method ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Renders the sticky Quick Check-In bar.
	 *
	 * @param string                $location Active location slug.
	 * @param array<int, \WP_Post>  $classes  Today's classes.
	 * @return void
	 */
	private function render_quick_checkin( string $location, array $classes ): void {
		echo '<form id="gym-quick-checkin-form" class="gym-quick-checkin">';
		echo '<label>' . esc_html__( 'Quick Check-In', 'gym-core' ) . '</label>';

		echo '<div class="gym-typeahead-wrap">';
		echo '<input type="text" id="gym-member-search" placeholder="' . esc_attr__( 'Search member...', 'gym-core' ) . '" autocomplete="off" role="combobox" aria-expanded="false" aria-owns="gym-member-results" aria-autocomplete="list">';
		echo '<input type="hidden" id="gym-member-id" name="user_id" value="">';
		echo '<div id="gym-member-results" class="gym-typeahead-results" role="listbox"></div>';
		echo '</div>';

		echo '<select name="class_id">';
		echo '<option value="0">' . esc_html__( '-- Walk-in --', 'gym-core' ) . '</option>';
		foreach ( $classes as $class ) {
			$start = get_post_meta( $class->ID, '_gym_class_start_time', true );
			$label = $class->post_title;
			if ( $start ) {
				$label .= ' (' . $start . ')';
			}
			echo '<option value="' . esc_attr( (string) $class->ID ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<input type="hidden" name="location" value="' . esc_attr( $location ) . '">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Check In', 'gym-core' ) . '</button>';
		echo '<span class="gym-checkin-msg"></span>';
		echo '</form>';
	}

	// ─── History tab ────────────────────────────────────────────────

	/**
	 * Renders the History tab with WP_List_Table.
	 *
	 * @return void
	 */
	private function render_history_tab(): void {
		$this->render_filter_bar();

		$table = new Attendance_List_Table( $this->store );
		$table->prepare_items();
		$table->display();
	}

	/**
	 * Renders the filter bar above the History list table.
	 *
	 * @return void
	 */
	private function render_filter_bar(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$from     = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to       = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$program  = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : '';
		$location = isset( $_GET['location'] ) ? sanitize_key( wp_unslash( $_GET['location'] ) ) : '';
		// phpcs:enable

		$base_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=history' );

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::MENU_SLUG ) . '">';
		echo '<input type="hidden" name="tab" value="history">';

		echo '<div class="gym-filter-bar">';

		echo '<div class="filter-group">';
		echo '<label for="gym-from">' . esc_html__( 'From', 'gym-core' ) . '</label>';
		echo '<input type="date" id="gym-from" name="from" value="' . esc_attr( $from ) . '">';
		echo '</div>';

		echo '<div class="filter-group">';
		echo '<label for="gym-to">' . esc_html__( 'To', 'gym-core' ) . '</label>';
		echo '<input type="date" id="gym-to" name="to" value="' . esc_attr( $to ) . '">';
		echo '</div>';

		echo '<div class="filter-group">';
		echo '<label for="gym-program">' . esc_html__( 'Program', 'gym-core' ) . '</label>';
		echo '<select id="gym-program" name="program" onchange="this.form.submit();">';
		echo '<option value="">' . esc_html__( 'All Programs', 'gym-core' ) . '</option>';
		$terms = get_terms(
			array(
				'taxonomy'   => ClassPostType::PROGRAM_TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $term->slug ),
					selected( $program, $term->slug, false ),
					esc_html( $term->name )
				);
			}
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="filter-group">';
		echo '<label for="gym-location-filter">' . esc_html__( 'Location', 'gym-core' ) . '</label>';
		echo '<select id="gym-location-filter" name="location" onchange="this.form.submit();">';
		echo '<option value="">' . esc_html__( 'All Locations', 'gym-core' ) . '</option>';
		foreach ( self::LOCATIONS as $slug => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $slug ),
				selected( $location, $slug, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="filter-group">';
		echo '<label>&nbsp;</label>';
		echo '<button type="submit" class="button">' . esc_html__( 'Filter', 'gym-core' ) . '</button>';
		echo '</div>';

		echo '</div>';
		echo '</form>';
	}

	// ─── Trends tab ─────────────────────────────────────────────────

	/**
	 * Renders the Trends tab with stat cards and at-risk list.
	 *
	 * @return void
	 */
	private function render_trends_tab(): void {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// Week boundaries.
		$this_week_start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
		$last_week_start = gmdate( 'Y-m-d', strtotime( 'monday last week' ) );
		$last_week_end   = gmdate( 'Y-m-d', strtotime( 'sunday last week' ) );
		$four_weeks_ago  = gmdate( 'Y-m-d', strtotime( '-4 weeks monday' ) );

		// This week count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this_week_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['attendance']} WHERE checked_in_at >= %s",
				$this_week_start . ' 00:00:00'
			)
		);

		// Last week count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$last_week_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['attendance']} WHERE checked_in_at >= %s AND checked_in_at <= %s",
				$last_week_start . ' 00:00:00',
				$last_week_end . ' 23:59:59'
			)
		);

		// 4-week rolling average per program.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$program_avg_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.name AS program_name,
						COUNT(*) / 4.0 AS weekly_avg
				FROM {$tables['attendance']} a
				LEFT JOIN {$wpdb->term_relationships} tr ON a.class_id = tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
				LEFT JOIN {$wpdb->terms} p ON tt.term_id = p.term_id
				WHERE a.checked_in_at >= %s
				GROUP BY p.term_id",
				ClassPostType::PROGRAM_TAXONOMY,
				$four_weeks_ago . ' 00:00:00'
			)
		);

		// Trend cards.
		$delta       = $this_week_count - $last_week_count;
		$delta_class = 'neutral';
		$delta_text  = __( 'No change', 'gym-core' );
		if ( $delta > 0 ) {
			$delta_class = 'positive';
			/* translators: %d: number of additional check-ins */
			$delta_text = sprintf( __( '+%d from last week', 'gym-core' ), $delta );
		} elseif ( $delta < 0 ) {
			$delta_class = 'negative';
			/* translators: %d: number of fewer check-ins */
			$delta_text = sprintf( __( '%d from last week', 'gym-core' ), $delta );
		}

		echo '<div class="gym-trend-cards">';

		echo '<div class="gym-trend-card">';
		echo '<h3>' . esc_html__( 'This Week', 'gym-core' ) . '</h3>';
		echo '<div class="trend-value">' . esc_html( (string) $this_week_count ) . '</div>';
		echo '<div class="trend-delta ' . esc_attr( $delta_class ) . '">' . esc_html( $delta_text ) . '</div>';
		echo '</div>';

		echo '<div class="gym-trend-card">';
		echo '<h3>' . esc_html__( 'Last Week', 'gym-core' ) . '</h3>';
		echo '<div class="trend-value">' . esc_html( (string) $last_week_count ) . '</div>';
		echo '</div>';

		// Per-program 4-week averages.
		if ( $program_avg_rows ) {
			foreach ( $program_avg_rows as $row ) {
				$prog_name = $row->program_name ?: __( 'Unassigned', 'gym-core' );
				$avg       = round( (float) $row->weekly_avg, 1 );

				echo '<div class="gym-trend-card">';
				echo '<h3>' . esc_html( $prog_name ) . '</h3>';
				echo '<div class="trend-value">' . esc_html( (string) $avg ) . '</div>';
				echo '<div class="trend-delta neutral">' . esc_html__( '4-week avg/week', 'gym-core' ) . '</div>';
				echo '</div>';
			}
		}

		echo '</div>';

		// At-risk members: zero attendance in 14+ days.
		$this->render_at_risk_members();
	}

	/**
	 * Renders the at-risk members list (no attendance in 14+ days).
	 *
	 * @return void
	 */
	private function render_at_risk_members(): void {
		global $wpdb;
		$tables    = TableManager::get_table_names();
		$threshold = gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) );

		// Find users who have attendance records but none in the last 14 days.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$at_risk = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.user_id,
						u.display_name,
						MAX(a.checked_in_at) AS last_checkin
				FROM {$tables['attendance']} a
				INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
				GROUP BY a.user_id
				HAVING MAX(a.checked_in_at) < %s
				ORDER BY last_checkin ASC
				LIMIT 50",
				$threshold
			)
		);

		echo '<div class="gym-at-risk-section">';
		echo '<h3>' . esc_html__( 'At Risk: No Attendance in 14+ Days', 'gym-core' ) . '</h3>';

		if ( empty( $at_risk ) ) {
			echo '<p>' . esc_html__( 'No at-risk members found.', 'gym-core' ) . '</p>';
		} else {
			echo '<ul class="at-risk-list">';
			foreach ( $at_risk as $member ) {
				$days_ago    = (int) floor( ( time() - strtotime( $member->last_checkin ) ) / DAY_IN_SECONDS );
				$profile_url = admin_url( 'user-edit.php?user_id=' . (int) $member->user_id );
				echo '<li>';
				echo '<a href="' . esc_url( $profile_url ) . '">' . esc_html( $member->display_name ) . '</a>';
				echo '<span class="days-absent">';
				/* translators: %d: number of days since last check-in */
				printf( esc_html__( '%d days ago', 'gym-core' ), $days_ago );
				echo '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	// ─── Helpers ────────────────────────────────────────────────────

	/**
	 * Returns the active location, reading from query param or user meta.
	 *
	 * Persists the choice to user meta when changed via query param.
	 *
	 * @return string Location slug.
	 */
	private function get_current_location(): string {
		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['location'] ) ) {
			$location = sanitize_key( wp_unslash( $_GET['location'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( self::LOCATIONS[ $location ] ) ) {
				update_user_meta( $user_id, self::LOCATION_META, $location );
				return $location;
			}
		}

		$saved = get_user_meta( $user_id, self::LOCATION_META, true );
		if ( is_string( $saved ) && isset( self::LOCATIONS[ $saved ] ) ) {
			return $saved;
		}

		return 'rockford';
	}

	/**
	 * Queries active gym_class posts for a given day of the week and location.
	 *
	 * @param string $day_of_week Lowercase day name (monday, tuesday, etc.).
	 * @param string $location    Location slug.
	 * @return array<int, \WP_Post>
	 */
	private function get_classes_for_day( string $day_of_week, string $location ): array {
		$args = array(
			'post_type'      => ClassPostType::POST_TYPE,
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'   => '_gym_class_day_of_week',
					'value' => $day_of_week,
				),
				array(
					'key'   => '_gym_class_status',
					'value' => 'active',
				),
			),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'gym_location',
					'field'    => 'slug',
					'terms'    => $location,
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_gym_class_start_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'ASC',
		);

		return get_posts( $args );
	}
}

// ─── WP_List_Table subclass for History tab ─────────────────────────

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Attendance history list table.
 *
 * @since 2.1.0
 */
class Attendance_List_Table extends \WP_List_Table {

	/**
	 * Attendance store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $store;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore $store Attendance data store.
	 */
	public function __construct( AttendanceStore $store ) {
		$this->store = $store;
		parent::__construct(
			array(
				'singular' => 'attendance',
				'plural'   => 'attendances',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Returns the column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'member_name' => __( 'Member Name', 'gym-core' ),
			'class_name'  => __( 'Class', 'gym-core' ),
			'location'    => __( 'Location', 'gym-core' ),
			'checked_in'  => __( 'Date/Time', 'gym-core' ),
			'method'      => __( 'Method', 'gym-core' ),
		);
	}

	/**
	 * Returns the sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	public function get_sortable_columns(): array {
		return array(
			'member_name' => array( 'display_name', false ),
			'checked_in'  => array( 'checked_in_at', true ),
			'location'    => array( 'location', false ),
		);
	}

	/**
	 * Prepares items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		global $wpdb;
		$tables = TableManager::get_table_names();

		$per_page = 30;
		$paged    = $this->get_pagenum();
		$offset   = ( $paged - 1 ) * $per_page;

		// Build WHERE clauses.
		$where = 'WHERE 1=1';
		$args  = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['from'] ) ) {
			$from   = sanitize_text_field( wp_unslash( $_GET['from'] ) );
			$where .= ' AND a.checked_in_at >= %s';
			$args[] = $from . ' 00:00:00';
		}

		if ( ! empty( $_GET['to'] ) ) {
			$to     = sanitize_text_field( wp_unslash( $_GET['to'] ) );
			$where .= ' AND a.checked_in_at <= %s';
			$args[] = $to . ' 23:59:59';
		}

		if ( ! empty( $_GET['location'] ) ) {
			$loc    = sanitize_key( wp_unslash( $_GET['location'] ) );
			$where .= ' AND a.location = %s';
			$args[] = $loc;
		}

		if ( ! empty( $_GET['program'] ) ) {
			$prog   = sanitize_key( wp_unslash( $_GET['program'] ) );
			$where .= ' AND p.slug = %s';
			$args[] = $prog;
		}
		// phpcs:enable

		// Ordering.
		$allowed_orderby = array( 'display_name', 'checked_in_at', 'location' );
		$orderby         = 'a.checked_in_at';
		$order           = 'DESC';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) ) {
			$req_orderby = sanitize_key( wp_unslash( $_GET['orderby'] ) );
			if ( in_array( $req_orderby, $allowed_orderby, true ) ) {
				$orderby = 'display_name' === $req_orderby ? 'u.display_name' : 'a.' . $req_orderby;
			}
		}
		if ( isset( $_GET['order'] ) ) {
			$req_order = strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) );
			if ( in_array( $req_order, array( 'ASC', 'DESC' ), true ) ) {
				$order = $req_order;
			}
		}
		// phpcs:enable

		$sql = "SELECT a.*, u.display_name
				FROM {$tables['attendance']} a
				INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
				LEFT JOIN {$wpdb->term_relationships} tr ON a.class_id = tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = '" . esc_sql( ClassPostType::PROGRAM_TAXONOMY ) . "'
				LEFT JOIN {$wpdb->terms} p ON tt.term_id = p.term_id
				{$where}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d";

		$args[] = $per_page;
		$args[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) ?: array();

		// Total count for pagination.
		$count_sql = "SELECT COUNT(*)
				FROM {$tables['attendance']} a
				INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
				LEFT JOIN {$wpdb->term_relationships} tr ON a.class_id = tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = '" . esc_sql( ClassPostType::PROGRAM_TAXONOMY ) . "'
				LEFT JOIN {$wpdb->terms} p ON tt.term_id = p.term_id
				{$where}";

		// Remove the last two LIMIT/OFFSET args for count query.
		$count_args = array_slice( $args, 0, -2 );

		if ( ! empty( $count_args ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_args ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Renders the Member Name column.
	 *
	 * @param object $item Row object.
	 * @return string
	 */
	public function column_member_name( object $item ): string {
		return esc_html( $item->display_name ?? '#' . $item->user_id );
	}

	/**
	 * Renders the Class column.
	 *
	 * @param object $item Row object.
	 * @return string
	 */
	public function column_class_name( object $item ): string {
		$class_id = (int) $item->class_id;
		if ( 0 === $class_id ) {
			return '<em>' . esc_html__( 'Walk-in', 'gym-core' ) . '</em>';
		}
		$class_post = get_post( $class_id );
		return $class_post ? esc_html( $class_post->post_title ) : '#' . esc_html( (string) $class_id );
	}

	/**
	 * Renders the Location column.
	 *
	 * @param object $item Row object.
	 * @return string
	 */
	public function column_location( object $item ): string {
		return esc_html( ucfirst( $item->location ) );
	}

	/**
	 * Renders the Date/Time column.
	 *
	 * @param object $item Row object.
	 * @return string
	 */
	public function column_checked_in( object $item ): string {
		$timestamp = strtotime( $item->checked_in_at );
		return esc_html( gmdate( 'M j, Y g:i A', $timestamp ) );
	}

	/**
	 * Renders the Method column.
	 *
	 * @param object $item Row object.
	 * @return string
	 */
	public function column_method( object $item ): string {
		return esc_html( ucfirst( $item->method ) );
	}

	/**
	 * Message displayed when no items found.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No attendance records found.', 'gym-core' );
	}
}
