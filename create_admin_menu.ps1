# Admin Menu Implementation Script - Fixed Version
# Create complete admin menu system for Visitor Dashboard plugin

Write-Host "Creating Admin Menu Implementation..." -ForegroundColor Green

# 1. Create admin menu core class
Write-Host "Creating admin menu core class..." -ForegroundColor Yellow

$adminCoreContent = @'
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
'@

Set-Content -Path "visitor-dashboard\admin\class-admin-core.php" -Value $adminCoreContent -Encoding UTF8
Write-Host "  Created admin core class with menu system" -ForegroundColor Green

# 2. Create capabilities class
Write-Host ""
Write-Host "Creating menu capabilities class..." -ForegroundColor Yellow

$capabilitiesContent = @'
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
        $dashboard_admin_role = get_role('dashboard_admin');
        $dashboard_client_role = get_role('dashboard_client');

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
        $roles = array('administrator', 'dashboard_admin', 'dashboard_client');
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
'@

Set-Content -Path "visitor-dashboard\admin\class-menu-capabilities.php" -Value $capabilitiesContent -Encoding UTF8
Write-Host "  Created menu capabilities class" -ForegroundColor Green

# 3. Create admin JavaScript
Write-Host ""
Write-Host "Creating admin JavaScript..." -ForegroundColor Yellow

# Create directory if it doesn't exist
if (!(Test-Path "visitor-dashboard\admin\assets\js")) {
    New-Item -ItemType Directory -Path "visitor-dashboard\admin\assets\js" -Force | Out-Null
}

$adminJsContent = @'
/**
 * Admin Dashboard JavaScript
 *
 * @package VisitorDashboard
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize admin dashboard functionality
     */
    function initAdminDashboard() {
        // Add loading states to buttons
        initLoadingStates();
        
        // Initialize confirmation dialogs
        initConfirmationDialogs();
        
        // Initialize AJAX forms
        initAjaxForms();
    }

    /**
     * Add loading states to buttons
     */
    function initLoadingStates() {
        $('.memo-admin-btn').on('click', function() {
            var $button = $(this);
            if (!$button.hasClass('memo-admin-btn-danger')) {
                $button.addClass('loading');
            }
        });
    }

    /**
     * Initialize confirmation dialogs
     */
    function initConfirmationDialogs() {
        // Delete confirmations
        $('.delete-item').on('click', function(e) {
            if (!confirm(visitor_dashboard_admin.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        // Export confirmations
        $('.export-data').on('click', function(e) {
            if (!confirm(visitor_dashboard_admin.strings.confirm_export)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Initialize AJAX forms
     */
    function initAjaxForms() {
        $('.ajax-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = new FormData(this);
            formData.append('action', $form.data('action'));
            formData.append('nonce', visitor_dashboard_admin.nonce);

            $.ajax({
                url: visitor_dashboard_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                    } else {
                        showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    showNotice('error', visitor_dashboard_admin.strings.error);
                }
            });
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var noticeClass = (type === 'error') ? 'notice-error' : 'notice-success';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initAdminDashboard();
    });

})(jQuery);
'@

Set-Content -Path "visitor-dashboard\admin\assets\js\admin-dashboard.js" -Value $adminJsContent -Encoding UTF8
Write-Host "  Created admin JavaScript file" -ForegroundColor Green

# 4. Update plugin core
Write-Host ""
Write-Host "Updating plugin core integration..." -ForegroundColor Yellow

# Read current plugin core and update it
$currentCore = Get-Content "visitor-dashboard\includes\class-plugin-core.php" -Raw

# Update define_admin_hooks method
$newAdminHooks = @'
    private function define_admin_hooks() {
        if (is_admin()) {
            $plugin_admin = new Visitor_Dashboard_Admin_Core($this->get_plugin_name(), $this->get_version());
            
            // Add admin menu
            $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
            
            // Enqueue admin styles and scripts
            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
            
            // Add admin notices
            $this->loader->add_action('admin_notices', $plugin_admin, 'add_admin_notices');
            
            // Customize admin footer
            $this->loader->add_filter('admin_footer_text', $plugin_admin, 'admin_footer_text');
        }
    }
'@

# Replace the existing define_admin_hooks method
$updatedCore = $currentCore -replace "    private function define_admin_hooks\(\) \{[^}]*\}", $newAdminHooks

Set-Content -Path "visitor-dashboard\includes\class-plugin-core.php" -Value $updatedCore -Encoding UTF8
Write-Host "  Updated plugin core with admin hooks" -ForegroundColor Green

# 5. Update activator
Write-Host ""
Write-Host "Updating activator with capabilities..." -ForegroundColor Yellow

$currentActivator = Get-Content "visitor-dashboard\includes\class-activator.php" -Raw

# Add capabilities to activator
$newActivatorContent = $currentActivator -replace "        flush_rewrite_rules\(\);", @'
        // Add menu capabilities
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'admin/class-menu-capabilities.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'admin/class-menu-capabilities.php';
            Visitor_Dashboard_Menu_Capabilities::add_capabilities();
        }
        
        flush_rewrite_rules();
'@

Set-Content -Path "visitor-dashboard\includes\class-activator.php" -Value $newActivatorContent -Encoding UTF8
Write-Host "  Updated activator with capabilities" -ForegroundColor Green

# 6. Verification
Write-Host ""
Write-Host "Verifying admin menu implementation..." -ForegroundColor Yellow

$requiredFiles = @(
    "visitor-dashboard\admin\class-admin-core.php",
    "visitor-dashboard\admin\class-menu-capabilities.php",
    "visitor-dashboard\admin\assets\js\admin-dashboard.js"
)

$allGood = $true
foreach ($file in $requiredFiles) {
    if (Test-Path $file) {
        Write-Host "  [OK] $(Split-Path $file -Leaf) created" -ForegroundColor Green
    } else {
        Write-Host "  [MISSING] $(Split-Path $file -Leaf) not found" -ForegroundColor Red
        $allGood = $false
    }
}

Write-Host ""
if ($allGood) {
    Write-Host "ADMIN MENU IMPLEMENTATION COMPLETED SUCCESSFULLY!" -ForegroundColor Green
} else {
    Write-Host "ADMIN MENU IMPLEMENTATION COMPLETED WITH WARNINGS!" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Admin Menu Structure:" -ForegroundColor Yellow
Write-Host "  Main Menu: Visitors" -ForegroundColor Gray
Write-Host "    - All Visitors" -ForegroundColor Gray
Write-Host "    - Analytics" -ForegroundColor Gray
Write-Host "    - CRM Queue" -ForegroundColor Gray
Write-Host "    - Users (Admin only)" -ForegroundColor Gray
Write-Host "    - Settings (Admin only)" -ForegroundColor Gray
Write-Host "    - Import Logs (Admin only)" -ForegroundColor Gray
Write-Host "    - Audit Trail (Admin only)" -ForegroundColor Gray
Write-Host "    - Export" -ForegroundColor Gray
Write-Host ""
Write-Host "Ready to test in WordPress!" -ForegroundColor Green