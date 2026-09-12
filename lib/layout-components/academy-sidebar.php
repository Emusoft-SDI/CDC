<?php
// Layout Component: academy-sidebar.php
?>
<aside class="sidebar" id="sidebar">
  <a class="sb-brand" href="dashboard.php?screen=catalog">
    <div class="sb-logo"><img src="<?= e($logo) ?>" alt="NATCODEV"></div>
    <div><h1>NATCODEV</h1><small>Academy</small></div>
  </a>
  <div class="sb-nav-label">Academy</div>
  <nav>
    <?php foreach ([
      'catalog' => ['fas fa-compass', 'Catalog'],
      'learning' => ['fas fa-book-open', 'My Learning'],
      'lesson' => ['fas fa-play-circle', 'Lesson Player'],
      'quiz' => ['fas fa-question-circle', 'Quiz / Exam'],
      'certificates' => ['fas fa-award', 'Certificates'],
      'transactions' => ['fas fa-exchange-alt', 'Transactions'],
      'messages' => ['fas fa-envelope', 'Messages'],
      'settings' => ['fas fa-cog', 'Settings & Feedback'],
      'support' => ['fas fa-headset', 'Help & Support'],
    ] as $key => $item): ?>
      <a class="sb-item <?= $screen === $key ? 'active' : '' ?>" href="dashboard.php?screen=<?= e($key) ?>"><i class="<?= e($item[0]) ?>"></i><span><?= e($item[1]) ?></span><?= $key === 'messages' && count($messages) > 0 ? '<span class="badge">' . count($messages) . '</span>' : '' ?></a>
    <?php endforeach; ?>
    <a class="sb-item <?= $screen === 'transactions' ? 'active' : '' ?>" href="dashboard.php?screen=transactions"><i class="fas fa-wallet"></i><span>Fund Wallet</span></a>
    <a class="sb-item" href="../academy/index.php"><i class="fas fa-home"></i><span>Academy Site</span></a>
 
     <a class="sb-item" href="../academy/request-role.php"><i class="fas fa-user-shield"></i><span>Request Role</span></a>
    <a class="sb-item" href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </nav>
  <div class="sb-footer">
    <h4>Learn. Grow. Certify.</h4>
    <p>Practical skills. Real farm impact.</p>
    <a class="btn-sb" href="dashboard.php?screen=catalog">Browse Catalog <i class="fas fa-arrow-right"></i></a>
    <a class="btn-sb" href="logout.php">Logout</a>
  </div>
</aside>
