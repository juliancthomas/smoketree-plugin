<?php
/**
 * Extra members list partial
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$member = $data['member'] ?? array();
$extra_members = $data['extra_members'] ?? array();

$extra_limit = 3;
$can_add_more = count( $extra_members ) < $extra_limit;
?>

<div class="stsrc-portal-section">
	<h2><?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?></h2>
	
	<p class="stsrc-description">
		<?php echo esc_html__( 'Extra members can be added to Household memberships for $50 each (maximum 3). Payment is required before activation.', 'smoketree-plugin' ); ?>
	</p>

	<?php if ( ! empty( $extra_members ) ) : ?>
		<div class="stsrc-extra-members-list">
			<?php foreach ( $extra_members as $extra_member ) : ?>
				<div class="stsrc-extra-member-item" data-id="<?php echo esc_attr( $extra_member['extra_member_id'] ); ?>">
					<div class="stsrc-member-details">
						<strong><?php echo esc_html( $extra_member['first_name'] . ' ' . $extra_member['last_name'] ); ?></strong>
						<?php if ( ! empty( $extra_member['email'] ) ) : ?>
							<span class="stsrc-member-email"><?php echo esc_html( $extra_member['email'] ); ?></span>
						<?php endif; ?>
						<?php if ( 'paid' === $extra_member['payment_status'] ) : ?>
							<span class="stsrc-status-badge active"><?php echo esc_html__( 'Paid', 'smoketree-plugin' ); ?></span>
						<?php else : ?>
							<span class="stsrc-status-badge pending"><?php echo esc_html__( 'Payment Pending', 'smoketree-plugin' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="stsrc-member-actions">
						<?php if ( 'paid' === $extra_member['payment_status'] ) : ?>
							<button type="button" class="stsrc-button stsrc-button-secondary stsrc-edit-extra-member" data-id="<?php echo esc_attr( $extra_member['extra_member_id'] ); ?>">
								<?php echo esc_html__( 'Edit', 'smoketree-plugin' ); ?>
							</button>
							<button type="button" class="stsrc-button stsrc-button-danger stsrc-delete-extra-member" data-id="<?php echo esc_attr( $extra_member['extra_member_id'] ); ?>">
								<?php echo esc_html__( 'Delete', 'smoketree-plugin' ); ?>
							</button>
						<?php else : ?>
							<span class="stsrc-payment-required"><?php echo esc_html__( 'Payment required', 'smoketree-plugin' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="stsrc-empty-state"><?php echo esc_html__( 'No extra members added yet.', 'smoketree-plugin' ); ?></p>
	<?php endif; ?>

	<?php if ( $can_add_more ) : ?>
		<button type="button" class="stsrc-button stsrc-button-primary" id="stsrc-add-extra-member-btn">
			<?php echo esc_html__( '+ Add Extra Member ($50)', 'smoketree-plugin' ); ?>
		</button>
	<?php endif; ?>
</div>

<!-- Add/Edit Extra Member Modal -->
<div class="stsrc-modal-overlay" id="stsrc-extra-member-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2 id="stsrc-extra-member-modal-title"><?php echo esc_html__( 'Add Extra Member', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-extra-member-form">
				<input type="hidden" name="action" id="stsrc-extra-member-action" value="stsrc_add_extra_member">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_extra_member_nonce' ) ); ?>">
				<input type="hidden" name="extra_member_id" id="stsrc-extra-member-id" value="">
				
				<div class="stsrc-form-group">
					<label for="extra_first_name"><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></label>
					<input type="text" name="first_name" id="extra_first_name" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="extra_last_name"><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></label>
					<input type="text" name="last_name" id="extra_last_name" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="extra_email"><?php echo esc_html__( 'Email (optional)', 'smoketree-plugin' ); ?></label>
					<input type="email" name="email" id="extra_email">
				</div>
				
				<div class="stsrc-modal-footer">
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-modal-close"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
					<button type="submit" class="stsrc-button stsrc-button-primary"><?php echo esc_html__( 'Save', 'smoketree-plugin' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

