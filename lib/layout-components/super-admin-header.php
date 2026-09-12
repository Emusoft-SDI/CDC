<?php
// Layout Component: super-admin-header.php
?>
  <header class="super-header">
    <div class="bar">
      <a class="brand" href="index.php"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV Super Admin</span></a>
      <nav class="super-nav" aria-label="Super Admin menus">
        <?php foreach (super_admin_nav_groups() as $groupLabel => $items): ?>
          <details class="<?= super_admin_nav_group_is_active($items, $activeView) ? 'active' : '' ?>">
            <summary><?= e($groupLabel) ?></summary>
            <div class="super-menu">
              <?php foreach ($items as $item): ?>
                <a class="<?= (($item['view'] ?? '') === $activeView) ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                  <?= e($item['label']) ?>
                  <small><?= e($item['hint']) ?></small>
                </a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </nav>
      <nav class="header-actions"><a href="../admin/admin.php">Admin Console</a><a href="index.php?view=profile">My Profile</a><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="secondary" type="submit">Logout</button></form></nav>
    </div>
  </header>
