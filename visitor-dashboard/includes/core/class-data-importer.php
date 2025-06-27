<?php
/**
 * data importer Class
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
 * Data Import Management Class
 * File: includes/class-data-importer.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VisitorDashboard_Data_Importer {
    
    /**
     * Import RB2B visitor data
     */
    public function import_rb2b_data($csv_data, $filename) {
        $import_log_id = null;
        
        try {
            // Extract GroundTruth ID from filename
            $groundtruth_id = $this->extract_groundtruth_id_from_filename($filename);
            
            if (!$groundtruth_id) {
                throw new Exception('Could not extract GroundTruth ID from filename: ' . $filename);
            }
            
            // Get client ID
            $client_id = $this->get_client_id_by_groundtruth_id($groundtruth_id);
            
            if (!$client_id) {
                throw new Exception('Client not found for GroundTruth ID: ' . $groundtruth_id);
            }
            
            // Start import log
            $import_log_id = $this->start_import_log('rb2b', $filename, $client_id);
            
            // Parse CSV data
            $parsed_data = $this->parse_csv_data($csv_data);
            
            if (empty($parsed_data)) {
                throw new Exception('No valid data found in CSV');
            }
            
            $success_count = 0;
            $error_count = 0;
            $errors = array();
            
            // Process each visitor record
            foreach ($parsed_data as $row_number => $row_data) {
                try {
                    $result = VisitorDashboard_Visitor_Manager::create_or_update_visitor($row_data, $client_id);
                    
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $errors[] = "Row {$row_number}: " . $result['message'];
                    }
                    
                } catch (Exception $e) {
                    $error_count++;
                    $errors[] = "Row {$row_number}: " . $e->getMessage();
                }
            }
            
            // Complete import log
            $this->complete_import_log($import_log_id, count($parsed_data), $success_count, $error_count, $errors);
            
            return array(
                'success' => true,
                'message' => "RB2B import completed: {$success_count} success, {$error_count} errors",
                'records_processed' => count($parsed_data),
                'records_success' => $success_count,
                'records_error' => $error_count,
                'client_id' => $client_id,
                'groundtruth_id' => $groundtruth_id
            );
            
        } catch (Exception $e) {
            if ($import_log_id) {
                $this->fail_import_log($import_log_id, $e->getMessage());
            }
            
            error_log('RB2B Import Error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Import GroundTruth campaign data
     */
    public function import_groundtruth_data($csv_data, $filename) {
        $import_log_id = null;
        
        try {
            // Start import log
            $import_log_id = $this->start_import_log('groundtruth', $filename, null);
            
            // Parse CSV data
            $parsed_data = $this->parse_csv_data($csv_data);
            
            if (empty($parsed_data)) {
                throw new Exception('No valid data found in CSV');
            }
            
            $success_count = 0;
            $error_count = 0;
            $errors = array();
            $clients_updated = array();
            
            // Process each campaign record
            foreach ($parsed_data as $row_number => $row_data) {
                try {
                    $result = $this->process_groundtruth_record($row_data);
                    
                    if ($result['success']) {
                        $success_count++;
                        if (isset($result['client_id'])) {
                            $clients_updated[$result['client_id']] = true;
                        }
                    } else {
                        $error_count++;
                        $errors[] = "Row {$row_number}: " . $result['message'];
                    }
                    
                } catch (Exception $e) {
                    $error_count++;
                    $errors[] = "Row {$row_number}: " . $e->getMessage();
                }
            }
            
            // Complete import log
            $this->complete_import_log($import_log_id, count($parsed_data), $success_count, $error_count, $errors);
            
            return array(
                'success' => true,
                'message' => "GroundTruth import completed: {$success_count} success, {$error_count} errors",
                'records_processed' => count($parsed_data),
                'records_success' => $success_count,
                'records_error' => $error_count,
                'clients_updated' => array_keys($clients_updated)
            );
            
        } catch (Exception $e) {
            if ($import_log_id) {
                $this->fail_import_log($import_log_id, $e->getMessage());
            }
            
            error_log('GroundTruth Import Error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Extract GroundTruth ID from RB2B filename
     */
    private function extract_groundtruth_id_from_filename($filename) {
        // Expected format: daily_rpt_MM_DD_YY_GROUNDTRUTHID.csv
        // Example: daily_rpt_5_31_24_315957.csv
        
        if (preg_match('/daily_rpt_\d+_\d+_\d+_(\d+)\.csv$/', $filename, $matches)) {
            return $matches[1];
        }
        
        // Alternative format: just numbers at the end
        if (preg_match('/(\d{6,})\.csv$/', $filename, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get client ID by GroundTruth account ID
     */
    private function get_client_id_by_groundtruth_id($groundtruth_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}clients WHERE groundtruth_account_id = %s",
            $groundtruth_id
        ));
    }
    
    /**
     * Parse CSV data into array
     */
    private function parse_csv_data($csv_data) {
        $lines = str_getcsv($csv_data, "\n");
        
        if (empty($lines)) {
            return array();
        }
        
        // Get headers from first line
        $headers = str_getcsv($lines[0], "\t"); // Tab-delimited
        
        if (empty($headers)) {
            // Try comma-delimited
            $headers = str_getcsv($lines[0], ",");
        }
        
        if (empty($headers)) {
            throw new Exception('Could not parse CSV headers');
        }
        
        $parsed_data = array();
        
        // Process data rows
        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) {
                continue; // Skip empty lines
            }
            
            $row = str_getcsv($lines[$i], "\t"); // Tab-delimited
            
            if (empty($row) || count($row) === 1) {
                // Try comma-delimited
                $row = str_getcsv($lines[$i], ",");
            }
            
            if (count($row) !== count($headers)) {
                // Skip malformed rows
                continue;
            }
            
            // Combine headers with data
            $row_data = array_combine($headers, $row);
            $parsed_data[$i] = $row_data;
        }
        
        return $parsed_data;
    }
    
    /**
     * Process single GroundTruth record
     */
    private function process_groundtruth_record($data) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        // Validate required fields
        if (empty($data['Account ID']) || empty($data['Date'])) {
            return array('success' => false, 'message' => 'Missing required fields (Account ID or Date)');
        }
        
        $account_id = $data['Account ID'];
        $account_name = $data['Account Name'] ?? null;
        $organization_name = $data['Organization Name'] ?? 'MEMO Marketing Group';
        
        // Create or update client
        $client_id = $this->create_or_update_client($account_id, $account_name, $organization_name);
        
        if (!$client_id) {
            return array('success' => false, 'message' => 'Failed to create/update client');
        }
        
        // Prepare campaign performance data
        $campaign_data = array(
            'client_id' => $client_id,
            'report_date' => $this->parse_date($data['Date']),
            'campaign_id' => $data['Campaign ID'] ?? null,
            'campaign_name' => $data['Campaign Name'] ?? null,
            'campaign_start_date' => $this->parse_date($data['Campaign Start Date'] ?? null),
            'campaign_end_date' => $this->parse_date($data['Campaign End Date'] ?? null),
            'campaign_budget' => $this->parse_decimal($data['Campaign Budget'] ?? null),
            'ad_group_id' => $data['Ad Group ID'] ?? null,
            'ad_group_name' => $data['Ad Group Name'] ?? null,
            'creative_id' => $data['Creative ID'] ?? null,
            'creative_name' => $data['Creative Name'] ?? null,
            'creative_size' => $data['Creative Size'] ?? null,
            'creative_url' => $data['Creative URL'] ?? null,
            'total_impressions' => intval($data['Impressions'] ?? 0),
            'clicks' => intval($data['Clicks'] ?? 0),
            'ctr' => $this->parse_decimal($data['CTR'] ?? 0),
            'visits' => intval($data['Visits'] ?? 0),
            'total_spent' => $this->parse_decimal($data['Total Spent'] ?? 0),
            'daily_reach' => intval($data['Daily Reach'] ?? 0),
            'video_start' => intval($data['Video Start'] ?? 0),
            'first_quartile' => intval($data['First Quartile'] ?? 0),
            'midpoint' => intval($data['Midpoint'] ?? 0),
            'third_quartile' => intval($data['Third Quartile'] ?? 0),
            'video_complete' => intval($data['Video Complete'] ?? 0)
        );
        
        // Check if record exists (same client, date, campaign, ad group, creative)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}campaign_performance 
             WHERE client_id = %d AND report_date = %s AND campaign_id = %s AND ad_group_id = %s AND creative_id = %s",
            $client_id,
            $campaign_data['report_date'],
            $campaign_data['campaign_id'],
            $campaign_data['ad_group_id'],
            $campaign_data['creative_id']
        ));
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                "{$prefix}campaign_performance",
                $campaign_data,
                array('id' => $existing),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%d'),
                array('%d')
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                "{$prefix}campaign_performance",
                $campaign_data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%d')
            );
        }
        
        if ($result !== false) {
            return array('success' => true, 'client_id' => $client_id);
        } else {
            return array('success' => false, 'message' => 'Failed to save campaign data');
        }
    }
    
    /**
     * Create or update client
     */
    private function create_or_update_client($account_id, $account_name, $organization_name) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        // Check if client exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}clients WHERE groundtruth_account_id = %s",
            $account_id
        ));
        
        $client_data = array(
            'groundtruth_account_id' => $account_id,
            'account_name' => $account_name,
            'organization_name' => $organization_name,
            'status' => 'active'
        );
        
        if ($existing) {
            // Update existing client
            $result = $wpdb->update(
                "{$prefix}clients",
                $client_data,
                array('id' => $existing->id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $result !== false ? $existing->id : false;
        } else {
            // Create new client
            $result = $wpdb->insert(
                "{$prefix}clients",
                $client_data,
                array('%s', '%s', '%s', '%s')
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Start import log
     */
    private function start_import_log($source, $filename, $client_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $result = $wpdb->insert(
            "{$prefix}import_logs",
            array(
                'source' => $source,
                'filename' => $filename,
                'client_id' => $client_id,
                'status' => 'processing',
                'file_size' => strlen($filename) // Approximate
            ),
            array('%s', '%s', '%d', '%s', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Complete import log
     */
    private function complete_import_log($import_log_id, $records_processed, $records_success, $records_error, $errors) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $error_details = !empty($errors) ? json_encode($errors) : null;
        
        $wpdb->update(
            "{$prefix}import_logs",
            array(
                'records_processed' => $records_processed,
                'records_success' => $records_success,
                'records_error' => $records_error,
                'status' => 'completed',
                'error_details' => $error_details,
                'completed_at' => current_time('mysql')
            ),
            array('id' => $import_log_id),
            array('%d', '%d', '%d', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Fail import log
     */
    private function fail_import_log($import_log_id, $error_message) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $wpdb->update(
            "{$prefix}import_logs",
            array(
                'status' => 'failed',
                'error_details' => json_encode(array($error_message)),
                'completed_at' => current_time('mysql')
            ),
            array('id' => $import_log_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Parse date from various formats
     */
    private function parse_date($date_string) {
        if (empty($date_string)) {
            return null;
        }
        
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return null;
        }
        
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Parse decimal value
     */
    private function parse_decimal($value) {
        if (empty($value)) {
            return 0.00;
        }
        
        // Remove any non-numeric characters except decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', $value);
        
        return floatval($cleaned);
    }
    
    /**
     * Get import history
     */
    public function get_import_history($source = null, $client_id = null, $limit = 50) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $where_conditions = array();
        $query_params = array();
        
        if ($source) {
            $where_conditions[] = "i.source = %s";
            $query_params[] = $source;
        }
        
        if ($client_id) {
            $where_conditions[] = "i.client_id = %d";
            $query_params[] = $client_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $query_params[] = $limit;
        
        $query = "SELECT i.*, c.account_name 
                  FROM {$prefix}import_logs i 
                  LEFT JOIN {$prefix}clients c ON i.client_id = c.id 
                  {$where_clause} 
                  ORDER BY i.created_at DESC 
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $query_params));
    }
    
    /**
     * Get import statistics
     */
    public function get_import_statistics($days = 30) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Total imports
        $stats['total_imports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s",
            $cutoff_date
        ));
        
        // Successful imports
        $stats['successful_imports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND status = 'completed'",
            $cutoff_date
        ));
        
        // Failed imports
        $stats['failed_imports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND status = 'failed'",
            $cutoff_date
        ));
        
        // Total records processed
        $stats['total_records'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(records_processed) FROM {$prefix}import_logs WHERE created_at >= %s",
            $cutoff_date
        ));
        
        // RB2B imports
        $stats['rb2b_imports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND source = 'rb2b'",
            $cutoff_date
        ));
        
        // GroundTruth imports
        $stats['groundtruth_imports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}import_logs WHERE created_at >= %s AND source = 'groundtruth'",
            $cutoff_date
        ));
        
        // Daily import trend
        $stats['daily_trend'] = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, 
                    COUNT(*) as total_imports,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(records_processed) as records
             FROM {$prefix}import_logs 
             WHERE created_at >= %s
             GROUP BY DATE(created_at) 
             ORDER BY date ASC",
            $cutoff_date
        ));
        
        return $stats;
    }
    
    /**
     * Validate CSV structure for RB2B
     */
    public function validate_rb2b_csv($csv_data) {
        $required_headers = array(
            'LinkedInUrl',
            'FirstName',
            'LastName',
            'CompanyName'
        );
        
        $lines = str_getcsv($csv_data, "\n");
        if (empty($lines)) {
            return array('valid' => false, 'message' => 'Empty CSV data');
        }
        
        $headers = str_getcsv($lines[0], "\t");
        if (empty($headers)) {
            $headers = str_getcsv($lines[0], ",");
        }
        
        $missing_headers = array();
        foreach ($required_headers as $required) {
            if (!in_array($required, $headers)) {
                $missing_headers[] = $required;
            }
        }
        
        if (!empty($missing_headers)) {
            return array(
                'valid' => false, 
                'message' => 'Missing required headers: ' . implode(', ', $missing_headers)
            );
        }
        
        return array('valid' => true, 'headers' => $headers);
    }
    
    /**
     * Validate CSV structure for GroundTruth
     */
    public function validate_groundtruth_csv($csv_data) {
        $required_headers = array(
            'Date',
            'Account ID',
            'Account Name'
        );
        
        $lines = str_getcsv($csv_data, "\n");
        if (empty($lines)) {
            return array('valid' => false, 'message' => 'Empty CSV data');
        }
        
        $headers = str_getcsv($lines[0], "\t");
        if (empty($headers)) {
            $headers = str_getcsv($lines[0], ",");
        }
        
        $missing_headers = array();
        foreach ($required_headers as $required) {
            if (!in_array($required, $headers)) {
                $missing_headers[] = $required;
            }
        }
        
        if (!empty($missing_headers)) {
            return array(
                'valid' => false, 
                'message' => 'Missing required headers: ' . implode(', ', $missing_headers)
            );
        }
        
        return array('valid' => true, 'headers' => $headers);
    }
    
    /**
     * Process sample data for testing
     */
    public function import_sample_data() {
        // Sample RB2B data
        $sample_rb2b_data = array(
            array(
                'LinkedInUrl' => 'https://www.linkedin.com/in/john-doe',
                'FirstName' => 'John',
                'LastName' => 'Doe',
                'Title' => 'Software Developer',
                'CompanyName' => 'Tech Corp',
                'AllTimePageViews' => 5,
                'WorkEmail' => 'john.doe@techcorp.com',
                'Website' => 'https://techcorp.com',
                'Industry' => 'Technology',
                'EstimatedEmployeeCount' => '51-200',
                'EstimateRevenue' => '$10M - $50M',
                'City' => 'San Francisco',
                'State' => 'CA',
                'Zipcode' => '94105',
                'LastSeenAt' => date('Y-m-d H:i:s'),
                'FirstSeenAt' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'NewProfile' => false,
                'MostRecentReferrer' => 'https://google.com',
                'RecentPageCount' => 3,
                'RecentPageUrls' => json_encode(array('/home', '/about', '/contact')),
                'Tags' => json_encode(array()),
                'FilterMatches' => json_encode(array()),
                'ProfileType' => 'Person'
            ),
            array(
                'LinkedInUrl' => 'https://www.linkedin.com/in/jane-smith',
                'FirstName' => 'Jane',
                'LastName' => 'Smith',
                'Title' => 'Marketing Manager',
                'CompanyName' => 'Marketing Plus',
                'AllTimePageViews' => 8,
                'WorkEmail' => 'jane.smith@marketingplus.com',
                'Website' => 'https://marketingplus.com',
                'Industry' => 'Marketing',
                'EstimatedEmployeeCount' => '11-50',
                'EstimateRevenue' => '$1M - $10M',
                'City' => 'Austin',
                'State' => 'TX',
                'Zipcode' => '78701',
                'LastSeenAt' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'FirstSeenAt' => date('Y-m-d H:i:s', strtotime('-1 week')),
                'NewProfile' => true,
                'MostRecentReferrer' => 'https://linkedin.com',
                'RecentPageCount' => 5,
                'RecentPageUrls' => json_encode(array('/services', '/pricing', '/contact', '/about', '/blog')),
                'Tags' => json_encode(array('lead')),
                'FilterMatches' => json_encode(array()),
                'ProfileType' => 'Person'
            )
        );
        
        // Import sample visitors for Office Evolution (client ID 315957)
        $client_id = $this->get_client_id_by_groundtruth_id('315957');
        
        if (!$client_id) {
            return array('success' => false, 'message' => 'Office Evolution client not found');
        }
        
        $success_count = 0;
        foreach ($sample_rb2b_data as $visitor_data) {
            $result = VisitorDashboard_Visitor_Manager::create_or_update_visitor($visitor_data, $client_id);
            if ($result['success']) {
                $success_count++;
            }
        }
        
        // Sample GroundTruth data
        $sample_campaign_data = array(
            'Date' => date('Y-m-d'),
            'Organization Name' => 'MEMO Marketing Group',
            'Account ID' => '315957',
            'Account Name' => 'Office Evolution',
            'Campaign ID' => '1513557',
            'Campaign Name' => 'Office Evolution Campaign',
            'Campaign Start Date' => date('Y-m-d', strtotime('-30 days')),
            'Campaign End Date' => date('Y-m-d', strtotime('+30 days')),
            'Campaign Budget' => '1500.00',
            'Ad Group ID' => '7575632',
            'Ad Group Name' => 'Independent Financial Advisers',
            'Creative ID' => '31035467',
            'Creative Name' => 'OE Main Campaign 5.1 300 x 250.gif',
            'Creative Size' => '300x250',
            'Creative URL' => 'https://example.com/creative.gif',
            'Impressions' => '1250',
            'Clicks' => '25',
            'CTR' => '2.0',
            'Visits' => '20',
            'Total Spent' => '75.50',
            'Daily Reach' => '1000'
        );
        
        $campaign_result = $this->process_groundtruth_record($sample_campaign_data);
        
        return array(
            'success' => true,
            'message' => "Sample data imported: {$success_count} visitors, 1 campaign record",
            'visitors_imported' => $success_count,
            'campaign_imported' => $campaign_result['success'] ? 1 : 0
        );
    }
    
    /**
     * Clean up old import logs
     */
    public function cleanup_old_import_logs($days = 90) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$prefix}import_logs WHERE created_at < %s",
            $cutoff_date
        ));
        
        return $deleted;
    }
    
    /**
     * Get latest import status for monitoring
     */
    public function get_latest_import_status() {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $latest_rb2b = $wpdb->get_row(
            "SELECT * FROM {$prefix}import_logs WHERE source = 'rb2b' ORDER BY created_at DESC LIMIT 1"
        );
        
        $latest_groundtruth = $wpdb->get_row(
            "SELECT * FROM {$prefix}import_logs WHERE source = 'groundtruth' ORDER BY created_at DESC LIMIT 1"
        );
        
        return array(
            'rb2b' => $latest_rb2b,
            'groundtruth' => $latest_groundtruth
        );
    }
    
    /**
     * Retry failed import
     */
    public function retry_failed_import($import_log_id) {
        global $wpdb;
        $prefix = VisitorDashboard_Database::get_table_prefix();
        
        $import_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}import_logs WHERE id = %d AND status = 'failed'",
            $import_log_id
        ));
        
        if (!$import_log) {
            return array('success' => false, 'message' => 'Failed import not found');
        }
        
        // Reset status to processing
        $wpdb->update(
            "{$prefix}import_logs",
            array(
                'status' => 'processing',
                'error_details' => null,
                'completed_at' => null
            ),
            array('id' => $import_log_id),
            array('%s', null, null),
            array('%d')
        );
        
        return array(
            'success' => true,
            'message' => 'Import status reset for retry',
            'import_log' => $import_log
        );
    }
}
