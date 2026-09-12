<div class="support-view-container">
  <!-- Calm, reassuring hero header -->
  <div class="support-page-hero">
    <div class="hero-text-content">
      <h1>We’re here to help you</h1>
      <p class="hero-subtext">Have a question about your account, payment, farm registration, or marketplace order? Fill out the form below, and our support specialists will assist you.</p>
    </div>
    <div class="hero-badge-reassurance">
      <div class="reassurance-icon"><i class="fas fa-headset"></i></div>
      <div class="reassurance-text">
        <strong>Average Response: &lt; 2 hours</strong>
        <span>Mon – Fri, 8:00 AM – 5:00 PM</span>
      </div>
    </div>
  </div>

  <!-- Quick Category Selector -->
  <div class="support-category-pills">
    <span class="pills-label">Common Topics:</span>
    <div class="pills-list">
      <?php foreach ($categories as $key => $cat): ?>
        <button type="button" class="category-pill <?= $prefillCategory === $key ? 'active' : '' ?>" 
                onclick="selectCategory('<?= e($key) ?>', this)">
          <i class="fas <?= e($cat['icon']) ?>"></i>
          <span><?= e($cat['label']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Main Ticket Submission Form -->
  <div class="support-form-card">
    <div class="form-card-header">
      <div>
        <h2>Create a Support Ticket</h2>
        <p class="form-header-desc">Please provide as much detail as possible so we can route your ticket to the right department immediately.</p>
      </div>
      <span class="badge info">
        <i class="fas fa-shield-halved"></i> <?= e(support_role_label(support_role_key($user))) ?>
      </span>
    </div>

    <form method="post" class="support-ticket-form" id="ticketForm">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">

      <div class="form-row-grid">
        <div class="form-group">
          <label for="ticket_name">Your Full Name <span class="required">*</span></label>
          <input id="ticket_name" name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" required placeholder="e.g. Adebayo Ogunlesi">
        </div>

        <div class="form-group">
          <label for="ticket_email">Email Address <span class="required">*</span></label>
          <input id="ticket_email" type="email" name="email" value="<?= e((string) ($user['email'] ?? '')) ?>" required placeholder="you@example.com">
          <small class="field-hint">We'll send your tracking reference and responses here.</small>
        </div>
      </div>

      <div class="form-row-grid">
        <div class="form-group">
          <label for="ticket_phone">Phone Number (Optional)</label>
          <input id="ticket_phone" type="tel" name="phone" value="" placeholder="e.g. 0801 234 5678">
        </div>

        <div class="form-group">
          <label for="category_select">Department / Topic <span class="required">*</span></label>
          <select id="category_select" name="category" required>
            <?php foreach ($categories as $key => $cat): ?>
              <option value="<?= e($key) ?>" <?= $prefillCategory === $key ? 'selected' : '' ?>>
                <?= e($cat['label']) ?> (<?= e($cat['team']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row-grid">
        <div class="form-group">
          <label for="priority_select">Urgency / Priority</label>
          <select id="priority_select" name="priority">
            <?php foreach ($priorities as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="linked_record_type">Related Service (Optional)</label>
          <select id="linked_record_type" name="linked_record_type">
            <option value="">None / General Question</option>
            <option value="order">Marketplace Order</option>
            <option value="wallet_transaction">Wallet / Payment Transaction</option>
            <option value="application">Grower Registration / ID Validation</option>
            <option value="course_enrollment">Academy Course / Training</option>
            <option value="certificate">Certification Verification</option>
          </select>
        </div>
      </div>

      <div class="form-group field-full">
        <label for="ticket_subject">Subject / Issue Summary <span class="required">*</span></label>
        <input id="ticket_subject" name="subject" placeholder="Brief summary of what you need help with" required>
      </div>

      <div class="form-group field-full">
        <label for="ticket_description">Detailed Explanation <span class="required">*</span></label>
        <textarea id="ticket_description" name="description" rows="5" placeholder="Please describe what happened, dates, any error messages, or details that will help us resolve this swiftly." required></textarea>
      </div>

      <div class="form-group field-full">
        <label for="ticket_linked_ref">Reference Number (Optional)</label>
        <input id="ticket_linked_ref" name="linked_record_ref" placeholder="Order ID (e.g. ORD-...), Monnify Reference, Certificate Number, or Transaction Ref">
        <small class="field-hint">If your inquiry relates to a payment, order, or application, providing the reference helps resolve it much faster.</small>
      </div>

      <div class="form-submit-row">
        <button class="btn btn-primary" type="submit" id="submitTicketBtn">
          <i class="fas fa-paper-plane"></i>
          <span>Submit Support Request</span>
        </button>
        <div class="form-submit-note">
          <i class="fas fa-lock"></i>
          <span>Your request is secure and private. You will receive an instant tracking link.</span>
        </div>
      </div>
    </form>
  </div>

  <!-- Reassuring Process Timeline Steps -->
  <div class="support-reassurance-cards">
    <div class="reassurance-card">
      <div class="step-num">1</div>
      <div class="step-details">
        <h4>Instant Confirmation</h4>
        <p>You receive an automated reference code to track live status without needing to log in.</p>
      </div>
    </div>
    <div class="reassurance-card">
      <div class="step-num">2</div>
      <div class="step-details">
        <h4>Specialist Routing</h4>
        <p>Your ticket is automatically routed to the right team (Payments, Registry, Academy, or Market).</p>
      </div>
    </div>
    <div class="reassurance-card">
      <div class="step-num">3</div>
      <div class="step-details">
        <h4>Prompt Resolution</h4>
        <p>Receive clear updates and replies. You can reply directly or rate your representative once resolved.</p>
      </div>
    </div>
  </div>
</div>

<script>
function selectCategory(catKey, btn) {
  const select = document.getElementById('category_select');
  if (select) {
    select.value = catKey;
  }
  document.querySelectorAll('.category-pill').forEach(el => el.classList.remove('active'));
  if (btn) {
    btn.classList.add('active');
  }
}
</script>
