<?php
/**
 * Order REST controller — exposes WooCommerce order, billing, and churn data.
 *
 * Provides per-member order history, billing details, tiered subscription
 * status, churn metrics, and refund management for AI agents.
 *
 * @package Gym_Core\API
 * @since   2.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

/**
 * WooCommerce order and subscription endpoints.
 *
 * Routes:
 *   GET  /gym/v1/orders/member/{user_id}          Order history
 *   GET  /gym/v1/orders/member/{user_id}/billing   Billing details
 *   GET  /gym/v1/subscriptions/member/{user_id}    Subscription status (tiered)
 *   GET  /gym/v1/orders/churn                      Churn metrics
 *   POST /gym/v1/orders/{order_id}/refund          Issue refund
 */
class OrderController extends BaseController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders';

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Member order history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/member/(?P<user_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_member_orders' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array_merge(
					$this->pagination_route_args(),
					array(
						'user_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					)
				),
			)
		);

		// Member billing details.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/member/(?P<user_id>[\d]+)/billing',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_member_billing' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'user_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// Tiered subscription status.
		register_rest_route(
			$this->namespace,
			'/subscriptions/member/(?P<user_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_subscription_status' ),
				'permission_callback' => array( $this, 'permissions_subscription_status' ),
				'args'                => array(
					'user_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// Churn metrics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/churn',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_churn_metrics' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'days' => array(
						'type'              => 'integer',
						'default'           => 30,
						'minimum'           => 1,
						'maximum'           => 365,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Look-back period in days.', 'gym-core' ),
					),
				),
			)
		);

		// Issue refund.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<order_id>[\d]+)/refund',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'issue_refund' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
				'args'                => array(
					'order_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'amount'   => array(
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Refund amount. Omit for full refund.', 'gym-core' ),
					),
					'reason'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permissions
	// -------------------------------------------------------------------------

	/**
	 * Permission: gym_view_attendance (status-only) or manage_woocommerce (full).
	 *
	 * The subscription status endpoint uses a lower capability bar because
	 * the coaching agent needs membership status visibility without financial data.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_subscription_status( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'gym_view_attendance' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You do not have permission to view subscription status.', 'gym-core' ), 403 );
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/**
	 * Returns order history for a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_member_orders( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id  = (int) $request->get_param( 'user_id' );
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => $per_page,
				'offset'      => ( $page - 1 ) * $per_page,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$data = array();
		foreach ( $orders as $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = $item->get_name();
			}

			$data[] = array(
				'id'         => $order->get_id(),
				'date'       => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d' ) : null,
				'status'     => $order->get_status(),
				'total'      => $order->get_total(),
				'currency'   => $order->get_currency(),
				'line_items' => $items,
			);
		}

		return $this->success_response( $data );
	}

	/**
	 * Returns billing details for a member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_member_billing( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = (int) $request->get_param( 'user_id' );

		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return $this->error_response( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'gym-core' ), 503 );
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );
		$billing_data  = array();

		foreach ( $subscriptions as $subscription ) {
			$status = $subscription->get_status();

			if ( ! in_array( $status, array( 'active', 'on-hold', 'pending-cancel' ), true ) ) {
				continue;
			}

			$next_payment = $subscription->get_date( 'next_payment' );

			$billing_data[] = array(
				'subscription_id' => $subscription->get_id(),
				'plan_name'       => $this->get_subscription_plan_name( $subscription ),
				'status'          => $status,
				'payment_method'  => $subscription->get_payment_method_title(),
				'recurring_total' => $subscription->get_total(),
				'currency'        => $subscription->get_currency(),
				'next_payment'    => $next_payment ? gmdate( 'Y-m-d', strtotime( $next_payment ) ) : null,
				'start_date'      => $subscription->get_date( 'start' ) ? gmdate( 'Y-m-d', strtotime( $subscription->get_date( 'start' ) ) ) : null,
			);
		}

		// Recent payment history (last 10 completed orders).
		$recent_payments = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => 'completed',
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$payments = array();
		foreach ( $recent_payments as $order ) {
			$payments[] = array(
				'order_id' => $order->get_id(),
				'date'     => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d' ) : null,
				'total'    => $order->get_total(),
				'currency' => $order->get_currency(),
				'method'   => $order->get_payment_method_title(),
			);
		}

		return $this->success_response(
			array(
				'subscriptions'  => $billing_data,
				'recent_payments' => $payments,
			)
		);
	}

	/**
	 * Returns subscription status — tiered based on caller capability.
	 *
	 * Coaches (gym_view_attendance) see status only. Finance/admin
	 * (manage_woocommerce) see full amounts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_subscription_status( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id         = (int) $request->get_param( 'user_id' );
		$include_amounts = current_user_can( 'manage_woocommerce' );

		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return $this->error_response( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'gym-core' ), 503 );
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );
		$data          = array();

		foreach ( $subscriptions as $subscription ) {
			$status = $subscription->get_status();

			if ( ! in_array( $status, array( 'active', 'on-hold', 'pending-cancel', 'cancelled' ), true ) ) {
				continue;
			}

			$entry = array(
				'subscription_id' => $subscription->get_id(),
				'plan_name'       => $this->get_subscription_plan_name( $subscription ),
				'status'          => $status,
				'start_date'      => $subscription->get_date( 'start' ) ? gmdate( 'Y-m-d', strtotime( $subscription->get_date( 'start' ) ) ) : null,
			);

			if ( $include_amounts ) {
				$entry['recurring_total'] = $subscription->get_total();
				$entry['currency']        = $subscription->get_currency();
				$entry['payment_method']  = $subscription->get_payment_method_title();
				$next_payment             = $subscription->get_date( 'next_payment' );
				$entry['next_payment']    = $next_payment ? gmdate( 'Y-m-d', strtotime( $next_payment ) ) : null;
			}

			$data[] = $entry;
		}

		return $this->success_response( $data );
	}

	/**
	 * Returns churn metrics for a given period.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_churn_metrics( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return $this->error_response( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'gym-core' ), 503 );
		}

		$days  = (int) $request->get_param( 'days' );
		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Cancelled subscriptions in period.
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wc_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$cancelled_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table}
				WHERE type = 'shop_subscription'
				AND status = 'wc-cancelled'
				AND date_updated_gmt >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since . ' 00:00:00'
			)
		);

		// Active subscriptions count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$active_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$orders_table}
			WHERE type = 'shop_subscription'
			AND status IN ('wc-active', 'wc-pending-cancel')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// New subscriptions in period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table}
				WHERE type = 'shop_subscription'
				AND status IN ('wc-active', 'wc-pending-cancel')
				AND date_created_gmt >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since . ' 00:00:00'
			)
		);

		$total_start    = $active_count + $cancelled_count - $new_count;
		$retention_rate = $total_start > 0
			? round( ( $total_start - $cancelled_count ) / $total_start * 100, 1 )
			: 100.0;

		return $this->success_response(
			array(
				'period_days'    => $days,
				'cancelled'      => $cancelled_count,
				'new_signups'    => $new_count,
				'active_total'   => $active_count,
				'retention_rate' => $retention_rate,
			)
		);
	}

	/**
	 * Issues a refund for an order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function issue_refund( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$order_id = (int) $request->get_param( 'order_id' );
		$amount   = $request->get_param( 'amount' );
		$reason   = (string) $request->get_param( 'reason' );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return $this->error_response( 'order_not_found', __( 'Order not found.', 'gym-core' ), 404 );
		}

		$refund_amount = null !== $amount ? (float) $amount : (float) $order->get_total();

		$refund = wc_create_refund(
			array(
				'order_id'   => $order_id,
				'amount'     => $refund_amount,
				'reason'     => $reason,
				'refund_payment' => true,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return $this->error_response( 'refund_failed', $refund->get_error_message(), 400 );
		}

		return $this->success_response(
			array(
				'refund_id' => $refund->get_id(),
				'amount'    => $refund_amount,
				'order_id'  => $order_id,
			),
			null,
			201
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Derives a human-readable plan name from a subscription.
	 *
	 * @param \WC_Subscription $subscription WC Subscription object.
	 * @return string
	 */
	private function get_subscription_plan_name( $subscription ): string {
		$items = $subscription->get_items();

		if ( ! empty( $items ) ) {
			$first = reset( $items );
			return $first->get_name();
		}

		return __( 'Membership', 'gym-core' );
	}
}
