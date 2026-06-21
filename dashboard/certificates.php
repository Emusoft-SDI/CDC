<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = db();
$user = current_user($pdo);
if (!$user) {
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $user);

app_ensure_certificate_schema($pdo);
academy_ensure_schema($pdo);
wallet_ensure_schema($pdo);

$userId = (int) $user['id'];
$message = '';
$error = '';

$appStmt = $pdo->prepare("
    SELECT a.*
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    WHERE u.id = ?
    LIMIT 1
");
$appStmt->execute([$userId]);
$application = $appStmt->fetch() ?: [];
$applicationId = (int) ($application['id'] ?? 0);
$canIssueGrowerCertificate = $applicationId > 0 && canIssueCertificate($userId, $pdo);
$growerCertificatePaidRequired = grower_certificate_payment_required($pdo);
$growerCertificateAmount = grower_certificate_amount($pdo);
$growerCertificatePaid = $applicationId > 0 && grower_certificate_is_paid($pdo, $userId, $applicationId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token expired. Refresh the page and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'issue_grower_certificate') {
                if (!$canIssueGrowerCertificate) {
                    throw new RuntimeException('Your grower verification is not complete yet.');
                }
                if ($growerCertificatePaidRequired && !$growerCertificatePaid) {
                    throw new RuntimeException('Pay the grower certificate fee before issuance.');
                }
                generateCertificate($applicationId, $userId, $pdo);
                redirect_to('certificates.php?message=' . urlencode('Grower certificate issued.'));
            }

            if ($action === 'pay_grower_certificate') {
                if (!$canIssueGrowerCertificate) {
                    throw new RuntimeException('Your grower verification is not complete yet.');
                }
                if (!$growerCertificatePaidRequired || $growerCertificateAmount <= 0) {
                    generateCertificate($applicationId, $userId, $pdo);
                    redirect_to('certificates.php?message=' . urlencode('Grower certificate issued.'));
                }
                if ($growerCertificatePaid) {
                    generateCertificate($applicationId, $userId, $pdo);
                    redirect_to('certificates.php?message=' . urlencode('Certificate payment already confirmed. Certificate issued.'));
                }

                $wallet = wallet_get_or_create($pdo, $userId);
                $pdo->beginTransaction();
                $lock = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
                $lock->execute([(int) $wallet['id']]);
                $lockedWallet = $lock->fetch();
                $balance = (float) ($lockedWallet['balance'] ?? 0);
                if ($balance < $growerCertificateAmount) {
                    $pdo->rollBack();
                    throw new RuntimeException('Insufficient wallet balance. Fund your wallet and try again.');
                }
                $reference = grower_certificate_payment_reference($userId, $applicationId) . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $after = $balance - $growerCertificateAmount;
                $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$after, (int) $lockedWallet['id']]);
                $pdo->prepare("
                    INSERT INTO wallet_transactions
                        (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status, balance_before, balance_after, completed_at)
                    VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'wallet', ?, ?, 'completed', ?, ?, NOW())
                ")->execute([
                    (int) $lockedWallet['id'],
                    $userId,
                    $growerCertificateAmount,
                    'Verified grower certificate issuance fee',
                    $reference,
                    $reference,
                    json_encode(['certificate_type' => 'grower', 'application_id' => $applicationId], JSON_UNESCAPED_SLASHES),
                    $balance,
                    $after,
                ]);
                $pdo->commit();
                generateCertificate($applicationId, $userId, $pdo);
                redirect_to('certificates.php?message=' . urlencode('Certificate fee paid and grower certificate issued.'));
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$growerStmt = $pdo->prepare("
    SELECT COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) display_ref,
           c.certificate_ref, c.status, c.issued_at, c.expires_at, c.verification_url, c.certificate_path, c.certificate_pdf_path,
           a.app_ref, a.name, a.location
    FROM certificates c
    JOIN applications a ON a.id = c.application_id
    WHERE c.user_id = ? OR c.application_id = ?
    ORDER BY c.issued_at DESC
");
$growerStmt->execute([$userId, $applicationId]);
$growerCertificates = $growerStmt->fetchAll();

