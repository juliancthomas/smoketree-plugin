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
$search      = $data['search'] ?? '';
$is_active   = $data['is_active'];
$type_labels = $data['type_labels'] ?? array();
$type_rows   = $data['type_rows'] ?? array();
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Promo Codes', 'smoketree-plugin' ); ?></h1>
	<button type="button" class="page-title-action" id="stsrc-open-promo-modal">
		<?php echo esc_html__( 'Add New', 'smoketree-plugin' ); ?>
	</button>
	<hr class="wp-header-end">

	<form method="get" class="stsrc-promo-filters">
		<input type="hidden" name="page" value="stsrc-promo-codes">
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
				<th><?php echo esc_html__( 'Value', 'smoketree-plugin' ); ?></th>
				<th><?php echo esc_html__( 'Uses', 'smoketree-plugin' ); ?></th>
				<th><?php echo esc_html__( 'Expires', 'smoketree-plugin' ); ?></th>
				<th><?php echo esc_html__( 'Membership Restriction', 'smoketree-plugin' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'smoketree-plugin' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $codes ) ) : ?>
				<tr>
					<td colspan="8"><?php echo esc_html__( 'No promo codes found.', 'smoketree-plugin' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $codes as $code ) : ?>
					<?php
					$allowed_types = array();
					if ( ! empty( $code->allowed_type_ids ) ) {
						$decoded = json_decode( (string) $code->allowed_type_ids, true );
						if ( is_array( $decoded ) ) {
							foreach ( $decoded as $type_id ) {
								$type_id = (int) $type_id;
								if ( isset( $type_labels[ $type_id ] ) ) {
									$allowed_types[] = $type_labels[ $type_id ];
								}
							}
						}
					}
					$row_payload = array(
						'code_id'           => (int) $code->code_id,
						'code_name'         => (string) $code->code_name,
						'discount_type'     => (string) $code->discount_type,
						'discount_value'    => (float) $code->discount_value,
						'expires_at'        => (string) ( $code->expires_at ?? '' ),
						'is_one_time_use'   => (int) $code->is_one_time_use,
						'usage_limit'       => null !== $code->usage_limit ? (int) $code->usage_limit : null,
						'allowed_type_ids'  => ! empty( $code->allowed_type_ids ) ? json_decode( (string) $code->allowed_type_ids, true ) : array(),
						'is_active'         => (int) $code->is_active,
					);
					?>
					<tr data-code-id="<?php echo esc_attr( (int) $code->code_id ); ?>">
						<td><strong><?php echo esc_html( (string) $code->code_name ); ?></strong></td>
						<td><?php echo esc_html( ucfirst( (string) $code->discount_type ) ); ?></td>
						<td>
							<?php
							if ( 'percentage' === $code->discount_type ) {
								echo esc_html( number_format_i18n( (float) $code->discount_value, 2 ) . '%' );
							} else {
								echo esc_html( '$' . number_format_i18n( (float) $code->discount_value, 2 ) );
							}
							?>
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
							<?php echo ! empty( $allowed_types ) ? esc_html( implode( ', ', $allowed_types ) ) : esc_html__( 'All Types', 'smoketree-plugin' ); ?>
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
</div>

<?php include plugin_dir_path( __FILE__ ) . 'promo-codes-form.php'; ?>

