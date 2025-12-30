<?php

/**
 * Access code database operations class
 *
 * Handles all database operations for access codes table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Access code database operations class.
 *
 * Provides CRUD methods for access code records.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Access_Code_DB {

	/**
	 * Create a new access code.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Array with access code fields (code, description, expires_at, is_active, is_premium)
	 * @return   int|false         Access code ID on success, false on failure
	 */
	public static function create_access_code( array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_access_codes';

		// Validate required field
		if ( empty( $data['code'] ) ) {
			return false;
		}

		// Set defaults
		if ( ! isset( $data['is_active'] ) ) {
			$data['is_active'] = 1;
		}
		if ( ! isset( $data['is_premium'] ) ) {
			$data['is_premium'] = 0;
		}

		// Set timestamps
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		// Define format strings
		$formats = array(
			'code'       => '%s',
			'description' => '%s',
			'expires_at' => '%s',
			'is_active'  => '%d',
			'is_premium' => '%d',
			'created_at' => '%s',
			'updated_at' => '%s',
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
	 * Retrieve all access codes.
	 *
	 * @since    1.0.0
	 * @return   array    Array of access code arrays
	 */
	public static function get_access_codes(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_access_codes';

		$results = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY created_at DESC",
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Retrieve active access codes (not expired and is_active = 1).
	 *
	 * @since    1.0.0
	 * @param    bool|null $is_premium Filter by premium status. Null returns all.
	 * @return   array    Array of active access code arrays
	 */
	public static function get_active_access_codes( ?bool $is_premium = null ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_access_codes';
		$now        = current_time( 'mysql' );

		$where_clauses = array(
			'is_active = %d',
			'(expires_at IS NULL OR expires_at >= %s)',
		);
		$where_values  = array(
			1,
			$now,
		);

		if ( null !== $is_premium ) {
			$where_clauses[] = 'COALESCE(is_premium, 0) = %d';
			$where_values[]  = $is_premium ? 1 : 0;
		}

		$query = "SELECT * FROM {$table_name} WHERE " . implode( ' AND ', $where_clauses ) . ' ORDER BY created_at DESC';

		$prepared_query = $wpdb->prepare( $query, $where_values );

		$results = $wpdb->get_results( $prepared_query, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Update access code record.
	 *
	 * @since    1.0.0
	 * @param    int      $code_id    Access code ID
	 * @param    array    $data       Fields to update
	 * @return   bool                 True on success, false on failure
	 */
	public static function update_access_code( int $code_id, array $data ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_access_codes';

		// Always update the updated_at timestamp
		$data['updated_at'] = current_time( 'mysql' );

		// Define format strings
		$formats = array(
			'code'       => '%s',
			'description' => '%s',
			'expires_at' => '%s',
			'is_active'  => '%d',
			'is_premium' => '%d',
			'updated_at' => '%s',
		);

		// Build format array
		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'code_id' => $code_id ),
			$format_array,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete access code record.
	 *
	 * @since    1.0.0
	 * @param    int    $code_id    Access code ID
	 * @return   bool               True on success, false on failure
	 */
	public static function delete_access_code( int $code_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_access_codes';

		$result = $wpdb->delete(
			$table_name,
			array( 'code_id' => $code_id ),
			array( '%d' )
		);

		return false !== $result;
	}
}

