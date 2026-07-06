<?php $courseCertificateRejected = $currentCourseCertificate && (string) $currentCourseCertificate['status'] === 'rejected'; ?>
  <?php if ($courseCertificateRejected): ?>
    <section class="card" style="margin-bottom:18px">
      <div class="card-h"><h3>Certificate Needs Correction</h3><?= ac_badge('rejected', 'Rejected') ?></div>
      <h3><?= e((string) $course['title']) ?></h3>
      <p class="muted">Your certificate request for this course was rejected. Use the review note below, fix any missing lesson or assessment requirement, then submit it back for Academy review.</p>
      <div class="grid g3" style="margin:12px 0">
        <div class="card"><strong>Lessons</strong><br><?= (int) ($currentCertificateEligibility['completed_lessons'] ?? 0) ?>/<?= (int) ($currentCertificateEligibility['required_lessons'] ?? 0) ?></div>
        <div class="card"><strong>Assessment</strong><br><?= ($currentCertificateEligibility['passed_score'] ?? null) !== null ? 'Passed (' . e((string) $currentCertificateEligibility['passed_score']) . '%)' : 'Not passed yet' ?></div>
        <div class="card"><strong>Certificate Ref</strong><br><?= e((string) $currentCourseCertificate['certificate_ref']) ?></div>
      </div>
      <?php if (!empty($currentCourseCertificate['notes'])): ?><div class="notice err"><strong>Review note:</strong> <?= e((string) $currentCourseCertificate['notes']) ?></div><?php endif; ?>
      <?php if ($currentCertificateEligibility && $currentCertificateEligibility['eligible']): ?>
        <form method="post" class="grid g2" style="align-items:end">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="resubmit_certificate">
          <input type="hidden" name="certificate_id" value="<?= (int) $currentCourseCertificate['id'] ?>">
          <label style="margin:0">What did you fix?<textarea name="fix_note" required placeholder="Example: I completed the final lesson and passed the assessment again."></textarea></label>
          <button type="submit">Submit Correction for Review</button>
        </form>
      <?php else: ?>
        <p class="muted"><?= e(implode(' ', $currentCertificateEligibility['reasons'] ?? ['Complete the missing course requirements before resubmitting.'])) ?></p>
        <div class="actions"><a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>">Fix Lessons</a><a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>">Retake / Pass Assessment</a><a class="btn btn-o" href="dashboard.php?screen=certificates">Open Certificates</a></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  <section class="grid g3">
    <article class="card"><div class="card-h"><h3><?= $courseCertificateRejected ? 'Enrollment Complete' : 'Select Payment Method' ?></h3></div><p class="muted">Wallet balance: <strong><?= e(ac_money($walletBalance)) ?></strong></p><p class="muted">Monnify direct payment supports card, bank, transfer and USSD where configured.</p><a class="btn btn-p btn-full" href="dashboard.php?screen=course&course_id=<?= $courseId ?>">Continue Enrollment</a></article>
    <article class="card"><div class="card-h"><h3>Registration Flow</h3></div><div class="info-row"><span>1. Choose course</span><strong>Done</strong></div><div class="info-row"><span>2. Pay/Register</span><strong><?= $isCourseRegistered ? 'Done' : 'Now' ?></strong></div><div class="info-row"><span>3. Access lessons</span><strong><?= $isCourseRegistered ? 'Available' : 'After registration' ?></strong></div><div class="info-row"><span>4. Certificate review</span><strong><?= $courseCertificateRejected ? 'Fix needed' : 'After completion' ?></strong></div></article>
    <article class="card"><div class="card-h"><h3>Course Summary</h3></div><h3><?= e((string) $course['title']) ?></h3><p class="muted"><?= e((string) ($course['description'] ?? '')) ?></p><div class="info-row"><span>Price</span><strong><?= (int) $course['is_free'] === 1 ? 'Free' : e(ac_money((float) $course['price'])) ?></strong></div><div class="info-row"><span>Certificate</span><strong><?= $courseCertificateRejected ? 'Rejected - correction needed' : ((int) ($course['certification_required'] ?? 0) === 1 ? 'Available after completion' : 'Learning only') ?></strong></div></article>
  </section>