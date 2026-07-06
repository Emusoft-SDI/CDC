<?php
declare(strict_types=1);

$registryRequiredFeature = 'certificates';
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/../../lib/academy.php';

$pageTitle = 'Registry Certificate Central - NATCODEV Registry';
$activeNav = 'certificates';
$canRevokeImmediately = admin_current_user_is_super_admin($pdo);
academy_ensure_schema($pdo);
admin_ensure_action_request_schema($pdo);

$tabs = [
    'overview' => 'Overview',
    'grower' => 'Grower Registry',
    'provider' => 'Provider Accreditation',
    'academy' => 'Academy',
    'grouped' => 'Grouped Pathways',
    'revocations' => 'Revocations',
];
$tab = (string) ($_GET['tab'] ?? 'overview');
if (!isset($tabs[$tab])) {
    $tab = 'overview';
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = rx_per_page();
$offset = ($page - 1) * $limit;

$downloadRef = trim((string) ($_GET['download'] ?? ''));
if ($downloadRef !== '') {
    $stmt = $pdo->prepare("
        SELECT c.*, a.app_ref, a.name, a.location, a.farm_size
        FROM certificates c
        JOIN applications a ON a.id = c.application_id
        WHERE c.certificate_ref = ? OR c.qr_code_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$downloadRef, $downloadRef]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$certificate || ($certificate['status'] ?? '') !== 'issued') {
        http_response_code(404);
        exit('Certificate is unavailable.');
    }
    $relativePath = ltrim((string) ($certificate['certificate_pdf_path'] ?? ''), '/');
    $absolutePath = $relativePath !== '' ? dirname(__DIR__, 2) . '/' . $relativePath : '';
    if ($absolutePath === '' || !is_file($absolutePath)) {
        $safeRef = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $downloadRef) ?? 'certificate');
        $relativePath = 'certificates/' . trim($safeRef, '-') . '.pdf';
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
        $verifyUrl = (string) ($certificate['verification_url'] ?: app_base_url() . '/verify-certificate.php?ref=' . urlencode($downloadRef));
        $pdf = certificate_pdf_document([
            'display_ref' => $downloadRef,
            'certificate_ref' => $downloadRef,
            'issued_at' => (string) $certificate['issued_at'],
            'expires_at' => (string) ($certificate['expires_at'] ?? ''),
            'verification_url' => $verifyUrl,
            'app_ref' => (string) $certificate['app_ref'],
            'name' => (string) $certificate['name'],
            'location' => (string) $certificate['location'],
            'farm_size' => (string) $certificate['farm_size'],
        ]);
        if (file_put_contents($absolutePath, $pdf, LOCK_EX) === false) {
            http_response_code(500);
            exit('Certificate file could not be rebuilt.');
        }
        $pdo->prepare('UPDATE certificates SET certificate_pdf_path = ? WHERE id = ?')->execute([$relativePath, (int) $certificate['id']]);
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($absolutePath) . '"');
    header('Content-Length: ' . filesize($absolutePath));
    readfile($absolutePath);
    exit;
}

