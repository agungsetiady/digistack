<?php
// api/middleware.php
// Helper bersama untuk memverifikasi Bearer JWT pada endpoint REST API DigiStack.
// require_once file ini SETELAH admin/config.php dan api/jwt.php di-include.

/**
 * Ambil token Bearer dari header Authorization request saat ini.
 */
function get_bearer_token(): ?string {
    $authHeader = null;

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }
    }

    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        // Beberapa konfigurasi Apache/FastCGI membuang header Authorization
        // kecuali diteruskan lewat REDIRECT_HTTP_AUTHORIZATION.
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (!$authHeader) {
        return null;
    }

    if (stripos($authHeader, 'Bearer ') === 0) {
        return trim(substr($authHeader, 7));
    }

    return null;
}

/**
 * Wajibkan autentikasi. Menghentikan request (401) jika token tidak ada/tidak valid.
 * Mengembalikan payload JWT ($payload['sub'] = user_id) jika valid.
 */
function require_auth(): array {
    $token = get_bearer_token();

    if (!$token) {
        http_response_code(401);
        echo json_encode([
            "status"  => "fail",
            "message" => "Autentikasi diperlukan. Silakan login terlebih dahulu."
        ]);
        exit;
    }

    $payload = JwtHelper::decode_jwt($token);

    if (!$payload || empty($payload['sub'])) {
        http_response_code(401);
        echo json_encode([
            "status"  => "fail",
            "message" => "Sesi login tidak valid atau telah kadaluarsa. Silakan login kembali."
        ]);
        exit;
    }

    return $payload;
}

/**
 * Autentikasi opsional. Mengembalikan payload JWT jika token valid, atau null bila
 * tidak ada token / token tidak valid (tidak menghentikan request).
 */
function optional_auth(): ?array {
    $token = get_bearer_token();
    if (!$token) {
        return null;
    }

    $payload = JwtHelper::decode_jwt($token);
    if (!$payload || empty($payload['sub'])) {
        return null;
    }

    return $payload;
}