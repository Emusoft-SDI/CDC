<details class="support-panel" open><summary>Track Ticket And Replies</summary><div class="support-panel-body">
  <section class="hero" style="margin-top:16px">
    <article class="panel" id="lookup">
      <div class="head"><h2><?= $user ? 'My Tickets' : 'Track Public Ticket' ?></h2><span class="badge neutral"><?= (int) count($myTickets) ?> ticket(s)</span></div>
      <?php if (!$user): ?>
        <form method="get" class="form-grid">
          <input type="hidden" name="view" value="lookup">
          <div><label>Ticket Reference</label><input name="ticket" value="<?= e($lookupRef) ?>" required></div>
          <div><label>Email Used</label><input type="email" name="email" value="<?= e($lookupEmail) ?>" required></div>
          <div style="display:flex;align-items:end"><button class="btn light" type="submit">Track Ticket</button></div>
        </form>
      <?php endif; ?>
      <div class="grid" style="margin-top:14px">
        <?php foreach ($myTickets as $ticket): ?><a class="ticket <?= $selectedTicket && (int) $selectedTicket['id'] === (int) $ticket['id'] ? 'active' : '' ?>" href="index.php?view=lookup&ticket=<?= e((string) $ticket['ticket_ref']) ?>&email=<?= e((string) $ticket['requester_email']) ?>"><strong><?= e((string) $ticket['ticket_ref']) ?></strong><br><span class="muted"><?= e((string) $ticket['subject']) ?></span><br><span class="badge <?= e(support_badge_class((string) $ticket['status'])) ?>"><?= e(support_statuses()[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span></a><?php endforeach; ?>
        <?php if ($user && !$myTickets): ?><div class="ticket">No ticket yet. Submit a request above.</div><?php endif; ?>
      </div>
    </article>

    <article class="panel">
      <div class="head"><h2>Ticket Detail</h2><?php if ($selectedTicket): ?><span class="badge <?= e(support_badge_class((string) $selectedTicket['priority'])) ?>"><?= e(ucfirst((string) $selectedTicket['priority'])) ?> Priority</span><?php endif; ?></div>
      <?php if ($selectedTicket): ?>
        <h3><?= e((string) $selectedTicket['subject']) ?></h3>
        <p class="muted"><?= e((string) $selectedTicket['ticket_ref']) ?> / <?= e($categories[(string) $selectedTicket['category']]['label'] ?? (string) $selectedTicket['category']) ?> / <?= e((string) $selectedTicket['assigned_team']) ?></p>
        <div class="conversation">
          <?php foreach ($conversation as $msg): ?><div class="msg <?= $msg['admin_id'] ? 'agent' : '' ?>"><strong><?= e((string) $msg['author_name']) ?></strong><p><?= nl2br(e((string) $msg['message'])) ?></p><small class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></small></div><?php endforeach; ?>
        </div>
        <?php if (!in_array((string) $selectedTicket['status'], ['resolved', 'closed', 'rejected'], true)): ?>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedTicket['ticket_ref']) ?>">
            <input type="hidden" name="email" value="<?= e((string) $selectedTicket['requester_email']) ?>">
            <label>Reply</label><textarea name="reply" required></textarea>
            <p><button class="btn" type="submit">Send Reply</button></p>
          </form>
        <?php endif; ?>

        <?php if ((string) $selectedTicket['status'] === 'resolved' && empty($selectedTicket['rating'])): ?>
          <div class="rating-box" style="margin-top:20px;padding:20px;background:var(--green-light);border:1px solid rgba(7,95,42,0.2);border-radius:var(--radius-md);">
            <h4 style="margin:0 0 10px 0;color:var(--deep);font-weight:700;">Rate Your Support Experience</h4>
            <p class="muted" style="font-size:0.85rem;margin:0 0 16px 0;">Please help us improve our service by rating your representative.</p>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="rate_ticket">
              <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedTicket['ticket_ref']) ?>">
              <input type="hidden" name="email" value="<?= e((string) $selectedTicket['requester_email']) ?>">
              <div style="margin-bottom:12px;">
                <label>Rating Score</label>
                <select name="rating" required style="max-width:240px;">
                  <option value="5">5 - Excellent</option>
                  <option value="4">4 - Good</option>
                  <option value="3">3 - Average</option>
                  <option value="2">2 - Poor</option>
                  <option value="1">1 - Terrible</option>
                </select>
              </div>
              <div style="margin-bottom:16px;">
                <label>Review Comments (Optional)</label>
                <textarea name="feedback_comment" placeholder="Tell us how we did..."></textarea>
              </div>
              <button class="btn" type="submit">Submit Feedback</button>
            </form>
          </div>
        <?php elseif (!empty($selectedTicket['rating'])): ?>
          <div class="rating-box" style="margin-top:20px;padding:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--radius-md);color:#166534;">
            <strong style="display:block;font-size:1rem;margin-bottom:6px;"><i class="fas fa-circle-check"></i> Thank you!</strong>
            <p style="margin:0;font-size:0.9rem;line-height:1.5;">You rated this support experience: <strong><?= (int) $selectedTicket['rating'] ?> / 5</strong></p>
            <?php if (!empty($selectedTicket['feedback_comment'])): ?>
              <p style="margin:8px 0 0 0;font-size:0.88rem;font-style:italic;color:#15803d;padding-left:10px;border-left:3px solid #bbf7d0;">"<?= e($selectedTicket['feedback_comment']) ?>"</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p class="muted">Select a ticket or use the lookup form to view the conversation and admin updates.</p>
      <?php endif; ?>
    </article>
  </section>

  </div></details>
