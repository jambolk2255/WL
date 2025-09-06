<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Rbic_Shortcodes {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        $shortcodes = [
            'income_summary',
            'kawaii_cat_income',
            'income_history_table',
            'monthly_income_history'
        ];

        foreach ($shortcodes as $shortcode) {
            add_shortcode($shortcode, [$this, $shortcode]);
        }
    }

    /**
     * Enqueue CSS and JS assets.
     */
    public function enqueue_assets() {
        // We should only enqueue these assets if the shortcode is present on the page.
        // For simplicity during this refactoring, I will enqueue them generally.
        // This can be optimized later.
        wp_enqueue_style('rbic-styles', RBIC_PLUGIN_URL . 'assets/css/rbic-styles.css');
        wp_enqueue_script('rbic-scripts', RBIC_PLUGIN_URL . 'assets/js/rbic-scripts.js', [], false, true);
    }

    /**
     * [income_summary] shortcode.
     */
    public function income_summary() {
        if (!is_user_logged_in()) return '';
        global $wpdb;
        $uid = get_current_user_id();

        $pending = $wpdb->get_var($wpdb->prepare("SELECT SUM(ih.income) FROM {$wpdb->prefix}income_history_pending ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected')", $uid)) ?: 0;
        $approved = $wpdb->get_var($wpdb->prepare("SELECT SUM(ih.income) FROM {$wpdb->prefix}income_history_approved ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected')", $uid)) ?: 0;

        ob_start(); ?>
        <div class="dark-income-card">
            <h3>Income Overview</h3>
            <div class="item"><div class="label">Pending Income</div><div class="value pending">$<?= number_format($pending, 2) ?></div></div>
            <div class="item"><div class="label">Approved Income</div><div class="value approved">$<?= number_format($approved, 2) ?></div></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [kawaii_cat_income] shortcode.
     */
    public function kawaii_cat_income() {
        if (!is_user_logged_in()) return '<p>Please log in to view your income summary.</p>';
        global $wpdb;
        $user_id = get_current_user_id();

        $pending = $wpdb->get_var($wpdb->prepare("SELECT SUM(ih.income) FROM {$wpdb->prefix}income_history_pending ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected')", $user_id)) ?: 0;
        $approved = $wpdb->get_var($wpdb->prepare("SELECT SUM(ih.income) FROM {$wpdb->prefix}income_history_approved ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected')", $user_id)) ?: 0;
        $total = $pending + $approved;

        ob_start(); ?>
        <div class="kawaii-cat-wrapper">
             <div class="coins-rain">
                 <div class="coin">🪙</div><div class="coin">💰</div><div class="coin">🪙</div><div class="coin">💰</div><div class="coin">🪙</div><div class="coin">💎</div>
             </div>

             <?php if ($total > 0): ?>
             <div class="cat-speech-bubble"><div class="speech-text">Meow! You've earned $<?php echo number_format($total, 2); ?>! 🐱</div></div>
             <?php else: ?>
             <div class="cat-speech-bubble"><div class="speech-text">Meow! Submit proof to start earning! 😸</div></div>
             <?php endif; ?>

             <div class="kawaii-income-cards">
                 <div class="kawaii-card"><div class="kawaii-card-content"><div class="kawaii-card-left"><div class="kawaii-card-emoji">⌛</div><div class="kawaii-card-text">Pending</div></div><div class="kawaii-card-amount pending">$<?php echo number_format($pending, 2); ?></div></div></div>
                 <div class="kawaii-card"><div class="kawaii-card-content"><div class="kawaii-card-left"><div class="kawaii-card-emoji">💎</div><div class="kawaii-card-text">Approved</div></div><div class="kawaii-card-amount approved">$<?php echo number_format($approved, 2); ?></div></div></div>
             </div>

             <?php if ($total == 0): ?>
             <div class="cat-no-income"><div class="sad-cat">😿</div><div class="no-income-text">No income yet, but I believe in you!</div><div class="no-income-subtitle">Submit your first proof to make this kitty happy! 🐾</div></div>
             <?php endif; ?>
         </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [income_history_table] shortcode.
     */
    public function income_history_table() {
        if (!is_user_logged_in()) return '';
        global $wpdb;
        $uid = get_current_user_id();

        $approved = $wpdb->get_results($wpdb->prepare("SELECT ih.* FROM {$wpdb->prefix}income_history_approved ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected') ORDER BY ih.recorded_at DESC", $uid));
        $pending = $wpdb->get_results($wpdb->prepare("SELECT ih.* FROM {$wpdb->prefix}income_history_pending ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected') ORDER BY ih.recorded_at DESC", $uid));

        ob_start(); ?>
        <div class="income-history-wrapper">
            <div class="income-tabs">
                <button class="active" onclick="showIncomeTab('approved_table')">Approved Income</button>
                <button onclick="showIncomeTab('pending_table')">Pending Income</button>
            </div>
            <div class="income-table-container">
                <div id="approved_table" class="income-tab-content">
                    <table class="income-table">
                        <thead><tr><th>Course</th><th>Description</th><th>Role</th><th>Income</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($approved as $row):
                            $role_name = isset(wp_roles()->roles[$row->role]['name']) ? wp_roles()->roles[$row->role]['name'] : $row->role;
                            $title = $wpdb->get_var($wpdb->prepare("SELECT title_id FROM {$wpdb->prefix}proof_submissions WHERE id = %d", $row->submission_id));
                            $description = $title ? get_the_title($title) : 'N/A';
                        ?>
                            <tr>
                                <td><?= get_the_title($row->course_id) ?></td>
                                <td><?= esc_html($description) ?></td>
                                <td><?= esc_html($role_name) ?></td>
                                <td>$<?= number_format($row->income, 2) ?></td>
                                <td><?= esc_html($row->recorded_at) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="pending_table" class="income-tab-content" style="display:none">
                    <table class="income-table">
                         <thead><tr><th>Course</th><th>Description</th><th>Role</th><th>Income</th><th>Date</th></tr></thead>
                         <tbody>
                         <?php foreach ($pending as $row):
                             $role_name = isset(wp_roles()->roles[$row->role]['name']) ? wp_roles()->roles[$row->role]['name'] : $row->role;
                             $title = $wpdb->get_var($wpdb->prepare("SELECT title_id FROM {$wpdb->prefix}proof_submissions WHERE id = %d", $row->submission_id));
                             $description = $title ? get_the_title($title) : 'N/A';
                         ?>
                             <tr>
                                 <td><?= get_the_title($row->course_id) ?></td>
                                 <td><?= esc_html($description) ?></td>
                                 <td><?= esc_html($role_name) ?></td>
                                 <td>$<?= number_format($row->income, 2) ?></td>
                                 <td><?= esc_html($row->recorded_at) ?></td>
                             </tr>
                         <?php endforeach; ?>
                         </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [monthly_income_history] shortcode.
     */
    public function monthly_income_history() {
        if (!is_user_logged_in()) return '<p>Please log in to view your income history.</p>';
        global $wpdb;
        $user_id = get_current_user_id();

        $approved = $wpdb->get_results($wpdb->prepare("SELECT ih.*, ps.title_id FROM {$wpdb->prefix}income_history_approved ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected') ORDER BY ih.recorded_at DESC", $user_id));
        $pending = $wpdb->get_results($wpdb->prepare("SELECT ih.*, ps.title_id FROM {$wpdb->prefix}income_history_pending ih INNER JOIN {$wpdb->prefix}proof_submissions ps ON ih.submission_id = ps.id WHERE ih.user_id = %d AND ps.status NOT IN ('Declined', 'Rejected') ORDER BY ih.recorded_at DESC", $user_id));

        $groupByMonth = function ($data) {
            $grouped = [];
            foreach ($data as $item) {
                $month = date('F Y', strtotime($item->recorded_at));
                if (!isset($grouped[$month])) $grouped[$month] = [];
                $grouped[$month][] = $item;
            }
            return $grouped;
        };

        $approvedByMonth = $groupByMonth($approved);
        $pendingByMonth = $groupByMonth($pending);

        ob_start();
        ?>
        <div class="monthly-income-wrapper">
            <div class="monthly-tabs">
                <button class="monthly-tab-btn active" onclick="showMonthlyTab('approved_monthly')">✅ Approved</button>
                <button class="monthly-tab-btn" onclick="showMonthlyTab('pending_monthly')">⏳ Pending</button>
            </div>

            <div id="approved_monthly" class="monthly-tab-content">
                <?php $this->render_monthly_view($approvedByMonth, 'approved'); ?>
            </div>
            <div id="pending_monthly" class="monthly-tab-content" style="display: none;">
                <?php $this->render_monthly_view($pendingByMonth, 'pending'); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper to render the monthly view to avoid code duplication.
     */
    private function render_monthly_view($dataByMonth, $type) {
        if (empty($dataByMonth)) {
            echo "<div class='no-income-message'><div class='no-income-icon'>📊</div><div class='no-income-text'>No {$type} income yet.</div></div>";
            return;
        }

        echo '<div class="monthly-groups">';
        foreach ($dataByMonth as $month => $items) {
            $monthTotal = array_sum(array_column($items, 'income'));
            ?>
            <div class="monthly-group">
                <div class="monthly-header" onclick="toggleMonthlyGroup(this)">
                    <div class="monthly-title"><?= esc_html($month) ?></div>
                    <div class="monthly-info">
                        <span class="monthly-count"><?= count($items) ?> entries</span>
                        <span class="monthly-total">$<?= number_format($monthTotal, 2) ?></span>
                        <span class="monthly-arrow">▼</span>
                    </div>
                </div>
                <div class="monthly-items">
                    <?php foreach ($items as $item):
                        $role_name = isset(wp_roles()->roles[$item->role]['name']) ? wp_roles()->roles[$item->role]['name'] : $item->role;
                        $title_name = $item->title_id ? get_the_title($item->title_id) : 'N/A';
                        $course_title = get_the_title($item->course_id);
                    ?>
                    <div class="monthly-item-row">
                        <div class="item-course-title"><?= esc_html($course_title) ?></div>
                        <div class="item-desc-and-meta">
                            <div class="item-description"><?= esc_html($title_name) ?></div>
                            <div class="item-meta">
                                <span class="item-role"><?= esc_html($role_name) ?></span> | <span class="item-date"><?= date('M j, Y', strtotime($item->recorded_at)) ?></span>
                            </div>
                        </div>
                        <div class="item-amount">$<?= number_format($item->income, 2) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
}
