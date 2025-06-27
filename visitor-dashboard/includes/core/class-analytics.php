<?php
/**
 * analytics Class
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
 * Analytics Calculation Class
 * File: includes/class-analytics.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VisitorDashboard_Analytics {
    
    /**
     * Get visitor analytics summary
     */
    public static function get_visitor_summary($client_id = null, $date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id" : "";
        $date_filter = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));
        
        $summary = array();
        
        // Total visitors
        $summary['total_visitors'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'active' " . ($client_id ? "AND client_id = $client_id" : ""));
        
        // New visitors in date range
        $summary['new_visitors'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE created_at >= %s " . ($client_id ? "AND client_id = $client_id" : ""),
            $date_filter
        ));
        
        // Visitors added to CRM
        $summary['crm_visitors'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' " . ($client_id ? "AND client_id = $client_id" : ""));
        
        // Archived visitors
        $summary['archived_visitors'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'deleted' " . ($client_id ? "AND client_id = $client_id" : ""));
        
        // Average page views per visitor
        $summary['avg_page_views'] = $wpdb->get_var("SELECT AVG(all_time_page_views) FROM {$prefix}visitors WHERE status = 'active' " . ($client_id ? "AND client_id = $client_id" : ""));
        $summary['avg_page_views'] = round(floatval($summary['avg_page_views']), 2);
        
        // Total page views
        $summary['total_page_views'] = $wpdb->get_var("SELECT SUM(all_time_page_views) FROM {$prefix}visitors WHERE status = 'active' " . ($client_id ? "AND client_id = $client_id" : ""));
        
        // CRM conversion rate
        $summary['crm_conversion_rate'] = $summary['total_visitors'] > 0 ? 
            round(($summary['crm_visitors'] / $summary['total_visitors']) * 100, 2) : 0;
        
        // Growth rate (compared to previous period)
        $previous_period_start = date('Y-m-d H:i:s', strtotime("-" . ($date_range * 2) . " days"));
        $previous_period_end = $date_filter;
        
        $previous_visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE created_at >= %s AND created_at < %s " . ($client_id ? "AND client_id = $client_id" : ""),
            $previous_period_start,
            $previous_period_end
        ));
        
        if ($previous_visitors > 0) {
            $summary['growth_rate'] = round((($summary['new_visitors'] - $previous_visitors) / $previous_visitors) * 100, 2);
        } else {
            $summary['growth_rate'] = $summary['new_visitors'] > 0 ? 100 : 0;
        }
        
        return $summary;
    }
    
    /**
     * Get visitor trends over time
     */
    public static function get_visitor_trends($client_id = null, $days = 30, $granularity = 'daily') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id AND" : "WHERE";
        $date_format = $granularity === 'weekly' ? '%Y-%u' : '%Y-%m-%d';
        $date_filter = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $query = "SELECT DATE_FORMAT(created_at, '{$date_format}') as period,
                         COUNT(*) as new_visitors,
                         COUNT(CASE WHEN status = 'crm_added' THEN 1 END) as crm_added,
                         COUNT(CASE WHEN status = 'deleted' THEN 1 END) as archived
                  FROM {$prefix}visitors
                  {$where_clause} created_at >= %s
                  GROUP BY period
                  ORDER BY period ASC";
        
        $trends = $wpdb->get_results($wpdb->prepare($query, $date_filter));
        
        // Fill in missing dates with zero values
        $filled_trends = self::fill_missing_dates($trends, $days, $granularity);
        
        return $filled_trends;
    }
    
    /**
     * Get top companies by visitor count
     */
    public static function get_top_companies($client_id = null, $limit = 10, $date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_conditions = array("company_name IS NOT NULL", "company_name != ''");
        $query_params = array();
        
        if ($client_id) {
            $where_conditions[] = "client_id = %d";
            $query_params[] = $client_id;
        }
        
        if ($date_range) {
            $where_conditions[] = "created_at >= %s";
            $query_params[] = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query_params[] = $limit;
        
        $query = "SELECT company_name,
                         COUNT(*) as visitor_count,
                         COUNT(CASE WHEN status = 'crm_added' THEN 1 END) as crm_count,
                         AVG(all_time_page_views) as avg_page_views,
                         industry
                  FROM {$prefix}visitors
                  {$where_clause}
                  GROUP BY company_name, industry
                  ORDER BY visitor_count DESC
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $query_params));
    }
    
    /**
     * Get visitor engagement metrics
     */
    public static function get_engagement_metrics($client_id = null, $date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id AND" : "WHERE";
        $date_filter = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));
        
        // Page view distribution
        $page_view_distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN all_time_page_views = 1 THEN '1 page'
                    WHEN all_time_page_views BETWEEN 2 AND 5 THEN '2-5 pages'
                    WHEN all_time_page_views BETWEEN 6 AND 10 THEN '6-10 pages'
                    WHEN all_time_page_views BETWEEN 11 AND 20 THEN '11-20 pages'
                    ELSE '20+ pages'
                END as page_range,
                COUNT(*) as visitor_count
             FROM {$prefix}visitors
             {$where_clause} created_at >= %s
             GROUP BY page_range
             ORDER BY 
                CASE 
                    WHEN all_time_page_views = 1 THEN 1
                    WHEN all_time_page_views BETWEEN 2 AND 5 THEN 2
                    WHEN all_time_page_views BETWEEN 6 AND 10 THEN 3
                    WHEN all_time_page_views BETWEEN 11 AND 20 THEN 4
                    ELSE 5
                END",
            $date_filter
        ));
        
        // Engagement scores
        $engagement_query = "SELECT 
                                AVG(all_time_page_views) as avg_page_views,
                                MAX(all_time_page_views) as max_page_views,
                                COUNT(CASE WHEN all_time_page_views >= 5 THEN 1 END) as highly_engaged,
                                COUNT(CASE WHEN all_time_page_views = 1 THEN 1 END) as single_page_visitors,
                                COUNT(*) as total_visitors
                             FROM {$prefix}visitors
                             {$where_clause} created_at >= %s";
        
        $engagement_stats = $wpdb->get_row($wpdb->prepare($engagement_query, $date_filter));
        
        // Calculate engagement rate
        $engagement_rate = $engagement_stats->total_visitors > 0 ? 
            round(($engagement_stats->highly_engaged / $engagement_stats->total_visitors) * 100, 2) : 0;
        
        // Bounce rate (single page visitors)
        $bounce_rate = $engagement_stats->total_visitors > 0 ? 
            round(($engagement_stats->single_page_visitors / $engagement_stats->total_visitors) * 100, 2) : 0;
        
        return array(
            'page_view_distribution' => $page_view_distribution,
            'avg_page_views' => round(floatval($engagement_stats->avg_page_views), 2),
            'max_page_views' => intval($engagement_stats->max_page_views),
            'engagement_rate' => $engagement_rate,
            'bounce_rate' => $bounce_rate,
            'highly_engaged_count' => intval($engagement_stats->highly_engaged)
        );
    }
    
    /**
     * Get geographic distribution of visitors
     */
    public static function get_geographic_distribution($client_id = null, $date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_conditions = array("state IS NOT NULL", "state != ''");
        $query_params = array();
        
        if ($client_id) {
            $where_conditions[] = "client_id = %d";
            $query_params[] = $client_id;
        }
        
        if ($date_range) {
            $where_conditions[] = "created_at >= %s";
            $query_params[] = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // By state
        $by_state = $wpdb->get_results($wpdb->prepare(
            "SELECT state,
                    COUNT(*) as visitor_count,
                    COUNT(CASE WHEN status = 'crm_added' THEN 1 END) as crm_count
             FROM {$prefix}visitors
             {$where_clause}
             GROUP BY state
             ORDER BY visitor_count DESC
             LIMIT 15",
            $query_params
        ));
        
        // By city (top 20)
        $where_conditions[0] = "city IS NOT NULL AND city != ''";
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $by_city = $wpdb->get_results($wpdb->prepare(
            "SELECT city, state,
                    COUNT(*) as visitor_count,
                    COUNT(CASE WHEN status = 'crm_added' THEN 1 END) as crm_count
             FROM {$prefix}visitors
             {$where_clause}
             GROUP BY city, state
             ORDER BY visitor_count DESC
             LIMIT 20",
            $query_params
        ));
        
        return array(
            'by_state' => $by_state,
            'by_city' => $by_city
        );
    }
    
    /**
     * Get industry analysis
     */
    public static function get_industry_analysis($client_id = null, $date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_conditions = array("industry IS NOT NULL", "industry != ''");
        $query_params = array();
        
        if ($client_id) {
            $where_conditions[] = "client_id = %d";
            $query_params[] = $client_id;
        }
        
        if ($date_range) {
            $where_conditions[] = "created_at >= %s";
            $query_params[] = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $industry_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT industry,
                    COUNT(*) as visitor_count,
                    COUNT(CASE WHEN status = 'crm_added' THEN 1 END) as crm_count,
                    AVG(all_time_page_views) as avg_page_views,
                    COUNT(DISTINCT company_name) as unique_companies
             FROM {$prefix}visitors
             {$where_clause}
             GROUP BY industry
             ORDER BY visitor_count DESC",
            $query_params
        ));
        
        // Calculate CRM conversion rate per industry
        foreach ($industry_stats as &$industry) {
            $industry->crm_conversion_rate = $industry->visitor_count > 0 ? 
                round(($industry->crm_count / $industry->visitor_count) * 100, 2) : 0;
            $industry->avg_page_views = round(floatval($industry->avg_page_views), 2);
        }
        
        return $industry_stats;
    }
    
    /**
     * Get campaign performance analytics
     */
    public static function get_campaign_performance($client_id = null, $date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        if (!$client_id) {
            // Return summary for all clients
            return self::get_all_clients_campaign_performance($date_range);
        }
        
        $date_filter = date('Y-m-d', strtotime("-{$date_range} days"));
        
        // Campaign summary
        $campaign_summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(total_impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                AVG(ctr) as avg_ctr,
                SUM(visits) as total_visits,
                SUM(total_spent) as total_spent,
                SUM(daily_reach) as total_reach,
                COUNT(DISTINCT campaign_id) as unique_campaigns
             FROM {$prefix}campaign_performance 
             WHERE client_id = %d AND report_date >= %s",
            $client_id,
            $date_filter
        ));
        
        // Daily performance trends
        $daily_trends = $wpdb->get_results($wpdb->prepare(
            "SELECT report_date,
                    SUM(total_impressions) as impressions,
                    SUM(clicks) as clicks,
                    AVG(ctr) as ctr,
                    SUM(visits) as visits,
                    SUM(total_spent) as spent
             FROM {$prefix}campaign_performance 
             WHERE client_id = %d AND report_date >= %s
             GROUP BY report_date
             ORDER BY report_date ASC",
            $client_id,
            $date_filter
        ));
        
        // Top performing campaigns
        $top_campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT campaign_name,
                    SUM(total_impressions) as impressions,
                    SUM(clicks) as clicks,
                    AVG(ctr) as ctr,
                    SUM(visits) as visits,
                    SUM(total_spent) as spent
             FROM {$prefix}campaign_performance 
             WHERE client_id = %d AND report_date >= %s
             GROUP BY campaign_id, campaign_name
             ORDER BY impressions DESC
             LIMIT 10",
            $client_id,
            $date_filter
        ));
        
        // Ad group performance
        $ad_group_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT ad_group_name,
                    SUM(total_impressions) as impressions,
                    SUM(clicks) as clicks,
                    AVG(ctr) as ctr,
                    SUM(visits) as visits,
                    SUM(total_spent) as spent
             FROM {$prefix}campaign_performance 
             WHERE client_id = %d AND report_date >= %s AND ad_group_name IS NOT NULL
             GROUP BY ad_group_id, ad_group_name
             ORDER BY impressions DESC
             LIMIT 15",
            $client_id,
            $date_filter
        ));
        
        // Calculate performance metrics
        $performance_metrics = array(
            'total_impressions' => intval($campaign_summary->total_impressions ?? 0),
            'total_clicks' => intval($campaign_summary->total_clicks ?? 0),
            'avg_ctr' => round(floatval($campaign_summary->avg_ctr ?? 0), 4),
            'total_visits' => intval($campaign_summary->total_visits ?? 0),
            'total_spent' => floatval($campaign_summary->total_spent ?? 0),
            'total_reach' => intval($campaign_summary->total_reach ?? 0),
            'unique_campaigns' => intval($campaign_summary->unique_campaigns ?? 0),
            'cost_per_click' => ($campaign_summary->total_clicks ?? 0) > 0 ? 
                round(($campaign_summary->total_spent ?? 0) / ($campaign_summary->total_clicks ?? 1), 2) : 0,
            'cost_per_visit' => ($campaign_summary->total_visits ?? 0) > 0 ? 
                round(($campaign_summary->total_spent ?? 0) / ($campaign_summary->total_visits ?? 1), 2) : 0,
            'visit_rate' => ($campaign_summary->total_clicks ?? 0) > 0 ? 
                round((($campaign_summary->total_visits ?? 0) / ($campaign_summary->total_clicks ?? 1)) * 100, 2) : 0
        );
        
        return array(
            'summary' => $performance_metrics,
            'daily_trends' => $daily_trends,
            'top_campaigns' => $top_campaigns,
            'ad_group_performance' => $ad_group_performance
        );
    }
    
    /**
     * Get campaign performance for all clients
     */
    private static function get_all_clients_campaign_performance($date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $date_filter = date('Y-m-d', strtotime("-{$date_range} days"));
        
        $client_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT c.account_name,
                    c.groundtruth_account_id,
                    SUM(cp.total_impressions) as impressions,
                    SUM(cp.clicks) as clicks,
                    AVG(cp.ctr) as ctr,
                    SUM(cp.visits) as visits,
                    SUM(cp.total_spent) as spent,
                    COUNT(DISTINCT cp.campaign_id) as campaigns
             FROM {$prefix}clients c
             LEFT JOIN {$prefix}campaign_performance cp ON c.id = cp.client_id AND cp.report_date >= %s
             WHERE c.status = 'active'
             GROUP BY c.id
             ORDER BY impressions DESC",
            $date_filter
        ));
        
        return array('client_performance' => $client_performance);
    }
    
    /**
     * Get visitor to CRM conversion funnel
     */
    public static function get_conversion_funnel($client_id = null, $date_range = '30') {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id AND" : "WHERE";
        $date_filter = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));
        
        $funnel_data = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_visitors,
                COUNT(CASE WHEN all_time_page_views >= 2 THEN 1 END) as engaged_visitors,
                COUNT(CASE WHEN all_time_page_views >= 5 THEN 1 END) as highly_engaged,
                COUNT(CASE WHEN status = 'crm_added' THEN 1 END) as crm_added,
                COUNT(CASE WHEN status = 'deleted' THEN 1 END) as archived
             FROM {$prefix}visitors
             {$where_clause} created_at >= %s",
            $date_filter
        ));
        
        // Calculate conversion rates
        $funnel = array(
            'total_visitors' => intval($funnel_data->total_visitors),
            'engaged_visitors' => intval($funnel_data->engaged_visitors),
            'highly_engaged' => intval($funnel_data->highly_engaged),
            'crm_added' => intval($funnel_data->crm_added),
            'archived' => intval($funnel_data->archived)
        );
        
        // Calculate percentages
        $total = $funnel['total_visitors'];
        if ($total > 0) {
            $funnel['engagement_rate'] = round(($funnel['engaged_visitors'] / $total) * 100, 2);
            $funnel['high_engagement_rate'] = round(($funnel['highly_engaged'] / $total) * 100, 2);
            $funnel['crm_conversion_rate'] = round(($funnel['crm_added'] / $total) * 100, 2);
            $funnel['archive_rate'] = round(($funnel['archived'] / $total) * 100, 2);
        } else {
            $funnel['engagement_rate'] = 0;
            $funnel['high_engagement_rate'] = 0;
            $funnel['crm_conversion_rate'] = 0;
            $funnel['archive_rate'] = 0;
        }
        
        return $funnel;
    }
    
    /**
     * Get visitor activity timeline
     */
    public static function get_activity_timeline($client_id = null, $days = 7) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id AND" : "WHERE";
        $date_filter = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $timeline = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date,
                    HOUR(created_at) as hour,
                    COUNT(*) as visitor_count
             FROM {$prefix}visitors
             {$where_clause} created_at >= %s
             GROUP BY DATE(created_at), HOUR(created_at)
             ORDER BY date ASC, hour ASC",
            $date_filter
        ));
        
        // Group by hour across all days
        $hourly_pattern = array_fill(0, 24, 0);
        $daily_totals = array();
        
        foreach ($timeline as $entry) {
            $hourly_pattern[$entry->hour] += $entry->visitor_count;
            
            if (!isset($daily_totals[$entry->date])) {
                $daily_totals[$entry->date] = 0;
            }
            $daily_totals[$entry->date] += $entry->visitor_count;
        }
        
        return array(
            'hourly_pattern' => $hourly_pattern,
            'daily_totals' => $daily_totals,
            'timeline_data' => $timeline
        );
    }
    
    /**
     * Generate comprehensive analytics report
     */
    public static function generate_analytics_report($client_id = null, $date_range = '30') {
        $report = array(
            'generated_at' => current_time('c'),
            'date_range' => $date_range,
            'client_id' => $client_id
        );
        
        // Get all analytics components
        $report['visitor_summary'] = self::get_visitor_summary($client_id, $date_range);
        $report['visitor_trends'] = self::get_visitor_trends($client_id, $date_range);
        $report['engagement_metrics'] = self::get_engagement_metrics($client_id, $date_range);
        $report['top_companies'] = self::get_top_companies($client_id, 10, $date_range);
        $report['geographic_distribution'] = self::get_geographic_distribution($client_id, $date_range);
        $report['industry_analysis'] = self::get_industry_analysis($client_id, $date_range);
        $report['conversion_funnel'] = self::get_conversion_funnel($client_id, $date_range);
        
        if ($client_id) {
            $report['campaign_performance'] = self::get_campaign_performance($client_id, $date_range);
        }
        
        return $report;
    }
    
    /**
     * Fill missing dates in trend data
     */
    private static function fill_missing_dates($trends, $days, $granularity) {
        $filled = array();
        $format = $granularity === 'weekly' ? 'Y-W' : 'Y-m-d';
        $interval = $granularity === 'weekly' ? 'P1W' : 'P1D';
        
        $period = new DatePeriod(
            new DateTime("-{$days} days"),
            new DateInterval($interval),
            new DateTime('tomorrow')
        );
        
        $trend_data = array();
        foreach ($trends as $trend) {
            $trend_data[$trend->period] = $trend;
        }
        
        foreach ($period as $date) {
            $date_key = $date->format($format);
            
            if (isset($trend_data[$date_key])) {
                $filled[] = $trend_data[$date_key];
            } else {
                $filled[] = (object) array(
                    'period' => $date_key,
                    'new_visitors' => 0,
                    'crm_added' => 0,
                    'archived' => 0
                );
            }
        }
        
        return $filled;
    }
    
    /**
     * Get real-time statistics
     */
    public static function get_realtime_stats($client_id = null) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE client_id = $client_id AND" : "WHERE";
        
        $stats = array();
        
        // Visitors added in last hour
        $stats['last_hour'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors {$where_clause} created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-1 hour'))
        ));
        
        // Visitors added today
        $stats['today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors {$where_clause} DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        
        // CRM additions today
        $stats['crm_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND DATE(crm_added_at) = %s " . ($client_id ? "AND client_id = $client_id" : ""),
            current_time('Y-m-d')
        ));
        
        // Recent activity (last 24 hours)
        $recent_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, COUNT(*) as count
             FROM {$prefix}audit_logs
             WHERE created_at >= %s " . ($client_id ? "AND client_id = $client_id" : "") . "
             GROUP BY action_type",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        
        $stats['recent_activity'] = array();
        foreach ($recent_activity as $activity) {
            $stats['recent_activity'][$activity->action_type] = $activity->count;
        }
        
        return $stats;
    }
}
