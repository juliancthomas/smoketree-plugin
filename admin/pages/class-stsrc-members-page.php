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

		// Get members
		$members = STSRC_Member_DB::get_members( $filters );

		// Get membership types for filter dropdown
		$membership_types = STSRC_Membership_DB::get_all_membership_types();

		// Get active member count
		$active_count = STSRC_Member_DB::get_active_member_count();

		$data = array(
			'members'         => $members,
			'membership_types' => $membership_types,
			'filters'         => $filters,
			'active_count'    => $active_count,
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
		if ( $member ) {
			$family_members = STSRC_Family_Member_DB::get_family_members( $member_id );
			$extra_members = STSRC_Extra_Member_DB::get_extra_members( $member_id );
		}

		$data = array(
			'member'          => $member,
			'membership_types' => $membership_types,
			'family_members'  => $family_members,
			'extra_members'   => $extra_members,
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

