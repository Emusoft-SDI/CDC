<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('accreditation', 'Accreditation', 'Review provider approval, documents, certification readiness, and NATCODEV quality checks.', function(PDO $pdo, array $user, array $provider): void {
    $items = provider_accreditation_types();
    $documents = provider_accreditation_documents($pdo, (int) $provider['id']);
    $approved = count(array_filter($documents, static fn(array $row): bool => (string) $row['status'] === 'approved'));
    ?>
    <section class="card">
      <div class="card-head"><h2>Accreditation Status</h2><span class="badge"><?= e(provider_status_label((string) $provider['status'])) ?></span></div>
      <div class="list">
        <?php foreach ($items as $key => $item): $document = $documents[$key] ?? null; $status = (string) ($document['status'] ?? 'not_uploaded'); $class = $status === 'rejected' ? 'red' : ($status === 'approved' ? '' : 'warn'); ?>
          <div class="row">
            <span><i class="fas fa-file-shield" style="color:#08753a"></i> <strong><?= e($item) ?></strong><?php if ($document): ?><br><small><?= e((string) $document['original_name']) ?><?= $document['reviewed_at'] ? ' / reviewed by ' . e((string) ($document['reviewer_name'] ?: 'NATCODEV administrator')) . ' on ' . e(date('M j, Y', strtotime((string) $document['reviewed_at']))) : '' ?><?= $document['reviewer_notes'] ? ' / ' . e((string) $document['reviewer_notes']) : '' ?></small><?php endif; ?></span>
            <span><?php if ($document): ?><a class="btn light" target="_blank" href="document.php?id=<?= (int) $document['id'] ?>">View</a> <?php endif; ?><span class="badge <?= e($class) ?>"><?= e(provider_status_label($status)) ?></span></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="notice <?= $approved === count($items) ? 'ok' : 'err' ?>" style="margin-top:14px"><?= (int) $approved ?> of <?= count($items) ?> accreditation documents approved. Approval is performed by an authorized NATCODEV administrator after evidence review.</p>
      <a class="btn" href="profile.php#documents">Upload or Replace Evidence</a>
    </section>
    <?php if ($approved === count($items) && in_array((string) $provider['status'], ['approved', 'verified'], true)): ?>
    <section class="card" style="margin-top:16px">
      <div class="card-head"><h2>Accreditation Certificate</h2></div>
      <p class="notice ok">Your provider accreditation is approved. Download your NATCODEV Accreditation Certificate for official records and network recognition.</p>
      <a class="btn" href="download-accreditation-certificate.php"><i class="fas fa-download"></i> Download Accreditation Certificate</a>
    </section>
    <?php endif; ?>
    <section class="card" style="margin-top:16px">
      <div class="card-head"><h2>NATCODEV Accreditation Pathway</h2><span class="badge">Provider Relationship</span></div>
      <div class="list">
        <div class="row"><span><strong>Evidence review</strong><br><small>Upload CAC, licence, quality proof, insurance, identity, and other provider evidence for administrator review.</small></span><a class="btn light" href="profile.php#documents">Documents</a></div>
        <div class="row"><span><strong>Academy readiness</strong><br><small>Complete provider accreditation and marketplace conduct courses. Certificates remain part of the provider network record.</small></span><a class="btn light" href="academy.php">Academy</a></div>
        <div class="row"><span><strong>Optional seller store</strong><br><small>Provider accreditation does not automatically make the account a seller. When ready to sell, request seller-store access and Seller Central will appear after approval/profile creation.</small></span><a class="btn light" href="support.php">Request Seller Store</a></div>
      </div>
    </section>
    <?php
});