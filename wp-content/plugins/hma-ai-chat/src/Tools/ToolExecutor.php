<?php
/**
 * Tool executor — dispatches AI tool calls to gym/v1 REST endpoints.
 *
 * Read tools are executed internally via rest_do_request() (zero HTTP
 * overhead). Write tools are queued as PendingAction records for staff
 * approval before any mutation occurs.
 *
 * @package HMA_AI_Chat\Tools
 * @since   0.2.0
 */

declare( strict_types=1 );

namespace HMA_AI_Chat\Tools;

use HMA_AI_Chat\Agents\AgentUserManager;
use HMA_AI_Chat\Data\PendingActionStore;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Dispatches tool calls from AI responses to gym/v1 REST endpoints.
 *
 * @since 0.2.0
 */
class ToolExecutor {

	/**
	 * REST namespace for gym-core endpoints.
	 *
	 * @var string
	 */
	private const GYM_NAMESPACE = 'gym/v1';

	/**
	 * REST namespace for WooCommerce endpoints.
	 *
	 * @var string
	 */
	private const WC_NAMESPACE = 'wc/v3';

	/**
	 * Tool registry instance.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $registry;

	/**
	 * Pending action store for write-tool approvals.
	 *
	 * @var PendingActionStore
	 */
	private PendingActionStore $pending_store;

	/**
	 * Constructor.
	 *
	 * @param ToolRegistry       $registry      Tool registry.
	 * @param PendingActionStore $pending_store  Pending action store.
	 */
	public function __construct( ToolRegistry $registry, PendingActionStore $pending_store ) {
		$this->registry      = $registry;
		$this->pending_store = $pending_store;
	}

	/**
	 * Execute a tool call, routing to read or write handler as appropriate.
	 *
	 * @param string $tool_name  Tool name as defined in ToolRegistry.
	 * @param array  $parameters Parameters from the AI model's tool call.
	 * @param int    $user_id    WordPress user ID of the staff member.
	 * @param string $persona    Agent persona slug invoking the tool.
	 * @return array{success: bool, data: mixed, pending?: bool, action_id?: int, error?: string}
	 */
	public function execute( string $tool_name, array $parameters, int $user_id, string $persona = '' ): array {
		// 1. Validate tool exists.
		$tool = $this->registry->get_tool( $tool_name );

		if ( null === $tool ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => sprintf(
					/* translators: %s: tool name */
					__( 'Unknown tool: %s', 'hma-ai-chat' ),
					$tool_name
				),
			);
		}

		// 2. Validate persona access (when persona is provided).
		if ( '' !== $persona && ! $this->registry->persona_has_tool( $persona, $tool_name ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => sprintf(
					/* translators: 1: persona slug, 2: tool name */
					__( 'Agent "%1$s" does not have access to tool "%2$s".', 'hma-ai-chat' ),
					$persona,
					$tool_name
				),
			);
		}

		// 3. Check user capability.
		if ( ! user_can( $user_id, $tool['auth_cap'] ) && ! user_can( $user_id, 'manage_options' ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Insufficient permissions for this tool.', 'hma-ai-chat' ),
			);
		}

		// 4. Route to read or write handler.
		$defaults = $tool['defaults'] ?? array();

		if ( ! empty( $tool['write'] ) ) {
			return $this->create_pending_action( $tool_name, $parameters, $user_id, $persona );
		}

