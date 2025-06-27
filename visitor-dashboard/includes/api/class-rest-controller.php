<?php
/**
 * REST API Controller Class
 *
 * @package    Visitor_Dashboard
 * @subpackage Visitor_Dashboard/includes/api
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * REST API endpoints for Visitor Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Visitor_Dashboard_REST_Controller {
    
    /**
     * Initialize REST API endpoints
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register all REST API routes
     */
    public static function register_routes() {
        $namespace = 'visitor-dashboard/v1';
        
        // Visitor endpoints
        register_rest_route($namespace, '/visitors', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_visitors'),
            'permission_callback' => array(__CLASS__, 'check_read_visitors_permission')
        ));
        
        register_rest_route($namespace, '/visitors/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_visitor'),
            'permission_callback' => array(__CLASS__, 'check_read_visitors_permission')
        ));
        
        register_rest_route($namespace, '/visitors/(?P<id>\d+)/crm-add', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'add_visitor_to_crm'),
            'permission_callback' => array(__CLASS__, 'check_manage_visitors_permission')
        ));
        
        register_rest_route($namespace, '/visitors/(?P<id>\d+)/delete', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'delete_visitor'),
            'permission_callback' => array(__CLASS__, 'check_manage_visitors_permission')
        ));
        
        register_rest_route($namespace, '/visitors/(?P<id>\d+)/restore', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'restore_visitor'),
            'permission_callback' => array(__CLASS__, 'check_restore_visitors_permission')
        ));
        
        // Analytics endpoints
        register_rest_route($namespace, '/analytics/summary', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_analytics_summary'),
            'permission_callback' => array(__CLASS__, 'check_analytics_permission')
        ));
        
        register_rest_route($namespace, '/analytics/campaign-performance', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_campaign_performance'),
            'permission_callback' => array(__CLASS__, 'check_analytics_permission')
        ));
        
        // Admin endpoints
        register_rest_route($namespace, '/admin/clients', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_clients'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission')
        ));
        
        register_rest_route($namespace, '/admin/users', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_dashboard_users'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission')
        ));
        
        register_rest_route($namespace, '/admin/crm-queue', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_crm_queue'),
            'permission_callback' => array(__CLASS__, 'check_crm_permission')
        ));
        
        register_rest_route($namespace, '/admin/crm-export', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'export_crm_data'),
            'permission_callback' => array(__CLASS__, 'check_crm_permission')
        ));
        
        // Data import endpoints (for Make.com webhooks)
        register_rest_route($namespace, '/import/rb2b', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'import_rb2b_data'),
            'permission_callback' => array(__CLASS__, 'check_import_permission')
        ));
        
        register_rest_route($namespace, '/import/groundtruth', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'import_groundtruth_data'),
            'permission_callback' => array(__CLASS__, 'check_import_permission')
        ));
    }
    
    /**
     * Get visitors list
     */
    public static function get_visitors($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $params = $request->get_params();
        $current_user_id = get_current_user_id();
        
        // Pagination
        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $per_page = isset($params['per_page']) ? min(100, max(1, intval($params['per_page']))) : 20;
        $offset = ($page - 1) * $per_page;
        
        // Filters
        $client_id = isset($params['client_id']) ? intval($params['client_id']) : null;
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : 'active';
        $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
        
        // Check client access
        if ($client_id && !VisitorDashboard_User_Roles::user_can_access_client($current_user_id, $client_id)) {
            return new WP_Error('access_denied', 'Access denied to this client data', array('status' => 403));
        }
        
        // If not admin and no client_id specified, get user's client
        if (!current_user_can('manage_all_visitors') && !$client_id) {
            $client_id = VisitorDashboard_User_Roles::get_user_client_id($current_user_id);
        }
        
        // Build query
        $where_conditions = array();
        $query_params = array();
        
        if ($client_id) {
            $where_conditions[] = "v.client_id = %d";
            $query_params[] = $client_id;
        }
        
        if ($status) {
            $where_conditions[] = "v.status = %s";
            $query_params[] = $status;
        }
        
        if ($search) {
            $where_conditions[] = "(v.first_name LIKE %s OR v.last_name LIKE %s OR v.company_name LIKE %s OR v.title LIKE %s)";
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Count total records
        $count_query = "SELECT COUNT(*) FROM {$prefix}visitors v {$where_clause}";
        $total = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
        
        // Get visitors
        $query = "SELECT v.*, c.account_name 
                  FROM {$prefix}visitors v 
                  LEFT JOIN {$prefix}clients c ON v.client_id = c.id 
                  {$where_clause} 
                  ORDER BY v.last_seen_at DESC 
                  LIMIT %d OFFSET %d";
        
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        $visitors = $wpdb->get_results($wpdb->prepare($query, $query_params));
        
        // Format visitor data
        $formatted_visitors = array();
        foreach ($visitors as $visitor) {
            $formatted_visitors[] = self::format_visitor_data($visitor);
        }
        
        return array(
            'visitors' => $formatted_visitors,
            'total' => intval($total),
            'pages' => ceil($total / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        );
    }
    
    /**
     * Get single visitor
     */
    public static function get_visitor($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $visitor_id = intval($request['id']);
        $current_user_id = get_current_user_id();
        
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, c.account_name 
             FROM {$prefix}visitors v 
             LEFT JOIN {$prefix}clients c ON v.client_id = c.id 
             WHERE v.id = %d",
            $visitor_id
        ));
        
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Visitor not found', array('status' => 404));
        }
        
        // Check access
        if (!VisitorDashboard_User_Roles::user_can_access_client($current_user_id, $visitor->client_id)) {
            return new WP_Error('access_denied', 'Access denied', array('status' => 403));
        }
        
        return self::format_visitor_data($visitor);
    }
    
    /**
     * Add visitor to CRM
     */
    public static function add_visitor_to_crm($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $visitor_id = intval($request['id']);
        $current_user_id = get_current_user_id();
        
        // Get visitor
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}visitors WHERE id = %d",
            $visitor_id
        ));
        
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Visitor not found', array('status' => 404));
        }
        
        // Check access
        if (!VisitorDashboard_User_Roles::user_can_access_client($current_user_id, $visitor->client_id)) {
            return new WP_Error('access_denied', 'Access denied', array('status' => 403));
        }
        
        // Update visitor status
        $result = $wpdb->update(
            "{$prefix}visitors",
            array(
                'status' => 'crm_added',
                'crm_added_at' => current_time('mysql'),
                'crm_added_by' => $current_user_id
            ),
            array('id' => $visitor_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the action
            self::log_visitor_action('visitor_crm_add', $visitor_id, $current_user_id, $visitor->client_id);
            
            return array(
                'success' => true,
                'message' => 'Visitor added to CRM queue'
            );
        }
        
        return new WP_Error('update_failed', 'Failed to update visitor', array('status' => 500));
    }
    
    /**
     * Delete (archive) visitor
     */
    public static function delete_visitor($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $visitor_id = intval($request['id']);
        $current_user_id = get_current_user_id();
        
        // Get visitor
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}visitors WHERE id = %d",
            $visitor_id
        ));
        
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Visitor not found', array('status' => 404));
        }
        
        // Check access
        if (!VisitorDashboard_User_Roles::user_can_access_client($current_user_id, $visitor->client_id)) {
            return new WP_Error('access_denied', 'Access denied', array('status' => 403));
        }
        
        // Update visitor status (soft delete)
        $result = $wpdb->update(
            "{$prefix}visitors",
            array(
                'status' => 'deleted',
                'deleted_at' => current_time('mysql'),
                'deleted_by' => $current_user_id
            ),
            array('id' => $visitor_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the action
            self::log_visitor_action('visitor_delete', $visitor_id, $current_user_id, $visitor->client_id);
            
            return array(
                'success' => true,
                'message' => 'Visitor archived'
            );
        }
        
        return new WP_Error('update_failed', 'Failed to archive visitor', array('status' => 500));
    }
    
    /**
     * Restore visitor
     */
    public static function restore_visitor($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $visitor_id = intval($request['id']);
        $current_user_id = get_current_user_id();
        
        // Check admin permission
        if (!current_user_can('restore_visitors')) {
            return new WP_Error('permission_denied', 'Permission denied', array('status' => 403));
        }
        
        // Get visitor
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}visitors WHERE id = %d AND status = 'deleted'",
            $visitor_id
        ));
        
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Deleted visitor not found', array('status' => 404));
        }
        
        // Restore visitor
        $result = $wpdb->update(
            "{$prefix}visitors",
            array(
                'status' => 'active',
                'deleted_at' => null,
                'deleted_by' => null
            ),
            array('id' => $visitor_id),
            array('%s', null, null),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the action
            self::log_visitor_action('visitor_restore', $visitor_id, $current_user_id, $visitor->client_id);
            
            return array(
                'success' => true,
                'message' => 'Visitor restored'
            );
        }
        
        return new WP_Error('update_failed', 'Failed to restore visitor', array('status' => 500));
    }
    
    /**
     * Get analytics summary
     */
    public static function get_analytics_summary($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $current_user_id = get_current_user_id();
        $params = $request->get_params();
        $client_id = isset($params['client_id']) ? intval($params['client_id']) : null;
        
        // Check client access
        if ($client_id && !VisitorDashboard_User_Roles::user_can_access_client($current_user_id, $client_id)) {
            return new WP_Error('access_denied', 'Access denied', array('status' => 403));
        }
        
        // If not admin and no client_id specified, get user's client
        if (!current_user_can('manage_all_visitors') && !$client_id) {
            $client_id = VisitorDashboard_User_Roles::get_user_client_id($current_user_id);
        }
        
        $where_clause = $client_id ? "WHERE client_id = $client_id" : "";
        
        // Get visitor statistics
        $stats = array();
        
        // Total active visitors
        $stats['total_visitors'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'active' " . ($client_id ? "AND client_id = $client_id" : ""));
        
        // New visitors this week
        $stats['new_this_week'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE DATE(created_at) >= %s " . ($client_id ? "AND client_id = $client_id" : ""),
            date('Y-m-d', strtotime('-7 days'))
        ));
        
        // Visitors added to CRM today
        $stats['crm_added_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND DATE(crm_added_at) = %s " . ($client_id ? "AND client_id = $client_id" : ""),
            current_time('Y-m-d')
        ));
        
        // Campaign performance (if available)
        if ($client_id) {
            $campaign_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    SUM(total_impressions) as total_impressions,
                    SUM(clicks) as total_clicks,
                    AVG(ctr) as avg_ctr,
                    SUM(visits) as total_visits,
                    SUM(total_spent) as total_spent
                 FROM {$prefix}campaign_performance 
                 WHERE client_id = %d AND report_date >= %s",
                $client_id,
                date('Y-m-d', strtotime('-30 days'))
            ));
            
            if ($campaign_stats) {
                $stats['campaign_impressions'] = intval($campaign_stats->total_impressions);
                $stats['campaign_clicks'] = intval($campaign_stats->total_clicks);
                $stats['campaign_ctr'] = round(floatval($campaign_stats->avg_ctr), 4);
                $stats['campaign_visits'] = intval($campaign_stats->total_visits);
                $stats['campaign_spent'] = floatval($campaign_stats->total_spent);
            }
        }
        
        return $stats;
    }
    
    /**
     * Get campaign performance data
     */
    public static function get_campaign_performance($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $current_user_id = get_current_user_id();
        $params = $request->get_params();
        $client_id = isset($params['client_id']) ? intval($params['client_id']) : null;
        
        // Check client access
        if ($client_id && !VisitorDashboard_User_Roles::user_can_access_client($current_user_id, $client_id)) {
            return new WP_Error('access_denied', 'Access denied', array('status' => 403));
        }
        
        if (!$client_id && !current_user_can('manage_all_visitors')) {
            $client_id = VisitorDashboard_User_Roles::get_user_client_id($current_user_id);
        }
        
        $date_range = isset($params['days']) ? intval($params['days']) : 30;
        
        // Get campaign performance data
        $performance_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                campaign_name,
                ad_group_name,
                SUM(total_impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr,
                SUM(visits) as visits,
                SUM(total_spent) as spent,
                SUM(daily_reach) as reach
             FROM {$prefix}campaign_performance 
             WHERE " . ($client_id ? "client_id = %d AND " : "") . "report_date >= %s
             GROUP BY campaign_id, ad_group_id
             ORDER BY impressions DESC
             LIMIT 20",
            $client_id ? array($client_id, date('Y-m-d', strtotime("-{$date_range} days"))) : array(date('Y-m-d', strtotime("-{$date_range} days")))
        ));
        
        // Get impressions trend
        $trend_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                report_date,
                SUM(total_impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(visits) as visits
             FROM {$prefix}campaign_performance 
             WHERE " . ($client_id ? "client_id = %d AND " : "") . "report_date >= %s
             GROUP BY report_date
             ORDER BY report_date ASC",
            $client_id ? array($client_id, date('Y-m-d', strtotime("-{$date_range} days"))) : array(date('Y-m-d', strtotime("-{$date_range} days")))
        ));
        
        return array(
            'performance_data' => $performance_data,
            'trend_data' => $trend_data
        );
    }
    
    /**
     * Get clients (admin only)
     */
    public static function get_clients($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $clients = $wpdb->get_results(
            "SELECT c.*, 
                    COUNT(v.id) as visitor_count,
                    COUNT(CASE WHEN v.status = 'active' THEN 1 END) as active_visitors,
                    COUNT(CASE WHEN v.status = 'crm_added' THEN 1 END) as crm_visitors
             FROM {$prefix}clients c
             LEFT JOIN {$prefix}visitors v ON c.id = v.client_id
             WHERE c.status = 'active'
             GROUP BY c.id
             ORDER BY c.account_name"
        );
        
        return array('clients' => $clients);
    }
    
    /**
     * Get dashboard users (admin only)
     */
    public static function get_dashboard_users($request) {
        $users = VisitorDashboard_User_Roles::get_all_dashboard_users();
        return array('users' => $users);
    }
    
    /**
     * Get CRM queue
     */
    public static function get_crm_queue($request) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $params = $request->get_params();
        $client_id = isset($params['client_id']) ? intval($params['client_id']) : null;
        
        $where_clause = "WHERE v.status = 'crm_added'";
        $query_params = array();
        
        if ($client_id) {
            $where_clause .= " AND v.client_id = %d";
            $query_params[] = $client_id;
        }
        
        $crm_queue = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, c.account_name, u.display_name as added_by_name
             FROM {$prefix}visitors v
             LEFT JOIN {$prefix}clients c ON v.client_id = c.id
             LEFT JOIN {$wpdb->users} u ON v.crm_added_by = u.ID
             {$where_clause}
             ORDER BY v.crm_added_at DESC",
            $query_params
        ));
        
        return array(
            'queue' => array_map(array(__CLASS__, 'format_visitor_data'), $crm_queue),
            'total' => count($crm_queue)
        );
    }
    
    /**
     * Export CRM data
     */
    public static function export_crm_data($request) {
        $params = $request->get_params();
        $client_id = isset($params['client_id']) ? intval($params['client_id']) : null;
        
        // Use CRM Exporter class
        require_once VISITOR_DASHBOARD_PLUGIN_PATH . 'includes/class-crm-exporter.php';
        $exporter = new VisitorDashboard_CRM_Exporter();
        
        $result = $exporter->export_daily_crm_data($client_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'message' => 'CRM export completed',
                'export_id' => $result['export_id'],
                'visitor_count' => $result['visitor_count']
            );
        } else {
            return new WP_Error('export_failed', $result['message'], array('status' => 500));
        }
    }
    
    /**
     * Import RB2B data (webhook endpoint)
     */
    public static function import_rb2b_data($request) {
        // Verify API key or webhook authentication
        if (!self::verify_webhook_auth($request)) {
            return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
        }
        
        $params = $request->get_params();
        $csv_data = isset($params['csv_data']) ? $params['csv_data'] : '';
        $filename = isset($params['filename']) ? $params['filename'] : '';
        
        if (empty($csv_data) || empty($filename)) {
            return new WP_Error('missing_data', 'Missing CSV data or filename', array('status' => 400));
        }
        
        // Use Data Importer class
        require_once VISITOR_DASHBOARD_PLUGIN_PATH . 'includes/class-data-importer.php';
        $importer = new VisitorDashboard_Data_Importer();
        
        $result = $importer->import_rb2b_data($csv_data, $filename);
        
        return $result;
    }
    
    /**
     * Import GroundTruth data (webhook endpoint)
     */
    public static function import_groundtruth_data($request) {
        // Verify API key or webhook authentication
        if (!self::verify_webhook_auth($request)) {
            return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
        }
        
        $params = $request->get_params();
        $csv_data = isset($params['csv_data']) ? $params['csv_data'] : '';
        $filename = isset($params['filename']) ? $params['filename'] : '';
        
        if (empty($csv_data) || empty($filename)) {
            return new WP_Error('missing_data', 'Missing CSV data or filename', array('status' => 400));
        }
        
        // Use Data Importer class
        require_once VISITOR_DASHBOARD_PLUGIN_PATH . 'includes/class-data-importer.php';
        $importer = new VisitorDashboard_Data_Importer();
        
        $result = $importer->import_groundtruth_data($csv_data, $filename);
        
        return $result;
    }
    
    /**
     * Format visitor data for API response
     */
    private static function format_visitor_data($visitor) {
        return array(
            'id' => intval($visitor->id),
            'client_id' => intval($visitor->client_id),
            'client_name' => $visitor->account_name ?? '',
            'linkedin_url' => $visitor->linkedin_url,
            'first_name' => $visitor->first_name,
            'last_name' => $visitor->last_name,
            'full_name' => trim($visitor->first_name . ' ' . $visitor->last_name),
            'title' => $visitor->title,
            'company_name' => $visitor->company_name,
            'all_time_page_views' => intval($visitor->all_time_page_views),
            'work_email' => $visitor->work_email,
            'website' => $visitor->website,
            'industry' => $visitor->industry,
            'estimated_employee_count' => $visitor->estimated_employee_count,
            'estimate_revenue' => $visitor->estimate_revenue,
            'city' => $visitor->city,
            'state' => $visitor->state,
            'zipcode' => $visitor->zipcode,
            'location' => trim($visitor->city . ', ' . $visitor->state . ' ' . $visitor->zipcode),
            'last_seen_at' => $visitor->last_seen_at,
            'first_seen_at' => $visitor->first_seen_at,
            'recent_page_count' => intval($visitor->recent_page_count),
            'recent_page_urls' => json_decode($visitor->recent_page_urls, true) ?? array(),
            'status' => $visitor->status,
            'created_at' => $visitor->created_at,
            'crm_added_at' => $visitor->crm_added_at,
            'deleted_at' => $visitor->deleted_at
        );
    }
    
    /**
     * Log visitor action
     */
    private static function log_visitor_action($action, $visitor_id, $user_id, $client_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $wpdb->insert(
            "{$prefix}audit_logs",
            array(
                'user_id' => $user_id,
                'client_id' => $client_id,
                'action_type' => $action,
                'entity_type' => 'visitor',
                'entity_id' => $visitor_id,
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
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return 'unknown';
    }
    
    /**
     * Verify webhook authentication
     */
    private static function verify_webhook_auth($request) {
        $auth_header = $request->get_header('Authorization');
        $api_key = get_option('visitor_dashboard_api_key', '');
        
        if (empty($api_key)) {
            // Generate API key if not exists
            $api_key = wp_generate_password(32, false);
            update_option('visitor_dashboard_api_key', $api_key);
        }
        
        return ($auth_header === 'Bearer ' . $api_key);
    }
    
    // Permission callback functions
    public static function check_read_visitors_permission($request) {
        return current_user_can('read_visitors');
    }
    
    public static function check_manage_visitors_permission($request) {
        return current_user_can('manage_own_visitors') || current_user_can('manage_all_visitors');
    }
    
    public static function check_restore_visitors_permission($request) {
        return current_user_can('restore_visitors');
    }
    
    public static function check_analytics_permission($request) {
        return current_user_can('view_analytics');
    }
    
    public static function check_admin_permission($request) {
        return current_user_can('manage_all_visitors');
    }
    
    public static function check_crm_permission($request) {
        return current_user_can('export_crm_data') || current_user_can('manage_crm_queue');
    }
    
    public static function check_import_permission($request) {
        return current_user_can('import_data') || self::verify_webhook_auth($request);
    }
}
