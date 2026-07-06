<section class="grid g2" style="margin-bottom:18px">
    <?php workspace_account_render_profile_forms($user, 'academy', 'Learner Profile', 'Change Password'); ?>
  </section>
  <section class="grid g2">
    <?php if ($registered): ?>
      <form class="card" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="submit_feedback"><div class="card-h"><h3>Rate A Course</h3></div><label>Course<select name="webinar_id" required><?php foreach ($registered as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e((string) $c['title']) ?></option><?php endforeach; ?></select></label><label>Rating<select name="rating"><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Fair</option><option value="2">2 - Poor</option><option value="1">1 - Very Poor</option></select></label><label>Comment<textarea name="comment"></textarea></label><button type="submit">Submit Feedback</button></form>
    <?php else: ?>
      <section class="card"><div class="card-h"><h3>Rate A Course</h3><a class="link" href="dashboard.php?screen=catalog">Catalog</a></div><div class="empty">Enroll in an Academy course before submitting course feedback.</div></section>
    <?php endif; ?>
    <section class="card"><div class="card-h"><h3>My Feedback</h3></div><table><tr><th>Course</th><th>Rating</th><th>Comment</th></tr><?php foreach ($myFeedback as $row): ?><tr><td><?= e((string) $row['course_title']) ?></td><td><?= (int) $row['rating'] ?>/5</td><td><?= e((string) $row['comment']) ?></td></tr><?php endforeach; ?><?php if (!$myFeedback): ?><tr><td colspan="3">No feedback yet.</td></tr><?php endif; ?></table></section>
  </section>