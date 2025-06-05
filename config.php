<?php
// ⚙️ Configuration (قابل ویرایش)
// EN: Configuration file for DB and admin credentials
// FA: فایل تنظیمات پایگاه داده و ورود ادمین
// RU: Конфигурационный файл для БД и входа администратора

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', '123456789'); // توصیه: در محیط واقعی از password_hash استفاده کنید

$dsn = 'mysql:host=localhost;dbname=firewall;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>