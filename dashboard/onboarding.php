<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];
$currentUser = current_user($pdo);

if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}

// Ensure the table exists or schema is ready, just in case
$stmt = $pdo->prepare("SELECT * FROM grower_farms WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$farm = $stmt->fetch();

$docStmt = $pdo->prepare("SELECT COUNT(*) FROM document_requirements WHERE user_id = ? AND verification_status = 'verified'");
$docStmt->execute([$userId]);
$docsVerified = (int) $docStmt->fetchColumn() > 0;

dashboard_page_start('Onboarding', ['active' => 'onboarding.php']);
?>
<style>
  .onboarding-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
  .step-card { background: #fff; border: 1px solid var(--border-color); padding: 25px; border-radius: 12px; display: flex; flex-direction: column; gap: 15px; box-shadow: var(--shadow-sm); }
  .step-card h3 { display: flex; align-items: center; gap: 10px; margin: 0; color: var(--text-primary); }
  .step-card i { color: var(--primary-green); font-size: 1.2rem; }
  .status-badge { font-size: 0.8rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; align-self: flex-start; }
  .status-done { background: #eef8ef; color: var(--primary-green); }
  .status-pending { background: #fff7ed; color: #d97706; }
</style>

<div class="card">
  <h2>Welcome to NATCODEV!</h2>
  <p>You're almost ready to maximize your coconut farm. Complete these steps to unlock full platform features like marketplace selling, training certification, and financial tools.</p>
  
  <div class="onboarding-grid">
    <!-- Step 1 -->
    <div class="step-card">
      <span class="status-badge <?= $farm ? 'status-done' : 'status-pending' ?>"><?= $farm ? 'Completed' : 'Pending' ?></span>
      <h3><i class="fas fa-map-marker-alt"></i> Farm Location</h3>
      <p>Define your farm's location so we can provide accurate weather and yield tracking.</p>
      <a href="fields.php" class="btn <?= $farm ? 'btn-outline' : '' ?>"><?= $farm ? 'View Location' : 'Set Location' ?></a>
    </div>
    
    <!-- Step 2 -->
    <div class="step-card">
      <span class="status-badge <?= ($farm && (int)($farm['coconut_stands'] ?? 0) > 0) ? 'status-done' : 'status-pending' ?>"><?= ($farm && (int)($farm['coconut_stands'] ?? 0) > 0) ? 'Completed' : 'Pending' ?></span>
      <h3><i class="fas fa-tree"></i> Coconut Stands</h3>
      <p>Tell us how many coconut trees you have to help us forecast your production.</p>
      <a href="fields.php" class="btn">Update Stands</a>
    </div>
    
    <!-- Step 3 -->
    <div class="step-card">
      <span class="status-badge <?= $docsVerified ? 'status-done' : 'status-pending' ?>"><?= $docsVerified ? 'Completed' : 'Pending' ?></span>
      <h3><i class="fas fa-file-alt"></i> Upload Documents</h3>
      <p>Submit your ID and farm evidence to get verified for subsidies and training.</p>
      <a href="documents.php" class="btn">Upload Docs</a>
    </div>
  </div>
</div>

<?php dashboard_page_end(); ?>
