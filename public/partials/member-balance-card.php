<?php
/**
 * Member balance card partial
 *
 * Displays a prominent outstanding balance card in member portal.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$card = $balance_card_data ?? array();

$balance_owed         = (float) ( $card['balance_owed'] ?? 0 );
$membership_type_name = $card['membership_type_name'] ?? __( 'Membership', 'smoketree-plugin' );
$season_price         = (float) ( $card['season_price'] ?? 0 );
$total_paid           = (float) ( $card['total_paid'] ?? 0 );
$remaining_balance    = (float) ( $card['remaining_balance'] ?? $balance_owed );
?>

<section class="stsrc-member-balance-card" aria-label="<?php echo esc_attr__( 'Outstanding balance', 'smoketree-plugin' ); ?>">
	<div class="stsrc-member-balance-card__left">
		<p class="stsrc-member-balance-card__eyebrow">
			<?php echo esc_html__( 'Outstanding Balance', 'smoketree-plugin' ); ?>
		</p>
		<div class="stsrc-member-balance-card__amount">
			$<?php echo esc_html( number_format( $balance_owed, 2 ) ); ?>
		</div>
		<p class="stsrc-member-balance-card__note">
			<?php echo esc_html__( 'Please complete payment to finish account activation.', 'smoketree-plugin' ); ?>
		</p>
	</div>

	<div class="stsrc-member-balance-card__right">
		<div class="stsrc-member-balance-card__details">
			<div class="stsrc-member-balance-card__detail">
				<span class="stsrc-member-balance-card__label"><?php echo esc_html__( 'Membership', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-member-balance-card__value"><?php echo esc_html( $membership_type_name ); ?></span>
			</div>
			<div class="stsrc-member-balance-card__detail">
				<span class="stsrc-member-balance-card__label"><?php echo esc_html__( 'Season Price', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-member-balance-card__value">$<?php echo esc_html( number_format( $season_price, 2 ) ); ?></span>
			</div>
			<div class="stsrc-member-balance-card__detail">
				<span class="stsrc-member-balance-card__label"><?php echo esc_html__( 'Total Paid', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-member-balance-card__value">$<?php echo esc_html( number_format( $total_paid, 2 ) ); ?></span>
			</div>
			<div class="stsrc-member-balance-card__detail">
				<span class="stsrc-member-balance-card__label"><?php echo esc_html__( 'Remaining', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-member-balance-card__value stsrc-member-balance-card__value--remaining">
					$<?php echo esc_html( number_format( $remaining_balance, 2 ) ); ?>
				</span>
			</div>
		</div>

		<button type="button" class="stsrc-button stsrc-button-primary stsrc-pay-balance-btn">
			<?php echo esc_html__( 'Pay Balance', 'smoketree-plugin' ); ?>
		</button>
	</div>
</section>
