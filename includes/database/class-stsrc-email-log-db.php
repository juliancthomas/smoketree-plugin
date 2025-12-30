<?php

/**
 * Email log database operations class
 *
 * Handles all database operations for email logs table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Email log database operations class.
 *
 * Provides methods for email logging and tracking.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Email_Log_DB {

	/**
	 * Log an email entry.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Array with email log fields (email_campaign_id, member_id, recipient_email, subject, status, error_message, sent_at)
	 * @return   int|false         Email log ID on success, false on failure
	 */
	public static function log_email( array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_email_logs';

		// Set created_at timestamp
		if ( ! isset( $data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		// Define format strings
		$formats = array(
			'email_campaign_id' => '%s',
			'member_id'        => '%d',
			'recipient_email'  => '%s',
			'subject'          => '%s',
			'status'           => '%s',
			'error_message'   => '%s',
			'sent_at'         => '%s',
			'created_at'      => '%s',
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
	 * Retrieve email logs with optional filters.
	 *
	 * @since    1.0.0
	 * @param    array    $filters    Optional filters (member_id, status, date_from, date_to, campaign_id)
	 * @return   array                Array of email log entries
	 */
	public static function get_email_logs( array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_email_logs';

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

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = sanitize_text_field( $filters['date_to'] );
		}

		if ( ! empty( $filters['campaign_id'] ) ) {
			$where_clauses[] = 'email_campaign_id = %s';
			$where_values[]  = sanitize_text_field( $filters['campaign_id'] );
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

		return $results ? $results : array();
	}

	/**
	 * Retrieve all email logs for a specific campaign.
	 *
	 * @since    1.0.0
	 * @param    string    $campaign_id    Email campaign ID
	 * @return   array                    Array of email log entries for the campaign
	 */
	public static function get_campaign_logs( string $campaign_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_email_logs';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE email_campaign_id = %s ORDER BY created_at DESC",
				$campaign_id
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Update email log status.
	 *
	 * @since    1.0.0
	 * @param    int       $email_log_id    Email log ID
	 * @param    string    $status          New status
	 * @param    string    $error_message   Optional error message
	 * @param    string    $sent_at         Optional sent_at timestamp
	 * @return   bool                       True on success, false on failure
	 */
	public static function update_email_status( int $email_log_id, string $status, string $error_message = '', string $sent_at = '' ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_email_logs';

		$data = array( 'status' => $status );

		if ( ! empty( $error_message ) ) {
			$data['error_message'] = $error_message;
		}

		if ( ! empty( $sent_at ) ) {
			$data['sent_at'] = $sent_at;
		} elseif ( 'sent' === $status && empty( $sent_at ) ) {
			// Auto-set sent_at if status is 'sent' and not provided
			$data['sent_at'] = current_time( 'mysql' );
		}

		$formats = array(
			'status'        => '%s',
			'error_message' => '%s',
			'sent_at'      => '%s',
		);

		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'email_log_id' => $email_log_id ),
			$format_array,
			array( '%d' )
		);

		return false !== $result;
	}
}

