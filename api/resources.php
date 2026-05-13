<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

try {
    $pdo = db();
    $offlineOnly = ($_GET['offline'] ?? '') === '1';
    $sql = "SELECT id, title, file_path, category, description FROM resources";
    if ($offlineOnly) {
        $sql .= " WHERE offline_available = 1";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Resources API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
