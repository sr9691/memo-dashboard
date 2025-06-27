<?php
/**
 * Database Management Class
 *
 * @package    Visitor_Dashboard
 * @subpackage Visitor_Dashboard/includes/database
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Database management class for Visitor Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Visitor_Dashboard_Database_Manager {
    
    /**
     * Create all plugin tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Get custom table prefix
        $prefix = $wpdb->prefix . 'dashdev_';
        
        $tables = array();
        
        // Clients table
        $tables[] = "CREATE TABLE {$prefix}clients (
            id int(11) NOT NULL AUTO_INCREMENT,
            groundtruth_account_id varchar(50) NOT NULL UNIQUE,
            account_name varchar(255) NOT NULL,
            organization_name varchar(255) DEFAULT NULL,
            status enum('active', 'inactive') DEFAULT 'active',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_account_id (groundtruth_account_id),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // Visitors table (RB2B data)
        $tables[] = "CREATE TABLE {$prefix}visitors (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            linkedin_url varchar(500) NOT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            title varchar(255) DEFAULT NULL,
            company_name varchar(255) DEFAULT NULL,
            all_time_page_views int(11) DEFAULT 0,
            work_email varchar(255) DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            industry varchar(255) DEFAULT NULL,
            estimated_employee_count varchar(50) DEFAULT NULL,
            estimate_revenue varchar(50) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(50) DEFAULT NULL,
            zipcode varchar(20) DEFAULT NULL,
            last_seen_at timestamp NULL,
            first_seen_at timestamp NULL,
            new_profile boolean DEFAULT false,
            most_recent_referrer text DEFAULT NULL,
            recent_page_count int(11) DEFAULT 0,
            recent_page_urls json DEFAULT NULL,
            tags json DEFAULT NULL,
            filter_matches json DEFAULT NULL,
            profile_type varchar(50) DEFAULT 'Person',
            status enum('active', 'deleted', 'crm_added') DEFAULT 'active',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at timestamp NULL,
            deleted_by int(11) NULL,
            crm_added_at timestamp NULL,
            crm_added_by int(11) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_linkedin_url (linkedin_url),
            KEY idx_client_status (client_id, status),
            KEY idx_last_seen (last_seen_at),
            KEY idx_company (company_name),
            KEY idx_status (status),
            FOREIGN KEY (client_id) REFERENCES {$prefix}clients(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Campaign Performance table (GroundTruth data)
        $tables[] = "CREATE TABLE {$prefix}campaign_performance (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            report_date date NOT NULL,
            campaign_id varchar(50) NOT NULL,
            campaign_name varchar(255) DEFAULT NULL,
            campaign_start_date date DEFAULT NULL,
            campaign_end_date date DEFAULT NULL,
            campaign_budget decimal(10,2) DEFAULT NULL,
            ad_group_id varchar(50) DEFAULT NULL,
            ad_group_name varchar(255) DEFAULT NULL,
            creative_id varchar(50) DEFAULT NULL,
            creative_name varchar(255) DEFAULT NULL,
            creative_size varchar(50) DEFAULT NULL,
            creative_url text DEFAULT NULL,
            total_impressions int(11) DEFAULT 0,
            clicks int(11) DEFAULT 0,
            ctr decimal(5,4) DEFAULT 0.0000,
            visits int(11) DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0.00,
            daily_reach int(11) DEFAULT 0,
            video_start int(11) DEFAULT 0,
            first_quartile int(11) DEFAULT 0,
            midpoint int(11) DEFAULT 0,
            third_quartile int(11) DEFAULT 0,
            video_complete int(11) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_date (client_id, report_date),
            KEY idx_campaign (campaign_id),
            KEY idx_date (report_date),
            FOREIGN KEY (client_id) REFERENCES {$prefix}clients(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Dashboard Users table
        $tables[] = "CREATE TABLE {$prefix}dashboard_users (
            id int(11) NOT NULL AUTO_INCREMENT,
            wp_user_id int(11) NOT NULL,
            client_id int(11) NULL,
            role enum('admin', 'client_user') NOT NULL,
            name varchar(255) DEFAULT NULL,
            email varchar(255) NOT NULL,
            date_joined timestamp DEFAULT CURRENT_TIMESTAMP,
            date_deleted timestamp NULL,
            status enum('active', 'inactive') DEFAULT 'active',
            last_login timestamp NULL,
            login_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_wp_user (wp_user_id),
            KEY idx_role_client (role, client_id),
            KEY idx_status (status),
            FOREIGN KEY (client_id) REFERENCES {$prefix}clients(id) ON DELETE SET NULL
        ) $charset_collate;";
        
        // Audit Logs table
        $tables[] = "CREATE TABLE {$prefix}audit_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            client_id int(11) NULL,
            action_type enum('visitor_delete', 'visitor_crm_add', 'visitor_restore', 'user_create', 'user_delete', 'client_create', 'crm_export', 'data_import') NOT NULL,
            entity_type enum('visitor', 'user', 'client', 'campaign', 'system') NOT NULL,
            entity_id int(11) NULL,
            details json DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_action (client_id, action_type),
            KEY idx_date (created_at),
            KEY idx_user (user_id),
            KEY idx_entity (entity_type, entity_id),
            FOREIGN KEY (user_id) REFERENCES {$prefix}dashboard_users(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES {$prefix}clients(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // CRM Export Queue table
        $tables[] = "CREATE TABLE {$prefix}crm_exports (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            export_date date NOT NULL,
            visitor_count int(11) DEFAULT 0,
            file_path varchar(255) DEFAULT NULL,
            email_sent boolean DEFAULT false,
            email_sent_at timestamp NULL,
            status enum('pending', 'completed', 'failed') DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            completed_at timestamp NULL,
            PRIMARY KEY (id),
            KEY idx_client_date (client_id, export_date),
            KEY idx_status (status),
            FOREIGN KEY (client_id) REFERENCES {$prefix}clients(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Data Import Logs table
        $tables[] = "CREATE TABLE {$prefix}import_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            source enum('rb2b', 'groundtruth') NOT NULL,
            filename varchar(255) NOT NULL,
            client_id int(11) NULL,
            records_processed int(11) DEFAULT 0,
            records_success int(11) DEFAULT 0,
            records_error int(11) DEFAULT 0,
            status enum('processing', 'completed', 'failed') DEFAULT 'processing',
            error_details json DEFAULT NULL,
            file_size int(11) DEFAULT 0,
            processing_time int(11) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            completed_at timestamp NULL,
            PRIMARY KEY (id),
            KEY idx_source_date (source, created_at),
            KEY idx_client (client_id),
            KEY idx_status (status),
            FOREIGN KEY (client_id) REFERENCES {$prefix}clients(id) ON DELETE SET NULL
        ) $charset_collate;";
        
        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_sql) {
            dbDelta($table_sql);
        }
        
        // Insert default data
        self::insert_default_data();
        
        // Update database version
        update_option('visitor_dashboard_db_version', '1.0.0');
    }
    
    /**
     * Insert default data
     */
    private static function insert_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'dashdev_';
        
        // Insert sample clients based on the GroundTruth data structure
        $default_clients = array(
            array(
                'groundtruth_account_id' => '315957',
                'account_name' => 'Office Evolution',
                'organization_name' => 'MEMO Marketing Group'
            ),
            array(
                'groundtruth_account_id' => '311669',
                'account_name' => 'CleanSlate Technologies',
                'organization_name' => 'MEMO Marketing Group'
            ),
            array(
                'groundtruth_account_id' => '319648',
                'account_name' => 'Vertical Immersive',
                'organization_name' => 'MEMO Marketing Group'
            ),
            array(
                'groundtruth_account_id' => '312056',
                'account_name' => 'Urban Air - Plainfield',
                'organization_name' => 'MEMO Marketing Group'
            ),
            array(
                'groundtruth_account_id' => '313861',
                'account_name' => 'Club Works',
                'organization_name' => 'MEMO Marketing Group'
            ),
            array(
                'groundtruth_account_id' => '313568',
                'account_name' => 'Legends Pub',
                'organization_name' => 'MEMO Marketing Group'
            )
        );
        
        foreach ($default_clients as $client) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}clients WHERE groundtruth_account_id = %s",
                $client['groundtruth_account_id']
            ));
            
            if (!$existing) {
                $wpdb->insert(
                    "{$prefix}clients",
                    $client,
                    array('%s', '%s', '%s')
                );
            }
        }
    }
    
    /**
     * Get table prefix
     */
    public static function get_table_prefix() {
        global $wpdb;
        return $wpdb->prefix . 'dashdev_';
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        $prefix = self::get_table_prefix();
        
        $tables = array('clients', 'visitors', 'campaign_performance', 'dashboard_users', 'audit_logs');
        
        foreach ($tables as $table) {
            $table_name = $prefix . $table;
            $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$result) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get database statistics
     */
    public static function get_database_stats() {
        global $wpdb;
        $prefix = self::get_table_prefix();
        
        $stats = array();
        
        // Total visitors
        $stats['total_visitors'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'active'");
        
        // Total clients
        $stats['total_clients'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}clients WHERE status = 'active'");
        
        // Visitors added to CRM today
        $stats['crm_added_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND DATE(crm_added_at) = %s",
            current_time('Y-m-d')
        ));
        
        // New visitors this week
        $stats['new_this_week'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}visitors WHERE DATE(created_at) >= %s",
            date('Y-m-d', strtotime('-7 days'))
        ));
        
        // Active dashboard users
        $stats['active_users'] = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}dashboard_users WHERE status = 'active'");
        
        return $stats;
    }
    
    /**
     * Clean up old data based on retention policy
     */
    public static function cleanup_old_data() {
        global $wpdb;
        $prefix = self::get_table_prefix();
        
        $retention_days = get_option('data_retention_days', 365);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Delete old audit logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$prefix}audit_logs WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Delete old import logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$prefix}import_logs WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Archive old visitors based on campaign end dates
        // This will be implemented when we process GroundTruth data
        
        return true;
    }
}
