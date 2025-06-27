<?php
/**
 * visitor manager Class
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
 * Visitor Management Class
 * File: includes/class-visitor-manager.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VisitorDashboard_Visitor_Manager {
    
    /**
     * Get visitors with filtering and pagination
     */
    public static function get_visitors($args = array()) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $defaults = array(
            'client_id' => null,
            'status' => 'active',
            'search' => '',
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'last_seen_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = array();
        $query_params = array();
        
        if ($args['client_id']) {
            $where_conditions[] = "v.client_id = %d";
            $query_params[] = $args['client_id'];
        }
        
        if ($args['status']) {
            $where_conditions[] = "v.status = %s";
            $query_params[] = $args['status'];
        }
        
        if ($args['search']) {
            $where_conditions[] = "(v.first_name LIKE %s OR v.last_name LIKE %s OR v.company_name LIKE %s OR v.title LIKE %s)";
            $search_param = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Count total records
        $count_query = "SELECT COUNT(*) FROM {$prefix}visitors v {$where_clause}";
        $total = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
        
        // Calculate pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Get visitors
        $query = "SELECT v.*, c.account_name 
                  FROM {$prefix}visitors v 
                  LEFT JOIN {$prefix}clients c ON v.client_id = c.id 
                  {$where_clause} 
                  ORDER BY v.{$args['orderby']} {$args['order']} 
                  LIMIT %d OFFSET %d";
        
        $query_params[] = $args['per_page'];
        $query_params[] = $offset;
        
        $visitors = $wpdb->get_results($wpdb->prepare($query, $query_params));
        
        return array(
            'visitors' => $visitors,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
            'per_page' => $args['per_page']
        );
    }
    
    /**
     * Get single visitor by ID
     */
    public static function get_visitor($visitor_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, c.account_name 
             FROM {$prefix}visitors v 
             LEFT JOIN {$prefix}clients c ON v.client_id = c.id 
             WHERE v.id = %d",
            $visitor_id
        ));
    }
    
    /**
     * Add visitor to CRM queue
     */
    public static function add_to_crm($visitor_id, $user_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        // Get visitor first
        $visitor = self::get_visitor($visitor_id);
        if (!$visitor) {
            return array('success' => false, 'message' => 'Visitor not found');
        }
        
        // Check if already in CRM
        if ($visitor->status === 'crm_added') {
            return array('success' => false, 'message' => 'Visitor already in CRM queue');
        }
        
        // Update visitor status
        $result = $wpdb->update(
            "{$prefix}visitors",
            array(
                'status' => 'crm_added',
                'crm_added_at' => current_time('mysql'),
                'crm_added_by' => $user_id
            ),
            array('id' => $visitor_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the action
            self::log_visitor_action('visitor_crm_add', $visitor_id, $user_id, $visitor->client_id);
            
            return array('success' => true, 'message' => 'Visitor added to CRM queue');
        }
        
        return array('success' => false, 'message' => 'Failed to add visitor to CRM');
    }
    
    /**
     * Archive (soft delete) visitor
     */
    public static function archive_visitor($visitor_id, $user_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        // Get visitor first
        $visitor = self::get_visitor($visitor_id);
        if (!$visitor) {
            return array('success' => false, 'message' => 'Visitor not found');
        }
        
        // Update visitor status
        $result = $wpdb->update(
            "{$prefix}visitors",
            array(
                'status' => 'deleted',
                'deleted_at' => current_time('mysql'),
                'deleted_by' => $user_id
            ),
            array('id' => $visitor_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the action
            self::log_visitor_action('visitor_delete', $visitor_id, $user_id, $visitor->client_id);
            
            return array('success' => true, 'message' => 'Visitor archived');
        }
        
        return array('success' => false, 'message' => 'Failed to archive visitor');
    }
    
    /**
     * Restore archived visitor
     */
    public static function restore_visitor($visitor_id, $user_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        // Get visitor first
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}visitors WHERE id = %d AND status = 'deleted'",
            $visitor_id
        ));
        
        if (!$visitor) {
            return array('success' => false, 'message' => 'Archived visitor not found');
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
            self::log_visitor_action('visitor_restore', $visitor_id, $user_id, $visitor->client_id);
            
            return array('success' => true, 'message' => 'Visitor restored');
        }
        
        return array('success' => false, 'message' => 'Failed to restore visitor');
    }
    
    /**
     * Create or update visitor from RB2B data
     */
    public static function create_or_update_visitor($data, $client_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        // Validate required fields
        if (empty($data['LinkedInUrl'])) {
            return array('success' => false, 'message' => 'LinkedIn URL is required');
        }
        
        // Check if visitor exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}visitors WHERE linkedin_url = %s",
            $data['LinkedInUrl']
        ));
        
        // Prepare visitor data
        $visitor_data = array(
            'client_id' => $client_id,
            'linkedin_url' => $data['LinkedInUrl'],
            'first_name' => $data['FirstName'] ?? null,
            'last_name' => $data['LastName'] ?? null,
            'title' => $data['Title'] ?? null,
            'company_name' => $data['CompanyName'] ?? null,
            'all_time_page_views' => intval($data['AllTimePageViews'] ?? 0),
            'work_email' => $data['WorkEmail'] ?? null,
            'website' => $data['Website'] ?? null,
            'industry' => $data['Industry'] ?? null,
            'estimated_employee_count' => $data['EstimatedEmployeeCount'] ?? null,
            'estimate_revenue' => $data['EstimateRevenue'] ?? null,
            'city' => $data['City'] ?? null,
            'state' => $data['State'] ?? null,
            'zipcode' => $data['Zipcode'] ?? null,
            'last_seen_at' => self::parse_datetime($data['LastSeenAt'] ?? null),
            'first_seen_at' => self::parse_datetime($data['FirstSeenAt'] ?? null),
            'new_profile' => isset($data['NewProfile']) ? (bool)$data['NewProfile'] : false,
            'most_recent_referrer' => $data['MostRecentReferrer'] ?? null,
            'recent_page_count' => intval($data['RecentPageCount'] ?? 0),
            'recent_page_urls' => isset($data['RecentPageUrls']) ? json_encode($data['RecentPageUrls']) : null,
            'tags' => isset($data['Tags']) ? json_encode($data['Tags']) : null,
            'filter_matches' => isset($data['FilterMatches']) ? json_encode($data['FilterMatches']) : null,
            'profile_type' => $data['ProfileType'] ?? 'Person'
        );
        
        if ($existing) {
            // Update existing visitor
            $result = $wpdb->update(
                "{$prefix}visitors",
                $visitor_data,
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                return array('success' => true, 'action' => 'updated', 'visitor_id' => $existing->id);
            }
        } else {
            // Create new visitor
            $visitor_data['status'] = 'active';
            
            $result = $wpdb->insert(
                "{$prefix}visitors",
                $visitor_data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                return array('success' => true, 'action' => 'created', 'visitor_id' => $wpdb->insert_id);
            }
        }
        
        return array('success' => false, 'message' => 'Failed to save visitor data');
    }
    
    /**
     * Get visitors for CRM export
     */
    public static function get_crm_queue($client_id = null, $date = null) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_conditions = array("v.status = 'crm_added'");
        $query_params = array();
        
        if ($client_id) {
            $where_conditions[] = "v.client_id = %d";
            $query_params[] = $client_id;
        }
        
        if ($date) {
            $where_conditions[] = "DATE(v.crm_added_at) = %s";
            $query_params[] = $date;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT v.*, c.account_name 
                  FROM {$prefix}visitors v 
                  LEFT JOIN {$prefix}clients c ON v.client_id = c.id 
                  {$where_clause} 
                  ORDER BY v.crm_added_at ASC";
        
        return $wpdb->get_results($wpdb->prepare($query, $query_params));
    }
    
    /**
     * Get visitor statistics
     */
    public static function get_visitor_stats($client_id = null) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id" : "";
        
        $stats = array();
        
        // Total active visitors
        $stats['total_active'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'active' " . ($client_id ? "AND client_id = $client_id" : ""));
        
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
        
        // Archived visitors
        $stats['archived'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'deleted' " . ($client_id ? "AND client_id = $client_id" : ""));
        
        // Top companies
        $stats['top_companies'] = $wpdb->get_results($wpdb->prepare(
            "SELECT company_name, COUNT(*) as visitor_count 
             FROM {$prefix}visitors 
             WHERE status = 'active' AND company_name IS NOT NULL " . ($client_id ? "AND client_id = %d " : "") . "
             GROUP BY company_name 
             ORDER BY visitor_count DESC 
             LIMIT 10",
            $client_id ? array($client_id) : array()
        ));
        
        // Recent activity
        $stats['recent_activity'] = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as new_visitors
             FROM {$prefix}visitors 
             WHERE created_at >= %s " . ($client_id ? "AND client_id = $client_id " : "") . "
             GROUP BY DATE(created_at) 
             ORDER BY date DESC 
             LIMIT 7",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        return $stats;
    }
    
    /**
     * Search visitors
     */
    public static function search_visitors($search_term, $client_id = null, $limit = 20) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_conditions = array(
            "v.status = 'active'",
            "(v.first_name LIKE %s OR v.last_name LIKE %s OR v.company_name LIKE %s OR v.title LIKE %s OR v.linkedin_url LIKE %s)"
        );
        
        $search_param = '%' . $wpdb->esc_like($search_term) . '%';
        $query_params = array($search_param, $search_param, $search_param, $search_param, $search_param);
        
        if ($client_id) {
            $where_conditions[] = "v.client_id = %d";
            $query_params[] = $client_id;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query_params[] = $limit;
        
        $query = "SELECT v.*, c.account_name 
                  FROM {$prefix}visitors v 
                  LEFT JOIN {$prefix}clients c ON v.client_id = c.id 
                  {$where_clause} 
                  ORDER BY v.last_seen_at DESC 
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $query_params));
    }
    
    /**
     * Get visitor engagement metrics
     */
    public static function get_engagement_metrics($visitor_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $visitor = self::get_visitor($visitor_id);
        if (!$visitor) {
            return false;
        }
        
        $metrics = array(
            'total_page_views' => intval($visitor->all_time_page_views),
            'recent_page_views' => intval($visitor->recent_page_count),
            'first_visit' => $visitor->first_seen_at,
            'last_visit' => $visitor->last_seen_at,
            'visit_frequency' => self::calculate_visit_frequency($visitor),
            'engagement_score' => self::calculate_engagement_score($visitor),
            'recent_pages' => json_decode($visitor->recent_page_urls, true) ?? array()
        );
        
        return $metrics;
    }
    
    /**
     * Calculate visit frequency
     */
    private static function calculate_visit_frequency($visitor) {
        if (!$visitor->first_seen_at || !$visitor->last_seen_at) {
            return 'Unknown';
        }
        
        $first_visit = strtotime($visitor->first_seen_at);
        $last_visit = strtotime($visitor->last_seen_at);
        $days_between = ($last_visit - $first_visit) / (60 * 60 * 24);
        
        if ($days_between < 1) {
            return 'Single day';
        } elseif ($days_between <= 7) {
            return 'Weekly';
        } elseif ($days_between <= 30) {
            return 'Monthly';
        } else {
            return 'Occasional';
        }
    }
    
    /**
     * Calculate engagement score
     */
    private static function calculate_engagement_score($visitor) {
        $score = 0;
        
        // Page views scoring
        $page_views = intval($visitor->all_time_page_views);
        if ($page_views >= 10) $score += 40;
        elseif ($page_views >= 5) $score += 25;
        elseif ($page_views >= 2) $score += 15;
        elseif ($page_views >= 1) $score += 5;
        
        // Recent activity scoring
        if ($visitor->last_seen_at) {
            $days_since_last = (time() - strtotime($visitor->last_seen_at)) / (60 * 60 * 24);
            if ($days_since_last <= 1) $score += 30;
            elseif ($days_since_last <= 7) $score += 20;
            elseif ($days_since_last <= 30) $score += 10;
        }
        
        // Profile completeness scoring
        if ($visitor->work_email) $score += 15;
        if ($visitor->title) $score += 10;
        if ($visitor->company_name) $score += 5;
        
        return min(100, $score);
    }
    
    /**
     * Parse datetime from various formats
     */
    private static function parse_datetime($datetime_string) {
        if (empty($datetime_string)) {
            return null;
        }
        
        // Try to parse the datetime
        $timestamp = strtotime($datetime_string);
        if ($timestamp === false) {
            return null;
        }
        
        return date('Y-m-d H:i:s', $timestamp);
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
     * Clean up old visitor data based on retention policy
     */
    public static function cleanup_old_visitors() {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $retention_days = get_option('data_retention_days', 365);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Get visitors that should be cleaned up based on campaign end dates
        $cleanup_query = "
            SELECT v.id 
            FROM {$prefix}visitors v
            LEFT JOIN {$prefix}campaign_performance cp ON v.client_id = cp.client_id
            WHERE v.status = 'deleted' 
            AND v.deleted_at < %s
            AND (cp.campaign_end_date < %s OR cp.campaign_end_date IS NULL)
        ";
        
        $visitors_to_delete = $wpdb->get_col($wpdb->prepare(
            $cleanup_query,
            $cutoff_date,
            date('Y-m-d', strtotime("-{$retention_days} days"))
        ));
        
        if (!empty($visitors_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($visitors_to_delete), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$prefix}visitors WHERE id IN ($placeholders)",
                $visitors_to_delete
            ));
            
            return count($visitors_to_delete);
        }
        
        return 0;
    }
    
    /**
     * Export visitors to CSV format
     */
    public static function export_to_csv($visitors) {
        $csv_data = array();
        
        // CSV Headers
        $csv_data[] = array(
            'LinkedIn URL',
            'First Name',
            'Last Name',
            'Title',
            'Company Name',
            'Total Page Views',
            'Work Email',
            'Website',
            'Industry',
            'Employee Count',
            'Revenue',
            'City',
            'State',
            'Zip Code',
            'Last Seen',
            'First Seen',
            'Recent Page Count',
            'Recent Pages',
            'Status',
            'Added to CRM'
        );
        
        // Visitor data
        foreach ($visitors as $visitor) {
            $recent_pages = json_decode($visitor->recent_page_urls, true);
            $recent_pages_str = is_array($recent_pages) ? implode('; ', $recent_pages) : '';
            
            $csv_data[] = array(
                $visitor->linkedin_url,
                $visitor->first_name,
                $visitor->last_name,
                $visitor->title,
                $visitor->company_name,
                $visitor->all_time_page_views,
                $visitor->work_email,
                $visitor->website,
                $visitor->industry,
                $visitor->estimated_employee_count,
                $visitor->estimate_revenue,
                $visitor->city,
                $visitor->state,
                $visitor->zipcode,
                $visitor->last_seen_at,
                $visitor->first_seen_at,
                $visitor->recent_page_count,
                $recent_pages_str,
                $visitor->status,
                $visitor->crm_added_at
            );
        }
        
        return $csv_data;
    }
}
