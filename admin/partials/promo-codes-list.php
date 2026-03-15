<?php
/**
 * Promo codes list partial.
 *
 * @package Smoketree_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$codes       = $data['codes'] ?? array();
$tab         = $data['tab'] ?? 'promo-codes';
$search      = $data['search'] ?? '';
$is_active   = $data['is_active'];
$type_labels = $data['type_labels'] ?? array();
$type_rows   = $data['type_rows'] ?? array();
$referrals   = $data['referrals'] ?? array();
$payout      = $data['payout'] ?? '';
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Promo Codes', 'smoketree-plugin' ); ?></h1>
	<?php if ( 'promo-codes' === $tab ) : ?>
		<button type="button" class="page-title-action" id="stsrc-open-promo-modal">
			<?php echo esc_html__( 'Add New', 'smoketree-plugin' ); ?>
		</button>
	<?php endif; ?>
	<hr class="wp-header-end">

	<h2 class="nav-tab-wrapper stsrc-promo-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-promo-codes&tab=promo-codes' ) ); ?>" class="nav-tab <?php echo 'promo-codes' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html__( 'Promo Codes', 'smoketree-plugin' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-promo-codes&tab=referrals' ) ); ?>" class="nav-tab <?php echo 'referrals' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html__( 'Referral Report', 'smoketree-plugin' ); ?>
		</a>
	</h2>

	<?php if ( 'referrals' === $tab ) : ?>
		<?php include plugin_dir_path( __FILE__ ) . 'affiliate-referrals-report.php'; ?>
	<?php else : ?>
		<form method="get" class="stsrc-promo-filters">
			<input type="hidden" name="page" value="stsrc-promo-codes">
			<input type="hidden" name="tab" value="promo-codes">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search code name...', 'smoketree-plugin' ); ?>">
			<select name="is_active">
				<option value=""><?php echo esc_html__( 'All statuses', 'smoketree-plugin' ); ?></option>
				<option value="1" <?php selected( $is_active, 1 ); ?>><?php echo esc_html__( 'Active', 'smoketree-plugin' ); ?></option>
				<option value="0" <?php selected( $is_active, 0 ); ?>><?php echo esc_html__( 'Inactive', 'smoketree-plugin' ); ?></option>
			</select>
			<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'smoketree-plugin' ); ?></button>
		</form>

		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Code Name', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Discounts', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Uses', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Expires', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Actions', 'smoketree-plugin' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $codes ) ) : ?>
					<tr>
						<td colspan="7"><?php echo esc_html__( 'No promo codes found.', 'smoketree-plugin' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $codes as $code ) : ?>
						<?php
						$discount_values = json_decode( (string) ( $code->discount_values ?? '{}' ), true );
						if ( ! is_array( $discount_values ) ) {
							$discount_values = array();
						}

						$discount_summary = array();
						foreach ( $discount_values as $type_id => $value ) {
							$type_id = (int) $type_id;
							$label   = isset( $type_labels[ $type_id ] ) ? $type_labels[ $type_id ] : ( '#' . $type_id );
							if ( 'percentage' === $code->discount_type ) {
								$discount_summary[] = $label . ': ' . number_format_i18n( (float) $value, 2 ) . '%';
							} else {
								$discount_summary[] = $label . ': $' . number_format_i18n( (float) $value, 2 );
							}
						}

						$row_payload = array(
							'code_id'         => (int) $code->code_id,
							'code_name'       => (string) $code->code_name,
							'discount_type'   => (string) $code->discount_type,
							'discount_values' => $discount_values,
							'expires_at'      => (string) ( $code->expires_at ?? '' ),
							'is_one_time_use' => (int) $code->is_one_time_use,
							'usage_limit'     => null !== $code->usage_limit ? (int) $code->usage_limit : null,
							'is_active'       => (int) $code->is_active,
						);
						?>
						<tr data-code-id="<?php echo esc_attr( (int) $code->code_id ); ?>">
							<td><strong><?php echo esc_html( (string) $code->code_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( (string) $code->discount_type ) ); ?></td>
							<td>
								<?php if ( ! empty( $discount_summary ) ) : ?>
									<?php echo esc_html( implode( ', ', $discount_summary ) ); ?>
								<?php else : ?>
									<em><?php echo esc_html__( 'None', 'smoketree-plugin' ); ?></em>
								<?php endif; ?>
							</td>
							<td>
								<?php
								echo esc_html( (int) $code->usage_count );
								echo ' / ';
								echo null === $code->usage_limit ? esc_html__( 'Unlimited', 'smoketree-plugin' ) : esc_html( (int) $code->usage_limit );
								?>
							</td>
							<td>
								<?php echo ! empty( $code->expires_at ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) $code->expires_at ) ) ) : esc_html__( 'Never', 'smoketree-plugin' ); ?>
							</td>
							<td>
								<span class="stsrc-status-badge <?php echo (int) $code->is_active ? 'is-active' : 'is-inactive'; ?>">
									<?php echo (int) $code->is_active ? esc_html__( 'Active', 'smoketree-plugin' ) : esc_html__( 'Inactive', 'smoketree-plugin' ); ?>
								</span>
							</td>
							<td>
								<button type="button" class="button-link stsrc-edit-promo-code" data-code="<?php echo esc_attr( wp_json_encode( $row_payload ) ); ?>">
									<?php echo esc_html__( 'Edit', 'smoketree-plugin' ); ?>
								</button>
								|
								<button type="button" class="button-link stsrc-toggle-promo-code" data-id="<?php echo esc_attr( (int) $code->code_id ); ?>" data-next="<?php echo esc_attr( (int) $code->is_active ? 0 : 1 ); ?>">
									<?php echo (int) $code->is_active ? esc_html__( 'Deactivate', 'smoketree-plugin' ) : esc_html__( 'Activate', 'smoketree-plugin' ); ?>
								</button>
								|
								<button type="button" class="button-link-delete stsrc-delete-promo-code" data-id="<?php echo esc_attr( (int) $code->code_id ); ?>">
									<?php echo esc_html__( 'Delete', 'smoketree-plugin' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<?php include plugin_dir_path( __FILE__ ) . 'promo-codes-form.php'; ?>

