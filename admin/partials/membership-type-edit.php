<?php
/**
 * Membership type edit form template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$membership_type = $data['membership_type'] ?? null;
$benefits = $data['benefits'] ?? array();
$is_edit = ! empty( $membership_type );
$membership_type_id = $membership_type['membership_type_id'] ?? 0;
$selected_benefits = $membership_type['benefits'] ?? array();
?>

<div class="wrap">
	<h1>
		<?php
		if ( $is_edit ) {
			echo esc_html__( 'Edit Membership Type', 'smoketree-plugin' );
		} else {
			echo esc_html__( 'Add New Membership Type', 'smoketree-plugin' );
		}
		?>
	</h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="stsrc-membership-type-form" class="stsrc-membership-type-form">
		<input type="hidden" name="action" value="<?php echo $is_edit ? 'stsrc_update_membership_type' : 'stsrc_create_membership_type'; ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="membership_type_id" value="<?php echo esc_attr( $membership_type_id ); ?>">
		<?php endif; ?>

		<div class="stsrc-form-sections">
			<!-- Basic Information -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Basic Information', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="name"><?php echo esc_html__( 'Name', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="text" name="name" id="name" value="<?php echo esc_attr( $membership_type['name'] ?? '' ); ?>" required class="regular-text">
							<p class="description"><?php echo esc_html__( 'Unique name for this membership type (e.g., "Household", "Duo", "Single").', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="description"><?php echo esc_html__( 'Description', 'smoketree-plugin' ); ?></label></th>
						<td>
							<textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $membership_type['description'] ?? '' ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Description of this membership type (displayed on registration page).', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="price"><?php echo esc_html__( 'Price', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="number" name="price" id="price" value="<?php echo esc_attr( $membership_type['price'] ?? '0.00' ); ?>" step="0.01" min="0" required class="small-text">
							<p class="description"><?php echo esc_html__( 'Price in USD (e.g., 500.00).', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="expiration_period"><?php echo esc_html__( 'Expiration Period (Days)', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="number" name="expiration_period" id="expiration_period" value="<?php echo esc_attr( $membership_type['expiration_period'] ?? '365' ); ?>" min="1" required class="small-text">
							<p class="description"><?php echo esc_html__( 'Number of days until membership expires (e.g., 365 for annual).', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Stripe Integration -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Stripe Integration', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="stripe_product_id"><?php echo esc_html__( 'Stripe Product ID', 'smoketree-plugin' ); ?></label></th>
						<td>
							<input type="text" name="stripe_product_id" id="stripe_product_id" value="<?php echo esc_attr( $membership_type['stripe_product_id'] ?? '' ); ?>" class="regular-text">
							<p class="description"><?php echo esc_html__( 'Stripe Product ID for this membership type (optional).', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Display Options -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Display Options', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="is_selectable"><?php echo esc_html__( 'Is Selectable', 'smoketree-plugin' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="is_selectable" id="is_selectable" value="1" <?php checked( $membership_type['is_selectable'] ?? true, true ); ?>>
								<?php echo esc_html__( 'Show this membership type on the registration page', 'smoketree-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="is_best_seller"><?php echo esc_html__( 'Mark as Best Seller', 'smoketree-plugin' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="is_best_seller" id="is_best_seller" value="1" <?php checked( $membership_type['is_best_seller'] ?? false, true ); ?>>
								<?php echo esc_html__( 'Mark this membership type as a best seller', 'smoketree-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="can_have_additional_members"><?php echo esc_html__( 'Can Have Additional Members', 'smoketree-plugin' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="can_have_additional_members" id="can_have_additional_members" value="1" <?php checked( $membership_type['can_have_additional_members'] ?? false, true ); ?>>
								<?php echo esc_html__( 'Allow family and extra members for this membership type', 'smoketree-plugin' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<!-- Benefits -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Benefits', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Select Benefits', 'smoketree-plugin' ); ?></th>
						<td>
							<fieldset>
								<?php
								// Handle both key-based and label-based stored benefits
								// ACF might store labels, our system stores keys
								$normalized_selected = array();
								foreach ( $selected_benefits as $selected ) {
									// If it's already a key, use it; if it's a label, find the key
									if ( isset( $benefits[ $selected ] ) ) {
										$normalized_selected[] = $selected;
									} else {
										// Try to find by label
										foreach ( $benefits as $key => $label ) {
											if ( $label === $selected ) {
												$normalized_selected[] = $key;
												break;
											}
										}
									}
								}
								?>
								<?php foreach ( $benefits as $benefit_key => $benefit_label ) : ?>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" name="benefits[]" value="<?php echo esc_attr( $benefit_key ); ?>" <?php checked( in_array( $benefit_key, $normalized_selected, true ), true ); ?>>
										<?php echo esc_html( $benefit_label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save Membership Type', 'smoketree-plugin' ); ?>">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-memberships' ) ); ?>" class="button">
				<?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?>
			</a>
		</p>
	</form>
</div>

