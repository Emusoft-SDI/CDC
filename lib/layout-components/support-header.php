<?php
// Layout Component: support-header.php
$currentView = (string) ($_GET['view'] ?? 'new-ticket');
?>
<header class="support-top-header">
  <div class="support-header-container">
    <div class="support-brand-group">
      <a class="support-brand" href="../index.php" title="NATCODEV Homepage">
        <img src="<?= e($logo) ?>" alt="NATCODEV" class="support-brand-logo">
        <div class="support-brand-text">
          <span class="support-brand-title">NATCODEV</span>
          <span class="support-brand-sub">Help & Support Desk</span>
        </div>
      </a>
      <a href="../index.php" class="support-return-home" title="Return to Homepage">
        <i class="fas fa-arrow-left"></i>
        <span>Return to Homepage</span>
      </a>
    </div>

    <nav class="support-top-nav" aria-label="Support navigation">
      <a href="index.php?view=new-ticket" class="support-nav-link <?= $currentView === 'new-ticket' ? 'active' : '' ?>">
        <i class="fas fa-circle-plus"></i> <span>New Request</span>
      </a>
      <a href="index.php?view=lookup" class="support-nav-link <?= $currentView === 'lookup' ? 'active' : '' ?>">
        <i class="fas fa-magnifying-glass"></i> <span>Track Ticket</span>
      </a>
      <a href="index.php?view=knowledge" class="support-nav-link <?= $currentView === 'knowledge' ? 'active' : '' ?>">
        <i class="fas fa-book-open"></i> <span>Help & FAQs</span>
      </a>
      <a href="index.php?view=upgrade" class="support-nav-link <?= $currentView === 'upgrade' ? 'active' : '' ?>">
        <i class="fas fa-route"></i> <span>Portals & Paths</span>
      </a>

      <!-- Access Member Area Dropdown -->
      <div class="support-member-dropdown" id="memberDropdown">
        <button class="support-member-btn" type="button" aria-haspopup="true" aria-expanded="false" id="memberDropdownBtn">
          <i class="fas fa-user-circle"></i>
          <span><?= $user ? e($user['name'] ?? 'Member Area') : 'Access Member Area' ?></span>
          <i class="fas fa-chevron-down caret-icon"></i>
        </button>
        <div class="support-member-menu" role="menu" id="memberDropdownMenu">
          <?php if ($user): ?>
            <div class="dropdown-header">Logged In: <?= e($user['email'] ?? '') ?></div>
            <a href="../dashboard/index.php" class="dropdown-item" role="menuitem">
              <i class="fas fa-gauge-high"></i>
              <div>
                <strong>Grower Dashboard</strong>
                <small>Farm records, certificates & wallet</small>
              </div>
            </a>
            <a href="../market/orders.php" class="dropdown-item" role="menuitem">
              <i class="fas fa-shopping-bag"></i>
              <div>
                <strong>Marketplace Orders</strong>
                <small>View purchases & delivery status</small>
              </div>
            </a>
            <a href="../logout.php" class="dropdown-item text-danger" role="menuitem">
              <i class="fas fa-sign-out-alt"></i>
              <div><strong>Sign Out</strong></div>
            </a>
          <?php else: ?>
            <div class="dropdown-header">Stakeholder Portals</div>
            <a href="../dashboard/login.php" class="dropdown-item" role="menuitem">
              <i class="fas fa-seedling"></i>
              <div>
                <strong>Grower Portal</strong>
                <small>Farmers, producers & farm registry</small>
              </div>
            </a>
            <a href="../buyer/login.php" class="dropdown-item" role="menuitem">
              <i class="fas fa-cart-shopping"></i>
              <div>
                <strong>Buyer Portal</strong>
                <small>Coconut off-takers & corporate buyers</small>
              </div>
            </a>
            <a href="../provider/login.php" class="dropdown-item" role="menuitem">
              <i class="fas fa-truck-fast"></i>
              <div>
                <strong>Service Provider Central</strong>
                <small>Logistics, inputs & mechanization</small>
              </div>
            </a>
            <a href="../academy/login.php" class="dropdown-item" role="menuitem">
              <i class="fas fa-graduation-cap"></i>
              <div>
                <strong>Academy Learner</strong>
                <small>Courses, training & certificates</small>
              </div>
            </a>
            <a href="../field-agent/login.php" class="dropdown-item" role="menuitem">
              <i class="fas fa-user-shield"></i>
              <div>
                <strong>Field Agent Portal</strong>
                <small>Enumerator & field verification access</small>
              </div>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </nav>
  </div>
</header>
