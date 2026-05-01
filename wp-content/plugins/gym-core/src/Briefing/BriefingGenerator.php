<?php
/**
 * Coach Briefing generator engine.
 *
 * Given a class ID (gym_class CPT post ID), generates a complete pre-class
 * briefing containing class identity, forecasted student roster, per-student
 * alerts (Foundations, first-timers, absences, promotions), and active
 * announcements.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Briefing;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Attendance\PromotionEligibility;
use Gym_Core\Data\TableManager;
use Gym_Core\Rank\RankStore;
use Gym_Core\Schedule\ClassPostType;

/**
 * Generates pre-class intelligence briefings for coaches.
 */
class BriefingGenerator {

	/**
	 * Defensive upper bound for unbounded WP_Query result sets in this generator.
	 *
	 * @var int
	 */
	private const MAX_QUERY_RESULTS = 500;

	/**
	 * Default number of weeks to look back for attendance forecasting.
	 *
	 * @var int
	 */
	private const DEFAULT_FORECAST_WEEKS = 4;

	/**
	 * Default absence threshold in days to trigger an alert.
	 *
	 * @var int
	 */
	private const DEFAULT_ABSENCE_THRESHOLD = 14;

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
	 * Foundations clearance service.
	 *
	 * @var FoundationsClearance
	 */
	private FoundationsClearance $foundations;

	/**
	 * Promotion eligibility engine.
	 *
	 * @var PromotionEligibility
	 */
	private PromotionEligibility $promotion;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore      $attendance  Attendance data store.
	 * @param RankStore            $ranks       Rank data store.
	 * @param FoundationsClearance $foundations  Foundations clearance service.
	 * @param PromotionEligibility $promotion   Promotion eligibility engine.
	 */
	public function __construct(
		AttendanceStore $attendance,
		RankStore $ranks,
		FoundationsClearance $foundations,
		PromotionEligibility $promotion
	) {
		$this->attendance  = $attendance;
		$this->ranks       = $ranks;
		$this->foundations = $foundations;
		$this->promotion   = $promotion;
	}

