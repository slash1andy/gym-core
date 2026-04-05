<?php
/**
 * Form-to-CRM integration.
 *
 * Converts website form submissions (Jetpack Forms, WooCommerce checkout)
 * into Jetpack CRM contacts and pipeline entries with automatic sales rep
 * assignment based on location.
 *
 * @package Gym_Core
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Integrations;

/**
 * Hooks into form submission events and creates Jetpack CRM contacts
 * and pipeline entries.
 *
 * Supports:
 * - Jetpack Forms (Grunion) submissions
 * - WooCommerce first-time customer registrations
 *
 * @since 2.2.0
 */
final class FormToCrm {

	/**
	 * WooCommerce settings option IDs.
	 */
	private const OPT_ENABLED       = 'gym_core_crm_enabled';
	private const OPT_ROCKFORD_REP  = 'gym_core_crm_rockford_rep';
	private const OPT_BELOIT_REP    = 'gym_core_crm_beloit_rep';
	private const OPT_DEFAULT_STAGE = 'gym_core_crm_default_pipeline_stage';

	/**
	 * Default pipeline stage name for new leads.
	 *
	 * @var string
	 */
	private const DEFAULT_STAGE = 'New Lead';

	/**
	 * Tag applied to all form-sourced contacts.
	 *
	 * @var string
	 */
	private const SOURCE_TAG = 'source: website-form';

	/**
	 * Registers hooks if the integration is enabled and Jetpack CRM is active.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( 'yes' !== get_option( self::OPT_ENABLED, 'no' ) ) {
			return;
		}

		if ( ! self::is_jetpack_crm_active() ) {
			return;
		}

		// Jetpack Forms (Grunion).
		add_action( 'jetpack_contact_form_process_data', array( $this, 'handle_jetpack_form' ), 10, 2 );

		// WooCommerce new customer.
		add_action( 'woocommerce_new_customer', array( $this, 'handle_new_customer' ), 10, 2 );

		// WooCommerce first purchase — update lead to member.
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_completed' ), 10, 2 );

		// Register CRM settings section.
		add_filter( 'gym_core_settings_sections', array( $this, 'add_settings_section' ) );
		add_filter( 'gym_core_settings_crm', array( $this, 'get_settings' ) );
	}

	/**
	 * Checks whether Jetpack CRM is active by looking for its core function.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	private static function is_jetpack_crm_active(): bool {
		return function_exists( 'zeroBSCRM_getContactIDFromEmail' )
			|| function_exists( 'zeroBS_getCustomerIDWithEmail' )
			|| class_exists( '\ZeroBSCRM' );
	}

	/**
	 * Handles a Jetpack Form (Grunion) submission.
	 *
	 * Extracts contact fields, creates or updates the CRM contact,
	 * and creates a pipeline entry for new leads.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, mixed> $data    Submitted form field values.
	 * @param array<string, mixed> $headers Form metadata / headers.
	 * @return void
	 */
	public function handle_jetpack_form( array $data, array $headers = array() ): void {
		$fields = $this->extract_form_fields( $data );

		if ( empty( $fields['email'] ) ) {
			return;
		}

		$this->process_contact( $fields, 'jetpack-form' );
	}

	/**
	 * Handles a WooCommerce new customer registration.
	 *
	 * Creates a CRM contact for the new customer if one does not exist.
	 *
	 * @since 2.2.0
	 *
	 * @param int                  $customer_id WP user ID.
	 * @param array<string, mixed> $data        Customer data from WooCommerce.
	 * @return void
	 */
	public function handle_new_customer( int $customer_id, array $data = array() ): void {
		$user = get_userdata( $customer_id );

		if ( ! $user ) {
			return;
		}

		$fields = array(
			'email'      => $user->user_email,
			'first_name' => $user->first_name ?: '',
			'last_name'  => $user->last_name ?: '',
			'phone'      => get_user_meta( $customer_id, 'billing_phone', true ) ?: '',
			'program'    => '',
			'location'   => get_user_meta( $customer_id, 'gym_core_location', true ) ?: '',
		);

		$this->process_contact( $fields, 'woocommerce' );
	}

