<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
    }
}

if (!function_exists('app_branded_email_html')) {
    function app_branded_email_html(
        string $title,
        string $contentHtml,
        ?string $preheader = null,
        ?string $actionButtonText = null,
        ?string $actionButtonUrl = null,
        ?string $otpCode = null
    ): string {
        $logoUrl = app_base_url() . '/assets/logo/natcodev.jpeg';
        $year = date('Y');
        $preheaderText = $preheader ?: $title;

        $otpBlock = '';
        if ($otpCode !== null && $otpCode !== '') {
            $otpBlock = '
                <div style="margin: 28px 0; text-align: center;">
                    <div style="display: inline-block; background: #eef8ef; border: 2px dashed #14733a; border-radius: 12px; padding: 18px 36px; text-align: center;">
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #14733a; font-weight: 800; margin-bottom: 6px;">Your One-Time Code</div>
                        <div style="font-size: 32px; font-weight: 900; letter-spacing: 6px; color: #14733a; font-family: monospace;">' . e($otpCode) . '</div>
                        <div style="font-size: 11px; color: #667085; margin-top: 6px;">Valid for 10 minutes. Do not share with anyone.</div>
                    </div>
                </div>
            ';
        }

        $actionBlock = '';
        if ($actionButtonText !== null && $actionButtonUrl !== null && $actionButtonText !== '') {
            $actionBlock = '
                <div style="margin: 32px 0 24px; text-align: center;">
                    <a href="' . e($actionButtonUrl) . '" style="display: inline-block; background: #14733a; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 12px rgba(20, 115, 58, 0.25);">' . e($actionButtonText) . '</a>
                </div>
            ';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . e($title) . '</title>
    <style>
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f4faf2; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4faf2;">
    <!-- Preheader for email inbox preview -->
    <div style="display: none; font-size: 1px; color: #f4faf2; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
        ' . e($preheaderText) . '
    </div>
    
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4faf2;">
        <tr>
            <td align="center" style="padding: 30px 15px 40px 15px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <!-- Brand Header -->
                    <tr>
                        <td align="center" style="padding: 0 0 24px 0;">
                            <table border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="vertical-align: middle; padding-right: 12px;">
                                        <img src="' . e($logoUrl) . '" alt="NATCODEV Logo" width="54" height="54" style="display: block; border-radius: 50%; border: 1px solid #dfe8d8; background: #ffffff;">
                                    </td>
                                    <td style="vertical-align: middle; text-align: left;">
                                        <div style="font-size: 20px; font-weight: 900; color: #075f2a; letter-spacing: 0.5px;">NATCODEV</div>
                                        <div style="font-size: 11px; color: #66715f; font-weight: 600;">National Coconut Development & Propagation Initiative</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Main Card -->
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 12px; border: 1px solid #dfe8d8; box-shadow: 0 4px 20px rgba(16,24,40,0.06); overflow: hidden;">
                            <!-- Top Brand Accent Bar -->
                            <div style="height: 6px; background: linear-gradient(90deg, #075f2a 0%, #14733a 50%, #c9a227 100%);"></div>
                            
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="padding: 36px 32px 32px 32px;">
                                <tr>
                                    <td style="font-size: 15px; line-height: 1.6; color: #1f2937;">
                                        ' . $contentHtml . '
                                        ' . $otpBlock . '
                                        ' . $buttonBlock . '
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Security / Disclaimer Footer -->
                    <tr>
                        <td align="center" style="padding: 24px 20px 0 20px; font-size: 12px; line-height: 1.5; color: #667085; text-align: center;">
                            <p style="margin: 0 0 8px 0;">This is an automated system notification from the official NATCODEV registry platform.</p>
                            <p style="margin: 0 0 12px 0;">Suite T11, 3rd Floor, Febson Mall, 24/25 Herbert Macaulay Way, Wuse Zone 4, Abuja &bull; Tel: +234 703 337 7202</p>
                            <p style="margin: 0; color: #98a2b3;">&copy; ' . e((string) $year) . ' NATCODEV. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}
}

if (!function_exists('app_send_mail')) {
function app_send_mail(string $to, string $subject, string $plainText, ?string $html = null): bool
{
    $fromEmail = app_env('MAIL_FROM_ADDRESS', 'noreply@coconutventurehub.ng');
    $fromName = app_env('MAIL_FROM_NAME', 'NATCODEV');
    $replyTo = app_env('MAIL_REPLY_TO', 'info@coconutventurehub.ng');
    $transport = strtolower((string) app_env('MAIL_TRANSPORT', app_is_production() ? 'mail' : 'log'));

    // If no HTML was explicitly provided, wrap the plaintext in a branded HTML template
    if ($html === null) {
        $html = app_branded_email_html($subject, nl2br(e($plainText)));
    } elseif (!str_contains($html, '<!DOCTYPE html>') && !str_contains($html, '<html')) {
        $html = app_branded_email_html($subject, $html);
    }

    if ($transport === 'log') {
        $logDir = app_private_storage_path('logs');
        $logPath = $logDir . DIRECTORY_SEPARATOR . 'mail.log';
        $entry = [
            'sent_at' => date('c'),
            'to' => $to,
            'subject' => $subject,
            'plain' => $plainText,
            'html' => $html,
        ];
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logged = is_dir($logDir) && @file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
        app_log_notification('email', $to, $subject, $plainText, $logged ? 'logged' : 'failed', $transport, $logged ? 'Written to ' . $logPath : null, $logged ? null : 'Unable to write mail log at ' . $logPath);
        return $logged;
    }

    $boundary = 'natcodev_' . bin2hex(random_bytes(12));
    $host = parse_url(app_base_url(), PHP_URL_HOST) ?: 'coconutventurehub.ng';
    $messageId = '<' . bin2hex(random_bytes(16)) . '@' . $host . '>';

    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "Return-Path: <{$fromEmail}>\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: {$messageId}\r\n";
    $headers .= "X-Mailer: NATCODEV Communications Engine v2.0\r\n";
    $headers .= "Auto-Submitted: auto-generated\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= trim($plainText) . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $html . "\r\n\r\n";
    $message .= "--{$boundary}--";

    // Pass envelope sender flag to mail() for SPF alignment
    $sent = @mail($to, $subject, $message, $headers, "-f{$fromEmail}");
    if (!$sent) {
        $sent = @mail($to, $subject, $message, $headers);
    }

    app_log_notification('email', $to, $subject, $plainText, $sent ? 'sent' : 'failed', $transport, $sent ? 'mail() accepted message' : null, $sent ? null : 'mail() returned false');
    return $sent;
}
}

if (!function_exists('app_log_notification')) {
function app_log_notification(
    string $channel,
    string $recipient,
    ?string $subject,
    string $message,
    string $status,
    ?string $transport = null,
    ?string $providerResponse = null,
    ?string $errorMessage = null,
    ?string $context = null
): void {
    try {
        $pdo = db();
        if (!app_table_exists($pdo, 'notification_logs')) {
            return;
        }
        $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 0, 600);
        $stmt = $pdo->prepare("
            INSERT INTO notification_logs
                (channel, recipient, subject, message_preview, status, transport, provider_response, error_message, context)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$channel, $recipient, $subject, $preview, $status, $transport, $providerResponse, $errorMessage, $context]);
    } catch (Throwable $e) {
        error_log('Notification audit log failed: ' . $e->getMessage());
    }
}
}

