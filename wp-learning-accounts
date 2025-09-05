<?php
/**
 * Plugin Name: WP Learning Accounts
 * Plugin URI: https://yoursite.com/
 * Description: Enhanced account assignment and tracking system with account types and split percentages
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wp-learning-accounts
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WLA_VERSION', '2.0.0');
define('WLA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WLA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WLA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class WP_Learning_Accounts {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_wla_assign_account', array($this, 'ajax_assign_account'));
        add_action('wp_ajax_wla_reassign_account', array($this, 'ajax_reassign_account'));
        add_action('wp_ajax_wla_update_split', array('WLA_Admin', 'ajax_update_split'));
        add_action('wp_ajax_wla_save_custom_fields', array('WLA_Admin', 'ajax_save_custom_fields'));
        
        // Shortcodes
        add_shortcode('wla_student_dashboard', array($this, 'student_dashboard_shortcode'));
    }
    
    private function load_dependencies() {
        require_once WLA_PLUGIN_DIR . 'includes/class-wla-database.php';
        require_once WLA_PLUGIN_DIR . 'includes/class-wla-admin.php';
        require_once WLA_PLUGIN_DIR . 'includes/class-wla-notifications.php';
        require_once WLA_PLUGIN_DIR . 'includes/class-wla-student.php';
    }
    
    public function activate() {
        WLA_Database::create_tables();
        
        // Run database upgrades for existing installations
        $this->upgrade_database();
        
        flush_rewrite_rules();
    }
    
    private function upgrade_database() {
        global $wpdb;
        
        // Check if we need to add new columns to existing tables
        $table_accounts = $wpdb->prefix . 'wl_accounts';
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                   WHERE table_name = '$table_accounts' AND column_name = 'account_type'");
        
        if (empty($row)) {
            // Add new columns if they don't exist
            $wpdb->query("ALTER TABLE $table_accounts 
                         ADD COLUMN account_type ENUM('individual', 'public') DEFAULT 'individual',
                         ADD COLUMN first_owner_id INT(11) DEFAULT NULL,
                         ADD COLUMN split_first_owner DECIMAL(5,2) DEFAULT 0,
                         ADD COLUMN split_current_owner DECIMAL(5,2) DEFAULT 0,
                         ADD COLUMN splits_configured BOOLEAN DEFAULT FALSE,
                         ADD COLUMN custom_field_1 VARCHAR(255),
                         ADD COLUMN custom_field_2 VARCHAR(255),
                         ADD COLUMN custom_field_3 TEXT,
                         ADD COLUMN custom_field_4 TEXT,
                         ADD COLUMN custom_field_5 TEXT");
        }
        
        // Check if splits_configured column exists (for upgrades from earlier version)
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                   WHERE table_name = '$table_accounts' AND column_name = 'splits_configured'");
        
        if (empty($row)) {
            $wpdb->query("ALTER TABLE $table_accounts ADD COLUMN splits_configured BOOLEAN DEFAULT FALSE");
            
            // Set splits_configured to true for existing public accounts with non-zero splits
            $wpdb->query("UPDATE $table_accounts 
                         SET splits_configured = 1 
                         WHERE account_type = 'public' 
                         AND (split_first_owner > 0 OR split_current_owner > 0)");
        }
        
        // Add assignment_count to assignments table if not exists
        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                   WHERE table_name = '$table_assignments' AND column_name = 'assignment_count'");
        
        if (empty($row)) {
            $wpdb->query("ALTER TABLE $table_assignments ADD COLUMN assignment_count INT(11) DEFAULT 0");
        }
        
        // Add account_type_changed to logs table if not exists
        $table_logs = $wpdb->prefix . 'wl_assignment_logs';
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                   WHERE table_name = '$table_logs' AND column_name = 'account_type_changed'");
        
        if (empty($row)) {
            $wpdb->query("ALTER TABLE $table_logs ADD COLUMN account_type_changed BOOLEAN DEFAULT FALSE");
        }
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function init() {
        // Initialize components
        WLA_Admin::get_instance();
        WLA_Notifications::get_instance();
        WLA_Student::get_instance();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Learning Accounts', 'wp-learning-accounts'),
            __('Learning Accounts', 'wp-learning-accounts'),
            'manage_options',
            'wla-accounts',
            array('WLA_Admin', 'render_accounts_page'),
            'dashicons-id-alt',
            30
        );
        
        add_submenu_page(
            'wla-accounts',
            __('All Accounts', 'wp-learning-accounts'),
            __('All Accounts', 'wp-learning-accounts'),
            'manage_options',
            'wla-accounts',
            array('WLA_Admin', 'render_accounts_page')
        );
        
        add_submenu_page(
            'wla-accounts',
            __('Add Account', 'wp-learning-accounts'),
            __('Add Account', 'wp-learning-accounts'),
            'manage_options',
            'wla-add-account',
            array('WLA_Admin', 'render_add_account_page')
        );
        
        add_submenu_page(
            'wla-accounts',
            __('Assignments', 'wp-learning-accounts'),
            __('Assignments', 'wp-learning-accounts'),
            'manage_options',
            'wla-assignments',
            array('WLA_Admin', 'render_assignments_page')
        );
        
        add_submenu_page(
            'wla-accounts',
            __('Assignment Logs', 'wp-learning-accounts'),
            __('Assignment Logs', 'wp-learning-accounts'),
            'manage_options',
            'wla-logs',
            array('WLA_Admin', 'render_logs_page')
        );
        
        add_submenu_page(
            'wla-accounts',
            __('Settings', 'wp-learning-accounts'),
            __('Settings', 'wp-learning-accounts'),
            'manage_options',
            'wla-settings',
            array('WLA_Admin', 'render_settings_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wla-') !== false) {
            wp_enqueue_style('wla-admin', WLA_PLUGIN_URL . 'assets/css/admin.css', array(), WLA_VERSION);
            wp_enqueue_script('wla-admin', WLA_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WLA_VERSION, true);
            wp_localize_script('wla-admin', 'wla_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wla_ajax_nonce'),
                'admin_url' => admin_url()
            ));
        }
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('wla-frontend', WLA_PLUGIN_URL . 'assets/css/frontend.css', array(), WLA_VERSION);
        wp_enqueue_script('wla-frontend', WLA_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), WLA_VERSION, true);
        wp_localize_script('wla-frontend', 'wla_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wla_ajax_nonce')
        ));
    }
    
    public function student_dashboard_shortcode($atts) {
        return WLA_Student::render_dashboard();
    }
    
    public function ajax_assign_account() {
        check_ajax_referer('wla_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $account_id = intval($_POST['account_id']);
        $user_id = intval($_POST['user_id']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        $result = WLA_Database::assign_account($account_id, $user_id, $notes);
        
        if ($result) {
            WLA_Notifications::send_assignment_notification($user_id, $account_id, 'assigned');
            wp_send_json_success('Account assigned successfully');
        } else {
            wp_send_json_error('Failed to assign account');
        }
    }
    
    public function ajax_reassign_account() {
        check_ajax_referer('wla_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $account_id = intval($_POST['account_id']);
        $new_user_id = intval($_POST['new_user_id']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Get the current assignment to find old user
        global $wpdb;
        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assignments WHERE account_id = %d AND status = 'active'",
            $account_id
        ));
        
        $old_user_id = $current ? $current->user_id : null;
        
        // Perform reassignment (this will handle all notifications internally)
        $result = WLA_Database::reassign_account($account_id, $new_user_id, $notes);
        
        if ($result) {
            wp_send_json_success('Account reassigned successfully');
        } else {
            wp_send_json_error('Failed to reassign account');
        }
    }
}

// Initialize the plugin
WP_Learning_Accounts::get_instance();
