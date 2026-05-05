<?php
/**
 * Finance Copilot REST controller.
 *
 * Endpoints backing the Finance Copilot admin page (NLP query box, AR aging
 * table, monthly close). All endpoints gate on `manage_woocommerce` — the
 * same capability bookkeeping uses on the Woo reports screens — so Joy's
 * existing role grants access without a new capability layer.
 *
 * The dunning-draft endpoint NEVER sends a message; it returns the proposed
 * draft and queues it as a pending action via the
 * `gym_core_finance_dunning_drafted` hook. Approval lives in hma-ai-chat's
 * existing PendingActionStore.
 *
 * @package Gym_Core\Finance\API
 * @since   5.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Finance\API;

use Gym_Core\API\BaseController;
use Gym_Core\Finance\ARAggregator;
use Gym_Core\Finance\MonthlyClose;

/**
 * REST routes for Finance Copilot.
 *
 * Routes registered under gym/v1:
 *   GET  /finance/ar-aging               Aged receivables table.
 *   POST /finance/dunning/draft          Draft dunning message (queued, never sent).
 *   GET  /finance/close/{month}          Status of a month's close.
 *   POST /finance/close/{month}/run      Execute the close (idempotent).
 *   GET  /finance/recovery-queue         Failed-payment recovery snapshot.
 *
 * @since 5.0.0
 */
