<?php
// api/auth/google-callback.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../admin/config.php';
require_once '../jwt.php';

$code = $_GET['code'] ?? null;

if (!$code) {
    http_response_code(400);
    echo json_encode(["status" => "fail", "message" => "Authorization code tidak ditemukan."]);
    exit;
}

$client_id     = '';
$client_secret = '';
$scheme        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$redirect_uri  = $scheme . $_SERVER['HTTP_HOST'] . '/digistack/api/auth/google-callback.php';

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
    echo json_encode(["status" => "fail", "message" => "Gagal mendapatkan access token dari Google."]);
    exit;
}

// 2. Ambil Profil User Google
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$google_user = json_decode(curl_exec($ch), true);
curl_close($ch);

$email = filter_var($google_user['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(["status" => "fail", "message" => "Email Google tidak valid."]);
    exit;
}

try {
    // 3. Sync ke Tabel admins (DB Digistack)
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM admins WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $username = strtolower(explode('@', $email)[0]);
        $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $role     = 'admin';

        $insertStmt = $pdo->prepare("INSERT INTO admins (username, email, password_hash, role) VALUES (:username, :email, :password, :role)");
        $insertStmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':password' => $password,
            ':role'     => $role
        ]);

        $user_id   = $pdo->lastInsertId();
        $user_name = $username;
        $user_role = $role;
    } else {
        $user_id   = $user['id'];
        $user_name = $user['username'];
        $user_role = $user['role'];
    }

    // 4. Issue Token JWT
    $payload = [
        "iss"   => "digistack",
        "sub"   => $user_id,
        "email" => $email,
        "name"  => $user_name,
        "role"  => $user_role,
        "iat"   => time(),
        "exp"   => time() + (60 * 60 * 24 * 7)
    ];
    $token = JwtHelper::generate_jwt($payload);

    http_response_code(200);
    echo json_encode([
        "status"  => "success",
        "message" => "Login Google berhasil.",
        "token"   => $token,
        "user"    => [
            "id"       => $user_id,
            "username" => $user_name,
            "email"    => $email,
            "role"     => $user_role
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
}