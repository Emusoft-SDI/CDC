<?php
// Layout Component: support-sidebar.php
$currentView = (string) ($_GET['view'] ?? 'new-ticket');
?>
<aside class="support-rail" aria-label="Support navigation sidebar">
  <!-- Return to Homepage Card -->
  <div class="support-rail-card return-home-card">
    <a href="../index.php" class="support-rail-home-btn">
      <i class="fas fa-arrow-left"></i>
      <div>
        <span class="home-title">Return to Homepage</span>
        <span class="home-sub">Back to main website</span>
      </div>
    </a>
  </div>

  <!-- Primary Support Navigation -->
  <div class="support-rail-card">
    <h3 class="support-rail-heading">Support Desk</h3>
    <nav class="support-rail-nav">
      <a href="index.php?view=new-ticket" class="support-rail-link <?= $currentView === 'new-ticket' ? 'active' : '' ?>">
        <i class="fas fa-circle-plus"></i>
        <span>Submit a Request</span>
      </a>
      <a href="index.php?view=lookup" class="support-rail-link <?= $currentView === 'lookup' ? 'active' : '' ?>">
        <i class="fas fa-magnifying-glass"></i>
        <span>Track Ticket Status</span>
      </a>
      <a href="index.php?view=knowledge" class="support-rail-link <?= $currentView === 'knowledge' ? 'active' : '' ?>">
        <i class="fas fa-book-open"></i>
        <span>Knowledge Base & FAQs</span>
      </a>
      <a href="index.php?view=support-flow" class="support-rail-link <?= $currentView === 'support-flow' ? 'active' : '' ?>">
        <i class="fas fa-list-check"></i>
        <span>How Resolution Works</span>
      </a>
      <a href="index.php?view=upgrade" class="support-rail-link <?= $currentView === 'upgrade' ? 'active' : '' ?>">
        <i class="fas fa-route"></i>
        <span>Portals & Registration</span>
      </a>
    </nav>
  </div>

  <!-- Member Area Quick Access -->
  <div class="support-rail-card member-area-card">
    <h3 class="support-rail-heading">
      <i class="fas fa-users-viewfinder"></i> Member Area
    </h3>
    <p class="support-rail-sub">Quick links to stakeholder portals:</p>
    <div class="member-links-stack">
      <?php if ($user): ?>
        <a href="../dashboard/index.php" class="member-portal-row">
          <i class="fas fa-gauge-high"></i>
          <span>My Grower Dashboard</span>
        </a>
        <a href="../market/orders.php" class="member-portal-row">
          <i class="fas fa-shopping-bag"></i>
          <span>Marketplace Orders</span>
        </a>
      <?php else: ?>
        <a href="../dashboard/login.php" class="member-portal-row">
          <i class="fas fa-seedling"></i>
          <span>Grower Portal Login</span>
        </a>
        <a href="../buyer/login.php" class="member-portal-row">
          <i class="fas fa-cart-shopping"></i>
          <span>Buyer Portal Login</span>
        </a>
        <a href="../provider/login.php" class="member-portal-row">
          <i class="fas fa-truck-fast"></i>
          <span>Provider Central</span>
        </a>
        <a href="../academy/login.php" class="member-portal-row">
          <i class="fas fa-graduation-cap"></i>
          <span>Academy Learner Login</span>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Direct Help & Contact Card -->
  <div class="support-rail-card helpdesk-contact-card">
    <h3 class="support-rail-heading">
      <i class="fas fa-headset text-success"></i> Direct Helpdesk
    </h3>
    <p class="support-rail-sub">Need immediate support or have urgent questions?</p>
    <div class="rail-contact-list">
      <div class="rail-contact-item">
        <i class="fas fa-phone-volume"></i>
        <div>
          <span class="label">Phone Line</span>
          <a href="tel:<?= e($office['phone_tel']) ?>"><?= e($office['phone_display']) ?></a>
        </div>
      </div>
      <div class="rail-contact-item">
        <i class="fas fa-envelope-open-text"></i>
        <div>
          <span class="label">Email Address</span>
          <a href="mailto:support@natcodev.com.ng">support@natcodev.com.ng</a>
        </div>
      </div>
      <div class="rail-contact-item">
        <i class="fas fa-clock"></i>
        <div>
          <span class="label">Working Hours</span>
          <span class="val">Mon – Fri: 8:00 AM – 5:00 PM</span>
        </div>
      </div>
    </div>
  </div>
</aside>
