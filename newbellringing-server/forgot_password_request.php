<?php

declare(strict_types=1);

require __DIR__ . '/inc/db_connect.php';
require_once __DIR__ . '/password_reset_schema.php';
require_once __DIR__ . '/password_reset_mail.php';
require_once __DIR__ . '/password_reset_mode.php';

header('Content-Type: application/json; charset=utf-8');

try {
    password_reset_ensure_table($dbh);
} catch (Throwable $e) {
    error_log('password_reset_ensure_table: ' . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'error' => 'Could not create the password reset table. Grant CREATE permission or run password_reset_token.sql in phpMyAdmin.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = strtolower(trim($_POST['forgotPasswordEmailInput'] ?? $_POST['email'] ?? ''));
$generic = [
    'ok' => true,
    'message' => 'If an account exists for that email, you will receive password reset instructions shortly.',
];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($generic);
    exit;
}

try {
    $stmt = $dbh->prepare('SELECT id, email FROM ringers WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode($generic);
        exit;
    }

    $dbh->prepare('DELETE FROM password_reset_token WHERE ringer_id = :rid OR expires_at < NOW()')
        ->execute([':rid' => $row['id']]);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $insert = $dbh->prepare(
        'INSERT INTO password_reset_token (ringer_id, token_hash, expires_at) VALUES (:rid, :th, :ex)'
    );
    $insert->execute([
        ':rid' => $row['id'],
        ':th' => $tokenHash,
        ':ex' => $expires,
    ]);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $resetUrl = $scheme . '://' . $host . ($basePath === '' ? '' : $basePath) . '/reset_password.php?token=' . rawurlencode($token);

    if (password_reset_use_browser_link()) {
        echo json_encode([
            'ok' => true,
            'message' => 'Use the link below to set a new password. It expires in one hour.',
            'dev_reset_url' => $resetUrl,
        ]);
        exit;
    }

    $subject = 'Reset your Bell Ringing password';
    $body = "We received a request to reset the password for your Bell Ringing account.\n\n"
        . "Open this link within one hour to choose a new password:\n{$resetUrl}\n\n"
        . "If you did not request this, you can ignore this email.\n";

    $sent = password_reset_send_mail($row['email'], $subject, $body);
    if (!$sent) {
        $dbh->prepare('DELETE FROM password_reset_token WHERE token_hash = :th')->execute([':th' => $tokenHash]);
        echo json_encode([
            'ok' => false,
            'error' => 'The reset link could not be sent by email. Configure SMTP (see reset_mail_config.example.php) or contact the site administrator.',
        ]);
        exit;
    }

    echo json_encode($generic);
} catch (Throwable $e) {
    error_log('forgot_password_request: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Something went wrong. Please try again later.']);
}
