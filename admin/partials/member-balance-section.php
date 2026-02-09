<?php
/**
 * Member balance section partial
 *
 * Displays account balance information on the admin member edit page.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Support both $balance_section_data and $data for flexibility
if ( isset( $balance_section_data['member_id'] ) ) {
	$member_id = $balance_section_data['member_id'];
} elseif ( isset( $data['member_id'] ) ) {
	$member_id = $data['member_id'];
} else {
	$member_id = 0;
}

if ( empty( $member_id ) ) {
	return;
}

// Load required classes
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/services/class-stsrc-balance-service.php';

// Get balance display data
$balance_data = STSRC_Balance_Service::get_balance_display_data( $member_id );

if ( null === $balance_data ) {
	return;
}

// Extract data
$membership_name   = $balance_data['membership_type_name'] ?? 'Unknown';
$original_price    = $balance_data['original_price'] ?? 0.00;
$total_paid        = $balance_data['total_paid'] ?? 0.00;
$total_adjustments = $balance_data['total_adjustments'] ?? 0.00;
$balance_owed      = $balance_data['balance_owed'] ?? 0.00;
$final_method      = $balance_data['final_payment_method'] ?? null;
$status_badge      = $balance_data['status_badge'] ?? 'outstanding';
$status_label      = $balance_data['status_label'] ?? 'Outstanding Balance';
$member_status     = $balance_data['member_status'] ?? 'pending';

// Determine badge colors
$badge_color = match ( $status_badge ) {
	'paid_in_full' => '#4caf50',
	'overpaid'     => '#ff9800',
	'outstanding'  => '#f44336',
	default        => '#757575',
};

// Determine balance display color
$balance_color = $balance_owed > 0 ? '#f44336' : ( $balance_owed < 0 ? '#ff9800' : '#4caf50' );
?>

<div class="stsrc-form-section" id="stsrc-balance-section">
	<h2><?php echo esc_html__( 'Account Balance', 'smoketree-plugin' ); ?></h2>
	
	<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
		<!-- Status Badge -->
		<div style="margin-bottom: 15px;">
			<span style="display: inline-block; background: <?php echo esc_attr( $badge_color ); ?>; color: #fff; padding: 4px 12px; border-radius: 3px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
				<?php echo esc_html( $status_label ); ?>
			</span>
		</div>

		<!-- Balance Summary Grid -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
			<!-- Membership Type -->
			<div>
				<div style="font-size: 12px; color: #646970; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">
					<?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?>
				</div>
				<div style="font-size: 16px; color: #1d2327; font-weight: 500;">
					<?php echo esc_html( $membership_name ); ?>
				</div>
			</div>

			<!-- Original Price -->
			<div>
				<div style="font-size: 12px; color: #646970; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">
					<?php echo esc_html__( 'Original Price', 'smoketree-plugin' ); ?>
				</div>
				<div style="font-size: 16px; color: #1d2327; font-weight: 500;">
					$<?php echo esc_html( number_format( $original_price, 2 ) ); ?>
				</div>
			</div>

			<!-- Total Paid -->
			<div>
				<div style="font-size: 12px; color: #646970; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">
					<?php echo esc_html__( 'Total Paid', 'smoketree-plugin' ); ?>
				</div>
				<div style="font-size: 16px; color: #4caf50; font-weight: 500;">
					$<?php echo esc_html( number_format( $total_paid, 2 ) ); ?>
				</div>
			</div>

			<!-- Total Adjustments -->
			<?php if ( abs( $total_adjustments ) > 0.01 ) : ?>
			<div>
				<div style="font-size: 12px; color: #646970; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">
					<?php echo esc_html__( 'Adjustments', 'smoketree-plugin' ); ?>
				</div>
				<div style="font-size: 16px; color: <?php echo $total_adjustments > 0 ? '#f44336' : '#4caf50'; ?>; font-weight: 500;">
					<?php echo $total_adjustments > 0 ? '+' : ''; ?>$<?php echo esc_html( number_format( $total_adjustments, 2 ) ); ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Final Payment Method -->
			<?php if ( ! empty( $final_method ) ) : ?>
			<div>
				<div style="font-size: 12px; color: #646970; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">
					<?php echo esc_html__( 'Last Payment Method', 'smoketree-plugin' ); ?>
				</div>
				<div style="font-size: 16px; color: #1d2327; font-weight: 500;">
					<?php
					$method_labels = array(
						'card'            => __( 'Credit Card', 'smoketree-plugin' ),
						'us_bank_account' => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
						'check'           => __( 'Check', 'smoketree-plugin' ),
						'zelle'           => __( 'Zelle', 'smoketree-plugin' ),
						'cash'            => __( 'Cash', 'smoketree-plugin' ),
					);
					echo esc_html( $method_labels[ $final_method ] ?? ucfirst( $final_method ) );
					?>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<!-- Current Balance Owed (Prominent) -->
		<div style="background: #f0f0f1; border-radius: 4px; padding: 20px; text-align: center; margin-top: 20px;">
			<div style="font-size: 14px; color: #646970; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">
				<?php echo esc_html__( 'Current Balance Owed', 'smoketree-plugin' ); ?>
			</div>
			<div style="font-size: 32px; color: <?php echo esc_attr( $balance_color ); ?>; font-weight: 700;">
				<?php if ( $balance_owed < 0 ) : ?>
					-$<?php echo esc_html( number_format( abs( $balance_owed ), 2 ) ); ?>
					<span style="font-size: 14px; color: #646970; font-weight: 400; display: block; margin-top: 5px;">
						(<?php echo esc_html__( 'Overpaid - Refund Due', 'smoketree-plugin' ); ?>)
					</span>
				<?php else : ?>
					$<?php echo esc_html( number_format( $balance_owed, 2 ) ); ?>
				<?php endif; ?>
			</div>
			<?php if ( $balance_owed <= 0 && $balance_owed >= -0.01 ) : ?>
				<div style="font-size: 14px; color: #4caf50; font-weight: 600; margin-top: 10px;">
					✓ <?php echo esc_html__( 'Paid in Full', 'smoketree-plugin' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Auto-Activation Note -->
		<?php if ( 'pending' === $member_status && $balance_owed > 0 ) : ?>
			<div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2271b1; font-size: 13px; color: #1d2327;">
				<strong><?php echo esc_html__( 'Note:', 'smoketree-plugin' ); ?></strong>
				<?php echo esc_html__( 'This member will be automatically activated when their balance reaches $0.', 'smoketree-plugin' ); ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- Action Buttons -->
	<div style="display: flex; gap: 10px; flex-wrap: wrap;">
		<button type="button" class="button button-primary" id="stsrc-view-transactions-btn">
			<?php echo esc_html__( 'View Transaction History', 'smoketree-plugin' ); ?>
		</button>
		
		<?php if ( $balance_owed > 0.01 ) : ?>
			<button type="button" class="button" id="stsrc-record-payment-btn">
				<?php echo esc_html__( 'Record Manual Payment', 'smoketree-plugin' ); ?>
			</button>
		<?php endif; ?>
		
		<button type="button" class="button" id="stsrc-adjust-balance-btn">
			<?php echo esc_html__( 'Adjust Balance', 'smoketree-plugin' ); ?>
		</button>
	</div>
</div>

<style>
#stsrc-balance-section {
	border-top: 3px solid #2271b1;
	padding-top: 15px;
	margin-top: 20px;
}

#stsrc-balance-section h2 {
	color: #2271b1;
}

@media (max-width: 782px) {
	#stsrc-balance-section > div > div[style*="grid"] {
		grid-template-columns: 1fr !important;
	}
}
</style>

