<?php
/**
 * admin/oauth-config.php
 * -------------------------------------------------------------
 * Membaca kredensial sensitive (JWT Secret, Google & GitHub OAuth)
 * dari tabel database `app_settings` agar tidak di-hardcode di dalam file.
 * -------------------------------------------------------------
 */

// Pastikan koneksi PDO ($pdo) sudah tersedia dari admin/config.php
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Define JWT Secret
        if (!defined('JWT_SECRET')) {
            define('JWT_SECRET', $settings['jwt_secret'] ?? 'fallback_default_secret_key_change_in_db');
        }

        // Define Google OAuth
        if (!defined('GOOGLE_CLIENT_ID')) {
            define('GOOGLE_CLIENT_ID', $settings['google_client_id'] ?? '');
        }
        if (!defined('GOOGLE_CLIENT_SECRET')) {
            define('GOOGLE_CLIENT_SECRET', $settings['google_client_secret'] ?? '');
        }

        // Define GitHub OAuth
        if (!defined('GITHUB_CLIENT_ID')) {
            define('GITHUB_CLIENT_ID', $settings['github_client_id'] ?? '');
        }
        if (!defined('GITHUB_CLIENT_SECRET')) {
            define('GITHUB_CLIENT_SECRET', $settings['github_client_secret'] ?? '');
        }
    } catch (PDOException $e) {
        // Fallback jika terjadi error koneksi / tabel belum terbuat
        error_log("Failed to load oauth settings from DB: " . $e->getMessage());
    }
}

// Fallback Guard (Menjaga agar konstanta tetap terdefinisi jika query gagal)
if (!defined('JWT_SECRET'))          define('JWT_SECRET', '');
if (!defined('GOOGLE_CLIENT_ID'))      define('GOOGLE_CLIENT_ID', '');
if (!defined('GOOGLE_CLIENT_SECRET'))  define('GOOGLE_CLIENT_SECRET', '');
if (!defined('GITHUB_CLIENT_ID'))      define('GITHUB_CLIENT_ID', '');
if (!defined('GITHUB_CLIENT_SECRET'))  define('GITHUB_CLIENT_SECRET', '');