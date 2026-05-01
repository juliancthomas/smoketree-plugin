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
							<option value="small"      <?php selected( $banner['size'] ?? 'small', 'small' ); ?>><?php echo esc_html__( 'Small (default)', 'smoketree-plugin' ); ?></option>
							<option value="medium"     <?php selected( $banner['size'] ?? '', 'medium' ); ?>><?php echo esc_html__( 'Medium', 'smoketree-plugin' ); ?></option>
							<option value="large"      <?php selected( $banner['size'] ?? '', 'large' ); ?>><?php echo esc_html__( 'Large', 'smoketree-plugin' ); ?></option>
							<option value="xl"         <?php selected( $banner['size'] ?? '', 'xl' ); ?>><?php echo esc_html__( 'XL', 'smoketree-plugin' ); ?></option>
							<option value="fullscreen" <?php selected( $banner['size'] ?? '', 'fullscreen' ); ?>><?php echo esc_html__( 'Full Screen', 'smoketree-plugin' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="banner_type"><?php echo esc_html__( 'Banner Type', 'smoketree-plugin' ); ?></label></th>
					<td>
						<select name="banner_type" id="banner_type">
							<option value="info"    <?php selected( $banner['type'] ?? 'info', 'info' ); ?>><?php echo esc_html__( 'Info (blue)', 'smoketree-plugin' ); ?></option>
							<option value="success" <?php selected( $banner['type'] ?? '', 'success' ); ?>><?php echo esc_html__( 'Success (green)', 'smoketree-plugin' ); ?></option>
							<option value="warning" <?php selected( $banner['type'] ?? '', 'warning' ); ?>><?php echo esc_html__( 'Warning (orange)', 'smoketree-plugin' ); ?></option>
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
					<th><label for="banner_resession"><?php echo esc_html__( 'Re-show on Tab Reopen', 'smoketree-plugin' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="banner_resession" id="banner_resession" value="1" <?php checked( $banner['resession'] ?? '0', '1' ); ?>>
							<?php echo esc_html__( 'Re-show the banner when the visitor reopens the tab or browser', 'smoketree-plugin' ); ?>
						</label>
						<p class="description"><?php echo esc_html__( 'When checked, dismissal only lasts for the current tab session. When unchecked, dismissal is remembered permanently.', 'smoketree-plugin' ); ?></p>
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
				<tr>
					<th><?php echo esc_html__( 'Star Sticker', 'smoketree-plugin' ); ?></th>
					<td>
						<table style="border-collapse: collapse;">
							<tr>
								<td style="padding: 0 1rem 0.5rem 0; vertical-align: middle;">
									<label for="banner_star_text" style="display:block; margin-bottom: 0.25rem; font-weight: 600;"><?php echo esc_html__( 'Text', 'smoketree-plugin' ); ?></label>
									<input type="text" name="banner_star_text" id="banner_star_text" value="<?php echo esc_attr( $banner['star_text'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'NEW!', 'smoketree-plugin' ); ?>" maxlength="12">
									<p class="description"><?php echo esc_html__( 'Leave blank to hide the star. Keep it short (1–2 words).', 'smoketree-plugin' ); ?></p>
								</td>
							</tr>
							<tr>
								<td style="padding: 0 1rem 0.5rem 0; vertical-align: middle;">
									<label for="banner_star_bg_color" style="display:block; margin-bottom: 0.25rem; font-weight: 600;"><?php echo esc_html__( 'Background Color', 'smoketree-plugin' ); ?></label>
									<input type="color" name="banner_star_bg_color" id="banner_star_bg_color" value="<?php echo esc_attr( $banner['star_bg_color'] ?? '#facc15' ); ?>">
								</td>
								<td style="padding: 0 0 0.5rem 1rem; vertical-align: middle;">
									<label for="banner_star_text_color" style="display:block; margin-bottom: 0.25rem; font-weight: 600;"><?php echo esc_html__( 'Text Color', 'smoketree-plugin' ); ?></label>
									<input type="color" name="banner_star_text_color" id="banner_star_text_color" value="<?php echo esc_attr( $banner['star_text_color'] ?? '#1a1a1a' ); ?>">
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>

			<p class="submit">
			<input type="submit" id="stsrc-banner-submit" class="button button-primary" value="<?php echo esc_attr__( 'Save Banner', 'smoketree-plugin' ); ?>">
		</p>
	</form>

	<!-- Live Preview (below form so large/XL sizes have room to render) -->
	<h2><?php echo esc_html__( 'Preview', 'smoketree-plugin' ); ?></h2>
	<div style="border: 1px solid #ddd; border-radius: 4px; background: #1a1a2e; overflow: hidden; margin-bottom: 2rem;">
		<div id="stsrc-banner-preview" style="position: relative; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-align: center; overflow: visible;">
			<div id="stsrc-preview-content" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
				<span id="stsrc-preview-message"></span>
				<a id="stsrc-preview-link" href="#" style="font-weight: 600; text-decoration: underline; color: inherit; white-space: nowrap; display: none;"><?php echo esc_html__( 'Link text', 'smoketree-plugin' ); ?></a>
			</div>
			<span id="stsrc-preview-dismiss" style="position: absolute; top: 10px; right: 10px; opacity: 0.6; cursor: pointer; display: none;">
				<svg id="stsrc-preview-dismiss-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
			</span>
			<div id="stsrc-preview-star" style="
				position: absolute;
				top: 50%;
				transform: translateY(-50%);
				left: 16px;
				width: 68px;
				height: 68px;
				clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
				display: none;
				align-items: center;
				justify-content: center;
				font-size: 0.6rem;
				font-weight: 800;
				text-align: center;
				line-height: 1.1;
				padding: 18px 10px 10px;
			"></div>
		</div>
		<p style="text-align: center; color: #888; font-size: 0.75rem; margin: 8px 0 0; padding: 0 8px;"><?php echo esc_html__( 'Preview updates as you type.', 'smoketree-plugin' ); ?></p>
	</div>
</div>

<script>
jQuery(document).ready(function($) {

	// ── Form submit ──────────────────────────────────────────────────────────
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

	// ── Live preview ─────────────────────────────────────────────────────────
	var colors = {
		info:    { bg: '#dbeafe', text: '#1e40af' },
		warning: { bg: '#ffedd5', text: '#9a3412' },
		alert:   { bg: '#fee2e2', text: '#991b1b' },
		success: { bg: '#dcfce7', text: '#166534' },
	};

	var sizes = {
		small:      { fontSize: '0.875rem', padding: '0.5rem 1rem',  fontWeight: 'normal', minHeight: '',      dismissIcon: 14 },
		medium:     { fontSize: '1.5rem',   padding: '1.25rem 1.5rem', fontWeight: '700',  minHeight: '',      dismissIcon: 20 },
		large:      { fontSize: '2.5rem',   padding: '2.5rem 2rem',  fontWeight: '800',    minHeight: '',      dismissIcon: 28 },
		xl:         { fontSize: '4rem',     padding: '2rem',         fontWeight: '900',    minHeight: '220px', dismissIcon: 36 },
		fullscreen: { fontSize: '4rem',     padding: '2rem',         fontWeight: '900',    minHeight: '340px', dismissIcon: 44 },
	};

	function updatePreview() {
		var type        = $('#banner_type').val() || 'info';
		var size        = $('#banner_size').val() || 'small';
		var message     = $('#banner_message').val() || '(no message)';
		var linkLabel   = $('#banner_link_label').val();
		var linkUrl     = $('#banner_link_url').val();
		var dismissible = $('#banner_dismissible').is(':checked');
		var starText    = $('#banner_star_text').val();
		var starBg      = $('#banner_star_bg_color').val();
		var starColor   = $('#banner_star_text_color').val();

		var c = colors[type] || colors.info;
		var s = sizes[size]  || sizes.small;

		var $preview = $('#stsrc-banner-preview');
		$preview.css({
			backgroundColor: c.bg,
			color: c.text,
			fontSize: s.fontSize,
			fontWeight: s.fontWeight,
			minHeight: s.minHeight || '',
			padding: s.padding,
			paddingLeft: '100px',
			paddingRight: '60px',
		});

		var iconSize = s.dismissIcon + 'px';
		$('#stsrc-preview-dismiss-icon').css({ width: iconSize, height: iconSize });

		$('#stsrc-preview-message').text(message);

		var $link = $('#stsrc-preview-link');
		if (linkLabel && linkUrl) {
			$link.text(linkLabel).attr('href', linkUrl).show();
		} else {
			$link.hide();
		}

		$('#stsrc-preview-dismiss').css('display', dismissible ? 'flex' : 'none');

		var $star = $('#stsrc-preview-star');
		if (starText) {
			$star.text(starText).css({
				display: 'flex',
				backgroundColor: starBg,
				color: starColor,
			});
		} else {
			$star.hide();
		}
	}

	$('#stsrc-banner-form').on(
		'input change',
		'#banner_message, #banner_type, #banner_size, #banner_link_label, #banner_link_url, #banner_dismissible, #banner_star_text, #banner_star_bg_color, #banner_star_text_color',
		updatePreview
	);

	updatePreview();
});
</script>
