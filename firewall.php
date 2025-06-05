<?php
require_once 'config.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$request_time = date('Y-m-d H:i:s');

// Check if IP is banned
$stmt = $pdo->prepare("SELECT * FROM firewall_bans WHERE ip_address = ? AND banned_until > NOW()");
$stmt->execute([$ip]);
if ($stmt->fetch()) {
    die('Access Denied - Banned.');
}

// Proxy/VPN detection
$isProxy = false;
$proxyHeaders = [
    'HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED_FOR',
    'HTTP_X_FORWARDED', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP'
];
foreach ($proxyHeaders as $header) {
    if (!empty($_SERVER[$header])) {
        $isProxy = true;
        break;
    }
}
if ($isProxy) {
    $banTime = time() + 300; // 5 min ban for proxy users
    $banUntil = date('Y-m-d H:i:s', $banTime);
    $stmt = $pdo->prepare("INSERT INTO firewall_bans (ip_address, banned_until) VALUES (?, ?) ON DUPLICATE KEY UPDATE banned_until = ?");
    $stmt->execute([$ip, $banUntil, $banUntil]);
    die('Access denied. Proxy/VPN detected.');
}

// Log request
$stmt = $pdo->prepare("INSERT INTO firewall_logs (ip_address, user_agent, request_uri, request_time) VALUES (?, ?, ?, ?)");
$stmt->execute([$ip, $user_agent, $request_uri, $request_time]);

// Fetch settings
$settings = $pdo->query("SELECT * FROM firewall_admin LIMIT 1")->fetch();

// Traffic check
$stmt = $pdo->prepare("SELECT COUNT(*) FROM firewall_logs WHERE request_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$stmt->execute();
$request_count = $stmt->fetchColumn();

if ($request_count > 1000) {
    readfile('index2.html');
    exit;
}

if ($settings['mirror_mode']) {
    header("Content-Type: text/plain");
    echo str_repeat("Mirrored packet\n", 20);
    exit;
}
?>
