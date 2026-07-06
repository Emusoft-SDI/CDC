<section class="grid g3" style="margin-bottom:18px">
    <?php foreach ($certificateGroups as $group): ?>
      <?php
        $eligibility = academy_group_eligibility($pdo, (int) $user['id'], (int) $group['id']);
        $existing = null;
        foreach ($groupCertificates as $cert) {
            if ((int) $cert['group_id'] === (int) $group['id']) {
                $existing = $cert;
                break;
            }
        }
        $existingStatus = (string) ($existing['status'] ?? '');
        $groupDisplayStatus = $existingStatus === 'rejected' ? 'rejected' : ($eligibility['eligible'] ? 'eligible' : 'pending');
        $groupDisplayLabel = $existingStatus === 'rejected' ? 'Rejected' : ($eligibility['eligible'] ? 'Eligible' : 'In Progress');
      ?>
      <article class="card">
        <div class="card-h"><h3><?= e((string) $group['title']) ?></h3><?= ac_badge($groupDisplayStatus, $groupDisplayLabel) ?></div>
        <p class="muted"><?= e((string) $group['description']) ?></p>
        <?php if ($existing): ?>
          <p><strong><?= e((string) $existing['certificate_ref']) ?></strong></p>
          <?php if ($existingStatus === 'issued'): ?>
            <a class="btn btn-o" href="../dashboard/download-academy-certificate.php?ref=<?= urlencode((string) $existing['certificate_ref']) ?>">Download</a>
          <?php elseif ($existingStatus === 'rejected'): ?>
            <?= ac_badge('rejected', 'Rejected') ?>
            <p class="muted">This pathway request was rejected. Complete any missing course requirement, then request review again.</p>
            <?php if (!empty($existing['notes'])): ?><p class="muted"><strong>Review note:</strong> <?= e((string) $existing['notes']) ?></p><?php endif; ?>
            <?php if ($eligibility['eligible']): ?>
              <form method="post" class="grid" style="gap:8px"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resubmit_group_certificate"><input type="hidden" name="certificate_id" value="<?= (int) $existing['id'] ?>"><label>What did you fix?<textarea name="fix_note" required placeholder="Tell the review team what changed."></textarea></label><button type="submit">Submit Correction</button></form>
            <?php else: ?>
              <small class="muted">Missing <?= count($eligibility['missing']) ?> required course(s).</small>
            <?php endif; ?>
          <?php else: ?>
            <?= ac_badge($existingStatus ?: 'pending') ?>
          <?php endif; ?>
        <?php elseif ($eligibility['eligible']): ?>
          <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="request_group_certificate"><input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>"><button type="submit">Request Certificate</button></form>
        <?php else: ?>
          <small class="muted">Missing <?= count($eligibility['missing']) ?> required course(s).</small>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
  <section class="card" style="margin-bottom:18px"><div class="card-h"><h3>Eligible Course Certificates</h3><a class="link" href="dashboard.php?screen=learning">My Learning</a></div><div class="grid g2">
    <?php $eligibleRows = 0; foreach ($registered as $reg): ?>
      <?php
        if ((int) ($reg['certification_required'] ?? 0) !== 1) {
            continue;
        }
        $existingCert = null;
        foreach ($certificates as $cert) {
            if ((int) $cert['webinar_id'] === (int) $reg['id']) {
                $existingCert = $cert;
                break;
            }
        }
        $certEligibility = ac_certificate_eligibility($pdo, (int) $user['id'], (int) $reg['registration_id']);
        $displayCertificateStatus = (string) ($existingCert['status'] ?? ($reg['certificate_status'] ?? 'not_started'));
        $displayCertificateLabel = $certEligibility['eligible'] && $displayCertificateStatus !== 'rejected' ? 'Eligible' : ac_status($displayCertificateStatus);
        $eligibleRows++;
      ?>
      <article class="card">
        <div class="card-h"><h3><?= e((string) $reg['title']) ?></h3><?= ac_badge($displayCertificateStatus === 'rejected' ? 'rejected' : ($certEligibility['eligible'] ? 'eligible' : $displayCertificateStatus), 'Certificate: ' . $displayCertificateLabel) ?></div>
        <p class="muted">Lessons: <?= (int) ($certEligibility['completed_lessons'] ?? 0) ?>/<?= (int) ($certEligibility['required_lessons'] ?? 0) ?>. Assessment: <?= $certEligibility['passed_score'] !== null ? 'Passed (' . e((string) $certEligibility['passed_score']) . '%)' : 'Not passed yet' ?>.</p>
        <?php if ($existingCert): ?>
          <p><strong><?= e((string) $existingCert['certificate_ref']) ?></strong></p>
          <?php if ((string) $existingCert['status'] === 'issued'): ?>
            <a class="btn btn-o" href="../dashboard/download-academy-certificate.php?ref=<?= urlencode((string) $existingCert['certificate_ref']) ?>">Download Certificate</a>
          <?php elseif ((string) $existingCert['status'] === 'rejected'): ?>
            <?= ac_badge('rejected', 'Rejected') ?>
            <p class="muted">This certificate request was rejected. You can still recover it: complete any missing lesson or assessment requirement, then request review again.</p>
            <?php if (!empty($existingCert['notes'])): ?><p class="muted"><strong>Review note:</strong> <?= e((string) $existingCert['notes']) ?></p><?php endif; ?>
            <?php if ($certEligibility['eligible']): ?>
              <form method="post" class="grid" style="gap:8px"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resubmit_certificate"><input type="hidden" name="certificate_id" value="<?= (int) $existingCert['id'] ?>"><label>What did you fix?<textarea name="fix_note" required placeholder="Tell the review team what changed."></textarea></label><button type="submit">Submit Correction</button></form>
            <?php else: ?>
              <p class="muted"><?= e(implode(' ', $certEligibility['reasons'])) ?></p>
              <a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= (int) $reg['id'] ?>">Continue Course</a>
              <?php if ((int) ($reg['assessments'] ?? 0) > 0): ?><a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= (int) $reg['id'] ?>">Retake / Pass Assessment</a><?php endif; ?>
            <?php endif; ?>
          <?php else: ?>
            <?= ac_badge((string) $existingCert['status']) ?>
          <?php endif; ?>
        <?php elseif ($certEligibility['eligible']): ?>
          <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="request_certificate"><input type="hidden" name="registration_id" value="<?= (int) $reg['registration_id'] ?>"><button type="submit">Request Certificate</button></form>
        <?php else: ?>
          <p class="muted"><?= e(implode(' ', $certEligibility['reasons'])) ?></p>
          <a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= (int) $reg['id'] ?>">Continue Course</a>
          <?php if ((int) ($reg['assessments'] ?? 0) > 0): ?><a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= (int) $reg['id'] ?>">Take Assessment</a><?php endif; ?>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    <?php if ($eligibleRows === 0): ?><div class="empty">No certification course enrollment yet.</div><?php endif; ?>
  </div></section>
  <section class="card"><div class="card-h"><h3>Course Certificates</h3><a class="link" href="../verify-certificate.php">Verify Online</a></div><table><tr><th>Course</th><th>Reference</th><th>Status</th><th>Action</th></tr><?php foreach ($certificates as $cert): ?><tr><td><?= e((string) $cert['course_title']) ?></td><td><?= e((string) $cert['certificate_ref']) ?></td><td><?= ac_badge((string) $cert['status']) ?></td><td><?php if ((string) $cert['status'] === 'issued'): ?><a class="btn btn-o btn-s" href="../dashboard/download-academy-certificate.php?ref=<?= urlencode((string) $cert['certificate_ref']) ?>">Download</a><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$certificates): ?><tr><td colspan="4">No course certificates yet.</td></tr><?php endif; ?></table></section>