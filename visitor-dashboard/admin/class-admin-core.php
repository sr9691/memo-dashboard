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

/**
 * Admin Management Class
 * File: includes/class-admin-manager.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Visitor_Dashboard_Admin_Core {
    
    /**
     * Get comprehensive dashboard statistics
     */
    public static function get_dashboard_statistics($client_id = null) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $stats = array();
        $where_clause = $client_id ? "WHERE client_id = $client_id" : "";
        
        // Visitor statistics
        $stats['visitors'] = array(
            'total_active' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'active' " . ($client_id ? "AND client_id = $client_id" : "")),
            'total_deleted' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'deleted' " . ($client_id ? "AND client_id = $client_id" : "")),
            'total_crm_added' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' " . ($client_id ? "AND client_id = $client_id" : "")),
            'new_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}visitors WHERE DATE(created_at) = %s " . ($client_id ? "AND client_id = $client_id" : ""),
                current_time('Y-m-d')
            )),
            'new_this_week' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}visitors WHERE created_at >= %s " . ($client_id ? "AND client_id = $client_id" : ""),
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )),
            'new_this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}visitors WHERE created_at >= %s " . ($client_id ? "AND client_id = $client_id" : ""),
                date('Y-m-d H:i:s', strtotime('-30 days'))
            ))
        );
        
        // CRM statistics
        $stats['crm'] = array(
            'added_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND DATE(crm_added_at) = %s " . ($client_id ? "AND client_id = $client_id" : ""),
                current_time('Y-m-d')
            )),
            'added_this_week' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND crm_added_at >= %s " . ($client_id ? "AND client_id = $client_id" : ""),
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )),
            'pending_export' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' " . ($client_id ? "AND client_id = $client_id" : "")),
            'total_exported' => $wpdb->get_var("SELECT SUM(visitor_count) FROM {$prefix}crm_exports WHERE status = 'completed' " . ($client_id ? "AND client_id = $client_id" : ""))
        );
        
        // Campaign performance (if available)
        if ($client_id) {
            $campaign_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    SUM(total_impressions) as total_impressions,
                    SUM(clicks) as total_clicks,
                    AVG(ctr) as avg_ctr,
                    SUM(visits) as total_visits,
                    SUM(total_spent) as total_spent,
                    SUM(daily_reach) as total_reach
                 FROM {$prefix}campaign_performance 
                 WHERE client_id = %d AND report_date >= %s",
                $client_id,
                date('Y-m-d', strtotime('-30 days'))
            ));
            
            $stats['campaign'] = array(
                'impressions' => intval($campaign_stats->total_impressions ?? 0),
                'clicks' => intval($campaign_stats->total_clicks ?? 0),
                'ctr' => round(floatval($campaign_stats->avg_ctr ?? 0), 4),
                'visits' => intval($campaign_stats->total_visits ?? 0),
                'spent' => floatval($campaign_stats->total_spent ?? 0),
                'reach' => intval($campaign_stats->total_reach ?? 0)
            );
        }
        
        // Client statistics (admin only)
        if (!$client_id) {
            $stats['clients'] = array(
                'total_active' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}clients WHERE status = 'active'"),
                'total_inactive' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}clients WHERE status = 'inactive'"),
                'with_visitors' => $wpdb->get_var("SELECT COUNT(DISTINCT client_id) FROM {$prefix}visitors WHERE status = 'active'"),
                'with_campaigns' => $wpdb->get_var("SELECT COUNT(DISTINCT client_id) FROM {$prefix}campaign_performance WHERE report_date >= '" . date('Y-m-d', strtotime('-30 days')) . "'")
            );
        }
        
        // User statistics (admin only)
        if (!$client_id) {
            $stats['users'] = VisitorDashboard_User_Roles::get_user_statistics();
        }
        
        return $stats;
    }
    
    /**
     * Get system health status
     */
    public static function get_system_health() {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $health = array(
            'overall_status' => 'healthy',
            'checks' => array()
        );
        
        // Database connectivity
        try {
            $wpdb->get_var("SELECT 1");
            $health['checks']['database'] = array(
                'status' => 'pass',
                'message' => 'Database connection successful'
            );
        } catch (Exception $e) {
            $health['checks']['database'] = array(
                'status' => 'fail',
                'message' => 'Database connection failed: ' . $e->getMessage()
            );
            $health['overall_status'] = 'critical';
        }
        
        // Plugin tables existence
        $required_tables = array('clients', 'visitors', 'dashboard_users', 'audit_logs', 'crm_exports', 'import_logs', 'campaign_performance');
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $table_name = $prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            $health['checks']['tables'] = array(
                'status' => 'pass',
                'message' => 'All required tables exist'
            );
        } else {
            $health['checks']['tables'] = array(
                'status' => 'fail',
                'message' => 'Missing tables: ' . implode(', ', $missing_tables)
            );
            $health['overall_status'] = 'critical';
        }
        
        // REST API availability
        if (rest_url()) {
            $health['checks']['rest_api'] = array(
                'status' => 'pass',
                'message' => 'REST API is available'
            );
        } else {
            $health['checks']['rest_api'] = array(
                'status' => 'fail',
                'message' => 'REST API is not available'
            );
            $health['overall_status'] = 'warning';
        }
        
        // Cron jobs
        $cron_scheduled = wp_next_scheduled('visitor_dashboard_daily_crm_export');
        if ($cron_scheduled) {
            $health['checks']['cron'] = array(
                'status' => 'pass',
                'message' => 'Daily CRM export scheduled for ' . date('Y-m-d H:i:s', $cron_scheduled)
            );
        } else {
            $health['checks']['cron'] = array(
                'status' => 'warning',
                'message' => 'Daily CRM export not scheduled'
            );
            if ($health['overall_status'] === 'healthy') {
                $health['overall_status'] = 'warning';
            }
        }
        
        // Email functionality
        if (function_exists('wp_mail')) {
            $health['checks']['email'] = array(
                'status' => 'pass',
                'message' => 'Email function available'
            );
        } else {
            $health['checks']['email'] = array(
                'status' => 'fail',
                'message' => 'Email function not available'
            );
            $health['overall_status'] = 'critical';
        }
        
        // File permissions for uploads
        $upload_dir = wp_upload_dir();
        if (is_writable($upload_dir['basedir'])) {
            $health['checks']['file_permissions'] = array(
                'status' => 'pass',
                'message' => 'Upload directory is writable'
            );
        } else {
            $health['checks']['file_permissions'] = array(
                'status' => 'fail',
                'message' => 'Upload directory is not writable'
            );
            $health['overall_status'] = 'warning';
        }
        
        // API key configuration
        $api_key = get_option('visitor_dashboard_api_key');
        if (!empty($api_key)) {
            $health['checks']['api_key'] = array(
                'status' => 'pass',
                'message' => 'API key is configured'
            );
        } else {
            $health['checks']['api_key'] = array(
                'status' => 'warning',
                'message' => 'API key not configured - webhook integration will not work'
            );
            if ($health['overall_status'] === 'healthy') {
                $health['overall_status'] = 'warning';
            }
        }
        
        // Recent data imports
        $recent_import = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$prefix}import_logs WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1"
        ));
        
        if ($recent_import) {
            $hours_ago = (time() - strtotime($recent_import)) / 3600;
            if ($hours_ago <= 48) {
                $health['checks']['data_imports'] = array(
                    'status' => 'pass',
                    'message' => 'Recent data import: ' . human_time_diff(strtotime($recent_import)) . ' ago'
                );
            } else {
                $health['checks']['data_imports'] = array(
                    'status' => 'warning',
                    'message' => 'Last successful import: ' . human_time_diff(strtotime($recent_import)) . ' ago'
                );
                if ($health['overall_status'] === 'healthy') {
                    $health['overall_status'] = 'warning';
                }
            }
        } else {
            $health['checks']['data_imports'] = array(
                'status' => 'warning',
                'message' => 'No successful data imports found'
            );
            if ($health['overall_status'] === 'healthy') {
                $health['overall_status'] = 'warning';
            }
        }
        
        return $health;
    }
    
    /**
     * Get recent activity logs
     */
    public static function get_recent_activity($limit = 50, $client_id = null, $action_filter = null) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_conditions = array();
        $query_params = array();
        
        if ($client_id) {
            $where_conditions[] = "al.client_id = %d";
            $query_params[] = $client_id;
        }
        
        if ($action_filter) {
            $where_conditions[] = "al.action_type = %s";
            $query_params[] = $action_filter;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $query_params[] = $limit;
        
        $query = "SELECT al.*, 
                         du.name as user_name,
                         c.account_name as client_name
                  FROM {$prefix}audit_logs al
                  LEFT JOIN {$prefix}dashboard_users du ON al.user_id = du.id
                  LEFT JOIN {$prefix}clients c ON al.client_id = c.id
                  {$where_clause}
                  ORDER BY al.created_at DESC
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $query_params));
    }
    
    /**
     * Get client performance summary
     */
    public static function get_client_performance_summary($days = 30) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $query = "SELECT c.id,
                         c.account_name,
                         c.groundtruth_account_id,
                         COUNT(DISTINCT v.id) as visitor_count,
                         COUNT(DISTINCT CASE WHEN v.status = 'active' THEN v.id END) as active_visitors,
                         COUNT(DISTINCT CASE WHEN v.status = 'crm_added' THEN v.id END) as crm_visitors,
                         COUNT(DISTINCT CASE WHEN v.created_at >= %s THEN v.id END) as new_visitors,
                         SUM(cp.total_impressions) as total_impressions,
                         SUM(cp.clicks) as total_clicks,
                         AVG(cp.ctr) as avg_ctr,
                         SUM(cp.total_spent) as total_spent
                  FROM {$prefix}clients c
                  LEFT JOIN {$prefix}visitors v ON c.id = v.client_id
                  LEFT JOIN {$prefix}campaign_performance cp ON c.id = cp.client_id AND cp.report_date >= %s
                  WHERE c.status = 'active'
                  GROUP BY c.id
                  ORDER BY visitor_count DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $cutoff_date, $cutoff_date));
    }
    
    /**
     * Get top performing clients
     */
    public static function get_top_performing_clients($metric = 'visitors', $limit = 10) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $order_by = 'visitor_count DESC';
        
        switch ($metric) {
            case 'impressions':
                $order_by = 'total_impressions DESC';
                break;
            case 'clicks':
                $order_by = 'total_clicks DESC';
                break;
            case 'ctr':
                $order_by = 'avg_ctr DESC';
                break;
            case 'spent':
                $order_by = 'total_spent DESC';
                break;
            case 'crm_conversions':
                $order_by = 'crm_visitors DESC';
                break;
        }
        
        $query = "SELECT c.account_name,
                         c.groundtruth_account_id,
                         COUNT(DISTINCT v.id) as visitor_count,
                         COUNT(DISTINCT CASE WHEN v.status = 'crm_added' THEN v.id END) as crm_visitors,
                         SUM(cp.total_impressions) as total_impressions,
                         SUM(cp.clicks) as total_clicks,
                         AVG(cp.ctr) as avg_ctr,
                         SUM(cp.total_spent) as total_spent
                  FROM {$prefix}clients c
                  LEFT JOIN {$prefix}visitors v ON c.id = v.client_id
                  LEFT JOIN {$prefix}campaign_performance cp ON c.id = cp.client_id 
                      AND cp.report_date >= %s
                  WHERE c.status = 'active'
                  GROUP BY c.id
                  ORDER BY {$order_by}
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare(
            $query,
            date('Y-m-d', strtotime('-30 days')),
            $limit
        ));
    }
    
    /**
     * Get visitor trends over time
     */
    public static function get_visitor_trends($days = 30, $client_id = null) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id" : "";
        
        $query = "SELECT DATE(created_at) as date,
                         COUNT(*) as new_visitors,
                         COUNT(CASE WHEN status = 'crm_added' THEN 1 END) as crm_added
                  FROM {$prefix}visitors
                  {$where_clause}
                  AND created_at >= %s
                  GROUP BY DATE(created_at)
                  ORDER BY date ASC";
        
        return $wpdb->get_results($wpdb->prepare(
            $query,
            date('Y-m-d H:i:s', strtotime("-{$days} days"))
        ));
    }
    
    /**
     * Get import/export statistics
     */
    public static function get_import_export_stats($days = 30) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Import statistics
        $stats['imports'] = array(
            'total' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s",
                $cutoff_date
            )),
            'successful' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND status = 'completed'",
                $cutoff_date
            )),
            'failed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND status = 'failed'",
                $cutoff_date
            )),
            'rb2b' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND source = 'rb2b'",
                $cutoff_date
            )),
            'groundtruth' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND source = 'groundtruth'",
                $cutoff_date
            )),
            'records_processed' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(records_processed) FROM {$prefix}import_logs WHERE created_at >= %s",
                $cutoff_date
            ))
        );
        
        // Export statistics
        $stats['exports'] = array(
            'total' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}crm_exports WHERE created_at >= %s",
                $cutoff_date
            )),
            'successful' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}crm_exports WHERE created_at >= %s AND status = 'completed'",
                $cutoff_date
            )),
            'failed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}crm_exports WHERE created_at >= %s AND status = 'failed'",
                $cutoff_date
            )),
            'visitors_exported' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(visitor_count) FROM {$prefix}crm_exports WHERE created_at >= %s AND status = 'completed'",
                $cutoff_date
            )),
            'emails_sent' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}crm_exports WHERE created_at >= %s AND email_sent = 1",
                $cutoff_date
            ))
        );
        
        return $stats;
    }
    
    /**
     * Clean up old data based on retention policies
     */
    public static function cleanup_old_data() {
        $results = array();
        
        // Clean up visitors
        $visitor_cleanup = VisitorDashboard_Visitor_Manager::cleanup_old_visitors();
        $results['visitors_deleted'] = $visitor_cleanup;
        
        // Clean up audit logs
        $audit_cleanup = VisitorDashboard_Database::cleanup_old_data();
        $results['audit_logs_cleaned'] = $audit_cleanup;
        
        // Clean up import logs
        require_once VISITOR_DASHBOARD_PLUGIN_PATH . 'includes/class-data-importer.php';
        $importer = new VisitorDashboard_Data_Importer();
        $import_cleanup = $importer->cleanup_old_import_logs();
        $results['import_logs_cleaned'] = $import_cleanup;
        
        // Clean up export files
        require_once VISITOR_DASHBOARD_PLUGIN_PATH . 'includes/class-crm-exporter.php';
        $exporter = new VisitorDashboard_CRM_Exporter();
        $export_cleanup = $exporter->cleanup_old_exports();
        $results['export_files_cleaned'] = $export_cleanup;
        
        return $results;
    }
    
    /**
     * Get database optimization recommendations
     */
    public static function get_optimization_recommendations() {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $recommendations = array();
        
        // Check table sizes
        $table_sizes = $wpdb->get_results($wpdb->prepare(
            "SELECT table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size in MB'
             FROM information_schema.TABLES 
             WHERE table_schema = %s 
             AND table_name LIKE %s
             ORDER BY (data_length + index_length) DESC",
            DB_NAME,
            $prefix . '%'
        ));
        
        foreach ($table_sizes as $table) {
            if ($table->{'Size in MB'} > 100) {
                $recommendations[] = array(
                    'type' => 'warning',
                    'message' => "Table {$table->table_name} is large ({$table->{'Size in MB'}} MB). Consider archiving old data."
                );
            }
        }
        
        // Check for old data
        $old_visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-1 year'))
        ));
        
        if ($old_visitors > 1000) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => "{$old_visitors} visitors are older than 1 year. Consider running data cleanup."
            );
        }
        
        // Check audit log size
        $old_logs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}audit_logs WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
        
        if ($old_logs > 10000) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => "{$old_logs} audit logs are older than 90 days. Consider running log cleanup."
            );
        }
        
        // Check for failed imports/exports
        $failed_imports = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}import_logs WHERE status = 'failed' AND created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        if ($failed_imports > 5) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => "{$failed_imports} import failures in the last 7 days. Check data source configurations."
            );
        }
        
        // Check API key configuration
        $api_key = get_option('visitor_dashboard_api_key');
        if (empty($api_key)) {
            $recommendations[] = array(
                'type' => 'error',
                'message' => 'API key not configured. Webhook integrations will not work.'
            );
        }
        
        // Check cron scheduling
        if (!wp_next_scheduled('visitor_dashboard_daily_crm_export')) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => 'Daily CRM export not scheduled. Automatic exports will not run.'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Export system configuration
     */
    public static function export_system_config() {
        $config = array(
            'plugin_version' => VISITOR_DASHBOARD_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $GLOBALS['wpdb']->db_version(),
            'exported_at' => current_time('c'),
            'settings' => array(
                'general' => array(
                    'crm_email' => get_option('visitor_dashboard_crm_email'),
                    'data_retention_days' => get_option('visitor_dashboard_data_retention_days'),
                    'enable_audit_logging' => get_option('visitor_dashboard_enable_audit_logging'),
                    'enable_daily_export' => get_option('visitor_dashboard_enable_daily_export'),
                    'export_time' => get_option('visitor_dashboard_export_time')
                ),
                'api' => array(
                    'webhook_secret' => get_option('visitor_dashboard_webhook_secret'),
                    'rate_limit' => get_option('visitor_dashboard_rate_limit'),
                    'enable_api_logging' => get_option('visitor_dashboard_enable_api_logging')
                ),
                'email' => array(
                    'smtp_host' => get_option('visitor_dashboard_smtp_host'),
                    'smtp_port' => get_option('visitor_dashboard_smtp_port'),
                    'smtp_username' => get_option('visitor_dashboard_smtp_username'),
                    'smtp_encryption' => get_option('visitor_dashboard_smtp_encryption'),
                    'email_from_name' => get_option('visitor_dashboard_email_from_name'),
                    'email_from_address' => get_option('visitor_dashboard_email_from_address')
                )
            ),
            'system_health' => self::get_system_health(),
            'statistics' => self::get_dashboard_statistics()
        );
        
        return $config;
    }
    
    /**
     * Generate admin summary report
     */
    public static function generate_summary_report($period = 'week') {
        $days = ($period === 'month') ? 30 : 7;
        $stats = self::get_dashboard_statistics();
        $trends = self::get_visitor_trends($days);
        $import_export = self::get_import_export_stats($days);
        $top_clients = self::get_top_performing_clients('visitors', 5);
        
        $report = array(
            'period' => $period,
            'generated_at' => current_time('c'),
            'summary' => array(
                'total_visitors' => $stats['visitors']['total_active'],
                'new_visitors' => ($period === 'month') ? $stats['visitors']['new_this_month'] : $stats['visitors']['new_this_week'],
                'crm_additions' => ($period === 'month') ? 
                    array_sum(array_column($trends, 'crm_added')) : 
                    $stats['crm']['added_this_week'],
                'data_imports' => $import_export['imports']['successful'],
                'crm_exports' => $import_export['exports']['successful']
            ),
            'trends' => $trends,
            'top_clients' => $top_clients,
            'import_export_stats' => $import_export,
            'recommendations' => self::get_optimization_recommendations()
        );
        
        return $report;
    }
}
