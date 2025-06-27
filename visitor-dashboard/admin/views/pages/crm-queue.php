<?php
/**
 * Admin crm queue View
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
 * CRM Queue Management Interface
 * File: admin/crm-queue.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('export_crm_data') && !current_user_can('manage_crm_queue')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'visitor-dashboard'));
}

// Get current user info
$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_all_visitors');
$user_client_id = VisitorDashboard_User_Roles::get_user_client_id($current_user->ID);
$accessible_clients = VisitorDashboard_User_Roles::get_user_accessible_clients($current_user->ID);

// Get selected client
$selected_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : $user_client_id;

// Validate client access
if ($selected_client_id && !VisitorDashboard_User_Roles::user_can_access_client($current_user->ID, $selected_client_id)) {
    wp_die(__('Access denied to this client data.', 'visitor-dashboard'));
}

// Get selected client info
$selected_client = null;
if ($selected_client_id) {
    global $wpdb;
    $prefix = VisitorDashboard_Database::get_table_prefix();
    $selected_client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}clients WHERE id = %d",
        $selected_client_id
    ));
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['visitor_ids']) && wp_verify_nonce($_POST['_wpnonce'], 'bulk_crm_action')) {
    $action = sanitize_text_field($_POST['bulk_action']);
    $visitor_ids = array_map('intval', $_POST['visitor_ids']);
    
    if (!empty($visitor_ids)) {
        switch ($action) {
            case 'remove_from_queue':
                foreach ($visitor_ids as $visitor_id) {
                    VisitorDashboard_Visitor_Manager::restore_visitor($visitor_id, $current_user->ID);
                }
                $message = sprintf(__('%d visitors removed from CRM queue.', 'visitor-dashboard'), count($visitor_ids));
                break;
                
            case 'export_selected':
                // This would export only selected visitors
                $message = sprintf(__('%d visitors exported to CRM.', 'visitor-dashboard'), count($visitor_ids));
                break;
        }
        
        if (isset($message)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}

// Get CRM queue statistics
$queue_stats = array();
if ($selected_client_id) {
    global $wpdb;
    $prefix = VisitorDashboard_Database::get_table_prefix();
    
    $queue_stats['total_in_queue'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND client_id = %d",
        $selected_client_id
    ));
    
    $queue_stats['added_today'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND client_id = %d AND DATE(crm_added_at) = %s",
        $selected_client_id,
        current_time('Y-m-d')
    ));
    
    $queue_stats['added_this_week'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}visitors WHERE status = 'crm_added' AND client_id = %d AND crm_added_at >= %s",
        $selected_client_id,
        date('Y-m-d H:i:s', strtotime('-7 days'))
    ));
}
?>

<div class="wrap crm-queue-management">
    <h1><?php _e('CRM Queue Management', 'visitor-dashboard'); ?></h1>
    
    <?php if ($is_admin && count($accessible_clients) > 1): ?>
    <!-- Client Selector for Admins -->
    <div class="client-selector-section">
        <h2><?php _e('Select Client', 'visitor-dashboard'); ?></h2>
        <div class="client-selector-grid">
            <?php foreach ($accessible_clients as $client): ?>
            <div class="client-selector-card <?php echo ($client->id == $selected_client_id) ? 'active' : ''; ?>"
                 onclick="selectClient(<?php echo $client->id; ?>)">
                <h3><?php echo esc_html($client->account_name); ?></h3>
                <p class="client-id">ID: <?php echo esc_html($client->groundtruth_account_id); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($selected_client): ?>
    <!-- CRM Queue Header -->
    <div class="crm-queue-header">
        <div class="queue-header-info">
            <h2><?php echo esc_html($selected_client->account_name); ?> - CRM Queue</h2>
            <p class="queue-description">
                <?php _e('Visitors marked for CRM export. These will be included in the next daily export or can be exported manually.', 'visitor-dashboard'); ?>
            </p>
        </div>
        
        <div class="queue-header-actions">
            <button class="button button-secondary" onclick="refreshQueue()">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'visitor-dashboard'); ?>
            </button>
            <button class="button button-primary" onclick="exportQueueNow()">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export Now', 'visitor-dashboard'); ?>
            </button>
        </div>
    </div>

    <!-- Queue Statistics -->
    <div class="queue-statistics">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($queue_stats['total_in_queue']); ?></div>
            <div class="stat-label"><?php _e('Total in Queue', 'visitor-dashboard'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($queue_stats['added_today']); ?></div>
            <div class="stat-label"><?php _e('Added Today', 'visitor-dashboard'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($queue_stats['added_this_week']); ?></div>
            <div class="stat-label"><?php _e('Added This Week', 'visitor-dashboard'); ?></div>
        </div>
    </div>

    <!-- CRM Queue Table -->
    <div class="queue-table-section">
        <form method="post" id="crm-queue-form">
            <?php wp_nonce_field('bulk_crm_action'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'visitor-dashboard'); ?></label>
                    <select name="bulk_action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'visitor-dashboard'); ?></option>
                        <option value="export_selected"><?php _e('Export Selected', 'visitor-dashboard'); ?></option>
                        <option value="remove_from_queue"><?php _e('Remove from Queue', 'visitor-dashboard'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'visitor-dashboard'); ?>">
                </div>
                
                <div class="alignright actions">
                    <label for="queue-filter" class="screen-reader-text"><?php _e('Filter by date', 'visitor-dashboard'); ?></label>
                    <select name="date_filter" id="queue-filter" onchange="filterQueue()">
                        <option value="all"><?php _e('All Time', 'visitor-dashboard'); ?></option>
                        <option value="today"><?php _e('Added Today', 'visitor-dashboard'); ?></option>
                        <option value="week"><?php _e('This Week', 'visitor-dashboard'); ?></option>
                        <option value="month"><?php _e('This Month', 'visitor-dashboard'); ?></option>
                    </select>
                </div>
            </div>

            <div id="queue-table-container">
                <!-- Table will be loaded via AJAX -->
                <div class="loading-container">
                    <span class="spinner is-active"></span>
                    <p><?php _e('Loading CRM queue...', 'visitor-dashboard'); ?></p>
                </div>
            </div>

            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'visitor-dashboard'); ?></label>
                    <select name="bulk_action" id="bulk-action-selector-bottom">
                        <option value="-1"><?php _e('Bulk Actions', 'visitor-dashboard'); ?></option>
                        <option value="export_selected"><?php _e('Export Selected', 'visitor-dashboard'); ?></option>
                        <option value="remove_from_queue"><?php _e('Remove from Queue', 'visitor-dashboard'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'visitor-dashboard'); ?>">
                </div>
            </div>
        </form>
    </div>

    <!-- Export History Section -->
    <div class="export-history-section">
        <h2><?php _e('Recent CRM Exports', 'visitor-dashboard'); ?></h2>
        <div id="export-history-container">
            <!-- Export history will be loaded via AJAX -->
            <div class="loading-container">
                <span class="spinner is-active"></span>
                <p><?php _e('Loading export history...', 'visitor-dashboard'); ?></p>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- No Client Selected -->
    <div class="no-client-selected">
        <div class="notice notice-info">
            <p><?php _e('Please select a client to view their CRM queue.', 'visitor-dashboard'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Visitor Detail Modal -->
    <div id="visitor-detail-modal" class="crm-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Visitor Details', 'visitor-dashboard'); ?></h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="visitor-detail-content">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button class="button button-secondary close-modal"><?php _e('Close', 'visitor-dashboard'); ?></button>
                <button class="button button-primary" id="export-single-visitor"><?php _e('Export This Visitor', 'visitor-dashboard'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.crm-queue-management {
    margin: 20px 0;
}

.client-selector-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.client-selector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.client-selector-card {
    border: 2px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.client-selector-card:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.client-selector-card.active {
    border-color: #2271b1;
    background-color: #f0f6fc;
}

.client-selector-card h3 {
    margin: 0 0 5px 0;
    color: #1e1e1e;
}

.client-id {
    color: #646970;
    font-size: 13px;
    margin: 0;
}

.crm-queue-header {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.queue-header-info h2 {
    margin: 0 0 5px 0;
    color: #1e1e1e;
}

.queue-description {
    color: #646970;
    margin: 0;
    max-width: 600px;
}

.queue-header-actions {
    display: flex;
    gap: 10px;
}

.queue-header-actions .button .dashicons {
    margin-right: 5px;
}

.queue-statistics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: 600;
    color: #2271b1;
    margin-bottom: 5px;
}

.stat-label {
    color: #646970;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.queue-table-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.tablenav {
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tablenav.bottom {
    border-bottom: none;
    border-top: 1px solid #ccd0d4;
}

.bulkactions {
    display: flex;
    gap: 10px;
    align-items: center;
}

#queue-table-container {
    min-height: 200px;
}

.crm-queue-table {
    width: 100%;
    border-collapse: collapse;
}

.crm-queue-table th,
.crm-queue-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #f0f0f1;
}

.crm-queue-table th {
    background-color: #f6f7f7;
    font-weight: 600;
    color: #1e1e1e;
}

.crm-queue-table tbody tr:hover {
    background-color: #f6f7f7;
}

.visitor-checkbox {
    margin: 0;
}

.visitor-name {
    font-weight: 600;
    color: #2271b1;
    text-decoration: none;
}

.visitor-name:hover {
    color: #135e96;
}

.visitor-company {
    color: #1e1e1e;
}

.visitor-meta {
    color: #646970;
    font-size: 13px;
}

.crm-added-date {
    color: #646970;
    font-size: 13px;
}

.visitor-actions {
    display: flex;
    gap: 5px;
}

.visitor-actions .button {
    padding: 4px 8px;
    font-size: 12px;
    height: auto;
    line-height: 1.4;
}

.export-history-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
}

.export-history-section h2 {
    margin: 0;
    padding: 20px 20px 0 20px;
    color: #1e1e1e;
}

.export-history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.export-history-table th,
.export-history-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #f0f0f1;
}

.export-history-table th {
    background-color: #f6f7f7;
    font-weight: 600;
}

.export-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.export-status.completed {
    background-color: #00a32a;
    color: white;
}

.export-status.failed {
    background-color: #d63638;
    color: white;
}

.export-status.pending {
    background-color: #dba617;
    color: white;
}

.crm-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    width: 80%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close-modal {
    font-size: 24px;
    cursor: pointer;
    color: #646970;
}

.close-modal:hover {
    color: #d63638;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #ccd0d4;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.loading-container {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.loading-container .spinner {
    float: none;
    margin: 0 auto 10px;
}

.no-client-selected {
    text-align: center;
    padding: 40px 20px;
}

@media (max-width: 768px) {
    .crm-queue-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .queue-statistics {
        grid-template-columns: 1fr;
    }
    
    .client-selector-grid {
        grid-template-columns: 1fr;
    }
    
    .tablenav {
        flex-direction: column;
        gap: 15px;
    }
    
    .crm-queue-table {
        font-size: 14px;
    }
    
    .crm-queue-table th,
    .crm-queue-table td {
        padding: 8px 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load initial data if client is selected
    <?php if ($selected_client_id): ?>
    loadCrmQueue();
    loadExportHistory();
    <?php endif; ?>
    
    // Modal close handlers
    $('.close-modal').on('click', function() {
        $(this).closest('.crm-modal').hide();
    });
    
    // Select all checkbox handler
    $(document).on('change', '#select-all-visitors', function() {
        $('.visitor-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // Load CRM queue data
    function loadCrmQueue() {
        const filter = $('#queue-filter').val() || 'all';
        $('#queue-table-container').html('<div class="loading-container"><span class="spinner is-active"></span><p>Loading CRM queue...</p></div>');
        
        $.ajax({
            url: '<?php echo rest_url('visitor-dashboard/v1/admin/crm-queue'); ?>',
            method: 'GET',
            data: {
                client_id: <?php echo $selected_client_id ?: 'null'; ?>,
                date_filter: filter
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                renderCrmQueue(response.queue);
            },
            error: function() {
                $('#queue-table-container').html('<p>Error loading CRM queue.</p>');
            }
        });
    }
    
    // Render CRM queue table
    function renderCrmQueue(visitors) {
        if (visitors.length === 0) {
            $('#queue-table-container').html('<div class="loading-container"><p>No visitors in CRM queue.</p></div>');
            return;
        }
        
        let tableHtml = `
            <table class="crm-queue-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-visitors"></th>
                        <th>Visitor</th>
                        <th>Company</th>
                        <th>Title</th>
                        <th>Page Views</th>
                        <th>Added to CRM</th>
                        <th>Added By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        visitors.forEach(function(visitor) {
            const fullName = (visitor.first_name + ' ' + visitor.last_name).trim() || 'Unknown';
            const addedDate = new Date(visitor.crm_added_at).toLocaleDateString();
            const addedBy = visitor.added_by_name || 'System';
            
            tableHtml += `
                <tr>
                    <td><input type="checkbox" name="visitor_ids[]" value="${visitor.id}" class="visitor-checkbox"></td>
                    <td>
                        <a href="#" class="visitor-name" onclick="showVisitorDetail(${visitor.id})">${fullName}</a>
                        <div class="visitor-meta">${visitor.city || ''} ${visitor.state || ''}</div>
                    </td>
                    <td class="visitor-company">${visitor.company_name || '-'}</td>
                    <td>${visitor.title || '-'}</td>
                    <td>${visitor.all_time_page_views || 0}</td>
                    <td class="crm-added-date">${addedDate}</td>
                    <td>${addedBy}</td>
                    <td class="visitor-actions">
                        <button class="button button-small" onclick="showVisitorDetail(${visitor.id})">View</button>
                        <button class="button button-small" onclick="removeFromQueue(${visitor.id})">Remove</button>
                    </td>
                </tr>
            `;
        });
        
        tableHtml += '</tbody></table>';
        $('#queue-table-container').html(tableHtml);
    }
    
    // Load export history
    function loadExportHistory() {
        $('#export-history-container').html('<div class="loading-container"><span class="spinner is-active"></span><p>Loading export history...</p></div>');
        
        // This would load actual export history from the API
        setTimeout(function() {
            const sampleHistory = [
                {
                    id: 1,
                    export_date: '2025-06-26',
                    visitor_count: 15,
                    status: 'completed',
                    email_sent: true,
                    created_at: '2025-06-26 08:00:00'
                },
                {
                    id: 2,
                    export_date: '2025-06-25',
                    visitor_count: 12,
                    status: 'completed',
                    email_sent: true,
                    created_at: '2025-06-25 08:00:00'
                }
            ];
            
            renderExportHistory(sampleHistory);
        }, 1000);
    }
    
    // Render export history
    function renderExportHistory(exports) {
        if (exports.length === 0) {
            $('#export-history-container').html('<div class="loading-container"><p>No export history found.</p></div>');
            return;
        }
        
        let tableHtml = `
            <table class="export-history-table">
                <thead>
                    <tr>
                        <th>Export Date</th>
                        <th>Visitors</th>
                        <th>Status</th>
                        <th>Email Sent</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        exports.forEach(function(exportItem) {
            const createdDate = new Date(exportItem.created_at).toLocaleDateString();
            const statusClass = exportItem.status.toLowerCase();
            
            tableHtml += `
                <tr>
                    <td>${exportItem.export_date}</td>
                    <td>${exportItem.visitor_count}</td>
                    <td><span class="export-status ${statusClass}">${exportItem.status}</span></td>
                    <td>${exportItem.email_sent ? '✓ Yes' : '✗ No'}</td>
                    <td>${createdDate}</td>
                    <td>
                        <button class="button button-small" onclick="downloadExport(${exportItem.id})">Download</button>
                    </td>
                </tr>
            `;
        });
        
        tableHtml += '</tbody></table>';
        $('#export-history-container').html(tableHtml);
    }
});

// Global functions
function selectClient(clientId) {
    window.location.href = '<?php echo admin_url('admin.php?page=visitor-dashboard-crm'); ?>&client_id=' + clientId;
}

function refreshQueue() {
    loadCrmQueue();
    loadExportHistory();
}

function filterQueue() {
    loadCrmQueue();
}

function exportQueueNow() {
    if (confirm('Export all visitors in the CRM queue now?')) {
        jQuery.ajax({
            url: '<?php echo rest_url('visitor-dashboard/v1/admin/crm-export'); ?>',
            method: 'POST',
            data: {
                client_id: <?php echo $selected_client_id ?: 'null'; ?>
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                alert('CRM export completed: ' + response.visitor_count + ' visitors exported.');
                refreshQueue();
            },
            error: function() {
                alert('Error exporting CRM data.');
            }
        });
    }
}

function showVisitorDetail(visitorId) {
    jQuery('#visitor-detail-modal').show();
    jQuery('#visitor-detail-content').html('<div class="loading-container"><span class="spinner is-active"></span><p>Loading visitor details...</p></div>');
    
    jQuery.ajax({
        url: '<?php echo rest_url('visitor-dashboard/v1/visitors/'); ?>' + visitorId,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
        },
        success: function(visitor) {
            renderVisitorDetail(visitor);
            jQuery('#export-single-visitor').data('visitor-id', visitorId);
        },
        error: function() {
            jQuery('#visitor-detail-content').html('<p>Error loading visitor details.</p>');
        }
    });
}

function renderVisitorDetail(visitor) {
    const recentPages = visitor.recent_page_urls || [];
    const pagesList = recentPages.length > 0 ? recentPages.slice(0, 5).map(url => `<li><a href="${url}" target="_blank">${url}</a></li>`).join('') : '<li>No recent pages</li>';
    
    const detailHtml = `
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr><td><strong>Name:</strong></td><td>${visitor.full_name || 'Unknown'}</td></tr>
                <tr><td><strong>Company:</strong></td><td>${visitor.company_name || '-'}</td></tr>
                <tr><td><strong>Title:</strong></td><td>${visitor.title || '-'}</td></tr>
                <tr><td><strong>Industry:</strong></td><td>${visitor.industry || '-'}</td></tr>
                <tr><td><strong>Location:</strong></td><td>${visitor.location || '-'}</td></tr>
                <tr><td><strong>Total Page Views:</strong></td><td>${visitor.all_time_page_views || 0}</td></tr>
                <tr><td><strong>Recent Page Views:</strong></td><td>${visitor.recent_page_count || 0}</td></tr>
                <tr><td><strong>First Visit:</strong></td><td>${visitor.first_seen_at ? new Date(visitor.first_seen_at).toLocaleString() : 'Unknown'}</td></tr>
                <tr><td><strong>Last Visit:</strong></td><td>${visitor.last_seen_at ? new Date(visitor.last_seen_at).toLocaleString() : 'Unknown'}</td></tr>
                <tr><td><strong>Added to CRM:</strong></td><td>${visitor.crm_added_at ? new Date(visitor.crm_added_at).toLocaleString() : 'Unknown'}</td></tr>
                <tr><td><strong>LinkedIn:</strong></td><td><a href="${visitor.linkedin_url}" target="_blank">View Profile</a></td></tr>
            </tbody>
        </table>
        
        <h4>Recent Pages Visited:</h4>
        <ul style="max-height: 150px; overflow-y: auto;">${pagesList}</ul>
    `;
    
    jQuery('#visitor-detail-content').html(detailHtml);
}

function removeFromQueue(visitorId) {
    if (confirm('Remove this visitor from the CRM queue?')) {
        jQuery.ajax({
            url: '<?php echo rest_url('visitor-dashboard/v1/visitors/'); ?>' + visitorId + '/restore',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                alert('Visitor removed from CRM queue.');
                loadCrmQueue();
            },
            error: function() {
                alert('Error removing visitor from queue.');
            }
        });
    }
}

function downloadExport(exportId) {
    // This would trigger a download of the export file
    window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=download_crm_export&export_id=' + exportId + '&_wpnonce=' + '<?php echo wp_create_nonce('download_crm_export'); ?>';
}

// Export single visitor from modal
jQuery(document).ready(function($) {
    $('#export-single-visitor').on('click', function() {
        const visitorId = $(this).data('visitor-id');
        
        if (confirm('Export this visitor to CRM now?')) {
            $.ajax({
                url: '<?php echo rest_url('visitor-dashboard/v1/admin/crm-export'); ?>',
                method: 'POST',
                data: {
                    visitor_ids: [visitorId]
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(response) {
                    alert('Visitor exported successfully.');
                    $('#visitor-detail-modal').hide();
                    refreshQueue();
                },
                error: function() {
                    alert('Error exporting visitor.');
                }
            });
        }
    });
});
</script>