function cert_status_badge(?string $status): string
{
    $status = trim((string) ($status ?: 'pending'));
    return '<span class="status-badge ' . rx_e(rx_status_class($status)) . '">' . rx_e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

function cert_ref_link(string $ref): string
{
    return '<a href="../../verify-certificate.php?ref=' . rx_e(urlencode($ref)) . '" target="_blank" rel="noopener">' . rx_e($ref) . '</a>';
}

function cert_query_clause(string $alias, string $search, string $statusFilter, array &$params, array $columns): string
{
    $where = '1=1';
    if ($search !== '') {
        $likes = [];
        foreach ($columns as $column) {
            $likes[] = $column . ' LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $where .= ' AND (' . implode(' OR ', $likes) . ')';
    }
    if ($statusFilter !== '') {
        $where .= " AND {$alias}.status = ?";
        $params[] = $statusFilter;
    }
    return $where;
}

$growerCounts = [
    'total' => rx_scalar($pdo, "SELECT COUNT(*) FROM certificates"),
    'issued' => rx_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE status='issued'"),
    'revoked' => rx_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE status='revoked'"),
];
$hasProviderCertificates = app_table_exists($pdo, 'provider_accreditation_certificates') && app_table_exists($pdo, 'provider_registry');
$providerCounts = $hasProviderCertificates ? [
    'total' => rx_scalar($pdo, "SELECT COUNT(*) FROM provider_accreditation_certificates"),
    'issued' => rx_scalar($pdo, "SELECT COUNT(*) FROM provider_accreditation_certificates WHERE status='issued'"),
    'revoked' => rx_scalar($pdo, "SELECT COUNT(*) FROM provider_accreditation_certificates WHERE status='revoked'"),
] : ['total' => 0, 'issued' => 0, 'revoked' => 0];
$academyCounts = app_table_exists($pdo, 'academy_certificates') ? [
    'total' => rx_scalar($pdo, "SELECT COUNT(*) FROM academy_certificates"),
    'issued' => rx_scalar($pdo, "SELECT COUNT(*) FROM academy_certificates WHERE status='issued'"),
    'revoked' => rx_scalar($pdo, "SELECT COUNT(*) FROM academy_certificates WHERE status='revoked'"),
] : ['total' => 0, 'issued' => 0, 'revoked' => 0];
$groupCounts = app_table_exists($pdo, 'academy_group_certificates') ? [
    'total' => rx_scalar($pdo, "SELECT COUNT(*) FROM academy_group_certificates"),
    'issued' => rx_scalar($pdo, "SELECT COUNT(*) FROM academy_group_certificates WHERE status='issued'"),
    'revoked' => rx_scalar($pdo, "SELECT COUNT(*) FROM academy_group_certificates WHERE status='revoked'"),
] : ['total' => 0, 'issued' => 0, 'revoked' => 0];
$pendingRevocations = rx_scalar($pdo, "SELECT COUNT(*) FROM admin_action_requests WHERE request_type='revoke_certificate' AND target_table='certificates' AND status='pending'");

$rows = [];
$totalRows = 0;
$params = [];
if ($tab === 'grower') {
    $where = cert_query_clause('c', $search, $statusFilter, $params, ['c.certificate_ref', 'c.qr_code_hash', 'a.name', 'a.email', 'a.app_ref']);
    $totalRows = rx_scalar($pdo, "SELECT COUNT(*) FROM certificates c JOIN applications a ON a.id=c.application_id WHERE {$where}", $params);
    $rows = rx_rows($pdo, "SELECT c.*, a.name, a.email, a.app_ref, u.role, u.platform_role FROM certificates c JOIN applications a ON a.id=c.application_id LEFT JOIN users u ON u.id=c.user_id WHERE {$where} ORDER BY c.issued_at DESC, c.id DESC LIMIT {$limit} OFFSET {$offset}", $params);
} elseif ($tab === 'provider' && $hasProviderCertificates) {
    $where = cert_query_clause('c', $search, $statusFilter, $params, ['c.certificate_ref', 'pr.company_name', 'pr.contact_person', 'u.email']);
    $totalRows = rx_scalar($pdo, "SELECT COUNT(*) FROM provider_accreditation_certificates c LEFT JOIN provider_registry pr ON pr.id=c.provider_id LEFT JOIN users u ON u.id=c.user_id WHERE {$where}", $params);
    $rows = rx_rows($pdo, "SELECT c.*, pr.company_name, pr.contact_person, u.email, u.name user_name FROM provider_accreditation_certificates c LEFT JOIN provider_registry pr ON pr.id=c.provider_id LEFT JOIN users u ON u.id=c.user_id WHERE {$where} ORDER BY c.issued_at DESC, c.id DESC LIMIT {$limit} OFFSET {$offset}", $params);
} elseif ($tab === 'academy' && app_table_exists($pdo, 'academy_certificates')) {
    $where = cert_query_clause('c', $search, $statusFilter, $params, ['c.certificate_ref', 'u.name', 'u.email', 'w.title']);
    $totalRows = rx_scalar($pdo, "SELECT COUNT(*) FROM academy_certificates c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN webinars w ON w.id=c.webinar_id WHERE {$where}", $params);
    $rows = rx_rows($pdo, "SELECT c.*, u.name, u.email, w.title course_title FROM academy_certificates c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN webinars w ON w.id=c.webinar_id WHERE {$where} ORDER BY c.issued_at DESC, c.id DESC LIMIT {$limit} OFFSET {$offset}", $params);
} elseif ($tab === 'grouped' && app_table_exists($pdo, 'academy_group_certificates')) {
    $where = cert_query_clause('c', $search, $statusFilter, $params, ['c.certificate_ref', 'u.name', 'u.email', 'g.title']);
    $totalRows = rx_scalar($pdo, "SELECT COUNT(*) FROM academy_group_certificates c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN academy_certificate_groups g ON g.id=c.group_id WHERE {$where}", $params);
    $rows = rx_rows($pdo, "SELECT c.*, u.name, u.email, g.title group_title FROM academy_group_certificates c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN academy_certificate_groups g ON g.id=c.group_id WHERE {$where} ORDER BY c.issued_at DESC, c.id DESC LIMIT {$limit} OFFSET {$offset}", $params);
} elseif ($tab === 'revocations') {
    $where = "ar.request_type='revoke_certificate' AND ar.target_table='certificates'";
    if ($statusFilter !== '') {
        $where .= ' AND ar.status = ?';
        $params[] = $statusFilter;
    }
    if ($search !== '') {
        $where .= ' AND (ar.target_label LIKE ? OR ar.target_key LIKE ? OR ar.reason LIKE ? OR u.email LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
    }
    $totalRows = rx_scalar($pdo, "SELECT COUNT(*) FROM admin_action_requests ar LEFT JOIN users u ON u.id=ar.requested_by WHERE {$where}", $params);
    $rows = rx_rows($pdo, "SELECT ar.*, u.name requester_name, u.email requester_email FROM admin_action_requests ar LEFT JOIN users u ON u.id=ar.requested_by WHERE {$where} ORDER BY ar.created_at DESC LIMIT {$limit} OFFSET {$offset}", $params);
}

require __DIR__ . '/layout/header.php';
?>

<style>
.cert-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 22px}.cert-tabs a{padding:10px 13px;border:1px solid #d8e2dc;border-radius:8px;background:#fff;font-weight:800;text-decoration:none;color:#1f2937}.cert-tabs a.active{background:#1f8a55;color:#fff;border-color:#1f8a55}.cert-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:14px;margin-bottom:18px}.cert-kpi{background:#fff;border:1px solid #dfe8d8;border-radius:8px;padding:15px}.cert-kpi small{display:block;color:#667085;font-weight:800}.cert-kpi strong{display:block;color:#06451f;font-size:1.75rem;margin-top:6px}.cert-type{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:#667085;font-weight:900}
</style>

<div class="page-header">
  <div>
    <h1 class="page-title">Registry Certificate Central</h1>
    <p class="page-subtitle">One registry command center for grower, provider, Academy, grouped pathway, and revocation certificate records.</p>
  </div>
  <div class="header-actions">
    <button class="btn btn-secondary" onclick="openModal('batchModal')">Batch Verify Tool</button>
  </div>
</div>

<div class="cert-kpis">
  <div class="cert-kpi"><small>Grower Certificates</small><strong><?= number_format($growerCounts['total']) ?></strong><span><?= number_format($growerCounts['issued']) ?> issued / <?= number_format($growerCounts['revoked']) ?> revoked</span></div>
  <div class="cert-kpi"><small>Provider Accreditation</small><strong><?= number_format($providerCounts['total']) ?></strong><span><?= number_format($providerCounts['issued']) ?> issued / <?= number_format($providerCounts['revoked']) ?> revoked</span></div>
  <div class="cert-kpi"><small>Academy Certificates</small><strong><?= number_format($academyCounts['total']) ?></strong><span><?= number_format($academyCounts['issued']) ?> issued / <?= number_format($academyCounts['revoked']) ?> revoked</span></div>
  <div class="cert-kpi"><small>Grouped Pathways</small><strong><?= number_format($groupCounts['total']) ?></strong><span><?= number_format($groupCounts['issued']) ?> issued / <?= number_format($groupCounts['revoked']) ?> revoked</span></div>
  <div class="cert-kpi"><small>Pending Revocations</small><strong><?= number_format($pendingRevocations) ?></strong><span>Super Admin approval queue</span></div>
</div>

<nav class="cert-tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="<?= $tab === $key ? 'active' : '' ?>" href="certificates.php?tab=<?= rx_e($key) ?>"><?= rx_e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
  <div class="card">
    <div class="card-body">
      <h2 class="card-title">Certificate Families</h2>
      <div class="stats-grid">
        <a class="stat-card" href="certificates.php?tab=grower"><div class="stat-card-label">Grower Registry</div><div class="stat-card-value"><?= number_format($growerCounts['total']) ?></div><p>Farm and grower participation credentials.</p></a>
        <a class="stat-card" href="certificates.php?tab=provider"><div class="stat-card-label">Provider Accreditation</div><div class="stat-card-value"><?= number_format($providerCounts['total']) ?></div><p>Input/service provider accreditation certificates.</p></a>
        <a class="stat-card" href="certificates.php?tab=academy"><div class="stat-card-label">Academy</div><div class="stat-card-value"><?= number_format($academyCounts['total']) ?></div><p>Course completion certificates.</p></a>
        <a class="stat-card" href="certificates.php?tab=grouped"><div class="stat-card-label">Grouped Pathways</div><div class="stat-card-value"><?= number_format($groupCounts['total']) ?></div><p>Certificate pathway credentials.</p></a>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-header">
      <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="tab" value="<?= rx_e($tab) ?>">
        <input type="text" name="search" class="form-input" placeholder="Search certificate, holder, email, course..." value="<?= rx_e($search) ?>" style="max-width:340px">
        <select name="status" class="form-select" style="width:auto">
          <option value="">All statuses</option>
          <?php foreach (['issued','revoked','pending','approved','rejected','active'] as $status): ?>
            <option value="<?= rx_e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= rx_e(ucfirst($status)) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a class="btn btn-secondary" href="certificates.php?tab=<?= rx_e($tab) ?>">Reset</a>
      </form>
    </div>
    <div class="card-body p0">
      <table>
        <thead><tr><th>Certificate</th><th>Holder / Subject</th><th>Family</th><th>Issued / Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
              $ref = (string) ($row['certificate_ref'] ?? $row['target_key'] ?? '');
              $holder = (string) ($row['name'] ?? $row['company_name'] ?? $row['user_name'] ?? $row['target_label'] ?? 'Unknown');
              $email = (string) ($row['email'] ?? $row['requester_email'] ?? '');
              $subject = (string) ($row['app_ref'] ?? $row['course_title'] ?? $row['group_title'] ?? $row['contact_person'] ?? $row['reason'] ?? '');
              $status = (string) ($row['status'] ?? $row['certificate_status'] ?? 'pending');
              $issuedAt = (string) ($row['issued_at'] ?? $row['created_at'] ?? '');
            ?>
            <tr>
              <td><strong><?= $ref !== '' ? cert_ref_link($ref) : rx_e((string) ($row['target_label'] ?? 'Request #' . (int) ($row['id'] ?? 0))) ?></strong><br><small><?= rx_e($ref) ?></small></td>
              <td><strong><?= rx_e($holder) ?></strong><br><small><?= rx_e($email) ?><?= $subject !== '' ? ' / ' . rx_e($subject) : '' ?></small></td>
              <td><span class="cert-type"><?= rx_e($tabs[$tab] ?? $tab) ?></span></td>
              <td><?= $issuedAt !== '' ? rx_e(date('M j, Y', strtotime($issuedAt))) : '<span class="text-secondary">Not issued</span>' ?><br><?= cert_status_badge($status) ?></td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <?php if ($tab === 'grower' && $ref !== ''): ?><a href="certificates.php?download=<?= urlencode($ref) ?>" class="btn btn-sm btn-secondary">Download</a><?php endif; ?>
                  <?php if ($ref !== ''): ?><a href="../../verify-certificate.php?ref=<?= urlencode($ref) ?>" class="btn btn-sm btn-secondary" target="_blank" rel="noopener">Public Verify</a><?php endif; ?>
                  <?php
                    $targetTable = 'certificates';
                    if ($tab === 'provider') $targetTable = 'provider_accreditation_certificates';
                    if ($tab === 'academy') $targetTable = 'academy_certificates';
                    if ($tab === 'grouped') $targetTable = 'academy_group_certificates';
                  ?>
                  <?php if ($status === 'issued'): ?><button type="button" class="danger btn btn-sm btn-danger" onclick="openRevokeModal(<?= (int) $row['id'] ?>, <?= htmlspecialchars(json_encode($ref), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($targetTable), ENT_QUOTES, 'UTF-8') ?>)"><?= $canRevokeImmediately ? 'Revoke' : 'Request Revocation' ?></button><?php endif; ?>
                  <?php if ($status === 'revoked'): ?>
                    <form action="inc/actions.php" method="post" onsubmit="return confirm('Restore this revoked certificate?');" style="display:inline;">
                      <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="restore_certificate">
                      <input type="hidden" name="certificate_id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="target_table" value="<?= rx_e($targetTable) ?>">
                      <input type="hidden" name="page" value="../certificates.php?tab=<?= rx_e($tab) ?>">
                      <button type="submit" class="btn btn-sm btn-success"><?= $canRevokeImmediately ? 'Restore' : 'Request Restore' ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="5" style="text-align:center;padding:40px">No records found for this certificate family.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?= rx_pagination_links($totalRows, $limit, $page, 'certificates.php') ?>
<?php endif; ?>

<div class="modal-overlay" id="batchModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Batch Certificate Verification</h3>
      <button class="btn-icon" onclick="closeModal('batchModal')" style="margin:15px">x</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
      <input type="hidden" name="action" value="batch_verify_certificates">
      <input type="hidden" name="page" value="../certificates.php">
      <div class="card-body">
        <label class="form-label">Paste Grower Certificate References (one per line)</label>
        <textarea name="refs" class="form-textarea" rows="10" placeholder="CERT-NAT-001..."></textarea>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('batchModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="margin-left:10px">Verify Batch</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="revokeModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px"><?= $canRevokeImmediately ? 'Revoke Certificate' : 'Request Certificate Revocation' ?>: <span id="revokeCertRef"></span></h3>
      <button class="btn-icon" onclick="closeModal('revokeModal')" style="margin:15px">x</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
      <input type="hidden" name="action" value="revoke_certificate">
      <input type="hidden" name="certificate_id" id="revokeCertId">
      <input type="hidden" name="target_table" id="revokeTargetTable">
      <input type="hidden" name="page" id="revokePageUrl" value="../certificates.php?tab=<?= rx_e($tab) ?>">
      <div style="margin:20px">
        <label>Reason for Revocation</label>
        <textarea name="reason" rows="3" required placeholder="Required..."></textarea>
        <?php if (!$canRevokeImmediately): ?><p class="text-secondary" style="margin-top:0">This creates a Super Admin approval request before the certificate is marked revoked.</p><?php endif; ?>
      </div>
      <div style="text-align:right;margin:20px;padding-top:15px;border-top:1px solid #E5E7EB">
        <button type="button" class="btn btn-secondary" onclick="closeModal('revokeModal')">Cancel</button>
        <button type="submit" class="btn btn-danger" style="margin-left:10px"><?= $canRevokeImmediately ? 'Revoke Certificate' : 'Send to Super Admin' ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openRevokeModal(id, ref, table) {
    document.getElementById('revokeCertId').value = id;
    document.getElementById('revokeCertRef').textContent = ref;
    document.getElementById('revokeTargetTable').value = table || 'certificates';
    openModal('revokeModal');
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>