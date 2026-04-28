<?php
/**
 * CRM REST controller — exposes Jetpack CRM contact and pipeline data.
 *
 * Wraps Jetpack CRM's DAL functions behind gym/v1 REST endpoints so AI
 * agents can query contacts, add notes, and view pipeline state.
 *
 * @package Gym_Core\API
 * @since   2.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Sales\ProspectFilter;
use Gym_Core\Integrations\CrmSmsBridge;

/**
 * CRM endpoints for AI agent consumption.
 *
 * Routes:
 *   GET  /gym/v1/crm/contacts                     Search/list contacts
 *   GET  /gym/v1/crm/contacts/{contact_id}         Single contact detail
 *   GET  /gym/v1/crm/contacts/{contact_id}/notes   Contact activity log
 *   POST /gym/v1/crm/contacts/{contact_id}/notes   Add note to contact
 *   GET  /gym/v1/crm/pipeline                      Pipeline summary
 */
class CrmController extends BaseController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'crm';

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/contacts',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contacts' ),
				'permission_callback' => array( $this, 'permissions_crm' ),
				'args'                => array_merge(
					$this->pagination_route_args(),
					array(
						'search'         => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'description'       => __( 'Search contacts by name, email, or phone.', 'gym-core' ),
						),
						'status'         => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'description'       => __( 'Filter by CRM status (e.g., Lead, Customer).', 'gym-core' ),
						),
						'prospects_only' => array(
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
							'description'       => __( 'Return only contacts without an active subscription.', 'gym-core' ),
						),
					)
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/contacts/(?P<contact_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contact' ),
				'permission_callback' => array( $this, 'permissions_crm' ),
				'args'                => array(
					'contact_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/contacts/(?P<contact_id>[\d]+)/notes',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_contact_notes' ),
					'permission_callback' => array( $this, 'permissions_crm' ),
					'args'                => array_merge(
						$this->pagination_route_args(),
						array(
							'contact_id' => array(
								'type'              => 'integer',
								'required'          => true,
								'sanitize_callback' => 'absint',
								'validate_callback' => 'rest_validate_request_arg',
							),
						)
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_contact_note' ),
					'permission_callback' => array( $this, 'permissions_crm' ),
					'args'                => array(
						'contact_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'note'       => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
							'validate_callback' => 'rest_validate_request_arg',
							'description'       => __( 'Note content to add to the contact.', 'gym-core' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/pipeline',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pipeline' ),
				'permission_callback' => array( $this, 'permissions_crm' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permissions
	// -------------------------------------------------------------------------

	/**
	 * Permission: gym_process_sale or manage_woocommerce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_crm( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_process_sale' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You do not have permission to access CRM data.', 'gym-core' ), 403 );
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/**
	 * Lists/searches CRM contacts with optional prospect filtering.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_contacts( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! CrmSmsBridge::is_crm_active() ) {
			return $this->error_response( 'crm_unavailable', __( 'Jetpack CRM is not active.', 'gym-core' ), 503 );
		}

		$search         = (string) $request->get_param( 'search' );
		$status         = (string) $request->get_param( 'status' );
		$prospects_only = (bool) $request->get_param( 'prospects_only' );
		$page           = (int) $request->get_param( 'page' );
		$per_page       = (int) $request->get_param( 'per_page' );

		$contacts = $this->query_contacts( $search, $status, $page, $per_page + 10 );

		if ( $prospects_only ) {
			$contacts = ProspectFilter::filter_prospects( $contacts );
		}

		$total    = count( $contacts );
		$contacts = array_slice( $contacts, 0, $per_page );

		return $this->success_response(
			array_map( array( $this, 'format_contact_summary' ), $contacts ),
			$this->pagination_meta( $total, (int) ceil( $total / $per_page ), $page, $per_page )
		);
	}

	/**
	 * Returns a single CRM contact with full detail.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_contact( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! CrmSmsBridge::is_crm_active() ) {
			return $this->error_response( 'crm_unavailable', __( 'Jetpack CRM is not active.', 'gym-core' ), 503 );
		}

		$contact_id = (int) $request->get_param( 'contact_id' );
		$contact    = $this->get_crm_contact( $contact_id );

		if ( null === $contact ) {
			return $this->error_response( 'contact_not_found', __( 'CRM contact not found.', 'gym-core' ), 404 );
		}

		return $this->success_response( $this->format_contact_detail( $contact ) );
	}

	/**
	 * Returns activity notes for a CRM contact.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_contact_notes( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! CrmSmsBridge::is_crm_active() ) {
			return $this->error_response( 'crm_unavailable', __( 'Jetpack CRM is not active.', 'gym-core' ), 503 );
		}

		$contact_id = (int) $request->get_param( 'contact_id' );
		$page       = (int) $request->get_param( 'page' );
		$per_page   = (int) $request->get_param( 'per_page' );

		$notes = $this->query_contact_notes( $contact_id, $page, $per_page );

		return $this->success_response( $notes );
	}

	/**
	 * Adds a note to a CRM contact.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_contact_note( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! CrmSmsBridge::is_crm_active() ) {
			return $this->error_response( 'crm_unavailable', __( 'Jetpack CRM is not active.', 'gym-core' ), 503 );
		}

		$contact_id = (int) $request->get_param( 'contact_id' );
		$note       = (string) $request->get_param( 'note' );

		$contact = $this->get_crm_contact( $contact_id );

		if ( null === $contact ) {
			return $this->error_response( 'contact_not_found', __( 'CRM contact not found.', 'gym-core' ), 404 );
		}

		if ( function_exists( 'zeroBSCRM_activity_addToLog' ) ) {
			zeroBSCRM_activity_addToLog(
				$contact_id,
				array(
					'type'      => 'note',
					'shortdesc' => __( 'AI agent note', 'gym-core' ),
					'longdesc'  => wp_kses( $note, array() ),
				)
			);
		}

		return $this->success_response(
			array( 'added' => true ),
			null,
			201
		);
	}

	/**
	 * Returns pipeline summary — count of contacts per status.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_pipeline( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! CrmSmsBridge::is_crm_active() ) {
			return $this->error_response( 'crm_unavailable', __( 'Jetpack CRM is not active.', 'gym-core' ), 503 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'zbs_contacts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT zbsc_status AS status, COUNT(*) AS count FROM {$table} GROUP BY zbsc_status ORDER BY count DESC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: array();

		$pipeline = array();
		foreach ( $rows as $row ) {
			$pipeline[] = array(
				'stage' => $row->status ?: __( '(No status)', 'gym-core' ),
				'count' => (int) $row->count,
			);
		}

		return $this->success_response( $pipeline );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Queries CRM contacts via Jetpack CRM functions.
	 *
	 * @param string $search   Search term.
	 * @param string $status   Filter by status.
	 * @param int    $page     Page number.
	 * @param int    $per_page Results per page.
	 * @return array<int, array<string, mixed>>
	 */
	private function query_contacts( string $search, string $status, int $page, int $per_page ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'zbs_contacts';
		$where  = array( '1=1' );
		$values = array();

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(zbsc_fname LIKE %s OR zbsc_lname LIKE %s OR zbsc_email LIKE %s OR zbsc_mobtel LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( '' !== $status ) {
			$where[]  = 'zbsc_status = %s';
			$values[] = $status;
		}

		$offset    = ( $page - 1 ) * $per_page;
		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			empty( $values )
				? "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY ID DESC LIMIT {$per_page} OFFSET {$offset}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				: $wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY ID DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array_merge( $values, array( $per_page, $offset ) )
				),
			ARRAY_A
		) ?: array();

		return $results;
	}

	/**
	 * Gets a single CRM contact by ID.
	 *
	 * @param int $contact_id CRM contact ID.
	 * @return array<string, mixed>|null
	 */
	private function get_crm_contact( int $contact_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'zbs_contacts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$contact = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id
			),
			ARRAY_A
		);

		return $contact ?: null;
	}

	/**
	 * Queries activity log notes for a CRM contact.
	 *
	 * @param int $contact_id CRM contact ID.
	 * @param int $page       Page number.
	 * @param int $per_page   Results per page.
	 * @return array<int, array<string, mixed>>
	 */
	private function query_contact_notes( int $contact_id, int $page, int $per_page ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'zbs_logs';
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT zbsl_type AS type, zbsl_shortdesc AS title, zbsl_longdesc AS body, zbsl_created AS created_at
				FROM {$table}
				WHERE zbsl_objid = %d AND zbsl_objtype = 1
				ORDER BY zbsl_created DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id,
				$per_page,
				$offset
			),
			ARRAY_A
		) ?: array();

		return $results;
	}

	/**
	 * Formats a CRM contact row for list responses.
	 *
	 * @param array<string, mixed> $contact Raw CRM contact row.
	 * @return array<string, mixed>
	 */
	private function format_contact_summary( array $contact ): array {
		return array(
			'id'         => (int) ( $contact['ID'] ?? 0 ),
			'first_name' => $contact['zbsc_fname'] ?? '',
			'last_name'  => $contact['zbsc_lname'] ?? '',
			'email'      => $contact['zbsc_email'] ?? '',
			'phone'      => $contact['zbsc_mobtel'] ?? $contact['zbsc_hometel'] ?? '',
			'status'     => $contact['zbsc_status'] ?? '',
		);
	}

	/**
	 * Formats a CRM contact row for detail responses.
	 *
	 * @param array<string, mixed> $contact Raw CRM contact row.
	 * @return array<string, mixed>
	 */
	private function format_contact_detail( array $contact ): array {
		return array(
			'id'         => (int) ( $contact['ID'] ?? 0 ),
			'first_name' => $contact['zbsc_fname'] ?? '',
			'last_name'  => $contact['zbsc_lname'] ?? '',
			'email'      => $contact['zbsc_email'] ?? '',
			'phone'      => $contact['zbsc_mobtel'] ?? $contact['zbsc_hometel'] ?? '',
			'work_phone' => $contact['zbsc_worktel'] ?? '',
			'status'     => $contact['zbsc_status'] ?? '',
			'address'    => array(
				'street'   => $contact['zbsc_addr1'] ?? '',
				'city'     => $contact['zbsc_city'] ?? '',
				'state'    => $contact['zbsc_county'] ?? '',
				'postcode' => $contact['zbsc_postcode'] ?? '',
			),
			'tags'       => $this->get_contact_tags( (int) ( $contact['ID'] ?? 0 ) ),
			'created_at' => $contact['zbsc_created'] ?? '',
			'updated_at' => $contact['zbsc_lastupdated'] ?? '',
		);
	}

	/**
	 * Gets tags for a CRM contact.
	 *
	 * @param int $contact_id CRM contact ID.
	 * @return list<string>
	 */
	private function get_contact_tags( int $contact_id ): array {
		global $wpdb;
		$tags_table     = $wpdb->prefix . 'zbs_tags';
		$obj_tags_table = $wpdb->prefix . 'zbs_tags_links';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.zbstag_name FROM {$tags_table} t
				INNER JOIN {$obj_tags_table} ot ON t.ID = ot.zbstl_tagid
				WHERE ot.zbstl_objid = %d AND ot.zbstl_objtype = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id
			)
		) ?: array();

		return $results;
	}
}
