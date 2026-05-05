<?php
/**
 * Stateless magic-link signing for the Coach Briefing SMS surface.
 *
 * The 30-minute pre-class SMS includes a URL that points to a public-facing
 * briefing page. Coaches receive that SMS on their phone and may not be
 * logged in to WordPress at the moment they tap it, so a session-tied
 * `wp_create_nonce` is the wrong primitive — it would fail silently for
 * logged-out coaches. Instead we sign the link with HMAC-SHA256 over a
 * canonical payload and verify with `hash_equals` on the way back in.
 *
 * Secret rotation: the secret is derived from WordPress salts (NONCE_SALT
 * primarily, AUTH_SALT as fallback) so rotating those salts immediately
 * invalidates outstanding links.
 *
 * @package Gym_Core\Briefing
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Briefing;

/**
 * Signs and verifies stateless coach-briefing magic links.
 */
final class MagicLink {

	/**
	 * Query-var carrying the signed token.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'gym_briefing_token';

	/**
	 * Query-var carrying the class id (kept separate so it's debuggable).
	 *
	 * @var string
	 */
	public const QUERY_VAR_CLASS = 'gym_briefing_class';

	/**
	 * Default link lifetime in seconds (12 hours).
	 *
	 * @var int
	 */
	public const DEFAULT_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Returns the HMAC secret used for signing briefing links.
	 *
	 * Derived from WordPress salts so rotating salts invalidates all
	 * outstanding tokens. Falls back to a hashed AUTH_SALT if NONCE_SALT
	 * is not defined (older configs).
	 *
	 * @return string Raw secret bytes.
	 */
	private static function get_secret(): string {
		$salt = '';
		if ( defined( 'NONCE_SALT' ) ) {
			$salt = (string) NONCE_SALT;
		} elseif ( defined( 'AUTH_SALT' ) ) {
			$salt = (string) AUTH_SALT;
		}

		// Final fallback for unusual configs (e.g., test runs) — derive
		// from siteurl so the secret is at least site-scoped rather than
		// empty. Never returns an empty string.
		if ( '' === $salt ) {
			$salt = (string) get_option( 'siteurl', 'gym-core-briefing' ) . '|gym-briefing';
		}

		return hash( 'sha256', 'gym_briefing|' . $salt, true );
	}

	/**
	 * Creates a signed token for a class briefing.
	 *
	 * @since 2.2.0
	 *
	 * @param int      $class_id Class post ID.
	 * @param int      $user_id  Recipient user ID (the coach). Use 0 for unscoped links.
	 * @param int|null $ttl      Lifetime in seconds. Defaults to DEFAULT_TTL.
	 * @return string URL-safe base64 token: payload.signature
	 */
	public static function create( int $class_id, int $user_id = 0, ?int $ttl = null ): string {
		$ttl     = $ttl ?? self::DEFAULT_TTL;
		$expires = time() + max( 60, $ttl );

		$payload = array(
			'c' => $class_id,
			'u' => $user_id,
			'e' => $expires,
		);

		$payload_json = wp_json_encode( $payload );
		$payload_b64  = self::base64url_encode( (string) $payload_json );
		$signature    = hash_hmac( 'sha256', $payload_b64, self::get_secret(), true );
		$sig_b64      = self::base64url_encode( $signature );

		return $payload_b64 . '.' . $sig_b64;
	}

	/**
	 * Verifies a signed token and returns the decoded payload.
	 *
	 * Constant-time signature comparison (`hash_equals`) prevents timing
	 * attacks. Verifies expiry before returning so callers can rely on the
	 * payload being fresh.
	 *
	 * @since 2.2.0
	 *
	 * @param string $token Token from a magic link.
	 * @return array{class_id: int, user_id: int, expires: int}|\WP_Error
	 */
	public static function verify( string $token ) {
		if ( '' === $token || strpos( $token, '.' ) === false ) {
			return new \WP_Error( 'invalid_token', __( 'Briefing link is malformed.', 'gym-core' ), array( 'status' => 400 ) );
		}

		list( $payload_b64, $sig_b64 ) = explode( '.', $token, 2 );

		$expected = hash_hmac( 'sha256', $payload_b64, self::get_secret(), true );
		$actual   = self::base64url_decode( $sig_b64 );

		if ( false === $actual || ! hash_equals( $expected, $actual ) ) {
			return new \WP_Error( 'invalid_signature', __( 'Briefing link signature is invalid.', 'gym-core' ), array( 'status' => 403 ) );
		}

		$payload_json = self::base64url_decode( $payload_b64 );
		if ( false === $payload_json ) {
			return new \WP_Error( 'invalid_payload', __( 'Briefing link payload could not be decoded.', 'gym-core' ), array( 'status' => 400 ) );
		}

		$payload = json_decode( $payload_json, true );
		if ( ! is_array( $payload ) || ! isset( $payload['c'], $payload['e'] ) ) {
			return new \WP_Error( 'invalid_payload', __( 'Briefing link payload is incomplete.', 'gym-core' ), array( 'status' => 400 ) );
		}

		if ( time() > (int) $payload['e'] ) {
			return new \WP_Error( 'token_expired', __( 'Briefing link has expired.', 'gym-core' ), array( 'status' => 403 ) );
		}

		return array(
			'class_id' => (int) $payload['c'],
			'user_id'  => (int) ( $payload['u'] ?? 0 ),
			'expires'  => (int) $payload['e'],
		);
	}

	/**
	 * Returns the public URL for a signed briefing link.
	 *
	 * @since 2.2.0
	 *
	 * @param int      $class_id Class post ID.
	 * @param int      $user_id  Recipient user ID.
	 * @param int|null $ttl      Lifetime override.
	 * @return string Absolute URL.
	 */
	public static function url( int $class_id, int $user_id = 0, ?int $ttl = null ): string {
		$token = self::create( $class_id, $user_id, $ttl );

		return add_query_arg(
			array(
				self::QUERY_VAR_CLASS => $class_id,
				self::QUERY_VAR       => $token,
			),
			home_url( '/coach-briefing/' )
		);
	}

	/**
	 * URL-safe base64 encoding (no padding).
	 *
	 * @param string $data Bytes to encode.
	 * @return string
	 */
	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * URL-safe base64 decoding (handles missing padding).
	 *
	 * @param string $data Encoded data.
	 * @return string|false
	 */
	private static function base64url_decode( string $data ) {
		$pad = strlen( $data ) % 4;
		if ( $pad > 0 ) {
			$data .= str_repeat( '=', 4 - $pad );
		}
		return base64_decode( strtr( $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
}
