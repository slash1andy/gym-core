<?php
/**
 * MCP-compatible tool definitions per agent persona.
 *
 * Maps each agent persona to the gym/v1 REST endpoints it may invoke.
 * Tools are defined with JSON Schema input_schema so the AI model can
 * generate valid tool-call arguments.
 *
 * @package HMA_AI_Chat\Tools
 * @since   0.2.0
 */

declare( strict_types=1 );

namespace HMA_AI_Chat\Tools;

/**
 * Registry of AI-callable tools organised by agent persona.
 *
 * Each tool definition contains:
 *   - name          (string)  Unique tool identifier.
 *   - description   (string)  Human-readable purpose shown to the AI model.
 *   - input_schema  (array)   JSON Schema describing accepted parameters.
 *   - endpoint      (string)  gym/v1 REST route (may contain {placeholder} tokens).
 *   - method        (string)  HTTP verb: GET or POST.
 *   - auth_cap      (string)  WordPress capability required to execute.
 *   - write         (bool)    Whether the tool mutates data (requires approval).
 *
 * @since 0.2.0
 */
class ToolRegistry {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Flat map of all tool definitions keyed by tool name.
	 *
	 * Populated lazily on first access.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $tools = null;

	/**
	 * Full tool set for admin-level personas (finance + admin share this).
	 *
	 * @var list<string>
	 */
	private const ADMIN_TOOLS = array(
		// Operations.
		'get_today_attendance',
		'get_schedule',
		'get_classes',
		'assign_class_program',
		'get_locations',
		'get_announcements',
		// Training / coaching.
		'get_member_rank',
		'get_rank_history',
		'get_attendance',
		'get_badges',
		'get_streak',
		'get_briefing',
		'get_briefing_today',
		'get_foundations_status',
		'get_active_foundations',
		'get_promotion_eligible',
		'recommend_promotion',
		'promote_member',
		'record_coach_roll',
		'enroll_foundations',
		'clear_foundations',
		// Sales & SMS.
		'get_pricing',
		'calculate_pricing',
		'lookup_customer',
		'create_lead',
		'create_kiosk_order',
		'get_trial_info',
		'draft_sms',
		'get_sms_templates',
		// Announcements & social.
		'draft_announcement',
		'draft_social_post',
		'get_social_pending',
		'approve_social_post',
		// CRM (new).
		'search_crm_contacts',
		'get_crm_contact',
		'get_crm_contact_notes',
		'add_crm_contact_note',
		'get_crm_pipeline',
		// Orders & billing (new).
		'get_member_orders',
		'get_member_billing',
		'get_member_subscription_status',
		'get_churn_metrics',
		'issue_refund',
		// SMS history (new).
		'get_sms_history',
		// Class rosters (new).
		'get_class_roster',
	);

	/**
	 * Persona-to-tool-names mapping.
	 *
	 * Sales and coaching have restricted tool sets.
	 * Finance and admin share the full ADMIN_TOOLS set — differentiated by
	 * system prompt only (Joyous has a finance-oriented persona).
	 *
	 * @var array<string, list<string>>
	 */
	private const PERSONA_TOOLS = array(
		'sales'    => array(
			'get_pricing',
			'calculate_pricing',
			'lookup_customer',
			'create_lead',
			'create_kiosk_order',
			'get_schedule',
			'get_locations',
			'draft_sms',
			'get_trial_info',
			'get_announcements',
			'get_today_attendance',
			// CRM (new) — prospect-filtered via tool defaults.
			'search_crm_contacts',
			'get_crm_contact',
			'get_crm_contact_notes',
			'add_crm_contact_note',
			'get_crm_pipeline',
			// SMS history (new).
			'get_sms_history',
			// Class rosters (new).
			'get_class_roster',
		),
		'coaching' => array(
			'get_member_rank',
			'get_rank_history',
			'get_attendance',
			'get_badges',
			'get_streak',
			'get_schedule',
			'recommend_promotion',
			'get_briefing',
			'get_foundations_status',
			'record_coach_roll',
			'enroll_foundations',
			'clear_foundations',
			'get_active_foundations',
			'get_today_attendance',
			'get_promotion_eligible',
			'get_announcements',
			// Class rosters (new).
			'get_class_roster',
			// Subscription status — status only, no amounts (new).
			'get_member_subscription_status',
		),
		'finance'  => self::ADMIN_TOOLS,
		'admin'    => self::ADMIN_TOOLS,
	);

