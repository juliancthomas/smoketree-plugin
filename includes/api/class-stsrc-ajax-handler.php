<?php

/**
 * AJAX handler class
 *
 * Handles all AJAX requests for the plugin.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 */

/**
 * AJAX handler class.
 *
 * Provides AJAX endpoints for frontend interactions.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 * @author     Smoketree Swim and Recreation Club
 */
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-logger.php';

class STSRC_Ajax_Handler {

	/**
	 * Login user.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function login(): void {
		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_login_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token. Please refresh the page and try again.' ) );
			return;
		}

		if ( ! $this->enforce_rate_limit( 'login', 10, 5 * MINUTE_IN_SECONDS, __( 'Too many login attempts. Please try again in a few minutes.', 'smoketree-plugin' ) ) ) {
			return;
		}

		$user_login    = sanitize_text_field( $post_data['user_login'] ?? '' );
		$user_password = $post_data['user_password'] ?? '';
		$remember_raw  = $post_data['rememberme'] ?? '';
		$remember      = 'forever' === $remember_raw;
		$redirect_to   = isset( $post_data['redirect_to'] ) ? esc_url_raw( $post_data['redirect_to'] ) : home_url( '/member-portal' );

		if ( empty( $user_login ) || empty( $user_password ) ) {
			wp_send_json_error( array( 'message' => 'Email and password are required.' ) );
			return;
		}

		// Attempt login
		$user = wp_authenticate( $user_login, $user_password );

		if ( is_wp_error( $user ) ) {
			STSRC_Logger::info(
				'Login attempt failed.',
				array(
					'method'  => __METHOD__,
					'login'   => $user_login,
					'ip'      => $this->get_client_ip(),
					'error'   => $user->get_error_code(),
				)
			);
			wp_send_json_error( array( 'message' => 'Invalid email or password.' ) );
			return;
		}

		// Check if user is a member
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( $user->user_email );
		if ( ! $member ) {
			STSRC_Logger::warning(
				'Authenticated WordPress user is not linked to a member record.',
				array(
					'method'  => __METHOD__,
					'user_id' => $user->ID,
					'email'   => $user->user_email,
				)
			);
			wp_send_json_error( array( 'message' => 'This account is not associated with a membership.' ) );
			return;
		}

		// Set auth cookie and log in
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember );

		wp_send_json_success(
			array(
				'message'      => 'Login successful.',
				'redirect_url' => $redirect_to,
			)
		);
	}

	/**
	 * Register member (registration endpoint).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_member(): void {
		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_registration_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token. Please refresh the page and try again.' ) );
			return;
		}

		if ( ! $this->enforce_rate_limit( 'registration', 3, HOUR_IN_SECONDS, __( 'Too many registration attempts. Please try again later.', 'smoketree-plugin' ) ) ) {
			return;
		}

		// Check if registration is enabled
		$registration_enabled = get_option( 'stsrc_registration_enabled', '1' );
		if ( '0' === $registration_enabled || ! $registration_enabled ) {
			STSRC_Logger::info(
				'Registration attempt blocked because registrations are disabled.',
				array(
					'method' => __METHOD__,
					'email'  => $post_data['email'] ?? '',
					'ip'     => $this->get_client_ip(),
				)
			);
			wp_send_json_error( array( 'message' => 'Registration is currently disabled.' ) );
			return;
		}

		// Validate CAPTCHA
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-captcha-service.php';
		$captcha_service = new STSRC_Captcha_Service();
		if ( $captcha_service->is_enabled() ) {
			$captcha_token = sanitize_text_field( $post_data['captcha_token'] ?? '' );
			if ( ! $captcha_service->verify_token( $captcha_token ) ) {
				wp_send_json_error( array( 'message' => 'CAPTCHA verification failed. Please try again.' ) );
				return;
			}
		}

		// Validate and sanitize input
		$data = $this->validate_registration_data( $post_data );
		if ( is_wp_error( $data ) ) {
			STSRC_Logger::info(
				'Registration validation failed.',
				array(
					'method' => __METHOD__,
					'error'  => $data->get_error_code(),
					'ip'     => $this->get_client_ip(),
				)
			);
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
			return;
		}

		// Check for duplicate email or cancelled account
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		
		$existing_member = STSRC_Member_DB::get_member_by_email( $data['email'] );
		
		if ( $existing_member ) {
			// If member is cancelled, send reactivation email instead
			if ( 'cancelled' === $existing_member['status'] ) {
				$this->send_reactivation_email( $existing_member, $data );
				wp_send_json_success(
					array(
						'message' => 'A previous account was found. We\'ve sent a reactivation link to ' . $data['email'] . '. Please check your email to reactivate your account.',
					)
				);
				return;
			}
			
			// If member is active or pending, block registration
			STSRC_Logger::info(
				'Registration attempt blocked due to duplicate email.',
				array(
					'method' => __METHOD__,
					'email'  => $data['email'],
					'status' => $existing_member['status'],
					'ip'     => $this->get_client_ip(),
				)
			);
			wp_send_json_error( array( 'message' => 'An account with this email address already exists.' ) );
			return;
		}

		// Process payment based on payment type
		$payment_type = $data['payment_type'] ?? '';

		if ( in_array( $payment_type, array( 'card', 'bank_account' ), true ) ) {
			// Stripe payment flow
			$result = $this->process_stripe_payment( $data );
			if ( is_wp_error( $result ) ) {
				STSRC_Logger::error(
					'Stripe checkout session creation failed during registration.',
					array(
						'method' => __METHOD__,
						'email'  => $data['email'],
						'error'  => $result->get_error_code(),
					)
				);
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				return;
			}

			wp_send_json_success(
				array(
					'message'      => 'Redirecting to payment...',
					'checkout_url' => $result,
				)
			);
		} else {
			// Manual payment flow (Zelle, Check, Pay Later)
			$result = $this->process_manual_payment( $data );
			if ( is_wp_error( $result ) ) {
				STSRC_Logger::error(
					'Manual registration payment handling failed.',
					array(
						'method' => __METHOD__,
						'email'  => $data['email'],
						'error'  => $result->get_error_code(),
					)
				);
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				return;
			}

			wp_send_json_success(
				array(
					'message' => 'Registration submitted successfully! You will receive an email confirmation shortly.',
				)
			);
		}
	}

	/**
	 * Validate registration data.
	 *
	 * @since    1.0.0
	 * @param    array    $post_data    POST data
	 * @return   array|WP_Error         Validated data or WP_Error on failure
	 */
	private function validate_registration_data( array $post_data ): array|WP_Error {
		$data = array();

		// Required fields
		$required_fields = array(
			'first_name',
			'last_name',
			'email',
			'phone',
			'street_1',
			'membership_type_id',
			'password',
			'password_confirm',
			'waiver_full_name',
			'waiver_signed_date',
			'payment_type',
		);

		foreach ( $required_fields as $field ) {
			if ( empty( $post_data[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( 'The %s field is required.', $field ) );
			}
		}

		// Validate email
		if ( ! is_email( $post_data['email'] ) ) {
			return new WP_Error( 'invalid_email', 'Please enter a valid email address.' );
		}

		// Validate password match
		if ( $post_data['password'] !== $post_data['password_confirm'] ) {
			return new WP_Error( 'password_mismatch', 'Passwords do not match.' );
		}

		// Validate password strength
		if ( strlen( $post_data['password'] ) < 8 ) {
			return new WP_Error( 'weak_password', 'Password must be at least 8 characters long.' );
		}

		// Sanitize and prepare data
		$data['first_name']         = sanitize_text_field( $post_data['first_name'] );
		$data['last_name']          = sanitize_text_field( $post_data['last_name'] );
		$data['email']              = sanitize_email( $post_data['email'] );
		$data['phone']              = sanitize_text_field( $post_data['phone'] );
		$data['street_1']            = sanitize_text_field( $post_data['street_1'] );
		$data['street_2']            = sanitize_text_field( $post_data['street_2'] ?? '' );
		$data['city']               = sanitize_text_field( $post_data['city'] ?? 'Tucker' );
		$data['state']               = sanitize_text_field( $post_data['state'] ?? 'GA' );
		$data['zip']                 = sanitize_text_field( $post_data['zip'] ?? '30084' );
		$data['country']             = sanitize_text_field( $post_data['country'] ?? 'US' );
		$data['referral_source']     = sanitize_text_field( $post_data['referral_source'] ?? '' );
		$data['membership_type_id']  = intval( $post_data['membership_type_id'] );
		$data['password']            = $post_data['password']; // Will be hashed by WordPress
		$data['waiver_full_name']    = sanitize_text_field( $post_data['waiver_full_name'] );
		$data['waiver_signed_date']  = sanitize_text_field( $post_data['waiver_signed_date'] );
		$data['payment_type']        = sanitize_text_field( $post_data['payment_type'] );
		$data['status']              = 'pending';

		// Handle family members if provided
		if ( ! empty( $post_data['family_members'] ) && is_array( $post_data['family_members'] ) ) {
			$data['family_members'] = array();
			foreach ( $post_data['family_members'] as $family_member ) {
				if ( ! empty( $family_member['first_name'] ) && ! empty( $family_member['last_name'] ) ) {
					$data['family_members'][] = array(
						'first_name' => sanitize_text_field( $family_member['first_name'] ),
						'last_name'  => sanitize_text_field( $family_member['last_name'] ),
						'email'      => sanitize_email( $family_member['email'] ?? '' ),
					);
				}
			}
		}

		// Handle extra members if provided
		if ( ! empty( $post_data['extra_members'] ) && is_array( $post_data['extra_members'] ) ) {
			$data['extra_members'] = array();
			foreach ( $post_data['extra_members'] as $extra_member ) {
				if ( ! empty( $extra_member['first_name'] ) && ! empty( $extra_member['last_name'] ) ) {
					$data['extra_members'][] = array(
						'first_name' => sanitize_text_field( $extra_member['first_name'] ),
						'last_name'  => sanitize_text_field( $extra_member['last_name'] ),
						'email'      => sanitize_email( $extra_member['email'] ?? '' ),
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Process Stripe payment for registration.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Registration data
	 * @return   string|WP_Error   Checkout URL or WP_Error on failure
	 */
	private function process_stripe_payment( array $data, int $member_id = 0 ): string|WP_Error {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-payment-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		// Get membership type
		$membership_type = STSRC_Membership_DB::get_membership_type( $data['membership_type_id'] );
		if ( ! $membership_type ) {
			STSRC_Logger::error(
				'Registration attempted with invalid membership type.',
				array(
					'method'              => __METHOD__,
					'membership_type_id'  => $data['membership_type_id'] ?? null,
					'email'               => $data['email'] ?? '',
				)
			);
			return new WP_Error( 'invalid_membership', 'Invalid membership type selected.' );
		}

		// Calculate total with fee
		$payment_service = new STSRC_Payment_Service();
		$membership_slug = strtolower( str_replace( ' ', '-', $membership_type['name'] ) );
		$total = $payment_service->calculate_total_with_fee( (float) $membership_type['price'], $membership_slug );

		// Generate unique key for registration data
		$registration_key = 'reg_' . md5( $data['email'] . time() . wp_rand() );
		$transient_key = 'stsrc_registration_' . $registration_key;

		// If member_id is provided (reactivation), add it to data
		if ( $member_id > 0 ) {
			$data['member_id'] = $member_id;
		}

		// Store registration data in transient (will be retrieved by webhook using metadata)
		set_transient( $transient_key, $data, HOUR_IN_SECONDS * 2 ); // 2 hours

		// Create checkout session
		$checkout_url = $payment_service->create_checkout_session(
			array(
				'amount'         => $total,
				'product_name'   => $membership_type['name'] . ' Membership',
				'customer_email' => $data['email'],
				'customer_name'  => $data['first_name'] . ' ' . $data['last_name'],
				'success_url'    => home_url( '/member-portal?payment=success&session_id={CHECKOUT_SESSION_ID}' ),
				'cancel_url'     => home_url( '/register?payment=cancelled' ),
				'metadata'       => array(
					'membership_type_id' => $data['membership_type_id'],
					'payment_type' => 'registration',
					'registration_key' => $registration_key,
					'member_id' => $member_id,
				),
			)
		);

		if ( ! $checkout_url ) {
			// Clean up transient on failure
			delete_transient( $transient_key );
			STSRC_Logger::error(
				'Stripe checkout session creation returned empty URL during registration.',
				array(
					'method'             => __METHOD__,
					'membership_type_id' => $data['membership_type_id'],
					'email'              => $data['email'],
				)
			);
			return new WP_Error( 'stripe_error', 'Failed to create payment session. Please try again.' );
		}

		return $checkout_url;
	}

	/**
	 * Process manual payment for registration.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Registration data
	 * @return   bool|WP_Error     True on success, WP_Error on failure
	 */
	private function process_manual_payment( array $data ): bool|WP_Error {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		// Create member account with pending status
		$member_service = new STSRC_Member_Service();
		$member_id = $member_service->create_member_account( $data );

		if ( false === $member_id ) {
			STSRC_Logger::error(
				'Manual registration failed to create member account.',
				array(
					'method' => __METHOD__,
					'email'  => $data['email'] ?? '',
				)
			);
			return new WP_Error( 'creation_failed', 'Failed to create member account. Please try again.' );
		}

		// Get membership type for amount
		$membership_type = STSRC_Membership_DB::get_membership_type( $data['membership_type_id'] );
		$amount_due = $membership_type['price'] ?? 0;

		// Send emails
		$email_service = new STSRC_Email_Service();

		// Email to member
		$email_service->send_email(
			'thank-you-pay-later.php',
			array(
				'first_name'          => $data['first_name'],
				'last_name'           => $data['last_name'],
				'email'               => $data['email'],
				'amount_due'          => '$' . number_format( $amount_due, 2 ),
				'payment_instructions' => $this->get_payment_instructions( $data['payment_type'] ),
			),
			$data['email'],
			'Thank You for Your Registration - Smoketree Swim and Recreation Club'
		);

		// Email to admin/treasurer
		$admin_email = get_option( 'admin_email' );
		$secretary_email = get_option( 'stsrc_secretary_email', '' );
		$admin_emails = array_filter( array( $admin_email, $secretary_email ) );

		foreach ( $admin_emails as $admin_email_address ) {
			$email_service->send_email(
				'treasurer-pay-later.php',
				array(
					'first_name'   => $data['first_name'],
					'last_name'    => $data['last_name'],
					'email'        => $data['email'],
					'amount_due'   => '$' . number_format( $amount_due, 2 ),
					'payment_type' => $data['payment_type'],
				),
				$admin_email_address,
				'New Registration - Manual Payment Required'
			);
		}

		return true;
	}

	/**
	 * Get payment instructions for manual payment types.
	 *
	 * @since    1.0.0
	 * @param    string    $payment_type    Payment type
	 * @return   string                      Payment instructions
	 */
	private function get_payment_instructions( string $payment_type ): string {
		$instructions = get_option( 'stsrc_payment_instructions_' . $payment_type, '' );
		if ( ! empty( $instructions ) ) {
			return $instructions;
		}

		// Default instructions
		switch ( $payment_type ) {
			case 'zelle':
				return 'Please send payment via Zelle to the email address provided in your confirmation email.';
			case 'check':
				return 'Please mail your check to the address provided in your confirmation email.';
			case 'pay_later':
				return 'Payment arrangements will be made with club administration.';
			default:
				return 'Please contact the club for payment instructions.';
		}
	}

	/**
	 * Apply rate limiting guard for high-risk actions.
	 *
	 * @since 1.0.0
	 * @param string $action        Action identifier (e.g., login, registration).
	 * @param int    $limit         Maximum attempts within the window.
	 * @param int    $window        Window length in seconds.
	 * @param string $error_message Message returned when rate limited.
	 * @return bool True when processing may continue.
	 */
	private function enforce_rate_limit( string $action, int $limit, int $window, string $error_message ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-captcha-service.php';
		$captcha_service = new STSRC_Captcha_Service();

		if ( $captcha_service->is_rate_limited( $action, $limit, $window ) ) {
			STSRC_Logger::warning(
				'Rate limit triggered for AJAX action.',
				array(
					'action'   => $action,
					'user_id'  => get_current_user_id() ?: null,
					'ip'       => $this->get_client_ip(),
				)
			);
			wp_send_json_error( array( 'message' => $error_message ) );
			return false;
		}

		$captcha_service->increment_rate_limit( $action, $window );

		return true;
	}

	/**
	 * Retrieve client IP address for logging purposes.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$value = wp_unslash( $_SERVER[ $header ] );
				if ( strpos( $value, ',' ) !== false ) {
					$value = trim( explode( ',', $value )[0] );
				}
				$value = trim( $value );
				if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
					return $value;
				}
			}
		}

		return '';
	}

	/**
	 * Update member profile.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function update_profile(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in to update your profile.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_profile_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get member ID from current user
		$user_id = get_current_user_id();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member || (int) $member['user_id'] !== $user_id ) {
			STSRC_Logger::warning(
				'Profile update request failed because member record could not be located.',
				array(
					'method'   => __METHOD__,
					'user_id'  => $user_id,
					'email'    => wp_get_current_user()->user_email,
				)
			);
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		// Prepare update data
		$update_data = array();
		$allowed_fields = array( 'first_name', 'last_name', 'email', 'phone', 'street_1', 'street_2', 'city', 'state', 'zip', 'country' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $post_data[ $field ] ) && ! is_array( $post_data[ $field ] ) ) {
				$update_data[ $field ] = sanitize_text_field( $post_data[ $field ] );
			}
		}

		// Update profile
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		$member_service = new STSRC_Member_Service();
		$result = $member_service->update_member_profile( (int) $member['member_id'], $update_data );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Profile updated successfully.' ) );
		} else {
			STSRC_Logger::error(
				'Member profile update failed via AJAX.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member['member_id'] ?? null,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to update profile.' ) );
		}
	}

	/**
	 * Change password.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function change_password(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in to change your password.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_password_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$current_password  = $post_data['current_password'] ?? '';
		$new_password      = $post_data['new_password'] ?? '';
		$confirm_password  = $post_data['confirm_password'] ?? '';

		// Validate
		if ( empty( $current_password ) || empty( $new_password ) || empty( $confirm_password ) ) {
			wp_send_json_error( array( 'message' => 'All password fields are required.' ) );
			return;
		}

		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => 'New passwords do not match.' ) );
			return;
		}

		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => 'Password must be at least 8 characters long.' ) );
			return;
		}

		// Get member ID
		$user_id = get_current_user_id();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member || (int) $member['user_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		// Change password
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		$member_service = new STSRC_Member_Service();
		$result = $member_service->change_password( (int) $member['member_id'], $current_password, $new_password );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Password changed successfully.' ) );
		} else {
			STSRC_Logger::warning(
				'Password change request failed.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member['member_id'] ?? null,
				)
			);
			wp_send_json_error( array( 'message' => 'Current password is incorrect.' ) );
		}
	}

	/**
	 * Toggle auto-renewal preference.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function toggle_auto_renewal(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to change auto-renewal settings.', 'smoketree-plugin' ) ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_auto_renewal_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'smoketree-plugin' ) ) );
			return;
		}

		$enabled_flag = sanitize_text_field( $post_data['enabled'] ?? '' );
		$enable_auto_renewal = '1' === $enabled_flag;

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member ) {
			wp_send_json_error( array( 'message' => __( 'Member account not found.', 'smoketree-plugin' ) ) );
			return;
		}

		$member_id = (int) $member['member_id'];

		if ( $enable_auto_renewal && empty( $member['stripe_customer_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Add a saved payment method before enabling auto-renewal.', 'smoketree-plugin' ) ) );
			return;
		}

		$current_state = ! empty( $member['auto_renewal_enabled'] );
		if ( $current_state === $enable_auto_renewal ) {
			$message = $enable_auto_renewal
				? __( 'Auto-renewal is already enabled.', 'smoketree-plugin' )
				: __( 'Auto-renewal is already disabled.', 'smoketree-plugin' );

			wp_send_json_success(
				array(
					'message' => $message,
					'enabled' => $current_state,
				)
			);
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		$member_service = new STSRC_Member_Service();
		$result = $member_service->set_auto_renewal_preference( $member_id, $enable_auto_renewal );

		if ( ! $result ) {
			STSRC_Logger::error(
				'Failed to update auto-renewal preference via AJAX.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
					'desired'   => $enable_auto_renewal,
				)
			);
			wp_send_json_error( array( 'message' => __( 'Unable to update auto-renewal preference. Please try again.', 'smoketree-plugin' ) ) );
			return;
		}

		$message = $enable_auto_renewal
			? __( 'Auto-renewal enabled. You will receive a reminder before the renewal date.', 'smoketree-plugin' )
			: __( 'Auto-renewal disabled. You can enable it again at any time.', 'smoketree-plugin' );

		wp_send_json_success(
			array(
				'message' => $message,
				'enabled' => $enable_auto_renewal,
			)
		);
	}

	/**
	 * Bulk update member statuses.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function bulk_update_members(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'smoketree-plugin' ) ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'smoketree-plugin' ) ) );
			return;
		}

		$target          = sanitize_text_field( $post_data['target'] ?? 'selected' );
		$clear_auto      = ! empty( $post_data['clear_auto_renewal'] );
		$reset_guest     = ! empty( $post_data['reset_guest_pass_balance'] );
		$allowed_statuses = array( 'active', 'pending', 'cancelled', 'inactive' );

		// Handle season reset (bulk update all members with a specific status).
		if ( 'season_reset' === $target ) {
			$from_status = sanitize_text_field( $post_data['from_status'] ?? 'active' );
			$new_status  = sanitize_text_field( $post_data['new_status'] ?? 'inactive' );

			if ( ! in_array( $from_status, $allowed_statuses, true ) || ! in_array( $new_status, $allowed_statuses, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid status selected.', 'smoketree-plugin' ) ) );
				return;
			}

			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-auto-renewal-service.php';

			$service  = new STSRC_Auto_Renewal_Service();
			$options  = array(
				'clear_auto_renewal'       => $clear_auto,
				'reset_guest_pass_balance' => $reset_guest,
			);
			$affected = $service->bulk_update_status( $from_status, $new_status, $options );

			if ( ! $affected ) {
				wp_send_json_success(
					array(
						'message' => __( 'No members required updating.', 'smoketree-plugin' ),
						'updated' => 0,
					)
				);
			}

			$message = sprintf(
				/* translators: 1: number of members, 2: status label */
				__( 'Season reset complete. %1$d member(s) moved to %2$s.', 'smoketree-plugin' ),
				(int) $affected,
				ucfirst( $new_status )
			);

			if ( $clear_auto ) {
				$message .= ' ' . __( 'Auto-renewal preferences cleared.', 'smoketree-plugin' );
			}

			if ( $reset_guest ) {
				$message .= ' ' . __( 'Guest pass balances reset.', 'smoketree-plugin' );
			}

			wp_send_json_success(
				array(
					'message' => $message,
					'updated' => (int) $affected,
				)
			);
		}

		$member_ids = array_map( 'intval', (array) ( $post_data['member_ids'] ?? array() ) );
		$member_ids = array_values( array_filter( $member_ids, static fn( $id ) => $id > 0 ) );

		if ( empty( $member_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select at least one member.', 'smoketree-plugin' ) ) );
			return;
		}

		$new_status = sanitize_text_field( $post_data['new_status'] ?? '' );
		if ( ! in_array( $new_status, $allowed_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status selected.', 'smoketree-plugin' ) ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$updated = 0;

		foreach ( $member_ids as $member_id ) {
			$update_data = array(
				'status' => $new_status,
			);

			if ( $clear_auto ) {
				$update_data['auto_renewal_enabled'] = 0;
			}

			if ( $reset_guest ) {
				$update_data['guest_pass_balance'] = 0;
			}

			$updated_member = STSRC_Member_DB::update_member( $member_id, $update_data );
			if ( $updated_member ) {
				$updated++;
				do_action(
					'stsrc_member_bulk_status_updated',
					$member_id,
					$new_status,
					array(
						'cleared_auto_renewal'     => $clear_auto,
						'reset_guest_pass_balance' => $reset_guest,
					)
				);
			}
		}

		if ( 0 === $updated ) {
			wp_send_json_success(
				array(
					'message' => __( 'No members were updated.', 'smoketree-plugin' ),
					'updated' => 0,
				)
			);
		}

		$message = sprintf(
			/* translators: 1: number of members, 2: status label */
			__( 'Updated %1$d member(s) to %2$s.', 'smoketree-plugin' ),
			$updated,
			ucfirst( $new_status )
		);

		if ( $clear_auto ) {
			$message .= ' ' . __( 'Auto-renewal preferences cleared.', 'smoketree-plugin' );
		}

		if ( $reset_guest ) {
			$message .= ' ' . __( 'Guest pass balances reset.', 'smoketree-plugin' );
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'updated' => $updated,
			)
		);
	}

	/**
	 * Add family member.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_family_member(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );
		$is_admin = current_user_can( 'manage_options' );

		// Verify nonce (different nonce for admin vs member portal)
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		$nonce_action = $is_admin ? 'stsrc_admin_nonce' : 'stsrc_family_member_nonce';
		
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		// Get member ID (either from POST for admin or from current user for member portal)
		if ( $is_admin ) {
			$member_id = isset( $post_data['member_id'] ) ? intval( $post_data['member_id'] ) : 0;
			if ( $member_id <= 0 ) {
				wp_send_json_error( array( 'message' => 'Invalid member ID.' ) );
				return;
			}
		} else {
			// Member portal: get member from current user
			$user_id = get_current_user_id();
			$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

			if ( ! $member || (int) $member['user_id'] !== $user_id ) {
				wp_send_json_error( array( 'message' => 'Member not found.' ) );
				return;
			}

			$member_id = (int) $member['member_id'];
		}

		// Validate input
		$first_name = sanitize_text_field( $post_data['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $post_data['last_name'] ?? '' );
		$email      = sanitize_email( $post_data['email'] ?? '' );

		if ( empty( $first_name ) || empty( $last_name ) ) {
			wp_send_json_error( array( 'message' => 'First name and last name are required.' ) );
			return;
		}

		// Check family member limits (will be checked in service layer)
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-family-member-db.php';
		$family_member_id = STSRC_Family_Member_DB::add_family_member(
			$member_id,
			array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'email'      => $email,
			)
		);

		if ( false === $family_member_id ) {
			wp_send_json_error( array( 'message' => 'Failed to add family member. Name may already exist or limit reached.' ) );
			return;
		}

		// Get updated list
		$family_members = STSRC_Family_Member_DB::get_family_members( $member_id );
		wp_send_json_success(
			array(
				'message'        => 'Family member added successfully.',
				'family_members' => $family_members,
			)
		);
	}

	/**
	 * Update family member.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function update_family_member(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_family_member_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get member
		$user_id = get_current_user_id();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		$family_member_id = intval( $post_data['family_member_id'] ?? 0 );
		if ( empty( $family_member_id ) ) {
			wp_send_json_error( array( 'message' => 'Family member ID is required.' ) );
			return;
		}

		// Prepare update data
		$update_data = array();
		if ( ! empty( $post_data['first_name'] ) && ! is_array( $post_data['first_name'] ) ) {
			$update_data['first_name'] = sanitize_text_field( $post_data['first_name'] );
		}
		if ( ! empty( $post_data['last_name'] ) && ! is_array( $post_data['last_name'] ) ) {
			$update_data['last_name'] = sanitize_text_field( $post_data['last_name'] );
		}
		if ( isset( $post_data['email'] ) && ! is_array( $post_data['email'] ) ) {
			$update_data['email'] = sanitize_email( $post_data['email'] );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-family-member-db.php';
		$result = STSRC_Family_Member_DB::update_family_member( $family_member_id, $update_data );

		if ( $result ) {
			$family_members = STSRC_Family_Member_DB::get_family_members( (int) $member['member_id'] );
			wp_send_json_success(
				array(
					'message'        => 'Family member updated successfully.',
					'family_members' => $family_members,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update family member.' ) );
		}
	}

	/**
	 * Delete family member.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function delete_family_member(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );
		$is_admin = current_user_can( 'manage_options' );

		// Verify nonce (different nonce for admin vs member portal)
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		$nonce_action = $is_admin ? 'stsrc_admin_nonce' : 'stsrc_family_member_nonce';
		
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		// For non-admin users, verify they own the family member
		if ( ! $is_admin ) {
			$user_id = get_current_user_id();
			$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

			if ( ! $member ) {
				wp_send_json_error( array( 'message' => 'Member not found.' ) );
				return;
			}
		}

		$family_member_id = intval( $post_data['family_member_id'] ?? 0 );
		if ( empty( $family_member_id ) ) {
			wp_send_json_error( array( 'message' => 'Family member ID is required.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-family-member-db.php';
		$result = STSRC_Family_Member_DB::delete_family_member( $family_member_id );

		if ( $result ) {
			$family_members = STSRC_Family_Member_DB::get_family_members( (int) $member['member_id'] );
			wp_send_json_success(
				array(
					'message'        => 'Family member deleted successfully.',
					'family_members' => $family_members,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete family member.' ) );
		}
	}

	/**
	 * Add extra member.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_extra_member(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );
		$is_admin = current_user_can( 'manage_options' );

		// Verify nonce (different nonce for admin vs member portal)
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		$nonce_action = $is_admin ? 'stsrc_admin_nonce' : 'stsrc_extra_member_nonce';
		
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		// Get member ID (either from POST for admin or from current user for member portal)
		if ( $is_admin ) {
			$member_id = isset( $post_data['member_id'] ) ? intval( $post_data['member_id'] ) : 0;
			if ( $member_id <= 0 ) {
				wp_send_json_error( array( 'message' => 'Invalid member ID.' ) );
				return;
			}
			$member = STSRC_Member_DB::get_member( $member_id );
			if ( ! $member ) {
				wp_send_json_error( array( 'message' => 'Member not found.' ) );
				return;
			}
		} else {
			// Member portal: get member from current user
			$user_id = get_current_user_id();
			$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

			if ( ! $member ) {
				wp_send_json_error( array( 'message' => 'Member not found.' ) );
				return;
			}

			$member_id = (int) $member['member_id'];
		}

		// Validate input
		$first_name = sanitize_text_field( $post_data['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $post_data['last_name'] ?? '' );
		$email      = sanitize_email( $post_data['email'] ?? '' );

		if ( empty( $first_name ) || empty( $last_name ) ) {
			wp_send_json_error( array( 'message' => 'First name and last name are required.' ) );
			return;
		}

		// Check extra member limit (max 3)
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-extra-member-db.php';
		$current_count = STSRC_Extra_Member_DB::count_extra_members( $member_id );
		if ( $current_count >= 3 ) {
			wp_send_json_error( array( 'message' => 'Maximum of 3 extra members allowed.' ) );
			return;
		}

		// Admin bypass: directly add extra member without Stripe payment
		// Default to "paid" status since admin would have verified offline payment
		if ( $is_admin ) {
			$extra_member_id = STSRC_Extra_Member_DB::add_extra_member(
				$member_id,
				array(
					'first_name'     => $first_name,
					'last_name'      => $last_name,
					'email'          => $email,
					'payment_status' => 'paid', // Admin-added members default to paid
				)
			);

			if ( false === $extra_member_id ) {
				wp_send_json_error( array( 'message' => 'Failed to add extra member. Name may already exist.' ) );
				return;
			}

			wp_send_json_success(
				array(
					'message' => 'Extra member added successfully (marked as paid).',
				)
			);
			return;
		}

		// Member portal: Create Stripe checkout for $50
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-payment-service.php';
		$payment_service = new STSRC_Payment_Service();

		$checkout_url = $payment_service->create_checkout_session(
			array(
				'amount'         => 50.00,
				'product_name'   => 'Extra Member - ' . $first_name . ' ' . $last_name,
				'customer_id'    => $member['stripe_customer_id'] ?? null,
				'customer_email' => $member['email'],
				'success_url'    => home_url( '/member-portal?extra_member=success&session_id={CHECKOUT_SESSION_ID}' ),
				'cancel_url'     => home_url( '/member-portal?extra_member=cancelled' ),
				'metadata'       => array(
					'payment_type' => 'extra_member',
					'member_id'    => $member_id,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'email'        => $email,
				),
			)
		);

		if ( ! $checkout_url ) {
			STSRC_Logger::error(
				'Failed to create Stripe checkout session for extra member.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
					'name'      => $first_name . ' ' . $last_name,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to create payment session.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message'      => 'Redirecting to payment...',
				'checkout_url' => $checkout_url,
			)
		);
	}

	/**
	 * Update extra member.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function update_extra_member(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_extra_member_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get member
		$user_id = get_current_user_id();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		$extra_member_id = intval( $post_data['extra_member_id'] ?? 0 );
		if ( empty( $extra_member_id ) ) {
			wp_send_json_error( array( 'message' => 'Extra member ID is required.' ) );
			return;
		}

		// Prepare update data
		$update_data = array();
		if ( ! empty( $post_data['first_name'] ) && ! is_array( $post_data['first_name'] ) ) {
			$update_data['first_name'] = sanitize_text_field( $post_data['first_name'] );
		}
		if ( ! empty( $post_data['last_name'] ) && ! is_array( $post_data['last_name'] ) ) {
			$update_data['last_name'] = sanitize_text_field( $post_data['last_name'] );
		}
		if ( isset( $post_data['email'] ) && ! is_array( $post_data['email'] ) ) {
			$update_data['email'] = sanitize_email( $post_data['email'] );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-extra-member-db.php';
		$result = STSRC_Extra_Member_DB::update_extra_member( $extra_member_id, $update_data );

		if ( $result ) {
			$extra_members = STSRC_Extra_Member_DB::get_extra_members( (int) $member['member_id'] );
			wp_send_json_success(
				array(
					'message'      => 'Extra member updated successfully.',
					'extra_members' => $extra_members,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update extra member.' ) );
		}
	}

	/**
	 * Delete extra member.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function delete_extra_member(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );
		$is_admin = current_user_can( 'manage_options' );

		// Verify nonce (different nonce for admin vs member portal)
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		$nonce_action = $is_admin ? 'stsrc_admin_nonce' : 'stsrc_extra_member_nonce';
		
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		// For non-admin users, verify they own the extra member
		if ( ! $is_admin ) {
			$user_id = get_current_user_id();
			$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

			if ( ! $member ) {
				wp_send_json_error( array( 'message' => 'Member not found.' ) );
				return;
			}
		}

		$extra_member_id = intval( $post_data['extra_member_id'] ?? 0 );
		if ( empty( $extra_member_id ) ) {
			wp_send_json_error( array( 'message' => 'Extra member ID is required.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-extra-member-db.php';
		$result = STSRC_Extra_Member_DB::delete_extra_member( $extra_member_id );

		if ( $result ) {
			$extra_members = STSRC_Extra_Member_DB::get_extra_members( (int) $member['member_id'] );
			wp_send_json_success(
				array(
					'message'      => 'Extra member deleted successfully.',
					'extra_members' => $extra_members,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete extra member.' ) );
		}
	}

	/**
	 * Use guest pass (decrement balance and log usage).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function use_guest_pass(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_guest_pass_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get member
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		$member_id = (int) $member['member_id'];
		$notes = sanitize_text_field( $post_data['notes'] ?? '' );

		// Use guest pass
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-guest-pass-db.php';
		$result = STSRC_Guest_Pass_DB::use_guest_pass( $member_id, $notes );

		if ( $result ) {
			// Send admin notification
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
			$email_service = new STSRC_Email_Service();
			$admin_email = get_option( 'admin_email' );
			$secretary_email = get_option( 'stsrc_secretary_email', '' );
			$admin_emails = array_filter( array( $admin_email, $secretary_email ) );

			foreach ( $admin_emails as $admin_email_address ) {
				$email_service->send_email(
					'notify-admin-guest-pass-was-used.php',
					array(
						'first_name' => $member['first_name'],
						'last_name'  => $member['last_name'],
						'email'      => $member['email'],
						'balance'    => STSRC_Guest_Pass_DB::get_guest_pass_balance( $member_id ),
					),
					$admin_email_address,
					'Guest Pass Used - Smoketree Swim and Recreation Club'
				);
			}

			wp_send_json_success(
				array(
					'message' => 'Guest pass used successfully.',
					'balance' => STSRC_Guest_Pass_DB::get_guest_pass_balance( $member_id ),
				)
			);
		} else {
			STSRC_Logger::warning(
				'Guest pass usage failed.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to use guest pass. You may not have any passes available.' ) );
		}
	}

	/**
	 * Purchase guest passes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function purchase_guest_passes(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_guest_pass_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get member
		$user_id = get_current_user_id();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		$quantity = intval( $post_data['quantity'] ?? 0 );
		if ( $quantity <= 0 ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid quantity.' ) );
			return;
		}

		// Calculate total ($5 per pass)
		$total = $quantity * 5.00;

		// Create Stripe checkout
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-payment-service.php';
		$payment_service = new STSRC_Payment_Service();

		$checkout_url = $payment_service->create_checkout_session(
			array(
				'amount'         => $total,
				'product_name'   => $quantity . ' Guest Pass' . ( $quantity > 1 ? 'es' : '' ),
				'customer_id'    => $member['stripe_customer_id'] ?? null,
				'customer_email' => $member['email'],
				'success_url'    => home_url( '/guest-pass-portal?purchase=success&session_id={CHECKOUT_SESSION_ID}' ),
				'cancel_url'     => home_url( '/member-portal?guest_pass=cancelled' ),
				'metadata'       => array(
					'payment_type' => 'guest_pass',
					'member_id'    => (int) $member['member_id'],
					'quantity'     => $quantity,
				),
			)
		);

		if ( ! $checkout_url ) {
			STSRC_Logger::error(
				'Failed to create Stripe checkout session for guest pass purchase.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member['member_id'] ?? null,
					'quantity'  => $quantity,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to create payment session.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message'      => 'Redirecting to payment...',
				'checkout_url' => $checkout_url,
			)
		);
	}

	/**
	 * Get Stripe Customer Portal URL.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function get_customer_portal_url(): void {
		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_portal_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get member
		$user_id = get_current_user_id();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( wp_get_current_user()->user_email );

		if ( ! $member || empty( $member['stripe_customer_id'] ) ) {
			STSRC_Logger::warning(
				'Stripe customer portal request failed because member has no Stripe customer ID.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member['member_id'] ?? null,
				)
			);
			wp_send_json_error( array( 'message' => 'Stripe customer not found.' ) );
			return;
		}

		// Get portal URL
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-payment-service.php';
		$payment_service = new STSRC_Payment_Service();
		$portal_url = $payment_service->get_customer_portal_url( $member['stripe_customer_id'] );

		if ( ! $portal_url ) {
			STSRC_Logger::error(
				'Failed to generate Stripe customer portal URL.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member['member_id'] ?? null,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to generate portal URL.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'portal_url' => $portal_url,
			)
		);
	}

	/**
	 * Export members to CSV.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function export_members(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Verify nonce
		$nonce = sanitize_text_field( $_POST['nonce'] ?? $_GET['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get filters
		$filters = array();
		if ( ! empty( $_POST['membership_type_id'] ?? $_GET['membership_type_id'] ?? '' ) ) {
			$filters['membership_type_id'] = intval( $_POST['membership_type_id'] ?? $_GET['membership_type_id'] ?? 0 );
		}
		if ( ! empty( $_POST['status'] ?? $_GET['status'] ?? '' ) ) {
			$filters['status'] = sanitize_text_field( $_POST['status'] ?? $_GET['status'] ?? '' );
		}
		if ( ! empty( $_POST['payment_type'] ?? $_GET['payment_type'] ?? '' ) ) {
			$filters['payment_type'] = sanitize_text_field( $_POST['payment_type'] ?? $_GET['payment_type'] ?? '' );
		}
		if ( ! empty( $_POST['date_from'] ?? $_GET['date_from'] ?? '' ) ) {
			$filters['date_from'] = sanitize_text_field( $_POST['date_from'] ?? $_GET['date_from'] ?? '' );
		}
		if ( ! empty( $_POST['date_to'] ?? $_GET['date_to'] ?? '' ) ) {
			$filters['date_to'] = sanitize_text_field( $_POST['date_to'] ?? $_GET['date_to'] ?? '' );
		}
		if ( ! empty( $_POST['search'] ?? $_GET['search'] ?? '' ) ) {
			$filters['search'] = sanitize_text_field( $_POST['search'] ?? $_GET['search'] ?? '' );
		}

		// Get members
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$members = STSRC_Member_DB::get_members( $filters );

		// Generate CSV
		$filename = 'members-export-' . date( 'Y-m-d-H-i-s' ) . '.csv';
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// CSV headers
		fputcsv(
			$output,
			array(
				'Member ID',
				'Name',
				'Email',
				'Phone',
				'Address',
				'City',
				'State',
				'ZIP',
				'Membership Type',
				'Status',
				'Payment Type',
				'Created Date',
				'Expiration Date',
			)
		);

		// Get membership types for lookup
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
		$membership_types = array();
		$all_types = STSRC_Membership_DB::get_all_membership_types();
		foreach ( $all_types as $type ) {
			$membership_types[ $type['membership_type_id'] ] = $type['name'];
		}

		// CSV rows
		foreach ( $members as $member ) {
			$membership_type_name = $membership_types[ $member['membership_type_id'] ] ?? 'Unknown';
			$address = $member['street_1'];
			if ( ! empty( $member['street_2'] ) ) {
				$address .= ', ' . $member['street_2'];
			}

			fputcsv(
				$output,
				array(
					$member['member_id'],
					$member['first_name'] . ' ' . $member['last_name'],
					$member['email'],
					$member['phone'],
					$address,
					$member['city'],
					$member['state'],
					$member['zip'],
					$membership_type_name,
					$member['status'],
					$member['payment_type'],
					$member['created_at'],
					$member['expiration_date'] ?? '',
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Send batch email.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function send_batch_email(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Verify nonce
		$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$subject = sanitize_text_field( $_POST['subject'] ?? '' );
		$message = wp_kses_post( $_POST['message'] ?? '' );
		$template = sanitize_text_field( $_POST['template'] ?? '' );
		$is_test = isset( $_POST['test_email'] ) && '1' === $_POST['test_email'];

		if ( empty( $subject ) ) {
			wp_send_json_error( array( 'message' => 'Subject is required.' ) );
			return;
		}

		if ( empty( $message ) && empty( $template ) ) {
			wp_send_json_error( array( 'message' => 'Either a message or template is required.' ) );
			return;
		}

		// Get filters
		$filters = array();
		if ( ! empty( $_POST['membership_type_id'] ) ) {
			$filters['membership_type_id'] = intval( $_POST['membership_type_id'] );
		}
		if ( ! empty( $_POST['status'] ) ) {
			$filters['status'] = sanitize_text_field( $_POST['status'] );
		}
		if ( ! empty( $_POST['payment_type'] ) ) {
			$filters['payment_type'] = sanitize_text_field( $_POST['payment_type'] );
		}
		if ( ! empty( $_POST['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( $_POST['date_from'] );
		}
		if ( ! empty( $_POST['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( $_POST['date_to'] );
		}

		// Get recipients
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$members = STSRC_Member_DB::get_members( $filters );

		// Handle test email
		if ( $is_test ) {
			$admin_email = get_option( 'admin_email' );
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
			$email_service = new STSRC_Email_Service();

			$email_template = ! empty( $template ) ? $template : 'payment-reminder.php';
			$template_data = array(
				'message'    => $message,
				'first_name' => 'Admin',
				'last_name'  => 'Test',
				'email'      => $admin_email,
			);

			$result = $email_service->send_email( $email_template, $template_data, $admin_email, '[TEST] ' . $subject );

			if ( $result ) {
				wp_send_json_success( array( 'message' => 'Test email sent successfully to ' . $admin_email . '.' ) );
			} else {
				wp_send_json_error( array( 'message' => 'Failed to send test email.' ) );
			}
			return;
		}

		if ( empty( $members ) ) {
			wp_send_json_error( array( 'message' => 'No members found matching the criteria.' ) );
			return;
		}

		// Prepare recipient list
		$recipients = array();
		foreach ( $members as $member ) {
			$recipients[] = (int) $member['member_id'];
		}

		// Handle attachments
		$attachments = array();
		if ( ! empty( $_FILES['attachments'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			foreach ( $_FILES['attachments']['tmp_name'] as $key => $tmp_name ) {
				if ( ! empty( $tmp_name ) && is_uploaded_file( $tmp_name ) ) {
					$file = array(
						'name'     => $_FILES['attachments']['name'][ $key ],
						'type'     => $_FILES['attachments']['type'][ $key ],
						'tmp_name' => $tmp_name,
						'error'    => $_FILES['attachments']['error'][ $key ],
						'size'     => $_FILES['attachments']['size'][ $key ],
					);

					$upload = wp_handle_upload( $file, array( 'test_form' => false ) );
					if ( ! isset( $upload['error'] ) ) {
						$attachments[] = $upload['file'];
					}
				}
			}
		}

		// Send batch email
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
		$email_service = new STSRC_Email_Service();

		$campaign_id = 'batch_' . time() . '_' . wp_rand();
		$template_data = array(
			'message' => $message,
		);

		// Use custom template if provided, otherwise use a default
		$email_template = ! empty( $template ) ? $template : 'payment-reminder.php';

		$results = $email_service->send_batch_email(
			$recipients,
			$email_template,
			$template_data,
			$subject,
			$attachments,
			$campaign_id
		);

		// Clean up attachments
		foreach ( $attachments as $attachment ) {
			if ( file_exists( $attachment ) ) {
				unlink( $attachment );
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					'Batch email sent: %d successful, %d failed out of %d total.',
					$results['sent'],
					$results['failed'],
					$results['total']
				),
				'results' => $results,
			)
		);
	}

	/**
	 * Preview recipient count for batch email.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function preview_recipients(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Verify nonce
		$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Get filters
		$filters = array();
		if ( ! empty( $_POST['filters']['membership_type_id'] ) ) {
			$filters['membership_type_id'] = intval( $_POST['filters']['membership_type_id'] );
		}
		if ( ! empty( $_POST['filters']['status'] ) ) {
			$filters['status'] = sanitize_text_field( $_POST['filters']['status'] );
		}
		if ( ! empty( $_POST['filters']['payment_type'] ) ) {
			$filters['payment_type'] = sanitize_text_field( $_POST['filters']['payment_type'] );
		}
		if ( ! empty( $_POST['filters']['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( $_POST['filters']['date_from'] );
		}
		if ( ! empty( $_POST['filters']['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( $_POST['filters']['date_to'] );
		}

		// Get members count
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$members = STSRC_Member_DB::get_members( $filters );
		$count = count( $members );

		wp_send_json_success(
			array(
				'count' => $count,
				'message' => sprintf( '%d %s will receive this email', $count, $count === 1 ? 'recipient' : 'recipients' ),
			)
		);
	}

	/**
	 * Create access code (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function create_access_code(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Validate required fields
		if ( empty( $post_data['code'] ) ) {
			wp_send_json_error( array( 'message' => 'Code is required.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-access-code-db.php';

		// Check for duplicate code
		$all_codes = STSRC_Access_Code_DB::get_access_codes();
		foreach ( $all_codes as $code ) {
			if ( strtolower( $code['code'] ) === strtolower( sanitize_text_field( $post_data['code'] ) ) ) {
				wp_send_json_error( array( 'message' => 'An access code with this value already exists.' ) );
				return;
			}
		}

		// Prepare access code data
		$expires_at = null;
		if ( ! empty( $post_data['expires_at'] ) && '' !== trim( (string) $post_data['expires_at'] ) ) {
			// Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL datetime format
			$expires_at = sanitize_text_field( $post_data['expires_at'] );
			$expires_at = str_replace( 'T', ' ', $expires_at ) . ':00'; // Add seconds
		}

		$code_data = array(
			'code'        => sanitize_text_field( $post_data['code'] ),
			'description' => sanitize_text_field( $post_data['description'] ?? '' ),
			'expires_at'  => $expires_at,
			'is_active'   => isset( $post_data['is_active'] ) ? 1 : 0,
			'is_premium'  => isset( $post_data['is_premium'] ) ? 1 : 0,
		);

		// Create access code
		$code_id = STSRC_Access_Code_DB::create_access_code( $code_data );

		if ( ! $code_id ) {
			STSRC_Logger::error(
				'Failed to create access code record.',
				array(
					'method' => __METHOD__,
					'code'   => $code_data['code'],
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to create access code.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message'      => 'Access code created successfully.',
				'code_id'      => $code_id,
				'redirect_url' => admin_url( 'admin.php?page=stsrc-access-codes' ),
			)
		);
	}

	/**
	 * Update access code (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function update_access_code(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$code_id = isset( $post_data['code_id'] ) ? intval( $post_data['code_id'] ) : 0;
		if ( $code_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid access code ID.' ) );
			return;
		}

		// Validate required fields
		if ( empty( $post_data['code'] ) ) {
			wp_send_json_error( array( 'message' => 'Code is required.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-access-code-db.php';

		// Check if access code exists
		$all_codes = STSRC_Access_Code_DB::get_access_codes();
		$existing = null;
		foreach ( $all_codes as $code ) {
			if ( (int) $code['code_id'] === $code_id ) {
				$existing = $code;
				break;
			}
		}

		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => 'Access code not found.' ) );
			return;
		}

		// Check for duplicate code (excluding current)
		$new_code = strtolower( sanitize_text_field( $post_data['code'] ) );
		foreach ( $all_codes as $code ) {
			if ( (int) $code['code_id'] !== $code_id && strtolower( $code['code'] ) === $new_code ) {
				wp_send_json_error( array( 'message' => 'An access code with this value already exists.' ) );
				return;
			}
		}

		// Prepare update data
		$expires_at = null;
		if ( ! empty( $post_data['expires_at'] ) && '' !== trim( (string) $post_data['expires_at'] ) ) {
			// Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL datetime format
			$expires_at = sanitize_text_field( $post_data['expires_at'] );
			$expires_at = str_replace( 'T', ' ', $expires_at ) . ':00'; // Add seconds
		}

		$update_data = array(
			'code'        => sanitize_text_field( $post_data['code'] ),
			'description' => sanitize_text_field( $post_data['description'] ?? '' ),
			'expires_at'  => $expires_at,
			'is_active'   => isset( $post_data['is_active'] ) ? 1 : 0,
			'is_premium'  => isset( $post_data['is_premium'] ) ? 1 : 0,
		);

		// Update access code
		$result = STSRC_Access_Code_DB::update_access_code( $code_id, $update_data );

		if ( ! $result ) {
			STSRC_Logger::error(
				'Failed to update access code record.',
				array(
					'method' => __METHOD__,
					'code_id'=> $code_id,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to update access code.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message'      => 'Access code updated successfully.',
				'code_id'      => $code_id,
				'redirect_url' => admin_url( 'admin.php?page=stsrc-access-codes' ),
			)
		);
	}

	/**
	 * Delete access code (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function delete_access_code(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );
		$get_data  = wp_unslash( $_GET );

		// Verify nonce
		$nonce_value = '';
		if ( isset( $post_data['nonce'] ) && ! is_array( $post_data['nonce'] ) ) {
			$nonce_value = $post_data['nonce'];
		} elseif ( isset( $get_data['nonce'] ) && ! is_array( $get_data['nonce'] ) ) {
			$nonce_value = $get_data['nonce'];
		}
		$nonce = sanitize_text_field( $nonce_value );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$code_id_raw = $post_data['code_id'] ?? ( $get_data['code_id'] ?? 0 );
		$code_id     = intval( $code_id_raw );
		if ( $code_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid access code ID.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-access-code-db.php';

		// Delete access code
		$result = STSRC_Access_Code_DB::delete_access_code( $code_id );

		if ( ! $result ) {
			STSRC_Logger::error(
				'Failed to delete access code record.',
				array(
					'method' => __METHOD__,
					'code_id'=> $code_id,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to delete access code.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message'      => 'Access code deleted successfully.',
				'redirect_url' => admin_url( 'admin.php?page=stsrc-access-codes' ),
			)
		);
	}

	/**
	 * Save settings (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function save_settings(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Save Stripe settings
		if ( isset( $post_data['stripe_publishable_key'] ) ) {
			update_option( 'stsrc_stripe_publishable_key', sanitize_text_field( $post_data['stripe_publishable_key'] ) );
		}
		if ( isset( $post_data['stripe_secret_key'] ) ) {
			update_option( 'stsrc_stripe_secret_key', sanitize_text_field( $post_data['stripe_secret_key'] ) );
		}
		if ( isset( $post_data['stripe_test_mode'] ) ) {
			update_option( 'stsrc_stripe_test_mode', '1' );
		} else {
			update_option( 'stsrc_stripe_test_mode', '0' );
		}
		if ( isset( $post_data['stripe_test_publishable_key'] ) ) {
			update_option( 'stsrc_stripe_test_publishable_key', sanitize_text_field( $post_data['stripe_test_publishable_key'] ) );
		}
		if ( isset( $post_data['stripe_test_secret_key'] ) ) {
			update_option( 'stsrc_stripe_test_secret_key', sanitize_text_field( $post_data['stripe_test_secret_key'] ) );
		}
		if ( isset( $post_data['stripe_webhook_secret'] ) ) {
			update_option( 'stsrc_stripe_webhook_secret', sanitize_text_field( $post_data['stripe_webhook_secret'] ) );
		}

		// Save CAPTCHA settings
		$captcha_provider = sanitize_text_field( $post_data['captcha_provider'] ?? 'recaptcha' );
		update_option( 'stsrc_captcha_provider', $captcha_provider );
		
		if ( isset( $post_data['captcha_enabled'] ) ) {
			update_option( 'stsrc_captcha_enabled', '1' );
		} else {
			update_option( 'stsrc_captcha_enabled', '0' );
		}
		
		// Save provider-specific keys
		$captcha_site_key_option = 'stsrc_captcha_' . $captcha_provider . '_site_key';
		$captcha_secret_key_option = 'stsrc_captcha_' . $captcha_provider . '_secret_key';
		if ( isset( $post_data['captcha_site_key'] ) ) {
			update_option( $captcha_site_key_option, sanitize_text_field( $post_data['captcha_site_key'] ) );
		}
		if ( isset( $post_data['captcha_secret_key'] ) ) {
			update_option( $captcha_secret_key_option, sanitize_text_field( $post_data['captcha_secret_key'] ) );
		}

		// Save general settings
		if ( isset( $post_data['registration_enabled'] ) ) {
			update_option( 'stsrc_registration_enabled', '1' );
		} else {
			update_option( 'stsrc_registration_enabled', '0' );
		}
		if ( isset( $post_data['payment_plan_enabled'] ) ) {
			update_option( 'stsrc_payment_plan_enabled', '1' );
		} else {
			update_option( 'stsrc_payment_plan_enabled', '0' );
		}
		if ( isset( $post_data['secretary_email'] ) ) {
			$raw_secretary_email = is_array( $post_data['secretary_email'] ) ? '' : $post_data['secretary_email'];
			$email                = sanitize_email( $raw_secretary_email );
			if ( '' === $raw_secretary_email || ! empty( $email ) ) {
				update_option( 'stsrc_secretary_email', $email );
			}
		}
		if ( isset( $post_data['season_renewal_date'] ) ) {
			update_option( 'stsrc_season_renewal_date', sanitize_text_field( $post_data['season_renewal_date'] ) );
		}

		// If ACF is available, also save to ACF options
		if ( function_exists( 'update_field' ) ) {
			if ( isset( $post_data['stripe_publishable_key'] ) ) {
				update_field( 'stsrc_stripe_publishable_key', sanitize_text_field( $post_data['stripe_publishable_key'] ), 'option' );
			}
			if ( isset( $post_data['stripe_secret_key'] ) ) {
				update_field( 'stsrc_stripe_secret_key', sanitize_text_field( $post_data['stripe_secret_key'] ), 'option' );
			}
			// Add other ACF field updates as needed
		}

		wp_send_json_success(
			array(
				'message' => 'Settings saved successfully.',
			)
		);
	}

	/**
	 * Admin adjust guest pass balance.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function admin_adjust_guest_passes(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$member_id  = intval( $post_data['member_id'] ?? 0 );
		$adjustment = intval( $post_data['adjustment'] ?? 0 );
		$notes      = sanitize_text_field( $post_data['notes'] ?? '' );

		if ( empty( $member_id ) || $adjustment === 0 ) {
			wp_send_json_error( array( 'message' => 'Member ID and adjustment amount are required.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-guest-pass-db.php';
		$result = STSRC_Guest_Pass_DB::admin_adjust_balance( $member_id, $adjustment, $notes );

		if ( $result ) {
			$balance = STSRC_Guest_Pass_DB::get_guest_pass_balance( $member_id );
			wp_send_json_success(
				array(
					'message' => 'Guest pass balance adjusted successfully.',
					'balance' => $balance,
				)
			);
		} else {
			STSRC_Logger::error(
				'Admin guest pass balance adjustment failed.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
					'adjustment'=> $adjustment,
				)
			);
			wp_send_json_error( array( 'message' => 'Failed to adjust guest pass balance.' ) );
		}
	}

	/**
	 * Create member (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function create_member(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Validate required fields
		$required_fields = array( 'first_name', 'last_name', 'email', 'phone', 'street_1', 'city', 'state', 'zip', 'membership_type_id', 'status', 'payment_type', 'waiver_full_name', 'waiver_signed_date' );
		foreach ( $required_fields as $field ) {
			if ( empty( $post_data[ $field ] ) ) {
				wp_send_json_error( array( 'message' => sprintf( 'Field %s is required.', $field ) ) );
				return;
			}
		}

		// Prepare member data
		$member_data = array(
			'first_name'        => sanitize_text_field( $post_data['first_name'] ),
			'last_name'         => sanitize_text_field( $post_data['last_name'] ),
			'email'             => sanitize_email( $post_data['email'] ),
			'phone'             => sanitize_text_field( $post_data['phone'] ),
			'street_1'          => sanitize_text_field( $post_data['street_1'] ),
			'street_2'          => sanitize_text_field( $post_data['street_2'] ?? '' ),
			'city'              => sanitize_text_field( $post_data['city'] ),
			'state'             => strtoupper( sanitize_text_field( $post_data['state'] ) ),
			'zip'               => sanitize_text_field( $post_data['zip'] ),
			'country'           => sanitize_text_field( $post_data['country'] ?? 'US' ),
			'membership_type_id'=> intval( $post_data['membership_type_id'] ),
			'status'            => sanitize_text_field( $post_data['status'] ),
			'payment_type'      => sanitize_text_field( $post_data['payment_type'] ),
			'waiver_full_name'  => sanitize_text_field( $post_data['waiver_full_name'] ),
			'waiver_signed_date'=> sanitize_text_field( $post_data['waiver_signed_date'] ),
			'referral_source'   => sanitize_text_field( $post_data['referral_source'] ?? '' ),
			'password'          => wp_generate_password( 12, true, true ), // Generate secure password
		);

		// Create member account (WordPress user + member record)
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		$member_service = new STSRC_Member_Service();
		$member_id = $member_service->create_member_account( $member_data );

		if ( ! $member_id ) {
			wp_send_json_error( array( 'message' => 'Failed to create member. Email may already be in use.' ) );
			return;
		}

		// Send password reset email to new member
		$user = get_user_by( 'email', $member_data['email'] );
		if ( $user ) {
			$reset_key = get_password_reset_key( $user );
			if ( ! is_wp_error( $reset_key ) ) {
				// Send email with reset link
				$reset_url = network_site_url( "wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode( $user->user_login ), 'login' );
				
				$subject = 'Welcome to Smoketree Swim and Recreation Club - Set Your Password';
				$message = sprintf(
					"Hello %s,\n\n" .
					"An account has been created for you at Smoketree Swim and Recreation Club.\n\n" .
					"To set your password and access your account, please click the link below:\n\n" .
					"%s\n\n" .
					"If you did not request this account, please ignore this email.\n\n" .
					"Best regards,\n" .
					"Smoketree Swim and Recreation Club",
					$member_data['first_name'],
					$reset_url
				);
				
				wp_mail( $member_data['email'], $subject, $message );
			}
		}

		wp_send_json_success(
			array(
				'message'    => 'Member created successfully. A password setup email has been sent to ' . $member_data['email'],
				'member_id'  => $member_id,
				'redirect_url' => admin_url( 'admin.php?page=stsrc-members' ),
			)
		);
	}

	/**
	 * Update member (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function update_member(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$member_id = isset( $post_data['member_id'] ) ? intval( $post_data['member_id'] ) : 0;
		if ( $member_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid member ID.' ) );
			return;
		}

		// Validate required fields
		$required_fields = array( 'first_name', 'last_name', 'email', 'phone', 'street_1', 'city', 'state', 'zip', 'membership_type_id', 'status', 'payment_type', 'waiver_full_name', 'waiver_signed_date' );
		foreach ( $required_fields as $field ) {
			if ( empty( $post_data[ $field ] ) ) {
				wp_send_json_error( array( 'message' => sprintf( 'Field %s is required.', $field ) ) );
				return;
			}
		}

		// Check if email is being changed and if it's already in use
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$current_member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $current_member ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		$new_email = sanitize_email( $post_data['email'] );
		if ( $new_email !== $current_member['email'] ) {
			$existing = STSRC_Member_DB::get_member_by_email( $new_email );
			if ( $existing && $existing['member_id'] !== $member_id ) {
				wp_send_json_error( array( 'message' => 'A member with this email already exists.' ) );
				return;
			}
		}

		// Prepare update data
		$update_data = array(
			'first_name'        => sanitize_text_field( $post_data['first_name'] ),
			'last_name'         => sanitize_text_field( $post_data['last_name'] ),
			'email'             => $new_email,
			'phone'             => sanitize_text_field( $post_data['phone'] ),
			'street_1'          => sanitize_text_field( $post_data['street_1'] ),
			'street_2'          => sanitize_text_field( $post_data['street_2'] ?? '' ),
			'city'              => sanitize_text_field( $post_data['city'] ),
			'state'             => strtoupper( sanitize_text_field( $post_data['state'] ) ),
			'zip'               => sanitize_text_field( $post_data['zip'] ),
			'country'           => sanitize_text_field( $post_data['country'] ?? 'US' ),
			'membership_type_id'=> intval( $post_data['membership_type_id'] ),
			'status'            => sanitize_text_field( $post_data['status'] ),
			'payment_type'      => sanitize_text_field( $post_data['payment_type'] ),
			'waiver_full_name'  => sanitize_text_field( $post_data['waiver_full_name'] ),
			'waiver_signed_date'=> sanitize_text_field( $post_data['waiver_signed_date'] ),
			'referral_source'   => sanitize_text_field( $post_data['referral_source'] ?? '' ),
		);

		// Update member
		$result = STSRC_Member_DB::update_member( $member_id, $update_data );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => 'Failed to update member.' ) );
			return;
		}

		// Handle password change if provided
		$new_password = $post_data['new_password'] ?? '';
		$confirm_password = $post_data['confirm_password'] ?? '';

		if ( ! empty( $new_password ) || ! empty( $confirm_password ) ) {
			// Validate passwords match
			if ( $new_password !== $confirm_password ) {
				wp_send_json_error( array( 'message' => 'Passwords do not match.' ) );
				return;
			}

			// Validate password length
			if ( strlen( $new_password ) < 8 ) {
				wp_send_json_error( array( 'message' => 'Password must be at least 8 characters long.' ) );
				return;
			}

			// Update password without sending email
			if ( ! empty( $current_member['user_id'] ) ) {
				// Prevent WordPress from sending password change email
				add_filter( 'send_password_change_email', '__return_false' );
				add_filter( 'send_email_change_email', '__return_false' );
				
				wp_set_password( $new_password, $current_member['user_id'] );
				
				// Re-enable email filters for other operations
				remove_filter( 'send_password_change_email', '__return_false' );
				remove_filter( 'send_email_change_email', '__return_false' );
			}
		}

		wp_send_json_success(
			array(
				'message' => 'Member updated successfully.' . ( ! empty( $new_password ) ? ' Password has been changed.' : '' ),
				'member_id' => $member_id,
				'redirect_url' => admin_url( 'admin.php?page=stsrc-members' ),
			)
		);
	}

	/**
	 * Create membership type (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function create_membership_type(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Verify nonce
		$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		// Validate required fields
		if ( empty( $_POST['name'] ) || empty( $_POST['price'] ) || empty( $_POST['expiration_period'] ) ) {
			wp_send_json_error( array( 'message' => 'Name, price, and expiration period are required.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		// Check for duplicate name
		$all_types = STSRC_Membership_DB::get_all_membership_types();
		foreach ( $all_types as $type ) {
			if ( strtolower( $type['name'] ) === strtolower( sanitize_text_field( $_POST['name'] ) ) ) {
				wp_send_json_error( array( 'message' => 'A membership type with this name already exists.' ) );
				return;
			}
		}

		// Prepare membership type data
		$type_data = array(
			'name'                      => sanitize_text_field( $_POST['name'] ),
			'description'               => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'price'                     => floatval( $_POST['price'] ),
			'expiration_period'         => intval( $_POST['expiration_period'] ),
			'stripe_product_id'         => sanitize_text_field( $_POST['stripe_product_id'] ?? '' ),
			'is_selectable'             => isset( $_POST['is_selectable'] ) ? 1 : 0,
			'is_best_seller'            => isset( $_POST['is_best_seller'] ) ? 1 : 0,
			'can_have_additional_members' => isset( $_POST['can_have_additional_members'] ) ? 1 : 0,
			'benefits'                  => isset( $_POST['benefits'] ) && is_array( $_POST['benefits'] ) ? array_map( 'sanitize_text_field', $_POST['benefits'] ) : array(),
		);

		// Create membership type
		$membership_type_id = STSRC_Membership_DB::create_membership_type( $type_data );

		if ( ! $membership_type_id ) {
			wp_send_json_error( array( 'message' => 'Failed to create membership type.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message'      => 'Membership type created successfully.',
				'membership_type_id' => $membership_type_id,
				'redirect_url' => admin_url( 'admin.php?page=stsrc-memberships&action=edit&membership_type_id=' . $membership_type_id ),
			)
		);
	}

	/**
	 * Update membership type (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function update_membership_type(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Verify nonce
		$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$membership_type_id = isset( $_POST['membership_type_id'] ) ? intval( $_POST['membership_type_id'] ) : 0;
		if ( $membership_type_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid membership type ID.' ) );
			return;
		}

		// Validate required fields
		if ( empty( $_POST['name'] ) || empty( $_POST['price'] ) || empty( $_POST['expiration_period'] ) ) {
			wp_send_json_error( array( 'message' => 'Name, price, and expiration period are required.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		// Check if membership type exists
		$existing = STSRC_Membership_DB::get_membership_type( $membership_type_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => 'Membership type not found.' ) );
			return;
		}

		// Check for duplicate name (excluding current)
		$all_types = STSRC_Membership_DB::get_all_membership_types();
		$new_name = strtolower( sanitize_text_field( $_POST['name'] ) );
		foreach ( $all_types as $type ) {
			if ( $type['membership_type_id'] !== $membership_type_id && strtolower( $type['name'] ) === $new_name ) {
				wp_send_json_error( array( 'message' => 'A membership type with this name already exists.' ) );
				return;
			}
		}

		// Prepare update data
		$update_data = array(
			'name'                      => sanitize_text_field( $_POST['name'] ),
			'description'               => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'price'                     => floatval( $_POST['price'] ),
			'expiration_period'         => intval( $_POST['expiration_period'] ),
			'stripe_product_id'         => sanitize_text_field( $_POST['stripe_product_id'] ?? '' ),
			'is_selectable'             => isset( $_POST['is_selectable'] ) ? 1 : 0,
			'is_best_seller'            => isset( $_POST['is_best_seller'] ) ? 1 : 0,
			'can_have_additional_members' => isset( $_POST['can_have_additional_members'] ) ? 1 : 0,
			'benefits'                  => isset( $_POST['benefits'] ) && is_array( $_POST['benefits'] ) ? array_map( 'sanitize_text_field', $_POST['benefits'] ) : array(),
		);

		// Update membership type
		$result = STSRC_Membership_DB::update_membership_type( $membership_type_id, $update_data );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => 'Failed to update membership type.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message' => 'Membership type updated successfully.',
				'membership_type_id' => $membership_type_id,
			)
		);
	}

	/**
	 * Delete membership type (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function delete_membership_type(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Verify nonce
		$nonce = sanitize_text_field( $_POST['nonce'] ?? $_GET['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$membership_type_id = isset( $_POST['membership_type_id'] ) ? intval( $_POST['membership_type_id'] ) : ( isset( $_GET['membership_type_id'] ) ? intval( $_GET['membership_type_id'] ) : 0 );
		if ( $membership_type_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid membership type ID.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		// Check if membership type is in use
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$members = STSRC_Member_DB::get_members( array( 'membership_type_id' => $membership_type_id ) );
		if ( ! empty( $members ) ) {
			wp_send_json_error(
				array(
					'message' => 'Cannot delete membership type that is in use by ' . count( $members ) . ' member(s).',
				)
			);
			return;
		}

		// Delete membership type
		$result = STSRC_Membership_DB::delete_membership_type( $membership_type_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => 'Failed to delete membership type.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message'      => 'Membership type deleted successfully.',
				'redirect_url' => admin_url( 'admin.php?page=stsrc-memberships' ),
			)
		);
	}

	/**
	 * Forgot password request.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function forgot_password(): void {
		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_forgot_password_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		if ( ! $this->enforce_rate_limit( 'forgot_password', 5, 15 * MINUTE_IN_SECONDS, __( 'Too many password reset requests. Please try again later.', 'smoketree-plugin' ) ) ) {
			return;
		}

		$email = sanitize_email( $post_data['email'] ?? '' );
		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
			return;
		}

		// Check if user exists
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			// Don't reveal if email exists (security best practice)
			wp_send_json_success(
				array(
					'message' => 'If an account exists with that email, a password reset link has been sent.',
				)
			);
			return;
		}

		// Check if user is a member
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member_by_email( $email );
		if ( ! $member ) {
			// Not a member, but don't reveal
			wp_send_json_success(
				array(
					'message' => 'If an account exists with that email, a password reset link has been sent.',
				)
			);
			return;
		}

		// Generate reset token
		$reset_token = wp_generate_password( 32, false );
		$expiration = time() + HOUR_IN_SECONDS; // 1 hour

		// Store token in user meta
		update_user_meta( $user->ID, 'stsrc_password_reset_token', $reset_token );
		update_user_meta( $user->ID, 'stsrc_password_reset_expiration', $expiration );

		// Build reset URL
		$reset_url = add_query_arg(
			array(
				'token' => $reset_token,
				'email' => urlencode( $email ),
			),
			home_url( '/reset-password' )
		);

		// Send email
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
		$email_service = new STSRC_Email_Service();

		$email_service->send_email(
			'password-reset.php',
			array(
				'first_name'      => $member['first_name'],
				'last_name'       => $member['last_name'],
				'email'           => $email,
				'reset_link'      => $reset_url,
				'expiration_time' => '1 hour',
			),
			$email,
			'Password Reset Request - Smoketree Swim and Recreation Club'
		);

		wp_send_json_success(
			array(
				'message' => 'If an account exists with that email, a password reset link has been sent.',
			)
		);
	}

	/**
	 * Reset password.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function reset_password(): void {
		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_reset_password_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		if ( ! $this->enforce_rate_limit( 'reset_password', 5, 15 * MINUTE_IN_SECONDS, __( 'Too many password reset attempts. Please try again later.', 'smoketree-plugin' ) ) ) {
			return;
		}

		$token            = sanitize_text_field( $post_data['token'] ?? '' );
		$email            = sanitize_email( $post_data['email'] ?? '' );
		$new_password     = $post_data['new_password'] ?? '';
		$confirm_password = $post_data['confirm_password'] ?? '';

		if ( empty( $token ) || empty( $email ) || empty( $new_password ) || empty( $confirm_password ) ) {
			wp_send_json_error( array( 'message' => 'All fields are required.' ) );
			return;
		}

		// Validate token
		$validation_result = $this->validate_reset_token( $token, $email );
		if ( is_wp_error( $validation_result ) ) {
			STSRC_Logger::warning(
				'Password reset request failed token validation.',
				array(
					'method' => __METHOD__,
					'email'  => $email,
					'error'  => $validation_result->get_error_code(),
				)
			);
			wp_send_json_error( array( 'message' => $validation_result->get_error_message() ) );
			return;
		}

		$user_id = $validation_result;

		// Validate passwords match
		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => 'Passwords do not match.' ) );
			return;
		}

		// Validate password strength
		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => 'Password must be at least 8 characters long.' ) );
			return;
		}

		// Update password
		wp_set_password( $new_password, $user_id );

		// Invalidate token
		delete_user_meta( $user_id, 'stsrc_password_reset_token' );
		delete_user_meta( $user_id, 'stsrc_password_reset_expiration' );

		wp_send_json_success(
			array(
				'message' => 'Password reset successfully. You can now log in with your new password.',
				'redirect_url' => home_url( '/login' ),
			)
		);
	}

	/**
	 * Validate reset token.
	 *
	 * @since    1.0.0
	 * @param    string    $token    Reset token
	 * @param    string    $email    User email
	 * @return   int|WP_Error        User ID on success, WP_Error on failure
	 */
	public function validate_reset_token( string $token, string $email ): int|WP_Error {
		if ( empty( $token ) || empty( $email ) ) {
			STSRC_Logger::warning(
				'Password reset token validation failed due to missing data.',
				array(
					'method' => __METHOD__,
					'email'  => $email,
				)
			);
			return new WP_Error( 'invalid_data', 'Token and email are required.' );
		}

		// Get user by email
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			STSRC_Logger::info(
				'Password reset token validation failed because user was not found.',
				array(
					'method' => __METHOD__,
					'email'  => $email,
				)
			);
			return new WP_Error( 'invalid_token', 'Invalid or expired reset token.' );
		}

		// Get stored token and expiration
		$stored_token = get_user_meta( $user->ID, 'stsrc_password_reset_token', true );
		$expiration = get_user_meta( $user->ID, 'stsrc_password_reset_expiration', true );

		if ( empty( $stored_token ) || empty( $expiration ) ) {
			STSRC_Logger::info(
				'Password reset token validation failed because no token metadata was stored.',
				array(
					'method' => __METHOD__,
					'user_id' => $user->ID,
				)
			);
			return new WP_Error( 'invalid_token', 'Invalid or expired reset token.' );
		}

		// Check if token matches
		if ( ! hash_equals( $stored_token, $token ) ) {
			STSRC_Logger::info(
				'Password reset token mismatch detected.',
				array(
					'method' => __METHOD__,
					'user_id' => $user->ID,
				)
			);
			return new WP_Error( 'invalid_token', 'Invalid or expired reset token.' );
		}

		// Check if token is expired
		if ( time() > (int) $expiration ) {
			// Clean up expired token
			delete_user_meta( $user->ID, 'stsrc_password_reset_token' );
			delete_user_meta( $user->ID, 'stsrc_password_reset_expiration' );
			STSRC_Logger::info(
				'Password reset token expired.',
				array(
					'method' => __METHOD__,
					'user_id' => $user->ID,
				)
			);
			return new WP_Error( 'expired_token', 'Reset token has expired. Please request a new password reset.' );
		}

		return $user->ID;
	}

	/**
	 * Send reactivation email for cancelled account.
	 *
	 * @since    1.0.0
	 * @param    array    $existing_member    Existing member data
	 * @param    array    $new_data          New registration data
	 * @return   void
	 */
	private function send_reactivation_email( array $existing_member, array $new_data ): void {
		// Generate reactivation token
		$token = bin2hex( random_bytes( 32 ) );
		$expiration = time() + ( 24 * HOUR_IN_SECONDS ); // 24 hours

		// Store token and new registration data in transient
		set_transient(
			'stsrc_reactivation_' . $token,
			array(
				'member_id' => $existing_member['member_id'],
				'new_data'  => $new_data,
			),
			24 * HOUR_IN_SECONDS
		);

		// Build reactivation URL
		$reactivation_url = add_query_arg(
			array(
				'action' => 'stsrc_reactivate',
				'token'  => $token,
			),
			home_url()
		);

		// Send email
		$subject = 'Reactivate Your Smoketree Membership';
		$message = sprintf(
			"Hello %s,\n\n" .
			"You recently tried to register for Smoketree Swim and Recreation Club with an email address that was previously used for a cancelled membership.\n\n" .
			"To reactivate your account and complete your registration, please click the link below:\n\n" .
			"%s\n\n" .
			"This link will expire in 24 hours. If you did not make this request, you can safely ignore this email.\n\n" .
			"Best regards,\n" .
			"Smoketree Swim and Recreation Club",
			$existing_member['first_name'],
			$reactivation_url
		);

		wp_mail( $existing_member['email'], $subject, $message );

		STSRC_Logger::info(
			'Reactivation email sent for cancelled member.',
			array(
				'method'    => __METHOD__,
				'member_id' => $existing_member['member_id'],
				'email'     => $existing_member['email'],
			)
		);
	}

	/**
	 * Handle init hook to check for reactivation request.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_reactivation_request(): void {
		if ( isset( $_GET['action'] ) && 'stsrc_reactivate' === $_GET['action'] ) {
			$this->reactivate_member();
		}
	}

	/**
	 * Handle member reactivation from email link.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function reactivate_member(): void {
		$token = sanitize_text_field( $_GET['token'] ?? '' );

		if ( empty( $token ) ) {
			wp_die( 'Invalid reactivation link.' );
		}

		// Get reactivation data
		$reactivation_data = get_transient( 'stsrc_reactivation_' . $token );

		if ( false === $reactivation_data ) {
			wp_die( 'This reactivation link has expired or is invalid. Please try registering again.' );
		}

		$member_id = $reactivation_data['member_id'];
		$new_data = $reactivation_data['new_data'];

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		
		// Get current member data
		$member = STSRC_Member_DB::get_member( $member_id );
		
		if ( ! $member || 'cancelled' !== $member['status'] ) {
			wp_die( 'This account cannot be reactivated. Please contact support.' );
		}

		// Update member with new registration data and set to pending
		$update_data = array(
			'first_name'        => $new_data['first_name'],
			'last_name'         => $new_data['last_name'],
			'phone'             => $new_data['phone'],
			'street_1'          => $new_data['street_1'],
			'street_2'          => $new_data['street_2'] ?? '',
			'city'              => $new_data['city'],
			'state'             => $new_data['state'],
			'zip'               => $new_data['zip'],
			'country'           => $new_data['country'],
			'membership_type_id'=> $new_data['membership_type_id'],
			'status'            => 'pending',
			'payment_type'      => $new_data['payment_type'],
			'waiver_full_name'  => $new_data['waiver_full_name'],
			'waiver_signed_date'=> $new_data['waiver_signed_date'],
			'referral_source'   => $new_data['referral_source'] ?? '',
		);

		$result = STSRC_Member_DB::update_member( $member_id, $update_data );

		if ( ! $result ) {
			wp_die( 'Failed to reactivate account. Please try again or contact support.' );
		}

		// Update WordPress user password if provided
		if ( ! empty( $new_data['password'] ) && ! empty( $member['user_id'] ) ) {
			wp_set_password( $new_data['password'], $member['user_id'] );
		}

		// Delete the reactivation token
		delete_transient( 'stsrc_reactivation_' . $token );

		STSRC_Logger::info(
			'Member account reactivated successfully.',
			array(
				'method'    => __METHOD__,
				'member_id' => $member_id,
				'email'     => $member['email'],
			)
		);

		// Process payment based on payment type
		if ( in_array( $new_data['payment_type'], array( 'card', 'bank_account' ), true ) ) {
			// Redirect to Stripe for payment
			$result = $this->process_stripe_payment( $new_data, $member_id );
			if ( ! is_wp_error( $result ) ) {
				wp_redirect( $result );
				exit;
			}
		}

		// Redirect to success page for manual payments
		wp_redirect( home_url( '/?registration=success&reactivated=true' ) );
		exit;
	}

	/**
	 * Delete member (soft delete).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function delete_member(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$member_id = isset( $post_data['member_id'] ) ? intval( $post_data['member_id'] ) : 0;
		if ( $member_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid member ID.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		// Soft delete (set status to cancelled)
		$result = STSRC_Member_DB::delete_member( $member_id, false );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => 'Failed to delete member.' ) );
			return;
		}

		STSRC_Logger::info(
			'Member soft deleted by admin.',
			array(
				'method'    => __METHOD__,
				'member_id' => $member_id,
				'admin_id'  => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => 'Member deleted successfully. They can reactivate by registering again with the same email.',
			)
		);
	}

	/**
	 * Reactivate member (admin).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function reactivate_member_admin(): void {
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			return;
		}

		$member_id = isset( $post_data['member_id'] ) ? intval( $post_data['member_id'] ) : 0;
		if ( $member_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid member ID.' ) );
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		// Get current member
		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
			return;
		}

		// Check if member is cancelled
		if ( 'cancelled' !== $member['status'] ) {
			wp_send_json_error( array( 'message' => 'Only cancelled members can be reactivated.' ) );
			return;
		}

		// Reactivate by setting status to pending
		$result = STSRC_Member_DB::update_member(
			$member_id,
			array( 'status' => 'pending' )
		);

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => 'Failed to reactivate member.' ) );
			return;
		}

		STSRC_Logger::info(
			'Member reactivated by admin.',
			array(
				'method'    => __METHOD__,
				'member_id' => $member_id,
				'admin_id'  => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => 'Member reactivated successfully. Status set to pending.',
			)
		);
	}

}

