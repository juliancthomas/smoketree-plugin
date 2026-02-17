<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin
 * @author     Smoketree Swim and Recreation Club
 */
class Smoketree_Plugin_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	/**
	 * Admin pages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $pages    Array of admin page instances
	 */
	private array $pages = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->register_admin_menu();
		$this->register_acf_options_page();

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string    $hook    The current admin page hook
	 */
	public function enqueue_styles( string $hook ): void {
		// Only load on plugin admin pages or WP dashboard
		if ( ! $this->is_plugin_admin_page( $hook ) && ! $this->is_wp_dashboard_page( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/smoketree-plugin-admin.css',
			array(),
			$this->version,
			'all'
		);

		// Load balance management styles on member edit page (v1.1.0+)
		if ( $this->is_member_edit_page() ) {
			wp_enqueue_style(
				$this->plugin_name . '-balance',
				plugin_dir_url( __FILE__ ) . 'css/balance-management.css',
				array(),
				$this->version,
				'all'
			);
		}

		// Load dashboard widget styles on WP dashboard
		if ( $this->is_wp_dashboard_page( $hook ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-dashboard-widgets',
				plugin_dir_url( __FILE__ ) . 'css/dashboard-widgets.css',
				array(),
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string    $hook    The current admin page hook
	 */
	public function enqueue_scripts( string $hook ): void {
		// Only load on plugin admin pages
		if ( ! $this->is_plugin_admin_page( $hook ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/smoketree-plugin-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		// Localize script with AJAX URL and nonce
		wp_localize_script(
			$this->plugin_name,
			'stsrcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'stsrc_admin_nonce' ),
				'strings'  => array(
					'confirmDelete' => __( 'Are you sure you want to delete this item? This action cannot be undone.', 'smoketree-plugin' ),
					'confirmBulk'    => __( 'Are you sure you want to apply this action to the selected items?', 'smoketree-plugin' ),
					'confirmBulkStatus' => __( 'Apply the %status% status to %count% selected member(s)?', 'smoketree-plugin' ),
					'confirmSeasonReset' => __( 'This will mark all active members as inactive. Continue?', 'smoketree-plugin' ),
					'noMembersSelected' => __( 'Please select at least one member.', 'smoketree-plugin' ),
					'statusRequired' => __( 'Please choose a status before applying changes.', 'smoketree-plugin' ),
					'saving'         => __( 'Saving...', 'smoketree-plugin' ),
					'saved'          => __( 'Saved successfully!', 'smoketree-plugin' ),
					'error'          => __( 'An error occurred. Please try again.', 'smoketree-plugin' ),
				),
			)
		);

		// Load balance management script on member edit page (v1.1.0+)
		if ( $this->is_member_edit_page() ) {
			$member_id = isset( $_GET['member_id'] ) ? absint( wp_unslash( $_GET['member_id'] ) ) : 0;
			$minimum_balance_payment = (float) get_option( 'stsrc_minimum_balance_payment', 10.00 );

			wp_enqueue_script(
				$this->plugin_name . '-balance',
				plugin_dir_url( __FILE__ ) . 'js/balance-management.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_localize_script(
				$this->plugin_name . '-balance',
				'stsrcBalanceAdmin',
				array(
					'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
					'memberId'              => $member_id,
					'minimumBalancePayment' => $minimum_balance_payment,
					'nonces'                => array(
						'adjustBalance' => wp_create_nonce( 'stsrc_adjust_balance' ),
						'recordPayment' => wp_create_nonce( 'stsrc_record_payment' ),
					),
				)
			);
		}
	}

	/**
	 * Check if current page is a plugin admin page.
	 *
	 * @since    1.0.0
	 * @param    string    $hook    The current admin page hook
	 * @return   bool               True if plugin admin page
	 */
	private function is_plugin_admin_page( string $hook ): bool {
		$plugin_pages = array(
			'toplevel_page_stsrc-dashboard',
			'smoketree-club_page_stsrc-dashboard',
			'smoketree-club_page_stsrc-members',
			'smoketree-club_page_stsrc-memberships',
			'smoketree-club_page_stsrc-guest-passes',
			'smoketree-club_page_stsrc-email',
			'smoketree-club_page_stsrc-access-codes',
			'smoketree-club_page_stsrc-settings',
		);

		return in_array( $hook, $plugin_pages, true );
	}

	/**
	 * Check if current page is member edit page.
	 *
	 * @since    1.1.0
	 * @return   bool    True if member edit page
	 */
	private function is_member_edit_page(): bool {
		// Check if we're on the members page with a member_id parameter
		if ( isset( $_GET['page'] ) && 'stsrc-members' === $_GET['page'] ) {
			// Check if there's a member_id or action=edit parameter
			return isset( $_GET['member_id'] ) || ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] );
		}

		return false;
	}

	/**
	 * Check if current page is the WordPress dashboard.
	 *
	 * @since    1.1.0
	 * @param    string    $hook    The current admin page hook
	 * @return   bool               True if WP dashboard
	 */
	private function is_wp_dashboard_page( string $hook ): bool {
		return 'index.php' === $hook;
	}

	/**
	 * Register admin menu.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function register_admin_menu(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Register ACF options page for settings.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function register_acf_options_page(): void {
		if ( function_exists( 'acf_add_options_page' ) ) {
			add_action( 'acf/init', array( $this, 'add_acf_options_page' ) );
		}
	}

	/**
	 * Add ACF options page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_acf_options_page(): void {
		acf_add_options_page(
			array(
				'page_title' => __( 'Smoketree Settings', 'smoketree-plugin' ),
				'menu_title' => __( 'Settings', 'smoketree-plugin' ),
				'menu_slug'  => 'stsrc-settings',
				'capability' => 'manage_options',
				'parent_slug' => 'stsrc-dashboard',
				'redirect'   => false,
				'position'   => false,
				'icon_url'   => false,
			)
		);
	}

	/**
	 * Add admin menu items.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_admin_menu(): void {
		// Main menu
		add_menu_page(
			__( 'Smoketree Club', 'smoketree-plugin' ),
			__( 'Smoketree Club', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-groups',
			30
		);

		// Dashboard (same as main menu, but as submenu)
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Dashboard', 'smoketree-plugin' ),
			__( 'Dashboard', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		// Members
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Members', 'smoketree-plugin' ),
			__( 'Members', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-members',
			array( $this, 'render_members_page' )
		);

		// Membership Types
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Membership Types', 'smoketree-plugin' ),
			__( 'Membership Types', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-memberships',
			array( $this, 'render_memberships_page' )
		);

		// Guest Passes
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Guest Passes', 'smoketree-plugin' ),
			__( 'Guest Passes', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-guest-passes',
			array( $this, 'render_guest_passes_page' )
		);

		// Batch Email
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Batch Email', 'smoketree-plugin' ),
			__( 'Batch Email', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-email',
			array( $this, 'render_email_page' )
		);

		// Access Codes
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Access Codes', 'smoketree-plugin' ),
			__( 'Access Codes', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-access-codes',
			array( $this, 'render_access_codes_page' )
		);

		// Settings
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Settings', 'smoketree-plugin' ),
			__( 'Settings', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_dashboard_page(): void {
		require_once plugin_dir_path( __FILE__ ) . 'pages/class-stsrc-dashboard-page.php';
		$page = new STSRC_Dashboard_Page();
		$page->render();
	}

	/**
	 * Render members page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_members_page(): void {
		require_once plugin_dir_path( __FILE__ ) . 'pages/class-stsrc-members-page.php';
		$page = new STSRC_Members_Page();
		$page->render();
	}

	/**
	 * Render memberships page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_memberships_page(): void {
		require_once plugin_dir_path( __FILE__ ) . 'pages/class-stsrc-memberships-page.php';
		$page = new STSRC_Memberships_Page();
		$page->render();
	}

	/**
	 * Render guest passes page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_guest_passes_page(): void {
		require_once plugin_dir_path( __FILE__ ) . 'pages/class-stsrc-guest-passes-page.php';
		$page = new STSRC_Guest_Passes_Page();
		$page->render();
	}

	/**
	 * Render email page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_email_page(): void {
		require_once plugin_dir_path( __FILE__ ) . 'pages/class-stsrc-email-page.php';
		$page = new STSRC_Email_Page();
		$page->render();
	}

	/**
	 * Render access codes page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_access_codes_page(): void {
		require_once plugin_dir_path( __FILE__ ) . 'pages/class-stsrc-access-codes-page.php';
		$page = new STSRC_Access_Codes_Page();
		$page->render();
	}

	/**
	 * Render settings page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_settings_page(): void {
		require_once plugin_dir_path( __FILE__ ) . 'pages/class-stsrc-settings-page.php';
		$page = new STSRC_Settings_Page();
		$page->render();
	}
}

