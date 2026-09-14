<?php
// api/auth/github-callback.php
require_once '../../admin/config.php';
if (file_exists(__DIR__ . '/../../admin/oauth-config.php')) {
    require_once '../../admin/oauth-config.php';
}
require_once '../jwt.php';

function digistack_github_fail(string $message) {
    header('Location: /digistack/?auth_error=' . urlencode($message));
    exit;
}

$code = $_GET['code'] ?? null;
if (!$code) {
    digistack_github_fail('Authorization code dari GitHub tidak ditemukan.');
}

$client_id     = defined('GITHUB_CLIENT_ID') ? GITHUB_CLIENT_ID : '';
$client_secret = defined('GITHUB_CLIENT_SECRET') ? GITHUB_CLIENT_SECRET : '';

if (!$client_id || !$client_secret) {
    digistack_github_fail('Login GitHub belum dikonfigurasi di server.');
}

$scheme       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$redirect_uri = $scheme . $_SERVER['HTTP_HOST'] . '/digistack/api/auth/github-callback.php';

// 1. Tukar authorization code dengan access token
$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'code'          => $code,
    'redirect_uri'  => $redirect_uri,
]));
$tokenResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = $tokenResponse['access_token'] ?? null;
if (!$access_token) {
    digistack_github_fail('Gagal mendapatkan access token dari GitHub.');
}

// 2. Ambil profil user GitHub
$ch = curl_init('https://api.github.com/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'User-Agent: DigiStack-App',
    'Accept: application/vnd.github+json',
]);
$github_user = json_decode(curl_exec($ch), true);
curl_close($ch);

$github_id = $github_user['id'] ?? null;
$name      = $github_user['name'] ?: ($github_user['login'] ?? 'GitHub User');
$avatar    = $github_user['avatar_url'] ?? null;
$email     = $github_user['email'] ?? null;

// GitHub sering menyembunyikan email di profil publik -> ambil dari /user/emails
if (!$email) {
    $ch = curl_init('https://api.github.com/user/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'User-Agent: DigiStack-App',
        'Accept: application/vnd.github+json',
    ]);
    $emails = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (is_array($emails)) {
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['verified'])) {
                $email = $e['email'];
                break;
            }
        }
        if (!$email && !empty($emails[0]['email'])) {
            $email = $emails[0]['email'];
        }
    }
}

$email = $email ? strtolower(filter_var($email, FILTER_VALIDATE_EMAIL)) : null;

if (!$github_id || !$email) {
    digistack_github_fail('Tidak bisa membaca email publik dari akun GitHub kamu. Pastikan email di GitHub sudah diverifikasi.');
}

try {
    // 3. Sync ke tabel users (bukan admins)
    $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE github_id = :gid LIMIT 1");
    $stmt->execute([':gid' => $github_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Akun sudah ada (mis. dari OTP/Google) -> tautkan github_id-nya
            $link = $pdo->prepare("UPDATE users SET github_id = :gid, avatar = COALESCE(avatar, :avatar) WHERE id = :id");
            $link->execute([':gid' => $github_id, ':avatar' => $avatar, ':id' => $user['id']]);
        }
    }

    if (!$user) {
        $insertStmt = $pdo->prepare(
            "INSERT INTO users (name, email, github_id, auth_provider, avatar) 
             VALUES (:name, :email, :gid, 'github', :avatar)"
        );
        $insertStmt->execute([
            ':name'   => $name,
            ':email'  => $email,
            ':gid'    => $github_id,
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
    digistack_github_fail('Terjadi kesalahan database: ' . $e->getMessage());
}
