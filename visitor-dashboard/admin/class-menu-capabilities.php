<?php
/**
 * Menu Capabilities Class
 *
 * @package    Visitor_Dashboard
 * @subpackage Visitor_Dashboard/admin
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Visitor_Dashboard_Menu_Capabilities {

    /**
     * Add custom capabilities to user roles
     */
    public static function add_capabilities() {
        // Get roles
        $admin_role = get_role('administrator');
        $dashboard_admin_role = get_role('visitor_dashboard_admin'); // Corrected role name
        $dashboard_client_role = get_role('visitor_dashboard_client'); // Corrected role name

        // Administrator capabilities (full access)
        if ($admin_role) {
            $admin_capabilities = array(
                'read_visitors',
                'edit_visitors',
                'delete_visitors',
                'manage_dashboard_users',
                'manage_dashboard_settings',
                'manage_crm_queue',
                'export_visitor_data',
                'import_visitor_data',
                'view_audit_logs',
                'view_import_logs',
                'manage_all_clients'
            );

            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }

        // Dashboard Admin capabilities
        if ($dashboard_admin_role) {
            $dashboard_admin_capabilities = array(
                'read_visitors',
                'manage_all_visitors',
                'manage_own_visitors',
                'delete_visitors',
                'restore_visitors',
                'export_visitors',
                'export_crm_data',
                'manage_crm_queue',
                'manage_clients',
                'view_all_clients',
                'switch_clients',
                'manage_dashboard_users',
                'create_users',
                'delete_users',
                'view_analytics',
                'view_audit_logs',
                'manage_system_settings',
                'import_data'
            );

            foreach ($dashboard_admin_capabilities as $cap) {
                $dashboard_admin_role->add_cap($cap);
            }
        }

        // Dashboard Client capabilities (limited access)
        if ($dashboard_client_role) {
            $dashboard_client_capabilities = array(
                'read_visitors',
                'edit_visitors',
                'manage_crm_queue',
                'export_visitor_data'
            );

            foreach ($dashboard_client_capabilities as $cap) {
                $dashboard_client_role->add_cap($cap);
            }
        }
    }

    /**
     * Remove custom capabilities from user roles
     */
    public static function remove_capabilities() {
        $roles = array('administrator', 'visitor_dashboard_admin', 'visitor_dashboard_client');
        $capabilities = array(
            'read_visitors',
            'edit_visitors',
            'delete_visitors',
            'manage_dashboard_users',
            'manage_dashboard_settings',
            'manage_crm_queue',
            'export_visitor_data',
            'import_visitor_data',
            'view_audit_logs',
            'view_import_logs',
            'manage_all_clients'
        );

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}
