<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Rbic_Admin_Menus {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus']);
    }

    /**
     * Register all the admin menus for the plugin.
     */
    public function register_menus() {
        // Main Menu Page
        add_menu_page(
            'Income Split Settings',
            'Income Splits',
            'manage_options',
            'rbic_split_management',
            [$this, 'render_split_management_page'],
            'dashicons-chart-pie',
            61
        );

        // Submenu for Global Assignments
        add_submenu_page(
            'rbic_split_management',
            'Global Assignments',
            'Global Assignments',
            'manage_options',
            'rbic_global_assignments',
            [$this, 'render_global_assignments_page']
        );

        // Submenu for Audit Log
        add_submenu_page(
            'rbic_split_management',
            'Audit Log',
            'Audit Log',
            'manage_options',
            'rbic_audit_log',
            [$this, 'render_audit_log_page']
        );

        // The old 'Course Teachers' submenu is now obsolete and has been removed.
    }

    /**
     * Renders the main split management page.
     * The actual content is in a separate view file.
     */
    public function render_split_management_page() {
        // The logic from the original `income_split_admin_page` will be moved here
        // and the view part will be in the required file.
        require_once RBIC_PLUGIN_DIR . 'admin/views/view-split-management.php';
    }

    /**
     * Renders the global assignments page.
     */
    public function render_global_assignments_page() {
        require_once RBIC_PLUGIN_DIR . 'admin/views/view-global-assignments.php';
    }

    /**
     * Renders the audit log page.
     */
    public function render_audit_log_page() {
        require_once RBIC_PLUGIN_DIR . 'admin/views/view-audit-log.php';
    }
}