		return $this->execute_read( $tool['endpoint'], $tool['method'], $parameters, $user_id, $defaults );
	}

	/**
	 * Execute a read tool via internal REST dispatch (no HTTP overhead).
	 *
	 * Resolves endpoint placeholders, maps parameters to the request, and
	 * dispatches via rest_do_request().
	 *
	 * @param string $endpoint Raw endpoint path (may contain {placeholder} tokens).
	 * @param string $method   HTTP method (GET or POST).
	 * @param array  $params   Parameters from the AI tool call.
	 * @param int    $user_id  WordPress user ID for auth context.
	 * @param array  $defaults Tool-level default parameters (merged before dispatch).
	 * @return array{success: bool, data: mixed, error?: string}
	 */
	public function execute_read( string $endpoint, string $method, array $params, int $user_id, array $defaults = array() ): array {
		// Merge tool-level defaults (e.g. prospects_only for sales CRM tools).
		if ( ! empty( $defaults ) ) {
			$params = array_merge( $defaults, $params );
		}

		// Resolve route namespace.
		$route = $this->resolve_route( $endpoint, $params );

		// Build the internal REST request.
		$request = new WP_REST_Request( strtoupper( $method ), $route );

		// Set remaining parameters (those not consumed by placeholder resolution).
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		// Switch to the staff user context for permission callbacks.
		$previous_user = get_current_user_id();
		wp_set_current_user( $user_id );

		/** @var WP_REST_Response $response */
		$response = rest_do_request( $request );

		// Restore previous user context.
		wp_set_current_user( $previous_user );

		// Interpret the response.
		$status = $response->get_status();
		$data   = $response->get_data();

		if ( $status >= 200 && $status < 300 ) {
			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		// Error response — extract message from WP_Error envelope.
		$error_message = __( 'Tool request failed.', 'hma-ai-chat' );

		if ( is_array( $data ) && isset( $data['message'] ) ) {
			$error_message = $data['message'];
		} elseif ( is_array( $data ) && isset( $data['code'] ) ) {
			$error_message = $data['code'];
		}

		return array(
			'success' => false,
			'data'    => $data,
			'error'   => $error_message,
		);
	}

	/**
	 * Queue a write tool as a pending action for staff approval.
	 *
	 * Write tools (draft_sms, recommend_promotion, promote_member,
	 * record_coach_roll, create_lead, create_kiosk_order,
	 * draft_announcement) are never executed immediately. Instead they
	 * create a PendingAction record that surfaces in the admin approval
	 * queue. Once approved, the action is dispatched by ActionEndpoint.
	 *
	 * @param string $tool_name Tool name.
	 * @param array  $params    Parameters from the AI tool call.
	 * @param int    $user_id   WordPress user ID who initiated the tool call.
	 * @param string $persona   Agent persona slug.
	 * @return array{success: bool, data: array<string, mixed>, pending: bool, action_id?: int, error?: string}
	 */
	public function create_pending_action( string $tool_name, array $params, int $user_id, string $persona = '' ): array {
		$tool = $this->registry->get_tool( $tool_name );

		if ( null === $tool ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Unknown tool.', 'hma-ai-chat' ),
			);
		}

		$agent_user_id = AgentUserManager::get_agent_user_id( $persona );

		// Two strings on every queued action:
		//   - summary: request-specific "what is this asking" — surfaces in
		//     the approval list ("Assign program 'kids-bjj' to class #113").
		//   - description: original generic tool description, kept around for
		//     audit-log context and tooltips.
		$action_data = array(
			'tool_name'     => $tool_name,
			'summary'       => self::summarize_call( $tool_name, $params, $tool ),
			'description'   => $tool['description'],
			'endpoint'      => $tool['endpoint'],
			'method'        => $tool['method'],
			'parameters'    => $params,
			'requested_by'  => $user_id,
			'agent_user_id' => $agent_user_id,
			'requested_at'  => gmdate( 'Y-m-d H:i:s' ),
		);

		$agent_slug = '' !== $persona ? $persona : 'unknown';

		$action_id = $this->pending_store->store_pending_action(
			$agent_slug,
			$tool_name,
			$action_data
		);

		if ( false === $action_id ) {
			return array(
				'success' => false,
				'data'    => null,
				'pending' => false,
				'error'   => __( 'Failed to queue action for approval.', 'hma-ai-chat' ),
			);
		}

		/**
		 * Fires when a write tool call is queued for approval.
		 *
		 * @param int    $action_id   Pending action ID.
		 * @param string $tool_name   Tool that was called.
		 * @param array  $params      Tool parameters.
		 * @param int    $user_id     Requesting user ID.
		 * @param string $agent_slug  Agent persona slug.
		 *
		 * @since 0.2.0
		 */
		do_action( 'hma_ai_chat_tool_queued', $action_id, $tool_name, $params, $user_id, $agent_slug );

		return array(
			'success'   => true,
			'data'      => array(
				'message'   => sprintf(
					/* translators: %s: tool name */
					__( 'Action "%s" has been queued for staff approval.', 'hma-ai-chat' ),
					$tool_name
				),
				'action_id' => $action_id,
				'tool'      => $tool_name,
			),
			'pending'   => true,
			'action_id' => $action_id,
		);
	}

	/**
	 * Execute an approved write action using the agent's user context.
	 *
	 * Called by ActionEndpoint after staff approves a pending action.
	 * Sets wp_set_current_user() to the agent's dedicated WP account so
	 * that created content (posts, announcements) has the agent as author.
	 *
	 * @since 0.5.0
	 *
	 * @param array $action_data The action_data from the PendingAction record.
	 * @return array{success: bool, data: mixed, error?: string}
	 */
	public function execute_approved_write( array $action_data ): array {
		$endpoint = $action_data['endpoint'] ?? '';
		$method   = $action_data['method'] ?? 'POST';
		$params   = $action_data['parameters'] ?? array();

		// Use agent user for content authorship if available,
		// otherwise fall back to the requesting staff user.
		$execute_as = $action_data['agent_user_id'] ?? $action_data['requested_by'] ?? 0;

		if ( empty( $execute_as ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'No user context available for write execution.', 'hma-ai-chat' ),
			);
		}

		return $this->execute_read( $endpoint, $method, $params, (int) $execute_as );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve an endpoint template into a full REST route path.
	 *
	 * Replaces {placeholder} tokens with values from $params, consuming
	 * matched keys so they are not sent as query parameters. Prepends the
	 * appropriate namespace (gym/v1 or wc/v3).
	 *
	 * @param string $endpoint Raw endpoint with optional {placeholders}.
	 * @param array  &$params  Parameters array (consumed keys are removed).
	 * @return string Fully-qualified REST route path (e.g. "/gym/v1/members/42/rank").
	 */
	private function resolve_route( string $endpoint, array &$params ): string {
		// Determine namespace from endpoint prefix.
		if ( str_starts_with( $endpoint, '/wc/' ) ) {
			// WooCommerce endpoints include their own namespace.
			$route = $endpoint;
		} else {
			$route = '/' . self::GYM_NAMESPACE . $endpoint;
		}

		// Replace {placeholder} tokens with parameter values.
		$route = preg_replace_callback(
			'/\{(\w+)\}/',
			static function ( array $matches ) use ( &$params ): string {
				$key = $matches[1];

				if ( isset( $params[ $key ] ) ) {
					$value = $params[ $key ];
					unset( $params[ $key ] );
					return (string) $value;
				}

				// Placeholder not found — leave as-is (will 404 gracefully).
				return $matches[0];
			},
			$route
		);

		return $route;
	}

	/**
	 * Build a request-specific summary line for a queued tool call.
	 *
	 * The approval UI renders one line per pending action; the previous code
	 * used the tool's generic registry description, so 13 queued
	 * assign_class_program actions all looked identical. This produces a
	 * concise human summary grounded in the actual parameters so reviewers
	 * can scan and bulk-approve confidently.
	 *
	 * @param string     $tool_name Tool name from the registry.
	 * @param array      $params    Parameters from the AI tool call.
	 * @param array|null $tool      Optional tool definition (used for label).
	 * @return string
	 */
	private static function summarize_call( string $tool_name, array $params, ?array $tool = null ): string {
		switch ( $tool_name ) {
			case 'assign_class_program':
				return sprintf(
					/* translators: 1: program slug, 2: class id */
					__( 'Assign program "%1$s" to class #%2$s', 'hma-ai-chat' ),
					(string) ( $params['program'] ?? '?' ),
					(string) ( $params['class_id'] ?? '?' )
				);

			case 'recommend_promotion':
				return sprintf(
					/* translators: 1: user id, 2: program slug */
					__( 'Recommend user #%1$s for promotion in %2$s', 'hma-ai-chat' ),
					(string) ( $params['user_id'] ?? '?' ),
					(string) ( $params['program'] ?? '?' )
				);

			case 'promote_member':
				$belt    = isset( $params['belt'] ) ? ' to ' . $params['belt'] : '';
				$stripes = isset( $params['stripes'] ) ? ' (' . (int) $params['stripes'] . ' stripes)' : '';
				return sprintf(
					/* translators: 1: user id, 2: program slug, 3: " to <belt>", 4: " (<stripes> stripes)" */
					__( 'Promote user #%1$s in %2$s%3$s%4$s', 'hma-ai-chat' ),
					(string) ( $params['user_id'] ?? '?' ),
					(string) ( $params['program'] ?? '?' ),
					$belt,
					$stripes
				);

			case 'record_coach_roll':
				return sprintf(
					/* translators: 1: user id, 2: roll outcome */
					__( 'Record coach roll for user #%1$s — outcome: %2$s', 'hma-ai-chat' ),
					(string) ( $params['user_id'] ?? '?' ),
					(string) ( $params['result'] ?? $params['outcome'] ?? '?' )
				);

			case 'enroll_foundations':
				return sprintf(
					/* translators: %s: user id */
					__( 'Enroll user #%s in Foundations', 'hma-ai-chat' ),
					(string) ( $params['user_id'] ?? '?' )
				);

			case 'clear_foundations':
				return sprintf(
					/* translators: %s: user id */
					__( 'Clear Foundations for user #%s', 'hma-ai-chat' ),
					(string) ( $params['user_id'] ?? '?' )
				);

			case 'create_lead':
				$name = trim( ( $params['first_name'] ?? '' ) . ' ' . ( $params['last_name'] ?? '' ) );
				$contact = $params['email'] ?? $params['phone'] ?? '';
				if ( '' !== $name && '' !== $contact ) {
					return sprintf(
						/* translators: 1: lead full name, 2: email or phone */
						__( 'Create lead: %1$s (%2$s)', 'hma-ai-chat' ),
						$name,
						$contact
					);
				}
				return __( 'Create lead', 'hma-ai-chat' ) . ( '' !== $name ? ': ' . $name : '' ) . ( '' !== $contact ? ' (' . $contact . ')' : '' );

			case 'create_kiosk_order':
				$name  = trim( ( $params['first_name'] ?? '' ) . ' ' . ( $params['last_name'] ?? '' ) );
				$down  = isset( $params['down_payment'] ) ? '$' . number_format( (float) $params['down_payment'], 2 ) : '?';
				$prod  = isset( $params['product_id'] ) ? '#' . (int) $params['product_id'] : '?';
				return sprintf(
					/* translators: 1: customer name (may be empty), 2: down payment, 3: product id */
					__( 'Kiosk order for %1$s — %2$s down on product %3$s', 'hma-ai-chat' ),
					'' !== $name ? $name : __( '(no name)', 'hma-ai-chat' ),
					$down,
					$prod
				);

			case 'draft_sms':
				$body = $params['message'] ?? $params['template_slug'] ?? '';
				$body = mb_substr( (string) $body, 0, 80 );
				return sprintf(
					/* translators: 1: phone, 2: truncated message body */
					__( 'SMS to %1$s: "%2$s"', 'hma-ai-chat' ),
					(string) ( $params['phone'] ?? '?' ),
					$body
				);

			case 'draft_announcement':
				$title = $params['title'] ?? mb_substr( (string) ( $params['body'] ?? $params['content'] ?? '' ), 0, 60 );
				return sprintf(
					/* translators: %s: announcement title or excerpt */
					__( 'Announcement: %s', 'hma-ai-chat' ),
					(string) $title
				);

			case 'draft_social_post':
				$body = mb_substr( (string) ( $params['content'] ?? $params['body'] ?? '' ), 0, 80 );
				return sprintf(
					/* translators: %s: social post excerpt */
					__( 'Social post: "%s"', 'hma-ai-chat' ),
					$body
				);

			case 'approve_social_post':
				return sprintf(
					/* translators: %s: post id */
					__( 'Approve social post #%s', 'hma-ai-chat' ),
					(string) ( $params['post_id'] ?? $params['id'] ?? '?' )
				);

			case 'add_crm_contact_note':
				$note = mb_substr( (string) ( $params['note'] ?? $params['content'] ?? '' ), 0, 80 );
				return sprintf(
					/* translators: 1: contact id, 2: truncated note */
					__( 'CRM note on contact #%1$s: "%2$s"', 'hma-ai-chat' ),
					(string) ( $params['contact_id'] ?? '?' ),
					$note
				);

			case 'issue_refund':
				$amount = isset( $params['amount'] ) ? '$' . number_format( (float) $params['amount'], 2 ) : __( 'full', 'hma-ai-chat' );
				return sprintf(
					/* translators: 1: amount, 2: order id */
					__( 'Refund %1$s on order #%2$s', 'hma-ai-chat' ),
					$amount,
					(string) ( $params['order_id'] ?? '?' )
				);
		}

		// Generic fallback: tool_name(key=value, key=value)
		$pairs = array();
		foreach ( $params as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$str = (string) $value;
			} else {
				$encoded = wp_json_encode( $value );
				$str     = is_string( $encoded ) ? $encoded : '?';
			}
			$pairs[] = $key . '=' . mb_substr( $str, 0, 60 );
		}
		$args_string = implode( ', ', $pairs );

		if ( '' === $args_string ) {
			return $tool_name;
		}

		return $tool_name . ' (' . $args_string . ')';
	}
}
