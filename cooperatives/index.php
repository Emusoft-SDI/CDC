<?php
declare(strict_types=1);
require_once __DIR__ . '/_cooperative.php';

$pdo = coop_pdo();
$user = coop_require($pdo);
$cooperative = cooperative_current($pdo);
$cooperativeId = (int) ($cooperative['id'] ?? 0);
$cooperativeName = (string) ($cooperative['name'] ?? 'Your Cooperative');
$stateName = coop_state_name($pdo, (int) ($cooperative['state_id'] ?? 0));
$lgaName = (string) ($cooperative['lga'] ?? '');
$status = (string) ($cooperative['status'] ?? 'pending');

$stats = cooperative_stats($pdo, $cooperativeId);
$pending = cooperative_pending_members($pdo, $cooperativeId);
$removed = (int) ($stats['removed'] ?? 0);
$recent = cooperative_member_list($pdo, $cooperativeId, '', '', 10);

// Fetch recent import batches for this cooperative
$importBatches = [];
if (app_table_exists($pdo, 'user_import_records')) {
    $batchStmt = $pdo->prepare("SELECT id, batch_ref, status, created_at FROM user_import_records WHERE source LIKE ? ORDER BY id DESC LIMIT 5");
    $batchStmt->execute([$cooperativeId . '%']);
    $importBatches = $batchStmt->fetchAll();
}

$activationRate = 0;
$totalMembers = (int) ($stats['members'] ?? 0) + $removed;
if ($totalMembers > 0) {
    $activationRate = round((($totalMembers - $removed) / $totalMembers) * 100, 1);
}

coop_header(
    'Cooperative Dashboard',
    'Welcome back — manage ' . $cooperativeName . ', its members, imports, and profile.',
    $user,
    'home'
);
?>
<style>.profile-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.profile-cell small{display:block;font-size:.72rem;text-transform:uppercase;color:var(--muted);font-weight:800}.profile-cell b{display:block;margin-top:3px}.profile-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}@media(max-width:980px){.profile-grid{grid-template-columns:1fr}}</style>

<section class="card">
  <div class="profile-head">
    <div>
      <h2><?= e($cooperativeName) ?></h2>
      <p style="color:var(--muted);margin:4px 0 0">Cooperative profile — partnership account linked to this workspace.</p>
    </div>
    <span class="badge <?= $status === 'active' ? '' : ($status === 'suspended' ? 'bad' : 'warn') ?>"><?= e($status) ?></span>
  </div>
  <div class="profile-grid" style="margin-top:14px">
    <div class="profile-cell"><small>Registration number</small><b><?= e((string) ($cooperative['registration_number'] ?? '') !== '' ? (string) $cooperative['registration_number'] : 'Not registered') ?></b></div>
    <div class="profile-cell"><small>State</small><b><?= e($stateName !== '' ? $stateName : 'Not set') ?></b></div>
    <div class="profile-cell"><small>Local government area</small><b><?= e($lgaName !== '' ? $lgaName : 'Not set') ?></b></div>
    <div class="profile-cell"><small>Address</small><b><?= e((string) ($cooperative['address'] ?? '') !== '' ? (string) $cooperative['address'] : 'Not set') ?></b></div>
    <div class="profile-cell"><small>Contact person</small><b><?= e((string) ($cooperative['contact_name'] ?? '') !== '' ? (string) $cooperative['contact_name'] : 'Not set') ?></b></div>
    <div class="profile-cell"><small>Contact email</small><b><?= e((string) ($cooperative['contact_email'] ?? '') !== '' ? (string) $cooperative['contact_email'] : 'Not set') ?></b></div>
    <div class="profile-cell"><small>Contact phone</small><b><?= e((string) ($cooperative['contact_phone'] ?? '') !== '' ? (string) $cooperative['contact_phone'] : 'Not set') ?></b></div>
    <div class="profile-cell"><small>Member since</small><b><?= e($cooperative['created_at'] ? date('M j, Y', strtotime((string) $cooperative['created_at'])) : 'Not set') ?></b></div>
  </div>
</section>

