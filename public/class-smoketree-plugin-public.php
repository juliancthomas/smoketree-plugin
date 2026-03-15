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

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-stsrc-renewal-helpers.php';

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
				$this->get_asset_version( 'css/smoketree-plugin-public.css' ),
				'all'
			);

			if ( $this->is_member_portal_page() ) {
				wp_enqueue_style(
					'smartwizard',
					plugin_dir_url( __FILE__ ) . 'vendor/smartwizard/smart_wizard_all.min.css',
					array(),
					$this->get_asset_version( 'vendor/smartwizard/smart_wizard_all.min.css' ),
					'all'
				);

				wp_enqueue_style(
					$this->plugin_name . '-member-portal',
					plugin_dir_url( __FILE__ ) . 'css/member-portal.css',
					array( $this->plugin_name, 'smartwizard' ),
					$this->get_asset_version( 'css/member-portal.css' ),
					'all'
				);
			}

			if ( $this->is_registration_page() ) {
				wp_enqueue_script(
					$this->plugin_name . '-registration',
					plugin_dir_url( __FILE__ ) . 'js/registration.js',
					array( 'jquery', $this->plugin_name ),
					$this->get_asset_version( 'js/registration.js' ),
					true
				);

				wp_localize_script(
					$this->plugin_name . '-registration',
					'stsrc_registration',
					array(
						'ajax_url'                => admin_url( 'admin-ajax.php' ),
						'nonce'                   => wp_create_nonce( 'stsrc_registration_nonce' ),
						'ref_cookie_name'         => 'stsrc_ref_code',
						'affiliate_discount_label'=> __( 'Referral Discount', 'smoketree-plugin' ),
					)
				);
			}
		}
	}

	/**
	 * Get cache-safe asset version using file modification time.
	 *
	 * @since    1.1.0
	 * @param    string $relative_path Relative path from public directory.
	 * @return   string
	 */
	private function get_asset_version( string $relative_path ): string {
		$absolute_path = plugin_dir_path( __FILE__ ) . ltrim( $relative_path, '/\\' );

		if ( file_exists( $absolute_path ) ) {
			return (string) filemtime( $absolute_path );
		}

		return $this->version;
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @since    1.0.0
	 * @return   bool    True if plugin page
	 */
	private function is_plugin_page(): bool {
		if ( is_front_page() && ! is_home() ) {
			return true;
		}

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
	 * Check if current page is the member portal.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	private function is_member_portal_page(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		$page_slug     = $post->post_name;
		$page_template = get_post_meta( $post->ID, '_wp_page_template', true );

		return 'member-portal' === $page_slug || 'member-portal.php' === $page_template;
	}

	/**
	 * Check if current page is the registration form.
	 *
	 * @since    1.4.2
	 * @return   bool
	 */
	private function is_registration_page(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		$page_slug     = $post->post_name;
		$page_template = get_post_meta( $post->ID, '_wp_page_template', true );

		return 'register' === $page_slug || 'registration-form.php' === $page_template;
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

			if ( $this->is_member_portal_page() ) {
				$portal_context = $this->get_member_portal_context();
				$minimum_payment = (float) get_option( 'stsrc_minimum_balance_payment', 10.00 );

				wp_enqueue_script(
					'smartwizard',
					plugin_dir_url( __FILE__ ) . 'vendor/smartwizard/jquery.smartWizard.min.js',
					array( 'jquery' ),
					$this->get_asset_version( 'vendor/smartwizard/jquery.smartWizard.min.js' ),
					true
				);

				wp_enqueue_script(
					$this->plugin_name . '-member-portal',
					plugin_dir_url( __FILE__ ) . 'js/member-portal.js',
					array( 'jquery', $this->plugin_name, 'smartwizard' ),
					$this->version,
					true
				);

				wp_enqueue_script(
					$this->plugin_name . '-balance-payment',
					plugin_dir_url( __FILE__ ) . 'js/balance-payment.js',
					array( 'jquery' ),
					$this->version,
					true
				);

				wp_localize_script(
					$this->plugin_name . '-balance-payment',
					'stsrcBalancePayment',
					array(
						'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
						'nonce'          => wp_create_nonce( 'stsrc_balance_payment_nonce' ),
						'memberId'       => (int) ( $portal_context['member_id'] ?? 0 ),
						'currentBalance' => (float) ( $portal_context['current_balance'] ?? 0.0 ),
						'minimumPayment' => $minimum_payment,
					)
				);
			}

			// Localize script for AJAX
			$localize = array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'stsrc_registration_nonce' ),
				'paymentPlanEnabled' => $this->is_payment_plan_enabled(),
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
			);
			if ( $this->is_member_portal_page() ) {
				$localize['portalNonce'] = wp_create_nonce( 'stsrc_portal_nonce' );
				$localize['renewal']    = array(
					'enabled'   => STSRC_Renewal_Helpers::is_renewal_enabled(),
					'seasonKey' => STSRC_Renewal_Helpers::get_season_key(),
					'nonce'     => wp_create_nonce( 'stsrc_renewal_nonce' ),
					'actions'   => array(
						'quote'  => 'stsrc_renewal_quote',
						'submit' => 'stsrc_renewal_submit',
					),
					'member'    => $portal_context,
				);
			}
			wp_localize_script(
				$this->plugin_name,
				'stsrcPublic',
				$localize
			);
		}
	}

	/**
	 * Get member context for localized member portal scripts.
	 *
	 * @since    1.1.0
	 * @return   array{member_id:int,current_balance:float}
	 */
	private function get_member_portal_context(): array {
		if ( ! is_user_logged_in() ) {
			return array(
				'member_id'       => 0,
				'current_balance' => 0.0,
			);
		}

		global $wpdb;

		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT member_id, balance_owed FROM {$wpdb->prefix}stsrc_members WHERE user_id = %d LIMIT 1",
				get_current_user_id()
			),
			ARRAY_A
		);

		if ( empty( $member ) ) {
			return array(
				'member_id'       => 0,
				'current_balance' => 0.0,
			);
		}

		return array(
			'member_id'       => (int) ( $member['member_id'] ?? 0 ),
			'current_balance' => (float) ( $member['balance_owed'] ?? 0.0 ),
		);
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
		add_filter( 'template_include', array( $this, 'override_front_page_template' ) );
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
			set_query_var( 'stsrc_payment_plan_enabled', $this->is_payment_plan_enabled() );
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
	 * Override the front page template.
	 *
	 * WordPress's template hierarchy resolves front-page.php from the theme
	 * before checking page templates, so the page_template filter never fires.
	 * The template_include filter fires last and can override everything.
	 *
	 * @since    1.0.0
	 * @param    string    $template    Resolved template path
	 * @return   string                 Template path
	 */
	public function override_front_page_template( string $template ): string {
		if ( is_front_page() && ! is_home() ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/front-page.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/**
	 * Handle login redirect.
	 *
	 * @since    1.0.0
	 * @param    string         $redirect_to           The redirect destination URL.
	 * @param    string         $requested_redirect_to The requested redirect destination URL passed as a parameter.
	 * @param    WP_User|WP_Error $user                WP_User object if login was successful, WP_Error object otherwise.
	 * @return   string                                The redirect URL.
	 */
	public function handle_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		// If login failed, return original redirect
		if ( is_wp_error( $user ) ) {
			return $redirect_to;
		}

		// If a specific redirect was requested, honor it
		if ( ! empty( $requested_redirect_to ) && $requested_redirect_to !== admin_url() ) {
			return $requested_redirect_to;
		}

		// Check if user has admin capabilities
		if ( isset( $user->ID ) && user_can( $user, 'manage_options' ) ) {
			// Admins go to wp-admin
			return admin_url();
		}

		// All other users (members) go to member portal
		return home_url( '/member-portal' );
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
			$login_page = get_page_by_path( 'login' );
			if ( $login_page && 'publish' === $login_page->post_status ) {
				wp_safe_redirect( home_url( '/login?loggedout=true' ) );
			} else {
				wp_safe_redirect( wp_login_url() . '?loggedout=true' );
			}
			exit;
		}
	}

	/**
	 * Redirect wp-login.php to custom login page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function redirect_wp_login(): void {
		global $pagenow;
		
		// Only redirect wp-login.php, not wp-admin
		if ( 'wp-login.php' === $pagenow ) {

			// Don't redirect if the custom login page doesn't exist yet
			$login_page = get_page_by_path( 'login' );
			if ( ! $login_page || 'publish' !== $login_page->post_status ) {
				return;
			}

			$redirect_to = '';
			
			// Preserve redirect_to parameter if present
			if ( isset( $_GET['redirect_to'] ) ) {
				$redirect_to = '?redirect_to=' . urlencode( wp_unslash( $_GET['redirect_to'] ) );
			}
			
			// Handle different actions
			if ( isset( $_GET['action'] ) ) {
				$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
				
				switch ( $action ) {
					case 'lostpassword':
					case 'retrievepassword':
						wp_safe_redirect( home_url( '/forgot-password' . $redirect_to ) );
						exit;
					
					case 'rp':
					case 'resetpass':
						// Preserve reset key and login parameters
						$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
						$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
						wp_safe_redirect( home_url( '/reset-password?token=' . $key . '&email=' . $login ) );
						exit;
					
					case 'logout':
						// Let the handle_logout_redirect method handle this
						return;
					
					case 'register':
						wp_safe_redirect( home_url( '/register' ) );
						exit;
					
					default:
						// For any other action, redirect to login
						wp_safe_redirect( home_url( '/login' . $redirect_to ) );
						exit;
				}
			}
			
			// Default: redirect to custom login page
			wp_safe_redirect( home_url( '/login' . $redirect_to ) );
			exit;
		}
	}

	/**
	 * Redirect /my-account to /member-portal.
	 *
	 * @since    1.3.0
	 * @return   void
	 */
	public function redirect_my_account(): void {
		if ( isset( $_SERVER['REQUEST_URI'] ) && preg_match( '#^/my-account/?(\?.*)?$#i', $_SERVER['REQUEST_URI'] ) ) {
			wp_safe_redirect( home_url( '/member-portal' ), 301 );
			exit;
		}
	}

	/**
	 * Hide the WordPress admin bar for members.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function hide_admin_bar_for_members(): void {
		if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
			show_admin_bar( false );
		}
	}

	/**
	 * Block wp-admin access for members and redirect to member portal.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function block_dashboard_for_members(): void {
		if ( is_admin() && ! current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
			wp_safe_redirect( home_url( '/member-portal' ) );
			exit;
		}
	}

	/**
	 * Customize password reset email.
	 *
	 * @since    1.0.0
	 * @param    string    $message    Default password reset email message.
	 * @param    string    $key        Password reset key.
	 * @param    string    $user_login User login name.
	 * @param    WP_User   $user_data  User data object.
	 * @return   string                Modified password reset email message.
	 */
	public function custom_password_reset_email( $message, $key, $user_login, $user_data ) {
		// Create custom reset URL pointing to our custom page
		$reset_url = home_url( '/reset-password?token=' . $key . '&email=' . rawurlencode( $user_data->user_email ) );
		
		$message = __( 'Someone has requested a password reset for the following account:', 'smoketree-plugin' ) . "\r\n\r\n";
		$message .= network_home_url( '/' ) . "\r\n\r\n";
		$message .= sprintf( __( 'Email: %s', 'smoketree-plugin' ), $user_data->user_email ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.', 'smoketree-plugin' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:', 'smoketree-plugin' ) . "\r\n\r\n";
		$message .= $reset_url . "\r\n";
		
		return $message;
	}

	/**
	 * Determine whether the payment-plan ("pay later") option is enabled.
	 *
	 * @since    1.2.0
	 * @return   bool
	 */
	private function is_payment_plan_enabled(): bool {
		$use_acf = function_exists( 'get_field' );
		$value   = $use_acf ? get_field( 'stsrc_payment_plan_enabled', 'option' ) : get_option( 'stsrc_payment_plan_enabled', '0' );

		return ! empty( $value ) && '0' !== (string) $value;
	}

}

