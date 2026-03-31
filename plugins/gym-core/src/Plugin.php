<?php
/**
 * Main plugin loader.
 *
 * @package Gym_Core
 */

declare( strict_types=1 );

namespace Gym_Core;

/**
 * Singleton that bootstraps all plugin modules.
 *
 * Usage: Plugin::instance()->init()
 * Do not instantiate directly — use the static factory.
 */
final class Plugin {

	/**
	 * The single instance of this class.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Shared Location Manager — one instance per request so the in-memory
	 * location cache is consistent across the location module and REST API.
	 *
	 * @var Location\Manager|null
	 */
	private ?Location\Manager $location_manager = null;

	/**
	 * Attendance store — shared instance for DI.
	 *
	 * @var Attendance\AttendanceStore|null
	 */
	private ?Attendance\AttendanceStore $attendance_store = null;

	/**
	 * Rank store — shared instance for DI.
	 *
	 * @var Rank\RankStore|null
	 */
	private ?Rank\RankStore $rank_store = null;

	/**
	 * Check-in validator — shared instance for DI.
	 *
	 * @var Attendance\CheckInValidator|null
	 */
	private ?Attendance\CheckInValidator $checkin_validator = null;

	/**
	 * Promotion eligibility engine — shared instance for DI.
	 *
	 * @var Attendance\PromotionEligibility|null
	 */
	private ?Attendance\PromotionEligibility $promotion_eligibility = null;

	/**
	 * Foundations clearance — shared instance for DI.
	 *
	 * @var Attendance\FoundationsClearance|null
	 */
	private ?Attendance\FoundationsClearance $foundations_clearance = null;

