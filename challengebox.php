<?php

/**
 * The plugin bootstrap file
 *
 * @link              http://www.getchallengebox.com
 * @since             1.0.0
 * @package           ChallengeBox
 *
 * @wordpress-plugin
 * Plugin Name:       ChallengeBox Custom plugin
 * Plugin URI:        http://www.getchallengebox.com/plugin/
 * Description:       Customizations for the ChallengeBox application on wordpress.
 * Version:           1.0.0
 * Author:            Ryan Witt (ryan@getchallengebox.com)
 * Author URI:        http://linkedin.com/in/ryanwitt
 * License:           Proprietary
 * Text Domain:       challengebox
 * Domain Path:       /languages
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cb-activator.php
 */
function activate_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cb-activator.php';
	CBActivator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cb-deactivator.php
 */
function deactivate_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cb-deactivator.php';
	CBDeactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_plugin_name' );
register_deactivation_hook( __FILE__, 'deactivate_plugin_name' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cb.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_challengebox() {

	$plugin = new CB();
	$plugin->run();

}
run_challengebox();
