<?php
/**
 * Plugin Name: Visitor Dashboard
 * Plugin URI: https://github.com/sr9691/memo-dashboard
 * Description: Interactive visitor management dashboard with CRM integration capabilities. Built for MEMO Marketing Group to consolidate visitor tracking from RB2B and campaign performance from GroundTruth into a unified dashboard.
 * Version: 1.0.0
 * Author: ANSA Solutions
 * License: Proprietary
 * Text Domain: visitor-dashboard
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('VISITOR_DASHBOARD_VERSION', '1.0.0');
define('VISITOR_DASHBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VISITOR_DASHBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VISITOR_DASHBOARD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VISITOR_DASHBOARD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_visitor_dashboard() {
    require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/class-activator.php';
    Visitor_Dashboard_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_visitor_dashboard() {
    require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/class-deactivator.php';
    Visitor_Dashboard_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_visitor_dashboard');
register_deactivation_hook(__FILE__, 'deactivate_visitor_dashboard');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/class-plugin-core.php';

/**
 * Begins execution of the plugin.
 */
function run_visitor_dashboard() {
    $plugin = new Visitor_Dashboard_Plugin_Core();
    $plugin->run();
}
run_visitor_dashboard();

