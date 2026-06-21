<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);

$importId = $_GET['id'] ?? null;
if (!$importId) die("Import ID required");

// Fetch import details
$import = $pdo->prepare("SELECT * FROM bank_statement_imports WHERE id = ?");
$import->execute([$importId]);
$importData = $import->fetch();

// Fetch matches
$matches = $pdo->prepare("
    SELECT m.*, wt.reference as internal_ref, wt.amount as internal_amount
    FROM reconciliation_matches m
    LEFT JOIN wallet_transactions wt ON m.transaction_id = wt.id
    WHERE m.import_id = ?
");
$matches->execute([$importId]);
$results = $matches->fetchAll();
?>
<?php admin_page_start('Reconciliation Details: ' . e($importData['filename']), ['active' => 'reconciliation.php']); ?>

<div class="card">
    <table>
        <thead><tr><th>External Ref</th><th>Ext Amount</th><th>Internal Ref</th><th>Int Amount</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($results as $m): ?>
            <tr>
                <td><?= e($m['external_reference']) ?></td>
                <td>₦<?= number_format($m['amount'], 2) ?></td>
                <td><?= e($m['internal_ref'] ?? 'N/A') ?></td>
                <td><?= $m['transaction_id'] ? '₦' . number_format($m['internal_amount'], 2) : 'N/A' ?></td>
                <td><span class="badge <?= e($m['match_status']) ?>"><?= e($m['match_status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php admin_page_end(); ?>
