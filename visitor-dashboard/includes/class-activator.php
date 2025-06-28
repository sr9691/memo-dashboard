<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Visitor_Dashboard_Activator {

    public static function activate() {
        // Create database tables
        require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/database/class-database-manager.php';
        VisitorDashboard_Database::create_tables();

        // Create user roles
        require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-user-roles.php';
        $user_roles = new VisitorDashboard_User_Roles(); // Corrected class name
        $user_roles->create_roles();

        // Add menu capabilities
        require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'admin/class-menu-capabilities.php';
        Visitor_Dashboard_Menu_Capabilities::add_capabilities();

        // Set default options
        add_option('visitor_dashboard_version', VISITOR_DASHBOARD_VERSION);
        add_option('visitor_dashboard_activated_time', current_time('timestamp'));
        
        flush_rewrite_rules();
    }
}
