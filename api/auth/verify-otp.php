<?php
// api/auth/verify-otp.php
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../../admin/config.php';
if (file_exists(__DIR__ . '/../../admin/oauth-config.php')) {
    require_once '../../admin/oauth-config.php';
}
require_once '../jwt.php';

// Pastikan koneksi PDO menggunakan timezone WIB (+07:00)
try {
    $pdo->exec("SET time_zone = '+07:00'");
} catch (Exception $e) {}

$data = json_decode(file_get_contents("php://input"));

$email = strtolower(trim($data->email ?? ''));
$otp   = trim((string)($data->otp ?? ''));

if (empty($email) || empty($otp)) {
    http_response_code(400);
    echo json_encode(["status" => "fail", "message" => "Email dan Kode OTP wajib diisi."]);
    exit;
}

try {
    // Gunakan parameter waktu eksplisit dari PHP (WIB) agar terhindar dari bentrok timezone MySQL
    $nowFormatted = date('Y-m-d H:i:s');

    $query = "SELECT id, expires_at FROM email_otps 
              WHERE email = :email 
                AND otp_code = :otp 
                AND is_used = 0 
                AND expires_at >= :now 
              ORDER BY id DESC LIMIT 1";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':email' => $email,
        ':otp'   => $otp,
        ':now'   => $nowFormatted
    ]);

    if ($stmt->rowCount() > 0) {
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);

        // Tandai OTP sudah terpakai
        $updateStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE id = :id");
        $updateStmt->execute([':id' => $otp_record['id']]);

        // Cek / Create User di tabel users
        $userStmt = $pdo->prepare("SELECT id, name, email, avatar, auth_provider FROM users WHERE email = :email");
        $userStmt->execute([':email' => $email]);

        if ($userStmt->rowCount() == 0) {
            $name = explode('@', $email)[0];

            $createUser = $pdo->prepare(
                "INSERT INTO users (name, email, password, auth_provider) 
                 VALUES (:name, :email, NULL, 'local')"
            );
            $createUser->execute([
                ':name'  => $name,
                ':email' => $email,
            ]);

            $user_id     = $pdo->lastInsertId();
            $user_name   = $name;
            $user_avatar = null;
        } else {
            $user        = $userStmt->fetch(PDO::FETCH_ASSOC);
            $user_id     = $user['id'];
            $user_name   = $user['name'];
            $user_avatar = $user['avatar'];
        }

        // Simpan User ID dan Data ke PHP SESSION bawaan
        $_SESSION['user_id']    = $user_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name']  = $user_name;
        $_SESSION['logged_in']  = true;

        // Bersihkan session pending OTP
        unset($_SESSION['pending_otp_email'], $_SESSION['pending_otp_code'], $_SESSION['pending_otp_exp']);

        // Issue Token JWT
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

        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Verifikasi berhasil.",
            "token"   => $token,
            "user"    => [
                "id"     => $user_id,
                "name"   => $user_name,
                "email"  => $email,
                "avatar" => $user_avatar
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "fail", "message" => "Kode OTP salah atau telah kadaluarsa."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
}