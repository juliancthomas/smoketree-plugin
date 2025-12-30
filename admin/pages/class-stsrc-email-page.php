<?php

/**
 * Batch email composer page class
 *
 * Handles the batch email admin page display and operations.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Batch email composer page class.
 *
 * Provides email composer interface with member filtering.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Email_Page {

	/**
	 * Render the email composer page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		// Get membership types for filter dropdown
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
		$membership_types = STSRC_Membership_DB::get_all_membership_types();

		// Get available email templates
		$templates = $this->get_available_templates();

		$data = array(
			'membership_types' => $membership_types,
			'templates'        => $templates,
		);

		// Include composer template
		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/email-composer.php';
	}

	/**
	 * Get available email templates.
	 *
	 * @since    1.0.0
	 * @return   array    Array of template filenames
	 */
	private function get_available_templates(): array {
		$templates_dir = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'templates/';
		$templates     = array();

		if ( is_dir( $templates_dir ) ) {
			$files = glob( $templates_dir . '*.php' );
			foreach ( $files as $file ) {
				$filename = basename( $file );
				// Skip admin notification templates for batch emails
				if ( strpos( $filename, 'notify-admin' ) === false && strpos( $filename, 'treasurer' ) === false ) {
					$templates[] = $filename;
				}
			}
		}

		return $templates;
	}
}

