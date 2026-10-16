<?php
// Layout Component: academy-topbar.php
?>
<header class="topbar">
  <div class="tb-left">
    <button class="tb-icon menu-btn" id="menuBtn" type="button" aria-label="Toggle navigation sidebar"><i class="fas fa-bars"></i></button>
    <div>
        <div class="tb-title">NATCODEV Academy - <?= e($titleSet[0]) ?></div>
        <div class="tb-sub"><?= e($titleSet[1]) ?></div>
    </div>
  </div>
  <div class="tb-right">
    <div class="topbar-badges"><span class="badge-pill bp-green"><i class="fas fa-book-open"></i> <?= count($registered) ?></span><span class="badge-pill bp-teal"><i class="fas fa-chart-line"></i> <?= $avgProgress ?>%</span><span class="badge-pill bp-blue"><i class="fas fa-wallet"></i> <?= e(ac_money($walletBalance)) ?></span></div>
    <a class="tb-icon" href="dashboard.php?screen=support" title="Help & Support"><i class="far fa-question-circle"></i></a>
    <a class="tb-icon" href="dashboard.php?screen=messages" title="Messages"><i class="far fa-bell"></i></a>
    <div class="tb-user-dropdown dropdown" id="profileDropdown">
        <button class="tb-user" id="profileDropdownBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Learner profile menu">
            <span class="av"><?= e($initials) ?></span>
            <span class="tb-user-details">
                <span class="nm" title="<?= e((string) ($user['name'] ?? 'User')) ?>"><?= e((string) ($user['name'] ?? 'User')) ?></span>
                <span class="st" title="<?= e(academy_role_label($role)) ?>"><?= e(academy_role_label($role)) ?></span>
            </span>
            <i class="fas fa-chevron-down tb-user-caret" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu" id="profileDropdownMenu" role="menu" aria-labelledby="profileDropdownBtn">
            <div class="dropdown-header">
                <div class="dh-name"><?= e((string) ($user['name'] ?? 'User')) ?></div>
                <div class="dh-email"><?= e((string) ($user['email'] ?? '')) ?></div>
                <div class="dh-tags">
                    <span class="badge-pill bp-green"><?= e(academy_role_label($role)) ?></span>
                    <span class="badge-pill bp-blue"><i class="fas fa-wallet"></i> <?= e(ac_money($walletBalance)) ?></span>
                </div>
            </div>
            <div class="dropdown-divider"></div>
            <a href="dashboard.php?screen=overview" class="dropdown-item" role="menuitem">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="dashboard.php?screen=catalog" class="dropdown-item" role="menuitem">
                <i class="fas fa-compass"></i>
                <span>Course Catalog</span>
            </a>
            <a href="dashboard.php?screen=learning" class="dropdown-item" role="menuitem">
                <i class="fas fa-graduation-cap"></i>
                <span>My Learning</span>
            </a>
            <a href="dashboard.php?screen=certificates" class="dropdown-item" role="menuitem">
                <i class="fas fa-award"></i>
                <span>Certificates</span>
            </a>
            <a href="dashboard.php?screen=transactions" class="dropdown-item" role="menuitem">
                <i class="fas fa-wallet"></i>
                <span>Wallet & Transactions</span>
            </a>
            <a href="dashboard.php?screen=messages" class="dropdown-item" role="menuitem">
                <i class="fas fa-bell"></i>
                <span>Messages & Alerts</span>
            </a>
            <a href="dashboard.php?screen=settings" class="dropdown-item" role="menuitem">
                <i class="fas fa-user-gear"></i>
                <span>Profile & Settings</span>
            </a>
            <a href="dashboard.php?screen=support" class="dropdown-item" role="menuitem">
                <i class="fas fa-circle-question"></i>
                <span>Help & Support</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="../index.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-house"></i>
                <span>NATCODEV Home</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="logout.php?_csrf=<?= e(csrf_token()) ?>" class="dropdown-item dropdown-item-danger" role="menuitem">
                <i class="fas fa-right-from-bracket"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>
  </div>
</header>
