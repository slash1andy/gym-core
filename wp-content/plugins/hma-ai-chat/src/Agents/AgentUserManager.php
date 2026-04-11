<?php
declare( strict_types=1 );
/**
 * Agent user account lifecycle manager.
 *
 * Creates and manages dedicated WordPress user accounts for each Gandalf
 * agent persona. These accounts own agent-authored content (social posts,
 * announcements) and provide a clean audit trail separate from staff users.
 *
 * Agent accounts:
 * - Use a custom 'hma_ai_agent' role with only the capabilities their tools require.
 * - Cannot log in (authentication blocked via the 'authenticate' filter).
 * - Are hidden from the Users list table in wp-admin.
 * - Are preserved on plugin deactivation; deleted only on full uninstall.
 *
 * @package HMA_AI_Chat\Agents
 * @since   0.5.0
 */

namespace HMA_AI_Chat\Agents;

use HMA_AI_Chat\Tools\ToolRegistry;

/**
 * Manages WordPress user accounts for agent personas.
 *
 * @since 0.5.0
 */
class AgentUserManager {

	/**
	 * Username prefix for agent accounts.
	 *
	 * @var string
	 */
	const USER_PREFIX = 'gandalf-';

	/**
	 * User meta key identifying an account as an agent.
	 *
	 * @var string
	 */
	const AGENT_META_KEY = '_hma_ai_chat_agent_slug';

	/**
	 * Custom WordPress role slug.
	 *
	 * @var string
	 */
	const ROLE_SLUG = 'hma_ai_agent';

	/**
	 * Option key storing the agent user ID map.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'hma_ai_chat_agent_user_ids';

	/**
	 * Non-routable email domain for agent accounts.
	 *
	 * @var string
	 */
	const EMAIL_DOMAIN = 'gandalf.haanpaa.local';

	/**
	 * Provision agent role, users, and capabilities.
	 *
	 * Idempotent — safe to call on every init. Creates missing
	 * accounts and syncs capabilities with the current tool set.
	 *
	 * @since 0.5.0
	 */
	public static function provision(): void {
		self::ensure_role();
		self::ensure_users();
		self::sync_capabilities();
	}

	/**
	 * Create the hma_ai_agent role if it doesn't exist.
	 *
	 * The role starts with zero capabilities. Per-agent capabilities are
	 * granted by sync_capabilities() based on each agent's tool set.
	 *
	 * @since 0.5.0
	 */
	public static function ensure_role(): void {
		if ( null === get_role( self::ROLE_SLUG ) ) {
			add_role(
				self::ROLE_SLUG,
				__( 'AI Agent', 'hma-ai-chat' ),
				array()
			);
		}
	}

	/**
	 * Create WordPress user accounts for each registered agent persona.
	 *
	 * @since 0.5.0
	 */
	public static function ensure_users(): void {
		$registry   = AgentRegistry::instance();
		$agents     = $registry->get_all_agents();
		$user_ids   = get_option( self::OPTION_KEY, array() );
		$updated    = false;

		foreach ( $agents as $slug => $agent ) {
			$login = self::USER_PREFIX . $slug;

			// Check if already tracked and still exists.
			if ( isset( $user_ids[ $slug ] ) ) {
				$existing = get_userdata( $user_ids[ $slug ] );
				if ( false !== $existing ) {
					continue;
				}
				// Tracked ID no longer exists — recreate below.
			}

			// Check if user exists by login (manual creation or re-activation).
			$user = get_user_by( 'login', $login );

			if ( false === $user ) {
				$user_id = wp_insert_user(
					array(
						'user_login'   => $login,
						'user_email'   => $login . '@' . self::EMAIL_DOMAIN,
						'user_pass'    => wp_generate_password( 64, true, true ),
						'display_name' => $agent->get_name(),
						'role'         => self::ROLE_SLUG,
					)
				);

				if ( is_wp_error( $user_id ) ) {
					continue;
				}

				update_user_meta( $user_id, self::AGENT_META_KEY, $slug );
			} else {
				$user_id = $user->ID;

				// Ensure meta is set (may be missing if user was created manually).
				update_user_meta( $user_id, self::AGENT_META_KEY, $slug );

				// Ensure correct role.
				$user->set_role( self::ROLE_SLUG );
			}

			$user_ids[ $slug ] = $user_id;
			$updated           = true;
		}

		if ( $updated ) {
			update_option( self::OPTION_KEY, $user_ids, true );
		}
	}

