<?php
/**
 * Template Name: Smoketree Forgot Password
 * 
 * Forgot password page template.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Redirect if already logged in
if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/member-portal' ) );
	exit;
}

get_header();
?>

<div class="stsrc-forgot-password-page">
	<div class="stsrc-container">
		<h1><?php echo esc_html__( 'Forgot Password', 'smoketree-plugin' ); ?></h1>
		
		<p><?php echo esc_html__( 'Enter your email address and we will send you a link to reset your password.', 'smoketree-plugin' ); ?></p>

		<form id="stsrc-forgot-password-form" class="stsrc-forgot-password-form" method="post">
			<input type="hidden" name="action" value="stsrc_forgot_password">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_forgot_password_nonce' ) ); ?>">
			
			<div id="stsrc-form-messages"></div>

			<div class="stsrc-form-group">
				<label for="email"><?php echo esc_html__( 'Email Address', 'smoketree-plugin' ); ?></label>
				<input type="email" name="email" id="email" required autocomplete="email">
			</div>

			<div class="stsrc-form-group">
				<button type="submit" class="stsrc-button stsrc-button-primary" id="stsrc-submit-forgot-password">
					<?php echo esc_html__( 'Send Reset Link', 'smoketree-plugin' ); ?>
				</button>
			</div>

			<div class="stsrc-form-group">
				<a href="<?php echo esc_url( home_url( '/login' ) ); ?>">
					<?php echo esc_html__( 'Back to Login', 'smoketree-plugin' ); ?>
				</a>
			</div>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#stsrc-forgot-password-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $submitBtn = $('#stsrc-submit-forgot-password');
		const $messages = $('#stsrc-form-messages');
		
		$submitBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'smoketree-plugin' ) ); ?>');
		$messages.html('');
		
		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: $form.serialize(),
			success: function(response) {
				if (response.success) {
					$messages.html('<div class="stsrc-notice success"><p>' + response.data.message + '</p></div>');
					$form[0].reset();
				} else {
					$messages.html('<div class="stsrc-notice error"><p>' + response.data.message + '</p></div>');
					$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Reset Link', 'smoketree-plugin' ) ); ?>');
				}
			},
			error: function() {
				$messages.html('<div class="stsrc-notice error"><p><?php echo esc_js( __( 'An error occurred. Please try again.', 'smoketree-plugin' ) ); ?></p></div>');
				$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Reset Link', 'smoketree-plugin' ) ); ?>');
			}
		});
	});
});
</script>

<?php
get_footer();

