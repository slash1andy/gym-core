<?php
/**
 * Targeted content delivery system.
 *
 * Provides shortcodes for showing personalized content blocks to members
 * based on their rank, program, attendance, gamification data, and
 * membership status. Content is conditionally rendered on the front end
 * so only matching members see it.
 *
 * Shortcodes:
 *   [gym_targeted]      — Conditional content block with multi-criteria filtering.
 *   [gym_member_greeting] — Personalized greeting with rank and streak info.
 *   [gym_progress_card]  — Visual progress card (belt, stripes, classes, streak, badges).
 *
 * @package Gym_Core
 * @since   5.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Gamification;

use Gym_Core\Rank\RankStore;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Member\ContentGating;

/**
 * Registers and renders targeted content shortcodes.
 */
final class TargetedContent {

	/**
	 * Option key for enabling/disabling targeted content.
	 */
	public const OPTION_ENABLED = 'gym_core_targeted_content_enabled';

	/**
	 * Rank store.
	 *
	 * @var RankStore
	 */
	private RankStore $ranks;

	/**
	 * Attendance store.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Streak tracker.
	 *
	 * @var StreakTracker
	 */
	private StreakTracker $streaks;

	/**
	 * Badge engine.
	 *
	 * @var BadgeEngine
	 */
	private BadgeEngine $badges;

	/**
	 * Foundations clearance gate.
	 *
	 * @var FoundationsClearance
	 */
	private FoundationsClearance $foundations;

	/**
	 * Constructor.
	 *
	 * @param RankStore            $ranks       Rank data store.
	 * @param AttendanceStore      $attendance  Attendance data store.
	 * @param StreakTracker        $streaks     Streak tracker.
	 * @param BadgeEngine          $badges      Badge engine.
	 * @param FoundationsClearance $foundations Foundations clearance gate.
	 */
	public function __construct(
		RankStore $ranks,
		AttendanceStore $attendance,
		StreakTracker $streaks,
		BadgeEngine $badges,
		FoundationsClearance $foundations
	) {
		$this->ranks       = $ranks;
		$this->attendance  = $attendance;
		$this->streaks     = $streaks;
		$this->badges      = $badges;
		$this->foundations = $foundations;
	}

	/**
	 * Registers shortcodes and hooks.
	 *
	 * @since 5.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( 'yes' !== get_option( self::OPTION_ENABLED, 'yes' ) ) {
			return;
		}

		add_shortcode( 'gym_targeted', array( $this, 'render_targeted' ) );
		add_shortcode( 'gym_member_greeting', array( $this, 'render_member_greeting' ) );
		add_shortcode( 'gym_progress_card', array( $this, 'render_progress_card' ) );
	}

	/**
	 * Returns all member data needed for targeting decisions.
	 *
	 * Used by the shortcodes internally and also available for theme/block
	 * templates via `TargetedContent::get_member_context( $user_id )`.
	 *
	 * @since 5.3.0
	 *
	 * @param int $user_id User ID.
	 * @return array{
	 *   ranks: array<int, object>,
	 *   foundations_status: array,
	 *   total_classes: int,
	 *   current_streak: int,
	 *   badges_count: int,
	 *   active_membership: bool
	 * }
	 */
	public static function get_member_context( int $user_id ): array {
		$ranks_store      = new RankStore();
		$attendance_store = new AttendanceStore();
		$streak_tracker   = new StreakTracker( $attendance_store );
		$badge_engine     = new BadgeEngine( $attendance_store, $streak_tracker );
		$foundations_gate = new FoundationsClearance( $attendance_store );

		$streak_data = $streak_tracker->get_streak( $user_id );
		$user_badges = $badge_engine->get_user_badges( $user_id );

		return array(
			'ranks'              => $ranks_store->get_all_ranks( $user_id ),
			'foundations_status' => $foundations_gate->get_status( $user_id ),
			'total_classes'      => $attendance_store->get_total_count( $user_id ),
			'current_streak'     => $streak_data['current_streak'],
			'badges_count'       => count( $user_badges ),
			'active_membership'  => ContentGating::has_active_membership( $user_id ),
		);
	}

