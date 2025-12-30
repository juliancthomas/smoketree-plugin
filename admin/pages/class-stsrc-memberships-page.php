<?php

/**
 * Membership types management page class
 *
 * Handles the membership types admin page display and operations.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Membership types management page class.
 *
 * Provides membership type CRUD operations interface.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Memberships_Page {

	/**
	 * Get available benefits from ACF or fallback to defaults.
	 *
	 * @since    1.0.0
	 * @return   array    Array of benefit options (key => label)
	 */
	private function get_benefits(): array {
		// Try to get benefits from ACF field definition
		if ( function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( 'various_membership_benefits' );
			if ( $field && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
				// Convert ACF choices to our format (value => label)
				// ACF stores as label => label, we need to create keys
				$benefits = array();
				foreach ( $field['choices'] as $key => $label ) {
					// Use the key if it's not numeric, otherwise create a slug from label
					$benefit_key = is_numeric( $key ) ? sanitize_key( str_replace( array( ' ', '/', '&' ), array( '_', '_', 'and' ), strtolower( $label ) ) ) : $key;
					$benefits[ $benefit_key ] = $label;
				}
				if ( ! empty( $benefits ) ) {
					return $benefits;
				}
			}
		}

		// Fallback to default benefits if ACF is not available or field not found
		return array(
			'up_to_5_people'        => 'Up to 5 people',
			'2_people'              => '2 people',
			'1_person'              => '1 person',
			'pool_use_for_season'   => 'Pool use for season',
			'lakefront_and_dock'    => 'Lakefront and Dock',
			'playground'            => 'Playground',
			'tennis_pickleball'     => 'Tennis/Pickleball Court',
			'dog_run'               => 'Dog Run',
			'pavilion'              => 'Pavilion',
			'membership_voting'      => 'Membership Voting Rights',
		);
	}

	/**
	 * Render the memberships page.
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
		$action = isset( $request['action'] ) ? sanitize_text_field( $request['action'] ) : 'list';
		$membership_type_id = isset( $request['membership_type_id'] ) ? intval( $request['membership_type_id'] ) : 0;

		switch ( $action ) {
			case 'edit':
				$this->render_edit_form( $membership_type_id );
				break;
			case 'list':
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render membership types list.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_list(): void {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';

		// Get all membership types
		$membership_types = STSRC_Membership_DB::get_all_membership_types();

		$data = array(
			'membership_types' => $membership_types,
		);

		// Include list template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/membership-types-list.php';
	}

	/**
	 * Render membership type edit form.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_edit_form( int $membership_type_id ): void {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';

		$membership_type = null;
		if ( $membership_type_id > 0 ) {
			$membership_type = STSRC_Membership_DB::get_membership_type( $membership_type_id );
		}

		if ( ! $membership_type && $membership_type_id > 0 ) {
			wp_die( esc_html__( 'Membership type not found.', 'smoketree-plugin' ) );
		}

		// Decode benefits if present
		if ( ! empty( $membership_type['benefits'] ) ) {
			if ( is_string( $membership_type['benefits'] ) ) {
				$membership_type['benefits'] = json_decode( $membership_type['benefits'], true );
			}
		} else {
			$membership_type['benefits'] = array();
		}

		$data = array(
			'membership_type' => $membership_type,
			'benefits'        => $this->get_benefits(),
		);

		// Include edit template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/membership-type-edit.php';
	}

}