	/**
	 * Sync agent user capabilities with their current tool sets.
	 *
	 * Grants each agent user the auth_cap values required by their tools
	 * and removes any stale capabilities no longer in the tool set.
	 *
	 * @since 0.5.0
	 */
	public static function sync_capabilities(): void {
		$tool_registry = ToolRegistry::instance();
		$user_ids      = get_option( self::OPTION_KEY, array() );

		foreach ( $user_ids as $slug => $user_id ) {
			$user = get_userdata( $user_id );
			if ( false === $user ) {
				continue;
			}

			// Collect required capabilities from tools.
			$tools      = $tool_registry->get_tools_for_persona( $slug );
			$needed_caps = array();

			foreach ( $tools as $tool ) {
				if ( ! empty( $tool['auth_cap'] ) ) {
					$needed_caps[ $tool['auth_cap'] ] = true;
				}
			}

			// Grant missing capabilities.
			foreach ( $needed_caps as $cap => $grant ) {
				if ( ! $user->has_cap( $cap ) ) {
					$user->add_cap( $cap );
				}
			}

			// Remove stale capabilities (only custom gym caps, not core ones).
			$current_caps = $user->allcaps;
			foreach ( $current_caps as $cap => $granted ) {
				if ( $granted && ! isset( $needed_caps[ $cap ] ) && str_starts_with( $cap, 'gym_' ) ) {
					$user->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Get the WordPress user ID for an agent persona.
	 *
	 * If the tracked user no longer exists (manual deletion), automatically
	 * recreates the account and returns the new ID.
	 *
	 * @since 0.5.0
	 *
	 * @param string $persona_slug Agent persona slug.
	 * @return int|null WordPress user ID, or null if slug is unknown.
	 */
	public static function get_agent_user_id( string $persona_slug ): ?int {
		$user_ids = get_option( self::OPTION_KEY, array() );

		if ( isset( $user_ids[ $persona_slug ] ) ) {
			$user = get_userdata( $user_ids[ $persona_slug ] );
			if ( false !== $user ) {
				return $user_ids[ $persona_slug ];
			}

			// User was deleted — recreate.
			self::ensure_users();
			$user_ids = get_option( self::OPTION_KEY, array() );

			return $user_ids[ $persona_slug ] ?? null;
		}

		return null;
	}

	/**
	 * Block login attempts for agent user accounts.
	 *
	 * Hooked to the 'authenticate' filter at priority 100 (after WordPress
	 * has resolved the user). Returns a WP_Error if the user is an agent.
	 *
	 * @since 0.5.0
	 *
	 * @param \WP_User|\WP_Error|null $user     Authenticated user or error.
	 * @param string                  $username  Login username.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function block_agent_login( $user, $username ) {
		if ( is_string( $username ) && str_starts_with( $username, self::USER_PREFIX ) ) {
			$agent_user = get_user_by( 'login', $username );

			if ( false !== $agent_user && get_user_meta( $agent_user->ID, self::AGENT_META_KEY, true ) ) {
				return new \WP_Error(
					'hma_ai_chat_agent_login_blocked',
					__( 'Agent accounts cannot be used for interactive login.', 'hma-ai-chat' )
				);
			}
		}

		return $user;
	}

	/**
	 * Hide agent users from the wp-admin Users list table.
	 *
	 * Hooked to 'pre_get_users'. Excludes agent user IDs from the query
	 * so they don't clutter the staff user list.
	 *
	 * @since 0.5.0
	 *
	 * @param \WP_User_Query $query User query.
	 */
	public static function hide_from_user_list( \WP_User_Query $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		$user_ids = get_option( self::OPTION_KEY, array() );

		if ( empty( $user_ids ) ) {
			return;
		}

		$exclude = $query->get( 'exclude' );
		if ( ! is_array( $exclude ) ) {
			$exclude = array();
		}

		$exclude = array_merge( $exclude, array_values( $user_ids ) );
		$query->set( 'exclude', $exclude );
	}

	/**
	 * Clean up agent users and role on full uninstall.
	 *
	 * @since 0.5.0
	 */
	public static function uninstall(): void {
		$user_ids = get_option( self::OPTION_KEY, array() );

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		foreach ( $user_ids as $slug => $uid ) {
			if ( get_userdata( $uid ) ) {
				wp_delete_user( $uid );
			}
		}

		delete_option( self::OPTION_KEY );
		remove_role( self::ROLE_SLUG );
	}
}
