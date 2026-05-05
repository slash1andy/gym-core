<?php
/**
 * Encrypted credential storage for Twilio settings.
 *
 * Provides at-rest encryption for the Twilio Auth Token using a key derived
 * from `wp_salt('auth')`. Hooks into the `pre_update_option_*` and
 * `option_*` filters so the WooCommerce settings UI sees plaintext while the
 * database row is encrypted.
 *
 * Display masking is also handled here: when the saved option is non-empty,
 * the WC settings render is short-circuited to a masked placeholder of the
 * form `********XXXX` (last 4 of the real token). If the form re-posts the
 * mask placeholder unchanged, the update is skipped so the real token isn't
 * overwritten.
 *
 * @package Gym_Core\SMS
 * @since   4.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\SMS;

/**
 * Encrypts, decrypts, and masks the Twilio Auth Token at rest.
 */
final class CredentialStore {

	/**
	 * Option key for the encrypted Twilio Auth Token.
	 *
	 * @var string
	 */
	public const AUTH_TOKEN_OPTION = 'gym_core_twilio_auth_token';

	/**
	 * Marker prefix on encrypted ciphertext so we can detect un-encrypted
	 * legacy values and re-encrypt them transparently.
	 *
	 * @var string
	 */
	private const CIPHERTEXT_PREFIX = 'gymenc:v1:';

	/**
	 * Mask placeholder shown in the settings UI. The trailing four characters
	 * are replaced with the last four of the real token at render time.
	 *
	 * @var string
	 */
	private const MASK_PREFIX = '********';

	/**
	 * Registers WP option filters so plaintext values entering the option API
	 * get encrypted at rest, and reads come back transparently decrypted.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'pre_update_option_' . self::AUTH_TOKEN_OPTION, array( $this, 'filter_pre_update_option' ), 10, 2 );
		add_filter( 'option_' . self::AUTH_TOKEN_OPTION, array( $this, 'filter_option_read' ), 10, 1 );
	}

	/**
	 * Encrypts plaintext values before they are written to the options table.
	 *
	 * If the incoming value matches the mask placeholder we just re-rendered,
	 * the real stored value is preserved (return $old_value).
	 *
	 * @param mixed $new_value Incoming value.
	 * @param mixed $old_value Stored value.
	 * @return mixed
	 */
	public function filter_pre_update_option( mixed $new_value, mixed $old_value ): mixed {
		if ( ! is_string( $new_value ) ) {
			return $new_value;
		}

		$incoming = (string) $new_value;

		if ( '' === $incoming ) {
			return '';
		}

		// Mask round-trip: form posted back the mask placeholder unchanged.
		if ( $this->looks_like_mask( $incoming ) ) {
			return is_string( $old_value ) ? $old_value : '';
		}

		// Already-encrypted values (defensive) — pass through unchanged.
		if ( str_starts_with( $incoming, self::CIPHERTEXT_PREFIX ) ) {
			return $incoming;
		}

		return self::encrypt( $incoming );
	}

	/**
	 * Decrypts the option value when WordPress reads it.
	 *
	 * @param mixed $value Stored option value.
	 * @return mixed Decrypted plaintext, or original value if not encrypted.
	 */
	public function filter_option_read( mixed $value ): mixed {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		if ( ! str_starts_with( $value, self::CIPHERTEXT_PREFIX ) ) {
			// Legacy plaintext — return as-is. Will get encrypted next save.
			return $value;
		}

		$decrypted = self::decrypt( $value );

		return null === $decrypted ? '' : $decrypted;
	}

	/**
	 * Returns the decrypted plaintext value of the configured Twilio Auth Token.
	 *
	 * Read order: encrypted option (decrypted via the `option_*` filter) first,
	 * `wp-config.php` constant `GYM_CORE_TWILIO_AUTH_TOKEN` as deprecated fallback.
	 * Returns empty string when neither is set.
	 *
	 * @return string Plaintext token, or empty string.
	 */
	public static function get(): string {
		$value = (string) get_option( self::AUTH_TOKEN_OPTION, '' );

		if ( '' !== $value ) {
			return $value;
		}

		if ( defined( 'GYM_CORE_TWILIO_AUTH_TOKEN' ) && '' !== (string) GYM_CORE_TWILIO_AUTH_TOKEN ) {
			return (string) GYM_CORE_TWILIO_AUTH_TOKEN;
		}

		return '';
	}

