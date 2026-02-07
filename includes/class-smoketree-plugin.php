<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes
 * @author     Smoketree Swim and Recreation Club
 */
class Smoketree_Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Smoketree_Plugin_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'SMOKETREE_PLUGIN_VERSION' ) ) {
			$this->version = SMOKETREE_PLUGIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'smoketree-plugin';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_auto_renewal_hooks();
		$this->register_rest_routes();
		$this->register_ajax_handlers();
		$this->maybe_run_database_migrations();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Smoketree_Plugin_Loader. Orchestrates the hooks of the plugin.
	 * - Smoketree_Plugin_i18n. Defines internationalization functionality.
	 * - Smoketree_Plugin_Admin. Defines all hooks for the admin area.
	 * - Smoketree_Plugin_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-smoketree-plugin-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-smoketree-plugin-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-smoketree-plugin-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-smoketree-plugin-public.php';

		/**
		 * Auto-renewal service class (cron handlers, scheduling).
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/services/class-stsrc-auto-renewal-service.php';

		/**
		 * Database management utilities.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-database.php';

		$this->loader = new Smoketree_Plugin_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Smoketree_Plugin_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Smoketree_Plugin_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Smoketree_Plugin_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Smoketree_Plugin_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'plugins_loaded', $plugin_public, 'register_page_templates' );
		$this->loader->add_filter( 'login_redirect', $plugin_public, 'handle_login_redirect', 10, 3 );
		$this->loader->add_action( 'init', $plugin_public, 'handle_logout_redirect' );

	}

	/**
	 * Register cron handlers for the auto-renewal service.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_auto_renewal_hooks() {

		$auto_service = new STSRC_Auto_Renewal_Service();

		$this->loader->add_action( STSRC_Auto_Renewal_Service::CRON_HOOK_NOTIFICATION, $auto_service, 'handle_notification_cron' );
		$this->loader->add_action( STSRC_Auto_Renewal_Service::CRON_HOOK_PROCESS, $auto_service, 'handle_processing_cron' );
		$this->loader->add_action( 'init', 'STSRC_Auto_Renewal_Service', 'ensure_cron_events' );

	}

	/**
	 * Register REST API routes.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function register_rest_routes() {
		$this->loader->add_action( 'rest_api_init', $this, 'register_stripe_webhook_route' );
	}

	/**
	 * Register Stripe webhook REST API route.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_stripe_webhook_route() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/api/class-smoketree-stripe-webhooks.php';

		register_rest_route(
			'stripe/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'Smoketree_Stripe_Webhooks', 'handle_webhook' ),
				'permission_callback' => '__return_true', // Public endpoint for Stripe webhooks
			)
		);
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function register_ajax_handlers() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/api/class-stsrc-ajax-handler.php';
		$ajax_handler = new STSRC_Ajax_Handler();

		// Login endpoint (no login required)
		$this->loader->add_action( 'wp_ajax_nopriv_stsrc_login', $ajax_handler, 'login' );
		$this->loader->add_action( 'wp_ajax_stsrc_login', $ajax_handler, 'login' );

		// Registration endpoint (no login required)
		$this->loader->add_action( 'wp_ajax_nopriv_stsrc_register_member', $ajax_handler, 'register_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_register_member', $ajax_handler, 'register_member' );

		// Member portal endpoints (login required)
		$this->loader->add_action( 'wp_ajax_stsrc_update_profile', $ajax_handler, 'update_profile' );
		$this->loader->add_action( 'wp_ajax_stsrc_change_password', $ajax_handler, 'change_password' );
		$this->loader->add_action( 'wp_ajax_stsrc_add_family_member', $ajax_handler, 'add_family_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_update_family_member', $ajax_handler, 'update_family_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_delete_family_member', $ajax_handler, 'delete_family_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_add_extra_member', $ajax_handler, 'add_extra_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_update_extra_member', $ajax_handler, 'update_extra_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_delete_extra_member', $ajax_handler, 'delete_extra_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_purchase_guest_passes', $ajax_handler, 'purchase_guest_passes' );
		$this->loader->add_action( 'wp_ajax_stsrc_use_guest_pass', $ajax_handler, 'use_guest_pass' );
		$this->loader->add_action( 'wp_ajax_stsrc_get_customer_portal_url', $ajax_handler, 'get_customer_portal_url' );
		$this->loader->add_action( 'wp_ajax_stsrc_toggle_auto_renewal', $ajax_handler, 'toggle_auto_renewal' );

		// Admin endpoints (admin capability required)
		$this->loader->add_action( 'wp_ajax_stsrc_create_member', $ajax_handler, 'create_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_update_member', $ajax_handler, 'update_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_delete_member', $ajax_handler, 'delete_member' );
		$this->loader->add_action( 'wp_ajax_stsrc_reactivate_member', $ajax_handler, 'reactivate_member_admin' );
		$this->loader->add_action( 'wp_ajax_stsrc_create_membership_type', $ajax_handler, 'create_membership_type' );
		$this->loader->add_action( 'wp_ajax_stsrc_update_membership_type', $ajax_handler, 'update_membership_type' );
		$this->loader->add_action( 'wp_ajax_stsrc_delete_membership_type', $ajax_handler, 'delete_membership_type' );
		$this->loader->add_action( 'wp_ajax_stsrc_export_members', $ajax_handler, 'export_members' );
		$this->loader->add_action( 'wp_ajax_stsrc_preview_recipients', $ajax_handler, 'preview_recipients' );
		$this->loader->add_action( 'wp_ajax_stsrc_send_batch_email', $ajax_handler, 'send_batch_email' );
		$this->loader->add_action( 'wp_ajax_stsrc_create_access_code', $ajax_handler, 'create_access_code' );
		$this->loader->add_action( 'wp_ajax_stsrc_update_access_code', $ajax_handler, 'update_access_code' );
		$this->loader->add_action( 'wp_ajax_stsrc_delete_access_code', $ajax_handler, 'delete_access_code' );
		$this->loader->add_action( 'wp_ajax_stsrc_save_settings', $ajax_handler, 'save_settings' );
		$this->loader->add_action( 'wp_ajax_stsrc_admin_adjust_guest_passes', $ajax_handler, 'admin_adjust_guest_passes' );
		$this->loader->add_action( 'wp_ajax_stsrc_bulk_update_members', $ajax_handler, 'bulk_update_members' );

		// Password reset endpoints (no login required)
		$this->loader->add_action( 'wp_ajax_nopriv_stsrc_forgot_password', $ajax_handler, 'forgot_password' );
		$this->loader->add_action( 'wp_ajax_stsrc_forgot_password', $ajax_handler, 'forgot_password' );
		$this->loader->add_action( 'wp_ajax_nopriv_stsrc_reset_password', $ajax_handler, 'reset_password' );
		$this->loader->add_action( 'wp_ajax_stsrc_reset_password', $ajax_handler, 'reset_password' );

		// Public reactivation endpoint
		$this->loader->add_action( 'init', $ajax_handler, 'handle_reactivation_request' );
	}

	/**
	 * Ensure database schema upgrades run when the plugin version changes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function maybe_run_database_migrations() {
		$installed_version = get_option( 'stsrc_plugin_version' );

		if ( $installed_version === $this->get_version() ) {
			return;
		}

		STSRC_Database::create_tables();
		update_option( 'stsrc_plugin_version', $this->get_version() );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Smoketree_Plugin_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

