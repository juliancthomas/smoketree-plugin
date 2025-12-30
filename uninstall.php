<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/database/class-stsrc-database.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/services/class-stsrc-auto-renewal-service.php';

// Drop all custom tables created by the plugin.
STSRC_Database::drop_tables();

// Clear scheduled cron events related to auto-renewal.
STSRC_Auto_Renewal_Service::clear_cron_events();

// Remove custom user role.
remove_role( 'stsrc_member' );

// Delete plugin options.
$option_keys = array(
	'stsrc_registration_enabled',
	'stsrc_payment_plan_enabled',
	'stsrc_stripe_publishable_key',
	'stsrc_stripe_secret_key',
	'stsrc_stripe_test_mode',
	'stsrc_stripe_test_publishable_key',
	'stsrc_stripe_test_secret_key',
	'stsrc_stripe_webhook_secret',
	'stsrc_secretary_email',
	'stsrc_season_renewal_date',
	'stsrc_captcha_provider',
	'stsrc_captcha_enabled',
	'stsrc_captcha_score_threshold',
	'stsrc_stripe_processed_events',
);

foreach ( array( 'recaptcha', 'hcaptcha' ) as $provider ) {
	$option_keys[] = 'stsrc_captcha_' . $provider . '_site_key';
	$option_keys[] = 'stsrc_captcha_' . $provider . '_secret_key';
}

foreach ( $option_keys as $option_key ) {
	delete_option( $option_key );

	if ( is_multisite() ) {
		delete_site_option( $option_key );
	}
}

// Clean up plugin transients.
global $wpdb;

$transient_like         = $wpdb->esc_like( '_transient_stsrc_' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_stsrc_' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$transient_like,
		$transient_timeout_like
	)
);

if ( is_multisite() ) {
	$site_transient_like         = $wpdb->esc_like( '_site_transient_stsrc_' ) . '%';
	$site_transient_timeout_like = $wpdb->esc_like( '_site_transient_timeout_stsrc_' ) . '%';

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$site_transient_like,
			$site_transient_timeout_like
		)
	);
}

// Remove password reset metadata for all users.
delete_metadata( 'user', 0, 'stsrc_password_reset_token', '', true );
delete_metadata( 'user', 0, 'stsrc_password_reset_expiration', '', true );