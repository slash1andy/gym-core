<?php
/**
 * Voice-and-tone heuristic tests for dunning drafts.
 *
 * Brand-guide §8 forbids aggressive vocabulary in member-facing copy:
 * "fight", "fighter(s)", "warrior(s)", "grind", "hustle", "dominate",
 * "crush". A dunning message is the highest-stakes member touch in the
 * Finance Copilot — a single wrong word reads like a collections agency
 * and undoes the brand. This test pins the rule.
 *
 * @package Gym_Core\Tests\Unit\Finance
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Finance;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Finance\API\FinanceController;
use PHPUnit\Framework\TestCase;

/**
 * Asserts compose_dunning_draft() output never contains banned words and
 * always includes the brand voice anchor "Haanpaa".
 */
class DunningVoiceToneTest extends TestCase {

	/**
	 * Per brand-guide §8: do-not-use language for member-facing copy.
	 *
	 * The check is case-insensitive and word-boundary aware so legitimate
	 * substrings (e.g. "tighten") never trip the heuristic.
	 *
	 * @var list<string>
	 */
	private const BANNED_WORDS = array(
		'fight',
		'fights',
		'fighting',
		'fighter',
		'fighters',
		'warrior',
		'warriors',
		'crush',
		'dominate',
		'grind',
		'hustle',
		'destroy',
		'kill',
	);

	/**
	 * Set up the test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stub WP translation passthrough so __()/sprintf() round-trip.
		Functions\when( '__' )->returnArg( 1 );
	}

	/**
	 * Tear down the test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The gentle draft must never contain a banned word.
	 *
	 * @return void
	 */
	public function test_gentle_draft_uses_brand_voice(): void {
		$draft = FinanceController::compose_dunning_draft( 'Sam', '$120.00', 4242, 'gentle' );

		$this->assertNotEmpty( $draft );
		$this->assertStringContainsString( 'Sam', $draft );
		$this->assertStringContainsString( 'Haanpaa', $draft );
		$this->assertStringContainsString( '$120.00', $draft );
		$this->assertStringContainsString( '#4242', $draft );

		foreach ( self::BANNED_WORDS as $bad ) {
			$this->assertDoesNotMatchRegularExpression(
				'/\b' . preg_quote( $bad, '/' ) . '\b/i',
				$draft,
				"Banned word '{$bad}' appeared in gentle dunning draft."
			);
		}
	}

	/**
	 * The firm draft must never contain a banned word either.
	 *
	 * Firm tone trades softness for directness — but never aggression.
	 *
	 * @return void
	 */
	public function test_firm_draft_uses_brand_voice(): void {
		$draft = FinanceController::compose_dunning_draft( 'Alex', '$95.00', 7, 'firm' );

		$this->assertNotEmpty( $draft );
		$this->assertStringContainsString( 'Alex', $draft );
		$this->assertStringContainsString( 'Haanpaa', $draft );

		foreach ( self::BANNED_WORDS as $bad ) {
			$this->assertDoesNotMatchRegularExpression(
				'/\b' . preg_quote( $bad, '/' ) . '\b/i',
				$draft,
				"Banned word '{$bad}' appeared in firm dunning draft."
			);
		}
	}

	/**
	 * A blank first name still produces a valid greeting (no "Hi ,").
	 *
	 * @return void
	 */
	public function test_draft_handles_missing_first_name_gracefully(): void {
		$draft = FinanceController::compose_dunning_draft( 'there', '$50.00', 1, 'gentle' );

		$this->assertStringNotContainsString( 'Hi ,', $draft );
		$this->assertStringContainsString( 'Hi there', $draft );
	}
}
