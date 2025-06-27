<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Visitor_Dashboard_Deactivator {

    public static function deactivate() {
        flush_rewrite_rules();
        wp_cache_flush();
    }
}

