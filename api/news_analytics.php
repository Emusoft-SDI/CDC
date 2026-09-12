<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/news.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$newsId = (int) ($_GET['news_id'] ?? 0);
$action = strtolower(trim((string) ($_GET['action'] ?? 'view')));
if (!in_array($action, ['view', 'click'], true)) {
    $action = 'view';
}

if ($newsId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid news ID.']);
    exit;
}

try {
    $pdo = db();
    news_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT id FROM coop_news WHERE id = ? LIMIT 1');
    $stmt->execute([$newsId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Article not found.']);
        exit;
    }

    if ($action === 'click') {
        $stmt = $pdo->prepare('
            INSERT INTO coop_news_analytics (news_id, clicks_count)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE clicks_count = clicks_count + 1
        ');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO coop_news_analytics (news_id, views_count)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE views_count = views_count + 1
        ');
    }
    $stmt->execute([$newsId]);

    echo json_encode(['status' => 'success', 'message' => 'Analytics logged.']);
} catch (Throwable $e) {
    error_log('News analytics failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Analytics logging failed.']);
}
