<?php
declare(strict_types=1);
/**
 * Audit log admin page for action approval history.
 *
 * @package HMA_AI_Chat
 * @since   0.3.0
 */

namespace HMA_AI_Chat\Admin;

/**
 * Renders the audit log page showing all approval/rejection history.
 *
 * @since 0.3.0
 */
class AuditLogPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'hma-ai-chat-audit-log';

	/**
	 * Render the audit log page.
	 *
	 * @since 0.3.0
	 * @internal
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'hma-ai-chat' ) );
		}

		$store = new \HMA_AI_Chat\Data\PendingActionStore();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = isset( $_GET['action_status'] ) ? sanitize_text_field( wp_unslash( $_GET['action_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_agent = isset( $_GET['agent'] ) ? sanitize_text_field( wp_unslash( $_GET['agent'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$result = $store->get_all_actions(
			array(
				'status'   => $current_status,
				'agent'    => $current_agent,
				'per_page' => 20,
				'page'     => $current_page,
			)
		);

		$items       = $result['items'];
		$total       = $result['total'];
		$total_pages = $result['total_pages'];

		$statuses = array(
			''                      => __( 'All Statuses', 'hma-ai-chat' ),
			'pending'               => __( 'Pending', 'hma-ai-chat' ),
			'approved'              => __( 'Approved', 'hma-ai-chat' ),
			'approved_with_changes' => __( 'Approved with Changes', 'hma-ai-chat' ),
			'completed'             => __( 'Completed', 'hma-ai-chat' ),
			'rejected'              => __( 'Rejected', 'hma-ai-chat' ),
		);

		$agents = array(
			''         => __( 'All Agents', 'hma-ai-chat' ),
			'sales'    => __( 'Sales', 'hma-ai-chat' ),
			'coaching' => __( 'Coaching', 'hma-ai-chat' ),
			'finance'  => __( 'Finance', 'hma-ai-chat' ),
			'admin'    => __( 'Admin', 'hma-ai-chat' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gandalf Audit Log', 'hma-ai-chat' ); ?></h1>

			<form method="get" class="hma-audit-log-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />

				<select name="action_status">
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select name="agent">
					<?php foreach ( $agents as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_agent, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php submit_button( __( 'Filter', 'hma-ai-chat' ), 'secondary', 'filter', false ); ?>
			</form>

			<p class="displaying-num">
				<?php
				printf(
					/* translators: %s: total items count */
					esc_html( _n( '%s item', '%s items', $total, 'hma-ai-chat' ) ),
					number_format_i18n( $total )
				);
				?>
			</p>

			<table class="widefat fixed striped hma-audit-log-table">
				<thead>
					<tr>
						<th class="column-id"><?php esc_html_e( 'ID', 'hma-ai-chat' ); ?></th>
						<th class="column-agent"><?php esc_html_e( 'Agent', 'hma-ai-chat' ); ?></th>
						<th class="column-action"><?php esc_html_e( 'Action', 'hma-ai-chat' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'hma-ai-chat' ); ?></th>
						<th class="column-detail"><?php esc_html_e( 'Detail', 'hma-ai-chat' ); ?></th>
						<th class="column-reviewer"><?php esc_html_e( 'Reviewer', 'hma-ai-chat' ); ?></th>
						<th class="column-created"><?php esc_html_e( 'Created', 'hma-ai-chat' ); ?></th>
						<th class="column-reviewed"><?php esc_html_e( 'Reviewed', 'hma-ai-chat' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'No actions found.', 'hma-ai-chat' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td class="column-id"><?php echo esc_html( $item['id'] ); ?></td>
								<td class="column-agent">
									<span class="hma-audit-agent-badge"><?php echo esc_html( ucfirst( $item['agent'] ) ); ?></span>
								</td>
								<td class="column-action"><?php echo esc_html( $item['action_type'] ); ?></td>
								<td class="column-status">
									<?php echo wp_kses_post( $this->status_badge( $item['status'] ) ); ?>
								</td>
								<td class="column-detail">
									<?php echo esc_html( $this->get_detail_summary( $item ) ); ?>
								</td>
								<td class="column-reviewer">
									<?php
									if ( ! empty( $item['approved_by'] ) ) {
										$user = get_userdata( (int) $item['approved_by'] );
										echo esc_html( $user ? $user->display_name : __( 'Unknown', 'hma-ai-chat' ) );
									} else {
										echo '<span class="hma-audit-na">&mdash;</span>';
									}
									?>
								</td>
								<td class="column-created"><?php echo esc_html( $this->format_time( $item['created_at'] ) ); ?></td>
								<td class="column-reviewed">
									<?php
									if ( ! empty( $item['approved_at'] ) ) {
										echo esc_html( $this->format_time( $item['approved_at'] ) );
									} else {
										echo '<span class="hma-audit-na">&mdash;</span>';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'current'   => $current_page,
									'total'     => $total_pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.hma-audit-log-filters {
				display: flex;
				gap: 8px;
				align-items: center;
				margin: 16px 0;
			}
			.hma-audit-log-table .column-id { width: 50px; }
			.hma-audit-log-table .column-agent { width: 90px; }
			.hma-audit-log-table .column-status { width: 130px; }
			.hma-audit-log-table .column-reviewer { width: 120px; }
			.hma-audit-log-table .column-created,
			.hma-audit-log-table .column-reviewed { width: 140px; }
			.hma-audit-agent-badge {
				display: inline-block;
				padding: 2px 8px;
				background: #e8f0fe;
				color: #1a73e8;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 500;
			}
			.hma-audit-status-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 500;
			}
			.hma-audit-status-pending { background: #fff3cd; color: #856404; }
			.hma-audit-status-approved { background: #d4edda; color: #155724; }
			.hma-audit-status-approved_with_changes { background: #cce5ff; color: #004085; }
			.hma-audit-status-completed { background: #d1ecf1; color: #0c5460; }
			.hma-audit-status-rejected { background: #f8d7da; color: #721c24; }
			.hma-audit-na { color: #999; }

			@media (max-width: 782px) {
				.hma-audit-log-filters {
					flex-wrap: wrap;
				}
				.hma-audit-log-table .column-detail,
				.hma-audit-log-table .column-reviewed {
					display: none;
				}
			}
		</style>
		<?php
	}

	/**
	 * Render a coloured status badge.
	 *
	 * @param string $status Action status.
	 * @return string HTML badge.
	 */
	private function status_badge( $status ) {
		$labels = array(
			'pending'               => __( 'Pending', 'hma-ai-chat' ),
			'approved'              => __( 'Approved', 'hma-ai-chat' ),
			'approved_with_changes' => __( 'Approved + Edits', 'hma-ai-chat' ),
			'completed'             => __( 'Completed', 'hma-ai-chat' ),
			'rejected'              => __( 'Rejected', 'hma-ai-chat' ),
		);

		$label = $labels[ $status ] ?? ucfirst( $status );

		return sprintf(
			'<span class="hma-audit-status-badge hma-audit-status-%s">%s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Get a one-line detail summary for an action.
	 *
	 * @param array $item Action row.
	 * @return string
	 */
	private function get_detail_summary( $item ) {
		$data = $item['action_data'];
		if ( ! is_array( $data ) ) {
			return '';
		}

		// Show rejection reason if rejected.
		if ( 'rejected' === $item['status'] && ! empty( $data['rejection_reason'] ) ) {
			return sprintf(
				/* translators: %s: rejection reason */
				__( 'Rejected: %s', 'hma-ai-chat' ),
				wp_trim_words( $data['rejection_reason'], 12 )
			);
		}

		// Show staff changes if approved with changes.
		if ( ! empty( $data['staff_changes'] ) ) {
			return sprintf(
				/* translators: %s: staff instructions */
				__( 'Changes: %s', 'hma-ai-chat' ),
				wp_trim_words( $data['staff_changes'], 12 )
			);
		}

		// Fall back to description.
		if ( ! empty( $data['description'] ) ) {
			return wp_trim_words( $data['description'], 12 );
		}

		return '';
	}

	/**
	 * Format a datetime string for display.
	 *
	 * @param string $datetime MySQL datetime string.
	 * @return string
	 */
	private function format_time( $datetime ) {
		$timestamp = strtotime( $datetime );
		if ( ! $timestamp ) {
			return $datetime;
		}

		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}
