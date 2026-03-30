<?php
/**
 * Location manager.
 *
 * @package HMA_Core\Location
 */

declare( strict_types=1 );

namespace HMA_Core\Location;

/**
 * Manages the current visitor's active location.
 *
 * Resolution priority (highest to lowest):
 *   1. Logged-in user meta
 *   2. Cookie
 *   3. Empty string — no location selected yet
 *
 * Persistence:
 *   - Cookie set for all visitors (1-year lifetime, HttpOnly, SameSite=Lax)
 *   - User meta saved for logged-in users
 *
 * Location changes are applied via an AJAX endpoint available to both guests
 * and authenticated users.
 */
class Manager {

	/**
	 * Cookie name used to persist the selected location.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const COOKIE_NAME = 'hma_location';

	/**
	 * Cookie lifetime in seconds (1 year).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const COOKIE_EXPIRY = YEAR_IN_SECONDS;

	/**
	 * User meta key for the preferred location.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const USER_META_KEY = 'hma_location';

	/**
	 * AJAX action name for setting the location.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const AJAX_ACTION = 'hma_set_location';

	/**
	 * In-memory cache of the resolved location for the current request.
	 *
	 * Null means "not yet resolved". Empty string means "resolved: no location".
	 *
	 * @var string|null
	 */
	private ?string $current_location = null;

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_set_location' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_ajax_set_location' ) );
	}

	/**
	 * Returns the current visitor's active location slug.
	 *
	 * Resolves in priority order: user meta → cookie → empty string.
	 * Result is cached for the duration of the request.
	 *
	 * @since 1.0.0
	 *
	 * @return string Location slug ('rockford', 'beloit') or empty string.
	 */
	public function get_current_location(): string {
		if ( null !== $this->current_location ) {
			return $this->current_location;
		}

		// Logged-in users: prefer their saved meta over the cookie.
		if ( is_user_logged_in() ) {
			$meta = $this->get_from_user_meta( get_current_user_id() );
			if ( Taxonomy::is_valid( $meta ) ) {
				$this->current_location = $meta;
				return $this->current_location;
			}
		}

		// Fall back to the cookie (covers guests and logged-in users without saved meta).
		$cookie = $this->get_from_cookie();
		if ( Taxonomy::is_valid( $cookie ) ) {
			$this->current_location = $cookie;
			return $this->current_location;
		}

		$this->current_location = '';
		return $this->current_location;
	}

	/**
	 * Sets the active location for the current visitor.
	 *
	 * Persists to cookie for all visitors and to user meta for logged-in users.
	 * Invalidates the in-memory cache so subsequent calls to get_current_location()
	 * reflect the new value immediately.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The location slug to activate.
	 * @return bool True on success, false if the slug is not a valid location.
	 */
	public function set_location( string $slug ): bool {
		if ( ! Taxonomy::is_valid( $slug ) ) {
			return false;
		}

		$this->set_cookie( $slug );

		if ( is_user_logged_in() ) {
			$this->set_user_meta( get_current_user_id(), $slug );
		}

		$this->current_location = $slug;

		return true;
	}

	/**
	 * Sets the preferred location for a specific user by ID.
	 *
	 * Used when associating a user account with a location independently of
	 * the current request session (e.g. after order processing).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The WordPress user ID.
	 * @param string $slug    The location slug.
	 * @return bool True on success, false if either argument is invalid.
	 */
	public function set_user_location( int $user_id, string $slug ): bool {
		if ( $user_id <= 0 || ! Taxonomy::is_valid( $slug ) ) {
			return false;
		}

		$this->set_user_meta( $user_id, $slug );
		return true;
	}

	/**
	 * Returns the stored location for a specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return string Location slug or empty string if none stored.
	 */
	public function get_user_location( int $user_id ): string {
		return $this->get_from_user_meta( $user_id );
	}

	/**
	 * Invalidates the in-memory location cache.
	 *
	 * Useful in tests and after external meta changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		$this->current_location = null;
	}

	/**
	 * Handles the AJAX request to switch the active location.
	 *
	 * Accepts POST: location (string), nonce (string).
	 * Returns JSON: { success: true, data: { location, message } }
	 *           or: { success: false, data: { message } }
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_ajax_set_location(): void {
		check_ajax_referer( 'hma_location_nonce', 'nonce' );

		$slug = isset( $_POST['location'] )
			? sanitize_key( wp_unslash( $_POST['location'] ) )
			: '';

		if ( ! $this->set_location( $slug ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid location.', 'hma-core' ) ),
				400
			);
			return;
		}

		wp_send_json_success(
			array(
				'location' => $slug,
				'message'  => __( 'Location updated.', 'hma-core' ),
			)
		);
	}

	/**
	 * Reads the location from the request cookie.
	 *
	 * @since 1.0.0
	 *
	 * @return string The location slug from the cookie, or empty string.
	 */
	private function get_from_cookie(): string {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		return sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
	}

	/**
	 * Reads the stored location from user meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID.
	 * @return string The location slug from user meta, or empty string.
	 */
	private function get_from_user_meta( int $user_id ): string {
		$value = get_user_meta( $user_id, self::USER_META_KEY, true );
		return is_string( $value ) ? sanitize_key( $value ) : '';
	}

	/**
	 * Sets the location cookie on the response.
	 *
	 * Uses the array form of setcookie() (PHP 7.3+) for SameSite support.
	 * No-ops silently if headers have already been sent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The location slug.
	 * @return void
	 */
	private function set_cookie( string $slug ): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			$slug,
			array(
				'expires'  => time() + self::COOKIE_EXPIRY,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Reflect the new value immediately within this request.
		$_COOKIE[ self::COOKIE_NAME ] = $slug;
	}

	/**
	 * Saves the location to user meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The user ID.
	 * @param string $slug    The location slug.
	 * @return void
	 */
	private function set_user_meta( int $user_id, string $slug ): void {
		update_user_meta( $user_id, self::USER_META_KEY, $slug );
	}
}
