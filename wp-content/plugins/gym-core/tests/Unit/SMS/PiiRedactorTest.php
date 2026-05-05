<?php
/**
 * Unit tests for PiiRedactor.
 *
 * @package Gym_Core\Tests\Unit\SMS
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\SMS;

use Gym_Core\SMS\PiiRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SMS PII redaction.
 *
 * The redactor is the only thing standing between the live Twilio
 * payload and the persistent activity log; if anything here regresses,
 * names and phone numbers leak into the CRM database.
 */
class PiiRedactorTest extends TestCase {

	public function test_redact_strips_email_addresses(): void {
		$body   = 'Reach out to coach@haanpaa.com if you need anything.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( 'coach@haanpaa.com', $result );
		$this->assertStringContainsString( PiiRedactor::REDACTED, $result );
	}

	public function test_redact_strips_e164_phone_numbers(): void {
		$body   = 'Text me back at +15551234567 if you can make class.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( '+15551234567', $result );
		$this->assertStringContainsString( PiiRedactor::REDACTED, $result );
	}

	public function test_redact_strips_dashed_phone_numbers(): void {
		$body   = 'Call 555-123-4567 to reschedule.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( '555-123-4567', $result );
	}

	public function test_redact_strips_parenthesized_phone_numbers(): void {
		$body   = 'Reach out: (555) 123-4567 anytime.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( '(555) 123-4567', $result );
		$this->assertStringNotContainsString( '555) 123-4567', $result );
	}

	public function test_redact_strips_full_names(): void {
		$body   = 'Marcus Chen earned a stripe today.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( 'Marcus Chen', $result );
		$this->assertStringContainsString( PiiRedactor::REDACTED, $result );
	}

	public function test_redact_strips_three_word_names(): void {
		$body   = 'Sarah Jane Smith confirmed for 6pm class.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( 'Sarah Jane Smith', $result );
	}

	public function test_redact_preserves_brand_terms(): void {
		// Allowlist must keep gym-domain compounds intact so the redactor
		// does not destroy legitimate program / brand references.
		$body   = 'Welcome to Haanpaa Martial Arts Adult BJJ class!';
		$result = PiiRedactor::redact( $body );

		$this->assertStringContainsString( 'Haanpaa Martial Arts', $result );
		$this->assertStringContainsString( 'Adult BJJ', $result );
	}

	public function test_redact_preserves_lone_capitalized_words(): void {
		// Single capitalized words (program names, locations) must NOT be
		// redacted. Only multi-word capitalized runs are full-name shaped.
		$body   = 'Foundations check-in opens at the Rockford location.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringContainsString( 'Foundations', $result );
		$this->assertStringContainsString( 'Rockford', $result );
		$this->assertStringNotContainsString( PiiRedactor::REDACTED, $result );
	}

	public function test_redact_strips_multiple_pii_types_in_one_message(): void {
		$body   = 'Marcus Chen left voicemail at 555-123-4567 — email back at marcus@example.com.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( 'Marcus Chen', $result );
		$this->assertStringNotContainsString( '555-123-4567', $result );
		$this->assertStringNotContainsString( 'marcus@example.com', $result );
	}

	public function test_redact_returns_empty_string_unchanged(): void {
		$this->assertSame( '', PiiRedactor::redact( '' ) );
	}

	public function test_redact_does_not_mangle_message_with_no_pii(): void {
		$body   = 'Reminder: bring water and your gi.';
		$result = PiiRedactor::redact( $body );

		$this->assertSame( $body, $result );
	}

	public function test_redact_emails_handles_plus_addressing(): void {
		$body   = 'Reply to coach+billing@haanpaa.com with questions.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringNotContainsString( 'coach+billing@haanpaa.com', $result );
	}

	public function test_redact_phones_does_not_strip_numeric_dates(): void {
		// 4-digit years and short timestamps must survive — they are not
		// phone-shaped (need >= 10 digits run for the bare-digit fallback).
		$body   = 'Class on 2026 at 6pm sharp.';
		$result = PiiRedactor::redact( $body );

		$this->assertStringContainsString( '2026', $result );
	}
}
