<?php
/**
 * Balance AJAX handler class
 *
 * Handles admin balance-related AJAX requests.
 *
 * @link       https://smoketree.us
 * @since      1.1.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load required classes
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-balance-service.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-payment-service.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

/**
 * Balance AJAX handler class.
 *
 * @since      1.1.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 */
class STSRC_Balance_Ajax {

	/**
	 * Handle admin balance adjustment submissions.
	 *
	 * Validates input and creates an adjustment transaction via Balance Service.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function handle_admin_adjust_balance(): void {
		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['stsrc_adjust_balance_nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_adjust_balance' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token. Please refresh the page and try again.', 'smoketree-plugin' ) ) );
			return;
		}

		// Capability check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'smoketree-plugin' ) ) );
			return;
		}

		$member_id       = absint( $post_data['member_id'] ?? 0 );
		$adjustment_type = sanitize_text_field( $post_data['adjustment_type'] ?? '' );
		$amount_raw      = $post_data['amount'] ?? '';
		$description     = sanitize_text_field( $post_data['description'] ?? '' );
		$admin_notes     = sanitize_textarea_field( $post_data['admin_notes'] ?? '' );

		if ( $member_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid member ID.', 'smoketree-plugin' ) ) );
			return;
		}

		if ( empty( $adjustment_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Adjustment type is required.', 'smoketree-plugin' ) ) );
			return;
		}

		$allowed_types = array( 'discount', 'fee', 'correction', 'other' );
		if ( ! in_array( $adjustment_type, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid adjustment type.', 'smoketree-plugin' ) ) );
			return;
		}

		$amount = floatval( $amount_raw );
		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Amount must be greater than 0.', 'smoketree-plugin' ) ) );
			return;
		}

		if ( empty( $description ) ) {
			wp_send_json_error( array( 'message' => __( 'Description is required.', 'smoketree-plugin' ) ) );
			return;
		}

		// Map UI adjustment types to balance service types and signed amount
		$service_type = match ( $adjustment_type ) {
			'discount'   => 'discount',
			'fee'        => 'late_fee',
			'correction' => 'other',
			'other'      => 'other',
			default      => 'other',
		};

		$signed_amount = ( 'fee' === $adjustment_type ) ? abs( $amount ) : -abs( $amount );

		$transaction = STSRC_Balance_Service::adjust_balance(
			$member_id,
			$service_type,
			$signed_amount,
			$description,
			$admin_notes,
			get_current_user_id()
		);

		if ( false === $transaction ) {
			wp_send_json_error( array( 'message' => __( 'Failed to adjust balance. Please try again.', 'smoketree-plugin' ) ) );
			return;
		}

		$member = STSRC_Member_DB::get_member( $member_id );
		$new_balance = $member['balance_owed'] ?? null;

		wp_send_json_success(
			array(
				'message'        => __( 'Balance adjusted successfully.', 'smoketree-plugin' ),
				'transaction_id' => $transaction['transaction_id'] ?? null,
				'new_balance'    => $new_balance,
			)
		);
	}

	/**
	 * Handle admin manual payment submissions.
	 *
	 * Validates input and records a manual payment via Balance Service.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function handle_admin_record_payment(): void {
		$post_data = wp_unslash( $_POST );

		// Verify nonce
		$nonce = sanitize_text_field( $post_data['stsrc_record_payment_nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_record_payment' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token. Please refresh the page and try again.', 'smoketree-plugin' ) ) );
			return;
		}

		// Capability check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'smoketree-plugin' ) ) );
			return;
		}

		$member_id     = absint( $post_data['member_id'] ?? 0 );
		$payment_method = sanitize_text_field( $post_data['payment_method'] ?? '' );
		$amount_raw    = $post_data['amount'] ?? '';
		$description   = sanitize_text_field( $post_data['description'] ?? '' );
		$admin_notes   = sanitize_textarea_field( $post_data['admin_notes'] ?? '' );
		$date_received = sanitize_text_field( $post_data['date_received'] ?? '' );
		$check_number  = sanitize_text_field( $post_data['check_number'] ?? '' );

		if ( $member_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid member ID.', 'smoketree-plugin' ) ) );
			return;
		}

		$allowed_methods = array( 'check', 'zelle', 'cash' );
		if ( empty( $payment_method ) || ! in_array( $payment_method, $allowed_methods, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment method.', 'smoketree-plugin' ) ) );
			return;
		}

		$amount = floatval( $amount_raw );
		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Amount must be greater than 0.', 'smoketree-plugin' ) ) );
			return;
		}

		if ( empty( $description ) ) {
			wp_send_json_error( array( 'message' => __( 'Description is required.', 'smoketree-plugin' ) ) );
			return;
		}

		if ( ! empty( $check_number ) && 'check' === $payment_method ) {
			$admin_notes = trim( $admin_notes . "\n" . sprintf( __( 'Check #: %s', 'smoketree-plugin' ), $check_number ) );
		}

		$transaction = STSRC_Balance_Service::record_manual_payment(
			$member_id,
			$payment_method,
			$amount,
			$description,
			$admin_notes,
			get_current_user_id(),
			$date_received
		);

		if ( false === $transaction ) {
			wp_send_json_error( array( 'message' => __( 'Failed to record payment. Please try again.', 'smoketree-plugin' ) ) );
			return;
		}

		// Balance service sends confirmation email; fallback here if send failed.
		$transaction_id = isset( $transaction['transaction_id'] ) ? absint( $transaction['transaction_id'] ) : 0;
		if ( $transaction_id > 0 && empty( $transaction['manual_payment_email_sent'] ) ) {
			$email_service = new STSRC_Email_Service();
			$email_service->send_manual_payment_confirmation_email( $member_id, $transaction_id );
		}

		$member = STSRC_Member_DB::get_member( $member_id );
		$new_balance = $member['balance_owed'] ?? null;

		wp_send_json_success(
			array(
				'message'        => __( 'Manual payment recorded successfully.', 'smoketree-plugin' ),
				'transaction_id' => $transaction['transaction_id'] ?? null,
				'new_balance'    => $new_balance,
			)
		);
	}

	/**
	 * Fee rate definitions for each Stripe payment method.
	 *
	 * @since  1.2.0
	 */
	private const FEE_RATES = array(
		'card'            => array( 'percent' => 0.029, 'flat' => 0.30, 'cap' => null ),
		'us_bank_account' => array( 'percent' => 0.008, 'flat' => 0.00, 'cap' => 5.00 ),
	);

