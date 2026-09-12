<div class="support-view-container">
  <!-- Calm Hero Header -->
  <div class="support-page-hero">
    <div class="hero-text-content">
      <h1>How Support Resolution Works</h1>
      <p class="hero-subtext">Our structured support resolution system ensures every inquiry is routed swiftly, investigated thoroughly, and solved with complete accountability.</p>
    </div>
    <a href="index.php?view=new-ticket" class="btn btn-primary" style="align-self:center;">
      <i class="fas fa-circle-plus"></i> Open New Ticket
    </a>
  </div>

  <div class="resolution-timeline-flow">
    <?php
    $steps = [
      [
        'step' => '01',
        'icon' => 'fa-ticket-simple',
        'title' => 'Submit Request',
        'desc' => 'Open a request online with your details, subject, and any reference codes (Order, Transaction, Certificate). You receive an instant tracking reference.'
      ],
      [
        'step' => '02',
        'icon' => 'fa-network-wired',
        'title' => 'Automated Smart Routing',
        'desc' => 'Your inquiry is instantly assigned to the designated departmental queue (Payment Audits, Farm Registry, Academy, or Marketplace Logistics).'
      ],
      [
        'step' => '03',
        'icon' => 'fa-user-check',
        'title' => 'Specialist Review',
        'desc' => 'A qualified specialist investigates the issue, verifies system logs or transaction records, and responds within 2 hours during active business periods.'
      ],
      [
        'step' => '04',
        'icon' => 'fa-comments',
        'title' => 'Interactive Updates',
        'desc' => 'You can track real-time status, read specialist replies, and provide follow-up explanations directly on your tracking page without logging in.'
      ],
      [
        'step' => '05',
        'icon' => 'fa-circle-check',
        'title' => 'Swift Resolution',
        'desc' => 'Once the underlying issue or transaction is completed, the ticket is marked resolved with full summary documentation.'
      ],
      [
        'step' => '06',
        'icon' => 'fa-star',
        'title' => 'Feedback & Assurance',
        'desc' => 'You are invited to rate your support specialist. Feedback is audited weekly by NATCODEV leadership to maintain service excellence.'
      ]
    ];
    ?>

    <div class="steps-card-stack">
      <?php foreach ($steps as $s): ?>
        <div class="resolution-step-card">
          <div class="step-card-badge">
            <span><?= e($s['step']) ?></span>
          </div>
          <div class="step-card-icon">
            <i class="fas <?= e($s['icon']) ?>"></i>
          </div>
          <div class="step-card-body">
            <h3><?= e($s['title']) ?></h3>
            <p><?= e($s['desc']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="support-flow-cta-card">
    <div class="flow-cta-content">
      <h3>Ready to get started?</h3>
      <p>Have an active issue that needs attention? Open a case now and our team will get on it immediately.</p>
    </div>
    <div class="flow-cta-actions">
      <a href="index.php?view=new-ticket" class="btn btn-primary">
        <i class="fas fa-plus-circle"></i> Create Support Ticket
      </a>
      <a href="index.php?view=lookup" class="btn btn-outline">
        <i class="fas fa-magnifying-glass"></i> Track Existing Ticket
      </a>
    </div>
  </div>
</div>
