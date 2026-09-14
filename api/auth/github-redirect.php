<?php
// api/auth/github-redirect.php
require_once '../../admin/config.php';
if (file_exists(__DIR__ . '/../../admin/oauth-config.php')) {
    require_once '../../admin/oauth-config.php';
}

$client_id = defined('GITHUB_CLIENT_ID') ? GITHUB_CLIENT_ID : '';

if (!$client_id) {
    // Client ID belum didaftarkan oleh pemilik aplikasi.
    // Tampilkan halaman sederhana + auto-redirect kembali dengan pesan error,
    // supaya modal login di frontend bisa menampilkannya secara rapi.
    header('Location: /digistack/?auth_error=' . urlencode('Login GitHub belum dikonfigurasi. Coba lagi nanti.'));
    exit;
}

$scheme       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$redirect_uri = $scheme . $_SERVER['HTTP_HOST'] . '/digistack/api/auth/github-callback.php';

$github_auth_url = 'https://github.com/login/oauth/authorize?' . http_build_query([
    'client_id'    => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope'        => 'read:user user:email',
    'allow_signup' => 'true'
]);

header('Location: ' . $github_auth_url);
exit;
