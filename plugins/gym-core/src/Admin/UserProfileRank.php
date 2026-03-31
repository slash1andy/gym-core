<?php
/**
 * Rank display on the WordPress user profile edit page.
 *
 * Renders belt rank cards, Foundations clearance status, and provides
 * AJAX handlers for coach roll recording and Foundations clearance.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

use Gym_Core\Rank\RankStore;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;

/**
 * Renders rank information on user profile pages and handles related AJAX.
 */
final class UserProfileRank {

	/**
	 * Rank store instance.
	 *
	 * @var RankStore
	 */
	private RankStore $rank_store;

	/**
	 * Attendance store instance.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance_store;

	/**
	 * Foundations clearance instance.
	 *
	 * @var FoundationsClearance
	 */
	private FoundationsClearance $foundations;

	/**
	 * Constructor.
	 *
	 * @param RankStore            $rank_store       Rank data store.
	 * @param AttendanceStore      $attendance_store  Attendance data store.
	 * @param FoundationsClearance $foundations       Foundations clearance system.
	 */
	public function __construct( RankStore $rank_store, AttendanceStore $attendance_store, FoundationsClearance $foundations ) {
		$this->rank_store       = $rank_store;
		$this->attendance_store = $attendance_store;
		$this->foundations      = $foundations;
	}

