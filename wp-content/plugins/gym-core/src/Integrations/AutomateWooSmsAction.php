<?php
/**
 * Custom AutomateWoo action: Send SMS via Twilio.
 *
 * Allows AutomateWoo workflows to send SMS messages using the gym-core
 * TwilioClient. Supports both predefined templates from MessageTemplates
 * and custom freeform messages.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Integrations;

use Gym_Core\SMS\TwilioClient;
use Gym_Core\SMS\MessageTemplates;

/**
 * Registers the Send SMS action with AutomateWoo.
 */
final class AutomateWooSmsAction {

	/**
	 * Registers the action class with AutomateWoo.
	 *
	 * Called from Plugin::init(). Safe to call unconditionally --
	 * the action class guards itself with class_exists().
	 *
	 * @since 2.1.0
	 */
	public static function init(): void {
		add_action( 'automatewoo/actions/register', array( __CLASS__, 'register_action' ) );
	}

	/**
	 * Registers the SMS action with the AutomateWoo action registry.
	 *
	 * @since 2.1.0
	 *
	 * @param \AutomateWoo\Actions $actions AutomateWoo actions registry.
	 */
	public static function register_action( $actions ): void {
		if ( class_exists( '\AutomateWoo\Action' ) ) {
			$actions->register( new GymSendSms() );
		}
	}
}