	/**
	 * Generates a complete briefing for a given class.
	 *
	 * @since 2.1.0
	 *
	 * @param int $class_id Class post ID (gym_class CPT).
	 * @return array{class: array, roster: array, alerts: array, announcements: array}|\WP_Error
	 */
	public function generate( int $class_id ) {
		$post = get_post( $class_id );

		if ( ! $post || ClassPostType::POST_TYPE !== $post->post_type ) {
			return new \WP_Error(
				'invalid_class',
				__( 'Class not found.', 'gym-core' ),
				array( 'status' => 404 )
			);
		}

		$class_identity  = $this->build_class_identity( $post );
		$expected_roster = $this->forecast_roster( $class_id );
		$student_details = $this->enrich_roster( $expected_roster, $class_identity['program_slug'] );
		$alerts          = $this->build_alerts( $student_details );
		$announcements   = AnnouncementPostType::get_active_announcements(
			$class_identity['location'] ?? '',
			$class_identity['program_slug'] ?? ''
		);

		return array(
			'class'         => $class_identity,
			'roster'        => $student_details,
			'alerts'        => $alerts,
			'announcements' => $announcements,
			'generated_at'  => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Returns all classes scheduled for today at an optional location.
	 *
	 * @since 2.1.0
	 *
	 * @param string $location Optional location slug filter.
	 * @return array<int, int> Array of class post IDs.
	 */
	public function get_todays_classes( string $location = '' ): array {
		$today_day = strtolower( gmdate( 'l' ) );

		$args = array(
			'post_type'      => ClassPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::MAX_QUERY_RESULTS,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gym_class_day_of_week',
					'value' => $today_day,
				),
				array(
					'key'   => '_gym_class_status',
					'value' => 'active',
				),
			),
		);

		if ( '' !== $location ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'gym_location',
					'field'    => 'slug',
					'terms'    => $location,
				),
			);
		}

		$query = new \WP_Query( $args );

		return array_map( static fn( $p ) => $p instanceof \WP_Post ? $p->ID : (int) $p, $query->posts );
	}

	/**
	 * Builds the class identity section of the briefing.
	 *
	 * @param \WP_Post $post Class post object.
	 * @return array{id: int, name: string, program: string|null, program_slug: string|null, location: string|null, day_of_week: string, start_time: string, end_time: string, instructor: array|null}
	 */
	private function build_class_identity( \WP_Post $post ): array {
		$program_terms  = get_the_terms( $post->ID, ClassPostType::PROGRAM_TAXONOMY );
		$location_terms = get_the_terms( $post->ID, 'gym_location' );

		$program_name = null;
		$program_slug = null;
		if ( $program_terms && ! is_wp_error( $program_terms ) ) {
			$program_name = $program_terms[0]->name;
			$program_slug = $program_terms[0]->slug;
		}

		$location_slug = null;
		if ( $location_terms && ! is_wp_error( $location_terms ) ) {
			$location_slug = $location_terms[0]->slug;
		}

		$instructor_id = (int) get_post_meta( $post->ID, '_gym_class_instructor', true );
		$instructor    = null;
		if ( $instructor_id ) {
			$user = get_userdata( $instructor_id );
			if ( $user ) {
				$instructor = array(
					'id'   => $instructor_id,
					'name' => $user->display_name,
				);
			}
		}

		return array(
			'id'           => $post->ID,
			'name'         => $post->post_title,
			'program'      => $program_name,
			'program_slug' => $program_slug,
			'location'     => $location_slug,
			'day_of_week'  => get_post_meta( $post->ID, '_gym_class_day_of_week', true ),
			'start_time'   => get_post_meta( $post->ID, '_gym_class_start_time', true ),
			'end_time'     => get_post_meta( $post->ID, '_gym_class_end_time', true ),
			'instructor'   => $instructor,
		);
	}

	/**
	 * Forecasts the expected student roster based on recent attendance patterns.
	 *
	 * Looks at the last N weeks of attendance for this specific class and
	 * returns users who attended at least 2 of the last N instances (>= 50%).
	 *
	 * @param int $class_id Class post ID.
	 * @return array<int, array{user_id: int, attendance_rate: float}> User IDs with attendance rates.
	 */
	private function forecast_roster( int $class_id ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		$weeks = (int) get_option( 'gym_core_briefing_forecast_weeks', self::DEFAULT_FORECAST_WEEKS );
		$since = gmdate( 'Y-m-d', (int) strtotime( "-{$weeks} weeks" ) );

		// Count how many class instances occurred in the window.
		// Each distinct date with at least one check-in = one class instance.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$instance_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT DATE(checked_in_at))
				FROM {$tables['attendance']}
				WHERE class_id = %d AND checked_in_at >= %s",
				$class_id,
				$since . ' 00:00:00'
			)
		);

		if ( $instance_count < 1 ) {
			return array();
		}

		// Get per-user attendance counts for this class in the window.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) AS attend_count
				FROM {$tables['attendance']}
				WHERE class_id = %d AND checked_in_at >= %s
				GROUP BY user_id
				HAVING attend_count >= 2
				ORDER BY attend_count DESC",
				$class_id,
				$since . ' 00:00:00'
			)
		) ?: array();

		$roster = array();
		foreach ( $results as $row ) {
			$rate     = (float) $row->attend_count / $instance_count;
			$roster[] = array(
				'user_id'         => (int) $row->user_id,
				'attendance_rate' => round( $rate, 2 ),
			);
		}

		return $roster;
	}

	/**
	 * Enriches the forecasted roster with per-student detail.
	 *
	 * For each expected student, gathers: name, rank, Foundations status,
	 * days since last class, promotion eligibility, and medical notes.
	 *
	 * @param array<int, array{user_id: int, attendance_rate: float}> $roster  Forecasted roster.
	 * @param string|null                                             $program Program slug.
	 * @return array<int, array>
	 */
	private function enrich_roster( array $roster, ?string $program ): array {
		if ( empty( $roster ) ) {
			return array();
		}

		// Prime user cache.
		$user_ids = array_map( static fn( array $r ) => $r['user_id'], $roster );
		cache_users( $user_ids );

		$enriched = array();

		foreach ( $roster as $entry ) {
			$user_id = $entry['user_id'];
			$user    = get_userdata( $user_id );

			if ( ! $user ) {
				continue;
			}

			// Rank.
			$rank_data = null;
			if ( $program ) {
				$rank = $this->ranks->get_rank( $user_id, $program );
				if ( $rank ) {
					$rank_data = array(
						'belt'    => $rank->belt,
						'stripes' => (int) $rank->stripes,
					);
				}
			}

			// Foundations status.
			$foundations_status = $this->foundations->get_status( $user_id );

			// Days since last class.
			$days_since_last = $this->get_days_since_last_class( $user_id );

			// Total attendance count (for first-timer detection).
			$total_classes = $this->attendance->get_total_count( $user_id );

			// Promotion eligibility.
			$promotion_data = null;
			if ( $program ) {
				$promo_check = $this->promotion->check( $user_id, $program );
				if ( $promo_check['eligible'] || $promo_check['attendance_count'] >= ( $promo_check['attendance_required'] * 0.8 ) ) {
					$promotion_data = array(
						'eligible'            => $promo_check['eligible'],
						'attendance_count'    => $promo_check['attendance_count'],
						'attendance_required' => $promo_check['attendance_required'],
						'days_at_rank'        => $promo_check['days_at_rank'],
						'days_required'       => $promo_check['days_required'],
						'next_belt'           => $promo_check['next_belt'],
					);
				}
			}

			// Medical notes.
			$medical_notes = get_user_meta( $user_id, '_gym_medical_notes', true ) ?: '';

			$enriched[] = array(
				'user_id'         => $user_id,
				'display_name'    => $user->display_name,
				'rank'            => $rank_data,
				'foundations'     => $foundations_status['in_foundations'] ? array(
					'phase'              => $foundations_status['phase'],
					'classes_completed'  => $foundations_status['classes_completed'],
					'coach_rolls_needed' => 'phase2_coach_rolls' === $foundations_status['phase'],
				) : null,
				'days_since_last' => $days_since_last,
				'total_classes'   => $total_classes,
				'is_first_timer'  => 0 === $total_classes,
				'promotion'       => $promotion_data,
				'medical_notes'   => $medical_notes,
				'attendance_rate' => $entry['attendance_rate'],
			);
		}

		return $enriched;
	}

	/**
	 * Builds prioritized alerts from the enriched roster.
	 *
	 * Alert priorities (descending):
	 *   1. Foundations Phase 2 students needing coach rolls
	 *   2. First-timers (total_classes = 0)
	 *   3. Returning after long absence (> threshold days)
	 *   4. Medical/injury flags
	 *   5. Promotion candidates
	 *
	 * @param array<int, array> $roster Enriched roster.
	 * @return array<int, array{priority: int, type: string, user_id: int, display_name: string, detail: string}>
	 */
	private function build_alerts( array $roster ): array {
		$alerts            = array();
		$absence_threshold = (int) get_option( 'gym_core_briefing_absence_threshold', self::DEFAULT_ABSENCE_THRESHOLD );

		foreach ( $roster as $student ) {
			// Priority 1: Foundations Phase 2 (coach roll needed).
			if ( $student['foundations'] && $student['foundations']['coach_rolls_needed'] ) {
				$alerts[] = array(
					'priority'     => 1,
					'type'         => 'foundations_coach_roll',
					'user_id'      => $student['user_id'],
					'display_name' => $student['display_name'],
					'detail'       => sprintf(
						/* translators: 1: phase name, 2: classes completed */
						__( 'Phase 2 — needs supervised coach roll. %d classes completed.', 'gym-core' ),
						$student['foundations']['classes_completed']
					),
				);
			} elseif ( $student['foundations'] ) {
				// Other Foundations students are still notable but lower priority.
				$alerts[] = array(
					'priority'     => 1,
					'type'         => 'foundations',
					'user_id'      => $student['user_id'],
					'display_name' => $student['display_name'],
					'detail'       => sprintf(
						/* translators: 1: phase name, 2: classes completed */
						__( 'Foundations %1$s — %2$d classes completed.', 'gym-core' ),
						str_replace( '_', ' ', $student['foundations']['phase'] ),
						$student['foundations']['classes_completed']
					),
				);
			}

			// Priority 2: First-timers.
			if ( $student['is_first_timer'] ) {
				$alerts[] = array(
					'priority'     => 2,
					'type'         => 'first_timer',
					'user_id'      => $student['user_id'],
					'display_name' => $student['display_name'],
					'detail'       => __( 'First class ever — welcome, orient, and pair with a safe training partner.', 'gym-core' ),
				);
			}

			// Priority 3: Returning after long absence.
			if ( null !== $student['days_since_last'] && $student['days_since_last'] >= $absence_threshold && ! $student['is_first_timer'] ) {
				$alerts[] = array(
					'priority'     => 3,
					'type'         => 'returning_absence',
					'user_id'      => $student['user_id'],
					'display_name' => $student['display_name'],
					'detail'       => sprintf(
						/* translators: %d: number of days */
						__( 'Returning after %d days away — may need to ease back in.', 'gym-core' ),
						$student['days_since_last']
					),
				);
			}

			// Priority 4: Medical/injury flags.
			if ( '' !== $student['medical_notes'] ) {
				$alerts[] = array(
					'priority'     => 4,
					'type'         => 'medical',
					'user_id'      => $student['user_id'],
					'display_name' => $student['display_name'],
					'detail'       => $student['medical_notes'],
				);
			}

			// Priority 5: Promotion candidates.
			if ( $student['promotion'] ) {
				$label = $student['promotion']['eligible']
					? __( 'Eligible for promotion', 'gym-core' )
					: __( 'Approaching promotion eligibility', 'gym-core' );

				$alerts[] = array(
					'priority'     => 5,
					'type'         => 'promotion',
					'user_id'      => $student['user_id'],
					'display_name' => $student['display_name'],
					'detail'       => sprintf(
						/* translators: 1: label, 2: next belt/rank */
						'%1$s — %2$s (%3$d/%4$d classes)',
						$label,
						$student['promotion']['next_belt'] ?? __( 'next rank', 'gym-core' ),
						$student['promotion']['attendance_count'],
						$student['promotion']['attendance_required']
					),
				);
			}
		}

		// Sort alerts by priority (ascending = highest priority first).
		usort(
			$alerts,
			static function ( array $a, array $b ): int {
				if ( $a['priority'] !== $b['priority'] ) {
					return $a['priority'] <=> $b['priority'];
				}
				return strcmp( $a['display_name'], $b['display_name'] );
			}
		);

		return $alerts;
	}

	/**
	 * Returns the number of days since a user's last class attendance.
	 *
	 * @param int $user_id User ID.
	 * @return int|null Days since last class, or null if no attendance history.
	 */
	private function get_days_since_last_class( int $user_id ): ?int {
		$history = $this->attendance->get_user_history( $user_id, 1 );

		if ( empty( $history ) ) {
			return null;
		}

		$last_date = strtotime( $history[0]->checked_in_at );

		if ( false === $last_date ) {
			return null;
		}

		return (int) floor( ( time() - $last_date ) / DAY_IN_SECONDS );
	}
}
