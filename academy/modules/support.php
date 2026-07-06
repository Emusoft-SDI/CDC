<section class="support-stats" aria-label="Academy support summary">
    <div class="support-stat"><strong><?= (int) ($supportStats['open'] ?? 0) ?></strong><span>Open</span></div>
    <div class="support-stat"><strong><?= (int) ($supportStats['in_progress'] ?? 0) ?></strong><span>In Progress</span></div>
    <div class="support-stat"><strong><?= (int) ($supportStats['waiting_on_user'] ?? 0) ?></strong><span>Needs Reply</span></div>
    <div class="support-stat"><strong><?= (int) ($supportStats['resolved'] ?? 0) ?></strong><span>Resolved</span></div>
  </section>

  <section class="grid g2">
    <form class="card" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="academy_support_create">
      <div class="card-h"><h3>New Academy Help Request</h3><?= ac_badge('active', 'Learner Support') ?></div>
      <label>Help Topic
        <select name="category">
          <?php foreach ($supportCategories as $key => $cat): ?><option value="<?= e($key) ?>" <?= $key === 'academy' ? 'selected' : '' ?>><?= e((string) $cat['label']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Priority
        <select name="priority">
          <?php foreach ($supportPriorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Related Item
        <select name="linked_record_type">
          <option value="course_enrollment">Course / Enrollment</option>
          <option value="wallet_transaction">Wallet / Payment</option>
          <option value="certificate">Certificate</option>
          <option value="assessment">Assessment</option>
          <option value="">Not listed</option>
        </select>
      </label>
      <label>Reference or Course Name
        <input name="linked_record_ref" placeholder="Course title, payment ref, certificate ref">
      </label>
      <label>Subject
        <input name="subject" maxlength="190" placeholder="e.g., Payment issue for course X" required>
      </label>
      <label>Describe the issue
        <textarea name="description" placeholder="Tell the Academy team what happened, what you expected, and any reference number." required></textarea>
      </label>
      <button type="submit"><i class="fas fa-paper-plane"></i> Send To Academy Support</button>
    </form>

    <article class="card">
      <div class="card-h"><h3>My Academy Support Context</h3><a class="link" href="dashboard.php?screen=learning">My Learning</a></div>
      <div class="help-grid">
        <div class="help-card"><i class="fas fa-book-open"></i><strong><?= count($registered) ?></strong><span>Registered course<?= count($registered) === 1 ? '' : 's' ?></span></div>
        <div class="help-card"><i class="fas fa-chart-line"></i><strong><?= $avgProgress ?>%</strong><span>Average course progress</span></div>
        <div class="help-card"><i class="fas fa-wallet"></i><strong><?= e(ac_money($walletBalance)) ?></strong><span>Current wallet balance</span></div>
        <div class="help-card"><i class="fas fa-receipt"></i><strong><?= count($transactions) ?></strong><span>Academy payment record<?= count($transactions) === 1 ? '' : 's' ?></span></div>
        <div class="help-card"><i class="fas fa-award"></i><strong><?= count($certificates) + count($groupCertificates) ?></strong><span>Certificate record<?= (count($certificates) + count($groupCertificates)) === 1 ? '' : 's' ?></span></div>
        <div class="help-card"><i class="fas fa-headset"></i><strong><?= count($learnerTickets) ?></strong><span>Support ticket<?= count($learnerTickets) === 1 ? '' : 's' ?></span></div>
      </div>
    </article>
  </section>

  <section class="grid g2" style="margin-top:18px">
    <article class="card">
      <div class="card-h"><h3>My Academy Tickets</h3><?= ac_badge('active', (string) count($learnerTickets) . ' Ticket(s)') ?></div>
      <div class="support-ticket-list">
        <?php foreach ($learnerTickets as $ticket): ?>
          <a class="support-ticket <?= $selectedSupportTicket && (int) $selectedSupportTicket['id'] === (int) $ticket['id'] ? 'active' : '' ?>" href="dashboard.php?screen=support&ticket=<?= urlencode((string) $ticket['ticket_ref']) ?>">
            <strong><?= e((string) $ticket['ticket_ref']) ?></strong>
            <small><?= e((string) $ticket['subject']) ?></small>
            <?= ac_badge((string) $ticket['status'], $supportStatuses[(string) $ticket['status']] ?? ac_status((string) $ticket['status'])) ?>
          </a>
        <?php endforeach; ?>
        <?php if (!$learnerTickets): ?><div class="empty">You have not opened any Academy support tickets yet.</div><?php endif; ?>
      </div>
    </article>

    <article class="card">
      <div class="card-h"><h3>Ticket Conversation</h3><?php if ($selectedSupportTicket): ?><?= ac_badge((string) $selectedSupportTicket['priority'], ucfirst((string) $selectedSupportTicket['priority']) . ' Priority') ?><?php endif; ?></div>
      <?php if ($selectedSupportTicket): ?>
        <p class="muted"><strong><?= e((string) $selectedSupportTicket['ticket_ref']) ?></strong> / <?= e((string) $selectedSupportTicket['subject']) ?><br>Assigned to <?= e((string) ($selectedSupportTicket['assigned_team'] ?: 'Academy Support')) ?></p>
        <div class="support-chat">
          <?php foreach ($selectedSupportMessages as $chat): ?>
            <div class="support-message <?= e((string) $chat['author_role']) ?>">
              <strong><?= e((string) $chat['author_name']) ?> <span class="muted">(<?= e(support_role_label((string) $chat['author_role'])) ?>)</span></strong>
              <p><?= nl2br(e((string) $chat['message'])) ?></p>
              <small><?= e(date('M j, Y g:ia', strtotime((string) $chat['created_at']))) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!in_array((string) $selectedSupportTicket['status'], ['resolved', 'closed', 'rejected'], true)): ?>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="academy_support_reply">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedSupportTicket['ticket_ref']) ?>">
            <label>Reply to Academy Support
              <textarea name="reply" required placeholder="Add more detail or answer the support team's question."></textarea>
            </label>
            <button type="submit"><i class="fas fa-reply"></i> Add Reply</button>
          </form>
        <?php else: ?>
          <div class="empty">This ticket is closed. Open a new Academy help request if you still need support.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty">Select a ticket to view replies from Academy support.</div>
      <?php endif; ?>
    </article>
  </section>