<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();
admin_ensure_schema($pdo);
app_ensure_certificate_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo);
admin_require_feature($pdo, 'certificates');

$results = [];
$refsInput = '';

function admin_certificate_lookup(PDO $pdo, string $ref): ?array
{
    $certificate = findCertificate($ref, $pdo);
    if ($certificate) {
        if (empty($certificate['expires_at'] ?? null) && !empty($certificate['issued_at'])) {
            $certificate['expires_at'] = grower_certificate_expires_at($pdo, (string) $certificate['issued_at']);
        }
        return [
            'ref' => $ref,
            'found' => true,
            'type' => 'Verified Grower Certificate',
            'holder' => (string) ($certificate['name'] ?? ''),
            'subject' => (string) ($certificate['app_ref'] ?? ''),
            'status' => (string) ($certificate['status'] ?? ''),
            'issued_at' => (string) ($certificate['issued_at'] ?? ''),
            'expires_at' => (string) ($certificate['expires_at'] ?? ''),
            'revoked_reason' => (string) ($certificate['revoked_reason'] ?? ''),
        ];
    }

    $stmt = $pdo->prepare("
        SELECT c.certificate_ref, c.status, c.issued_at, NULL expires_at, NULL revoked_reason,
               u.name holder, w.title subject
        FROM academy_certificates c
        JOIN users u ON u.id = c.user_id
        JOIN webinars w ON w.id = c.webinar_id
        WHERE c.certificate_ref = ?
        LIMIT 1
    ");
    $stmt->execute([$ref]);
    $certificate = $stmt->fetch();
    if ($certificate) {
        return [
            'ref' => $ref,
            'found' => true,
            'type' => 'Academy Course Certificate',
            'holder' => (string) $certificate['holder'],
            'subject' => (string) $certificate['subject'],
            'status' => (string) $certificate['status'],
            'issued_at' => (string) $certificate['issued_at'],
            'expires_at' => '',
            'revoked_reason' => '',
        ];
    }

    $stmt = $pdo->prepare("
        SELECT c.certificate_ref, c.status, c.issued_at, NULL expires_at, NULL revoked_reason,
               u.name holder, g.title subject
        FROM academy_group_certificates c
        JOIN users u ON u.id = c.user_id
        JOIN academy_certificate_groups g ON g.id = c.group_id
        WHERE c.certificate_ref = ?
        LIMIT 1
    ");
    $stmt->execute([$ref]);
    $certificate = $stmt->fetch();
    if ($certificate) {
        return [
            'ref' => $ref,
            'found' => true,
            'type' => 'Grouped Academy Certificate',
            'holder' => (string) $certificate['holder'],
            'subject' => (string) $certificate['subject'],
            'status' => (string) $certificate['status'],
            'issued_at' => (string) $certificate['issued_at'],
            'expires_at' => '',
            'revoked_reason' => '',
        ];
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $results[] = ['ref' => '', 'found' => false, 'validity' => 'Invalid security token'];
    } else {
        $refsInput = trim((string) ($_POST['certificate_refs'] ?? ''));
        $refs = preg_split('/[\r\n,;\t ]+/', $refsInput) ?: [];
        $refs = array_values(array_unique(array_filter(array_map('trim', $refs))));
        foreach (array_slice($refs, 0, 500) as $ref) {
            $row = admin_certificate_lookup($pdo, $ref);
            if (!$row) {
                $results[] = ['ref' => $ref, 'found' => false, 'type' => '', 'holder' => '', 'subject' => '', 'status' => 'not_found', 'issued_at' => '', 'expires_at' => '', 'validity' => 'Not found'];
                continue;
            }
            $expired = !empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time();
            $row['validity'] = ($row['status'] === 'issued' && !$expired) ? 'Valid' : ($expired ? 'Expired' : ucfirst($row['status']));
            $results[] = $row;
        }
    }
}

admin_page_start('Batch Certificate Verification', [
    'active' => 'certificate-batch-verification.php',
    'description' => 'Paste participation or Academy certificate references and verify them in one back-office run.',
    'wide' => true,
]);
?>
<form class="panel" method="post">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label>Certificate References</label>
  <textarea name="certificate_refs" rows="8" placeholder="Paste one reference per line, or separate by commas/spaces."><?= e($refsInput) ?></textarea>
  <p class="muted">Supports grower participation certificates, Academy course certificates, and grouped Academy certificates. Maximum 500 references per run.</p>
  <button type="submit">Verify Batch</button>
</form>

<?php if ($results): ?>
  <section class="panel" style="margin-top:16px;">
    <h2>Verification Results</h2>
    <table>
      <thead><tr><th>Reference</th><th>Validity</th><th>Type</th><th>Holder</th><th>Subject</th><th>Issued</th><th>Expires</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($results as $row): ?>
          <tr>
            <td><?= e((string) $row['ref']) ?></td>
            <td><span class="badge <?= ($row['validity'] ?? '') === 'Valid' ? 'verified' : 'pending' ?>"><?= e((string) ($row['validity'] ?? '')) ?></span></td>
            <td><?= e((string) ($row['type'] ?? '')) ?></td>
            <td><?= e((string) ($row['holder'] ?? '')) ?></td>
            <td><?= e((string) ($row['subject'] ?? '')) ?></td>
            <td><?= e((string) ($row['issued_at'] ?? '')) ?></td>
            <td><?= e((string) ($row['expires_at'] ?? 'Permanent')) ?></td>
            <td><?= e((string) ($row['status'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>
<?php admin_page_end(); ?>
