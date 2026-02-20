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

				<div class="stsrc-form-group">
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

				<div class="stsrc-pay-balance-summary">
					<div class="stsrc-pay-balance-summary__row">
						<span><?php echo esc_html__( 'Balance Payment', 'smoketree-plugin' ); ?></span>
						<span id="stsrc-summary-payment">$<?php echo esc_html( number_format( $current_balance, 2 ) ); ?></span>
					</div>
					<div class="stsrc-pay-balance-summary__row stsrc-pay-balance-summary__row--fee">
						<span><?php echo esc_html__( 'Processing Fee', 'smoketree-plugin' ); ?></span>
						<span id="stsrc-summary-fee">$0.00</span>
					</div>
					<div class="stsrc-pay-balance-summary__row stsrc-pay-balance-summary__row--total">
						<strong><?php echo esc_html__( 'Total Charge', 'smoketree-plugin' ); ?></strong>
						<strong id="stsrc-summary-total">$0.00</strong>
					</div>
					<p class="stsrc-pay-balance-summary__note">
						<?php echo esc_html__( 'The processing fee covers the cost of the transaction and does not apply to your balance.', 'smoketree-plugin' ); ?>
					</p>
				</div>

				<div class="stsrc-pay-balance-preview">
					<div>
						<span><?php echo esc_html__( 'Balance After Payment', 'smoketree-plugin' ); ?></span>
						<strong id="stsrc-balance-after-preview">$0.00</strong>
					</div>
				</div>

				<div class="stsrc-pay-balance-method-info">
					<?php echo esc_html__( 'Payment is processed securely via Stripe.', 'smoketree-plugin' ); ?>
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
