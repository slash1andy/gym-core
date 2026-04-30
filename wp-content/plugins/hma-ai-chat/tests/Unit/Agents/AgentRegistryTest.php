<?php
declare(strict_types=1);
/**
 * Unit tests for AgentRegistry.
 *
 * @package HMA_AI_Chat\Tests\Unit\Agents
 */

namespace HMA_AI_Chat\Tests\Unit\Agents;

use HMA_AI_Chat\Agents\AgentRegistry;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass( \HMA_AI_Chat\Agents\AgentRegistry::class )]
class AgentRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->reset_singleton();
	}

	protected function tearDown(): void {
		$this->reset_singleton();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Reset the private static $instance so each test gets a clean registry.
	 */
	private function reset_singleton(): void {
		// setAccessible() is a no-op since PHP 8.1 and deprecated in 8.5.
		$ref = new \ReflectionProperty( AgentRegistry::class, 'instance' );
		$ref->setValue( null, null );
	}

	/**
	 * Stub the WP functions that register_all_agents() calls.
	 */
	private function stub_wp_i18n(): void {
		Functions\stubs(
			array(
				'esc_html__' => static function ( string $text ): string { return $text; },
				'__'         => static function ( string $text ): string { return $text; },
			)
		);
		Functions\expect( 'do_action' )->zeroOrMoreTimes();
	}

	public function test_instance_returns_same_object(): void {
		$a = AgentRegistry::instance();
		$b = AgentRegistry::instance();
		$this->assertSame( $a, $b );
	}

	public function test_register_all_agents_populates_registry(): void {
		$this->stub_wp_i18n();
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$agents = $registry->get_all_agents();
		$this->assertNotEmpty( $agents );
	}

	public function test_registry_contains_four_default_agents(): void {
		$this->stub_wp_i18n();
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$this->assertCount( 4, $registry->get_all_agents() );
	}

	public function test_get_agent_returns_null_for_unknown_slug(): void {
		$this->stub_wp_i18n();
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$this->assertNull( $registry->get_agent( 'nonexistent-slug' ) );
	}

	public function test_get_agent_returns_persona_for_known_slug(): void {
		$this->stub_wp_i18n();
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$persona = $registry->get_agent( 'sales' );
		$this->assertNotNull( $persona );
		$this->assertInstanceOf( \HMA_AI_Chat\Agents\AgentPersona::class, $persona );
	}

	public function test_get_available_agents_filters_by_capability(): void {
		$this->stub_wp_i18n();
		// user_can: return true only for edit_posts, not manage_options.
		Functions\expect( 'user_can' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( int $user_id, string $cap ): bool {
					return 'edit_posts' === $cap;
				}
			);
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$all       = $registry->get_all_agents();
		$available = $registry->get_available_agents( 1 );
		// Available should be a subset of all agents.
		$this->assertLessThanOrEqual( count( $all ), count( $available ) );
		// edit_posts agents (sales, coaching) should appear.
		$this->assertNotEmpty( $available );
	}

	public function test_get_available_agents_returns_exactly_edit_posts_agents(): void {
		$this->stub_wp_i18n();
		// Only grant edit_posts — should yield sales + coaching (2 agents).
		Functions\expect( 'user_can' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( int $user_id, string $cap ): bool {
					return 'edit_posts' === $cap;
				}
			);
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$available = $registry->get_available_agents( 1 );
		$this->assertCount( 2, $available );
	}

	public function test_get_available_agents_empty_when_no_caps(): void {
		$this->stub_wp_i18n();
		Functions\expect( 'user_can' )
			->zeroOrMoreTimes()
			->andReturn( false );
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$available = $registry->get_available_agents( 99 );
		$this->assertEmpty( $available );
	}

	public function test_register_all_agents_is_idempotent(): void {
		$this->stub_wp_i18n();
		$registry = AgentRegistry::instance();
		$registry->register_all_agents();
		$count_first = count( $registry->get_all_agents() );
		$registry->register_all_agents(); // Second call should no-op.
		$count_second = count( $registry->get_all_agents() );
		$this->assertSame( $count_first, $count_second );
	}
}
