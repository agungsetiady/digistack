<?php
// api/auth/google-callback.php

require_once '../../admin/config.php';
if (file_exists(__DIR__ . '/../../admin/oauth-config.php')) {
    require_once '../../admin/oauth-config.php';
}
require_once '../jwt.php';

$code = $_GET['code'] ?? null;

if (!$code) {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["status" => "fail", "message" => "Authorization code tidak ditemukan."]);
    exit;
}

$client_id     = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$client_secret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';

if (!$client_id || !$client_secret) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["status" => "error", "message" => "Login Google belum dikonfigurasi di server."]);
    exit;
}

$scheme       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$redirect_uri = $scheme . $_SERVER['HTTP_HOST'] . '/digistack/api/auth/google-callback.php';

// 1. Exchange Token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $code,
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code'
]));

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = $response['access_token'] ?? null;

if (!$access_token) {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["status" => "fail", "message" => "Gagal mendapatkan access token dari Google."]);
    exit;
}

// 2. Ambil Profil User Google
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$google_user = json_decode(curl_exec($ch), true);
curl_close($ch);

$email = filter_var(strtolower($google_user['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$google_id = $google_user['id'] ?? null;
$name = $google_user['name'] ?? ($email ? explode('@', $email)[0] : 'User');
$avatar = $google_user['picture'] ?? null;

if (!$email || !$google_id) {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["status" => "fail", "message" => "Data akun Google tidak lengkap/valid."]);
    exit;
}

try {
    // 3. Sync ke tabel users (bukan admins)
    // Cari dulu berdasarkan google_id, lalu fallback ke email (untuk auto-link akun OTP lama)
    $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE google_id = :gid LIMIT 1");
    $stmt->execute([':gid' => $google_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Akun sudah ada (mis. dari login OTP) -> tautkan google_id-nya
            $link = $pdo->prepare("UPDATE users SET google_id = :gid, avatar = COALESCE(avatar, :avatar) WHERE id = :id");
            $link->execute([':gid' => $google_id, ':avatar' => $avatar, ':id' => $user['id']]);
        }
    }

    if (!$user) {
        $insertStmt = $pdo->prepare(
            "INSERT INTO users (name, email, google_id, auth_provider, avatar) 
             VALUES (:name, :email, :gid, 'google', :avatar)"
        );
        $insertStmt->execute([
            ':name'   => $name,
            ':email'  => $email,
            ':gid'    => $google_id,
            ':avatar' => $avatar,
        ]);

        $user_id     = $pdo->lastInsertId();
        $user_name   = $name;
        $user_avatar = $avatar;
    } else {
        $user_id     = $user['id'];
        $user_name   = $user['name'];
        $user_avatar = $user['avatar'] ?: $avatar;
    }

    // 4. Issue Token JWT
    $payload = [
        "iss"    => "digistack",
        "sub"    => $user_id,
        "email"  => $email,
        "name"   => $user_name,
        "avatar" => $user_avatar,
        "iat"    => time(),
        "exp"    => time() + (60 * 60 * 24 * 7)
    ];
    $token = JwtHelper::generate_jwt($payload);

    // Callback dibuka via redirect browser (bukan fetch/AJAX), jadi kita
    // kirim token lewat halaman perantara yang menutup popup / redirect balik.
    $frontend_redirect = '/digistack/';
    ?>
    <!DOCTYPE html>
    <html lang="id"><head><meta charset="UTF-8"><title>Menyelesaikan Login...</title></head>
    <body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;background:#0f172a;color:#fff;">
        <p>Login berhasil, mengalihkan...</p>
        <script>
            try {
                localStorage.setItem('digistack_token', <?= json_encode($token) ?>);
                localStorage.setItem('digistack_user', <?= json_encode(json_encode([
                    'id' => $user_id, 'name' => $user_name, 'email' => $email, 'avatar' => $user_avatar
                ])) ?>);
            } catch (e) {}
            window.location.href = <?= json_encode($frontend_redirect) ?>;
        </script>
    </body></html>
    <?php
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["status" => "error", "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
}