$academyStmt = $pdo->prepare("
    SELECT ac.*, w.title course_title, w.certification_required
    FROM academy_certificates ac
    JOIN webinars w ON w.id = ac.webinar_id
    WHERE ac.user_id = ?
    ORDER BY ac.requested_at DESC
");
$academyStmt->execute([$userId]);
$academyCertificates = $academyStmt->fetchAll();

$academyGroupStmt = $pdo->prepare("
    SELECT ac.*, g.title group_title
    FROM academy_group_certificates ac
    JOIN academy_certificate_groups g ON g.id = ac.group_id
    WHERE ac.user_id = ?
    ORDER BY ac.requested_at DESC
");
$academyGroupStmt->execute([$userId]);
$academyGroupCertificates = $academyGroupStmt->fetchAll();

$walletStmt = $pdo->prepare("SELECT COALESCE(balance, 0) FROM wallets WHERE user_id = ? LIMIT 1");
$walletStmt->execute([$userId]);
$walletBalance = (float) ($walletStmt->fetchColumn() ?: 0);

$issuedGrowerCertificates = array_values(array_filter($growerCertificates, static fn(array $cert): bool => (string) ($cert['status'] ?? '') === 'issued'));
$issuedAcademyCertificates = array_values(array_filter($academyCertificates, static fn(array $cert): bool => (string) ($cert['status'] ?? '') === 'issued'));
$issuedGroupCertificates = array_values(array_filter($academyGroupCertificates, static fn(array $cert): bool => (string) ($cert['status'] ?? '') === 'issued'));
$academyCertificateTotal = count($academyCertificates) + count($academyGroupCertificates);
$issuedCertificateTotal = count($issuedGrowerCertificates) + count($issuedAcademyCertificates) + count($issuedGroupCertificates);
$expiringGrowerCertificates = array_values(array_filter($growerCertificates, static function (array $cert): bool {
    if (empty($cert['expires_at'])) {
        return false;
    }
    $expires = strtotime((string) $cert['expires_at']);
    return $expires !== false && $expires >= time() && $expires <= strtotime('+90 days');
}));
$latestGrowerCertificate = $growerCertificates[0] ?? null;
$libraryHasCertificates = $growerCertificates || $academyCertificates || $academyGroupCertificates;

dashboard_page_start('Certificates', [
    'active' => 'certificates.php',
    'description' => 'View, verify, and download your grower and NATCODEV Academy credentials from one organized library.',
    'wide' => true,
]);
?>
<?php if (!empty($_GET['message'])): ?><div class="notice ok"><?= e((string) $_GET['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<style>
  .cert-workspace { display:grid; gap:18px; }
  .cert-hero { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr); gap:18px; align-items:stretch; }
  .cert-panel { background:#fff; border:1px solid rgba(24,43,18,.1); border-radius:8px; box-shadow:var(--shadow); padding:18px; }
  .cert-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:14px; }
  .cert-head h2, .cert-panel h2 { margin:0; color:var(--green); font-size:1.15rem; }
  .cert-head p { margin:6px 0 0; color:var(--muted); line-height:1.5; }
  .cert-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
  .cert-stat { border:1px solid var(--line); border-radius:8px; background:#fbfcfa; padding:14px; }
  .cert-stat span { display:block; color:var(--muted); font-size:.84rem; font-weight:800; }
  .cert-stat strong { display:block; color:var(--green); font-size:1.9rem; line-height:1.05; margin-top:7px; }
  .cert-feature { display:flex; gap:14px; align-items:flex-start; padding:15px; border:1px solid var(--line); border-radius:8px; background:linear-gradient(135deg,#fffdf5,#f7fbf4); }
  .cert-seal { width:58px; height:58px; border-radius:50%; display:grid; place-items:center; background:#b91c1c; color:#fff; border:5px solid #f2c75c; box-shadow:inset 0 0 0 4px #8f1010; font-weight:900; flex:0 0 auto; }
  .cert-feature h3 { margin:0; color:var(--green); font-size:1.35rem; }
  .cert-feature p { margin:7px 0 0; color:var(--muted); line-height:1.5; }
  .cert-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:14px; }
  .cert-meta div { border:1px solid var(--line); border-radius:7px; padding:10px; background:#fbfcfa; }
  .cert-meta span { display:block; color:var(--muted); font-size:.82rem; margin-bottom:4px; }
  .cert-meta strong { color:#26351f; }
  .cert-tabs { display:flex; flex-wrap:wrap; gap:8px; }
  .cert-tab { display:inline-flex; align-items:center; gap:7px; border:1px solid var(--line); border-radius:999px; padding:8px 11px; color:var(--green); background:#edf6e8; font-weight:850; }
  .cert-library { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
  .cert-card { border:1px solid rgba(24,43,18,.1); border-radius:8px; background:#fff; box-shadow:0 10px 26px rgba(24,43,18,.06); padding:15px; display:grid; gap:12px; }
  .cert-card-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
  .cert-card h3 { margin:0; color:var(--green); font-size:1.08rem; line-height:1.25; }
  .cert-ref { font-family:Consolas, monospace; border:1px solid var(--line); background:#f7f9f5; border-radius:6px; padding:9px 10px; color:#26351f; overflow-wrap:anywhere; }
  .cert-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
  .cert-detail-grid div { border-top:1px solid #edf1ea; padding-top:9px; }
  .cert-detail-grid span { display:block; color:var(--muted); font-size:.8rem; font-weight:800; margin-bottom:3px; }
  .cert-detail-grid strong { color:#26351f; font-size:.94rem; }
  .cert-empty { border:1px dashed var(--line); border-radius:8px; padding:24px; background:#fbfcfa; color:var(--muted); }
  .cert-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:2px; }
  @media (max-width: 980px) {
    .cert-hero, .cert-library, .cert-summary { grid-template-columns:1fr; }
  }
  @media (max-width: 560px) {
    .cert-meta, .cert-detail-grid { grid-template-columns:1fr; }
  }
</style>

<section class="cert-workspace" aria-label="Certificates library">
  <section class="cert-hero">
    <article class="cert-panel">
      <div class="cert-head">
        <div>
          <h2>Certificates Library</h2>
          <p>Your issued credentials stay here with public verification and PDF download where available.</p>
        </div>
        <?= ntv_badge($libraryHasCertificates ? 'issued' : 'pending', $libraryHasCertificates ? 'Records found' : 'No records yet') ?>
      </div>
      <div class="cert-summary">
        <div class="cert-stat"><span>Total Credentials</span><strong><?= count($growerCertificates) + $academyCertificateTotal ?></strong></div>
        <div class="cert-stat"><span>Issued</span><strong><?= $issuedCertificateTotal ?></strong></div>
        <div class="cert-stat"><span>Academy</span><strong><?= $academyCertificateTotal ?></strong></div>
        <div class="cert-stat"><span>Expiring Soon</span><strong><?= count($expiringGrowerCertificates) ?></strong></div>
      </div>
      <div class="cert-tabs" style="margin-top:14px;">
        <a class="cert-tab" href="#grower-certificates">Grower Credentials</a>
        <a class="cert-tab" href="#academy-certificates">Academy Certificates</a>
        <a class="cert-tab" href="<?= e(app_base_url() . '/verify-certificate.php') ?>" target="_blank" rel="noopener">Public Verification</a>
      </div>
    </article>

    <article class="cert-panel">
      <div class="cert-feature">
        <div class="cert-seal">NAT</div>
        <div>
          <h3>Verified Grower Credential</h3>
          <p>Issued after identity/farm verification and certificate payment when Super Admin sets the credential as paid.</p>
        </div>
      </div>
      <div class="cert-meta">
        <div><span>Eligibility</span><strong><?= $canIssueGrowerCertificate ? 'Verification complete' : 'Verification incomplete' ?></strong></div>
        <div><span>Payment Policy</span><strong><?= $growerCertificatePaidRequired ? 'NGN ' . e(number_format($growerCertificateAmount, 2)) : 'Free' ?></strong></div>
        <div><span>Payment Status</span><strong><?= $growerCertificatePaidRequired ? ($growerCertificatePaid ? 'Paid' : 'Not paid') : 'Not required' ?></strong></div>
        <div><span>Wallet Balance</span><strong>NGN <?= e(number_format($walletBalance, 2)) ?></strong></div>
      </div>
      <?php if (!$growerCertificates && $canIssueGrowerCertificate): ?>
        <div class="cert-actions">
          <?php if ($growerCertificatePaidRequired && !$growerCertificatePaid): ?>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="pay_grower_certificate">
              <button type="submit" <?= $walletBalance >= $growerCertificateAmount ? '' : 'disabled' ?>>Pay From Wallet & Issue</button>
            </form>
            <a class="button secondary" href="wallet.php?amount=<?= e((string) ceil($growerCertificateAmount)) ?>">Fund Wallet</a>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="issue_grower_certificate">
              <button type="submit">Issue Free Certificate</button>
            </form>
          <?php endif; ?>
        </div>
      <?php elseif ($latestGrowerCertificate): ?>
        <div class="cert-actions">
          <a class="button secondary" href="#grower-certificates">Open Grower Certificate</a>
        </div>
      <?php else: ?>
        <p class="note">Complete identity and farm verification before requesting this credential.</p>
        <div class="cert-actions"><a class="button secondary" href="documents.php">Open Verification</a></div>
      <?php endif; ?>
    </article>
  </section>

  <section id="grower-certificates" class="cert-panel">
    <div class="cert-head">
      <div>
        <h2>Grower Credentials</h2>
        <p>Participation and verified grower certificates are valid for 3 years when expiry is configured. Revoked or expired records remain visible for audit clarity.</p>
      </div>
      <?= ntv_badge('validity', '3-year validity where set') ?>
    </div>
    <div class="cert-library">
      <?php foreach ($growerCertificates as $cert): ?>
        <?php
          $displayRef = (string) $cert['display_ref'];
          $verifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($displayRef);
          $htmlPath = '';
          if (!empty($cert['certificate_path'])) {
              $htmlPath = ltrim((string) $cert['certificate_path'], '/');
              if ($htmlPath !== '' && !str_starts_with($htmlPath, 'certificates/')) {
                  $htmlPath = 'certificates/' . $htmlPath;
              }
          }
          $expired = !empty($cert['expires_at']) && strtotime((string) $cert['expires_at']) < time();
          $statusLabel = $expired ? 'Expired' : ntv_status_label((string) $cert['status']);
        ?>
        <article class="cert-card">
          <div class="cert-card-top">
            <div>
              <h3>Verified Grower Certificate</h3>
              <p class="note" style="margin:5px 0 0;"><?= e((string) $cert['app_ref']) ?> / <?= e((string) $cert['location']) ?></p>
            </div>
            <?= ntv_badge($expired ? 'expired' : (string) $cert['status'], $statusLabel) ?>
          </div>
          <div class="cert-ref"><?= e($displayRef) ?></div>
          <div class="cert-detail-grid">
            <div><span>Issued</span><strong><?= !empty($cert['issued_at']) ? e(date('M j, Y', strtotime((string) $cert['issued_at']))) : 'Pending' ?></strong></div>
            <div><span>Validity</span><strong><?= !empty($cert['expires_at']) ? 'Until ' . e(date('M j, Y', strtotime((string) $cert['expires_at']))) : 'Not set' ?></strong></div>
            <div><span>Owner</span><strong><?= e((string) $cert['name']) ?></strong></div>
            <div><span>Public Check</span><strong>Verifiable online</strong></div>
          </div>
          <div class="cert-actions">
            <?php if ((string) $cert['status'] === 'issued' && !$expired): ?>
              <a class="button secondary" href="download-certificate.php?ref=<?= urlencode($displayRef) ?>">Download PDF</a>
              <?php if ($htmlPath !== ''): ?><a class="button secondary" href="../<?= e($htmlPath) ?>" target="_blank" rel="noopener">View Online</a><?php endif; ?>
            <?php endif; ?>
            <a class="button secondary" href="<?= e($verifyUrl) ?>" target="_blank" rel="noopener">Verify</a>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$growerCertificates): ?>
        <div class="cert-empty">No grower certificate has been issued yet. When eligible, the issue/payment action will appear above.</div>
      <?php endif; ?>
    </div>
  </section>

  <section id="academy-certificates" class="cert-panel">
    <div class="cert-head">
      <div>
        <h2>Academy Certificates</h2>
        <p>Course and grouped pathway certificates are permanent credentials once issued and remain available for verification and download.</p>
      </div>
      <a class="button secondary" href="../academy/index.php?screen=certificates">Open My Learning</a>
    </div>
    <div class="cert-library">
      <?php foreach ($academyCertificates as $cert): ?>
        <?php $academyIssued = (string) $cert['status'] === 'issued'; ?>
        <article class="cert-card">
          <div class="cert-card-top">
            <div>
              <h3><?= e((string) $cert['course_title']) ?></h3>
              <p class="note" style="margin:5px 0 0;">Academy course certificate</p>
            </div>
            <?= ntv_badge((string) $cert['status']) ?>
          </div>
          <div class="cert-ref"><?= e((string) $cert['certificate_ref']) ?></div>
          <div class="cert-detail-grid">
            <div><span>Requested</span><strong><?= !empty($cert['requested_at']) ? e(date('M j, Y', strtotime((string) $cert['requested_at']))) : 'Pending' ?></strong></div>
            <div><span>Issued</span><strong><?= !empty($cert['issued_at']) ? e(date('M j, Y', strtotime((string) $cert['issued_at']))) : 'Pending' ?></strong></div>
            <div><span>Validity</span><strong>Permanent</strong></div>
            <div><span>Public Check</span><strong><?= $academyIssued ? 'Verifiable online' : 'Pending issuance' ?></strong></div>
          </div>
          <?php if ($academyIssued): ?>
            <div class="cert-actions">
              <a class="button secondary" href="download-academy-certificate.php?ref=<?= urlencode((string) $cert['certificate_ref']) ?>">Download PDF</a>
              <a class="button secondary" href="<?= e(app_base_url() . '/verify-certificate.php?ref=' . urlencode((string) $cert['certificate_ref'])) ?>" target="_blank" rel="noopener">Verify</a>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      <?php foreach ($academyGroupCertificates as $cert): ?>
        <?php $groupIssued = (string) $cert['status'] === 'issued'; ?>
        <article class="cert-card">
          <div class="cert-card-top">
            <div>
              <h3><?= e((string) $cert['group_title']) ?></h3>
              <p class="note" style="margin:5px 0 0;">Grouped Academy pathway certificate</p>
            </div>
            <?= ntv_badge((string) $cert['status']) ?>
          </div>
          <div class="cert-ref"><?= e((string) $cert['certificate_ref']) ?></div>
          <div class="cert-detail-grid">
            <div><span>Requested</span><strong><?= !empty($cert['requested_at']) ? e(date('M j, Y', strtotime((string) $cert['requested_at']))) : 'Pending' ?></strong></div>
            <div><span>Issued</span><strong><?= !empty($cert['issued_at']) ? e(date('M j, Y', strtotime((string) $cert['issued_at']))) : 'Pending' ?></strong></div>
            <div><span>Validity</span><strong>Permanent</strong></div>
            <div><span>Certificate Type</span><strong>Grouped pathway</strong></div>
          </div>
          <?php if ($groupIssued): ?>
            <div class="cert-actions">
              <a class="button secondary" href="download-academy-certificate.php?ref=<?= urlencode((string) $cert['certificate_ref']) ?>">Download PDF</a>
              <a class="button secondary" href="<?= e(app_base_url() . '/verify-certificate.php?ref=' . urlencode((string) $cert['certificate_ref'])) ?>" target="_blank" rel="noopener">Verify</a>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      <?php if (!$academyCertificates && !$academyGroupCertificates): ?>
        <div class="cert-empty">No Academy certificate has been issued or requested yet. Registered courses and certificate requests are managed from NATCODEV Academy.</div>
      <?php endif; ?>
    </div>
  </section>
</section>
<?php dashboard_page_end(); ?>
