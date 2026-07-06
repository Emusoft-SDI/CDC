<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider.php';

$pdo = provider_boot();
$user = current_user($pdo);
if (!$user) {
    http_response_code(401);
    exit('Authentication required.');
}

$stmt = $pdo->prepare("SELECT d.*, pr.user_id provider_user_id, pr.email provider_email FROM provider_accreditation_documents d JOIN provider_registry pr ON pr.id=d.provider_id WHERE d.id=? LIMIT 1");
$stmt->execute([max(0, (int) ($_GET['id'] ?? 0))]);
$document = $stmt->fetch();
if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$isOwner = (int) ($document['provider_user_id'] ?? 0) === (int) $user['id']
    || (string) ($document['provider_email'] ?? '') === (string) ($user['email'] ?? '');
$isAdmin = admin_session_is_authenticated($pdo);
if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    exit('Forbidden.');
}

$root = realpath(dirname(__DIR__));
$path = realpath(dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', (string) $document['stored_path']), '/'));
$allowedRoot = realpath(dirname(__DIR__) . '/provider_uploads/accreditation');
if (!$root || !$path || !$allowedRoot || !str_starts_with(strtolower($path), strtolower($allowedRoot . DIRECTORY_SEPARATOR)) || !is_file($path)) {
    http_response_code(404);
    exit('Document file is unavailable.');
}

$mime = (string) ($document['mime_type'] ?: 'application/octet-stream');
$name = preg_replace('/[^a-z0-9._-]/i', '_', (string) $document['original_name']) ?: 'document';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
