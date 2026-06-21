<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/../../lib/admin-user-import.php';

$pageTitle = 'Batch Import - NATCODEV Registry';
$activeNav = 'import';

$counts = rx_rows($pdo, "
    SELECT status, COUNT(*) total 
    FROM user_import_records 
    GROUP BY status
");

$recentImports = rx_rows($pdo, "
    SELECT * FROM user_import_records 
    ORDER BY created_at DESC 
    LIMIT 100
");

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Batch Import</h1>
    <p class="page-subtitle">Onboard legacy farmers, growers, and staff in bulk from spreadsheets.</p>
  </div>
</div>

<div class="grid-4" style="margin-bottom:24px">
    <?php foreach($counts as $c): ?>
        <div class="stat-card">
            <div class="stat-card-label"><?= rx_e(ucwords(str_replace('_', ' ', $c['status']))) ?></div>
            <div class="stat-card-value"><?= number_format((int)$c['total']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Upload New Spreadsheet</h3></div>
        <form action="../admin/import-users.php" method="post" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="form-group">
                    <label class="form-label">CSV or XLSX File</label>
                    <input type="file" name="user_upload" class="form-input" accept=".csv,.xlsx" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Default Role</label>
                    <select name="default_role" class="form-select">
                        <option value="grower">Grower</option>
                        <option value="field_agent">Field Agent</option>
                        <option value="agronomist">Agronomist</option>
                    </select>
                </div>
            </div>
            <div class="card-header" style="justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Start Import Process</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Import Instructions</h3></div>
        <div class="card-body">
            <p>Ensure your spreadsheet contains these exact column headers (case-insensitive):</p>
            <ul style="margin:15px 0 15px 20px; font-size:12px; line-height:1.6">
                <li><strong>NAME:</strong> Full name of the person or business</li>
                <li><strong>PHONE NUMBER:</strong> Primary contact phone</li>
                <li><strong>EMAIL ADDRESS:</strong> Primary email (required for staff)</li>
                <li><strong>ADDRESS:</strong> Full physical address</li>
                <li><strong>STATE / LGA:</strong> Operating location</li>
            </ul>
            <p class="muted">Growers will receive an engagement confirmation link before being fully added to the live registry.</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Audit Log: Recent Import Rows</h3></div>
    <div class="card-body p0">
        <table>
            <thead>
                <tr>
                    <th>Batch/Row</th>
                    <th>Identity</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recentImports as $row): ?>
                    <tr>
                        <td><strong><?= rx_e($row['batch_ref']) ?></strong><br><small>Row <?= $row['source_row'] ?></small></td>
                        <td>
                            <strong><?= rx_e($row['name']) ?></strong><br>
                            <small><?= rx_e($row['email'] ?: $row['phone']) ?></small>
                        </td>
                        <td><?= rx_e($row['role']) ?></td>
                        <td><span class="status-badge <?= rx_status_class($row['status']) ?>"><?= rx_e($row['status']) ?></span></td>
                        <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
