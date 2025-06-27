<?php
/**
 * Admin dashboard View
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
 * Main Dashboard Interface
 * File: admin/dashboard.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current user info
$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_all_visitors');
$user_client_id = VisitorDashboard_User_Roles::get_user_client_id($current_user->ID);
$accessible_clients = VisitorDashboard_User_Roles::get_user_accessible_clients($current_user->ID);

// Get selected client (for admins)
$selected_client_id = $is_admin && isset($_GET['client_id']) ? intval($_GET['client_id']) : $user_client_id;

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #1e293b;
            color: white;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #334155;
        }
        
        .user-info {
            background: #334155;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .user-role {
            font-size: 0.875rem;
            color: #94a3b8;
        }
        
        .client-selector {
            margin-bottom: 2rem;
        }
        
        .client-selector h3 {
            font-size: 1rem;
            margin-bottom: 1rem;
            color: #e2e8f0;
        }
        
        .client-list {
            list-style: none;
        }
        
        .client-item {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #334155;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .client-item:hover {
            background: #475569;
        }
        
        .client-item.active {
            background: #3b82f6;
        }
        
        .navigation {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: block;
            padding: 0.75rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .nav-link:hover {
            background: #334155;
            color: white;
        }
        
        .nav-link.active {
            background: #3b82f6;
            color: white;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        /* Analytics Cards */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .analytics-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .analytics-card h3 {
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .analytics-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        
        .analytics-change {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .change-positive {
            color: #059669;
        }
        
        .change-negative {
            color: #dc2626;
        }
        
        /* Visitors Table */
        .visitors-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .visitors-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .visitors-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .visitors-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .visitors-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .visitor-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .visitor-linkedin {
            color: #3b82f6;
            text-decoration: none;
        }
        
        .visitor-linkedin:hover {
            text-decoration: underline;
        }
        
        .visitor-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-success:hover {
            background: #047857;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }
        
        /* Loading States */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #6b7280;
        }
        
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .visitors-table {
                font-size: 0.875rem;
            }
            
            .visitor-actions {
                flex-direction: column;
            }
        }
        
        /* Visitor Detail Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .visitor-detail-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detail-label {
            font-weight: 500;
            color: #6b7280;
        }
        
        .detail-value {
            color: #1e293b;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                🎯 Visitor Dashboard
            </div>
            
            <div class="user-info">
                <div class="user-name"><?php echo esc_html($current_user->display_name); ?></div>
                <div class="user-role"><?php echo $is_admin ? 'Dashboard Admin' : 'Client User'; ?></div>
            </div>
            
            <?php if ($is_admin && count($accessible_clients) > 1): ?>
            <div class="client-selector">
                <h3>Clients</h3>
                <ul class="client-list">
                    <?php foreach ($accessible_clients as $client): ?>
                    <li class="client-item <?php echo ($client->id == $selected_client_id) ? 'active' : ''; ?>"
                        onclick="selectClient(<?php echo $client->id; ?>)">
                        <div><?php echo esc_html($client->account_name); ?></div>
                        <div style="font-size: 0.75rem; color: #94a3b8;">
                            ID: <?php echo esc_html($client->groundtruth_account_id); ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <nav>
                <ul class="navigation">
                    <li class="nav-item">
                        <a href="#visitors" class="nav-link active" onclick="showSection('visitors')">
                            👥 Visitors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#analytics" class="nav-link" onclick="showSection('analytics')">
                            📊 Analytics
                        </a>
                    </li>
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a href="#crm-queue" class="nav-link" onclick="showSection('crm-queue')">
                            📤 CRM Queue
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#users" class="nav-link" onclick="showSection('users')">
                            👤 Users
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="<?php echo wp_logout_url(); ?>" class="nav-link">
                            🚪 Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>
                        <?php if ($selected_client): ?>
                            <?php echo esc_html($selected_client->account_name); ?>
                        <?php else: ?>
                            Visitor Dashboard
                        <?php endif; ?>
                    </h1>
                    <?php if ($selected_client): ?>
                    <p style="color: #6b7280; margin-top: 0.5rem;">
                        Account ID: <?php echo esc_html($selected_client->groundtruth_account_id); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="refreshData()">
                        🔄 Refresh
                    </button>
                    <?php if (current_user_can('export_crm_data')): ?>
                    <button class="btn btn-primary" onclick="exportCrmData()">
                        📤 Export CRM
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Analytics Section -->
            <div class="analytics-grid" id="analytics-cards">
                <div class="analytics-card">
                    <h3>Total Visitors</h3>
                    <div class="analytics-number" id="total-visitors">-</div>
                    <div class="analytics-change">
                        <span class="change-positive">Loading...</span>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>New This Week</h3>
                    <div class="analytics-number" id="new-this-week">-</div>
                    <div class="analytics-change">
                        <span class="change-positive">Loading...</span>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>Added to CRM Today</h3>
                    <div class="analytics-number" id="crm-added-today">-</div>
                    <div class="analytics-change">
                        <span class="change-positive">Loading...</span>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>Campaign Impressions</h3>
                    <div class="analytics-number" id="campaign-impressions">-</div>
                    <div class="analytics-change">
                        <span class="change-positive">Last 30 days</span>
                    </div>
                </div>
            </div>
            
            <!-- Visitors Section -->
            <div class="visitors-section">
                <div class="section-header">
                    <h2 class="section-title">Recent Visitors</h2>
                    <div>
                        <button class="btn btn-secondary btn-sm" onclick="toggleArchivedVisitors()">
                            👁️ Show Archived
                        </button>
                    </div>
                </div>
                
                <div id="visitors-table-container">
                    <div class="loading">
                        <div class="spinner"></div>
                        Loading visitors...
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Visitor Detail Modal -->
    <div id="visitor-modal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Visitor Details</h3>
                <button class="close-btn" onclick="closeVisitorModal()">&times;</button>
            </div>
            
            <div id="visitor-details-content">
                <!-- Content will be populated by JavaScript -->
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeVisitorModal()">Close</button>
                <button class="btn btn-success" id="modal-crm-btn" onclick="addToCrmFromModal()">Add to CRM</button>
                <button class="btn btn-danger" id="modal-delete-btn" onclick="deleteFromModal()">Archive</button>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        let currentClientId = <?php echo $selected_client_id ? $selected_client_id : 'null'; ?>;
        let showArchived = false;
        let currentVisitorId = null;
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadAnalytics();
            loadVisitors();
        });
        
        // Load analytics data
        function loadAnalytics() {
            const url = new URL('<?php echo rest_url('visitor-dashboard/v1/analytics/summary'); ?>');
            if (currentClientId) {
                url.searchParams.set('client_id', currentClientId);
            }
            
            fetch(url, {
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('total-visitors').textContent = data.total_visitors || 0;
                document.getElementById('new-this-week').textContent = data.new_this_week || 0;
                document.getElementById('crm-added-today').textContent = data.crm_added_today || 0;
                document.getElementById('campaign-impressions').textContent = formatNumber(data.campaign_impressions || 0);
            })
            .catch(error => {
                console.error('Error loading analytics:', error);
            });
        }
        
        // Load visitors
        function loadVisitors() {
            const container = document.getElementById('visitors-table-container');
            container.innerHTML = '<div class="loading"><div class="spinner"></div>Loading visitors...</div>';
            
            const url = new URL('<?php echo rest_url('visitor-dashboard/v1/visitors'); ?>');
            if (currentClientId) {
                url.searchParams.set('client_id', currentClientId);
            }
            url.searchParams.set('status', showArchived ? 'deleted' : 'active');
            url.searchParams.set('per_page', '50');
            
            fetch(url, {
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                renderVisitorsTable(data.visitors || []);
            })
            .catch(error => {
                console.error('Error loading visitors:', error);
                container.innerHTML = '<div class="loading">Error loading visitors</div>';
            });
        }
        
        // Render visitors table
        function renderVisitorsTable(visitors) {
            const container = document.getElementById('visitors-table-container');
            
            if (visitors.length === 0) {
                container.innerHTML = '<div class="loading">No visitors found</div>';
                return;
            }
            
            let tableHTML = `
                <table class="visitors-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Last Seen</th>
                            <th>Page Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            visitors.forEach(visitor => {
                const lastSeen = visitor.last_seen_at ? new Date(visitor.last_seen_at).toLocaleDateString() : 'Unknown';
                const isArchived = visitor.status === 'deleted';
                const isCrmAdded = visitor.status === 'crm_added';
                
                tableHTML += `
                    <tr>
                        <td>
                            <div class="visitor-name">
                                <a href="${visitor.linkedin_url}" target="_blank" class="visitor-linkedin">
                                    ${visitor.full_name || 'Unknown'}
                                </a>
                            </div>
                        </td>
                        <td>${visitor.company_name || '-'}</td>
                        <td>${visitor.title || '-'}</td>
                        <td>${visitor.location || '-'}</td>
                        <td>${lastSeen}</td>
                        <td>${visitor.all_time_page_views}</td>
                        <td>
                            <div class="visitor-actions">
                                <button class="btn btn-secondary btn-sm" onclick="showVisitorDetails(${visitor.id})">
                                    👁️ View
                                </button>
                                ${!isArchived && !isCrmAdded ? `
                                    <button class="btn btn-success btn-sm" onclick="addToCrm(${visitor.id})">
                                        📤 CRM
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteVisitor(${visitor.id})">
                                        🗑️ Archive
                                    </button>
                                ` : ''}
                                ${isArchived ? `
                                    <button class="btn btn-success btn-sm" onclick="restoreVisitor(${visitor.id})">
                                        ↩️ Restore
                                    </button>
                                ` : ''}
                                ${isCrmAdded ? `
                                    <span style="color: #059669; font-weight: 500;">✓ In CRM</span>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tableHTML += '</tbody></table>';
            container.innerHTML = tableHTML;
        }
        
        // Show visitor details modal
        function showVisitorDetails(visitorId) {
            currentVisitorId = visitorId;
            
            fetch(`<?php echo rest_url('visitor-dashboard/v1/visitors/'); ?>${visitorId}`, {
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(response => response.json())
            .then(visitor => {
                renderVisitorDetails(visitor);
                document.getElementById('visitor-modal').classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error loading visitor details:', error);
            });
        }
        
        // Render visitor details
        function renderVisitorDetails(visitor) {
            const content = document.getElementById('visitor-details-content');
            const recentPages = visitor.recent_page_urls ? visitor.recent_page_urls.slice(0, 5) : [];
            
            content.innerHTML = `
                <div class="visitor-detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value">${visitor.full_name || 'Unknown'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Company:</span>
                        <span class="detail-value">${visitor.company_name || '-'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Title:</span>
                        <span class="detail-value">${visitor.title || '-'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Industry:</span>
                        <span class="detail-value">${visitor.industry || '-'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Company Size:</span>
                        <span class="detail-value">${visitor.estimated_employee_count || '-'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Revenue:</span>
                        <span class="detail-value">${visitor.estimate_revenue || '-'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value">${visitor.location || '-'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Page Views:</span>
                        <span class="detail-value">${visitor.all_time_page_views}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Recent Page Views:</span>
                        <span class="detail-value">${visitor.recent_page_count}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">First Visit:</span>
                        <span class="detail-value">${visitor.first_seen_at ? new Date(visitor.first_seen_at).toLocaleString() : 'Unknown'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Visit:</span>
                        <span class="detail-value">${visitor.last_seen_at ? new Date(visitor.last_seen_at).toLocaleString() : 'Unknown'}</span>
                    </div>
                    ${recentPages.length > 0 ? `
                        <div style="grid-column: 1 / -1; margin-top: 1rem;">
                            <h4 style="margin-bottom: 0.5rem; color: #374151;">Recent Pages:</h4>
                            <ul style="list-style: none; padding: 0;">
                                ${recentPages.map(url => `<li style="padding: 0.25rem 0; color: #3b82f6;"><a href="${url}" target="_blank">${url}</a></li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                </div>
            `;
            
            // Update modal buttons based on visitor status
            const crmBtn = document.getElementById('modal-crm-btn');
            const deleteBtn = document.getElementById('modal-delete-btn');
            
            if (visitor.status === 'crm_added') {
                crmBtn.style.display = 'none';
                deleteBtn.textContent = 'Archive';
            } else if (visitor.status === 'deleted') {
                crmBtn.style.display = 'none';
                deleteBtn.textContent = 'Restore';
                deleteBtn.className = 'btn btn-success';
                deleteBtn.onclick = () => restoreFromModal();
            } else {
                crmBtn.style.display = 'inline-flex';
                deleteBtn.textContent = 'Archive';
                deleteBtn.className = 'btn btn-danger';
                deleteBtn.onclick = () => deleteFromModal();
            }
        }
        
        // Close visitor modal
        function closeVisitorModal() {
            document.getElementById('visitor-modal').classList.add('hidden');
            currentVisitorId = null;
        }
        
        // Add visitor to CRM
        function addToCrm(visitorId) {
            if (!confirm('Add this visitor to CRM queue?')) return;
            
            fetch(`<?php echo rest_url('visitor-dashboard/v1/visitors/'); ?>${visitorId}/crm-add`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadVisitors();
                    loadAnalytics();
                } else {
                    alert('Error: ' + (data.message || 'Failed to add to CRM'));
                }
            })
            .catch(error => {
                console.error('Error adding to CRM:', error);
                alert('Error adding to CRM');
            });
        }
        
        // Delete visitor
        function deleteVisitor(visitorId) {
            if (!confirm('Archive this visitor? They will be moved to the archived section.')) return;
            
            fetch(`<?php echo rest_url('visitor-dashboard/v1/visitors/'); ?>${visitorId}/delete`, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadVisitors();
                    loadAnalytics();
                } else {
                    alert('Error: ' + (data.message || 'Failed to archive visitor'));
                }
            })
            .catch(error => {
                console.error('Error archiving visitor:', error);
                alert('Error archiving visitor');
            });
        }
        
        // Restore visitor
        function restoreVisitor(visitorId) {
            if (!confirm('Restore this visitor?')) return;
            
            fetch(`<?php echo rest_url('visitor-dashboard/v1/visitors/'); ?>${visitorId}/restore`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadVisitors();
                    loadAnalytics();
                } else {
                    alert('Error: ' + (data.message || 'Failed to restore visitor'));
                }
            })
            .catch(error => {
                console.error('Error restoring visitor:', error);
                alert('Error restoring visitor');
            });
        }
        
        // Modal action functions
        function addToCrmFromModal() {
            if (currentVisitorId) {
                addToCrm(currentVisitorId);
                closeVisitorModal();
            }
        }
        
        function deleteFromModal() {
            if (currentVisitorId) {
                deleteVisitor(currentVisitorId);
                closeVisitorModal();
            }
        }
        
        function restoreFromModal() {
            if (currentVisitorId) {
                restoreVisitor(currentVisitorId);
                closeVisitorModal();
            }
        }
        
        // Toggle archived visitors
        function toggleArchivedVisitors() {
            showArchived = !showArchived;
            const btn = event.target;
            btn.textContent = showArchived ? '👁️ Show Active' : '👁️ Show Archived';
            loadVisitors();
        }
        
        // Select client (admin only)
        function selectClient(clientId) {
            currentClientId = clientId;
            window.location.href = `?client_id=${clientId}`;
        }
        
        // Refresh data
        function refreshData() {
            loadAnalytics();
            loadVisitors();
        }
        
        // Export CRM data
        function exportCrmData() {
            if (!confirm('Export CRM data for today?')) return;
            
            const url = new URL('<?php echo rest_url('visitor-dashboard/v1/admin/crm-export'); ?>');
            if (currentClientId) {
                url.searchParams.set('client_id', currentClientId);
            }
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`CRM export completed! ${data.visitor_count} visitors exported.`);
                } else {
                    alert('Error: ' + (data.message || 'Export failed'));
                }
            })
            .catch(error => {
                console.error('Error exporting CRM data:', error);
                alert('Error exporting CRM data');
            });
        }
        
        // Utility functions
        function formatNumber(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }
        
        // Show/hide sections (for future navigation)
        function showSection(section) {
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Add active class to clicked nav link
            event.target.classList.add('active');
            
            // For now, all sections show the same content
            // This can be expanded for different views
        }
    </script>
</body>
</html>
