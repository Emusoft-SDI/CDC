<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo();
$user = fa_require_user($pdo);
$visitId = max(0, (int) ($_GET['visit_id'] ?? 0));
$fileIndex = max(0, (int) ($_GET['file'] ?? 0));
$stmt = $pdo->prepare("SELECT fv.*, ft.assigned_to FROM farm_visits fv LEFT JOIN field_tasks ft ON ft.id=fv.task_id WHERE fv.id=? LIMIT 1");
$stmt->execute([$visitId]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$visit) { http_response_code(404); exit('Evidence not found.'); }
$role = fa_role_key($user);
$owns = (int) ($visit['agent_id'] ?? 0) === (int) $user['id'] || (int) ($visit['assigned_to'] ?? 0) === (int) $user['id'] || $role === 'admin';
if (!$owns) { http_response_code(403); exit('Access denied.'); }
$files = json_decode((string) ($visit['photos'] ?? '[]'), true);
if (!is_array($files) || !isset($files[$fileIndex]) || !is_array($files[$fileIndex])) { http_response_code(404); exit('Evidence file not found.'); }
$file = $files[$fileIndex];
$relative = str_replace('\\', '/', (string) ($file['path'] ?? ''));
$root = realpath(dirname(__DIR__) . '/field_uploads/evidence');
$path = realpath(dirname(__DIR__) . '/' . ltrim($relative, '/'));
if (!$root || !$path || !str_starts_with($path, $root) || !is_file($path)) { http_response_code(404); exit('Evidence file missing.'); }
$name = preg_replace('/[^a-z0-9._-]/i', '_', (string) ($file['name'] ?? basename($path))) ?: 'field-evidence';
$mime = (string) ($file['mime'] ?? mime_content_type($path) ?: 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;