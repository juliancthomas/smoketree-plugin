<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://smoketree.us
 * @since             1.0.0
 * @package           Smoketree_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Smoketree Swim and Recreation Club
 * Plugin URI:        https://smoketree.us
 * Description:       Comprehensive membership management plugin for Smoketree Swim and Recreation Club. Handles member registration, payment processing via Stripe, guest pass management, batch email communications, and provides both member and admin portals.
 * Version:           1.0.0
 * Author:            Smoketree Swim and Recreation Club
 * Author URI:        https://smoketree.us
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smoketree-plugin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SMOKETREE_PLUGIN_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-smoketree-plugin-activator.php
 */
function activate_smoketree_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-smoketree-plugin-activator.php';
	Smoketree_Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-smoketree-plugin-deactivator.php
 */
function deactivate_smoketree_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-smoketree-plugin-deactivator.php';
	Smoketree_Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_smoketree_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_smoketree_plugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-smoketree-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_smoketree_plugin() {

	$plugin = new Smoketree_Plugin();
	$plugin->run();

}
run_smoketree_plugin();

