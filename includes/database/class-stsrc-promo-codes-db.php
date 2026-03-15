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

		$discount_values = self::normalize_discount_values( $data['discount_values'] ?? null );
		if ( null === $discount_values ) {
			return new WP_Error( 'missing_discount_values', __( 'At least one membership type must have a discount value.', 'smoketree-plugin' ) );
		}

		$now = current_time( 'mysql' );

		$insert_data = array(
			'code_name'       => sanitize_text_field( $data['code_name'] ),
			'discount_type'   => sanitize_text_field( $data['discount_type'] ),
			'discount_values' => $discount_values,
			'expires_at'      => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
			'is_one_time_use' => isset( $data['is_one_time_use'] ) ? (int) $data['is_one_time_use'] : 0,
			'usage_limit'     => isset( $data['usage_limit'] ) && '' !== $data['usage_limit'] ? (int) $data['usage_limit'] : null,
			'usage_count'     => isset( $data['usage_count'] ) ? (int) $data['usage_count'] : 0,
			'is_active'       => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		$insert_format = array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
			'%d',
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
		if ( array_key_exists( 'discount_values', $data ) ) {
			$normalized = self::normalize_discount_values( $data['discount_values'] );
			if ( null === $normalized ) {
				return new WP_Error( 'missing_discount_values', __( 'At least one membership type must have a discount value.', 'smoketree-plugin' ) );
			}
			$update['discount_values'] = $normalized;
			$formats[]                 = '%s';
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
				pc.discount_values,
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
	 * Normalize per-membership-type discount values as a JSON object.
	 *
	 * Accepts an associative array or JSON string mapping membership_type_id
	 * to discount value.  Returns null when no valid entries remain.
	 *
	 * @since    1.5.0
	 * @param    mixed $discount_values Raw discount values input.
	 * @return   string|null
	 */
	private static function normalize_discount_values( mixed $discount_values ): ?string {
		if ( null === $discount_values || '' === $discount_values ) {
			return null;
		}

		if ( is_string( $discount_values ) ) {
			$decoded = json_decode( $discount_values, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$discount_values = $decoded;
			} else {
				return null;
			}
		}

		if ( ! is_array( $discount_values ) ) {
			return null;
		}

		$cleaned = array();
		foreach ( $discount_values as $type_id => $value ) {
			$type_id = absint( $type_id );
			$value   = round( (float) $value, 2 );
			if ( $type_id > 0 && $value > 0 ) {
				$cleaned[ (string) $type_id ] = $value;
			}
		}

		if ( empty( $cleaned ) ) {
			return null;
		}

		return wp_json_encode( $cleaned );
	}
}

