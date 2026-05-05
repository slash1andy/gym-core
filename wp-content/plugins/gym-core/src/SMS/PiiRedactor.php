<?php
/**
 * PII redaction helper for SMS persistence layers.
 *
 * The actual outbound SMS payload Twilio receives is unchanged; this
 * helper only filters the *persistent record* (CRM activity log,
 * timeline entries, error logs, anything queryable) so a security or
 * privacy review of the database does not surface full names, phone
 * numbers, or email addresses.
 *
 * @package Gym_Core
 * @since   2.2.0
 */

declare(strict_types=1);

namespace Gym_Core\SMS;

/**
 * Strips full names, phone numbers, and emails from SMS body text.
 *
 * @since 2.2.0
 */
class PiiRedactor {

	/**
	 * Redaction marker used in place of stripped PII.
	 *
	 * @since 2.2.0
	 *
	 * @var string
	 */
	const REDACTED = '[redacted]';

	/**
	 * Apply all redaction passes to a body string.
	 *
	 * Used by the SMS → CRM activity log bridge so anything written to
	 * `{$wpdb->prefix}zbsc_logs` (or its successor) for an outbound or
	 * inbound SMS does not contain personal data. The original body is
	 * still delivered to Twilio in real time; only the audit row is
	 * scrubbed.
	 *
	 * Order matters: emails are redacted first (they look like a token
	 * with an @ that the phone regex would otherwise mangle), then
	 * phone numbers (numeric runs that look phone-shaped), then full
	 * names (sequences of two or more capitalized words).
	 *
	 * @since 2.2.0
	 *
	 * @param string $body Raw SMS body — what was actually sent or received.
	 * @return string Same body with PII tokens replaced by self::REDACTED.
	 */
	public static function redact( string $body ): string {
		if ( '' === $body ) {
			return $body;
		}

		$body = self::redact_emails( $body );
		$body = self::redact_phones( $body );
		$body = self::redact_full_names( $body );

		return $body;
	}

	/**
	 * Replace email-shaped substrings with the redaction marker.
	 *
	 * @since 2.2.0
	 *
	 * @param string $body Source body.
	 * @return string Redacted body.
	 */
	public static function redact_emails( string $body ): string {
		// Conservative RFC-5321 superset: token @ token.tld. We do not try
		// to match every legal email; we match anything that looks like
		// one to a human, which is the threat surface for log scraping.
		$pattern = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';
		return (string) preg_replace( $pattern, self::REDACTED, $body );
	}

	/**
	 * Replace phone-shaped substrings with the redaction marker.
	 *
	 * Targets E.164 (`+15551234567`), NANP (`555-123-4567`,
	 * `(555) 123-4567`, `555.123.4567`), and bare 10/11-digit runs.
	 *
	 * @since 2.2.0
	 *
	 * @param string $body Source body.
	 * @return string Redacted body.
	 */
	public static function redact_phones( string $body ): string {
		// Match phone-shaped tokens. The order in the alternation matters:
		// prefer the most-specific (with separators) so the bare-digit
		// fallback does not eat parts of those.
		$patterns = array(
			'/\+\d{1,3}[\s\-.]?\(?\d{2,4}\)?[\s\-.]?\d{2,4}[\s\-.]?\d{2,4}([\s\-.]?\d{1,4})?/',
			'/\(?\d{3}\)?[\s\-.]\d{3}[\s\-.]\d{4}/',
			'/(?<!\d)\d{10,11}(?!\d)/',
		);
		foreach ( $patterns as $pattern ) {
			$body = (string) preg_replace( $pattern, self::REDACTED, $body );
		}
		return $body;
	}

	/**
	 * Replace likely full names (two-or-more capitalized words in a row).
	 *
	 * This is intentionally narrow — message templates often contain
	 * `{first_name}` placeholders that have been substituted, and a
	 * lone first name is hard to distinguish from any English word. We
	 * scrub the high-signal pattern (First Last, First Middle Last) and
	 * leave standalone capitalized words alone so we do not redact
	 * legitimate words like "Foundations" or "Rockford".
	 *
	 * Common multi-word non-name nouns from the gym's domain (program,
	 * location, brand) are excluded so the cure is not worse than the
	 * disease.
	 *
	 * @since 2.2.0
	 *
	 * @param string $body Source body.
	 * @return string Redacted body.
	 */
	public static function redact_full_names( string $body ): string {
		$allowlist = array(
			'Haanpaa Martial Arts',
			'Adult BJJ',
			'Kids BJJ',
			'Teen BJJ',
			'Adult Kickboxing',
			'Kids Kickboxing',
			'Beloit Wisconsin',
			'Rockford Illinois',
		);

		// Stash allowlisted phrases behind unique sentinels before the
		// name-shaped regex runs, then restore them. This avoids the case
		// where a 3-word allowlist entry would be eaten by the 2-word
		// match inside it ("Haanpaa Martial" matches before "Haanpaa
		// Martial Arts" is considered).
		$placeholders = array();
		// Sort longest-first so 3-word phrases stash before 2-word ones
		// they could otherwise contain.
		usort(
			$allowlist,
			static function ( string $a, string $b ): int {
				return strlen( $b ) - strlen( $a );
			}
		);
		foreach ( $allowlist as $idx => $phrase ) {
			$sentinel = sprintf( '__GYM_ALLOW_%d__', $idx );
			if ( false !== strpos( $body, $phrase ) ) {
				$body                   = str_replace( $phrase, $sentinel, $body );
				$placeholders[ $sentinel ] = $phrase;
			}
		}

		// Two or more consecutive Capitalized words.
		$pattern = '/\b([A-Z][a-z]+)(\s+[A-Z][a-z]+){1,3}\b/';
		$body    = (string) preg_replace( $pattern, self::REDACTED, $body );

		// Restore allowlisted phrases.
		foreach ( $placeholders as $sentinel => $phrase ) {
			$body = str_replace( $sentinel, $phrase, $body );
		}

		return $body;
	}
}
