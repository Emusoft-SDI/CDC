<div class="support-view-container">
  <!-- Calm Hero Header -->
  <div class="support-page-hero">
    <div class="hero-text-content">
      <h1>Track Ticket & Live Updates</h1>
      <p class="hero-subtext">Check the real-time status of your ticket, read direct replies from our support specialists, or send an update.</p>
    </div>
    <?php if ($selectedTicket): ?>
      <a href="index.php?view=lookup" class="btn btn-outline" style="align-self:center;">
        <i class="fas fa-search"></i> Track Different Ticket
      </a>
    <?php endif; ?>
  </div>

  <?php if (!$selectedTicket): ?>
    <!-- Ticket Search / Lookup Box -->
    <div class="support-form-card lookup-search-card">
      <div class="form-card-header">
        <div>
          <h2><?= $user ? 'Find a Support Request' : 'Enter Ticket Details' ?></h2>
          <p class="form-header-desc">Enter your tracking reference code and the email address you used when opening the request.</p>
        </div>
        <span class="badge info"><i class="fas fa-shield-halved"></i> Verified Access</span>
      </div>

      <form method="get" class="lookup-form">
        <input type="hidden" name="view" value="lookup">
        <div class="form-row-grid">
          <div class="form-group">
            <label for="lookup_ticket">Ticket Reference <span class="required">*</span></label>
            <input id="lookup_ticket" name="ticket" value="<?= e($lookupRef) ?>" placeholder="e.g. TIK-2026-0001" required>
          </div>
          <div class="form-group">
            <label for="lookup_email">Requester Email Address <span class="required">*</span></label>
            <input id="lookup_email" type="email" name="email" value="<?= e($lookupEmail ?: ($user['email'] ?? '')) ?>" placeholder="you@example.com" required>
          </div>
        </div>
        <div class="form-submit-row" style="margin-top:16px;">
          <button class="btn btn-primary" type="submit">
            <i class="fas fa-magnifying-glass"></i> Track Ticket
          </button>
        </div>
      </form>
    </div>

    <!-- User's Existing Tickets List (If Logged In) -->
    <?php if ($user && !empty($myTickets)): ?>
      <div class="support-tickets-history-card">
        <div class="form-card-header">
          <div>
            <h3>Your Recent Tickets</h3>
            <p class="form-header-desc">All support cases registered under your account (<?= count($myTickets) ?> total)</p>
          </div>
        </div>
        <div class="ticket-list-grid">
          <?php foreach ($myTickets as $ticket): ?>
            <a class="ticket-item-card" href="index.php?view=lookup&ticket=<?= e((string) $ticket['ticket_ref']) ?>&email=<?= e((string) $ticket['requester_email']) ?>">
              <div class="ticket-item-main">
                <div class="ticket-item-ref">
                  <i class="fas fa-ticket-alt"></i>
                  <strong><?= e((string) $ticket['ticket_ref']) ?></strong>
                </div>
                <h4 class="ticket-item-subject"><?= e((string) $ticket['subject']) ?></h4>
                <div class="ticket-item-meta">
                  <span><i class="fas fa-tag"></i> <?= e($categories[(string) $ticket['category']]['label'] ?? (string) $ticket['category']) ?></span>
                  <span><i class="fas fa-calendar-alt"></i> <?= date('M j, Y', strtotime((string) $ticket['created_at'])) ?></span>
                </div>
              </div>
              <div class="ticket-item-badge">
                <span class="badge <?= e(support_badge_class((string) $ticket['status'])) ?>">
                  <?= e(support_statuses()[(string) $ticket['status']] ?? (string) $ticket['status']) ?>
                </span>
                <span class="view-link-text">View Ticket <i class="fas fa-chevron-right"></i></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php elseif ($user): ?>
      <div class="support-empty-state">
        <i class="fas fa-folder-open empty-icon"></i>
        <h3>No Tickets Submitted Yet</h3>
        <p>You do not currently have any active or past support tickets under this account.</p>
        <a href="index.php?view=new-ticket" class="btn btn-primary" style="margin-top:12px;">
          <i class="fas fa-circle-plus"></i> Submit Your First Request
        </a>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- Active Ticket Detail View -->
    <div class="ticket-detail-wrapper">
      <!-- Ticket Header Info Card -->
      <div class="ticket-header-card">
        <div class="ticket-header-top">
          <div class="ticket-title-group">
            <span class="ticket-ref-tag">
              <i class="fas fa-ticket"></i> <?= e((string) $selectedTicket['ticket_ref']) ?>
            </span>
            <h2 class="ticket-subject"><?= e((string) $selectedTicket['subject']) ?></h2>
            <div class="ticket-meta-tags">
              <span class="meta-tag">
                <i class="fas fa-folder"></i> <?= e($categories[(string) $selectedTicket['category']]['label'] ?? (string) $selectedTicket['category']) ?>
              </span>
              <span class="meta-tag">
                <i class="fas fa-users"></i> Team: <?= e((string) $selectedTicket['assigned_team']) ?>
              </span>
              <span class="meta-tag">
                <i class="fas fa-clock"></i> Opened <?= date('M j, Y g:i A', strtotime((string) $selectedTicket['created_at'])) ?>
              </span>
              <?php if (!empty($selectedTicket['linked_record_ref'])): ?>
                <span class="meta-tag">
                  <i class="fas fa-link"></i> Ref: <?= e((string) $selectedTicket['linked_record_ref']) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="ticket-status-group">
            <span class="badge <?= e(support_badge_class((string) $selectedTicket['status'])) ?> badge-lg">
              <?= e(support_statuses()[(string) $selectedTicket['status']] ?? (string) $selectedTicket['status']) ?>
            </span>
            <span class="badge <?= e(support_badge_class((string) $selectedTicket['priority'])) ?>">
              <?= ucfirst((string) $selectedTicket['priority']) ?> Priority
            </span>
          </div>
        </div>
      </div>

      <!-- Ticket Conversation Stream -->
      <div class="ticket-conversation-section">
        <h3 class="conversation-heading">Conversation & Response History</h3>
        <div class="conversation-thread">
          <?php foreach ($conversation as $msg): ?>
            <?php $isAgent = !empty($msg['admin_id']) || (string) ($msg['author_role'] ?? '') === 'support_admin'; ?>
            <div class="conversation-bubble-wrap <?= $isAgent ? 'agent-reply' : 'user-reply' ?>">
              <div class="message-bubble">
                <div class="message-bubble-header">
                  <div class="author-info">
                    <i class="fas <?= $isAgent ? 'fa-user-tie text-success' : 'fa-user-circle text-muted' ?>"></i>
                    <strong><?= e((string) $msg['author_name']) ?></strong>
                    <?php if ($isAgent): ?>
                      <span class="agent-tag">Support Specialist</span>
                    <?php else: ?>
                      <span class="client-tag">Requester</span>
                    <?php endif; ?>
                  </div>
                  <span class="message-time">
                    <?= date('M j, Y g:i A', strtotime((string) $msg['created_at'])) ?>
                  </span>
                </div>
                <div class="message-bubble-body">
                  <?= nl2br(e((string) $msg['message'])) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Reply Box (if ticket is active) -->
      <?php if (!in_array((string) $selectedTicket['status'], ['resolved', 'closed', 'rejected'], true)): ?>
        <div class="ticket-reply-card">
          <div class="reply-card-header">
            <h3><i class="fas fa-reply"></i> Add an Update or Reply</h3>
            <p>Send additional information, answer questions from the specialist, or request further assistance.</p>
          </div>
          <form method="post" class="ticket-reply-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedTicket['ticket_ref']) ?>">
            <input type="hidden" name="email" value="<?= e((string) $selectedTicket['requester_email']) ?>">
            
            <div class="form-group">
              <label for="reply_text">Your Message <span class="required">*</span></label>
              <textarea id="reply_text" name="reply" rows="4" placeholder="Type your reply here..." required></textarea>
            </div>
            <div class="form-submit-row">
              <button class="btn btn-primary" type="submit">
                <i class="fas fa-paper-plane"></i> Send Reply
              </button>
            </div>
          </form>
        </div>
      <?php else: ?>
        <div class="ticket-closed-notice">
          <i class="fas fa-circle-check text-success"></i>
          <div>
            <strong>This ticket has been marked as <?= e(ucfirst((string) $selectedTicket['status'])) ?>.</strong>
            <p>If you have any further questions or require new assistance, please <a href="index.php?view=new-ticket">submit a new request</a>.</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Satisfaction Rating (if resolved and unrated) -->
      <?php if ((string) $selectedTicket['status'] === 'resolved' && empty($selectedTicket['rating'])): ?>
        <div class="ticket-rating-card">
          <div class="rating-header">
            <i class="fas fa-star text-gold"></i>
            <div>
              <h3>How was your support experience?</h3>
              <p>Your honest feedback helps us improve our service for all community members.</p>
            </div>
          </div>
          <form method="post" class="rating-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="rate_ticket">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedTicket['ticket_ref']) ?>">
            <input type="hidden" name="email" value="<?= e((string) $selectedTicket['requester_email']) ?>">
            
            <div class="rating-select-group">
              <label>Select Rating Score <span class="required">*</span></label>
              <div class="rating-options-grid">
                <label class="rating-choice-pill">
                  <input type="radio" name="rating" value="5" checked>
                  <span><i class="fas fa-star"></i> 5 - Excellent</span>
                </label>
                <label class="rating-choice-pill">
                  <input type="radio" name="rating" value="4">
                  <span><i class="fas fa-star"></i> 4 - Good</span>
                </label>
                <label class="rating-choice-pill">
                  <input type="radio" name="rating" value="3">
                  <span><i class="fas fa-star"></i> 3 - Average</span>
                </label>
                <label class="rating-choice-pill">
                  <input type="radio" name="rating" value="2">
                  <span><i class="fas fa-star"></i> 2 - Poor</span>
                </label>
                <label class="rating-choice-pill">
                  <input type="radio" name="rating" value="1">
                  <span><i class="fas fa-star"></i> 1 - Terrible</span>
                </label>
              </div>
            </div>

            <div class="form-group" style="margin-top:16px;">
              <label for="feedback_comment">Comments or Observations (Optional)</label>
              <textarea id="feedback_comment" name="feedback_comment" rows="3" placeholder="Tell us how we did or how we could improve..."></textarea>
            </div>

            <div class="form-submit-row" style="margin-top:14px;">
              <button class="btn btn-primary" type="submit">
                <i class="fas fa-check-circle"></i> Submit Rating & Feedback
              </button>
            </div>
          </form>
        </div>
      <?php elseif (!empty($selectedTicket['rating'])): ?>
        <div class="ticket-rated-badge">
          <i class="fas fa-star text-gold"></i>
          <span>You rated this support experience <strong><?= (int) $selectedTicket['rating'] ?> / 5 stars</strong>. Thank you for your feedback!</span>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
