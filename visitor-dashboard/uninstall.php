<?php
/**
 * Fired when the plugin is uninstalled.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('visitor_dashboard_options');
delete_option('visitor_dashboard_version');
wp_cache_flush();
