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

