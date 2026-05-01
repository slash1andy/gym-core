<?php
declare(strict_types=1);
/**
 * Unit tests for AgentUserManager.
 *
 * @package HMA_AI_Chat\Tests\Unit\Agents
 */

namespace HMA_AI_Chat\Tests\Unit\Agents;

use HMA_AI_Chat\Agents\AgentUserManager;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass( \HMA_AI_Chat\Agents\AgentUserManager::class )]
class AgentUserManagerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// block_agent_login
	// -----------------------------------------------------------------------

	/**
	 * When the username starts with 'gandalf-' AND the WP user exists AND
	 * has the agent meta key, login is blocked with WP_Error.
	 */
	public function test_block_agent_login_blocks_confirmed_agent_user(): void {
		$agent_user         = new \WP_User( 5, 'gandalf-sales' );

		Functions\expect( 'get_user_by' )
			->once()
			->with( 'login', 'gandalf-sales' )
			->andReturn( $agent_user );

		Functions\expect( 'get_user_meta' )
			->once()
			->with( 5, AgentUserManager::AGENT_META_KEY, true )
			->andReturn( 'sales' ); // Non-empty = confirmed agent.

		Functions\stubs( array( '__' => static fn( $t ) => $t ) );

		$result = AgentUserManager::block_agent_login( $agent_user, 'gandalf-sales' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * If username starts with 'gandalf-' but no WP user exists for it,
	 * fall through and return the original $user value.
	 */
	public function test_block_agent_login_passes_through_when_no_wp_user(): void {
		Functions\expect( 'get_user_by' )
			->once()
			->with( 'login', 'gandalf-ghost' )
			->andReturn( false );

		$user   = new \WP_User( 1, 'gandalf-ghost' );
		$result = AgentUserManager::block_agent_login( $user, 'gandalf-ghost' );
		$this->assertSame( $user, $result );
	}

	/**
	 * Normal username — no WP user lookup occurs, original $user returned.
	 */
	public function test_block_agent_login_passes_through_normal_username(): void {
		// No get_user_by call expected for non-gandalf usernames.
		Functions\expect( 'get_user_by' )->never();

		$user   = new \WP_User( 1, 'admin' );
		$result = AgentUserManager::block_agent_login( $user, 'admin' );
		$this->assertSame( $user, $result );
	}

	/**
	 * Null $user with non-gandalf username — returns null unchanged.
	 */
	public function test_block_agent_login_passes_through_null_user(): void {
		Functions\expect( 'get_user_by' )->never();

		$result = AgentUserManager::block_agent_login( null, 'regular-user' );
		$this->assertNull( $result );
	}

	/**
	 * Username starts with 'gandalf-' but meta is empty — not an agent account.
	 */
	public function test_block_agent_login_passes_through_when_meta_empty(): void {
		$non_agent_user = new \WP_User( 7, 'gandalf-notanagent' );

		Functions\expect( 'get_user_by' )
			->once()
			->andReturn( $non_agent_user );

		Functions\expect( 'get_user_meta' )
			->once()
			->andReturn( '' ); // Empty = not an agent.

		$result = AgentUserManager::block_agent_login( $non_agent_user, 'gandalf-notanagent' );
		$this->assertSame( $non_agent_user, $result );
	}

	// -----------------------------------------------------------------------
	// hide_from_user_list
	// -----------------------------------------------------------------------

	/**
	 * When not in wp-admin, hide_from_user_list returns early (void).
	 * Verify the query object is unmodified by confirming set() is never called.
	 */
	public function test_hide_from_user_list_no_op_on_frontend(): void {
		Functions\expect( 'is_admin' )->once()->andReturn( false );

		$query = \Mockery::mock( \WP_User_Query::class );
		// set() must never be called when not in admin — early return.
		$query->shouldNotReceive( 'set' );

		$result = AgentUserManager::hide_from_user_list( $query );
		$this->assertNull( $result ); // void method.
	}

	/**
	 * In admin with no tracked agent users — also a no-op (empty array guard).
	 */
	public function test_hide_from_user_list_no_op_when_no_agent_users(): void {
		Functions\expect( 'is_admin' )->once()->andReturn( true );
		Functions\expect( 'get_option' )
			->once()
			->with( AgentUserManager::OPTION_KEY, array() )
			->andReturn( array() );

		$query = \Mockery::mock( \WP_User_Query::class );
		$query->shouldNotReceive( 'set' );

		AgentUserManager::hide_from_user_list( $query );
		// Mockery verifies shouldNotReceive; count explicitly so PHPUnit doesn't flag as risky.
		$this->addToAssertionCount( 1 );
	}

	/**
	 * In admin with tracked agent users — query gets the exclude list set.
	 */
	public function test_hide_from_user_list_excludes_agent_ids_in_admin(): void {
		Functions\expect( 'is_admin' )->once()->andReturn( true );
		Functions\expect( 'get_option' )
			->once()
			->with( AgentUserManager::OPTION_KEY, array() )
			->andReturn( array( 'sales' => 5, 'coaching' => 6 ) );

		$query = \Mockery::mock( \WP_User_Query::class );
		$query->shouldReceive( 'get' )
			->once()
			->with( 'exclude' )
			->andReturn( array() );
		$query->shouldReceive( 'set' )
			->once()
			->with( 'exclude', \Mockery::on( static function ( array $ids ): bool {
				return in_array( 5, $ids, true ) && in_array( 6, $ids, true );
			} ) );

		AgentUserManager::hide_from_user_list( $query );
		// Mockery verifies the set() call expectations; count explicitly for PHPUnit.
		$this->addToAssertionCount( 1 );
	}
}