if (!function_exists('app_csv_value')) {
function app_csv_value(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $text = (string) $value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        return "'" . $text;
    }

    return $text;
}
}

if (!function_exists('app_export_csv')) {
function app_export_csv(string $filename, array $headers, iterable $rows): void
{
    if (!str_ends_with(strtolower($filename), '.csv')) {
        $filename .= '.csv';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($filename)) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    if (!$out) {
        exit;
    }

    fwrite($out, "\xEF\xBB\xBF");
    if ($headers) {
        $headers[0] = preg_match('/^id\b/i', (string) $headers[0]) ? 'Record ' . (string) $headers[0] : (string) $headers[0];
        fputcsv($out, array_map('app_csv_value', $headers));
    }
    foreach ($rows as $row) {
        if ($row instanceof Traversable) {
            $row = iterator_to_array($row);
        }
        if (!is_array($row)) {
            $row = [$row];
        }
        fputcsv($out, array_map('app_csv_value', array_values($row)));
    }
    fclose($out);
    exit;
}
}

if (!function_exists('app_csv_import_rows')) {
function app_csv_import_rows(string $path, int $maxRows = 20000): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to open CSV file.');
    }

    $sample = (string) fgets($handle);
    $delimiters = [',', ';', "\t"];
    $delimiter = ',';
    $bestCount = 0;
    foreach ($delimiters as $candidate) {
        $count = count(str_getcsv($sample, $candidate));
        if ($count > $bestCount) {
            $bestCount = $count;
            $delimiter = $candidate;
        }
    }
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($rows) >= $maxRows) {
            fclose($handle);
            throw new RuntimeException('CSV exceeds the maximum allowed row count of ' . $maxRows . '.');
        }
        if ($rows === [] && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
        }
        $rows[] = array_map(static fn($value): string => trim(str_replace("\0", '', (string) $value)), $row);
    }
    fclose($handle);
    return $rows;
}
}

