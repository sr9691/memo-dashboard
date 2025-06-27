<?php
/**
 * CSS Asset Management
 * 
 * WordPress enqueue functions for visitor dashboard styles
 * 
 * @package VisitorDashboard
 * @version 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Enqueue dashboard styles based on context
 */
function visitor_dashboard_enqueue_styles() {
    // Always enqueue shared variables
    wp_enqueue_style(
        'visitor-dashboard-variables',
        VISITOR_DASHBOARD_PLUGIN_URL . 'assets/css/shared/variables.css',
        [],
        VISITOR_DASHBOARD_VERSION,
        'all'
    );
    
    wp_enqueue_style(
        'visitor-dashboard-utilities',
        VISITOR_DASHBOARD_PLUGIN_URL . 'assets/css/shared/utilities.css',
        ['visitor-dashboard-variables'],
        VISITOR_DASHBOARD_VERSION,
        'all'
    );
    
    wp_enqueue_style(
        'visitor-dashboard-accessibility',
        VISITOR_DASHBOARD_PLUGIN_URL . 'assets/css/shared/accessibility.css',
        ['visitor-dashboard-variables'],
        VISITOR_DASHBOARD_VERSION,
        'all'
    );
}

/**
 * Enqueue admin-specific styles
 */
function visitor_dashboard_admin_styles() {
    // Only load on visitor dashboard admin pages
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'visitor-dashboard') !== false) {
        visitor_dashboard_enqueue_styles();
        
        wp_enqueue_style(
            'visitor-dashboard-admin',
            VISITOR_DASHBOARD_PLUGIN_URL . 'assets/css/admin/admin-dashboard.css',
            ['visitor-dashboard-variables', 'visitor-dashboard-utilities', 'visitor-dashboard-accessibility'],
            VISITOR_DASHBOARD_VERSION,
            'all'
        );
    }
}

/**
 * Enqueue public/client dashboard styles
 */
function visitor_dashboard_public_styles() {
    // Only load on dashboard pages
    if (is_visitor_dashboard_page()) {
        visitor_dashboard_enqueue_styles();
        
        wp_enqueue_style(
            'visitor-dashboard-client',
            VISITOR_DASHBOARD_PLUGIN_URL . 'assets/css/public/client-dashboard.css',
            ['visitor-dashboard-variables', 'visitor-dashboard-utilities', 'visitor-dashboard-accessibility'],
            VISITOR_DASHBOARD_VERSION,
            'all'
        );
    }
}

/**
 * Check if current page is a visitor dashboard page
 */
function is_visitor_dashboard_page() {
    // Add logic to determine if current page is dashboard
    // This could check for specific page templates, query vars, etc.
    return (
        is_page('dashboard') || 
        is_page('visitor-dashboard') ||
        (isset($_GET['page']) && strpos($_GET['page'], 'visitor-dashboard') !== false)
    );
}

// Hook the enqueue functions
add_action('admin_enqueue_scripts', 'visitor_dashboard_admin_styles');
add_action('wp_enqueue_scripts', 'visitor_dashboard_public_styles');
