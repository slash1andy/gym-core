<?php
declare(strict_types=1);
/**
 * Unit tests for ConversationOwnership.
 *
 * @package HMA_AI_Chat\Tests\Unit\Security
 */

namespace HMA_AI_Chat\Tests\Unit\Security;

use HMA_AI_Chat\Security\ConversationOwnership;
use HMA_AI_Chat\Data\ConversationStore;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Error;

#[\PHPUnit\Framework\Attributes\CoversClass( \HMA_AI_Chat\Security\ConversationOwnership::class )]
class ConversationOwnershipTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// Stub WP i18n/escape functions used in WP_Error messages so Brain\Monkey
		// does not throw "not defined nor mocked" when ConversationOwnership builds
		// its error responses. stubTranslationFunctions() covers __(), _x(), etc.;
		// stubEscapeFunctions() covers esc_html(), esc_html__(), esc_attr__(), etc.
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	public function test_zero_conversation_id_passes_through(): void {
		$store = Mockery::mock( ConversationStore::class );
		$gate  = new ConversationOwnership( $store );

		$this->assertTrue( $gate->check( 0 ) );
	}

	public function test_unauthenticated_user_is_rejected(): void {
		$store = Mockery::mock( ConversationStore::class );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$gate   = new ConversationOwnership( $store );
		$result = $gate->check( 42 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_owner_is_allowed(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$store = Mockery::mock( ConversationStore::class );
		$store->shouldReceive( 'get_conversation_record' )
			->with( 42 )
			->andReturn( array( 'id' => 42, 'user_id' => 7 ) );

		$gate = new ConversationOwnership( $store );

		$this->assertTrue( $gate->check( 42 ) );
	}

	public function test_non_owner_gets_403(): void {
		// THIS is the canonical regression test from the playbook:
		// a non-owner attempting to access someone else's conversation
		// must be rejected with HTTP 403.
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$store = Mockery::mock( ConversationStore::class );
		$store->shouldReceive( 'get_conversation_record' )
			->with( 42 )
			->andReturn( array( 'id' => 42, 'user_id' => 7 ) );

		$gate   = new ConversationOwnership( $store );
		$result = $gate->check( 42 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_admin_with_manage_options_bypasses_ownership(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'current_user_can' )
			->alias( static fn ( string $cap ) => 'manage_options' === $cap );

		$store = Mockery::mock( ConversationStore::class );
		$store->shouldReceive( 'get_conversation_record' )
			->with( 42 )
			->andReturn( array( 'id' => 42, 'user_id' => 7 ) );

		$gate = new ConversationOwnership( $store );

		$this->assertTrue( $gate->check( 42 ) );
	}

	public function test_missing_conversation_returns_403_not_404(): void {
		// Returning 403 (not 404) for a missing row prevents an attacker
		// from enumerating conversation IDs by distinguishing
		// "does not exist" from "belongs to someone else".
		Functions\when( 'get_current_user_id' )->justReturn( 99 );

		$store = Mockery::mock( ConversationStore::class );
		$store->shouldReceive( 'get_conversation_record' )
			->with( 9999 )
			->andReturn( null );

		$gate   = new ConversationOwnership( $store );
		$result = $gate->check( 9999 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}
}