	/**
	 * Renders the [gym_targeted] shortcode.
	 *
	 * Shows enclosed content only if the current user matches ALL specified
	 * criteria. If the user does not match, an optional fallback message is
	 * shown instead.
	 *
	 * Attributes:
	 *   - program         (string) Program slug: adult-bjj, kids-bjj, kickboxing.
	 *   - min_belt        (string) Minimum belt slug (inclusive).
	 *   - max_belt        (string) Maximum belt slug (inclusive).
	 *   - min_classes     (int)    Minimum total class count.
	 *   - min_streak      (int)    Minimum current streak (weeks).
	 *   - foundations_only (bool)   Show only to members currently in Foundations.
	 *   - members_only    (bool)   Show only to members with an active membership.
	 *   - fallback        (string) Message shown when criteria are not met.
	 *
	 * @since 5.3.0
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed content.
	 * @return string Rendered HTML or empty string.
	 */
	public function render_targeted( $atts, ?string $content = null ): string {
		$atts = shortcode_atts(
			array(
				'program'          => '',
				'min_belt'         => '',
				'max_belt'         => '',
				'min_classes'      => '',
				'min_streak'       => '',
				'foundations_only' => '',
				'members_only'    => '',
				'fallback'         => '',
			),
			$atts,
			'gym_targeted'
		);

		$user_id = get_current_user_id();

		// Not logged in — show fallback.
		if ( 0 === $user_id ) {
			return $this->fallback_output( $atts['fallback'] );
		}

		// Members only gate.
		if ( $this->is_truthy( $atts['members_only'] ) && ! ContentGating::has_active_membership( $user_id ) ) {
			return $this->fallback_output( $atts['fallback'] );
		}

		// Foundations only gate.
		if ( $this->is_truthy( $atts['foundations_only'] ) ) {
			$foundations_status = $this->foundations->get_status( $user_id );
			if ( ! $foundations_status['in_foundations'] ) {
				return $this->fallback_output( $atts['fallback'] );
			}
		}

		// Program and belt checks.
		$program = sanitize_text_field( $atts['program'] );

		if ( '' !== $program ) {
			$rank = $this->ranks->get_rank( $user_id, $program );

			// User has no rank in this program — fail.
			if ( ! $rank ) {
				return $this->fallback_output( $atts['fallback'] );
			}

			$user_position = RankDefinitions::get_belt_position( $program, $rank->belt );

			if ( null === $user_position ) {
				return $this->fallback_output( $atts['fallback'] );
			}

			// Min belt check.
			if ( '' !== $atts['min_belt'] ) {
				$min_position = RankDefinitions::get_belt_position( $program, sanitize_text_field( $atts['min_belt'] ) );
				if ( null !== $min_position && $user_position < $min_position ) {
					return $this->fallback_output( $atts['fallback'] );
				}
			}

			// Max belt check.
			if ( '' !== $atts['max_belt'] ) {
				$max_position = RankDefinitions::get_belt_position( $program, sanitize_text_field( $atts['max_belt'] ) );
				if ( null !== $max_position && $user_position > $max_position ) {
					return $this->fallback_output( $atts['fallback'] );
				}
			}
		} elseif ( '' !== $atts['min_belt'] || '' !== $atts['max_belt'] ) {
			// Belt criteria without a program is invalid — show fallback.
			return $this->fallback_output( $atts['fallback'] );
		}

		// Min classes check.
		if ( '' !== $atts['min_classes'] ) {
			$min_classes  = absint( $atts['min_classes'] );
			$total_count  = $this->attendance->get_total_count( $user_id );
			if ( $total_count < $min_classes ) {
				return $this->fallback_output( $atts['fallback'] );
			}
		}

		// Min streak check.
		if ( '' !== $atts['min_streak'] ) {
			$min_streak   = absint( $atts['min_streak'] );
			$streak_data  = $this->streaks->get_streak( $user_id );
			if ( $streak_data['current_streak'] < $min_streak ) {
				return $this->fallback_output( $atts['fallback'] );
			}
		}

		// All criteria passed — render the content.
		return do_shortcode( (string) $content );
	}

	/**
	 * Renders the [gym_member_greeting] shortcode.
	 *
	 * Shows a personalized greeting including name, rank, class count,
	 * and current streak. Non-logged-in users see a generic welcome.
	 *
	 * @since 5.3.0
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string Rendered HTML.
	 */
	public function render_member_greeting( $atts ): string {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return '<p class="gym-greeting gym-greeting--guest">' . esc_html( sprintf( __( 'Welcome to %s!', 'gym-core' ), \Gym_Core\Utilities\Brand::name() ) ) . '</p>';
		}