if ( class_exists( '\AutomateWoo\Action' ) ) :

	/**
	 * Action: Send SMS (Twilio).
	 *
	 * Sends an SMS to the workflow customer's billing phone number
	 * using either a predefined MessageTemplates template or a custom message.
	 *
	 * @since 2.1.0
	 */
	class GymSendSms extends \AutomateWoo\Action {

		/**
		 * Sets admin-visible title and description.
		 *
		 * @since 2.1.0
		 */
		public function load_admin_details(): void {
			$this->title       = __( 'Send SMS (Twilio)', 'gym-core' );
			$this->description = __( 'Sends an SMS message via Twilio. Choose a predefined template or write a custom message.', 'gym-core' );
			$this->group       = __( 'Gym', 'gym-core' );
		}

		/**
		 * Defines the admin UI fields for this action.
		 *
		 * @since 2.1.0
		 */
		public function load_fields(): void {
			// Template slug select field.
			$template_field = new \AutomateWoo\Fields\Select( false );
			$template_field->set_name( 'template_slug' );
			$template_field->set_title( __( 'Message Template', 'gym-core' ) );
			$template_field->set_description( __( 'Select a predefined SMS template. Leave empty to use the custom message below.', 'gym-core' ) );
			$template_field->set_required( false );

			$options = array( '' => __( '-- None (use custom message) --', 'gym-core' ) );
			foreach ( MessageTemplates::get_all() as $slug => $template ) {
				$options[ $slug ] = $template['name'];
			}
			$template_field->set_options( $options );
			$this->add_field( $template_field );

			// Custom message textarea.
			$custom_field = new \AutomateWoo\Fields\Text_Area();
			$custom_field->set_name( 'custom_message' );
			$custom_field->set_title( __( 'Custom Message', 'gym-core' ) );
			$custom_field->set_description( __( 'Optional. Overrides the template if provided. Supports AutomateWoo variables.', 'gym-core' ) );
			$custom_field->set_required( false );
			$custom_field->set_variable_validation();
			$this->add_field( $custom_field );
		}

		/**
		 * Executes the action: resolves phone number, renders message, sends SMS.
		 *
		 * @since 2.1.0
		 */
		public function run(): void {
			/** @var \AutomateWoo\Workflow $workflow */
			$workflow = $this->workflow;
			$customer = $workflow->data_layer()->get_customer();

			if ( ! $customer ) {
				$workflow->log_action_note( $this, __( 'SMS skipped: no customer in workflow data.', 'gym-core' ) );
				return;
			}

			// Resolve phone number from the WC customer billing phone.
			$user = $customer->get_user();
			$phone = '';

			if ( $user ) {
				$wc_customer = new \WC_Customer( $user->ID );
				$phone       = $wc_customer->get_billing_phone();
			}

			$phone = TwilioClient::sanitize_phone( $phone );

			if ( '' === $phone ) {
				$workflow->log_action_note( $this, __( 'SMS skipped: customer has no valid billing phone number.', 'gym-core' ) );
				return;
			}

			// Determine the message body.
			$custom_message = $this->get_option( 'custom_message' );
			$template_slug  = $this->get_option( 'template_slug' );
			$body           = '';

			if ( ! empty( $custom_message ) ) {
				// Process AutomateWoo variables in the custom message.
				$body = $workflow->variable_processor()->process_field( $custom_message );
			} elseif ( ! empty( $template_slug ) ) {
				// Build template variables from workflow data.
				$variables = $this->build_template_variables( $workflow, $customer );
				$rendered  = MessageTemplates::render( $template_slug, $variables );

				if ( null === $rendered ) {
					$workflow->log_action_note(
						$this,
						/* translators: %s: template slug */
						sprintf( __( 'SMS skipped: template "%s" not found.', 'gym-core' ), $template_slug )
					);
					return;
				}

				$body = $rendered;
			}

			if ( '' === trim( $body ) ) {
				$workflow->log_action_note( $this, __( 'SMS skipped: message body is empty (no template or custom message set).', 'gym-core' ) );
				return;
			}

			// Send via TwilioClient.
			$client = new TwilioClient();
			$result = $client->send( $phone, $body );

			if ( $result['success'] ) {
				$workflow->log_action_note(
					$this,
					/* translators: 1: phone number, 2: Twilio SID */
					sprintf( __( 'SMS sent to %1$s (SID: %2$s).', 'gym-core' ), $phone, $result['sid'] ?? '' )
				);
			} else {
				$workflow->log_action_note(
					$this,
					/* translators: 1: phone number, 2: error message */
					sprintf( __( 'SMS failed to %1$s: %2$s', 'gym-core' ), $phone, $result['error'] ?? __( 'Unknown error.', 'gym-core' ) )
				);
			}
		}

		/**
		 * Builds template placeholder variables from workflow data.
		 *
		 * @since 2.1.0
		 *
		 * @param \AutomateWoo\Workflow $workflow Workflow instance.
		 * @param \AutomateWoo\Customer $customer Customer data item.
		 * @return array<string, string> Variables keyed by placeholder name.
		 */
		private function build_template_variables( \AutomateWoo\Workflow $workflow, \AutomateWoo\Customer $customer ): array {
			$user      = $customer->get_user();
			$variables = array();

			if ( $user ) {
				$variables['first_name'] = $user->first_name ?: $user->display_name;
			}

			// Pull gym-specific data items from the workflow data layer.
			$data_layer = $workflow->data_layer();

			$program = $data_layer->get_item( 'program' );
			if ( is_string( $program ) ) {
				$variables['program'] = $program;
			}

			$new_belt = $data_layer->get_item( 'new_belt' );
			if ( is_string( $new_belt ) ) {
				$variables['belt'] = $new_belt;
			}

			$location = $data_layer->get_item( 'location' );
			if ( is_string( $location ) ) {
				$variables['location'] = $location;
			}

			$milestone = $data_layer->get_item( 'milestone_count' );
			if ( null !== $milestone ) {
				$variables['milestone_count'] = (string) $milestone;
			}

			/**
			 * Filters the template variables before rendering an SMS template.
			 *
			 * @since 2.1.0
			 *
			 * @param array<string, string>  $variables Template variables.
			 * @param \AutomateWoo\Workflow   $workflow  Current workflow.
			 * @param \AutomateWoo\Customer   $customer  Current customer.
			 */
			return apply_filters( 'gym_core_sms_template_variables', $variables, $workflow, $customer );
		}
	}

endif;
