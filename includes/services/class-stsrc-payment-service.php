<?php

/**
 * Payment service class
 *
 * Handles Stripe payment processing and customer management.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

/**
 * Payment service class.
 *
 * Provides Stripe payment processing functionality.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @author     Smoketree Swim and Recreation Club
 */
require_once __DIR__ . '/class-stsrc-logger.php';

class STSRC_Payment_Service {

	/**
	 * Initialize Stripe SDK.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function init_stripe(): void {
		// Load Stripe SDK if not already loaded
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			$stripe_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'vendor/stripe/stripe-php/init.php';
			if ( file_exists( $stripe_path ) ) {
				require_once $stripe_path;
			}
		}

		// Set API key
		$secret_key = $this->get_secret_key();
		if ( ! empty( $secret_key ) && class_exists( '\Stripe\Stripe' ) ) {
			\Stripe\Stripe::setApiKey( $secret_key );
		}
	}

	/**
	 * Get Stripe secret key.
	 *
	 * @since    1.0.0
	 * @return   string    Secret key or empty string
	 */
	private function get_secret_key(): string {
		$key = get_option( 'stsrc_stripe_secret_key', '' );
		// Check for test mode
		$test_mode = get_option( 'stsrc_stripe_test_mode', '0' );
		if ( '1' === $test_mode ) {
			$key = get_option( 'stsrc_stripe_test_secret_key', $key );
		}
		return $key;
	}

	/**
	 * Get Stripe publishable key.
	 *
	 * @since    1.0.0
	 * @return   string    Publishable key or empty string
	 */
	public function get_publishable_key(): string {
		$key = get_option( 'stsrc_stripe_publishable_key', '' );
		// Check for test mode
		$test_mode = get_option( 'stsrc_stripe_test_mode', '0' );
		if ( '1' === $test_mode ) {
			$key = get_option( 'stsrc_stripe_test_publishable_key', $key );
		}
		return $key;
	}

	/**
	 * Create Stripe Checkout Session.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Array with checkout session data
	 * @return   string|false       Checkout session URL or false on failure
	 */
	public function create_checkout_session( array $data ): string|false {
		$this->init_stripe();

		if ( ! class_exists( '\Stripe\Checkout\Session' ) ) {
			STSRC_Logger::error(
				'Stripe SDK not loaded when creating checkout session.',
				array( 'method' => __METHOD__ )
			);
			return false;
		}

		// Validate required fields
		if ( empty( $data['amount'] ) || empty( $data['success_url'] ) || empty( $data['cancel_url'] ) ) {
			STSRC_Logger::warning(
				'Checkout session creation aborted due to missing required parameters.',
				array(
					'method' => __METHOD__,
					'keys'   => array_keys( $data ),
				)
			);
			return false;
		}

		// Convert amount to cents
		$amount_cents = (int) ( $data['amount'] * 100 );

		// Build line items
		$line_items = array(
			array(
				'price_data' => array(
					'currency'    => 'usd',
					'product_data' => array(
						'name' => $data['product_name'] ?? 'Membership',
					),
					'unit_amount' => $amount_cents,
				),
				'quantity'   => 1,
			),
		);

		// Build session parameters
		$session_params = array(
			'payment_method_types' => array( 'card', 'us_bank_account' ),
			'line_items'           => $line_items,
			'mode'                 => 'payment',
			'success_url'          => $data['success_url'],
			'cancel_url'           => $data['cancel_url'],
		);

		// Add customer if provided
		if ( ! empty( $data['customer_id'] ) ) {
			$session_params['customer'] = $data['customer_id'];
		} else {
			// Create customer if email provided
			if ( ! empty( $data['customer_email'] ) ) {
				$customer_id = $this->create_customer(
					array(
						'email' => $data['customer_email'],
						'name'  => ( $data['customer_name'] ?? '' ),
					)
				);
				if ( $customer_id ) {
					$session_params['customer'] = $customer_id;
				}
			}
		}

		// Add metadata
		if ( ! empty( $data['metadata'] ) ) {
			$session_params['metadata'] = $data['metadata'];
		}

		try {
			$session = \Stripe\Checkout\Session::create( $session_params );
			return $session->url;
		} catch ( \Exception $e ) {
			STSRC_Logger::exception(
				$e,
				array(
					'method'        => __METHOD__,
					'customer_id'   => $session_params['customer'] ?? null,
					'customer_email'=> $data['customer_email'] ?? null,
					'amount'        => $data['amount'] ?? null,
				)
			);
			return false;
		}
	}

