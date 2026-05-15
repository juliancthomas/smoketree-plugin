<?php

/**
 * Transaction database operations class
 *
 * Handles all database operations for transactions table.
 *
 * @link       https://smoketree.us
 * @since      1.1.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Transaction database operations class.
 *
 * Provides methods for creating and managing member transaction records
 * including payments, adjustments, refunds, and fees.
 *
 * @since      1.1.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Transaction_DB {

	/**
	 * Create the transactions table.
	 *
	 * Uses dbDelta to create or update the table schema.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public static function create_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'stsrc_transactions';
		$members_table   = $wpdb->prefix . 'stsrc_members';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE $table_name (
			transaction_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			transaction_type ENUM('payment','adjustment','refund','fee','initial') NOT NULL,
			payment_method ENUM('check','zelle','card','us_bank_account','admin_adjustment','initial') DEFAULT NULL,
			amount DECIMAL(10,2) NOT NULL,
			balance_after DECIMAL(10,2) NOT NULL,
			stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
			stripe_charge_id VARCHAR(255) DEFAULT NULL,
			stripe_session_id VARCHAR(255) DEFAULT NULL,
			description TEXT NOT NULL,
			admin_user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			admin_notes TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (transaction_id),
			KEY member_id (member_id),
			KEY transaction_type (transaction_type),
			KEY created_at (created_at),
			KEY stripe_payment_intent_id (stripe_payment_intent_id(191))
		) $charset_collate;";

		dbDelta( $sql );

		// Note: Foreign key constraint is added by STSRC_Database::add_foreign_key_constraints()
		// after all tables are created, as dbDelta doesn't handle foreign keys properly.
	}

	/**
	 * Drop the transactions table.
	 *
	 * Used during plugin uninstallation.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}

	/**
	 * Create a new transaction record.
	 *
	 * @since    1.1.0
	 * @param    int      $member_id          Member ID
	 * @param    array    $transaction_data   Transaction data array
	 * @return   int|false                    Transaction ID on success, false on failure
	 */
	public static function create_transaction( int $member_id, array $transaction_data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		// Validate required fields
		if ( empty( $transaction_data['transaction_type'] ) || ! isset( $transaction_data['amount'] ) || ! isset( $transaction_data['balance_after'] ) || empty( $transaction_data['description'] ) ) {
			error_log( 'STSRC Transaction Insert Failed: Missing required fields' );
			return false;
		}

		// Set member_id
		$data = array(
			'member_id' => $member_id,
		);

		// Merge in transaction data
		$data = array_merge( $data, $transaction_data );

		// Set created_at timestamp if not provided
		if ( ! isset( $data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		// Define format strings for each field
		$formats = array(
			'member_id'               => '%d',
			'transaction_type'        => '%s',
			'payment_method'          => '%s',
			'amount'                  => '%f',
			'balance_after'           => '%f',
			'stripe_payment_intent_id' => '%s',
			'stripe_charge_id'        => '%s',
			'stripe_session_id'       => '%s',
			'description'             => '%s',
			'admin_user_id'           => '%d',
			'admin_notes'             => '%s',
			'created_at'              => '%s',
		);

		// Build format array in the same order as $data
		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->insert( $table_name, $data, $format_array );

		if ( false === $result ) {
			error_log( 'STSRC Transaction Insert Failed: ' . $wpdb->last_error );
			error_log( 'Data: ' . print_r( $data, true ) );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Retrieve transaction by ID.
	 *
	 * @since    1.1.0
	 * @param    int    $transaction_id    Transaction ID
	 * @return   array|null                Transaction array or null if not found
	 */
	public static function get_transaction( int $transaction_id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE transaction_id = %d",
				$transaction_id
			),
			ARRAY_A
		);

		if ( null === $transaction ) {
			return null;
		}

		return $transaction;
	}

	/**
	 * Retrieve transactions for a member with optional filters.
	 *
	 * @since    1.1.0
	 * @param    int      $member_id    Member ID
	 * @param    int|null $year         Optional year filter (e.g., 2026)
	 * @param    int      $page         Page number for pagination (default 1)
	 * @param    int      $per_page     Results per page (default 20)
	 * @return   array                  Array of transaction arrays
	 */
	public static function get_transactions( int $member_id, ?int $year = null, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';
		$page       = max( 1, $page );
		$per_page   = max( 1, $per_page );

		// Build WHERE clause
		$where_clauses = array( 'member_id = %d' );
		$where_values  = array( $member_id );

		// Add year filter if provided
		if ( null !== $year ) {
			$where_clauses[] = 'YEAR(created_at) = %d';
			$where_values[]  = $year;
		}

		// Calculate offset for pagination
		$offset = ( $page - 1 ) * $per_page;

		// Build query
		$query = "SELECT * FROM {$table_name}";
		$query .= ' WHERE ' . implode( ' AND ', $where_clauses );
		$query .= ' ORDER BY created_at DESC';
		$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

		// Execute query with prepared statement
		$query = $wpdb->prepare( $query, $where_values );

		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get total amount paid by a member (sum of payment transactions).
	 *
	 * @since    1.1.0
	 * @param    int    $member_id    Member ID
	 * @return   float                Total amount paid
	 */
	public static function get_total_paid( int $member_id ): float {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(ABS(amount)) FROM {$table_name} 
				WHERE member_id = %d 
				AND transaction_type = 'payment' 
				AND amount < 0",
				$member_id
			)
		);

		return (float) ( $total ?? 0.00 );
	}

	/**
	 * Get total adjustments for a member (sum of adjustment transactions).
	 *
	 * @since    1.1.0
	 * @param    int    $member_id    Member ID
	 * @return   float                Total adjustments (positive or negative)
	 */
	public static function get_total_adjustments( int $member_id ): float {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$table_name} 
				WHERE member_id = %d 
				AND transaction_type = 'adjustment'",
				$member_id
			)
		);

		return (float) ( $total ?? 0.00 );
	}

	/**
	 * Get balance summary for a member.
	 *
	 * Returns array with season price, total paid, total adjustments, and current balance.
	 *
	 * @since    1.1.0
	 * @param    int    $member_id    Member ID
	 * @return   array|null           Balance summary array or null if member not found
	 */
	public static function get_balance_summary( int $member_id ): ?array {
		// Get member data for original price and current balance
		$member = STSRC_Member_DB::get_member( $member_id );

		if ( null === $member ) {
			return null;
		}

		// Get transaction totals
		$total_paid        = self::get_total_paid( $member_id );
		$total_adjustments = self::get_total_adjustments( $member_id );

		return array(
			'season_price'        => (float) ( $member['season_membership_price'] ?? 0.00 ),
			'total_paid'          => $total_paid,
			'total_adjustments'   => $total_adjustments,
			'balance_owed'        => (float) ( $member['balance_owed'] ?? 0.00 ),
			'final_payment_method' => $member['final_payment_method'] ?? null,
		);
	}

	/**
	 * Get transaction by Stripe payment intent ID.
	 *
	 * @since    1.1.0
	 * @param    string    $payment_intent_id    Stripe payment intent ID
	 * @return   array|null                      Transaction array or null if not found
	 */
	public static function get_transaction_by_payment_intent( string $payment_intent_id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE stripe_payment_intent_id = %s",
				$payment_intent_id
			),
			ARRAY_A
		);

		if ( null === $transaction ) {
			return null;
		}

		return $transaction;
	}

	/**
	 * Get transaction by Stripe session ID.
	 *
	 * @since    1.1.0
	 * @param    string    $session_id    Stripe session ID
	 * @return   array|null                Transaction array or null if not found
	 */
	public static function get_transaction_by_session_id( string $session_id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE stripe_session_id = %s",
				$session_id
			),
			ARRAY_A
		);

		if ( null === $transaction ) {
			return null;
		}

		return $transaction;
	}

	/**
	 * Count total transactions for a member.
	 *
	 * @since    1.1.0
	 * @param    int      $member_id    Member ID
	 * @param    int|null $year         Optional year filter
	 * @return   int                    Transaction count
	 */
	public static function count_transactions( int $member_id, ?int $year = null ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		// Build WHERE clause
		$where_clauses = array( 'member_id = %d' );
		$where_values  = array( $member_id );

		// Add year filter if provided
		if ( null !== $year ) {
			$where_clauses[] = 'YEAR(created_at) = %d';
			$where_values[]  = $year;
		}

		// Build query
		$query = "SELECT COUNT(*) FROM {$table_name}";
		$query .= ' WHERE ' . implode( ' AND ', $where_clauses );

		// Execute query with prepared statement
		$query = $wpdb->prepare( $query, $where_values );
		$count = $wpdb->get_var( $query );

		return (int) $count;
	}

	/**
	 * Get distinct years that have transactions for a member, newest first.
	 *
	 * @since    1.19.0
	 * @param    int    $member_id    Member ID
	 * @return   int[]                Array of years, descending
	 */
	public static function get_transaction_years( int $member_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_transactions';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT YEAR(created_at) FROM {$table_name} WHERE member_id = %d ORDER BY YEAR(created_at) DESC",
				$member_id
			)
		);

		return array_map( 'intval', $results ?: array() );
	}

	/**
	 * Backfill initial transactions for existing members.
	 *
	 * Creates initial transaction records for all members who don't have any transactions yet.
	 * For active members: creates a transaction showing they paid in full
	 * For pending/cancelled members: creates a transaction showing outstanding balance
	 *
	 * @since    1.1.0
	 * @return   int    Number of transaction records created
	 */
	public static function backfill_initial_transactions(): int {
		global $wpdb;

		$members_table      = $wpdb->prefix . 'stsrc_members';
		$memberships_table  = $wpdb->prefix . 'stsrc_membership_types';
		$transactions_table = $wpdb->prefix . 'stsrc_transactions';

		// Get all members who don't have any transactions yet
		$members = $wpdb->get_results(
			"SELECT m.member_id, m.membership_type_id, m.status, m.balance_owed, 
				m.season_membership_price, m.created_at, mt.name as membership_name
			FROM {$members_table} m
			LEFT JOIN {$memberships_table} mt ON m.membership_type_id = mt.membership_type_id
			LEFT JOIN {$transactions_table} t ON m.member_id = t.member_id
			WHERE t.transaction_id IS NULL
			GROUP BY m.member_id",
			ARRAY_A
		);

		if ( empty( $members ) ) {
			return 0;
		}

		$created_count = 0;

		foreach ( $members as $member ) {
			$member_id        = (int) $member['member_id'];
			$status           = $member['status'] ?? 'pending';
			$balance_owed     = (float) ( $member['balance_owed'] ?? 0.00 );
			$season_price     = (float) ( $member['season_membership_price'] ?? 0.00 );
			$membership_name  = $member['membership_name'] ?? 'Membership';
			$created_at       = $member['created_at'] ?? current_time( 'mysql' );

			// Create appropriate transaction based on member status
			if ( 'active' === $status ) {
				$description = sprintf(
					'Initial membership registration - %s ($%s) - Paid in full',
					$membership_name,
					number_format( $season_price, 2 )
				);

				$transaction_data = array(
					'transaction_type' => 'initial',
					'payment_method'   => 'initial',
					'amount'           => 0.00,
					'balance_after'    => 0.00,
					'description'      => $description,
					'created_at'       => $created_at,
				);
			} else {
				$description = sprintf(
					'Initial membership registration - %s ($%s)',
					$membership_name,
					number_format( $season_price, 2 )
				);

				$transaction_data = array(
					'transaction_type' => 'initial',
					'payment_method'   => 'initial',
					'amount'           => 0.00,
					'balance_after'    => $balance_owed,
					'description'      => $description,
					'created_at'       => $created_at,
				);
			}

			$result = self::create_transaction( $member_id, $transaction_data );

			if ( false !== $result ) {
				$created_count++;
			}
		}

		return $created_count;
	}
}

