<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/certificates.php';

provider_simple_page('certificates', 'Certificates', 'View your issued provider accreditation certificate and verify public credentials.', function(PDO $pdo, array $user, array $provider): void {
    $certificate = provider_accreditation_certificate_record($pdo, (int) $provider['id']);
    $documents = provider_accreditation_documents($pdo, (int) $provider['id']);
    $approved = count(array_filter($documents, static fn(array $row): bool => (string) $row['status'] === 'approved'));
    $total = count(provider_accreditation_types());
    $accessRequired = provider_accreditation_access_required($pdo, $provider);
    $feeAmount = provider_accreditation_fee_amount($pdo);
    $accessStatus = provider_accreditation_access_status($pdo, (int) $user['id'], (int) $provider['id'], (int) ($certificate['id'] ?? null));
    $hasAccess = (bool) $accessStatus['paid'];
    $message = '';
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            $error = 'Security token expired. Refresh the page and try again.';
        } else {
            try {
                if (($action = (string) ($_POST['action'] ?? '')) === 'pay_provider_certificate_access') {
                    if (!$accessRequired) {
                        $message = 'Provider accreditation access is not currently required.';
                    } else {
                        if (!$certificate) {
                            $certificate = provider_accreditation_certificate_issue($pdo, $provider);
                        }
                        $certificate = provider_accreditation_certificate_record($pdo, (int) $provider['id']) ?: $certificate;
                        provider_accreditation_pay_access($pdo, (int) $user['id'], (int) $provider['id'], (int) ($certificate['id'] ?? null), 'view_download');
                        $hasAccess = true;
                        $message = 'Provider accreditation certificate access fee paid. You can now download your certificate.';
                    }
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
    ?>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
    <section class="card">
      <div class="card-head"><h2>Provider Accreditation Certificate</h2><span class="badge"><?= e($certificate ? 'Issued' : 'Pending') ?></span></div>
      <div class="list">
        <div class="row"><span><strong>Accreditation Documents Approved</strong></span><span class="badge <?= $approved === $total ? '' : 'warn' ?>"><?= e($approved . ' / ' . $total) ?></span></div>
        <div class="row"><span><strong>Provider Status</strong></span><span class="badge"><?= e(provider_status_label((string) $provider['status'])) ?></span></div>
        <?php if ($certificate): ?>
          <div class="row"><span><strong>Certificate Reference</strong></span><span class="badge"><?= e((string) $certificate['certificate_ref']) ?></span></div>
          <div class="row"><span><strong>Issued At</strong></span><span><?= e(date('F j, Y', strtotime((string) $certificate['issued_at']))) ?></span></div>
          <div class="row"><span><strong>Verification URL</strong></span><span><a href="<?= e((string) $certificate['verification_url']) ?>" target="_blank" rel="noopener">View Verified Certificate</a></span></div>
          <div class="row"><span><strong>Access Fee</strong></span><span><?= e($accessRequired ? 'NGN ' . number_format($feeAmount, 2) : 'Free') ?></span></div>
          <div class="row"><span><strong>Access Status</strong></span><span class="badge <?= $accessRequired && !$hasAccess ? 'warn' : '' ?>"><?= e($accessRequired ? ($hasAccess ? 'Paid' : 'Payment required') : 'Not required') ?></span></div>
          <div class="row"><span><strong>Download</strong></span><span>
            <?php if ($accessRequired && !$hasAccess): ?>
              <form method="post" style="display:inline;margin-right:.5rem">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="pay_provider_certificate_access">
                <button class="btn">Pay Access Fee & Download</button>
              </form>
              <a class="btn light" href="wallet.php">Fund Wallet</a>
            <?php else: ?>
              <a class="btn" href="download-accreditation-certificate.php"><i class="fas fa-download"></i> Download PDF</a>
            <?php endif; ?>
          </span></div>
        <?php else: ?>
          <div class="row"><span>No accreditation certificate is available yet.</span></div>
          <div class="row"><span>Complete approval for all required documents and provider status to generate your certificate.</span></div>
        <?php endif; ?>
      </div>
    </section>
    <section class="card" style="margin-top:16px">
      <div class="card-head"><h2>Certificate Readiness</h2></div>
      <p class="notice <?= $approved === $total ? 'ok' : 'warn' ?>"><?= $approved === $total ? 'All accreditation evidence is approved. Your certificate is ready once your provider account is fully approved.' : 'Complete the remaining evidence review items to become eligible for accreditation certification.' ?></p>
      <a class="btn light" href="accreditation.php"><i class="fas fa-file-shield"></i> View Accreditation Documents</a>
    </section>
    <?php
});
