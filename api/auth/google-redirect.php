<?php
// api/auth/google-redirect.php
require_once '../../admin/config.php';

$client_id = '';

// Redirect URI disesuaikan dengan daftar di Google Cloud Console
$scheme       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$redirect_uri = $scheme . $_SERVER['HTTP_HOST'] . '/digistack/api/auth/google-callback.php';

$google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account'
]);

header('Location: ' . $google_auth_url);
exit;