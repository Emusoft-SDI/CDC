<?php
/**
 * Shared homepage landing sections (Template A + Template B).
 *
 * Required from index.php scope, so it receives $roles, $features, $trust and
 * $recentNews. Keeping these sections in one partial is what lets an operator
 * switch templates without duplicating the role grid, feature strip, news and
 * story markup between the two templates.
 */
?>
    <section id="registry" class="role-panel">
      <h2 class="section-title"><i class="fas fa-leaf"></i> Choose your role. Start your journey. <i class="fas fa-leaf"></i></h2>
      <div class="role-grid">
        <?php foreach ($roles as $role): ?>
          <a class="role-card <?= e($role['tone']) ?>" href="<?= e($role['href']) ?>">
            <span class="role-icon"><i class="fas <?= e($role['icon']) ?>"></i></span>
            <h3><?= e($role['title']) ?></h3>
            <p><?= e($role['text']) ?></p>
            <span class="arrow"><i class="fas fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section id="programs" class="platform">
      <h2 class="section-title"><i class="fas fa-leaf"></i> One Platform, Every Stakeholder <i class="fas fa-leaf"></i></h2>
      <div class="feature-grid">
        <?php foreach ($features as $feature): ?>
          <a class="feature-card" href="<?= e($feature['href']) ?>">
            <i class="fas <?= e($feature['icon']) ?>"></i>
            <span><h3><?= e($feature['title']) ?></h3><p><?= e($feature['text']) ?></p></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="trust-row">
        <?php foreach ($trust as $item): ?>
          <div class="trust-item"><i class="fas <?= e($item['icon']) ?>"></i><span><?= e($item['title']) ?><br><?= e($item['text']) ?></span></div>
        <?php endforeach; ?>
        <a class="trust-item learn" href="#about">Learn More About NATCODEV <i class="fas fa-chevron-right"></i></a>
      </div>
    </section>

    <?php if (!empty($recentNews)): ?>
      <!-- Latest News & Announcements -->
      <section id="updates" class="news-teaser">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
          <h2 style="color:var(--green);font-size:1.8rem;margin:0;font-weight:900;"><i class="fas fa-newspaper"></i> Latest News & Bulletins</h2>
          <a href="news.php" class="btn primary" style="min-height:38px;padding:8px 18px;font-size:0.92rem;">View All Releases &rarr;</a>
        </div>
        <div class="news-teaser-grid">
          <?php foreach ($recentNews as $post):
            $img = !empty($post['image_url']) ? $post['image_url'] : 'assets/public/natcodev-community-impact.png';
            $detailUrl = 'news-detail.php?slug=' . urlencode((string) $post['slug']);
          ?>
            <article class="teaser-card">
              <a href="<?= e($detailUrl) ?>">
                <img src="<?= e($img) ?>" alt="<?= e($post['title']) ?>" class="teaser-img" onerror="this.src='assets/public/natcodev-community-impact.png'">
              </a>
              <div class="teaser-body">
                <span style="background:rgba(8,117,58,0.1);color:var(--green2);font-size:0.75rem;font-weight:800;text-transform:uppercase;padding:4px 10px;border-radius:12px;align-self:flex-start;margin-bottom:10px;"><?= e($post['category'] ?? 'General') ?></span>
                <h3 style="margin:0 0 8px;font-size:1.15rem;color:var(--green);line-height:1.4;"><a href="<?= e($detailUrl) ?>"><?= e($post['title']) ?></a></h3>
                <p style="margin:0 0 16px;color:#475569;font-size:0.9rem;line-height:1.5;flex-grow:1;"><?= e(mb_strimwidth((string)($post['summary'] ?? strip_tags($post['content'])), 0, 120, '...')) ?></p>
                <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--line);padding-top:12px;font-size:0.82rem;color:var(--muted);margin-top:auto;">
                  <span><?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                  <a href="<?= e($detailUrl) ?>" style="color:var(--green2);font-weight:800;">Read More &rarr;</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section id="about" class="story">
      <div class="story-img" aria-label="Coconut farmers and marketplace community"></div>
      <article class="story-card">
        <h2>A meaningful entry point for the whole coconut value chain.</h2>
        <p>NATCODEV brings registry, verification, Academy training, certificates, marketplace access, wallet payments, farm operations, reporting, and support into one public-facing platform for growers, farm hands, providers, sellers, buyers, field teams, coordinators, and visitors.</p>
        <div class="quick-links">
          <a href="market/index.php"><i class="fas fa-store"></i> Marketplace</a>
          <a href="academy/index.php"><i class="fas fa-graduation-cap"></i> Academy</a>
          <a href="verify-certificate.php"><i class="fas fa-award"></i> Verify Certificate</a>
          <a href="support/index.php"><i class="fas fa-headset"></i> Support Desk</a>
        </div>
      </article>
    </section>
