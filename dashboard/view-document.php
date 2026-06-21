<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

$pdo = db();
app_ensure_farmer_engagement_schema($pdo);

$user = current_user($pdo);
$isAdmin = admin_session_is_authenticated($pdo);
if (!$user && !$isAdmin) {
    http_response_code(403);
    exit('Forbidden');
}
if ($user && !$isAdmin) {
    dashboard_redirect_learner_only($pdo, $user);
}

$file = null;
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM document_files WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $file = $stmt->fetch() ?: null;
} else {
    $type = trim((string) ($_GET['type'] ?? ''));
    if ($type !== '' && $user) {
        $stmt = $pdo->prepare("
            SELECT id, user_id, document_type, file_path, NULL original_name, NULL mime_type, NULL file_size
            FROM document_requirements
            WHERE user_id = ? AND document_type = ? AND file_path IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([(int) $user['id'], $type]);
        $file = $stmt->fetch() ?: null;
    }
}

if (!$file) {
    http_response_code(404);
    exit('Document not found');
}

$ownerId = (int) ($file['user_id'] ?? 0);
if (!$isAdmin && (!$user || $ownerId !== (int) $user['id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$documentsDir = realpath(dirname(__DIR__) . '/documents');
if (!$documentsDir) {
    http_response_code(404);
    exit('Document storage not found');
}

$relativePath = str_replace(['\\', "\0"], ['/', ''], (string) ($file['file_path'] ?? ''));
$relativePath = ltrim($relativePath, '/');
if (str_starts_with($relativePath, 'documents/')) {
    $relativePath = substr($relativePath, strlen('documents/'));
}

$absolutePath = realpath($documentsDir . DIRECTORY_SEPARATOR . $relativePath);
if (!$absolutePath || !str_starts_with($absolutePath, $documentsDir . DIRECTORY_SEPARATOR) || !is_file($absolutePath)) {
    http_response_code(404);
    exit('Document not found');
}

$downloadName = basename((string) ($file['original_name'] ?: $absolutePath));
$mime = (string) ($file['mime_type'] ?? '');
if ($mime === '' || $mime === 'application/octet-stream') {
    $mime = function_exists('mime_content_type') ? (string) mime_content_type($absolutePath) : 'application/octet-stream';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($absolutePath));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($absolutePath);
exit;
