<?php
// api/auth/send-otp.php
// Set timezone konsisten di awal skrip
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once '../../admin/config.php';
require_once __DIR__ . '/../mailer.php';

// Pastikan koneksi PDO menggunakan timezone WIB (+07:00)
try {
    $pdo->exec("SET time_zone = '+07:00'");
} catch (Exception $e) {}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(strtolower(trim($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'Format email tidak valid.']);
    exit;
}

$otpCode   = sprintf('%06d', mt_rand(100000, 999999));
// Kedaluwarsa 10 menit dari sekarang
$expiresAt = date('Y-m-d H:i:s', time() + (10 * 60));

try {
    // Nonaktifkan OTP lama yang belum dipakai untuk email ini
    $invalidateStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE email = :email AND is_used = 0");
    $invalidateStmt->execute(['email' => $email]);

    // Insert OTP baru
    $stmt = $pdo->prepare("INSERT INTO email_otps (email, otp_code, expires_at, is_used) 
                           VALUES (:email, :code, :expires_at, 0)");
    
    $stmt->execute([
        'email'      => $email,
        'code'       => $otpCode,
        'expires_at' => $expiresAt
    ]);

    // Simpan juga ke Session PHP sebagai cadangan
    $_SESSION['pending_otp_email'] = $email;
    $_SESSION['pending_otp_code']  = $otpCode;
    $_SESSION['pending_otp_exp']   = time() + (10 * 60);

    $subject  = 'Kode Verifikasi Login DigiStack';
    $htmlBody = '
        <div style="font-family:sans-serif;max-width:420px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px;">
          <h2 style="color:#1d4ed8;margin-bottom:4px;">DigiStack</h2>
          <p style="color:#334155;">Berikut kode verifikasi (OTP) untuk login kamu:</p>
          <div style="background:#f1f5f9;padding:12px;text-align:center;border-radius:6px;margin:16px 0;">
            <span style="font-size:32px;font-weight:bold;letter-spacing:6px;color:#0f172a;">' . htmlspecialchars($otpCode) . '</span>
          </div>
          <p style="color:#64748b;font-size:13px;line-height:1.5;">Kode ini berlaku selama 10 menit. Jangan bagikan kode ini kepada siapa pun.</p>
        </div>';
    $textBody = "Kode verifikasi login DigiStack kamu: {$otpCode}\nKode ini berlaku selama 10 menit. Jangan bagikan kode ini kepada siapa pun.";

    $mailResult = Mailer::send($email, $email, $subject, $htmlBody, $textBody);

    if (!$mailResult['success']) {
        error_log('[DigiStack OTP] Gagal mengirim email ke ' . $email . ': ' . ($mailResult['error'] ?? 'unknown error'));

        if (!(defined('DEV_MODE') && DEV_MODE)) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Gagal mengirim email OTP: ' . ($mailResult['error'] ?? 'Gagal koneksi SMTP')
            ]);
            exit;
        }
    }

    echo json_encode([
        'status'    => 'success',
        'message'   => 'Kode OTP berhasil dikirim ke ' . $email,
        'debug_otp' => (defined('DEV_MODE') && DEV_MODE) ? $otpCode : null
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memproses OTP: ' . $e->getMessage()]);
}