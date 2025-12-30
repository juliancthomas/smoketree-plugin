<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public
 * @author     Smoketree Swim and Recreation Club
 */
class Smoketree_Plugin_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles(): void {
		// Only load on plugin pages
		if ( $this->is_plugin_page() ) {
			$tailwind_relative = 'css/tailwind.css';
			$tailwind_path     = plugin_dir_path( __FILE__ ) . $tailwind_relative;
			$style_dependencies = array();

			if ( file_exists( $tailwind_path ) ) {
				wp_enqueue_style(
					$this->plugin_name . '-tailwind',
					plugin_dir_url( __FILE__ ) . $tailwind_relative,
					array(),
					filemtime( $tailwind_path ),
					'all'
				);
				$style_dependencies[] = $this->plugin_name . '-tailwind';
			}

			wp_enqueue_style(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'css/smoketree-plugin-public.css',
				$style_dependencies,
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @since    1.0.0
	 * @return   bool    True if plugin page
	 */
	private function is_plugin_page(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		$page_slug = $post->post_name;
		$plugin_pages = array(
			'register',
			'login',
			'forgot-password',
			'reset-password',
			'member-portal',
			'guest-pass-portal',
		);

		// Check by slug
		if ( in_array( $page_slug, $plugin_pages, true ) ) {
			return true;
		}

		// Check by template
		$page_template = get_post_meta( $post->ID, '_wp_page_template', true );
		$plugin_templates = array(
			'registration-form.php',
			'login.php',
			'forgot-password.php',
			'reset-password.php',
			'member-portal.php',
			'guest-pass-portal.php',
		);

		return in_array( $page_template, $plugin_templates, true );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts(): void {
		// Only load on plugin pages
		if ( $this->is_plugin_page() ) {
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/smoketree-plugin-public.js',
				array( 'jquery' ),
				$this->version,
				false
			);

			// Localize script for AJAX
			wp_localize_script(
				$this->plugin_name,
				'stsrcPublic',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'stsrc_registration_nonce' ),
					'strings' => array(
						'saving'    => __( 'Saving...', 'smoketree-plugin' ),
						'submitting' => __( 'Submitting...', 'smoketree-plugin' ),
						'loading'   => __( 'Loading...', 'smoketree-plugin' ),
						'success'  => __( 'Operation completed successfully.', 'smoketree-plugin' ),
						'error'    => __( 'An error occurred. Please try again.', 'smoketree-plugin' ),
						'autoRenewalEnabled'  => __( 'Enabled', 'smoketree-plugin' ),
						'autoRenewalDisabled' => __( 'Disabled', 'smoketree-plugin' ),
						'autoRenewalUpdating' => __( 'Updating preference...', 'smoketree-plugin' ),
						'autoRenewalError'    => __( 'Unable to update auto-renewal.', 'smoketree-plugin' ),
					),
				)
			);
		}
	}

	/**
	 * Register page templates.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_page_templates(): void {
		add_filter( 'page_template', array( $this, 'assign_page_template' ) );
		add_filter( 'theme_page_templates', array( $this, 'add_page_templates' ) );
	}

	/**
	 * Add page templates to dropdown.
	 *
	 * @since    1.0.0
	 * @param    array    $templates    Existing templates
	 * @return   array                 Templates with plugin templates added
	 */
	public function add_page_templates( array $templates ): array {
		$templates['registration-form.php'] = __( 'Smoketree Registration', 'smoketree-plugin' );
		$templates['login.php'] = __( 'Smoketree Login', 'smoketree-plugin' );
		$templates['forgot-password.php'] = __( 'Smoketree Forgot Password', 'smoketree-plugin' );
		$templates['reset-password.php'] = __( 'Smoketree Reset Password', 'smoketree-plugin' );
		$templates['member-portal.php'] = __( 'Smoketree Member Portal', 'smoketree-plugin' );
		$templates['guest-pass-portal.php'] = __( 'Smoketree Guest Pass Portal', 'smoketree-plugin' );
		return $templates;
	}

	/**
	 * Assign page template based on page slug or template selection.
	 *
	 * @since    1.0.0
	 * @param    string    $template    Current template
	 * @return   string                 Template path
	 */
	public function assign_page_template( string $template ): string {
		global $post;

		if ( ! $post ) {
			return $template;
		}

		// Check if page uses our template
		$page_template = get_post_meta( $post->ID, '_wp_page_template', true );
		$page_slug = $post->post_name;

		// Registration page
		if ( 'registration-form.php' === $page_template || 'register' === $page_slug ) {
			$template = plugin_dir_path( __FILE__ ) . 'templates/registration-form.php';
		}
		// Login page
		elseif ( 'login.php' === $page_template || 'login' === $page_slug ) {
			$template = plugin_dir_path( __FILE__ ) . 'templates/login.php';
		}
		// Forgot password page
		elseif ( 'forgot-password.php' === $page_template || 'forgot-password' === $page_slug ) {
			$template = plugin_dir_path( __FILE__ ) . 'templates/forgot-password.php';
		}
		// Reset password page
		elseif ( 'reset-password.php' === $page_template || 'reset-password' === $page_slug ) {
			$template = plugin_dir_path( __FILE__ ) . 'templates/reset-password.php';
		}
		// Member portal page
		elseif ( 'member-portal.php' === $page_template || 'member-portal' === $page_slug ) {
			$template = plugin_dir_path( __FILE__ ) . 'templates/member-portal.php';
		}
		// Guest pass portal page
		elseif ( 'guest-pass-portal.php' === $page_template || 'guest-pass-portal' === $page_slug ) {
			$template = plugin_dir_path( __FILE__ ) . 'templates/guest-pass-portal.php';
		}

		return $template;
	}

	/**
	 * Handle logout redirect.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_logout_redirect(): void {
		if ( isset( $_GET['action'] ) && 'logout' === $_GET['action'] ) {
			check_admin_referer( 'log-out' );
			wp_logout();
			wp_safe_redirect( home_url( '/login?loggedout=true' ) );
			exit;
		}
	}

}

