<?php
/**
 * Settings form template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = $data['settings'] ?? array();
$acf_available = $data['acf_available'] ?? false;
?>

<div class="wrap">
	<h1><?php echo esc_html__( 'Smoketree Club Settings', 'smoketree-plugin' ); ?></h1>

	<?php if ( ! $acf_available ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html__( 'ACF Pro Not Detected', 'smoketree-plugin' ); ?>:</strong>
				<?php echo esc_html__( 'Advanced Custom Fields Pro is recommended for better settings management. Settings will be stored in WordPress options.', 'smoketree-plugin' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-info">
			<p>
				<?php echo esc_html__( 'Settings are managed via ACF Pro. If ACF fields are configured, they will be used. Otherwise, WordPress options will be used.', 'smoketree-plugin' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $acf_available && function_exists( 'acf_form' ) ) : ?>
		<!-- ACF Form (if ACF options page is set up) -->
		<?php
		acf_form(
			array(
				'post_id'       => 'options',
				'post_title'    => false,
				'post_content'  => false,
				'submit_value'  => __( 'Save Settings', 'smoketree-plugin' ),
				'return'        => admin_url( 'admin.php?page=stsrc-settings&updated=1' ),
			)
		);
		?>
	<?php else : ?>
		<!-- Fallback WordPress Options Form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="stsrc-settings-form">
			<input type="hidden" name="action" value="stsrc_save_settings">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>">

			<div class="stsrc-form-sections">
				<!-- Stripe Settings -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Stripe Payment Settings', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="stripe_test_mode"><?php echo esc_html__( 'Test Mode', 'smoketree-plugin' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="stripe_test_mode" id="stripe_test_mode" value="1" <?php checked( $settings['stripe_test_mode'] ?? '0', '1' ); ?>>
									<?php echo esc_html__( 'Enable Stripe test mode', 'smoketree-plugin' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="stripe_publishable_key"><?php echo esc_html__( 'Publishable Key', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="stripe_publishable_key" id="stripe_publishable_key" value="<?php echo esc_attr( $settings['stripe_publishable_key'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'Stripe publishable API key (starts with pk_)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="stripe_secret_key"><?php echo esc_html__( 'Secret Key', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="password" name="stripe_secret_key" id="stripe_secret_key" value="<?php echo esc_attr( $settings['stripe_secret_key'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'Stripe secret API key (starts with sk_)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="stripe_test_publishable_key"><?php echo esc_html__( 'Test Publishable Key', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="stripe_test_publishable_key" id="stripe_test_publishable_key" value="<?php echo esc_attr( $settings['stripe_test_publishable_key'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'Stripe test publishable API key', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="stripe_test_secret_key"><?php echo esc_html__( 'Test Secret Key', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="password" name="stripe_test_secret_key" id="stripe_test_secret_key" value="<?php echo esc_attr( $settings['stripe_test_secret_key'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'Stripe test secret API key', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="stripe_webhook_secret"><?php echo esc_html__( 'Webhook Secret', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret" value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'Stripe webhook signing secret (starts with whsec_)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- CAPTCHA Settings -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'CAPTCHA Settings', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="captcha_enabled"><?php echo esc_html__( 'Enable CAPTCHA', 'smoketree-plugin' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="captcha_enabled" id="captcha_enabled" value="1" <?php checked( $settings['captcha_enabled'] ?? '0', '1' ); ?>>
									<?php echo esc_html__( 'Enable CAPTCHA verification on registration forms', 'smoketree-plugin' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="captcha_provider"><?php echo esc_html__( 'CAPTCHA Provider', 'smoketree-plugin' ); ?></label></th>
							<td>
								<select name="captcha_provider" id="captcha_provider">
									<option value="recaptcha" <?php selected( $settings['captcha_provider'] ?? 'recaptcha', 'recaptcha' ); ?>><?php echo esc_html__( 'Google reCAPTCHA v3', 'smoketree-plugin' ); ?></option>
									<option value="hcaptcha" <?php selected( $settings['captcha_provider'] ?? '', 'hcaptcha' ); ?>><?php echo esc_html__( 'hCaptcha', 'smoketree-plugin' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="captcha_site_key"><?php echo esc_html__( 'Site Key', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="captcha_site_key" id="captcha_site_key" value="<?php echo esc_attr( $settings['captcha_site_key'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'CAPTCHA site key (public key)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="captcha_secret_key"><?php echo esc_html__( 'Secret Key', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="password" name="captcha_secret_key" id="captcha_secret_key" value="<?php echo esc_attr( $settings['captcha_secret_key'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'CAPTCHA secret key', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- General Settings -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'General Settings', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="registration_enabled"><?php echo esc_html__( 'Registration Enabled', 'smoketree-plugin' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="registration_enabled" id="registration_enabled" value="1" <?php checked( $settings['registration_enabled'] ?? '1', '1' ); ?>>
									<?php echo esc_html__( 'Allow new member registrations', 'smoketree-plugin' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="payment_plan_enabled"><?php echo esc_html__( 'Payment Plan Enabled', 'smoketree-plugin' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="payment_plan_enabled" id="payment_plan_enabled" value="1" <?php checked( $settings['payment_plan_enabled'] ?? '0', '1' ); ?>>
									<?php echo esc_html__( 'Enable payment plan options', 'smoketree-plugin' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="secretary_email"><?php echo esc_html__( 'Secretary Email', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="email" name="secretary_email" id="secretary_email" value="<?php echo esc_attr( $settings['secretary_email'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Email address for secretary notifications (new registrations, etc.)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="season_renewal_date"><?php echo esc_html__( 'Season Renewal Date', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="date" name="season_renewal_date" id="season_renewal_date" value="<?php echo esc_attr( $settings['season_renewal_date'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Date for season-wide auto-renewal (YYYY-MM-DD)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save Settings', 'smoketree-plugin' ); ?>">
			</p>
		</form>
	<?php endif; ?>
</div>

<?php if ( ! $acf_available || ! function_exists( 'acf_form' ) ) : ?>
<script>
jQuery(document).ready(function($) {
	// Update CAPTCHA key labels when provider changes
	$('#captcha_provider').on('change', function() {
		var provider = $(this).val();
		var providerName = provider === 'recaptcha' ? 'reCAPTCHA' : 'hCaptcha';
		$('label[for="captcha_site_key"]').text('<?php echo esc_js( __( 'Site Key', 'smoketree-plugin' ) ); ?> (' + providerName + ')');
		$('label[for="captcha_secret_key"]').text('<?php echo esc_js( __( 'Secret Key', 'smoketree-plugin' ) ); ?> (' + providerName + ')');
	});

	// Handle form submission
	$('#stsrc-settings-form').on('submit', function(e) {
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
					alert('<?php echo esc_js( __( 'Settings saved successfully!', 'smoketree-plugin' ) ); ?>');
					location.reload();
				} else {
					alert('<?php echo esc_js( __( 'Error: ', 'smoketree-plugin' ) ); ?>' + response.data.message);
					$submitBtn.prop('disabled', false).val('<?php echo esc_js( __( 'Save Settings', 'smoketree-plugin' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Error saving settings', 'smoketree-plugin' ) ); ?>');
				$submitBtn.prop('disabled', false).val('<?php echo esc_js( __( 'Save Settings', 'smoketree-plugin' ) ); ?>');
			}
		});
	});
});
</script>
<?php endif; ?>

