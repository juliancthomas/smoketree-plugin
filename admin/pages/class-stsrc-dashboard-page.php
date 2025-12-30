<?php

/**
 * Dashboard page class
 *
 * Handles the admin dashboard page display.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Dashboard page class.
 *
 * Provides dashboard widgets and overview.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Dashboard_Page {

	/**
	 * Render the dashboard page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		// Get dashboard data
		$data = $this->get_dashboard_data();

		// Include dashboard template (data is available as $data variable)
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/dashboard-widgets.php';
	}

	/**
	 * Get dashboard data.
	 *
	 * @since    1.0.0
	 * @return   array    Dashboard data array
	 */
	private function get_dashboard_data(): array {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-member-db.php';

		// Get active member count (cached)
		$cache_key = 'stsrc_active_member_count';
		$active_count = get_transient( $cache_key );
		if ( false === $active_count ) {
			$active_count = STSRC_Member_DB::get_active_member_count();
			set_transient( $cache_key, $active_count, 5 * MINUTE_IN_SECONDS );
		}

		// Get recent signups (last 10)
		$recent_signups = STSRC_Member_DB::get_members(
			array(
				'date_from' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			)
		);
		$recent_signups = array_slice( $recent_signups, 0, 10 );

		// Get pending members count
		$pending_members = STSRC_Member_DB::get_members( array( 'status' => 'pending' ) );
		$pending_count = count( $pending_members );

		// Get guest pass stats
		global $wpdb;
		$guest_pass_table = $wpdb->prefix . 'stsrc_guest_passes';
		$total_purchased = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(quantity) FROM {$guest_pass_table} WHERE payment_status = %s",
				'paid'
			)
		);
		$total_used = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(quantity) FROM {$guest_pass_table} WHERE used_at IS NOT NULL AND payment_status = %s",
				'paid'
			)
		);

		$members_table = $wpdb->prefix . 'stsrc_members';
		$total_balance = $wpdb->get_var(
			"SELECT SUM(guest_pass_balance) FROM {$members_table}"
		);

		return array(
			'active_member_count' => (int) $active_count,
			'recent_signups'      => $recent_signups,
			'pending_count'       => $pending_count,
			'guest_pass_stats'    => array(
				'total_purchased' => (int) $total_purchased,
				'total_used'      => (int) $total_used,
				'total_balance'   => (int) $total_balance,
			),
		);
	}
}

