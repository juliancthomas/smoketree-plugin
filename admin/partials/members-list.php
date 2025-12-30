<?php
/**
 * Members list template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$members = $data['members'] ?? array();
$membership_types = $data['membership_types'] ?? array();
$filters = $data['filters'] ?? array();
$active_count = $data['active_count'] ?? 0;
$admin_nonce = wp_create_nonce( 'stsrc_admin_nonce' );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Members', 'smoketree-plugin' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members&action=edit' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add New', 'smoketree-plugin' ); ?>
	</a>
	<hr class="wp-header-end">

	<!-- Filters -->
	<div class="stsrc-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="stsrc-members">
			
			<div class="stsrc-filter-row">
				<div class="stsrc-filter-group">
					<label for="search"><?php echo esc_html__( 'Search', 'smoketree-plugin' ); ?>:</label>
					<input type="text" name="search" id="search" value="<?php echo esc_attr( $filters['search'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Name or email...', 'smoketree-plugin' ); ?>">
				</div>

				<div class="stsrc-filter-group">
					<label for="membership_type_id"><?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?>:</label>
					<select name="membership_type_id" id="membership_type_id">
						<option value=""><?php echo esc_html__( 'All Types', 'smoketree-plugin' ); ?></option>
						<?php foreach ( $membership_types as $type ) : ?>
							<option value="<?php echo esc_attr( $type['membership_type_id'] ); ?>" <?php selected( $filters['membership_type_id'] ?? '', $type['membership_type_id'] ); ?>>
								<?php echo esc_html( $type['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="status"><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?>:</label>
					<select name="status" id="status">
						<option value=""><?php echo esc_html__( 'All Statuses', 'smoketree-plugin' ); ?></option>
						<option value="active" <?php selected( $filters['status'] ?? '', 'active' ); ?>><?php echo esc_html__( 'Active', 'smoketree-plugin' ); ?></option>
						<option value="pending" <?php selected( $filters['status'] ?? '', 'pending' ); ?>><?php echo esc_html__( 'Pending', 'smoketree-plugin' ); ?></option>
						<option value="cancelled" <?php selected( $filters['status'] ?? '', 'cancelled' ); ?>><?php echo esc_html__( 'Cancelled', 'smoketree-plugin' ); ?></option>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="payment_type"><?php echo esc_html__( 'Payment Type', 'smoketree-plugin' ); ?>:</label>
					<select name="payment_type" id="payment_type">
						<option value=""><?php echo esc_html__( 'All Types', 'smoketree-plugin' ); ?></option>
						<option value="card" <?php selected( $filters['payment_type'] ?? '', 'card' ); ?>><?php echo esc_html__( 'Card', 'smoketree-plugin' ); ?></option>
						<option value="bank_account" <?php selected( $filters['payment_type'] ?? '', 'bank_account' ); ?>><?php echo esc_html__( 'Bank Account', 'smoketree-plugin' ); ?></option>
						<option value="zelle" <?php selected( $filters['payment_type'] ?? '', 'zelle' ); ?>><?php echo esc_html__( 'Zelle', 'smoketree-plugin' ); ?></option>
						<option value="check" <?php selected( $filters['payment_type'] ?? '', 'check' ); ?>><?php echo esc_html__( 'Check', 'smoketree-plugin' ); ?></option>
						<option value="pay_later" <?php selected( $filters['payment_type'] ?? '', 'pay_later' ); ?>><?php echo esc_html__( 'Pay Later', 'smoketree-plugin' ); ?></option>
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
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members' ) ); ?>" class="button">
						<?php echo esc_html__( 'Clear', 'smoketree-plugin' ); ?>
					</a>
				</div>
			</div>
		</form>
	</div>

	<!-- Stats -->
	<div class="stsrc-stats">
		<p>
			<strong><?php echo esc_html__( 'Total Active Members:', 'smoketree-plugin' ); ?></strong>
			<?php echo esc_html( number_format( $active_count ) ); ?>
			| <strong><?php echo esc_html__( 'Filtered Results:', 'smoketree-plugin' ); ?></strong>
			<?php echo esc_html( number_format( count( $members ) ) ); ?>
		</p>
	</div>

	<!-- Export Button -->
	<div class="stsrc-actions">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" style="display: inline;">
			<input type="hidden" name="action" value="stsrc_export_members">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( $admin_nonce ); ?>">
			<?php foreach ( $filters as $key => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>
			<input type="submit" class="button" value="<?php echo esc_attr__( 'Export to CSV', 'smoketree-plugin' ); ?>">
		</form>
	</div>

	<!-- Bulk Status Update -->
	<form method="post"
		action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		id="stsrc-members-form"
		class="stsrc-ajax-form stsrc-members-bulk-form"
		data-reload="true"
		data-confirm="<?php echo esc_attr__( 'Apply the %status% status to %count% selected member(s)?', 'smoketree-plugin' ); ?>">
		<input type="hidden" name="action" value="stsrc_bulk_update_members">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $admin_nonce ); ?>">
		<input type="hidden" name="target" value="selected">

		<div class="stsrc-bulk-status-box">
			<h2><?php echo esc_html__( 'Bulk Status Update', 'smoketree-plugin' ); ?></h2>
			<p class="description">
				<?php echo esc_html__( 'Select members in the table below, choose a new status, and optionally clear auto-renewal preferences or guest pass balances.', 'smoketree-plugin' ); ?>
			</p>

			<div class="stsrc-bulk-status-fields">
				<label for="stsrc-bulk-status-select">
					<span class="stsrc-field-label"><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></span>
					<select name="new_status" id="stsrc-bulk-status-select" required>
						<option value=""><?php echo esc_html__( 'Select status…', 'smoketree-plugin' ); ?></option>
						<option value="active"><?php echo esc_html__( 'Active', 'smoketree-plugin' ); ?></option>
						<option value="pending"><?php echo esc_html__( 'Pending', 'smoketree-plugin' ); ?></option>
						<option value="cancelled"><?php echo esc_html__( 'Cancelled', 'smoketree-plugin' ); ?></option>
						<option value="inactive"><?php echo esc_html__( 'Inactive', 'smoketree-plugin' ); ?></option>
					</select>
				</label>

				<label class="stsrc-inline-checkbox">
					<input type="checkbox" name="clear_auto_renewal" value="1">
					<span><?php echo esc_html__( 'Clear auto-renewal opt-in', 'smoketree-plugin' ); ?></span>
				</label>

				<label class="stsrc-inline-checkbox">
					<input type="checkbox" name="reset_guest_pass_balance" value="1">
					<span><?php echo esc_html__( 'Reset guest pass balance to 0', 'smoketree-plugin' ); ?></span>
				</label>
			</div>

			<div class="stsrc-bulk-status-actions">
				<button type="submit" class="button button-primary">
					<?php echo esc_html__( 'Apply to Selected Members', 'smoketree-plugin' ); ?>
				</button>
			</div>
		</div>

		<div class="stsrc-table-wrapper">
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all">
						</td>
						<th class="manage-column"><?php echo esc_html__( 'Name', 'smoketree-plugin' ); ?></th>
						<th class="manage-column"><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
						<th class="manage-column"><?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?></th>
						<th class="manage-column"><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></th>
						<th class="manage-column"><?php echo esc_html__( 'Payment Type', 'smoketree-plugin' ); ?></th>
						<th class="manage-column"><?php echo esc_html__( 'Created', 'smoketree-plugin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $members ) ) : ?>
						<?php
						// Build membership type lookup
						$type_lookup = array();
						foreach ( $membership_types as $type ) {
							$type_lookup[ $type['membership_type_id'] ] = $type['name'];
						}
						?>
						<?php foreach ( $members as $member ) : ?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="member_ids[]" value="<?php echo esc_attr( $member['member_id'] ); ?>">
								</th>
								<td class="column-name">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members&action=edit&member_id=' . $member['member_id'] ) ); ?>">
											<?php echo esc_html( $member['first_name'] . ' ' . $member['last_name'] ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members&action=edit&member_id=' . $member['member_id'] ) ); ?>">
												<?php echo esc_html__( 'Edit', 'smoketree-plugin' ); ?>
											</a> |
										</span>
										<span class="view">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members&action=view&member_id=' . $member['member_id'] ) ); ?>">
												<?php echo esc_html__( 'View', 'smoketree-plugin' ); ?>
											</a>
										</span>
									</div>
								</td>
								<td><?php echo esc_html( $member['email'] ); ?></td>
								<td><?php echo esc_html( $type_lookup[ $member['membership_type_id'] ] ?? __( 'Unknown', 'smoketree-plugin' ) ); ?></td>
								<td>
									<span class="stsrc-status stsrc-status-<?php echo esc_attr( $member['status'] ); ?>">
										<?php echo esc_html( ucfirst( $member['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $member['payment_type'] ) ) ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member['created_at'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="7"><?php echo esc_html__( 'No members found.', 'smoketree-plugin' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</form>

	<!-- Season Reset -->
	<div class="stsrc-season-reset-box">
		<h2><?php echo esc_html__( 'Season Reset', 'smoketree-plugin' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Use this tool at the start of a new season to move every active member to the inactive status. You can optionally clear auto-renewal preferences and reset guest pass balances.', 'smoketree-plugin' ); ?>
		</p>

		<form method="post"
			action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			class="stsrc-ajax-form stsrc-season-reset-form"
			data-reload="true"
			data-confirm="<?php echo esc_attr__( 'This will mark all active members as inactive. Continue?', 'smoketree-plugin' ); ?>">
			<input type="hidden" name="action" value="stsrc_bulk_update_members">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( $admin_nonce ); ?>">
			<input type="hidden" name="target" value="season_reset">
			<input type="hidden" name="from_status" value="active">
			<input type="hidden" name="new_status" value="inactive">

			<label class="stsrc-inline-checkbox">
				<input type="checkbox" name="clear_auto_renewal" value="1" checked>
				<span><?php echo esc_html__( 'Clear auto-renewal opt-in for all members', 'smoketree-plugin' ); ?></span>
			</label>

			<label class="stsrc-inline-checkbox">
				<input type="checkbox" name="reset_guest_pass_balance" value="1">
				<span><?php echo esc_html__( 'Reset guest pass balances to 0', 'smoketree-plugin' ); ?></span>
			</label>

			<div class="stsrc-bulk-status-actions">
				<button type="submit" class="button button-secondary">
					<?php echo esc_html__( 'Start Season Reset', 'smoketree-plugin' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