		$user         = get_userdata( $user_id );
		$display_name = $user ? $user->display_name : __( 'Member', 'gym-core' );
		$all_ranks    = $this->ranks->get_all_ranks( $user_id );
		$total        = $this->attendance->get_total_count( $user_id );
		$streak_data  = $this->streaks->get_streak( $user_id );
		$programs     = RankDefinitions::get_programs();

		// Build the rank portion of the greeting.
		$rank_parts = array();
		foreach ( $all_ranks as $rank_row ) {
			$program_label = $programs[ $rank_row->program ] ?? $rank_row->program;
			$belt_defs     = RankDefinitions::get_ranks( $rank_row->program );
			$belt_name     = $rank_row->belt;

			foreach ( $belt_defs as $def ) {
				if ( $def['slug'] === $rank_row->belt ) {
					$belt_name = $def['name'];
					break;
				}
			}

			$rank_parts[] = sprintf(
				/* translators: 1: belt name, 2: program name */
				__( '%1$s in %2$s', 'gym-core' ),
				$belt_name,
				$program_label
			);
		}

		$greeting = sprintf(
			/* translators: 1: member name */
			__( 'Welcome back, %s!', 'gym-core' ),
			esc_html( $display_name )
		);

		$details = array();

		if ( ! empty( $rank_parts ) ) {
			/* translators: comma-separated list of belt ranks */
			$details[] = sprintf( __( "You're a %s", 'gym-core' ), implode( ', ', $rank_parts ) );
		}

		$details[] = sprintf(
			/* translators: %d: total class count */
			_n( 'with %d class', 'with %d classes', $total, 'gym-core' ),
			$total
		);

		if ( $streak_data['current_streak'] > 0 ) {
			$details[] = sprintf(
				/* translators: %d: streak week count */
				_n( 'and a %d-week streak', 'and a %d-week streak', $streak_data['current_streak'], 'gym-core' ),
				$streak_data['current_streak']
			);
		}

		$output  = '<div class="gym-greeting gym-greeting--member">';
		$output .= '<p class="gym-greeting__hello">' . esc_html( $greeting ) . '</p>';
		$output .= '<p class="gym-greeting__details">' . esc_html( implode( ' ', $details ) ) . '.</p>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Renders the [gym_progress_card] shortcode.
	 *
	 * Displays a visual progress card for the current user showing belt
	 * rank, stripes, class counts, days at rank, current streak, and badge
	 * count. Non-logged-in users see a CTA to join.
	 *
	 * @since 5.3.0
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string Rendered HTML.
	 */
	public function render_progress_card( $atts ): string {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return '<div class="gym-progress-card gym-progress-card--guest">'
				. '<p>' . esc_html__( 'Join to track your progress!', 'gym-core' ) . '</p>'
				. '</div>';
		}

		$all_ranks    = $this->ranks->get_all_ranks( $user_id );
		$streak_data  = $this->streaks->get_streak( $user_id );
		$user_badges  = $this->badges->get_user_badges( $user_id );
		$programs     = RankDefinitions::get_programs();

		$output = '<div class="gym-progress-card">';

		if ( empty( $all_ranks ) ) {
			$output .= '<p class="gym-progress-card__no-rank">' . esc_html__( 'No rank recorded yet. Check in to your first class!', 'gym-core' ) . '</p>';
		}

