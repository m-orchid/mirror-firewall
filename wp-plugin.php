<?php
/**
 * Plugin Name: Simple PHP Firewall
 * Description: Lightweight PHP Firewall Plugin with admin interface, mirror mode, and ban control.
 * Version: 1.0
 * Author: Custom
 */

// 🔐 Define constants
define('SFW_ADMIN_USER', 'admin');
define('SFW_ADMIN_PASS', '123456789');

global $wpdb;
$table_logs = $wpdb->prefix . 'sfw_logs';
$table_bans = $wpdb->prefix . 'sfw_bans';
$table_settings = $wpdb->prefix . 'sfw_settings';

// 🚧 Create DB tables on activation
register_activation_hook(__FILE__, function () use ($table_logs, $table_bans, $table_settings, $wpdb) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE IF NOT EXISTS $table_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        user_agent TEXT,
        request_uri TEXT,
        request_time DATETIME
    )");

    dbDelta("CREATE TABLE IF NOT EXISTS $table_bans (
        ip_address VARCHAR(45) PRIMARY KEY,
        banned_until DATETIME
    )");

    dbDelta("CREATE TABLE IF NOT EXISTS $table_settings (
        id INT PRIMARY KEY,
        mirror_mode BOOLEAN DEFAULT 0,
        ban_duration INT DEFAULT 300
    )");

    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_settings WHERE id = 1");
    if (!$exists) {
        $wpdb->insert($table_settings, [ 'id' => 1, 'mirror_mode' => 0, 'ban_duration' => 300 ]);
    }
});

// 🛡️ Run firewall on init
add_action('init', function () use ($wpdb, $table_logs, $table_bans, $table_settings) {
    if (is_admin()) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $now = current_time('mysql');

    $ban = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bans WHERE ip_address = %s AND banned_until > NOW()", $ip));
    if ($ban) {
        wp_die('Access Denied - Banned');
    }

    $proxyHeaders = ['HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP'];
    foreach ($proxyHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $until = date('Y-m-d H:i:s', time() + 300);
            $wpdb->replace($table_bans, ['ip_address' => $ip, 'banned_until' => $until]);
            wp_die('Access Denied - Proxy detected');
        }
    }

    $wpdb->insert($table_logs, [
        'ip_address' => $ip,
        'user_agent' => $ua,
        'request_uri' => $uri,
        'request_time' => $now
    ]);

    $settings = $wpdb->get_row("SELECT * FROM $table_settings WHERE id = 1");
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_logs WHERE request_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");

    if ($count > 1000) {
        echo file_get_contents(plugin_dir_path(__FILE__) . 'index2.html');
        exit;
    }

    if ($settings && $settings->mirror_mode) {
        wp_die(str_repeat("Mirrored packet\n", 20));
    }
});

// ⚙️ Admin Menu
add_action('admin_menu', function () {
    add_menu_page('Simple Firewall', 'Firewall', 'manage_options', 'simple-firewall', 'sfw_admin_page');
});

function sfw_admin_page() {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'sfw_logs';
    $table_bans = $wpdb->prefix . 'sfw_bans';
    $table_settings = $wpdb->prefix . 'sfw_settings';

    if (isset($_POST['save'])) {
        $mirror = isset($_POST['mirror_mode']) ? 1 : 0;
        $duration = intval($_POST['ban_duration']);
        $wpdb->update($table_settings, [ 'mirror_mode' => $mirror, 'ban_duration' => $duration ], ['id' => 1]);
    }

    if (isset($_GET['ban_ip'])) {
        $ip = sanitize_text_field($_GET['ban_ip']);
        $until = date('Y-m-d H:i:s', time() + 300);
        $wpdb->replace($table_bans, [ 'ip_address' => $ip, 'banned_until' => $until ]);
    }
    if (isset($_GET['unban_ip'])) {
        $ip = sanitize_text_field($_GET['unban_ip']);
        $wpdb->delete($table_bans, ['ip_address' => $ip]);
    }

    $settings = $wpdb->get_row("SELECT * FROM $table_settings WHERE id = 1");
    $logs = $wpdb->get_results("SELECT * FROM $table_logs ORDER BY request_time DESC LIMIT 30");
    $bans = $wpdb->get_results("SELECT * FROM $table_bans");

    echo '<h2>🛡️ Firewall Settings</h2>';
    echo '<form method="post">';
    echo '<label><input type="checkbox" name="mirror_mode" ' . ($settings->mirror_mode ? 'checked' : '') . '> Mirror Mode</label><br>';
    echo '<label>Ban Duration: <input type="number" name="ban_duration" value="' . esc_attr($settings->ban_duration) . '"></label><br>';
    echo '<input type="submit" name="save" value="Save"></form>';
    echo '<p><a href="mailto:1.0.metalorchid.0.1@gmail.com">📧 Contact Support</a></p>';

    echo '<h3>Logs</h3><table><tr><th>IP</th><th>Time</th><th>UA</th><th>Action</th></tr>';
    foreach ($logs as $log) {
        echo "<tr><td>{$log->ip_address}</td><td>{$log->request_time}</td><td>{$log->user_agent}</td><td><a href='?page=simple-firewall&ban_ip={$log->ip_address}'>Ban</a></td></tr>";
    }
    echo '</table>';

    echo '<h3>Banned IPs</h3><table><tr><th>IP</th><th>Until</th><th>Action</th></tr>';
    foreach ($bans as $ban) {
        echo "<tr><td>{$ban->ip_address}</td><td>{$ban->banned_until}</td><td><a href='?page=simple-firewall&unban_ip={$ban->ip_address}'>Unban</a></td></tr>";
    }
    echo '</table>';
}
