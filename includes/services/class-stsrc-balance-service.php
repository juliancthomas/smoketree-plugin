<?php

/**
 * Balance Service Class
 *
 * Handles all business logic for member balance operations including
 * adjustments, manual payments, balance calculations, and status updates.
 *
 * @link       https://smoketree.us
 * @since      1.1.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

// Load required database classes
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-member-service.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-stsrc-activity-log.php';

/**
 * Balance Service Class.
 *
 * Orchestrates balance-related operations and enforces business rules.
 *
 * @since      1.1.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Balance_Service {

	/**
	 * Adjust member balance (add fee or discount).
	 *
	 * Creates an adjustment transaction and updates the member's balance.
	 *
	 * @since    1.1.0
	 * @param    int    $member_id         Member ID
	 * @param    string $adjustment_type   Type: 'discount', 'credit', 'late_fee', 'processing_fee', 'refund', 'other'
	 * @param    float  $amount            Amount (positive = charge/fee, negative = discount/credit)
	 * @param    string $description       User-facing description
	 * @param    string $admin_notes       Internal admin notes (optional)
	 * @param    int    $admin_user_id     Admin user making the adjustment
	 * @return   array|false               Transaction data on success, false on failure
	 */
	public static function adjust_balance( int $member_id, string $adjustment_type, float $amount, string $description, string $admin_notes = '', int $admin_user_id = 0 ): array|false {
		// Validate required fields
		if ( empty( $description ) ) {
			error_log( 'STSRC Balance Adjustment Failed: Description is required' );
			return false;
		}

		// Get current member data
		$member = STSRC_Member_DB::get_member( $member_id );

		if ( null === $member ) {
			error_log( 'STSRC Balance Adjustment Failed: Member not found' );
			return false;
		}

		$current_balance = (float) ( $member['balance_owed'] ?? 0.00 );

		// Calculate new balance
		$new_balance = $current_balance + $amount;

		// Ensure balance doesn't go negative unless it's a refund/credit
		if ( $new_balance < 0 && ! in_array( $adjustment_type, array( 'refund', 'credit' ), true ) ) {
			// Allow negative balances for refunds/credits (overpayment scenario)
		}

		// Create transaction record
		$transaction_data = array(
			'transaction_type' => 'adjustment',
			'payment_method'   => 'admin_adjustment',
			'amount'           => $amount,
			'balance_after'    => $new_balance,
			'description'      => sanitize_text_field( $description ),
			'admin_user_id'    => $admin_user_id,
			'admin_notes'      => sanitize_textarea_field( $admin_notes ),
		);

		$transaction_id = STSRC_Transaction_DB::create_transaction( $member_id, $transaction_data );

		if ( false === $transaction_id ) {
			error_log( 'STSRC Balance Adjustment Failed: Could not create transaction' );
			return false;
		}

		// Update member balance
		$balance_updated = STSRC_Member_DB::update_balance( $member_id, $new_balance );

		if ( ! $balance_updated ) {
			error_log( 'STSRC Balance Adjustment Failed: Could not update member balance' );
			return false;
		}

		// Check if member should be activated
		self::update_member_status_if_paid( $member_id );

		// Return transaction data
		$transaction = STSRC_Transaction_DB::get_transaction( $transaction_id );
		if ( $transaction ) {
			STSRC_Activity_Log::log_balance_activity(
				$member_id,
				'balance_adjustment_recorded',
				array(
					'transaction_id'  => (int) $transaction_id,
					'adjustment_type' => $adjustment_type,
					'amount'          => $amount,
					'previous_balance'=> $current_balance,
					'new_balance'     => $new_balance,
					'description'     => $description,
				),
				$admin_user_id
			);
		}

		return $transaction;
	}

	/**
	 * Record a manual payment (check, Zelle, cash).
	 *
	 * Creates a payment transaction and updates the member's balance.
	 *
	 * @since    1.1.0
	 * @param    int    $member_id         Member ID
	 * @param    string $payment_method    Payment method: 'check', 'zelle', 'cash'
	 * @param    float  $amount            Payment amount (must be positive)
	 * @param    string $description       User-facing description
	 * @param    string $admin_notes       Internal admin notes (optional)
	 * @param    int    $admin_user_id     Admin user recording the payment
	 * @param    string $date_received     Date payment was received (YYYY-MM-DD format)
	 * @return   array|false               Transaction data on success, false on failure
	 */
	public static function record_manual_payment( int $member_id, string $payment_method, float $amount, string $description, string $admin_notes = '', int $admin_user_id = 0, string $date_received = '' ): array|false {
		// Validate amount is positive
		if ( $amount <= 0 ) {
			error_log( 'STSRC Manual Payment Failed: Amount must be positive' );
			return false;
		}

		// Validate required fields
		if ( empty( $description ) ) {
			error_log( 'STSRC Manual Payment Failed: Description is required' );
			return false;
		}

		// Validate payment method
		$allowed_methods = array( 'check', 'zelle', 'cash' );
		if ( ! in_array( $payment_method, $allowed_methods, true ) ) {
			error_log( 'STSRC Manual Payment Failed: Invalid payment method' );
			return false;
		}

		// Get current member data
		$member = STSRC_Member_DB::get_member( $member_id );

		if ( null === $member ) {
			error_log( 'STSRC Manual Payment Failed: Member not found' );
			return false;
		}

		$current_balance = (float) ( $member['balance_owed'] ?? 0.00 );

		// Calculate new balance (payment reduces balance, so use negative amount)
		$new_balance = $current_balance - $amount;

		// Set date received or use current date
		if ( empty( $date_received ) ) {
			$date_received = current_time( 'mysql' );
		} else {
			// Convert YYYY-MM-DD to datetime
			$date_received = date( 'Y-m-d H:i:s', strtotime( $date_received ) );
		}

		// Create transaction record (negative amount for payment)
		$transaction_data = array(
			'transaction_type' => 'payment',
			'payment_method'   => $payment_method,
			'amount'           => -$amount, // Negative because payment reduces balance
			'balance_after'    => $new_balance,
			'description'      => sanitize_text_field( $description ),
			'admin_user_id'    => $admin_user_id,
			'admin_notes'      => sanitize_textarea_field( $admin_notes ),
			'created_at'       => $date_received,
		);

		$transaction_id = STSRC_Transaction_DB::create_transaction( $member_id, $transaction_data );

		if ( false === $transaction_id ) {
			error_log( 'STSRC Manual Payment Failed: Could not create transaction' );
			return false;
		}

		// Update member balance and final payment method
		$balance_updated = STSRC_Member_DB::update_balance( $member_id, $new_balance, $payment_method );

		if ( ! $balance_updated ) {
			error_log( 'STSRC Manual Payment Failed: Could not update member balance' );
			return false;
		}

		// Check if member should be activated
		self::update_member_status_if_paid( $member_id );

		// Return transaction data
		$transaction = STSRC_Transaction_DB::get_transaction( $transaction_id );
		if ( $transaction ) {
			$email_service = new STSRC_Email_Service();
			$email_sent    = $email_service->send_manual_payment_confirmation_email( $member_id, $transaction_id );
			$transaction['manual_payment_email_sent'] = $email_sent;

			STSRC_Activity_Log::log_balance_activity(
				$member_id,
				'manual_payment_recorded',
				array(
					'transaction_id'   => (int) $transaction_id,
					'payment_method'   => $payment_method,
					'amount'           => $amount,
					'previous_balance' => $current_balance,
					'new_balance'      => $new_balance,
					'date_received'    => $date_received,
					'email_sent'       => $email_sent,
				),
				$admin_user_id
			);
		}

		return $transaction;
	}

	/**
	 * Record a Stripe payment (card or ACH).
	 *
	 * Creates a payment transaction and updates the member's balance.
	 * Called from webhook handler.
	 *
	 * @since    1.1.0
	 * @param    int    $member_id       Member ID
	 * @param    float  $amount          Payment amount (must be positive)
	 * @param    string $payment_method  Payment method: 'card' or 'us_bank_account'
	 * @param    array  $stripe_ids      Stripe IDs: payment_intent_id, charge_id, session_id
	 * @param    string $description     User-facing description
	 * @return   array|false             Transaction data on success, false on failure
	 */
	public static function record_stripe_payment( int $member_id, float $amount, string $payment_method, array $stripe_ids, string $description ): array|false {
		// Validate amount is positive
		if ( $amount <= 0 ) {
			error_log( 'STSRC Stripe Payment Failed: Amount must be positive' );
			return false;
		}

		// Validate payment method
		$allowed_methods = array( 'card', 'us_bank_account' );
		if ( ! in_array( $payment_method, $allowed_methods, true ) ) {
			error_log( 'STSRC Stripe Payment Failed: Invalid payment method' );
			return false;
		}

		// Get current member data
		$member = STSRC_Member_DB::get_member( $member_id );

		if ( null === $member ) {
			error_log( 'STSRC Stripe Payment Failed: Member not found' );
			return false;
		}

		$current_balance = (float) ( $member['balance_owed'] ?? 0.00 );

		// Calculate new balance (payment reduces balance, so use negative amount)
		$new_balance = $current_balance - $amount;

		// Create transaction record (negative amount for payment)
		$transaction_data = array(
			'transaction_type'        => 'payment',
			'payment_method'          => $payment_method,
			'amount'                  => -$amount, // Negative because payment reduces balance
			'balance_after'           => $new_balance,
			'stripe_payment_intent_id' => $stripe_ids['payment_intent_id'] ?? null,
			'stripe_charge_id'        => $stripe_ids['charge_id'] ?? null,
			'stripe_session_id'       => $stripe_ids['session_id'] ?? null,
			'description'             => sanitize_text_field( $description ),
		);

		$transaction_id = STSRC_Transaction_DB::create_transaction( $member_id, $transaction_data );

		if ( false === $transaction_id ) {
			error_log( 'STSRC Stripe Payment Failed: Could not create transaction' );
			return false;
		}

		// Update member balance and final payment method
		$balance_updated = STSRC_Member_DB::update_balance( $member_id, $new_balance, $payment_method );

		if ( ! $balance_updated ) {
			error_log( 'STSRC Stripe Payment Failed: Could not update member balance' );
			return false;
		}

		// Check if member should be activated
		self::update_member_status_if_paid( $member_id );

		// Return transaction data
		$transaction = STSRC_Transaction_DB::get_transaction( $transaction_id );
		if ( $transaction ) {
			$email_service = new STSRC_Email_Service();
			$email_service->send_balance_payment_success_email( $member_id, $transaction_id );
			$email_service->send_admin_balance_payment_notification( $member_id, $transaction_id );

			if ( $new_balance < 0 ) {
				$email_service->send_admin_overpayment_alert( $member_id, $transaction_id );
			}

			STSRC_Activity_Log::log_balance_activity(
				$member_id,
				'stripe_balance_payment_recorded',
				array(
					'transaction_id'      => (int) $transaction_id,
					'payment_method'      => $payment_method,
					'amount'              => $amount,
					'previous_balance'    => $current_balance,
					'new_balance'         => $new_balance,
					'payment_intent_id'   => $stripe_ids['payment_intent_id'] ?? '',
					'stripe_session_id'   => $stripe_ids['session_id'] ?? '',
					'overpayment_detected'=> ( $new_balance < 0 ),
				),
				0
			);
		}

		return $transaction;
	}

	/**
	 * Update member status to 'active' if balance is paid.
	 *
	 * Automatically activates a member if:
	 * - Current status is 'pending'
	 * - Balance owed is <= 0
	 *
	 * @since    1.1.0
	 * @param    int  $member_id    Member ID
	 * @return   bool               True if status was changed, false otherwise
	 */
	public static function update_member_status_if_paid( int $member_id ): bool {
		// Get current member data
		$member = STSRC_Member_DB::get_member( $member_id );

		if ( null === $member ) {
			return false;
		}

		$current_status  = $member['status'] ?? 'pending';
		$balance_owed    = (float) ( $member['balance_owed'] ?? 0.00 );

		// Only activate if currently pending and balance is zero or negative
		if ( 'pending' === $current_status && $balance_owed <= 0 ) {
			$member_service = new STSRC_Member_Service();
			$activated      = $member_service->activate_member( $member_id );

			if ( $activated ) {
				error_log(
					sprintf(
						'STSRC: Member #%d automatically activated after balance reached %s',
						$member_id,
						number_format( $balance_owed, 2 )
					)
				);

				do_action( 'stsrc_member_auto_activated_after_balance_paid', $member_id, $balance_owed, $member );

				STSRC_Activity_Log::log_balance_activity(
					$member_id,
					'member_auto_activated',
					array(
						'previous_status' => $current_status,
						'new_status'      => 'active',
						'balance_owed'    => $balance_owed,
						'reason'          => 'balance_paid_in_full',
					),
					0
				);
			}

			return $activated;
		}

		return false;
	}

	/**
	 * Log admin status override when member is activated with outstanding balance.
	 *
	 * @since    1.1.0
	 * @param    int    $member_id      Member ID.
	 * @param    int    $admin_user_id  Admin user ID performing override.
	 * @param    string $context        Optional context string.
	 * @return   bool                   True when override was logged.
	 */
	public static function handle_admin_status_override( int $member_id, int $admin_user_id = 0, string $context = '' ): bool {
		$member = STSRC_Member_DB::get_member( $member_id );
		if ( null === $member ) {
			return false;
		}

		$current_status = (string) ( $member['status'] ?? '' );
		$balance_owed   = (float) ( $member['balance_owed'] ?? 0.00 );

		if ( 'active' !== $current_status || $balance_owed <= 0 ) {
			return false;
		}

		$admin_user_id = $admin_user_id > 0 ? $admin_user_id : get_current_user_id();
		$context       = sanitize_text_field( $context );

		error_log(
			sprintf(
				'STSRC: Admin override activation | member_id=%d | admin_user_id=%d | outstanding_balance=%s | context=%s',
				$member_id,
				$admin_user_id,
				number_format( $balance_owed, 2, '.', '' ),
				$context
			)
		);

		do_action(
			'stsrc_member_status_override_with_balance',
			$member_id,
			$admin_user_id,
			$balance_owed,
			$context,
			$member
		);

		STSRC_Activity_Log::log_balance_activity(
			$member_id,
			'admin_status_override_with_balance',
			array(
				'previous_status'   => $current_status,
				'new_status'        => 'active',
				'outstanding_balance' => $balance_owed,
				'context'           => $context,
			),
			$admin_user_id
		);

		return true;
	}

	/**
	 * Get balance display data for a member.
	 *
	 * Returns formatted data ready for display in admin or member portal.
	 *
	 * @since    1.1.0
	 * @param    int        $member_id    Member ID
	 * @return   array|null               Balance display data or null if member not found
	 */
	public static function get_balance_display_data( int $member_id ): ?array {
		// Get balance summary from transaction DB
		$summary = STSRC_Transaction_DB::get_balance_summary( $member_id );

		if ( null === $summary ) {
			return null;
		}

		// Get member data for additional info
		$member = STSRC_Member_DB::get_member( $member_id );

		if ( null === $member ) {
			return null;
		}

		// Get membership type name
		$membership_type = STSRC_Membership_DB::get_membership_type( $member['membership_type_id'] );
		$membership_name = $membership_type['name'] ?? 'Unknown';

		$balance_owed = $summary['balance_owed'];

		// Determine status badge
		if ( $balance_owed <= 0 && $balance_owed >= -0.01 ) {
			$status_badge = 'paid_in_full';
			$status_label = 'Paid in Full';
		} elseif ( $balance_owed < 0 ) {
			$status_badge = 'overpaid';
			$status_label = 'Overpaid';
		} else {
			$status_badge = 'outstanding';
			$status_label = 'Outstanding Balance';
		}

		return array(
			'member_id'            => $member_id,
			'membership_type_name' => $membership_name,
			'original_price'       => $summary['original_price'],
			'total_paid'           => $summary['total_paid'],
			'total_adjustments'    => $summary['total_adjustments'],
			'balance_owed'         => $balance_owed,
			'final_payment_method' => $summary['final_payment_method'],
			'status_badge'         => $status_badge,
			'status_label'         => $status_label,
			'member_status'        => $member['status'],
		);
	}
}

