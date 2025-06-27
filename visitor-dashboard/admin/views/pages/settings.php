<?php
/**
 * Admin settings View
 *
 * @package    Visitor_Dashboard
 * @subpackage Visitor_Dashboard/admin/views/pages
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin Settings Interface
 * File: admin/settings.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'visitor-dashboard'));
}

// Handle form submissions
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'visitor_dashboard_settings')) {
    
    // General Settings
    if (isset($_POST['tab']) && $_POST['tab'] === 'general') {
        update_option('visitor_dashboard_crm_email', sanitize_email($_POST['crm_email']));
        update_option('visitor_dashboard_data_retention_days', intval($_POST['data_retention_days']));
        update_option('visitor_dashboard_enable_audit_logging', isset($_POST['enable_audit_logging']) ? 1 : 0);
        update_option('visitor_dashboard_enable_daily_export', isset($_POST['enable_daily_export']) ? 1 : 0);
        update_option('visitor_dashboard_export_time', sanitize_text_field($_POST['export_time']));
        
        $message = __('General settings saved.', 'visitor-dashboard');
    }
    
    // API Settings
    if (isset($_POST['tab']) && $_POST['tab'] === 'api') {
        update_option('visitor_dashboard_api_key', sanitize_text_field($_POST['api_key']));
        update_option('visitor_dashboard_webhook_secret', sanitize_text_field($_POST['webhook_secret']));
        update_option('visitor_dashboard_rate_limit', intval($_POST['rate_limit']));
        update_option('visitor_dashboard_enable_api_logging', isset($_POST['enable_api_logging']) ? 1 : 0);
        
        $message = __('API settings saved.', 'visitor-dashboard');
    }
    
    // Email Settings
    if (isset($_POST['tab']) && $_POST['tab'] === 'email') {
        update_option('visitor_dashboard_smtp_host', sanitize_text_field($_POST['smtp_host']));
        update_option('visitor_dashboard_smtp_port', intval($_POST['smtp_port']));
        update_option('visitor_dashboard_smtp_username', sanitize_text_field($_POST['smtp_username']));
        update_option('visitor_dashboard_smtp_password', sanitize_text_field($_POST['smtp_password']));
        update_option('visitor_dashboard_smtp_encryption', sanitize_text_field($_POST['smtp_encryption']));
        update_option('visitor_dashboard_email_from_name', sanitize_text_field($_POST['email_from_name']));
        update_option('visitor_dashboard_email_from_address', sanitize_email($_POST['email_from_address']));
        
        $message = __('Email settings saved.', 'visitor-dashboard');
    }
    
    // Display success message
    if (isset($message)) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

// Handle actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'generate_api_key':
            if (wp_verify_nonce($_POST['_wpnonce'], 'generate_api_key')) {
                $new_api_key = wp_generate_password(32, false);
                update_option('visitor_dashboard_api_key', $new_api_key);
                echo '<div class="notice notice-success is-dismissible"><p>' . __('New API key generated.', 'visitor-dashboard') . '</p></div>';
            }
            break;
            
        case 'test_email':
            if (wp_verify_nonce($_POST['_wpnonce'], 'test_email')) {
                $test_result = $this->test_email_configuration();
                if ($test_result['success']) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($test_result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($test_result['message']) . '</p></div>';
                }
            }
            break;
            
        case 'clear_logs':
            if (wp_verify_nonce($_POST['_wpnonce'], 'clear_logs')) {
                $this->clear_audit_logs();
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Audit logs cleared.', 'visitor-dashboard') . '</p></div>';
            }
            break;
    }
}

// Get current settings
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Get option values
$crm_email = get_option('visitor_dashboard_crm_email', get_option('admin_email'));
$data_retention_days = get_option('visitor_dashboard_data_retention_days', 365);
$enable_audit_logging = get_option('visitor_dashboard_enable_audit_logging', true);
$enable_daily_export = get_option('visitor_dashboard_enable_daily_export', true);
$export_time = get_option('visitor_dashboard_export_time', '08:00');

$api_key = get_option('visitor_dashboard_api_key', '');
$webhook_secret = get_option('visitor_dashboard_webhook_secret', '');
$rate_limit = get_option('visitor_dashboard_rate_limit', 1000);
$enable_api_logging = get_option('visitor_dashboard_enable_api_logging', true);

$smtp_host = get_option('visitor_dashboard_smtp_host', '');
$smtp_port = get_option('visitor_dashboard_smtp_port', 587);
$smtp_username = get_option('visitor_dashboard_smtp_username', '');
$smtp_password = get_option('visitor_dashboard_smtp_password', '');
$smtp_encryption = get_option('visitor_dashboard_smtp_encryption', 'tls');
$email_from_name = get_option('visitor_dashboard_email_from_name', get_option('blogname'));
$email_from_address = get_option('visitor_dashboard_email_from_address', get_option('admin_email'));

?>

<div class="wrap visitor-dashboard-settings">
    <h1><?php _e('Visitor Dashboard Settings', 'visitor-dashboard'); ?></h1>
    
    <!-- Settings Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="?page=visitor-dashboard-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'visitor-dashboard'); ?>
        </a>
        <a href="?page=visitor-dashboard-settings&tab=api" class="nav-tab <?php echo $current_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <?php _e('API & Webhooks', 'visitor-dashboard'); ?>
        </a>
        <a href="?page=visitor-dashboard-settings&tab=email" class="nav-tab <?php echo $current_tab === 'email' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Email', 'visitor-dashboard'); ?>
        </a>
        <a href="?page=visitor-dashboard-settings&tab=maintenance" class="nav-tab <?php echo $current_tab === 'maintenance' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Maintenance', 'visitor-dashboard'); ?>
        </a>
        <a href="?page=visitor-dashboard-settings&tab=about" class="nav-tab <?php echo $current_tab === 'about' ? 'nav-tab-active' : ''; ?>">
            <?php _e('About', 'visitor-dashboard'); ?>
        </a>
    </nav>

    <!-- General Settings Tab -->
    <?php if ($current_tab === 'general'): ?>
    <div class="settings-tab-content">
        <h2><?php _e('General Settings', 'visitor-dashboard'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('visitor_dashboard_settings'); ?>
            <input type="hidden" name="tab" value="general">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="crm_email"><?php _e('CRM Export Email', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="crm_email" name="crm_email" value="<?php echo esc_attr($crm_email); ?>" class="regular-text" required>
                        <p class="description"><?php _e('Email address where CRM exports will be sent.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="data_retention_days"><?php _e('Data Retention (Days)', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="data_retention_days" name="data_retention_days" value="<?php echo esc_attr($data_retention_days); ?>" min="1" max="3650" class="small-text">
                        <p class="description"><?php _e('Number of days to retain visitor data after campaign end date.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Audit Logging', 'visitor-dashboard'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_audit_logging" value="1" <?php checked($enable_audit_logging); ?>>
                            <?php _e('Enable audit logging for user actions', 'visitor-dashboard'); ?>
                        </label>
                        <p class="description"><?php _e('Log all user actions for security and compliance purposes.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Daily CRM Export', 'visitor-dashboard'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_daily_export" value="1" <?php checked($enable_daily_export); ?>>
                            <?php _e('Enable automatic daily CRM exports', 'visitor-dashboard'); ?>
                        </label>
                        <p class="description"><?php _e('Automatically export CRM data daily at the specified time.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="export_time"><?php _e('Export Time', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="time" id="export_time" name="export_time" value="<?php echo esc_attr($export_time); ?>">
                        <p class="description"><?php _e('Time of day to run automatic CRM exports (24-hour format).', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- API Settings Tab -->
    <?php if ($current_tab === 'api'): ?>
    <div class="settings-tab-content">
        <h2><?php _e('API & Webhook Settings', 'visitor-dashboard'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('visitor_dashboard_settings'); ?>
            <input type="hidden" name="tab" value="api">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_key"><?php _e('API Key', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="large-text code" readonly>
                        <br><br>
                        <button type="button" class="button button-secondary" onclick="generateApiKey()">
                            <?php _e('Generate New API Key', 'visitor-dashboard'); ?>
                        </button>
                        <p class="description"><?php _e('API key for Make.com webhook authentication.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="webhook_secret"><?php _e('Webhook Secret', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="webhook_secret" name="webhook_secret" value="<?php echo esc_attr($webhook_secret); ?>" class="regular-text">
                        <p class="description"><?php _e('Optional secret for additional webhook security.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rate_limit"><?php _e('API Rate Limit', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="rate_limit" name="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="100" max="10000" class="small-text">
                        <span><?php _e('requests per hour', 'visitor-dashboard'); ?></span>
                        <p class="description"><?php _e('Maximum number of API requests per hour per IP address.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('API Logging', 'visitor-dashboard'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_api_logging" value="1" <?php checked($enable_api_logging); ?>>
                            <?php _e('Enable API request logging', 'visitor-dashboard'); ?>
                        </label>
                        <p class="description"><?php _e('Log all API requests for debugging and monitoring.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Webhook Endpoints', 'visitor-dashboard'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('RB2B Import', 'visitor-dashboard'); ?></th>
                    <td>
                        <code><?php echo rest_url('visitor-dashboard/v1/import/rb2b'); ?></code>
                        <button type="button" class="button button-small" onclick="copyToClipboard('<?php echo rest_url('visitor-dashboard/v1/import/rb2b'); ?>')">
                            <?php _e('Copy', 'visitor-dashboard'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('GroundTruth Import', 'visitor-dashboard'); ?></th>
                    <td>
                        <code><?php echo rest_url('visitor-dashboard/v1/import/groundtruth'); ?></code>
                        <button type="button" class="button button-small" onclick="copyToClipboard('<?php echo rest_url('visitor-dashboard/v1/import/groundtruth'); ?>')">
                            <?php _e('Copy', 'visitor-dashboard'); ?>
                        </button>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Authentication', 'visitor-dashboard'); ?></h3>
            <p><?php _e('Include this header in your webhook requests:', 'visitor-dashboard'); ?></p>
            <code>Authorization: Bearer <?php echo esc_html($api_key ?: '[API Key Not Set]'); ?></code>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- Email Settings Tab -->
    <?php if ($current_tab === 'email'): ?>
    <div class="settings-tab-content">
        <h2><?php _e('Email Settings', 'visitor-dashboard'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('visitor_dashboard_settings'); ?>
            <input type="hidden" name="tab" value="email">
            
            <h3><?php _e('From Address', 'visitor-dashboard'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_from_name"><?php _e('From Name', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="email_from_name" name="email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                        <p class="description"><?php _e('Name displayed in sent emails.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_from_address"><?php _e('From Email', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="email_from_address" name="email_from_address" value="<?php echo esc_attr($email_from_address); ?>" class="regular-text">
                        <p class="description"><?php _e('Email address displayed in sent emails.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('SMTP Configuration', 'visitor-dashboard'); ?></h3>
            <p><?php _e('Configure SMTP settings for reliable email delivery. Leave empty to use WordPress default.', 'visitor-dashboard'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smtp_host"><?php _e('SMTP Host', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo esc_attr($smtp_host); ?>" class="regular-text">
                        <p class="description"><?php _e('SMTP server hostname (e.g., smtp.gmail.com).', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_port"><?php _e('SMTP Port', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo esc_attr($smtp_port); ?>" min="1" max="65535" class="small-text">
                        <p class="description"><?php _e('SMTP server port (usually 587 for TLS or 465 for SSL).', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_encryption"><?php _e('Encryption', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <select id="smtp_encryption" name="smtp_encryption">
                            <option value="none" <?php selected($smtp_encryption, 'none'); ?>><?php _e('None', 'visitor-dashboard'); ?></option>
                            <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>><?php _e('TLS', 'visitor-dashboard'); ?></option>
                            <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>><?php _e('SSL', 'visitor-dashboard'); ?></option>
                        </select>
                        <p class="description"><?php _e('Encryption method for SMTP connection.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_username"><?php _e('SMTP Username', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo esc_attr($smtp_username); ?>" class="regular-text">
                        <p class="description"><?php _e('Username for SMTP authentication.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_password"><?php _e('SMTP Password', 'visitor-dashboard'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="smtp_password" name="smtp_password" value="<?php echo esc_attr($smtp_password); ?>" class="regular-text">
                        <p class="description"><?php _e('Password for SMTP authentication.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
            </table>
            
            <div class="email-test-section">
                <h3><?php _e('Test Email Configuration', 'visitor-dashboard'); ?></h3>
                <p><?php _e('Send a test email to verify your configuration.', 'visitor-dashboard'); ?></p>
                <button type="button" class="button button-secondary" onclick="testEmailConfig()">
                    <?php _e('Send Test Email', 'visitor-dashboard'); ?>
                </button>
            </div>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- Maintenance Tab -->
    <?php if ($current_tab === 'maintenance'): ?>
    <div class="settings-tab-content">
        <h2><?php _e('Maintenance & System Tools', 'visitor-dashboard'); ?></h2>
        
        <div class="maintenance-section">
            <h3><?php _e('Database Maintenance', 'visitor-dashboard'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Database Status', 'visitor-dashboard'); ?></th>
                    <td>
                        <div id="database-status">
                            <span class="spinner is-active"></span>
                            <?php _e('Checking database status...', 'visitor-dashboard'); ?>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Clear Audit Logs', 'visitor-dashboard'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" onclick="clearAuditLogs()">
                            <?php _e('Clear Old Audit Logs', 'visitor-dashboard'); ?>
                        </button>
                        <p class="description"><?php _e('Remove audit logs older than 90 days.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Clean Import Logs', 'visitor-dashboard'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" onclick="cleanImportLogs()">
                            <?php _e('Clean Import Logs', 'visitor-dashboard'); ?>
                        </button>
                        <p class="description"><?php _e('Remove import logs older than 30 days.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Export Old Files', 'visitor-dashboard'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" onclick="cleanExportFiles()">
                            <?php _e('Clean Export Files', 'visitor-dashboard'); ?>
                        </button>
                        <p class="description"><?php _e('Remove CRM export files older than 30 days.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="system-info-section">
            <h3><?php _e('System Information', 'visitor-dashboard'); ?></h3>
            <div id="system-info">
                <span class="spinner is-active"></span>
                <?php _e('Loading system information...', 'visitor-dashboard'); ?>
            </div>
        </div>
        
        <div class="backup-section">
            <h3><?php _e('Backup & Export', 'visitor-dashboard'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Export Settings', 'visitor-dashboard'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" onclick="exportSettings()">
                            <?php _e('Export Plugin Settings', 'visitor-dashboard'); ?>
                        </button>
                        <p class="description"><?php _e('Download a JSON file with all plugin settings.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Import Settings', 'visitor-dashboard'); ?></th>
                    <td>
                        <input type="file" id="settings-import" accept=".json">
                        <button type="button" class="button button-secondary" onclick="importSettings()">
                            <?php _e('Import Settings', 'visitor-dashboard'); ?>
                        </button>
                        <p class="description"><?php _e('Import plugin settings from a JSON file.', 'visitor-dashboard'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- About Tab -->
    <?php if ($current_tab === 'about'): ?>
    <div class="settings-tab-content">
        <div class="about-section">
            <h2><?php _e('About Visitor Management Dashboard', 'visitor-dashboard'); ?></h2>
            
            <div class="about-info">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Version', 'visitor-dashboard'); ?></th>
                        <td><?php echo esc_html(VISITOR_DASHBOARD_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('WordPress Version', 'visitor-dashboard'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('PHP Version', 'visitor-dashboard'); ?></th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('MySQL Version', 'visitor-dashboard'); ?></th>
                        <td><?php echo esc_html($GLOBALS['wpdb']->db_version()); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="about-description">
                <h3><?php _e('Features', 'visitor-dashboard'); ?></h3>
                <ul>
                    <li><?php _e('Multi-client visitor management with role-based access', 'visitor-dashboard'); ?></li>
                    <li><?php _e('Real-time visitor tracking from RB2B data integration', 'visitor-dashboard'); ?></li>
                    <li><?php _e('Campaign performance analytics from GroundTruth', 'visitor-dashboard'); ?></li>
                    <li><?php _e('Automated CRM exports with email delivery', 'visitor-dashboard'); ?></li>
                    <li><?php _e('Modern responsive dashboard interface', 'visitor-dashboard'); ?></li>
                    <li><?php _e('Comprehensive audit logging and security', 'visitor-dashboard'); ?></li>
                    <li><?php _e('RESTful API for webhook integrations', 'visitor-dashboard'); ?></li>
                </ul>
                
                <h3><?php _e('Support', 'visitor-dashboard'); ?></h3>
                <p><?php _e('For technical support, feature requests, or bug reports, please contact the development team.', 'visitor-dashboard'); ?></p>
                
                <h3><?php _e('Credits', 'visitor-dashboard'); ?></h3>
                <p><?php _e('Developed for MEMO Marketing Group with modern WordPress best practices.', 'visitor-dashboard'); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.visitor-dashboard-settings {
    max-width: 1200px;
}

.nav-tab-wrapper {
    margin-bottom: 20px;
}

.settings-tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.form-table th {
    width: 200px;
    padding: 20px 10px 20px 0;
}

.maintenance-section,
.system-info-section,
.backup-section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
}

.email-test-section {
    margin-top: 20px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f0f6fc;
}

.about-section {
    max-width: 800px;
}

.about-info {
    margin-bottom: 30px;
}

.about-description h3 {
    margin-top: 25px;
    margin-bottom: 10px;
}

.about-description ul {
    margin-left: 20px;
}

.about-description li {
    margin-bottom: 5px;
}

.system-status {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.status-item {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
}

.status-item h4 {
    margin: 0 0 10px 0;
    color: #1e1e1e;
}

.status-value {
    font-weight: 600;
    color: #2271b1;
}

.status-ok {
    color: #00a32a;
}

.status-warning {
    color: #dba617;
}

.status-error {
    color: #d63638;
}

.spinner.is-active {
    float: none;
    margin-right: 5px;
}

@media (max-width: 768px) {
    .form-table th {
        width: auto;
        display: block;
        padding: 10px 0 5px 0;
    }
    
    .form-table td {
        display: block;
        padding: 0 0 20px 0;
    }
    
    .system-status {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load system information on maintenance tab
    <?php if ($current_tab === 'maintenance'): ?>
    loadDatabaseStatus();
    loadSystemInfo();
    <?php endif; ?>
    
    // Auto-generate API key if empty
    <?php if (empty($api_key) && $current_tab === 'api'): ?>
    generateApiKey();
    <?php endif; ?>
});

// Generate new API key
function generateApiKey() {
    if (confirm('<?php _e('Generate a new API key? This will invalidate the current key.', 'visitor-dashboard'); ?>')) {
        jQuery.post(ajaxurl, {
            action: 'visitor_dashboard_generate_api_key',
            _wpnonce: '<?php echo wp_create_nonce('generate_api_key'); ?>'
        }, function(response) {
            if (response.success) {
                jQuery('#api_key').val(response.data.api_key);
                alert('<?php _e('New API key generated successfully.', 'visitor-dashboard'); ?>');
            } else {
                alert('<?php _e('Error generating API key.', 'visitor-dashboard'); ?>');
            }
        });
    }
}

// Copy text to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('<?php _e('Copied to clipboard!', 'visitor-dashboard'); ?>');
    }, function() {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('<?php _e('Copied to clipboard!', 'visitor-dashboard'); ?>');
    });
}

// Test email configuration
function testEmailConfig() {
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '<?php _e('Sending...', 'visitor-dashboard'); ?>';
    button.disabled = true;
    
    jQuery.post(ajaxurl, {
        action: 'visitor_dashboard_test_email',
        _wpnonce: '<?php echo wp_create_nonce('test_email'); ?>'
    }, function(response) {
        button.textContent = originalText;
        button.disabled = false;
        
        if (response.success) {
            alert('<?php _e('Test email sent successfully! Check your inbox.', 'visitor-dashboard'); ?>');
        } else {
            alert('<?php _e('Error sending test email: ', 'visitor-dashboard'); ?>' + response.data.message);
        }
    });
}

// Load database status
function loadDatabaseStatus() {
    jQuery.post(ajaxurl, {
        action: 'visitor_dashboard_database_status',
        _wpnonce: '<?php echo wp_create_nonce('database_status'); ?>'
    }, function(response) {
        if (response.success) {
            const data = response.data;
            const statusHtml = `
                <div class="system-status">
                    <div class="status-item">
                        <h4><?php _e('Tables', 'visitor-dashboard'); ?></h4>
                        <div class="status-value status-${data.tables_status}">${data.tables_count} <?php _e('tables', 'visitor-dashboard'); ?></div>
                    </div>
                    <div class="status-item">
                        <h4><?php _e('Visitors', 'visitor-dashboard'); ?></h4>
                        <div class="status-value">${data.visitor_count.toLocaleString()}</div>
                    </div>
                    <div class="status-item">
                        <h4><?php _e('Clients', 'visitor-dashboard'); ?></h4>
                        <div class="status-value">${data.client_count}</div>
                    </div>
                    <div class="status-item">
                        <h4><?php _e('Database Size', 'visitor-dashboard'); ?></h4>
                        <div class="status-value">${data.database_size}</div>
                    </div>
                </div>
            `;
            jQuery('#database-status').html(statusHtml);
        } else {
            jQuery('#database-status').html('<span class="status-error"><?php _e('Error loading database status', 'visitor-dashboard'); ?></span>');
        }
    });
}

// Load system information
function loadSystemInfo() {
    jQuery.post(ajaxurl, {
        action: 'visitor_dashboard_system_info',
        _wpnonce: '<?php echo wp_create_nonce('system_info'); ?>'
    }, function(response) {
        if (response.success) {
            const data = response.data;
            const infoHtml = `
                <div class="system-status">
                    <div class="status-item">
                        <h4><?php _e('Plugin Status', 'visitor-dashboard'); ?></h4>
                        <div class="status-value status-${data.plugin_status}">${data.plugin_status_text}</div>
                    </div>
                    <div class="status-item">
                        <h4><?php _e('REST API', 'visitor-dashboard'); ?></h4>
                        <div class="status-value status-${data.api_status}">${data.api_status_text}</div>
                    </div>
                    <div class="status-item">
                        <h4><?php _e('Cron Jobs', 'visitor-dashboard'); ?></h4>
                        <div class="status-value status-${data.cron_status}">${data.cron_status_text}</div>
                    </div>
                    <div class="status-item">
                        <h4><?php _e('Email Function', 'visitor-dashboard'); ?></h4>
                        <div class="status-value status-${data.email_status}">${data.email_status_text}</div>
                    </div>
                </div>
            `;
            jQuery('#system-info').html(infoHtml);
        } else {
            jQuery('#system-info').html('<span class="status-error"><?php _e('Error loading system information', 'visitor-dashboard'); ?></span>');
        }
    });
}

// Clear audit logs
function clearAuditLogs() {
    if (confirm('<?php _e('Are you sure you want to clear old audit logs? This action cannot be undone.', 'visitor-dashboard'); ?>')) {
        jQuery.post(ajaxurl, {
            action: 'visitor_dashboard_clear_audit_logs',
            _wpnonce: '<?php echo wp_create_nonce('clear_logs'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Audit logs cleared successfully.', 'visitor-dashboard'); ?>');
                loadDatabaseStatus();
            } else {
                alert('<?php _e('Error clearing audit logs.', 'visitor-dashboard'); ?>');
            }
        });
    }
}

// Clean import logs
function cleanImportLogs() {
    if (confirm('<?php _e('Remove import logs older than 30 days?', 'visitor-dashboard'); ?>')) {
        jQuery.post(ajaxurl, {
            action: 'visitor_dashboard_clean_import_logs',
            _wpnonce: '<?php echo wp_create_nonce('clean_logs'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Import logs cleaned successfully.', 'visitor-dashboard'); ?>');
                loadDatabaseStatus();
            } else {
                alert('<?php _e('Error cleaning import logs.', 'visitor-dashboard'); ?>');
            }
        });
    }
}

// Clean export files
function cleanExportFiles() {
    if (confirm('<?php _e('Remove CRM export files older than 30 days?', 'visitor-dashboard'); ?>')) {
        jQuery.post(ajaxurl, {
            action: 'visitor_dashboard_clean_export_files',
            _wpnonce: '<?php echo wp_create_nonce('clean_files'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Export files cleaned successfully.', 'visitor-dashboard'); ?>');
            } else {
                alert('<?php _e('Error cleaning export files.', 'visitor-dashboard'); ?>');
            }
        });
    }
}

// Export settings
function exportSettings() {
    jQuery.post(ajaxurl, {
        action: 'visitor_dashboard_export_settings',
        _wpnonce: '<?php echo wp_create_nonce('export_settings'); ?>'
    }, function(response) {
        if (response.success) {
            // Create and download file
            const blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'visitor-dashboard-settings-' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } else {
            alert('<?php _e('Error exporting settings.', 'visitor-dashboard'); ?>');
        }
    });
}

// Import settings
function importSettings() {
    const fileInput = document.getElementById('settings-import');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('<?php _e('Please select a settings file to import.', 'visitor-dashboard'); ?>');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const settings = JSON.parse(e.target.result);
            
            jQuery.post(ajaxurl, {
                action: 'visitor_dashboard_import_settings',
                settings: settings,
                _wpnonce: '<?php echo wp_create_nonce('import_settings'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Settings imported successfully. Page will reload.', 'visitor-dashboard'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Error importing settings: ', 'visitor-dashboard'); ?>' + response.data.message);
                }
            });
        } catch (error) {
            alert('<?php _e('Invalid settings file format.', 'visitor-dashboard'); ?>');
        }
    };
    reader.readAsText(file);
}
</script>

<?php
// AJAX handlers would be added to the main plugin file or a separate AJAX handler file

// Add AJAX actions
add_action('wp_ajax_visitor_dashboard_generate_api_key', 'visitor_dashboard_ajax_generate_api_key');
add_action('wp_ajax_visitor_dashboard_test_email', 'visitor_dashboard_ajax_test_email');
add_action('wp_ajax_visitor_dashboard_database_status', 'visitor_dashboard_ajax_database_status');
add_action('wp_ajax_visitor_dashboard_system_info', 'visitor_dashboard_ajax_system_info');
add_action('wp_ajax_visitor_dashboard_clear_audit_logs', 'visitor_dashboard_ajax_clear_audit_logs');
add_action('wp_ajax_visitor_dashboard_clean_import_logs', 'visitor_dashboard_ajax_clean_import_logs');
add_action('wp_ajax_visitor_dashboard_clean_export_files', 'visitor_dashboard_ajax_clean_export_files');
add_action('wp_ajax_visitor_dashboard_export_settings', 'visitor_dashboard_ajax_export_settings');
add_action('wp_ajax_visitor_dashboard_import_settings', 'visitor_dashboard_ajax_import_settings');

// AJAX handler functions
function visitor_dashboard_ajax_generate_api_key() {
    check_ajax_referer('generate_api_key');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $new_api_key = wp_generate_password(32, false);
    update_option('visitor_dashboard_api_key', $new_api_key);
    
    wp_send_json_success(array('api_key' => $new_api_key));
}

function visitor_dashboard_ajax_test_email() {
    check_ajax_referer('test_email');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $to = get_option('visitor_dashboard_crm_email', get_option('admin_email'));
    $subject = 'Visitor Dashboard Test Email';
    $message = 'This is a test email from the Visitor Dashboard plugin. If you receive this, your email configuration is working correctly.';
    
    $result = wp_mail($to, $subject, $message);
    
    if ($result) {
        wp_send_json_success(array('message' => 'Test email sent successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to send test email'));
    }
}

function visitor_dashboard_ajax_database_status() {
    check_ajax_referer('database_status');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $prefix = VisitorDashboard_Database::get_table_prefix();
    
    // Count tables
    $tables = $wpdb->get_results("SHOW TABLES LIKE '{$prefix}%'");
    $tables_count = count($tables);
    $tables_status = $tables_count >= 7 ? 'ok' : 'error';
    
    // Count visitors
    $visitor_count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}visitors");
    
    // Count clients
    $client_count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}clients");
    
    // Get database size
    $database_size = $wpdb->get_var("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = DATABASE()");
    
    wp_send_json_success(array(
        'tables_count' => $tables_count,
        'tables_status' => $tables_status,
        'visitor_count' => intval($visitor_count),
        'client_count' => intval($client_count),
        'database_size' => $database_size . ' MB'
    ));
}

function visitor_dashboard_ajax_system_info() {
    check_ajax_referer('system_info');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Check plugin status
    $plugin_status = VisitorDashboard_Database::tables_exist() ? 'ok' : 'error';
    $plugin_status_text = $plugin_status === 'ok' ? 'Active' : 'Database Error';
    
    // Check REST API
    $api_status = rest_url() ? 'ok' : 'error';
    $api_status_text = $api_status === 'ok' ? 'Available' : 'Not Available';
    
    // Check cron
    $cron_status = wp_next_scheduled('visitor_dashboard_daily_crm_export') ? 'ok' : 'warning';
    $cron_status_text = $cron_status === 'ok' ? 'Scheduled' : 'Not Scheduled';
    
    // Check email function
    $email_status = function_exists('wp_mail') ? 'ok' : 'error';
    $email_status_text = $email_status === 'ok' ? 'Available' : 'Not Available';
    
    wp_send_json_success(array(
        'plugin_status' => $plugin_status,
        'plugin_status_text' => $plugin_status_text,
        'api_status' => $api_status,
        'api_status_text' => $api_status_text,
        'cron_status' => $cron_status,
        'cron_status_text' => $cron_status_text,
        'email_status' => $email_status,
        'email_status_text' => $email_status_text
    ));
}

function visitor_dashboard_ajax_clear_audit_logs() {
    check_ajax_referer('clear_logs');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $prefix = VisitorDashboard_Database::get_table_prefix();
    
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-90 days'));
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$prefix}audit_logs WHERE created_at < %s",
        $cutoff_date
    ));
    
    wp_send_json_success(array('deleted' => $deleted));
}

function visitor_dashboard_ajax_clean_import_logs() {
    check_ajax_referer('clean_logs');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    require_once VISITOR_DASHBOARD_PLUGIN_PATH . 'includes/class-data-importer.php';
    $importer = new VisitorDashboard_Data_Importer();
    $deleted = $importer->cleanup_old_import_logs(30);
    
    wp_send_json_success(array('deleted' => $deleted));
}

function visitor_dashboard_ajax_clean_export_files() {
    check_ajax_referer('clean_files');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    require_once VISITOR_DASHBOARD_PLUGIN_PATH . 'includes/class-crm-exporter.php';
    $exporter = new VisitorDashboard_CRM_Exporter();
    $deleted = $exporter->cleanup_old_exports(30);
    
    wp_send_json_success(array('deleted' => $deleted));
}

function visitor_dashboard_ajax_export_settings() {
    check_ajax_referer('export_settings');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $settings = array(
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
        ),
        'exported_at' => current_time('Y-m-d H:i:s'),
        'plugin_version' => VISITOR_DASHBOARD_VERSION
    );
    
    wp_send_json_success($settings);
}

function visitor_dashboard_ajax_import_settings() {
    check_ajax_referer('import_settings');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $settings = $_POST['settings'];
    
    if (!is_array($settings)) {
        wp_send_json_error(array('message' => 'Invalid settings format'));
    }
    
    // Import general settings
    if (isset($settings['general'])) {
        foreach ($settings['general'] as $key => $value) {
            update_option('visitor_dashboard_' . $key, $value);
        }
    }
    
    // Import API settings (excluding API key for security)
    if (isset($settings['api'])) {
        foreach ($settings['api'] as $key => $value) {
            if ($key !== 'api_key') {
                update_option('visitor_dashboard_' . $key, $value);
            }
        }
    }
    
    // Import email settings (excluding password for security)
    if (isset($settings['email'])) {
        foreach ($settings['email'] as $key => $value) {
            if ($key !== 'smtp_password') {
                update_option('visitor_dashboard_' . $key, $value);
            }
        }
    }
    
    wp_send_json_success(array('message' => 'Settings imported successfully'));
}
?>
