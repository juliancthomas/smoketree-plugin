<?php
/**
 * Template Name: Smoketree Reset Password
 * 
 * Password reset page template.
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

// Get token and email from URL
$request_params = wp_unslash( $_GET );
$token = isset( $request_params['token'] ) ? sanitize_text_field( $request_params['token'] ) : '';
$email = isset( $request_params['email'] ) ? sanitize_email( $request_params['email'] ) : '';

// Validate token if provided
$token_valid = false;
$token_error = '';

if ( ! empty( $token ) && ! empty( $email ) ) {
	require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/api/class-stsrc-ajax-handler.php';
	$ajax_handler = new STSRC_Ajax_Handler();
	$validation_result = $ajax_handler->validate_reset_token( $token, $email );
	
	if ( ! is_wp_error( $validation_result ) ) {
		$token_valid = true;
	} else {
		$token_error = $validation_result->get_error_message();
	}
} elseif ( empty( $token ) || empty( $email ) ) {
	$token_error = __( 'Invalid reset link. Please request a new password reset.', 'smoketree-plugin' );
}

get_header();
?>

<div class="stsrc-reset-password-page">
	<div class="stsrc-container">
		<h1><?php echo esc_html__( 'Reset Password', 'smoketree-plugin' ); ?></h1>
		
		<?php if ( ! empty( $token_error ) ) : ?>
			<div class="stsrc-notice error">
				<p><?php echo esc_html( $token_error ); ?></p>
			</div>
			<div class="stsrc-form-group">
				<a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" class="stsrc-button stsrc-button-primary">
					<?php echo esc_html__( 'Request New Reset Link', 'smoketree-plugin' ); ?>
				</a>
			</div>
		<?php elseif ( $token_valid ) : ?>
			<form id="stsrc-reset-password-form" class="stsrc-reset-password-form" method="post">
				<input type="hidden" name="action" value="stsrc_reset_password">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_reset_password_nonce' ) ); ?>">
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
				
				<div id="stsrc-form-messages"></div>

				<div class="stsrc-form-group">
					<label for="new_password"><?php echo esc_html__( 'New Password', 'smoketree-plugin' ); ?></label>
					<input type="password" name="new_password" id="new_password" required minlength="8" autocomplete="new-password">
					<small><?php echo esc_html__( 'Must be at least 8 characters long.', 'smoketree-plugin' ); ?></small>
				</div>

				<div class="stsrc-form-group">
					<label for="confirm_password"><?php echo esc_html__( 'Confirm New Password', 'smoketree-plugin' ); ?></label>
					<input type="password" name="confirm_password" id="confirm_password" required minlength="8" autocomplete="new-password">
				</div>

				<div class="stsrc-form-group">
					<button type="submit" class="stsrc-button stsrc-button-primary" id="stsrc-submit-reset-password">
						<?php echo esc_html__( 'Reset Password', 'smoketree-plugin' ); ?>
					</button>
				</div>
			</form>
		<?php else : ?>
			<div class="stsrc-notice error">
				<p><?php echo esc_html__( 'Invalid reset link. Please request a new password reset.', 'smoketree-plugin' ); ?></p>
			</div>
			<div class="stsrc-form-group">
				<a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" class="stsrc-button stsrc-button-primary">
					<?php echo esc_html__( 'Request New Reset Link', 'smoketree-plugin' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<div class="stsrc-form-group">
			<a href="<?php echo esc_url( home_url( '/login' ) ); ?>">
				<?php echo esc_html__( 'Back to Login', 'smoketree-plugin' ); ?>
			</a>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#stsrc-reset-password-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $submitBtn = $('#stsrc-submit-reset-password');
		const $messages = $('#stsrc-form-messages');
		
		// Validate password match
		if ($('#new_password').val() !== $('#confirm_password').val()) {
			$messages.html('<div class="stsrc-notice error"><p><?php echo esc_js( __( 'Passwords do not match.', 'smoketree-plugin' ) ); ?></p></div>');
			return;
		}
		
		$submitBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Resetting...', 'smoketree-plugin' ) ); ?>');
		$messages.html('');
		
		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: $form.serialize(),
			success: function(response) {
				if (response.success) {
					$messages.html('<div class="stsrc-notice success"><p>' + response.data.message + '</p></div>');
					if (response.data.redirect_url) {
						setTimeout(function() {
							window.location.href = response.data.redirect_url;
						}, 2000);
					}
				} else {
					$messages.html('<div class="stsrc-notice error"><p>' + response.data.message + '</p></div>');
					$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Reset Password', 'smoketree-plugin' ) ); ?>');
				}
			},
			error: function() {
				$messages.html('<div class="stsrc-notice error"><p><?php echo esc_js( __( 'An error occurred. Please try again.', 'smoketree-plugin' ) ); ?></p></div>');
				$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Reset Password', 'smoketree-plugin' ) ); ?>');
			}
		});
	});
});
</script>

<?php
get_footer();

