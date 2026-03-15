<?php

/**
 * Legacy Family Member Migration Class
 *
 * Handles migration of family members from old wp_smoketree_family_members
 * table to new wp_stsrc_family_members table.
 *
 * @link       https://smoketree.us
 * @since      1.3.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/migration
 */

/**
 * Legacy Family Member Migration Class.
 *
 * Migrates old family member data to new system, mapping parent member IDs
 * via the stsrc_old_member_id user-meta set during primary member migration.
 *
 * @since      1.3.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/migration
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Legacy_Family_Member_Migrator {

	/**
	 * Spam/test member IDs to skip (matches primary migrator).
	 *
	 * @var array
	 */
	private static $spam_member_ids = array( 139, 140, 141, 142, 143, 144 );

	/**
	 * Junk family member record IDs to skip.
	 *
	 * @var array
	 */
	private static $junk_record_ids = array( 75 );

	/**
	 * Build member-ID mapping from old system to new system.
	 *
	 * Uses the stsrc_old_member_id user-meta stored during primary migration.
	 *
	 * @since    1.3.0
	 * @return   array    Associative array: old member ID => new member_id
	 */
	private static function build_member_id_map(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT um.meta_value AS old_id, m.member_id AS new_id
			 FROM {$wpdb->usermeta} um
			 JOIN {$wpdb->prefix}stsrc_members m ON m.user_id = um.user_id
			 WHERE um.meta_key = 'stsrc_old_member_id'",
			ARRAY_A
		);

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row['old_id'] ] = (int) $row['new_id'];
		}

		return $map;
	}

	/**
	 * Split a full name into first_name and last_name on the first space.
	 *
	 * @since    1.3.0
	 * @param    string    $full_name    Full name string
	 * @return   array                   Associative array with first_name and last_name keys
	 */
	private static function split_name( string $full_name ): array {
		$full_name   = trim( $full_name );
		$first_space = strpos( $full_name, ' ' );

		if ( false === $first_space ) {
			return array(
				'first_name' => $full_name,
				'last_name'  => '',
			);
		}

		return array(
			'first_name' => trim( substr( $full_name, 0, $first_space ) ),
			'last_name'  => trim( substr( $full_name, $first_space + 1 ) ),
		);
	}

	/**
	 * Fetch and filter old family member rows.
	 *
	 * @since    1.3.0
	 * @return   array|null    Old rows sorted for processing, or null if old table missing
	 */
	private static function fetch_old_rows(): ?array {
		global $wpdb;

		$old_table = $wpdb->prefix . 'smoketree_family_members';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) !== $old_table ) {
			return null;
		}

		return $wpdb->get_results(
			"SELECT * FROM {$old_table} ORDER BY isDeleted ASC, id ASC",
			ARRAY_A
		);
	}

	/**
	 * Process old rows against the member-ID map and return categorised results.
	 *
	 * Shared logic for both dry_run() and run_migration().
	 *
	 * @since    1.3.0
	 * @param    array    $old_rows        Old family member rows
	 * @param    array    $member_id_map   Old → new member ID mapping
	 * @param    bool     $execute         Whether to actually write rows
	 * @return   array                     Migration results
	 */
	private static function process_rows( array $old_rows, array $member_id_map, bool $execute ): array {
		global $wpdb;

		$new_table = $wpdb->prefix . 'stsrc_family_members';
		$now       = current_time( 'mysql' );
		$seen      = array();

		$results = array(
			'total_old_rows'    => count( $old_rows ),
			'migrated'          => 0,
			'skipped_junk'      => 0,
			'skipped_spam'      => 0,
			'skipped_orphan'    => 0,
			'skipped_unmapped'  => 0,
			'skipped_dup'       => 0,
			'skipped_exists'    => 0,
			'errors'            => 0,
			'error_messages'    => array(),
			'orphan_details'    => array(),
			'unmapped_details'  => array(),
			'single_name_rows'  => array(),
		);

		foreach ( $old_rows as $row ) {
			$old_id            = (int) $row['id'];
			$primary_member_id = (int) $row['primary_member_id'];

			if ( in_array( $old_id, self::$junk_record_ids, true ) ) {
				$results['skipped_junk']++;
				continue;
			}

			if ( in_array( $primary_member_id, self::$spam_member_ids, true ) ) {
				$results['skipped_spam']++;
				continue;
			}

			if ( 0 === $primary_member_id ) {
				$results['skipped_orphan']++;
				$results['orphan_details'][] = sprintf(
					'id=%d  name="%s"  email="%s"  legacy=%d',
					$old_id,
					$row['full_name'],
					$row['email'],
					$row['isLegacy']
				);
				continue;
			}

			$new_member_id = $member_id_map[ $primary_member_id ] ?? null;
			if ( null === $new_member_id ) {
				$results['skipped_unmapped']++;
				$results['unmapped_details'][] = sprintf(
					'id=%d  primary_member_id=%d  name="%s"',
					$old_id,
					$primary_member_id,
					$row['full_name']
				);
				continue;
			}

			$name       = self::split_name( $row['full_name'] );
			$first_name = sanitize_text_field( $name['first_name'] );
			$last_name  = sanitize_text_field( $name['last_name'] );

			if ( '' === $first_name ) {
				$results['skipped_junk']++;
				continue;
			}

			if ( '' === $last_name ) {
				$results['single_name_rows'][] = sprintf(
					'id=%d  "%s" → first="%s" last=""',
					$old_id,
					$row['full_name'],
					$first_name
				);
			}

			$dedup_key = "{$new_member_id}|{$first_name}|{$last_name}";
			if ( isset( $seen[ $dedup_key ] ) ) {
				$results['skipped_dup']++;
				continue;
			}
			$seen[ $dedup_key ] = true;

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$new_table}
					 WHERE member_id = %d AND first_name = %s AND last_name = %s",
					$new_member_id,
					$first_name,
					$last_name
				)
			);
			if ( $exists > 0 ) {
				$results['skipped_exists']++;
				continue;
			}

			$status     = ( 1 === (int) $row['isDeleted'] ) ? 'deleted' : 'active';
			$created_at = ( '0000-00-00 00:00:00' === $row['created_at'] || empty( $row['created_at'] ) )
				? $now
				: $row['created_at'];
			$email      = ( empty( $row['email'] ) || '' === trim( $row['email'] ) )
				? null
				: sanitize_email( $row['email'] );

			if ( ! $execute ) {
				$results['migrated']++;
				continue;
			}

			// Use raw query for proper NULL email handling.
			if ( null === $email ) {
				$insert_result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$new_table}
						 (member_id, first_name, last_name, email, status, created_at, updated_at)
						 VALUES (%d, %s, %s, NULL, %s, %s, %s)",
						$new_member_id,
						$first_name,
						$last_name,
						$status,
						$created_at,
						$now
					)
				);
			} else {
				$insert_result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$new_table}
						 (member_id, first_name, last_name, email, status, created_at, updated_at)
						 VALUES (%d, %s, %s, %s, %s, %s, %s)",
						$new_member_id,
						$first_name,
						$last_name,
						$email,
						$status,
						$created_at,
						$now
					)
				);
			}

			if ( false === $insert_result ) {
				$results['errors']++;
				$results['error_messages'][] = sprintf(
					'id=%d  member_id=%d  name="%s %s"  DB error: %s',
					$old_id,
					$new_member_id,
					$first_name,
					$last_name,
					$wpdb->last_error
				);
			} else {
				$results['migrated']++;
			}
		}

		return $results;
	}

	/**
	 * Dry run migration to preview results without making changes.
	 *
	 * @since    1.3.0
	 * @return   array    Preview data
	 */
	public static function dry_run(): array {
		$old_rows = self::fetch_old_rows();

		if ( null === $old_rows ) {
			return array( 'error' => 'Old table wp_smoketree_family_members does not exist.' );
		}

		$member_id_map = self::build_member_id_map();

		if ( empty( $member_id_map ) ) {
			return array( 'error' => 'No member-ID mappings found. Run the primary member migration first.' );
		}

		return self::process_rows( $old_rows, $member_id_map, false );
	}

	/**
	 * Run the migration.
	 *
	 * @since    1.3.0
	 * @return   array    Migration results with counts and errors
	 */
	public static function run_migration(): array {
		$old_rows = self::fetch_old_rows();

		if ( null === $old_rows ) {
			return array(
				'migrated'       => 0,
				'errors'         => 1,
				'error_messages' => array( 'Old table wp_smoketree_family_members does not exist.' ),
			);
		}

		$member_id_map = self::build_member_id_map();

		if ( empty( $member_id_map ) ) {
			return array(
				'migrated'       => 0,
				'errors'         => 1,
				'error_messages' => array( 'No member-ID mappings found. Run the primary member migration first.' ),
			);
		}

		return self::process_rows( $old_rows, $member_id_map, true );
	}
}
