<?php
/**
 * Shared Academy landing sections (Template A + Template B).
 *
 * Required from academy-a.php / academy-b.php via academy/index.php.
 * Receives: $pathways, $programs, $certTracks, $stats, $selectedCourse,
 * $featuredCourse, $courseImages, $catalogCategories, $catalogCategory,
 * $catalogPayment, $pagedCourseGroups, $catalogTotal, $catalogPage,
 * $catalogPages, $user, $learnerName, $homepage.
 */
?>
  <nav class="quick-nav-bar" id="academy-nav" aria-label="Academy sections">
    <div class="quick-nav-inner">
      <span class="quick-nav-label"><i class="fas fa-layer-group"></i> Explore:</span>
      <div class="quick-nav-pills">
        <a href="#catalog" class="quick-pill active"><i class="fas fa-graduation-cap"></i> Course Catalog</a>
        <a href="#pathways" class="quick-pill"><i class="fas fa-route"></i> Pathways</a>
        <a href="#programs" class="quick-pill"><i class="fas fa-book-bookmark"></i> Programs</a>
        <a href="#certificates" class="quick-pill"><i class="fas fa-award"></i> Certificates</a>
        <a href="#route" class="quick-pill"><i class="fas fa-signs-post"></i> How It Works</a>
        <a href="#stats" class="quick-pill"><i class="fas fa-chart-pie"></i> Campus Stats</a>
      </div>
    </div>
  </nav>

  <section class="section" id="catalog" style="padding-top:48px">
    <div class="section-head reveal">
      <div>
        <span class="kicker">Course catalog</span>
        <h2>Browse before you commit</h2>
        <p class="sub">Compare courses, lessons, assessments, and certificate tracks before creating an account. Every course lists its price, delivery format, and audience up front.</p>
      </div>
      <form class="catalog-tools" method="get" action="index.php#catalog" aria-label="Course catalog filters">
        <div class="field">
          <label for="category">Category</label>
          <select name="category" id="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach (array_keys($catalogCategories) as $categoryOption): ?>
              <option value="<?= e($categoryOption) ?>" <?= $catalogCategory === $categoryOption ? 'selected' : '' ?>><?= e($categoryOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="payment">Tuition</label>
          <select name="payment" id="payment" onchange="this.form.submit()">
            <option value="">All Courses</option>
            <option value="free" <?= $catalogPayment === 'free' ? 'selected' : '' ?>>Free only</option>
            <option value="paid" <?= $catalogPayment === 'paid' ? 'selected' : '' ?>>Paid only</option>
          </select>
        </div>
        <?php if ($catalogCategory !== '' || $catalogPayment !== ''): ?>
          <a class="btn btn-ghost btn-sm" href="index.php#catalog"><i class="fas fa-xmark"></i> Clear filters</a>
        <?php endif; ?>
      </form>
    </div>

    <p class="catalog-count reveal" role="status">Showing <?= number_format($catalogTotal) ?> active course<?= $catalogTotal === 1 ? '' : 's' ?><?= $catalogCategory !== '' ? ' in ' . e($catalogCategory) : '' ?><?= $catalogPayment !== '' ? ($catalogPayment === 'free' ? ' · free tuition' : ' · paid tuition') : '' ?>. Page <?= $catalogPage ?> of <?= $catalogPages ?>.</p>

    <?php foreach ($pagedCourseGroups as $categoryLabel => $categoryCourses): ?>
      <div class="category-head reveal">
        <h3><?= e($categoryLabel) ?></h3>
        <span class="rule" aria-hidden="true"></span>
        <span class="badge gold"><?= count($categoryCourses) ?> shown</span>
      </div>
      <div class="course-grid">
        <?php foreach ($categoryCourses as $courseIndex => $course): ?>
          <article class="course reveal" style="--d:<?= ($courseIndex % 3) * 60 ?>ms">
            <?php $courseThumb = academy_course_thumbnail_src($course); ?>
            <div class="course-thumb">
              <img src="<?= e($courseThumb !== '' ? $courseThumb : public_academy_course_image($course, $courseImages)) ?>" alt="<?= e((string) $course['title']) ?> course image" loading="lazy">
              <span class="code"><?= e((string) ($course['course_code'] ?? 'Course')) ?></span>
            </div>
            <div class="course-body">
              <div class="course-tags">
                <span class="badge"><?= e((string) ($course['category'] ?? $categoryLabel)) ?></span>
                <?php if (!empty($course['program_title'])): ?>
                  <a href="program.php?program_id=<?= (int) ($course['program_id'] ?? 0) ?>"><span class="badge sky"><?= e((string) $course['program_title']) ?></span></a>
                <?php endif; ?>
                <?php if ((int) ($course['is_free'] ?? 0) === 1): ?>
                  <span class="badge forest"><i class="fas fa-tag"></i> Free</span>
                <?php endif; ?>
              </div>
              <h3><?= e((string) $course['title']) ?></h3>
              <p class="desc"><?= e((string) mb_substr((string) ($course['description'] ?? 'Practical NATCODEV Academy course.'), 0, 150)) ?></p>
              <div class="course-meta">
                <span><i class="fas fa-layer-group"></i> <?= (int) ($course['lessons'] ?? 0) ?> lessons</span>
                <span><i class="fas fa-clipboard-check"></i> <?= (int) ($course['assessments'] ?? 0) ?> assessment<?= (int) ($course['assessments'] ?? 0) === 1 ? '' : 's' ?></span>
                <span><i class="fas fa-users"></i> <?= e(academy_role_labels((string) ($course['target_roles'] ?? 'all'))) ?></span>
              </div>
              <div class="course-foot">
                <div class="price<?= (int) $course['is_free'] === 1 ? ' free' : '' ?>"><?= (int) $course['is_free'] === 1 ? 'Free' : e(public_academy_money((float) $course['price'])) ?></div>
                <a class="btn btn-primary btn-sm" href="<?= e(public_academy_course_url($course, $user)) ?>"><?= e(academy_delivery_action((string) ($course['delivery_type'] ?? 'lms'))) ?><i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!$pagedCourses): ?>
      <div class="panel empty-state"><i class="fas fa-search"></i>No public Academy course is active in this category yet. Try clearing the filters above.</div>
    <?php endif; ?>

    <?php if ($catalogPages > 1): ?>
      <nav class="pagination" aria-label="Course catalog pages">
        <?php $catalogBase = []; if ($catalogCategory !== '') { $catalogBase['category'] = $catalogCategory; } if ($catalogPayment !== '') { $catalogBase['payment'] = $catalogPayment; } ?>
        <?php if ($catalogPage > 1): ?>
          <a href="index.php?<?= e(http_build_query($catalogBase + ['page' => $catalogPage - 1])) ?>#catalog" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($page = 1; $page <= $catalogPages; $page++): ?>
          <a class="<?= $page === $catalogPage ? 'active' : '' ?>" <?= $page === $catalogPage ? 'aria-current="page"' : '' ?> href="index.php?<?= e(http_build_query($catalogBase + ['page' => $page])) ?>#catalog"><?= $page ?></a>
        <?php endfor; ?>
        <?php if ($catalogPage < $catalogPages): ?>
          <a href="index.php?<?= e(http_build_query($catalogBase + ['page' => $catalogPage + 1])) ?>#catalog" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </section>

  <section class="section" id="pathways">
    <div class="section-head reveal">
      <div>
        <span class="kicker">Learning pathways</span>
        <h2>Choose a direction before requesting a role</h2>
        <p class="sub">Each pathway mirrors a real position in the coconut value chain. Learn the skills, gather the evidence, then request the matching platform role.</p>
      </div>
    </div>
    <div class="pathway-grid">
      <?php foreach ($pathways as $index => $pathway): ?>
        <article class="pathway reveal" style="--d:<?= $index * 70 ?>ms" data-index="0<?= $index + 1 ?>">
          <div class="pathway-icon"><i class="fas <?= e($pathway['icon']) ?>"></i></div>
          <h3><?= e($pathway['title']) ?></h3>
          <p><?= e($pathway['text']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" id="programs">
    <div class="section-head reveal">
      <div>
        <span class="kicker">Academic programs</span>
        <h2>Structured programs, real operating paths</h2>
        <p class="sub">Each program bundles courses, lessons, and assessments into a recognized operating path. Pick a program, then browse the courses inside it.</p>
      </div>
    </div>
    <div class="program-grid">
      <?php foreach ($programs as $programIndex => $programRow): ?>
        <?php $programThumb = academy_program_thumbnail_src($programRow); ?>
        <article class="program reveal" style="--d:<?= ($programIndex % 3) * 70 ?>ms">
          <a href="program.php?program_id=<?= (int) $programRow['id'] ?>">
            <div class="program-media">
              <?php if ($programThumb !== ''): ?>
                <img src="<?= e($programThumb) ?>" alt="<?= e((string) $programRow['title']) ?> thumbnail" loading="lazy">
              <?php else: ?>
                <span class="program-num" aria-hidden="true"><?= $programIndex + 1 ?></span>
              <?php endif; ?>
            </div>
            <div class="program-body">
              <h3><?= e((string) $programRow['title']) ?></h3>
              <p><?= e((string) mb_substr((string) ($programRow['description'] ?? ''), 0, 160)) ?></p>
              <span class="badge"><?= e(academy_role_labels((string) ($programRow['audience_roles'] ?? 'all'))) ?></span>
              <div class="program-foot" style="margin-top:16px">
                <span class="badge gold"><i class="fas fa-arrow-up-right-from-square"></i> View details</span>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if (!$programs): ?>
      <div class="panel empty-state"><i class="fas fa-seedling"></i>No active Academy program is available yet. Check back soon.</div>
    <?php endif; ?>
  </section>

  <section class="section" id="certificates">
    <div class="cert-band reveal">
      <div class="section-head">
        <div>
          <span class="kicker">Certificate tracks</span>
          <h2>Credentials the platform recognizes</h2>
          <p class="sub">Complete the required courses, pass the assessments, then request verifiable Academy credentials reviewed by admins.</p>
        </div>
        <a class="btn btn-ghost" href="../verify-certificate.php" style="background:rgba(255,255,255,.10);color:#fff;border-color:rgba(255,255,255,.35)"><i class="fas fa-qrcode"></i> Verify a Certificate</a>
      </div>
      <div class="cert-grid">
        <?php foreach (array_slice($certTracks, 0, 6) as $track): ?>
          <article class="cert-track">
            <div class="mark"><i class="fas fa-certificate"></i></div>
            <div>
              <h3><?= e((string) $track['title']) ?></h3>
              <p><?= e((string) ($track['description'] ?? 'Complete the required Academy courses to become eligible.')) ?></p>
              <span class="count"><?= (int) ($track['course_count'] ?? 0) ?> linked course<?= (int) ($track['course_count'] ?? 0) === 1 ? '' : 's' ?></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if (!$certTracks): ?>
        <div class="empty-state cert-empty"><i class="fas fa-certificate"></i>No active certificate track is available yet.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="section" id="route">
    <div class="section-head reveal">
      <div>
        <span class="kicker">How learning works</span>
        <h2>Your route from discovery to certification</h2>
        <p class="sub">Clear progression from public discovery to verified skills. No hidden steps, no surprises at enrollment.</p>
      </div>
    </div>
    <div class="route-grid">
      <article class="route-step reveal" style="--d:0ms">
        <div class="route-num">1</div>
        <h3>Discover</h3>
        <p>Compare courses, programs, lessons, assessments, and certificate tracks before creating an account. Every price and audience is public.</p>
      </article>
      <article class="route-step reveal" style="--d:90ms">
        <div class="route-num">2</div>
        <h3>Enroll &amp; study</h3>
        <p>Register as a learner, unlock free or paid courses, complete lessons at your own pace, and submit the assessments.</p>
      </article>
      <article class="route-step reveal" style="--d:180ms">
        <div class="route-num">3</div>
        <h3>Certify &amp; apply</h3>
        <p>Use completed training as readiness for certificates and role requests reviewed by admins, with verifiable references.</p>
      </article>
    </div>
  </section>

  <section class="section" id="stats">
    <div class="section-head reveal">
      <div>
        <span class="kicker">Campus impact</span>
        <h2>Academy at a glance</h2>
        <p class="sub">Empowering learners, farmers, and entrepreneurs across the Nigerian coconut value chain with measurable impact.</p>
      </div>
    </div>
    <div class="stats-band reveal">
      <div class="stats-inner" style="padding:0">
        <div class="stats-card" role="list" aria-label="Academy statistics">
          <div class="stat" role="listitem"><b><?= number_format($stats['courses']) ?></b><span>Active courses</span></div>
          <div class="stat" role="listitem"><b><?= number_format($stats['programs']) ?></b><span>Programs</span></div>
          <div class="stat" role="listitem"><b><?= number_format($stats['certificates']) ?></b><span>Certificate tracks</span></div>
          <div class="stat" role="listitem"><b><?= number_format($stats['learners']) ?></b><span>Registered learners</span></div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($selectedCourse): ?>
    <section class="section" aria-label="Selected course">
      <div class="spotlight reveal">
        <div class="spotlight-main">
          <span class="badge gold"><i class="fas fa-bookmark"></i> Selected course</span>
          <h2><?= e((string) $selectedCourse['title']) ?></h2>
          <p><?= e((string) ($selectedCourse['description'] ?? '')) ?></p>
          <div class="spotlight-meta">
            <span><i class="fas fa-layer-group"></i> <?= (int) ($selectedCourse['lessons'] ?? 0) ?> lessons</span>
            <span><i class="fas fa-clipboard-check"></i> <?= (int) ($selectedCourse['assessments'] ?? 0) ?> assessments</span>
            <span><i class="fas fa-chalkboard-user"></i> <?= e(academy_delivery_label((string) ($selectedCourse['delivery_type'] ?? 'lms'))) ?></span>
            <span><i class="fas fa-users"></i> <?= e(academy_role_labels((string) ($selectedCourse['target_roles'] ?? 'all'))) ?></span>
          </div>
        </div>
        <div class="spotlight-side">
          <div class="price"><small>Tuition</small><?= (int) $selectedCourse['is_free'] === 1 ? 'Free' : e(public_academy_money((float) $selectedCourse['price'])) ?></div>
          <a class="btn btn-gold" href="<?= e(public_academy_course_url($selectedCourse, $user)) ?>"><i class="fas fa-arrow-right"></i> Continue to Enrollment</a>
        </div>
      </div>
    </section>
  <?php endif; ?>
