<?php
/**
 * Custom AutomateWoo triggers for gym events.
 *
 * Registers triggers for belt promotions, foundations clearance,
 * class check-ins, and attendance milestones so gym automations
 * can be built entirely within the AutomateWoo workflow editor.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Integrations;

/**
 * Registers all custom AutomateWoo triggers.
 */
final class AutomateWooTriggers {

	/**
	 * Registers trigger classes with AutomateWoo.
	 *
	 * Called from Plugin::init(). Safe to call unconditionally —
	 * each trigger class guards itself with class_exists().
	 *
	 * @since 2.1.0
	 */
	public static function init(): void {
		add_action( 'automatewoo/triggers/register', array( __CLASS__, 'register_triggers' ) );
	}

	/**
	 * Registers each trigger with the AutomateWoo trigger registry.
	 *
	 * @since 2.1.0
	 *
	 * @param \AutomateWoo\Triggers $triggers AutomateWoo triggers registry.
	 */
	public static function register_triggers( $triggers ): void {
		if ( class_exists( '\AutomateWoo\Trigger' ) ) {
			$triggers->register( new GymBeltPromotion() );
			$triggers->register( new GymFoundationsCleared() );
			$triggers->register( new GymAttendanceRecorded() );
			$triggers->register( new GymAttendanceMilestone() );
		}
	}
}

