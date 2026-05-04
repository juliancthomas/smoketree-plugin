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
$membership_types = $data['membership_types'] ?? array();
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
							<th><label for="renewal_enabled"><?php echo esc_html__( 'Member Renewal Enabled', 'smoketree-plugin' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="renewal_enabled" id="renewal_enabled" value="1" <?php checked( $settings['renewal_enabled'] ?? '0', '1' ); ?>>
									<?php echo esc_html__( 'Enable member self-service renewal in the portal', 'smoketree-plugin' ); ?>
								</label>
							</td>
						</tr>
					<tr>
						<th><label for="secretary_email"><?php echo esc_html__( 'Secretary Email', 'smoketree-plugin' ); ?></label></th>
						<td>
							<input type="text" name="secretary_email" id="secretary_email" value="<?php echo esc_attr( $settings['secretary_email'] ?? '' ); ?>" class="regular-text">
							<p class="description"><?php echo esc_html__( 'Secretary notification emails (comma-separated for multiple addresses).', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="contact_email"><?php echo esc_html__( 'Contact Email', 'smoketree-plugin' ); ?></label></th>
						<td>
							<input type="email" name="contact_email" id="contact_email" value="<?php echo esc_attr( $settings['contact_email'] ?? '' ); ?>" class="regular-text">
							<p class="description"><?php echo esc_html__( 'Public contact email shown in the footer of all member emails (e.g. info@smoketree.us).', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
						<tr>
							<th><label for="season_renewal_date"><?php echo esc_html__( 'Season Renewal Date', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="date" name="season_renewal_date" id="season_renewal_date" value="<?php echo esc_attr( $settings['season_renewal_date'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Date for season-wide auto-renewal (YYYY-MM-DD)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="tax_rate"><?php echo esc_html__( 'Tax Rate (%)', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="number" name="tax_rate" id="tax_rate" value="<?php echo esc_attr( $settings['tax_rate'] ?? '0' ); ?>" class="regular-text" step="0.01" min="0" max="100">
								<p class="description"><?php echo esc_html__( 'Tax percentage to apply to membership fees (e.g., 7.5 for 7.5%)', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Community Links -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Community Links', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="whatsapp_url"><?php echo esc_html__( 'WhatsApp Group Link', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="url" name="whatsapp_url" id="whatsapp_url" value="<?php echo esc_attr( $settings['whatsapp_url'] ?? '' ); ?>" class="large-text" placeholder="https://chat.whatsapp.com/...">
								<p class="description"><?php echo esc_html__( 'Neighborhood WhatsApp group invite link. Included in welcome emails sent to new members. Leave blank to hide.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Member Portal Announcement -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Member Portal Announcement', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="portal_announcement"><?php echo esc_html__( 'Announcement Content', 'smoketree-plugin' ); ?></label></th>
							<td>
								<?php
								wp_editor(
									wp_kses_post( $settings['portal_announcement'] ?? '' ),
									'portal_announcement',
									array(
										'textarea_name' => 'portal_announcement',
										'media_buttons' => true,
										'textarea_rows' => 10,
										'teeny'         => false,
									)
								);
								?>
								<p class="description"><?php echo esc_html__( 'Content shown to members at the top of the Member Portal. Use this for important links, announcements, or seasonal notices. Leave blank to hide the section.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Waiver Settings -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Waiver Agreement', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="waiver_text"><?php echo esc_html__( 'Waiver Agreement Text', 'smoketree-plugin' ); ?></label></th>
							<td>
								<textarea name="waiver_text" id="waiver_text" rows="10" class="large-text"><?php echo esc_textarea( $settings['waiver_text'] ?? '' ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'This text will be displayed in a read-only field on the registration form. Members must sign and date to agree.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Auto-Renewal Agreement Settings -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Auto-Renewal Agreement', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="auto_renewal_text"><?php echo esc_html__( 'Auto-Renewal Agreement Text', 'smoketree-plugin' ); ?></label></th>
							<td>
								<textarea name="auto_renewal_text" id="auto_renewal_text" rows="10" class="large-text"><?php echo esc_textarea( $settings['auto_renewal_text'] ?? '' ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'This text will be displayed on the registration form when a Stripe-compatible payment method (card or bank account) is selected. Members must check a box to acknowledge and opt in to auto-renewal.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Google Places Settings -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Google Places Autocomplete', 'smoketree-plugin' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="google_places_api_key"><?php echo esc_html__( 'Google Places API Key', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="password" name="google_places_api_key" id="google_places_api_key" value="<?php echo esc_attr( $settings['google_places_api_key'] ?? '' ); ?>" class="large-text">
								<p class="description"><?php echo esc_html__( 'Google API key with Places API (New) enabled. Used for address autocomplete on the registration form. Leave blank to disable.', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Transaction Fees Settings -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Transaction Fees', 'smoketree-plugin' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Configure transaction fees for each payment method. These will be displayed to users during registration.', 'smoketree-plugin' ); ?></p>
					<table class="form-table">
						<tr>
							<th><label for="fee_card"><?php echo esc_html__( 'Credit/Debit Card Fee', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="fee_card" id="fee_card" value="<?php echo esc_attr( $settings['fee_card'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Transaction fee description for card payments (e.g., "2.9% + $0.30")', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="fee_bank_account"><?php echo esc_html__( 'Bank Account Fee', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="fee_bank_account" id="fee_bank_account" value="<?php echo esc_attr( $settings['fee_bank_account'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Transaction fee description for bank account payments (e.g., "0.8%")', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="fee_zelle"><?php echo esc_html__( 'Zelle Fee', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="fee_zelle" id="fee_zelle" value="<?php echo esc_attr( $settings['fee_zelle'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Transaction fee description for Zelle payments (e.g., "No fee")', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="fee_check"><?php echo esc_html__( 'Check Fee', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="fee_check" id="fee_check" value="<?php echo esc_attr( $settings['fee_check'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Transaction fee description for check payments (e.g., "No fee")', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="fee_pay_later"><?php echo esc_html__( 'Pay Later Fee', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="text" name="fee_pay_later" id="fee_pay_later" value="<?php echo esc_attr( $settings['fee_pay_later'] ?? '' ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__( 'Transaction fee description for pay later option (e.g., "No fee")', 'smoketree-plugin' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Payment Settings (v1.1.0+) -->
				<div class="stsrc-form-section">
					<h2><?php echo esc_html__( 'Payment Settings', 'smoketree-plugin' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Configure settings for balance payments and payment tracking.', 'smoketree-plugin' ); ?></p>
					<table class="form-table">
						<tr>
							<th><label for="minimum_balance_payment"><?php echo esc_html__( 'Minimum Balance Payment Amount', 'smoketree-plugin' ); ?></label></th>
							<td>
								<input type="number" name="minimum_balance_payment" id="minimum_balance_payment" value="<?php echo esc_attr( $settings['minimum_balance_payment'] ?? '10.00' ); ?>" class="regular-text" step="0.01" min="0.01" required>
								<p class="description"><?php echo esc_html__( 'Minimum amount members can pay toward their balance via Stripe (e.g., 10.00 for $10 minimum)', 'smoketree-plugin' ); ?></p>
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


<!-- Auto-Renewal Tools -->
<div class="stsrc-form-section" style="margin-top:24px;">
	<h2><?php echo esc_html__( 'Auto-Renewal Tools', 'smoketree-plugin' ); ?></h2>
	<p class="description">
		<?php echo esc_html__( 'Manually trigger auto-renewal operations. Notifications are normally sent 7 days before the season renewal date, and payments are processed on the renewal date itself.', 'smoketree-plugin' ); ?>
	</p>

	<div id="stsrc-renewal-tools-result" style="display:none; margin: 12px 0;"></div>

	<p>
		<button type="button" class="button" id="stsrc-trigger-notifications-btn">
			<?php echo esc_html__( 'Send Renewal Notifications Now', 'smoketree-plugin' ); ?>
		</button>
		<button type="button" class="button button-primary" id="stsrc-trigger-processing-btn" style="margin-left:8px;">
			<?php echo esc_html__( 'Process Renewals Now', 'smoketree-plugin' ); ?>
		</button>
	</p>
	<p class="description">
		<?php echo esc_html__( 'These actions bypass the date check and run immediately for all eligible members.', 'smoketree-plugin' ); ?>
	</p>
</div>

<script>
jQuery(document).ready(function($) {
	var renewalNonce = '<?php echo esc_js( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>';
	var $result = $('#stsrc-renewal-tools-result');

	function showRenewalResult(message, type) {
		var cls = type === 'error' ? 'notice-error' : 'notice-success';
		$result.html('<div class="notice ' + cls + ' inline"><p>' + $('<span>').text(message).html() + '</p></div>').show();
	}

	$('#stsrc-trigger-notifications-btn').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Send renewal notification emails to all eligible members now?', 'smoketree-plugin' ) ); ?>')) {
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'smoketree-plugin' ) ); ?>');
		$result.hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: { action: 'stsrc_trigger_renewal_notifications', nonce: renewalNonce },
			success: function(response) {
				showRenewalResult(response.data.message, response.success ? 'success' : 'error');
			},
			error: function() {
				showRenewalResult('<?php echo esc_js( __( 'Request failed. Please try again.', 'smoketree-plugin' ) ); ?>', 'error');
			},
			complete: function() {
				$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Renewal Notifications Now', 'smoketree-plugin' ) ); ?>');
			}
		});
	});

	$('#stsrc-trigger-processing-btn').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Process auto-renewal payments for all eligible members now? This will charge their saved payment methods.', 'smoketree-plugin' ) ); ?>')) {
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'smoketree-plugin' ) ); ?>');
		$result.hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: { action: 'stsrc_trigger_renewal_processing', nonce: renewalNonce },
			success: function(response) {
				showRenewalResult(response.data.message, response.success ? 'success' : 'error');
			},
			error: function() {
				showRenewalResult('<?php echo esc_js( __( 'Request failed. Please try again.', 'smoketree-plugin' ) ); ?>', 'error');
			},
			complete: function() {
				$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Process Renewals Now', 'smoketree-plugin' ) ); ?>');
			}
		});
	});
});
</script>

<!-- Renewal History Log -->
<?php
$renewal_logs = $data['renewal_logs'] ?? array();
$renewal_member_names = $data['renewal_member_names'] ?? array();
?>
<div class="stsrc-form-section" style="margin-top:24px;">
	<h2><?php echo esc_html__( 'Renewal History', 'smoketree-plugin' ); ?></h2>
	<p class="description">
		<?php echo esc_html__( 'Recent auto-renewal payment attempts (most recent first, up to 50 entries).', 'smoketree-plugin' ); ?>
	</p>

	<?php if ( ! empty( $renewal_logs ) ) : ?>
		<?php
		$succeeded_count = count( array_filter( $renewal_logs, static fn( $l ) => 'succeeded' === ( $l['status'] ?? '' ) ) );
		$failed_count    = count( array_filter( $renewal_logs, static fn( $l ) => 'failed' === ( $l['status'] ?? '' ) ) );
		$pending_count   = count( array_filter( $renewal_logs, static fn( $l ) => 'pending' === ( $l['status'] ?? '' ) ) );
		?>
		<p style="margin: 12px 0;">
			<strong><?php echo esc_html__( 'Summary:', 'smoketree-plugin' ); ?></strong>
			<span style="color: #00a32a;"><?php echo esc_html( $succeeded_count ); ?> <?php echo esc_html__( 'succeeded', 'smoketree-plugin' ); ?></span>,
			<span style="color: #d63638;"><?php echo esc_html( $failed_count ); ?> <?php echo esc_html__( 'failed', 'smoketree-plugin' ); ?></span>,
			<span style="color: #996800;"><?php echo esc_html( $pending_count ); ?> <?php echo esc_html__( 'pending', 'smoketree-plugin' ); ?></span>
		</p>

		<table class="widefat striped" style="max-width:100%;">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Date', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Member', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Amount', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></th>
					<th><?php echo esc_html__( 'Renewal Date', 'smoketree-plugin' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $renewal_logs as $log ) :
					$log_member_id = (int) ( $log['member_id'] ?? 0 );
					$member_name   = $renewal_member_names[ $log_member_id ] ?? __( 'Unknown', 'smoketree-plugin' );
					$meta          = is_array( $log['metadata'] ?? null ) ? $log['metadata'] : array();
					$season_date   = $meta['season_renewal_date'] ?? '';
					$status_colors = array(
						'succeeded' => '#00a32a',
						'failed'    => '#d63638',
						'pending'   => '#996800',
					);
					$status_color = $status_colors[ $log['status'] ?? '' ] ?? '#646970';
				?>
					<tr>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) ); ?></td>
						<td>
							<?php if ( $log_member_id > 0 ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-members&action=edit&member_id=' . $log_member_id ) ); ?>">
									<?php echo esc_html( $member_name ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $member_name ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( '$' . number_format( (float) ( $log['amount'] ?? 0 ), 2 ) ); ?></td>
						<td>
							<span style="color: <?php echo esc_attr( $status_color ); ?>; font-weight: 600;">
								<?php echo esc_html( ucfirst( $log['status'] ?? 'unknown' ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( ! empty( $season_date ) ) : ?>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $season_date ) ) ); ?>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php echo esc_html__( 'No auto-renewal payment attempts yet.', 'smoketree-plugin' ); ?></p>
	<?php endif; ?>
</div>

<?php $balance_tools_result = $data['balance_tools_result'] ?? null; ?>

<div class="stsrc-form-section" style="margin-top:24px;">
	<h2><?php echo esc_html__( 'Balance Integrity Tools', 'smoketree-plugin' ); ?></h2>
	<p class="description">
		<?php echo esc_html__( 'Verify member balances against the transaction ledger and optionally fix mismatches.', 'smoketree-plugin' ); ?>
	</p>

	<?php if ( is_array( $balance_tools_result ) && ! empty( $balance_tools_result['message'] ) ) : ?>
		<div class="notice <?php echo ( 'error' === ( $balance_tools_result['type'] ?? '' ) ) ? 'notice-error' : 'notice-success'; ?> inline">
			<p><?php echo esc_html( $balance_tools_result['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post">
		<input type="hidden" name="stsrc_balance_tools_nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_balance_tools' ) ); ?>">
		<p>
			<button type="submit" class="button" name="stsrc_balance_tools_action" value="verify">
				<?php echo esc_html__( 'Verify Balance Integrity', 'smoketree-plugin' ); ?>
			</button>
			<button type="submit" class="button button-primary" name="stsrc_balance_tools_action" value="recalculate" style="margin-left:8px;">
				<?php echo esc_html__( 'Recalculate All Balances', 'smoketree-plugin' ); ?>
			</button>
		</p>
	</form>

	<?php if ( is_array( $balance_tools_result ) && ! empty( $balance_tools_result['report'] ) ) : ?>
		<?php $report = $balance_tools_result['report']; ?>
		<p>
			<strong><?php echo esc_html__( 'Checked:', 'smoketree-plugin' ); ?></strong>
			<?php echo esc_html( (string) (int) ( $report['checked'] ?? 0 ) ); ?>
			&nbsp;|&nbsp;
			<strong><?php echo esc_html__( 'Discrepancies:', 'smoketree-plugin' ); ?></strong>
			<?php echo esc_html( (string) (int) ( $report['discrepancies_count'] ?? 0 ) ); ?>
			&nbsp;|&nbsp;
			<strong><?php echo esc_html__( 'Fixed:', 'smoketree-plugin' ); ?></strong>
			<?php echo esc_html( (string) (int) ( $report['fixed_count'] ?? 0 ) ); ?>
		</p>

		<?php if ( ! empty( $report['discrepancies'] ) && is_array( $report['discrepancies'] ) ) : ?>
			<table class="widefat striped" style="max-width:100%; margin-top:12px;">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Member ID', 'smoketree-plugin' ); ?></th>
						<th><?php echo esc_html__( 'Name', 'smoketree-plugin' ); ?></th>
						<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
						<th><?php echo esc_html__( 'Stored Balance', 'smoketree-plugin' ); ?></th>
						<th><?php echo esc_html__( 'Calculated Balance', 'smoketree-plugin' ); ?></th>
						<th><?php echo esc_html__( 'Difference', 'smoketree-plugin' ); ?></th>
						<th><?php echo esc_html__( 'Fixed', 'smoketree-plugin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $report['discrepancies'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) (int) ( $row['member_id'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['member_name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['email'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( '$' . number_format( (float) ( $row['stored_balance'] ?? 0 ), 2 ) ); ?></td>
							<td><?php echo esc_html( '$' . number_format( (float) ( $row['calculated_balance'] ?? 0 ), 2 ) ); ?></td>
							<td><?php echo esc_html( '$' . number_format( (float) ( $row['difference'] ?? 0 ), 2 ) ); ?></td>
							<td><?php echo esc_html( ! empty( $row['fixed'] ) ? __( 'Yes', 'smoketree-plugin' ) : __( 'No', 'smoketree-plugin' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>

