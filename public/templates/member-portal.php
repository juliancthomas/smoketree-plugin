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

// Handle magic-link auto-login via ?token= query param.
$portal_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
if ( $portal_token ) {
	$token_data = get_transient( 'stsrc_portal_token_' . $portal_token );

	if ( false !== $token_data && ! empty( $token_data['user_id'] ) ) {
		delete_transient( 'stsrc_portal_token_' . $portal_token );

		if ( ! is_user_logged_in() || (int) get_current_user_id() !== (int) $token_data['user_id'] ) {
			wp_set_current_user( $token_data['user_id'] );
			wp_set_auth_cookie( $token_data['user_id'], true );
		}

		wp_safe_redirect( home_url( '/member-portal/' ) );
		exit;
	}
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/login?redirect_to=' . urlencode( home_url( '/member-portal' ) ) ) );
	exit;
}

// Get current user
$current_user = wp_get_current_user();

// Member portal helper
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-stsrc-member-portal.php';

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
$deleted_family_members = STSRC_Family_Member_DB::get_deleted_by_member_id( (int) $member['member_id'] );
$deleted_extra_members  = STSRC_Extra_Member_DB::get_deleted_by_member_id( (int) $member['member_id'] );
$has_deleted_members    = ! empty( $deleted_family_members ) || ! empty( $deleted_extra_members );

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
$renewal_context = STSRC_Member_Portal::get_renewal_context( $member );
$data['renewal_context'] = $renewal_context;
$is_renewal_active = ! empty( $renewal_context['show_section'] );

$request_params        = wp_unslash( $_GET );
$payment_status        = isset( $request_params['payment'] ) ? sanitize_text_field( $request_params['payment'] ) : '';
$extra_member_state    = isset( $request_params['extra_member'] ) ? sanitize_text_field( $request_params['extra_member'] ) : '';
$registration_status   = isset( $request_params['registration'] ) ? sanitize_text_field( $request_params['registration'] ) : '';
$registration_pay_type = isset( $request_params['payment_type'] ) ? sanitize_text_field( $request_params['payment_type'] ) : '';
$renewal_status        = isset( $request_params['renewal'] ) ? sanitize_text_field( $request_params['renewal'] ) : '';
$renewal_pay_method    = isset( $request_params['payment_method'] ) ? sanitize_text_field( $request_params['payment_method'] ) : '';

// Load plugin header
require_once plugin_dir_path( __FILE__ ) . 'header.php';
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

		<?php STSRC_Member_Portal::render_registration_notice( $registration_status, $registration_pay_type ); ?>
		<?php STSRC_Member_Portal::render_payment_status_notice( $payment_status ); ?>
		<?php STSRC_Member_Portal::render_renewal_pending_notice( $renewal_status, $renewal_pay_method ); ?>

		<?php if ( 'success' === $extra_member_state ) : ?>
			<div class="stsrc-notice success">
				<p><?php echo esc_html__( 'Extra member added successfully!', 'smoketree-plugin' ); ?></p>
			</div>
		<?php endif; ?>

		<div id="stsrc-portal-messages"></div>

		<!-- Outstanding Balance Card -->
		<?php STSRC_Member_Portal::render_balance_card( (int) $member['member_id'] ); ?>
		<?php include plugin_dir_path( __FILE__ ) . '../partials/pay-balance-modal.php'; ?>

		<!-- Renewal Section -->
		<?php if ( ! empty( $renewal_context['show_section'] ) ) : ?>
			<?php
			$renewal_partial = plugin_dir_path( __FILE__ ) . '../partials/renewal-section.php';
			if ( file_exists( $renewal_partial ) ) {
				include $renewal_partial;
			}
			?>
		<?php endif; ?>

		<!-- Member Profile Section -->
		<?php include plugin_dir_path( __FILE__ ) . '../partials/member-profile.php'; ?>

		<?php if ( ! $is_renewal_active ) : ?>
			<!-- Guest Pass Balance Section -->
			<?php include plugin_dir_path( __FILE__ ) . '../partials/guest-pass-balance.php'; ?>

			<!-- Family Members Section (Household & Duo only) -->
			<?php if ( ! empty( $membership_type ) && in_array( strtolower( $membership_type['name'] ), array( 'household', 'duo' ), true ) ) : ?>
				<?php include plugin_dir_path( __FILE__ ) . '../partials/family-members.php'; ?>
			<?php endif; ?>

			<!-- Extra Members Section -->
			<?php if ( ! empty( $membership_type ) && 'household' === strtolower( $membership_type['name'] ) ) : ?>
				<?php include plugin_dir_path( __FILE__ ) . '../partials/extra-members.php'; ?>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Restore Deleted Members Section -->
		<?php if ( $has_deleted_members ) : ?>
			<div class="stsrc-portal-section">
				<h2><?php echo esc_html__( 'Restore Previous Members', 'smoketree-plugin' ); ?></h2>
				<p class="stsrc-description"><?php echo esc_html__( 'Restore previously deleted family or extra members to make them active again.', 'smoketree-plugin' ); ?></p>

				<?php if ( ! empty( $deleted_family_members ) ) : ?>
					<h3><?php echo esc_html__( 'Deleted Family Members', 'smoketree-plugin' ); ?></h3>
					<div class="stsrc-family-members-list">
						<?php foreach ( $deleted_family_members as $family_member ) : ?>
							<div class="stsrc-family-member-item">
								<div class="stsrc-member-details">
									<strong><?php echo esc_html( $family_member['first_name'] . ' ' . $family_member['last_name'] ); ?></strong>
									<?php if ( ! empty( $family_member['email'] ) ) : ?>
										<span class="stsrc-member-email"><?php echo esc_html( $family_member['email'] ); ?></span>
									<?php endif; ?>
								</div>
								<div class="stsrc-member-actions">
									<button
										type="button"
										class="stsrc-button stsrc-button-secondary stsrc-restore-family-member"
										data-id="<?php echo esc_attr( $family_member['family_member_id'] ); ?>">
										<?php echo esc_html__( 'Restore', 'smoketree-plugin' ); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $deleted_extra_members ) ) : ?>
					<h3><?php echo esc_html__( 'Deleted Extra Members', 'smoketree-plugin' ); ?></h3>
					<div class="stsrc-extra-members-list">
						<?php foreach ( $deleted_extra_members as $extra_member ) : ?>
							<div class="stsrc-extra-member-item">
								<div class="stsrc-member-details">
									<strong><?php echo esc_html( $extra_member['first_name'] . ' ' . $extra_member['last_name'] ); ?></strong>
									<?php if ( ! empty( $extra_member['email'] ) ) : ?>
										<span class="stsrc-member-email"><?php echo esc_html( $extra_member['email'] ); ?></span>
									<?php endif; ?>
								</div>
								<div class="stsrc-member-actions">
									<button
										type="button"
										class="stsrc-button stsrc-button-secondary stsrc-restore-extra-member"
										data-id="<?php echo esc_attr( $extra_member['extra_member_id'] ); ?>">
										<?php echo esc_html__( 'Restore', 'smoketree-plugin' ); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! $is_renewal_active ) : ?>
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

			<!-- Transaction History Section -->
			<?php include plugin_dir_path( __FILE__ ) . '../partials/member-transaction-history.php'; ?>
		<?php endif; ?>
	</div>
</div>

<?php
// Load plugin footer
require_once plugin_dir_path( __FILE__ ) . 'footer.php';

