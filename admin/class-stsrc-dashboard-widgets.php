<?php
/**
 * Dashboard widgets class
 *
 * Registers and renders WordPress dashboard widgets.
 *
 * @link       https://smoketree.us
 * @since      1.1.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard widgets class.
 *
 * @since      1.1.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin
 */
class STSRC_Dashboard_Widgets {

	/**
	 * Register dashboard widgets.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function register_widgets(): void {
		wp_add_dashboard_widget(
			'stsrc_outstanding_balances_widget',
			__( 'Outstanding Balances', 'smoketree-plugin' ),
			array( $this, 'render_outstanding_balances_widget' )
		);
		wp_add_dashboard_widget(
			'stsrc_total_active_members_widget',
			__( 'Total Active Members', 'smoketree-plugin' ),
			array( $this, 'render_total_active_members_widget' )
		);
		wp_add_dashboard_widget(
			'stsrc_guest_pass_activity_widget',
			__( 'Guest Pass Activity', 'smoketree-plugin' ),
			array( $this, 'render_guest_pass_activity_widget' )
		);
		wp_add_dashboard_widget(
			'stsrc_recent_signups_widget',
			__( 'Recent Signups', 'smoketree-plugin' ),
			array( $this, 'render_recent_signups_widget' )
		);
		wp_add_dashboard_widget(
			'stsrc_recent_activity_widget',
			__( 'Recent Activity', 'smoketree-plugin' ),
			array( $this, 'render_recent_activity_widget' )
		);
	}

	/**
	 * Render Outstanding Balances dashboard widget.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function render_outstanding_balances_widget(): void {
		$stats = $this->get_outstanding_balance_stats();

		$member_count = (int) ( $stats['member_count'] ?? 0 );
		$total_due    = (float) ( $stats['total_due'] ?? 0.0 );
		$avg_due      = (float) ( $stats['avg_due'] ?? 0.0 );

		$members_url = admin_url( 'admin.php?page=stsrc-members&balance_status=outstanding' );
		?>
		<div class="stsrc-dashboard-widget">
			<div class="stsrc-dashboard-widget__stat">
				<div class="stsrc-dashboard-widget__value">$<?php echo esc_html( number_format( $total_due, 2 ) ); ?></div>
				<div class="stsrc-dashboard-widget__label"><?php echo esc_html__( 'Total Outstanding', 'smoketree-plugin' ); ?></div>
			</div>

			<div class="stsrc-dashboard-widget__grid">
				<div class="stsrc-dashboard-widget__item">
					<div class="stsrc-dashboard-widget__item-value"><?php echo esc_html( number_format( $member_count ) ); ?></div>
					<div class="stsrc-dashboard-widget__item-label"><?php echo esc_html__( 'Members Owing Balance', 'smoketree-plugin' ); ?></div>
				</div>
				<div class="stsrc-dashboard-widget__item">
					<div class="stsrc-dashboard-widget__item-value">$<?php echo esc_html( number_format( $avg_due, 2 ) ); ?></div>
					<div class="stsrc-dashboard-widget__item-label"><?php echo esc_html__( 'Average Balance', 'smoketree-plugin' ); ?></div>
				</div>
			</div>

			<div class="stsrc-dashboard-widget__footer">
				<a class="button button-primary" href="<?php echo esc_url( $members_url ); ?>">
					<?php echo esc_html__( 'View Members with Balance', 'smoketree-plugin' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Total Active Members dashboard widget.
	 *
	 * @since  1.18.0
	 * @return void
	 */
	public function render_total_active_members_widget(): void {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-member-db.php';

		$active_count  = STSRC_Member_DB::get_active_member_count();
		$members_url   = admin_url( 'admin.php?page=stsrc-members&status=active' );
		?>
		<div class="stsrc-dashboard-widget">
			<div class="stsrc-dashboard-widget__stat">
				<div class="stsrc-dashboard-widget__value"><?php echo esc_html( number_format( $active_count ) ); ?></div>
				<div class="stsrc-dashboard-widget__label"><?php esc_html_e( 'Active Members', 'smoketree-plugin' ); ?></div>
			</div>
			<div class="stsrc-dashboard-widget__footer">
				<a class="button button-primary" href="<?php echo esc_url( $members_url ); ?>">
					<?php esc_html_e( 'View Active Members', 'smoketree-plugin' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Guest Pass Activity dashboard widget.
	 *
	 * @since  1.18.0
	 * @return void
	 */
	public function render_guest_pass_activity_widget(): void {
		global $wpdb;

		$gp_table      = $wpdb->prefix . 'stsrc_guest_passes';
		$members_table = $wpdb->prefix . 'stsrc_members';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT gp.type, gp.quantity, gp.created_at,
				        m.first_name, m.last_name
				 FROM {$gp_table} gp
				 JOIN {$members_table} m ON m.member_id = gp.member_id
				 WHERE gp.type IN ('purchase','usage')
				 ORDER BY gp.created_at DESC
				 LIMIT %d",
				10
			),
			ARRAY_A
		);

		$gp_url = admin_url( 'admin.php?page=stsrc-guest-passes' );
		?>
		<div class="stsrc-dashboard-widget">
			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No guest pass activity yet.', 'smoketree-plugin' ); ?></p>
			<?php else : ?>
				<table class="stsrc-dashboard-widget__table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Member', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Type', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Qty', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Date', 'smoketree-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['first_name'] . ' ' . $row['last_name'] ); ?></td>
								<td><?php echo esc_html( ucfirst( $row['type'] ) ); ?></td>
								<td><?php echo esc_html( $row['quantity'] ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j', strtotime( $row['created_at'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<div class="stsrc-dashboard-widget__footer">
				<a class="button button-primary" href="<?php echo esc_url( $gp_url ); ?>">
					<?php esc_html_e( 'View Guest Passes', 'smoketree-plugin' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Recent Signups dashboard widget.
	 *
	 * @since  1.18.0
	 * @return void
	 */
	public function render_recent_signups_widget(): void {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-member-db.php';

		$signups = STSRC_Member_DB::get_members(
			array(
				'date_from' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'is_demo'   => 0,
			)
		);
		$signups = array_slice( $signups, 0, 10 );

		$members_url = admin_url( 'admin.php?page=stsrc-members' );
		?>
		<div class="stsrc-dashboard-widget">
			<?php if ( empty( $signups ) ) : ?>
				<p><?php esc_html_e( 'No new signups in the last 30 days.', 'smoketree-plugin' ); ?></p>
			<?php else : ?>
				<table class="stsrc-dashboard-widget__table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Member', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Status', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Joined', 'smoketree-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $signups as $member ) : ?>
							<tr>
								<td><?php echo esc_html( $member['first_name'] . ' ' . $member['last_name'] ); ?></td>
								<td><?php echo esc_html( ucfirst( $member['status'] ) ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j', strtotime( $member['created_at'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<div class="stsrc-dashboard-widget__footer">
				<a class="button button-primary" href="<?php echo esc_url( $members_url ); ?>">
					<?php esc_html_e( 'View All Members', 'smoketree-plugin' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Recent Activity dashboard widget.
	 *
	 * @since  1.18.0
	 * @return void
	 */
	public function render_recent_activity_widget(): void {
		global $wpdb;

		$tx_table      = $wpdb->prefix . 'stsrc_transactions';
		$members_table = $wpdb->prefix . 'stsrc_members';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.transaction_type, t.amount, t.description, t.created_at,
				        m.first_name, m.last_name
				 FROM {$tx_table} t
				 JOIN {$members_table} m ON m.member_id = t.member_id
				 ORDER BY t.created_at DESC
				 LIMIT %d",
				10
			),
			ARRAY_A
		);

		$members_url = admin_url( 'admin.php?page=stsrc-members' );
		?>
		<div class="stsrc-dashboard-widget">
			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No recent activity.', 'smoketree-plugin' ); ?></p>
			<?php else : ?>
				<table class="stsrc-dashboard-widget__table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Member', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Type', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'smoketree-plugin' ); ?></th>
							<th><?php esc_html_e( 'Date', 'smoketree-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['first_name'] . ' ' . $row['last_name'] ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['transaction_type'] ) ) ); ?></td>
								<td>$<?php echo esc_html( number_format( (float) $row['amount'], 2 ) ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j', strtotime( $row['created_at'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<div class="stsrc-dashboard-widget__footer">
				<a class="button button-primary" href="<?php echo esc_url( $members_url ); ?>">
					<?php esc_html_e( 'View Members', 'smoketree-plugin' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get outstanding balance statistics.
	 *
	 * @since  1.1.0
	 * @return array
	 */
	private function get_outstanding_balance_stats(): array {
		$cache_key = 'stsrc_outstanding_balance_stats';
		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/database/class-stsrc-member-db.php';

		$members = STSRC_Member_DB::get_members_with_balance( '>', 0 );
		$total_due = 0.0;

		foreach ( $members as $member ) {
			$total_due += (float) ( $member['balance_owed'] ?? 0 );
		}

		$member_count = count( $members );
		$avg_due = $member_count > 0 ? $total_due / $member_count : 0.0;

		$stats = array(
			'member_count' => $member_count,
			'total_due'    => $total_due,
			'avg_due'      => $avg_due,
		);

		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}
}
