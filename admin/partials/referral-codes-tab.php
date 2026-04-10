<?php
/**
 * Referral Codes tab partial.
 *
 * @package Smoketree_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type_rows              = $data['type_rows'] ?? array();
$affiliate_settings     = $data['affiliate_settings'] ?? array();
$affiliate_discounts    = $affiliate_settings['type_discounts'] ?? array();
$referrer_credit        = $affiliate_settings['referrer_credit'] ?? 50;
$members                = $data['affiliate_members'] ?? array();
$member_search          = $data['member_search'] ?? '';
$saved                  = $data['referral_settings_saved'] ?? false;
$type_labels            = $data['type_labels'] ?? array();
?>

<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Referral settings saved.', 'smoketree-plugin' ); ?></p></div>
<?php endif; ?>

<!-- ── Settings ────────────────────────────────────────────────────── -->
<div class="stsrc-form-section" style="margin-top:1.5rem;">
	<h2><?php esc_html_e( 'Referral Settings', 'smoketree-plugin' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-promo-codes&tab=referral-codes' ) ); ?>">
		<?php wp_nonce_field( 'stsrc_save_referral_settings', 'stsrc_referral_settings_nonce' ); ?>

		<table class="form-table" style="max-width:700px;">
			<tr>
				<th style="width:260px;">
					<?php esc_html_e( 'Referral Discount per Membership Type', 'smoketree-plugin' ); ?>
				</th>
				<td>
					<table class="widefat stsrc-affiliate-type-discounts-table" style="max-width:480px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Membership Type', 'smoketree-plugin' ); ?></th>
								<th><?php esc_html_e( 'Price', 'smoketree-plugin' ); ?></th>
								<th><?php esc_html_e( 'New-Member Discount ($)', 'smoketree-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $type_rows as $mt ) : ?>
								<?php $mt_id = (int) $mt['membership_type_id']; ?>
								<tr>
									<td><?php echo esc_html( (string) $mt['name'] ); ?></td>
									<td>$<?php echo esc_html( number_format( (float) $mt['price'], 2 ) ); ?></td>
									<td>
										<input
											type="number"
											name="affiliate_type_discounts[<?php echo esc_attr( (string) $mt_id ); ?>]"
											value="<?php echo esc_attr( isset( $affiliate_discounts[ (string) $mt_id ] ) ? (string) $affiliate_discounts[ (string) $mt_id ] : '' ); ?>"
											class="small-text"
											step="0.01"
											min="0"
											placeholder="0"
										>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Dollar discount applied to a new member who registers with a valid referral code, per membership type. Leave blank for no discount.', 'smoketree-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="stsrc_affiliate_referrer_credit"><?php esc_html_e( 'Referrer Credit Amount ($)', 'smoketree-plugin' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						name="affiliate_referrer_credit"
						id="stsrc_affiliate_referrer_credit"
						value="<?php echo esc_attr( (string) $referrer_credit ); ?>"
						class="regular-text"
						step="0.01"
						min="0"
					>
					<p class="description"><?php esc_html_e( 'Dollar credit owed to the referring member for each successful referral.', 'smoketree-plugin' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Referral Settings', 'smoketree-plugin' ) ); ?>
	</form>
</div>

<hr>

<!-- ── Member Referral Codes ────────────────────────────────────────── -->
<div class="stsrc-form-section" style="margin-top:1.5rem;">
	<h2><?php esc_html_e( 'Member Referral Codes', 'smoketree-plugin' ); ?></h2>

	<form method="get" class="stsrc-promo-filters" style="margin-bottom:1rem;">
		<input type="hidden" name="page" value="stsrc-promo-codes">
		<input type="hidden" name="tab" value="referral-codes">
		<input
			type="search"
			name="ms"
			value="<?php echo esc_attr( $member_search ); ?>"
			placeholder="<?php esc_attr_e( 'Search by name or email…', 'smoketree-plugin' ); ?>"
			style="width:280px;"
		>
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'smoketree-plugin' ); ?></button>
		<?php if ( '' !== $member_search ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-promo-codes&tab=referral-codes' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'smoketree-plugin' ); ?></a>
		<?php endif; ?>
	</form>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Member', 'smoketree-plugin' ); ?></th>
				<th><?php esc_html_e( 'Email', 'smoketree-plugin' ); ?></th>
				<th><?php esc_html_e( 'Membership Type', 'smoketree-plugin' ); ?></th>
				<th><?php esc_html_e( 'Status', 'smoketree-plugin' ); ?></th>
				<th><?php esc_html_e( 'Referral Code', 'smoketree-plugin' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'smoketree-plugin' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $members ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No members found.', 'smoketree-plugin' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $members as $member ) : ?>
					<?php
					$member_id   = (int) $member['member_id'];
					$type_id     = (int) ( $member['membership_type_id'] ?? 0 );
					$type_label  = $type_labels[ $type_id ] ?? '—';
					$has_code    = ! empty( $member['affiliate_code'] );
					?>
					<tr id="stsrc-member-row-<?php echo esc_attr( (string) $member_id ); ?>">
						<td><?php echo esc_html( $member['first_name'] . ' ' . $member['last_name'] ); ?></td>
						<td><?php echo esc_html( (string) $member['email'] ); ?></td>
						<td><?php echo esc_html( $type_label ); ?></td>
						<td><?php echo esc_html( ucfirst( (string) ( $member['status'] ?? '' ) ) ); ?></td>
						<td>
							<code id="stsrc-aff-code-<?php echo esc_attr( (string) $member_id ); ?>">
								<?php echo $has_code ? esc_html( (string) $member['affiliate_code'] ) : '<em>' . esc_html__( 'None', 'smoketree-plugin' ) . '</em>'; ?>
							</code>
						</td>
						<td>
							<button
								type="button"
								class="button button-small stsrc-reset-affiliate-code"
								data-member-id="<?php echo esc_attr( (string) $member_id ); ?>"
								data-member-name="<?php echo esc_attr( $member['first_name'] . ' ' . $member['last_name'] ); ?>"
							>
								<?php echo $has_code ? esc_html__( 'Regenerate', 'smoketree-plugin' ) : esc_html__( 'Generate', 'smoketree-plugin' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
