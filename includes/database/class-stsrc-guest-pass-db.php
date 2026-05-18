<?php

/**
 * Guest pass database operations class
 *
 * Handles all database operations for guest passes.
 * Balance is computed from the stsrc_guest_passes ledger table.
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
 * All balance reads are derived from the ledger (no denormalized column).
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Guest_Pass_DB {

	/**
	 * Credit types that increase the balance.
	 *
	 * @var array
	 */
	private static array $credit_types = array( 'purchase', 'admin_credit' );

	/**
	 * Debit types that decrease the balance.
	 *
	 * @var array
	 */
	private static array $debit_types = array( 'usage', 'admin_debit', 'reset' );

	/**
	 * Record a guest pass purchase (increment balance).
	 *
	 * @since    1.2.0
	 * @param    int       $member_id              Member ID.
	 * @param    int       $quantity                Number of passes purchased.
	 * @param    float     $amount                  Payment amount.
	 * @param    string    $stripe_payment_intent   Stripe payment intent ID.
	 * @param    string    $notes                   Optional notes.
	 * @return   int|false                           Inserted row ID or false on failure.
	 */
	public static function record_purchase( int $member_id, int $quantity, float $amount, string $stripe_payment_intent = '', string $notes = '' ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . 'stsrc_guest_passes';

		$data = array(
			'member_id'                => $member_id,
			'type'                     => 'purchase',
			'quantity'                 => $quantity,
			'amount'                   => $amount,
			'stripe_payment_intent_id' => $stripe_payment_intent,
			'payment_status'           => 'succeeded',
			'admin_adjusted'           => 0,
			'notes'                    => $notes,
			'created_at'               => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%d', '%f', '%s', '%s', '%d', '%s', '%s' );

		$result = $wpdb->insert( $table, $data, $formats );

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update guest pass balance (increment).
	 *
	 * Legacy wrapper around record_purchase for backward compatibility.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @param    int    $quantity     Quantity to add (positive number)
	 * @return   bool                 True on success, false on failure
	 */
	public static function update_guest_pass_balance( int $member_id, int $quantity ): bool {
		return false !== self::record_purchase( $member_id, $quantity, 0.00 );
	}

	/**
	 * Use a guest pass (decrement balance and log usage).
	 *
	 * @since    1.0.0
	 * @param    int       $member_id    Member ID
	 * @param    string    $notes        Optional notes about the usage
	 * @return   bool                    True on success, false on failure
	 */
	public static function use_guest_pass( int $member_id, string $notes = '', int $quantity = 1 ): bool {
		global $wpdb;

		$current_balance = self::get_guest_pass_balance( $member_id );

		if ( $current_balance <= 0 || $quantity < 1 || $quantity > $current_balance ) {
			return false;
		}

		$table = $wpdb->prefix . 'stsrc_guest_passes';

		$data = array(
			'member_id'      => $member_id,
			'type'           => 'usage',
			'quantity'       => $quantity,
			'amount'         => 0.00,
			'used_at'        => current_time( 'mysql' ),
			'payment_status' => 'paid',
			'admin_adjusted' => 0,
			'notes'          => $notes,
			'created_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%d', '%f', '%s', '%s', '%d', '%s', '%s' );

		$result = $wpdb->insert( $table, $data, $formats );

		return false !== $result;
	}

	/**
	 * Get guest pass balance for a member.
	 *
	 * Computed from the ledger: SUM(credits) - SUM(debits).
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   int                  Guest pass balance
	 */
	public static function get_guest_pass_balance( int $member_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'stsrc_guest_passes';

		$credits_in = self::sql_in_placeholders( self::$credit_types );
		$debits_in  = self::sql_in_placeholders( self::$debit_types );

		$all_types = array_merge( array( $member_id ), self::$credit_types, array( $member_id ), self::$debit_types );

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT
					COALESCE(
						(SELECT SUM(quantity) FROM {$table} WHERE member_id = %d AND type IN ({$credits_in})),
						0
					)
					-
					COALESCE(
						(SELECT SUM(quantity) FROM {$table} WHERE member_id = %d AND type IN ({$debits_in})),
						0
					)",
				...$all_types
			)
		);

		return max( 0, (int) $balance );
	}

	/**
	 * Get guest pass usage log for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @param    array  $filters      Optional filters (date_from, date_to, payment_status)
	 * @return   array                Array of guest pass log entries
	 */
	public static function get_guest_pass_log( int $member_id, array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_guest_passes';

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

		$query = "SELECT * FROM {$table_name} WHERE " . implode( ' AND ', $where_clauses );
		$query .= ' ORDER BY created_at DESC';

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

		if ( 0 === $adjustment ) {
			return false;
		}

		// For negative adjustments, verify balance won't go below zero.
		if ( $adjustment < 0 ) {
			$current_balance = self::get_guest_pass_balance( $member_id );
			if ( $current_balance + $adjustment < 0 ) {
				return false;
			}
		}

		$table = $wpdb->prefix . 'stsrc_guest_passes';
		$type  = $adjustment > 0 ? 'admin_credit' : 'admin_debit';

		$data = array(
			'member_id'      => $member_id,
			'type'           => $type,
			'quantity'       => abs( $adjustment ),
			'amount'         => 0.00,
			'payment_status' => 'paid',
			'admin_adjusted' => 1,
			'adjusted_by'    => get_current_user_id(),
			'notes'          => $notes,
			'created_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%d', '%f', '%s', '%d', '%d', '%s', '%s' );

		$result = $wpdb->insert( $table, $data, $formats );

		return false !== $result;
	}

	/**
	 * Reset guest pass balance for a member by inserting a reset entry.
	 *
	 * Debits the full current balance so the net becomes zero.
	 *
	 * @since    1.2.0
	 * @param    int       $member_id    Member ID.
	 * @param    string    $notes        Optional notes.
	 * @return   bool                    True on success (or balance already zero), false on failure.
	 */
	public static function reset_balance( int $member_id, string $notes = 'Season reset' ): bool {
		$current_balance = self::get_guest_pass_balance( $member_id );

		if ( 0 === $current_balance ) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'stsrc_guest_passes';

		$data = array(
			'member_id'      => $member_id,
			'type'           => 'reset',
			'quantity'       => $current_balance,
			'amount'         => 0.00,
			'payment_status' => 'paid',
			'admin_adjusted' => 1,
			'adjusted_by'    => get_current_user_id(),
			'notes'          => $notes,
			'created_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%d', '%f', '%s', '%d', '%d', '%s', '%s' );

		$result = $wpdb->insert( $table, $data, $formats );

		return false !== $result;
	}

	/**
	 * Get the total guest pass balance across all members (or for a specific member).
	 *
	 * @since    1.2.0
	 * @param    int|null $member_id    Optional member ID to filter.
	 * @return   int                    Total balance.
	 */
	public static function get_total_balance( ?int $member_id = null ): int {
		global $wpdb;

		$table         = $wpdb->prefix . 'stsrc_guest_passes';
		$members_table = $wpdb->prefix . 'stsrc_members';

		$credits_in = self::sql_in_placeholders( self::$credit_types );
		$debits_in  = self::sql_in_placeholders( self::$debit_types );

		if ( null !== $member_id ) {
			$balance = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT
						COALESCE((SELECT SUM(quantity) FROM {$table} WHERE type IN ({$credits_in}) AND member_id = %d), 0)
						-
						COALESCE((SELECT SUM(quantity) FROM {$table} WHERE type IN ({$debits_in}) AND member_id = %d), 0)",
					...array_merge( self::$credit_types, array( $member_id ), self::$debit_types, array( $member_id ) )
				)
			);

			return max( 0, (int) $balance );
		}

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(
					SUM(
						CASE
							WHEN gp.type IN ({$credits_in}) THEN gp.quantity
							WHEN gp.type IN ({$debits_in}) THEN -gp.quantity
							ELSE 0
						END
					),
					0
				)
				FROM {$table} gp
				INNER JOIN {$members_table} m ON m.member_id = gp.member_id
				WHERE m.is_demo = 0",
				...array_merge( self::$credit_types, self::$debit_types )
			)
		);

		return max( 0, (int) $balance );
	}

	/**
	 * Generate SQL IN() placeholders for an array of strings.
	 *
	 * @param  array $items Array of string values.
	 * @return string       Comma-separated %s placeholders.
	 */
	private static function sql_in_placeholders( array $items ): string {
		return implode( ',', array_fill( 0, count( $items ), '%s' ) );
	}
}
