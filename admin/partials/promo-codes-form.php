<?php
/**
 * Promo codes modal form partial.
 *
 * @package Smoketree_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type_rows = $data['type_rows'] ?? array();
?>

<div id="stsrc-promo-code-modal" class="stsrc-promo-modal" style="display:none;" aria-hidden="true">
	<div class="stsrc-promo-modal__overlay"></div>
	<div class="stsrc-promo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="stsrc-promo-modal-title">
		<button type="button" class="stsrc-promo-modal__close" aria-label="<?php echo esc_attr__( 'Close modal', 'smoketree-plugin' ); ?>">&times;</button>
		<h2 id="stsrc-promo-modal-title"><?php echo esc_html__( 'Add Promo Code', 'smoketree-plugin' ); ?></h2>
		<form id="stsrc-promo-code-form">
			<input type="hidden" name="code_id" id="stsrc_promo_code_id" value="">
			<div class="stsrc-promo-form-grid">
				<div>
					<label for="stsrc_code_name"><?php echo esc_html__( 'Code Name', 'smoketree-plugin' ); ?></label>
					<input type="text" id="stsrc_code_name" name="code_name" required maxlength="50">
				</div>
				<div>
					<label><?php echo esc_html__( 'Discount Type', 'smoketree-plugin' ); ?></label>
					<label><input type="radio" name="discount_type" value="flat" checked> <?php echo esc_html__( 'Flat ($)', 'smoketree-plugin' ); ?></label>
					<label><input type="radio" name="discount_type" value="percentage"> <?php echo esc_html__( 'Percentage (%)', 'smoketree-plugin' ); ?></label>
				</div>
				<div class="stsrc-promo-form-grid__full">
					<label><?php echo esc_html__( 'Discount per Membership Type', 'smoketree-plugin' ); ?></label>
					<p class="description"><?php echo esc_html__( 'Enter a discount value for each type that should receive a discount. Leave blank to exclude a type.', 'smoketree-plugin' ); ?></p>
					<table class="stsrc-discount-values-table widefat" id="stsrc-discount-values-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Price', 'smoketree-plugin' ); ?></th>
								<th class="stsrc-discount-values-table__value-col">
									<span id="stsrc-discount-col-label"><?php echo esc_html__( 'Discount ($)', 'smoketree-plugin' ); ?></span>
								</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $type_rows as $row ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $row['name'] ); ?></td>
									<td>$<?php echo esc_html( number_format( (float) $row['price'], 2 ) ); ?></td>
									<td>
										<input type="number"
											name="discount_values[<?php echo esc_attr( (int) $row['membership_type_id'] ); ?>]"
											class="stsrc-discount-type-value"
											data-type-id="<?php echo esc_attr( (int) $row['membership_type_id'] ); ?>"
											min="0" step="0.01" placeholder="0">
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div>
					<label for="stsrc_expires_at"><?php echo esc_html__( 'Expiration Date', 'smoketree-plugin' ); ?></label>
					<input type="date" id="stsrc_expires_at" name="expires_at">
				</div>
				<div>
					<label><input type="checkbox" id="stsrc_is_one_time_use" name="is_one_time_use" value="1"> <?php echo esc_html__( 'One-time use', 'smoketree-plugin' ); ?></label>
				</div>
				<div>
					<label for="stsrc_usage_limit"><?php echo esc_html__( 'Usage Limit', 'smoketree-plugin' ); ?></label>
					<input type="number" id="stsrc_usage_limit" name="usage_limit" min="1" step="1">
				</div>
				<div class="stsrc-promo-form-grid__full">
					<label><input type="checkbox" id="stsrc_is_active" name="is_active" value="1" checked> <?php echo esc_html__( 'Code is active', 'smoketree-plugin' ); ?></label>
				</div>
			</div>
			<div class="stsrc-promo-modal__actions">
				<button type="submit" class="button button-primary" id="stsrc-save-promo-code"><?php echo esc_html__( 'Save Promo Code', 'smoketree-plugin' ); ?></button>
				<button type="button" class="button stsrc-promo-modal-cancel"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
			</div>
		</form>
	</div>
</div>

