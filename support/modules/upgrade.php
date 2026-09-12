<div class="support-view-container">
  <!-- Calm Hero Header -->
  <div class="support-page-hero">
    <div class="hero-text-content">
      <h1>Platform Portals & Registration Paths</h1>
      <p class="hero-subtext">A public support inquiry is for assistance. To participate directly in the NATCODEV coconut ecosystem, select the verified onboarding pathway tailored to your role.</p>
    </div>
    <span class="badge info" style="align-self:center;">
      <i class="fas fa-route"></i> Stakeholder Onboarding
    </span>
  </div>

  <div class="portals-pathways-grid">
    <?php foreach ($upgradePaths as $path): ?>
      <div class="portal-path-card">
        <div class="path-card-top">
          <div class="path-icon-box">
            <i class="fas <?= e($path['icon']) ?>"></i>
          </div>
          <div class="path-title-group">
            <h3><?= e($path['title']) ?></h3>
            <span class="path-role-tag">Verified Pathway</span>
          </div>
        </div>
        <p class="path-desc"><?= e($path['text']) ?></p>
        <div class="path-action-row">
          <a class="btn btn-outline" href="<?= e($path['href']) ?>">
            <span><?= e($path['label']) ?></span>
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Helpful Footnote Card -->
  <div class="pathways-note-card">
    <div class="note-icon"><i class="fas fa-info-circle"></i></div>
    <div class="note-content">
      <strong>Already Registered?</strong>
      <p>If you already hold an account, you can sign in directly through your dedicated portal above or visit the <a href="index.php?view=new-ticket">Support Desk</a> if you need credential assistance.</p>
    </div>
  </div>
</div>
