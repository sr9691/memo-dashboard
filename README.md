# Visitor Dashboard WordPress Plugin

A comprehensive WordPress plugin for managing website visitor data with CRM integration capabilities. Built for MEMO Marketing Group to consolidate visitor tracking from RB2B and campaign performance from GroundTruth into a unified dashboard.

## 🌟 Features

- **Multi-Client Management** - Admin users can manage multiple clients with role-based access
- **Visitor Tracking** - Real-time visitor data from RB2B with detailed analytics
- **Campaign Performance** - GroundTruth campaign metrics and reporting
- **CRM Integration** - Automated daily CRM exports via email
- **User Management** - Role-based access control (Admin vs Client users)
- **Modern Interface** - Responsive dashboard with mobile support built with Montserrat font and MEMO brand colors
- **Data Security** - Comprehensive audit logging and secure data handling
- **API Integration** - RESTful API for Make.com webhook integration
- **Professional Architecture** - Clean, maintainable code following WordPress standards

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **SSL Certificate**: Recommended for production
- **Modern Browser**: Support for CSS Grid and Flexbox

## 🚀 Installation

### Automatic Installation (Recommended)
1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to **Plugins → Add New → Upload Plugin**
4. Select the ZIP file and click **"Install Now"**
5. Activate the plugin
6. Configure initial settings under **Dashboard → Visitor Dashboard**

### Manual Installation
1. Clone this repository:
   ```bash
   git clone https://github.com/sr9691/memo-dashboard.git
   ```
2. Copy the `visitor-dashboard` folder to your WordPress plugins directory:
   ```bash
   cp -r memo-dashboard/visitor-dashboard /path/to/wordpress/wp-content/plugins/
   ```
3. Activate the plugin through WordPress admin

## 🗄️ Database Structure

The plugin automatically creates the following tables on activation:

- **`dashdev_clients`** - Client information and account details
- **`dashdev_visitors`** - Visitor data from RB2B with engagement metrics
- **`dashdev_campaign_performance`** - GroundTruth campaign data and analytics
- **`dashdev_dashboard_users`** - User management and role assignments
- **`dashdev_audit_logs`** - Activity tracking and security monitoring
- **`dashdev_crm_exports`** - CRM export history and status tracking
- **`dashdev_import_logs`** - Data import tracking and error handling

## 👥 User Roles

Two main user roles are created automatically:

### Dashboard Admin
- Full access to all clients and system management
- User management capabilities
- System configuration and settings
- Audit log access
- CRM export management

### Dashboard Client
- Access only to assigned client data
- Visitor management for their clients
- CRM queue functionality
- Basic analytics and reporting

## 🏢 Default Clients

The following clients are automatically created based on GroundTruth data:

- **Office Evolution** (Account ID: 315957)
- **CleanSlate Technologies** (Account ID: 311669)
- **Vertical Immersive** (Account ID: 319648)
- **Urban Air - Plainfield** (Account ID: 312056)
- **Club Works** (Account ID: 313861)
- **Legends Pub** (Account ID: 313568)

## 🔌 API Integration

### Make.com Webhook Setup

1. Navigate to **Dashboard Settings** in WordPress admin
2. Copy the generated API key
3. Configure Make.com scenarios with these webhook URLs:

**RB2B Import:**
```
https://your-domain.com/wp-json/visitor-dashboard/v1/import/rb2b
```

**GroundTruth Import:**
```
https://your-domain.com/wp-json/visitor-dashboard/v1/import/groundtruth
```

### Available API Endpoints

#### Visitor Management
- `GET /wp-json/visitor-dashboard/v1/visitors` - Get all visitors
- `GET /wp-json/visitor-dashboard/v1/visitors/{id}` - Get specific visitor
- `POST /wp-json/visitor-dashboard/v1/visitors/{id}/crm-add` - Add visitor to CRM queue
- `DELETE /wp-json/visitor-dashboard/v1/visitors/{id}/delete` - Archive visitor
- `POST /wp-json/visitor-dashboard/v1/visitors/{id}/restore` - Restore archived visitor

#### Analytics
- `GET /wp-json/visitor-dashboard/v1/analytics/summary` - Get analytics summary
- `GET /wp-json/visitor-dashboard/v1/analytics/campaign-performance` - Campaign metrics

#### Administration
- `GET /wp-json/visitor-dashboard/v1/admin/clients` - Get all clients
- `GET /wp-json/visitor-dashboard/v1/admin/users` - Get all users
- `GET /wp-json/visitor-dashboard/v1/admin/crm-queue` - Get CRM queue
- `POST /wp-json/visitor-dashboard/v1/admin/crm-export` - Trigger CRM export

