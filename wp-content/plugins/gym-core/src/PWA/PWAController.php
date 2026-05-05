<?php
/**
 * PWA wrapper controller.
 *
 * Injects manifest + Apple PWA meta tags, exposes service worker and manifest
 * at their expected root-scope paths, and wires up the install-prompt banner.
 *
 * Endpoints served:
 *   /sw.js        — service worker (must be at root scope)
 *   /manifest.json — web app manifest
 *   /offline       — offline fallback page
 *
 * @package Gym_Core\PWA
 * @since   5.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\PWA;

/**
 * Registers all PWA surface-area hooks.
 */
final class PWAController {

	/**
	 * Query variable for service-worker requests.
	 *
	 * @var string
	 */
	private const QV_SW = 'gym_pwa_sw';

	/**
	 * Query variable for manifest requests.
	 *
	 * @var string
	 */
	private const QV_MANIFEST = 'gym_pwa_manifest';

	/**
	 * Query variable for offline-page requests.
	 *
	 * @var string
	 */
	private const QV_OFFLINE = 'gym_pwa_offline';

	/**
	 * Theme-relative base path for PWA assets.
	 *
	 * @var string
	 */
	private const PWA_ASSET_DIR = '/assets/pwa';

	/**
	 * Registers all WordPress hooks.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_pwa_asset' ) );
		add_action( 'wp_head', array( $this, 'inject_head_tags' ) );
		add_action( 'wp_footer', array( $this, 'inject_service_worker_registration' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_install_prompt' ) );
	}

	/**
	 * Adds rewrite rules for PWA assets.
	 *
	 * Service workers must be served from root scope — /sw.js is required.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^sw\.js$', 'index.php?' . self::QV_SW . '=1', 'top' );
		add_rewrite_rule( '^manifest\.json$', 'index.php?' . self::QV_MANIFEST . '=1', 'top' );
		add_rewrite_rule( '^offline$', 'index.php?' . self::QV_OFFLINE . '=1', 'top' );
	}

	/**
	 * Registers custom query variables.
	 *
	 * @since 5.0.0
	 *
	 * @param array<string> $vars Existing query variables.
	 * @return array<string>
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::QV_SW;
		$vars[] = self::QV_MANIFEST;
		$vars[] = self::QV_OFFLINE;
		return $vars;
	}

	/**
	 * Serves the requested PWA asset (sw.js / manifest.json / offline.html) if
	 * the current request matches one of the registered query variables.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function maybe_serve_pwa_asset(): void {
		if ( get_query_var( self::QV_SW ) ) {
			$this->serve_file(
				get_stylesheet_directory() . self::PWA_ASSET_DIR . '/service-worker.js',
				'application/javascript',
				array( 'Service-Worker-Allowed' => '/' )
			);
		}

		if ( get_query_var( self::QV_MANIFEST ) ) {
			$this->serve_file(
				get_stylesheet_directory() . self::PWA_ASSET_DIR . '/manifest.json',
				'application/manifest+json'
			);
		}

		if ( get_query_var( self::QV_OFFLINE ) ) {
			$this->serve_file(
				get_stylesheet_directory() . self::PWA_ASSET_DIR . '/offline.html',
				'text/html; charset=UTF-8'
			);
		}
	}

	/**
	 * Injects PWA-related tags into <head>.
	 *
	 * Includes: web app manifest link, Apple PWA meta tags, Apple touch icon.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function inject_head_tags(): void {
		$manifest_url    = self::get_manifest_url();
		$apple_icon_url  = get_stylesheet_directory_uri() . '/assets/images/icon-192.png';

		printf(
			'<link rel="manifest" href="%s">' . "\n",
			esc_url( $manifest_url )
		);

		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
		printf(
			'<meta name="apple-mobile-web-app-title" content="%s">' . "\n",
			esc_attr__( 'Haanpaa MA', 'gym-core' )
		);
		printf(
			'<link rel="apple-touch-icon" href="%s">' . "\n",
			esc_url( $apple_icon_url )
		);
	}

	/**
	 * Injects the service worker registration script just before </body>.
	 *
	 * Silently skips if the browser does not support service workers.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function inject_service_worker_registration(): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script>
if(\'serviceWorker\'in navigator){
  navigator.serviceWorker.register(\'/sw.js\')
    .catch(function(e){console.warn(\'[HMA SW]\',e);});
}
</script>
';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Enqueues the install-prompt script on member portal pages.
	 *
	 * Only loaded on /my-account/ and its sub-pages.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function enqueue_install_prompt(): void {
		if ( ! is_account_page() ) {
			return;
		}

		wp_enqueue_script(
			'gym-pwa-install',
			plugins_url( 'assets/js/pwa-install.js', GYM_CORE_FILE ),
			array(),
			GYM_CORE_VERSION,
			true
		);
	}

	/**
	 * Returns the absolute URL for the web app manifest.
	 *
	 * @since 5.0.0
	 *
	 * @return string
	 */
	public static function get_manifest_url(): string {
		return home_url( '/manifest.json' );
	}

	// ─── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Outputs a file with correct headers then exits.
	 *
	 * @since 5.0.0
	 *
	 * @param string               $path            Absolute filesystem path.
	 * @param string               $content_type    MIME type for Content-Type header.
	 * @param array<string,string> $extra_headers   Additional headers to send.
	 * @return void
	 */
	private function serve_file( string $path, string $content_type, array $extra_headers = array() ): void {
		if ( ! is_readable( $path ) ) {
			status_header( 404 );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: ' . $content_type );

		foreach ( $extra_headers as $name => $value ) {
			header( $name . ': ' . $value );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.Security.EscapeOutput.OutputNotEscaped
		echo file_get_contents( $path );
		exit;
	}
}
