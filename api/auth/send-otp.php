<?php
// api/auth/send-otp.php
header('Content-Type: application/json');
require_once '../../admin/config.php';
require_once __DIR__ . '/../mailer.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(strtolower(trim($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);

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

    // BUG LAMA: kode OTP di atas hanya disimpan ke database, TIDAK PERNAH
    // benar-benar dikirim ke email user (tidak ada mail()/SMTP sama sekali).
    // Sekarang benar-benar dikirim lewat Mailer (SMTP jika dikonfigurasi di
    // admin/config.php, otomatis fallback ke mail() bawaan PHP jika belum).
    $subject  = 'Kode Verifikasi Login DigiStack';
    $htmlBody = '
        <div style="font-family:sans-serif;max-width:420px;margin:0 auto;">
          <h2 style="color:#1d4ed8;margin-bottom:4px;">DigiStack</h2>
          <p>Berikut kode verifikasi (OTP) untuk login kamu:</p>
          <p style="font-size:32px;font-weight:bold;letter-spacing:6px;margin:16px 0;">' . htmlspecialchars($otpCode) . '</p>
          <p style="color:#64748b;font-size:13px;">Kode ini berlaku selama 10 menit. Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai admin DigiStack.</p>
        </div>';
    $textBody = "Kode verifikasi login DigiStack kamu: {$otpCode}\nKode ini berlaku selama 10 menit. Jangan bagikan kode ini kepada siapa pun.";

    $mailResult = Mailer::send($email, $email, $subject, $htmlBody, $textBody);

    if (!$mailResult['success']) {
        error_log('[DigiStack OTP] Gagal mengirim email ke ' . $email . ': ' . ($mailResult['error'] ?? 'unknown error'));

        // Di luar mode dev, beri tahu user secara jujur bahwa pengiriman gagal
        // supaya mereka tidak menunggu kode yang tidak akan pernah sampai.
        if (!(defined('DEV_MODE') && DEV_MODE)) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Gagal mengirim email OTP. Silakan hubungi admin atau coba lagi nanti.'
            ]);
            exit;
        }
    }

    echo json_encode([
        'status'    => 'success',
        'message'   => 'Kode OTP berhasil dikirim.',
        'debug_otp' => (defined('DEV_MODE') && DEV_MODE) ? $otpCode : null
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memproses OTP: ' . $e->getMessage()]);
}