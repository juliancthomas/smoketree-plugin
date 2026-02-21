<?php

/**
 * Database setup and management class
 *
 * Handles creation and management of all custom database tables for the plugin.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 */

/**
 * Database setup and management class.
 *
 * Creates and manages all custom database tables for the plugin.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/database
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Database {

	/**
	 * Create all custom database tables.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Load transaction DB class for table creation
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		// Table: wp_stsrc_members
		$table_members = $wpdb->prefix . 'stsrc_members';
		$sql_members = "CREATE TABLE $table_members (
			member_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			membership_type_id BIGINT(20) UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			payment_type VARCHAR(20) NOT NULL,
			stripe_customer_id VARCHAR(255) DEFAULT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(20) NOT NULL,
			street_1 VARCHAR(255) NOT NULL,
			street_2 VARCHAR(255) DEFAULT NULL,
			city VARCHAR(100) NOT NULL DEFAULT 'Tucker',
			state VARCHAR(2) NOT NULL DEFAULT 'GA',
			zip VARCHAR(10) NOT NULL DEFAULT '30084',
			country VARCHAR(2) NOT NULL DEFAULT 'US',
			referral_source VARCHAR(100) DEFAULT NULL,
			waiver_full_name VARCHAR(255) NOT NULL,
			waiver_signed_date DATE NOT NULL,
			auto_renewal_enabled TINYINT(1) NOT NULL DEFAULT 0,
			expiration_date DATE DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (member_id),
			UNIQUE KEY email (email(191)),
			UNIQUE KEY user_id (user_id),
			KEY membership_type_id (membership_type_id),
			KEY status (status),
			KEY stripe_customer_id (stripe_customer_id(191)),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_members );

		// Table: wp_stsrc_membership_types
		$table_membership_types = $wpdb->prefix . 'stsrc_membership_types';
		$sql_membership_types = "CREATE TABLE $table_membership_types (
			membership_type_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			description TEXT,
			price DECIMAL(10,2) NOT NULL,
			expiration_period INT(11) NOT NULL COMMENT 'Days until expiration',
			stripe_product_id VARCHAR(255) DEFAULT NULL,
			is_selectable TINYINT(1) NOT NULL DEFAULT 1,
			is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
			can_have_additional_members TINYINT(1) NOT NULL DEFAULT 0,
			benefits JSON DEFAULT NULL COMMENT 'Array of benefit IDs',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (membership_type_id),
			UNIQUE KEY name (name),
			KEY is_selectable (is_selectable)
		) $charset_collate;";
		dbDelta( $sql_membership_types );

		// Table: wp_stsrc_family_members
		$table_family_members = $wpdb->prefix . 'stsrc_family_members';
		$sql_family_members = "CREATE TABLE $table_family_members (
			family_member_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			email VARCHAR(255) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (family_member_id),
			KEY member_id (member_id),
			UNIQUE KEY member_name (member_id, first_name, last_name)
		) $charset_collate;";
		dbDelta( $sql_family_members );

		// Table: wp_stsrc_extra_members
		$table_extra_members = $wpdb->prefix . 'stsrc_extra_members';
		$sql_extra_members = "CREATE TABLE $table_extra_members (
			extra_member_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			email VARCHAR(255) DEFAULT NULL,
			payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (extra_member_id),
			KEY member_id (member_id),
			UNIQUE KEY member_name (member_id, first_name, last_name)
		) $charset_collate;";
		dbDelta( $sql_extra_members );

		// Table: wp_stsrc_guest_passes
		$table_guest_passes = $wpdb->prefix . 'stsrc_guest_passes';
		$sql_guest_passes = "CREATE TABLE $table_guest_passes (
			guest_pass_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'purchase',
			quantity INT(11) NOT NULL DEFAULT 1,
			amount DECIMAL(10,2) NOT NULL,
			stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
			used_at DATETIME DEFAULT NULL,
			payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			admin_adjusted TINYINT(1) NOT NULL DEFAULT 0,
			adjusted_by BIGINT(20) UNSIGNED DEFAULT NULL,
			notes TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (guest_pass_id),
			KEY member_id (member_id),
			KEY type (type),
			KEY used_at (used_at),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_guest_passes );

		// Backfill type column for existing rows that pre-date the column.
		self::backfill_guest_pass_types();

		// Table: wp_stsrc_email_logs
		$table_email_logs = $wpdb->prefix . 'stsrc_email_logs';
		$sql_email_logs = "CREATE TABLE $table_email_logs (
			email_log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email_campaign_id VARCHAR(100) NOT NULL,
			member_id BIGINT(20) UNSIGNED DEFAULT NULL,
			recipient_email VARCHAR(255) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			error_message TEXT DEFAULT NULL,
			sent_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (email_log_id),
			KEY email_campaign_id (email_campaign_id),
			KEY member_id (member_id),
			KEY status (status),
			KEY sent_at (sent_at)
		) $charset_collate;";
		dbDelta( $sql_email_logs );

		// Table: wp_stsrc_access_codes
		$table_access_codes = $wpdb->prefix . 'stsrc_access_codes';
		$sql_access_codes = "CREATE TABLE $table_access_codes (
			code_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(100) NOT NULL,
			description VARCHAR(255) DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			is_premium TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (code_id),
			KEY is_active (is_active),
			KEY expires_at (expires_at)
		) $charset_collate;";
		dbDelta( $sql_access_codes );

		// Table: wp_stsrc_payment_logs
		$table_payment_logs = $wpdb->prefix . 'stsrc_payment_logs';
		$sql_payment_logs = "CREATE TABLE $table_payment_logs (
			payment_log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
			stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
			amount DECIMAL(10,2) NOT NULL,
			fee_amount DECIMAL(10,2) DEFAULT NULL,
			payment_type VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			stripe_event_id VARCHAR(255) DEFAULT NULL,
			metadata JSON DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (payment_log_id),
			KEY member_id (member_id),
			KEY stripe_payment_intent_id (stripe_payment_intent_id(191)),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_payment_logs );

		// Create transactions table (v1.1.0+)
		STSRC_Transaction_DB::create_table();

		// Enhance members table with balance tracking columns (v1.1.0+)
		STSRC_Member_DB::enhance_table_for_balance_tracking();

		// Drop legacy guest_pass_balance column (v1.2.0+) — balance is now computed from stsrc_guest_passes.
		self::drop_guest_pass_balance_column();

		// Add foreign key constraints after all tables are created
		self::add_foreign_key_constraints();
	}

	/**
	 * Backfill the `type` column for existing guest pass rows.
	 *
	 * Derives the type from existing columns: used_at indicates usage,
	 * admin_adjusted indicates an admin adjustment, otherwise it's a purchase.
	 * Only touches rows where type is still the default 'purchase'.
	 *
	 * @since    1.2.0
	 * @return   void
	 */
	private static function backfill_guest_pass_types() {
		global $wpdb;

		$table = $wpdb->prefix . 'stsrc_guest_passes';

		// Usage rows: used_at is set and not admin-adjusted.
		$wpdb->query(
			"UPDATE {$table} SET type = 'usage' WHERE used_at IS NOT NULL AND admin_adjusted = 0 AND type = 'purchase'"
		);

		// Admin adjustment rows.
		$wpdb->query(
			"UPDATE {$table} SET type = 'admin_credit' WHERE admin_adjusted = 1 AND type = 'purchase'"
		);
	}

	/**
	 * Drop the legacy guest_pass_balance column from the members table.
	 *
	 * Balance is now computed from the stsrc_guest_passes ledger.
	 *
	 * @since    1.2.0
	 * @return   void
	 */
	public static function drop_guest_pass_balance_column() {
		global $wpdb;

		$table = $wpdb->prefix . 'stsrc_members';

		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'guest_pass_balance'",
				DB_NAME,
				$table
			)
		);

		if ( ! empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table} DROP COLUMN guest_pass_balance" );
		}
	}

	/**
	 * Add foreign key constraints to tables.
	 *
	 * This is done separately from CREATE TABLE because dbDelta doesn't handle
	 * foreign keys properly in CREATE TABLE statements.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function add_foreign_key_constraints() {
		global $wpdb;

		$table_members        = $wpdb->prefix . 'stsrc_members';
		$table_family_members = $wpdb->prefix . 'stsrc_family_members';
		$table_extra_members  = $wpdb->prefix . 'stsrc_extra_members';
		$table_guest_passes   = $wpdb->prefix . 'stsrc_guest_passes';
		$table_email_logs     = $wpdb->prefix . 'stsrc_email_logs';
		$table_payment_logs   = $wpdb->prefix . 'stsrc_payment_logs';
		$table_transactions   = $wpdb->prefix . 'stsrc_transactions';

		// Check and add foreign keys if they don't exist
		$constraints = array(
			array(
				'table' => $table_family_members,
				'name'  => 'fk_family_members_member_id',
				'sql'   => "ALTER TABLE {$table_family_members} 
					ADD CONSTRAINT fk_family_members_member_id 
					FOREIGN KEY (member_id) REFERENCES {$table_members}(member_id) ON DELETE CASCADE"
			),
			array(
				'table' => $table_extra_members,
				'name'  => 'fk_extra_members_member_id',
				'sql'   => "ALTER TABLE {$table_extra_members} 
					ADD CONSTRAINT fk_extra_members_member_id 
					FOREIGN KEY (member_id) REFERENCES {$table_members}(member_id) ON DELETE CASCADE"
			),
			array(
				'table' => $table_guest_passes,
				'name'  => 'fk_guest_passes_member_id',
				'sql'   => "ALTER TABLE {$table_guest_passes} 
					ADD CONSTRAINT fk_guest_passes_member_id 
					FOREIGN KEY (member_id) REFERENCES {$table_members}(member_id) ON DELETE CASCADE"
			),
			array(
				'table' => $table_email_logs,
				'name'  => 'fk_email_logs_member_id',
				'sql'   => "ALTER TABLE {$table_email_logs} 
					ADD CONSTRAINT fk_email_logs_member_id 
					FOREIGN KEY (member_id) REFERENCES {$table_members}(member_id) ON DELETE SET NULL"
			),
			array(
				'table' => $table_payment_logs,
				'name'  => 'fk_payment_logs_member_id',
				'sql'   => "ALTER TABLE {$table_payment_logs} 
					ADD CONSTRAINT fk_payment_logs_member_id 
					FOREIGN KEY (member_id) REFERENCES {$table_members}(member_id) ON DELETE CASCADE"
			),
			array(
				'table' => $table_transactions,
				'name'  => 'fk_transactions_member_id',
				'sql'   => "ALTER TABLE {$table_transactions} 
					ADD CONSTRAINT fk_transactions_member_id 
					FOREIGN KEY (member_id) REFERENCES {$table_members}(member_id) ON DELETE CASCADE"
			),
		);

		foreach ( $constraints as $constraint ) {
			// Check if foreign key already exists
			$existing = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT CONSTRAINT_NAME 
					FROM information_schema.TABLE_CONSTRAINTS 
					WHERE TABLE_SCHEMA = %s 
					AND TABLE_NAME = %s 
					AND CONSTRAINT_NAME = %s 
					AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
					DB_NAME,
					$constraint['table'],
					$constraint['name']
				)
			);

			// Only add if it doesn't exist
			if ( empty( $existing ) ) {
				$wpdb->query( $constraint['sql'] );
			}
		}
	}

	/**
	 * Drop all custom database tables.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'stsrc_transactions',
			$wpdb->prefix . 'stsrc_payment_logs',
			$wpdb->prefix . 'stsrc_access_codes',
			$wpdb->prefix . 'stsrc_email_logs',
			$wpdb->prefix . 'stsrc_guest_passes',
			$wpdb->prefix . 'stsrc_extra_members',
			$wpdb->prefix . 'stsrc_family_members',
			$wpdb->prefix . 'stsrc_membership_types',
			$wpdb->prefix . 'stsrc_members',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
	}
}

