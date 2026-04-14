<?php

/**
 * Members list template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if (! defined('ABSPATH')) {
	exit;
}

$members = $data['members'] ?? array();
$membership_types = $data['membership_types'] ?? array();
$filters = $data['filters'] ?? array();
$active_count = $data['active_count'] ?? 0;
$guest_pass_balances = $data['guest_pass_balances'] ?? array();
$admin_nonce = wp_create_nonce('stsrc_admin_nonce');
$current_orderby = $filters['orderby'] ?? 'created_at';
$current_order   = strtoupper($filters['order'] ?? 'DESC');
$next_balance_order = ('balance' === $current_orderby && 'ASC' === $current_order) ? 'DESC' : 'ASC';

$balance_sort_url = add_query_arg(
	array(
		'page'               => 'stsrc-members',
		'search'             => $filters['search'] ?? '',
		'membership_type_id' => $filters['membership_type_id'] ?? '',
		'status'             => $filters['status'] ?? '',
		'payment_type'       => $filters['payment_type'] ?? '',
		'date_from'          => $filters['date_from'] ?? '',
		'date_to'            => $filters['date_to'] ?? '',
		'balance_status'     => $filters['balance_status'] ?? '',
		'auto_renewal'       => $filters['auto_renewal'] ?? '',
		'demo_filter'        => $filters['demo_filter'] ?? 'all',
		'show_deleted'       => $filters['show_deleted'] ?? '',
		'signup_month'       => $filters['signup_month'] ?? '',
		'orderby'            => 'balance',
		'order'              => $next_balance_order,
	),
	admin_url('admin.php')
);

// Build the last 12 months for the signup-month dropdown.
$signup_month_options = array();
for ($i = 0; $i < 12; $i++) {
	$ts    = mktime(0, 0, 0, (int) gmdate('n') - $i, 1);
	$signup_month_options[] = array(
		'value' => gmdate('Y-m', $ts),
		'label' => gmdate('M Y', $ts),
	);
}
$active_signup_month = $filters['signup_month'] ?? '';
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__('Members', 'smoketree-plugin'); ?></h1>
	<a href="<?php echo esc_url(admin_url('admin.php?page=stsrc-members&action=edit')); ?>" class="page-title-action">
		<?php echo esc_html__('Add New', 'smoketree-plugin'); ?>
	</a>
	<hr class="wp-header-end">

	<!-- Filters -->
	<div class="stsrc-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="stsrc-members">

			<div class="stsrc-filter-row">
				<div class="stsrc-filter-group">
					<label for="search"><?php echo esc_html__('Search', 'smoketree-plugin'); ?>:</label>
					<input type="text" name="search" id="search" value="<?php echo esc_attr($filters['search'] ?? ''); ?>" placeholder="<?php echo esc_attr__('Name or email...', 'smoketree-plugin'); ?>">
				</div>

				<div class="stsrc-filter-group">
					<label for="membership_type_id"><?php echo esc_html__('Membership Type', 'smoketree-plugin'); ?>:</label>
					<select name="membership_type_id" id="membership_type_id">
						<option value=""><?php echo esc_html__('All Types', 'smoketree-plugin'); ?></option>
						<?php foreach ($membership_types as $type) : ?>
							<option value="<?php echo esc_attr($type['membership_type_id']); ?>" <?php selected($filters['membership_type_id'] ?? '', $type['membership_type_id']); ?>>
								<?php echo esc_html($type['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="status"><?php echo esc_html__('Status', 'smoketree-plugin'); ?>:</label>
					<select name="status" id="status">
						<option value=""><?php echo esc_html__('All Statuses', 'smoketree-plugin'); ?></option>
						<option value="active" <?php selected($filters['status'] ?? '', 'active'); ?>><?php echo esc_html__('Active', 'smoketree-plugin'); ?></option>
						<option value="inactive" <?php selected($filters['status'] ?? '', 'inactive'); ?>><?php echo esc_html__('Inactive', 'smoketree-plugin'); ?></option>
						<option value="pending" <?php selected($filters['status'] ?? '', 'pending'); ?>><?php echo esc_html__('Pending', 'smoketree-plugin'); ?></option>
						<option value="cancelled" <?php selected($filters['status'] ?? '', 'cancelled'); ?>><?php echo esc_html__('Cancelled', 'smoketree-plugin'); ?></option>
						<option value="deleted" <?php selected($filters['status'] ?? '', 'deleted'); ?>><?php echo esc_html__('Deleted', 'smoketree-plugin'); ?></option>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="show_deleted"><?php echo esc_html__('Deleted Records', 'smoketree-plugin'); ?>:</label>
					<label class="stsrc-inline-checkbox" style="margin: 0;">
						<input type="checkbox" name="show_deleted" id="show_deleted" value="1" <?php checked($filters['show_deleted'] ?? '0', '1'); ?>>
						<span><?php echo esc_html__('Show Deleted', 'smoketree-plugin'); ?></span>
					</label>
				</div>

				<div class="stsrc-filter-group">
					<label for="payment_type"><?php echo esc_html__('Payment Type', 'smoketree-plugin'); ?>:</label>
					<select name="payment_type" id="payment_type">
						<option value=""><?php echo esc_html__('All Types', 'smoketree-plugin'); ?></option>
						<option value="card" <?php selected($filters['payment_type'] ?? '', 'card'); ?>><?php echo esc_html__('Card', 'smoketree-plugin'); ?></option>
						<option value="bank_account" <?php selected($filters['payment_type'] ?? '', 'bank_account'); ?>><?php echo esc_html__('Bank Account', 'smoketree-plugin'); ?></option>
						<option value="zelle" <?php selected($filters['payment_type'] ?? '', 'zelle'); ?>><?php echo esc_html__('Zelle', 'smoketree-plugin'); ?></option>
						<option value="check" <?php selected($filters['payment_type'] ?? '', 'check'); ?>><?php echo esc_html__('Check', 'smoketree-plugin'); ?></option>
						<option value="pay_later" <?php selected($filters['payment_type'] ?? '', 'pay_later'); ?>><?php echo esc_html__('Pay Later', 'smoketree-plugin'); ?></option>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="balance_status"><?php echo esc_html__('Balance Status', 'smoketree-plugin'); ?>:</label>
					<select name="balance_status" id="balance_status">
						<option value=""><?php echo esc_html__('All Balances', 'smoketree-plugin'); ?></option>
						<option value="paid_in_full" <?php selected($filters['balance_status'] ?? '', 'paid_in_full'); ?>><?php echo esc_html__('Paid in Full', 'smoketree-plugin'); ?></option>
						<option value="outstanding" <?php selected($filters['balance_status'] ?? '', 'outstanding'); ?>><?php echo esc_html__('Outstanding', 'smoketree-plugin'); ?></option>
						<option value="overpaid" <?php selected($filters['balance_status'] ?? '', 'overpaid'); ?>><?php echo esc_html__('Overpaid', 'smoketree-plugin'); ?></option>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="auto_renewal"><?php echo esc_html__('Auto-Renewal', 'smoketree-plugin'); ?>:</label>
					<select name="auto_renewal" id="auto_renewal">
						<option value=""><?php echo esc_html__('All', 'smoketree-plugin'); ?></option>
						<option value="1" <?php selected($filters['auto_renewal'] ?? '', '1'); ?>><?php echo esc_html__('Enabled', 'smoketree-plugin'); ?></option>
						<option value="0" <?php selected($filters['auto_renewal'] ?? '', '0'); ?>><?php echo esc_html__('Disabled', 'smoketree-plugin'); ?></option>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="demo_filter"><?php echo esc_html__('Demo Filter', 'smoketree-plugin'); ?>:</label>
					<select name="demo_filter" id="demo_filter">
						<option value="all" <?php selected($filters['demo_filter'] ?? 'all', 'all'); ?>><?php echo esc_html__('All Members', 'smoketree-plugin'); ?></option>
						<option value="real" <?php selected($filters['demo_filter'] ?? 'all', 'real'); ?>><?php echo esc_html__('Real Members Only', 'smoketree-plugin'); ?></option>
						<option value="demo" <?php selected($filters['demo_filter'] ?? 'all', 'demo'); ?>><?php echo esc_html__('Demo Members Only', 'smoketree-plugin'); ?></option>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<label for="date_from"><?php echo esc_html__('Date From', 'smoketree-plugin'); ?>:</label>
					<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($filters['date_from'] ?? ''); ?>">
				</div>

				<div class="stsrc-filter-group">
					<label for="date_to"><?php echo esc_html__('Date To', 'smoketree-plugin'); ?>:</label>
					<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($filters['date_to'] ?? ''); ?>">
				</div>

				<div class="stsrc-filter-group">
					<label for="signup_month"><?php echo esc_html__('Signup Month', 'smoketree-plugin'); ?>:</label>
					<select name="signup_month" id="signup_month">
						<option value=""><?php echo esc_html__('All Months', 'smoketree-plugin'); ?></option>
						<?php foreach ($signup_month_options as $opt) : ?>
							<option value="<?php echo esc_attr($opt['value']); ?>" <?php selected($active_signup_month, $opt['value']); ?>>
								<?php echo esc_html($opt['label']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="stsrc-filter-group">
					<input type="submit" class="button" value="<?php echo esc_attr__('Filter', 'smoketree-plugin'); ?>">
					<a href="<?php echo esc_url(admin_url('admin.php?page=stsrc-members')); ?>" class="button">
						<?php echo esc_html__('Clear', 'smoketree-plugin'); ?>
					</a>
				</div>
			</div>


		</form>
	</div>

	<!-- Stats & Toolbar -->
	<div class="stsrc-stats">
		<div class="stsrc-stats-toolbar">
			<div class="stsrc-stat-cards">
				<div class="stsrc-stat-card">
					<span class="stsrc-stat-value"><?php echo esc_html(number_format($active_count)); ?></span>
					<span class="stsrc-stat-label"><?php echo esc_html__('Active Members', 'smoketree-plugin'); ?></span>
				</div>
				<div class="stsrc-stat-card">
					<span class="stsrc-stat-value"><?php echo esc_html(number_format(count($members))); ?></span>
					<span class="stsrc-stat-label"><?php echo esc_html__('Filtered Results', 'smoketree-plugin'); ?></span>
				</div>
				<div class="stsrc-stat-card stsrc-stat-card--selected">
					<span class="stsrc-stat-value" id="stsrc-selected-count-display">0</span>
					<span class="stsrc-stat-label"><?php echo esc_html__('Selected', 'smoketree-plugin'); ?></span>
				</div>
			</div>
			<div class="stsrc-toolbar-actions">
				<form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
					<input type="hidden" name="action" value="stsrc_export_members">
					<input type="hidden" name="nonce" value="<?php echo esc_attr($admin_nonce); ?>">
					<?php foreach ($filters as $key => $value) : ?>
						<input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
					<?php endforeach; ?>
					<input type="submit" class="button" value="<?php echo esc_attr__('Export to CSV', 'smoketree-plugin'); ?>">
				</form>
			</div>
		</div>
	</div>

	<!-- Bulk Status Update -->
	<form method="post"
		action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
		id="stsrc-members-form"
		class="stsrc-ajax-form stsrc-members-bulk-form"
		data-reload="true"
		data-confirm="<?php echo esc_attr__('Apply the %status% status to %count% selected member(s)?', 'smoketree-plugin'); ?>">
		<input type="hidden" name="action" value="stsrc_bulk_update_members">
		<input type="hidden" name="nonce" value="<?php echo esc_attr($admin_nonce); ?>">
		<input type="hidden" name="target" value="selected">

		<div class="stsrc-table-wrapper">
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all">
						</td>
						<th class="manage-column"><?php echo esc_html__('Name', 'smoketree-plugin'); ?></th>
						<th class="manage-column"><?php echo esc_html__('Email', 'smoketree-plugin'); ?></th>
						<th class="manage-column"><?php echo esc_html__('Membership Type', 'smoketree-plugin'); ?></th>
						<th class="manage-column"><?php echo esc_html__('Status', 'smoketree-plugin'); ?></th>
						<th class="manage-column"><?php echo esc_html__('Payment Type', 'smoketree-plugin'); ?></th>
						<th class="manage-column stsrc-auto-renewal-column" title="<?php echo esc_attr__('Auto-Renewal', 'smoketree-plugin'); ?>"><?php echo esc_html__('AR', 'smoketree-plugin'); ?></th>
						<th class="manage-column stsrc-guest-pass-column" title="<?php echo esc_attr__('Guest Passes', 'smoketree-plugin'); ?>"><?php echo esc_html__('GP', 'smoketree-plugin'); ?></th>
						<th class="manage-column stsrc-balance-column">
							<a href="<?php echo esc_url($balance_sort_url); ?>" class="stsrc-sort-link">
								<?php echo esc_html__('Balance', 'smoketree-plugin'); ?>
								<?php if ('balance' === $current_orderby) : ?>
									<span class="stsrc-sort-indicator"><?php echo esc_html('ASC' === $current_order ? '▲' : '▼'); ?></span>
								<?php endif; ?>
							</a>
						</th>
						<th class="manage-column"><?php echo esc_html__('Created', 'smoketree-plugin'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (! empty($members)) : ?>
						<?php
						// Build membership type lookup
						$type_lookup = array();
						foreach ($membership_types as $type) {
							$type_lookup[$type['membership_type_id']] = $type['name'];
						}
						?>
						<?php foreach ($members as $member) :
							$mid = (int) $member['member_id'];
							$gp_balance = $guest_pass_balances[$mid] ?? 0;
						?>
							<tr id="member-row-<?php echo esc_attr($mid); ?>"
								data-member-id="<?php echo esc_attr($mid); ?>"
								data-status="<?php echo esc_attr($member['status']); ?>"
								data-membership-type-id="<?php echo esc_attr($member['membership_type_id']); ?>"
								data-payment-type="<?php echo esc_attr($member['payment_type']); ?>"
								data-auto-renewal="<?php echo esc_attr(! empty($member['auto_renewal_enabled']) ? '1' : '0'); ?>"
								data-guest-pass-balance="<?php echo esc_attr($gp_balance); ?>">
								<th scope="row" class="check-column">
									<input type="checkbox" name="member_ids[]" value="<?php echo esc_attr($mid); ?>">
								</th>
								<td class="column-name">
									<strong>
										<a href="<?php echo esc_url(admin_url('admin.php?page=stsrc-members&action=edit&member_id=' . $mid)); ?>">
											<?php echo esc_html($member['first_name'] . ' ' . $member['last_name']); ?>
										</a>
										<?php if (1 === (int) ($member['is_demo'] ?? 0)) : ?>
											<span class="stsrc-demo-badge"><?php echo esc_html__('Demo', 'smoketree-plugin'); ?></span>
										<?php endif; ?>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url(admin_url('admin.php?page=stsrc-members&action=edit&member_id=' . $mid)); ?>">
												<?php echo esc_html__('Edit', 'smoketree-plugin'); ?>
											</a> |
										</span>
										<span class="view">
											<a href="<?php echo esc_url(admin_url('admin.php?page=stsrc-members&action=view&member_id=' . $mid)); ?>">
												<?php echo esc_html__('View', 'smoketree-plugin'); ?>
											</a> |
										</span>
										<span class="quick-edit">
											<button type="button" class="button-link stsrc-quick-edit-btn" data-member-id="<?php echo esc_attr($mid); ?>">
												<?php echo esc_html__('Quick Edit', 'smoketree-plugin'); ?>
											</button>
										</span>
									</div>
								</td>
								<td><?php echo esc_html($member['email']); ?></td>
								<td class="column-membership-type"><?php echo esc_html($type_lookup[$member['membership_type_id']] ?? __('Unknown', 'smoketree-plugin')); ?></td>
								<td class="column-status">
									<span class="stsrc-status stsrc-status-<?php echo esc_attr($member['status']); ?>">
										<?php echo esc_html(ucfirst($member['status'])); ?>
									</span>
								</td>
								<td class="column-payment-type"><?php echo esc_html(ucfirst(str_replace('_', ' ', $member['payment_type']))); ?></td>
								<td class="stsrc-auto-renewal-column column-auto-renewal">
									<?php if (! empty($member['auto_renewal_enabled'])) : ?>
										<span class="dashicons dashicons-update" style="color: #00a32a;" title="<?php echo esc_attr__('Auto-renewal enabled', 'smoketree-plugin'); ?>"></span>
									<?php else : ?>
										<span class="dashicons dashicons-minus" style="color: #b0b0b0;" title="<?php echo esc_attr__('Auto-renewal disabled', 'smoketree-plugin'); ?>"></span>
									<?php endif; ?>
								</td>
								<td class="stsrc-guest-pass-column column-guest-passes"><?php echo esc_html($gp_balance); ?></td>
								<?php
								$balance = (float) ($member['balance_owed'] ?? 0);
								$balance_class = $balance > 0.01 ? 'stsrc-balance-positive' : ($balance < -0.01 ? 'stsrc-balance-negative' : 'stsrc-balance-zero');
								?>
								<td class="stsrc-balance-column <?php echo esc_attr($balance_class); ?>">
									<?php if ($balance < 0) : ?>
										-<?php echo esc_html('$' . number_format(abs($balance), 2)); ?>
									<?php else : ?>
										<?php echo esc_html('$' . number_format($balance, 2)); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($member['created_at']))); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="10"><?php echo esc_html__('No members found.', 'smoketree-plugin'); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="stsrc-bulk-status-box stsrc-collapsible is-collapsed">
			<h2>
				<button type="button" class="stsrc-collapsible-toggle" aria-expanded="false" data-key="stsrc_status_box">
					<?php echo esc_html__( 'Bulk Status Update', 'smoketree-plugin' ); ?>
					<span class="stsrc-collapse-icon" aria-hidden="true">&#9654;</span>
				</button>
			</h2>
			<div class="stsrc-collapsible-body">
				<p class="description">
					<?php echo esc_html__('Select members in the table above, choose a new status, and optionally clear auto-renewal preferences or guest pass balances.', 'smoketree-plugin'); ?>
				</p>

				<div class="stsrc-bulk-status-fields">
					<label for="stsrc-bulk-status-select">
						<span class="stsrc-field-label"><?php echo esc_html__('Status', 'smoketree-plugin'); ?></span>
						<select name="new_status" id="stsrc-bulk-status-select" required>
							<option value=""><?php echo esc_html__('Select status…', 'smoketree-plugin'); ?></option>
							<option value="active"><?php echo esc_html__('Active', 'smoketree-plugin'); ?></option>
							<option value="pending"><?php echo esc_html__('Pending', 'smoketree-plugin'); ?></option>
							<option value="cancelled"><?php echo esc_html__('Cancelled', 'smoketree-plugin'); ?></option>
							<option value="inactive"><?php echo esc_html__('Inactive', 'smoketree-plugin'); ?></option>
							<option value="deleted"><?php echo esc_html__('Deleted', 'smoketree-plugin'); ?></option>
						</select>
					</label>

					<label class="stsrc-inline-checkbox">
						<input type="checkbox" name="clear_auto_renewal" value="1">
						<span><?php echo esc_html__('Clear auto-renewal opt-in', 'smoketree-plugin'); ?></span>
					</label>

					<label class="stsrc-inline-checkbox">
						<input type="checkbox" name="reset_guest_pass_balance" value="1">
						<span><?php echo esc_html__('Reset guest pass balance to 0', 'smoketree-plugin'); ?></span>
					</label>
				</div>

				<div class="stsrc-bulk-status-actions">
					<button type="submit" class="button button-primary">
						<?php echo esc_html__('Apply to Selected Members', 'smoketree-plugin'); ?>
					</button>
				</div>
			</div>
		</div>
	</form>

	<!-- Bulk Guest Passes -->
	<div class="stsrc-bulk-guest-pass-box stsrc-collapsible is-collapsed">
		<h2>
			<button type="button" class="stsrc-collapsible-toggle" aria-expanded="false" data-key="stsrc_gp_box">
				<?php echo esc_html__( 'Bulk Add Guest Passes', 'smoketree-plugin' ); ?>
				<span class="stsrc-collapse-icon" aria-hidden="true">&#9654;</span>
			</button>
		</h2>
		<div class="stsrc-collapsible-body">
			<p class="description">
				<?php echo esc_html__('Select members in the table above, enter a quantity, and click Apply.', 'smoketree-plugin'); ?>
			</p>

			<form class="stsrc-ajax-form stsrc-bulk-guest-pass-form" data-reload="true">
				<input type="hidden" name="action" value="stsrc_bulk_update_members">
				<input type="hidden" name="nonce" value="<?php echo esc_attr($admin_nonce); ?>">
				<input type="hidden" name="target" value="add_guest_passes">

				<div class="stsrc-bulk-status-fields">
					<label for="stsrc-bulk-gp-qty">
						<span class="stsrc-field-label"><?php echo esc_html__('Quantity', 'smoketree-plugin'); ?></span>
						<input type="number" id="stsrc-bulk-gp-qty" name="guest_pass_quantity" min="1" step="1" value="25" style="width: 80px;">
					</label>
				</div>

				<div class="stsrc-bulk-status-actions">
					<button type="submit" class="button button-primary">
						<?php echo esc_html__('Add to Selected Members', 'smoketree-plugin'); ?>
					</button>
				</div>
			</form>
		</div>
	</div>



	<!-- Quick Edit Template (hidden, cloned by JS) -->
	<table style="display:none;">
		<tbody>
			<tr id="stsrc-quick-edit-template" class="stsrc-quick-edit-row inline-edit-row">
				<td colspan="10">
					<div class="stsrc-quick-edit-inner">
						<h3><?php echo esc_html__('Quick Edit', 'smoketree-plugin'); ?></h3>
						<input type="hidden" class="stsrc-qe-member-id" value="">

						<div class="stsrc-quick-edit-fields">
							<div class="stsrc-qe-field">
								<label><?php echo esc_html__('Status', 'smoketree-plugin'); ?></label>
								<select class="stsrc-qe-status">
									<option value="active"><?php echo esc_html__('Active', 'smoketree-plugin'); ?></option>
									<option value="pending"><?php echo esc_html__('Pending', 'smoketree-plugin'); ?></option>
									<option value="inactive"><?php echo esc_html__('Inactive', 'smoketree-plugin'); ?></option>
									<option value="cancelled"><?php echo esc_html__('Cancelled', 'smoketree-plugin'); ?></option>
								</select>
							</div>

							<div class="stsrc-qe-field">
								<label><?php echo esc_html__('Membership Type', 'smoketree-plugin'); ?></label>
								<select class="stsrc-qe-membership-type">
									<?php foreach ($membership_types as $type) : ?>
										<option value="<?php echo esc_attr($type['membership_type_id']); ?>">
											<?php echo esc_html($type['name']); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="stsrc-qe-field">
								<label><?php echo esc_html__('Payment Type', 'smoketree-plugin'); ?></label>
								<select class="stsrc-qe-payment-type">
									<option value="card"><?php echo esc_html__('Card', 'smoketree-plugin'); ?></option>
									<option value="bank_account"><?php echo esc_html__('Bank Account', 'smoketree-plugin'); ?></option>
									<option value="zelle"><?php echo esc_html__('Zelle', 'smoketree-plugin'); ?></option>
									<option value="check"><?php echo esc_html__('Check', 'smoketree-plugin'); ?></option>
									<option value="pay_later"><?php echo esc_html__('Pay Later', 'smoketree-plugin'); ?></option>
								</select>
							</div>

							<div class="stsrc-qe-field">
								<label class="stsrc-inline-checkbox">
									<input type="checkbox" class="stsrc-qe-auto-renewal" value="1">
									<span><?php echo esc_html__('Auto-Renewal', 'smoketree-plugin'); ?></span>
								</label>
							</div>

							<div class="stsrc-qe-field">
								<label><?php echo esc_html__('Add Guest Passes', 'smoketree-plugin'); ?></label>
								<input type="number" class="stsrc-qe-guest-passes" min="0" step="1" value="" placeholder="0">
								<p class="description"><?php echo esc_html__('Adds to the current balance.', 'smoketree-plugin'); ?></p>
							</div>
						</div>

						<div class="stsrc-quick-edit-actions">
							<button type="button" class="button button-primary stsrc-qe-save"><?php echo esc_html__('Update', 'smoketree-plugin'); ?></button>
							<button type="button" class="button stsrc-qe-cancel"><?php echo esc_html__('Cancel', 'smoketree-plugin'); ?></button>
							<span class="stsrc-qe-spinner spinner"></span>
						</div>
					</div>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- Season Reset -->
	<div class="stsrc-season-reset-box stsrc-collapsible is-collapsed">
		<h2>
			<button type="button" class="stsrc-collapsible-toggle" aria-expanded="false" data-key="stsrc_season_reset_box">
				<?php echo esc_html__('Season Reset', 'smoketree-plugin'); ?>
				<span class="stsrc-collapse-icon" aria-hidden="true">&#9654;</span>
			</button>
		</h2>
		<div class="stsrc-collapsible-body">
			<p class="description">
				<?php echo esc_html__('Run this once at the start of a new season. It changes every active member\'s status to inactive so they must renew to regain access. Members who opted in to auto-renewal (Stripe only) will be charged automatically by the scheduled cron job and moved back to active.', 'smoketree-plugin'); ?>
			</p>
			<p class="description">
				<strong><?php echo esc_html__('What this does:', 'smoketree-plugin'); ?></strong>
			</p>
			<ol class="description" style="margin: 4px 0 12px 1.5em; list-style: decimal;">
				<li><?php echo esc_html__('Sets all active members to inactive.', 'smoketree-plugin'); ?></li>
				<li><?php echo esc_html__('Members must go through the renewal flow in the portal to become active again.', 'smoketree-plugin'); ?></li>
				<li><?php echo esc_html__('Stripe members with auto-renewal enabled will be charged automatically and reactivated.', 'smoketree-plugin'); ?></li>
			</ol>

			<form method="post"
				action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
				class="stsrc-ajax-form stsrc-season-reset-form"
				data-reload="true"
				data-confirm="<?php echo esc_attr__('This will mark all active members as inactive. Members with auto-renewal (Stripe) will be charged and reactivated by the next cron run. Continue?', 'smoketree-plugin'); ?>">
				<input type="hidden" name="action" value="stsrc_bulk_update_members">
				<input type="hidden" name="nonce" value="<?php echo esc_attr($admin_nonce); ?>">
				<input type="hidden" name="target" value="season_reset">
				<input type="hidden" name="from_status" value="active">
				<input type="hidden" name="new_status" value="inactive">

				<label class="stsrc-inline-checkbox">
					<input type="checkbox" name="clear_auto_renewal" value="1">
					<span><?php echo esc_html__('Clear auto-renewal opt-in for all members (uncheck to preserve existing preferences)', 'smoketree-plugin'); ?></span>
				</label>

				<label class="stsrc-inline-checkbox">
					<input type="checkbox" name="reset_guest_pass_balance" value="1">
					<span><?php echo esc_html__('Reset guest pass balances to 0', 'smoketree-plugin'); ?></span>
				</label>

				<div class="stsrc-bulk-status-actions">
					<button type="submit" class="button button-secondary">
						<?php echo esc_html__('Start Season Reset', 'smoketree-plugin'); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>