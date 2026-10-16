<?php
/**
 * Homepage Template A (original dense layout).
 *
 * Required from index.php scope ($roles, $features, $trust, $recentNews).
 * Renders the original two-button hero, then the shared landing sections.
 */
?>
    <section class="hero">
      <div class="hero-inner">
        <div class="hero-copy">
          <h1>National Coconut <span>Development & Propagation</span> Initiative</h1>
          <p>Register, verify, train, trade, and grow with one connected coconut development platform.</p>
          <div class="hero-actions">
            <a class="btn primary" href="apply.php?type=farmer"><i class="fas fa-user-plus"></i> Register Now</a>
            <a class="btn light" href="market/index.php"><i class="fas fa-cart-shopping"></i> Explore Marketplace</a>
          </div>
        </div>
      </div>
    </section>
    <?php require __DIR__ . '/home-common.php'; ?>