#### Data Import
- `POST /wp-json/visitor-dashboard/v1/import/rb2b` - Import RB2B data
- `POST /wp-json/visitor-dashboard/v1/import/groundtruth` - Import GroundTruth data

## 🎨 Design System

The plugin features a comprehensive design system with:

### Brand Colors
- **Primary**: #2c435d (MEMO Dark Blue)
- **Secondary**: #4294cc (MEMO Light Blue)
- **Gradients**: Smooth transitions between brand colors

### Typography
- **Font Family**: Montserrat (Google Fonts)
- **Weights**: 300, 400, 500, 600, 700

### CSS Architecture
- **Shared Variables**: Centralized design tokens in `assets/css/shared/variables.css`
- **Utilities**: Reusable utility classes in `assets/css/shared/utilities.css`
- **Accessibility**: Comprehensive accessibility features in `assets/css/shared/accessibility.css`
- **Admin Styles**: WordPress admin integration in `assets/css/admin/admin-dashboard.css`
- **Client Styles**: Frontend dashboard styling in `assets/css/public/client-dashboard.css`

## 📱 User Interface

### Admin Dashboard (`/wp-admin/admin.php?page=visitor-dashboard`)
- Multi-client selector sidebar
- Visitor management across all clients
- User management and role assignment
- CRM queue management
- System analytics and reporting
- Audit trail access

### Client Dashboard (`/dashboard/`)
- Single client view
- Visitor list management
- Add visitors to CRM queue
- Archive visitor data
- Campaign performance overview

## 🔄 Data Processing Workflow

### RB2B Data Processing
1. Make.com receives daily delta files
2. Webhook posts CSV data to import endpoint
3. System extracts GroundTruth ID from filename
4. Visitors are created/updated for assigned client
5. Import logs track processing results

### GroundTruth Data Processing
1. Make.com receives daily consolidated file
2. Webhook posts CSV data to import endpoint
3. System processes campaign performance data
4. Clients are updated/created as needed
5. Analytics are refreshed automatically

### CRM Export Process
1. Daily automation or manual trigger
2. System queries visitors marked for CRM
3. CSV file generated with all required fields
4. Email sent to configured recipients
5. Visitors marked as exported and archived

## 🔒 Security Features

### Data Protection
- Role-based access control with WordPress capabilities
- Client data isolation - users can only access assigned clients
- Input validation and sanitization on all endpoints
- SQL injection prevention with prepared statements
- HTTPS enforcement for all sensitive operations

### Audit Logging
- User actions tracked with timestamps
- IP address logging for security monitoring
- Data modification tracking for compliance
- Export activity logging for audit trails

### Privacy Compliance
- GDPR/CCPA ready with data retention policies
- Data export capabilities for compliance requests
- Automatic data cleanup based on campaign end dates
- Consent management integration ready

## 📁 File Structure

```
visitor-dashboard/
├── visitor-dashboard.php              # Main plugin file
├── uninstall.php                      # Cleanup on plugin deletion
├── readme.txt                         # WordPress plugin repository standard
├── README.md                          # This file
├── CHANGELOG.md                       # Version history
├── composer.json                      # PHP dependencies
│
├── includes/                          # Core functionality
│   ├── class-plugin-core.php         # Main plugin orchestrator
│   ├── class-loader.php              # Hook management system
│   ├── class-activator.php           # Plugin activation logic
│   ├── class-deactivator.php         # Plugin deactivation logic
│   ├── class-i18n.php                # Internationalization
│   ├── class-style-loader.php        # CSS/JS enqueue management
│   │
│   ├── database/                      # Database operations
│   │   ├── class-database-manager.php # Database management
│   │   └── class-schema.php          # Database schema management
│   │
│   ├── core/                          # Core business logic
│   │   ├── class-visitor-manager.php  # Visitor operations
│   │   ├── class-analytics.php        # Analytics calculations
│   │   ├── class-crm-exporter.php    # CRM export functionality
│   │   ├── class-data-importer.php   # Data import processing
│   │   └── class-user-roles.php      # User roles & permissions
│   │
│   ├── api/                           # API layer
│   │   ├── class-rest-controller.php  # REST API endpoints
│   │   └── endpoints/                 # Individual endpoint classes
│   │
│   └── utilities/                     # Helper classes
│       ├── class-security.php         # Security utilities
│       ├── class-validator.php        # Input validation
│       └── class-logger.php           # Logging functionality
│
├── admin/                             # WordPress admin area
│   ├── class-admin-core.php          # Admin area orchestrator
│   ├── views/                         # Admin view templates
│   │   ├── layout/                    # Layout templates
│   │   ├── pages/                     # Full page templates
│   │   └── partials/                  # Reusable components
│   └── assets/                        # Admin-specific assets
│       ├── css/                       # Admin stylesheets
│       └── js/                        # Admin JavaScript
│
├── public/                            # Public-facing functionality
│   ├── class-public-core.php         # Public area orchestrator
│   ├── views/                         # Client view templates
│   │   ├── layout/                    # Layout templates
│   │   └── pages/                     # Page templates
│   └── assets/                        # Public-specific assets
│
├── assets/                            # Shared static assets
│   ├── css/                          # Stylesheets
│   │   ├── shared/                   # Shared styles and variables
│   │   ├── admin/                    # Admin-specific styles
│   │   ├── public/                   # Public-facing styles
│   │   └── vendor/                   # Third-party CSS
│   ├── js/                           # JavaScript files
│   ├── images/                       # Images and icons
│   └── fonts/                        # Custom fonts
│
├── templates/                         # Template files
│   ├── emails/                       # Email templates
│   └── components/                   # Reusable template components
│
├── languages/                        # Internationalization
│   ├── visitor-dashboard.pot         # Translation template
│   └── visitor-dashboard-en_US.po    # English translations
│
└── deployment/                       # Deployment files
    ├── database/                     # Database schema and migrations
    ├── config/                       # Configuration files
    └── docs/                         # Documentation
```

