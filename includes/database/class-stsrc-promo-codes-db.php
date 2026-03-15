<?php

/**
 * Promo codes database operations class.
 *
 * Handles CRUD and reporting operations for promo codes and usage records.
 *
 * @link       https://smoketree.us
 * @since      1.4.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */
class STSRC_Promo_Codes_DB {

	/**
	 * Create a promo code.
	 *
	 * @since    1.4.0
	 * @param    array $data Promo code data.
	 * @return   int|WP_Error
	 */
	public static function create_code( array $data ): int|WP_Error {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_promo_codes';

		if ( empty( $data['code_name'] ) ) {
			return new WP_Error( 'missing_code_name', __( 'Promo code name is required.', 'smoketree-plugin' ) );
		}

		if ( empty( $data['discount_type'] ) || ! in_array( $data['discount_type'], array( 'flat', 'percentage' ), true ) ) {
			return new WP_Error( 'invalid_discount_type', __( 'Discount type must be flat or percentage.', 'smoketree-plugin' ) );
		}

		if ( ! isset( $data['discount_value'] ) ) {
			return new WP_Error( 'missing_discount_value', __( 'Discount value is required.', 'smoketree-plugin' ) );
		}

		$now = current_time( 'mysql' );

		$insert_data = array(
			'code_name'        => sanitize_text_field( $data['code_name'] ),
			'discount_type'    => sanitize_text_field( $data['discount_type'] ),
			'discount_value'   => (float) $data['discount_value'],
			'expires_at'       => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
			'is_one_time_use'  => isset( $data['is_one_time_use'] ) ? (int) $data['is_one_time_use'] : 0,
			'usage_limit'      => isset( $data['usage_limit'] ) && '' !== $data['usage_limit'] ? (int) $data['usage_limit'] : null,
			'usage_count'      => isset( $data['usage_count'] ) ? (int) $data['usage_count'] : 0,
			'allowed_type_ids' => self::normalize_allowed_type_ids( $data['allowed_type_ids'] ?? null ),
			'is_active'        => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$insert_format = array(
			'%s',
			'%s',
			'%f',
			'%s',
			'%d',
			'%d',
			'%d',
			'%s',
			'%d',
			'%s',
			'%s',
		);

		$result = $wpdb->insert( $table_name, $insert_data, $insert_format );

		if ( false === $result ) {
			return new WP_Error( 'promo_code_create_failed', $wpdb->last_error ?: __( 'Failed to create promo code.', 'smoketree-plugin' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a promo code.
	 *
	 * @since    1.4.0
	 * @param    int   $code_id Promo code ID.
	 * @param    array $data    Promo code data to update.
	 * @return   bool|WP_Error
	 */
	public static function update_code( int $code_id, array $data ): bool|WP_Error {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_promo_codes';
		$update     = array();
		$formats    = array();

		if ( isset( $data['code_name'] ) ) {
			$update['code_name'] = sanitize_text_field( $data['code_name'] );
			$formats[]           = '%s';
		}
		if ( isset( $data['discount_type'] ) ) {
			if ( ! in_array( $data['discount_type'], array( 'flat', 'percentage' ), true ) ) {
				return new WP_Error( 'invalid_discount_type', __( 'Discount type must be flat or percentage.', 'smoketree-plugin' ) );
			}
			$update['discount_type'] = sanitize_text_field( $data['discount_type'] );
			$formats[]               = '%s';
		}
		if ( isset( $data['discount_value'] ) ) {
			$update['discount_value'] = (float) $data['discount_value'];
			$formats[]                = '%f';
		}
		if ( array_key_exists( 'expires_at', $data ) ) {
			$update['expires_at'] = ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null;
			$formats[]            = '%s';
		}
		if ( isset( $data['is_one_time_use'] ) ) {
			$update['is_one_time_use'] = (int) $data['is_one_time_use'];
			$formats[]                 = '%d';
		}
		if ( array_key_exists( 'usage_limit', $data ) ) {
			$update['usage_limit'] = '' !== (string) $data['usage_limit'] ? (int) $data['usage_limit'] : null;
			$formats[]             = '%d';
		}
		if ( isset( $data['usage_count'] ) ) {
			$update['usage_count'] = (int) $data['usage_count'];
			$formats[]             = '%d';
		}
		if ( array_key_exists( 'allowed_type_ids', $data ) ) {
			$update['allowed_type_ids'] = self::normalize_allowed_type_ids( $data['allowed_type_ids'] );
			$formats[]                  = '%s';
		}
		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) $data['is_active'];
			$formats[]           = '%d';
		}

		$update['updated_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$result = $wpdb->update(
			$table_name,
			$update,
			array( 'code_id' => $code_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'promo_code_update_failed', $wpdb->last_error ?: __( 'Failed to update promo code.', 'smoketree-plugin' ) );
		}

		return true;
	}

	/**
	 * Soft-delete a promo code.
	 *
	 * @since    1.4.0
	 * @param    int $code_id Promo code ID.
	 * @return   bool
	 */
	public static function soft_delete_code( int $code_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_promo_codes';

		$result = $wpdb->update(
			$table_name,
			array(
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'code_id' => $code_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get promo code by name.
	 *
	 * @since    1.4.0
	 * @param    string $name Promo code name.
	 * @return   object|null
	 */
	public static function get_code_by_name( string $name ): ?object {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_promo_codes';
		$code_name  = sanitize_text_field( $name );

		$query = $wpdb->prepare(
			"SELECT * FROM {$table_name}
			WHERE UPPER(code_name) = UPPER(%s)
			AND deleted_at IS NULL
			LIMIT 1",
			$code_name
		);

		$result = $wpdb->get_row( $query );

		return $result ?: null;
	}

	/**
	 * Get all promo codes with optional filters.
	 *
	 * @since    1.4.0
	 * @param    array $filters Query filters.
	 * @return   array
	 */
	public static function get_all_codes( array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_promo_codes';

		$where   = array( 'deleted_at IS NULL' );
		$params  = array();
		$per_page = isset( $filters['per_page'] ) ? max( 1, (int) $filters['per_page'] ) : 20;
		$page     = isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		if ( isset( $filters['is_active'] ) && '' !== (string) $filters['is_active'] ) {
			$where[]  = 'is_active = %d';
			$params[] = (int) $filters['is_active'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[]  = 'code_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[]  = $per_page;
		$params[]  = $offset;

		$prepared = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $prepared );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Increment promo code usage count atomically.
	 *
	 * @since    1.4.0
	 * @param    int $code_id Promo code ID.
	 * @return   void
	 */
	public static function increment_usage_count( int $code_id ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_promo_codes';
		$query      = $wpdb->prepare(
			"UPDATE {$table_name} SET usage_count = usage_count + 1, updated_at = %s WHERE code_id = %d",
			current_time( 'mysql' ),
			$code_id
		);

		$wpdb->query( $query );
	}

	/**
	 * Record promo code usage for a member.
	 *
	 * @since    1.4.0
	 * @param    int   $code_id          Promo code ID.
	 * @param    int   $member_id        Member ID.
	 * @param    float $discount_amount  Applied discount amount.
	 * @param    int   $type_id          Membership type ID.
	 * @return   int
	 */
	public static function record_usage( int $code_id, int $member_id, float $discount_amount, int $type_id ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_promo_code_usages';

		$result = $wpdb->insert(
			$table_name,
			array(
				'code_id'            => $code_id,
				'member_id'          => $member_id,
				'discount_amount'    => $discount_amount,
				'membership_type_id' => $type_id,
				'used_at'            => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%d', '%s' )
		);

		if ( false === $result ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get promo usage report rows.
	 *
	 * @since    1.4.0
	 * @param    array $filters Query filters.
	 * @return   array
	 */
	public static function get_usage_report( array $filters = array() ): array {
		global $wpdb;

		$promo_codes_table = $wpdb->prefix . 'stsrc_promo_codes';
		$usage_table       = $wpdb->prefix . 'stsrc_promo_code_usages';
		$members_table     = $wpdb->prefix . 'stsrc_members';

		$where    = array( 'pc.deleted_at IS NULL' );
		$params   = array();
		$per_page = isset( $filters['per_page'] ) ? max( 1, (int) $filters['per_page'] ) : 20;
		$page     = isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		if ( ! empty( $filters['code_id'] ) ) {
			$where[]  = 'u.code_id = %d';
			$params[] = (int) $filters['code_id'];
		}

		if ( ! empty( $filters['member_id'] ) ) {
			$where[]  = 'u.member_id = %d';
			$params[] = (int) $filters['member_id'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'u.used_at >= %s';
			$params[] = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'u.used_at <= %s';
			$params[] = sanitize_text_field( $filters['date_to'] );
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT
				u.usage_id,
				u.code_id,
				pc.code_name,
				pc.discount_type,
				pc.discount_value,
				u.member_id,
				CONCAT(m.first_name, ' ', m.last_name) AS member_name,
				u.membership_type_id,
				u.discount_amount,
				u.used_at
			FROM {$usage_table} u
			INNER JOIN {$promo_codes_table} pc ON pc.code_id = u.code_id
			INNER JOIN {$members_table} m ON m.member_id = u.member_id
			WHERE {$where_sql}
			ORDER BY u.used_at DESC
			LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		$prepared = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $prepared );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Normalize membership type restrictions as JSON text.
	 *
	 * @since    1.4.0
	 * @param    mixed $allowed_type_ids Raw allowed type IDs input.
	 * @return   string|null
	 */
	private static function normalize_allowed_type_ids( mixed $allowed_type_ids ): ?string {
		if ( null === $allowed_type_ids || '' === $allowed_type_ids ) {
			return null;
		}

		if ( is_string( $allowed_type_ids ) ) {
			$decoded = json_decode( $allowed_type_ids, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$allowed_type_ids = $decoded;
			} else {
				$allowed_type_ids = array_filter( array_map( 'trim', explode( ',', $allowed_type_ids ) ) );
			}
		}

		if ( ! is_array( $allowed_type_ids ) ) {
			return null;
		}

		$allowed_type_ids = array_values(
			array_filter(
				array_map( 'absint', $allowed_type_ids ),
				static fn ( int $value ): bool => $value > 0
			)
		);

		if ( empty( $allowed_type_ids ) ) {
			return null;
		}

		return wp_json_encode( $allowed_type_ids );
	}
}

