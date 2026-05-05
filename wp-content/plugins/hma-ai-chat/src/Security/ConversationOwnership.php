<?php
declare(strict_types=1);
/**
 * Conversation ownership verification.
 *
 * Centralizes the ownership check used by every REST route that accepts a
 * `conversation_id` parameter. The requesting user must either own the
 * conversation row in the ConversationStore, or hold `manage_options`.
 *
 * @package HMA_AI_Chat
 * @since   0.4.0
 */

namespace HMA_AI_Chat\Security;

use HMA_AI_Chat\Data\ConversationStore;
use WP_Error;

/**
 * Verifies that the current user can access a given conversation_id.
 *
 * @since 0.4.0
 */
class ConversationOwnership {

	/**
	 * Conversation store used to resolve the row.
	 *
	 * @since 0.4.0
	 *
	 * @var ConversationStore
	 */
	private ConversationStore $store;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param ConversationStore|null $store Optional injected store.
	 */
	public function __construct( ?ConversationStore $store = null ) {
		$this->store = $store ?? new ConversationStore();
	}

	/**
	 * Check whether the current user may access $conversation_id.
	 *
	 * Returns true when the user owns the conversation OR holds
	 * `manage_options`. Returns a WP_Error (HTTP 403) otherwise.
	 *
	 * A conversation_id of 0/empty is treated as "no conversation yet" and
	 * passes — the caller will create one. The handler is responsible for
	 * not letting an unauthenticated user reach this code path; this helper
	 * is purely an authorization gate, not an authentication gate.
	 *
	 * @since 0.4.0
	 *
	 * @param int $conversation_id The conversation row primary key. 0 means none yet.
	 * @return true|WP_Error True when allowed; WP_Error when denied.
	 */
	public function check( int $conversation_id ) {
		if ( $conversation_id <= 0 ) {
			return true;
		}

		$current_user_id = get_current_user_id();
		if ( $current_user_id <= 0 ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You must be logged in to access this conversation.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		$conversation = $this->store->get_conversation_record( $conversation_id );
		if ( ! is_array( $conversation ) || ! isset( $conversation['user_id'] ) ) {
			// We deliberately return 403 (not 404) so that a non-owner cannot
			// distinguish "conversation does not exist" from "conversation
			// belongs to someone else". This denies enumeration.
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access this conversation.', 'hma-ai-chat' ),
				array( 'status' => 403 )
			);
		}

		if ( (int) $conversation['user_id'] === $current_user_id ) {
			return true;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'You do not have permission to access this conversation.', 'hma-ai-chat' ),
			array( 'status' => 403 )
		);
	}
}
