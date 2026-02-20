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

		$balance_tools_result = $this->handle_balance_integrity_tools();

		// Check if ACF is available
		$acf_available = function_exists( 'acf_get_field_groups' );

		// Get current settings
		$settings = $this->get_settings();

		// Load recent auto-renewal payment logs
		require_once plugin_dir_path( dirname( __FILE__ ) ) . '../includes/database/class-stsrc-payment-log-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . '../includes/database/class-stsrc-member-db.php';

		$renewal_logs = STSRC_Payment_Log_DB::get_payment_logs( array( 'payment_type' => 'auto_renewal' ) );
		$renewal_logs = array_slice( $renewal_logs, 0, 50 );

		$renewal_member_ids = array_unique( array_filter( array_column( $renewal_logs, 'member_id' ) ) );
		$renewal_member_names = array();
		foreach ( $renewal_member_ids as $mid ) {
			$m = STSRC_Member_DB::get_member( (int) $mid );
			if ( $m ) {
				$renewal_member_names[ (int) $mid ] = $m['first_name'] . ' ' . $m['last_name'];
			}
		}

		$data = array(
			'settings'              => $settings,
			'acf_available'         => $acf_available,
			'balance_tools_result'  => $balance_tools_result,
			'renewal_logs'          => $renewal_logs,
			'renewal_member_names'  => $renewal_member_names,
		);

		// Include settings template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/settings-form.php';
	}

	/**
	 * Handle verify/recalculate balance integrity tool actions.
	 *
	 * @since    1.1.0
	 * @return   array|null Action result report, or null when no action was submitted.
	 */
	private function handle_balance_integrity_tools(): ?array {
		$post_data = wp_unslash( $_POST );
		$action    = sanitize_text_field( $post_data['stsrc_balance_tools_action'] ?? '' );

		if ( empty( $action ) ) {
			return null;
		}

		if ( ! in_array( $action, array( 'verify', 'recalculate' ), true ) ) {
			return array(
				'type'    => 'error',
				'message' => __( 'Invalid balance tool action.', 'smoketree-plugin' ),
			);
		}

		$nonce = sanitize_text_field( $post_data['stsrc_balance_tools_nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_balance_tools' ) ) {
			return array(
				'type'    => 'error',
				'message' => __( 'Security check failed. Please refresh and try again.', 'smoketree-plugin' ),
			);
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . '../includes/database/class-stsrc-member-db.php';

		$apply_fixes = ( 'recalculate' === $action );
		$report      = STSRC_Member_DB::recalculate_all_balances( $apply_fixes );

		$message = $apply_fixes
			? sprintf(
				/* translators: 1: checked count, 2: discrepancy count, 3: fixed count */
				__( 'Recalculation complete. Checked %1$d members, found %2$d discrepancies, fixed %3$d balances.', 'smoketree-plugin' ),
				(int) ( $report['checked'] ?? 0 ),
				(int) ( $report['discrepancies_count'] ?? 0 ),
				(int) ( $report['fixed_count'] ?? 0 )
			)
			: sprintf(
				/* translators: 1: checked count, 2: discrepancy count */
				__( 'Verification complete. Checked %1$d members and found %2$d discrepancies.', 'smoketree-plugin' ),
				(int) ( $report['checked'] ?? 0 ),
				(int) ( $report['discrepancies_count'] ?? 0 )
			);

		return array(
			'type'    => 'success',
			'action'  => $action,
			'message' => $message,
			'report'  => $report,
		);
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
		$settings['stripe_webhook_secret'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_webhook_secret', 'option' ) : get_option( 'stsrc_stripe_webhook_secret', '' );
		$settings['stripe_test_mode'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_mode', 'option' ) : get_option( 'stsrc_stripe_test_mode', '0' );
		$settings['stripe_test_publishable_key'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_publishable_key', 'option' ) : get_option( 'stsrc_stripe_test_publishable_key', '' );
		$settings['stripe_test_secret_key'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_secret_key', 'option' ) : get_option( 'stsrc_stripe_test_secret_key', '' );
		$settings['stripe_test_webhook_secret'] = function_exists( 'get_field' ) ? get_field( 'stsrc_stripe_test_webhook_secret', 'option' ) : get_option( 'stsrc_stripe_test_webhook_secret', '' );

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
		$settings['tax_rate'] = function_exists( 'get_field' ) ? get_field( 'stsrc_tax_rate', 'option' ) : get_option( 'stsrc_tax_rate', '0' );

		// Waiver Settings
		$settings['waiver_text'] = function_exists( 'get_field' ) ? get_field( 'stsrc_waiver_text', 'option' ) : get_option( 'stsrc_waiver_text', '' );

		// Auto-Renewal Agreement Settings
		$settings['auto_renewal_text'] = function_exists( 'get_field' ) ? get_field( 'stsrc_auto_renewal_text', 'option' ) : get_option( 'stsrc_auto_renewal_text', '' );

		// Transaction Fee Settings
		$settings['fee_card'] = function_exists( 'get_field' ) ? get_field( 'stsrc_fee_card', 'option' ) : get_option( 'stsrc_fee_card', '' );
		$settings['fee_bank_account'] = function_exists( 'get_field' ) ? get_field( 'stsrc_fee_bank_account', 'option' ) : get_option( 'stsrc_fee_bank_account', '' );
		$settings['fee_zelle'] = function_exists( 'get_field' ) ? get_field( 'stsrc_fee_zelle', 'option' ) : get_option( 'stsrc_fee_zelle', '' );
		$settings['fee_check'] = function_exists( 'get_field' ) ? get_field( 'stsrc_fee_check', 'option' ) : get_option( 'stsrc_fee_check', '' );
		$settings['fee_pay_later'] = function_exists( 'get_field' ) ? get_field( 'stsrc_fee_pay_later', 'option' ) : get_option( 'stsrc_fee_pay_later', '' );

		// Payment Settings (v1.1.0+)
		$settings['minimum_balance_payment'] = function_exists( 'get_field' ) ? get_field( 'stsrc_minimum_balance_payment', 'option' ) : get_option( 'stsrc_minimum_balance_payment', '10.00' );

		return $settings;
	}
}

