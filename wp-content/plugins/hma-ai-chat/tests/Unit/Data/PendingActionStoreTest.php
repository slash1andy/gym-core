<?php
declare(strict_types=1);
/**
 * Unit tests for PendingActionStore.
 *
 * @package HMA_AI_Chat\Tests\Unit\Data
 */

namespace HMA_AI_Chat\Tests\Unit\Data;

use HMA_AI_Chat\Data\PendingActionStore;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass( \HMA_AI_Chat\Data\PendingActionStore::class )]
class PendingActionStoreTest extends TestCase {

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
	// store_pending_action
	// Signature: ($agent, $action_type, $action_data, $status='pending', $run_id=null)
	// -----------------------------------------------------------------------

	public function test_store_pending_action_returns_insert_id_on_success(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 42;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Functions\stubs(
			array(
				'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
				'sanitize_text_field' => static fn( $v ) => $v,
				'wp_cache_delete'     => static fn() => true,
			)
		);
		Functions\expect( 'do_action' )->once(); // hma_ai_chat_pending_action_created

		$store  = new PendingActionStore();
		$result = $store->store_pending_action( 'sales', 'send_sms', array( 'message' => 'Hi' ) );
		$this->assertSame( 42, $result );
	}

	public function test_store_pending_action_returns_false_on_db_failure(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 0;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		Functions\stubs(
			array(
				'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
				'sanitize_text_field' => static fn( $v ) => $v,
			)
		);
		Functions\expect( 'do_action' )->zeroOrMoreTimes();

		$store  = new PendingActionStore();
		$result = $store->store_pending_action( 'sales', 'send_sms', array( 'message' => 'Hi' ) );
		$this->assertFalse( $result );
	}

	public function test_store_pending_action_does_not_fire_action_for_non_pending_status(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 7;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Functions\stubs(
			array(
				'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
				'sanitize_text_field' => static fn( $v ) => $v,
				'wp_cache_delete'     => static fn() => true,
			)
		);
		// do_action must NOT be called when status != 'pending'.
		Functions\expect( 'do_action' )->never();

		$store  = new PendingActionStore();
		$result = $store->store_pending_action( 'sales', 'send_sms', array( 'message' => 'Hi' ), 'approved' );
		$this->assertSame( 7, $result );
	}

	public function test_store_pending_action_stores_run_id_when_provided(): void {
		global $wpdb;
		$wpdb            = $this->make_wpdb();
		$wpdb->insert_id = 15;
		// Capture the insert data to verify run_id is passed through.
		$captured = null;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing( static function ( $table, array $data ) use ( &$captured ): int {
				$captured = $data;
				return 1;
			} );

		Functions\stubs(
			array(
				'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
				'sanitize_text_field' => static fn( $v ) => $v,
				'wp_cache_delete'     => static fn() => true,
			)
		);
		Functions\expect( 'do_action' )->once();

		$store = new PendingActionStore();
		$store->store_pending_action( 'admin', 'refund', array( 'amount' => 50 ), 'pending', 'run-xyz' );
		$this->assertSame( 'run-xyz', $captured['run_id'] ?? null );
	}

	// -----------------------------------------------------------------------
	// get_action_by_run_id
	// -----------------------------------------------------------------------

	public function test_get_action_by_run_id_returns_null_when_not_found(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$store  = new PendingActionStore();
		$result = $store->get_action_by_run_id( 'nonexistent-run' );
		$this->assertNull( $result );
	}

	public function test_get_action_by_run_id_returns_decoded_array_when_found(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared-sql' );

		$row = array(
			'id'          => 10,
			'status'      => 'pending',
			'action_data' => '{"tool_name":"send_sms"}',
		);
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );

		$store  = new PendingActionStore();
		$result = $store->get_action_by_run_id( 'run-abc' );
		$this->assertIsArray( $result );
		$this->assertSame( 'pending', $result['status'] );
		// action_data should be decoded to array.
		$this->assertIsArray( $result['action_data'] );
	}

	// -----------------------------------------------------------------------
	// approve_action
	// -----------------------------------------------------------------------

	public function test_approve_action_returns_true_on_success(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		Functions\stubs(
			array(
				'current_time' => static fn() => '2026-04-30 12:00:00',
				'absint'       => static fn( $v ) => abs( (int) $v ),
				'wp_cache_delete' => static fn() => true,
			)
		);
		Functions\expect( 'do_action' )->once(); // hma_ai_chat_action_approved

		$store  = new PendingActionStore();
		$result = $store->approve_action( 1, 99 );
		$this->assertTrue( $result );
	}

	public function test_approve_action_returns_false_on_failure(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'update' )->once()->andReturn( false );

		Functions\stubs(
			array(
				'current_time' => static fn() => '2026-04-30 12:00:00',
				'absint'       => static fn( $v ) => abs( (int) $v ),
			)
		);
		Functions\expect( 'do_action' )->zeroOrMoreTimes();

		$store  = new PendingActionStore();
		$result = $store->approve_action( 1, 99 );
		$this->assertFalse( $result );
	}

	// -----------------------------------------------------------------------
	// complete_revised_action — identity mutation rejection
	// Signature: ($action_id, $revised_data = array())
	// Requires status = 'approved_with_changes' to proceed.
	// Returns WP_Error (not false) on identity mutation.
	// -----------------------------------------------------------------------

	public function test_complete_revised_action_rejects_when_identity_key_mutated(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->zeroOrMoreTimes()->andReturn( 'prepared-sql' );

		// Existing row has status 'approved_with_changes' and tool_name = 'original_tool'.
		$existing = array(
			'id'          => 20,
			'status'      => 'approved_with_changes',
			'action_data' => json_encode( array( 'tool_name' => 'original_tool', 'amount' => 100 ) ),
		);
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $existing );

		Functions\expect( 'apply_filters' )
			->zeroOrMoreTimes()
			->andReturnUsing( static function ( string $tag, mixed $value ): mixed { return $value; } );
		Functions\stubs( array( 'esc_html__' => static fn( $t ) => $t ) );

		$store = new PendingActionStore();
		// Revised data mutates the identity key 'tool_name'.
		$result = $store->complete_revised_action(
			20,
			array( 'tool_name' => 'mutated_tool', 'amount' => 200 )
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_complete_revised_action_returns_false_when_status_not_approved_with_changes(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->zeroOrMoreTimes()->andReturn( 'prepared-sql' );

		// Row with wrong status — method should return false immediately.
		$existing = array(
			'id'          => 21,
			'status'      => 'pending',
			'action_data' => json_encode( array( 'tool_name' => 'tool1' ) ),
		);
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $existing );

		$store  = new PendingActionStore();
		$result = $store->complete_revised_action( 21, array( 'amount' => 50 ) );
		$this->assertFalse( $result );
	}

	public function test_complete_revised_action_returns_false_when_action_not_found(): void {
		global $wpdb;
		$wpdb = $this->make_wpdb();
		$wpdb->shouldReceive( 'prepare' )->zeroOrMoreTimes()->andReturn( 'prepared-sql' );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$store  = new PendingActionStore();
		$result = $store->complete_revised_action( 999, array() );
		$this->assertFalse( $result );
	}
}
