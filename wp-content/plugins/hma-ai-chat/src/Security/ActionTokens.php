<?php
declare(strict_types=1);
/**
 * HMAC-signed tokens for the Gandalf approval queue.
 *
 * Re-pins action identity in the queue: each pending action card the
 * dashboard renders carries an `action_token` that's an HMAC of
 * (action_id + user_id + a per-action nonce). Approve/reject endpoints
 * verify the token before mutating state. This blocks the
 * swap-on-double-click bug where a stale DOM node could submit one
 * action ID while the user thought they were approving another.
 *
 * @package HMA_AI_Chat
 * @since   0.4.0
 */

namespace HMA_AI_Chat\Security;

/**
 * Issues and verifies action_token values for the approval queue.
 *
 * @since 0.4.0
 */
class ActionTokens {

	/**
	 * Option key for the token signing key.
	 *
	 * @var string
	 */
	const SIGNING_KEY_OPTION = 'hma_ai_chat_action_token_signing_key';

	/**
	 * HMAC algorithm.
	 *
	 * @var string
	 */
	const ALGO = 'sha256';

	/**
	 * Issue a token for the given action / user pair.
	 *
	 * Uses a per-action nonce as a salt so the token cannot be replayed
	 * across actions even if the same user_id is involved.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $action_id Action row primary key.
	 * @param int    $user_id   Current user ID (the approver/rejecter).
	 * @param string $nonce     Per-action nonce (typically the action's run_id or a fresh wp_create_nonce).
	 * @return string Hex-encoded HMAC.
	 */
	public static function issue( int $action_id, int $user_id, string $nonce ): string {
		$key     = self::get_signing_key();
		$payload = sprintf( 'action=%d|user=%d|nonce=%s', $action_id, $user_id, $nonce );
		return hash_hmac( self::ALGO, $payload, $key );
	}

	/**
	 * Verify an action_token for the given action / user / nonce tuple.
	 *
	 * @since 0.4.0
	 *
	 * @param string $token     Token from the request.
	 * @param int    $action_id Action row primary key.
	 * @param int    $user_id   Current user ID.
	 * @param string $nonce     Per-action nonce that was used at issue time.
	 * @return bool True iff the token validates.
	 */
	public static function verify( string $token, int $action_id, int $user_id, string $nonce ): bool {
		if ( '' === $token || $action_id <= 0 || $user_id <= 0 || '' === $nonce ) {
			return false;
		}
		$expected = self::issue( $action_id, $user_id, $nonce );
		return hash_equals( $expected, $token );
	}

	/**
	 * Resolve (or lazily generate) the per-site signing key.
	 *
	 * Stored once in wp_options as a 64-byte hex secret. Independent of
	 * the webhook secret so rotating one does not invalidate dashboard
	 * tokens that are already in flight.
	 *
	 * @since 0.4.0
	 *
	 * @return string The signing key.
	 */
	private static function get_signing_key(): string {
		$key = (string) get_option( self::SIGNING_KEY_OPTION, '' );
		if ( '' === $key ) {
			$key = bin2hex( random_bytes( 32 ) );
			update_option( self::SIGNING_KEY_OPTION, $key, false );
		}
		return $key;
	}
}
