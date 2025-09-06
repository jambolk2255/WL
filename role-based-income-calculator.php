<?php
/**
 * Plugin Name:       Role-Based Income Calculator
 * Plugin URI:        https://example.com/
 * Description:       Calculates income based on proof submissions with role-based course-linked percentages.
 * Version:           3.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       role-based-income-calculator
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define constants
define('RBIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RBIC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The main plugin class.
 */
final class Role_Based_Income_Calculator {

    /**
     * The single instance of the class.
     */
    private static $_instance = null;

    /**
     * Main instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        // Core Logic
        require_once RBIC_PLUGIN_DIR . 'includes/class-rbic-db.php';
        require_once RBIC_PLUGIN_DIR . 'includes/class-rbic-core-logic.php';
        require_once RBIC_PLUGIN_DIR . 'includes/class-rbic-log.php';
        require_once RBIC_PLUGIN_DIR . 'includes/class-rbic-email.php';

        // Admin functionality
        if (is_admin()) {
            require_once RBIC_PLUGIN_DIR . 'admin/class-rbic-admin-menus.php';
        }

        // Public functionality
        require_once RBIC_PLUGIN_DIR . 'public/class-rbic-shortcodes.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, ['Rbic_Db', 'create_tables']);

        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    /**
     * On plugins loaded.
     */
    public function on_plugins_loaded() {
        // Initialization
        new Rbic_Core_Logic();
        new Rbic_Shortcodes();
        if (is_admin()){
            new Rbic_Admin_Menus();
        }
    }
}

// Lets Go...
Role_Based_Income_Calculator::instance();