	/**
	 * Calculate the processing fee for a given amount and payment method.
	 *
	 * @since  1.2.0
	 * @param  float  $amount         Payment amount in dollars.
	 * @param  string $payment_method One of the keys in FEE_RATES.
	 * @return float  Fee in dollars, rounded to two decimal places.
	 */
	public static function calculate_processing_fee( float $amount, string $payment_method ): float {
		$rate = self::FEE_RATES[ $payment_method ] ?? null;
		if ( null === $rate || $amount <= 0 ) {
			return 0.00;
		}
		$fee = $amount * $rate['percent'] + $rate['flat'];
		if ( null !== $rate['cap'] && $fee > $rate['cap'] ) {
			$fee = $rate['cap'];
		}
		return round( $fee, 2 );
	}

	/**
	 * Create Stripe checkout session for member balance payment.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function handle_create_balance_payment(): void {
		$post_data = wp_unslash( $_POST );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'smoketree-plugin' ) ) );
			return;
		}

		$nonce = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_balance_payment_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token. Please refresh the page and try again.', 'smoketree-plugin' ) ) );
			return;
		}

		$member_id      = absint( $post_data['member_id'] ?? 0 );
		$amount         = (float) ( $post_data['amount'] ?? 0 );
		$payment_method = sanitize_text_field( $post_data['payment_method'] ?? 'card' );

		if ( $member_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid member.', 'smoketree-plugin' ) ) );
			return;
		}

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Payment amount must be greater than zero.', 'smoketree-plugin' ) ) );
			return;
		}

		$allowed_methods = array_keys( self::FEE_RATES );
		if ( ! in_array( $payment_method, $allowed_methods, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment method.', 'smoketree-plugin' ) ) );
			return;
		}

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => __( 'Member record not found.', 'smoketree-plugin' ) ) );
			return;
		}

		$current_user_id = get_current_user_id();
		$member_user_id  = (int) ( $member['user_id'] ?? 0 );
		$is_admin        = current_user_can( 'manage_options' );

		if ( ! $is_admin && $member_user_id !== $current_user_id ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to pay this balance.', 'smoketree-plugin' ) ) );
			return;
		}

		$balance_owed = (float) ( $member['balance_owed'] ?? 0 );
		if ( $balance_owed <= 0.01 ) {
			wp_send_json_error( array( 'message' => __( 'This account does not have an outstanding balance.', 'smoketree-plugin' ) ) );
			return;
		}

		if ( $amount > $balance_owed ) {
			wp_send_json_error( array( 'message' => __( 'Payment amount cannot exceed your outstanding balance.', 'smoketree-plugin' ) ) );
			return;
		}

		$payment_service = new STSRC_Payment_Service();
		$minimum_payment = $payment_service->get_minimum_balance_payment();

		if ( $amount < $minimum_payment ) {
			wp_send_json_error(
				array(
					/* translators: %s: minimum payment amount */
					'message' => sprintf( __( 'Minimum payment amount is $%s.', 'smoketree-plugin' ), number_format( $minimum_payment, 2 ) ),
				)
			);
			return;
		}

		$remaining = $balance_owed - $amount;
		if ( $remaining > 0 && $remaining < $minimum_payment ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: remaining amount, 2: minimum payment */
						__( 'This payment would leave a remaining balance of $%1$s, which is below the minimum payment of $%2$s. Please pay the full balance or reduce your payment.', 'smoketree-plugin' ),
						number_format( $remaining, 2 ),
						number_format( $minimum_payment, 2 )
					),
				)
			);
			return;
		}

		$processing_fee = self::calculate_processing_fee( $amount, $payment_method );

		$session_url = $payment_service->create_balance_payment_checkout_session( $member_id, $amount, $payment_method, $processing_fee );
		if ( false === $session_url || empty( $session_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to create payment session. Please try again.', 'smoketree-plugin' ) ) );
			return;
		}

		wp_send_json_success(
			array(
				'session_url' => esc_url_raw( $session_url ),
			)
		);
	}
}
