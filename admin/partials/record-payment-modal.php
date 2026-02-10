<?php
/**
 * Record Manual Payment Modal Partial
 *
 * Modal dialog for admin recording manual payments (check, Zelle, cash).
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$member_id = isset( $data['member_id'] ) ? absint( $data['member_id'] ) : 0;
$today     = current_time( 'Y-m-d' );
?>

<!-- Record Manual Payment Modal -->
<div id="stsrc-record-payment-modal" class="stsrc-modal" style="display: none;">
	<div class="stsrc-modal-overlay"></div>
	<div class="stsrc-modal-content">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Record Manual Payment', 'smoketree-plugin' ); ?></h2>
			<button type="button" class="stsrc-modal-close" aria-label="<?php echo esc_attr__( 'Close modal', 'smoketree-plugin' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="stsrc-modal-body">
			<form id="stsrc-record-payment-form">
				<input type="hidden" name="member_id" id="stsrc-record-payment-member-id" value="<?php echo esc_attr( $member_id ); ?>" />
				<?php wp_nonce_field( 'stsrc_record_payment', 'stsrc_record_payment_nonce' ); ?>

				<div class="stsrc-form-group">
					<label for="stsrc-payment-method">
						<?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?>
						<span class="required">*</span>
					</label>
					<select name="payment_method" id="stsrc-payment-method" required>
						<option value=""><?php echo esc_html__( 'Select payment method...', 'smoketree-plugin' ); ?></option>
						<option value="check"><?php echo esc_html__( 'Check', 'smoketree-plugin' ); ?></option>
						<option value="zelle"><?php echo esc_html__( 'Zelle', 'smoketree-plugin' ); ?></option>
						<option value="cash"><?php echo esc_html__( 'Cash', 'smoketree-plugin' ); ?></option>
					</select>
				</div>

				<div class="stsrc-form-group stsrc-check-number-group stsrc-hidden">
					<label for="stsrc-check-number">
						<?php echo esc_html__( 'Check Number', 'smoketree-plugin' ); ?>
					</label>
					<input type="text" name="check_number" id="stsrc-check-number" class="regular-text" placeholder="<?php echo esc_attr__( 'Optional', 'smoketree-plugin' ); ?>" maxlength="30" />
				</div>

				<div class="stsrc-form-group">
					<label for="stsrc-payment-amount">
						<?php echo esc_html__( 'Amount', 'smoketree-plugin' ); ?>
						<span class="required">*</span>
					</label>
					<div class="stsrc-amount-input-wrapper">
						<span class="stsrc-currency-symbol">$</span>
						<input
							type="number"
							name="amount"
							id="stsrc-payment-amount"
							step="0.01"
							min="0.01"
							placeholder="0.00"
							required
						/>
					</div>
					<div class="stsrc-error-message" id="stsrc-payment-amount-error" style="display: none;"></div>
				</div>

				<div class="stsrc-form-group">
					<label for="stsrc-payment-description">
						<?php echo esc_html__( 'Description', 'smoketree-plugin' ); ?>
						<span class="required">*</span>
					</label>
					<input
						type="text"
						name="description"
						id="stsrc-payment-description"
						class="regular-text"
						placeholder="<?php echo esc_attr__( 'e.g., Check payment received', 'smoketree-plugin' ); ?>"
						maxlength="255"
						required
					/>
				</div>

				<div class="stsrc-form-group">
					<label for="stsrc-payment-date">
						<?php echo esc_html__( 'Date Received', 'smoketree-plugin' ); ?>
						<span class="required">*</span>
					</label>
					<input
						type="date"
						name="date_received"
						id="stsrc-payment-date"
						value="<?php echo esc_attr( $today ); ?>"
						required
					/>
					<p class="description"><?php echo esc_html__( 'Defaults to today. Adjust if payment was received earlier.', 'smoketree-plugin' ); ?></p>
				</div>

				<div class="stsrc-form-group">
					<label for="stsrc-payment-admin-notes">
						<?php echo esc_html__( 'Admin Notes (Internal)', 'smoketree-plugin' ); ?>
					</label>
					<textarea
						name="admin_notes"
						id="stsrc-payment-admin-notes"
						rows="3"
						placeholder="<?php echo esc_attr__( 'Internal notes not visible to member (optional)', 'smoketree-plugin' ); ?>"
					></textarea>
				</div>

				<div class="stsrc-form-group stsrc-hidden" id="stsrc-record-payment-message"></div>
			</form>
		</div>

		<div class="stsrc-modal-footer">
			<button type="button" class="button stsrc-modal-close">
				<?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?>
			</button>
			<button type="button" class="button button-primary" id="stsrc-submit-record-payment" disabled>
				<?php echo esc_html__( 'Record Payment', 'smoketree-plugin' ); ?>
			</button>
		</div>
	</div>
</div>
