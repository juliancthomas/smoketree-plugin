<?php

/**
 * Banner page class
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

class STSRC_Banner_Page {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		$data = array(
			'banner' => $this->get_banner_settings(),
		);

		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/banner-form.php';
	}

	private function get_banner_settings(): array {
		return array(
			'enabled'     => get_option( 'stsrc_banner_enabled', '0' ),
			'message'     => get_option( 'stsrc_banner_message', '' ),
			'type'        => get_option( 'stsrc_banner_type', 'info' ),
			'audience'    => get_option( 'stsrc_banner_audience', 'all' ),
			'dismissible' => get_option( 'stsrc_banner_dismissible', '1' ),
			'expiry_date' => get_option( 'stsrc_banner_expiry_date', '' ),
			'link_label'  => get_option( 'stsrc_banner_link_label', '' ),
			'link_url'    => get_option( 'stsrc_banner_link_url', '' ),
		);
	}
}
