<?php
// Layout Component: support-header.php
?>
<header class="top">
  <div class="bar">
    <a class="brand" href="../index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span>NATCODEV<br><small>Support Desk</small></span></a>
    <nav class="nav" aria-label="Public support navigation">
      <a href="index.php">Support Home</a>
      <a href="index.php?view=new-ticket">New Ticket</a>
      <a href="index.php?view=lookup">Track Ticket</a>
      <a href="index.php?view=knowledge">Knowledge Base</a>
      <a href="index.php?view=upgrade">Registration Paths</a>
      <?php if ($user): ?><a class="btn light" href="../dashboard/index.php">Dashboard</a><?php else: ?><a class="btn light" href="login.php?next=index.php">Track Existing Ticket</a><?php endif; ?>
    </nav>
  </div>
</header>
