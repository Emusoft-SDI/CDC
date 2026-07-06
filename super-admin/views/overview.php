<?php defined('NATCODEV_SUPER_ADMIN') || exit; ?>
<section class="super-dashboard">
  <a class="command-card" href="index.php?view=users">
    <span>User Governance</span>
    <strong><?= (int) $stats['privileged'] ?> privileged profiles</strong>
    <small>Promote, suspend, restore, reset passwords, and control root access.</small>
  </a>
  <a class="command-card" href="index.php?view=controls">
    <span>Access & Policy</span>
    <strong><?= count(super_admin_feature_catalog()) ?> controlled features</strong>
    <small>Define what each platform role can see and do inside operations.</small>
  </a>
  <a class="command-card" href="index.php?view=disaster">
    <span>Recovery</span>
    <strong><?= count($siteNodes) ?> site nodes</strong>
    <small>Backups, standby sites, sync health, and restore evidence.</small>
  </a>
  <a class="command-card operations" href="../admin/admin.php">
    <span>Operational Handoff</span>
    <strong>Open Admin Console</strong>
    <small>Applications, verification, support, field network, and daily registry work live there.</small>
  </a>
</section>

<section class="readiness-grid">
  <article>
    <span>Permission Boundary</span>
    <strong>Enforced</strong>
    <small>Admin pages now check the Super Admin access matrix before showing menus or allowing direct URL access.</small>
  </article>
  <article>
    <span>Audit Trail</span>
    <strong><?= count($auditRows) ?> recent events</strong>
    <small>Privileged actions are recorded for review from Access & Policy.</small>
  </article>
  <article>
    <span>Account Recovery</span>
    <strong><?= (int) $stats['archived'] ?> archived</strong>
    <small>Deleted users are archived and can be restored through User Governance.</small>
  </article>
</section>

<section class="panel">
  <div class="section-head">
    <div>
      <h2>User Governance Snapshot</h2>
      <p>Quick role and account-health summary. Open the full review only when you need to edit, reset passwords, suspend, archive, or delete users.</p>
    </div>
    <div class="actions">
      <a class="button" href="index.php?view=users">Open User Governance</a>
      <a class="button secondary" href="index.php?export=users">Export CSV</a>
    </div>
  </div>
  <div class="role-summary">
    <?php foreach ($roleSummary as $roleKey => $summary): ?>
      <a href="index.php?view=users&role=<?= e($roleKey) ?>">
        <span><?= e($summary['label']) ?></span>
        <strong><?= (int) $summary['total'] ?></strong>
      </a>
    <?php endforeach; ?>
  </div>
</section>
