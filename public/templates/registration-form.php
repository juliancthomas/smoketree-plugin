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

// Check if registration is enabled
$registration_enabled = get_option( 'stsrc_registration_enabled', '1' );
if ( '0' === $registration_enabled || ! $registration_enabled ) {
	wp_die( esc_html__( 'Registration is currently disabled.', 'smoketree-plugin' ) );
}

// Get membership types
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
$membership_types = STSRC_Membership_DB::get_all_membership_types( true );

// Get CAPTCHA service
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/services/class-stsrc-captcha-service.php';
$captcha_service = new STSRC_Captcha_Service();
$captcha_enabled = $captcha_service->is_enabled();
$captcha_site_key = $captcha_service->get_site_key();
$captcha_provider = $captcha_service->get_provider();

// Get settings for waiver and fees
$use_acf = function_exists( 'get_field' );
$waiver_text = $use_acf ? get_field( 'stsrc_waiver_text', 'option' ) : get_option( 'stsrc_waiver_text', '' );
$tax_rate = floatval( $use_acf ? get_field( 'stsrc_tax_rate', 'option' ) : get_option( 'stsrc_tax_rate', '0' ) );
$transaction_fees = array(
	'card'         => $use_acf ? get_field( 'stsrc_fee_card', 'option' ) : get_option( 'stsrc_fee_card', '' ),
	'bank_account' => $use_acf ? get_field( 'stsrc_fee_bank_account', 'option' ) : get_option( 'stsrc_fee_bank_account', '' ),
	'zelle'        => $use_acf ? get_field( 'stsrc_fee_zelle', 'option' ) : get_option( 'stsrc_fee_zelle', '' ),
	'check'        => $use_acf ? get_field( 'stsrc_fee_check', 'option' ) : get_option( 'stsrc_fee_check', '' ),
	'pay_later'    => $use_acf ? get_field( 'stsrc_fee_pay_later', 'option' ) : get_option( 'stsrc_fee_pay_later', '' ),
);
$extra_member_fee = 50.00; // Fee per extra member

$request_params = wp_unslash( $_GET );
$payment_flag   = isset( $request_params['payment'] ) ? sanitize_text_field( $request_params['payment'] ) : '';

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

