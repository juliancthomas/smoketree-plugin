<?php
/**
 * Renewal section partial.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';

$member          = $data['member'] ?? array();
$membership_type = $data['membership_type'] ?? array();
$renewal_context = $data['renewal_context'] ?? array();
$season_key      = (string) ( $renewal_context['season_key'] ?? gmdate( 'Y' ) );
$types           = STSRC_Membership_DB::get_all_membership_types( true );
$current_type_id = (int) ( $member['membership_type_id'] ?? 0 );
$current_price   = (float) ( $membership_type['price'] ?? 0.00 );
$balance_owed    = (float) ( $member['balance_owed'] ?? 0.00 );
$renewal_nonce   = wp_create_nonce( 'stsrc_renewal_nonce' );
?>

<section class="stsrc-portal-section stsrc-renewal-section" id="stsrc-renewal-section">
	<div class="stsrc-renewal-header">
		<h2><?php echo esc_html__( 'Renew Membership', 'smoketree-plugin' ); ?></h2>
		<p class="stsrc-description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s season key */
					__( 'Choose your %s membership plan and payment method.', 'smoketree-plugin' ),
					$season_key
				)
			);
			?>
		</p>
	</div>

	<form id="stsrc-renewal-form" class="stsrc-renewal-grid">
		<input type="hidden" name="member_id" value="<?php echo esc_attr( (string) (int) ( $member['member_id'] ?? 0 ) ); ?>">
		<input type="hidden" name="season_key" value="<?php echo esc_attr( $season_key ); ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $renewal_nonce ); ?>">

		<div class="stsrc-renewal-main">
			<div class="stsrc-renewal-cards">
				<?php foreach ( $types as $type ) : ?>
					<?php
					$type_id         = (int) ( $type['membership_type_id'] ?? 0 );
					$type_name       = (string) ( $type['name'] ?? '' );
					$type_price      = (float) ( $type['price'] ?? 0.00 );
					$type_benefits   = $type['benefits'] ?? array();
					$is_current      = $type_id === $current_type_id;
					$is_downgrade    = $type_price < $current_price;
					?>
					<label class="stsrc-renewal-card<?php echo $is_current ? ' is-current' : ''; ?>">
						<input
							type="radio"
							name="target_membership_type_id"
							value="<?php echo esc_attr( (string) $type_id ); ?>"
							<?php checked( $is_current ); ?>
						>
						<div class="stsrc-renewal-card__header">
							<h3><?php echo esc_html( $type_name ); ?></h3>
							<span class="stsrc-renewal-card__price"><?php echo esc_html( '$' . number_format( $type_price, 2 ) ); ?></span>
						</div>
						<?php if ( $is_current ) : ?>
							<p class="stsrc-renewal-badge"><?php echo esc_html__( 'Your current plan', 'smoketree-plugin' ); ?></p>
						<?php endif; ?>
						<?php if ( $is_downgrade ) : ?>
							<p class="stsrc-renewal-warning">
								<?php echo esc_html__( 'Downgrade may remove some member benefits.', 'smoketree-plugin' ); ?>
							</p>
						<?php endif; ?>
						<?php if ( is_array( $type_benefits ) && ! empty( $type_benefits ) ) : ?>
							<ul class="stsrc-renewal-benefits">
								<?php foreach ( $type_benefits as $benefit ) : ?>
									<li><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $benefit ) ) ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="stsrc-renewal-payment-methods">
				<h3><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></h3>
				<label><input type="radio" name="payment_method" value="card" checked> <?php echo esc_html__( 'Credit/Debit Card', 'smoketree-plugin' ); ?></label>
				<label><input type="radio" name="payment_method" value="ach"> <?php echo esc_html__( 'Bank Account (ACH)', 'smoketree-plugin' ); ?></label>
				<label><input type="radio" name="payment_method" value="zelle"> <?php echo esc_html__( 'Zelle', 'smoketree-plugin' ); ?></label>
				<label><input type="radio" name="payment_method" value="check"> <?php echo esc_html__( 'Check', 'smoketree-plugin' ); ?></label>
			</div>
		</div>

		<aside class="stsrc-renewal-summary" id="stsrc-renewal-summary">
			<h3><?php echo esc_html__( 'Order Summary', 'smoketree-plugin' ); ?></h3>
			<div class="stsrc-renewal-summary__row"><span><?php echo esc_html__( 'Membership', 'smoketree-plugin' ); ?></span><strong id="stsrc-renewal-membership-amount">$0.00</strong></div>
			<div class="stsrc-renewal-summary__row"><span><?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?></span><strong id="stsrc-renewal-extras-amount">$0.00</strong></div>
			<div class="stsrc-renewal-summary__row"><span><?php echo esc_html__( 'Current Balance', 'smoketree-plugin' ); ?></span><strong id="stsrc-renewal-balance-amount"><?php echo esc_html( '$' . number_format( $balance_owed, 2 ) ); ?></strong></div>
			<div class="stsrc-renewal-summary__row"><span><?php echo esc_html__( 'Processing Fee', 'smoketree-plugin' ); ?></span><strong id="stsrc-renewal-fee-amount">$0.00</strong></div>
			<div class="stsrc-renewal-summary__row stsrc-renewal-summary__row--total"><span><?php echo esc_html__( 'Total', 'smoketree-plugin' ); ?></span><strong id="stsrc-renewal-total-amount">$0.00</strong></div>
			<button type="button" class="stsrc-button stsrc-button-primary" id="stsrc-renewal-continue-btn" disabled>
				<?php echo esc_html__( 'Continue to Renewal Payment', 'smoketree-plugin' ); ?>
			</button>
		</aside>
	</form>
</section>

