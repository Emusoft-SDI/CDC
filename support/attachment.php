<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/support.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
support_ensure_schema($pdo);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(404);
    exit('Attachment not found.');
}

$attachment = support_attachment_by_id($pdo, (int) $id);
if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

$user = current_user($pdo);
$isAdmin = admin_session_is_authenticated($pdo);
$tokenEmail = trim((string) ($_GET['email'] ?? ''));
$tokenRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));

$allowed = false;
if ($isAdmin) {
    $allowed = true;
} elseif ($user && (int) ($attachment['ticket_user_id'] ?? 0) > 0 && (int) $user['id'] === (int) $attachment['ticket_user_id']) {
    $allowed = true;
} elseif ($user && strcasecmp((string) $user['email'], (string) $attachment['requester_email']) === 0) {
    $allowed = true;
} elseif ($tokenEmail !== '' && strcasecmp($tokenEmail, (string) $attachment['requester_email']) === 0 && ($tokenRef === '' || strcasecmp($tokenRef, (string) $attachment['ticket_ref']) === 0)) {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    exit('Access denied to this attachment.');
}

$uploadDir = realpath(support_upload_dir());
if (!$uploadDir) {
    http_response_code(404);
    exit('Attachment storage directory not found.');
}

$relativePath = str_replace(['\\', "\0"], ['/', ''], (string) ($attachment['file_path'] ?? ''));
$relativePath = ltrim($relativePath, '/');
if (str_starts_with($relativePath, 'uploads/support/')) {
    $relativePath = substr($relativePath, strlen('uploads/support/'));
}

$absolutePath = realpath($uploadDir . DIRECTORY_SEPARATOR . $relativePath);
if (!$absolutePath || !str_starts_with($absolutePath, $uploadDir . DIRECTORY_SEPARATOR) || !is_file($absolutePath)) {
    http_response_code(404);
    exit('Attachment file not found on disk.');
}

$filename = basename((string) ($attachment['original_name'] ?: $absolutePath));
$mime = (string) ($attachment['mime_type'] ?? '');
if ($mime === '' || $mime === 'application/octet-stream') {
    if (function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($absolutePath);
    }
}
if ($mime === '') {
    $mime = 'application/octet-stream';
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$inlineTypes = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'txt'];
$disposition = in_array($extension, $inlineTypes, true) ? 'inline' : 'attachment';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($absolutePath));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($absolutePath);
exit;
