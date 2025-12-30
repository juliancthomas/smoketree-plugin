<?php

/**
 * Access codes management page class
 *
 * Handles the access codes admin page display and operations.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Access codes management page class.
 *
 * Provides access code CRUD operations interface.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Access_Codes_Page {

	/**
	 * Render the access codes page.
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
		$action  = isset( $request['action'] ) ? sanitize_text_field( $request['action'] ) : 'list';
		$code_id = isset( $request['code_id'] ) ? intval( $request['code_id'] ) : 0;

		switch ( $action ) {
			case 'edit':
				$this->render_edit_form( $code_id );
				break;
			case 'list':
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render access codes list.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_list(): void {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-access-code-db.php';

		// Get all access codes
		$access_codes = STSRC_Access_Code_DB::get_access_codes();

		// Check expiration status
		$now = current_time( 'mysql' );
		foreach ( $access_codes as &$code ) {
			$code['is_expired'] = false;
			if ( ! empty( $code['expires_at'] ) && $code['expires_at'] < $now ) {
				$code['is_expired'] = true;
			}
		}

		$data = array(
			'access_codes' => $access_codes,
		);

		// Include list template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/access-codes-list.php';
	}

	/**
	 * Render access code edit form.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_edit_form( int $code_id ): void {
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-access-code-db.php';

		$access_code = null;
		if ( $code_id > 0 ) {
			// Get single access code
			$all_codes = STSRC_Access_Code_DB::get_access_codes();
			foreach ( $all_codes as $code ) {
				if ( (int) $code['code_id'] === $code_id ) {
					$access_code = $code;
					break;
				}
			}
		}

		if ( ! $access_code && $code_id > 0 ) {
			wp_die( esc_html__( 'Access code not found.', 'smoketree-plugin' ) );
		}

		$data = array(
			'access_code' => $access_code,
		);

		// Include edit template (reuse list template with form)
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/access-codes-list.php';
	}
}