class FinanceController extends BaseController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'finance';

	/**
	 * AR aggregator dependency.
	 *
	 * @var ARAggregator
	 */
	private ARAggregator $ar;

	/**
	 * Monthly close orchestrator dependency.
	 *
	 * @var MonthlyClose
	 */
	private MonthlyClose $close;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param ARAggregator|null $ar    Optional injection seam.
	 * @param MonthlyClose|null $close Optional injection seam.
	 */
	public function __construct( ?ARAggregator $ar = null, ?MonthlyClose $close = null ) {
		parent::__construct();
		$this->ar    = $ar ?? new ARAggregator();
		$this->close = $close ?? new MonthlyClose( $this->ar );
	}

	/**
	 * Registers REST routes.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/ar-aging',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ar_aging' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/recovery-queue',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_recovery_queue' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dunning/draft',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'draft_dunning' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'order_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'tone'     => array(
						'type'              => 'string',
						'required'          => false,
						'enum'              => array( 'gentle', 'firm' ),
						'default'           => 'gentle',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/close/(?P<month>\d{4}-\d{2})',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_close_status' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'month' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/close/(?P<month>\d{4}-\d{2})/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_close' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'month' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'force' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * GET /finance/ar-aging
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_ar_aging( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		return $this->success_response( $this->ar->aggregate() );
	}

	/**
	 * GET /finance/recovery-queue
	 *
	 * Snapshots the failed-payment recovery queue for the dashboard widget.
	 * Reads orders in failed/on-hold status and exposes the next retry hint
	 * via the `gym_core_finance_recovery_next_retry` filter so WC
	 * Subscriptions integrations can plug in real retry timestamps.
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_recovery_queue( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );

		$rows  = array();
		$total = 0.0;

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'status'  => array( 'failed', 'on-hold' ),
					'limit'   => 100,
					'orderby' => 'date_modified',
					'order'   => 'DESC',
					'type'    => 'shop_order',
				)
			);

			$orders = is_array( $orders ) ? $orders : array();

			foreach ( $orders as $order ) {
				if ( ! ( $order instanceof \WC_Order ) ) {
					continue;
				}

				$order_id      = (int) $order->get_id();
				$amount        = (float) $order->get_total();
				$modified_date = $order->get_date_modified();
				$last_attempt  = null !== $modified_date ? (string) $modified_date->date( 'c' ) : '';

				/**
				 * Filters the next retry timestamp for a failed-payment recovery row.
				 *
				 * Default is empty; WC Subscriptions integrations should return an
				 * ISO-8601 timestamp.
				 *
				 * @since 5.0.0
				 *
				 * @param string $next_attempt ISO-8601 timestamp or empty string.
				 * @param int    $order_id     WooCommerce order ID.
				 */
				$next_attempt = (string) apply_filters( 'gym_core_finance_recovery_next_retry', '', $order_id );

				$rows[] = array(
					'order_id'      => $order_id,
					'customer_name' => $this->order_customer_name( $order ),
					'amount'        => $amount,
					'currency'      => (string) $order->get_currency(),
					'status'        => (string) $order->get_status(),
					'last_attempt'  => $last_attempt,
					'next_attempt'  => $next_attempt,
				);

				$total += $amount;
			}
		}

		return $this->success_response(
			array(
				'rows'  => $rows,
				'count' => count( $rows ),
				'total' => round( $total, 2 ),
			)
		);
	}

	/**
	 * POST /finance/dunning/draft
	 *
	 * Returns a draft outreach message AND files it on the pending action queue.
	 * Drafts are NEVER auto-sent — Joy approves each one through hma-ai-chat.
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function draft_dunning( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$order_id = (int) $request->get_param( 'order_id' );
		$tone     = (string) $request->get_param( 'tone' );

		if ( ! function_exists( 'wc_get_order' ) ) {
			return $this->error_response( 'wc_missing', __( 'WooCommerce is not loaded.', 'gym-core' ), 503 );
		}

		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return $this->error_response( 'order_not_found', __( 'Order not found.', 'gym-core' ), 404 );
		}

		$first_name = (string) $order->get_billing_first_name();
		$first_name = '' !== $first_name ? $first_name : __( 'there', 'gym-core' );
		$amount     = (float) $order->get_total();
		$amount_str = function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : (string) $amount;

		$draft = self::compose_dunning_draft( $first_name, $amount_str, $order_id, $tone );

		$payload = array(
			'order_id'          => (int) $order->get_id(),
			'customer_id'       => (int) $order->get_customer_id(),
			'tone'              => $tone,
			'channel'           => 'email',
			'subject'           => sprintf(
				/* translators: %d: order ID */
				__( 'Quick note about your Haanpaa membership (order #%d)', 'gym-core' ),
				(int) $order->get_id()
			),
			'message'           => $draft,
			'requires_approval' => true,
			'auto_send'         => false,
		);

		/**
		 * Fires when a dunning draft is generated.
		 *
		 * Listeners (hma-ai-chat) are expected to file the draft on the
		 * PendingActionStore so Joy can approve, edit, or reject it. The
		 * message is NOT sent by this filter — sending happens only after
		 * staff approval through the existing pending-action workflow.
		 *
		 * @since 5.0.0
		 *
		 * @param array<string, mixed> $payload Draft payload.
		 */
		do_action( 'gym_core_finance_dunning_drafted', $payload );

		return $this->success_response( $payload );
	}

	/**
	 * GET /finance/close/{month}
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_close_status( \WP_REST_Request $request ): \WP_REST_Response {
		$month  = (string) $request->get_param( 'month' );
		$status = $this->close->get_status( $month );

		return $this->success_response(
			array(
				'month'  => $month,
				'status' => $status,
			)
		);
	}

	/**
	 * POST /finance/close/{month}/run
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function run_close( \WP_REST_Request $request ): \WP_REST_Response {
		$month  = (string) $request->get_param( 'month' );
		$force  = (bool) $request->get_param( 'force' );
		$result = $this->close->run( $month, $force );

		return $this->success_response( $result );
	}

	/**
	 * Composes a brand-aligned dunning draft.
	 *
	 * Voice rules from brand-guide.md §8: warm, encouraging, direct. No
	 * pressure tactics ("act now"), no money-shaming language, never
	 * "fighting" / "warriors" — this is martial arts speaking, not a
	 * collections agency.
	 *
	 * @since 5.0.0
	 *
	 * @param string $first_name Recipient first name (or fallback).
	 * @param string $amount_str Stripped wc_price() string.
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $tone       'gentle' or 'firm'.
	 * @return string
	 */
	public static function compose_dunning_draft( string $first_name, string $amount_str, int $order_id, string $tone ): string {
		if ( 'firm' === $tone ) {
			return sprintf(
				/* translators: 1: first name, 2: amount, 3: order id */
				__( "Hi %1\$s,\n\nWe wanted to flag that your most recent membership payment of %2\$s (order #%3\$d) hasn't gone through. We've held off on any change to your account access while we sort this out.\n\nWhen you have a minute, could you update your payment details on file or reply to this message so we can get it cleared up? If something has changed on your end, just let us know — we'll work with you.\n\nWe appreciate you being part of the Haanpaa community.\n\n— Haanpaa Martial Arts", 'gym-core' ),
				$first_name,
				$amount_str,
				$order_id
			);
		}

		return sprintf(
			/* translators: 1: first name, 2: amount, 3: order id */
			__( "Hi %1\$s,\n\nQuick note — your most recent membership payment of %2\$s (order #%3\$d) didn't go through. No worries, this happens — usually it's an expired card or a brief bank hiccup.\n\nWhen you get a chance, could you take a look at your payment details? If you'd rather chat in person, stop by the front desk and we'll sort it together.\n\nThanks for training with us.\n\n— Haanpaa Martial Arts", 'gym-core' ),
			$first_name,
			$amount_str,
			$order_id
		);
	}

	/**
	 * Resolves a customer display name from an order.
	 *
	 * @param \WC_Order $order WC_Order instance.
	 * @return string
	 */
	private function order_customer_name( \WC_Order $order ): string {
		$first = (string) $order->get_billing_first_name();
		$last  = (string) $order->get_billing_last_name();
		$name  = trim( $first . ' ' . $last );

		if ( '' !== $name ) {
			return $name;
		}

		$email = (string) $order->get_billing_email();
		if ( '' !== $email ) {
			return $email;
		}

		return '#' . (int) $order->get_id();
	}
}
