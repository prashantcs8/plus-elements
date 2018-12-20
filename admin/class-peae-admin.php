<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class PEAE_Admin
 * 
 */
class PEAE_Admin {

    private static $ins = null;
    public $admin_path;
    public $admin_url;
    public $section_page = '';
    public $should_show_shortcodes = null;

    public function __construct() {
        $this->admin_path = PEAE_PLUGIN_DIR . '/admin';
        $this->admin_url = PEAE_PLUGIN_URL . '/admin';

        /**
         * Admin enqueue scripts
         */
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_assets'), 99);
    }

    public static function get_instance() {
        if (null == self::$ins) {
            self::$ins = new self;
        }

        return self::$ins;
    }

    public function admin_enqueue_assets() {
        /**
         * Including One Click Upsell assets on all OCU pages.
         */
        wp_enqueue_style('peae-admin', $this->admin_url . '/assets/css/peae-admin.css', array(), PEAE_VERSION_DEV);
        wp_enqueue_script('peae-admin', $this->admin_url . '/assets/js/peae-admin.js', array(), PEAE_VERSION_DEV);
        $data = array(
            'ajax_nonce' => wp_create_nonce('peaeaction-admin'),
            'plugin_url' => plugin_dir_url(PEAE_PLUGIN_FILE),
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'ajax_chosen' => wp_create_nonce('json-search'),
            'search_products_nonce' => wp_create_nonce('search-products'),
            'integrations_pg' => admin_url('admin.php?page=deadlinecoupon&tab=integrations'),
        );
        wp_localize_script('peae-admin', 'peaeParams', $data);
    }

}

if (class_exists('PEAE_Core')) {
    PEAE_Core::register('admin', 'PEAE_Admin');
}