		foreach ( $all_ranks as $rank_row ) {
			$program       = $rank_row->program;
			$program_label = $programs[ $program ] ?? $program;
			$belt_defs     = RankDefinitions::get_ranks( $program );
			$belt_name     = $rank_row->belt;
			$belt_color    = '#cccccc';
			$max_stripes   = 4;
			$belt_type     = 'belt';

			foreach ( $belt_defs as $def ) {
				if ( $def['slug'] === $rank_row->belt ) {
					$belt_name   = $def['name'];
					$belt_color  = $def['color'];
					$max_stripes = $def['max_stripes'];
					$belt_type   = $def['type'];
					break;
				}
			}

			$stripes     = (int) $rank_row->stripes;
			$promoted_at = $rank_row->promoted_at ?? '';

			// Calculate days at current rank.
			$days_at_rank = 0;
			if ( '' !== $promoted_at ) {
				$promoted_ts  = strtotime( $promoted_at );
				$days_at_rank = $promoted_ts ? (int) floor( ( time() - $promoted_ts ) / DAY_IN_SECONDS ) : 0;
			}

			// Classes since last promotion.
			$classes_since = $this->attendance->get_count_since( $user_id, $promoted_at ?: gmdate( 'Y-m-d H:i:s' ) );

			// Promotion threshold for the next rank.
			$threshold     = RankDefinitions::get_promotion_threshold( $program, $rank_row->belt );
			$next_belt     = RankDefinitions::get_next_belt( $program, $rank_row->belt );

			$output .= '<div class="gym-progress-card__rank">';
			$output .= '<h3 class="gym-progress-card__program">' . esc_html( $program_label ) . '</h3>';

			// Belt with color indicator.
			$output .= '<div class="gym-progress-card__belt" style="border-left: 4px solid ' . esc_attr( $belt_color ) . ';">';
			$output .= '<span class="gym-progress-card__belt-name">' . esc_html( $belt_name ) . '</span>';

			// Stripes visualization (filled/empty dots).
			if ( $max_stripes > 0 ) {
				$stripe_label = 'degree' === $belt_type
					? /* translators: 1: count, 2: max */ __( '%1$d / %2$d degrees', 'gym-core' )
					: /* translators: 1: count, 2: max */ __( '%1$d / %2$d stripes', 'gym-core' );

				$output .= '<span class="gym-progress-card__stripes" aria-label="' . esc_attr( sprintf( $stripe_label, $stripes, $max_stripes ) ) . '">';
				for ( $i = 0; $i < $max_stripes; $i++ ) {
					$filled  = $i < $stripes ? ' gym-progress-card__dot--filled' : '';
					$output .= '<span class="gym-progress-card__dot' . $filled . '"></span>';
				}
				$output .= '</span>';
			}

			$output .= '</div>'; // .gym-progress-card__belt

			// Stats.
			$output .= '<dl class="gym-progress-card__stats">';

			$output .= '<div class="gym-progress-card__stat">';
			$output .= '<dt>' . esc_html__( 'Classes since promotion', 'gym-core' ) . '</dt>';
			$output .= '<dd>' . esc_html( (string) $classes_since );
			if ( $threshold['min_classes'] > 0 && $next_belt ) {
				$output .= ' / ' . esc_html( (string) $threshold['min_classes'] );
			}
			$output .= '</dd></div>';

			$output .= '<div class="gym-progress-card__stat">';
			$output .= '<dt>' . esc_html__( 'Days at current rank', 'gym-core' ) . '</dt>';
			$output .= '<dd>' . esc_html( (string) $days_at_rank );
			if ( $threshold['min_days'] > 0 && $next_belt ) {
				$output .= ' / ' . esc_html( (string) $threshold['min_days'] );
			}
			$output .= '</dd></div>';

			$output .= '</dl>';
			$output .= '</div>'; // .gym-progress-card__rank
		}

		// Streak.
		$output .= '<div class="gym-progress-card__streak">';
		$output .= '<span class="gym-progress-card__label">' . esc_html__( 'Current streak', 'gym-core' ) . '</span>';
		$output .= '<span class="gym-progress-card__value">';
		$output .= sprintf(
			/* translators: %d: streak week count */
			esc_html( _n( '%d week', '%d weeks', $streak_data['current_streak'], 'gym-core' ) ),
			$streak_data['current_streak']
		);
		$output .= '</span></div>';

		// Badges.
		$output .= '<div class="gym-progress-card__badges">';
		$output .= '<span class="gym-progress-card__label">' . esc_html__( 'Badges earned', 'gym-core' ) . '</span>';
		$output .= '<span class="gym-progress-card__value">' . esc_html( (string) count( $user_badges ) ) . '</span>';
		$output .= '</div>';

		$output .= '</div>'; // .gym-progress-card

		return $output;
	}

	/**
	 * Returns fallback output or empty string.
	 *
	 * @param string $fallback Fallback message from shortcode attribute.
	 * @return string
	 */
	private function fallback_output( string $fallback ): string {
		if ( '' === $fallback ) {
			return '';
		}

		return '<p class="gym-targeted-fallback">' . esc_html( $fallback ) . '</p>';
	}

	/**
	 * Checks whether a shortcode attribute value is truthy.
	 *
	 * Accepts "true", "yes", "1", or the bare attribute (empty string when
	 * used as a flag like `[gym_targeted foundations_only]`).
	 *
	 * @param string $value Attribute value.
	 * @return bool
	 */
	private function is_truthy( string $value ): bool {
		$value = strtolower( trim( $value ) );
		return in_array( $value, array( 'true', 'yes', '1' ), true );
	}
}
