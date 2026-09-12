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