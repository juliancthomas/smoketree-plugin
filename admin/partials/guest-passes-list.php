<?php
/**
 * Guest passes list template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logs = $data['logs'] ?? array();
$analytics = $data['analytics'] ?? array();
$filters = $data['filters'] ?? array();
$members = $data['members'] ?? array();
?>

<div class="wrap">
	<h1><?php echo esc_html__( 'Guest Passes', 'smoketree-plugin' ); ?></h1>

	<!-- Analytics Summary -->
	<div class="stsrc-analytics">
		<div class="stsrc-analytics-row">
			<div class="stsrc-stat-box">
				<h3><?php echo esc_html__( 'Total Purchased', 'smoketree-plugin' ); ?></h3>
				<p class="stsrc-stat-number"><?php echo esc_html( number_format( $analytics['total_purchased'] ?? 0 ) ); ?></p>
			</div>
			<div class="stsrc-stat-box">
				<h3><?php echo esc_html__( 'Total Used', 'smoketree-plugin' ); ?></h3>
				<p class="stsrc-stat-number"><?php echo esc_html( number_format( $analytics['total_used'] ?? 0 ) ); ?></p>
			</div>
			<div class="stsrc-stat-box">
				<h3><?php echo esc_html__( 'Active Balance', 'smoketree-plugin' ); ?></h3>
				<p class="stsrc-stat-number"><?php echo esc_html( number_format( $analytics['total_balance'] ?? 0 ) ); ?></p>
			</div>
			<div class="stsrc-stat-box">
				<h3><?php echo esc_html__( 'Total Revenue', 'smoketree-plugin' ); ?></h3>
				<p class="stsrc-stat-number">$<?php echo esc_html( number_format( $analytics['total_revenue'] ?? 0, 2 ) ); ?></p>
			</div>
		</div>
	</div>

	<!-- Filters -->
	<div class="stsrc-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="stsrc-guest-passes">
			
			<div class="stsrc-filter-row">
				<div class="stsrc-filter-group">
					<label for="search"><?php echo esc_html__( 'Search', 'smoketree-plugin' ); ?>:</label>
					<input type="text" name="search" id="search" value="<?php echo esc_attr( $filters['search'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Member name or email...', 'smoketree-plugin' ); ?>">
				</div>

				<div class="stsrc-filter-group">
					<label for="member_id"><?php echo esc_html__( 'Member', 'smoketree-plugin' ); ?>:</label>
					<select name="member_id" id="member_id">
						<option value=""><?php echo esc_html__( 'All Members', 'smoketree-plugin' ); ?></option>
						<?php foreach ( $members as $member ) : ?>
							<option value="<?php echo esc_attr( $member['member_id'] ); ?>" <?php selected( $filters['member_id'] ?? '', $member['member_id'] ); ?>>
								<?php echo esc_html( $member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['email'] . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="payment_status"><?php echo esc_html__( 'Payment Status', 'smoketree-plugin' ); ?>:</label>
					<select name="payment_status" id="payment_status">
						<option value=""><?php echo esc_html__( 'All Statuses', 'smoketree-plugin' ); ?></option>
						<option value="paid" <?php selected( $filters['payment_status'] ?? '', 'paid' ); ?>><?php echo esc_html__( 'Paid', 'smoketree-plugin' ); ?></option>
						<option value="pending" <?php selected( $filters['payment_status'] ?? '', 'pending' ); ?>><?php echo esc_html__( 'Pending', 'smoketree-plugin' ); ?></option>
						<option value="failed" <?php selected( $filters['payment_status'] ?? '', 'failed' ); ?>><?php echo esc_html__( 'Failed', 'smoketree-plugin' ); ?></option>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="date_from"><?php echo esc_html__( 'Date From', 'smoketree-plugin' ); ?>:</label>
					<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>">
				</div>

				<div class="stsrc-filter-group">
					<label for="date_to"><?php echo esc_html__( 'Date To', 'smoketree-plugin' ); ?>:</label>
					<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>">
				</div>

				<div class="stsrc-filter-group">
					<input type="submit" class="button" value="<?php echo esc_attr__( 'Filter', 'smoketree-plugin' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-guest-passes' ) ); ?>" class="button">
						<?php echo esc_html__( 'Clear', 'smoketree-plugin' ); ?>
					</a>
				</div>
			</div>
		</form>
	</div>

	<!-- Guest Pass Logs Table -->
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th class="manage-column"><?php echo esc_html__( 'Date', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Member', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Quantity', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Amount', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Type', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Payment Status', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Used At', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Notes', 'smoketree-plugin' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $logs ) ) : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) ); ?></td>
						<td>
							<?php if ( ! empty( $log['first_name'] ) ) : ?>
								<strong><?php echo esc_html( $log['first_name'] . ' ' . $log['last_name'] ); ?></strong><br>
								<small><?php echo esc_html( $log['email'] ?? '' ); ?></small>
							<?php else : ?>
								<span class="description"><?php echo esc_html__( 'Member ID:', 'smoketree-plugin' ); ?> <?php echo esc_html( $log['member_id'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log['quantity'] ); ?></td>
						<td>
							<?php if ( ! empty( $log['amount'] ) && $log['amount'] > 0 ) : ?>
								$<?php echo esc_html( number_format( floatval( $log['amount'] ), 2 ) ); ?>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $log['admin_adjusted'] ) ) : ?>
								<span class="stsrc-badge stsrc-badge-admin"><?php echo esc_html__( 'Admin Adjustment', 'smoketree-plugin' ); ?></span>
							<?php elseif ( ! empty( $log['used_at'] ) ) : ?>
								<span class="stsrc-badge stsrc-badge-used"><?php echo esc_html__( 'Usage', 'smoketree-plugin' ); ?></span>
							<?php else : ?>
								<span class="stsrc-badge stsrc-badge-purchase"><?php echo esc_html__( 'Purchase', 'smoketree-plugin' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<span class="stsrc-status stsrc-status-<?php echo esc_attr( $log['payment_status'] ); ?>">
								<?php echo esc_html( ucfirst( $log['payment_status'] ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( ! empty( $log['used_at'] ) ) : ?>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['used_at'] ) ) ); ?>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log['notes'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="8"><?php echo esc_html__( 'No guest pass logs found.', 'smoketree-plugin' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Balance Adjustment Form -->
	<div class="stsrc-balance-adjustment" style="margin-top: 30px;">
		<h2><?php echo esc_html__( 'Adjust Guest Pass Balance', 'smoketree-plugin' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="stsrc-adjust-balance-form">
			<input type="hidden" name="action" value="stsrc_admin_adjust_guest_passes">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>">

			<table class="form-table">
				<tr>
					<th><label for="adjust_member_id"><?php echo esc_html__( 'Member', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
					<td>
						<select name="member_id" id="adjust_member_id" required>
							<option value=""><?php echo esc_html__( 'Select Member', 'smoketree-plugin' ); ?></option>
							<?php foreach ( $members as $member ) : ?>
								<option value="<?php echo esc_attr( $member['member_id'] ); ?>">
									<?php echo esc_html( $member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['email'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="adjustment"><?php echo esc_html__( 'Adjustment', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
					<td>
						<input type="number" name="adjustment" id="adjustment" required class="small-text" step="1">
						<p class="description"><?php echo esc_html__( 'Enter positive number to add passes, negative number to remove passes.', 'smoketree-plugin' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="adjustment_notes"><?php echo esc_html__( 'Notes', 'smoketree-plugin' ); ?></label></th>
					<td>
						<textarea name="notes" id="adjustment_notes" rows="3" class="large-text"></textarea>
						<p class="description"><?php echo esc_html__( 'Optional notes about this adjustment.', 'smoketree-plugin' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Adjust Balance', 'smoketree-plugin' ); ?>">
			</p>
		</form>
	</div>
</div>

