<?php

/**
 * Affiliate referrals database operations class.
 *
 * Handles writes and reporting for affiliate referral usage and payout status.
 *
 * @link       https://smoketree.us
 * @since      1.4.2
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */
class STSRC_Affiliate_Referrals_DB {

	/**
	 * Create a referral record.
	 *
	 * @since    1.4.2
	 * @param    array $data Referral data payload.
	 * @return   int|WP_Error
	 */
	public static function create_referral( array $data ): int|WP_Error {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_affiliate_referrals';

		$required = array(
			'referral_code',
			'referrer_member_id',
			'new_member_id',
			'new_member_discount',
			'referrer_credit',
		);

		foreach ( $required as $key ) {
			if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] ) {
				return new WP_Error( 'missing_referral_field', sprintf( __( 'Missing required referral field: %s', 'smoketree-plugin' ), $key ) );
			}
		}

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'referral_code'      => strtoupper( sanitize_text_field( $data['referral_code'] ) ),
				'referrer_member_id' => (int) $data['referrer_member_id'],
				'new_member_id'      => (int) $data['new_member_id'],
				'new_member_discount'=> (float) $data['new_member_discount'],
				'referrer_credit'    => (float) $data['referrer_credit'],
				'payout_status'      => ! empty( $data['payout_status'] ) ? sanitize_text_field( $data['payout_status'] ) : 'pending',
				'paid_at'            => $data['paid_at'] ?? null,
				'paid_by_user_id'    => $data['paid_by_user_id'] ?? null,
				'referred_at'        => ! empty( $data['referred_at'] ) ? sanitize_text_field( $data['referred_at'] ) : current_time( 'mysql' ),
			),
			array(
				'%s',
				'%d',
				'%d',
				'%f',
				'%f',
				'%s',
				'%s',
				'%d',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'referral_insert_failed', $wpdb->last_error ?: __( 'Failed to create referral.', 'smoketree-plugin' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update payout status for a referral.
	 *
	 * @since    1.4.2
	 * @param    int    $referral_id    Referral ID.
	 * @param    string $status         Target status ("pending" or "paid").
	 * @param    int    $admin_user_id  Admin user performing update.
	 * @return   bool
	 */
	public static function update_payout_status( int $referral_id, string $status, int $admin_user_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_affiliate_referrals';
		$status     = sanitize_text_field( strtolower( $status ) );

		if ( ! in_array( $status, array( 'pending', 'paid' ), true ) ) {
			return false;
		}

		$update_data = array(
			'payout_status'   => $status,
			'paid_at'         => 'paid' === $status ? current_time( 'mysql' ) : null,
			'paid_by_user_id' => 'paid' === $status ? $admin_user_id : null,
		);

		$updated = $wpdb->update(
			$table_name,
			$update_data,
			array( 'referral_id' => $referral_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get referral log rows.
	 *
	 * @since    1.4.2
	 * @param    array $filters Filter array.
	 * @return   array
	 */
	public static function get_referral_log( array $filters = array() ): array {
		global $wpdb;

		$table_name     = $wpdb->prefix . 'stsrc_affiliate_referrals';
		$members_table  = $wpdb->prefix . 'stsrc_members';
		$where_clauses  = array( '1=1' );
		$params         = array();
		$per_page       = isset( $filters['per_page'] ) ? max( 1, (int) $filters['per_page'] ) : 20;
		$page           = isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1;
		$offset         = ( $page - 1 ) * $per_page;

		if ( ! empty( $filters['payout_status'] ) ) {
			$where_clauses[] = 'r.payout_status = %s';
			$params[]        = sanitize_text_field( $filters['payout_status'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'r.referred_at >= %s';
			$params[]        = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'r.referred_at <= %s';
			$params[]        = sanitize_text_field( $filters['date_to'] );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$sql       = "SELECT
				r.referral_id,
				r.referral_code,
				r.referrer_member_id,
				CONCAT(referrer.first_name, ' ', referrer.last_name) AS referrer_name,
				r.new_member_id,
				CONCAT(new_member.first_name, ' ', new_member.last_name) AS new_member_name,
				r.new_member_discount,
				r.referrer_credit,
				r.payout_status,
				r.paid_at,
				r.paid_by_user_id,
				r.referred_at
			FROM {$table_name} r
			INNER JOIN {$members_table} referrer ON referrer.member_id = r.referrer_member_id
			INNER JOIN {$members_table} new_member ON new_member.member_id = r.new_member_id
			WHERE {$where_sql}
			ORDER BY r.referred_at DESC
			LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		$prepared = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $prepared );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Lookup referral by new member ID for idempotency checks.
	 *
	 * @since    1.4.2
	 * @param    int $member_id New member ID.
	 * @return   object|null
	 */
	public static function get_by_new_member_id( int $member_id ): ?object {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_affiliate_referrals';
		$query      = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE new_member_id = %d LIMIT 1",
			$member_id
		);

		$result = $wpdb->get_row( $query );

		return $result ?: null;
	}
}

