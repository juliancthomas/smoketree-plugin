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
}

