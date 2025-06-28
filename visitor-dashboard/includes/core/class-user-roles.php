<?php
/**
 * user roles Class
 *
 * @package    Visitor_Dashboard
 * @subpackage Visitor_Dashboard/includes/core
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * User roles and capabilities management for Visitor Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Require database class for static calls
require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/database/class-database-manager.php';

class VisitorDashboard_User_Roles {
    
    /**
     * Create custom roles and capabilities
     */
    public static function create_roles() {
        // Remove existing roles if they exist
        remove_role('visitor_dashboard_admin');
        remove_role('visitor_dashboard_client');
        
        // Define capabilities
        $admin_capabilities = array(
            // Visitor management
            'read_visitors' => true,
            'manage_all_visitors' => true,
            'manage_own_visitors' => true,
            'delete_visitors' => true,
            'restore_visitors' => true,
            'export_visitors' => true,
            
            // CRM capabilities
            'export_crm_data' => true,
            'manage_crm_queue' => true,
            
            // Client management
            'manage_clients' => true,
            'view_all_clients' => true,
            'switch_clients' => true,
            
            // User management
            'manage_dashboard_users' => true,
            'create_users' => true,
            'delete_users' => true,
            
            // System capabilities
            'view_analytics' => true,
            'view_audit_logs' => true,
            'manage_system_settings' => true,
            'import_data' => true,
            
            // WordPress capabilities needed
            'read' => true,
            'upload_files' => true,
            'edit_posts' => false,
            'edit_pages' => false,
            'publish_posts' => false,
            'manage_categories' => false
        );
        
        $client_capabilities = array(
            // Visitor management (limited)
            'read_visitors' => true,
            'manage_own_visitors' => true,
            'delete_visitors' => false, // Can only archive
            'restore_visitors' => false,
            'export_visitors' => false,
            
            // CRM capabilities (limited)
            'export_crm_data' => false,
            'manage_crm_queue' => false,
            
            // Client management (none)
            'manage_clients' => false,
            'view_all_clients' => false,
            'switch_clients' => false,
            
            // User management (none)
            'manage_dashboard_users' => false,
            'create_users' => false,
            'delete_users' => false,
            
            // System capabilities (limited)
            'view_analytics' => true,
            'view_audit_logs' => false,
            'manage_system_settings' => false,
            'import_data' => false,
            
            // WordPress capabilities needed
            'read' => true,
            'upload_files' => false,
            'edit_posts' => false,
            'edit_pages' => false,
            'publish_posts' => false,
            'manage_categories' => false
        );
        
        // Create admin role
        add_role(
            'visitor_dashboard_admin',
            __('Dashboard Admin', 'visitor-dashboard'),
            $admin_capabilities
        );
        
        // Create client user role
        add_role(
            'visitor_dashboard_client',
            __('Dashboard Client', 'visitor-dashboard'),
            $client_capabilities
        );
        
        // Add capabilities to existing WordPress roles
        self::add_capabilities_to_existing_roles();
    }
    
    /**
     * Add capabilities to existing WordPress roles
     */
    private static function add_capabilities_to_existing_roles() {
        // Give administrators all dashboard capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
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
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Give editors limited capabilities
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_capabilities = array(
                'read_visitors',
                'manage_own_visitors',
                'view_analytics'
            );
            
            foreach ($editor_capabilities as $cap) {
                $editor_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Remove custom roles and capabilities
     */
    public static function remove_roles() {
        // Remove custom roles
        remove_role('visitor_dashboard_admin');
        remove_role('visitor_dashboard_client');
        
        // Remove capabilities from existing roles
        self::remove_capabilities_from_existing_roles();
    }
    
    /**
     * Remove capabilities from existing WordPress roles
     */
    private static function remove_capabilities_from_existing_roles() {
        $roles_to_clean = array('administrator', 'editor');
        $capabilities_to_remove = array(
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
        
        foreach ($roles_to_clean as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities_to_remove as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Create dashboard user record
     */
    public static function create_dashboard_user($wp_user_id, $client_id = null, $role = 'client_user') {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        $wp_user = get_user_by('id', $wp_user_id);
        if (!$wp_user) {
            return false;
        }
        
        // Check if dashboard user already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}dashboard_users WHERE wp_user_id = %d",
            $wp_user_id
        ));
        
        if ($existing) {
            return $existing;
        }
        
        // Insert dashboard user record
        $result = $wpdb->insert(
            "{$prefix}dashboard_users",
            array(
                'wp_user_id' => $wp_user_id,
                'client_id' => $client_id,
                'role' => $role,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'status' => 'active'
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Assign WordPress role
            $wp_role = ($role === 'admin') ? 'visitor_dashboard_admin' : 'visitor_dashboard_client';
            $wp_user->set_role($wp_role);
            
            // Log the action
            self::log_user_action('user_create', $wpdb->insert_id, $wp_user_id);
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get user's dashboard profile
     */
    public static function get_dashboard_user($wp_user_id) {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT du.*, c.account_name, c.groundtruth_account_id 
             FROM {$prefix}dashboard_users du
             LEFT JOIN {$prefix}clients c ON du.client_id = c.id
             WHERE du.wp_user_id = %d AND du.status = 'active'",
            $wp_user_id
        ));
    }
    
    /**
     * Get user's client ID
     */
    public static function get_user_client_id($wp_user_id) {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$prefix}dashboard_users WHERE wp_user_id = %d AND status = 'active'",
            $wp_user_id
        ));
    }
    
    /**
     * Check if user can access client data
     */
    public static function user_can_access_client($wp_user_id, $client_id) {
        // Admins can access all clients
        if (user_can($wp_user_id, 'manage_all_visitors')) {
            return true;
        }
        
        // Client users can only access their own client
        $user_client_id = self::get_user_client_id($wp_user_id);
        return ($user_client_id == $client_id);
    }
    
    /**
     * Get clients accessible by user
     */
    public static function get_user_accessible_clients($wp_user_id) {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        // Admins can access all clients
        if (user_can($wp_user_id, 'manage_all_visitors')) {
            return $wpdb->get_results(
                "SELECT * FROM {$prefix}clients WHERE status = 'active' ORDER BY account_name"
            );
        }
        
        // Client users can only access their own client
        $client_id = self::get_user_client_id($wp_user_id);
        if ($client_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$prefix}clients WHERE id = %d AND status = 'active'",
                $client_id
            ));
        }
        
        return array();
    }
    
    /**
     * Update user last login
     */
    public static function update_user_login($wp_user_id) {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$prefix}dashboard_users 
             SET last_login = CURRENT_TIMESTAMP, login_count = login_count + 1 
             WHERE wp_user_id = %d",
            $wp_user_id
        ));
    }
    
    /**
     * Deactivate dashboard user
     */
    public static function deactivate_dashboard_user($wp_user_id, $deleted_by = null) {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        $result = $wpdb->update(
            "{$prefix}dashboard_users",
            array(
                'status' => 'inactive',
                'date_deleted' => current_time('mysql')
            ),
            array('wp_user_id' => $wp_user_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result) {
            // Remove WordPress role
            $wp_user = get_user_by('id', $wp_user_id);
            if ($wp_user) {
                $wp_user->set_role('subscriber'); // Demote to subscriber
            }
            
            // Log the action
            if ($deleted_by) {
                self::log_user_action('user_delete', $wp_user_id, $deleted_by);
            }
        }
        
        return $result;
    }
    
    /**
     * Log user action
     */
    private static function log_user_action($action, $entity_id, $user_id) {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        $client_id = self::get_user_client_id($user_id);
        
        $wpdb->insert(
            "{$prefix}audit_logs",
            array(
                'user_id' => $user_id,
                'client_id' => $client_id,
                'action_type' => $action,
                'entity_type' => 'user',
                'entity_id' => $entity_id,
                'ip_address' => self::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return 'unknown';
    }
    
    /**
     * Get all dashboard users for admin
     */
    public static function get_all_dashboard_users($status = 'active') {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT du.*, c.account_name, u.user_login, u.user_registered
             FROM {$prefix}dashboard_users du
             LEFT JOIN {$prefix}clients c ON du.client_id = c.id
             LEFT JOIN {$wpdb->users} u ON du.wp_user_id = u.ID
             WHERE du.status = %s
             ORDER BY du.date_joined DESC",
            $status
        ));
    }
    
    /**
     * Get user statistics
     */
    public static function get_user_statistics() {
        global $wpdb;
        $prefix = Visitor_Dashboard_Database_Manager::get_table_prefix(); // Corrected class name
        
        $stats = array();
        
        // Total active users
        $stats['total_active'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$prefix}dashboard_users WHERE status = 'active'"
        );
        
        // Admin users
        $stats['admin_users'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$prefix}dashboard_users WHERE role = 'admin' AND status = 'active'"
        );
        
        // Client users
        $stats['client_users'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$prefix}dashboard_users WHERE role = 'client_user' AND status = 'active'"
        );
        
        // Users logged in today
        $stats['logged_in_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}dashboard_users WHERE DATE(last_login) = %s",
            current_time('Y-m-d')
        ));
        
        // Users logged in this week
        $stats['logged_in_week'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}dashboard_users WHERE last_login >= %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        return $stats;
    }
}