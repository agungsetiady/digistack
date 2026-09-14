<?php
// helpers/jwt.php
if (!defined('JWT_SECRET') && file_exists(__DIR__ . '/../admin/oauth-config.php')) {
    require_once __DIR__ . '/../admin/oauth-config.php';
}

class JwtHelper {
    // Kunci rahasia untuk signature JWT.
    // Didefinisikan sebagai konstanta JWT_SECRET di admin/oauth-config.php
    // (atau admin/config.php). Nilai di bawah ini hanya fallback darurat.
    private static $secret_key = null;

    private static function secret(): string {
        if (self::$secret_key === null) {
            self::$secret_key = defined('JWT_SECRET') ? JWT_SECRET : 'd1g1st4ck_s3cr3t_k3y_2026_xYz!';
        }
        return self::$secret_key;
    }

    /**
     * Generate JWT Token
     * 
     * @param array $payload
     * @return string
     */
    public static function generate_jwt(array $payload): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::secret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Validasi dan Decode JWT Token
     * 
     * @param string $jwt
     * @return array|false Payload jika valid, false jika invalid/expired
     */
    public static function decode_jwt(string $jwt) {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) {
            return false;
        }

        $header = self::base64UrlDecode($tokenParts[0]);
        $payload = self::base64UrlDecode($tokenParts[1]);
        $signature_provided = $tokenParts[2];

        // Verifikasi Signature
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::secret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        if ($base64UrlSignature !== $signature_provided) {
            return false; // Signature tidak cocok
        }

        $payloadData = json_decode($payload, true);

        // Verifikasi Expiration (exp)
        if (isset($payloadData['exp']) && ($payloadData['exp'] - time()) < 0) {
            return false; // Token kadaluarsa
        }

        return $payloadData;
    }

    /**
     * Base64Url Encode Helper
     */
    private static function base64UrlEncode(string $text): string {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    /**
     * Base64Url Decode Helper
     */
    private static function base64UrlDecode(string $text): string {
        $b64 = str_replace(['-', '_'], ['+', '/'], $text);
        return base64_decode($b64);
    }
}