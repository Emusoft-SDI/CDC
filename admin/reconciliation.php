<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);

// Handle file upload and parsing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['statement_file'])) {
    $uploadDir = __DIR__ . '/../uploads/reconciliation/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = basename($_FILES['statement_file']['name']);
    $importId = time();
    $targetPath = $uploadDir . $importId . '_' . $filename;

    if (move_uploaded_file($_FILES['statement_file']['tmp_name'], $targetPath)) {
        $pdo->prepare("INSERT INTO bank_statement_imports (id, filename, bank_name, uploaded_by, status) VALUES (?, ?, ?, ?, 'processed')")
            ->execute([$importId, $filename, $_POST['bank_name'] ?? 'Unknown', admin_current_user_id($pdo)]);

        // Parse CSV and match
        if (($handle = fopen($targetPath, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Assuming CSV structure: reference, amount
                $ref = $data[0];
                $amount = (float)$data[1];

                // Try to match
                $stmt = $pdo->prepare("SELECT id FROM wallet_transactions WHERE reference = ? AND amount = ? LIMIT 1");
                $stmt->execute([$ref, $amount]);
                $match = $stmt->fetch();

                $status = $match ? 'matched' : 'unmatched';
                $pdo->prepare("INSERT INTO reconciliation_matches (import_id, transaction_id, external_reference, amount, match_status) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$importId, $match['id'] ?? null, $ref, $amount, $status]);
            }
            fclose($handle);
        }

        $message = "File processed and reconciliation run.";
    } else {
        $error = "Failed to upload file.";
    }
}

// Fetch recent imports
$imports = $pdo->query("SELECT i.*, u.name as uploader FROM bank_statement_imports i JOIN users u ON u.id = i.uploaded_by ORDER BY upload_date DESC LIMIT 20")->fetchAll();
?>
<?php admin_page_start('Reconciliation Engine', ['active' => 'reconciliation.php']); ?>
... (rest of the file remains same)

<?php if (isset($message)): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if (isset($error)): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <label>Bank Name</label>
        <input type="text" name="bank_name" required>
        <label>Select Bank Statement (CSV/Excel)</label>
        <input type="file" name="statement_file" required>
        <button type="submit" style="margin-top:15px;">Upload & Reconcile</button>
    </form>
</div>

<div class="card">
    <h2>Recent Imports</h2>
    <table>
        <thead><tr><th>Filename</th><th>Bank</th><th>Date</th><th>Status</th><th>Uploader</th></tr></thead>
        <tbody>
            <?php foreach ($imports as $import): ?>
            <tr>
                <td><?= e($import['filename']) ?></td>
                <td><?= e($import['bank_name']) ?></td>
                <td><?= e($import['upload_date']) ?></td>
                <td><span class="badge <?= e($import['status']) ?>"><?= e($import['status']) ?></span></td>
                <td><?= e($import['uploader']) ?></td>
                <td><a href="reconciliation_details.php?id=<?= e($import['id']) ?>">View Details</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php admin_page_end(); ?>
