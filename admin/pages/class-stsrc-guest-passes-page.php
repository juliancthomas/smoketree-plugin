<?php

/**
 * Guest passes management page class
 *
 * Handles the guest passes admin page display and operations.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Guest passes management page class.
 *
 * Provides guest pass log viewing and balance adjustment interface.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Guest_Passes_Page {

	/**
	 * Render the guest passes page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		$request = wp_unslash( $_GET );

		// Get filters from request
		$filters = array();
		if ( ! empty( $request['member_id'] ) ) {
			$filters['member_id'] = intval( $request['member_id'] );
		}
		if ( ! empty( $request['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( $request['date_from'] );
		}
		if ( ! empty( $request['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( $request['date_to'] );
		}
		if ( ! empty( $request['payment_status'] ) ) {
			$filters['payment_status'] = sanitize_text_field( $request['payment_status'] );
		}
		if ( ! empty( $request['search'] ) ) {
			$filters['search'] = sanitize_text_field( $request['search'] );
		}

		// Get guest pass logs
		$logs = $this->get_guest_pass_logs( $filters );

		// Get analytics
		$analytics = $this->get_analytics( $filters );

		// Get all members for filter dropdown
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-member-db.php';
		$members = STSRC_Member_DB::get_members( array() );

		$data = array(
			'logs'      => $logs,
			'analytics' => $analytics,
			'filters'   => $filters,
			'members'   => $members,
		);

		// Include list template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/guest-passes-list.php';
	}

	/**
	 * Get guest pass logs with filters.
	 *
	 * @since    1.0.0
	 * @param    array    $filters    Filter array
	 * @return   array                Array of guest pass log entries
	 */
	private function get_guest_pass_logs( array $filters ): array {
		global $wpdb;

		$passes_table = $wpdb->prefix . 'stsrc_guest_passes';
		$members_table = $wpdb->prefix . 'stsrc_members';

		// Build WHERE clause
		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( ! empty( $filters['member_id'] ) ) {
			$where_clauses[] = 'gp.member_id = %d';
			$where_values[]  = intval( $filters['member_id'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'gp.created_at >= %s';
			$where_values[]  = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'gp.created_at <= %s';
			$where_values[]  = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		if ( ! empty( $filters['payment_status'] ) ) {
			$where_clauses[] = 'gp.payment_status = %s';
			$where_values[]  = sanitize_text_field( $filters['payment_status'] );
		}

		// Build query with member info
		$query = "SELECT gp.*, m.first_name, m.last_name, m.email 
			FROM {$passes_table} gp
			LEFT JOIN {$members_table} m ON gp.member_id = m.member_id
			WHERE " . implode( ' AND ', $where_clauses );

		// Handle search
		if ( ! empty( $filters['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$query .= $wpdb->prepare(
				' AND (m.first_name LIKE %s OR m.last_name LIKE %s OR m.email LIKE %s)',
				$search,
				$search,
				$search
			);
		}

		$query .= ' ORDER BY gp.created_at DESC LIMIT 500';

		// Execute query with prepared statement
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get guest pass analytics.
	 *
	 * @since    1.0.0
	 * @param    array    $filters    Filter array
	 * @return   array                Analytics data
	 */
	private function get_analytics( array $filters ): array {
		global $wpdb;

		$passes_table = $wpdb->prefix . 'stsrc_guest_passes';
		$members_table = $wpdb->prefix . 'stsrc_members';

		// Build WHERE clause
		$where_clauses = array( 'gp.payment_status = %s' );
		$where_values  = array( 'paid' );

		if ( ! empty( $filters['member_id'] ) ) {
			$where_clauses[] = 'gp.member_id = %d';
			$where_values[]  = intval( $filters['member_id'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'gp.created_at >= %s';
			$where_values[]  = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'gp.created_at <= %s';
			$where_values[]  = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Total purchased (paid passes that haven't been used)
		$purchased_query = "SELECT SUM(gp.quantity) FROM {$passes_table} gp WHERE {$where_sql} AND gp.used_at IS NULL";
		$total_purchased = $wpdb->get_var( $wpdb->prepare( $purchased_query, $where_values ) );

		// Total used (paid passes that have been used)
		$used_query = "SELECT SUM(gp.quantity) FROM {$passes_table} gp WHERE {$where_sql} AND gp.used_at IS NOT NULL";
		$total_used = $wpdb->get_var( $wpdb->prepare( $used_query, $where_values ) );

		// Total balance across all members
		$total_balance_query = "SELECT SUM(guest_pass_balance) FROM {$members_table}";
		$total_balance_values = array();
		if ( ! empty( $filters['member_id'] ) ) {
			$total_balance_query .= ' WHERE member_id = %d';
			$total_balance_values[] = intval( $filters['member_id'] );
		}
		if ( ! empty( $total_balance_values ) ) {
			$total_balance = $wpdb->get_var( $wpdb->prepare( $total_balance_query, $total_balance_values ) );
		} else {
			$total_balance = $wpdb->get_var( $total_balance_query );
		}

		// Total revenue
		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(gp.amount) FROM {$passes_table} gp WHERE {$where_sql}",
				$where_values
			)
		);

		return array(
			'total_purchased' => (int) $total_purchased,
			'total_used'      => (int) $total_used,
			'total_balance'   => (int) $total_balance,
			'total_revenue'   => (float) $total_revenue,
		);
	}
}

