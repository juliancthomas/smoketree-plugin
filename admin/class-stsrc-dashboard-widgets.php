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
