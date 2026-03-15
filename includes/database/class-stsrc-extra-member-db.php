<?php

/**
 * Extra member database operations class
 *
 * Handles all database operations for extra members table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Extra member database operations class.
 *
 * Provides CRUD methods for extra member records.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Extra_Member_DB {
	public const MAX_HOUSEHOLD_EXTRAS = 3;

	/**
	 * Add an extra member to a member account.
	 *
	 * @since    1.0.0
	 * @param    int      $member_id    Member ID
	 * @param    array    $data         Array with extra member fields (first_name, last_name, email, payment_status, stripe_payment_intent_id)
	 * @return   int|false               Extra member ID on success, false on failure
	 */
	public static function add_extra_member( int $member_id, array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_extra_members';

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

		// Set defaults
		if ( ! isset( $data['payment_status'] ) ) {
			$data['payment_status'] = 'pending';
		}

		// Set timestamps and member_id
		$data['member_id']  = $member_id;
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		// Define format strings
		$formats = array(
			'member_id'                => '%d',
			'first_name'               => '%s',
			'last_name'                => '%s',
			'email'                    => '%s',
			'payment_status'           => '%s',
			'stripe_payment_intent_id' => '%s',
			'created_at'               => '%s',
			'updated_at'               => '%s',
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
	 * Retrieve all extra members for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   array                Array of extra member arrays
	 */
	public static function get_extra_members( int $member_id ): array {
		return self::get_by_member_id( $member_id );
	}

	/**
	 * Retrieve extra members for a member.
	 *
	 * Defaults to active-only records.
	 *
	 * @since    1.2.0
	 * @param    int   $member_id         Member ID.
	 * @param    bool  $include_deleted   Whether to include deleted records.
	 * @return   array                    Array of extra member arrays.
	 */
	public static function get_by_member_id( int $member_id, bool $include_deleted = false ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_extra_members';
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
	 * Retrieve all extra members including deleted records.
	 *
	 * @since    1.2.0
	 * @param    int    $member_id    Member ID.
	 * @return   array                Array of extra member arrays.
	 */
	public static function get_all_by_member_id_including_deleted( int $member_id ): array {
		return self::get_by_member_id( $member_id, true );
	}

	/**
	 * Retrieve deleted extra members for a member.
	 *
	 * @since    1.2.0
	 * @param    int    $member_id    Member ID.
	 * @return   array                Array of deleted extra member arrays.
	 */
	public static function get_deleted_by_member_id( int $member_id ): array {
		global $wpdb;

		if ( ! self::has_status_column() ) {
			return array();
		}

		$table_name = $wpdb->prefix . 'stsrc_extra_members';

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
	 * Retrieve a single extra member by ID.
	 *
	 * Defaults to active-only records.
	 *
	 * @since    1.2.0
	 * @param    int   $extra_member_id    Extra member ID.
	 * @param    bool  $include_deleted    Whether to include deleted records.
	 * @return   array|null                Extra member row as array, null if not found.
	 */
	public static function get_by_id( int $extra_member_id, bool $include_deleted = false ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_extra_members';
		$status_sql = ( ! $include_deleted && self::has_status_column() ) ? " AND status = 'active'" : '';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE extra_member_id = %d{$status_sql} LIMIT 1",
				$extra_member_id
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Update extra member record.
	 *
	 * @since    1.0.0
	 * @param    int      $extra_member_id    Extra member ID
	 * @param    array    $data              Fields to update
	 * @return   bool                        True on success, false on failure
	 */
	public static function update_extra_member( int $extra_member_id, array $data ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_extra_members';

		// Always update the updated_at timestamp
		$data['updated_at'] = current_time( 'mysql' );

		// If updating name, check for duplicates
		if ( isset( $data['first_name'] ) || isset( $data['last_name'] ) ) {
			// Get current member_id and name
			$current = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT member_id, first_name, last_name FROM {$table_name} WHERE extra_member_id = %d",
					$extra_member_id
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
						AND extra_member_id != %d",
						$member_id,
						$first_name,
						$last_name,
						$extra_member_id
					)
				);

				if ( $existing > 0 ) {
					return false; // Duplicate name
				}
			}
		}

		// Define format strings
		$formats = array(
			'member_id'                => '%d',
			'first_name'               => '%s',
			'last_name'                => '%s',
			'email'                    => '%s',
			'payment_status'           => '%s',
			'stripe_payment_intent_id' => '%s',
			'updated_at'               => '%s',
		);

		// Build format array
		$format_array = array();
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'extra_member_id' => $extra_member_id ),
			$format_array,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete extra member record.
	 *
	 * @since    1.0.0
	 * @param    int    $extra_member_id    Extra member ID
	 * @return   bool                       True on success, false on failure
	 */
	public static function delete_extra_member( int $extra_member_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_extra_members';

		$result = $wpdb->delete(
			$table_name,
			array( 'extra_member_id' => $extra_member_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Soft-delete all extra members for a member.
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

		$table_name = $wpdb->prefix . 'stsrc_extra_members';
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
	 * Restore a deleted extra member to active status.
	 *
	 * @since    1.2.0
	 * @param    int    $extra_member_id    Extra member ID.
	 * @return   bool                       True on success, false on failure.
	 */
	public static function restore( int $extra_member_id ): bool {
		global $wpdb;

		if ( ! self::has_status_column() ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'stsrc_extra_members';
		$result     = $wpdb->update(
			$table_name,
			array(
				'status'     => 'active',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'extra_member_id' => $extra_member_id,
				'status'          => 'deleted',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Count extra members for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   int                  Count of extra members
	 */
	public static function count_extra_members( int $member_id ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_extra_members';
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
	 * Validate selected extra-member count for Household memberships.
	 *
	 * @param int $extra_member_count Number of extras.
	 * @return bool
	 */
	public static function is_valid_household_extra_count( int $extra_member_count ): bool {
		return $extra_member_count >= 0 && $extra_member_count <= self::MAX_HOUSEHOLD_EXTRAS;
	}

	/**
	 * Get active extra member IDs for a member.
	 *
	 * @param int $member_id Member ID.
	 * @return int[]
	 */
	public static function get_active_ids_by_member( int $member_id ): array {
		$rows = self::get_by_member_id( $member_id, false );

		return array_values(
			array_map(
				'absint',
				array_column( $rows, 'extra_member_id' )
			)
		);
	}

	/**
	 * Check that all provided extra member IDs belong to the member.
	 *
	 * @param int   $member_id Member ID.
	 * @param int[] $extra_member_ids Extra member IDs.
	 * @return bool
	 */
	public static function member_owns_ids( int $member_id, array $extra_member_ids ): bool {
		$extra_member_ids = array_values( array_unique( array_map( 'absint', $extra_member_ids ) ) );
		$extra_member_ids = array_filter( $extra_member_ids );

		if ( empty( $extra_member_ids ) ) {
			return true;
		}

		$active_ids = self::get_active_ids_by_member( $member_id );

		foreach ( $extra_member_ids as $extra_member_id ) {
			if ( ! in_array( $extra_member_id, $active_ids, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Soft-delete a subset of active extra members for a member.
	 *
	 * @param int   $member_id Member ID.
	 * @param int[] $extra_member_ids Extra member IDs.
	 * @return int Number of rows updated.
	 */
	public static function soft_delete_member_ids( int $member_id, array $extra_member_ids ): int {
		global $wpdb;

		if ( ! self::has_status_column() ) {
			return 0;
		}

		$extra_member_ids = array_values( array_unique( array_map( 'absint', $extra_member_ids ) ) );
		$extra_member_ids = array_filter( $extra_member_ids );

		if ( empty( $extra_member_ids ) ) {
			return 0;
		}

		$table_name    = $wpdb->prefix . 'stsrc_extra_members';
		$placeholders  = implode( ', ', array_fill( 0, count( $extra_member_ids ), '%d' ) );
		$query         = "UPDATE {$table_name}
			SET status = 'deleted', updated_at = %s
			WHERE member_id = %d
			AND status = 'active'
			AND extra_member_id IN ({$placeholders})";
		$query_values  = array_merge(
			array( current_time( 'mysql' ), $member_id ),
			$extra_member_ids
		);
		$updated       = $wpdb->query( $wpdb->prepare( $query, $query_values ) );

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Apply extra-member retention set for a renewal transition.
	 *
	 * @param int   $member_id Member ID.
	 * @param int[] $retain_ids Extra member IDs to keep active.
	 * @return bool
	 */
	public static function apply_renewal_selection( int $member_id, array $retain_ids ): bool {
		$retain_ids = array_values( array_unique( array_map( 'absint', $retain_ids ) ) );
		$retain_ids = array_filter( $retain_ids );

		if ( ! self::member_owns_ids( $member_id, $retain_ids ) ) {
			return false;
		}

		$active_ids    = self::get_active_ids_by_member( $member_id );
		$to_soft_delete = array_values( array_diff( $active_ids, $retain_ids ) );
		self::soft_delete_member_ids( $member_id, $to_soft_delete );

		return true;
	}

	/**
	 * Check whether the extra members table has a status column.
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

		$table_name = $wpdb->prefix . 'stsrc_extra_members';
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

