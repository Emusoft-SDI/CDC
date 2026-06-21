<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Edit Grower - NATCODEV Registry';
$activeNav = 'growers';

$appId = (int)($_GET['app_id'] ?? 0);
if ($appId <= 0) {
    header('Location: growers.php?error=Invalid+application+ID');
    exit;
}

// Fetch application data
$stmt = $pdo->prepare('SELECT a.id, a.name, a.email, a.phone, a.commitments, a.location, a.farm_size FROM applications a WHERE a.id = ? LIMIT 1');
$stmt->execute([$appId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$app) {
    header('Location: growers.php?error=Application+not+found');
    exit;
}

require __DIR__ . '/layout/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Edit Grower</h1>
    <p class="page-subtitle">Modify details for application #<?= $app['id'] ?></p>
  </div>
  <div class="header-actions">
    <a href="growers.php" class="btn btn-secondary">Back to List</a>
  </div>
</div>

<form action="inc/actions.php" method="post">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="update_grower">
  <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
  <input type="hidden" name="page" value="../growers.php">
  <div class="card-body">
    <div class="form-group">
      <label class="form-label">Full Name / Business Name</label>
      <input type="text" name="name" class="form-input" required value="<?= rx_e($app['name']) ?>">
    </div>
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-input" required value="<?= rx_e($app['email']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number</label>
        <input type="text" name="phone" class="form-input" value="<?= rx_e($app['phone']) ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Type</label>
        <select name="type" class="form-select">
          <?php $types = ['Individual','Group','Cooperative'];
          foreach ($types as $t): ?>
            <option value="<?= $t ?>" <?= $t === $app['commitments'] ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">State</label>
        <select name="state" class="form-select">
          <option value="" <?= $app['location'] === '' ? 'selected' : '' ?>>Select State</option>
          <?php foreach (rx_rows($pdo, "SELECT state_name FROM nigeria_states ORDER BY state_name") as $s): ?>
            <option value="<?= rx_e($s['state_name']) ?>" <?= $s['state_name'] === $app['location'] ? 'selected' : '' ?>><?= rx_e($s['state_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Farm Size (ha)</label>
      <input type="number" step="0.01" min="0" name="farm_size" class="form-input" required value="<?= (float) $app['farm_size'] ?>">
    </div>
  </div>
  <div class="card-header" style="justify-content:flex-end; margin-top:20px;">
    <a href="growers.php" class="btn btn-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary" style="margin-left:10px;">Save Changes</button>
  </div>
</form>
<?php require __DIR__ . '/layout/footer.php'; ?>