	/**
	 * Private constructor — prevents direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes the plugin by registering all service providers.
	 *
	 * Called once from `plugins_loaded` after WooCommerce is confirmed active.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_textdomain();
		$this->register_capabilities();
		$this->register_admin_modules();
		$this->register_location_modules();
		$this->register_api_modules();

		$this->register_schedule_modules();
		$this->register_attendance_modules();
		$this->register_briefing_modules();
		$this->register_notification_modules();
		$this->register_kiosk_modules();
		$this->register_gamification_modules();
		$this->register_integration_modules();

		/**
		 * Fires after Gym Core has finished loading.
		 *
		 * Use this hook to register extensions or override behaviour.
		 *
		 * @since 1.0.0
		 */
		do_action( 'gym_core_loaded' );
	}

	/**
	 * Registers the kiosk check-in endpoint.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function register_kiosk_modules(): void {
		$kiosk = new Attendance\KioskEndpoint();
		$kiosk->register_hooks();
	}

	/**
	 * Registers admin modules (settings pages, user profile fields).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_admin_modules(): void {
		if ( is_admin() ) {
			$settings = new Admin\Settings();
			$settings->register_hooks();

			// Admin dashboards — class_exists guards allow safe loading before
			// the files are created by another agent.
			if ( class_exists( Admin\AttendanceDashboard::class ) ) {
				$attendance_dashboard = new Admin\AttendanceDashboard();
				$attendance_dashboard->register_hooks();
			}

			if ( class_exists( Admin\PromotionDashboard::class ) ) {
				$promotion_dashboard = new Admin\PromotionDashboard();
				$promotion_dashboard->register_hooks();
			}
		}

		// UserProfileRank hooks into show_user_profile / edit_user_profile
		// which fire on both admin and front-end profile pages.
		if ( class_exists( Admin\UserProfileRank::class ) ) {
			$user_profile_rank = new Admin\UserProfileRank();
			$user_profile_rank->register_hooks();
		}
	}

	/**
	 * Registers the class schedule module.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private function register_schedule_modules(): void {
		$class_post_type = new Schedule\ClassPostType();
		$class_post_type->register_hooks();
	}

	/**
	 * Registers REST API controllers.
	 *
	 * Each controller hooks into rest_api_init to register its routes, so this
	 * method is safe to call on every request — route registration is deferred
	 * until WordPress fires rest_api_init.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_api_modules(): void {
		$location_controller = new API\LocationController( $this->get_location_manager() );
		$location_controller->register_hooks();

		$schedule_controller = new API\ClassScheduleController();
		$schedule_controller->register_hooks();

		// Controllers that need the data stores initialized in register_attendance_modules().
		// Deferred to gym_core_loaded so stores are available.
		add_action(
			'gym_core_loaded',
			function (): void {
				$rank_controller = new API\RankController( $this->rank_store, $this->attendance_store );
				$rank_controller->register_hooks();

				$attendance_controller = new API\AttendanceController( $this->attendance_store, $this->checkin_validator );
				$attendance_controller->register_hooks();

				$promotion_controller = new API\PromotionController( $this->promotion_eligibility );
				$promotion_controller->register_hooks();

				// SMS controller.
				if ( 'yes' === get_option( 'gym_core_sms_enabled', 'no' ) ) {
					$twilio_client  = new SMS\TwilioClient();
					$sms_controller = new API\SMSController( $twilio_client );
					$sms_controller->register_hooks();

					$inbound_handler = new SMS\InboundHandler( $twilio_client );
					$inbound_handler->register_hooks();
				}

				// Foundations controller.
				$foundations_controller = new API\FoundationsController( $this->foundations_clearance );
				$foundations_controller->register_hooks();

				// Briefing controller.
				if ( 'yes' === get_option( 'gym_core_briefing_enabled', 'yes' ) ) {
					$briefing_generator = new Briefing\BriefingGenerator(
						$this->attendance_store,
						$this->rank_store,
						$this->foundations_clearance,
						$this->promotion_eligibility
					);

					$briefing_controller = new API\BriefingController( $briefing_generator );
					$briefing_controller->register_hooks();
				}

				// Gamification controller.
				if ( 'yes' === get_option( 'gym_core_gamification_enabled', 'yes' ) ) {
					$streak_tracker = new Gamification\StreakTracker( $this->attendance_store );
					$badge_engine   = new Gamification\BadgeEngine( $this->attendance_store, $streak_tracker );

					$gamification_controller = new API\GamificationController( $badge_engine, $streak_tracker );
					$gamification_controller->register_hooks();
				}
			}
		);
	}

	/**
	 * Returns the shared Location Manager instance, creating it on first call.
	 *
	 * Using a single Manager per request ensures the in-memory location cache
	 * is consistent whether the location is read by the frontend modules or the
	 * REST API controller.
	 *
	 * @since 1.0.0
	 *
	 * @return Location\Manager
	 */
	private function get_location_manager(): Location\Manager {
		if ( null === $this->location_manager ) {
			$this->location_manager = new Location\Manager();
		}

		return $this->location_manager;
	}

	/**
	 * Registers the attendance and promotion eligibility modules.
	 *
	 * Makes store instances available for dependency injection into
	 * REST API endpoints and admin dashboards.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private function register_attendance_modules(): void {
		// Stores are instantiated here and available for injection.
		// They don't register hooks themselves — they're consumed by
		// REST endpoints, admin pages, and the gamification engine.
		$this->attendance_store        = new Attendance\AttendanceStore();
		$this->rank_store              = new Rank\RankStore();
		$this->checkin_validator       = new Attendance\CheckInValidator( $this->attendance_store );
		$this->promotion_eligibility   = new Attendance\PromotionEligibility( $this->attendance_store, $this->rank_store );
		$this->foundations_clearance   = new Attendance\FoundationsClearance( $this->attendance_store );
	}

	/**
	 * Registers the briefing module (announcement CPT).
	 *
	 * The AnnouncementPostType CPT is always registered so the admin UI is
	 * available. The REST API controller and briefing generator are registered
	 * in register_api_modules() behind the gym_core_briefing_enabled option.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	private function register_briefing_modules(): void {
		$announcement_cpt = new Briefing\AnnouncementPostType();
		$announcement_cpt->register_hooks();
	}

	/**
	 * Registers the promotion notification module.
	 *
	 * PromotionNotifier listens for gym_core_rank_changed and
	 * gym_core_foundations_cleared to send email/SMS notifications.
	 * Only active when SMS is enabled (TwilioClient dependency).
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function register_notification_modules(): void {
		$twilio_client      = new SMS\TwilioClient();
		$promotion_notifier = new Notifications\PromotionNotifier( $twilio_client );
		$promotion_notifier->register_hooks();
	}

	/**
	 * Registers the gamification module (badges, streaks).
	 *
	 * The BadgeEngine hooks into gym_core_attendance_recorded and
	 * gym_core_rank_changed to automatically evaluate and award badges.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function register_gamification_modules(): void {
		if ( 'yes' !== get_option( 'gym_core_gamification_enabled', 'yes' ) ) {
			return;
		}

		$streak_tracker = new Gamification\StreakTracker( $this->attendance_store );
		$badge_engine   = new Gamification\BadgeEngine( $this->attendance_store, $streak_tracker );
		$badge_engine->register_hooks();
	}

	/**
	 * Registers custom capabilities and roles.
	 *
	 * The Capabilities class hooks into admin_init to sync capabilities
	 * when the plugin version changes, ensuring updates are seamless.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_capabilities(): void {
		$capabilities = new Capabilities();
		$capabilities->register_hooks();
	}

	/**
	 * Bootstraps all location-related modules.
	 *
	 * Registers the taxonomy, manager AJAX handler, product filter, order
	 * location recording, and frontend selector. Block checkout integration
	 * is deferred until woocommerce_blocks_loaded to ensure the Blocks package
	 * is available before we reference its classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_location_modules(): void {
		$taxonomy = new Location\Taxonomy();
		$taxonomy->register_hooks();

		$manager = $this->get_location_manager();
		$manager->register_hooks();

		$product_filter = new Location\ProductFilter( $manager );
		$product_filter->register_hooks();

		$order_location = new Location\OrderLocation( $manager );
		$order_location->register_hooks();

		$selector = new Frontend\LocationSelector( $manager );
		$selector->register_hooks();

		// Block checkout integration — deferred until WooCommerce Blocks is ready.
		add_action(
			'woocommerce_blocks_loaded',
			static function () use ( $manager ): void {
				$store_api = new Location\StoreApiExtension( $manager );
				$store_api->register();

				// Register block checkout integration when the registry is available.
				add_action(
					'woocommerce_blocks_checkout_block_registration',
					static function ( $registry ) use ( $manager ): void {
						$registry->register( new Location\BlockIntegration( $manager ) );
					}
				);
			}
		);
	}

	/**
	 * Registers the Form-to-CRM integration module.
	 *
	 * Hooks into Jetpack Forms and WooCommerce checkout to create
	 * Jetpack CRM contacts and pipeline entries. Only activates when
	 * the integration is enabled and Jetpack CRM is detected.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	private function register_integration_modules(): void {
		$form_to_crm = new Integrations\FormToCrm();
		$form_to_crm->register_hooks();
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'gym-core',
			false,
			dirname( GYM_CORE_BASENAME ) . '/languages'
		);
	}
}
