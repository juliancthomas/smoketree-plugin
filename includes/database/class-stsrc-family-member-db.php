<?php

/**
 * Family member database operations class
 *
 * Handles all database operations for family members table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Family member database operations class.
 *
 * Provides CRUD methods for family member records.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Family_Member_DB {

	/**
	 * Add a family member to a member account.
	 *
	 * @since    1.0.0
	 * @param    int      $member_id    Member ID
	 * @param    array    $data         Array with family member fields (first_name, last_name, email)
	 * @return   int|false              Family member ID on success, false on failure
	 */
	public static function add_family_member( int $member_id, array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';

		// Validate required fields
		if ( empty( $data['first_name'] ) || empty( $data['last_name'] ) ) {
			return false;
		}

		// Check for duplicate name (unique constraint: member_id, first_name, last_name)
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} 
				WHERE member_id = %d 
				AND first_name = %s 
				AND last_name = %s",
				$member_id,
				$data['first_name'],
				$data['last_name']
			)
		);

		if ( $existing > 0 ) {
			return false; // Duplicate name
		}

		// Set timestamps and member_id
		$data['member_id']  = $member_id;
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		// Define format strings
		$formats = array(
			'member_id'  => '%d',
			'first_name' => '%s',
			'last_name'  => '%s',
			'email'      => '%s',
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
	 * Retrieve all family members for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   array                Array of family member arrays
	 */
	public static function get_family_members( int $member_id ): array {
		return self::get_by_member_id( $member_id );
	}

	/**
	 * Retrieve family members for a member.
	 *
	 * Defaults to active-only records.
	 *
	 * @since    1.2.0
	 * @param    int   $member_id         Member ID.
	 * @param    bool  $include_deleted   Whether to include deleted records.
	 * @return   array                    Array of family member arrays.
	 */
	public static function get_by_member_id( int $member_id, bool $include_deleted = false ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';
		$status_sql = ( ! $include_deleted && self::has_status_column() ) ? " AND status = 'active'" : '';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE member_id = %d{$status_sql} ORDER BY created_at ASC",
				$member_id
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Retrieve all family members including deleted records.
	 *
	 * @since    1.2.0
	 * @param    int    $member_id    Member ID.
	 * @return   array                Array of family member arrays.
	 */
	public static function get_all_by_member_id_including_deleted( int $member_id ): array {
		return self::get_by_member_id( $member_id, true );
	}

	/**
	 * Retrieve deleted family members for a member.
	 *
	 * @since    1.2.0
	 * @param    int    $member_id    Member ID.
	 * @return   array                Array of deleted family member arrays.
	 */
	public static function get_deleted_by_member_id( int $member_id ): array {
		global $wpdb;

		if ( ! self::has_status_column() ) {
			return array();
		}

		$table_name = $wpdb->prefix . 'stsrc_family_members';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE member_id = %d AND status = 'deleted' ORDER BY created_at ASC",
				$member_id
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Retrieve a single family member by ID.
	 *
	 * Defaults to active-only records.
	 *
	 * @since    1.2.0
	 * @param    int   $family_member_id  Family member ID.
	 * @param    bool  $include_deleted   Whether to include deleted records.
	 * @return   array|null               Family member row as array, null if not found.
	 */
	public static function get_by_id( int $family_member_id, bool $include_deleted = false ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';
		$status_sql = ( ! $include_deleted && self::has_status_column() ) ? " AND status = 'active'" : '';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE family_member_id = %d{$status_sql} LIMIT 1",
				$family_member_id
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Update family member record.
	 *
	 * @since    1.0.0
	 * @param    int      $family_member_id    Family member ID
	 * @param    array    $data                Fields to update
	 * @return   bool                          True on success, false on failure
	 */
	public static function update_family_member( int $family_member_id, array $data ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';

		// Always update the updated_at timestamp
		$data['updated_at'] = current_time( 'mysql' );

		// If updating name, check for duplicates
		if ( isset( $data['first_name'] ) || isset( $data['last_name'] ) ) {
			// Get current member_id and name
			$current = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT member_id, first_name, last_name FROM {$table_name} WHERE family_member_id = %d",
					$family_member_id
				),
				ARRAY_A
			);

			if ( $current ) {
				$first_name = $data['first_name'] ?? $current['first_name'];
				$last_name  = $data['last_name'] ?? $current['last_name'];
				$member_id  = $current['member_id'];

				// Check for duplicate (excluding current record)
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table_name} 
						WHERE member_id = %d 
						AND first_name = %s 
						AND last_name = %s 
						AND family_member_id != %d",
						$member_id,
						$first_name,
						$last_name,
						$family_member_id
					)
				);

				if ( $existing > 0 ) {
					return false; // Duplicate name
				}
			}
		}

		// Define format strings
		$formats = array(
			'member_id'  => '%d',
			'first_name' => '%s',
			'last_name'  => '%s',
			'email'      => '%s',
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
			array( 'family_member_id' => $family_member_id ),
			$format_array,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete family member record.
	 *
	 * @since    1.0.0
	 * @param    int    $family_member_id    Family member ID
	 * @return   bool                        True on success, false on failure
	 */
	public static function delete_family_member( int $family_member_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';

		$result = $wpdb->delete(
			$table_name,
			array( 'family_member_id' => $family_member_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Soft-delete all family members for a member.
	 *
	 * @since    1.2.0
	 * @param    int    $member_id    Member ID.
	 * @return   int                  Number of rows updated.
	 */
	public static function soft_delete_by_member_id( int $member_id ): int {
		global $wpdb;

		if ( ! self::has_status_column() ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'stsrc_family_members';
		$result     = $wpdb->update(
			$table_name,
			array(
				'status'     => 'deleted',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'member_id' => $member_id,
				'status'    => 'active',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Restore a deleted family member to active status.
	 *
	 * @since    1.2.0
	 * @param    int    $family_member_id    Family member ID.
	 * @return   bool                        True on success, false on failure.
	 */
	public static function restore( int $family_member_id ): bool {
		global $wpdb;

		if ( ! self::has_status_column() ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'stsrc_family_members';
		$result     = $wpdb->update(
			$table_name,
			array(
				'status'     => 'active',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'family_member_id' => $family_member_id,
				'status'           => 'deleted',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Count family members for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   int                  Count of family members
	 */
	public static function count_family_members( int $member_id ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';
		$status_sql = self::has_status_column() ? " AND status = 'active'" : '';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE member_id = %d{$status_sql}",
				$member_id
			)
		);

		return (int) $count;
	}

	/**
	 * Get active family member IDs for a member.
	 *
	 * @param int $member_id Member ID.
	 * @return int[]
	 */
	public static function get_active_ids_by_member( int $member_id ): array {
		$rows = self::get_by_member_id( $member_id, false );

		return array_values(
			array_map(
				'absint',
				array_column( $rows, 'family_member_id' )
			)
		);
	}

	/**
	 * Check that all provided family member IDs belong to the member.
	 *
	 * @param int   $member_id Member ID.
	 * @param int[] $family_member_ids Family member IDs.
	 * @return bool
	 */
	public static function member_owns_ids( int $member_id, array $family_member_ids ): bool {
		$family_member_ids = array_values( array_unique( array_map( 'absint', $family_member_ids ) ) );
		$family_member_ids = array_filter( $family_member_ids );

		if ( empty( $family_member_ids ) ) {
			return true;
		}

		$active_ids = self::get_active_ids_by_member( $member_id );

		foreach ( $family_member_ids as $family_member_id ) {
			if ( ! in_array( $family_member_id, $active_ids, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Soft-delete a subset of active family members for a member.
	 *
	 * @param int   $member_id Member ID.
	 * @param int[] $family_member_ids Family member IDs.
	 * @return int Number of rows updated.
	 */
	public static function soft_delete_member_ids( int $member_id, array $family_member_ids ): int {
		global $wpdb;

		if ( ! self::has_status_column() ) {
			return 0;
		}

		$family_member_ids = array_values( array_unique( array_map( 'absint', $family_member_ids ) ) );
		$family_member_ids = array_filter( $family_member_ids );

		if ( empty( $family_member_ids ) ) {
			return 0;
		}

		$table_name    = $wpdb->prefix . 'stsrc_family_members';
		$placeholders  = implode( ', ', array_fill( 0, count( $family_member_ids ), '%d' ) );
		$query         = "UPDATE {$table_name}
			SET status = 'deleted', updated_at = %s
			WHERE member_id = %d
			AND status = 'active'
			AND family_member_id IN ({$placeholders})";
		$query_values  = array_merge(
			array( current_time( 'mysql' ), $member_id ),
			$family_member_ids
		);
		$updated       = $wpdb->query( $wpdb->prepare( $query, $query_values ) );

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Check whether the family members table has a status column.
	 *
	 * @since    1.2.0
	 * @return   bool
	 */
	private static function has_status_column(): bool {
		global $wpdb;

		static $has_status = null;

		if ( null !== $has_status ) {
			return $has_status;
		}

		$table_name = $wpdb->prefix . 'stsrc_family_members';
		$column     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COLUMN_NAME
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND COLUMN_NAME = 'status'
				LIMIT 1",
				DB_NAME,
				$table_name
			)
		);

		$has_status = ! empty( $column );
		return $has_status;
	}
}

