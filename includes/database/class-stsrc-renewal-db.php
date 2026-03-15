<?php
/**
 * Renewal database operations.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renewal database operations class.
 */
class STSRC_Renewal_DB {
	public const STATUS_INITIATED       = 'initiated';
	public const STATUS_PENDING_PAYMENT = 'pending_payment';
	public const STATUS_COMPLETED       = 'completed';
	public const STATUS_FAILED          = 'failed';
	public const STATUS_CANCELLED       = 'cancelled';

	/**
	 * Get the renewals table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'stsrc_member_renewals';
	}

	/**
	 * Create or update the renewals table schema.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			renewal_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			season_key VARCHAR(16) NOT NULL,
			old_membership_type_id BIGINT(20) UNSIGNED NOT NULL,
			new_membership_type_id BIGINT(20) UNSIGNED NOT NULL,
			payment_method VARCHAR(20) NOT NULL,
			payment_context VARCHAR(32) NOT NULL DEFAULT 'renewal',
			stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
			stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
			subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			processing_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			previous_balance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(20) NOT NULL DEFAULT 'initiated',
			transition_snapshot_json LONGTEXT NOT NULL,
			notes TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			completed_at DATETIME DEFAULT NULL,
			PRIMARY KEY (renewal_id),
			KEY member_id (member_id),
			KEY season_key (season_key),
			KEY status (status),
			KEY stripe_checkout_session_id (stripe_checkout_session_id(191)),
			KEY member_season (member_id, season_key),
			UNIQUE KEY member_season_status (member_id, season_key, status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create a renewal ledger record.
	 *
	 * @param array $data Renewal data.
	 * @return int|false
	 */
	public static function create_renewal( array $data ): int|false {
		global $wpdb;

		$table_name = self::get_table_name();
		$now        = current_time( 'mysql' );
		$payload    = array(
			'member_id'                => (int) ( $data['member_id'] ?? 0 ),
			'season_key'               => (string) ( $data['season_key'] ?? '' ),
			'old_membership_type_id'   => (int) ( $data['old_membership_type_id'] ?? 0 ),
			'new_membership_type_id'   => (int) ( $data['new_membership_type_id'] ?? 0 ),
			'payment_method'           => (string) ( $data['payment_method'] ?? '' ),
			'payment_context'          => (string) ( $data['payment_context'] ?? 'renewal' ),
			'stripe_checkout_session_id' => isset( $data['stripe_checkout_session_id'] ) ? (string) $data['stripe_checkout_session_id'] : null,
			'stripe_payment_intent_id' => isset( $data['stripe_payment_intent_id'] ) ? (string) $data['stripe_payment_intent_id'] : null,
			'subtotal_amount'          => (float) ( $data['subtotal_amount'] ?? 0.00 ),
			'processing_fee_amount'    => (float) ( $data['processing_fee_amount'] ?? 0.00 ),
			'total_amount'             => (float) ( $data['total_amount'] ?? 0.00 ),
			'previous_balance_amount'  => (float) ( $data['previous_balance_amount'] ?? 0.00 ),
			'status'                   => (string) ( $data['status'] ?? 'initiated' ),
			'transition_snapshot_json' => (string) ( $data['transition_snapshot_json'] ?? '{}' ),
			'notes'                    => isset( $data['notes'] ) ? (string) $data['notes'] : null,
			'created_at'               => (string) ( $data['created_at'] ?? $now ),
			'updated_at'               => (string) ( $data['updated_at'] ?? $now ),
			'completed_at'             => isset( $data['completed_at'] ) ? (string) $data['completed_at'] : null,
		);

		$result = $wpdb->insert(
			$table_name,
			$payload,
			array(
				'%d',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%f',
				'%f',
				'%f',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a renewal record by ID.
	 *
	 * @param int $renewal_id Renewal ID.
	 * @return array|null
	 */
	public static function get_renewal( int $renewal_id ): ?array {
		global $wpdb;

		$table_name = self::get_table_name();
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE renewal_id = %d LIMIT 1",
				$renewal_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get latest renewal record for a member and season.
	 *
	 * @param int        $member_id Member ID.
	 * @param string     $season_key Season key.
	 * @param string[]|null $statuses Optional status filters.
	 * @return array|null
	 */
	public static function get_latest_by_member_and_season( int $member_id, string $season_key, ?array $statuses = null ): ?array {
		global $wpdb;

		$table_name = self::get_table_name();
		$where      = 'member_id = %d AND season_key = %s';
		$args       = array( $member_id, $season_key );

		if ( is_array( $statuses ) && ! empty( $statuses ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$where       .= " AND status IN ({$placeholders})";
			$args         = array_merge( $args, array_values( $statuses ) );
		}

		$query = "SELECT * FROM {$table_name} WHERE {$where} ORDER BY renewal_id DESC LIMIT 1";
		$row   = $wpdb->get_row(
			$wpdb->prepare( $query, $args ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update renewal record.
	 *
	 * @param int   $renewal_id Renewal ID.
	 * @param array $data       Columns to update.
	 * @return bool
	 */
	public static function update_renewal( int $renewal_id, array $data ): bool {
		global $wpdb;

		if ( empty( $data ) ) {
			return false;
		}

		$table_name        = self::get_table_name();
		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'renewal_id' => $renewal_id ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Create an initiated renewal intent row.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $season_key Season key.
	 * @param int    $old_membership_type_id Current membership type ID.
	 * @param int    $new_membership_type_id Target membership type ID.
	 * @param string $payment_method Payment method.
	 * @param array  $quote Calculated quote.
	 * @param string $snapshot_json Transition snapshot JSON.
	 * @return int|false
	 */
	public static function create_intent_record(
		int $member_id,
		string $season_key,
		int $old_membership_type_id,
		int $new_membership_type_id,
		string $payment_method,
		array $quote,
		string $snapshot_json
	): int|false {
		return self::create_renewal(
			array(
				'member_id'              => $member_id,
				'season_key'             => $season_key,
				'old_membership_type_id' => $old_membership_type_id,
				'new_membership_type_id' => $new_membership_type_id,
				'payment_method'         => $payment_method,
				'subtotal_amount'        => (float) ( $quote['subtotal'] ?? 0.00 ),
				'processing_fee_amount'  => (float) ( $quote['processing_fee'] ?? 0.00 ),
				'total_amount'           => (float) ( $quote['total'] ?? 0.00 ),
				'previous_balance_amount' => (float) ( $quote['previous_balance_amount'] ?? 0.00 ),
				'status'                 => self::STATUS_INITIATED,
				'transition_snapshot_json' => $snapshot_json,
			)
		);
	}

	/**
	 * Mark an initiated renewal as pending offline payment.
	 *
	 * @param int         $renewal_id Renewal ID.
	 * @param string|null $notes Optional notes.
	 * @return bool
	 */
	public static function mark_pending_payment( int $renewal_id, ?string $notes = null ): bool {
		$extra_data = array();

		if ( null !== $notes && '' !== trim( $notes ) ) {
			$extra_data['notes'] = sanitize_text_field( $notes );
		}

		return self::transition_status(
			$renewal_id,
			array( self::STATUS_INITIATED ),
			self::STATUS_PENDING_PAYMENT,
			$extra_data
		);
	}

	/**
	 * Get statuses that block duplicate renewals in a season.
	 *
	 * @return string[]
	 */
	public static function get_blocking_statuses(): array {
		return array(
			self::STATUS_INITIATED,
			self::STATUS_PENDING_PAYMENT,
			self::STATUS_COMPLETED,
		);
	}

	/**
	 * Find an idempotency-blocking renewal for a member and season.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $season_key Season key.
	 * @return array|null
	 */
	public static function find_idempotent_renewal( int $member_id, string $season_key ): ?array {
		return self::get_latest_by_member_and_season(
			$member_id,
			$season_key,
			self::get_blocking_statuses()
		);
	}

	/**
	 * Compute season-level renewal eligibility.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $season_key Season key.
	 * @return array{eligible:bool,reason:string,existing_renewal:?array}
	 */
	public static function get_eligibility( int $member_id, string $season_key ): array {
		$existing = self::find_idempotent_renewal( $member_id, $season_key );

		if ( empty( $existing ) ) {
			return array(
				'eligible'         => true,
				'reason'           => 'eligible',
				'existing_renewal' => null,
			);
		}

		$status = (string) ( $existing['status'] ?? '' );
		$reason = self::STATUS_COMPLETED === $status
			? 'already_completed'
			: 'already_in_progress';

		return array(
			'eligible'         => false,
			'reason'           => $reason,
			'existing_renewal' => $existing,
		);
	}

	/**
	 * Transition a renewal status if current status is allowed.
	 *
	 * @param int      $renewal_id Renewal ID.
	 * @param string[] $from_statuses Allowed current statuses.
	 * @param string   $to_status Destination status.
	 * @param array    $extra_data Additional columns to update.
	 * @return bool
	 */
	public static function transition_status( int $renewal_id, array $from_statuses, string $to_status, array $extra_data = array() ): bool {
		global $wpdb;

		if ( empty( $from_statuses ) ) {
			return false;
		}

		$table_name   = self::get_table_name();
		$now          = current_time( 'mysql' );
		$set_clauses  = array( 'status = %s', 'updated_at = %s' );
		$set_values   = array( $to_status, $now );
		$where_values = array( $renewal_id );

		if ( self::STATUS_COMPLETED === $to_status && empty( $extra_data['completed_at'] ) ) {
			$set_clauses[] = 'completed_at = %s';
			$set_values[]  = $now;
		}

		foreach ( $extra_data as $column => $value ) {
			if ( in_array( $column, array( 'status', 'updated_at' ), true ) ) {
				continue;
			}

			$set_clauses[] = "{$column} = %s";
			$set_values[]  = is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value );
		}

		$in_placeholders = implode( ', ', array_fill( 0, count( $from_statuses ), '%s' ) );
		$sql             = "UPDATE {$table_name} SET " . implode( ', ', $set_clauses ) . " WHERE renewal_id = %d AND status IN ({$in_placeholders})";
		$values          = array_merge( $set_values, $where_values, array_values( $from_statuses ) );
		$updated         = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return is_numeric( $updated ) && (int) $updated > 0;
	}
}

