<?php
/**
 * admin/oauth-config.php
 * -------------------------------------------------------------
 * File konstanta untuk JWT secret & kredensial OAuth (Google/GitHub).
 * Di-require otomatis oleh setiap endpoint auth SETELAH admin/config.php.
 *
 * Silakan edit nilai-nilai di bawah ini langsung, atau (lebih baik)
 * pindahkan definisi ini ke admin/config.php aslimu lalu hapus file ini —
 * kode akan tetap jalan karena semua require memakai `file_exists()` guard.
 *
 * Kenapa file ini ada?
 * - Sebelumnya Client ID/Secret Google & JWT secret di-hardcode
 *   langsung di dalam kode (api/auth/google-*.php, api/jwt.php).
 *   Itu tidak aman kalau repo ini pernah dipush ke GitHub publik.
 * - Sekarang semua kredensial dibaca dari konstanta di config.php,
 *   supaya gampang di-rotate dan tidak ikut ke version control.
 * -------------------------------------------------------------
 */

// ==== JWT ====
// Ganti dengan string acak panjang milikmu sendiri (jangan dipakai bersama).
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'd1g1st4ck_s3cr3t_k3y_2026_Aguphia!');
}

// ==== Google OAuth ====
// Ambil dari https://console.cloud.google.com/apis/credentials
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    // PENTING: secret ini sebelumnya ada di kode yang kamu upload ke saya.
    // Sangat disarankan REGENERATE secret ini di Google Cloud Console,
    // lalu taruh nilai barunya di sini (bukan di file yang ikut ke-commit).
    define('GOOGLE_CLIENT_SECRET', '');
}

// ==== GitHub OAuth ====
// Daftarkan OAuth App di https://github.com/settings/developers
// Authorization callback URL: https://domainmu.com/digistack/api/auth/github/callback
// Kosongkan dulu jika belum registrasi — sistem akan menampilkan pesan
// "GitHub login belum dikonfigurasi" alih-alih error fatal.
if (!defined('GITHUB_CLIENT_ID')) {
    define('GITHUB_CLIENT_ID', '');
}
if (!defined('GITHUB_CLIENT_SECRET')) {
    define('GITHUB_CLIENT_SECRET', '');
}
