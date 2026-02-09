<?php
/**
 * Adjust Balance Modal Partial
 *
 * Modal dialog for admin balance adjustments with real-time preview.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get member_id if passed
$member_id = isset( $data['member_id'] ) ? absint( $data['member_id'] ) : 0;
?>

<!-- Adjust Balance Modal -->
<div id="stsrc-adjust-balance-modal" class="stsrc-modal" style="display: none;">
	<div class="stsrc-modal-overlay"></div>
	<div class="stsrc-modal-content">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Adjust Member Balance', 'smoketree-plugin' ); ?></h2>
			<button type="button" class="stsrc-modal-close" aria-label="<?php echo esc_attr__( 'Close modal', 'smoketree-plugin' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<!-- Step 1: Adjustment Form -->
		<div class="stsrc-modal-body" id="stsrc-adjust-form-step">
			<form id="stsrc-adjust-balance-form">
				<!-- Hidden fields -->
				<input type="hidden" name="member_id" id="stsrc-adjust-member-id" value="<?php echo esc_attr( $member_id ); ?>" />
				<?php wp_nonce_field( 'stsrc_adjust_balance', 'stsrc_adjust_balance_nonce' ); ?>

				<!-- Current Balance Display -->
				<div class="stsrc-form-group stsrc-current-balance-display">
					<label><?php echo esc_html__( 'Current Balance Owed:', 'smoketree-plugin' ); ?></label>
					<div class="stsrc-current-balance-value">
						$<span id="stsrc-adjust-current-balance">0.00</span>
					</div>
				</div>

				<!-- Adjustment Type -->
				<div class="stsrc-form-group">
					<label for="stsrc-adjustment-type">
						<?php echo esc_html__( 'Adjustment Type', 'smoketree-plugin' ); ?>
						<span class="required">*</span>
					</label>
					<select name="adjustment_type" id="stsrc-adjustment-type" required>
						<option value=""><?php echo esc_html__( 'Select adjustment type...', 'smoketree-plugin' ); ?></option>
						<option value="discount"><?php echo esc_html__( 'Discount (reduce balance)', 'smoketree-plugin' ); ?></option>
						<option value="fee"><?php echo esc_html__( 'Fee (increase balance)', 'smoketree-plugin' ); ?></option>
						<option value="correction"><?php echo esc_html__( 'Correction (can be positive or negative)', 'smoketree-plugin' ); ?></option>
						<option value="other"><?php echo esc_html__( 'Other Adjustment', 'smoketree-plugin' ); ?></option>
					</select>
					<p class="description" id="stsrc-adjustment-type-hint"></p>
				</div>

				<!-- Amount -->
				<div class="stsrc-form-group">
					<label for="stsrc-adjustment-amount">
						<?php echo esc_html__( 'Amount', 'smoketree-plugin' ); ?>
						<span class="required">*</span>
					</label>
					<div class="stsrc-amount-input-wrapper">
						<span class="stsrc-currency-symbol">$</span>
						<input 
							type="number" 
							name="amount" 
							id="stsrc-adjustment-amount" 
							step="0.01" 
							min="0.01" 
							placeholder="0.00"
							required 
						/>
					</div>
					<p class="description">
						<?php echo esc_html__( 'Enter the adjustment amount (positive values only). The type determines whether it increases or decreases the balance.', 'smoketree-plugin' ); ?>
					</p>
					<div class="stsrc-error-message" id="stsrc-amount-error" style="display: none;"></div>
				</div>

				<!-- Description -->
				<div class="stsrc-form-group">
					<label for="stsrc-adjustment-description">
						<?php echo esc_html__( 'Description', 'smoketree-plugin' ); ?>
						<span class="required">*</span>
					</label>
					<input 
						type="text" 
						name="description" 
						id="stsrc-adjustment-description" 
						class="regular-text"
						placeholder="<?php echo esc_attr__( 'e.g., Early bird discount, Late payment fee', 'smoketree-plugin' ); ?>"
						maxlength="255"
						required 
					/>
					<p class="description">
						<?php echo esc_html__( 'Brief description visible to the member in their transaction history.', 'smoketree-plugin' ); ?>
					</p>
				</div>

				<!-- Admin Notes -->
				<div class="stsrc-form-group">
					<label for="stsrc-adjustment-admin-notes">
						<?php echo esc_html__( 'Admin Notes (Internal)', 'smoketree-plugin' ); ?>
					</label>
					<textarea 
						name="admin_notes" 
						id="stsrc-adjustment-admin-notes" 
						rows="3"
						placeholder="<?php echo esc_attr__( 'Internal notes not visible to member (optional)', 'smoketree-plugin' ); ?>"
					></textarea>
				</div>

				<!-- Balance Preview -->
				<div class="stsrc-balance-preview" id="stsrc-balance-preview" style="display: none;">
					<div class="stsrc-preview-header">
						<span class="dashicons dashicons-visibility"></span>
						<?php echo esc_html__( 'Preview of New Balance', 'smoketree-plugin' ); ?>
					</div>
					<div class="stsrc-preview-content">
						<div class="stsrc-preview-row">
							<span><?php echo esc_html__( 'Current Balance:', 'smoketree-plugin' ); ?></span>
							<span class="stsrc-preview-current">$<span id="stsrc-preview-current-amount">0.00</span></span>
						</div>
						<div class="stsrc-preview-row stsrc-preview-adjustment">
							<span id="stsrc-preview-adjustment-label"><?php echo esc_html__( 'Adjustment:', 'smoketree-plugin' ); ?></span>
							<span class="stsrc-preview-change"><span id="stsrc-preview-adjustment-sign"></span>$<span id="stsrc-preview-adjustment-amount">0.00</span></span>
						</div>
						<div class="stsrc-preview-divider"></div>
						<div class="stsrc-preview-row stsrc-preview-new">
							<span><strong><?php echo esc_html__( 'New Balance:', 'smoketree-plugin' ); ?></strong></span>
							<span class="stsrc-preview-new-balance"><strong>$<span id="stsrc-preview-new-amount">0.00</span></strong></span>
						</div>
					</div>
				</div>
			</form>
		</div>

		<!-- Step 2: Confirmation -->
		<div class="stsrc-modal-body" id="stsrc-adjust-confirm-step" style="display: none;">
			<div class="stsrc-confirmation-content">
				<div class="stsrc-confirmation-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<h3><?php echo esc_html__( 'Confirm Balance Adjustment', 'smoketree-plugin' ); ?></h3>
				<p><?php echo esc_html__( 'Please review the adjustment details before proceeding:', 'smoketree-plugin' ); ?></p>
				
				<div class="stsrc-confirmation-details">
					<div class="stsrc-confirmation-row">
						<strong><?php echo esc_html__( 'Adjustment Type:', 'smoketree-plugin' ); ?></strong>
						<span id="stsrc-confirm-type"></span>
					</div>
					<div class="stsrc-confirmation-row">
						<strong><?php echo esc_html__( 'Amount:', 'smoketree-plugin' ); ?></strong>
						<span id="stsrc-confirm-amount"></span>
					</div>
					<div class="stsrc-confirmation-row">
						<strong><?php echo esc_html__( 'Description:', 'smoketree-plugin' ); ?></strong>
						<span id="stsrc-confirm-description"></span>
					</div>
					<div class="stsrc-confirmation-row stsrc-highlight">
						<strong><?php echo esc_html__( 'New Balance:', 'smoketree-plugin' ); ?></strong>
						<span id="stsrc-confirm-new-balance"></span>
					</div>
				</div>

				<p class="stsrc-confirmation-warning">
					<?php echo esc_html__( 'This action will create a permanent transaction record.', 'smoketree-plugin' ); ?>
				</p>
			</div>
		</div>

		<!-- Success Message -->
		<div class="stsrc-modal-body" id="stsrc-adjust-success-step" style="display: none;">
			<div class="stsrc-success-content">
				<div class="stsrc-success-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3><?php echo esc_html__( 'Balance Adjusted Successfully!', 'smoketree-plugin' ); ?></h3>
				<p id="stsrc-success-message"></p>
			</div>
		</div>

		<!-- Error Message -->
		<div class="stsrc-modal-body" id="stsrc-adjust-error-step" style="display: none;">
			<div class="stsrc-error-content">
				<div class="stsrc-error-icon">
					<span class="dashicons dashicons-dismiss"></span>
				</div>
				<h3><?php echo esc_html__( 'Error', 'smoketree-plugin' ); ?></h3>
				<p id="stsrc-error-message"></p>
			</div>
		</div>

		<!-- Modal Footer -->
		<div class="stsrc-modal-footer">
			<!-- Form Step Buttons -->
			<div id="stsrc-adjust-form-buttons">
				<button type="button" class="button stsrc-modal-close">
					<?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?>
				</button>
				<button type="button" class="button button-primary" id="stsrc-continue-to-confirm" disabled>
					<?php echo esc_html__( 'Continue', 'smoketree-plugin' ); ?>
				</button>
			</div>

			<!-- Confirmation Step Buttons -->
			<div id="stsrc-adjust-confirm-buttons" style="display: none;">
				<button type="button" class="button" id="stsrc-back-to-form">
					<?php echo esc_html__( 'Back', 'smoketree-plugin' ); ?>
				</button>
				<button type="button" class="button button-primary" id="stsrc-submit-adjustment">
					<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Confirm Adjustment', 'smoketree-plugin' ); ?>
				</button>
			</div>

			<!-- Success/Error Step Button -->
			<div id="stsrc-adjust-close-button" style="display: none;">
				<button type="button" class="button button-primary stsrc-modal-close-reload">
					<?php echo esc_html__( 'Close', 'smoketree-plugin' ); ?>
				</button>
			</div>

			<!-- Loading Spinner -->
			<div id="stsrc-adjust-loading" style="display: none;">
				<span class="spinner is-active"></span>
				<span><?php echo esc_html__( 'Processing...', 'smoketree-plugin' ); ?></span>
			</div>
		</div>
	</div>
</div>
