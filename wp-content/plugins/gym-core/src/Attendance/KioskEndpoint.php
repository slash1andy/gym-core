<?php
/**
 * Kiosk check-in endpoint.
 *
 * Registers the /check-in/ URL with a full-screen React template
 * designed for tablet use at the gym entrance. Data is PHP-injected
 * via window.gymKiosk so the React app boots without a REST round-trip.
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Attendance;

use Gym_Core\API\ClassScheduleController;
use Gym_Core\Data\TableManager;
use Gym_Core\Location\Taxonomy;
use Gym_Core\Schedule\ClassPostType;

/**
 * Registers the kiosk check-in page and its assets.
 */
final class KioskEndpoint {

	/**
	 * Rewrite endpoint slug.
	 *
	 * @var string
	 */
	public const SLUG = 'check-in';

	/**
	 * Registers hooks.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'render_kiosk' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 25 );
	}

	/**
	 * Registers a Check-In submenu under the Gym admin menu.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'gym-core',
			__( 'Check-In Kiosk', 'gym-core' ),
			__( 'Check-In', 'gym-core' ),
			'gym_check_in_member',
			'gym-checkin-redirect',
			array( $this, 'redirect_to_kiosk' )
		);
	}

	/**
	 * Redirect the admin menu link to the front-end kiosk URL.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function redirect_to_kiosk(): void {
		wp_safe_redirect( home_url( '/' . self::SLUG . '/' ) );
		exit;
	}

	/**
	 * Registers custom REST API fields on the user object for kiosk display.
	 *
	 * @since 3.1.0
	 *
	 * @return void
	 */
	public function register_rest_fields(): void {
		register_rest_field(
			'user',
			'gym_foundations_active',
			array(
				'get_callback' => static function ( array $user ): bool {
					return (bool) get_user_meta( $user['id'], '_gym_foundations_active', true );
				},
				'schema'       => array(
					'description' => __( 'Whether the member is currently in the Foundations program.', 'gym-core' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}

	/**
	 * Adds rewrite rule for /check-in/.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^' . self::SLUG . '/?$',
			'index.php?gym_kiosk=1',
			'top'
		);
	}

	/**
	 * Registers the query var.
	 *
	 * @since 1.3.0
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = 'gym_kiosk';
		return $vars;
	}

	/**
	 * Renders the kiosk template when the query var is set.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function render_kiosk(): void {
		if ( ! get_query_var( 'gym_kiosk' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/' . self::SLUG . '/' ) ) );
			exit;
		}

		$location = $this->get_kiosk_location();

		// Enqueue kiosk CSS only — JS is loaded directly in the template
		// via type="text/babel" tags so Babel standalone can compile JSX at runtime.
		wp_enqueue_style(
			'gym-kiosk',
			GYM_CORE_URL . 'assets/css/kiosk.css',
			array(),
			GYM_CORE_VERSION
		);

		$this->render_template( $location );
		exit;
	}

	/**
	 * Determines the kiosk location from user meta or the location cookie.
	 *
	 * @return string Location slug (defaults to 'rockford').
	 */
	private function get_kiosk_location(): string {
		$user_location = get_user_meta( get_current_user_id(), 'gym_location', true );
		if ( $user_location ) {
			return (string) $user_location;
		}

		$cookie_location = isset( $_COOKIE['gym_location'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['gym_location'] ) )
			: '';

		if ( '' === $cookie_location ) {
			$locations       = Taxonomy::get_location_labels();
			$cookie_location = ! empty( $locations ) ? array_key_first( $locations ) : '';
		}
		return (string) $cookie_location;
	}

	/**
	 * Renders the kiosk HTML template.
	 *
	 * @param string $location Location slug.
	 * @return void
	 */
	private function render_template( string $location ): void {
		$template_path = GYM_CORE_PATH . 'templates/kiosk.php';

		if ( file_exists( $template_path ) ) {
			$kiosk_data = $this->build_kiosk_data( $location );
			include $template_path;
		} else {
			$this->render_inline_template( $location );
		}
	}

	/**
	 * Builds the kiosk bootstrap data injected as window.gymKiosk.
	 *
	 * @param string $location Location slug.
	 * @return array<string, mixed>
	 */
	private function build_kiosk_data( string $location ): array {
		return array(
			'restUrl'      => esc_url_raw( rest_url( 'gym/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'location'     => $location,
			'members'      => $this->get_kiosk_members(),
			'nextClass'    => $this->get_next_class( $location ),
			'todayClasses' => $this->get_today_classes( $location ),
			'todayCount'   => $this->get_today_count( $location ),
		);
	}

	/**
	 * Returns all active members for client-side filtering.
	 *
	 * Loads all customer/subscriber users upfront so the search is instant
	 * without REST round-trips. Works for typical gym rosters (< 500 members).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_kiosk_members(): array {
		$query = new \WP_User_Query(
			array(
				'role__in' => array( 'customer', 'subscriber' ),
				'number'   => -1,
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'fields'   => 'all',
			)
		);

		$members = array();
		foreach ( $query->get_results() as $user ) {
			$members[] = array(
				'id'      => $user->ID,
				'first'   => get_user_meta( $user->ID, 'first_name', true ) ?: '',
				'last'    => get_user_meta( $user->ID, 'last_name', true ) ?: '',
				'kind'    => get_user_meta( $user->ID, 'gym_member_type', true ) ?: 'adult',
				'program' => get_user_meta( $user->ID, 'gym_program', true ) ?: '',
				'belt'    => get_user_meta( $user->ID, 'gym_belt', true ) ?: '',
			);
		}
		return $members;
	}

	/**
	 * Returns the next (or current) class for today at the given location.
	 *
	 * Picks the class whose start_time is soonest after the current time.
	 * Falls back to the last class of the day if all have ended.
	 *
	 * @param string $location Location slug.
	 * @return array<string, mixed>|null Formatted class or null if none scheduled.
	 */
	private function get_next_class( string $location ): ?array {
		$classes = $this->get_today_classes( $location );
		if ( empty( $classes ) ) {
			return null;
		}

		$now_time = (int) gmdate( 'Hi' ); // e.g. 1830 for 18:30
		$next     = null;

		foreach ( $classes as $cls ) {
			$start_time = (int) str_replace( ':', '', (string) ( $cls['start_time'] ?? '' ) );
			if ( $start_time >= $now_time ) {
				$next = $cls;
				break;
			}
		}

		// All classes passed today — return the last one so the card isn't blank.
		return $next ?? $classes[ count( $classes ) - 1 ];
	}

	/**
	 * Returns all classes scheduled for today at the given location, sorted by start time.
	 *
	 * @param string $location Location slug.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_today_classes( string $location ): array {
		$day_of_week = strtolower( gmdate( 'l' ) ); // 'monday', 'tuesday', etc.

		$query = new \WP_Query(
			array(
				'post_type'      => ClassPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_gym_class_start_time',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_gym_class_day_of_week',
						'value'   => $day_of_week,
						'compare' => '=',
					),
					// Exclude explicitly hidden classes, but include classes with no status meta
					// (status defaults to 'active' in format_class when the meta key is absent).
					array(
						'relation' => 'OR',
						array(
							'key'     => '_gym_class_status',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_gym_class_status',
							'value'   => 'hidden',
							'compare' => '!=',
						),
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return array();
		}

		$controller = new ClassScheduleController();
		$classes    = array();

		foreach ( $query->posts as $post ) {
			$cls = $controller->format_class_public( $post );
			if ( ! $cls ) {
				continue;
			}
			// Filter by location if set (empty location = show all).
			if ( $location && ! empty( $cls['location'] ) && $cls['location'] !== $location ) {
				continue;
			}
			$classes[] = $cls;
		}

		wp_reset_postdata();
		return $classes;
	}

	/**
	 * Returns the number of check-ins recorded today for the given location.
	 *
	 * @param string $location Location slug.
	 * @return int
	 */
	private function get_today_count( string $location ): int {
		global $wpdb;

		$tables = TableManager::get_table_names();
		$today  = gmdate( 'Y-m-d' );

		if ( $location ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tables['attendance']} WHERE location = %s AND DATE(checked_in_at) = %s",
					$location,
					$today
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['attendance']} WHERE DATE(checked_in_at) = %s",
				$today
			)
		);
	}

	/**
	 * Renders an inline kiosk template (fallback when templates/kiosk.php is missing).
	 *
	 * @param string $location Location slug.
	 * @return void
	 */
	private function render_inline_template( string $location ): void {
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: brand name */
			__( 'Check in — %s', 'gym-core' ),
			\Gym_Core\Utilities\Brand::name()
		)
	);
	?>
</title>
<?php wp_head(); ?>
</head>
<body style="margin:0;background:#0A0A0A;color:#F6F4EE;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;">
<p style="opacity:.5;font-size:1rem;">
	<?php esc_html_e( 'Kiosk template not found. Please reinstall the gym-core plugin.', 'gym-core' ); ?>
</p>
<?php wp_footer(); ?>
</body>
</html>
		<?php
	}
}
