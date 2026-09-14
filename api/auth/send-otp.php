<?php
// api/auth/send-otp.php
header('Content-Type: application/json');
require_once '../../admin/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'Format email tidak valid.']);
    exit;
}

$otpCode   = sprintf('%06d', mt_rand(100000, 999999));
$expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

try {
    // Tabel email_otps sesuai DB digistack
    $stmt = $pdo->prepare("INSERT INTO email_otps (email, otp_code, expires_at, is_used) 
                           VALUES (:email, :code, :expires_at, 0)");
    
    $stmt->execute([
        'email'      => $email,
        'code'       => $otpCode,
        'expires_at' => $expiresAt
    ]);

    echo json_encode([
        'status'    => 'success',
        'message'   => 'Kode OTP berhasil dikirim.',
        'debug_otp' => (defined('DEV_MODE') && DEV_MODE) ? $otpCode : null
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memproses OTP: ' . $e->getMessage()]);
}