<?php

/**
 * Fired during plugin activation
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes
 * @author     Smoketree Swim and Recreation Club
 */
class Smoketree_Plugin_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables, custom user role, and sets default options.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Load database class
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-database.php';

		// Create all database tables
		STSRC_Database::create_tables();

		// Run database upgrade routine
		self::upgrade_database();

		// Create custom user role for members
		self::create_member_role();

		// Set default options
		self::set_default_options();

		// Create default membership types
		self::create_default_membership_types();

		// Register auto-renewal cron events
		self::register_cron_events();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Upgrade database schema if needed.
	 *
	 * Checks the stored database version and runs necessary migrations.
	 * This method is called on plugin activation.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	private static function upgrade_database(): void {
		// Get current database version
		$current_db_version = get_option( 'stsrc_db_version', '1.0.0' );

		// Get plugin version constant
		$plugin_version = defined( 'SMOKETREE_PLUGIN_VERSION' ) ? SMOKETREE_PLUGIN_VERSION : '1.0.0';

		// Compare versions - if database version is lower, run upgrades
		if ( version_compare( $current_db_version, '1.1.0', '<' ) ) {
			// Version 1.1.0 upgrade: Balance tracking system
			// Tables and columns are created/enhanced by STSRC_Database::create_tables()
			// which uses dbDelta to safely add new tables and columns

			// Load required database classes for backfill
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-member-db.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-transaction-db.php';

			// Backfill balance fields for existing members
			$members_updated = STSRC_Member_DB::backfill_balance_fields();

			// Create initial transactions for existing members
			$transactions_created = STSRC_Transaction_DB::backfill_initial_transactions();

			// Log the migration results
			error_log( sprintf(
				'STSRC v1.1.0 Migration: Updated %d members and created %d initial transactions',
				$members_updated,
				$transactions_created
			) );

			// Update database version
			update_option( 'stsrc_db_version', '1.1.0' );
		}

		// Always update to current plugin version
		if ( version_compare( $current_db_version, $plugin_version, '<' ) ) {
			update_option( 'stsrc_db_version', $plugin_version );
		}

		self::upgrade_renewal_database();
		self::upgrade_promo_database();
		self::upgrade_demo_database();
	}

	/**
	 * Ensure renewal ledger schema is installed/upgraded safely.
	 *
	 * @since    1.3.0
	 * @return   void
	 */
	private static function upgrade_renewal_database(): void {
		$renewal_db_version = get_option( 'stsrc_renewal_db_version', '0.0.0' );

		if ( version_compare( $renewal_db_version, '1.0.0', '>=' ) ) {
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-renewal-db.php';
		STSRC_Renewal_DB::create_table();

		update_option( 'stsrc_renewal_db_version', '1.0.0' );
	}

	/**
	 * Ensure promo and referral schema is installed/upgraded safely.
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	private static function upgrade_promo_database(): void {
		global $wpdb;

		$promo_db_version = get_option( 'stsrc_promo_db_version', '0.0.0' );

		if ( version_compare( $promo_db_version, '1.0.0', '<' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-database.php';
			STSRC_Database::create_tables();

			$table_members = $wpdb->prefix . 'stsrc_members';

			$wpdb->query(
				"ALTER TABLE {$table_members}
				ADD COLUMN IF NOT EXISTS affiliate_code VARCHAR(30) NULL DEFAULT NULL"
			);

			$existing_unique_index = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT INDEX_NAME
					FROM information_schema.STATISTICS
					WHERE TABLE_SCHEMA = %s
					AND TABLE_NAME = %s
					AND INDEX_NAME = %s
					LIMIT 1",
					DB_NAME,
					$table_members,
					'uq_affiliate_code'
				)
			);

			if ( empty( $existing_unique_index ) ) {
				$wpdb->query( "ALTER TABLE {$table_members} ADD UNIQUE KEY uq_affiliate_code (affiliate_code)" );
			}

			update_option( 'stsrc_promo_db_version', '1.0.0' );
		}

		$backfill_done = get_option( 'stsrc_affiliate_code_backfill_done', '0' );
		if ( '1' !== (string) $backfill_done ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/services/class-stsrc-discount-service.php';
			$backfill_result = STSRC_Discount_Service::backfill_affiliate_codes();
			$errors          = is_array( $backfill_result['errors'] ?? null ) ? $backfill_result['errors'] : array();

			error_log(
				sprintf(
					'STSRC Affiliate Backfill: processed %d, skipped %d, errors %d',
					(int) ( $backfill_result['processed'] ?? 0 ),
					(int) ( $backfill_result['skipped'] ?? 0 ),
					count( $errors )
				)
			);

			if ( empty( $errors ) ) {
				update_option( 'stsrc_affiliate_code_backfill_done', '1' );
			}
		}
	}

	/**
	 * Ensure demo-account schema is installed/upgraded safely.
	 *
	 * @since    1.5.0
	 * @return   void
	 */
	private static function upgrade_demo_database(): void {
		global $wpdb;

		$demo_db_version = get_option( 'stsrc_demo_db_version', '0.0.0' );

		if ( version_compare( $demo_db_version, '1.0.0', '>=' ) ) {
			return;
		}

		$table_members = $wpdb->prefix . 'stsrc_members';
		$column_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$table_members} LIKE 'is_demo'"
		);

		if ( empty( $column_exists ) ) {
			$wpdb->query(
				"ALTER TABLE {$table_members}
				ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0"
			);
		}

		update_option( 'stsrc_demo_db_version', '1.0.0' );
	}

	/**
	 * Create custom user role for members.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function create_member_role() {
		// Remove role if it exists to avoid conflicts
		remove_role( 'stsrc_member' );

		// Add custom capabilities
		$capabilities = array(
			'read' => true,
		);

		// Create the role
		add_role( 'stsrc_member', __( 'Smoketree Member', 'smoketree-plugin' ), $capabilities );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function set_default_options() {
		// Set default options if they don't exist
		if ( ! get_option( 'stsrc_registration_enabled' ) ) {
			add_option( 'stsrc_registration_enabled', '1' );
		}

		if ( ! get_option( 'stsrc_payment_plan_enabled' ) ) {
			add_option( 'stsrc_payment_plan_enabled', '0' );
		}

		if ( false === get_option( 'stsrc_renewal_enabled', false ) ) {
			add_option( 'stsrc_renewal_enabled', '0' );
		}
	}

	/**
	 * Create default membership types.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function create_default_membership_types() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-membership-db.php';

		// Check if membership types already exist
		$existing_types = STSRC_Membership_DB::get_all_membership_types();
		if ( ! empty( $existing_types ) ) {
			return; // Don't create defaults if types already exist
		}

		// Get benefits from ACF or use defaults
		$all_benefits_keys = self::get_all_benefit_keys();
		$voting_benefit_key = self::get_voting_benefit_key();

		// Default membership types
		$default_types = array(
			array(
				'name'                      => 'Household',
				'description'              => 'Full membership for up to 5 people, includes all benefits. Can add up to 4 family members (free) and 3 extra members ($50 each).',
				'price'                    => 500.00, // Placeholder - should be updated
				'expiration_period'        => 365,
				'stripe_product_id'        => null,
				'is_selectable'            => true,
				'is_best_seller'           => true,
				'can_have_additional_members' => true,
				'benefits'                 => $all_benefits_keys,
			),
			array(
				'name'                      => 'Duo',
				'description'              => 'Membership for 2 people, includes all benefits. Can add up to 1 family member (free).',
				'price'                    => 400.00, // Placeholder - should be updated
				'expiration_period'        => 365,
				'stripe_product_id'        => null,
				'is_selectable'            => true,
				'is_best_seller'           => false,
				'can_have_additional_members' => true,
				'benefits'                 => $all_benefits_keys,
			),
			array(
				'name'                      => 'Single',
				'description'              => 'Single person membership, includes all benefits.',
				'price'                    => 300.00, // Placeholder - should be updated
				'expiration_period'        => 365,
				'stripe_product_id'        => null,
				'is_selectable'            => true,
				'is_best_seller'           => false,
				'can_have_additional_members' => false,
				'benefits'                 => $all_benefits_keys,
			),
			array(
				'name'                      => 'Civic',
				'description'              => 'Voting-only membership, no pool access.',
				'price'                    => 100.00, // Placeholder - should be updated
				'expiration_period'        => 365,
				'stripe_product_id'        => null,
				'is_selectable'            => true,
				'is_best_seller'           => false,
				'can_have_additional_members' => false,
				'benefits'                 => array( $voting_benefit_key ),
			),
		);

		// Create each default type
		foreach ( $default_types as $type_data ) {
			STSRC_Membership_DB::create_membership_type( $type_data );
		}
	}

	/**
	 * Get all benefit keys from ACF or return defaults.
	 *
	 * @since    1.0.0
	 * @return   array    Array of benefit keys
	 */
	private static function get_all_benefit_keys(): array {
		// Try to get benefits from ACF field definition
		if ( function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( 'various_membership_benefits' );
			if ( $field && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
				// Convert ACF choices to keys
				$keys = array();
				foreach ( $field['choices'] as $key => $label ) {
					// Use the key if it's not numeric, otherwise create a slug from label
					$benefit_key = is_numeric( $key ) ? sanitize_key( str_replace( array( ' ', '/', '&' ), array( '_', '_', 'and' ), strtolower( $label ) ) ) : $key;
					$keys[] = $benefit_key;
				}
				if ( ! empty( $keys ) ) {
					return $keys;
				}
			}
		}

		// Fallback to default benefit keys
		return array(
			'up_to_5_people',
			'2_people',
			'1_person',
			'pool_use_for_season',
			'lakefront_and_dock',
			'playground',
			'tennis_pickleball',
			'dog_run',
			'pavilion',
			'membership_voting',
		);
	}

	/**
	 * Get voting benefit key from ACF or return default.
	 *
	 * @since    1.0.0
	 * @return   string    Voting benefit key
	 */
	private static function get_voting_benefit_key(): string {
		// Try to find "Membership Voting Rights" in ACF
		if ( function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( 'various_membership_benefits' );
			if ( $field && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
				foreach ( $field['choices'] as $key => $label ) {
					if ( stripos( $label, 'voting' ) !== false ) {
						// Found voting-related benefit
						return is_numeric( $key ) ? sanitize_key( str_replace( array( ' ', '/', '&' ), array( '_', '_', 'and' ), strtolower( $label ) ) ) : $key;
					}
				}
			}
		}

		// Fallback to default
		return 'membership_voting';
	}

	/**
	 * Register cron events required for auto-renewal processing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_cron_events(): void {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/services/class-stsrc-auto-renewal-service.php';

		STSRC_Auto_Renewal_Service::ensure_cron_events();
	}
}

