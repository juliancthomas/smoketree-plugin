<?php

/**
 * Members management page class
 *
 * Handles the members admin page display and operations.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Members management page class.
 *
 * Provides member CRUD operations interface.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Members_Page {

	/**
	 * Render the members page.
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

		// Handle actions
		$action    = isset( $request['action'] ) ? sanitize_text_field( $request['action'] ) : 'list';
		$member_id = isset( $request['member_id'] ) ? intval( $request['member_id'] ) : 0;

		switch ( $action ) {
			case 'edit':
				$this->render_edit_form( $member_id );
				break;
			case 'view':
				$this->render_detail_view( $member_id );
				break;
			case 'list':
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render members list.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_list(): void {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-guest-pass-db.php';

		// Get filters from request
		$filters = array();
		$request = wp_unslash( $_GET );
		if ( ! empty( $request['membership_type_id'] ) ) {
			$filters['membership_type_id'] = intval( $request['membership_type_id'] );
		}
		if ( ! empty( $request['status'] ) ) {
			$filters['status'] = sanitize_text_field( $request['status'] );
		}
		if ( ! empty( $request['payment_type'] ) ) {
			$filters['payment_type'] = sanitize_text_field( $request['payment_type'] );
		}
		if ( ! empty( $request['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( $request['date_from'] );
		}
		if ( ! empty( $request['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( $request['date_to'] );
		}
		if ( ! empty( $request['search'] ) ) {
			$filters['search'] = sanitize_text_field( $request['search'] );
		}
		if ( ! empty( $request['balance_status'] ) ) {
			$filters['balance_status'] = sanitize_text_field( $request['balance_status'] );
		}
		if ( isset( $request['auto_renewal'] ) && '' !== $request['auto_renewal'] ) {
			$filters['auto_renewal'] = sanitize_text_field( $request['auto_renewal'] );
		}
		$demo_filter = isset( $request['demo_filter'] ) ? sanitize_key( $request['demo_filter'] ) : 'all';
		if ( ! in_array( $demo_filter, array( 'all', 'real', 'demo' ), true ) ) {
			$demo_filter = 'all';
		}
		$filters['demo_filter'] = $demo_filter;
		if ( 'real' === $demo_filter ) {
			$filters['is_demo'] = 0;
		} elseif ( 'demo' === $demo_filter ) {
			$filters['is_demo'] = 1;
		}
		$filters['show_deleted'] = ! empty( $request['show_deleted'] ) ? '1' : '0';

		$orderby = isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'created_at';
		$order   = isset( $request['order'] ) ? strtoupper( sanitize_text_field( $request['order'] ) ) : 'DESC';
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}
		$filters['orderby'] = $orderby;
		$filters['order']   = $order;

		// Get members
		$members = STSRC_Member_DB::get_members( $filters );

		// Hide deleted members by default unless explicitly requested.
		if ( '1' !== ( $filters['show_deleted'] ?? '0' ) && empty( $filters['status'] ) ) {
			$members = array_values(
				array_filter(
					$members,
					static function( array $member ): bool {
						return ( $member['status'] ?? '' ) !== 'deleted';
					}
				)
			);
		}

		// Apply balance status filtering (post-query for compatibility with existing DB API)
		if ( ! empty( $filters['balance_status'] ) ) {
			$members = array_values(
				array_filter(
					$members,
					static function( array $member ) use ( $filters ): bool {
						$balance = (float) ( $member['balance_owed'] ?? 0 );
						return match ( $filters['balance_status'] ) {
							'paid_in_full' => abs( $balance ) <= 0.01,
							'outstanding'  => $balance > 0.01,
							'overpaid'     => $balance < -0.01,
							default        => true,
						};
					}
				)
			);
		}

		// Apply balance sorting when requested
		if ( 'balance' === $orderby ) {
			usort(
				$members,
				static function( array $a, array $b ) use ( $order ): int {
					$balance_a = (float) ( $a['balance_owed'] ?? 0 );
					$balance_b = (float) ( $b['balance_owed'] ?? 0 );

					if ( abs( $balance_a - $balance_b ) < 0.00001 ) {
						return 0;
					}

					if ( 'ASC' === $order ) {
						return $balance_a <=> $balance_b;
					}

					return $balance_b <=> $balance_a;
				}
			);
		}

		// Get membership types for filter dropdown
		$membership_types = STSRC_Membership_DB::get_all_membership_types();

		// Get active member count
		$active_count = STSRC_Member_DB::get_active_member_count();

		// Build guest pass balance lookup.
		$guest_pass_balances = array();
		foreach ( $members as $member ) {
			$mid = (int) $member['member_id'];
			$guest_pass_balances[ $mid ] = STSRC_Guest_Pass_DB::get_guest_pass_balance( $mid );
		}

		$data = array(
			'members'              => $members,
			'membership_types'     => $membership_types,
			'filters'              => $filters,
			'active_count'         => $active_count,
			'guest_pass_balances'  => $guest_pass_balances,
		);

		// Include list template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/members-list.php';
	}

	/**
	 * Render member edit form.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_edit_form( int $member_id ): void {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-family-member-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-extra-member-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-renewal-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/helpers/class-stsrc-renewal-helpers.php';

		$member = null;
		if ( $member_id > 0 ) {
			$member = STSRC_Member_DB::get_member( $member_id );
		}

		if ( ! $member && $member_id > 0 ) {
			wp_die( esc_html__( 'Member not found.', 'smoketree-plugin' ) );
		}

		// Get membership types
		$membership_types = STSRC_Membership_DB::get_all_membership_types();

		// Get family and extra members if editing
		$family_members = array();
		$extra_members = array();
		$delete_meta = array();
		if ( $member ) {
			$family_members = STSRC_Family_Member_DB::get_family_members( $member_id );
			$extra_members = STSRC_Extra_Member_DB::get_extra_members( $member_id );
			$delete_meta = array(
				'member_id'       => $member_id,
				'member_name'     => trim( ( $member['first_name'] ?? '' ) . ' ' . ( $member['last_name'] ?? '' ) ),
				'family_count'    => count( $family_members ),
				'extra_count'     => count( $extra_members ),
				'has_wp_user'     => ! empty( $member['user_id'] ),
				'wp_user_id'      => (int) ( $member['user_id'] ?? 0 ),
				'ajax_nonce'      => wp_create_nonce( 'stsrc_admin_nonce' ),
				'delete_action'   => 'stsrc_soft_delete_member',
				'redirect_url'    => admin_url( 'admin.php?page=stsrc-members&deleted=1' ),
			);
		}

		$pending_renewal = null;
		if ( $member && STSRC_Renewal_Helpers::is_renewal_enabled() ) {
			$pending_renewal = STSRC_Renewal_DB::get_latest_by_member_and_season(
				$member_id,
				STSRC_Renewal_Helpers::get_season_key(),
				array( STSRC_Renewal_DB::STATUS_PENDING_PAYMENT )
			);
		}

		$data = array(
			'member'          => $member,
			'membership_types' => $membership_types,
			'family_members'  => $family_members,
			'extra_members'   => $extra_members,
			'delete_meta'     => $delete_meta,
			'pending_renewal' => $pending_renewal,
		);

		// Include edit template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/member-edit.php';
	}

	/**
	 * Render member detail view.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_detail_view( int $member_id ): void {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/services/class-stsrc-member-service.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-guest-pass-db.php';

		$member_service = new STSRC_Member_Service();
		$member_data = $member_service->get_member_data( $member_id );

		if ( ! $member_data ) {
			wp_die( esc_html__( 'Member not found.', 'smoketree-plugin' ) );
		}

		// Get guest pass balance and log
		$guest_pass_balance = STSRC_Guest_Pass_DB::get_guest_pass_balance( $member_id );
		$guest_pass_log = STSRC_Guest_Pass_DB::get_guest_pass_log( $member_id );

		$data = array(
			'member'           => $member_data,
			'guest_pass_balance' => $guest_pass_balance,
			'guest_pass_log'   => $guest_pass_log,
		);

		// Include detail view (reuse edit template for now, or create separate)
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/member-edit.php';
	}
}

