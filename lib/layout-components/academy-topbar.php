<?php
// Layout Component: academy-topbar.php
?>
<header class="topbar">
  <div class="tb-left">
    <button class="tb-icon menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
    <div>
        <div class="tb-title">NATCODEV Academy - <?= e($titleSet[0]) ?></div>
        <div class="tb-sub"><?= e($titleSet[1]) ?></div>
    </div>
  </div>
  <div class="tb-right">
    <div class="topbar-badges"><span class="badge-pill bp-green"><i class="fas fa-book-open"></i> <?= count($registered) ?></span><span class="badge-pill bp-teal"><i class="fas fa-chart-line"></i> <?= $avgProgress ?>%</span><span class="badge-pill bp-blue"><i class="fas fa-wallet"></i> <?= e(ac_money($walletBalance)) ?></span></div>
    <a class="tb-icon" href="dashboard.php?screen=support" title="Help & Support"><i class="far fa-question-circle"></i></a>
    <a class="tb-icon" href="dashboard.php?screen=messages" title="Messages"><i class="far fa-bell"></i></a>
    <div class="tb-user dropdown">
        <div class="av"><?= e($initials) ?></div>
        <div>
            <div class="nm"><?= e((string) ($user['name'] ?? 'User')) ?></div>
            <div class="st"><?= e(academy_role_label($role)) ?></div>
        </div>
        <div class="dropdown-menu">
            <a href="dashboard.php?screen=settings">Profile & Settings</a>
            <a href="dashboard.php?screen=support">Help & Support</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" style="color:var(--red);">Logout</a>
        </div>
    </div>
  </div>
</header>
