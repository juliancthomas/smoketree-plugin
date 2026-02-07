<?php
/**
 * Member edit form template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$member = $data['member'] ?? null;
$membership_types = $data['membership_types'] ?? array();
$family_members = $data['family_members'] ?? array();
$extra_members = $data['extra_members'] ?? array();
$is_edit = ! empty( $member );
$member_id = $member['member_id'] ?? 0;
?>

<div class="wrap">
	<h1>
		<?php
		if ( $is_edit ) {
			echo esc_html__( 'Edit Member', 'smoketree-plugin' );
		} else {
			echo esc_html__( 'Add New Member', 'smoketree-plugin' );
		}
		?>
	</h1>

	<?php if ( $is_edit && 'cancelled' === ( $member['status'] ?? '' ) ) : ?>
		<div class="notice notice-warning" style="margin: 15px 0;">
			<p>
				<strong><?php echo esc_html__( 'This member account is cancelled.', 'smoketree-plugin' ); ?></strong>
				<?php echo esc_html__( 'The member can reactivate by registering again, or you can reactivate them using the button below.', 'smoketree-plugin' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="stsrc-member-edit-form" class="stsrc-member-form stsrc-ajax-form">
		<input type="hidden" name="action" value="<?php echo $is_edit ? 'stsrc_update_member' : 'stsrc_create_member'; ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="member_id" value="<?php echo esc_attr( $member_id ); ?>">
		<?php endif; ?>

		<div class="stsrc-form-sections">
			<!-- Basic Information -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Basic Information', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="first_name"><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr( $member['first_name'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="last_name"><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr( $member['last_name'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="email"><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="email" name="email" id="email" value="<?php echo esc_attr( $member['email'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="phone"><?php echo esc_html__( 'Phone', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="phone" id="phone" value="<?php echo esc_attr( $member['phone'] ?? '' ); ?>" required></td>
					</tr>
				</table>
			</div>

			<!-- Address Information -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Address', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="street_1"><?php echo esc_html__( 'Street Address 1', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="street_1" id="street_1" value="<?php echo esc_attr( $member['street_1'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="street_2"><?php echo esc_html__( 'Street Address 2', 'smoketree-plugin' ); ?></label></th>
						<td><input type="text" name="street_2" id="street_2" value="<?php echo esc_attr( $member['street_2'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="city"><?php echo esc_html__( 'City', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="city" id="city" value="<?php echo esc_attr( $member['city'] ?? 'Tucker' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="state"><?php echo esc_html__( 'State', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="state" id="state" value="<?php echo esc_attr( $member['state'] ?? 'GA' ); ?>" required maxlength="2"></td>
					</tr>
					<tr>
						<th><label for="zip"><?php echo esc_html__( 'ZIP Code', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="zip" id="zip" value="<?php echo esc_attr( $member['zip'] ?? '30084' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="country"><?php echo esc_html__( 'Country', 'smoketree-plugin' ); ?></label></th>
						<td><input type="text" name="country" id="country" value="<?php echo esc_attr( $member['country'] ?? 'US' ); ?>"></td>
					</tr>
				</table>
			</div>

			<!-- Membership Information -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Membership Information', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="membership_type_id"><?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td>
							<select name="membership_type_id" id="membership_type_id" required>
								<option value=""><?php echo esc_html__( 'Select Membership Type', 'smoketree-plugin' ); ?></option>
								<?php foreach ( $membership_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type['membership_type_id'] ); ?>" <?php selected( $member['membership_type_id'] ?? '', $type['membership_type_id'] ); ?>>
										<?php echo esc_html( $type['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="status"><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td>
							<select name="status" id="status" required>
								<option value="pending" <?php selected( $member['status'] ?? 'pending', 'pending' ); ?>><?php echo esc_html__( 'Pending', 'smoketree-plugin' ); ?></option>
								<option value="active" <?php selected( $member['status'] ?? '', 'active' ); ?>><?php echo esc_html__( 'Active', 'smoketree-plugin' ); ?></option>
								<option value="cancelled" <?php selected( $member['status'] ?? '', 'cancelled' ); ?>><?php echo esc_html__( 'Cancelled', 'smoketree-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="payment_type"><?php echo esc_html__( 'Payment Type', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td>
							<select name="payment_type" id="payment_type" required>
								<option value="card" <?php selected( $member['payment_type'] ?? '', 'card' ); ?>><?php echo esc_html__( 'Card', 'smoketree-plugin' ); ?></option>
								<option value="bank_account" <?php selected( $member['payment_type'] ?? '', 'bank_account' ); ?>><?php echo esc_html__( 'Bank Account', 'smoketree-plugin' ); ?></option>
								<option value="zelle" <?php selected( $member['payment_type'] ?? '', 'zelle' ); ?>><?php echo esc_html__( 'Zelle', 'smoketree-plugin' ); ?></option>
								<option value="check" <?php selected( $member['payment_type'] ?? '', 'check' ); ?>><?php echo esc_html__( 'Check', 'smoketree-plugin' ); ?></option>
								<option value="pay_later" <?php selected( $member['payment_type'] ?? '', 'pay_later' ); ?>><?php echo esc_html__( 'Pay Later', 'smoketree-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<?php if ( $is_edit && ! empty( $member['stripe_customer_id'] ) ) : ?>
						<tr>
							<th><label><?php echo esc_html__( 'Stripe Customer ID', 'smoketree-plugin' ); ?></label></th>
							<td><code><?php echo esc_html( $member['stripe_customer_id'] ); ?></code></td>
						</tr>
					<?php endif; ?>
					<?php if ( $is_edit && ! empty( $member['expiration_date'] ) ) : ?>
						<tr>
							<th><label><?php echo esc_html__( 'Expiration Date', 'smoketree-plugin' ); ?></label></th>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member['expiration_date'] ) ) ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<?php if ( $is_edit ) : ?>
				<!-- Family Members -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Family Members', 'smoketree-plugin' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Family members are included FREE with Household and Duo memberships. No additional payment required.', 'smoketree-plugin' ); ?></p>
					
					<?php if ( ! empty( $family_members ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Actions', 'smoketree-plugin' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $family_members as $family_member ) : ?>
									<tr data-family-member-id="<?php echo esc_attr( $family_member['family_member_id'] ); ?>">
										<td><?php echo esc_html( $family_member['first_name'] ); ?></td>
										<td><?php echo esc_html( $family_member['last_name'] ); ?></td>
										<td><?php echo esc_html( $family_member['email'] ?? '' ); ?></td>
										<td>
											<button type="button" class="button button-small delete-family-member" data-id="<?php echo esc_attr( $family_member['family_member_id'] ); ?>">
												<?php echo esc_html__( 'Delete', 'smoketree-plugin' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="no-family-members"><?php echo esc_html__( 'No family members.', 'smoketree-plugin' ); ?></p>
					<?php endif; ?>

					<div style="margin-top: 15px;">
						<h3><?php echo esc_html__( 'Add Family Member', 'smoketree-plugin' ); ?></h3>
						<table class="form-table">
							<tr>
								<th><label for="family_first_name"><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></label></th>
								<td><input type="text" id="family_first_name" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="family_last_name"><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></label></th>
								<td><input type="text" id="family_last_name" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="family_email"><?php echo esc_html__( 'Email (Optional)', 'smoketree-plugin' ); ?></label></th>
								<td><input type="email" id="family_email" class="regular-text"></td>
							</tr>
						</table>
						<button type="button" class="button" id="add-family-member-btn">
							<?php echo esc_html__( 'Add Family Member', 'smoketree-plugin' ); ?>
						</button>
					</div>
				</div>

				<!-- Extra Members -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Extra members cost $50 each, paid by the main account holder. Available for Household memberships only.', 'smoketree-plugin' ); ?></p>
					
					<?php if ( ! empty( $extra_members ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Actions', 'smoketree-plugin' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $extra_members as $extra_member ) : ?>
									<tr data-extra-member-id="<?php echo esc_attr( $extra_member['extra_member_id'] ); ?>">
										<td><?php echo esc_html( $extra_member['first_name'] ); ?></td>
										<td><?php echo esc_html( $extra_member['last_name'] ); ?></td>
										<td><?php echo esc_html( $extra_member['email'] ?? '' ); ?></td>
										<td>
											<button type="button" class="button button-small delete-extra-member" data-id="<?php echo esc_attr( $extra_member['extra_member_id'] ); ?>">
												<?php echo esc_html__( 'Delete', 'smoketree-plugin' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="no-extra-members"><?php echo esc_html__( 'No extra members.', 'smoketree-plugin' ); ?></p>
					<?php endif; ?>

					<div style="margin-top: 15px;">
						<h3><?php echo esc_html__( 'Add Extra Member', 'smoketree-plugin' ); ?></h3>
						<p class="description"><?php echo esc_html__( 'When you add an extra member here, it will be marked as paid (assuming payment was received offline).', 'smoketree-plugin' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="extra_first_name"><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></label></th>
								<td><input type="text" id="extra_first_name" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="extra_last_name"><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></label></th>
								<td><input type="text" id="extra_last_name" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="extra_email"><?php echo esc_html__( 'Email (Optional)', 'smoketree-plugin' ); ?></label></th>
								<td><input type="email" id="extra_email" class="regular-text"></td>
							</tr>
						</table>
						<button type="button" class="button" id="add-extra-member-btn">
							<?php echo esc_html__( 'Add Extra Member', 'smoketree-plugin' ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Waiver Information -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Waiver Information', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="waiver_full_name"><?php echo esc_html__( 'Waiver Full Name', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" name="waiver_full_name" id="waiver_full_name" value="<?php echo esc_attr( $member['waiver_full_name'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="waiver_signed_date"><?php echo esc_html__( 'Waiver Signed Date', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td><input type="date" name="waiver_signed_date" id="waiver_signed_date" value="<?php echo esc_attr( $member['waiver_signed_date'] ?? date( 'Y-m-d' ) ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="referral_source"><?php echo esc_html__( 'Referral Source', 'smoketree-plugin' ); ?></label></th>
						<td>
							<select name="referral_source" id="referral_source">
								<option value=""><?php echo esc_html__( 'Select...', 'smoketree-plugin' ); ?></option>
								<option value="A current or previous member" <?php selected( $member['referral_source'] ?? '', 'A current or previous member' ); ?>><?php echo esc_html__( 'A current or previous member', 'smoketree-plugin' ); ?></option>
								<option value="social media" <?php selected( $member['referral_source'] ?? '', 'social media' ); ?>><?php echo esc_html__( 'Social media', 'smoketree-plugin' ); ?></option>
								<option value="friend or family" <?php selected( $member['referral_source'] ?? '', 'friend or family' ); ?>><?php echo esc_html__( 'Friend or family', 'smoketree-plugin' ); ?></option>
								<option value="search engine" <?php selected( $member['referral_source'] ?? '', 'search engine' ); ?>><?php echo esc_html__( 'Search engine', 'smoketree-plugin' ); ?></option>
								<option value="news article" <?php selected( $member['referral_source'] ?? '', 'news article' ); ?>><?php echo esc_html__( 'News article', 'smoketree-plugin' ); ?></option>
								<option value="advertisement" <?php selected( $member['referral_source'] ?? '', 'advertisement' ); ?>><?php echo esc_html__( 'Advertisement', 'smoketree-plugin' ); ?></option>
								<option value="event" <?php selected( $member['referral_source'] ?? '', 'event' ); ?>><?php echo esc_html__( 'Event', 'smoketree-plugin' ); ?></option>
								<option value="other" <?php selected( $member['referral_source'] ?? '', 'other' ); ?>><?php echo esc_html__( 'Other', 'smoketree-plugin' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<?php if ( $is_edit ) : ?>
				<!-- Change Password -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Change Password', 'smoketree-plugin' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Leave blank to keep the current password.', 'smoketree-plugin' ); ?></p>
					<table class="form-table">
						<tr>
							<th><label for="new_password"><?php echo esc_html__( 'New Password', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="new_password" id="new_password" autocomplete="new-password">
								<p class="description"><?php echo esc_html__( 'Minimum 8 characters recommended.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="confirm_password"><?php echo esc_html__( 'Confirm Password', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="confirm_password" id="confirm_password" autocomplete="new-password">
							</td>
						</tr>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save Member', 'smoketree-plugin' ); ?>">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members' ) ); ?>" class="button">
				<?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?>
			</a>
			<?php if ( $is_edit ) : ?>
				<?php if ( 'cancelled' === ( $member['status'] ?? '' ) ) : ?>
					<button type="button" class="button" id="reactivate-member-btn" style="margin-left: 20px; color: #007cba;">
						<?php echo esc_html__( 'Reactivate Member', 'smoketree-plugin' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button button-link-delete" id="delete-member-btn" style="margin-left: 20px; color: #b32d2e;">
						<?php echo esc_html__( 'Delete Member', 'smoketree-plugin' ); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>
		</p>
	</form>
</div>

<?php if ( $is_edit ) : ?>
<script>
jQuery(document).ready(function($) {
	const memberId = <?php echo intval( $member_id ); ?>;
	const nonce = '<?php echo wp_create_nonce( 'stsrc_admin_nonce' ); ?>';

	// Delete member
	$('#delete-member-btn').on('click', function(e) {
		e.preventDefault();
		
		if (!confirm('Are you sure you want to delete this member? They can reactivate their account by registering again with the same email address.')) {
			return;
		}
		
		const $button = $(this);
		$button.prop('disabled', true).text('Deleting...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'stsrc_delete_member',
				nonce: nonce,
				member_id: memberId
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || 'Member deleted successfully.');
					window.location.href = '<?php echo admin_url( 'admin.php?page=stsrc-members' ); ?>';
				} else {
					alert(response.data.message || 'Failed to delete member.');
					$button.prop('disabled', false).text('Delete Member');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$button.prop('disabled', false).text('Delete Member');
			}
		});
	});

	// Reactivate member
	$('#reactivate-member-btn').on('click', function(e) {
		e.preventDefault();
		
		if (!confirm('Are you sure you want to reactivate this member? Their status will be set to pending.')) {
			return;
		}
		
		const $button = $(this);
		$button.prop('disabled', true).text('Reactivating...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'stsrc_reactivate_member',
				nonce: nonce,
				member_id: memberId
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || 'Member reactivated successfully.');
					location.reload();
				} else {
					alert(response.data.message || 'Failed to reactivate member.');
					$button.prop('disabled', false).text('Reactivate Member');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$button.prop('disabled', false).text('Reactivate Member');
			}
		});
	});

	// Add family member
	$('#add-family-member-btn').on('click', function(e) {
		e.preventDefault();
		
		const firstName = $('#family_first_name').val().trim();
		const lastName = $('#family_last_name').val().trim();
		const email = $('#family_email').val().trim();
		
		if (!firstName || !lastName) {
			alert('First name and last name are required.');
			return;
		}
		
		const $button = $(this);
		$button.prop('disabled', true).text('Adding...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'stsrc_add_family_member',
				nonce: nonce,
				member_id: memberId,
				first_name: firstName,
				last_name: lastName,
				email: email
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || 'Family member added successfully.');
					location.reload();
				} else {
					alert(response.data.message || 'Failed to add family member.');
					$button.prop('disabled', false).text('Add Family Member');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$button.prop('disabled', false).text('Add Family Member');
			}
		});
	});

	// Delete family member
	$(document).on('click', '.delete-family-member', function(e) {
		e.preventDefault();
		
		if (!confirm('Are you sure you want to delete this family member?')) {
			return;
		}
		
		const familyMemberId = $(this).data('id');
		const $button = $(this);
		const $row = $button.closest('tr');
		
		$button.prop('disabled', true).text('Deleting...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'stsrc_delete_family_member',
				nonce: nonce,
				family_member_id: familyMemberId
			},
			success: function(response) {
				if (response.success) {
					$row.fadeOut(300, function() {
						$(this).remove();
						// Check if table is now empty
						if ($('.stsrc-form-section table tbody tr[data-family-member-id]').length === 0) {
							location.reload();
						}
					});
				} else {
					alert(response.data.message || 'Failed to delete family member.');
					$button.prop('disabled', false).text('Delete');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$button.prop('disabled', false).text('Delete');
			}
		});
	});

	// Add extra member
	$('#add-extra-member-btn').on('click', function(e) {
		e.preventDefault();
		
		const firstName = $('#extra_first_name').val().trim();
		const lastName = $('#extra_last_name').val().trim();
		const email = $('#extra_email').val().trim();
		
		if (!firstName || !lastName) {
			alert('First name and last name are required.');
			return;
		}
		
		const $button = $(this);
		$button.prop('disabled', true).text('Adding...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'stsrc_add_extra_member',
				nonce: nonce,
				member_id: memberId,
				first_name: firstName,
				last_name: lastName,
				email: email
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || 'Extra member added successfully.');
					location.reload();
				} else {
					alert(response.data.message || 'Failed to add extra member.');
					$button.prop('disabled', false).text('Add Extra Member');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$button.prop('disabled', false).text('Add Extra Member');
			}
		});
	});

	// Delete extra member
	$(document).on('click', '.delete-extra-member', function(e) {
		e.preventDefault();
		
		if (!confirm('Are you sure you want to delete this extra member?')) {
			return;
		}
		
		const extraMemberId = $(this).data('id');
		const $button = $(this);
		const $row = $button.closest('tr');
		
		$button.prop('disabled', true).text('Deleting...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'stsrc_delete_extra_member',
				nonce: nonce,
				extra_member_id: extraMemberId
			},
			success: function(response) {
				if (response.success) {
					$row.fadeOut(300, function() {
						$(this).remove();
						// Check if table is now empty
						if ($('.stsrc-form-section table tbody tr[data-extra-member-id]').length === 0) {
							location.reload();
						}
					});
				} else {
					alert(response.data.message || 'Failed to delete extra member.');
					$button.prop('disabled', false).text('Delete');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$button.prop('disabled', false).text('Delete');
			}
		});
	});
});
</script>
<?php endif; ?>

