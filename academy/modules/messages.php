<section class="support-stats" aria-label="Academy message and support summary">
    <div class="support-stat"><strong><?= count($messages) ?></strong><span>Messages</span></div>
    <div class="support-stat"><strong><?= (int) ($supportStats['open'] ?? 0) ?></strong><span>Open Tickets</span></div>
    <div class="support-stat"><strong><?= (int) ($supportStats['waiting_on_user'] ?? 0) ?></strong><span>Needs Reply</span></div>
    <div class="support-stat"><strong><?= (int) ($supportStats['resolved'] ?? 0) ?></strong><span>Resolved</span></div>
  </section>

  <section class="grid g2">
    <article class="card">
      <div class="card-h"><h3>Academy Messages</h3><?= ac_badge('active', (string) count($messages) . ' Update(s)') ?></div>
      <?php foreach ($messages as $msg): ?>
        <div class="support-message <?= (int) ($msg['is_read'] ?? 0) === 0 ? 'agent' : '' ?>">
          <strong><?= e((string) ($msg['ticket_id'] ?: 'Academy update')) ?> <?= ac_badge((int) $msg['is_read'] === 0 ? 'pending' : 'completed', (int) $msg['is_read'] === 0 ? 'New' : 'Read') ?></strong>
          <p><?= nl2br(e((string) $msg['message'])) ?></p>
          <?php if (!empty($msg['created_at'])): ?><small class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></small><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$messages): ?><div class="empty">No Academy messages yet. Course notices and support updates will appear here.</div><?php endif; ?>
    </article>

    <form class="card" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="academy_support_create">
      <input type="hidden" name="return_screen" value="messages">
      <div class="card-h"><h3>New Help Request</h3><?= ac_badge('active', 'Academy Support') ?></div>
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
      <label>Reference or Course Name<input name="linked_record_ref" placeholder="Course title, payment ref, certificate ref"></label>
      <label>Subject<input name="subject" maxlength="190" placeholder="e.g., Certificate correction for course X" required></label>
      <label>Describe the issue<textarea name="description" placeholder="Tell the Academy team what happened, what you expected, and any reference number." required></textarea></label>
      <button type="submit"><i class="fas fa-paper-plane"></i> Send Message To Support</button>
    </form>
  </section>

  <section class="grid g2" style="margin-top:18px">
    <article class="card">
      <div class="card-h"><h3>My Support Threads</h3><?= ac_badge('active', (string) count($learnerTickets) . ' Ticket(s)') ?></div>
      <div class="support-ticket-list">
        <?php foreach ($learnerTickets as $ticket): ?>
          <a class="support-ticket <?= $selectedSupportTicket && (int) $selectedSupportTicket['id'] === (int) $ticket['id'] ? 'active' : '' ?>" href="dashboard.php?screen=messages&ticket=<?= urlencode((string) $ticket['ticket_ref']) ?>">
            <strong><?= e((string) $ticket['ticket_ref']) ?></strong>
            <small><?= e((string) $ticket['subject']) ?></small>
            <?= ac_badge((string) $ticket['status'], $supportStatuses[(string) $ticket['status']] ?? ac_status((string) $ticket['status'])) ?>
          </a>
        <?php endforeach; ?>
        <?php if (!$learnerTickets): ?><div class="empty">You have not opened any Academy support thread yet. Use the form above to contact support.</div><?php endif; ?>
      </div>
    </article>

    <article class="card">
      <div class="card-h"><h3>Thread Conversation</h3><?php if ($selectedSupportTicket): ?><?= ac_badge((string) $selectedSupportTicket['status'], $supportStatuses[(string) $selectedSupportTicket['status']] ?? ac_status((string) $selectedSupportTicket['status'])) ?><?php endif; ?></div>
      <?php if ($selectedSupportTicket): ?>
        <p class="muted"><strong><?= e((string) $selectedSupportTicket['ticket_ref']) ?></strong> / <?= e((string) $selectedSupportTicket['subject']) ?><br>Assigned to <?= e((string) ($selectedSupportTicket['assigned_team'] ?: 'Academy Support')) ?></p>
        <div class="support-chat">
          <?php foreach ($selectedSupportMessages as $chat): ?>
            <div class="support-message <?= e((string) $chat['author_role']) ?>">
              <strong><?= e((string) $chat['author_name']) ?> <span class="muted">(<?= e(support_role_label((string) $chat['author_role'])) ?>)</span></strong>
              <p><?= nl2br(e((string) $chat['message'])) ?></p>
              <small class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $chat['created_at']))) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!in_array((string) $selectedSupportTicket['status'], ['resolved', 'closed', 'rejected'], true)): ?>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="academy_support_reply">
            <input type="hidden" name="return_screen" value="messages">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedSupportTicket['ticket_ref']) ?>">
            <label>Reply to Academy Support<textarea name="reply" required placeholder="Add more detail or answer the support team's question."></textarea></label>
            <button type="submit">Send Reply</button>
          </form>
        <?php else: ?>
          <div class="empty">This thread is closed. Open a new help request above if you still need support.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty">Select a support thread to read the conversation and reply here.</div>
      <?php endif; ?>
    </article>
  </section>