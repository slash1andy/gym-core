<?php
declare(strict_types=1);
/**
 * Unit tests for ConversationStore.
 *
 * @package HMA_AI_Chat\Tests\Unit\Data
 */

namespace HMA_AI_Chat\Tests\Unit\Data;

use HMA_AI_Chat\Data\ConversationStore;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass( \HMA_AI_Chat\Data\ConversationStore::class )]
class ConversationStoreTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a minimal $wpdb mock.
	 *
	 * @return \Mockery\MockInterface
	 */
	private function make_wpdb(): \Mockery\MockInterface {
		$wpdb         = \Mockery::mock( \stdClass::class );
		$wpdb->prefix = 'wp_';
		return $wpdb;
	}

	// -----------------------------------------------------------------------
	// create_conversation
	// -----------------------------------------------------------------------

	public function test_create_conversation_returns_insert_id_on_success(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 55;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Functions\stubs(
			array(
				'absint'              => static fn( $v ) => abs( (int) $v ),
				'sanitize_text_field' => static fn( $v ) => $v,
			)
		);

		$store  = new ConversationStore();
		$result = $store->create_conversation( 1, 'sales' );
		$this->assertSame( 55, $result );
	}

	public function test_create_conversation_returns_false_on_failure(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		Functions\stubs(
			array(
				'absint'              => static fn( $v ) => abs( (int) $v ),
				'sanitize_text_field' => static fn( $v ) => $v,
			)
		);

		$store  = new ConversationStore();
		$result = $store->create_conversation( 1, 'sales' );
		$this->assertFalse( $result );
	}

	public function test_create_conversation_passes_title_when_provided(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 60;
		$captured        = null;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing( static function ( $table, array $data ) use ( &$captured ): int {
				$captured = $data;
				return 1;
			} );

		Functions\stubs(
			array(
				'absint'              => static fn( $v ) => abs( (int) $v ),
				'sanitize_text_field' => static fn( $v ) => $v,
			)
		);

		$store = new ConversationStore();
		$store->create_conversation( 1, 'coaching', 'My Coaching Chat' );
		$this->assertSame( 'My Coaching Chat', $captured['title'] ?? null );
	}

	// -----------------------------------------------------------------------
	// save_message — role validation
	// -----------------------------------------------------------------------

	public function test_save_message_rejects_invalid_role(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		// insert should never be called for an invalid role.
		$wpdb->shouldReceive( 'insert' )->never();

		$store  = new ConversationStore();
		$result = $store->save_message( 1, 'system', 'Hello' );
		$this->assertFalse( $result );
	}

	public function test_save_message_rejects_tool_role(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'insert' )->never();

		$store  = new ConversationStore();
		$result = $store->save_message( 1, 'tool', 'result data' );
		$this->assertFalse( $result );
	}

	public function test_save_message_accepts_user_role(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 10;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Functions\stubs(
			array(
				'absint'         => static fn( $v ) => abs( (int) $v ),
				'wp_kses_post'   => static fn( $v ) => $v,
				'wp_json_encode' => static fn( $v ) => json_encode( $v ),
			)
		);

		$store  = new ConversationStore();
		$result = $store->save_message( 1, 'user', 'Hello world' );
		$this->assertSame( 10, $result );
	}

	public function test_save_message_accepts_assistant_role(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 11;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Functions\stubs(
			array(
				'absint'         => static fn( $v ) => abs( (int) $v ),
				'wp_kses_post'   => static fn( $v ) => $v,
				'wp_json_encode' => static fn( $v ) => json_encode( $v ),
			)
		);

		$store  = new ConversationStore();
		$result = $store->save_message( 1, 'assistant', 'Hi there' );
		$this->assertSame( 11, $result );
	}

	public function test_save_message_falls_back_without_tool_calls_on_insert_failure(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		// First insert (with tool_calls) fails; second (without) succeeds.
		$wpdb->insert_id = 12;
		$wpdb->shouldReceive( 'insert' )
			->twice()
			->andReturn( false, 1 );

		Functions\stubs(
			array(
				'absint'         => static fn( $v ) => abs( (int) $v ),
				'wp_kses_post'   => static fn( $v ) => $v,
				'wp_json_encode' => static fn( $v ) => json_encode( $v ),
			)
		);

		$store  = new ConversationStore();
		$result = $store->save_message( 1, 'user', 'Hi', null, array( array( 'name' => 'tool1' ) ) );
		$this->assertSame( 12, $result );
	}

	// -----------------------------------------------------------------------
	// delete_conversation — ownership check
	// -----------------------------------------------------------------------

	public function test_delete_conversation_returns_false_when_not_owner(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );

		// Row belongs to user 99, not user 1.
		$row          = new \stdClass();
		$row->user_id = '99';
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );

		Functions\stubs( array( 'absint' => static fn( $v ) => abs( (int) $v ) ) );

		$store  = new ConversationStore();
		$result = $store->delete_conversation( 5, 1 ); // conversation_id=5, user_id=1 (not owner)
		$this->assertFalse( $result );
	}

	public function test_delete_conversation_returns_false_when_not_found(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		Functions\stubs( array( 'absint' => static fn( $v ) => abs( (int) $v ) ) );

		$store  = new ConversationStore();
		$result = $store->delete_conversation( 99, 1 );
		$this->assertFalse( $result );
	}

	public function test_delete_conversation_succeeds_for_owner(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );

		$row          = new \stdClass();
		$row->user_id = '1';
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );
		$wpdb->shouldReceive( 'delete' )->once()->andReturn( 1 );

		Functions\stubs( array( 'absint' => static fn( $v ) => abs( (int) $v ) ) );

		$store  = new ConversationStore();
		$result = $store->delete_conversation( 5, 1 );
		$this->assertTrue( $result );
	}

	// -----------------------------------------------------------------------
	// purge_expired_conversations
	// -----------------------------------------------------------------------

	public function test_purge_fires_action_when_rows_deleted(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 3 );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'hma_ai_chat_retention_days', 30 )
			->andReturn( 30 );
		Functions\expect( 'do_action' )
			->once()
			->with(
				'hma_ai_chat_conversations_purged',
				3,
				30,
				\Mockery::type( 'string' )
			);

		$store  = new ConversationStore();
		$result = $store->purge_expired_conversations( 30 );
		$this->assertSame( 3, $result );
	}

	public function test_purge_does_not_fire_action_when_nothing_deleted(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 0 );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'hma_ai_chat_retention_days', 30 )
			->andReturn( 30 );
		Functions\expect( 'do_action' )->never();

		$store  = new ConversationStore();
		$result = $store->purge_expired_conversations( 30 );
		$this->assertSame( 0, $result );
	}

	public function test_purge_respects_filter_override(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 1 );

		// Filter overrides 30 -> 90 days.
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'hma_ai_chat_retention_days', 30 )
			->andReturn( 90 );
		Functions\expect( 'do_action' )
			->once()
			->with(
				'hma_ai_chat_conversations_purged',
				1,
				90,
				\Mockery::type( 'string' )
			);

		$store  = new ConversationStore();
		$result = $store->purge_expired_conversations( 30 );
		$this->assertSame( 1, $result );
	}
}
