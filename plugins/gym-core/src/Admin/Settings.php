<?php
/**
 * WooCommerce settings tab for Gym Core.
 *
 * Adds a "Gym Core" tab under WooCommerce > Settings with sections for
 * each module: General, Locations, Schedule, Ranks, Attendance, Gamification,
 * SMS, and API.
 *
 * @package Gym_Core
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

/**
 * Registers and renders the Gym Core settings tab in WooCommerce.
 */
final class Settings {

	/**
	 * Tab identifier.
	 *
	 * @var string
	 */
	private const TAB_ID = 'gym_core';

	/**
	 * Registers hooks for the settings tab.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_' . self::TAB_ID, array( $this, 'render_settings' ) );
		add_action( 'woocommerce_update_options_' . self::TAB_ID, array( $this, 'save_settings' ) );
		add_action( 'woocommerce_sections_' . self::TAB_ID, array( $this, 'render_sections' ) );
	}

	/**
	 * Adds the Gym Core tab to the WooCommerce settings tabs array.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, string> $tabs Existing settings tabs.
	 * @return array<string, string>
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = __( 'Gym Core', 'gym-core' );
		return $tabs;
	}

	/**
	 * Returns the available sections for this tab.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Section slug => label.
	 */
	private function get_sections(): array {
		return array(
			''             => __( 'General', 'gym-core' ),
			'locations'    => __( 'Locations', 'gym-core' ),
			'schedule'     => __( 'Schedule', 'gym-core' ),
			'ranks'        => __( 'Ranks', 'gym-core' ),
			'attendance'   => __( 'Attendance', 'gym-core' ),
			'gamification' => __( 'Gamification', 'gym-core' ),
			'sms'          => __( 'SMS', 'gym-core' ),
			'api'          => __( 'API', 'gym-core' ),
		);
	}

