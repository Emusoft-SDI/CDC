<?php
/**
 * Homepage Template B (focus-driven, less dense).
 *
 * Required from index.php scope ($roles, $features, $trust, $recentNews).
 * One primary hero CTA with a quiet secondary link, then the shared landing
 * sections. Density is reduced via the .home-template-b CSS overrides in
 * index.php (4-column role/feature grids, larger icons, more whitespace).
 */
?>
    <section class="hero">
      <div class="hero-inner">
        <div class="hero-copy">
          <h1>National Coconut <span>Development & Propagation</span> Initiative</h1>
          <p>Register, verify, train, trade, and grow with one connected coconut development platform.</p>
          <div class="hero-actions">
            <a class="btn primary" href="apply.php?type=farmer"><i class="fas fa-user-plus"></i> Get Started</a>
            <a class="quiet-link" href="market/index.php">Explore the Marketplace <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </section>
    <?php require __DIR__ . '/home-common.php'; ?>
