<?php
/**
 * Admin user management View
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
 * User Management Interface
 * File: admin/user-management.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_dashboard_users')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'visitor-dashboard'));
}

// Get accessible clients
$accessible_clients = VisitorDashboard_User_Roles::get_user_accessible_clients(get_current_user_id());

// Handle form submissions
if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'user_management_action')) {
    
    switch ($_POST['action']) {
        case 'create_user':
            $result = handle_create_user();
            break;
            
        case 'update_user':
            $result = handle_update_user();
            break;
            
        case 'delete_user':
            $result = handle_delete_user();
            break;
            
        case 'bulk_action':
            $result = handle_bulk_action();
            break;
    }
    
    if (isset($result)) {
        if ($result['success']) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
}

// Handle user creation
function handle_create_user() {
    $username = sanitize_user($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $role = sanitize_text_field($_POST['user_role']);
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $password = wp_generate_password(12, false);
    
    // Validate inputs
    if (empty($username) || empty($email) || !is_email($email)) {
        return array('success' => false, 'message' => __('Please provide valid username and email.', 'visitor-dashboard'));
    }
    
    if (username_exists($username) || email_exists($email)) {
        return array('success' => false, 'message' => __('Username or email already exists.', 'visitor-dashboard'));
    }
    
    if ($role === 'client_user' && empty($client_id)) {
        return array('success' => false, 'message' => __('Client users must be assigned to a client.', 'visitor-dashboard'));
    }
    
    // Create WordPress user
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        return array('success' => false, 'message' => $user_id->get_error_message());
    }
    
    // Update user meta
    wp_update_user(array(
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => trim($first_name . ' ' . $last_name)
    ));
    
    // Create dashboard user record
    $dashboard_role = ($role === 'admin') ? 'admin' : 'client_user';
    $dashboard_user_id = VisitorDashboard_User_Roles::create_dashboard_user($user_id, $client_id, $dashboard_role);
    
    if ($dashboard_user_id) {
        // Send welcome email with password
        wp_new_user_notification($user_id, null, 'both');
        
        return array('success' => true, 'message' => sprintf(__('User %s created successfully. Password: %s', 'visitor-dashboard'), $username, $password));
    } else {
        wp_delete_user($user_id);
        return array('success' => false, 'message' => __('Failed to create dashboard user record.', 'visitor-dashboard'));
    }
}

// Handle user update
function handle_update_user() {
    $user_id = intval($_POST['user_id']);
    $email = sanitize_email($_POST['email']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $role = sanitize_text_field($_POST['user_role']);
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $status = sanitize_text_field($_POST['status']);
    
    // Update WordPress user
    $user_data = array(
        'ID' => $user_id,
        'user_email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => trim($first_name . ' ' . $last_name)
    );
    
    $result = wp_update_user($user_data);
    
    if (is_wp_error($result)) {
        return array('success' => false, 'message' => $result->get_error_message());
    }
    
    // Update dashboard user record
    global $wpdb;
    $prefix = VisitorDashboard_Database::get_table_prefix();
    
    $dashboard_role = ($role === 'admin') ? 'admin' : 'client_user';
    
    $wpdb->update(
        "{$prefix}dashboard_users",
        array(
            'role' => $dashboard_role,
            'client_id' => $client_id,
            'email' => $email,
            'name' => trim($first_name . ' ' . $last_name),
            'status' => $status
        ),
        array('wp_user_id' => $user_id),
        array('%s', '%d', '%s', '%s', '%s'),
        array('%d')
    );
    
    // Update WordPress role
    $wp_user = get_user_by('id', $user_id);
    if ($wp_user) {
        $wp_role = ($role === 'admin') ? 'visitor_dashboard_admin' : 'visitor_dashboard_client';
        $wp_user->set_role($wp_role);
    }
    
    return array('success' => true, 'message' => __('User updated successfully.', 'visitor-dashboard'));
}

// Handle user deletion
function handle_delete_user() {
    $user_id = intval($_POST['user_id']);
    
    // Deactivate dashboard user
    VisitorDashboard_User_Roles::deactivate_dashboard_user($user_id, get_current_user_id());
    
    return array('success' => true, 'message' => __('User deactivated successfully.', 'visitor-dashboard'));
}

// Handle bulk actions
function handle_bulk_action() {
    $bulk_action = sanitize_text_field($_POST['bulk_action']);
    $user_ids = array_map('intval', $_POST['user_ids']);
    
    if (empty($user_ids)) {
        return array('success' => false, 'message' => __('No users selected.', 'visitor-dashboard'));
    }
    
    $count = 0;
    
    switch ($bulk_action) {
        case 'activate':
            foreach ($user_ids as $user_id) {
                global $wpdb;
                $prefix = VisitorDashboard_Database::get_table_prefix();
                $wpdb->update(
                    "{$prefix}dashboard_users",
                    array('status' => 'active'),
                    array('wp_user_id' => $user_id),
                    array('%s'),
                    array('%d')
                );
                $count++;
            }
            $message = sprintf(__('%d users activated.', 'visitor-dashboard'), $count);
            break;
            
        case 'deactivate':
            foreach ($user_ids as $user_id) {
                VisitorDashboard_User_Roles::deactivate_dashboard_user($user_id, get_current_user_id());
                $count++;
            }
            $message = sprintf(__('%d users deactivated.', 'visitor-dashboard'), $count);
            break;
            
        default:
            return array('success' => false, 'message' => __('Invalid bulk action.', 'visitor-dashboard'));
    }
    
    return array('success' => true, 'message' => $message);
}

// Get user statistics
$user_stats = VisitorDashboard_User_Roles::get_user_statistics();

?>

<div class="wrap user-management">
    <h1><?php _e('User Management', 'visitor-dashboard'); ?></h1>
    
    <!-- User Statistics -->
    <div class="user-stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user_stats['total_active']); ?></div>
                <div class="stat-label"><?php _e('Total Active Users', 'visitor-dashboard'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user_stats['admin_users']); ?></div>
                <div class="stat-label"><?php _e('Admin Users', 'visitor-dashboard'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user_stats['client_users']); ?></div>
                <div class="stat-label"><?php _e('Client Users', 'visitor-dashboard'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user_stats['logged_in_today']); ?></div>
                <div class="stat-label"><?php _e('Logged In Today', 'visitor-dashboard'); ?></div>
            </div>
        </div>
    </div>

    <!-- Add New User Section -->
    <div class="add-user-section">
        <h2><?php _e('Add New User', 'visitor-dashboard'); ?></h2>
        <button class="button button-primary" onclick="showAddUserForm()">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php _e('Add New User', 'visitor-dashboard'); ?>
        </button>
    </div>

    <!-- User Management Table -->
    <div class="users-table-section">
        <form method="post" id="users-form">
            <?php wp_nonce_field('user_management_action'); ?>
            <input type="hidden" name="action" value="bulk_action">
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'visitor-dashboard'); ?></label>
                    <select name="bulk_action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'visitor-dashboard'); ?></option>
                        <option value="activate"><?php _e('Activate', 'visitor-dashboard'); ?></option>
                        <option value="deactivate"><?php _e('Deactivate', 'visitor-dashboard'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'visitor-dashboard'); ?>">
                </div>
                
                <div class="alignright actions">
                    <select id="role-filter" onchange="filterUsers()">
                        <option value=""><?php _e('All Roles', 'visitor-dashboard'); ?></option>
                        <option value="admin"><?php _e('Admin Users', 'visitor-dashboard'); ?></option>
                        <option value="client_user"><?php _e('Client Users', 'visitor-dashboard'); ?></option>
                    </select>
                    <select id="status-filter" onchange="filterUsers()">
                        <option value=""><?php _e('All Status', 'visitor-dashboard'); ?></option>
                        <option value="active"><?php _e('Active', 'visitor-dashboard'); ?></option>
                        <option value="inactive"><?php _e('Inactive', 'visitor-dashboard'); ?></option>
                    </select>
                    <button type="button" class="button" onclick="refreshUsers()">
                        <?php _e('Refresh', 'visitor-dashboard'); ?>
                    </button>
                </div>
            </div>

            <div id="users-table-container">
                <!-- Table will be loaded via AJAX -->
                <div class="loading-container">
                    <span class="spinner is-active"></span>
                    <p><?php _e('Loading users...', 'visitor-dashboard'); ?></p>
                </div>
            </div>
        </form>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="user-modal" class="user-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><?php _e('Add New User', 'visitor-dashboard'); ?></h3>
                <span class="close-modal">&times;</span>
            </div>
            
            <form id="user-form" method="post">
                <?php wp_nonce_field('user_management_action'); ?>
                <input type="hidden" id="form-action" name="action" value="create_user">
                <input type="hidden" id="user-id" name="user_id" value="">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username"><?php _e('Username', 'visitor-dashboard'); ?> <span class="required">*</span></label>
                            <input type="text" id="username" name="username" required class="regular-text">
                            <p class="description"><?php _e('Username for login (cannot be changed later).', 'visitor-dashboard'); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><?php _e('Email Address', 'visitor-dashboard'); ?> <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label for="first_name"><?php _e('First Name', 'visitor-dashboard'); ?></label>
                            <input type="text" id="first_name" name="first_name" class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name"><?php _e('Last Name', 'visitor-dashboard'); ?></label>
                            <input type="text" id="last_name" name="last_name" class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label for="user_role"><?php _e('Role', 'visitor-dashboard'); ?> <span class="required">*</span></label>
                            <select id="user_role" name="user_role" required onchange="toggleClientField()">
                                <option value=""><?php _e('Select Role', 'visitor-dashboard'); ?></option>
                                <option value="admin"><?php _e('Dashboard Admin', 'visitor-dashboard'); ?></option>
                                <option value="client_user"><?php _e('Client User', 'visitor-dashboard'); ?></option>
                            </select>
                            <p class="description"><?php _e('Admin users can manage all clients. Client users can only access their assigned client.', 'visitor-dashboard'); ?></p>
                        </div>
                        
                        <div class="form-group" id="client-field" style="display: none;">
                            <label for="client_id"><?php _e('Assigned Client', 'visitor-dashboard'); ?> <span class="required">*</span></label>
                            <select id="client_id" name="client_id">
                                <option value=""><?php _e('Select Client', 'visitor-dashboard'); ?></option>
                                <?php foreach ($accessible_clients as $client): ?>
                                <option value="<?php echo $client->id; ?>">
                                    <?php echo esc_html($client->account_name); ?> (ID: <?php echo esc_html($client->groundtruth_account_id); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="status-field" style="display: none;">
                            <label for="status"><?php _e('Status', 'visitor-dashboard'); ?></label>
                            <select id="status" name="status">
                                <option value="active"><?php _e('Active', 'visitor-dashboard'); ?></option>
                                <option value="inactive"><?php _e('Inactive', 'visitor-dashboard'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button button-secondary close-modal"><?php _e('Cancel', 'visitor-dashboard'); ?></button>
                    <button type="submit" class="button button-primary" id="submit-user"><?php _e('Create User', 'visitor-dashboard'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Detail Modal -->
    <div id="user-detail-modal" class="user-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('User Details', 'visitor-dashboard'); ?></h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="user-detail-content">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary close-modal"><?php _e('Close', 'visitor-dashboard'); ?></button>
                <button type="button" class="button button-primary" id="edit-user-btn"><?php _e('Edit User', 'visitor-dashboard'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.user-management {
    max-width: 1200px;
}

.user-stats-section {
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
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

.add-user-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.add-user-section h2 {
    margin: 0;
    color: #1e1e1e;
}

.add-user-section .button .dashicons {
    margin-right: 5px;
}

.users-table-section {
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

.bulkactions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.alignright.actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table th,
.users-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #f0f0f1;
}

.users-table th {
    background-color: #f6f7f7;
    font-weight: 600;
    color: #1e1e1e;
}

.users-table tbody tr:hover {
    background-color: #f6f7f7;
}

.user-checkbox {
    margin: 0;
}

.user-name {
    font-weight: 600;
    color: #2271b1;
    text-decoration: none;
}

.user-name:hover {
    color: #135e96;
}

.user-meta {
    color: #646970;
    font-size: 13px;
}

.user-role-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.role-admin {
    background-color: #d63638;
    color: white;
}

.role-client {
    background-color: #2271b1;
    color: white;
}

.user-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-active {
    background-color: #00a32a;
    color: white;
}

.status-inactive {
    background-color: #646970;
    color: white;
}

.user-actions {
    display: flex;
    gap: 5px;
}

.user-actions .button {
    padding: 4px 8px;
    font-size: 12px;
    height: auto;
    line-height: 1.4;
}

.user-modal {
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

.form-grid {
    display: grid;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #1e1e1e;
}

.required {
    color: #d63638;
}

.form-group .description {
    margin-top: 5px;
    font-size: 13px;
    color: #646970;
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

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .add-user-section {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .tablenav {
        flex-direction: column;
        gap: 15px;
    }
    
    .alignright.actions {
        flex-direction: column;
        width: 100%;
    }
    
    .users-table {
        font-size: 14px;
    }
    
    .users-table th,
    .users-table td {
        padding: 8px 10px;
    }
    
    .modal-content {
        width: 95%;
        margin: 2% auto;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load users on page load
    loadUsers();
    
    // Modal close handlers
    $('.close-modal').on('click', function() {
        $(this).closest('.user-modal').hide();
    });
    
    // Form submission handler
    $('#user-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Parse response to find success/error messages
                const $response = $(response);
                const $notice = $response.find('.notice');
                
                if ($notice.hasClass('notice-success')) {
                    $('#user-modal').hide();
                    loadUsers();
                    alert('<?php _e('User saved successfully!', 'visitor-dashboard'); ?>');
                } else if ($notice.hasClass('notice-error')) {
                    alert($notice.find('p').text());
                }
            },
            error: function() {
                alert('<?php _e('Error saving user.', 'visitor-dashboard'); ?>');
            }
        });
    });
    
    // Select all checkbox
    $(document).on('change', '#select-all-users', function() {
        $('.user-checkbox').prop('checked', $(this).prop('checked'));
    });
});

// Load users table
function loadUsers() {
    const roleFilter = jQuery('#role-filter').val();
    const statusFilter = jQuery('#status-filter').val();
    
    jQuery('#users-table-container').html('<div class="loading-container"><span class="spinner is-active"></span><p><?php _e('Loading users...', 'visitor-dashboard'); ?></p></div>');
    
    jQuery.ajax({
        url: '<?php echo rest_url('visitor-dashboard/v1/admin/users'); ?>',
        method: 'GET',
        data: {
            role_filter: roleFilter,
            status_filter: statusFilter
        },
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
        },
        success: function(response) {
            renderUsersTable(response.users);
        },
        error: function() {
            jQuery('#users-table-container').html('<p><?php _e('Error loading users.', 'visitor-dashboard'); ?></p>');
        }
    });
}

// Render users table
function renderUsersTable(users) {
    if (users.length === 0) {
        jQuery('#users-table-container').html('<div class="loading-container"><p><?php _e('No users found.', 'visitor-dashboard'); ?></p></div>');
        return;
    }
    
    let tableHtml = `
        <table class="users-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-users"></th>
                    <th><?php _e('User', 'visitor-dashboard'); ?></th>
                    <th><?php _e('Role', 'visitor-dashboard'); ?></th>
                    <th><?php _e('Client', 'visitor-dashboard'); ?></th>
                    <th><?php _e('Status', 'visitor-dashboard'); ?></th>
                    <th><?php _e('Last Login', 'visitor-dashboard'); ?></th>
                    <th><?php _e('Actions', 'visitor-dashboard'); ?></th>
                </tr>
            </thead>
            <tbody>
    `;
    
    users.forEach(function(user) {
        const roleBadgeClass = user.role === 'admin' ? 'role-admin' : 'role-client';
        const roleText = user.role === 'admin' ? '<?php _e('Admin', 'visitor-dashboard'); ?>' : '<?php _e('Client User', 'visitor-dashboard'); ?>';
        const statusClass = user.status === 'active' ? 'status-active' : 'status-inactive';
        const statusText = user.status === 'active' ? '<?php _e('Active', 'visitor-dashboard'); ?>' : '<?php _e('Inactive', 'visitor-dashboard'); ?>';
        const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString() : '<?php _e('Never', 'visitor-dashboard'); ?>';
        
        tableHtml += `
            <tr>
                <td><input type="checkbox" name="user_ids[]" value="${user.wp_user_id}" class="user-checkbox"></td>
                <td>
                    <a href="#" class="user-name" onclick="showUserDetail(${user.wp_user_id})">${user.name || user.user_login}</a>
                    <div class="user-meta">${user.email}</div>
                </td>
                <td><span class="user-role-badge ${roleBadgeClass}">${roleText}</span></td>
                <td>${user.account_name || '-'}</td>
                <td><span class="user-status ${statusClass}">${statusText}</span></td>
                <td>${lastLogin}</td>
                <td class="user-actions">
                    <button class="button button-small" onclick="editUser(${user.wp_user_id})"><?php _e('Edit', 'visitor-dashboard'); ?></button>
                    <button class="button button-small" onclick="showUserDetail(${user.wp_user_id})"><?php _e('View', 'visitor-dashboard'); ?></button>
                    ${user.status === 'active' ? 
                        `<button class="button button-small" onclick="deactivateUser(${user.wp_user_id})"><?php _e('Deactivate', 'visitor-dashboard'); ?></button>` :
                        `<button class="button button-small" onclick="activateUser(${user.wp_user_id})"><?php _e('Activate', 'visitor-dashboard'); ?></button>`
                    }
                </td>
            </tr>
        `;
    });
    
    tableHtml += '</tbody></table>';
    jQuery('#users-table-container').html(tableHtml);
}

// Show add user form
function showAddUserForm() {
    jQuery('#modal-title').text('<?php _e('Add New User', 'visitor-dashboard'); ?>');
    jQuery('#form-action').val('create_user');
    jQuery('#user-id').val('');
    jQuery('#submit-user').text('<?php _e('Create User', 'visitor-dashboard'); ?>');
    jQuery('#status-field').hide();
    jQuery('#username').prop('readonly', false);
    
    // Reset form
    jQuery('#user-form')[0].reset();
    
    jQuery('#user-modal').show();
}

// Edit user
function editUser(userId) {
    jQuery('#modal-title').text('<?php _e('Edit User', 'visitor-dashboard'); ?>');
    jQuery('#form-action').val('update_user');
    jQuery('#user-id').val(userId);
    jQuery('#submit-user').text('<?php _e('Update User', 'visitor-dashboard'); ?>');
    jQuery('#status-field').show();
    jQuery('#username').prop('readonly', true);
    
    // Load user data
    jQuery.ajax({
        url: '<?php echo rest_url('visitor-dashboard/v1/admin/users/'); ?>' + userId,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
        },
        success: function(user) {
            // Populate form with user data
            jQuery('#username').val(user.user_login);
            jQuery('#email').val(user.email);
            jQuery('#first_name').val(user.first_name || '');
            jQuery('#last_name').val(user.last_name || '');
            jQuery('#user_role').val(user.role);
            jQuery('#client_id').val(user.client_id || '');
            jQuery('#status').val(user.status);
            
            // Toggle client field based on role
            toggleClientField();
            
            jQuery('#user-modal').show();
        },
        error: function() {
            alert('<?php _e('Error loading user data.', 'visitor-dashboard'); ?>');
        }
    });
}

// Show user detail
function showUserDetail(userId) {
    jQuery('#user-detail-modal').show();
    jQuery('#user-detail-content').html('<div class="loading-container"><span class="spinner is-active"></span><p><?php _e('Loading user details...', 'visitor-dashboard'); ?></p></div>');
    
    jQuery.ajax({
        url: '<?php echo rest_url('visitor-dashboard/v1/admin/users/'); ?>' + userId,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
        },
        success: function(user) {
            renderUserDetail(user);
            jQuery('#edit-user-btn').data('user-id', userId);
        },
        error: function() {
            jQuery('#user-detail-content').html('<p><?php _e('Error loading user details.', 'visitor-dashboard'); ?></p>');
        }
    });
}

// Render user detail
function renderUserDetail(user) {
    const roleText = user.role === 'admin' ? '<?php _e('Dashboard Admin', 'visitor-dashboard'); ?>' : '<?php _e('Client User', 'visitor-dashboard'); ?>';
    const statusText = user.status === 'active' ? '<?php _e('Active', 'visitor-dashboard'); ?>' : '<?php _e('Inactive', 'visitor-dashboard'); ?>';
    const lastLogin = user.last_login ? new Date(user.last_login).toLocaleString() : '<?php _e('Never logged in', 'visitor-dashboard'); ?>';
    const dateJoined = new Date(user.date_joined).toLocaleDateString();
    
    const detailHtml = `
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr><td><strong><?php _e('Username:', 'visitor-dashboard'); ?></strong></td><td>${user.user_login}</td></tr>
                <tr><td><strong><?php _e('Name:', 'visitor-dashboard'); ?></strong></td><td>${user.name || user.user_login}</td></tr>
                <tr><td><strong><?php _e('Email:', 'visitor-dashboard'); ?></strong></td><td><a href="mailto:${user.email}">${user.email}</a></td></tr>
                <tr><td><strong><?php _e('Role:', 'visitor-dashboard'); ?></strong></td><td>${roleText}</td></tr>
                <tr><td><strong><?php _e('Assigned Client:', 'visitor-dashboard'); ?></strong></td><td>${user.account_name || '<?php _e('None (Admin User)', 'visitor-dashboard'); ?>'}</td></tr>
                <tr><td><strong><?php _e('Status:', 'visitor-dashboard'); ?></strong></td><td>${statusText}</td></tr>
                <tr><td><strong><?php _e('Date Joined:', 'visitor-dashboard'); ?></strong></td><td>${dateJoined}</td></tr>
                <tr><td><strong><?php _e('Last Login:', 'visitor-dashboard'); ?></strong></td><td>${lastLogin}</td></tr>
                <tr><td><strong><?php _e('Login Count:', 'visitor-dashboard'); ?></strong></td><td>${user.login_count || 0}</td></tr>
                <tr><td><strong><?php _e('WordPress User ID:', 'visitor-dashboard'); ?></strong></td><td>${user.wp_user_id}</td></tr>
            </tbody>
        </table>
        
        ${user.role === 'client_user' && user.account_name ? `
            <h4><?php _e('Client Access', 'visitor-dashboard'); ?></h4>
            <p><?php _e('This user has access to:', 'visitor-dashboard'); ?></p>
            <ul>
                <li><?php _e('View visitors for', 'visitor-dashboard'); ?> <strong>${user.account_name}</strong></li>
                <li><?php _e('Add visitors to CRM queue', 'visitor-dashboard'); ?></li>
                <li><?php _e('Archive visitors', 'visitor-dashboard'); ?></li>
                <li><?php _e('View campaign performance data', 'visitor-dashboard'); ?></li>
            </ul>
        ` : ''}
        
        ${user.role === 'admin' ? `
            <h4><?php _e('Admin Access', 'visitor-dashboard'); ?></h4>
            <p><?php _e('This user has full administrative access:', 'visitor-dashboard'); ?></p>
            <ul>
                <li><?php _e('Manage all clients and visitors', 'visitor-dashboard'); ?></li>
                <li><?php _e('Create and manage users', 'visitor-dashboard'); ?></li>
                <li><?php _e('Export CRM data', 'visitor-dashboard'); ?></li>
                <li><?php _e('System configuration and maintenance', 'visitor-dashboard'); ?></li>
                <li><?php _e('View audit logs and system analytics', 'visitor-dashboard'); ?></li>
            </ul>
        ` : ''}
    `;
    
    jQuery('#user-detail-content').html(detailHtml);
}

// Toggle client field based on role selection
function toggleClientField() {
    const role = jQuery('#user_role').val();
    if (role === 'client_user') {
        jQuery('#client-field').show();
        jQuery('#client_id').prop('required', true);
    } else {
        jQuery('#client-field').hide();
        jQuery('#client_id').prop('required', false);
        jQuery('#client_id').val('');
    }
}

// Activate user
function activateUser(userId) {
    if (confirm('<?php _e('Activate this user?', 'visitor-dashboard'); ?>')) {
        jQuery.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                action: 'update_user',
                user_id: userId,
                status: 'active',
                _wpnonce: '<?php echo wp_create_nonce('user_management_action'); ?>'
            },
            success: function() {
                loadUsers();
                alert('<?php _e('User activated successfully.', 'visitor-dashboard'); ?>');
            },
            error: function() {
                alert('<?php _e('Error activating user.', 'visitor-dashboard'); ?>');
            }
        });
    }
}

// Deactivate user
function deactivateUser(userId) {
    if (confirm('<?php _e('Deactivate this user? They will no longer be able to access the dashboard.', 'visitor-dashboard'); ?>')) {
        jQuery.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                action: 'delete_user',
                user_id: userId,
                _wpnonce: '<?php echo wp_create_nonce('user_management_action'); ?>'
            },
            success: function() {
                loadUsers();
                alert('<?php _e('User deactivated successfully.', 'visitor-dashboard'); ?>');
            },
            error: function() {
                alert('<?php _e('Error deactivating user.', 'visitor-dashboard'); ?>');
            }
        });
    }
}

// Filter users
function filterUsers() {
    loadUsers();
}

// Refresh users
function refreshUsers() {
    loadUsers();
}

// Edit user from detail modal
jQuery(document).ready(function($) {
    $('#edit-user-btn').on('click', function() {
        const userId = $(this).data('user-id');
        $('#user-detail-modal').hide();
        editUser(userId);
    });
});

// Send password reset email
function sendPasswordReset(userId) {
    if (confirm('<?php _e('Send password reset email to this user?', 'visitor-dashboard'); ?>')) {
        jQuery.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'visitor_dashboard_send_password_reset',
                user_id: userId,
                _wpnonce: '<?php echo wp_create_nonce('send_password_reset'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Password reset email sent successfully.', 'visitor-dashboard'); ?>');
                } else {
                    alert('<?php _e('Error sending password reset email.', 'visitor-dashboard'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error sending password reset email.', 'visitor-dashboard'); ?>');
            }
        });
    }
}

// Bulk operations handler
jQuery(document).ready(function($) {
    $('#users-form').on('submit', function(e) {
        const selectedUsers = $('.user-checkbox:checked').length;
        if (selectedUsers === 0) {
            e.preventDefault();
            alert('<?php _e('Please select at least one user.', 'visitor-dashboard'); ?>');
            return false;
        }
        
        const action = $('#bulk-action-selector-top').val();
        if (action === '-1') {
            e.preventDefault();
            alert('<?php _e('Please select a bulk action.', 'visitor-dashboard'); ?>');
            return false;
        }
        
        if (!confirm(`<?php _e('Apply this action to', 'visitor-dashboard'); ?> ${selectedUsers} <?php _e('selected users?', 'visitor-dashboard'); ?>`)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php
// AJAX handler for sending password reset
add_action('wp_ajax_visitor_dashboard_send_password_reset', 'visitor_dashboard_ajax_send_password_reset');

function visitor_dashboard_ajax_send_password_reset() {
    check_ajax_referer('send_password_reset');
    
    if (!current_user_can('manage_dashboard_users')) {
        wp_die('Insufficient permissions');
    }
    
    $user_id = intval($_POST['user_id']);
    $user = get_user_by('id', $user_id);
    
    if (!$user) {
        wp_send_json_error(array('message' => 'User not found'));
    }
    
    // Send password reset email
    $result = retrieve_password($user->user_login);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        wp_send_json_success(array('message' => 'Password reset email sent'));
    }
}
?>
