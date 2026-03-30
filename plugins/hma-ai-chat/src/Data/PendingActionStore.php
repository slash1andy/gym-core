<?php
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

		return $result ? $wpdb->insert_id : false;
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
	 * Mark an approved-with-changes action as completed after agent revision.
	 *
	 * Called by the agent after incorporating staff changes and executing.
	 *
	 * @since 0.1.0
	 *
	 * @param int   $action_id     Action ID.
	 * @param array $revised_data  The revised action data after agent changes.
	 * @return bool
	 */
	public function complete_revised_action( $action_id, $revised_data = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_pending_actions';

		$action = $this->get_action( $action_id );
		if ( ! $action || 'approved_with_changes' !== $action['status'] ) {
			return false;
		}

		$action_data                  = $action['action_data'];
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