<div class="kpis">
  <div class="kpi"><i class="fas fa-users"></i><b><?= number_format((int) ($stats['members'] ?? 0)) ?></b><span>Active members</span></div>
  <div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($pending) ?></b><span>Pending activation</span></div>
  <div class="kpi"><i class="fas fa-clipboard-list"></i><b><?= number_format((int) ($stats['applications'] ?? 0)) ?></b><span>Applications</span></div>
  <div class="kpi"><i class="fas fa-seedling"></i><b><?= number_format((int) ($stats['farms'] ?? 0)) ?></b><span>Farms</span></div>
  <div class="kpi"><i class="fas fa-user-minus"></i><b><?= number_format($removed) ?></b><span>Removed</span></div>
  <div class="kpi"><i class="fas fa-chart-line"></i><b><?= number_format($activationRate, 1) ?>%</b><span>Activation rate</span></div>
</div>

<div class="grid">
  <!-- Analytics / Import Progress Card -->
  <section class="card span-4">
    <h2>Import History</h2>
    <?php if ($importBatches): ?>
      <div class="list">
        <?php foreach ($importBatches as $batch): ?>
          <div class="row">
            <span><strong><?= e((string) $batch['batch_ref']) ?></strong><br><small><?= e(date('M j, Y g:i A', strtotime((string) $batch['created_at']))) ?></small></span>
            <span class="badge <?= (string) $batch['status'] === 'completed' ? '' : ((string) $batch['status'] === 'processing' ? 'warn' : 'bad') ?>"><?= e((string) $batch['status']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="margin-top:14px"><a class="btn light" href="import.php"><i class="fas fa-file-import"></i> New Import</a></p>
    <?php else: ?>
      <p style="color:var(--muted);padding:10px 0">No imports yet.</p>
      <p style="margin-top:14px"><a class="btn light" href="import.php"><i class="fas fa-file-import"></i> Import Members</a></p>
    <?php endif; ?>
  </section>

  <!-- Quick Actions -->
  <section class="card span-4">
    <h2>Quick Actions</h2>
    <div class="list">
      <a class="row" href="import.php"><strong>1. Import members</strong><span class="badge">CSV</span></a>
      <a class="row" href="members.php"><strong>2. Manage members</strong><span class="badge">List</span></a>
      <a class="row" href="profile.php"><strong>3. Edit profile</strong><span class="badge">Settings</span></a>
      <a class="row" href="../market/index.php"><strong>4. Marketplace</strong><span class="badge">Trade</span></a>
      <a class="row" href="../academy/index.php"><strong>5. Academy</strong><span class="badge">Learn</span></a>
      <a class="row" href="academy-sponsorships.php"><strong>6. Academy Sponsorships & Recommendations</strong><span class="badge">Learn</span></a>
      <a class="row" href="../support/index.php"><strong>7. Support Desk</strong><span class="badge">Help</span></a>
    </div>
  </section>

  <!-- Recent Activity -->
  <section class="card span-4">
    <h2>Recent Members</h2>
    <div class="list">
      <?php foreach ($recent as $row): ?>
        <div class="row">
          <span>
            <strong><?= e((string) ($row['name'] ?? '')) ?></strong><br>
            <small><?= e((string) ($row['email'] ?? '')) ?><?php if ((string) ($row['phone'] ?? '') !== ''): ?> / <?= e((string) $row['phone']) ?><?php endif; ?><?php if ((string) ($row['app_ref'] ?? '') !== ''): ?> / <?= e((string) $row['app_ref']) ?><?php endif; ?></small>
          </span>
          <span>
            <span class="badge"><?= e((string) ($row['role_in_cooperative'] ?? 'member')) ?></span>
            <span class="badge <?= (string) ($row['status'] ?? 'active') === 'active' ? '' : 'bad' ?>"><?= e((string) ($row['status'] ?? 'active')) ?></span>
          </span>
        </div>
      <?php endforeach; ?>
      <?php if (!$recent): ?><div class="row"><span>No members are linked to this cooperative yet. Import members or wait for engagement confirmations.</span></div><?php endif; ?>
    </div>
    <p style="margin-top:14px"><a class="btn light" href="members.php"><i class="fas fa-users"></i> View All Members</a></p>
  </section>
</div>
<?php coop_footer(); ?>
