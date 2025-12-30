<?php

/**
 * Settings page class
 *
 * Handles the settings admin page display and operations.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Settings page class.
 *
 * Provides settings interface with ACF integration.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Settings_Page {

	/**
	 * Render the settings page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		// Check if ACF is available
		$acf_available = function_exists( 'acf_get_field_groups' );

		// Get current settings
		$settings = $this->get_settings();

		$data = array(
			'settings'      => $settings,
			'acf_available' => $acf_available,
		);

		// Include settings template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/settings-form.php';
	}

	/**
	 * Get current settings values.
	 *
	 * @since    1.0.0
	 * @return   array    Settings array
	 */
	private function get_settings(): array {
		// Try to get from ACF options page first, then fall back to WordPress options
		$settings = array();

		// Stripe Settings
		$settings['stripe_publishable_key'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_publishable_key', 'option' ) : get_option( 'stsrc_stripe_publishable_key', '' );
		$settings['stripe_secret_key'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_secret_key', 'option' ) : get_option( 'stsrc_stripe_secret_key', '' );
		$settings['stripe_test_mode'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_mode', 'option' ) : get_option( 'stsrc_stripe_test_mode', '0' );
		$settings['stripe_test_publishable_key'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_publishable_key', 'option' ) : get_option( 'stsrc_stripe_test_publishable_key', '' );
		$settings['stripe_test_secret_key'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_secret_key', 'option' ) : get_option( 'stsrc_stripe_test_secret_key', '' );
		$settings['stripe_webhook_secret'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_webhook_secret', 'option' ) : get_option( 'stsrc_stripe_webhook_secret', '' );

		// CAPTCHA Settings
		$captcha_provider = function_exists( 'get_field' ) ? get_field( 'stsrc_captcha_provider', 'option' ) : get_option( 'stsrc_captcha_provider', 'recaptcha' );
		$settings['captcha_provider'] = $captcha_provider;
		$settings['captcha_enabled'] = function_exists( 'get_field' ) ? get_field( 'stsrc_captcha_enabled', 'option' ) : get_option( 'stsrc_captcha_enabled', '0' );
		
		// Get CAPTCHA keys based on provider
		$captcha_site_key_option = 'stsrc_captcha_' . $captcha_provider . '_site_key';
		$captcha_secret_key_option = 'stsrc_captcha_' . $captcha_provider . '_secret_key';
		$settings['captcha_site_key'] = function_exists( 'get_field' ) ? get_field( $captcha_site_key_option, 'option' ) : get_option( $captcha_site_key_option, '' );
		$settings['captcha_secret_key'] = function_exists( 'get_field' ) ? get_field( $captcha_secret_key_option, 'option' ) : get_option( $captcha_secret_key_option, '' );

		// General Settings
		$settings['registration_enabled'] = function_exists( 'get_field' ) ? get_field( 'stsrc_registration_enabled', 'option' ) : get_option( 'stsrc_registration_enabled', '1' );
		$settings['payment_plan_enabled'] = function_exists( 'get_field' ) ? get_field( 'stsrc_payment_plan_enabled', 'option' ) : get_option( 'stsrc_payment_plan_enabled', '0' );
		$settings['secretary_email'] = function_exists( 'get_field' ) ? get_field( 'stsrc_secretary_email', 'option' ) : get_option( 'stsrc_secretary_email', '' );
		$settings['season_renewal_date'] = function_exists( 'get_field' ) ? get_field( 'stsrc_season_renewal_date', 'option' ) : get_option( 'stsrc_season_renewal_date', '' );

		return $settings;
	}
}

