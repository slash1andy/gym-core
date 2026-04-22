<?php
declare(strict_types=1);
/**
 * Pending action data store for approval queue.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\Data;

/**
 * Manages pending action approvals.
 *
 * @since 0.1.0
 */
class PendingActionStore {

	/**
	 * Store a pending action awaiting approval.
	 *
	 * @param string $agent        Agent slug.
	 * @param string $action_type  Type of action.
	 * @param array  $action_data  Action data as JSON-serializable array.
	 * @param string $status       Status (pending, approved, rejected).
	 * @param string $run_id       Optional Paperclip run ID.
	 * @return int|false Action ID on success, false on failure.
	 */
	public function store_pending_action( $agent, $action_type, $action_data, $status = 'pending', $run_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		$result = $wpdb->insert(
			$table,
			array(
				'agent'       => sanitize_text_field( $agent ),
				'action_type' => sanitize_text_field( $action_type ),
				'action_data' => wp_json_encode( $action_data ),
				'status'      => sanitize_text_field( $status ),
				'run_id'      => $run_id ? sanitize_text_field( $run_id ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$action_id = (int) $wpdb->insert_id;
		wp_cache_delete( 'hma_ai_pending_count' );

		// Fire only for genuinely pending actions — callers that seed historic or
		// pre-approved rows shouldn't trigger notifications.
		if ( 'pending' === $status ) {
			/**
			 * Fires when a new action is stored awaiting approval.
			 *
			 * Notifier plumbing (admin notice, Slack webhook, SMS) listens on
			 * this hook. Stays decoupled from the data layer so alternative
			 * notifiers can be added without touching the store.
			 *
			 * @since 0.3.2
			 *
			 * @param int    $action_id   Action ID just inserted.
			 * @param string $agent       Agent slug.
			 * @param string $action_type Type of action.
			 * @param array  $action_data Action data payload.
			 */
			do_action( 'hma_ai_chat_pending_action_created', $action_id, $agent, $action_type, $action_data );
		}

		return $action_id;
	}

	/**
	 * Get pending actions for an admin dashboard.
	 *
	 * @param int $limit Number of actions to retrieve.
	 * @return array Array of pending action records.
	 */
	public function get_pending_actions( $limit = 50 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, agent, action_type, action_data, run_id, created_at FROM $table WHERE status = 'pending' ORDER BY created_at DESC LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);

		if ( ! $results ) {
			return array();
		}

		// Decode action_data JSON.
		foreach ( $results as &$action ) {
			$action['action_data'] = json_decode( $action['action_data'], true );
		}

		return $results;
	}

	/**
	 * Approve a pending action for immediate execution.
	 *
	 * @since 0.1.0
	 *
	 * @param int $action_id Action ID.
	 * @param int $user_id   User ID approving the action.
	 * @return bool
	 */
	public function approve_action( $action_id, $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		$result = $wpdb->update(
			$table,
			array(
				'status'       => 'approved',
				'approved_at'  => current_time( 'mysql' ),
				'approved_by'  => absint( $user_id ),
			),
			array( 'id' => absint( $action_id ) ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( $result ) {
			wp_cache_delete( 'hma_ai_pending_count' );
			/**
			 * Fires when an action is approved.
			 *
			 * @param int $action_id Action ID.
			 * @param int $user_id   Approving user ID.
			 *
			 * @since 0.1.0
			 */
			do_action( 'hma_ai_chat_action_approved', $action_id, $user_id );
		}

		return false !== $result;
	}

	/**
	 * Approve a pending action with staff-directed changes.
	 *
	 * The agent will re-execute the action incorporating the staff's
	 * modifications before final execution. The action stays in
	 * 'approved_with_changes' status until the agent confirms completion.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $action_id    Action ID.
	 * @param int    $user_id      User ID approving the action.
	 * @param string $instructions Staff instructions describing the changes.
	 * @return bool
	 */
	public function approve_with_changes( $action_id, $user_id, $instructions ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		// Get existing action data to append instructions.
		$action = $this->get_action( $action_id );
		if ( ! $action || 'pending' !== $action['status'] ) {
			return false;
		}

		$action_data                      = $action['action_data'];
		$action_data['staff_changes']     = sanitize_textarea_field( $instructions );
		$action_data['original_proposal'] = $action_data['description'] ?? '';

		$result = $wpdb->update(
			$table,
			array(
				'status'       => 'approved_with_changes',
				'action_data'  => wp_json_encode( $action_data ),
				'approved_at'  => current_time( 'mysql' ),
				'approved_by'  => absint( $user_id ),
			),
			array( 'id' => absint( $action_id ) ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( $result ) {
			wp_cache_delete( 'hma_ai_pending_count' );
			/**
			 * Fires when an action is approved with staff-directed changes.
			 *
			 * The agent should re-execute incorporating the staff's instructions
			 * before completing the action.
			 *
			 * @param int    $action_id    Action ID.
			 * @param int    $user_id      Approving user ID.
			 * @param string $instructions Staff change instructions.
			 * @param array  $action_data  Updated action data with instructions.
			 *
			 * @since 0.1.0
			 */
			do_action( 'hma_ai_chat_action_approved_with_changes', $action_id, $user_id, $instructions, $action_data );
		}

		return false !== $result;
	}

	/**
	 * Identity keys that must NOT change between approve-with-changes and completion.
	 *
	 * Staff approve a specific action against a specific target. A revised
	 * payload that swaps `order_id`, `phone`, `tool_name`, etc. would be
	 * recorded against the audit row staff thought they approved.
	 *
	 * Filterable so site-specific tools can extend the lockset.
	 *
	 * @since 0.4.1
	 *
	 * @return string[]
	 */
	private function get_immutable_identity_keys() {
		$keys = array(
			'tool_name',
			'endpoint',
			'method',
			'target_id',
			'order_id',
			'user_id',
			'contact_id',
			'phone',
			'agent_user_id',
			'agent_id',
			'task_id',
			'run_id',
		);

		/**
		 * Filter the keys that may not be mutated by `complete_revised_action`.
		 *
		 * @since 0.4.1
		 *
		 * @param string[] $keys Immutable identity keys.
		 */
		return (array) apply_filters( 'hma_ai_chat_immutable_action_keys', $keys );
	}

	/**
	 * Mark an approved-with-changes action as completed after agent revision.
	 *
	 * Called by the agent after incorporating staff changes and executing.
	 *
	 * Identity keys (tool, endpoint, target IDs) are pinned to the original
	 * action_data — a revised payload that tries to swap them is rejected so
	 * the completed audit row matches what staff approved.
	 *
	 * @since 0.1.0
	 *
	 * @param int   $action_id     Action ID.
	 * @param array $revised_data  The revised action data after agent changes.
	 * @return bool|\WP_Error True on success, false on DB failure, WP_Error on identity-mutation rejection.
	 */
	public function complete_revised_action( $action_id, $revised_data = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		$action = $this->get_action( $action_id );
		if ( ! $action || 'approved_with_changes' !== $action['status'] ) {
			return false;
		}

		$action_data = $action['action_data'];

		// Reject any revised_data that mutates identity keys staff approved.
		$mutated = $this->find_identity_mutations( $action_data, (array) $revised_data );
		if ( ! empty( $mutated ) ) {
			return new \WP_Error(
				'identity_mutation_rejected',
				esc_html__( 'Revised action would mutate immutable identity keys.', 'hma-ai-chat' ),
				array(
					'status'        => 409,
					'mutated_keys'  => $mutated,
				)
			);
		}

		$action_data['revised_data']  = $revised_data;
		$action_data['completed_at']  = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			array(
				'status'      => 'completed',
				'action_data' => wp_json_encode( $action_data ),
			),
			array( 'id' => absint( $action_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result ) {
			/**
			 * Fires when a revised action completes after staff-directed changes.
			 *
			 * @param int   $action_id    Action ID.
			 * @param array $revised_data The final revised data.
			 *
			 * @since 0.1.0
			 */
			do_action( 'hma_ai_chat_revised_action_completed', $action_id, $revised_data );
		}

		return false !== $result;
	}

	/**
	 * Compare revised_data against original action_data on immutable keys.
	 *
	 * @since 0.4.1
	 *
	 * @param array $original Original action_data.
	 * @param array $revised  Revised payload from the agent.
	 * @return string[] Keys whose values differ. Empty when revision is safe.
	 */
	private function find_identity_mutations( array $original, array $revised ) {
		$mutated = array();

		foreach ( $this->get_immutable_identity_keys() as $key ) {
			if ( ! array_key_exists( $key, $revised ) ) {
				continue;
			}
			$original_value = array_key_exists( $key, $original ) ? $original[ $key ] : null;
			if ( $revised[ $key ] !== $original_value ) {
				$mutated[] = $key;
			}
		}

		return $mutated;
	}

	/**
	 * Reject a pending action.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $action_id Action ID.
	 * @param int    $user_id   User ID rejecting the action.
	 * @param string $reason    Optional rejection reason.
	 * @return bool
	 */
	public function reject_action( $action_id, $user_id, $reason = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		$data   = array(
			'status'      => 'rejected',
			'approved_at' => current_time( 'mysql' ),
			'approved_by' => absint( $user_id ),
		);
		$format = array( '%s', '%s', '%d' );

		// Store rejection reason in action_data if provided.
		if ( ! empty( $reason ) ) {
			$action = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT action_data FROM $table WHERE id = %d",
					absint( $action_id )
				)
			);

			if ( $action ) {
				$action_data                     = json_decode( $action->action_data, true );
				$action_data['rejection_reason'] = sanitize_text_field( $reason );
				$data['action_data']             = wp_json_encode( $action_data );
				$format[]                        = '%s';
			}
		}

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => absint( $action_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result ) {
			wp_cache_delete( 'hma_ai_pending_count' );
			/**
			 * Fires when an action is rejected.
			 *
			 * @param int    $action_id Action ID.
			 * @param int    $user_id   Rejecting user ID.
			 * @param string $reason    Rejection reason.
			 *
			 * @since 0.1.0
			 */
			do_action( 'hma_ai_chat_action_rejected', $action_id, $user_id, $reason );
		}

		return false !== $result;
	}

	/**
	 * Get the count of pending actions.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_pending_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		// Check object cache first to avoid duplicate queries per request.
		$cache_key = 'hma_ai_pending_count';
		$count     = wp_cache_get( $cache_key );

		if ( false === $count ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table WHERE status = %s",
					'pending'
				)
			);
			wp_cache_set( $cache_key, $count, '', 60 );
		}

		return (int) $count;
	}

	/**
	 * Get all actions for the audit log with optional filters.
	 *
	 * @since 0.3.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $status   Filter by status (pending, approved, rejected, etc.).
	 *     @type string $agent    Filter by agent slug.
	 *     @type int    $per_page Results per page. Default 20.
	 *     @type int    $page     Page number. Default 1.
	 * }
	 * @return array{ items: array, total: int, total_pages: int }
	 */
	public function get_all_actions( $args = array() ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'hma_ai_pending_actions';
		$defaults = array(
			'status'   => '',
			'agent'    => '',
			'per_page' => 20,
			'page'     => 1,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['agent'] ) ) {
			$where[]  = 'agent = %s';
			$values[] = sanitize_text_field( $args['agent'] );
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		// Get total count.
		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table $where_clause", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$values
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix, WHERE is static 1=1.
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table $where_clause" );
		}

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...array_merge( $values, array( $args['per_page'], $offset ) )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$args['per_page'],
				$offset
			);
		}

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		if ( ! $results ) {
			$results = array();
		}

		foreach ( $results as &$action ) {
			$action['action_data'] = json_decode( $action['action_data'], true );
		}

		return array(
			'items'       => $results,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Bulk approve pending actions.
	 *
	 * @since 0.3.0
	 *
	 * @param array $action_ids Array of action IDs to approve.
	 * @param int   $user_id    User ID approving the actions.
	 * @return array{ approved: int[], failed: int[] }
	 */
	public function bulk_approve( $action_ids, $user_id ) {
		$approved = array();
		$failed   = array();

		foreach ( $action_ids as $action_id ) {
			$action_id = absint( $action_id );
			$action    = $this->get_action( $action_id );

			if ( ! $action || 'pending' !== $action['status'] ) {
				$failed[] = $action_id;
				continue;
			}

			if ( $this->approve_action( $action_id, $user_id ) ) {
				$approved[] = $action_id;
			} else {
				$failed[] = $action_id;
			}
		}

		return array(
			'approved' => $approved,
			'failed'   => $failed,
		);
	}

	/**
	 * Bulk reject pending actions.
	 *
	 * @since 0.3.0
	 *
	 * @param array  $action_ids Array of action IDs to reject.
	 * @param int    $user_id    User ID rejecting the actions.
	 * @param string $reason     Optional rejection reason.
	 * @return array{ rejected: int[], failed: int[] }
	 */
	public function bulk_reject( $action_ids, $user_id, $reason = '' ) {
		$rejected = array();
		$failed   = array();

		foreach ( $action_ids as $action_id ) {
			$action_id = absint( $action_id );
			$action    = $this->get_action( $action_id );

			if ( ! $action || 'pending' !== $action['status'] ) {
				$failed[] = $action_id;
				continue;
			}

			if ( $this->reject_action( $action_id, $user_id, $reason ) ) {
				$rejected[] = $action_id;
			} else {
				$failed[] = $action_id;
			}
		}

		return array(
			'rejected' => $rejected,
			'failed'   => $failed,
		);
	}

	/**
	 * Get a single pending action.
	 *
	 * @param int $action_id Action ID.
	 * @return array|null
	 */
	public function get_action( $action_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				absint( $action_id )
			),
			ARRAY_A
		);

		if ( $result ) {
			$result['action_data'] = json_decode( $result['action_data'], true );
		}

		return $result;
	}
}
