<?php

/**
 * Stripe webhook handler class
 *
 * Handles Stripe webhook events with signature verification.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 */

/**
 * Stripe webhook handler class.
 *
 * Processes Stripe webhook events securely.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 * @author     Smoketree Swim and Recreation Club
 */
class Smoketree_Stripe_Webhooks {

	/**
	 * Handle webhook request.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    REST API request
	 * @return   WP_REST_Response               REST API response
	 */
	public static function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		// Get webhook payload
		$payload = $request->get_body();
		$sig_header = (string) $request->get_header( 'stripe-signature' );

		// Verify webhook signature
		if ( ! self::verify_signature( $payload, $sig_header ) ) {
			error_log( 'Stripe webhook signature verification failed' );
			return new WP_REST_Response(
				array( 'error' => 'Invalid signature' ),
				400
			);
		}

		// Parse event
		$event = json_decode( $payload, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $event ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid JSON payload' ),
				400
			);
		}

		if ( ! isset( $event['type'] ) || ! isset( $event['id'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid event data' ),
				400
			);
		}

		// Check idempotency (prevent duplicate processing)
		if ( self::is_event_processed( $event['id'] ) ) {
			return new WP_REST_Response(
				array( 'message' => 'Event already processed' ),
				200
			);
		}

		// Route to appropriate handler
		$result = self::route_event( $event );

		// Mark event as processed
		if ( $result ) {
			self::mark_event_processed( $event['id'] );
		}

		return new WP_REST_Response(
			array( 'received' => true ),
			200
		);
	}

	/**
	 * Get webhook secret based on test mode.
	 *
	 * @since    1.0.0
	 * @return   string    Webhook secret or empty string
	 */
	private static function get_webhook_secret(): string {
		// Try ACF first, then fallback to get_option
		$secret = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_webhook_secret', 'option' ) : get_option( 'stsrc_stripe_webhook_secret', '' );
		
		// Check for test mode (handle bool, int, and string values)
		$test_mode = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_mode', 'option' ) : get_option( 'stsrc_stripe_test_mode', '0' );
		$is_test_mode = ( true === $test_mode || 1 === $test_mode || '1' === $test_mode );
		
		if ( $is_test_mode ) {
			$test_secret = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_webhook_secret', 'option' ) : get_option( 'stsrc_stripe_test_webhook_secret', '' );
			if ( ! empty( $test_secret ) ) {
				$secret = $test_secret;
			}
		}
		return (string) $secret;
	}

	/**
	 * Verify webhook signature.
	 *
	 * @since    1.0.0
	 * @param    string    $payload      Webhook payload
	 * @param    string    $sig_header   Stripe signature header
	 * @return   bool                    True if valid, false otherwise
	 */
	private static function verify_signature( string $payload, string $sig_header ): bool {
		if ( empty( $sig_header ) ) {
			return false;
		}

		// Get webhook secret based on test mode
		$webhook_secret = self::get_webhook_secret();
		if ( empty( $webhook_secret ) ) {
			error_log( 'Stripe webhook rejected: webhook secret is not configured.' );
			return false;
		}

		// Load Stripe SDK if needed
		if ( ! class_exists( '\Stripe\Webhook' ) ) {
			$stripe_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'vendor/stripe/stripe-php/init.php';
			if ( file_exists( $stripe_path ) ) {
				require_once $stripe_path;
			}
		}

		if ( ! class_exists( '\Stripe\Webhook' ) ) {
			error_log( 'Stripe SDK not loaded for webhook verification' );
			return false;
		}

		try {
			\Stripe\Webhook::constructEvent( $payload, $sig_header, $webhook_secret );
			return true;
		} catch ( \Exception $e ) {
			error_log( 'Stripe webhook signature verification error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Route event to appropriate handler.
	 *
	 * @since    1.0.0
	 * @param    array    $event    Stripe event data
	 * @return   bool               True on success, false on failure
	 */
	private static function route_event( array $event ): bool {
		$event_type = $event['type'] ?? '';

		switch ( $event_type ) {
			case 'checkout.session.completed':
				return self::handle_checkout_session_completed( $event );

			case 'payment_intent.succeeded':
				return self::handle_payment_intent_succeeded( $event );

			case 'payment_intent.payment_failed':
				return self::handle_payment_intent_failed( $event );

			default:
				// Log unhandled event types
				error_log( 'Unhandled Stripe webhook event type: ' . $event_type );
				return true; // Return true to acknowledge receipt
		}
	}

	/**
	 * Handle checkout.session.completed event.
	 *
	 * @since    1.0.0
	 * @param    array    $event    Stripe event data
	 * @return   bool               True on success, false on failure
	 */
	private static function handle_checkout_session_completed( array $event ): bool {
		$session = $event['data']['object'] ?? null;
		if ( ! $session ) {
			return false;
		}

		$session_id = $session['id'] ?? '';
		if ( empty( $session_id ) ) {
			return false;
		}

		// Check if this is a member registration (member_id in metadata)
		$metadata = $session['metadata'] ?? array();
		$member_id = isset( $metadata['member_id'] ) ? intval( $metadata['member_id'] ) : 0;
		$payment_type = $metadata['payment_type'] ?? '';

		if ( $member_id > 0 && 'registration' === $payment_type ) {
			// New flow: Update existing member account
			return self::handle_registration_payment_for_existing_member( $session, $event, $member_id );
		}

		// Legacy flow or other payment types (guest passes, extra members, etc.)
		// Get registration data from transient
		$transient_key = 'stsrc_registration_' . $session_id;
		$registration_data = get_transient( $transient_key );

		if ( false === $registration_data ) {
			// Might be a guest pass or extra member purchase
			return self::handle_other_payment( $session, $event['id'] ?? '' );
		}

		// Legacy: Process new member registration (backward compatibility)
		return self::handle_legacy_registration_payment( $session, $event, $registration_data );
	}

	/**
	 * Handle registration payment for existing member account.
	 *
	 * @since    1.0.0
	 * @param    array    $session    Stripe session data
	 * @param    array    $event      Stripe event data
	 * @param    int      $member_id  Existing member ID
	 * @return   bool                 True on success, false on failure
	 */
	private static function handle_registration_payment_for_existing_member( array $session, array $event, int $member_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-payment-log-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';

		$member_service = new STSRC_Member_Service();
		$email_service = new STSRC_Email_Service();

		// Verify member exists
		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			error_log( 'Webhook received for non-existent member_id: ' . $member_id );
			return false;
		}

		// Get Stripe customer ID from session
		$stripe_customer_id = $session['customer'] ?? '';

		// Update member with Stripe customer ID if not already set
		if ( ! empty( $stripe_customer_id ) && empty( $member['stripe_customer_id'] ) ) {
			STSRC_Member_DB::update_member(
				$member_id,
				array( 'stripe_customer_id' => $stripe_customer_id )
			);
		}

		$metadata = $session['metadata'] ?? array();

		// Use the membership price from metadata (excludes processing fee) so
		// the fee is not credited against the member's balance.
		$payment_amount = isset( $metadata['payment_amount'] )
			? (float) $metadata['payment_amount']
			: (float) ( ( $session['amount_total'] ?? 0 ) / 100 );

		$processing_fee = isset( $metadata['processing_fee'] )
			? (float) $metadata['processing_fee']
			: 0.00;

		$payment_intent_id = $session['payment_intent'] ?? '';
		$session_id        = $session['id'] ?? '';

		STSRC_Payment_Log_DB::log_payment(
			array(
				'member_id'                  => $member_id,
				'stripe_payment_intent_id'   => $payment_intent_id,
				'stripe_checkout_session_id' => $session_id,
				'amount'                     => $payment_amount,
				'fee_amount'                 => $processing_fee,
				'payment_type'               => $member['payment_type'] ?? 'card',
				'status'                     => 'succeeded',
				'stripe_event_id'            => $event['id'] ?? '',
				'metadata'                   => array(
					'session_id'  => $session_id,
					'event_id'    => $event['id'] ?? '',
					'payment_for' => 'registration',
				),
			)
		);

		$payment_method  = self::detect_payment_method_from_session( $session );
		$current_balance = (float) ( $member['balance_owed'] ?? 0.00 );
		$new_balance     = $current_balance - $payment_amount;

		STSRC_Transaction_DB::create_transaction(
			$member_id,
			array(
				'transaction_type'         => 'payment',
				'payment_method'           => $payment_method,
				'amount'                   => -$payment_amount,
				'balance_after'            => $new_balance,
				'stripe_payment_intent_id' => $payment_intent_id,
				'stripe_session_id'        => $session_id,
				'description'              => 'Registration payment via Stripe checkout',
			)
		);

		STSRC_Member_DB::update_balance( $member_id, $new_balance, $payment_method );

		// Activate member
		$activation_result = $member_service->activate_member( $member_id );

		if ( ! $activation_result ) {
			error_log( 'Failed to activate member after payment: ' . $member_id );
			// Don't return false - payment was logged, just activation failed
		}

		// Get updated member data for emails
		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			error_log( 'Failed to retrieve member data after payment: ' . $member_id );
			return false;
		}

		// Get membership type for email
		$membership_type = STSRC_Membership_DB::get_membership_type( $member['membership_type_id'] );
		$membership_type_name = $membership_type['name'] ?? '';

		// Send admin notifications (to all admins + secretary)
		$president_email = get_field( 'stsrc_president_email', 'option' );
		$vice_president_email = get_field( 'stsrc_vice_president_email', 'option' );
		$treasurer_email = get_field( 'stsrc_treasurer_email', 'option' );
		$secretary_email = get_field( 'stsrc_secretary_email', 'option' );
		$admin_emails = array_filter( array( $president_email, $vice_president_email, $secretary_email ) );

		// Also get all admin users
		$admin_users = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admin_users as $admin_user ) {
			if ( ! empty( $admin_user->user_email ) && ! in_array( $admin_user->user_email, $admin_emails, true ) ) {
				$admin_emails[] = $admin_user->user_email;
			}
		}

		// Send notification to each admin
		foreach ( $admin_emails as $admin_email_address ) {
			$email_service->send_email(
				'notify-admin-of-member.php',
				array(
					'first_name'      => $member['first_name'],
					'last_name'       => $member['last_name'],
					'email'           => $member['email'],
					'membership_type' => $membership_type_name,
					'status'          => $member['status'],
					'member'          => $member,
				),
				$admin_email_address,
				'New Member Registration - Smoketree Swim and Recreation Club'
			);
		}

		return true;
	}

	/**
	 * Handle legacy registration payment (backward compatibility).
	 *
	 * @since    1.0.0
	 * @param    array    $session            Stripe session data
	 * @param    array    $event              Stripe event data
	 * @param    array    $registration_data  Registration data from transient
	 * @return   bool                         True on success, false on failure
	 */
	private static function handle_legacy_registration_payment( array $session, array $event, array $registration_data ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-family-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-payment-log-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$member_service = new STSRC_Member_Service();
		$email_service = new STSRC_Email_Service();
		$session_id = $session['id'] ?? '';

		// Get Stripe customer ID from session
		$stripe_customer_id = $session['customer'] ?? '';
		if ( empty( $stripe_customer_id ) && ! empty( $session['customer_details']['email'] ) ) {
			// Customer might be in customer_details if created during checkout
			// Try to get customer ID from payment service
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-payment-service.php';
			$payment_service = new STSRC_Payment_Service();
			$stripe_customer_id = $payment_service->create_customer(
				array(
					'email' => $session['customer_details']['email'],
					'name'  => ( $session['customer_details']['name'] ?? '' ),
				)
			);
		}

		// Add Stripe customer ID to registration data
		if ( ! empty( $stripe_customer_id ) ) {
			$registration_data['stripe_customer_id'] = $stripe_customer_id;
		}

		// Set status to pending initially (will be activated after account creation)
		$registration_data['status'] = 'pending';

		// Create WordPress user and member record
		$member_id = $member_service->create_member_account( $registration_data );

		if ( false === $member_id ) {
			error_log( 'Failed to create member account for session: ' . $session_id );
			// Clear transient even on failure
			$transient_key = 'stsrc_registration_' . $session_id;
			delete_transient( $transient_key );
			return false;
		}

		// Create family members if provided
		if ( ! empty( $registration_data['family_members'] ) && is_array( $registration_data['family_members'] ) ) {
			foreach ( $registration_data['family_members'] as $family_member ) {
				if ( ! empty( $family_member['first_name'] ) && ! empty( $family_member['last_name'] ) ) {
					STSRC_Family_Member_DB::add_family_member( $member_id, $family_member );
				}
			}
		}

		// Get payment amount from session
		$amount_total = ( $session['amount_total'] ?? 0 ) / 100; // Convert from cents
		$amount_subtotal = ( $session['amount_subtotal'] ?? 0 ) / 100;
		$fee_amount = $amount_total - $amount_subtotal;

		// Get payment intent ID if available
		$payment_intent_id = $session['payment_intent'] ?? '';

		// Log payment transaction
		STSRC_Payment_Log_DB::log_payment(
			array(
				'member_id'                  => $member_id,
				'stripe_payment_intent_id'   => $payment_intent_id,
				'stripe_checkout_session_id' => $session_id,
				'amount'                     => $amount_total,
				'fee_amount'                 => $fee_amount,
				'payment_type'               => $registration_data['payment_type'] ?? 'card',
				'status'                     => 'succeeded',
				'stripe_event_id'            => $event['id'] ?? '',
				'metadata'                   => array(
					'session_id' => $session_id,
					'event_id'   => $event['id'] ?? '',
				),
			)
		);

		// Activate member
		$activation_result = $member_service->activate_member( $member_id );

		if ( ! $activation_result ) {
			error_log( 'Failed to activate member: ' . $member_id );
			// Don't return false - member account was created, just activation failed
		}

		// Get member data for emails
		$member = $member_service->get_member_data( $member_id );
		if ( ! $member ) {
			error_log( 'Failed to retrieve member data: ' . $member_id );
			$transient_key = 'stsrc_registration_' . $session_id;
			delete_transient( $transient_key );
			return false;
		}

		// Get membership type for email
		$membership_type = STSRC_Membership_DB::get_membership_type( $member['membership_type_id'] );
		$membership_type_name = $membership_type['name'] ?? '';

		// Send admin notifications (to all admins + secretary)
		$president_email = get_field( 'stsrc_president_email', 'option' );
		$vice_president_email = get_field( 'stsrc_vice_president_email', 'option' );
		$treasurer_email = get_field( 'stsrc_treasurer_email', 'option' );
		$secretary_email = get_field( 'stsrc_secretary_email', 'option' );
		$admin_emails = array_filter( array( $president_email, $vice_president_email, $treasurer_email, $secretary_email ) );

		// Also get all admin users
		$admin_users = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admin_users as $admin_user ) {
			if ( ! empty( $admin_user->user_email ) && ! in_array( $admin_user->user_email, $admin_emails, true ) ) {
				$admin_emails[] = $admin_user->user_email;
			}
		}

		// Send notification to each admin
		foreach ( $admin_emails as $admin_email_address ) {
			$email_service->send_email(
				'notify-admin-of-member.php',
				array(
					'first_name'      => $member['first_name'],
					'last_name'       => $member['last_name'],
					'email'           => $member['email'],
					'membership_type' => $membership_type_name,
					'status'          => $member['status'],
					'member'          => $member,
				),
				$admin_email_address,
				'New Member Registration - Smoketree Swim and Recreation Club'
			);
		}

		// Clear transient
		$transient_key = 'stsrc_registration_' . $session_id;
		delete_transient( $transient_key );

		return true;
	}

	/**
	 * Handle payment_intent.succeeded event.
	 *
	 * @since    1.0.0
	 * @param    array    $event    Stripe event data
	 * @return   bool               True on success, false on failure
	 */
	private static function handle_payment_intent_succeeded( array $event ): bool {
		$payment_intent = $event['data']['object'] ?? null;
		if ( ! $payment_intent ) {
			return false;
		}

		$payment_intent_id = $payment_intent['id'] ?? '';
		$amount = ( $payment_intent['amount'] ?? 0 ) / 100; // Convert from cents

		// Log payment
		if ( class_exists( 'STSRC_Payment_Log_DB' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-payment-log-db.php';

			// Try to find existing payment log
			$payment_log = STSRC_Payment_Log_DB::get_payment_by_intent_id( $payment_intent_id );

			if ( $payment_log ) {
				// Update existing log
				STSRC_Payment_Log_DB::update_payment_status(
					$payment_log['payment_log_id'],
					'succeeded',
					$event['id']
				);
			}
		}

		return true;
	}

	/**
	 * Handle payment_intent.payment_failed event.
	 *
	 * @since    1.0.0
	 * @param    array    $event    Stripe event data
	 * @return   bool               True on success, false on failure
	 */
	private static function handle_payment_intent_failed( array $event ): bool {
		$payment_intent = $event['data']['object'] ?? null;
		if ( ! $payment_intent ) {
			return false;
		}

		$payment_intent_id = $payment_intent['id'] ?? '';
		$error_message = $payment_intent['last_payment_error']['message'] ?? 'Payment failed';
		$error_code = $payment_intent['last_payment_error']['code'] ?? '';
		$metadata = $payment_intent['metadata'] ?? array();
		$payment_type_from_metadata = $metadata['payment_type'] ?? '';

		// Dedicated flow for balance payment failures.
		if ( 'balance_payment' === $payment_type_from_metadata ) {
			return self::handle_balance_payment_failure( $payment_intent, $event );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-payment-log-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';

		// Get payment log to find member and payment type
		$payment_log = STSRC_Payment_Log_DB::get_payment_by_intent_id( $payment_intent_id );

		if ( $payment_log ) {
			// Update payment log status
			STSRC_Payment_Log_DB::update_payment_status(
				$payment_log['payment_log_id'],
				'failed',
				$event['id']
			);

			$member_id = isset( $payment_log['member_id'] ) ? intval( $payment_log['member_id'] ) : 0;
			$payment_type = $payment_log['payment_type'] ?? '';
			$session_id = $payment_log['stripe_checkout_session_id'] ?? '';

			// Get member data if member exists
			$member = null;
			if ( $member_id > 0 ) {
				$member = STSRC_Member_DB::get_member( $member_id );
			}

			// If member doesn't exist yet, check transient for registration data
			if ( ! $member && ! empty( $session_id ) ) {
				$transient_key = 'stsrc_registration_' . $session_id;
				$registration_data = get_transient( $transient_key );

				if ( $registration_data ) {
					// Member registration was attempted but payment failed
					// Don't create account - keep in transient for retry
					// Send notification with registration data
					$email_service = new STSRC_Email_Service();

					// Send failure notification to admins
					$president_email = get_field( 'stsrc_president_email', 'option' );
					$vice_president_email = get_field( 'stsrc_vice_president_email', 'option' );
					$treasurer_email = get_field( 'stsrc_treasurer_email', 'option' );
					$secretary_email = get_field( 'stsrc_secretary_email', 'option' );
					$admin_emails = array_filter( array( $president_email, $vice_president_email, $treasurer_email, $secretary_email ) );

					// Also get all admin users
					$admin_users = get_users( array( 'role' => 'administrator' ) );
					foreach ( $admin_users as $admin_user ) {
						if ( ! empty( $admin_user->user_email ) && ! in_array( $admin_user->user_email, $admin_emails, true ) ) {
							$admin_emails[] = $admin_user->user_email;
						}
					}

					// Send notification to each admin
					foreach ( $admin_emails as $admin_email_address ) {
						$email_service->send_email(
							'notify-admins-of-failed-registration.php',
							array(
								'first_name'   => $registration_data['first_name'] ?? '',
								'last_name'    => $registration_data['last_name'] ?? '',
								'email'        => $registration_data['email'] ?? '',
								'error_message' => $error_message,
								'payment_type' => $registration_data['payment_type'] ?? 'card',
							),
							$admin_email_address,
							'Failed Registration - Smoketree Swim and Recreation Club'
						);
					}
				}
			} elseif ( $member ) {
				// Member exists - ensure status is pending (don't activate)
				if ( $member['status'] !== 'pending' ) {
					// Only update to pending if it was active (shouldn't happen, but safety check)
					STSRC_Member_DB::update_member(
						$member_id,
						array( 'status' => 'pending' )
					);
				}

				// Send failure notification based on payment type
				$email_service = new STSRC_Email_Service();

				if ( 'guest_pass' === $payment_type || 'extra_member' === $payment_type ) {
					// For guest pass or extra member failures, just log - member account is fine
					error_log( 'Payment failed for ' . $payment_type . ': ' . $payment_intent_id . ' - Member: ' . $member_id );
				} else {
					// Registration payment failure
					$president_email = get_field( 'stsrc_president_email', 'option' );
					$vice_president_email = get_field( 'stsrc_vice_president_email', 'option' );
					$treasurer_email = get_field( 'stsrc_treasurer_email', 'option' );
					$secretary_email = get_field( 'stsrc_secretary_email', 'option' );
					$admin_emails = array_filter( array( $president_email, $vice_president_email, $treasurer_email, $secretary_email ) );

					// Also get all admin users
					$admin_users = get_users( array( 'role' => 'administrator' ) );
					foreach ( $admin_users as $admin_user ) {
						if ( ! empty( $admin_user->user_email ) && ! in_array( $admin_user->user_email, $admin_emails, true ) ) {
							$admin_emails[] = $admin_user->user_email;
						}
					}

					// Send notification to each admin
					foreach ( $admin_emails as $admin_email_address ) {
						$email_service->send_email(
							'notify-admins-of-failed-registration.php',
							array(
								'first_name'   => $member['first_name'],
								'last_name'    => $member['last_name'],
								'email'        => $member['email'],
								'error_message' => $error_message,
								'payment_type' => $payment_type,
							),
							$admin_email_address,
							'Failed Payment - Smoketree Swim and Recreation Club'
						);
					}
				}
			}
		} else {
			// Payment log not found - log error but don't fail
			error_log( 'Payment failed but no payment log found: ' . $payment_intent_id . ' - ' . $error_message );
		}

		return true;
	}

	/**
	 * Handle balance payment failure notifications.
	 *
	 * @since    1.1.0
	 * @param    array $payment_intent Stripe payment intent object payload.
	 * @param    array $event          Stripe event data.
	 * @return   bool
	 */
	private static function handle_balance_payment_failure( array $payment_intent, array $event ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';

		$payment_intent_id = sanitize_text_field( (string) ( $payment_intent['id'] ?? '' ) );
		$metadata          = $payment_intent['metadata'] ?? array();
		$member_id         = isset( $metadata['member_id'] ) ? intval( $metadata['member_id'] ) : 0;
		$attempted_amount  = isset( $metadata['payment_amount'] )
			? (float) $metadata['payment_amount']
			: (float) ( ( $payment_intent['amount'] ?? 0 ) / 100 );
		$reason            = $payment_intent['last_payment_error']['message'] ?? __( 'Payment failed', 'smoketree-plugin' );

		error_log(
			sprintf(
				'Balance payment failed. Intent: %s | Member: %d | Reason: %s',
				$payment_intent_id,
				$member_id,
				$reason
			)
		);

		if ( $member_id <= 0 ) {
			return true;
		}

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			return true;
		}

		$member_email = sanitize_email( $member['email'] ?? '' );
		$member_name  = trim( ( $member['first_name'] ?? '' ) . ' ' . ( $member['last_name'] ?? '' ) );
		$current_balance = (float) ( $member['balance_owed'] ?? 0 );

		if ( ! empty( $member_email ) ) {
			$email_service = new STSRC_Email_Service();
			$email_service->send_balance_payment_failed_email( $member_id, $attempted_amount, $reason );
		}

		$admin_emails = array_filter(
			array(
				get_field( 'stsrc_president_email', 'option' ),
				get_field( 'stsrc_treasurer_email', 'option' ),
				get_field( 'stsrc_vice_president_email', 'option' ),
				get_field( 'stsrc_secretary_email', 'option' ),
			)
		);
		$admin_users = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admin_users as $admin_user ) {
			if ( ! empty( $admin_user->user_email ) && ! in_array( $admin_user->user_email, $admin_emails, true ) ) {
				$admin_emails[] = $admin_user->user_email;
			}
		}

		$admin_subject = __( 'Member Balance Payment Failed', 'smoketree-plugin' );
		$admin_message = sprintf(
			/* translators: 1: member name, 2: member email, 3: attempted amount, 4: reason */
			__(
				'Balance payment failed for %1$s (%2$s). Attempted amount: $%3$s. Reason: %4$s.',
				'smoketree-plugin'
			),
			$member_name,
			$member_email,
			number_format( $attempted_amount, 2 ),
			$reason
		);

		foreach ( $admin_emails as $admin_email ) {
			wp_mail( $admin_email, $admin_subject, $admin_message );
		}

		do_action(
			'stsrc_balance_payment_failed',
			$member_id,
			$payment_intent_id,
			$attempted_amount,
			$reason,
			$event['id'] ?? ''
		);

		return true;
	}

	/**
	 * Handle other payment types (guest passes, extra members).
	 *
	 * @since    1.0.0
	 * @param    array    $session    Stripe checkout session
	 * @param    string   $event_id   Stripe event ID
	 * @return   bool                 True on success, false on failure
	 */
	private static function handle_other_payment( array $session, string $event_id = '' ): bool {
		$metadata = $session['metadata'] ?? array();
		$payment_type = $metadata['payment_type'] ?? '';

		switch ( $payment_type ) {
			case 'balance_payment':
				return self::handle_balance_payment_success( $session, $event_id );

			case 'guest_pass':
				return self::handle_guest_pass_purchase( $session, $event_id );

			case 'extra_member':
				return self::handle_extra_member_payment( $session, $event_id );

			default:
				error_log( 'Unknown payment type in checkout session: ' . $payment_type );
				return true;
		}
	}

	/**
	 * Handle balance payment webhook.
	 *
	 * @since    1.1.0
	 * @param    array    $session    Stripe checkout session
	 * @param    string   $event_id   Stripe event ID
	 * @return   bool                 True on success, false on failure
	 */
	private static function handle_balance_payment_success( array $session, string $event_id = '' ): bool {
		$session_id = $session['id'] ?? '';
		$metadata   = $session['metadata'] ?? array();
		$member_id  = isset( $metadata['member_id'] ) ? intval( $metadata['member_id'] ) : 0;

		if ( empty( $session_id ) || $member_id <= 0 ) {
			error_log( 'Invalid balance payment webhook payload.' );
			return false;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-balance-service.php';

		$payment_intent_id = sanitize_text_field( (string) ( $session['payment_intent'] ?? '' ) );

		// Use the balance-only amount from metadata so the processing fee
		// is not applied against the member's balance.
		$amount_paid = isset( $metadata['payment_amount'] )
			? (float) $metadata['payment_amount']
			: (float) ( ( $session['amount_total'] ?? 0 ) / 100 );

		if ( $amount_paid <= 0 ) {
			error_log( 'Balance payment webhook amount is zero or invalid for session: ' . $session_id );
			return false;
		}

		// Idempotency: ensure this payment intent was not recorded already.
		if ( ! empty( $payment_intent_id ) ) {
			$existing_txn = STSRC_Transaction_DB::get_transaction_by_payment_intent( $payment_intent_id );
			if ( ! empty( $existing_txn ) ) {
				return true;
			}
		}

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			error_log( 'Balance payment webhook member not found: ' . $member_id );
			return false;
		}

		$payment_method = self::detect_payment_method_from_session( $session );

		$transaction = STSRC_Balance_Service::record_stripe_payment(
			$member_id,
			$amount_paid,
			$payment_method,
			array(
				'payment_intent_id' => $payment_intent_id,
				'charge_id'         => sanitize_text_field( (string) ( $session['payment_intent'] ?? '' ) ),
				'session_id'        => $session_id,
			),
			__( 'Balance payment via Stripe checkout', 'smoketree-plugin' )
		);

		if ( false === $transaction ) {
			error_log( 'Failed to record balance payment transaction for member: ' . $member_id );
			return false;
		}

		$updated_member = STSRC_Member_DB::get_member( $member_id );
		$new_balance    = (float) ( $updated_member['balance_owed'] ?? 0 );

		// Notify internal listeners for future email/template integrations.
		do_action(
			'stsrc_balance_payment_succeeded',
			$member_id,
			$transaction['transaction_id'] ?? 0,
			$amount_paid,
			$new_balance,
			$event_id
		);

		return true;
	}

	/**
	 * Detect likely payment method from checkout session payload.
	 *
	 * @since    1.1.0
	 * @param    array $session Stripe session data.
	 * @return   string
	 */
	private static function detect_payment_method_from_session( array $session ): string {
		$metadata = $session['metadata'] ?? array();
		if ( ! empty( $metadata['payment_method'] ) ) {
			return sanitize_text_field( $metadata['payment_method'] );
		}

		$method_types = $session['payment_method_types'] ?? array();
		if ( is_array( $method_types ) ) {
			if ( in_array( 'us_bank_account', $method_types, true ) && 1 === count( $method_types ) ) {
				return 'us_bank_account';
			}
			if ( in_array( 'card', $method_types, true ) ) {
				return 'card';
			}
		}

		return 'card';
	}

	/**
	 * Handle guest pass purchase webhook.
	 *
	 * @since    1.0.0
	 * @param    array    $session    Stripe checkout session
	 * @param    string   $event_id   Stripe event ID
	 * @return   bool                 True on success, false on failure
	 */
	private static function handle_guest_pass_purchase( array $session, string $event_id = '' ): bool {
		$session_id = $session['id'] ?? '';
		if ( empty( $session_id ) ) {
			return false;
		}

		$metadata = $session['metadata'] ?? array();
		$member_id = isset( $metadata['member_id'] ) ? intval( $metadata['member_id'] ) : 0;
		$quantity = isset( $metadata['quantity'] ) ? intval( $metadata['quantity'] ) : 0;

		if ( $member_id <= 0 || $quantity <= 0 ) {
			error_log( 'Invalid guest pass purchase data for session: ' . $session_id );
			return false;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-guest-pass-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-payment-log-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';

		// Get member data
		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			error_log( 'Member not found for guest pass purchase: ' . $member_id );
			return false;
		}

		// Get payment amount from session
		$amount_total = ( $session['amount_total'] ?? 0 ) / 100; // Convert from cents
		$payment_intent_id = $session['payment_intent'] ?? '';

		// Record purchase in guest_passes ledger (single source of truth for balance).
		$purchase_id = STSRC_Guest_Pass_DB::record_purchase(
			$member_id,
			$quantity,
			$amount_total,
			$payment_intent_id,
			'Guest pass purchase via Stripe'
		);
		if ( false === $purchase_id ) {
			error_log( 'Failed to record guest pass purchase for member: ' . $member_id );
			return false;
		}

		// Log payment transaction
		STSRC_Payment_Log_DB::log_payment(
			array(
				'member_id'                  => $member_id,
				'stripe_payment_intent_id'   => $payment_intent_id,
				'stripe_checkout_session_id' => $session_id,
				'amount'                     => $amount_total,
				'fee_amount'                 => 0.00, // No fee for guest passes
				'payment_type'               => 'guest_pass',
				'status'                     => 'succeeded',
				'stripe_event_id'           => $event_id,
				'metadata'                  => array(
					'session_id' => $session_id,
					'quantity'   => $quantity,
				),
			)
		);

		// Generate single-use auto-login token for the email link (valid 7 days).
		$portal_token = bin2hex( random_bytes( 32 ) );
		set_transient(
			'stsrc_portal_token_' . $portal_token,
			array(
				'member_id' => $member_id,
				'user_id'   => $member['user_id'],
			),
			7 * DAY_IN_SECONDS
		);

		// Send confirmation email to member
		$email_service = new STSRC_Email_Service();
		$email_service->send_email(
			'guest-pass-purchase.php',
			array(
				'first_name'   => $member['first_name'],
				'last_name'    => $member['last_name'],
				'email'        => $member['email'],
				'quantity'     => $quantity,
				'amount'       => $amount_total,
				'balance'      => STSRC_Guest_Pass_DB::get_guest_pass_balance( $member_id ),
				'portal_token' => $portal_token,
			),
			$member['email'],
			'Guest Pass Purchase Confirmation - Smoketree Swim and Recreation Club'
		);

		// Send admin notification
		$president_email= get_field( 'stsrc_president_email', 'option' );
		$vice_president_email = get_field( 'stsrc_vice_president_email', 'option' );
		$treasurer_email = get_field( 'stsrc_treasurer_email', 'option' );
		$secretary_email = get_field( 'stsrc_secretary_email', 'option' );
		$admin_emails = array_filter( array( $president_email, $vice_president_email, $treasurer_email, $secretary_email ) );

		// Also get all admin users
		$admin_users = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admin_users as $admin_user ) {
			if ( ! empty( $admin_user->user_email ) && ! in_array( $admin_user->user_email, $admin_emails, true ) ) {
				$admin_emails[] = $admin_user->user_email;
			}
		}

		// Send notification to each admin
		foreach ( $admin_emails as $admin_email_address ) {
			$email_service->send_email(
				'notify-admin-of-guest-pass.php',
				array(
					'first_name' => $member['first_name'],
					'last_name'  => $member['last_name'],
					'email'      => $member['email'],
					'quantity'   => $quantity,
					'amount'     => $amount_total,
					'balance'    => STSRC_Guest_Pass_DB::get_guest_pass_balance( $member_id ),
				),
				$admin_email_address,
				'Guest Pass Purchase - Smoketree Swim and Recreation Club'
			);
		}

		return true;
	}

	/**
	 * Handle extra member payment webhook.
	 *
	 * @since    1.0.0
	 * @param    array    $session    Stripe checkout session
	 * @param    string   $event_id   Stripe event ID
	 * @return   bool                 True on success, false on failure
	 */
	private static function handle_extra_member_payment( array $session, string $event_id = '' ): bool {
		$session_id = $session['id'] ?? '';
		if ( empty( $session_id ) ) {
			return false;
		}

		$metadata   = $session['metadata'] ?? array();
		$member_id  = isset( $metadata['member_id'] ) ? intval( $metadata['member_id'] ) : 0;

		if ( $member_id <= 0 ) {
			error_log( 'Invalid extra member payment data for session: ' . $session_id );
			return false;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-extra-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-payment-log-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			error_log( 'Member not found for extra member payment: ' . $member_id );
			return false;
		}

		$payment_intent_id = $session['payment_intent'] ?? '';
		$processing_fee    = isset( $metadata['processing_fee'] ) ? (float) $metadata['processing_fee'] : 0.00;

		// Use payment_amount from metadata (excludes processing fee) with fallback
		$payment_amount = isset( $metadata['payment_amount'] )
			? (float) $metadata['payment_amount']
			: (float) ( ( $session['amount_total'] ?? 0 ) / 100 );

		// Build the list of extra members — support both new multi-member JSON
		// format and legacy single-member metadata fields.
		$members_to_create = array();

		if ( ! empty( $metadata['extra_members'] ) ) {
			$decoded = json_decode( $metadata['extra_members'], true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $em ) {
					$fn = sanitize_text_field( $em['first_name'] ?? '' );
					$ln = sanitize_text_field( $em['last_name'] ?? '' );
					if ( ! empty( $fn ) && ! empty( $ln ) ) {
						$members_to_create[] = array(
							'first_name' => $fn,
							'last_name'  => $ln,
							'email'      => sanitize_email( $em['email'] ?? '' ),
						);
					}
				}
			}
		}

		// Legacy fallback: single member in flat metadata fields
		if ( empty( $members_to_create ) ) {
			$fn = sanitize_text_field( $metadata['first_name'] ?? '' );
			$ln = sanitize_text_field( $metadata['last_name'] ?? '' );
			if ( ! empty( $fn ) && ! empty( $ln ) ) {
				$members_to_create[] = array(
					'first_name' => $fn,
					'last_name'  => $ln,
					'email'      => sanitize_email( $metadata['email'] ?? '' ),
				);
			}
		}

		if ( empty( $members_to_create ) ) {
			error_log( 'No extra member data found in metadata for session: ' . $session_id );
			return false;
		}

		$existing_extra_members = STSRC_Extra_Member_DB::get_extra_members( $member_id );
		$created_ids = array();

		foreach ( $members_to_create as $new_member ) {
			$extra_member_exists = false;
			$extra_member_id     = null;

			foreach ( $existing_extra_members as $existing ) {
				if ( $existing['first_name'] === $new_member['first_name'] && $existing['last_name'] === $new_member['last_name'] ) {
					$extra_member_exists = true;
					$extra_member_id     = $existing['extra_member_id'];
					break;
				}
			}

			if ( $extra_member_exists && $extra_member_id ) {
				STSRC_Extra_Member_DB::update_extra_member(
					$extra_member_id,
					array(
						'payment_status'           => 'succeeded',
						'stripe_payment_intent_id' => $payment_intent_id,
					)
				);
			} else {
				$extra_member_id = STSRC_Extra_Member_DB::add_extra_member(
					$member_id,
					array(
						'first_name'               => $new_member['first_name'],
						'last_name'                => $new_member['last_name'],
						'email'                    => $new_member['email'],
						'payment_status'           => 'succeeded',
						'stripe_payment_intent_id' => $payment_intent_id,
					)
				);

				if ( false === $extra_member_id ) {
					error_log( 'Failed to create extra member ' . $new_member['first_name'] . ' ' . $new_member['last_name'] . ' for member: ' . $member_id );
				}
			}

			if ( $extra_member_id ) {
				$created_ids[] = $extra_member_id;
			}
		}

		STSRC_Payment_Log_DB::log_payment(
			array(
				'member_id'                  => $member_id,
				'stripe_payment_intent_id'   => $payment_intent_id,
				'stripe_checkout_session_id' => $session_id,
				'amount'                     => $payment_amount,
				'fee_amount'                 => $processing_fee,
				'payment_type'               => 'extra_member',
				'status'                     => 'succeeded',
				'stripe_event_id'            => $event_id,
				'metadata'                   => array(
					'session_id'       => $session_id,
					'extra_member_ids' => $created_ids,
					'quantity'         => count( $members_to_create ),
				),
			)
		);

		return true;
	}

	/**
	 * Check if event has already been processed (idempotency).
	 *
	 * @since    1.0.0
	 * @param    string    $event_id    Stripe event ID
	 * @return   bool                   True if already processed
	 */
	private static function is_event_processed( string $event_id ): bool {
		$processed_events = get_option( 'stsrc_stripe_processed_events', array() );

		if ( ! is_array( $processed_events ) ) {
			$processed_events = array();
		}

		return in_array( $event_id, $processed_events, true );
	}

	/**
	 * Mark event as processed.
	 *
	 * @since    1.0.0
	 * @param    string    $event_id    Stripe event ID
	 * @return   void
	 */
	private static function mark_event_processed( string $event_id ): void {
		$processed_events = get_option( 'stsrc_stripe_processed_events', array() );

		if ( ! is_array( $processed_events ) ) {
			$processed_events = array();
		}

		// Add event ID
		$processed_events[] = $event_id;

		// Keep only last 1000 events to prevent option from growing too large
		if ( count( $processed_events ) > 1000 ) {
			$processed_events = array_slice( $processed_events, -1000 );
		}

		update_option( 'stsrc_stripe_processed_events', $processed_events );
	}
}

