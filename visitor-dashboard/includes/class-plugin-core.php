<?php
/**
 * The file that defines the core plugin class
 *
 * @package    Visitor_Dashboard
 * @subpackage Visitor_Dashboard/includes
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Visitor_Dashboard_Plugin_Core {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        if (defined('VISITOR_DASHBOARD_VERSION')) {
            $this->version = VISITOR_DASHBOARD_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'visitor-dashboard';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    private function load_dependencies() {
        // Core WordPress integration
        require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/class-loader.php';
        require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/class-i18n.php';

        // Style and script management
        require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/class-style-loader.php';

        // Database management
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/database/class-database-manager.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/database/class-database-manager.php';
        }

        // Core business logic
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-visitor-manager.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-visitor-manager.php';
        }
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-analytics.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-analytics.php';
        }
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-crm-exporter.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-crm-exporter.php';
        }
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-data-importer.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-data-importer.php';
        }
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-user-roles.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/core/class-user-roles.php';
        }

        // API management
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/api/class-rest-controller.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
        }

        // Admin functionality
        if (file_exists(VISITOR_DASHBOARD_PLUGIN_DIR . 'admin/class-admin-core.php')) {
            require_once VISITOR_DASHBOARD_PLUGIN_DIR . 'admin/class-admin-core.php';
        }

        $this->loader = new Visitor_Dashboard_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new Visitor_Dashboard_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        // Enqueue admin styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', 'visitor_dashboard_admin_styles');
    }

    private function define_public_hooks() {
        // Enqueue public styles and scripts
        $this->loader->add_action('wp_enqueue_scripts', 'visitor_dashboard_public_styles');
    }

    private function define_api_hooks() {
        // Initialize REST API endpoints
        if (class_exists('Visitor_Dashboard_REST_Controller')) {
            $this->loader->add_action('rest_api_init', 'Visitor_Dashboard_REST_Controller', 'init');
        }
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}

