<section class="card" style="margin-bottom:18px">
    <div class="card-h"><h3>Academy Catalog</h3><a class="link" href="dashboard.php?screen=learning">My Learning</a></div>
    <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
      <input type="hidden" name="screen" value="catalog"><input name="q" value="<?= e($catalogQuery) ?>" placeholder="Search courses, topics or instructors..." style="flex:1;min-width:180px">
      <select name="category">
        <option value="">All Categories</option>
        <?php foreach (array_keys($catalogCategories) as $categoryOption): ?><option value="<?= e($categoryOption) ?>" <?= $catalogCategory === $categoryOption ? 'selected' : '' ?>><?= e($categoryOption) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-o" type="submit"><i class="fas fa-search"></i> Search</button>
    </form>
    <p class="muted" style="margin-bottom:12px">Showing <?= number_format($catalogTotal) ?> course<?= $catalogTotal === 1 ? '' : 's' ?>, page <?= $catalogPage ?> of <?= $catalogPages ?>.</p>
    <div style="display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap">
      <?php foreach (array_keys($catalogGroupLabels) as $groupLabel): ?><span class="badge-pill bp-gray"><?= e($groupLabel) ?></span><?php endforeach; ?>
      <?php if (!$catalogGroupLabels): ?><span class="badge-pill bp-gray">No course categories found</span><?php endif; ?>
    </div>
    <?php foreach ($catalogPagedGroups as $groupLabel => $groupCourses): ?>
      <div style="margin:18px 0 10px;display:flex;align-items:center;justify-content:space-between;gap:12px"><h3 style="margin:0;color:var(--green-700);font-size:16px"><?= e($groupLabel) ?></h3><span class="badge-pill bp-green"><?= count($groupCourses) ?> shown</span></div>
      <div class="grid g4">
      <?php foreach ($groupCourses as $i => $c): ?>
          <article class="course-card">
            <a href="dashboard.php?screen=course&course_id=<?= (int) $c['id'] ?>">
              <div class="course-thumb cat-<?= ($i % 4) + 1 ?>"><i class="fas fa-graduation-cap"></i><span class="new-tag"><?= (int) $c['is_free'] === 1 ? 'FREE' : 'PAID' ?></span></div>
              <div class="course-body"><div class="course-title"><?= e((string) $c['title']) ?></div><div class="course-meta"><?= e((string) ($c['category'] ?? $groupLabel)) ?> / <?= e(academy_delivery_label((string) ($c['delivery_type'] ?? 'lms'))) ?></div><div class="course-footer"><span class="course-price"><?= (int) $c['is_free'] === 1 ? 'Free' : e(ac_money((float) $c['price'])) ?></span><span class="course-rating"><i class="fas fa-star"></i> <?= (int) ($c['lessons'] ?? 0) ?> lessons</span></div></div>
            </a>
          </article>
      <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$catalogPaged): ?><div class="empty">No Academy course is currently available for your role, category, or search.</div><?php endif; ?>
    <?php if ($catalogPages > 1): ?>
      <nav class="pagination" aria-label="Academy catalog pages">
        <?php $pageBase = ['screen' => 'catalog']; if ($catalogQuery !== '') { $pageBase['q'] = $catalogQuery; } if ($catalogCategory !== '') { $pageBase['category'] = $catalogCategory; } ?>
        <?php if ($catalogPage > 1): ?><a href="dashboard.php?<?= e(http_build_query($pageBase + ['page' => $catalogPage - 1])) ?>">Previous</a><?php endif; ?>
        <?php for ($page = 1; $page <= $catalogPages; $page++): ?><a class="<?= $page === $catalogPage ? 'active' : '' ?>" href="dashboard.php?<?= e(http_build_query($pageBase + ['page' => $page])) ?>"><?= $page ?></a><?php endfor; ?>
        <?php if ($catalogPage < $catalogPages): ?><a href="dashboard.php?<?= e(http_build_query($pageBase + ['page' => $catalogPage + 1])) ?>">Next</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </section>
  <div class="grid g4">
    <a class="nav-card" href="dashboard.php?screen=learning"><div class="nav-ic"><i class="fas fa-book-open"></i></div><div class="nav-info"><div class="nav-title">My Learning</div><div class="nav-desc">Continue registered courses</div></div></a>
    <a class="nav-card" href="dashboard.php?screen=certificates"><div class="nav-ic"><i class="fas fa-award"></i></div><div class="nav-info"><div class="nav-title">Certificates</div><div class="nav-desc">View and download</div></div></a>
    <a class="nav-card" href="dashboard.php?screen=transactions"><div class="nav-ic"><i class="fas fa-receipt"></i></div><div class="nav-info"><div class="nav-title">Transactions</div><div class="nav-desc">Payments and refunds</div></div></a>
    <a class="nav-card" href="dashboard.php?screen=support"><div class="nav-ic"><i class="fas fa-headset"></i></div><div class="nav-info"><div class="nav-title">Support</div><div class="nav-desc">Academy help desk</div></div></a>
  </div>