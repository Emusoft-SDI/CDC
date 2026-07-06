<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../provider/_provider.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$stmt = $pdo->query("SELECT c.*, pr.company_name, pr.email as provider_email FROM provider_accreditation_certificates c JOIN provider_registry pr ON pr.id = c.provider_id ORDER BY c.issued_at DESC");
$rows = $stmt->fetchAll();

admin_page_start('Provider Certificates', [
    'active' => 'certificates.php',
    'description' => 'Manage provider accreditation certificates.',
]);
?>
<section class="panel">
  <table class="narrow">
    <thead><tr><th>Ref</th><th>Provider</th><th>Email</th><th>Status</th><th>Issued</th><th>Actions</th></tr></thead>
    <tbody>
<?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['certificate_ref']) ?></td>
        <td><?= e($r['company_name']) ?></td>
        <td><?= e($r['provider_email']) ?></td>
        <td><?= e($r['status']) ?><?= $r['revoked_at'] ? ' (revoked)' : '' ?></td>
        <td><?= e($r['issued_at']) ?></td>
        <td>
          <a class="button" href="manage_certificate.php?ref=<?= urlencode($r['certificate_ref']) ?>">Manage</a>
          <form method="post" action="manage_certificate.php" style="display:inline" onsubmit="return confirm('Revoke certificate <?= htmlspecialchars($r['certificate_ref']) ?>?');">
            <input type="hidden" name="ref" value="<?= htmlspecialchars($r['certificate_ref']) ?>">
            <input type="hidden" name="action" value="REVOKE">
            <input type="hidden" name="reason" value="Admin revoke">
            <button class="danger" type="submit">Revoke</button>
          </form>
          <form method="post" action="manage_certificate.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Suspend certificate <?= htmlspecialchars($r['certificate_ref']) ?>?');">
            <input type="hidden" name="ref" value="<?= htmlspecialchars($r['certificate_ref']) ?>">
            <input type="hidden" name="action" value="SUSPEND">
            <input type="hidden" name="reason" value="Admin suspend">
            <button type="submit">Suspend</button>
          </form>
          <form method="post" action="manage_certificate.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Reinstate certificate <?= htmlspecialchars($r['certificate_ref']) ?>?');">
            <input type="hidden" name="ref" value="<?= htmlspecialchars($r['certificate_ref']) ?>">
            <input type="hidden" name="action" value="REINSTATE">
            <button type="submit">Reinstate</button>
          </form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_page_end(); ?>
