<?php
/**
 * Banner admin page template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$banner = $data['banner'] ?? array();
?>

<div class="wrap">
	<h1><?php echo esc_html__( 'Announcement Banner', 'smoketree-plugin' ); ?></h1>
	<p class="description" style="margin-bottom: 1.5rem;">
		<?php echo esc_html__( 'Display a site-wide banner at the top of every page. Useful for seasonal announcements, pool closures, or registration deadlines.', 'smoketree-plugin' ); ?>
	</p>

	<div id="stsrc-banner-save-result" style="display:none; margin-bottom: 1rem;"></div>

	<form method="post" id="stsrc-banner-form">
		<input type="hidden" name="action" value="stsrc_save_banner">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>">

		<table class="form-table">
			<tr>
				<th><label for="banner_enabled"><?php echo esc_html__( 'Enable Banner', 'smoketree-plugin' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="banner_enabled" id="banner_enabled" value="1" <?php checked( $banner['enabled'] ?? '0', '1' ); ?>>
						<?php echo esc_html__( 'Show banner on the site', 'smoketree-plugin' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="banner_message"><?php echo esc_html__( 'Message', 'smoketree-plugin' ); ?></label></th>
				<td>
					<textarea name="banner_message" id="banner_message" rows="3" class="large-text"><?php echo esc_textarea( $banner['message'] ?? '' ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'The announcement text. Plain text only.', 'smoketree-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="banner_size"><?php echo esc_html__( 'Size', 'smoketree-plugin' ); ?></label></th>
				<td>
					<select name="banner_size" id="banner_size">
						<option value="medium" <?php selected( $banner['size'] ?? 'medium', 'medium' ); ?>><?php echo esc_html__( 'Medium (default)', 'smoketree-plugin' ); ?></option>
						<option value="large"  <?php selected( $banner['size'] ?? '', 'large' ); ?>><?php echo esc_html__( 'Large', 'smoketree-plugin' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="banner_type"><?php echo esc_html__( 'Banner Type', 'smoketree-plugin' ); ?></label></th>
				<td>
					<select name="banner_type" id="banner_type">
						<option value="info"    <?php selected( $banner['type'] ?? 'info', 'info' ); ?>><?php echo esc_html__( 'Info (blue)', 'smoketree-plugin' ); ?></option>
						<option value="success" <?php selected( $banner['type'] ?? '', 'success' ); ?>><?php echo esc_html__( 'Success (green)', 'smoketree-plugin' ); ?></option>
						<option value="warning" <?php selected( $banner['type'] ?? '', 'warning' ); ?>><?php echo esc_html__( 'Warning (yellow)', 'smoketree-plugin' ); ?></option>
						<option value="alert"   <?php selected( $banner['type'] ?? '', 'alert' ); ?>><?php echo esc_html__( 'Alert (red)', 'smoketree-plugin' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="banner_audience"><?php echo esc_html__( 'Show To', 'smoketree-plugin' ); ?></label></th>
				<td>
					<select name="banner_audience" id="banner_audience">
						<option value="all"     <?php selected( $banner['audience'] ?? 'all', 'all' ); ?>><?php echo esc_html__( 'Everyone', 'smoketree-plugin' ); ?></option>
						<option value="members" <?php selected( $banner['audience'] ?? '', 'members' ); ?>><?php echo esc_html__( 'Logged-in members only', 'smoketree-plugin' ); ?></option>
						<option value="public"  <?php selected( $banner['audience'] ?? '', 'public' ); ?>><?php echo esc_html__( 'Logged-out visitors only', 'smoketree-plugin' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="banner_dismissible"><?php echo esc_html__( 'Dismissible', 'smoketree-plugin' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="banner_dismissible" id="banner_dismissible" value="1" <?php checked( $banner['dismissible'] ?? '1', '1' ); ?>>
						<?php echo esc_html__( 'Allow visitors to close the banner', 'smoketree-plugin' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'When unchecked, the banner cannot be dismissed. Use for urgent notices.', 'smoketree-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="banner_expiry_date"><?php echo esc_html__( 'Expiry Date', 'smoketree-plugin' ); ?></label></th>
				<td>
					<input type="date" name="banner_expiry_date" id="banner_expiry_date" value="<?php echo esc_attr( $banner['expiry_date'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php echo esc_html__( 'Banner auto-hides after this date. Leave blank for no expiry.', 'smoketree-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="banner_link_label"><?php echo esc_html__( 'Link Label', 'smoketree-plugin' ); ?></label></th>
				<td>
					<input type="text" name="banner_link_label" id="banner_link_label" value="<?php echo esc_attr( $banner['link_label'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'Learn more', 'smoketree-plugin' ); ?>">
					<p class="description"><?php echo esc_html__( 'Optional call-to-action link text. Leave blank to show no link.', 'smoketree-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="banner_link_url"><?php echo esc_html__( 'Link URL', 'smoketree-plugin' ); ?></label></th>
				<td>
					<input type="url" name="banner_link_url" id="banner_link_url" value="<?php echo esc_attr( $banner['link_url'] ?? '' ); ?>" class="large-text" placeholder="https://">
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" id="stsrc-banner-submit" class="button button-primary" value="<?php echo esc_attr__( 'Save Banner', 'smoketree-plugin' ); ?>">
		</p>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	$('#stsrc-banner-form').on('submit', function(e) {
		e.preventDefault();

		var $btn = $('#stsrc-banner-submit');
		var $result = $('#stsrc-banner-save-result');

		$btn.prop('disabled', true).val('<?php echo esc_js( __( 'Saving...', 'smoketree-plugin' ) ); ?>');
		$result.hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: $(this).serialize(),
			success: function(response) {
				var cls = response.success ? 'notice-success' : 'notice-error';
				var msg = response.data ? response.data.message : '<?php echo esc_js( __( 'An error occurred.', 'smoketree-plugin' ) ); ?>';
				$result.html('<div class="notice ' + cls + ' inline"><p>' + $('<span>').text(msg).html() + '</p></div>').show();
			},
			error: function() {
				$result.html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Request failed. Please try again.', 'smoketree-plugin' ) ); ?></p></div>').show();
			},
			complete: function() {
				$btn.prop('disabled', false).val('<?php echo esc_js( __( 'Save Banner', 'smoketree-plugin' ) ); ?>');
			}
		});
	});
});
</script>
