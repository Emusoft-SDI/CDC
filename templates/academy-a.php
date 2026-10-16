<?php
/**
 * Academy Template A (original dense layout).
 *
 * Required from academy/index.php. Receives $hero, $homepage, $featuredCourse,
 * $courseImages, $learnerName, $user.
 * Renders the original dense hero (headline + lede + two CTAs + trust) plus
 * the featured course passport, then the shared Academy landing sections.
 */
?>
  <section class="hero">
    <div class="hero-bg" aria-hidden="true"><img src="<?= e($hero) ?>" alt="" loading="eager"></div>
    <svg class="hero-leaf" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C7 7 4 11 4 15a8 8 0 0 0 16 0c0-4-3-8-8-13zm0 18a5 5 0 0 1-5-5c0-2.5 1.7-5 5-8.5 3.3 3.5 5 6 5 8.5a5 5 0 0 1-5 5z"/></svg>
    <div class="hero-inner">
      <div class="hero-copy">
        <span class="eyebrow"><?= e($homepage['eyebrow']) ?></span>
        <h1><?= $homepage['headline'] ?></h1>
        <p class="lede"><?= $homepage['lede'] ?></p>
        <div class="hero-actions">
          <?php if ($user): ?>
            <a class="btn btn-gold" href="dashboard.php?screen=catalog"><i class="fas fa-user-graduate"></i> Continue Learning, <?= e(mb_strimwidth($learnerName, 0, 24, '')) ?></a>
          <?php else: ?>
            <a class="btn btn-gold" href="register.php"><i class="fas fa-user-graduate"></i> Start Learning Free</a>
          <?php endif; ?>
          <a class="btn btn-ghost" href="#catalog"><i class="fas fa-book-open"></i> Browse Courses</a>
        </div>
        <div class="hero-trust">
          <?php foreach ($homepage['trust'] as $trustLabel): ?>
          <div><i class="fas fa-circle-check"></i> <?= e($trustLabel) ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="passport reveal" aria-label="Featured course">
        <div class="passport-card">
          <?php if ($featuredCourse): ?>
            <span class="passport-badge">Featured</span>
            <div class="passport-media">
              <?php $featThumb = academy_course_thumbnail_src($featuredCourse); ?>
              <img src="<?= e($featThumb !== '' ? $featThumb : public_academy_course_image($featuredCourse, $courseImages)) ?>" alt="<?= e((string) $featuredCourse['title']) ?> course artwork" loading="lazy">
              <span class="passport-code"><?= e((string) ($featuredCourse['course_code'] ?? 'Course')) ?></span>
              <span class="passport-kind"><?= e((string) ($featuredCourse['program_title'] ?? 'Academy Course')) ?></span>
            </div>
            <div class="passport-body">
              <h2><?= e((string) $featuredCourse['title']) ?></h2>
              <p><?= e((string) mb_substr((string) ($featuredCourse['description'] ?? 'Start with a practical Academy course.'), 0, 180)) ?></p>
              <div class="passport-meta">
                <span><i class="fas fa-layer-group"></i> <?= (int) ($featuredCourse['lessons'] ?? 0) ?> lessons</span>
                <span><i class="fas fa-clipboard-check"></i> <?= (int) ($featuredCourse['assessments'] ?? 0) ?> assessments</span>
                <span><i class="fas fa-clock"></i> <?= (int) ($featuredCourse['duration_minutes'] ?? 0) ?> min</span>
                <span><i class="fas fa-chalkboard-user"></i> <?= e(academy_delivery_label((string) ($featuredCourse['delivery_type'] ?? 'lms'))) ?></span>
              </div>
              <div class="passport-foot">
                <div class="passport-price"><small>Tuition</small><?= (int) $featuredCourse['is_free'] === 1 ? 'Free' : e(public_academy_money((float) $featuredCourse['price'])) ?></div>
                <a class="btn btn-primary btn-sm" href="<?= e(public_academy_course_url($featuredCourse, $user)) ?>"><?= (int) $featuredCourse['is_free'] === 1 ? 'Enroll Free' : 'View Course' ?><i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
          <?php else: ?>
            <div class="passport-body">
              <h2>Academy course catalog</h2>
              <p>Active courses will appear here once the Academy catalog is published.</p>
              <div class="passport-foot">
                <a class="btn btn-primary btn-sm" href="<?= $user ? 'dashboard.php?screen=catalog' : 'register.php' ?>">Enter Academy</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php require __DIR__ . '/academy-common.php'; ?>
