<?php

/**
 * Membership type database operations class
 *
 * Handles all database operations for membership types table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Membership type database operations class.
 *
 * Provides CRUD methods for membership type records.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Membership_DB {

	/**
	 * Create a new membership type record.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Array with membership type fields
	 * @return   int|false         Membership type ID on success, false on failure
	 */
	public static function create_membership_type( array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_membership_types';

		// Set timestamps
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		// Convert benefits array to JSON if it's an array
		if ( isset( $data['benefits'] ) && is_array( $data['benefits'] ) ) {
			$data['benefits'] = wp_json_encode( $data['benefits'] );
		}

		// Define format strings for each field
		$formats = array(
			'name'                      => '%s',
			'description'               => '%s',
			'price'                     => '%f',
			'expiration_period'         => '%d',
			'stripe_product_id'         => '%s',
			'is_selectable'             => '%d',
			'is_best_seller'            => '%d',
			'can_have_additional_members' => '%d',
			'benefits'                  => '%s',
			'created_at'                => '%s',
			'updated_at'                => '%s',
		);

		// Build format array in the same order as $data
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
	 * Retrieve membership type by ID.
	 *
	 * @since    1.0.0
	 * @param    int    $id    Membership type ID
	 * @return   array|null    Membership type array or null if not found
	 */
	public static function get_membership_type( int $id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_membership_types';

		$membership_type = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE membership_type_id = %d",
				$id
			),
			ARRAY_A
		);

		if ( null === $membership_type ) {
			return null;
		}

		// Decode JSON benefits if present
		if ( isset( $membership_type['benefits'] ) && ! empty( $membership_type['benefits'] ) ) {
			$decoded = json_decode( $membership_type['benefits'], true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$membership_type['benefits'] = $decoded;
			}
		}

		return $membership_type;
	}

	/**
	 * Retrieve all membership types.
	 *
	 * @since    1.0.0
	 * @param    bool    $selectable_only    If true, only return selectable membership types
	 * @return   array                       Array of membership type arrays
	 */
	public static function get_all_membership_types( bool $selectable_only = false ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_membership_types';

		$query = "SELECT * FROM {$table_name}";

		if ( $selectable_only ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE is_selectable = %d ORDER BY name ASC",
				1
			);
		} else {
			$query .= ' ORDER BY name ASC';
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! $results ) {
			return array();
		}

		// Decode JSON benefits for each membership type
		foreach ( $results as &$membership_type ) {
			if ( isset( $membership_type['benefits'] ) && ! empty( $membership_type['benefits'] ) ) {
				$decoded = json_decode( $membership_type['benefits'], true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$membership_type['benefits'] = $decoded;
				}
			}
		}

		return $results;
	}

	/**
	 * Update membership type record.
	 *
	 * @since    1.0.0
	 * @param    int      $id      Membership type ID
	 * @param    array    $data    Fields to update
	 * @return   bool              True on success, false on failure
	 */
	public static function update_membership_type( int $id, array $data ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_membership_types';

		// Always update the updated_at timestamp
		$data['updated_at'] = current_time( 'mysql' );

		// Convert benefits array to JSON if it's an array
		if ( isset( $data['benefits'] ) && is_array( $data['benefits'] ) ) {
			$data['benefits'] = wp_json_encode( $data['benefits'] );
		}

		// Define format strings
		$formats = array(
			'name'                      => '%s',
			'description'               => '%s',
			'price'                     => '%f',
			'expiration_period'         => '%d',
			'stripe_product_id'         => '%s',
			'is_selectable'             => '%d',
			'is_best_seller'            => '%d',
			'can_have_additional_members' => '%d',
			'benefits'                  => '%s',
			'updated_at'                => '%s',
		);

		// Build format array
		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'membership_type_id' => $id ),
			$format_array,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete membership type record.
	 *
	 * @since    1.0.0
	 * @param    int    $id    Membership type ID
	 * @return   bool          True on success, false on failure
	 */
	public static function delete_membership_type( int $id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_membership_types';

		$result = $wpdb->delete(
			$table_name,
			array( 'membership_type_id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}
}

