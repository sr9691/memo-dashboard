<?php
/**
 * Admin Core Class
 *
 * @package    Visitor_Dashboard
 * @subpackage Visitor_Dashboard/admin
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Require database class for static calls
require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/database/class-database-manager.php';

class Visitor_Dashboard_Admin_Core {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register admin menu pages
     */
    public function add_admin_menu() {
        // Check if user has required capability
        if (!current_user_can('read_visitors')) {
            return;
        }

        // Add main menu page
        add_menu_page(
            __('Visitor Dashboard', 'visitor-dashboard'),     // Page title
            __('Visitors', 'visitor-dashboard'),              // Menu title
            'read_visitors',                                   // Capability
            'visitor-dashboard',                               // Menu slug
            array($this, 'display_main_dashboard'),           // Callback function
            'dashicons-groups',                                // Icon
            30                                                 // Position
        );

        // Add submenu pages
        add_submenu_page(
            'visitor-dashboard',                               // Parent slug
            __('All Visitors', 'visitor-dashboard'),          // Page title
            __('All Visitors', 'visitor-dashboard'),          // Menu title
            'read_visitors',                                   // Capability
            'visitor-dashboard',                               // Menu slug (same as parent for main page)
            array($this, 'display_main_dashboard')            // Callback function
        );

        // Analytics submenu
        add_submenu_page(
            'visitor-dashboard',
            __('Analytics', 'visitor-dashboard'),
            __('Analytics', 'visitor-dashboard'),
            'read_visitors',
            'visitor-analytics',
            array($this, 'display_analytics_page')
        );

        // CRM Queue submenu
        add_submenu_page(
            'visitor-dashboard',
            __('CRM Queue', 'visitor-dashboard'),
            __('CRM Queue', 'visitor-dashboard'),
            'manage_crm_queue',
            'visitor-crm-queue',
            array($this, 'display_crm_queue_page')
        );

        // User Management submenu (Admin only)
        if (current_user_can('manage_dashboard_users')) {
            add_submenu_page(
                'visitor-dashboard',
                __('User Management', 'visitor-dashboard'),
                __('Users', 'visitor-dashboard'),
                'manage_dashboard_users',
                'visitor-users',
                array($this, 'display_user_management_page')
            );
        }

        // Settings submenu (Admin only)
        if (current_user_can('manage_dashboard_settings')) {
            add_submenu_page(
                'visitor-dashboard',
                __('Dashboard Settings', 'visitor-dashboard'),
                __('Settings', 'visitor-dashboard'),
                'manage_dashboard_settings',
                'visitor-settings',
                array($this, 'display_settings_page')
            );
        }

        // Import Logs submenu (Admin only)
        if (current_user_can('view_import_logs')) {
            add_submenu_page(
                'visitor-dashboard',
                __('Import Logs', 'visitor-dashboard'),
                __('Import Logs', 'visitor-dashboard'),
                'view_import_logs',
                'visitor-import-logs',
                array($this, 'display_import_logs_page')
            );
        }

        // Audit Trail submenu (Admin only)
        if (current_user_can('view_audit_logs')) {
            add_submenu_page(
                'visitor-dashboard',
                __('Audit Trail', 'visitor-dashboard'),
                __('Audit Trail', 'visitor-dashboard'),
                'view_audit_logs',
                'visitor-audit-logs',
                array($this, 'display_audit_logs_page')
            );
        }

        // Export submenu
        add_submenu_page(
            'visitor-dashboard',
            __('Export Data', 'visitor-dashboard'),
            __('Export', 'visitor-dashboard'),
            'export_visitor_data',
            'visitor-export',
            array($this, 'display_export_page')
        );
    }

    /**
     * Hide the WP admin menu for specific user roles
     */
    public function hide_admin_menu() {
        $current_user = wp_get_current_user();
        
        // Hide menu for both Dashboard Admin and Dashboard Client roles
        if (in_array('visitor_dashboard_admin', (array) $current_user->roles) || in_array('visitor_dashboard_client', (array) $current_user->roles)) {
            echo '<style>#adminmenumain, #wpadminbar, #wpfooter, #wpcontent .wrap h1:first-of-type { display: none; }</style>';
        }
    }

    /**
     * Display main dashboard page
     */
    public function display_main_dashboard() {
        $this->render_admin_page('dashboard');
    }

    /**
     * Display analytics page
     */
    public function display_analytics_page() {
        $this->render_admin_page('analytics');
    }

    /**
     * Display CRM queue page
     */
    public function display_crm_queue_page() {
        $this->render_admin_page('crm-queue');
    }

    /**
     * Display user management page
     */
    public function display_user_management_page() {
        $this->render_admin_page('user-management');
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        $this->render_admin_page('settings');
    }

    /**
     * Display import logs page
     */
    public function display_import_logs_page() {
        $this->render_admin_page('import-logs');
    }

    /**
     * Display audit logs page
     */
    public function display_audit_logs_page() {
        $this->render_admin_page('audit-logs');
    }

    /**
     * Display export page
     */
    public function display_export_page() {
        $this->render_admin_page('export');
    }

    /**
     * Render admin page template
     */
    private function render_admin_page($page) {
        // Set admin body class for styling
        add_filter('admin_body_class', function($classes) {
            return $classes . ' visitor-dashboard-page';
        });

        // Include the appropriate view file
        $view_file = VISITOR_DASHBOARD_PLUGIN_DIR . "admin/views/pages/{$page}.php";
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            // Fallback to default dashboard if specific view doesn't exist
            $default_view = VISITOR_DASHBOARD_PLUGIN_DIR . 'admin/views/pages/dashboard.php';
            if (file_exists($default_view)) {
                include $default_view;
            } else {
                echo '<div class="wrap"><h1>Page not found</h1><p>The requested page could not be loaded.</p></div>';
            }
        }
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        
        // Only load on visitor dashboard pages
        if ($screen && strpos($screen->id, 'visitor-dashboard') !== false) {
            wp_enqueue_style(
                $this->plugin_name . '-admin',
                VISITOR_DASHBOARD_PLUGIN_URL . 'assets/css/admin/admin-dashboard.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        
        // Only load on visitor dashboard pages
        if ($screen && strpos($screen->id, 'visitor-dashboard') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-admin',
                VISITOR_DASHBOARD_PLUGIN_URL . 'admin/assets/js/admin-dashboard.js',
                array('jquery'),
                $this->version,
                false
            );

            // Localize script for AJAX
            wp_localize_script(
                $this->plugin_name . '-admin',
                'visitor_dashboard_admin',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('visitor_dashboard_admin_nonce'),
                    'strings' => array(
                        'confirm_delete' => __('Are you sure you want to delete this item?', 'visitor-dashboard'),
                        'confirm_export' => __('Are you sure you want to export this data?', 'visitor-dashboard'),
                        'processing' => __('Processing...', 'visitor-dashboard'),
                        'error' => __('An error occurred. Please try again.', 'visitor-dashboard'),
                    )
                )
            );
        }
    }

    /**
     * Add admin notices
     */
    public function add_admin_notices() {
        $screen = get_current_screen();
        
        if ($screen && strpos($screen->id, 'visitor-dashboard') !== false) {
            // Check for success/error messages
            if (isset($_GET['message'])) {
                $message_type = sanitize_text_field($_GET['message']);
                $this->display_admin_notice($message_type);
            }
        }
    }

    /**
     * Display admin notices
     */
    private function display_admin_notice($type) {
        $messages = array(
            'updated' => __('Settings saved successfully.', 'visitor-dashboard'),
            'deleted' => __('Item deleted successfully.', 'visitor-dashboard'),
            'exported' => __('Data exported successfully.', 'visitor-dashboard'),
            'imported' => __('Data imported successfully.', 'visitor-dashboard'),
            'error' => __('An error occurred. Please try again.', 'visitor-dashboard'),
        );

        if (isset($messages[$type])) {
            $notice_class = ($type === 'error') ? 'notice-error' : 'notice-success';
            echo "<div class='notice {$notice_class} is-dismissible'>";
            echo "<p>" . esc_html($messages[$type]) . "</p>";
            echo "</div>";
        }
    }

    /**
     * Customize admin footer text on plugin pages
     */
    public function admin_footer_text($footer_text) {
        $screen = get_current_screen();
        
        if ($screen && strpos($screen->id, 'visitor-dashboard') !== false) {
            $footer_text = sprintf(
                __('Thank you for using %s. Built by %s for %s.', 'visitor-dashboard'),
                '<strong>Visitor Dashboard</strong>',
                '<a href="#" target="_blank">ANSA Solutions</a>',
                '<strong>MEMO Marketing Group</strong>'
            );
        }
        
        return $footer_text;
    }
}
