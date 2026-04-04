<?php
/**
 * Kiosk check-in endpoint.
 *
 * Registers the /check-in/ URL with a minimal full-screen template
 * designed for tablet use at the gym entrance. The template loads
 * kiosk-specific CSS and JS that handle member lookup, QR scanning,
 * and check-in confirmation via the gym/v1/check-in REST endpoint.
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Attendance;

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
	}

	/**
	 * Registers custom REST API fields on the user object for kiosk display.
	 *
	 * Exposes `gym_foundations_active` so the kiosk can badge Foundations members.
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
	 * Outputs a standalone HTML page (no theme header/footer) optimized
	 * for touch devices. Enqueues kiosk-specific CSS and JS.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function render_kiosk(): void {
		if ( ! get_query_var( 'gym_kiosk' ) ) {
			return;
		}

		// The kiosk page requires a logged-in staff member for API auth.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/' . self::SLUG . '/' ) ) );
			exit;
		}

		$timeout  = (int) get_option( 'gym_core_kiosk_timeout', 10 );
		$location = $this->get_kiosk_location();

		// Enqueue assets.
		wp_enqueue_style(
			'gym-kiosk',
			GYM_CORE_URL . 'assets/css/kiosk.css',
			array(),
			GYM_CORE_VERSION
		);

		wp_enqueue_script(
			'gym-kiosk',
			GYM_CORE_URL . 'assets/js/kiosk.js',
			array(),
			GYM_CORE_VERSION,
			true
		);

		wp_localize_script(
			'gym-kiosk',
			'gymKiosk',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'gym/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'location' => $location,
				'timeout'  => $timeout,
				'strings'  => array(
					'title'          => __( 'Haanpaa Martial Arts', 'gym-core' ),
					'subtitle'       => __( 'Tap to check in', 'gym-core' ),
					'searchLabel'    => __( 'Search by Name', 'gym-core' ),
					'searchPlaceholder' => __( 'Start typing your name...', 'gym-core' ),
					'checkingIn'     => __( 'Checking in...', 'gym-core' ),
					'success'        => __( 'Checked in!', 'gym-core' ),
					'welcomeBack'    => __( 'Welcome back,', 'gym-core' ),
					'error'          => __( 'Check-in failed', 'gym-core' ),
					'tryAgain'       => __( 'Tap to try again', 'gym-core' ),
					'noResults'      => __( 'No members found', 'gym-core' ),
					'selectClass'    => __( 'Select your class', 'gym-core' ),
					/* translators: %d: number of consecutive weeks */
					'weekStreak'     => __( 'Week %d streak!', 'gym-core' ),
				),
			)
		);

		// Output standalone HTML.
		$this->render_template( $location, $timeout );
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
			return $user_location;
		}

		// Fall back to cookie.
		return sanitize_text_field( wp_unslash( $_COOKIE['gym_location'] ?? 'rockford' ) );
	}

	/**
	 * Renders the kiosk HTML template.
	 *
	 * @param string $location Location slug.
	 * @param int    $timeout  Auto-reset timeout in seconds.
	 * @return void
	 */
	private function render_template( string $location, int $timeout ): void {
		$template_path = GYM_CORE_PATH . 'templates/kiosk.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			// Inline fallback if template file doesn't exist yet.
			$this->render_inline_template( $location );
		}
	}

	/**
	 * Renders an inline kiosk template.
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
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Check in — Haanpaa Martial Arts', 'gym-core' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="gym-kiosk" data-location="<?php echo esc_attr( $location ); ?>">

	<div id="gym-kiosk-app">
		<!-- Screen: Search -->
		<div class="kiosk-screen kiosk-screen--search active" id="kiosk-search">
			<div class="kiosk-header">
				<h1 class="kiosk-title"><?php esc_html_e( 'Haanpaa Martial Arts', 'gym-core' ); ?></h1>
				<p class="kiosk-subtitle"><?php echo esc_html( ucfirst( $location ) ); ?></p>
			</div>
			<div class="kiosk-search-box">
				<label for="kiosk-search-input" class="kiosk-search-label">
					<?php esc_html_e( 'Type your name to check in', 'gym-core' ); ?>
				</label>
				<label for="kiosk-search-input" class="screen-reader-text">
					<?php esc_html_e( 'Search by Name', 'gym-core' ); ?>
				</label>
				<input
					type="text"
					id="kiosk-search-input"
					class="kiosk-input"
					placeholder="<?php esc_attr_e( 'Tap here and type your name...', 'gym-core' ); ?>"
					autocomplete="off"
					autocorrect="off"
					spellcheck="false"
				>
			</div>
			<div id="kiosk-results" class="kiosk-results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'gym-core' ); ?>"></div>
		</div>

		<!-- Screen: Class Selection -->
		<div class="kiosk-screen kiosk-screen--classes" id="kiosk-classes">
			<div class="kiosk-header">
				<h2 class="kiosk-title" id="kiosk-member-name"></h2>
				<p class="kiosk-subtitle"><?php esc_html_e( 'Select your class', 'gym-core' ); ?></p>
			</div>
			<div id="kiosk-class-list" class="kiosk-class-list" role="listbox" aria-label="<?php esc_attr_e( 'Available classes', 'gym-core' ); ?>"></div>
			<button type="button" class="kiosk-btn kiosk-btn--back" id="kiosk-back">
				<?php esc_html_e( 'Back', 'gym-core' ); ?>
			</button>
		</div>

		<!-- Screen: Success -->
		<div class="kiosk-screen kiosk-screen--success" id="kiosk-success">
			<div class="kiosk-success-icon" aria-hidden="true">&#10003;</div>
			<h2 class="kiosk-title"><?php esc_html_e( 'Checked in!', 'gym-core' ); ?></h2>
			<p class="kiosk-welcome" id="kiosk-welcome-msg"></p>
			<p class="kiosk-streak" id="kiosk-streak-display" style="display:none;"></p>
			<p class="kiosk-rank" id="kiosk-rank-display"></p>
		</div>

		<!-- Screen: Error -->
		<div class="kiosk-screen kiosk-screen--error" id="kiosk-error">
			<div class="kiosk-error-icon" aria-hidden="true">&#10007;</div>
			<h2 class="kiosk-title"><?php esc_html_e( 'Check-in failed', 'gym-core' ); ?></h2>
			<p class="kiosk-error-msg" id="kiosk-error-msg"></p>
			<button type="button" class="kiosk-btn" id="kiosk-retry">
				<?php esc_html_e( 'Tap to try again', 'gym-core' ); ?>
			</button>
		</div>

		<!-- Loading overlay -->
		<div class="kiosk-loading" id="kiosk-loading">
			<div class="kiosk-spinner" aria-label="<?php esc_attr_e( 'Loading', 'gym-core' ); ?>"></div>
		</div>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
		<?php
	}
}
