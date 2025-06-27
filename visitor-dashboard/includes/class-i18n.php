<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Visitor_Dashboard_i18n {

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'visitor-dashboard',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}