	/**
	 * Handles a completed WooCommerce order.
	 *
	 * If the customer exists as a lead in Jetpack CRM, updates their
	 * pipeline stage to "Closed Won" and swaps tags from lead to member.
	 *
	 * @since 2.2.0
	 *
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    WC_Order object (passed by WC on some versions).
	 * @return void
	 */
	public function handle_order_completed( int $order_id, $order = null ): void {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$email = $order->get_billing_email();

		if ( empty( $email ) ) {
			return;
		}

		$contact_id = $this->get_crm_contact_id( $email );

		if ( ! $contact_id ) {
			return;
		}

		// Check if this is the customer's first completed order.
		$customer_id     = $order->get_customer_id();
		$completed_count = $this->get_completed_order_count( $customer_id, $email );

		if ( $completed_count > 1 ) {
			// Not a first purchase — just add an activity note.
			$product_names = $this->get_order_product_names( $order );
			$this->add_crm_activity(
				$contact_id,
				sprintf(
					/* translators: %s: product name(s) */
					__( 'Purchased %s', 'gym-core' ),
					$product_names
				)
			);
			return;
		}

		// First purchase: update pipeline to Closed Won.
		$this->update_pipeline_stage( $contact_id, __( 'Closed Won', 'gym-core' ) );

		// Swap tags: remove "lead", add "member".
		$this->remove_crm_tag( $contact_id, 'lead' );
		$this->add_crm_tag( $contact_id, 'member' );

		// Activity note.
		$product_names = $this->get_order_product_names( $order );
		$this->add_crm_activity(
			$contact_id,
			sprintf(
				/* translators: %s: product name(s) */
				__( 'First purchase — Purchased %s', 'gym-core' ),
				$product_names
			)
		);
	}

	/**
	 * Processes a contact: creates or updates in Jetpack CRM.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, string> $fields Contact fields (email, first_name, last_name, phone, program, location).
	 * @param string                $source Source identifier (jetpack-form, woocommerce).
	 * @return void
	 */
	private function process_contact( array $fields, string $source ): void {
		$contact_id = $this->get_crm_contact_id( $fields['email'] );

		if ( $contact_id ) {
			// Existing contact — add activity note.
			$this->add_crm_activity(
				$contact_id,
				sprintf(
					/* translators: 1: form source, 2: program interest if any */
					__( 'New submission via %1$s. Interest: %2$s', 'gym-core' ),
					$source,
					$fields['program'] ?: __( 'not specified', 'gym-core' )
				)
			);
			return;
		}

		// New contact — create in CRM.
		$tags = array( 'lead', self::SOURCE_TAG );

		if ( ! empty( $fields['program'] ) ) {
			$tags[] = sanitize_title( $fields['program'] );
		}

		if ( ! empty( $fields['location'] ) ) {
			$tags[] = sanitize_title( $fields['location'] );
		}

		$contact_id = $this->create_crm_contact( $fields, $tags );

		if ( ! $contact_id ) {
			return;
		}

		// Create pipeline entry at the configured default stage.
		$stage = get_option( self::OPT_DEFAULT_STAGE, self::DEFAULT_STAGE ) ?: self::DEFAULT_STAGE;
		$this->create_pipeline_entry( $contact_id, $stage );

		// Auto-assign sales rep based on location.
		$this->assign_sales_rep( $contact_id, $fields['location'] );
	}

	/**
	 * Extracts standard contact fields from Jetpack Form submission data.
	 *
	 * Supports common field names and variations.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, mixed> $data Raw form data.
	 * @return array<string, string> Normalized fields.
	 */
	private function extract_form_fields( array $data ): array {
		$fields = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'phone'      => '',
			'program'    => '',
			'location'   => '',
		);

		// Normalize keys to lowercase for matching.
		$normalized = array();
		foreach ( $data as $key => $value ) {
			$normalized[ strtolower( trim( (string) $key ) ) ] = is_string( $value ) ? trim( $value ) : (string) $value;
		}

		// Email.
		$fields['email'] = $this->find_field( $normalized, array( 'email', 'email_address', 'e-mail', 'your-email' ) );

		// Name — try structured first, fall back to single "name" field.
		$fields['first_name'] = $this->find_field( $normalized, array( 'first_name', 'first-name', 'firstname', 'your-name' ) );
		$fields['last_name']  = $this->find_field( $normalized, array( 'last_name', 'last-name', 'lastname', 'surname' ) );

		if ( empty( $fields['first_name'] ) ) {
			$full_name = $this->find_field( $normalized, array( 'name', 'full_name', 'fullname', 'your-name' ) );
			if ( $full_name ) {
				$parts                = explode( ' ', $full_name, 2 );
				$fields['first_name'] = $parts[0];
				$fields['last_name']  = $parts[1] ?? '';
			}
		}

