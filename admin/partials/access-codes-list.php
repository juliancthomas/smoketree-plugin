<?php
/**
 * Access codes list template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$access_codes = $data['access_codes'] ?? array();
$access_code = $data['access_code'] ?? null;
$is_edit = ! empty( $access_code );
$code_id = $access_code['code_id'] ?? 0;
$action = isset( $_GET['action'] ) && $_GET['action'] === 'edit' ? 'edit' : 'list';
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Access Codes', 'smoketree-plugin' ); ?></h1>
	<?php if ( 'list' === $action ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-access-codes&action=edit' ) ); ?>" class="page-title-action">
			<?php echo esc_html__( 'Add New', 'smoketree-plugin' ); ?>
		</a>
	<?php else : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-access-codes' ) ); ?>" class="page-title-action">
			<?php echo esc_html__( 'Back to List', 'smoketree-plugin' ); ?>
		</a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( 'edit' === $action ) : ?>
		<!-- Edit Form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="stsrc-access-code-form" class="stsrc-access-code-form">
			<input type="hidden" name="action" value="<?php echo $is_edit ? 'stsrc_update_access_code' : 'stsrc_create_access_code'; ?>">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>">
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="code_id" value="<?php echo esc_attr( $code_id ); ?>">
			<?php endif; ?>

			<div class="stsrc-form-sections">
				<div class="stsrc-form-section">
					<h2>
						<?php
						if ( $is_edit ) {
							echo esc_html__( 'Edit Access Code', 'smoketree-plugin' );
						} else {
							echo esc_html__( 'Add New Access Code', 'smoketree-plugin' );
						}
						?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="code"><?php echo esc_html__( 'Code', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
							<td>
								<input type="text" name="code" id="code" value="<?php echo esc_attr( $access_code['code'] ?? '' ); ?>" required class="regular-text">
								<p class="description"><?php echo esc_html__( 'The access code that members will use.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="description"><?php echo esc_html__( 'Description', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="description" id="description" value="<?php echo esc_attr( $access_code['description'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'Optional description for this access code.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="expires_at"><?php echo esc_html__( 'Expiration Date', 'smoketree-plugin' ); ?></label></th>
							<td>
								<?php
								$expires_value = '';
								if ( ! empty( $access_code['expires_at'] ) ) {
									// Convert MySQL datetime to datetime-local format (YYYY-MM-DDTHH:MM)
									$expires_value = date( 'Y-m-d\TH:i', strtotime( $access_code['expires_at'] ) );
								}
								?>
								<input type="datetime-local" name="expires_at" id="expires_at" value="<?php echo esc_attr( $expires_value ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Optional expiration date and time. Leave blank for no expiration.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="is_active"><?php echo esc_html__( 'Active', 'smoketree-plugin' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="is_active" id="is_active" value="1" <?php checked( $access_code['is_active'] ?? true, true ); ?>>
									<?php echo esc_html__( 'Code is active and can be used', 'smoketree-plugin' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="is_premium"><?php echo esc_html__( 'Premium Access', 'smoketree-plugin' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="is_premium" id="is_premium" value="1" <?php checked( ! empty( $access_code['is_premium'] ), true ); ?>>
									<?php echo esc_html__( 'Restrict this code to memberships with pool access', 'smoketree-plugin' ); ?>
								</label>
								<p class="description"><?php echo esc_html__( 'Premium codes are only shown to memberships that include pool access.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save Access Code', 'smoketree-plugin' ); ?>">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-access-codes' ) ); ?>" class="button">
							<?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?>
						</a>
					</p>
				</div>
			</div>
		</form>
	<?php else : ?>
		<!-- Access Codes Table -->
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th class="manage-column"><?php echo esc_html__( 'Code', 'smoketree-plugin' ); ?></th>
					<th class="manage-column"><?php echo esc_html__( 'Description', 'smoketree-plugin' ); ?></th>
					<th class="manage-column"><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></th>
					<th class="manage-column"><?php echo esc_html__( 'Access Level', 'smoketree-plugin' ); ?></th>
					<th class="manage-column"><?php echo esc_html__( 'Expires At', 'smoketree-plugin' ); ?></th>
					<th class="manage-column"><?php echo esc_html__( 'Created', 'smoketree-plugin' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $access_codes ) ) : ?>
					<?php foreach ( $access_codes as $code ) : ?>
						<tr>
							<td class="column-code">
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-access-codes&action=edit&code_id=' . $code['code_id'] ) ); ?>">
										<?php echo esc_html( $code['code'] ); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-access-codes&action=edit&code_id=' . $code['code_id'] ) ); ?>">
											<?php echo esc_html__( 'Edit', 'smoketree-plugin' ); ?>
										</a> |
									</span>
									<span class="delete">
										<a href="#" class="stsrc-delete-access-code" data-id="<?php echo esc_attr( $code['code_id'] ); ?>" data-code="<?php echo esc_attr( $code['code'] ); ?>">
											<?php echo esc_html__( 'Delete', 'smoketree-plugin' ); ?>
										</a>
									</span>
								</div>
							</td>
							<td><?php echo esc_html( $code['description'] ?? '' ); ?></td>
							<td>
								<?php
								$is_expired = ! empty( $code['expires_at'] ) && $code['expires_at'] < current_time( 'mysql' );
								if ( $is_expired ) {
									echo '<span class="stsrc-status stsrc-status-expired">' . esc_html__( 'Expired', 'smoketree-plugin' ) . '</span>';
								} elseif ( ! empty( $code['is_active'] ) ) {
									echo '<span class="stsrc-status stsrc-status-active">' . esc_html__( 'Active', 'smoketree-plugin' ) . '</span>';
								} else {
									echo '<span class="stsrc-status stsrc-status-inactive">' . esc_html__( 'Inactive', 'smoketree-plugin' ) . '</span>';
								}
								?>
							</td>
							<td>
								<?php if ( ! empty( $code['is_premium'] ) ) : ?>
									<span class="stsrc-status stsrc-status-premium"><?php echo esc_html__( 'Premium', 'smoketree-plugin' ); ?></span>
								<?php else : ?>
									<span class="stsrc-status stsrc-status-standard"><?php echo esc_html__( 'Standard', 'smoketree-plugin' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $code['expires_at'] ) ) : ?>
									<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $code['expires_at'] ) ) ); ?>
									<?php if ( $is_expired ) : ?>
										<br><small style="color: red;"><?php echo esc_html__( '(Expired)', 'smoketree-plugin' ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span class="description"><?php echo esc_html__( 'Never', 'smoketree-plugin' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $code['created_at'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="6"><?php echo esc_html__( 'No access codes found.', 'smoketree-plugin' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
	// Delete access code
	$('.stsrc-delete-access-code').on('click', function(e) {
		e.preventDefault();
		
		var codeId = $(this).data('id');
		var code = $(this).data('code');
		
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete access code "', 'smoketree-plugin' ) ); ?>' + code + '<?php echo esc_js( __( '"? This action cannot be undone.', 'smoketree-plugin' ) ); ?>')) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'stsrc_delete_access_code',
				nonce: '<?php echo esc_js( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>',
				code_id: codeId
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert('<?php echo esc_js( __( 'Error: ', 'smoketree-plugin' ) ); ?>' + response.data.message);
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Error deleting access code', 'smoketree-plugin' ) ); ?>');
			}
		});
	});

	// Handle form submission
	$('#stsrc-access-code-form').on('submit', function(e) {
		e.preventDefault();
		
		var formData = $(this).serialize();
		var $submitBtn = $('#submit');
		$submitBtn.prop('disabled', true).val('<?php echo esc_js( __( 'Saving...', 'smoketree-plugin' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=stsrc-access-codes' ) ); ?>';
				} else {
					alert('<?php echo esc_js( __( 'Error: ', 'smoketree-plugin' ) ); ?>' + response.data.message);
					$submitBtn.prop('disabled', false).val('<?php echo esc_js( __( 'Save Access Code', 'smoketree-plugin' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Error saving access code', 'smoketree-plugin' ) ); ?>');
				$submitBtn.prop('disabled', false).val('<?php echo esc_js( __( 'Save Access Code', 'smoketree-plugin' ) ); ?>');
			}
		});
	});
});
</script>

