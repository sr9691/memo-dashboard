# Installation Guide

## Quick Start

1. **Download Plugin**
   - Clone repository or download ZIP from GitHub
   - Extract to WordPress plugins directory

2. **Upload to WordPress**
   - Copy `visitor-dashboard/` folder to `/wp-content/plugins/`
   - Or upload ZIP via WordPress admin

3. **Activate Plugin**
   - Go to WordPress admin -> Plugins
   - Find "Visitor Management Dashboard"
   - Click "Activate"

4. **Configure Settings**
   - Go to WordPress admin -> Visitors
   - Configure client settings and user roles
   - Set up CRM export email addresses

## Detailed Setup

### System Requirements
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+

### Database Setup
The plugin automatically creates required tables on activation.

### User Configuration
1. Create admin users with "Dashboard Admin" role
2. Create client users and assign to specific clients
3. Configure permissions as needed

### API Integration
Configure Make.com webhooks with the provided API endpoints.

## Troubleshooting

### Common Issues
- **Plugin won't activate**: Check PHP version and WordPress compatibility
- **Database errors**: Verify MySQL permissions
- **API errors**: Check webhook authentication

For more help, see the main README.md file.