if (!function_exists('app_uploaded_file_info')) {
function app_uploaded_file_info(array $file, array $allowedExtensions, int $maxBytes, string $label = 'File', array $allowedMimes = []): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL => 'was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'was not selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'cannot be uploaded because the temporary folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'could not be written to disk.',
            UPLOAD_ERR_EXTENSION => 'was blocked by a server extension.',
        ];
        throw new RuntimeException($label . ' ' . ($messages[$error] ?? 'could not be uploaded.'));
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException($label . ' upload is invalid.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException($label . ' is empty.');
    }
    if ($size > $maxBytes) {
        throw new RuntimeException($label . ' exceeds the allowed file size.');
    }

    $original = (string) ($file['name'] ?? 'upload');
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = array_map('strtolower', $allowedExtensions);
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException($label . ' has an unsupported file type.');
    }

    $detectedMime = '';
    if ($allowedMimes !== []) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = (string) finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }
        if ($detectedMime === '' && function_exists('mime_content_type')) {
            $detectedMime = (string) mime_content_type($tmp);
        }

        $allowedMimeMap = array_fill_keys(array_map('strtolower', $allowedMimes), true);
        if ($extension === 'pdf') {
            $handle = fopen($tmp, 'rb');
            $signature = $handle ? (string) fread($handle, 4) : '';
            if ($handle) {
                fclose($handle);
            }
            if ($signature !== '%PDF') {
                throw new RuntimeException($label . ' is not a valid PDF file.');
            }
        } elseif ($detectedMime !== '' && !isset($allowedMimeMap[strtolower($detectedMime)])) {
            throw new RuntimeException($label . ' content does not match the selected file type.');
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $imageInfo = @getimagesize($tmp);
            if ($imageInfo === false) {
                throw new RuntimeException($label . ' is not a valid image file.');
            }
        }
    }

    return [
        'tmp_name' => $tmp,
        'name' => $original,
        'extension' => $extension,
        'size' => $size,
        'type' => $detectedMime !== '' ? $detectedMime : (string) ($file['type'] ?? ''),
    ];
}
}

if (!function_exists('app_safe_upload_name')) {
function app_safe_upload_name(string $prefix, string $originalName, string $extension): string
{
    $prefix = preg_replace('/[^a-z0-9_-]/i', '_', $prefix) ?: 'upload';
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-z0-9._-]/i', '_', $base) ?: 'file';
    $base = trim($base, '._-');
    return $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '_' . substr($base, 0, 80) . '.' . strtolower($extension);
}
}

if (!function_exists('generate_application_ref')) {
function generate_application_ref(): string
{
    return 'NAT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
}