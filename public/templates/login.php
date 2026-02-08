<?php
/**
 * Template Name: Smoketree Login
 * 
 * Login page template for member authentication.
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

// Handle query parameters
$request_params = wp_unslash( $_GET );
$redirect_to    = isset( $request_params['redirect_to'] ) ? esc_url_raw( $request_params['redirect_to'] ) : home_url( '/member-portal' );
$login_flag     = isset( $request_params['login'] ) ? sanitize_text_field( $request_params['login'] ) : '';
$loggedout_flag = isset( $request_params['loggedout'] ) ? sanitize_text_field( $request_params['loggedout'] ) : '';

get_header();
?>

<div class="stsrc-login-page">
	<div class="stsrc-login-container">
		<div class="stsrc-login-box">
			<!-- Logo and Branding -->
			<div class="stsrc-login-header">
				<img src="<?php echo esc_url( content_url( '/uploads/2026/02/450163785_915335023731570_93323246259874532_n-1.jpg' ) ); ?>" 
					 alt="<?php echo esc_attr__( 'Smoketree Swim and Recreation Club', 'smoketree-plugin' ); ?>" 
					 class="stsrc-login-logo">
				<h1><?php echo esc_html__( 'Smoketree Swim and Recreation Club', 'smoketree-plugin' ); ?></h1>
				<p class="stsrc-login-subtitle"><?php echo esc_html__( 'Member Login', 'smoketree-plugin' ); ?></p>
			</div>
			
			<?php if ( 'failed' === $login_flag ) : ?>
				<div class="stsrc-notice error">
					<p><?php echo esc_html__( 'Invalid username or password. Please try again.', 'smoketree-plugin' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( 'true' === $loggedout_flag ) : ?>
				<div class="stsrc-notice success">
					<p><?php echo esc_html__( 'You have been logged out successfully.', 'smoketree-plugin' ); ?></p>
				</div>
			<?php endif; ?>

			<form id="stsrc-login-form" class="stsrc-login-form" method="post">
				<input type="hidden" name="action" value="stsrc_login">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_login_nonce' ) ); ?>">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
				
				<div id="stsrc-form-messages"></div>

				<div class="stsrc-form-group">
					<label for="user_login"><?php echo esc_html__( 'Username', 'smoketree-plugin' ); ?></label>
					<input type="text" name="user_login" id="user_login" required autocomplete="email">
				</div>

				<div class="stsrc-form-group">
					<label for="user_password"><?php echo esc_html__( 'Password', 'smoketree-plugin' ); ?></label>
					<div class="stsrc-password-wrapper">
						<input type="password" name="user_password" id="user_password" required autocomplete="current-password">
						<button type="button" class="stsrc-password-toggle" id="stsrc-toggle-password" aria-label="<?php echo esc_attr__( 'Show password', 'smoketree-plugin' ); ?>">
							<span class="stsrc-eye-icon stsrc-eye-closed" aria-hidden="true">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
									<line x1="1" y1="1" x2="23" y2="23"></line>
								</svg>
							</span>
							<span class="stsrc-eye-icon stsrc-eye-open" style="display: none;" aria-hidden="true">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
									<circle cx="12" cy="12" r="3"></circle>
								</svg>
							</span>
						</button>
					</div>
				</div>

				<div class="stsrc-form-group">
					<label class="stsrc-checkbox-label">
						<input type="checkbox" name="rememberme" id="rememberme" value="forever">
						<?php echo esc_html__( 'Remember me', 'smoketree-plugin' ); ?>
					</label>
				</div>

				<div class="stsrc-form-group">
					<button type="submit" class="stsrc-button stsrc-button-primary stsrc-button-full" id="stsrc-submit-login">
						<?php echo esc_html__( 'Log In', 'smoketree-plugin' ); ?>
					</button>
				</div>

				<div class="stsrc-login-links">
					<a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>">
						<?php echo esc_html__( 'Forgot your password?', 'smoketree-plugin' ); ?>
					</a>
					<span class="stsrc-separator">|</span>
					<a href="<?php echo esc_url( home_url( '/register' ) ); ?>">
						<?php echo esc_html__( 'Register', 'smoketree-plugin' ); ?>
					</a>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Password toggle functionality
	$('#stsrc-toggle-password').on('click', function(e) {
		e.preventDefault();
		const $passwordInput = $('#user_password');
		const $toggleBtn = $(this);
		const $eyeClosed = $toggleBtn.find('.stsrc-eye-closed');
		const $eyeOpen = $toggleBtn.find('.stsrc-eye-open');
		
		if ($passwordInput.attr('type') === 'password') {
			$passwordInput.attr('type', 'text');
			$eyeClosed.hide();
			$eyeOpen.show();
			$toggleBtn.attr('aria-label', '<?php echo esc_js( __( 'Hide password', 'smoketree-plugin' ) ); ?>');
		} else {
			$passwordInput.attr('type', 'password');
			$eyeClosed.show();
			$eyeOpen.hide();
			$toggleBtn.attr('aria-label', '<?php echo esc_js( __( 'Show password', 'smoketree-plugin' ) ); ?>');
		}
	});
	
	// Login form submission
	$('#stsrc-login-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $submitBtn = $('#stsrc-submit-login');
		const $messages = $('#stsrc-form-messages');
		
		$submitBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Logging in...', 'smoketree-plugin' ) ); ?>');
		$messages.html('');
		
		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: $form.serialize(),
			success: function(response) {
				if (response.success) {
					if (response.data.redirect_url) {
						window.location.href = response.data.redirect_url;
					} else {
						window.location.reload();
					}
				} else {
					$messages.html('<div class="stsrc-notice error"><p>' + response.data.message + '</p></div>');
					$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Log In', 'smoketree-plugin' ) ); ?>');
				}
			},
			error: function() {
				$messages.html('<div class="stsrc-notice error"><p><?php echo esc_js( __( 'An error occurred. Please try again.', 'smoketree-plugin' ) ); ?></p></div>');
				$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Log In', 'smoketree-plugin' ) ); ?>');
			}
		});
	});
});
</script>

<?php
get_footer();