		// Phone.
		$fields['phone'] = $this->find_field( $normalized, array( 'phone', 'phone_number', 'telephone', 'tel', 'your-phone' ) );

		// Program / interest.
		$fields['program'] = $this->find_field( $normalized, array( 'program', 'most_interested_in', 'most interested in', 'interest', 'interested_in', 'class' ) );

		// Location.
		$fields['location'] = $this->find_field( $normalized, array( 'location', 'gym_location', 'preferred_location', 'gym-location', 'preferred location' ) );

		return $fields;
	}

	/**
	 * Searches an array for the first matching key from a list of candidates.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, string> $data       Normalized form data.
	 * @param array<int, string>    $candidates Field name candidates.
	 * @return string Found value or empty string.
	 */
	private function find_field( array $data, array $candidates ): string {
		foreach ( $candidates as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				return sanitize_text_field( $data[ $key ] );
			}
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// Jetpack CRM API wrappers (all guarded by function_exists).
	// -------------------------------------------------------------------------

	/**
	 * Gets a Jetpack CRM contact ID by email.
	 *
	 * @since 2.2.0
	 *
	 * @param string $email Contact email address.
	 * @return int|false Contact ID or false if not found.
	 */
	private function get_crm_contact_id( string $email ) {
		if ( function_exists( 'zeroBS_getCustomerIDWithEmail' ) ) {
			$id = zeroBS_getCustomerIDWithEmail( $email );
			return $id ? (int) $id : false;
		}

		if ( function_exists( 'zeroBSCRM_getContactIDFromEmail' ) ) {
			$id = zeroBSCRM_getContactIDFromEmail( $email );
			return $id ? (int) $id : false;
		}

		return false;
	}

	/**
	 * Creates a contact in Jetpack CRM.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, string> $fields Contact fields.
	 * @param array<int, string>    $tags   Tags to apply.
	 * @return int|false New contact ID or false on failure.
	 */
	private function create_crm_contact( array $fields, array $tags ) {
		if ( ! function_exists( 'zeroBS_integrations_addOrUpdateContact' ) ) {
			return false;
		}

		$contact_data = array(
			'email'   => $fields['email'],
			'fname'   => $fields['first_name'],
			'lname'   => $fields['last_name'],
			'hometel' => $fields['phone'],
			'tags'    => $tags,
		);

		/**
		 * Filters the contact data before creating in Jetpack CRM.
		 *
		 * @since 2.2.0
		 *
		 * @param array<string, mixed>  $contact_data CRM contact data array.
		 * @param array<string, string> $fields       Original extracted fields.
		 */
		$contact_data = apply_filters( 'gym_core_crm_contact_data', $contact_data, $fields );

		$result = zeroBS_integrations_addOrUpdateContact(
			'gym-core-form',
			$fields['email'],
			$contact_data
		);

		if ( is_array( $result ) && ! empty( $result['id'] ) ) {
			return (int) $result['id'];
		}

		// Fallback: try to retrieve the contact we just created.
		return $this->get_crm_contact_id( $fields['email'] );
	}

	/**
	 * Adds an activity / log note to a CRM contact.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $note       Activity note text.
	 * @return void
	 */
	private function add_crm_activity( int $contact_id, string $note ): void {
		if ( ! function_exists( 'zeroBSCRM_addUpdateLog' ) ) {
			return;
		}

		zeroBSCRM_addUpdateLog(
			$contact_id,
			-1,
			-1,
			array(
				'type'      => __( 'Form Submission', 'gym-core' ),
				'shortdesc' => $note,
				'longdesc'  => '',
			)
		);
	}

	/**
	 * Creates a pipeline / transaction entry for a CRM contact.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $stage      Pipeline stage name.
	 * @return void
	 */
	private function create_pipeline_entry( int $contact_id, string $stage ): void {
		if ( ! function_exists( 'zeroBSCRM_addUpdateObjectLinks' ) ) {
			return;
		}

		/**
		 * Fires when a new pipeline entry is created for a CRM contact.
		 *
		 * @since 2.2.0
		 *
		 * @param int    $contact_id CRM contact ID.
		 * @param string $stage      Pipeline stage name.
		 */
		do_action( 'gym_core_crm_pipeline_created', $contact_id, $stage );

		// Jetpack CRM uses quotes/invoices as pipeline stages depending on
		// the CRM configuration. We create a quote object as the pipeline entry.
		if ( function_exists( 'zeroBS_addUpdateQuote' ) ) {
			$quote_data = array(
				'title'      => sprintf(
					/* translators: 1: contact ID, 2: pipeline stage */
					__( 'Lead #%1$d — %2$s', 'gym-core' ),
					$contact_id,
					$stage
				),
				'value'      => '',
				'content'    => '',
				'notes'      => $stage,
				'customerid' => $contact_id,
			);

			zeroBS_addUpdateQuote( -1, $quote_data );
		}
	}

	/**
	 * Updates the pipeline stage for a CRM contact.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $stage      New pipeline stage.
	 * @return void
	 */
	private function update_pipeline_stage( int $contact_id, string $stage ): void {
		if ( ! function_exists( 'zeroBS_updateCustomerStatus' ) ) {
			return;
		}

		zeroBS_updateCustomerStatus( $contact_id, $stage );

		/**
		 * Fires when a CRM contact's pipeline stage is updated.
		 *
		 * @since 2.2.0
		 *
		 * @param int    $contact_id CRM contact ID.
		 * @param string $stage      New pipeline stage name.
		 */
		do_action( 'gym_core_crm_pipeline_updated', $contact_id, $stage );
	}

	/**
	 * Adds a tag to a CRM contact.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $tag        Tag name.
	 * @return void
	 */
	private function add_crm_tag( int $contact_id, string $tag ): void {
		if ( ! function_exists( 'zeroBSCRM_addUpdateTag' ) ) {
			return;
		}

		zeroBSCRM_addUpdateTag( 'contact', $contact_id, $tag );
	}

	/**
	 * Removes a tag from a CRM contact.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $tag        Tag name to remove.
	 * @return void
	 */
	private function remove_crm_tag( int $contact_id, string $tag ): void {
		if ( ! function_exists( 'zeroBSCRM_removeTag' ) ) {
			return;
		}

		zeroBSCRM_removeTag( 'contact', $contact_id, $tag );
	}

	/**
	 * Auto-assigns a sales rep to a CRM contact based on location.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $location   Location name (e.g. "Rockford", "Beloit").
	 * @return void
	 */
	private function assign_sales_rep( int $contact_id, string $location ): void {
		$rep_id = $this->get_rep_for_location( $location );

		if ( ! $rep_id ) {
			return;
		}

		if ( ! function_exists( 'zeroBS_setOwner' ) ) {
			return;
		}

		zeroBS_setOwner( $contact_id, $rep_id, 'contact' );

		/**
		 * Fires when a sales rep is auto-assigned to a CRM contact.
		 *
		 * @since 2.2.0
		 *
		 * @param int    $contact_id CRM contact ID.
		 * @param int    $rep_id     WordPress user ID of the assigned rep.
		 * @param string $location   Location that triggered the assignment.
		 */
		do_action( 'gym_core_crm_rep_assigned', $contact_id, $rep_id, $location );
	}

	/**
	 * Returns the configured sales rep user ID for a location.
	 *
	 * Falls back to the first admin user if no rep is configured.
	 *
	 * @since 2.2.0
	 *
	 * @param string $location Location name.
	 * @return int User ID of the assigned rep, or 0 if none found.
	 */
	private function get_rep_for_location( string $location ): int {
		$location_lower = strtolower( trim( $location ) );

		$rep_id = 0;

		if ( str_contains( $location_lower, 'rockford' ) ) {
			$rep_id = (int) get_option( self::OPT_ROCKFORD_REP, 0 );
		} elseif ( str_contains( $location_lower, 'beloit' ) ) {
			$rep_id = (int) get_option( self::OPT_BELOIT_REP, 0 );
		}

		// Fall back to first admin.
		if ( ! $rep_id ) {
			$rep_id = $this->get_first_admin_id();
		}

		return $rep_id;
	}

	/**
	 * Returns the user ID of the first administrator.
	 *
	 * @since 2.2.0
	 *
	 * @return int User ID or 0 if none found.
	 */
	private function get_first_admin_id(): int {
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);

		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}

	/**
	 * Returns the number of completed orders for a customer.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $customer_id WP user ID (0 for guests).
	 * @param string $email       Billing email as fallback for guests.
	 * @return int Number of completed orders.
	 */
	private function get_completed_order_count( int $customer_id, string $email ): int {
		$args = array(
			'status' => 'completed',
			'limit'  => -1,
			'return' => 'ids',
		);

		if ( $customer_id ) {
			$args['customer_id'] = $customer_id;
		} else {
			$args['billing_email'] = $email;
		}

		$orders = wc_get_orders( $args );

		return count( $orders );
	}

	/**
	 * Returns a comma-separated string of product names from an order.
	 *
	 * @since 2.2.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string Product names joined by comma.
	 */
	private function get_order_product_names( \WC_Order $order ): string {
		$names = array();

		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
		}

		return implode( ', ', $names ) ?: __( 'Unknown product', 'gym-core' );
	}

	// -------------------------------------------------------------------------
	// Settings (WooCommerce Settings API).
	// -------------------------------------------------------------------------

	/**
	 * Adds the CRM section to the Gym Core settings tab.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, string> $sections Existing sections.
	 * @return array<string, string>
	 */
	public function add_settings_section( array $sections ): array {
		$sections['crm'] = __( 'CRM', 'gym-core' );
		return $sections;
	}

	/**
	 * Returns the CRM settings fields for the WooCommerce settings page.
	 *
	 * @since 2.2.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings(): array {
		$admin_users = $this->get_admin_user_options();

		return array(
			array(
				'title' => __( 'CRM integration', 'gym-core' ),
				'desc'  => __( 'Convert website form submissions into Jetpack CRM contacts and pipeline entries. Requires Jetpack CRM to be active.', 'gym-core' ),
				'type'  => 'title',
				'id'    => 'gym_core_crm_options',
			),
			array(
				'title'   => __( 'Enable form-to-CRM', 'gym-core' ),
				'desc'    => __( 'Automatically create CRM contacts from Jetpack Forms and WooCommerce signups', 'gym-core' ),
				'id'      => self::OPT_ENABLED,
				'default' => 'no',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Rockford sales rep', 'gym-core' ),
				'desc'    => __( 'Auto-assign Rockford leads to this user', 'gym-core' ),
				'id'      => self::OPT_ROCKFORD_REP,
				'default' => '',
				'type'    => 'select',
				'options' => $admin_users,
				'class'   => 'wc-enhanced-select',
			),
			array(
				'title'   => __( 'Beloit sales rep', 'gym-core' ),
				'desc'    => __( 'Auto-assign Beloit leads to this user', 'gym-core' ),
				'id'      => self::OPT_BELOIT_REP,
				'default' => '',
				'type'    => 'select',
				'options' => $admin_users,
				'class'   => 'wc-enhanced-select',
			),
			array(
				'title'   => __( 'Default pipeline stage', 'gym-core' ),
				'desc'    => __( 'Pipeline stage assigned to new leads', 'gym-core' ),
				'id'      => self::OPT_DEFAULT_STAGE,
				'default' => self::DEFAULT_STAGE,
				'type'    => 'select',
				'options' => $this->get_pipeline_stage_options(),
				'class'   => 'wc-enhanced-select',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'gym_core_crm_options',
			),
		);
	}

	/**
	 * Returns an array of admin users for the settings select field.
	 *
	 * @since 2.2.0
	 *
	 * @return array<string, string> User ID => display name.
	 */
	private function get_admin_user_options(): array {
		$options = array(
			'' => __( '-- Auto (first admin) --', 'gym-core' ),
		);

		$users = get_users(
			array(
				'role__in' => array( 'administrator', 'shop_manager' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);

		foreach ( $users as $user ) {
			$options[ (string) $user->ID ] = sprintf( '%s (%s)', $user->display_name, $user->user_email );
		}

		return $options;
	}

	/**
	 * Returns available pipeline stage options.
	 *
	 * @since 2.2.0
	 *
	 * @return array<string, string> Stage name => label.
	 */
	private function get_pipeline_stage_options(): array {
		$stages = array(
			'New Lead'     => __( 'New Lead', 'gym-core' ),
			'Contacted'    => __( 'Contacted', 'gym-core' ),
			'Trial Booked' => __( 'Trial Booked', 'gym-core' ),
			'Trial Done'   => __( 'Trial Done', 'gym-core' ),
			'Negotiation'  => __( 'Negotiation', 'gym-core' ),
			'Closed Won'   => __( 'Closed Won', 'gym-core' ),
			'Closed Lost'  => __( 'Closed Lost', 'gym-core' ),
		);

		/**
		 * Filters the available pipeline stages for the CRM integration.
		 *
		 * @since 2.2.0
		 *
		 * @param array<string, string> $stages Stage slug => label.
		 */
		return apply_filters( 'gym_core_crm_pipeline_stages', $stages );
	}
}