	/**
	 * Registers hooks for user profile rendering and AJAX handlers.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'show_user_profile', array( $this, 'render_section' ) );
		add_action( 'edit_user_profile', array( $this, 'render_section' ) );

		add_action( 'wp_ajax_gym_record_coach_roll', array( $this, 'ajax_record_coach_roll' ) );
		add_action( 'wp_ajax_gym_clear_foundations', array( $this, 'ajax_clear_foundations' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues inline scripts on the user profile page.
	 *
	 * @since 2.1.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( 'user-edit.php' !== $hook_suffix && 'profile.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		$user_id = $this->get_profile_user_id();
		if ( ! $user_id ) {
			return;
		}

		wp_add_inline_script( 'jquery', $this->get_inline_js( $user_id ) );
	}

	/**
	 * Returns the user ID being viewed on the profile page.
	 *
	 * @return int User ID, or 0 if not determinable.
	 */
	private function get_profile_user_id(): int {
		if ( defined( 'IS_PROFILE_PAGE' ) && IS_PROFILE_PAGE ) {
			return get_current_user_id();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
	}

	/**
	 * Renders the Belt Rank & Programs section on the user profile page.
	 *
	 * @since 2.1.0
	 *
	 * @param \WP_User $user The user being viewed.
	 * @return void
	 */
	public function render_section( \WP_User $user ): void {
		$ranks    = $this->rank_store->get_all_ranks( $user->ID );
		$programs = RankDefinitions::get_programs();

		// Build a lookup of user ranks by program.
		$rank_map = array();
		foreach ( $ranks as $rank ) {
			$rank_map[ $rank->program ] = $rank;
		}

		// Determine if this is an Adult BJJ student for Foundations rendering.
		$has_adult_bjj       = isset( $rank_map['adult-bjj'] );
		$foundations_status   = $has_adult_bjj ? $this->foundations->get_status( $user->ID ) : null;
		$show_foundations     = $foundations_status && ( $foundations_status['in_foundations'] || $foundations_status['cleared'] );

		if ( empty( $ranks ) && ! $show_foundations ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Belt Rank & Programs', 'gym-core' ) . '</h2>';

		// Foundations section — rendered above rank cards for Adult BJJ students.
		if ( $show_foundations ) {
			$this->render_foundations( $user->ID, $foundations_status );
		}

		if ( ! empty( $ranks ) ) {
			echo '<table class="form-table" role="presentation"><tbody>';

			foreach ( $ranks as $rank ) {
				$program_label = $programs[ $rank->program ] ?? $rank->program;
				$belt_defs     = RankDefinitions::get_ranks( $rank->program );
				$current_def   = null;

				foreach ( $belt_defs as $def ) {
					if ( $def['slug'] === $rank->belt ) {
						$current_def = $def;
						break;
					}
				}

				if ( ! $current_def ) {
					continue;
				}

				echo '<tr>';
				echo '<th scope="row">' . esc_html( $program_label ) . '</th>';
				echo '<td>';
				$this->render_rank_card( $rank, $current_def, $user->ID );
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Renders a single rank card with belt indicator, stripes, and metadata.
	 *
	 * @param object $rank        Rank row object from RankStore.
	 * @param array  $current_def Belt definition from RankDefinitions.
	 * @param int    $user_id     The user ID.
	 * @return void
	 */
	private function render_rank_card( object $rank, array $current_def, int $user_id ): void {
		$color       = $current_def['color'];
		$type        = $current_def['type'];
		$stripes     = (int) $rank->stripes;
		$max_stripes = $current_def['max_stripes'];

		// Belt color circle.
		$border = '#ffffff' === strtolower( $color ) ? '2px solid #ccc' : 'none';
		echo '<span style="display:inline-block;width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:8px;'
			. 'background-color:' . esc_attr( $color ) . ';border:' . esc_attr( $border ) . ';"'
			. ' aria-hidden="true"></span>';

		// Belt name.
		echo '<strong style="vertical-align:middle;">' . esc_html( $current_def['name'] ) . '</strong>';

		// Stripe / degree / level indicator.
		if ( 'degree' === $type ) {
			// Black Belt: show degrees.
			echo ' <span style="vertical-align:middle;margin-left:8px;color:#555;">';
			echo esc_html(
				sprintf(
					/* translators: %d: number of degrees */
					_n( '%d degree', '%d degrees', $stripes, 'gym-core' ),
					$stripes
				)
			);
			echo '</span>';
		} elseif ( 'level' === $type ) {
			// Kickboxing: show "Level X" — name already contains level, no stripe indicator.
			// No additional indicator needed.
		} elseif ( $max_stripes > 0 ) {
			// Belt type: show filled vs empty stripe dots.
			echo ' <span style="vertical-align:middle;margin-left:8px;" aria-label="'
				. esc_attr(
					sprintf(
						/* translators: 1: stripe count, 2: max stripes */
						__( '%1$d of %2$d stripes', 'gym-core' ),
						$stripes,
						$max_stripes
					)
				) . '">';

			for ( $i = 1; $i <= $max_stripes; $i++ ) {
				if ( $i <= $stripes ) {
					echo '<span style="display:inline-block;width:10px;height:10px;border-radius:50;background:#333;margin-right:3px;border-radius:50%;" aria-hidden="true"></span>';
				} else {
					echo '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;border:1.5px solid #999;margin-right:3px;" aria-hidden="true"></span>';
				}
			}

			echo '</span>';
		}

		echo '<br>';

		// Promoted date with relative time.
		if ( ! empty( $rank->promoted_at ) ) {
			$promoted_ts   = strtotime( $rank->promoted_at );
			$days_ago      = $promoted_ts ? (int) floor( ( time() - $promoted_ts ) / DAY_IN_SECONDS ) : 0;
			$formatted     = $promoted_ts ? wp_date( 'M j, Y', $promoted_ts ) : $rank->promoted_at;
			$relative_text = sprintf(
				/* translators: %d: number of days */
				_n( '%d day ago', '%d days ago', $days_ago, 'gym-core' ),
				$days_ago
			);

			echo '<span style="color:#666;font-size:13px;">';
			echo esc_html(
				sprintf(
					/* translators: 1: relative time, 2: formatted date */
					__( 'Promoted %1$s (%2$s)', 'gym-core' ),
					$relative_text,
					$formatted
				)
			);
			echo '</span>';

			// Attendance since promotion.
			$attendance_since = $this->attendance_store->get_count_since( $user_id, $rank->promoted_at );
			echo ' &middot; <span style="color:#666;font-size:13px;">';
			echo esc_html(
				sprintf(
					/* translators: %d: number of classes */
					_n( '%d class since promotion', '%d classes since promotion', $attendance_since, 'gym-core' ),
					$attendance_since
				)
			);
			echo '</span>';
		}

		echo '<br>';

		// Quick links.
		$attendance_url = add_query_arg(
			array(
				'page'    => 'gym-core-attendance',
				'user_id' => $user_id,
			),
			admin_url( 'admin.php' )
		);

		$eligibility_url = add_query_arg(
			array(
				'page'    => 'gym-core-promotions',
				'user_id' => $user_id,
			),
			admin_url( 'admin.php' )
		);

		echo '<span style="font-size:13px;">';
		echo '<a href="' . esc_url( $attendance_url ) . '">' . esc_html__( 'View History', 'gym-core' ) . '</a>';
		echo ' &middot; ';
		echo '<a href="' . esc_url( $eligibility_url ) . '">' . esc_html__( 'View Eligibility', 'gym-core' ) . '</a>';
		echo '</span>';
	}

	/**
	 * Renders the Foundations clearance section.
	 *
	 * Displayed above rank cards with a colored left border.
	 * Amber for in-progress, green for cleared.
	 *
	 * @param int   $user_id User ID.
	 * @param array $status  Foundations status array from FoundationsClearance::get_status().
	 * @return void
	 */
	private function render_foundations( int $user_id, array $status ): void {
		if ( $status['cleared'] ) {
			$this->render_foundations_cleared( $status );
			return;
		}

		// In-progress Foundations rendering.
		$border_color = '#f59e0b'; // Amber.
		$reqs         = FoundationsClearance::get_requirements();
		$coach_rolls  = $this->foundations->get_coach_rolls( $user_id );

		echo '<div style="border-left:4px solid ' . esc_attr( $border_color ) . ';padding:12px 16px;margin-bottom:16px;background:#fffbeb;">';

		echo '<h3 style="margin:0 0 8px;">' . esc_html__( 'Foundations Program', 'gym-core' ) . '</h3>';

		// Phase.
		$phase_labels = array(
			'phase1'             => __( 'Phase 1 — Coached Instruction', 'gym-core' ),
			'phase2_coach_rolls' => __( 'Phase 2 — Coach Rolls', 'gym-core' ),
			'phase3'             => __( 'Phase 3 — Continued Training', 'gym-core' ),
			'ready_to_clear'     => __( 'Ready to Clear', 'gym-core' ),
		);
		$phase_label = $phase_labels[ $status['phase'] ] ?? $status['phase'];
		echo '<p style="margin:4px 0;"><strong>' . esc_html__( 'Phase:', 'gym-core' ) . '</strong> ' . esc_html( $phase_label ) . '</p>';

		// Classes completed / required.
		echo '<p style="margin:4px 0;"><strong>' . esc_html__( 'Classes:', 'gym-core' ) . '</strong> '
			. esc_html(
				sprintf(
					/* translators: 1: completed count, 2: required count */
					__( '%1$d / %2$d completed', 'gym-core' ),
					$status['classes_completed'],
					$status['classes_total_required']
				)
			)
			. '</p>';

		// Coach rolls completed / required.
		echo '<p style="margin:4px 0;"><strong>' . esc_html__( 'Coach Rolls:', 'gym-core' ) . '</strong> '
			. esc_html(
				sprintf(
					/* translators: 1: completed count, 2: required count */
					__( '%1$d / %2$d completed', 'gym-core' ),
					$status['coach_rolls_completed'],
					$status['coach_rolls_required']
				)
			)
			. '</p>';

		// List each coach roll.
		if ( ! empty( $coach_rolls ) ) {
			echo '<ul style="margin:8px 0 8px 16px;">';
			foreach ( $coach_rolls as $roll ) {
				$coach_user = get_userdata( (int) $roll['coach_id'] );
				$coach_name = $coach_user ? $coach_user->display_name : sprintf( __( 'Coach #%d', 'gym-core' ), $roll['coach_id'] );
				$roll_date  = wp_date( 'M j, Y', strtotime( $roll['date'] ) );

				echo '<li>';
				echo esc_html( $coach_name ) . ' &mdash; ' . esc_html( $roll_date );
				if ( ! empty( $roll['notes'] ) ) {
					echo ' <em style="color:#666;">(' . esc_html( $roll['notes'] ) . ')</em>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}

		// NOT CLEARED notice.
		echo '<p style="margin:8px 0 4px;"><span style="color:#dc2626;font-weight:700;">'
			. esc_html__( 'NOT CLEARED', 'gym-core' )
			. '</span></p>';

		// Record Coach Roll button and inline form.
		echo '<div style="margin-top:12px;">';
		echo '<button type="button" class="button" id="gym-show-coach-roll-form">'
			. esc_html__( 'Record Coach Roll', 'gym-core' )
			. '</button>';

		echo '<div id="gym-coach-roll-form" style="display:none;margin-top:8px;padding:10px;background:#fff;border:1px solid #ddd;">';
		echo '<label for="gym-coach-roll-notes" style="display:block;margin-bottom:4px;font-weight:600;">'
			. esc_html__( 'Notes', 'gym-core' )
			. '</label>';
		echo '<textarea id="gym-coach-roll-notes" rows="2" style="width:100%;max-width:400px;" placeholder="'
			. esc_attr__( 'Roll observations, technique notes...', 'gym-core' )
			. '"></textarea>';
		echo '<br>';
		echo '<button type="button" class="button button-primary" id="gym-save-coach-roll" style="margin-top:6px;">'
			. esc_html__( 'Save Coach Roll', 'gym-core' )
			. '</button>';
		echo '<span id="gym-coach-roll-status" style="margin-left:8px;"></span>';
		echo '</div>';
		echo '</div>';

		// Clear Student button (only for users with gym_promote_student cap).
		if ( current_user_can( 'gym_promote_student' ) ) {
			echo '<div style="margin-top:12px;">';
			echo '<button type="button" class="button" id="gym-clear-foundations-btn">'
				. esc_html__( 'Clear Student', 'gym-core' )
				. '</button>';

			echo '<span id="gym-clear-foundations-confirm" style="display:none;margin-left:8px;">';
			echo '<strong style="color:#dc2626;">' . esc_html__( 'Are you sure?', 'gym-core' ) . '</strong> ';
			echo '<button type="button" class="button button-primary" id="gym-clear-foundations-yes">'
				. esc_html__( 'Yes, Clear', 'gym-core' )
				. '</button> ';
			echo '<button type="button" class="button" id="gym-clear-foundations-no">'
				. esc_html__( 'Cancel', 'gym-core' )
				. '</button>';
			echo '<span id="gym-clear-foundations-status" style="margin-left:8px;"></span>';
			echo '</span>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Renders the compact cleared Foundations line.
	 *
	 * @param array $status Foundations status array.
	 * @return void
	 */
	private function render_foundations_cleared( array $status ): void {
		$cleared_date = ! empty( $status['cleared_at'] )
			? wp_date( 'M j, Y', strtotime( $status['cleared_at'] ) )
			: __( 'Unknown date', 'gym-core' );

		echo '<div style="border-left:4px solid #22c55e;padding:8px 16px;margin-bottom:16px;background:#f0fdf4;">';
		echo '<span style="color:#16a34a;font-size:16px;vertical-align:middle;" aria-hidden="true">&#10003;</span> ';
		echo '<strong style="vertical-align:middle;">' . esc_html__( 'Foundations:', 'gym-core' ) . '</strong> ';
		echo '<span style="vertical-align:middle;">';
		echo esc_html(
			sprintf(
				/* translators: %s: clearance date */
				__( 'Cleared (%s)', 'gym-core' ),
				$cleared_date
			)
		);
		echo '</span>';
		echo '</div>';
	}

	/**
	 * Returns the inline JavaScript for AJAX interactions.
	 *
	 * @param int $user_id The user ID being viewed.
	 * @return string JavaScript code.
	 */
	private function get_inline_js( int $user_id ): string {
		$nonce_roll  = wp_create_nonce( 'gym_record_coach_roll_' . $user_id );
		$nonce_clear = wp_create_nonce( 'gym_clear_foundations_' . $user_id );
		$ajax_url    = admin_url( 'admin-ajax.php' );

		return <<<JS
jQuery(function($) {
	// Toggle coach roll form.
	$('#gym-show-coach-roll-form').on('click', function() {
		$('#gym-coach-roll-form').slideToggle(200);
	});

	// Save coach roll via AJAX.
	$('#gym-save-coach-roll').on('click', function() {
		var btn = $(this);
		var notes = $('#gym-coach-roll-notes').val();
		var status = $('#gym-coach-roll-status');

		btn.prop('disabled', true);
		status.text('Saving...');

		$.post('{$ajax_url}', {
			action: 'gym_record_coach_roll',
			user_id: {$user_id},
			notes: notes,
			_wpnonce: '{$nonce_roll}'
		}).done(function(response) {
			if (response.success) {
				status.html('<span style="color:#16a34a;">' + response.data.message + '</span>');
				$('#gym-coach-roll-notes').val('');
				setTimeout(function() { location.reload(); }, 1200);
			} else {
				status.html('<span style="color:#dc2626;">' + response.data.message + '</span>');
				btn.prop('disabled', false);
			}
		}).fail(function() {
			status.html('<span style="color:#dc2626;">Request failed.</span>');
			btn.prop('disabled', false);
		});
	});

	// Clear Foundations — show confirmation.
	$('#gym-clear-foundations-btn').on('click', function() {
		$(this).hide();
		$('#gym-clear-foundations-confirm').show();
	});

	$('#gym-clear-foundations-no').on('click', function() {
		$('#gym-clear-foundations-confirm').hide();
		$('#gym-clear-foundations-btn').show();
	});

	$('#gym-clear-foundations-yes').on('click', function() {
		var btn = $(this);
		var status = $('#gym-clear-foundations-status');

		btn.prop('disabled', true);
		$('#gym-clear-foundations-no').prop('disabled', true);
		status.text('Clearing...');

		$.post('{$ajax_url}', {
			action: 'gym_clear_foundations',
			user_id: {$user_id},
			_wpnonce: '{$nonce_clear}'
		}).done(function(response) {
			if (response.success) {
				status.html('<span style="color:#16a34a;">' + response.data.message + '</span>');
				setTimeout(function() { location.reload(); }, 1200);
			} else {
				status.html('<span style="color:#dc2626;">' + response.data.message + '</span>');
				btn.prop('disabled', false);
				$('#gym-clear-foundations-no').prop('disabled', false);
			}
		}).fail(function() {
			status.html('<span style="color:#dc2626;">Request failed.</span>');
			btn.prop('disabled', false);
			$('#gym-clear-foundations-no').prop('disabled', false);
		});
	});
});
JS;
	}

	/**
	 * AJAX handler: Record a coach roll for a Foundations student.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function ajax_record_coach_roll(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'gym-core' ) ) );
		}

		check_ajax_referer( 'gym_record_coach_roll_' . $user_id );

		if ( ! current_user_can( 'gym_check_in_member' ) && ! current_user_can( 'gym_promote_student' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to record coach rolls.', 'gym-core' ) ) );
		}

		$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$coach_id = get_current_user_id();

		$result = $this->foundations->record_coach_roll( $user_id, $coach_id, $notes );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Coach roll recorded.', 'gym-core' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not record coach roll. Student may not be in Foundations.', 'gym-core' ) ) );
		}
	}

	/**
	 * AJAX handler: Clear a student from Foundations.
	 *
	 * Requires the gym_promote_student capability.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function ajax_clear_foundations(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'gym-core' ) ) );
		}

		check_ajax_referer( 'gym_clear_foundations_' . $user_id );

		if ( ! current_user_can( 'gym_promote_student' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to clear Foundations students.', 'gym-core' ) ) );
		}

		$coach_id = get_current_user_id();
		$result   = $this->foundations->clear( $user_id, $coach_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Student cleared from Foundations.', 'gym-core' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not clear student. They may not be in Foundations.', 'gym-core' ) ) );
		}
	}
}
