<?php

/**
 * Legacy Member Migration Class
 *
 * Handles migration of members from old wp_smoketree_members table to new wp_stsrc_members table.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/migration
 */

/**
 * Legacy Member Migration Class.
 *
 * Migrates old member data to new system with password reset tracking.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/migration
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Legacy_Member_Migrator {

	/**
	 * Old membership type ID mapping to new membership type names.
	 *
	 * @var array
	 */
	private static $membership_mapping = array(
		'1146' => 'Household',
		'1164' => 'Duo',
		'1165' => 'Single',
		'1147' => 'Civic',
	);

	/**
	 * Spam/test member IDs to skip (last 6 records).
	 *
	 * @var array
	 */
	private static $spam_ids = array( 139, 140, 141, 142, 143, 144 );

	/**
	 * Run the migration.
	 *
	 * @since    1.0.0
	 * @return   array    Migration results with counts and errors
	 */
	public static function run_migration(): array {
		global $wpdb;

		$results = array(
			'total_processed' => 0,
			'successful'      => 0,
			'skipped'         => 0,
			'errors'          => 0,
			'error_messages'  => array(),
		);

		// Check if old table exists
		$old_table = $wpdb->prefix . 'smoketree_members';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) !== $old_table ) {
			$results['error_messages'][] = 'Old table wp_smoketree_members does not exist.';
			return $results;
		}

		// Get all old members (excluding spam IDs)
		$spam_ids_placeholder = implode( ',', array_fill( 0, count( self::$spam_ids ), '%d' ) );
		$query = "SELECT * FROM {$old_table} WHERE id NOT IN ({$spam_ids_placeholder}) ORDER BY id ASC";
		$old_members = $wpdb->get_results( $wpdb->prepare( $query, ...self::$spam_ids ), ARRAY_A );

		if ( empty( $old_members ) ) {
			$results['error_messages'][] = 'No members found to migrate.';
			return $results;
		}

		// Load required classes
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		// Get membership type mappings
		$membership_types = self::get_membership_type_mappings();

		// Process each old member
		foreach ( $old_members as $old_member ) {
			$results['total_processed']++;

			try {
				// Check if already migrated (by email)
				$existing_member = STSRC_Member_DB::get_member_by_email( $old_member['email'] );
				if ( $existing_member ) {
					$results['skipped']++;
					continue;
				}

				// Migrate the member
				$migrated = self::migrate_member( $old_member, $membership_types );

				if ( $migrated ) {
					$results['successful']++;
				} else {
					$results['errors']++;
					$results['error_messages'][] = sprintf(
						'Failed to migrate member ID %d (%s %s)',
						$old_member['id'],
						$old_member['first_name'],
						$old_member['last_name']
					);
				}
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['error_messages'][] = sprintf(
					'Exception migrating member ID %d: %s',
					$old_member['id'],
					$e->getMessage()
				);
			}
		}

		return $results;
	}

	/**
	 * Migrate a single member from old system to new system.
	 *
	 * @since    1.0.0
	 * @param    array    $old_member          Old member data
	 * @param    array    $membership_types    Membership type ID mappings
	 * @return   bool                          True on success, false on failure
	 */
	private static function migrate_member( array $old_member, array $membership_types ): bool {
		// Map membership type
		$membership_type_id = self::map_membership_type( $old_member['membership_id'], $membership_types );
		if ( ! $membership_type_id ) {
			error_log( sprintf(
				'STSRC Migration: Could not map membership type "%s" for member ID %d',
				$old_member['membership_id'],
				$old_member['id']
			) );
			return false;
		}

		// Map status
		$status = self::map_status( $old_member );

		// Create WordPress user
		$user_id = self::create_wordpress_user( $old_member );
		if ( ! $user_id ) {
			return false;
		}

		// Create member record
		$member_data = array(
			'user_id'              => $user_id,
			'membership_type_id'   => $membership_type_id,
			'status'               => $status,
			'payment_type'         => self::normalize_payment_type( $old_member['payment_type'] ),
			'stripe_customer_id'   => $old_member['stripe_customer_id'] ?: null,
			'first_name'           => sanitize_text_field( $old_member['first_name'] ),
			'last_name'            => sanitize_text_field( $old_member['last_name'] ),
			'email'                => sanitize_email( $old_member['email'] ),
			'phone'                => sanitize_text_field( $old_member['phone'] ),
			'street_1'             => sanitize_text_field( $old_member['address'] ),
			'street_2'             => sanitize_text_field( $old_member['address2'] ),
			'city'                 => sanitize_text_field( $old_member['city'] ),
			'state'                => sanitize_text_field( $old_member['state'] ),
			'zip'                  => sanitize_text_field( $old_member['zipcode'] ),
			'country'              => sanitize_text_field( $old_member['country'] ),
			'referral_source'      => sanitize_text_field( $old_member['referral'] ),
			'waiver_full_name'     => sanitize_text_field( $old_member['waiver_full_name'] ),
			'waiver_signed_date'   => self::convert_date( $old_member['waiver_date'] ),
			'auto_renewal_enabled' => false,
			'expiration_date'      => null, // Ignoring as per requirements
		);

		$member_id = STSRC_Member_DB::create_member( $member_data );

		if ( ! $member_id ) {
			// Clean up user if member creation failed
			wp_delete_user( $user_id );
			return false;
		}

		// Store old member ID for reference
		update_user_meta( $user_id, 'stsrc_old_member_id', $old_member['id'] );

		return true;
	}

	/**
	 * Create WordPress user account for migrated member.
	 *
	 * @since    1.0.0
	 * @param    array    $old_member    Old member data
	 * @return   int|false               User ID on success, false on failure
	 */
	private static function create_wordpress_user( array $old_member ) {
		// Generate username from email
		$username = sanitize_user( $old_member['email'] );
		$email    = sanitize_email( $old_member['email'] );

		// Check if user already exists
		if ( username_exists( $username ) || email_exists( $email ) ) {
			$existing_user = get_user_by( 'email', $email );
			if ( $existing_user ) {
				// User exists, add migration flag and return user ID
				update_user_meta( $existing_user->ID, 'stsrc_legacy_password_needs_reset', true );
				return $existing_user->ID;
			}
			return false;
		}

		// Generate random temporary password
		$temp_password = wp_generate_password( 20, true, true );

		// Create user
		$user_id = wp_create_user( $username, $temp_password, $email );

		if ( is_wp_error( $user_id ) ) {
			error_log( sprintf(
				'STSRC Migration: Failed to create WordPress user for %s: %s',
				$email,
				$user_id->get_error_message()
			) );
			return false;
		}

		// Update user meta
		wp_update_user( array(
			'ID'         => $user_id,
			'first_name' => sanitize_text_field( $old_member['first_name'] ),
			'last_name'  => sanitize_text_field( $old_member['last_name'] ),
			'role'       => 'stsrc_member',
		) );

		// Flag for legacy password reset
		update_user_meta( $user_id, 'stsrc_legacy_password_needs_reset', true );

		// Store old hashed password for verification during first login
		update_user_meta( $user_id, 'stsrc_legacy_password_hash', $old_member['password'] );

		return $user_id;
	}

	/**
	 * Get membership type ID mappings.
	 *
	 * @since    1.0.0
	 * @return   array    Array of membership type IDs keyed by name
	 */
	private static function get_membership_type_mappings(): array {
		$types = STSRC_Membership_DB::get_all_membership_types();
		$mappings = array();

		foreach ( $types as $type ) {
			$mappings[ $type['name'] ] = $type['membership_type_id'];
		}

		return $mappings;
	}

	/**
	 * Map old membership type ID to new membership type ID.
	 *
	 * @since    1.0.0
	 * @param    string    $old_type_id         Old membership type ID
	 * @param    array     $membership_types    Membership type mappings
	 * @return   int|null                       New membership type ID or null if not found
	 */
	private static function map_membership_type( string $old_type_id, array $membership_types ): ?int {
		if ( ! isset( self::$membership_mapping[ $old_type_id ] ) ) {
			return null;
		}

		$type_name = self::$membership_mapping[ $old_type_id ];

		return $membership_types[ $type_name ] ?? null;
	}

	/**
	 * Map old member status to new status.
	 *
	 * @since    1.0.0
	 * @param    array    $old_member    Old member data
	 * @return   string                  New status value
	 */
	private static function map_status( array $old_member ): string {
		// Check if deleted (soft delete)
		if ( ! empty( $old_member['isDeleted'] ) && 1 === (int) $old_member['isDeleted'] ) {
			return 'cancelled';
		}

		// Map based on payment_status
		if ( 'paid' === $old_member['payment_status'] ) {
			return 'active';
		}

		return 'pending';
	}

	/**
	 * Normalize payment type to match new system values.
	 *
	 * @since    1.0.0
	 * @param    string    $old_payment_type    Old payment type value
	 * @return   string                         Normalized payment type
	 */
	private static function normalize_payment_type( string $old_payment_type ): string {
		$type_map = array(
			'card'             => 'card',
			'us_bank_account'  => 'bank_account',
			'check'            => 'check',
			'cash'             => 'cash',
			'zelle'            => 'zelle',
			'payment_plan'     => 'payment_plan',
		);

		return $type_map[ $old_payment_type ] ?? 'other';
	}

	/**
	 * Convert date from old format to new format.
	 *
	 * @since    1.0.0
	 * @param    string    $date    Date string
	 * @return   string|null       Formatted date or null
	 */
	private static function convert_date( string $date ): ?string {
		if ( empty( $date ) || '0000-00-00' === $date ) {
			return null;
		}

		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Dry run migration to preview results without making changes.
	 *
	 * @since    1.0.0
	 * @return   array    Preview data
	 */
	public static function dry_run(): array {
		global $wpdb;

		$preview = array(
			'total_records'       => 0,
			'spam_records'        => count( self::$spam_ids ),
			'to_migrate'          => 0,
			'already_exists'      => 0,
			'breakdown_by_status' => array(
				'active'    => 0,
				'pending'   => 0,
				'cancelled' => 0,
			),
			'breakdown_by_type'   => array(),
		);

		// Check if old table exists
		$old_table = $wpdb->prefix . 'smoketree_members';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) !== $old_table ) {
			$preview['error'] = 'Old table wp_smoketree_members does not exist.';
			return $preview;
		}

		// Get total count
		$preview['total_records'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$old_table}" );

		// Get members to migrate
		$spam_ids_placeholder = implode( ',', array_fill( 0, count( self::$spam_ids ), '%d' ) );
		$query = "SELECT * FROM {$old_table} WHERE id NOT IN ({$spam_ids_placeholder})";
		$old_members = $wpdb->get_results( $wpdb->prepare( $query, ...self::$spam_ids ), ARRAY_A );

		foreach ( $old_members as $old_member ) {
			// Check if already exists
			if ( email_exists( $old_member['email'] ) ) {
				$preview['already_exists']++;
				continue;
			}

			$preview['to_migrate']++;

			// Count by status
			$status = self::map_status( $old_member );
			$preview['breakdown_by_status'][ $status ]++;

			// Count by type
			$type_name = self::$membership_mapping[ $old_member['membership_id'] ] ?? 'Unknown';
			if ( ! isset( $preview['breakdown_by_type'][ $type_name ] ) ) {
				$preview['breakdown_by_type'][ $type_name ] = 0;
			}
			$preview['breakdown_by_type'][ $type_name ]++;
		}

		return $preview;
	}
}
