<?php
/**
 * Coach Briefing renderer.
 *
 * Renders a briefing DTO (produced by BriefingGenerator) to HTML for use in
 * three surfaces: the wp-admin Coach Briefings page, the kiosk Coach Mode
 * tab, and the magic-link briefing page reached via SMS. All three surfaces
 * share the same DTO shape so that visual changes only need to happen once.
 *
 * The renderer also produces a short SMS-friendly text summary used by
 * BriefingNotifier when sending the 30-minute pre-class link.
 *
 * @package Gym_Core\Briefing
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Briefing;

/**
 * Renders briefing DTOs to HTML and SMS text.
 */
final class BriefingRenderer {

	/**
	 * Returns the array of CSS rules used by every briefing surface.
	 *
	 * Inline tokens follow docs/brand-guide.md sections 3-7. Kept inline so
	 * the same markup works in admin, kiosk, and the (front-end) magic-link
	 * page without three separate stylesheet handles.
	 *
	 * @return string Raw CSS (no <style> tag).
	 */
	public static function get_inline_css(): string {
		return '
		.gym-briefing { font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1a1a1a; max-width: 960px; margin: 0 auto; }
		.gym-briefing__header { padding: 1.5rem 1.5rem 0; }
		.gym-briefing__title { font-family: "Barlow Condensed", "Inter", sans-serif; font-weight: 700; font-size: 2rem; line-height: 1.1; margin: 0 0 0.25rem; text-transform: uppercase; letter-spacing: 0.02em; color: #0032A0; }
		.gym-briefing__subtitle { font-size: 0.875rem; color: #444; margin: 0 0 1rem; }
		.gym-briefing__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; padding: 1.5rem; }
		.gym-briefing-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 1.5rem; transition: box-shadow 0.2s, transform 0.2s; }
		.gym-briefing-card:hover { box-shadow: 0 12px 32px rgba(0,0,0,0.1); }
		.gym-briefing-card__title { font-family: "Barlow Condensed", "Inter", sans-serif; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em; color: #0032A0; margin: 0 0 1rem; }
		.gym-briefing-card__body { font-size: 0.95rem; line-height: 1.5; }
		.gym-briefing-list { list-style: none; padding: 0; margin: 0; }
		.gym-briefing-list li { padding: 0.5rem 0; border-bottom: 1px solid #E5E5E7; }
		.gym-briefing-list li:last-child { border-bottom: none; }
		.gym-briefing-alert { display: flex; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: 4px; margin-bottom: 0.5rem; font-size: 0.9rem; }
		.gym-briefing-alert--p1 { background: #FFEBEE; border-left: 3px solid #C62828; }
		.gym-briefing-alert--p2 { background: #FFF3E0; border-left: 3px solid #EF6C00; }
		.gym-briefing-alert--p3 { background: #FFF8E1; border-left: 3px solid #F9A825; }
		.gym-briefing-alert--p4 { background: #E8F5E9; border-left: 3px solid #2E7D32; }
		.gym-briefing-alert--p5 { background: #E3F2FD; border-left: 3px solid #1565C0; }
		.gym-briefing-alert__name { font-weight: 600; }
		.gym-briefing-empty { color: #777; font-style: italic; }
		.gym-briefing-roster__name { font-weight: 600; }
		.gym-briefing-roster__meta { font-size: 0.85rem; color: #555; }
		.gym-briefing-meta { font-size: 0.8rem; color: #777; padding: 0 1.5rem 1.5rem; }
		.gym-briefing-pill { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; background: #0032A0; color: #fff; }
		.gym-briefing-pill--rust { background: #F9A825; color: #1a1a1a; }
		.gym-briefing-pill--first { background: #2E7D32; color: #fff; }
		.gym-briefing-pill--medical { background: #C62828; color: #fff; }
		.gym-briefing-pill--foundations { background: #EF6C00; color: #fff; }
		.gym-briefing-curriculum { background: #F4F1EC; padding: 1rem; border-radius: 4px; }
		';
	}

	/**
	 * Renders a briefing DTO to a complete HTML fragment.
	 *
	 * Surface-aware: when $surface is "magic_link" the renderer wraps the
	 * card grid in a minimal full-page chrome; admin and kiosk surfaces
	 * receive only the briefing content (the surrounding chrome is the
	 * caller's responsibility).
	 *
	 * @since 2.2.0
	 *
	 * @param array  $briefing Briefing DTO from BriefingGenerator::generate().
	 * @param string $surface  "admin", "kiosk", or "magic_link".
	 * @return string Sanitised HTML fragment.
	 */
	public function render( array $briefing, string $surface = 'admin' ): string {
		if ( ! isset( $briefing['class'] ) ) {
			return '<p class="gym-briefing-empty">' . esc_html__( 'No briefing data available.', 'gym-core' ) . '</p>';
		}

		$class         = $briefing['class'];
		$roster        = $briefing['roster'] ?? array();
		$alerts        = $briefing['alerts'] ?? array();
		$announcements = $briefing['announcements'] ?? array();

		$out  = '<div class="gym-briefing" data-surface="' . esc_attr( $surface ) . '">';
		$out .= $this->render_header( $class );
		$out .= '<div class="gym-briefing__grid">';
		$out .= $this->render_alerts_card( $alerts );
		$out .= $this->render_roster_card( $roster );
		$out .= $this->render_curriculum_card( (int) $class['id'] );
		$out .= $this->render_announcements_card( $announcements );
		$out .= $this->render_coach_hours_card( $class );
		$out .= '</div>';
		$out .= $this->render_meta( $briefing );
		$out .= '</div>';

		return $out;
	}

	/**
	 * Builds the SMS-ready text summary for a briefing.
	 *
	 * Format intentionally compact (under 320 chars when possible) so it
	 * fits inside the standard two-segment SMS budget.
	 *
	 * @since 2.2.0
	 *
	 * @param array  $briefing Briefing DTO from BriefingGenerator::generate().
	 * @param string $link     Magic-link URL to include at the end.
	 * @return string Plain-text SMS body.
	 */
	public function render_sms( array $briefing, string $link ): string {
		$class = $briefing['class'] ?? array();
		$start = $class['start_time'] ?? '';
		$name  = $class['name'] ?? __( 'Class', 'gym-core' );
		$loc   = ! empty( $class['location'] ) ? ' (' . $class['location'] . ')' : '';

		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: time, 2: class name, 3: location */
			__( 'Coach Briefing — %1$s %2$s%3$s', 'gym-core' ),
			$start,
			$name,
			$loc
		);

		$alerts             = $briefing['alerts'] ?? array();
		$foundations_count  = 0;
		$first_timer_count  = 0;
		$medical_count      = 0;
		foreach ( $alerts as $alert ) {
			if ( in_array( $alert['type'] ?? '', array( 'foundations', 'foundations_coach_roll' ), true ) ) {
				++$foundations_count;
			} elseif ( 'first_timer' === ( $alert['type'] ?? '' ) ) {
				++$first_timer_count;
			} elseif ( 'medical' === ( $alert['type'] ?? '' ) ) {
				++$medical_count;
			}
		}

		if ( $foundations_count > 0 ) {
			$lines[] = sprintf(
				/* translators: %d: count */
				_n( '%d Foundations student', '%d Foundations students', $foundations_count, 'gym-core' ),
				$foundations_count
			);
		}
		if ( $first_timer_count > 0 ) {
			$lines[] = sprintf(
				/* translators: %d: count */
				_n( '%d first-timer', '%d first-timers', $first_timer_count, 'gym-core' ),
				$first_timer_count
			);
		}
		if ( $medical_count > 0 ) {
			$lines[] = sprintf(
				/* translators: %d: count */
				_n( '%d medical note', '%d medical notes', $medical_count, 'gym-core' ),
				$medical_count
			);
		}

		$announcements = $briefing['announcements'] ?? array();
		if ( ! empty( $announcements ) ) {
			$lines[] = sprintf(
				/* translators: %d: count */
				_n( '%d announcement', '%d announcements', count( $announcements ), 'gym-core' ),
				count( $announcements )
			);
		}

		$lines[] = __( 'Full briefing: ', 'gym-core' ) . $link;

		return implode( "\n", $lines );
	}

	/**
	 * Renders the briefing header (class name + identity).
	 *
	 * @param array $class Class identity DTO.
	 * @return string
	 */
	private function render_header( array $class ): string {
		$program  = $class['program'] ?? '';
		$location = $class['location'] ?? '';
		$start    = $class['start_time'] ?? '';
		$end      = $class['end_time'] ?? '';
		$day      = $class['day_of_week'] ?? '';
		$name     = $class['name'] ?? '';

		$subtitle_parts = array();
		if ( $program ) {
			$subtitle_parts[] = $program;
		}
		if ( $day ) {
			$subtitle_parts[] = ucfirst( $day );
		}
		if ( $start ) {
			$subtitle_parts[] = $start . ( $end ? ' - ' . $end : '' );
		}
		if ( $location ) {
			$subtitle_parts[] = $location;
		}

		return sprintf(
			'<header class="gym-briefing__header"><h1 class="gym-briefing__title">%s</h1><p class="gym-briefing__subtitle">%s</p></header>',
			esc_html( $name ),
			esc_html( implode( ' | ', $subtitle_parts ) )
		);
	}

	/**
	 * Renders the alerts card (priority-sorted list of student alerts).
	 *
	 * @param array<int, array> $alerts Alert array from generator.
	 * @return string
	 */
	private function render_alerts_card( array $alerts ): string {
		$inner  = '<div class="gym-briefing-card"><h2 class="gym-briefing-card__title">' . esc_html__( 'Alerts', 'gym-core' ) . '</h2><div class="gym-briefing-card__body">';

		if ( empty( $alerts ) ) {
			$inner .= '<p class="gym-briefing-empty">' . esc_html__( 'No alerts. Have a great class.', 'gym-core' ) . '</p>';
		} else {
			foreach ( $alerts as $alert ) {
				$priority = (int) ( $alert['priority'] ?? 5 );
				$priority = max( 1, min( 5, $priority ) );
				$inner   .= sprintf(
					'<div class="gym-briefing-alert gym-briefing-alert--p%d"><div><span class="gym-briefing-alert__name">%s</span> — %s</div></div>',
					$priority,
					esc_html( $alert['display_name'] ?? '' ),
					esc_html( $alert['detail'] ?? '' )
				);
			}
		}

		$inner .= '</div></div>';
		return $inner;
	}

	/**
	 * Renders the roster card (full forecasted student list).
	 *
	 * @param array<int, array> $roster Enriched roster.
	 * @return string
	 */
	private function render_roster_card( array $roster ): string {
		$inner = sprintf(
			'<div class="gym-briefing-card"><h2 class="gym-briefing-card__title">%s (%d)</h2><div class="gym-briefing-card__body">',
			esc_html__( 'Expected Roster', 'gym-core' ),
			count( $roster )
		);

		if ( empty( $roster ) ) {
			$inner .= '<p class="gym-briefing-empty">' . esc_html__( 'No forecasted attendance yet — first class at this slot or insufficient history.', 'gym-core' ) . '</p>';
		} else {
			$inner .= '<ul class="gym-briefing-list">';
			foreach ( $roster as $entry ) {
				$pills = array();
				if ( ! empty( $entry['is_first_timer'] ) ) {
					$pills[] = '<span class="gym-briefing-pill gym-briefing-pill--first">' . esc_html__( 'First class', 'gym-core' ) . '</span>';
				}
				if ( ! empty( $entry['foundations'] ) ) {
					$pills[] = '<span class="gym-briefing-pill gym-briefing-pill--foundations">' . esc_html__( 'Foundations', 'gym-core' ) . '</span>';
				}
				if ( ! empty( $entry['medical_notes'] ) ) {
					$pills[] = '<span class="gym-briefing-pill gym-briefing-pill--medical">' . esc_html__( 'Medical', 'gym-core' ) . '</span>';
				}
				if ( ! empty( $entry['days_since_last'] ) && (int) $entry['days_since_last'] >= 14 ) {
					$pills[] = '<span class="gym-briefing-pill gym-briefing-pill--rust">' . esc_html(
						sprintf(
							/* translators: %d: days */
							__( '%dd away', 'gym-core' ),
							(int) $entry['days_since_last']
						)
					) . '</span>';
				}

				$rank_label = '';
				if ( ! empty( $entry['rank'] ) ) {
					$rank_label = sprintf( '%s %s', $entry['rank']['belt'] ?? '', $entry['rank']['stripes'] ? '(' . (int) $entry['rank']['stripes'] . ')' : '' );
				}

				$inner .= sprintf(
					'<li><div class="gym-briefing-roster__name">%s</div><div class="gym-briefing-roster__meta">%s%s</div></li>',
					esc_html( $entry['display_name'] ?? '' ),
					esc_html( trim( $rank_label ) ),
					' ' . implode( ' ', $pills )
				);
			}
			$inner .= '</ul>';
		}

		$inner .= '</div></div>';
		return $inner;
	}

	/**
	 * Renders the curriculum-of-the-day card.
	 *
	 * V1: pulls a single text/URL meta (`_gym_curriculum_today`) on the
	 * gym_class post. Phase 3 §M will replace this with a full curriculum
	 * graph; for v1 a coach-editable free-text field is sufficient.
	 *
	 * @param int $class_id Class post ID.
	 * @return string
	 */
	private function render_curriculum_card( int $class_id ): string {
		$curriculum = get_post_meta( $class_id, '_gym_curriculum_today', true );
		$inner      = '<div class="gym-briefing-card"><h2 class="gym-briefing-card__title">' . esc_html__( 'Curriculum', 'gym-core' ) . '</h2><div class="gym-briefing-card__body">';

		if ( '' === (string) $curriculum ) {
			$inner .= '<p class="gym-briefing-empty">' . esc_html__( 'No curriculum set. Coach call.', 'gym-core' ) . '</p>';
		} else {
			$inner .= '<div class="gym-briefing-curriculum">' . wp_kses_post( wpautop( (string) $curriculum ) ) . '</div>';
		}

		$inner .= '</div></div>';
		return $inner;
	}

	/**
	 * Renders the announcements card.
	 *
	 * @param array<int, array> $announcements Active announcements from AnnouncementPostType.
	 * @return string
	 */
	private function render_announcements_card( array $announcements ): string {
		$inner = '<div class="gym-briefing-card"><h2 class="gym-briefing-card__title">' . esc_html__( 'Announcements', 'gym-core' ) . '</h2><div class="gym-briefing-card__body">';

		if ( empty( $announcements ) ) {
			$inner .= '<p class="gym-briefing-empty">' . esc_html__( 'No active announcements.', 'gym-core' ) . '</p>';
		} else {
			$inner .= '<ul class="gym-briefing-list">';
			foreach ( $announcements as $a ) {
				$pinned   = ! empty( $a['pinned'] ) ? '<span class="gym-briefing-pill">' . esc_html__( 'Pinned', 'gym-core' ) . '</span> ' : '';
				$inner   .= sprintf(
					'<li><strong>%s</strong>%s<div class="gym-briefing-roster__meta">%s</div></li>',
					esc_html( $a['title'] ?? '' ),
					$pinned ? ' ' . $pinned : '',
					wp_kses_post( $a['content'] ?? '' )
				);
			}
			$inner .= '</ul>';
		}

		$inner .= '</div></div>';
		return $inner;
	}

	/**
	 * Renders the coach hours card (operational reminder).
	 *
	 * V1 simply surfaces the assigned instructor and a placeholder for hour
	 * logging; the full coach_hours table lives in Phase 2.
	 *
	 * @param array $class Class identity DTO.
	 * @return string
	 */
	private function render_coach_hours_card( array $class ): string {
		$instructor = $class['instructor']['name'] ?? __( 'Unassigned', 'gym-core' );

		$inner  = '<div class="gym-briefing-card"><h2 class="gym-briefing-card__title">' . esc_html__( 'Coach Hours', 'gym-core' ) . '</h2><div class="gym-briefing-card__body">';
		$inner .= '<p><strong>' . esc_html__( 'Coach on duty:', 'gym-core' ) . '</strong> ' . esc_html( (string) $instructor ) . '</p>';
		$inner .= '<p class="gym-briefing-empty">' . esc_html__( 'Hour logging UI ships in the next phase.', 'gym-core' ) . '</p>';
		$inner .= '</div></div>';

		return $inner;
	}

	/**
	 * Renders the generated-at footer.
	 *
	 * @param array $briefing Briefing DTO.
	 * @return string
	 */
	private function render_meta( array $briefing ): string {
		$generated = $briefing['generated_at'] ?? gmdate( 'Y-m-d H:i:s' );
		return sprintf(
			'<footer class="gym-briefing-meta">%s %s UTC</footer>',
			esc_html__( 'Generated:', 'gym-core' ),
			esc_html( $generated )
		);
	}
}
