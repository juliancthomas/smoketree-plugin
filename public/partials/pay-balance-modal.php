<?php
/**
 * Pay balance modal partial
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/services/class-stsrc-balance-service.php';

$member_data = $data['member'] ?? array();
$member_id   = isset( $member_data['member_id'] ) ? (int) $member_data['member_id'] : 0;

if ( $member_id <= 0 ) {
	return;
}

$balance_data = STSRC_Balance_Service::get_balance_display_data( $member_id );
$current_balance = (float) ( $balance_data['balance_owed'] ?? 0 );
$minimum_payment = (float) get_option( 'stsrc_minimum_balance_payment', 10.0 );

if ( $current_balance <= 0.01 ) {
	return;
}
?>

<div id="stsrc-pay-balance-modal" class="stsrc-portal-modal stsrc-hidden" aria-hidden="true">
	<div class="stsrc-portal-modal__overlay" data-close="1"></div>
	<div class="stsrc-portal-modal__content" role="dialog" aria-modal="true" aria-labelledby="stsrc-pay-balance-title">
		<div class="stsrc-portal-modal__header">
			<h3 id="stsrc-pay-balance-title"><?php echo esc_html__( 'Pay Outstanding Balance', 'smoketree-plugin' ); ?></h3>
			<button type="button" class="stsrc-portal-modal__close" data-close="1" aria-label="<?php echo esc_attr__( 'Close', 'smoketree-plugin' ); ?>">×</button>
		</div>

		<form id="stsrc-pay-balance-form">
			<input type="hidden" name="member_id" value="<?php echo esc_attr( $member_id ); ?>" />
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_balance_payment_nonce' ) ); ?>" />
			<input type="hidden" id="stsrc-current-balance-value" value="<?php echo esc_attr( number_format( $current_balance, 2, '.', '' ) ); ?>" />
			<input type="hidden" id="stsrc-minimum-balance-payment-value" value="<?php echo esc_attr( number_format( $minimum_payment, 2, '.', '' ) ); ?>" />

			<div class="stsrc-portal-modal__body">
				<div class="stsrc-pay-balance-current">
					<span class="stsrc-pay-balance-current__label"><?php echo esc_html__( 'Current Balance', 'smoketree-plugin' ); ?></span>
					<strong class="stsrc-pay-balance-current__value">$<?php echo esc_html( number_format( $current_balance, 2 ) ); ?></strong>
				</div>

				<div class="stsrc-form-group">
					<label for="stsrc-balance-payment-amount"><?php echo esc_html__( 'Payment Amount', 'smoketree-plugin' ); ?></label>
					<div class="stsrc-pay-balance-input-wrap">
						<span>$</span>
						<input
							type="number"
							id="stsrc-balance-payment-amount"
							name="amount"
							min="0.01"
							step="0.01"
							value="<?php echo esc_attr( number_format( $current_balance, 2, '.', '' ) ); ?>"
							required
						/>
					</div>
					<p class="stsrc-description">
						<?php
						printf(
							/* translators: %s: minimum payment amount */
							esc_html__( 'Minimum payment is $%s.', 'smoketree-plugin' ),
							esc_html( number_format( $minimum_payment, 2 ) )
						);
						?>
					</p>
					<div id="stsrc-balance-payment-error" class="stsrc-pay-balance-error stsrc-hidden"></div>
				</div>

				<div class="stsrc-pay-balance-preview">
					<div>
						<span><?php echo esc_html__( 'Balance After Payment', 'smoketree-plugin' ); ?></span>
						<strong id="stsrc-balance-after-preview">$0.00</strong>
					</div>
				</div>

				<div class="stsrc-pay-balance-method-info">
					<?php echo esc_html__( 'Payment is processed securely via Stripe (Card or Bank Account).', 'smoketree-plugin' ); ?>
				</div>
			</div>

			<div class="stsrc-portal-modal__footer">
				<button type="button" class="stsrc-button stsrc-button-secondary" data-close="1">
					<?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?>
				</button>
				<button type="submit" id="stsrc-continue-to-payment" class="stsrc-button stsrc-button-primary">
					<?php echo esc_html__( 'Continue to Payment', 'smoketree-plugin' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
