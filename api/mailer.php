<?php
// api/mailer.php
class Mailer {

    /**
     * @return array{success: bool, error?: string}
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): array {
        if (defined('SMTP_HOST') && SMTP_HOST) {
            return self::sendViaSmtp($toEmail, $toName, $subject, $htmlBody, $textBody);
        }
        return self::sendViaPhpMail($toEmail, $subject, $htmlBody, $textBody);
    }

    private static function sendViaPhpMail(string $toEmail, string $subject, string $htmlBody, string $textBody): array {
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@digistack.local';
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'DigiStack';

        $boundary = md5(uniqid((string) microtime(true), true));
        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . ($textBody !== '' ? $textBody : strip_tags($htmlBody)) . "\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n";
        $body .= "--{$boundary}--";

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $ok = @mail($toEmail, $encodedSubject, $body, $headers);

        return $ok
            ? ['success' => true]
            : ['success' => false, 'error' => 'Fungsi mail() bawaan PHP gagal/tidak terkonfigurasi (umum terjadi di local dev). Konfigurasikan SMTP_HOST dkk di admin/config.php.'];
    }

    private static function sendViaSmtp(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): array {
        $host      = SMTP_HOST;
        $port      = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
        $secure    = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls';
        $user      = defined('SMTP_USER') ? SMTP_USER : '';
        $pass      = defined('SMTP_PASS') ? SMTP_PASS : '';
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ($user ?: 'no-reply@digistack.local');
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'DigiStack';

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;

        $errno = 0;
        $errstr = '';
        $smtp = @stream_socket_client("{$remote}:{$port}", $errno, $errstr, 15);
        if (!$smtp) {
            return ['success' => false, 'error' => "Gagal konek ke SMTP server: {$errstr} ({$errno})"];
        }
        stream_set_timeout($smtp, 15);

        try {
            self::expect($smtp, '220');

            $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            self::command($smtp, "EHLO {$ehloHost}", '250');

            if ($secure === 'tls') {
                self::command($smtp, 'STARTTLS', '220');
                if (!@stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Gagal mengaktifkan TLS ke SMTP server.');
                }
                self::command($smtp, "EHLO {$ehloHost}", '250');
            }

            if ($user !== '') {
                self::command($smtp, 'AUTH LOGIN', '334');
                self::command($smtp, base64_encode($user), '334');
                self::command($smtp, base64_encode($pass), '235');
            }

            self::command($smtp, "MAIL FROM:<{$fromEmail}>", '250');
            self::command($smtp, "RCPT TO:<{$toEmail}>", '250');
            self::command($smtp, 'DATA', '354');

            $boundary = md5(uniqid((string) microtime(true), true));
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

            $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "To: {$toName} <{$toEmail}>\r\n";
            $headers .= "Subject: {$encodedSubject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

            $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . ($textBody !== '' ? $textBody : strip_tags($htmlBody)) . "\r\n";
            $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n";
            $body .= "--{$boundary}--\r\n";

            // Escape baris yang diawali titik tunggal sesuai standar SMTP (RFC 5321)
            $payload = $headers . "\r\n" . $body;
            $payload = preg_replace('/\r\n\./', "\r\n..", $payload);

            fwrite($smtp, $payload . "\r\n.\r\n");
            self::expect($smtp, '250');

            self::command($smtp, 'QUIT', '221');
            fclose($smtp);

            return ['success' => true];
        } catch (Exception $e) {
            @fclose($smtp);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param resource $smtp
     */
    private static function command($smtp, string $cmd, string $expectedCode): void {
        fwrite($smtp, $cmd . "\r\n");
        self::expect($smtp, $expectedCode);
    }

    /**
     * @param resource $smtp
     */
    private static function expect($smtp, string $expectedCode): void {
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            // Baris terakhir dari response multi-line SMTP ditandai spasi setelah kode (bukan '-')
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP error, expected {$expectedCode} got: " . trim($response));
        }
    }
}