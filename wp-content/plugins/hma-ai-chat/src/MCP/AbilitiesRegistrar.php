<?php
declare( strict_types=1 );
/**
 * WordPress Abilities API registrar.
 *
 * Registers all Gandalf tools as WordPress abilities via wp_register_ability()
 * so they are discoverable through the WP 7.0 MCP Adapter. Any MCP-compatible
 * client (Claude Desktop, other WP agents) can discover and invoke gym
 * operations through the standard Abilities API surface.
 *
 * Write tools continue to go through the PendingAction approval queue — MCP
 * callers cannot bypass staff approval.
 *
 * @package HMA_AI_Chat\MCP
 * @since   0.5.0
 */

namespace HMA_AI_Chat\MCP;

use HMA_AI_Chat\Plugin;
use HMA_AI_Chat\Tools\ToolRegistry;

/**
 * Registers Gandalf tools as WordPress abilities.
 *
 * @since 0.5.0
 */
class AbilitiesRegistrar {

	/**
	 * Namespace prefix for all Gandalf abilities.
	 *
	 * @var string
	 */
	const NAMESPACE_PREFIX = 'gandalf';

	/**
	 * Ability category slug.
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'gym-operations';

	/**
	 * Register the ability category.
	 *
	 * Hooked to 'wp_abilities_api_categories_init' (fires before abilities).
	 *
	 * @since 0.5.0
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			array(
				'label'       => __( 'Gym Operations', 'hma-ai-chat' ),
				'description' => __( 'AI-powered tools for Haanpaa Martial Arts gym management — attendance, promotions, billing, scheduling, and communications.', 'hma-ai-chat' ),
			)
		);
	}

	/**
	 * Register all tools as WordPress abilities.
	 *
	 * Hooked to 'wp_abilities_api_init'.
	 *
	 * @since 0.5.0
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$registry = ToolRegistry::instance();

		foreach ( $registry->get_all_tool_names() as $tool_name ) {
			$tool = $registry->get_tool( $tool_name );

			if ( null === $tool ) {
				continue;
			}

			self::register_ability( $tool_name, $tool );
		}
	}

	/**
	 * Register a single tool as a WordPress ability.
	 *
	 * @since 0.5.0
	 *
	 * @param string $tool_name Tool name from the ToolRegistry.
	 * @param array  $tool      Tool definition array.
	 */
	private static function register_ability( string $tool_name, array $tool ): void {
		// Convert snake_case tool name to kebab-case for ability naming.
		$ability_name = self::NAMESPACE_PREFIX . '/' . str_replace( '_', '-', $tool_name );

		/**
		 * Filters whether a Gandalf ability is exposed to MCP clients via mcp-adapter.
		 *
		 * The mcp-adapter plugin (WordPress/mcp-adapter) only exposes abilities
		 * with `meta['mcp']['public'] = true`. By default every Gandalf tool is
		 * public — write tools queue for staff approval through the existing
		 * PendingAction flow, so MCP callers cannot bypass approval gates. Site
		 * operators can suppress specific tools by returning false here.
		 *
		 * @param bool   $public      Whether to expose the ability to MCP clients.
		 * @param string $tool_name   Original tool name (e.g. 'get_mrr').
		 * @param array  $tool        Tool definition array from ToolRegistry.
		 *
		 * @since 0.5.2
		 */
		$mcp_public = (bool) apply_filters(
			'hma_ai_chat_mcp_public_ability',
			true,
			$tool_name,
			$tool
		);

		$args = array(
			'label'               => self::tool_name_to_label( $tool_name ),
			'description'         => $tool['description'] ?? '',
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => $tool['input_schema'] ?? array(),
			'execute_callback'    => self::make_execute_callback( $tool_name ),
			'permission_callback' => self::make_permission_callback( $tool ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => empty( $tool['write'] ),
					'destructive' => false,
					'idempotent'  => empty( $tool['write'] ),
				),
				// mcp-adapter (WordPress/mcp-adapter) reads these keys to
				// decide which abilities to expose to MCP clients. Without
				// 'public' => true the ability is registered with WP but
				// invisible to MCP discovery.
				'mcp'          => array(
					'public' => $mcp_public,
					'type'   => 'tool',
				),
			),
		);

		// Output schemas are optional — only forward when the tool defines one,
		// so abilities-api validation isn't enforced for unschemaed tools.
		if ( ! empty( $tool['output_schema'] ) && is_array( $tool['output_schema'] ) ) {
			$args['output_schema'] = $tool['output_schema'];
		}

		wp_register_ability( $ability_name, $args );
	}

	/**
	 * Create an execute callback for a tool.
	 *
	 * The callback delegates to ToolExecutor::execute() so that all tools
	 * pass through the same dispatch pipeline — capability checks, persona
	 * validation, and write-tool approval gating all apply.
	 *
	 * @since 0.5.0
	 *
	 * @param string $tool_name Tool name.
	 * @return callable
	 */
	private static function make_execute_callback( string $tool_name ): callable {
		return static function ( $input ) use ( $tool_name ) {
			$executor = Plugin::instance()->get_tool_executor();

			if ( null === $executor ) {
				return new \WP_Error(
					'hma_ai_chat_executor_unavailable',
					__( 'Tool executor is not available.', 'hma-ai-chat' )
				);
			}

			$params  = is_array( $input ) ? $input : array();
			$user_id = get_current_user_id();

			// MCP callers are persona-agnostic — empty persona skips
			// the persona_has_tool check in ToolExecutor::execute().
			$result = $executor->execute( $tool_name, $params, $user_id, '' );

			if ( empty( $result['success'] ) ) {
				return new \WP_Error(
					'hma_ai_chat_tool_error',
					$result['error'] ?? __( 'Tool execution failed.', 'hma-ai-chat' ),
					$result['data'] ?? null
				);
			}

			return $result['data'];
		};
	}

	/**
	 * Create a permission callback for a tool.
	 *
	 * @since 0.5.0
	 *
	 * @param array $tool Tool definition array.
	 * @return callable
	 */
	private static function make_permission_callback( array $tool ): callable {
		$required_cap = $tool['auth_cap'] ?? 'manage_options';

		return static function () use ( $required_cap ) {
			if ( current_user_can( $required_cap ) ) {
				return true;
			}

			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}

			return new \WP_Error(
				'hma_ai_chat_permission_denied',
				sprintf(
					/* translators: %s: capability name */
					__( 'You need the "%s" capability to use this tool.', 'hma-ai-chat' ),
					$required_cap
				)
			);
		};
	}

	/**
	 * Convert a snake_case tool name to a human-readable label.
	 *
	 * @since 0.5.0
	 *
	 * @param string $tool_name Tool name (e.g., 'get_member_rank').
	 * @return string Label (e.g., 'Get Member Rank').
	 */
	private static function tool_name_to_label( string $tool_name ): string {
		return ucwords( str_replace( '_', ' ', $tool_name ) );
	}
}
