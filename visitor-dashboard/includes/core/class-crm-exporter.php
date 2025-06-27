<?php
/**
 * crm exporter Class
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
 * CRM Export Management Class
 * File: includes/class-crm-exporter.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VisitorDashboard_CRM_Exporter {
    
    /**
     * Export daily CRM data
     */
    public function export_daily_crm_data($client_id = null) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $export_date = current_time('Y-m-d');
        $export_id = 'CRM_' . $export_date . '_' . uniqid();
        
        try {
            // Get visitors in CRM queue
            $visitors = VisitorDashboard_Visitor_Manager::get_crm_queue($client_id, $export_date);
            
            if (empty($visitors)) {
                return array(
                    'success' => false,
                    'message' => 'No visitors in CRM queue for today'
                );
            }
            
            // Generate CSV
            $csv_content = $this->generate_csv($visitors);
            
            // Save to file
            $filename = $this->save_csv_file($csv_content, $export_id, $client_id);
            
            // Send email
            $email_sent = $this->send_crm_export_email($filename, $visitors, $client_id);
            
            // Log export
            $export_log_id = $this->log_export($client_id, $export_date, count($visitors), $filename, $email_sent);
            
            // Archive exported visitors
            $this->archive_exported_visitors($visitors);
            
            return array(
                'success' => true,
                'export_id' => $export_id,
                'visitor_count' => count($visitors),
                'filename' => $filename,
                'email_sent' => $email_sent
            );
            
        } catch (Exception $e) {
            error_log('CRM Export Error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Generate CSV content from visitors
     */
    private function generate_csv($visitors) {
        $csv_rows = array();
        
        // Header row
        $csv_rows[] = array(
            'LinkedIn URL',
            'First Name',
            'Last Name',
            'Title',
            'Company Name',
            'All Time Page Views',
            'Work Email',
            'Website',
            'Industry',
            'Estimated Employee Count',
            'Estimate Revenue',
            'City',
            'State',
            'Zipcode',
            'Last Seen At',
            'First Seen At',
            'New Profile',
            'Most Recent Referrer',
            'Recent Page Count',
            'Recent Page URLs',
            'Tags',
            'Filter Matches',
            'Profile Type',
            'Account Name',
            'Added to CRM At',
            'Added by User'
        );
        
        // Data rows
        foreach ($visitors as $visitor) {
            // Get user who added to CRM
            $added_by_user = get_user_by('id', $visitor->crm_added_by);
            $added_by_name = $added_by_user ? $added_by_user->display_name : 'Unknown';
            
            // Parse JSON fields
            $recent_pages = json_decode($visitor->recent_page_urls, true);
            $recent_pages_str = is_array($recent_pages) ? implode('; ', $recent_pages) : '';
            
            $tags = json_decode($visitor->tags, true);
            $tags_str = is_array($tags) ? implode('; ', $tags) : '';
            
            $filter_matches = json_decode($visitor->filter_matches, true);
            $filter_matches_str = is_array($filter_matches) ? implode('; ', $filter_matches) : '';
            
            $csv_rows[] = array(
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
                $visitor->new_profile ? 'TRUE' : 'FALSE',
                $visitor->most_recent_referrer,
                $visitor->recent_page_count,
                $recent_pages_str,
                $tags_str,
                $filter_matches_str,
                $visitor->profile_type,
                $visitor->account_name,
                $visitor->crm_added_at,
                $added_by_name
            );
        }
        
        // Convert to CSV string
        $output = fopen('php://temp', 'w');
        foreach ($csv_rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    /**
     * Save CSV file to uploads directory
     */
    private function save_csv_file($csv_content, $export_id, $client_id = null) {
        $upload_dir = wp_upload_dir();
        $crm_exports_dir = $upload_dir['basedir'] . '/crm-exports';
        
        // Create directory if it doesn't exist
        if (!file_exists($crm_exports_dir)) {
            wp_mkdir_p($crm_exports_dir);
            
            // Add .htaccess to protect files
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($crm_exports_dir . '/.htaccess', $htaccess_content);
        }
        
        // Generate filename
        $client_suffix = $client_id ? "_client_{$client_id}" : "_all_clients";
        $filename = $export_id . $client_suffix . '.csv';
        $file_path = $crm_exports_dir . '/' . $filename;
        
        // Save file
        $result = file_put_contents($file_path, $csv_content);
        
        if ($result === false) {
            throw new Exception('Failed to save CSV file');
        }
        
        return $filename;
    }
    
    /**
     * Send CRM export email
     */
    private function send_crm_export_email($filename, $visitors, $client_id = null) {
        // Get email addresses
        $email_addresses = $this->get_export_email_addresses($client_id);
        
        if (empty($email_addresses)) {
            error_log('No email addresses configured for CRM export');
            return false;
        }
        
        // Get client info
        $client_info = null;
        if ($client_id) {
            global $wpdb;
            $prefix = VisitorDashboard_Database::get_table_prefix();
            $client_info = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}clients WHERE id = %d",
                $client_id
            ));
        }
        
        // Email subject and content
        $subject = $this->get_email_subject($client_info, count($visitors));
        $message = $this->get_email_message($client_info, $visitors, $filename);
        
        // File path for attachment
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/crm-exports/' . $filename;
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        // Send email with attachment
        $mail_sent = false;
        foreach ($email_addresses as $email) {
            if (is_email($email)) {
                $result = wp_mail($email, $subject, $message, $headers, array($file_path));
                if ($result) {
                    $mail_sent = true;
                }
            }
        }
        
        return $mail_sent;
    }
    
    /**
     * Get email addresses for CRM export
     */
    private function get_export_email_addresses($client_id = null) {
        $emails = array();
        
        // Get default admin email
        $admin_email = get_option('visitor_dashboard_crm_email', get_option('admin_email'));
        if ($admin_email) {
            $emails[] = $admin_email;
        }
        
        // Get client-specific emails if client_id provided
        if ($client_id) {
            global $wpdb;
            $prefix = VisitorDashboard_Database::get_table_prefix();
            
            // Get client users' emails
            $client_emails = $wpdb->get_col($wpdb->prepare(
                "SELECT email FROM {$prefix}dashboard_users WHERE client_id = %d AND status = 'active'",
                $client_id
            ));
            
            $emails = array_merge($emails, $client_emails);
        }
        
        // Remove duplicates and filter valid emails
        $emails = array_unique($emails);
        $emails = array_filter($emails, 'is_email');
        
        return $emails;
    }
    
    /**
     * Get email subject
     */
    private function get_email_subject($client_info, $visitor_count) {
        $date = current_time('F j, Y');
        
        if ($client_info) {
            return "Daily CRM Export - {$client_info->account_name} - {$visitor_count} Visitors - {$date}";
        } else {
            return "Daily CRM Export - All Clients - {$visitor_count} Visitors - {$date}";
        }
    }
    
    /**
     * Get email message content
     */
    private function get_email_message($client_info, $visitors, $filename) {
        $date = current_time('F j, Y');
        $visitor_count = count($visitors);
        
        $message = '<html><body>';
        $message .= '<h2>Daily CRM Export</h2>';
        
        if ($client_info) {
            $message .= '<p><strong>Client:</strong> ' . esc_html($client_info->account_name) . '</p>';
            $message .= '<p><strong>Account ID:</strong> ' . esc_html($client_info->groundtruth_account_id) . '</p>';
        } else {
            $message .= '<p><strong>Scope:</strong> All Clients</p>';
        }
        
        $message .= '<p><strong>Export Date:</strong> ' . $date . '</p>';
        $message .= '<p><strong>Total Visitors:</strong> ' . $visitor_count . '</p>';
        $message .= '<p><strong>File Name:</strong> ' . esc_html($filename) . '</p>';
        
        if ($visitor_count > 0) {
            $message .= '<h3>Visitor Summary:</h3>';
            $message .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';
            $message .= '<tr><th>Name</th><th>Company</th><th>Title</th><th>Last Seen</th><th>Page Views</th></tr>';
            
            foreach (array_slice($visitors, 0, 20) as $visitor) { // Show first 20 visitors
                $full_name = trim($visitor->first_name . ' ' . $visitor->last_name);
                $last_seen = $visitor->last_seen_at ? date('M j, Y', strtotime($visitor->last_seen_at)) : 'Unknown';
                
                $message .= '<tr>';
                $message .= '<td>' . esc_html($full_name) . '</td>';
                $message .= '<td>' . esc_html($visitor->company_name) . '</td>';
                $message .= '<td>' . esc_html($visitor->title) . '</td>';
                $message .= '<td>' . esc_html($last_seen) . '</td>';
                $message .= '<td>' . esc_html($visitor->all_time_page_views) . '</td>';
                $message .= '</tr>';
            }
            
            if ($visitor_count > 20) {
                $message .= '<tr><td colspan="5"><em>... and ' . ($visitor_count - 20) . ' more visitors in the attached CSV file.</em></td></tr>';
            }
            
            $message .= '</table>';
        }
        
        $message .= '<hr>';
        $message .= '<p><small>This email was automatically generated by the Visitor Management Dashboard.</small></p>';
        $message .= '<p><small>Please find the complete visitor data in the attached CSV file.</small></p>';
        $message .= '</body></html>';
        
        return $message;
    }
    
    /**
     * Log export activity
     */
    private function log_export($client_id, $export_date, $visitor_count, $filename, $email_sent) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $result = $wpdb->insert(
            "{$prefix}crm_exports",
            array(
                'client_id' => $client_id,
                'export_date' => $export_date,
                'visitor_count' => $visitor_count,
                'file_path' => $filename,
                'email_sent' => $email_sent,
                'email_sent_at' => $email_sent ? current_time('mysql') : null,
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Archive exported visitors
     */
    private function archive_exported_visitors($visitors) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        if (empty($visitors)) {
            return;
        }
        
        $visitor_ids = array_column($visitors, 'id');
        $placeholders = implode(',', array_fill(0, count($visitor_ids), '%d'));
        
        // Update visitors to archived status
        $wpdb->query($wpdb->prepare(
            "UPDATE {$prefix}visitors 
             SET status = 'deleted', 
                 deleted_at = %s,
                 deleted_by = NULL
             WHERE id IN ($placeholders)",
            array_merge(array(current_time('mysql')), $visitor_ids)
        ));
    }
    
    /**
     * Get export history
     */
    public function get_export_history($client_id = null, $limit = 50) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = $client_id ? "WHERE e.client_id = $client_id" : "";
        
        $query = "SELECT e.*, c.account_name 
                  FROM {$prefix}crm_exports e 
                  LEFT JOIN {$prefix}clients c ON e.client_id = c.id 
                  {$where_clause} 
                  ORDER BY e.created_at DESC 
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $limit));
    }
    
    /**
     * Schedule daily CRM export
     */
    public static function schedule_daily_export() {
        if (!wp_next_scheduled('visitor_dashboard_daily_crm_export')) {
            wp_schedule_event(strtotime('tomorrow 8:00 AM'), 'daily', 'visitor_dashboard_daily_crm_export');
        }
    }
    
    /**
     * Unschedule daily CRM export
     */
    public static function unschedule_daily_export() {
        $timestamp = wp_next_scheduled('visitor_dashboard_daily_crm_export');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'visitor_dashboard_daily_crm_export');
        }
    }
    
    /**
     * Handle scheduled daily export
     */
    public static function handle_daily_export() {
        $exporter = new self();
        
        // Get all active clients
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        $clients = $wpdb->get_results("SELECT id FROM {$prefix}clients WHERE status = 'active'");
        
        foreach ($clients as $client) {
            $result = $exporter->export_daily_crm_data($client->id);
            
            if ($result['success']) {
                error_log("Daily CRM export completed for client {$client->id}: {$result['visitor_count']} visitors");
            } else {
                error_log("Daily CRM export failed for client {$client->id}: {$result['message']}");
            }
        }
    }
    
    /**
     * Get CRM export statistics
     */
    public function get_export_statistics($client_id = null, $days = 30) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_clause = "";
        $params = array(date('Y-m-d', strtotime("-{$days} days")));
        
        if ($client_id) {
            $where_clause = "AND client_id = %d";
            $params[] = $client_id;
        }
        
        $stats = array();
        
        // Total exports
        $stats['total_exports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}crm_exports WHERE export_date >= %s {$where_clause}",
            $params
        ));
        
        // Total visitors exported
        $stats['total_visitors'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(visitor_count) FROM {$prefix}crm_exports WHERE export_date >= %s {$where_clause}",
            $params
        ));
        
        // Successful exports
        $stats['successful_exports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}crm_exports WHERE export_date >= %s AND status = 'completed' {$where_clause}",
            $params
        ));
        
        // Failed exports
        $stats['failed_exports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}crm_exports WHERE export_date >= %s AND status = 'failed' {$where_clause}",
            $params
        ));
        
        // Daily export trend
        $stats['daily_trend'] = $wpdb->get_results($wpdb->prepare(
            "SELECT export_date, SUM(visitor_count) as visitors, COUNT(*) as exports
             FROM {$prefix}crm_exports 
             WHERE export_date >= %s {$where_clause}
             GROUP BY export_date 
             ORDER BY export_date ASC",
            $params
        ));
        
        return $stats;
    }
    
    /**
     * Download export file
     */
    public function download_export_file($export_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        // Get export record
        $export = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}crm_exports WHERE id = %d",
            $export_id
        ));
        
        if (!$export) {
            return false;
        }
        
        // Check user permission
        $current_user_id = get_current_user_id();
        if (!current_user_can('export_crm_data')) {
            // Check if user can access this client
            if (!VisitorDashboard_User_Roles::user_can_access_client($current_user_id, $export->client_id)) {
                return false;
            }
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/crm-exports/' . $export->file_path;
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $export->file_path . '"');
        header('Content-Length: ' . filesize($file_path));
        
        // Output file
        readfile($file_path);
        exit;
    }
    
    /**
     * Clean up old export files
     */
    public function cleanup_old_exports($days = 90) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get old export files
        $old_exports = $wpdb->get_results($wpdb->prepare(
            "SELECT file_path FROM {$prefix}crm_exports WHERE export_date < %s",
            $cutoff_date
        ));
        
        $upload_dir = wp_upload_dir();
        $deleted_count = 0;
        
        foreach ($old_exports as $export) {
            $file_path = $upload_dir['basedir'] . '/crm-exports/' . $export->file_path;
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    $deleted_count++;
                }
            }
        }
        
        // Delete old export records
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$prefix}crm_exports WHERE export_date < %s",
            $cutoff_date
        ));
        
        return $deleted_count;
    }
    
    /**
     * Test email configuration
     */
    public function test_email_configuration($client_id = null) {
        $email_addresses = $this->get_export_email_addresses($client_id);
        
        if (empty($email_addresses)) {
            return array(
                'success' => false,
                'message' => 'No email addresses configured'
            );
        }
        
        $subject = 'CRM Export Test - ' . get_option('blogname');
        $message = '<p>This is a test email for CRM export configuration.</p>';
        $message .= '<p>If you receive this email, the configuration is working correctly.</p>';
        $message .= '<p>Sent at: ' . current_time('Y-m-d H:i:s') . '</p>';
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        $sent_count = 0;
        foreach ($email_addresses as $email) {
            if (wp_mail($email, $subject, $message, $headers)) {
                $sent_count++;
            }
        }
        
        return array(
            'success' => $sent_count > 0,
            'message' => "Test email sent to {$sent_count} of " . count($email_addresses) . " addresses",
            'email_addresses' => $email_addresses
        );
    }
}

// Hook for scheduled export
add_action('visitor_dashboard_daily_crm_export', array('VisitorDashboard_CRM_Exporter', 'handle_daily_export'));
