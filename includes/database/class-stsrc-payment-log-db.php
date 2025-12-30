<?php

/**
 * Payment log database operations class
 *
 * Handles all database operations for payment logs table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Payment log database operations class.
 *
 * Provides methods for payment transaction logging.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Payment_Log_DB {

	/**
	 * Log a payment transaction.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Array with payment log fields (member_id, stripe_payment_intent_id, stripe_checkout_session_id, amount, fee_amount, payment_type, status, stripe_event_id, metadata)
	 * @return   int|false         Payment log ID on success, false on failure
	 */
	public static function log_payment( array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_payment_logs';

		// Validate required fields
		if ( empty( $data['member_id'] ) || empty( $data['amount'] ) || empty( $data['payment_type'] ) ) {
			return false;
		}

		// Set defaults
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = 'pending';
		}

		// Set created_at timestamp
		if ( ! isset( $data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		// Convert metadata array to JSON if it's an array
		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		// Define format strings
		$formats = array(
			'member_id'                  => '%d',
			'stripe_payment_intent_id'   => '%s',
			'stripe_checkout_session_id' => '%s',
			'amount'                     => '%f',
			'fee_amount'                 => '%f',
			'payment_type'               => '%s',
			'status'                     => '%s',
			'stripe_event_id'            => '%s',
			'metadata'                  => '%s',
			'created_at'                 => '%s',
		);

		// Build format array
		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->insert( $table_name, $data, $format_array );

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Retrieve payment logs with optional filters.
	 *
	 * @since    1.0.0
	 * @param    array    $filters    Optional filters (member_id, status, payment_type, date_from, date_to)
	 * @return   array                Array of payment log entries
	 */
	public static function get_payment_logs( array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_payment_logs';

		// Build WHERE clause
		$where_clauses = array();
		$where_values  = array();

		if ( ! empty( $filters['member_id'] ) ) {
			$where_clauses[] = 'member_id = %d';
			$where_values[]  = intval( $filters['member_id'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = sanitize_text_field( $filters['status'] );
		}

		if ( ! empty( $filters['payment_type'] ) ) {
			$where_clauses[] = 'payment_type = %s';
			$where_values[]  = sanitize_text_field( $filters['payment_type'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = sanitize_text_field( $filters['date_to'] );
		}

		// Build query
		$query = "SELECT * FROM {$table_name}";

		if ( ! empty( $where_clauses ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		$query .= ' ORDER BY created_at DESC';

		// Execute query with prepared statement
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! $results ) {
			return array();
		}

		// Decode JSON metadata for each payment log
		foreach ( $results as &$log ) {
			if ( isset( $log['metadata'] ) && ! empty( $log['metadata'] ) ) {
				$decoded = json_decode( $log['metadata'], true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$log['metadata'] = $decoded;
				}
			}
		}

		return $results;
	}

	/**
	 * Retrieve payment log by Stripe payment intent ID.
	 *
	 * @since    1.0.0
	 * @param    string    $payment_intent_id    Stripe payment intent ID
	 * @return   array|null                      Payment log array or null if not found
	 */
	public static function get_payment_by_intent_id( string $payment_intent_id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_payment_logs';

		$payment_log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE stripe_payment_intent_id = %s",
				$payment_intent_id
			),
			ARRAY_A
		);

		if ( null === $payment_log ) {
			return null;
		}

		// Decode JSON metadata if present
		if ( isset( $payment_log['metadata'] ) && ! empty( $payment_log['metadata'] ) ) {
			$decoded = json_decode( $payment_log['metadata'], true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$payment_log['metadata'] = $decoded;
			}
		}

		return $payment_log;
	}

	/**
	 * Update payment log status.
	 *
	 * @since    1.0.0
	 * @param    int       $payment_log_id    Payment log ID
	 * @param    string    $status            New status
	 * @param    string    $stripe_event_id   Optional Stripe event ID
	 * @return   bool                         True on success, false on failure
	 */
	public static function update_payment_status( int $payment_log_id, string $status, string $stripe_event_id = '' ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_payment_logs';

		$data = array( 'status' => $status );

		if ( ! empty( $stripe_event_id ) ) {
			$data['stripe_event_id'] = $stripe_event_id;
		}

		$formats = array(
			'status'          => '%s',
			'stripe_event_id' => '%s',
		);

		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'payment_log_id' => $payment_log_id ),
			$format_array,
			array( '%d' )
		);

		return false !== $result;
	}
}