	/**
	 * Get registry singleton.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Get tool definitions available to a given agent persona.
	 *
	 * Returns an array of tool definition arrays ready to be serialised as
	 * MCP-compatible tool descriptions for the AI model.
	 *
	 * @param string $persona Agent slug (sales, coaching, finance, admin).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_tools_for_persona( string $persona ): array {
		$this->ensure_loaded();

		$tool_names = self::PERSONA_TOOLS[ $persona ] ?? array();
		$result     = array();

		foreach ( $tool_names as $name ) {
			if ( isset( $this->tools[ $name ] ) ) {
				$result[] = $this->tools[ $name ];
			}
		}

		/**
		 * Filters the tool list for a persona before it is sent to the AI model.
		 *
		 * @param array  $result  Tool definitions.
		 * @param string $persona Agent slug.
		 *
		 * @since 0.2.0
		 */
		return apply_filters( 'hma_ai_chat_persona_tools', $result, $persona );
	}

	/**
	 * Get a single tool definition by name.
	 *
	 * @param string $tool_name Tool name.
	 * @return array<string, mixed>|null Null when the tool does not exist.
	 */
	public function get_tool( string $tool_name ): ?array {
		$this->ensure_loaded();

		return $this->tools[ $tool_name ] ?? null;
	}

	/**
	 * Check whether a persona has access to a specific tool.
	 *
	 * @param string $persona   Agent slug.
	 * @param string $tool_name Tool name.
	 * @return bool
	 */
	public function persona_has_tool( string $persona, string $tool_name ): bool {
		$tool_names = self::PERSONA_TOOLS[ $persona ] ?? array();
		return in_array( $tool_name, $tool_names, true );
	}

	/**
	 * Get all registered tool names.
	 *
	 * @return list<string>
	 */
	public function get_all_tool_names(): array {
		$this->ensure_loaded();

		return array_keys( $this->tools );
	}

	// -------------------------------------------------------------------------
	// Definitions
	// -------------------------------------------------------------------------

	/**
	 * Lazily loads the full tool catalogue.
	 *
	 * @return void
	 */
	private function ensure_loaded(): void {
		if ( null !== $this->tools ) {
			return;
		}

		$definitions = $this->build_definitions();

		$this->tools = array();
		foreach ( $definitions as $tool ) {
			$this->tools[ $tool['name'] ] = $tool;
		}
	}

	/**
	 * Builds the complete tool definition catalogue.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function build_definitions(): array {
		return array(
			// -----------------------------------------------------------------
			// Sales tools
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_pricing',
				'description'  => 'Retrieve membership pricing and product listings for a location. Returns product names, prices, and descriptions.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'location' => array(
							'type'        => 'string',
							'description' => 'Location slug (e.g. "rockford", "beloit").',
						),
					),
					'required'   => array( 'location' ),
				),
				'endpoint'     => '/locations/{location}/products',
				'method'       => 'GET',
				'auth_cap'     => 'edit_posts',
				'write'        => false,
			),
			array(
				'name'         => 'calculate_pricing',
				'description'  => 'Calculate the final price breakdown for a membership product given a down payment. Returns the financed amount, schedule, and any taxes/fees so the agent can quote a bundle without doing arithmetic locally.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'   => array(
							'type'        => 'integer',
							'description' => 'WooCommerce product ID for the membership/bundle.',
						),
						'down_payment' => array(
							'type'        => 'number',
							'description' => 'Down payment amount in the store currency (e.g. 99.00).',
						),
					),
					'required'   => array( 'product_id', 'down_payment' ),
				),
				'endpoint'     => '/sales/calculate',
				'method'       => 'POST',
				'auth_cap'     => 'gym_process_sale',
				'write'        => false,
			),
			array(
				'name'         => 'lookup_customer',
				'description'  => 'Search for an existing customer by name or email at the sales kiosk. Use before creating a lead or order to avoid duplicates.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array(
							'type'        => 'string',
							'description' => 'Search query — name or email fragment.',
						),
					),
					'required'   => array( 'search' ),
				),
				'endpoint'     => '/sales/customer',
				'method'       => 'GET',
				'auth_cap'     => 'gym_process_sale',
				'write'        => false,
			),
			array(
				'name'         => 'create_kiosk_order',
				'description'  => 'Create a sales order at the kiosk for a customer purchasing a membership/bundle. Revenue-touching write — only execute after the staff member explicitly confirms product, price, and customer details. Pair with calculate_pricing to verify the breakdown first, and lookup_customer to avoid creating a duplicate.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'   => array(
							'type'        => 'integer',
							'description' => 'WooCommerce product ID for the membership/bundle being purchased.',
						),
						'down_payment' => array(
							'type'        => 'number',
							'description' => 'Down payment amount in store currency (e.g. 99.00).',
						),
						'email'        => array(
							'type'        => 'string',
							'description' => 'Customer email address.',
						),
						'first_name'   => array(
							'type'        => 'string',
							'description' => 'Customer first name.',
						),
						'last_name'    => array(
							'type'        => 'string',
							'description' => 'Customer last name.',
						),
						'phone'        => array(
							'type'        => 'string',
							'description' => 'Optional customer phone number in E.164 format.',
						),
						'address_1'    => array(
							'type'        => 'string',
							'description' => 'Optional billing street address.',
						),
						'city'         => array(
							'type'        => 'string',
							'description' => 'Optional billing city.',
						),
						'state'        => array(
							'type'        => 'string',
							'description' => 'Optional billing state.',
						),
						'postcode'     => array(
							'type'        => 'string',
							'description' => 'Optional billing postal code.',
						),
						'location'     => array(
							'type'        => 'string',
							'description' => 'Optional gym location slug to associate with the order.',
						),
					),
					'required'   => array( 'product_id', 'down_payment', 'email', 'first_name', 'last_name' ),
				),
				'endpoint'     => '/sales/order',
				'method'       => 'POST',
				'auth_cap'     => 'gym_process_sale',
				'write'        => true,
			),
			array(
				'name'         => 'create_lead',
				'description'  => 'Save a new prospect lead at the sales kiosk. Use when a walk-in or inquiry should be tracked but is not ready to purchase. All fields except the persisted record are optional, but at minimum capture either email or phone so the lead can be followed up.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'first_name' => array(
							'type'        => 'string',
							'description' => 'Lead first name.',
						),
						'last_name'  => array(
							'type'        => 'string',
							'description' => 'Lead last name.',
						),
						'email'      => array(
							'type'        => 'string',
							'description' => 'Lead email address.',
						),
						'phone'      => array(
							'type'        => 'string',
							'description' => 'Lead phone number in E.164 format (e.g. "+18155551234").',
						),
						'location'   => array(
							'type'        => 'string',
							'description' => 'Optional gym location slug to associate with the lead.',
						),
						'notes'      => array(
							'type'        => 'string',
							'description' => 'Optional staff notes (e.g. interest, source, follow-up cadence).',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/sales/lead',
				'method'       => 'POST',
				'auth_cap'     => 'gym_process_sale',
				'write'        => true,
			),
			array(
				'name'         => 'get_schedule',
				'description'  => 'Retrieve the weekly class schedule for a location, optionally filtered by program.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'location' => array(
							'type'        => 'string',
							'description' => 'Location slug (e.g. "rockford", "beloit").',
						),
						'program'  => array(
							'type'        => 'string',
							'description' => 'Optional program slug to filter classes (e.g. "bjj", "muay-thai").',
						),
						'week_of'  => array(
							'type'        => 'string',
							'description' => 'Optional date (Y-m-d) to get the schedule for that week. Defaults to current week.',
						),
					),
					'required'   => array( 'location' ),
				),
				'endpoint'     => '/schedule',
				'method'       => 'GET',
				'auth_cap'     => 'edit_posts',
				'write'        => false,
			),
			array(
				'name'         => 'get_classes',
				'description'  => 'List all active classes across the gym, optionally filtered by location, program, or instructor. Each class includes its assigned program (null when unassigned), location, instructor, capacity, and weekly time slot. Use this for audit-style queries that span all classes regardless of date — e.g. "which classes are missing a program" or "list every class an instructor teaches". Use get_schedule instead when you only need the day-by-day view for a single week.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'location'   => array(
							'type'        => 'string',
							'description' => 'Optional location slug to filter (e.g. "rockford", "beloit").',
						),
						'program'    => array(
							'type'        => 'string',
							'description' => 'Optional program slug to filter (e.g. "bjj", "muay-thai").',
						),
						'instructor' => array(
							'type'        => 'integer',
							'description' => 'Optional instructor user ID to filter.',
						),
						'per_page'   => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 20, max 100).',
						),
						'page'       => array(
							'type'        => 'integer',
							'description' => 'Page number for pagination (default 1).',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/classes',
				'method'       => 'GET',
				'auth_cap'     => 'edit_posts',
				'write'        => false,
			),
			array(
				'name'         => 'assign_class_program',
				'description'  => 'Assign a program to a class. Replaces any existing program assignment with the supplied program slug. Use this to fix classes returned by get_classes that have program=null. The slug must already exist in the gym_program taxonomy (e.g. "adult-bjj", "kids-bjj", "kickboxing"). Write tool — queues for staff approval.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'class_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress post ID of the class.',
						),
						'program'  => array(
							'type'        => 'string',
							'description' => 'Program slug to assign (e.g. "adult-bjj", "kids-bjj", "kickboxing"). Must match an existing gym_program term.',
						),
					),
					'required'   => array( 'class_id', 'program' ),
				),
				'endpoint'     => '/classes/{class_id}/program',
				'method'       => 'POST',
				'auth_cap'     => 'gym_promote_student',
				'write'        => true,
			),
			array(
				'name'         => 'get_locations',
				'description'  => 'List all gym locations with names, descriptions, and product counts.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'endpoint'     => '/locations',
				'method'       => 'GET',
				'auth_cap'     => 'edit_posts',
				'write'        => false,
			),
			array(
				'name'         => 'draft_sms',
				'description'  => 'Draft an SMS message to a prospect or member. Requires staff approval before sending. Can use a template slug or provide a raw message body.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'phone'         => array(
							'type'        => 'string',
							'description' => 'Recipient phone number in E.164 format (e.g. "+18155551234").',
						),
						'message'       => array(
							'type'        => 'string',
							'description' => 'Message body text (max 1600 chars). Required unless template_slug is provided.',
						),
						'template_slug' => array(
							'type'        => 'string',
							'description' => 'Predefined SMS template slug instead of raw message.',
						),
						'variables'     => array(
							'type'        => 'object',
							'description' => 'Template variable substitutions (e.g. {"first_name": "John"}).',
						),
						'contact_id'    => array(
							'type'        => 'integer',
							'description' => 'CRM contact ID for conversation tracking.',
						),
					),
					'required'   => array( 'phone' ),
				),
				'endpoint'     => '/sms/send',
				'method'       => 'POST',
				'auth_cap'     => 'gym_send_sms',
				'write'        => true,
			),
			array(
				'name'         => 'get_trial_info',
				'description'  => 'Get available SMS templates for trial-related communications and lead follow-up.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'endpoint'     => '/sms/templates',
				'method'       => 'GET',
				'auth_cap'     => 'edit_posts',
				'write'        => false,
			),

			// -----------------------------------------------------------------
			// Coaching tools
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_member_rank',
				'description'  => 'Get the current belt rank for a member, optionally filtered to a specific program. Includes attendance since last promotion and next belt info.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
						'program' => array(
							'type'        => 'string',
							'description' => 'Optional program slug (e.g. "bjj", "muay-thai").',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/members/{user_id}/rank',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_ranks',
				'write'        => false,
			),
			array(
				'name'         => 'get_rank_history',
				'description'  => 'Get the full promotion history for a member with dates, belt transitions, and who promoted them.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
						'program' => array(
							'type'        => 'string',
							'description' => 'Optional program slug to filter history.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/members/{user_id}/rank-history',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_ranks',
				'write'        => false,
			),
			array(
				'name'         => 'get_attendance',
				'description'  => 'Get attendance history for a member, with optional date range filtering.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
						'from'    => array(
							'type'        => 'string',
							'description' => 'Start date filter (Y-m-d).',
						),
						'to'      => array(
							'type'        => 'string',
							'description' => 'End date filter (Y-m-d).',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10, max 100).',
						),
						'page'    => array(
							'type'        => 'integer',
							'description' => 'Page number (default 1).',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/attendance/{user_id}',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_attendance',
				'write'        => false,
			),
			array(
				'name'         => 'get_badges',
				'description'  => 'Get all badges earned by a member, with badge details and earned timestamps.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/members/{user_id}/badges',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_achievements',
				'write'        => false,
			),
			array(
				'name'         => 'get_streak',
				'description'  => 'Get current and longest training streak for a member (consecutive weeks with attendance).',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/members/{user_id}/streak',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_achievements',
				'write'        => false,
			),
			array(
				'name'         => 'recommend_promotion',
				'description'  => 'Record a coach\'s recommendation that a member is ready for promotion. This does NOT execute the promotion — it only flags the member for staff review. Use promote_member to actually advance a member\'s rank.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member to recommend.',
						),
						'program' => array(
							'type'        => 'string',
							'description' => 'Program slug (e.g. "bjj", "muay-thai").',
						),
					),
					'required'   => array( 'user_id', 'program' ),
				),
				'endpoint'     => '/promotions/recommend',
				'method'       => 'POST',
				'auth_cap'     => 'gym_promote_student',
				'write'        => true,
			),
			array(
				'name'         => 'promote_member',
				'description'  => 'Execute a rank promotion for a member. This actually advances the member\'s rank — use only when the staff member explicitly confirms the promotion. For "ready for promotion" flags, use recommend_promotion instead.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member being promoted.',
						),
						'program' => array(
							'type'        => 'string',
							'description' => 'Program slug (e.g. "bjj", "muay-thai").',
						),
						'belt'    => array(
							'type'        => 'string',
							'description' => 'Optional new belt color/level for the member after promotion.',
						),
						'stripes' => array(
							'type'        => 'integer',
							'description' => 'Optional new stripe count after promotion.',
						),
						'notes'   => array(
							'type'        => 'string',
							'description' => 'Optional staff notes attached to the promotion record.',
						),
					),
					'required'   => array( 'user_id', 'program' ),
				),
				'endpoint'     => '/ranks/promote',
				'method'       => 'POST',
				'auth_cap'     => 'gym_promote_student',
				'write'        => true,
			),
			array(
				'name'         => 'get_briefing',
				'description'  => 'Generate a coach briefing for a specific class, including roster, attendance stats, new students, announcements, and promotion-eligible members.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'class_id' => array(
							'type'        => 'integer',
							'description' => 'Class post ID to generate the briefing for.',
						),
					),
					'required'   => array( 'class_id' ),
				),
				'endpoint'     => '/briefings/class/{class_id}',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_briefing',
				'write'        => false,
			),
			array(
				'name'         => 'get_foundations_status',
				'description'  => 'Get the Foundations clearance status for a member — whether they are in Foundations, their coach-roll count, and clearance status.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/foundations/{user_id}',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_ranks',
				'write'        => false,
			),
			array(
				'name'         => 'record_coach_roll',
				'description'  => 'Record a supervised coach roll for a Foundations student. Requires staff approval before recording. The student must be currently enrolled in Foundations.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the Foundations student.',
						),
						'notes'   => array(
							'type'        => 'string',
							'description' => 'Optional coach notes about the roll session.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/foundations/coach-roll',
				'method'       => 'POST',
				'auth_cap'     => 'gym_promote_student',
				'write'        => true,
			),

			// -----------------------------------------------------------------
			// Finance tools
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_revenue_summary',
				'description'  => 'Get a revenue summary from WooCommerce, including total sales, refunds, and net revenue for a date range.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'period'   => array(
							'type'        => 'string',
							'description' => 'Reporting period: "week", "month", "quarter", "year", or "custom".',
							'enum'        => array( 'week', 'month', 'quarter', 'year', 'custom' ),
						),
						'date_min' => array(
							'type'        => 'string',
							'description' => 'Start date for custom period (Y-m-d).',
						),
						'date_max' => array(
							'type'        => 'string',
							'description' => 'End date for custom period (Y-m-d).',
						),
					),
					'required'   => array( 'period' ),
				),
				'endpoint'     => '/wc/v3/reports/revenue/stats',
				'method'       => 'GET',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => false,
			),
			array(
				'name'         => 'get_subscriptions',
				'description'  => 'List active WooCommerce subscriptions with member names, plan details, and billing dates.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'        => 'string',
							'description' => 'Subscription status filter: "active", "on-hold", "cancelled", "pending-cancel".',
							'enum'        => array( 'active', 'on-hold', 'cancelled', 'pending-cancel', 'any' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10, max 100).',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number.',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/wc/v3/subscriptions',
				'method'       => 'GET',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => false,
			),
			array(
				'name'         => 'get_failed_payments',
				'description'  => 'List WooCommerce orders with failed payment status, useful for identifying billing issues.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10, max 100).',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number.',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/wc/v3/orders',
				'method'       => 'GET',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => false,
			),
			array(
				'name'         => 'get_reports',
				'description'  => 'Retrieve WooCommerce analytics reports index, showing available report types.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'endpoint'     => '/wc/v3/reports',
				'method'       => 'GET',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => false,
			),

			// -----------------------------------------------------------------
			// Admin tools
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_today_attendance',
				'description'  => 'Get today\'s check-in records across all locations or filtered by location/class.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'location' => array(
							'type'        => 'string',
							'description' => 'Optional location slug to filter (e.g. "rockford", "beloit").',
						),
						'class_id' => array(
							'type'        => 'integer',
							'description' => 'Optional class post ID to filter.',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/attendance/today',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_attendance',
				'write'        => false,
			),
			array(
				'name'         => 'get_promotion_eligible',
				'description'  => 'List members who are eligible or approaching eligibility for promotion in a given program.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'program' => array(
							'type'        => 'string',
							'description' => 'Program slug (e.g. "adult-bjj", "kids-bjj", "kickboxing").',
						),
					),
					'required'   => array( 'program' ),
				),
				'endpoint'     => '/promotions/eligible',
				'method'       => 'GET',
				'auth_cap'     => 'gym_promote_student',
				'write'        => false,
			),
			array(
				'name'         => 'draft_announcement',
				'description'  => 'Draft a gym announcement (global, location-specific, or program-specific). Requires staff approval before publishing.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'           => array(
							'type'        => 'string',
							'description' => 'Announcement title.',
						),
						'content'         => array(
							'type'        => 'string',
							'description' => 'Announcement body text (supports HTML).',
						),
						'type'            => array(
							'type'        => 'string',
							'description' => 'Scope: "global", "location", or "program".',
							'enum'        => array( 'global', 'location', 'program' ),
						),
						'target_location' => array(
							'type'        => 'string',
							'description' => 'Location slug when type is "location".',
						),
						'target_program'  => array(
							'type'        => 'string',
							'description' => 'Program slug when type is "program".',
						),
						'start_date'      => array(
							'type'        => 'string',
							'description' => 'Start date in Y-m-d format.',
						),
						'end_date'        => array(
							'type'        => 'string',
							'description' => 'End date in Y-m-d format.',
						),
						'pinned'          => array(
							'type'        => 'boolean',
							'description' => 'Whether to pin this announcement.',
						),
					),
					'required'   => array( 'title' ),
				),
				'endpoint'     => '/announcements',
				'method'       => 'POST',
				'auth_cap'     => 'gym_manage_announcements',
				'write'        => true,
			),
			array(
				'name'         => 'draft_social_post',
				'description'  => 'Draft a social media post for the gym. The post is created with pending status so a coach can review and approve it before Jetpack Publicize auto-shares to connected social accounts. Use this for promotional content, event highlights, member spotlights, or community updates.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array(
							'type'        => 'string',
							'description' => 'Social post title.',
						),
						'content'  => array(
							'type'        => 'string',
							'description' => 'Social post body content (supports HTML). Keep concise for social sharing.',
						),
						'category' => array(
							'type'        => 'string',
							'description' => 'Post category slug (e.g. "general", "event", "promo"). Defaults to "general".',
						),
					),
					'required'   => array( 'title', 'content' ),
				),
				'endpoint'     => '/social/draft',
				'method'       => 'POST',
				'auth_cap'     => 'gym_manage_announcements',
				'write'        => true,
			),
			array(
				'name'         => 'get_briefing_today',
				'description'  => 'Get briefings for all of today\'s classes, optionally filtered by location. Includes roster, attendance stats, and announcements.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'location' => array(
							'type'        => 'string',
							'description' => 'Optional location slug to filter.',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/briefings/today',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_briefing',
				'write'        => false,
			),

			// -----------------------------------------------------------------
			// Cross-persona read tools
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_announcements',
				'description'  => 'List active gym announcements, optionally filtered by location or program. Returns title, content, type, targeting, and date range.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'location' => array(
							'type'        => 'string',
							'description' => 'Optional location slug to filter announcements.',
						),
						'program'  => array(
							'type'        => 'string',
							'description' => 'Optional program slug to filter announcements.',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number (default 1).',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10).',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/announcements',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_briefing',
				'write'        => false,
			),
			array(
				'name'         => 'get_sms_templates',
				'description'  => 'List all available SMS message templates with their slugs, names, body text, and descriptions.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
				'endpoint'     => '/sms/templates',
				'method'       => 'GET',
				'auth_cap'     => 'gym_send_sms',
				'write'        => false,
			),

			// -----------------------------------------------------------------
			// Foundations management tools
			// -----------------------------------------------------------------
			array(
				'name'         => 'enroll_foundations',
				'description'  => 'Enroll a student in the Foundations program. Requires staff approval. The student must not already be enrolled or cleared.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the student to enroll.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/foundations/enroll',
				'method'       => 'POST',
				'auth_cap'     => 'gym_promote_student',
				'write'        => true,
			),
			array(
				'name'         => 'clear_foundations',
				'description'  => 'Clear a Foundations student for live training. Requires staff approval. The student must be currently enrolled in Foundations.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the student to clear.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/foundations/clear',
				'method'       => 'POST',
				'auth_cap'     => 'gym_promote_student',
				'write'        => true,
			),
			array(
				'name'         => 'get_active_foundations',
				'description'  => 'List all students currently enrolled in the Foundations program, with their status and coach-roll counts.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
				'endpoint'     => '/foundations/active',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_ranks',
				'write'        => false,
			),

			// -----------------------------------------------------------------
			// Social post management tools
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_social_pending',
				'description'  => 'List all pending social posts awaiting coach or admin approval before publication via Jetpack Publicize.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
				'endpoint'     => '/social/pending',
				'method'       => 'GET',
				'auth_cap'     => 'gym_manage_announcements',
				'write'        => false,
			),
			array(
				'name'         => 'approve_social_post',
				'description'  => 'Approve and publish a pending social post. Triggers Jetpack Publicize to share to connected social media accounts. Requires staff approval.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Post ID of the pending social post to approve.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'endpoint'     => '/social/{post_id}/approve',
				'method'       => 'POST',
				'auth_cap'     => 'gym_manage_announcements',
				'write'        => true,
			),

			// -----------------------------------------------------------------
			// CRM tools (new — Jetpack CRM contacts and pipeline)
			// -----------------------------------------------------------------
			array(
				'name'         => 'search_crm_contacts',
				'description'  => 'Search CRM contacts by name, email, or phone. Supports prospect-only filtering.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'         => array(
							'type'        => 'string',
							'description' => 'Search term (name, email, or phone).',
						),
						'status'         => array(
							'type'        => 'string',
							'description' => 'Filter by CRM status (e.g. "Lead", "Customer").',
						),
						'prospects_only' => array(
							'type'        => 'boolean',
							'description' => 'Only return contacts without an active subscription.',
						),
						'per_page'       => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10).',
						),
						'page'           => array(
							'type'        => 'integer',
							'description' => 'Page number.',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/crm/contacts',
				'method'       => 'GET',
				'auth_cap'     => 'gym_process_sale',
				'write'        => false,
				'defaults'     => array( 'prospects_only' => true ),
			),
			array(
				'name'         => 'get_crm_contact',
				'description'  => 'Get full details for a CRM contact including tags, address, and pipeline stage.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'contact_id' => array(
							'type'        => 'integer',
							'description' => 'Jetpack CRM contact ID.',
						),
					),
					'required'   => array( 'contact_id' ),
				),
				'endpoint'     => '/crm/contacts/{contact_id}',
				'method'       => 'GET',
				'auth_cap'     => 'gym_process_sale',
				'write'        => false,
			),
			array(
				'name'         => 'get_crm_contact_notes',
				'description'  => 'Get activity log and notes for a CRM contact.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'contact_id' => array(
							'type'        => 'integer',
							'description' => 'Jetpack CRM contact ID.',
						),
						'per_page'   => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10).',
						),
						'page'       => array(
							'type'        => 'integer',
							'description' => 'Page number.',
						),
					),
					'required'   => array( 'contact_id' ),
				),
				'endpoint'     => '/crm/contacts/{contact_id}/notes',
				'method'       => 'GET',
				'auth_cap'     => 'gym_process_sale',
				'write'        => false,
			),
			array(
				'name'         => 'add_crm_contact_note',
				'description'  => 'Add a note to a CRM contact. Requires staff approval.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'contact_id' => array(
							'type'        => 'integer',
							'description' => 'Jetpack CRM contact ID.',
						),
						'note'       => array(
							'type'        => 'string',
							'description' => 'Note content to add.',
						),
					),
					'required'   => array( 'contact_id', 'note' ),
				),
				'endpoint'     => '/crm/contacts/{contact_id}/notes',
				'method'       => 'POST',
				'auth_cap'     => 'gym_process_sale',
				'write'        => true,
			),
			array(
				'name'         => 'get_crm_pipeline',
				'description'  => 'Get pipeline summary — count of CRM contacts per pipeline stage.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'endpoint'     => '/crm/pipeline',
				'method'       => 'GET',
				'auth_cap'     => 'gym_process_sale',
				'write'        => false,
			),

			// -----------------------------------------------------------------
			// Order & billing tools (new — WooCommerce orders, churn, refunds)
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_member_orders',
				'description'  => 'Get order history for a member including dates, totals, and line items.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id'  => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10).',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/orders/member/{user_id}',
				'method'       => 'GET',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => false,
			),
			array(
				'name'         => 'get_member_billing',
				'description'  => 'Get billing details for a member: active subscriptions, payment methods, next renewal dates, and recent payment history.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/orders/member/{user_id}/billing',
				'method'       => 'GET',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => false,
			),
			array(
				'name'         => 'get_member_subscription_status',
				'description'  => 'Get subscription status for a member. Coaches see status only (active/lapsed/on-hold). Finance and admin also see amounts and payment details.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the member.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'endpoint'     => '/subscriptions/member/{user_id}',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_attendance',
				'write'        => false,
			),
			array(
				'name'         => 'get_churn_metrics',
				'description'  => 'Get churn and retention metrics: cancellations, new signups, retention rate for a period.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'days' => array(
							'type'        => 'integer',
							'description' => 'Look-back period in days (default 30, max 365).',
						),
					),
					'required'   => array(),
				),
				'endpoint'     => '/orders/churn',
				'method'       => 'GET',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => false,
			),
			array(
				'name'         => 'issue_refund',
				'description'  => 'Issue a refund on a WooCommerce order. Requires staff approval. Omit amount for full refund.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'order_id' => array(
							'type'        => 'integer',
							'description' => 'WooCommerce order ID.',
						),
						'amount'   => array(
							'type'        => 'number',
							'description' => 'Refund amount. Omit for full refund.',
						),
						'reason'   => array(
							'type'        => 'string',
							'description' => 'Reason for the refund.',
						),
					),
					'required'   => array( 'order_id' ),
				),
				'endpoint'     => '/orders/{order_id}/refund',
				'method'       => 'POST',
				'auth_cap'     => 'manage_woocommerce',
				'write'        => true,
			),

			// -----------------------------------------------------------------
			// SMS history tool (new)
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_sms_history',
				'description'  => 'Get SMS conversation history for a CRM contact, showing sent and received messages.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'contact_id' => array(
							'type'        => 'integer',
							'description' => 'Jetpack CRM contact ID.',
						),
						'per_page'   => array(
							'type'        => 'integer',
							'description' => 'Results per page (default 10).',
						),
						'page'       => array(
							'type'        => 'integer',
							'description' => 'Page number.',
						),
					),
					'required'   => array( 'contact_id' ),
				),
				'endpoint'     => '/sms/conversations/{contact_id}',
				'method'       => 'GET',
				'auth_cap'     => 'gym_send_sms',
				'write'        => false,
			),

			// -----------------------------------------------------------------
			// Class roster tool (new)
			// -----------------------------------------------------------------
			array(
				'name'         => 'get_class_roster',
				'description'  => 'Get the forecasted roster for a class based on recent attendance patterns. Shows expected students, their ranks, and Foundations status.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'class_id' => array(
							'type'        => 'integer',
							'description' => 'Class post ID.',
						),
					),
					'required'   => array( 'class_id' ),
				),
				'endpoint'     => '/classes/{class_id}/roster',
				'method'       => 'GET',
				'auth_cap'     => 'gym_view_attendance',
				'write'        => false,
			),
		);
	}
}