if ( class_exists( '\AutomateWoo\Trigger' ) ) :

	/**
	 * Trigger: Gym -- Belt Promotion.
	 *
	 * Fires when a member's belt rank changes via gym_core_rank_changed.
	 *
	 * @since 2.1.0
	 */
	class GymBeltPromotion extends \AutomateWoo\Trigger {

		/**
		 * Data items this trigger supplies to workflows.
		 *
		 * @var array<int, string>
		 */
		public $supplied_data_items = array( 'customer', 'user_id', 'program', 'new_belt', 'new_stripes' );

		/**
		 * Sets admin-visible title and description.
		 *
		 * @since 2.1.0
		 */
		public function load_admin_details(): void {
			$this->title       = __( 'Gym -- Belt Promotion', 'gym-core' );
			$this->description = __( 'Fires when a member is promoted to a new belt or earns a stripe.', 'gym-core' );
			$this->group       = __( 'Gym', 'gym-core' );
		}

		/**
		 * Hooks into the WordPress action that fires on rank change.
		 *
		 * @since 2.1.0
		 */
		public function register_hooks(): void {
			add_action( 'gym_core_rank_changed', array( $this, 'handle_rank_changed' ), 10, 6 );
		}

		/**
		 * Callback for the gym_core_rank_changed action.
		 *
		 * @since 2.1.0
		 *
		 * @param int         $user_id     Member user ID.
		 * @param string      $program     Program slug.
		 * @param string      $new_belt    New belt slug.
		 * @param int         $new_stripes New stripe count.
		 * @param string|null $from_belt   Previous belt slug.
		 * @param int         $promoted_by Promoter user ID.
		 */
		public function handle_rank_changed( int $user_id, string $program, string $new_belt, int $new_stripes, ?string $from_belt, int $promoted_by ): void {
			$customer = \AutomateWoo\Customer_Factory::get_by_user_id( $user_id );

			if ( ! $customer ) {
				return;
			}

			$this->maybe_run(
				array(
					'customer'    => $customer,
					'user_id'     => $user_id,
					'program'     => $program,
					'new_belt'    => $new_belt,
					'new_stripes' => $new_stripes,
				)
			);
		}
	}

	/**
	 * Trigger: Gym -- Foundations Cleared.
	 *
	 * Fires when a new student completes the Foundations safety gate.
	 *
	 * @since 2.1.0
	 */
	class GymFoundationsCleared extends \AutomateWoo\Trigger {

		/**
		 * Data items this trigger supplies to workflows.
		 *
		 * @var array<int, string>
		 */
		public $supplied_data_items = array( 'customer', 'user_id' );

		/**
		 * Sets admin-visible title and description.
		 *
		 * @since 2.1.0
		 */
		public function load_admin_details(): void {
			$this->title       = __( 'Gym -- Foundations Cleared', 'gym-core' );
			$this->description = __( 'Fires when a student completes the Foundations program and is cleared for live training.', 'gym-core' );
			$this->group       = __( 'Gym', 'gym-core' );
		}

		/**
		 * Hooks into the WordPress action that fires on foundations clearance.
		 *
		 * @since 2.1.0
		 */
		public function register_hooks(): void {
			add_action( 'gym_core_foundations_cleared', array( $this, 'handle_foundations_cleared' ), 10, 3 );
		}

		/**
		 * Callback for the gym_core_foundations_cleared action.
		 *
		 * @since 2.1.0
		 *
		 * @param int   $user_id  Student user ID.
		 * @param int   $coach_id Coach who cleared the student.
		 * @param array $status   Full foundations status array.
		 */
		public function handle_foundations_cleared( int $user_id, int $coach_id, array $status ): void {
			$customer = \AutomateWoo\Customer_Factory::get_by_user_id( $user_id );

			if ( ! $customer ) {
				return;
			}

			$this->maybe_run(
				array(
					'customer' => $customer,
					'user_id'  => $user_id,
				)
			);
		}
	}

	/**
	 * Trigger: Gym -- Class Check-In.
	 *
	 * Fires every time a member checks into a class.
	 *
	 * @since 2.1.0
	 */
	class GymAttendanceRecorded extends \AutomateWoo\Trigger {

		/**
		 * Data items this trigger supplies to workflows.
		 *
		 * @var array<int, string>
		 */
		public $supplied_data_items = array( 'customer', 'user_id', 'location', 'class_id' );

		/**
		 * Sets admin-visible title and description.
		 *
		 * @since 2.1.0
		 */
		public function load_admin_details(): void {
			$this->title       = __( 'Gym -- Class Check-In', 'gym-core' );
			$this->description = __( 'Fires when a member checks in to a class or open mat session.', 'gym-core' );
			$this->group       = __( 'Gym', 'gym-core' );
		}

		/**
		 * Hooks into the WordPress action that fires on attendance record.
		 *
		 * @since 2.1.0
		 */
		public function register_hooks(): void {
			add_action( 'gym_core_attendance_recorded', array( $this, 'handle_attendance_recorded' ), 10, 5 );
		}

		/**
		 * Callback for the gym_core_attendance_recorded action.
		 *
		 * @since 2.1.0
		 *
		 * @param int    $record_id Attendance record ID.
		 * @param int    $user_id   Member user ID.
		 * @param int    $class_id  Class post ID.
		 * @param string $location  Location slug.
		 * @param string $method    Check-in method.
		 */
		public function handle_attendance_recorded( int $record_id, int $user_id, int $class_id, string $location, string $method ): void {
			$customer = \AutomateWoo\Customer_Factory::get_by_user_id( $user_id );

			if ( ! $customer ) {
				return;
			}

			$this->maybe_run(
				array(
					'customer' => $customer,
					'user_id'  => $user_id,
					'location' => $location,
					'class_id' => $class_id,
				)
			);
		}
	}

	/**
	 * Trigger: Gym -- Attendance Milestone.
	 *
	 * Fires when a member reaches an attendance milestone (e.g. 50, 100, 250 classes).
	 *
	 * @since 2.1.0
	 */
	class GymAttendanceMilestone extends \AutomateWoo\Trigger {

		/**
		 * Data items this trigger supplies to workflows.
		 *
		 * @var array<int, string>
		 */
		public $supplied_data_items = array( 'customer', 'user_id', 'milestone_count' );

		/**
		 * Sets admin-visible title and description.
		 *
		 * @since 2.1.0
		 */
		public function load_admin_details(): void {
			$this->title       = __( 'Gym -- Attendance Milestone', 'gym-core' );
			$this->description = __( 'Fires when a member reaches a class attendance milestone (e.g. 50, 100, 250).', 'gym-core' );
			$this->group       = __( 'Gym', 'gym-core' );
		}

		/**
		 * Hooks into the WordPress action that fires on attendance milestones.
		 *
		 * @since 2.1.0
		 */
		public function register_hooks(): void {
			add_action( 'gym_core_attendance_milestone', array( $this, 'handle_attendance_milestone' ), 10, 2 );
		}

		/**
		 * Callback for the gym_core_attendance_milestone action.
		 *
		 * @since 2.1.0
		 *
		 * @param int $user_id         Member user ID.
		 * @param int $milestone_count The milestone number reached.
		 */
		public function handle_attendance_milestone( int $user_id, int $milestone_count ): void {
			$customer = \AutomateWoo\Customer_Factory::get_by_user_id( $user_id );

			if ( ! $customer ) {
				return;
			}

			$this->maybe_run(
				array(
					'customer'        => $customer,
					'user_id'         => $user_id,
					'milestone_count' => $milestone_count,
				)
			);
		}
	}

endif;
