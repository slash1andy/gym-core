<?php
/**
 * Lightweight conversation memory — persists key facts across conversations.
 *
 * Stores per-user "memory notes" as user meta so Gandalf can remember
 * important details between separate chat sessions (e.g. a member's interest
 * in the kids program, preferred schedule, or recent inquiry topics).
 *
 * @package HMA_AI_Chat\Context
 * @since   0.3.0
 */

declare( strict_types=1 );

namespace HMA_AI_Chat\Context;

/**
 * Manages per-user memory notes for the AI agent.
 *
 * Notes are short strings stored as serialized user meta. A FIFO eviction
 * policy keeps memory bounded (max 20 notes per user). The AI agent can
 * persist a note via the `add_memory` tool registered in ToolRegistry.
 *
 * @since 0.3.0
 */
class ConversationMemory {

	/**
	 * User meta key for memory notes.
	 *
	 * @var string
	 */
	private const META_KEY = '_gandalf_memory';

	/**
	 * Maximum notes retained per user (FIFO eviction).
	 *
	 * @var int
	 */
	private const MAX_NOTES = 20;

	/**
	 * Maximum length of a single note in characters.
	 *
	 * @var int
	 */
	private const MAX_NOTE_LENGTH = 280;

	/**
	 * Register hooks.
	 *
	 * Registers the `add_memory` tool definition into the ToolRegistry for
	 * all personas, and hooks into tool execution to handle it.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'hma_ai_chat_persona_tools', array( $this, 'inject_memory_tool' ), 10, 2 );
	}

	/**
	 * Get all memory notes for a user.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array{note: string, timestamp: string}> Memory notes, newest last.
	 */
	public function get_memory( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$raw = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return $raw;
	}

	/**
	 * Add a memory note for a user.
	 *
	 * Notes are trimmed and truncated to MAX_NOTE_LENGTH. When the note
	 * count exceeds MAX_NOTES the oldest notes are evicted (FIFO).
	 *
	 * @since 0.3.0
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $note    Short memory note to persist.
	 * @return void
	 */
	public function add_note( int $user_id, string $note ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$note = sanitize_text_field( $note );
		$note = mb_substr( trim( $note ), 0, self::MAX_NOTE_LENGTH );

		if ( '' === $note ) {
			return;
		}

		$memory = $this->get_memory( $user_id );

		$memory[] = array(
			'note'      => $note,
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
		);

		// FIFO eviction: keep only the most recent MAX_NOTES entries.
		if ( count( $memory ) > self::MAX_NOTES ) {
			$memory = array_slice( $memory, -self::MAX_NOTES );
		}

		update_user_meta( $user_id, self::META_KEY, $memory );

		/**
		 * Fires after a memory note is added for a user.
		 *
		 * @param int    $user_id The user the note was added for.
		 * @param string $note    The note text.
		 * @param int    $count   Total note count after addition.
		 *
		 * @since 0.3.0
		 */
		do_action( 'hma_ai_chat_memory_note_added', $user_id, $note, count( $memory ) );
	}

	/**
	 * Format memory notes as a system prompt section.
	 *
	 * Returns an empty string if the user has no memory notes, so callers
	 * can safely concatenate without checking.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Formatted prompt section, or empty string.
	 */
	public function get_memory_prompt( int $user_id ): string {
		$memory = $this->get_memory( $user_id );

		if ( empty( $memory ) ) {
			return '';
		}

		$lines   = array();
		$lines[] = '--- Conversation Memory ---';
		$lines[] = 'You have the following memory notes about this user from previous conversations:';

		foreach ( $memory as $entry ) {
			$timestamp = $entry['timestamp'] ?? '';
			$note      = $entry['note'] ?? '';

			if ( '' === $note ) {
				continue;
			}

			if ( $timestamp ) {
				$lines[] = sprintf( '- [%s] %s', $timestamp, $note );
			} else {
				$lines[] = sprintf( '- %s', $note );
			}
		}

		$lines[] = '';
		$lines[] = 'Use these notes to provide personalized, contextual responses. When you learn something important about the user that would be useful in future conversations, use the add_memory tool to save it.';

		return "\n\n" . implode( "\n", $lines );
	}

	/**
	 * Clear all memory notes for a user.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public function clear_memory( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Inject the add_memory tool into every persona's tool list.
	 *
	 * Hooked to `hma_ai_chat_persona_tools` so the tool is available to
	 * all agent personas without modifying ToolRegistry's PERSONA_TOOLS constant.
	 *
	 * @since 0.3.0
	 *
	 * @param array  $tools   Current tool definitions for the persona.
	 * @param string $persona Agent slug.
	 * @return array Modified tool list with add_memory appended.
	 */
	public function inject_memory_tool( array $tools, string $persona ): array {
		$tools[] = array(
			'name'         => 'add_memory',
			'description'  => 'Save a short memory note about the current user for future conversations. Use this when you learn something important — their goals, preferences, family details, schedule preferences, or any context that would help personalize future interactions. Notes are limited to 280 characters.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'note' => array(
						'type'        => 'string',
						'description' => 'Short memory note to save (max 280 chars). Example: "User is interested in kids BJJ program for their 8-year-old daughter." or "Prefers evening classes, works daytime shift."',
					),
				),
				'required'   => array( 'note' ),
			),
			'endpoint'     => '__internal_memory',
			'method'       => 'POST',
			'auth_cap'     => 'edit_posts',
			'write'        => false, // Handled internally, not queued for approval.
		);

		return $tools;
	}

	/**
	 * Execute the add_memory tool call.
	 *
	 * Called by the tool execution layer when the AI invokes `add_memory`.
	 * This is a static convenience method so the executor can dispatch
	 * without needing to locate the ConversationMemory instance.
	 *
	 * @since 0.3.0
	 *
	 * @param array $params  Tool parameters (expects 'note' key).
	 * @param int   $user_id Target user ID (the user being discussed, or current user).
	 * @return array{success: bool, data: array<string, mixed>}
	 */
	public function execute_add_memory( array $params, int $user_id ): array {
		$note = $params['note'] ?? '';

		if ( empty( $note ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'A note is required.', 'hma-ai-chat' ),
			);
		}

		$this->add_note( $user_id, $note );

		return array(
			'success' => true,
			'data'    => array(
				'message' => __( 'Memory note saved.', 'hma-ai-chat' ),
				'note'    => sanitize_text_field( mb_substr( trim( $note ), 0, self::MAX_NOTE_LENGTH ) ),
			),
		);
	}
}
