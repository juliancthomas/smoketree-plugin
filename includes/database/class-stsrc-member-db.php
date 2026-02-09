<?php

/**
 * Member database operations class
 *
 * Handles all database operations for members table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Member database operations class.
 *
 * Provides CRUD methods for member records.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Member_DB {

	/**
	 * Create a new member record.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Array with member fields (first_name, last_name, email, etc.)
	 * @return   int|false         Member ID on success, false on failure
	 */
	public static function create_member( array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		// Set timestamps
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		// Define format strings for each field
		$formats = array(
			'user_id'                  => '%d',
			'membership_type_id'       => '%d',
			'status'                   => '%s',
			'payment_type'             => '%s',
			'stripe_customer_id'       => '%s',
			'first_name'               => '%s',
			'last_name'                => '%s',
			'email'                    => '%s',
			'phone'                    => '%s',
			'street_1'                 => '%s',
			'street_2'                 => '%s',
			'city'                     => '%s',
			'state'                    => '%s',
			'zip'                      => '%s',
			'country'                  => '%s',
			'referral_source'          => '%s',
			'waiver_full_name'         => '%s',
			'waiver_signed_date'       => '%s',
			'guest_pass_balance'       => '%d',
			'auto_renewal_enabled'     => '%d',
			'expiration_date'          => '%s',
			'balance_owed'             => '%f',
			'original_membership_price' => '%f',
			'final_payment_method'     => '%s',
			'created_at'               => '%s',
			'updated_at'               => '%s',
		);

		// Build format array in the same order as $data
		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->insert( $table_name, $data, $format_array );

		if ( false === $result ) {
			error_log( 'STSRC Member Insert Failed: ' . $wpdb->last_error );
			error_log( 'Data: ' . print_r( $data, true ) );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Retrieve member by ID.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   array|null           Member array or null if not found
	 */
	public static function get_member( int $member_id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE member_id = %d",
				$member_id
			),
			ARRAY_A
		);

		if ( null === $member ) {
			return null;
		}

		return $member;
	}

	/**
	 * Retrieve member by email.
	 *
	 * @since    1.0.0
	 * @param    string    $email    Member email address
	 * @return   array|null          Member array or null if not found
	 */
	public static function get_member_by_email( string $email ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE email = %s",
				$email
			),
			ARRAY_A
		);

		if ( null === $member ) {
			return null;
		}

		return $member;
	}

	/**
	 * Update member record.
	 *
	 * @since    1.0.0
	 * @param    int      $member_id    Member ID
	 * @param    array    $data         Fields to update
	 * @return   bool                   True on success, false on failure
	 */
	public static function update_member( int $member_id, array $data ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		// Always update the updated_at timestamp
		$data['updated_at'] = current_time( 'mysql' );

		// Define format strings
		$formats = array(
			'user_id'                  => '%d',
			'membership_type_id'       => '%d',
			'status'                   => '%s',
			'payment_type'             => '%s',
			'stripe_customer_id'       => '%s',
			'first_name'               => '%s',
			'last_name'                => '%s',
			'email'                    => '%s',
			'phone'                    => '%s',
			'street_1'                 => '%s',
			'street_2'                 => '%s',
			'city'                     => '%s',
			'state'                    => '%s',
			'zip'                      => '%s',
			'country'                  => '%s',
			'referral_source'          => '%s',
			'waiver_full_name'         => '%s',
			'waiver_signed_date'       => '%s',
			'guest_pass_balance'       => '%d',
			'auto_renewal_enabled'     => '%d',
			'expiration_date'          => '%s',
			'balance_owed'             => '%f',
			'original_membership_price' => '%f',
			'final_payment_method'     => '%s',
			'updated_at'               => '%s',
		);

		// Build format array
		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'member_id' => $member_id ),
			$format_array,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete member record (soft delete by default).
	 *
	 * @since    1.0.0
	 * @param    int     $member_id    Member ID
	 * @param    bool    $hard_delete  If true, permanently delete. If false, soft delete (change status to cancelled).
	 * @return   bool                  True on success, false on failure
	 */
	public static function delete_member( int $member_id, bool $hard_delete = false ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		if ( $hard_delete ) {
			// Hard delete
			$result = $wpdb->delete(
				$table_name,
				array( 'member_id' => $member_id ),
				array( '%d' )
			);
		} else {
			// Soft delete - change status to cancelled
			$result = self::update_member(
				$member_id,
				array( 'status' => 'cancelled' )
			);
		}

		return false !== $result;
	}

	/**
	 * Retrieve filtered member list.
	 *
	 * @since    1.0.0
	 * @param    array    $filters    Array with keys: membership_type_id, status, payment_type, date_from, date_to, search
	 * @return   array                Array of member arrays
	 */
	public static function get_members( array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		// Build WHERE clause
		$where_clauses = array();
		$where_values  = array();

		if ( ! empty( $filters['membership_type_id'] ) ) {
			$where_clauses[] = 'membership_type_id = %d';
			$where_values[]  = intval( $filters['membership_type_id'] );
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

		if ( ! empty( $filters['search'] ) ) {
			$search_term     = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where_clauses[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
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
	 * Count active, paid members.
	 *
	 * @since    1.0.0
	 * @return   int    Count of active members
	 */
	public static function get_active_member_count(): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} 
				WHERE status = %s",
				'active'
			)
		);

		return (int) $count;
	}

	/**
	 * Enhance members table with balance tracking columns.
	 *
	 * Adds new columns for balance tracking: balance_owed, original_membership_price,
	 * and final_payment_method. Uses dbDelta to safely add columns without affecting
	 * existing data.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public static function enhance_table_for_balance_tracking(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'stsrc_members';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Get the full table structure including new columns
		// dbDelta will only add missing columns, not modify existing ones
		$sql = "CREATE TABLE $table_name (
			member_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			membership_type_id BIGINT(20) UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			payment_type VARCHAR(20) NOT NULL,
			stripe_customer_id VARCHAR(255) DEFAULT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(20) NOT NULL,
			street_1 VARCHAR(255) NOT NULL,
			street_2 VARCHAR(255) DEFAULT NULL,
			city VARCHAR(100) NOT NULL DEFAULT 'Tucker',
			state VARCHAR(2) NOT NULL DEFAULT 'GA',
			zip VARCHAR(10) NOT NULL DEFAULT '30084',
			country VARCHAR(2) NOT NULL DEFAULT 'US',
			referral_source VARCHAR(100) DEFAULT NULL,
			waiver_full_name VARCHAR(255) NOT NULL,
			waiver_signed_date DATE NOT NULL,
			guest_pass_balance INT(11) NOT NULL DEFAULT 0,
			auto_renewal_enabled TINYINT(1) NOT NULL DEFAULT 0,
			expiration_date DATE DEFAULT NULL,
			balance_owed DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			original_membership_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			final_payment_method VARCHAR(20) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (member_id),
			UNIQUE KEY email (email),
			UNIQUE KEY user_id (user_id),
			KEY membership_type_id (membership_type_id),
			KEY status (status),
			KEY stripe_customer_id (stripe_customer_id),
			KEY created_at (created_at),
			KEY balance_owed (balance_owed)
		) $charset_collate;";

		dbDelta( $sql );
	}
}

