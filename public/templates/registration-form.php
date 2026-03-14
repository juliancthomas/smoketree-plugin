<?php
/**
 * Template Name: Smoketree Registration
 * 
 * Registration page template for new member signups.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if registration is enabled (ACF field falls back to wp_options)
$use_acf = function_exists( 'get_field' );
$registration_enabled = $use_acf
	? get_field( 'stsrc_registration_enabled', 'option' )
	: get_option( 'stsrc_registration_enabled', '1' );
$registration_disabled = empty( $registration_enabled ) || '0' === $registration_enabled;

// Get membership types
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
$membership_types = STSRC_Membership_DB::get_all_membership_types( true );

// Get CAPTCHA service
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/services/class-stsrc-captcha-service.php';
$captcha_service = new STSRC_Captcha_Service();
$captcha_enabled = $captcha_service->is_enabled();
$captcha_site_key = $captcha_service->get_site_key();
$captcha_provider = $captcha_service->get_provider();

// Get settings for waiver and auto-renewal
$use_acf = function_exists( 'get_field' );
$waiver_text = $use_acf ? get_field( 'stsrc_waiver_text', 'option' ) : get_option( 'stsrc_waiver_text', '' );
$auto_renewal_text = $use_acf ? get_field( 'stsrc_auto_renewal_text', 'option' ) : get_option( 'stsrc_auto_renewal_text', '' );
$extra_member_fee = 50.00;

// Google Places API key for address autocomplete
$google_places_api_key = $use_acf ? get_field( 'stsrc_google_places_api_key', 'option' ) : get_option( 'stsrc_google_places_api_key', '' );

$request_params = wp_unslash( $_GET );
$payment_flag   = isset( $request_params['payment'] ) ? sanitize_text_field( $request_params['payment'] ) : '';
$payment_plan_enabled = get_query_var( 'stsrc_payment_plan_enabled', null );
if ( null === $payment_plan_enabled ) {
	$payment_plan_enabled = $use_acf
		? get_field( 'stsrc_payment_plan_enabled', 'option' )
		: get_option( 'stsrc_payment_plan_enabled', '0' );
}
$payment_plan_enabled = ! empty( $payment_plan_enabled ) && '0' !== (string) $payment_plan_enabled;

// Load plugin header
require_once plugin_dir_path( __FILE__ ) . 'header.php';
?>

<div class="stsrc-registration-page">
	<div class="stsrc-container">
		<h1><?php echo esc_html__( 'Become a Member', 'smoketree-plugin' ); ?></h1>
		
		<?php if ( 'cancelled' === $payment_flag ) : ?>
			<div class="stsrc-notice error">
				<p><?php echo esc_html__( 'Payment was cancelled. Please try again.', 'smoketree-plugin' ); ?></p>
			</div>
		<?php endif; ?>

		<?php include plugin_dir_path( __FILE__ ) . '../partials/registration-form.php'; ?>
	</div>
</div>

<?php
// Load plugin footer
require_once plugin_dir_path( __FILE__ ) . 'footer.php';

