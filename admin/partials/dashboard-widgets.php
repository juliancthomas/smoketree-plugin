<?php
/**
 * Dashboard widgets template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_count = $data['active_member_count'] ?? 0;
$recent_signups = $data['recent_signups'] ?? array();
$pending_count = $data['pending_count'] ?? 0;
$guest_pass_stats = $data['guest_pass_stats'] ?? array();
?>

<div class="wrap">
	<h1><?php echo esc_html__( 'Smoketree Club Dashboard', 'smoketree-plugin' ); ?></h1>

	<div class="stsrc-dashboard-widgets">
		<div class="stsrc-widget-row">
			<!-- Active Members Widget -->
			<div class="stsrc-widget">
				<div class="stsrc-widget-header">
					<h2><?php echo esc_html__( 'Active Members', 'smoketree-plugin' ); ?></h2>
				</div>
				<div class="stsrc-widget-content">
					<div class="stsrc-stat-number"><?php echo esc_html( number_format( $active_count ) ); ?></div>
					<p class="stsrc-stat-description"><?php echo esc_html__( 'Paid and active members', 'smoketree-plugin' ); ?></p>
				</div>
			</div>

			<!-- Pending Members Widget -->
			<div class="stsrc-widget">
				<div class="stsrc-widget-header">
					<h2><?php echo esc_html__( 'Pending Members', 'smoketree-plugin' ); ?></h2>
				</div>
				<div class="stsrc-widget-content">
					<div class="stsrc-stat-number"><?php echo esc_html( number_format( $pending_count ) ); ?></div>
					<p class="stsrc-stat-description"><?php echo esc_html__( 'Awaiting activation', 'smoketree-plugin' ); ?></p>
				</div>
			</div>

			<!-- Guest Pass Stats Widget -->
			<div class="stsrc-widget">
				<div class="stsrc-widget-header">
					<h2><?php echo esc_html__( 'Guest Passes', 'smoketree-plugin' ); ?></h2>
				</div>
				<div class="stsrc-widget-content">
					<div class="stsrc-stat-number"><?php echo esc_html( number_format( $guest_pass_stats['total_balance'] ?? 0 ) ); ?></div>
					<p class="stsrc-stat-description">
						<?php
						printf(
							/* translators: %1$s: purchased, %2$s: used */
							esc_html__( '%1$s purchased, %2$s used', 'smoketree-plugin' ),
							number_format( $guest_pass_stats['total_purchased'] ?? 0 ),
							number_format( $guest_pass_stats['total_used'] ?? 0 )
						);
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- Recent Signups Widget -->
		<div class="stsrc-widget-full">
			<div class="stsrc-widget-header">
				<h2><?php echo esc_html__( 'Recent Signups', 'smoketree-plugin' ); ?></h2>
			</div>
			<div class="stsrc-widget-content">
				<?php if ( ! empty( $recent_signups ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Name', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Payment Type', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Date', 'smoketree-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_signups as $member ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $member['first_name'] . ' ' . $member['last_name'] ); ?></strong>
									</td>
									<td><?php echo esc_html( $member['email'] ); ?></td>
									<td>
										<span class="stsrc-status stsrc-status-<?php echo esc_attr( $member['status'] ); ?>">
											<?php echo esc_html( ucfirst( $member['status'] ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $member['payment_type'] ) ) ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member['created_at'] ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php echo esc_html__( 'No recent signups.', 'smoketree-plugin' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