	/**
	 * Create Stripe Customer.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Array with customer data (email, name, metadata)
	 * @return   string|false      Customer ID or false on failure
	 */
	public function create_customer( array $data ): string|false {
		$this->init_stripe();

		if ( ! class_exists( '\Stripe\Customer' ) ) {
			STSRC_Logger::error(
				'Stripe SDK not loaded when creating a customer.',
				array( 'method' => __METHOD__ )
			);
			return false;
		}

		if ( empty( $data['email'] ) ) {
			STSRC_Logger::warning(
				'Attempted to create a Stripe customer without an email address.',
				array( 'method' => __METHOD__ )
			);
			return false;
		}

		$customer_params = array(
			'email' => sanitize_email( $data['email'] ),
		);

		if ( ! empty( $data['name'] ) ) {
			$customer_params['name'] = sanitize_text_field( $data['name'] );
		}

		if ( ! empty( $data['metadata'] ) ) {
			$customer_params['metadata'] = $data['metadata'];
		}

		try {
			$customer = \Stripe\Customer::create( $customer_params );
			return $customer->id;
		} catch ( \Exception $e ) {
			STSRC_Logger::exception(
				$e,
				array(
					'method' => __METHOD__,
					'email'  => $customer_params['email'] ?? null,
				)
			);
			return false;
		}
	}

	/**
	 * Get Customer Portal URL.
	 *
	 * @since    1.0.0
	 * @param    string    $customer_id    Stripe customer ID
	 * @param    string    $return_url     URL to return to after portal session
	 * @return   string|false              Portal session URL or false on failure
	 */
	public function get_customer_portal_url( string $customer_id, string $return_url = '' ): string|false {
		$this->init_stripe();

		if ( ! class_exists( '\Stripe\BillingPortal\Session' ) ) {
			STSRC_Logger::error(
				'Stripe SDK not loaded when creating billing portal session.',
				array( 'method' => __METHOD__ )
			);
			return false;
		}

		if ( empty( $return_url ) ) {
			$return_url = home_url( '/member-portal' );
		}

		try {
			$session = \Stripe\BillingPortal\Session::create(
				array(
					'customer'   => $customer_id,
					'return_url' => $return_url,
				)
			);
			return $session->url;
		} catch ( \Exception $e ) {
			STSRC_Logger::exception(
				$e,
				array(
					'method'      => __METHOD__,
					'customer_id' => $customer_id,
				)
			);
			return false;
		}
	}

	/**
	 * Calculate total with flat fee.
	 *
	 * @since    1.0.0
	 * @param    float    $base_amount         Base amount (membership price)
	 * @param    string   $membership_type_slug    Membership type slug for fee lookup
	 * @return   float                         Total amount with fee
	 */
	public function calculate_total_with_fee( float $base_amount, string $membership_type_slug = '' ): float {
		$fee = $this->get_flat_fee( $membership_type_slug );
		return $base_amount + $fee;
	}

	/**
	 * Get flat fee for membership type.
	 *
	 * @since    1.0.0
	 * @param    string    $membership_type_slug    Membership type slug (single, duo, household)
	 * @return   float                              Flat fee amount
	 */
	public function get_flat_fee( string $membership_type_slug = '' ): float {
		// Default flat fees
		$fees = array(
			'single'    => 6.00,
			'duo'       => 8.00,
			'household' => 10.00,
		);

		// Normalize slug to lowercase
		$slug = strtolower( $membership_type_slug );

		// Return fee if found, otherwise return default for single
		return $fees[ $slug ] ?? $fees['single'];
	}

