<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../provider/_provider.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$stmt = $pdo->query("SELECT c.*, pr.company_name, pr.email as provider_email FROM provider_accreditation_certificates c JOIN provider_registry pr ON pr.id = c.provider_id AND pr.deleted_at IS NULL ORDER BY c.issued_at DESC");
$rows = $stmt->fetchAll();

$certTotal = count($rows);
$certActive = count(array_filter($rows, static fn($r): bool => in_array(strtolower((string) $r['status']), ['active', 'issued', 'valid'], true)));
$certRevoked = count(array_filter($rows, static fn($r): bool => !empty($r['revoked_at']) || strtolower((string) $r['status']) === 'revoked'));
$certSuspended = count(array_filter($rows, static fn($r): bool => strtolower((string) $r['status']) === 'suspended'));

admin_page_start('Provider Certificates', [
    'active' => 'certificates.php',
    'description' => 'Manage provider accreditation certificates: issue, suspend, revoke and reinstate.',
    'wide' => true,
]);
?>
<?= admin_kpi_grid([
    ['Certificates', number_format($certTotal), 'Issued provider certs', 'fa-certificate', ''],
    ['Active', number_format($certActive), 'Valid certificates', 'fa-circle-check', 'blue'],
    ['Suspended', number_format($certSuspended), 'Temporarily on hold', 'fa-circle-pause', 'orange'],
    ['Revoked', number_format($certRevoked), 'Withdrawn', 'fa-ban', 'red'],
]) ?>

<section class="panel">
  <div class="user-toolbar">
    <h2 style="margin:0">Provider Certificates</h2>
    <span class="meta"><?= number_format($certTotal) ?> certificate(s)</span>
  </div>
  <div class="record-list">
    <?php foreach ($rows as $r): ?>
      <?php
        $status = strtolower((string) $r['status']);
        $statusTone = in_array($status, ['active', 'issued', 'valid'], true) ? 'ok' : (in_array($status, ['revoked', 'rejected'], true) ? 'bad' : 'warn');
      ?>
      <article class="record-row">
        <span class="record-avatar doc"><i class="fas fa-certificate"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($r['company_name']) ?>
            <span class="tag <?= e($statusTone) ?>"><?= e(ucwords($status)) ?></span>
            <?php if (!empty($r['revoked_at'])): ?><span class="tag bad">Revoked <?= e(date('M j, Y', strtotime((string) $r['revoked_at']))) ?></span><?php endif; ?>
          </div>
          <div class="record-contact">
            <span><i class="far fa-envelope"></i><?= e($r['provider_email']) ?></span>
            <span><i class="far fa-calendar"></i>Issued <?= e(date('M j, Y', strtotime((string) $r['issued_at']))) ?></span>
          </div>
        </div>
        <div class="record-meta">
          <span class="ref-pill"><i class="fas fa-hashtag"></i><?= e($r['certificate_ref']) ?></span>
        </div>
        <div class="record-actions">
          <a class="button secondary sm" href="manage_certificate.php?ref=<?= urlencode($r['certificate_ref']) ?>"><i class="fas fa-gear"></i> Manage</a>
          <details class="user-manage">
            <summary class="button secondary sm"><i class="fas fa-ellipsis"></i> Actions</summary>
            <form class="user-manage-form" method="post" action="manage_certificate.php">
              <input type="hidden" name="ref" value="<?= e($r['certificate_ref']) ?>">
              <label class="field"><span>Action</span>
                <select name="action">
                  <option value="REVOKE">Revoke</option>
                  <option value="SUSPEND">Suspend</option>
                  <option value="REINSTATE">Reinstate</option>
                </select>
              </label>
              <label class="field"><span>Reason</span><input type="text" name="reason" value="Admin action"></label>
              <button type="submit" class="sm" onclick="return confirm('Apply this certificate action?');"><i class="fas fa-check"></i> Apply action</button>
            </form>
          </details>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="record-empty">No provider certificates have been issued yet.</div><?php endif; ?>
  </div>
</section>
<?php admin_page_end(); ?>
