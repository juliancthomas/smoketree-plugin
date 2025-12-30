<?php
/**
 * Template Name: Smoketree Member Portal
 * 
 * Member portal dashboard template.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/login?redirect_to=' . urlencode( home_url( '/member-portal' ) ) ) );
	exit;
}

// Get current user
$current_user = wp_get_current_user();

// Get member data
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-member-db.php';
$member = STSRC_Member_DB::get_member_by_email( $current_user->user_email );

if ( ! $member ) {
	wp_die( esc_html__( 'Member account not found. Please contact support.', 'smoketree-plugin' ) );
}

// Get membership type
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
$membership_type = STSRC_Membership_DB::get_membership_type( (int) $member['membership_type_id'] );

// Get family members
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-family-member-db.php';
$family_members = STSRC_Family_Member_DB::get_family_members( (int) $member['member_id'] );

// Get extra members
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-extra-member-db.php';
$extra_members = STSRC_Extra_Member_DB::get_extra_members( (int) $member['member_id'] );

// Get guest pass balance
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-guest-pass-db.php';
$guest_pass_balance = STSRC_Guest_Pass_DB::get_guest_pass_balance( (int) $member['member_id'] );

// Determine pool access capability
$has_pool_access = false;
if ( ! empty( $membership_type ) ) {
	$benefits = $membership_type['benefits'] ?? array();
	if ( is_string( $benefits ) ) {
		$decoded_benefits = json_decode( $benefits, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$benefits = $decoded_benefits;
		}
	}

	if ( is_array( $benefits ) ) {
		// Normalize values to help match legacy label storage
		$normalized_benefits = array_map(
			static function ( $benefit ) {
				return is_string( $benefit ) ? sanitize_key( $benefit ) : $benefit;
			},
			$benefits
		);

		$has_pool_access = in_array( 'pool_use_for_season', $normalized_benefits, true ) || in_array( 'pool_use_for_season', $benefits, true );
	}
}

// Get access codes
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-access-code-db.php';
$access_codes = STSRC_Access_Code_DB::get_active_access_codes( $has_pool_access ? null : false );

// Prepare data for partials
$data = array(
	'member'          => $member,
	'membership_type' => $membership_type,
	'family_members'  => $family_members,
	'extra_members'   => $extra_members,
	'guest_pass_balance' => $guest_pass_balance,
	'access_codes'    => $access_codes,
);

$request_params     = wp_unslash( $_GET );
$payment_status     = isset( $request_params['payment'] ) ? sanitize_text_field( $request_params['payment'] ) : '';
$extra_member_state = isset( $request_params['extra_member'] ) ? sanitize_text_field( $request_params['extra_member'] ) : '';

get_header();
?>

<div class="stsrc-member-portal">
	<div class="stsrc-container">
		<div class="stsrc-portal-header">
			<h1><?php echo esc_html__( 'Member Portal', 'smoketree-plugin' ); ?></h1>
			<div class="stsrc-portal-actions">
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/login?loggedout=true' ) ) ); ?>" class="stsrc-button stsrc-button-secondary">
					<?php echo esc_html__( 'Log Out', 'smoketree-plugin' ); ?>
				</a>
			</div>
		</div>

		<?php if ( 'success' === $payment_status ) : ?>
			<div class="stsrc-notice success">
				<p><?php echo esc_html__( 'Payment processed successfully!', 'smoketree-plugin' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( 'success' === $extra_member_state ) : ?>
			<div class="stsrc-notice success">
				<p><?php echo esc_html__( 'Extra member added successfully!', 'smoketree-plugin' ); ?></p>
			</div>
		<?php endif; ?>

		<div id="stsrc-portal-messages"></div>

		<!-- Member Profile Section -->
		<?php include plugin_dir_path( __FILE__ ) . '../partials/member-profile.php'; ?>

		<!-- Guest Pass Balance Section -->
		<?php include plugin_dir_path( __FILE__ ) . '../partials/guest-pass-balance.php'; ?>

		<!-- Family Members Section -->
		<?php include plugin_dir_path( __FILE__ ) . '../partials/family-members.php'; ?>

		<!-- Extra Members Section -->
		<?php if ( ! empty( $membership_type ) && 'household' === strtolower( $membership_type['name'] ) ) : ?>
			<?php include plugin_dir_path( __FILE__ ) . '../partials/extra-members.php'; ?>
		<?php endif; ?>

		<!-- Access Codes Section -->
		<?php if ( ! empty( $access_codes ) ) : ?>
			<div class="stsrc-portal-section">
				<h2><?php echo esc_html__( 'Access Codes', 'smoketree-plugin' ); ?></h2>
				<div class="stsrc-access-codes">
					<?php foreach ( $access_codes as $code ) : ?>
						<div class="stsrc-access-code-item">
							<strong><?php echo esc_html( $code['code'] ); ?></strong>
							<?php if ( ! empty( $code['description'] ) ) : ?>
								<p><?php echo esc_html( $code['description'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();