## 🛠️ Development

### Local Development Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/sr9691/memo-dashboard.git
   cd memo-dashboard
   ```

2. **Setup local WordPress environment**

3. **Symlink plugin to WordPress:**
   ```bash
   ln -s /path/to/memo-dashboard/visitor-dashboard /path/to/wordpress/wp-content/plugins/
   ```

4. **Activate plugin and configure**

### Development Tools

The project includes development-friendly features:
- **Debug Mode**: Enable with `define('VISITOR_DASHBOARD_DEBUG', true);`
- **Error Logging**: Comprehensive logging system
- **Hot Reloading**: CSS/JS changes reflect immediately
- **Code Standards**: Follows WordPress Coding Standards

## 🧪 Testing

### Manual Testing Checklist
- [ ] Plugin activation/deactivation
- [ ] User role creation and permissions
- [ ] Visitor list loading and pagination
- [ ] Add to CRM functionality
- [ ] Archive/restore operations
- [ ] Analytics data display
- [ ] CRM export generation
- [ ] Data import via webhooks

### Sample Data
Sample data is available in `/deployment/sample-data.sql` for testing purposes.

## 🚨 Troubleshooting

### Common Issues

1. **Database tables not created**
   - Deactivate and reactivate plugin
   - Check WordPress database permissions
   - Review error logs

2. **API endpoints returning 404**
   - Flush WordPress permalinks in Settings → Permalinks
   - Check .htaccess configuration
   - Verify REST API is enabled

3. **Permission denied errors**
   - Check user roles and capabilities
   - Verify client assignments
   - Review audit logs

### Logs and Debugging

**Error Logs:**
- WordPress debug log: `/wp-content/debug.log`
- Import logs: Dashboard → Import Logs
- Audit logs: Dashboard → System → Audit Trail

**Debug Mode:**
```php
// Add to wp-config.php for debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('VISITOR_DASHBOARD_DEBUG', true);
```

## 📝 Changelog

### Version 1.0.0 (2025-06-27)
- **Added**: Initial release with complete plugin restructure
- **Added**: Professional WordPress plugin architecture
- **Added**: Comprehensive CSS organization with shared design system
- **Added**: Multi-client management with role-based access
- **Added**: Real-time visitor tracking from RB2B
- **Added**: Campaign performance analytics from GroundTruth
- **Added**: Automated CRM export functionality
- **Added**: RESTful API for webhook integration
- **Added**: Modern responsive interface with MEMO branding
- **Added**: Comprehensive security and audit logging
- **Added**: Full WordPress standards compliance
- **Improved**: Code organization and maintainability
- **Improved**: Performance optimization
- **Fixed**: All security vulnerabilities and best practices

## 📄 License

This project is proprietary software developed for MEMO Marketing Group. All rights reserved.

## 👥 Contributors

- **Lead Developer**: ANSA Solutions + Claude
- **Project Manager**: ANSA Solutions  
- **Client**: MEMO Marketing Group

## 🆘 Support

For technical support or feature requests:
- Create an issue in this repository
- Contact the development team at ANSA Solutions
- Review the troubleshooting section above

---

**Built with ❤️ for MEMO Marketing Group by ANSA Solutions**