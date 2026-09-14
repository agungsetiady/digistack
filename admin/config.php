<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'digistack';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// Helper URL Dinamis
function admin_url($path = '') {
    // Mengambil direktori tempat file config.php ini berada relatif terhadap SCRIPT_NAME
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $dir = str_replace('\\', '/', __DIR__);
    $basePath = str_replace($docRoot, '', $dir);
    
    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
}

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);              // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE', 'tls');          // 'tls' atau 'ssl'
define('SMTP_USER', 'aguphia@gmail.com');
define('SMTP_PASS', 'app-password');   // Gmail: WAJIB App Password, bukan password akun biasa
define('SMTP_FROM_EMAIL', 'aguphia@gmail.com');
define('SMTP_FROM_NAME', 'DigiStack');