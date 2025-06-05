<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// تنظیمات
if (isset($_POST['save_settings'])) {
    $mirror = isset($_POST['mirror_mode']) ? 1 : 0;
    $ban_duration = (int)$_POST['ban_duration'];
    $stmt = $pdo->prepare("UPDATE firewall_admin SET mirror_mode = ?, ban_duration = ?");
    $stmt->execute([$mirror, $ban_duration]);
}

// ارسال ایمیل (لینک mailto)
$support_mail_link = "mailto:1.0.metalorchid.0.1@gmail.com";

// بن و رفع بن
if (isset($_GET['ban_ip'])) {
    $ip = $_GET['ban_ip'];
    $banUntil = date('Y-m-d H:i:s', time() + 300);
    $stmt = $pdo->prepare("INSERT INTO firewall_bans (ip_address, banned_until) VALUES (?, ?) ON DUPLICATE KEY UPDATE banned_until = ?");
    $stmt->execute([$ip, $banUntil, $banUntil]);
}
if (isset($_GET['unban_ip'])) {
    $stmt = $pdo->prepare("DELETE FROM firewall_bans WHERE ip_address = ?");
    $stmt->execute([$_GET['unban_ip']]);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Firewall Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<h2>🛡️ Firewall Control Panel</h2>

<form method="post">
    <label><input type="checkbox" name="mirror_mode" <?= $pdo->query("SELECT mirror_mode FROM firewall_admin")->fetchColumn() ? 'checked' : '' ?>> Mirror Mode</label><br>
    <label>Ban Duration (seconds): <input type="number" name="ban_duration" value="<?= $pdo->query("SELECT ban_duration FROM firewall_admin")->fetchColumn() ?>"></label><br>
    <input type="submit" name="save_settings" value="Save Settings">
</form>

<p><a href="<?= $support_mail_link ?>">📧 Contact Support</a></p>

<hr>
<h3>📄 IP Logs</h3>
<table border="1">
<tr><th>IP</th><th>Time</th><th>User-Agent</th><th>Action</th></tr>
<?php
$logs = $pdo->query("SELECT * FROM firewall_logs ORDER BY request_time DESC LIMIT 50");
foreach ($logs as $row) {
    echo "<tr>
        <td>{$row['ip_address']}</td>
        <td>{$row['request_time']}</td>
        <td>{$row['user_agent']}</td>
        <td><a href='?ban_ip={$row['ip_address']}'>Ban</a></td>
    </tr>";
}
?>
</table>

<hr>
<h3>🚫 Banned IPs</h3>
<table border="1">
<tr><th>IP</th><th>Banned Until</th><th>Unban</th></tr>
<?php
$bans = $pdo->query("SELECT * FROM firewall_bans ORDER BY banned_until DESC");
foreach ($bans as $row) {
    echo "<tr>
        <td>{$row['ip_address']}</td>
        <td>{$row['banned_until']}</td>
        <td><a href='?unban_ip={$row['ip_address']}'>Unban</a></td>
    </tr>";
}
?>
</table>

<hr>
<h3>📊 Traffic (Last 10 Minutes)</h3>
<canvas id="trafficChart" width="600" height="200"></canvas>
<script>
const ctx = document.getElementById('trafficChart').getContext('2d');
const labels = <?= json_encode(array_map(function($i) {
    return date('H:i', strtotime("-$i minutes"));
}, range(9, 0))) ?>;
const data = <?= json_encode(array_map(function($i) use ($pdo) {
    $start = date('Y-m-d H:i:00', strtotime("-$i minutes"));
    $end = date('Y-m-d H:i:00', strtotime("-$i minutes +1 minute"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM firewall_logs WHERE request_time BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    return (int)$stmt->fetchColumn();
}, range(9, 0))) ?>;
new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Requests/minute',
            data: data,
            borderColor: 'blue',
            tension: 0.3,
            fill: false
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
    }
});
</script>

</body>
</html>
