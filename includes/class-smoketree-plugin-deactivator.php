<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes
 * @author     Smoketree Swim and Recreation Club
 */
class Smoketree_Plugin_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/services/class-stsrc-auto-renewal-service.php';

		STSRC_Auto_Renewal_Service::clear_cron_events();
	}

}