	/**
	 * Handle payment success (legacy method, webhook handles this now).
	 *
	 * This method is kept for backward compatibility but actual processing
	 * should be done in the webhook handler (Smoketree_Stripe_Webhooks::handle_checkout_session_completed).
	 *
	 * @since    1.0.0
	 * @param    string    $session_id    Stripe checkout session ID
	 * @return   bool                      True on success, false on failure
	 */
	public function handle_payment_success( string $session_id ): bool {
		$this->init_stripe();

		if ( ! class_exists( '\Stripe\Checkout\Session' ) ) {
			STSRC_Logger::error(
				'Stripe SDK not loaded when verifying payment success.',
				array( 'method' => __METHOD__ )
			);
			return false;
		}

		try {
			$session = \Stripe\Checkout\Session::retrieve( $session_id );

			// Verify payment status
			if ( 'complete' !== $session->payment_status && 'paid' !== $session->payment_status ) {
				STSRC_Logger::warning(
					'Stripe checkout session retrieved but payment not marked complete.',
					array(
						'method'     => __METHOD__,
						'session_id' => $session_id,
						'status'     => $session->payment_status,
					)
				);
				return false;
			}

			// Get registration data from transient
			$transient_key    = 'stsrc_registration_' . $session_id;
			$registration_data = get_transient( $transient_key );

			if ( false === $registration_data ) {
				STSRC_Logger::warning(
					'Registration data transient missing during payment success handling.',
					array(
						'method'     => __METHOD__,
						'session_id' => $session_id,
					)
				);
				return false;
			}

			// Note: Actual member creation and activation should be handled by webhook
			// This method is primarily for verification/legacy support
			// The webhook handler (handle_checkout_session_completed) does the actual work
			return true;
		} catch ( \Exception $e ) {
			STSRC_Logger::exception(
				$e,
				array(
					'method'     => __METHOD__,
					'session_id' => $session_id,
				)
			);
			return false;
		}
	}

	/**
	 * Retrieve Stripe checkout session.
	 *
	 * @since    1.0.0
	 * @param    string    $session_id    Stripe checkout session ID
	 * @return   object|false              Stripe session object or false on failure
	 */
	public function get_checkout_session( string $session_id ): object|false {
		$this->init_stripe();

		if ( ! class_exists( '\Stripe\Checkout\Session' ) ) {
			STSRC_Logger::error(
				'Stripe SDK not loaded when retrieving checkout session.',
				array( 'method' => __METHOD__ )
			);
			return false;
		}

		try {
			$session = \Stripe\Checkout\Session::retrieve(
				$session_id,
				array( 'expand' => array( 'customer', 'payment_intent' ) )
			);
			return $session;
		} catch ( \Exception $e ) {
			STSRC_Logger::exception(
				$e,
				array(
					'method'     => __METHOD__,
					'session_id' => $session_id,
				)
			);
			return false;
		}
	}

	/**
	 * Create checkout session for guest pass purchase.
	 *
	 * @since    1.0.0
	 * @param    int       $member_id    Member ID
	 * @param    int       $quantity     Number of guest passes to purchase
	 * @param    string    $return_url   URL to return to after payment
	 * @return   string|false            Checkout session URL or false on failure
	 */
	public function create_guest_pass_checkout_session( int $member_id, int $quantity, string $return_url = '' ): string|false {
		// Validate inputs
		if ( $member_id <= 0 || $quantity <= 0 ) {
			STSRC_Logger::warning(
				'Invalid parameters provided for guest pass checkout session.',
				array(
					'method'     => __METHOD__,
					'member_id'  => $member_id,
					'quantity'   => $quantity,
				)
			);
			return false;
		}

		// Get member data
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			STSRC_Logger::warning(
				'Member record not found when creating guest pass checkout session.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		// Calculate total ($5 per pass)
		$total = $quantity * 5.00;

		// Set default return URL if not provided
		if ( empty( $return_url ) ) {
			$return_url = home_url( '/guest-pass-portal?purchase=success&session_id={CHECKOUT_SESSION_ID}' );
		}

		// Create checkout session
		return $this->create_checkout_session(
			array(
				'amount'         => $total,
				'product_name'   => $quantity . ' Guest Pass' . ( $quantity > 1 ? 'es' : '' ),
				'customer_id'    => $member['stripe_customer_id'] ?? null,
				'customer_email' => $member['email'],
				'customer_name'  => $member['first_name'] . ' ' . $member['last_name'],
				'success_url'    => $return_url,
				'cancel_url'     => home_url( '/member-portal?guest_pass=cancelled' ),
				'metadata'       => array(
					'payment_type' => 'guest_pass',
					'member_id'    => $member_id,
					'quantity'     => $quantity,
				),
			)
		);
	}
}

