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
$current_count = count( $extra_members );
$slots_available = max( $extra_limit - $current_count, 0 );
$can_add_more = $slots_available > 0;
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

<!-- Add Extra Members Modal (multi-member with payment) -->
<?php if ( $can_add_more ) : ?>
<div class="stsrc-modal-overlay" id="stsrc-add-extra-member-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Add Extra Members', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-add-extra-members-form">
				<input type="hidden" name="action" value="stsrc_add_extra_member">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_extra_member_nonce' ) ); ?>">
				<input type="hidden" id="stsrc-extra-member-slots-available" value="<?php echo esc_attr( $slots_available ); ?>">

				<div id="stsrc-extra-member-slots">
					<div class="stsrc-extra-member-slot" data-index="0">
						<div class="stsrc-extra-member-slot__header">
							<strong><?php echo esc_html__( 'Extra Member 1', 'smoketree-plugin' ); ?></strong>
						</div>
						<div class="stsrc-form-row">
							<div class="stsrc-form-group">
								<label><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></label>
								<input type="text" name="members[0][first_name]" required>
							</div>
							<div class="stsrc-form-group">
								<label><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></label>
								<input type="text" name="members[0][last_name]" required>
							</div>
						</div>
						<div class="stsrc-form-group">
							<label><?php echo esc_html__( 'Email (optional)', 'smoketree-plugin' ); ?></label>
							<input type="email" name="members[0][email]">
						</div>
					</div>
				</div>

				<?php if ( $slots_available > 1 ) : ?>
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-button-small" id="stsrc-add-another-member-btn">
						<?php echo esc_html__( '+ Add Another Member', 'smoketree-plugin' ); ?>
					</button>
				<?php endif; ?>

				<div class="stsrc-form-group stsrc-mt-md">
					<label><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></label>
					<div class="stsrc-pay-balance-methods">
						<label class="stsrc-pay-balance-method stsrc-pay-balance-method--selected">
							<input type="radio" name="payment_method" value="card" checked />
							<span class="stsrc-pay-balance-method__label">
								<strong><?php echo esc_html__( 'Credit / Debit Card', 'smoketree-plugin' ); ?></strong>
								<span class="stsrc-pay-balance-method__fee"><?php echo esc_html__( '2.9% + $0.30 processing fee', 'smoketree-plugin' ); ?></span>
							</span>
						</label>
						<label class="stsrc-pay-balance-method">
							<input type="radio" name="payment_method" value="us_bank_account" />
							<span class="stsrc-pay-balance-method__label">
								<strong><?php echo esc_html__( 'Bank Account (ACH)', 'smoketree-plugin' ); ?></strong>
								<span class="stsrc-pay-balance-method__fee"><?php echo esc_html__( '0.8% processing fee ($5.00 max)', 'smoketree-plugin' ); ?></span>
							</span>
						</label>
					</div>
				</div>

				<div class="stsrc-pay-balance-summary" id="stsrc-extra-member-summary">
					<div class="stsrc-pay-balance-summary__row">
						<span id="stsrc-em-summary-label"><?php echo esc_html__( 'Extra Member (1)', 'smoketree-plugin' ); ?></span>
						<span id="stsrc-em-summary-subtotal">$50.00</span>
					</div>
					<div class="stsrc-pay-balance-summary__row stsrc-pay-balance-summary__row--fee">
						<span><?php echo esc_html__( 'Processing Fee', 'smoketree-plugin' ); ?></span>
						<span id="stsrc-em-summary-fee">$0.00</span>
					</div>
					<div class="stsrc-pay-balance-summary__row stsrc-pay-balance-summary__row--total">
						<strong><?php echo esc_html__( 'Total Charge', 'smoketree-plugin' ); ?></strong>
						<strong id="stsrc-em-summary-total">$0.00</strong>
					</div>
					<p class="stsrc-pay-balance-summary__note">
						<?php echo esc_html__( 'The processing fee covers the cost of the transaction and is not part of the membership price.', 'smoketree-plugin' ); ?>
					</p>
				</div>

				<div id="stsrc-extra-member-error" class="stsrc-pay-balance-error stsrc-hidden"></div>

				<div class="stsrc-modal-footer">
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-modal-close"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
					<button type="submit" class="stsrc-button stsrc-button-primary" id="stsrc-extra-member-submit">
						<?php echo esc_html__( 'Continue to Payment', 'smoketree-plugin' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- Edit Extra Member Modal (single member, no payment) -->
<div class="stsrc-modal-overlay" id="stsrc-extra-member-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Edit Extra Member', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-extra-member-form">
				<input type="hidden" name="action" value="stsrc_update_extra_member">
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
