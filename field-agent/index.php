<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';

$pdo = db();
$user = current_user($pdo);
if (!$user || $user['role'] !== 'field_agent') {
    header('Location: ../login.php');
    exit;
}

// Handle Task Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("UPDATE field_visits SET status = ? WHERE id = ? AND agent_id = ?");
    $stmt->execute([$_POST['status'], $_POST['task_id'], $user['id']]);
}

// Handle Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['farm_image'])) {
    $taskId = (int)$_POST['task_id'];
    $uploadDir = __DIR__ . '/../uploads/farm_imagery/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $filename = 'task_' . $taskId . '_' . time() . '_' . basename($_FILES['farm_image']['name']);
    if (move_uploaded_file($_FILES['farm_image']['tmp_name'], $uploadDir . $filename)) {
        $stmt = $pdo->prepare("INSERT INTO farm_imagery (task_id, file_path, status, uploaded_at) VALUES (?, ?, 'pending_approval', NOW())");
        $stmt->execute([$taskId, $filename]);
        $message = "Image uploaded successfully and pending admin approval.";
    }
}

// Fetch assigned work and performance metrics
$stmt = $pdo->prepare("SELECT fv.*, u.name as grower_name FROM field_visits fv LEFT JOIN users u ON fv.grower_id = u.id WHERE fv.agent_id = ? ORDER BY fv.visited_at DESC");
$stmt->execute([$user['id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAssigned = count($tasks);
$completed = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$performance = $totalAssigned > 0 ? round(($completed / $totalAssigned) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Field Agent Dashboard | NATCODEV</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2>
        <a href="../support/index.php" class="btn btn-warning">Support Desk</a>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-4"><div class="card p-3"><h5>Tasks Completed</h5><h3><?= $completed ?> / <?= $totalAssigned ?></h3></div></div>
        <div class="col-md-4"><div class="card p-3"><h5>Performance Rate</h5><h3><?= $performance ?>%</h3></div></div>
    </div>

    <?php if (isset($message)): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">Assigned Field Visits</div>
        <div class="card-body">
            <table class="table table-hover">
                <thead><tr><th>Grower</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?= htmlspecialchars($task['grower_name'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($task['visited_at']) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="assigned" <?= $task['status'] === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                        <td>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <input type="file" name="farm_image" accept="image/*" capture="environment" required>
                                <button type="submit" class="btn btn-sm btn-primary">Upload Imagery</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
