<?php

/**
 * Guest pass database operations class
 *
 * Handles all database operations for guest passes.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Guest pass database operations class.
 *
 * Provides methods for guest pass balance management and usage logging.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Guest_Pass_DB {

	/**
	 * Update guest pass balance (increment).
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @param    int    $quantity     Quantity to add (positive number)
	 * @return   bool                 True on success, false on failure
	 */
	public static function update_guest_pass_balance( int $member_id, int $quantity ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		// Get current balance
		$current_balance = self::get_guest_pass_balance( $member_id );

		// Calculate new balance
		$new_balance = $current_balance + $quantity;

		// Ensure balance doesn't go negative
		if ( $new_balance < 0 ) {
			return false;
		}

		// Update balance
		$result = $wpdb->update(
			$table_name,
			array( 'guest_pass_balance' => $new_balance ),
			array( 'member_id' => $member_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Use a guest pass (decrement balance and log usage).
	 *
	 * @since    1.0.0
	 * @param    int       $member_id    Member ID
	 * @param    string    $notes         Optional notes about the usage
	 * @return   bool                     True on success, false on failure
	 */
	public static function use_guest_pass( int $member_id, string $notes = '' ): bool {
		global $wpdb;

		$members_table = $wpdb->prefix . 'stsrc_members';
		$passes_table  = $wpdb->prefix . 'stsrc_guest_passes';

		// Get current balance
		$current_balance = self::get_guest_pass_balance( $member_id );

		if ( $current_balance <= 0 ) {
			return false; // No passes available
		}

		// Start transaction (WordPress doesn't have transactions, so we'll do it manually)
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Decrement balance
			$new_balance = $current_balance - 1;
			$result1     = $wpdb->update(
				$members_table,
				array( 'guest_pass_balance' => $new_balance ),
				array( 'member_id' => $member_id ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false === $result1 ) {
				throw new Exception( 'Failed to update balance' );
			}

			// Log usage
			$log_data = array(
				'member_id'      => $member_id,
				'quantity'       => 1,
				'amount'         => 0.00, // Usage doesn't have an amount
				'used_at'        => current_time( 'mysql' ),
				'payment_status' => 'paid', // Already paid when purchased
				'admin_adjusted'  => 0,
				'notes'          => $notes,
				'created_at'     => current_time( 'mysql' ),
			);

			$formats = array(
				'member_id'      => '%d',
				'quantity'      => '%d',
				'amount'        => '%f',
				'used_at'       => '%s',
				'payment_status' => '%s',
				'admin_adjusted' => '%d',
				'notes'         => '%s',
				'created_at'    => '%s',
			);

			$format_array = array();
			foreach ( array_keys( $log_data ) as $key ) {
				$format_array[] = $formats[ $key ] ?? '%s';
			}

			$result2 = $wpdb->insert( $passes_table, $log_data, $format_array );

			if ( false === $result2 ) {
				throw new Exception( 'Failed to log usage' );
			}

			$wpdb->query( 'COMMIT' );
			return true;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}

	/**
	 * Get guest pass balance for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   int                   Guest pass balance
	 */
	public static function get_guest_pass_balance( int $member_id ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT guest_pass_balance FROM {$table_name} WHERE member_id = %d",
				$member_id
			)
		);

		return (int) $balance;
	}

	/**
	 * Get guest pass usage log for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @param    array  $filters       Optional filters (date_from, date_to, payment_status)
	 * @return   array                Array of guest pass log entries
	 */
	public static function get_guest_pass_log( int $member_id, array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_guest_passes';

		// Build WHERE clause
		$where_clauses = array( 'member_id = %d' );
		$where_values  = array( $member_id );

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = sanitize_text_field( $filters['date_to'] );
		}

		if ( ! empty( $filters['payment_status'] ) ) {
			$where_clauses[] = 'payment_status = %s';
			$where_values[]  = sanitize_text_field( $filters['payment_status'] );
		}

		// Build query
		$query = "SELECT * FROM {$table_name} WHERE " . implode( ' AND ', $where_clauses );
		$query .= ' ORDER BY created_at DESC';

		// Execute query with prepared statement
		$query = $wpdb->prepare( $query, $where_values );

		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Admin adjustment to guest pass balance.
	 *
	 * @since    1.0.0
	 * @param    int       $member_id    Member ID
	 * @param    int       $adjustment   Adjustment amount (positive to add, negative to subtract)
	 * @param    string    $notes        Notes about the adjustment
	 * @return   bool                    True on success, false on failure
	 */
	public static function admin_adjust_balance( int $member_id, int $adjustment, string $notes = '' ): bool {
		global $wpdb;

		$members_table = $wpdb->prefix . 'stsrc_members';
		$passes_table  = $wpdb->prefix . 'stsrc_guest_passes';

		// Get current balance
		$current_balance = self::get_guest_pass_balance( $member_id );

		// Calculate new balance
		$new_balance = $current_balance + $adjustment;

		// Ensure balance doesn't go negative
		if ( $new_balance < 0 ) {
			return false;
		}

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update balance
			$result1 = $wpdb->update(
				$members_table,
				array( 'guest_pass_balance' => $new_balance ),
				array( 'member_id' => $member_id ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false === $result1 ) {
				throw new Exception( 'Failed to update balance' );
			}

			// Log adjustment
			$log_data = array(
				'member_id'       => $member_id,
				'quantity'       => abs( $adjustment ),
				'amount'         => 0.00,
				'payment_status' => 'paid',
				'admin_adjusted' => 1,
				'adjusted_by'    => get_current_user_id(),
				'notes'          => $notes,
				'created_at'     => current_time( 'mysql' ),
			);

			$formats = array(
				'member_id'       => '%d',
				'quantity'       => '%d',
				'amount'         => '%f',
				'payment_status' => '%s',
				'admin_adjusted' => '%d',
				'adjusted_by'    => '%d',
				'notes'          => '%s',
				'created_at'     => '%s',
			);

			$format_array = array();
			foreach ( array_keys( $log_data ) as $key ) {
				$format_array[] = $formats[ $key ] ?? '%s';
			}

			$result2 = $wpdb->insert( $passes_table, $log_data, $format_array );

			if ( false === $result2 ) {
				throw new Exception( 'Failed to log adjustment' );
			}

			$wpdb->query( 'COMMIT' );
			return true;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}
}

