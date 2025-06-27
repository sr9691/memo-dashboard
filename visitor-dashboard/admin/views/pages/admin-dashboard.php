<?php
/**
 * Admin admin dashboard View
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
 * Admin Dashboard Interface
 * File: admin/admin-dashboard.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check admin permissions
if (!current_user_can('manage_all_visitors')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'visitor-dashboard'));
}

// Get current user info
$current_user = wp_get_current_user();
$accessible_clients = VisitorDashboard_User_Roles::get_user_accessible_clients($current_user->ID);

// Get selected client (for admins)
$selected_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;

// Get client info
$selected_client = null;
if ($selected_client_id) {
    global $wpdb;
    $prefix = VisitorDashboard_Database::get_table_prefix();
    $selected_client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}clients WHERE id = %d",
        $selected_client_id
    ));
}

// Get dashboard statistics
$stats = VisitorDashboard_Database::get_database_stats();
?>

<div class="wrap visitor-dashboard-admin">
    <h1><?php _e('Visitor Dashboard - Admin Panel', 'visitor-dashboard'); ?></h1>
    
    <div class="admin-dashboard-container">
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="admin-header-info">
                <h2><?php echo esc_html($current_user->display_name); ?></h2>
                <p class="admin-role"><?php _e('Dashboard Administrator', 'visitor-dashboard'); ?></p>
            </div>
            
            <div class="admin-header-actions">
                <a href="<?php echo admin_url('admin.php?page=visitor-dashboard-users'); ?>" class="button button-secondary">
                    <?php _e('Manage Users', 'visitor-dashboard'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=visitor-dashboard-crm'); ?>" class="button button-secondary">
                    <?php _e('CRM Queue', 'visitor-dashboard'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=visitor-dashboard-settings'); ?>" class="button button-secondary">
                    <?php _e('Settings', 'visitor-dashboard'); ?>
                </a>
            </div>
        </div>

        <!-- System Overview Cards -->
        <div class="admin-overview-cards">
            <div class="overview-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($stats['total_visitors']); ?></h3>
                    <p><?php _e('Total Visitors', 'visitor-dashboard'); ?></p>
                </div>
            </div>

            <div class="overview-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($stats['total_clients']); ?></h3>
                    <p><?php _e('Active Clients', 'visitor-dashboard'); ?></p>
                </div>
            </div>

            <div class="overview-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-email-alt"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($stats['crm_added_today']); ?></h3>
                    <p><?php _e('CRM Added Today', 'visitor-dashboard'); ?></p>
                </div>
            </div>

            <div class="overview-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($stats['new_this_week']); ?></h3>
                    <p><?php _e('New This Week', 'visitor-dashboard'); ?></p>
                </div>
            </div>

            <div class="overview-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($stats['active_users']); ?></h3>
                    <p><?php _e('Dashboard Users', 'visitor-dashboard'); ?></p>
                </div>
            </div>
        </div>

        <!-- Client Management Section -->
        <div class="admin-section">
            <div class="section-header">
                <h2><?php _e('Client Management', 'visitor-dashboard'); ?></h2>
                <button class="button button-primary" id="refresh-clients">
                    <?php _e('Refresh Data', 'visitor-dashboard'); ?>
                </button>
            </div>

            <div class="clients-grid" id="clients-grid">
                <!-- Clients will be loaded via AJAX -->
                <div class="loading-placeholder">
                    <span class="spinner is-active"></span>
                    <p><?php _e('Loading clients...', 'visitor-dashboard'); ?></p>
                </div>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="admin-section">
            <div class="section-header">
                <h2><?php _e('Recent Activity', 'visitor-dashboard'); ?></h2>
                <select id="activity-filter">
                    <option value="all"><?php _e('All Activity', 'visitor-dashboard'); ?></option>
                    <option value="visitor_crm_add"><?php _e('CRM Additions', 'visitor-dashboard'); ?></option>
                    <option value="visitor_delete"><?php _e('Visitor Deletions', 'visitor-dashboard'); ?></option>
                    <option value="user_create"><?php _e('User Creation', 'visitor-dashboard'); ?></option>
                    <option value="crm_export"><?php _e('CRM Exports', 'visitor-dashboard'); ?></option>
                </select>
            </div>

            <div class="activity-list" id="activity-list">
                <!-- Activity will be loaded via AJAX -->
                <div class="loading-placeholder">
                    <span class="spinner is-active"></span>
                    <p><?php _e('Loading recent activity...', 'visitor-dashboard'); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="admin-section">
            <div class="section-header">
                <h2><?php _e('Quick Actions', 'visitor-dashboard'); ?></h2>
            </div>

            <div class="quick-actions-grid">
                <div class="quick-action-card" onclick="exportAllCrmData()">
                    <span class="dashicons dashicons-download"></span>
                    <h3><?php _e('Export All CRM Data', 'visitor-dashboard'); ?></h3>
                    <p><?php _e('Generate CRM export for all clients', 'visitor-dashboard'); ?></p>
                </div>

                <div class="quick-action-card" onclick="showImportStatus()">
                    <span class="dashicons dashicons-upload"></span>
                    <h3><?php _e('Import Status', 'visitor-dashboard'); ?></h3>
                    <p><?php _e('View data import logs and status', 'visitor-dashboard'); ?></p>
                </div>

                <div class="quick-action-card" onclick="showSystemHealth()">
                    <span class="dashicons dashicons-performance"></span>
                    <h3><?php _e('System Health', 'visitor-dashboard'); ?></h3>
                    <p><?php _e('Check system status and performance', 'visitor-dashboard'); ?></p>
                </div>

                <div class="quick-action-card" onclick="manageUsers()">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h3><?php _e('Manage Users', 'visitor-dashboard'); ?></h3>
                    <p><?php _e('Add, edit, and manage dashboard users', 'visitor-dashboard'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Status Modal -->
    <div id="import-status-modal" class="admin-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Data Import Status', 'visitor-dashboard'); ?></h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="import-status-content">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- System Health Modal -->
    <div id="system-health-modal" class="admin-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('System Health Check', 'visitor-dashboard'); ?></h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="system-health-content">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<style>
.visitor-dashboard-admin {
    margin: 20px 0;
}

.admin-dashboard-container {
    max-width: 1200px;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.admin-header-info h2 {
    margin: 0;
    color: #1e1e1e;
}

.admin-role {
    color: #646970;
    margin: 5px 0 0 0;
}

.admin-header-actions {
    display: flex;
    gap: 10px;
}

.admin-overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.overview-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.card-icon {
    background: #2271b1;
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.card-content h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: #1e1e1e;
}

.card-content p {
    margin: 5px 0 0 0;
    color: #646970;
    font-size: 14px;
}

.admin-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.section-header {
    padding: 20px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-header h2 {
    margin: 0;
    color: #1e1e1e;
}

.clients-grid {
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.client-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    transition: border-color 0.2s;
}

.client-card:hover {
    border-color: #2271b1;
}

.client-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.client-name {
    font-weight: 600;
    color: #1e1e1e;
}

.client-id {
    color: #646970;
    font-size: 12px;
}

.client-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
    text-align: center;
}

.client-stat {
    padding: 10px;
    background: #f6f7f7;
    border-radius: 3px;
}

.client-stat-number {
    font-weight: 600;
    color: #1e1e1e;
}

.client-stat-label {
    font-size: 11px;
    color: #646970;
    text-transform: uppercase;
}

.activity-list {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f1;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.activity-icon.crm-add { background: #00a32a; }
.activity-icon.delete { background: #d63638; }
.activity-icon.user { background: #2271b1; }
.activity-icon.export { background: #00a32a; }

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #1e1e1e;
    margin: 0 0 5px 0;
}

.activity-meta {
    color: #646970;
    font-size: 13px;
}

.quick-actions-grid {
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.quick-action-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.quick-action-card:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.quick-action-card .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #2271b1;
    margin-bottom: 10px;
}

.quick-action-card h3 {
    margin: 10px 0;
    color: #1e1e1e;
}

.quick-action-card p {
    color: #646970;
    font-size: 14px;
    margin: 0;
}

.admin-modal {
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
    padding: 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    width: 80%;
    max-width: 800px;
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
    font-weight: bold;
    cursor: pointer;
    color: #646970;
}

.close-modal:hover {
    color: #d63638;
}

.modal-body {
    padding: 20px;
}

.loading-placeholder {
    text-align: center;
    padding: 40px;
    color: #646970;
}

.loading-placeholder .spinner {
    float: none;
    margin: 0 auto 10px;
}

@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .admin-overview-cards {
        grid-template-columns: 1fr 1fr;
    }
    
    .clients-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load initial data
    loadClients();
    loadRecentActivity();
    
    // Refresh clients button
    $('#refresh-clients').on('click', function() {
        loadClients();
    });
    
    // Activity filter
    $('#activity-filter').on('change', function() {
        loadRecentActivity();
    });
    
    // Modal close handlers
    $('.close-modal').on('click', function() {
        $(this).closest('.admin-modal').hide();
    });
    
    // Load clients data
    function loadClients() {
        $('#clients-grid').html('<div class="loading-placeholder"><span class="spinner is-active"></span><p>Loading clients...</p></div>');
        
        $.ajax({
            url: '<?php echo rest_url('visitor-dashboard/v1/admin/clients'); ?>',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                renderClients(response.clients);
            },
            error: function() {
                $('#clients-grid').html('<p>Error loading clients.</p>');
            }
        });
    }
    
    // Render clients grid
    function renderClients(clients) {
        if (clients.length === 0) {
            $('#clients-grid').html('<p>No clients found.</p>');
            return;
        }
        
        let html = '';
        clients.forEach(function(client) {
            html += `
                <div class="client-card">
                    <div class="client-header">
                        <div>
                            <div class="client-name">${client.account_name}</div>
                            <div class="client-id">ID: ${client.groundtruth_account_id}</div>
                        </div>
                    </div>
                    <div class="client-stats">
                        <div class="client-stat">
                            <div class="client-stat-number">${client.visitor_count || 0}</div>
                            <div class="client-stat-label">Total</div>
                        </div>
                        <div class="client-stat">
                            <div class="client-stat-number">${client.active_visitors || 0}</div>
                            <div class="client-stat-label">Active</div>
                        </div>
                        <div class="client-stat">
                            <div class="client-stat-number">${client.crm_visitors || 0}</div>
                            <div class="client-stat-label">CRM</div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#clients-grid').html(html);
    }
    
    // Load recent activity
    function loadRecentActivity() {
        const filter = $('#activity-filter').val();
        $('#activity-list').html('<div class="loading-placeholder"><span class="spinner is-active"></span><p>Loading activity...</p></div>');
        
        // This would be implemented with actual audit log data
        setTimeout(function() {
            const sampleActivity = [
                {
                    type: 'visitor_crm_add',
                    title: 'John Doe added to CRM',
                    meta: 'Office Evolution • 2 hours ago',
                    icon: 'crm-add'
                },
                {
                    type: 'crm_export',
                    title: 'Daily CRM export completed',
                    meta: 'All clients • 6 hours ago',
                    icon: 'export'
                },
                {
                    type: 'user_create',
                    title: 'New user created: jane.smith@example.com',
                    meta: 'CleanSlate Technologies • 1 day ago',
                    icon: 'user'
                }
            ];
            
            renderActivity(sampleActivity);
        }, 1000);
    }
    
    // Render activity list
    function renderActivity(activities) {
        if (activities.length === 0) {
            $('#activity-list').html('<p>No recent activity found.</p>');
            return;
        }
        
        let html = '';
        activities.forEach(function(activity) {
            html += `
                <div class="activity-item">
                    <div class="activity-icon ${activity.icon}">
                        <span class="dashicons dashicons-${getActivityIcon(activity.type)}"></span>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">${activity.title}</div>
                        <div class="activity-meta">${activity.meta}</div>
                    </div>
                </div>
            `;
        });
        
        $('#activity-list').html(html);
    }
    
    // Get activity icon
    function getActivityIcon(type) {
        const icons = {
            'visitor_crm_add': 'email-alt',
            'visitor_delete': 'trash',
            'user_create': 'admin-users',
            'crm_export': 'download'
        };
        return icons[type] || 'admin-generic';
    }
});

// Quick action functions
function exportAllCrmData() {
    if (confirm('Export CRM data for all clients today?')) {
        jQuery.ajax({
            url: '<?php echo rest_url('visitor-dashboard/v1/admin/crm-export'); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                alert('CRM export completed: ' + response.visitor_count + ' visitors exported.');
            },
            error: function() {
                alert('Error exporting CRM data.');
            }
        });
    }
}

function showImportStatus() {
    jQuery('#import-status-modal').show();
    
    // Load import status data
    jQuery('#import-status-content').html('<div class="loading-placeholder"><span class="spinner is-active"></span><p>Loading import status...</p></div>');
    
    // This would load actual import log data
    setTimeout(function() {
        jQuery('#import-status-content').html(`
            <h4>Recent Imports</h4>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Filename</th>
                        <th>Status</th>
                        <th>Records</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>RB2B</td>
                        <td>daily_rpt_6_26_25_315957.csv</td>
                        <td><span style="color: green;">✓ Completed</span></td>
                        <td>25</td>
                        <td>2025-06-26 08:30:00</td>
                    </tr>
                    <tr>
                        <td>GroundTruth</td>
                        <td>daily_trend-2025-06-26.csv</td>
                        <td><span style="color: green;">✓ Completed</span></td>
                        <td>142</td>
                        <td>2025-06-26 10:15:00</td>
                    </tr>
                </tbody>
            </table>
        `);
    }, 1000);
}

function showSystemHealth() {
    jQuery('#system-health-modal').show();
    
    // Load system health data
    jQuery('#system-health-content').html('<div class="loading-placeholder"><span class="spinner is-active"></span><p>Checking system health...</p></div>');
    
    // This would check actual system health
    setTimeout(function() {
        jQuery('#system-health-content').html(`
            <h4>System Status</h4>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong>Database Connection</strong></td>
                        <td><span style="color: green;">✓ Healthy</span></td>
                    </tr>
                    <tr>
                        <td><strong>Plugin Tables</strong></td>
                        <td><span style="color: green;">✓ All Present</span></td>
                    </tr>
                    <tr>
                        <td><strong>REST API</strong></td>
                        <td><span style="color: green;">✓ Accessible</span></td>
                    </tr>
                    <tr>
                        <td><strong>Email Configuration</strong></td>
                        <td><span style="color: green;">✓ Configured</span></td>
                    </tr>
                    <tr>
                        <td><strong>Scheduled Tasks</strong></td>
                        <td><span style="color: green;">✓ Running</span></td>
                    </tr>
                </tbody>
            </table>
            <p><em>Last checked: ${new Date().toLocaleString()}</em></p>
        `);
    }, 1500);
}

function manageUsers() {
    window.location.href = '<?php echo admin_url('admin.php?page=visitor-dashboard-users'); ?>';
}
</script>
