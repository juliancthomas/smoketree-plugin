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

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="stsrc-member-edit-form" class="stsrc-member-form">
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
					<?php if ( ! empty( $family_members ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Name', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $family_members as $family_member ) : ?>
									<tr>
										<td><?php echo esc_html( $family_member['first_name'] . ' ' . $family_member['last_name'] ); ?></td>
										<td><?php echo esc_html( $family_member['email'] ?? '' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php echo esc_html__( 'No family members.', 'smoketree-plugin' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Extra Members -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?></h2>
					<?php if ( ! empty( $extra_members ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Name', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
									<th><?php echo esc_html__( 'Payment Status', 'smoketree-plugin' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $extra_members as $extra_member ) : ?>
									<tr>
										<td><?php echo esc_html( $extra_member['first_name'] . ' ' . $extra_member['last_name'] ); ?></td>
										<td><?php echo esc_html( $extra_member['email'] ?? '' ); ?></td>
										<td><?php echo esc_html( ucfirst( $extra_member['payment_status'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php echo esc_html__( 'No extra members.', 'smoketree-plugin' ); ?></p>
					<?php endif; ?>
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
		</div>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save Member', 'smoketree-plugin' ); ?>">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members' ) ); ?>" class="button">
				<?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?>
			</a>
		</p>
	</form>
</div>

