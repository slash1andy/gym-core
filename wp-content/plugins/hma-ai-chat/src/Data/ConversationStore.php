<?php
/**
 * Conversation data store.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\Data;

/**
 * Manages conversation CRUD operations.
 *
 * @since 0.1.0
 */
class ConversationStore {

	/**
	 * Create a new conversation.
	 *
	 * @param int    $user_id User ID.
	 * @param string $agent   Agent slug.
	 * @param string $title   Optional conversation title.
	 * @return int|false Conversation ID on success, false on failure.
	 */
	public function create_conversation( $user_id, $agent, $title = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_conversations';

		$result = $wpdb->insert(
			$table,
			array(
				'user_id' => absint( $user_id ),
				'agent'   => sanitize_text_field( $agent ),
				'title'   => $title ? sanitize_text_field( $title ) : null,
			),
			array( '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Save a message to a conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $role            Message role (user or assistant).
	 * @param string $content         Message content.
	 * @param int    $tokens_used     Optional token count.
	 * @return int|false Message ID on success, false on failure.
	 */
	public function save_message( $conversation_id, $role, $content, $tokens_used = null ) {
		global $wpdb;

		// Validate role.
		if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'hma_ai_messages';

		$result = $wpdb->insert(
			$table,
			array(
				'conversation_id' => absint( $conversation_id ),
				'role'            => $role,
				'content'         => wp_kses_post( $content ),
				'tokens_used'     => $tokens_used ? absint( $tokens_used ) : null,
			),
			array( '%d', '%s', '%s', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get conversation history.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array Array of messages.
	 */
	public function get_conversation( $conversation_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_messages';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM $table WHERE conversation_id = %d ORDER BY created_at ASC",
				absint( $conversation_id )
			),
			ARRAY_A
		);

		return $results ?? array();
	}

	/**
	 * Get all conversations for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of conversations to retrieve.
	 * @param int $offset  Pagination offset.
	 * @return array Array of conversation records.
	 */
	public function get_user_conversations( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_conversations';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, agent, title, created_at, updated_at FROM $table WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				absint( $user_id ),
				absint( $limit ),
				absint( $offset )
			),
			ARRAY_A
		);

		return $results ?? array();
	}

	/**
	 * Delete a conversation and its messages.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID (for permission check).
	 * @return bool
	 */
	public function delete_conversation( $conversation_id, $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_conversations';

		// Verify ownership before deletion.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM $table WHERE id = %d",
				absint( $conversation_id )
			)
		);

		if ( ! $conversation || (int) $conversation->user_id !== absint( $user_id ) ) {
			return false;
		}

		// Delete cascades via foreign key to messages table.
		$result = $wpdb->delete(
			$table,
			array( 'id' => absint( $conversation_id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Purge conversations older than the retention period.
	 *
	 * Deletes conversations (and cascading messages) that have not been
	 * updated within the retention window. Intended to be called by wp_cron.
	 *
	 * @since 0.1.0
	 *
	 * @param int $retention_days Number of days to retain. Default 30.
	 * @return int Number of conversations purged.
	 */
	public function purge_expired_conversations( $retention_days = 30 ) {
		global $wpdb;

		/**
		 * Filters the conversation retention period in days.
		 *
		 * @param int $retention_days Default retention period.
		 *
		 * @since 0.1.0
		 */
		$retention_days = (int) apply_filters( 'hma_ai_chat_retention_days', $retention_days );
		$retention_days = max( 1, $retention_days ); // Minimum 1 day.

		$table    = $wpdb->prefix . 'hma_ai_conversations';
		$cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Foreign key CASCADE handles message deletion.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table WHERE updated_at < %s",
				$cutoff
			)
		);

		if ( $deleted > 0 ) {
			/**
			 * Fires after expired conversations are purged.
			 *
			 * @param int    $deleted        Number of conversations deleted.
			 * @param int    $retention_days  Retention period used.
			 * @param string $cutoff         Cutoff datetime.
			 *
			 * @since 0.1.0
			 */
			do_action( 'hma_ai_chat_conversations_purged', $deleted, $retention_days, $cutoff );
		}

		return (int) $deleted;
	}

	/**
	 * Update conversation title.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $title           New title.
	 * @param int    $user_id         User ID (for permission check).
	 * @return bool
	 */
	public function update_conversation_title( $conversation_id, $title, $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hma_ai_conversations';

		// Verify ownership.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM $table WHERE id = %d",
				absint( $conversation_id )
			)
		);

		if ( ! $conversation || (int) $conversation->user_id !== absint( $user_id ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			array( 'title' => sanitize_text_field( $title ) ),
			array( 'id' => absint( $conversation_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}
}
