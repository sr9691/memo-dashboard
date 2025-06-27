<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Visitor_Dashboard_Activator {

    public static function activate() {
        // Set default options
        add_option('visitor_dashboard_version', VISITOR_DASHBOARD_VERSION);
        add_option('visitor_dashboard_activated_time', current_time('timestamp'));
        flush_rewrite_rules();
    }
}

