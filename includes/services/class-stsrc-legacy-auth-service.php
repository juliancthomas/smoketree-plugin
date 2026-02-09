<?php

/**
 * Legacy Authentication Service
 *
 * Handles authentication for migrated members with legacy passwords.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

/**
 * Legacy Authentication Service Class.
 *
 * Verifies legacy passwords and forces password reset for migrated members.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Legacy_Auth_Service {

	/**
	 * Initialize the service and register hooks.
	 *
	 * @since    1.0.0
	 */
	public static function init(): void {
		// Hook into WordPress authentication
		add_filter( 'authenticate', array( __CLASS__, 'authenticate_legacy_password' ), 30, 3 );

		// Hook into login redirect to force password reset
		add_filter( 'login_redirect', array( __CLASS__, 'force_password_reset_redirect' ), 10, 3 );

		// Add notice to member portal for password reset
		add_action( 'stsrc_member_portal_notices', array( __CLASS__, 'show_password_reset_notice' ) );
	}

	/**
	 * Authenticate user with legacy password if they haven't reset it yet.
	 *
	 * This filter runs after WordPress's default authentication.
	 * If the user has a legacy password flag and provides their old password,
	 * verify it against the legacy hash and allow login.
	 *
	 * @since    1.0.0
	 * @param    WP_User|WP_Error|null $user     WP_User if already authenticated, WP_Error if failed, null if not processed yet
	 * @param    string                $username Username or email
	 * @param    string                $password Password
	 * @return   WP_User|WP_Error                User object on success, WP_Error on failure
	 */
	public static function authenticate_legacy_password( $user, string $username, string $password ) {
		// If already authenticated or empty password, return as is
		if ( $user instanceof WP_User || empty( $password ) ) {
			return $user;
		}

		// Get user by username or email
		$user_obj = get_user_by( 'login', $username );
		if ( ! $user_obj ) {
			$user_obj = get_user_by( 'email', $username );
		}

		if ( ! $user_obj ) {
			return $user; // User not found, let WordPress handle it
		}

		// Check if this is a legacy user needing password reset
		$needs_reset = get_user_meta( $user_obj->ID, 'stsrc_legacy_password_needs_reset', true );
		if ( ! $needs_reset ) {
			return $user; // Not a legacy user, let WordPress handle it
		}

		// Get legacy password hash
		$legacy_hash = get_user_meta( $user_obj->ID, 'stsrc_legacy_password_hash', true );
		if ( empty( $legacy_hash ) ) {
			return $user; // No legacy hash stored, let WordPress handle it
		}

		// Verify legacy password (old system used password_hash with PASSWORD_DEFAULT)
		if ( password_verify( $password, $legacy_hash ) ) {
			// Legacy password is correct!
			// Remove the legacy hash (no longer needed)
			delete_user_meta( $user_obj->ID, 'stsrc_legacy_password_hash' );

			// Update WordPress password with the same password they just used
			// This allows them to log in next time with WordPress's authentication
			wp_set_password( $password, $user_obj->ID );

			// Keep the reset flag - they still need to change their password
			// (We'll force them to change it after login)

			// Return authenticated user
			return $user_obj;
		}

		// Legacy password verification failed, let WordPress continue with error
		return $user;
	}

	/**
	 * Redirect users who need to reset their legacy password to password reset page.
	 *
	 * @since    1.0.0
	 * @param    string         $redirect_to           The redirect destination URL
	 * @param    string         $requested_redirect_to The requested redirect destination URL
	 * @param    WP_User|WP_Error $user                WP_User if login successful, WP_Error otherwise
	 * @return   string                                Modified redirect URL
	 */
	public static function force_password_reset_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
		// If login failed, return original redirect
		if ( ! $user instanceof WP_User ) {
			return $redirect_to;
		}

		// Check if user needs to reset legacy password
		$needs_reset = get_user_meta( $user->ID, 'stsrc_legacy_password_needs_reset', true );
		if ( ! $needs_reset ) {
			return $redirect_to;
		}

		// Generate password reset key
		$reset_key = get_password_reset_key( $user );
		if ( is_wp_error( $reset_key ) ) {
			// If key generation failed, log error and continue with normal redirect
			error_log( 'STSRC: Failed to generate password reset key: ' . $reset_key->get_error_message() );
			return $redirect_to;
		}

		// Redirect to password reset page with key
		$reset_url = add_query_arg(
			array(
				'action' => 'stsrc_reset_password',
				'key'    => $reset_key,
				'login'  => rawurlencode( $user->user_login ),
				'legacy' => '1', // Flag to show special message
			),
			home_url( '/member-portal/' )
		);

		return $reset_url;
	}

	/**
	 * Show password reset notice on member portal for legacy users.
	 *
	 * @since    1.0.0
	 */
	public static function show_password_reset_notice(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$needs_reset = get_user_meta( $user_id, 'stsrc_legacy_password_needs_reset', true );

		if ( ! $needs_reset ) {
			return;
		}

		// Check if we're on the password reset page (don't show notice there)
		if ( isset( $_GET['action'] ) && 'stsrc_reset_password' === $_GET['action'] ) {
			return;
		}

		?>
		<div class="stsrc-notice stsrc-notice-warning" style="padding: 15px; margin: 20px 0; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
			<h3 style="margin-top: 0; color: #856404;">Password Reset Required</h3>
			<p style="margin-bottom: 10px; color: #856404;">
				Your account was migrated from our previous system. For security reasons, you must reset your password.
			</p>
			<a href="<?php echo esc_url( self::get_password_reset_url() ); ?>" class="button button-primary">
				Reset Password Now
			</a>
		</div>
		<?php
	}

	/**
	 * Get password reset URL for current user.
	 *
	 * @since    1.0.0
	 * @return   string    Password reset URL
	 */
	private static function get_password_reset_url(): string {
		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return home_url( '/member-portal/' );
		}

		$reset_key = get_password_reset_key( $user );
		if ( is_wp_error( $reset_key ) ) {
			return home_url( '/member-portal/' );
		}

		return add_query_arg(
			array(
				'action' => 'stsrc_reset_password',
				'key'    => $reset_key,
				'login'  => rawurlencode( $user->user_login ),
				'legacy' => '1',
			),
			home_url( '/member-portal/' )
		);
	}

	/**
	 * Handle AJAX password reset for legacy users.
	 *
	 * @since    1.0.0
	 */
	public static function handle_legacy_password_reset(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'stsrc_legacy_password_reset' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}

		// Get user from reset key
		$login = isset( $_POST['login'] ) ? sanitize_user( $_POST['login'] ) : '';
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';

		if ( empty( $login ) || empty( $key ) ) {
			wp_send_json_error( array( 'message' => 'Invalid reset link.' ) );
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => 'Invalid or expired reset link.' ) );
		}

		// Get new password
		$new_password = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '';

		if ( empty( $new_password ) || empty( $confirm_password ) ) {
			wp_send_json_error( array( 'message' => 'Please enter and confirm your new password.' ) );
		}

		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => 'Passwords do not match.' ) );
		}

		// Validate password strength
		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => 'Password must be at least 8 characters long.' ) );
		}

		// Update password
		wp_set_password( $new_password, $user->ID );

		// Remove legacy password flag
		delete_user_meta( $user->ID, 'stsrc_legacy_password_needs_reset' );
		delete_user_meta( $user->ID, 'stsrc_legacy_password_hash' );

		// Log user in
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		wp_send_json_success( array(
			'message'     => 'Password reset successfully!',
			'redirect_to' => home_url( '/member-portal/' ),
		) );
	}
}
