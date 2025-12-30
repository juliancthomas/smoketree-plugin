<?php
/**
 * Guest pass balance display partial
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$member = $data['member'] ?? array();
$guest_pass_balance = $data['guest_pass_balance'] ?? 0;
?>

<div class="stsrc-portal-section">
	<h2><?php echo esc_html__( 'Guest Passes', 'smoketree-plugin' ); ?></h2>
	
	<div class="stsrc-guest-pass-info">
		<div class="stsrc-balance-display">
			<strong><?php echo esc_html__( 'Current Balance:', 'smoketree-plugin' ); ?></strong>
			<span class="stsrc-balance-amount"><?php echo esc_html( number_format( $guest_pass_balance, 0 ) ); ?></span>
			<span class="stsrc-balance-label"><?php echo esc_html( 1 === $guest_pass_balance ? 'pass' : 'passes' ); ?></span>
		</div>
		
		<p class="stsrc-description">
			<?php echo esc_html__( 'Guest passes are $5 each. Purchase passes to allow guests to use the facility.', 'smoketree-plugin' ); ?>
		</p>
	</div>

	<div class="stsrc-portal-actions">
		<button type="button" class="stsrc-button stsrc-button-primary" id="stsrc-purchase-guest-passes-btn">
			<?php echo esc_html__( 'Purchase Guest Passes', 'smoketree-plugin' ); ?>
		</button>
		<a href="<?php echo esc_url( home_url( '/guest-pass-portal' ) ); ?>" class="stsrc-button stsrc-button-secondary">
			<?php echo esc_html__( 'View Guest Pass Portal', 'smoketree-plugin' ); ?>
		</a>
	</div>
</div>

<!-- Purchase Guest Passes Modal -->
<div class="stsrc-modal-overlay" id="stsrc-purchase-guest-passes-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Purchase Guest Passes', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-purchase-guest-passes-form">
				<input type="hidden" name="action" value="stsrc_purchase_guest_passes">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_guest_pass_nonce' ) ); ?>">
				
				<div class="stsrc-form-group">
					<label for="guest_pass_quantity"><?php echo esc_html__( 'Number of Passes', 'smoketree-plugin' ); ?></label>
					<input type="number" name="quantity" id="guest_pass_quantity" min="1" value="1" required>
					<small><?php echo esc_html__( '$5 per pass', 'smoketree-plugin' ); ?></small>
				</div>
				
				<div class="stsrc-form-group">
					<strong><?php echo esc_html__( 'Total:', 'smoketree-plugin' ); ?> $<span id="stsrc-guest-pass-total">5.00</span></strong>
				</div>
				
				<div class="stsrc-modal-footer">
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-modal-close"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
					<button type="submit" class="stsrc-button stsrc-button-primary"><?php echo esc_html__( 'Purchase', 'smoketree-plugin' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Calculate total when quantity changes
	$('#guest_pass_quantity').on('change', function() {
		const quantity = parseInt($(this).val()) || 1;
		const total = (quantity * 5).toFixed(2);
		$('#stsrc-guest-pass-total').text(total);
	});
});
</script>

