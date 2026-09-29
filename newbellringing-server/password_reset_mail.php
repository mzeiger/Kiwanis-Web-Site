<?php

declare(strict_types=1);

/**
 * Sends plain-text password reset mail.
 * Uses PHPMailer + SMTP if reset_mail_config.php enables it and PHPMailer exists,
 * otherwise uses PHP mail() with a domain-valid From address.
 */
function password_reset_default_from_address(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    if ($host !== '' && $host !== 'localhost' && !str_starts_with($host, '127.')) {
        if (str_ends_with($host, 'monumenthillkiwanis.org') || str_ends_with($host, 'mhkiwanis.org')) {
            return 'noreply-bellringing@monumenthillkiwanis.org';
        }

        return 'noreply@' . $host;
    }

    return 'noreply-bellringing@monumenthillkiwanis.org';
}

function password_reset_send_mail(string $toEmail, string $subject, string $plainBody): bool
{
    $configPath = __DIR__ . '/reset_mail_config.php';
    $config = file_exists($configPath) ? include $configPath : [];
    if (!is_array($config)) {
        $config = [];
    }

    $fromAddr = trim((string) ($config['from']['address'] ?? ''));
    if ($fromAddr === '' || str_ends_with(strtolower($fromAddr), '@localhost')) {
        $fromAddr = password_reset_default_from_address();
    }
    $fromName = $config['from']['name'] ?? 'Bell Ringing';
    $smtp = $config['smtp'] ?? [];
    $smtpOn = !empty($smtp['enabled']);

    $autoload = __DIR__ . '/vendor/autoload.php';
    if ($smtpOn && file_exists($autoload)) {
        require_once $autoload;
    }

    if ($smtpOn && class_exists('PHPMailer\PHPMailer\PHPMailer')) {

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) ($smtp['host'] ?? '');
            $mail->Port = (int) ($smtp['port'] ?? 587);
            $mail->SMTPAuth = true;
            $mail->Username = (string) ($smtp['user'] ?? '');
            $mail->Password = (string) ($smtp['pass'] ?? '');
            $secure = strtolower((string) ($smtp['secure'] ?? 'tls'));
            if ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->Body = $plainBody;
            $mail->isHTML(false);
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('password_reset_send_mail SMTP: ' . $e->getMessage());
            return false;
        }
    }

    $fromHeader = sprintf('%s <%s>', $fromName, $fromAddr);
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "From: {$fromHeader}\r\n"
        . "Reply-To: {$fromHeader}\r\n";

    $additionalParams = '';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $additionalParams = '-f' . $fromAddr;
    }

    $sent = $additionalParams !== ''
        ? @mail($toEmail, $subject, $plainBody, $headers, $additionalParams)
        : @mail($toEmail, $subject, $plainBody, $headers);

    if (!$sent) {
        error_log('password_reset_send_mail: PHP mail() failed for ' . $toEmail . ' (From: ' . $fromAddr . ')');
    } else {
        error_log('password_reset_send_mail: sent reset mail to ' . $toEmail . ' (From: ' . $fromAddr . ')');
    }

    return $sent;
}
