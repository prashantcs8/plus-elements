<?php
/**
 * Plugin Name: Plus Elements: Addons for Elementor
 * Plugin URI: https://codetutspan.com
 * Description: An Elegant, Modern and fully customizable collection of addons for Elementor Page Builder
 * Version: 0.1.0
 * Author: WooFunnels
 * Author URI: https://codetutspan.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: plus-elements
 *
 * Requires at least: 4.2.1
 * Tested up to: 5.0
 *
 * Plus Elements: Addons for Elementor is free software.
 * You can redistribute it and/or modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Plus Elements: Addons for Elementor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Plus Elements: Addons for Elementor. If not, see <http://www.gnu.org/licenses/>.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PEAE_Core {

    /**
     * @var PEAE_Core
     */
    public static $_instance = null;
    private static $_registered_entity = array(
        'active' => array(),
        'inactive' => array(),
    );

    /**
     * @var bool Dependency check property
     */
    private $is_dependency_exists = true;

    /**
     * Minimum Elementor Version
     *
     * @since 1.0.0
     *
     * @var string Minimum Elementor version required to run the plugin.
     */
    const MINIMUM_ELEMENTOR_VERSION = '2.0.0';

    /**
     * @var PEAE_Admin
     */
    public $admin;

    /**
     * @var PEAE_Public
     */
    public $public;

    /**
     * @var PEAE_Logger
     */
    public $log;

    /**
     * @var PEAE_WooFunnels_Support
     */
    public $support;

    public function __construct() {

        /**
         * Load important variables and constants
         */
        $this->define_plugin_properties();

        /**
         * Load dependency classes
         */
        $this->load_dependencies_support();

        /**
         * Initiates and loads WooFunnels start file
         */
        if (true === $this->is_dependency_exists) {
            /**
             * Loads common file
             */
            $this->load_commons();
        }
    }

    /**
     * Defining constants
     */
    public function define_plugin_properties() {
        define('PEAE_VERSION', '0.1.0');
        define('PEAE_MIN_WC_VERSION', '3.0');
        define('PEAE_SLUG', 'peae');
        define('PEAE_FULL_NAME', 'WooFunnels: Deadline Coupons');
        define('PEAE_PLUGIN_FILE', __FILE__);
        define('PEAE_PLUGIN_DIR', __DIR__);
        define('PEAE_PLUGIN_URL', untrailingslashit(plugin_dir_url(PEAE_PLUGIN_FILE)));
        define('PEAE_PLUGIN_BASENAME', plugin_basename(__FILE__));
        define('PEAE_IS_DEV', true);
        define('PEAE_DB_VERSION', '1.0');

        ( true === PEAE_IS_DEV ) ? define('PEAE_VERSION_DEV', time()) : define('PEAE_VERSION_DEV', PEAE_VERSION);
    }

    public function load_dependencies_support() {
        /* Check if Elementor installed and activated */
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_main_plugin']);
            return;
        }

        /* Check for required Elementor version */
        if (!version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_elementor_version']);
            return;
        }
    }

    public function load_commons() {
        require PEAE_PLUGIN_DIR . '/includes/class-peae-common.php';

        PEAE_Common::init();

        /**
         * Loads common hooks
         */
        $this->load_hooks();
    }

    public function load_hooks() {
        /**
         * Initialize Localization
         */
        add_action('init', array($this, 'localization'));
        add_action('plugins_loaded', array($this, 'load_classes'), 1);
        add_action('plugins_loaded', array($this, 'register_classes'), 1);
        /** Redirecting Plugin to the settings page after activation */
        add_action('activated_plugin', array($this, 'redirect_on_activation'));
    }

    public function load_classes() {

        /**
         * Loads all the admin
         */
        $this->load_admin();

        /**
         * Loads core classes
         */
        require PEAE_PLUGIN_DIR . '/includes/class-peae-ajax-controller.php';
    }

    public function load_admin() {
        require PEAE_PLUGIN_DIR . '/admin/class-peae-admin.php';
    }

    public static function get_instance() {
        if (null == self::$_instance) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

//    /public function load_woofunnels_core_classes() {
//
//        /** Setting Up WooFunnels Core */
//        require_once( 'start.php' );
//    }

    public function localization() {
        load_plugin_textdomain('plus-elements', false, plugin_basename(dirname(__FILE__)) . '/languages');
    }

    /**
     * Added redirection on plugin activation
     *
     * @param $plugin
     */
    public function redirect_on_activation($plugin) {
        if (peae_is_woocommerce_active() && class_exists('WooCommerce')) {
            if ($plugin == plugin_basename(__FILE__)) {
                wp_redirect(add_query_arg(array(
                    'page' => 'deadlinecoupon',
                                ), admin_url('admin.php')));
                exit;
            }
        }
    }

    public function register_classes() {
        $load_classes = self::get_registered_class();

        if (is_array($load_classes) && count($load_classes) > 0) {
            foreach ($load_classes as $access_key => $class) {
                $this->$access_key = $class::get_instance();
            }
            do_action('peae_loaded');
        }
    }

    public static function get_registered_class() {
        return self::$_registered_entity['active'];
    }

    public static function register($short_name, $class, $overrides = null) {
        //Ignore classes that have been marked as inactive
        if (in_array($class, self::$_registered_entity['inactive'])) {
            return;
        }
        //Mark classes as active. Override existing active classes if they are supposed to be overridden
        $index = array_search($overrides, self::$_registered_entity['active']);
        if (false !== $index) {
            self::$_registered_entity['active'][$index] = $class;
        } else {
            self::$_registered_entity['active'][$short_name] = $class;
        }

        //Mark overridden classes as inactive.
        if (!empty($overrides)) {
            self::$_registered_entity['inactive'][] = $overrides;
        }
    }

    public function wc_version_check_notice() {
        ?>
        <div class="error">
            <p>
        <?php
        /* translators: %1$s: Min required woocommerce version */
        printf(esc_html__('<strong> Attention: </strong>DeadlineCoupon requires WooCommerce version %1$s or greater. Kindly update the WooCommerce plugin.', 'plus-elements'), PEAE_MIN_WC_VERSION);
        ?>
            </p>
        </div>
                <?php
            }

            public function wc_not_installed_notice() {
                ?>
        <div class="error">
            <p>
        <?php
        echo esc_html__('<strong> Attention: </strong>WooCommerce is not installed or activated. DeadlineCoupon is a WooCommerce Extension and would only work if WooCommerce is activated. Please install the WooCommerce Plugin first.', 'plus-elements');
        ?>
            </p>
        </div>
                <?php
            }

            /**
             * Admin notice
             *
             * Warning when the site doesn't have Elementor installed or activated.
             *
             * @since 1.0.0
             *
             * @access public
             */
            public function admin_notice_missing_main_plugin() {

                if (isset($_GET['activate']))
                    unset($_GET['activate']);

                $message = sprintf(
                        /* translators: 1: Plugin name 2: Elementor */
                        esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'plus-elements'), '<strong>' . esc_html__('Elementor Test Extension', 'plus-elements') . '</strong>', '<strong>' . esc_html__('Elementor', 'plus-elements') . '</strong>'
                );

                printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
            }

            /**
             * Admin notice
             *
             * Warning when the site doesn't have a minimum required Elementor version.
             *
             * @since 1.0.0
             *
             * @access public
             */
            public function admin_notice_minimum_elementor_version() {

                if (isset($_GET['activate']))
                    unset($_GET['activate']);

                $message = sprintf(
                        /* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
                        esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'elementor-test-extension'), '<strong>' . esc_html__('Elementor Test Extension', 'elementor-test-extension') . '</strong>', '<strong>' . esc_html__('Elementor', 'elementor-test-extension') . '</strong>', self::MINIMUM_ELEMENTOR_VERSION
                );

                printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
            }

        }

        if (!function_exists('PEAE_Core')) {

            /**
             * Global Common function to load all the classes
             * @return PEAE_Core
             */
            function PEAE_Core() {  //@codingStandardsIgnoreLine
                return PEAE_Core::get_instance();
            }

        }

        $GLOBALS['PEAE_Core'] = PEAE_Core();
        