<?php
/**
 * Attendance milestone tracker.
 *
 * Listens to gym_core_attendance_recorded and fires
 * gym_core_attendance_milestone when a member hits a configurable
 * class-count milestone. The milestone action is already registered
 * as an AutomateWoo trigger (AutomateWooTriggers.php), enabling
 * milestone-based email/SMS workflows out of the box.
 *
 * @package Gym_Core
 * @since   2.4.0
 */

declare( strict_types=1 );

namespace Gym_Core\Attendance;

/**
 * Tracks attendance milestones and fires events when members reach them.
 */
final class MilestoneTracker {

	/**
	 * Default milestone thresholds.
	 *
	 * @var array<int, int>
	 */
	private const DEFAULT_MILESTONES = array( 10, 25, 50, 100, 150, 200, 250, 300, 500, 1000 );

	/**
	 * User meta key for tracking reached milestones.
	 *
	 * @var string
	 */
	private const META_KEY = '_gym_milestones_reached';

	/**
	 * Attendance store instance.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $attendance;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore $attendance Attendance data store.
	 */
	public function __construct( AttendanceStore $attendance ) {
		$this->attendance = $attendance;
	}

	/**
	 * Registers hooks for milestone tracking.
	 *
	 * Hooks after BadgeEngine (priority 10) so badges evaluate first.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'gym_core_attendance_recorded', array( $this, 'check_milestones' ), 20, 5 );
		add_filter( 'gym_core_attendance_settings', array( $this, 'add_settings_field' ) );
	}

	/**
	 * Checks whether the member has hit a new milestone after check-in.
	 *
	 * @since 2.4.0
	 *
	 * @param int    $record_id Attendance record ID.
	 * @param int    $user_id   Member user ID.
	 * @param int    $class_id  Class post ID.
	 * @param string $location  Location slug.
	 * @param string $method    Check-in method.
	 * @return void
	 */
	public function check_milestones( int $record_id, int $user_id, int $class_id, string $location, string $method ): void {
		// Skip imported records — don't retroactively fire milestones for historical data.
		if ( 'imported' === $method ) {
			return;
		}

		$total_count = $this->attendance->get_total_count( $user_id );
		$milestones  = $this->get_milestones();
		$reached     = $this->get_reached_milestones( $user_id );

		foreach ( $milestones as $milestone ) {
			if ( $total_count >= $milestone && ! in_array( $milestone, $reached, true ) ) {
				$this->award_milestone( $user_id, $milestone, $total_count );
			}
		}
	}

	/**
	 * Returns the configured milestone thresholds (sorted ascending).
	 *
	 * @since 2.4.0
	 *
	 * @return array<int, int> Sorted milestone counts.
	 */
	public function get_milestones(): array {
		$option = get_option( 'gym_core_attendance_milestones', '' );

		if ( is_string( $option ) && '' !== trim( $option ) ) {
			$milestones = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $option ) ) ) );
			$milestones = array_filter( $milestones, fn( int $v ): bool => $v > 0 );
		} else {
			$milestones = self::DEFAULT_MILESTONES;
		}

		$milestones = array_unique( $milestones );
		sort( $milestones, SORT_NUMERIC );

		/**
		 * Filters the attendance milestone thresholds.
		 *
		 * @since 2.4.0
		 *
		 * @param array<int, int> $milestones Sorted milestone class counts.
		 */
		return apply_filters( 'gym_core_attendance_milestone_thresholds', $milestones );
	}

	/**
	 * Returns milestones already reached by a user.
	 *
	 * @since 2.4.0
	 *
	 * @param int $user_id User ID.
	 * @return array<int, int> Previously reached milestone counts.
	 */
	public function get_reached_milestones( int $user_id ): array {
		$meta = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $meta ) ) {
			return array();
		}

		return array_map( 'absint', $meta );
	}

	/**
	 * Awards a milestone to a member and fires the milestone action.
	 *
	 * @since 2.4.0
	 *
	 * @param int $user_id     User ID.
	 * @param int $milestone   Milestone count reached.
	 * @param int $total_count Actual total attendance count at time of award.
	 * @return void
	 */
	private function award_milestone( int $user_id, int $milestone, int $total_count ): void {
		$reached   = $this->get_reached_milestones( $user_id );
		$reached[] = $milestone;

		update_user_meta( $user_id, self::META_KEY, array_unique( $reached ) );

		/**
		 * Fires when a member reaches an attendance milestone.
		 *
		 * This action is registered as an AutomateWoo trigger in
		 * AutomateWooTriggers.php, enabling milestone-based email and
		 * SMS workflows without additional configuration.
		 *
		 * @since 2.4.0
		 *
		 * @param int $user_id        Member user ID.
		 * @param int $milestone      Milestone class count (e.g. 50, 100).
		 * @param int $total_count    Actual total attendance count at time of award.
		 */
		do_action( 'gym_core_attendance_milestone', $user_id, $milestone, $total_count );
	}

	/**
	 * Adds the milestone settings field to the Attendance settings section.
	 *
	 * @since 2.4.0
	 *
	 * @param array<int, array<string, mixed>> $settings Existing attendance settings.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_settings_field( array $settings ): array {
		// Insert the milestone field before the sectionend element.
		$milestone_field = array(
			'title'       => __( 'Attendance milestones', 'gym-core' ),
			'desc'        => __( 'Comma-separated class counts that trigger milestone events (used by AutomateWoo workflows for email/SMS). Default: 10, 25, 50, 100, 150, 200, 250, 300, 500, 1000', 'gym-core' ),
			'id'          => 'gym_core_attendance_milestones',
			'default'     => '',
			'type'        => 'text',
			'placeholder' => implode( ', ', self::DEFAULT_MILESTONES ),
			'desc_tip'    => true,
		);

		$inserted = false;
		$result   = array();

		foreach ( $settings as $field ) {
			// Insert just before the sectionend for attendance options.
			if ( ! $inserted && isset( $field['type'], $field['id'] ) && 'sectionend' === $field['type'] && 'gym_core_attendance_options' === $field['id'] ) {
				$result[] = $milestone_field;
				$inserted = true;
			}
			$result[] = $field;
		}

		// Fallback: append before sectionend if we didn't find the expected ID.
		if ( ! $inserted ) {
			$result[] = $milestone_field;
		}

		return $result;
	}
}
