<?php
/**
 * Simple mail helper — log in development, SMTP when configured.
 */

class Mail
{
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $from = MAIL_FROM;
        $fromName = MAIL_FROM_NAME;

        if (MAIL_DRIVER === 'log' || VXM_ENV === 'development') {
            $logDir = STORAGE_PATH . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $entry = sprintf(
                "[%s] TO: %s | SUBJECT: %s\n%s\n---\n",
                date('c'),
                $to,
                $subject,
                $textBody ?: strip_tags($htmlBody)
            );
            @file_put_contents($logDir . '/mail_' . date('Y-m-d') . '.log', $entry, FILE_APPEND);
            return true;
        }

        if (MAIL_DRIVER === 'smtp' && SMTP_HOST && SMTP_USER) {
            return self::sendSmtp($to, $subject, $htmlBody, $textBody);
        }

        // Fallback PHP mail()
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: VXM',
        ];
        return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    private static function sendSmtp(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        // Minimal SMTP via stream — works on many shared hosts without PHPMailer
        $host = SMTP_HOST;
        $port = (int)SMTP_PORT;
        $user = SMTP_USER;
        $pass = SMTP_PASS;
        $secure = SMTP_SECURE; // tls or ssl

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 20);
        if (!$fp) {
            return false;
        }

        stream_set_timeout($fp, 20);
        self::smtpRead($fp);

        self::smtpCmd($fp, 'EHLO ' . ($host ?: 'localhost'));
        if ($secure === 'tls') {
            self::smtpCmd($fp, 'STARTTLS');
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            self::smtpCmd($fp, 'EHLO ' . ($host ?: 'localhost'));
        }

        self::smtpCmd($fp, 'AUTH LOGIN');
        self::smtpCmd($fp, base64_encode($user));
        self::smtpCmd($fp, base64_encode($pass));
        self::smtpCmd($fp, 'MAIL FROM:<' . MAIL_FROM . '>');
        self::smtpCmd($fp, 'RCPT TO:<' . $to . '>');
        self::smtpCmd($fp, 'DATA');

        $boundary = md5(uniqid());
        $msg = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $msg .= "To: <$to>\r\n";
        $msg .= "Subject: $subject\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=utf-8\r\n";
        $msg .= "\r\n" . $htmlBody . "\r\n.\r\n";
        fwrite($fp, $msg);
        self::smtpRead($fp);
        self::smtpCmd($fp, 'QUIT');
        fclose($fp);
        return true;
    }

    private static function smtpCmd($fp, string $cmd): void
    {
        fwrite($fp, $cmd . "\r\n");
        self::smtpRead($fp);
    }

    private static function smtpRead($fp): string
    {
        $data = '';
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') break;
        }
        return $data;
    }
}
