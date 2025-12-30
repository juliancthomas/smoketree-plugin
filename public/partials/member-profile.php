<?php
/**
 * Member profile display partial
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$member = $data['member'] ?? array();
$membership_type = $data['membership_type'] ?? null;
$auto_renewal_enabled = ! empty( $member['auto_renewal_enabled'] );
$auto_renewal_nonce = wp_create_nonce( 'stsrc_auto_renewal_nonce' );
?>

<div class="stsrc-portal-section">
	<h2><?php echo esc_html__( 'Membership Information', 'smoketree-plugin' ); ?></h2>
	
	<div class="stsrc-member-info">
		<div class="stsrc-info-row">
			<strong><?php echo esc_html__( 'Name:', 'smoketree-plugin' ); ?></strong>
			<span><?php echo esc_html( $member['first_name'] . ' ' . $member['last_name'] ); ?></span>
		</div>
		
		<div class="stsrc-info-row">
			<strong><?php echo esc_html__( 'Email:', 'smoketree-plugin' ); ?></strong>
			<span><?php echo esc_html( $member['email'] ); ?></span>
		</div>
		
		<div class="stsrc-info-row">
			<strong><?php echo esc_html__( 'Phone:', 'smoketree-plugin' ); ?></strong>
			<span><?php echo esc_html( $member['phone'] ?? '' ); ?></span>
		</div>
		
		<?php if ( ! empty( $membership_type ) ) : ?>
			<div class="stsrc-info-row">
				<strong><?php echo esc_html__( 'Membership Type:', 'smoketree-plugin' ); ?></strong>
				<span><?php echo esc_html( $membership_type['name'] ); ?></span>
			</div>
		<?php endif; ?>
		
		<div class="stsrc-info-row">
			<strong><?php echo esc_html__( 'Status:', 'smoketree-plugin' ); ?></strong>
			<span class="stsrc-status-badge <?php echo esc_attr( $member['status'] ); ?>">
				<?php echo esc_html( ucfirst( $member['status'] ) ); ?>
			</span>
		</div>

		<div class="stsrc-info-row stsrc-auto-renewal-row">
			<strong><?php echo esc_html__( 'Auto-Renewal:', 'smoketree-plugin' ); ?></strong>
			<div class="stsrc-auto-renewal-control">
				<form id="stsrc-auto-renewal-form">
					<input type="hidden" name="action" value="stsrc_toggle_auto_renewal">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $auto_renewal_nonce ); ?>">
					<input type="hidden" name="enabled" value="<?php echo $auto_renewal_enabled ? '1' : '0'; ?>">
					<label for="stsrc-auto-renewal-toggle">
						<input type="checkbox"
							id="stsrc-auto-renewal-toggle"
							name="auto_renewal_toggle"
							<?php checked( $auto_renewal_enabled ); ?>
							<?php disabled( empty( $member['stripe_customer_id'] ) ); ?>>
						<?php echo esc_html__( 'Enable automatic renewal', 'smoketree-plugin' ); ?>
					</label>
				</form>
				<span
					id="stsrc-auto-renewal-status"
					class="stsrc-auto-renewal-status"
					role="status"
					aria-live="polite"
					data-enabled-text="<?php echo esc_attr__( 'Enabled', 'smoketree-plugin' ); ?>"
					data-disabled-text="<?php echo esc_attr__( 'Disabled', 'smoketree-plugin' ); ?>"
				>
					<?php echo esc_html( $auto_renewal_enabled ? __( 'Enabled', 'smoketree-plugin' ) : __( 'Disabled', 'smoketree-plugin' ) ); ?>
				</span>
			</div>
			<p class="stsrc-description stsrc-auto-renewal-note">
				<?php
				if ( empty( $member['stripe_customer_id'] ) ) {
					echo esc_html__( 'Add a saved payment method to enable auto-renewal.', 'smoketree-plugin' );
				} else {
					echo esc_html__( 'When enabled, your saved payment method will be charged automatically on the renewal date.', 'smoketree-plugin' );
				}
				?>
			</p>
		</div>
		
		<?php if ( ! empty( $member['expires_at'] ) ) : ?>
			<div class="stsrc-info-row">
				<strong><?php echo esc_html__( 'Expires:', 'smoketree-plugin' ); ?></strong>
				<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member['expires_at'] ) ) ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<div class="stsrc-portal-actions">
		<button type="button" class="stsrc-button stsrc-button-primary" id="stsrc-edit-profile-btn">
			<?php echo esc_html__( 'Edit Profile', 'smoketree-plugin' ); ?>
		</button>
		<button type="button" class="stsrc-button stsrc-button-secondary" id="stsrc-change-password-btn">
			<?php echo esc_html__( 'Change Password', 'smoketree-plugin' ); ?>
		</button>
		<?php if ( ! empty( $member['stripe_customer_id'] ) ) : ?>
			<button type="button" class="stsrc-button stsrc-button-secondary" id="stsrc-stripe-portal-btn">
				<?php echo esc_html__( 'Manage Payment Methods', 'smoketree-plugin' ); ?>
			</button>
		<?php endif; ?>
	</div>
</div>

<!-- Edit Profile Modal -->
<div class="stsrc-modal-overlay" id="stsrc-edit-profile-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Edit Profile', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-edit-profile-form">
				<input type="hidden" name="action" value="stsrc_update_profile">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_profile_nonce' ) ); ?>">
				
				<div class="stsrc-form-group">
					<label for="edit_first_name"><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></label>
					<input type="text" name="first_name" id="edit_first_name" value="<?php echo esc_attr( $member['first_name'] ); ?>" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="edit_last_name"><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></label>
					<input type="text" name="last_name" id="edit_last_name" value="<?php echo esc_attr( $member['last_name'] ); ?>" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="edit_email"><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></label>
					<input type="email" name="email" id="edit_email" value="<?php echo esc_attr( $member['email'] ); ?>" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="edit_phone"><?php echo esc_html__( 'Phone', 'smoketree-plugin' ); ?></label>
					<input type="tel" name="phone" id="edit_phone" value="<?php echo esc_attr( $member['phone'] ?? '' ); ?>" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="edit_street_1"><?php echo esc_html__( 'Street Address', 'smoketree-plugin' ); ?></label>
					<input type="text" name="street_1" id="edit_street_1" value="<?php echo esc_attr( $member['street_1'] ?? '' ); ?>" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="edit_street_2"><?php echo esc_html__( 'Apartment, Suite, etc.', 'smoketree-plugin' ); ?></label>
					<input type="text" name="street_2" id="edit_street_2" value="<?php echo esc_attr( $member['street_2'] ?? '' ); ?>">
				</div>
				
				<div class="stsrc-form-row">
					<div class="stsrc-form-group">
						<label for="edit_city"><?php echo esc_html__( 'City', 'smoketree-plugin' ); ?></label>
						<input type="text" name="city" id="edit_city" value="<?php echo esc_attr( $member['city'] ?? '' ); ?>" required>
					</div>
					
					<div class="stsrc-form-group">
						<label for="edit_state"><?php echo esc_html__( 'State', 'smoketree-plugin' ); ?></label>
						<input type="text" name="state" id="edit_state" value="<?php echo esc_attr( $member['state'] ?? '' ); ?>" maxlength="2" required>
					</div>
					
					<div class="stsrc-form-group">
						<label for="edit_zip"><?php echo esc_html__( 'ZIP Code', 'smoketree-plugin' ); ?></label>
						<input type="text" name="zip" id="edit_zip" value="<?php echo esc_attr( $member['zip'] ?? '' ); ?>" required>
					</div>
				</div>
				
				<div class="stsrc-modal-footer">
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-modal-close"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
					<button type="submit" class="stsrc-button stsrc-button-primary"><?php echo esc_html__( 'Save Changes', 'smoketree-plugin' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Change Password Modal -->
<div class="stsrc-modal-overlay" id="stsrc-change-password-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Change Password', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-change-password-form">
				<input type="hidden" name="action" value="stsrc_change_password">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_password_nonce' ) ); ?>">
				
				<div class="stsrc-form-group">
					<label for="current_password"><?php echo esc_html__( 'Current Password', 'smoketree-plugin' ); ?></label>
					<input type="password" name="current_password" id="current_password" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="new_password"><?php echo esc_html__( 'New Password', 'smoketree-plugin' ); ?></label>
					<input type="password" name="new_password" id="new_password" required minlength="8">
					<small><?php echo esc_html__( 'Must be at least 8 characters long.', 'smoketree-plugin' ); ?></small>
				</div>
				
				<div class="stsrc-form-group">
					<label for="confirm_password"><?php echo esc_html__( 'Confirm New Password', 'smoketree-plugin' ); ?></label>
					<input type="password" name="confirm_password" id="confirm_password" required minlength="8">
				</div>
				
				<div class="stsrc-modal-footer">
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-modal-close"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
					<button type="submit" class="stsrc-button stsrc-button-primary"><?php echo esc_html__( 'Change Password', 'smoketree-plugin' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

