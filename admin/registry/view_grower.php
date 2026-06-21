<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'View Grower - NATCODEV Registry';
$activeNav = 'growers';

// Validate application ID
$appId = (int)($_GET['app_id'] ?? 0);
if ($appId <= 0) {
    header('Location: growers.php?error=Invalid+application+ID');
    exit;
}

// Fetch application details (read‑only)
$stmt = $pdo->prepare(
    'SELECT a.id, a.name, a.email, a.phone, a.commitments, a.location, a.farm_size, a.created_at, a.confirmed
     FROM applications a
     WHERE a.id = ? LIMIT 1'
);
$stmt->execute([$appId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$app) {
    header('Location: growers.php?error=Application+not+found');
    exit;
}

// Fetch latest issued certificate for this application, if any
$cert = null;
$certStmt = $pdo->prepare('SELECT * FROM certificates WHERE application_id = ? AND status = "issued" ORDER BY issued_at DESC LIMIT 1');
$certStmt->execute([$appId]);
$cert = $certStmt->fetch(PDO::FETCH_ASSOC);

require __DIR__ . '/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Grower Details</h1>
        <p class="page-subtitle">Application #<?= $app['id'] ?></p>
    </div>
    <div class="header-actions">
        <a href="growers.php" class="btn btn-secondary">Back to List</a>
        <?php if ((int)$app['confirmed'] === 1): ?>
            <a href="edit_grower.php?app_id=<?= $app['id'] ?>" class="btn btn-primary" style="margin-left:8px;">Edit Grower</a>
            <?php if ($cert): ?>
                <a href="<?= $cert['certificate_path'] ?>" class="btn btn-success" style="margin-left:8px;" target="_blank">View Certificate</a>
                <a href="<?= $cert['certificate_pdf_path'] ?>" class="btn btn-success" style="margin-left:8px;" target="_blank">Download PDF</a>
            <?php else: ?>
                <form action="inc/actions.php" method="post" style="display:inline; margin-left:8px;">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="issue_certificate">
                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="page" value="../growers.php">
                    <button type="submit" class="btn btn-success btn-sm">Issue Certificate</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="max-width:900px; margin:auto;">
    <div class="card-body" style="display:flex; flex-direction:column; gap:12px;">
        <div><strong>Name:</strong> <?= rx_e($app['name']) ?></div>
        <div><strong>Email:</strong> <?= rx_e($app['email']) ?></div>
        <div><strong>Phone:</strong> <?= rx_e($app['phone']) ?></div>
        <div><strong>Type:</strong> <?= rx_e($app['commitments']) ?></div>
        <div><strong>State:</strong> <?= rx_e($app['location'] ?: 'Unassigned') ?></div>
        <div><strong>Farm Size (ha):</strong> <?= (float) $app['farm_size'] ?></div>
        <div><strong>Registered On:</strong> <?= date('M j, Y', strtotime($app['created_at'])) ?></div>
        <div><strong>Status:</strong> <?= $app['confirmed'] ? 'Confirmed' : 'Pending Confirmation' ?></div>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
