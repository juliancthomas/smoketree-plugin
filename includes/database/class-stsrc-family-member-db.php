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
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE member_id = %d ORDER BY created_at ASC",
				$member_id
			),
			ARRAY_A
		);

		return $results ? $results : array();
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
	 * Count family members for a member.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   int                  Count of family members
	 */
	public static function count_family_members( int $member_id ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_family_members';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE member_id = %d",
				$member_id
			)
		);

		return (int) $count;
	}
}