	/**
	 * Persists a plaintext Twilio Auth Token under the encrypted option key.
	 *
	 * The `pre_update_option_*` filter encrypts the value at rest. Pass an
	 * empty string to clear the stored token.
	 *
	 * @param string $plaintext Plaintext token to store.
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $plaintext ): bool {
		return (bool) update_option( self::AUTH_TOKEN_OPTION, $plaintext );
	}

	/**
	 * Returns the masked display form of a token: `********ABCD`.
	 *
	 * @param string $token Plaintext token.
	 * @return string Masked string, or empty if token is empty.
	 */
	public static function mask( string $token ): string {
		if ( '' === $token ) {
			return '';
		}

		$last_four = strlen( $token ) >= 4 ? substr( $token, -4 ) : $token;

		return self::MASK_PREFIX . $last_four;
	}

	/**
	 * Encrypts a plaintext string, returning a prefixed base64 ciphertext.
	 *
	 * Uses libsodium when available, falling back to OpenSSL AES-256-GCM.
	 * Throws when neither algorithm can produce a valid ciphertext — the
	 * at-rest encryption guarantee is non-negotiable, so we'd rather block
	 * the save than persist plaintext.
	 *
	 * @param string $plaintext Plaintext to encrypt.
	 * @return string Prefixed ciphertext (`gymenc:v1:<algo>:<base64>`).
	 *
	 * @throws \RuntimeException When neither libsodium nor OpenSSL AES-256-GCM is available.
	 */
	public static function encrypt( string $plaintext ): string {
		$key = self::derive_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			$blob   = base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return self::CIPHERTEXT_PREFIX . 'sb:' . $blob;
		}

		// Fallback: OpenSSL AES-256-GCM.
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			throw new \RuntimeException( 'No supported encryption backend (libsodium or OpenSSL AES-256-GCM) is available.' );
		}

		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ct ) {
			throw new \RuntimeException( 'OpenSSL AES-256-GCM encryption failed; refusing to store plaintext.' );
		}

		$blob = base64_encode( $iv . $tag . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return self::CIPHERTEXT_PREFIX . 'aesgcm:' . $blob;
	}

	/**
	 * Decrypts a prefixed ciphertext blob.
	 *
	 * @param string $ciphertext Prefixed ciphertext from encrypt().
	 * @return string|null Plaintext, or null on failure.
	 */
	public static function decrypt( string $ciphertext ): ?string {
		if ( ! str_starts_with( $ciphertext, self::CIPHERTEXT_PREFIX ) ) {
			return null;
		}

		$body  = substr( $ciphertext, strlen( self::CIPHERTEXT_PREFIX ) );
		$parts = explode( ':', $body, 2 );

		if ( count( $parts ) !== 2 ) {
			return null;
		}

		[ $algo, $blob ] = $parts;
		$decoded         = base64_decode( $blob, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			return null;
		}

		$key = self::derive_key();

		if ( 'sb' === $algo && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

			if ( strlen( $decoded ) <= $nonce_len ) {
				return null;
			}

			$nonce  = substr( $decoded, 0, $nonce_len );
			$cipher = substr( $decoded, $nonce_len );
			$result = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

			return false === $result ? null : $result;
		}

		if ( 'aesgcm' === $algo ) {
			if ( strlen( $decoded ) <= 28 ) {
				return null;
			}

			$iv     = substr( $decoded, 0, 12 );
			$tag    = substr( $decoded, 12, 16 );
			$ct     = substr( $decoded, 28 );
			$result = openssl_decrypt( $ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			return false === $result ? null : $result;
		}

		return null;
	}

	/**
	 * Returns the 32-byte symmetric key derived from `wp_salt('auth')`.
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function derive_key(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'gym_core_fallback_salt';
		return hash_hmac( 'sha256', 'gym_core_twilio_auth_token', $salt, true );
	}

	/**
	 * Returns true if the value matches the mask placeholder pattern.
	 *
	 * @param string $value Candidate value.
	 * @return bool
	 */
	private function looks_like_mask( string $value ): bool {
		// Either the bare prefix or prefix + 4 trailing chars.
		if ( self::MASK_PREFIX === $value ) {
			return true;
		}

		return 1 === preg_match( '/^' . preg_quote( self::MASK_PREFIX, '/' ) . '.{1,8}$/', $value );
	}
}
