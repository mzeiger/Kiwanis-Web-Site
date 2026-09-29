<?php

declare(strict_types=1);

/**
 * Local dev: show reset URL in the browser instead of sending email.
 *
 * Optional override file password_reset_local.override.php (gitignored) may return:
 *   ['use_browser_link' => true]  - always show link, never send email
 *   ['use_browser_link' => false] - always send email (even on localhost)
 */
function password_reset_use_browser_link(): bool
{
    $path = __DIR__ . '/password_reset_local.override.php';
    if (file_exists($path)) {
        $override = include $path;
        if (is_array($override) && array_key_exists('use_browser_link', $override)) {
            return (bool) $override['use_browser_link'];
        }
    }

    $addr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($addr, ['127.0.0.1', '::1'], true)) {
        return false;
    }

    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if ($host === 'localhost' || $host === '127.0.0.1') {
        return true;
    }
    if (preg_match('/^127\.\d+\.\d+\.\d+(:\d+)?$/', $host)) {
        return true;
    }

    return str_ends_with($host, '.localhost');
}
