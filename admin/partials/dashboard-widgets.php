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
$auto_renewal_stats = $data['auto_renewal_stats'] ?? array();
$ar_opted_in = $auto_renewal_stats['opted_in_count'] ?? 0;
$ar_renewal_date = $auto_renewal_stats['season_renewal_date'] ?? '';
$ar_days_until = '';
if ( ! empty( $ar_renewal_date ) ) {
	$ar_ts = strtotime( $ar_renewal_date );
	if ( false !== $ar_ts ) {
		$ar_diff = (int) ceil( ( $ar_ts - time() ) / DAY_IN_SECONDS );
		if ( $ar_diff > 0 ) {
			$ar_days_until = sprintf( _n( '%s day away', '%s days away', $ar_diff, 'smoketree-plugin' ), number_format_i18n( $ar_diff ) );
		} elseif ( 0 === $ar_diff ) {
			$ar_days_until = __( 'Today', 'smoketree-plugin' );
		} else {
			$ar_days_until = sprintf( _n( '%s day ago', '%s days ago', abs( $ar_diff ), 'smoketree-plugin' ), number_format_i18n( abs( $ar_diff ) ) );
		}
	}
}
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

			<!-- Auto-Renewal Widget -->
			<div class="stsrc-widget">
				<div class="stsrc-widget-header">
					<h2><?php echo esc_html__( 'Auto-Renewal', 'smoketree-plugin' ); ?></h2>
				</div>
				<div class="stsrc-widget-content">
					<div class="stsrc-stat-number"><?php echo esc_html( number_format( $ar_opted_in ) ); ?></div>
					<p class="stsrc-stat-description">
						<?php echo esc_html__( 'Active members opted in', 'smoketree-plugin' ); ?>
					</p>
					<?php if ( ! empty( $ar_renewal_date ) ) : ?>
						<p class="stsrc-stat-description" style="margin-top: 6px;">
							<strong><?php echo esc_html__( 'Renewal date:', 'smoketree-plugin' ); ?></strong>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $ar_renewal_date ) ) ); ?>
							<?php if ( ! empty( $ar_days_until ) ) : ?>
								<br><span style="color: #646970;">(<?php echo esc_html( $ar_days_until ); ?>)</span>
							<?php endif; ?>
						</p>
					<?php else : ?>
						<p class="stsrc-stat-description" style="margin-top: 6px; color: #d63638;">
							<?php echo esc_html__( 'No renewal date set.', 'smoketree-plugin' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-settings' ) ); ?>">
								<?php echo esc_html__( 'Configure', 'smoketree-plugin' ); ?>
							</a>
						</p>
					<?php endif; ?>
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