	/**
	 * Renders the section navigation links.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_sections(): void {
		global $current_section;

		$sections = $this->get_sections();

		if ( count( $sections ) <= 1 ) {
			return;
		}

		echo '<ul class="subsubsub">';

		$links = array();
		foreach ( $sections as $id => $label ) {
			$url     = admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB_ID . '&section=' . $id );
			$class   = ( $current_section === $id ) ? 'current' : '';
			$links[] = sprintf(
				'<li><a href="%s" class="%s">%s</a></li>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}

		echo wp_kses_post( implode( ' | ', $links ) );
		echo '</ul><br class="clear" />';
	}

	/**
	 * Renders the settings fields for the current section.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_settings(): void {
		global $current_section;

		$settings = $this->get_settings_for_section( $current_section );
		\WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Saves the settings for the current section.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function save_settings(): void {
		global $current_section;

		$settings = $this->get_settings_for_section( $current_section );
		\WC_Admin_Settings::save_fields( $settings );
	}

	/**
	 * Returns the settings fields for a given section.
	 *
	 * @since 1.1.0
	 *
	 * @param string $section Section slug.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_settings_for_section( string $section ): array {
		switch ( $section ) {
			case 'locations':
				return $this->get_locations_settings();
			case 'schedule':
				return $this->get_schedule_settings();
			case 'ranks':
				return $this->get_ranks_settings();
			case 'attendance':
				return $this->get_attendance_settings();
			case 'gamification':
				return $this->get_gamification_settings();
			case 'sms':
				return $this->get_sms_settings();
			case 'api':
				return $this->get_api_settings();
			default:
				return $this->get_general_settings();
		}
	}

	/**
	 * General settings — module toggles and global config.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_general_settings(): array {
		return array(
			array(
				'title' => __( 'General Settings', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_general_options',
			),
			array(
				'title'   => __( 'Gamification', 'gym-core' ),
				'desc'    => __( 'Enable badges, streaks, and achievement tracking', 'gym-core' ),
				'id'      => 'gym_core_gamification_enabled',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'SMS Notifications', 'gym-core' ),
				'desc'    => __( 'Enable Twilio SMS integration', 'gym-core' ),
				'id'      => 'gym_core_sms_enabled',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'REST API', 'gym-core' ),
				'desc'    => __( 'Enable gym/v1 REST API endpoints for AI agents', 'gym-core' ),
				'id'      => 'gym_core_api_enabled',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_general_options',
			),
		);
	}

	/**
	 * Location settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_locations_settings(): array {
		return array(
			array(
				'title' => __( 'Location Settings', 'gym-core' ),
				'desc'  => __( 'Configure multi-location behavior. Locations are managed as taxonomy terms under Products > Gym Locations.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_location_options',
			),
			array(
				'title'   => __( 'Require Location Selection', 'gym-core' ),
				'desc'    => __( 'Show location selector banner until visitor picks a location', 'gym-core' ),
				'id'      => 'gym_core_require_location',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Filter Products by Location', 'gym-core' ),
				'desc'    => __( 'Only show products assigned to the selected location', 'gym-core' ),
				'id'      => 'gym_core_filter_products_by_location',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_location_options',
			),
		);
	}

	/**
	 * Schedule settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_schedule_settings(): array {
		return array(
			array(
				'title' => __( 'Class Schedule Settings', 'gym-core' ),
				'desc'  => __( 'Configure class scheduling behavior.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_schedule_options',
			),
			array(
				'title'             => __( 'Default Class Capacity', 'gym-core' ),
				'desc'              => __( 'Maximum students per class (can be overridden per class)', 'gym-core' ),
				'id'                => 'gym_core_default_class_capacity',
				'default'           => '30',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			array(
				'title'   => __( 'Enable Waitlist', 'gym-core' ),
				'desc'    => __( 'Allow students to join a waitlist when class is full', 'gym-core' ),
				'id'      => 'gym_core_waitlist_enabled',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'iCal Feed', 'gym-core' ),
				'desc'    => __( 'Enable iCal feed for members to subscribe to the class schedule', 'gym-core' ),
				'id'      => 'gym_core_ical_enabled',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_schedule_options',
			),
		);
	}

	/**
	 * Rank settings — promotion rules, Foundations gate, and per-program thresholds.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_ranks_settings(): array {
		return array(
			// --- Promotion Rules ---
			array(
				'title' => __( 'Promotion Rules', 'gym-core' ),
				'desc'  => __( 'Global rules for rank promotions across all programs.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_ranks_options',
			),
			array(
				'title'   => __( 'Require Coach Recommendation', 'gym-core' ),
				'desc'    => __( 'Promotions require a coach recommendation before instructor approval', 'gym-core' ),
				'id'      => 'gym_core_require_coach_recommendation',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Notify on Promotion', 'gym-core' ),
				'desc'    => __( 'Send SMS and email notification when a member is promoted', 'gym-core' ),
				'id'      => 'gym_core_notify_on_promotion',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_ranks_options',
			),

			// --- Foundations Clearance Gate ---
			array(
				'title' => __( 'Foundations Clearance (Adult BJJ)', 'gym-core' ),
				'desc'  => __( 'New Adult BJJ students must complete a Foundations phase before live training with non-coaches. This is a safety gate — not a belt. Time in Foundations counts toward White Belt stripes.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_foundations_options',
			),
			array(
				'title'   => __( 'Enable Foundations Gate', 'gym-core' ),
				'desc'    => __( 'Require new Adult BJJ students to complete Foundations before live training', 'gym-core' ),
				'id'      => 'gym_core_foundations_enabled',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'             => __( 'Phase 1 — Classes Before Coach Rolls', 'gym-core' ),
				'desc'              => __( 'Classes required before first coach roll evaluation', 'gym-core' ),
				'id'                => 'gym_core_foundations_phase1_classes',
				'default'           => '10',
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
			),
			array(
				'title'             => __( 'Phase 2 — Coach Rolls Required', 'gym-core' ),
				'desc'              => __( 'Supervised rolls with coaches needed after Phase 1', 'gym-core' ),
				'id'                => 'gym_core_foundations_coach_rolls_required',
				'default'           => '2',
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
			),
			array(
				'title'             => __( 'Phase 3 — Total Classes to Clear', 'gym-core' ),
				'desc'              => __( 'Total classes (Phases 1+3 combined) required to clear Foundations', 'gym-core' ),
				'id'                => 'gym_core_foundations_total_classes',
				'default'           => '25',
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_foundations_options',
			),

			// --- Adult BJJ Promotion Thresholds ---
			array(
				'title' => __( 'Adult BJJ — Promotion Thresholds', 'gym-core' ),
				'desc'  => __( 'Minimum days and classes at each belt before eligibility. Black Belt uses degrees (up to 10) instead of stripes.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_adult_bjj_thresholds',
			),
			array(
				'title'   => __( 'White Belt — Min Days', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_white_days',
				'default' => '25',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'White Belt — Min Classes', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_white_classes',
				'default' => '17',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Blue Belt — Min Days', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_blue_days',
				'default' => '500',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Blue Belt — Min Classes', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_blue_classes',
				'default' => '225',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Purple Belt — Min Days', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_purple_days',
				'default' => '700',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Purple Belt — Min Classes', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_purple_classes',
				'default' => '400',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Brown Belt — Min Days', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_brown_days',
				'default' => '700',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Brown Belt — Min Classes', 'gym-core' ),
				'id'      => 'gym_core_threshold_adult_bjj_brown_classes',
				'default' => '400',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_adult_bjj_thresholds',
			),

			// --- Kids BJJ Promotion Thresholds ---
			array(
				'title' => __( 'Kids BJJ — Promotion Thresholds', 'gym-core' ),
				'desc'  => __( 'Defaults: 340 days / 64 classes per belt (lower for first and last belts). 13 belt levels with 4 stripes each. Per-rank overrides available via the gym_core_rank_thresholds filter.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_kids_bjj_thresholds',
			),
			array(
				'title'   => __( 'Default — Min Days', 'gym-core' ),
				'desc'    => __( 'Applied to most Kids belts (Grey/White through Green/White)', 'gym-core' ),
				'id'      => 'gym_core_threshold_kids_bjj_default_days',
				'default' => '340',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Default — Min Classes', 'gym-core' ),
				'id'      => 'gym_core_threshold_kids_bjj_default_classes',
				'default' => '64',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'White Belt — Min Days', 'gym-core' ),
				'desc'    => __( 'First belt (typically 0)', 'gym-core' ),
				'id'      => 'gym_core_threshold_kids_bjj_white_days',
				'default' => '0',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'White Belt — Min Classes', 'gym-core' ),
				'id'      => 'gym_core_threshold_kids_bjj_white_classes',
				'default' => '0',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_kids_bjj_thresholds',
			),

			// --- Kickboxing Level Thresholds ---
			array(
				'title' => __( 'Kickboxing — Level Thresholds', 'gym-core' ),
				'desc'  => __( 'Kickboxing uses a simple two-level system. No stripes or belts — just progression levels.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_kickboxing_thresholds',
			),
			array(
				'title'   => __( 'Level 2 — Min Days', 'gym-core' ),
				'id'      => 'gym_core_threshold_kickboxing_level2_days',
				'default' => '500',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'title'   => __( 'Level 2 — Min Classes', 'gym-core' ),
				'id'      => 'gym_core_threshold_kickboxing_level2_classes',
				'default' => '200',
				'type'    => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_kickboxing_thresholds',
			),
		);
	}

	/**
	 * Attendance settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_attendance_settings(): array {
		return array(
			array(
				'title' => __( 'Attendance & Check-In Settings', 'gym-core' ),
				'desc'  => __( 'Configure the check-in kiosk and attendance tracking.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_attendance_options',
			),
			array(
				'title'   => __( 'Check-In Methods', 'gym-core' ),
				'desc'    => __( 'Allowed check-in methods on the kiosk', 'gym-core' ),
				'id'      => 'gym_core_checkin_methods',
				'default' => array( 'qr', 'search', 'manual' ),
				'type'    => 'multiselect',
				'options' => array(
					'qr'     => __( 'QR Code Scan', 'gym-core' ),
					'search' => __( 'Name Search', 'gym-core' ),
					'manual' => __( 'Manual (Staff)', 'gym-core' ),
				),
				'class'   => 'wc-enhanced-select',
			),
			array(
				'title'             => __( 'Kiosk Auto-Logout', 'gym-core' ),
				'desc'              => __( 'Seconds of inactivity before kiosk resets', 'gym-core' ),
				'id'                => 'gym_core_kiosk_timeout',
				'default'           => '10',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '5',
					'max'  => '60',
					'step' => '1',
				),
			),
			array(
				'title'   => __( 'Prevent Duplicate Check-Ins', 'gym-core' ),
				'desc'    => __( 'Block a member from checking into the same class twice in one day', 'gym-core' ),
				'id'      => 'gym_core_prevent_duplicate_checkin',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_attendance_options',
			),
		);
	}

	/**
	 * Gamification settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_gamification_settings(): array {
		return array(
			array(
				'title' => __( 'Gamification Settings', 'gym-core' ),
				'desc'  => __( 'Configure badges, streaks, and achievement tracking.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_gamification_options',
			),
			array(
				'title'             => __( 'Streak Freeze Allowance', 'gym-core' ),
				'desc'              => __( 'Number of streak freezes per quarter (0 to disable)', 'gym-core' ),
				'id'                => 'gym_core_streak_freezes_per_quarter',
				'default'           => '1',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '4',
					'step' => '1',
				),
			),
			array(
				'title'   => __( 'Notify on Badge Earned', 'gym-core' ),
				'desc'    => __( 'Send SMS and email when a member earns a badge', 'gym-core' ),
				'id'      => 'gym_core_notify_on_badge',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_gamification_options',
			),
		);
	}

	/**
	 * SMS / Twilio settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_sms_settings(): array {
		return array(
			array(
				'title' => __( 'Twilio SMS Settings', 'gym-core' ),
				'desc'  => __( 'Configure Twilio credentials for SMS notifications.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_sms_options',
			),
			array(
				'title'       => __( 'Twilio Account SID', 'gym-core' ),
				'id'          => 'gym_core_twilio_account_sid',
				'default'     => '',
				'type'        => 'text',
				'placeholder' => 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
			),
			array(
				'title'       => __( 'Twilio Auth Token', 'gym-core' ),
				'id'          => 'gym_core_twilio_auth_token',
				'default'     => '',
				'type'        => 'password',
				'placeholder' => __( 'Auth token (hidden after save)', 'gym-core' ),
			),
			array(
				'title'       => __( 'Twilio Phone Number', 'gym-core' ),
				'desc'        => __( 'The Twilio phone number to send SMS from (E.164 format)', 'gym-core' ),
				'id'          => 'gym_core_twilio_phone_number',
				'default'     => '',
				'type'        => 'text',
				'placeholder' => '+1XXXXXXXXXX',
			),
			array(
				'title'             => __( 'Rate Limit', 'gym-core' ),
				'desc'              => __( 'Maximum SMS per contact per hour', 'gym-core' ),
				'id'                => 'gym_core_sms_rate_limit',
				'default'           => '1',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '10',
					'step' => '1',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_sms_options',
			),
		);
	}

	/**
	 * REST API settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_api_settings(): array {
		return array(
			array(
				'title' => __( 'REST API Settings', 'gym-core' ),
				'desc'  => __( 'Configure the gym/v1 REST API endpoints used by AI agents (Gandalf).', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_api_options',
			),
			array(
				'title'   => __( 'Require Authentication', 'gym-core' ),
				'desc'    => __( 'Require Application Password or JWT for all API requests', 'gym-core' ),
				'id'      => 'gym_core_api_require_auth',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_api_options',
			),
		);
	}
}
