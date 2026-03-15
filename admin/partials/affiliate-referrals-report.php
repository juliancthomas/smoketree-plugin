<?php
/**
 * Affiliate referrals report partial.
 *
 * @package Smoketree_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referrals = $referrals ?? array();
$payout    = $payout ?? '';
?>

<form method="get" class="stsrc-promo-filters">
	<input type="hidden" name="page" value="stsrc-promo-codes">
	<input type="hidden" name="tab" value="referrals">
	<select name="payout_status">
		<option value=""><?php echo esc_html__( 'All payout statuses', 'smoketree-plugin' ); ?></option>
		<option value="pending" <?php selected( $payout, 'pending' ); ?>><?php echo esc_html__( 'Pending', 'smoketree-plugin' ); ?></option>
		<option value="paid" <?php selected( $payout, 'paid' ); ?>><?php echo esc_html__( 'Paid', 'smoketree-plugin' ); ?></option>
	</select>
	<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'smoketree-plugin' ); ?></button>
</form>

<table class="wp-list-table widefat fixed striped table-view-list">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'Referrer Name', 'smoketree-plugin' ); ?></th>
			<th><?php echo esc_html__( 'New Member Name', 'smoketree-plugin' ); ?></th>
			<th><?php echo esc_html__( 'Date', 'smoketree-plugin' ); ?></th>
			<th><?php echo esc_html__( 'Discount Given ($)', 'smoketree-plugin' ); ?></th>
			<th><?php echo esc_html__( 'Credit Owed ($)', 'smoketree-plugin' ); ?></th>
			<th><?php echo esc_html__( 'Payout Status', 'smoketree-plugin' ); ?></th>
			<th><?php echo esc_html__( 'Action', 'smoketree-plugin' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $referrals ) ) : ?>
			<tr>
				<td colspan="7"><?php echo esc_html__( 'No referrals found for this filter.', 'smoketree-plugin' ); ?></td>
			</tr>
		<?php else : ?>
			<?php foreach ( $referrals as $referral ) : ?>
				<tr data-referral-id="<?php echo esc_attr( (int) $referral->referral_id ); ?>">
					<td><?php echo esc_html( (string) $referral->referrer_name ); ?></td>
					<td><?php echo esc_html( (string) $referral->new_member_name ); ?></td>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) $referral->referred_at ) ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $referral->new_member_discount, 2 ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $referral->referrer_credit, 2 ) ); ?></td>
					<td>
						<span class="stsrc-status-badge <?php echo 'paid' === $referral->payout_status ? 'is-active' : 'is-inactive'; ?>">
							<?php echo 'paid' === $referral->payout_status ? esc_html__( 'Paid', 'smoketree-plugin' ) : esc_html__( 'Pending', 'smoketree-plugin' ); ?>
						</span>
					</td>
					<td>
						<?php $next_status = 'paid' === $referral->payout_status ? 'pending' : 'paid'; ?>
						<button
							type="button"
							class="button button-small stsrc-toggle-payout-status"
							data-id="<?php echo esc_attr( (int) $referral->referral_id ); ?>"
							data-next="<?php echo esc_attr( $next_status ); ?>"
						>
							<?php echo 'paid' === $referral->payout_status ? esc_html__( 'Revert to Pending', 'smoketree-plugin' ) : esc_html__( 'Mark as Paid', 'smoketree-plugin' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

