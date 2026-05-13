<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/assignments.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$agentId = (int) ($input['agent_id'] ?? 0);
$batchName = trim((string) ($input['batch_name'] ?? ''));
$criteria = is_array($input['criteria'] ?? null) ? $input['criteria'] : [];

if ($agentId <= 0 || $batchName === '') {
    json_response(['success' => false, 'error' => 'Assignment name and field agent are required'], 422);
}

try {
    $agentStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'field_agent' LIMIT 1");
    $agentStmt->execute([$agentId]);
    if (!$agentStmt->fetch()) {
        json_response(['success' => false, 'error' => 'Field agent not found'], 404);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS assignment_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            name VARCHAR(150) NOT NULL,
            criteria JSON NULL,
            total_assigned INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agronomist_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agronomist_id INT NOT NULL,
            grower_id INT NOT NULL,
            batch_id INT NULL,
            assignment_criteria JSON NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_active_assignment (grower_id, status),
            INDEX idx_agronomist_assignments_agent (agronomist_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    [$sql, $params] = assignment_grower_query($pdo, $criteria, 0);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $growerIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $pdo->beginTransaction();
    $pdo->prepare("
        INSERT INTO assignment_batches (admin_id, name, criteria)
        VALUES (?, ?, ?)
    ")->execute([
        $_SESSION['user_id'] ?? null,
        $batchName,
        json_encode($criteria, JSON_UNESCAPED_SLASHES),
    ]);
    $batchId = (int) $pdo->lastInsertId();

    $assigned = 0;
    $insert = $pdo->prepare("
        INSERT IGNORE INTO agronomist_assignments
            (agronomist_id, grower_id, batch_id, assignment_criteria, status)
        VALUES (?, ?, ?, ?, 'active')
    ");
    foreach ($growerIds as $growerId) {
        $insert->execute([$agentId, $growerId, $batchId, json_encode($criteria, JSON_UNESCAPED_SLASHES)]);
        $assigned += $insert->rowCount() > 0 ? 1 : 0;
    }

    $pdo->prepare("UPDATE assignment_batches SET total_assigned = ? WHERE id = ?")->execute([$assigned, $batchId]);
    $pdo->commit();

    json_response(['success' => true, 'assigned' => $assigned, 'batch_id' => $batchId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Assign growers API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to assign growers'], 500);
}
