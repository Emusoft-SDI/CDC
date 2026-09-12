<?php
// Layout Component: support-sidebar.php
?>
    <aside class="support-rail" aria-label="Public support navigation">
      <h2>Support Center</h2>
      <p>Open a case, track a ticket, read help notes, or choose the correct registration path without leaving support.</p>
      <nav>
        <a class="primary" href="index.php?view=new-ticket"><span><i class="fas fa-plus-circle"></i> New Ticket</span></a>
        <a href="index.php?view=lookup"><span><i class="fas fa-magnifying-glass"></i> Track Ticket</span></a>
        <a href="index.php?view=knowledge"><span><i class="fas fa-book-open"></i> Knowledge Base</span></a>
        <a href="index.php?view=upgrade"><span><i class="fas fa-route"></i> Registration Paths</span></a>
        <a href="index.php?view=support-flow"><span><i class="fas fa-list-check"></i> Resolution Flow</span></a>
        <?php if ($user): ?><a href="../dashboard/index.php"><span><i class="fas fa-table-columns"></i> My Dashboard</span></a><?php else: ?><a href="login.php?next=index.php"><span><i class="fas fa-ticket"></i> Ticket Access</span></a><?php endif; ?>
      </nav>
      <small>Support remains self-contained here. Service registration links are kept in the registration paths section only.</small>
    </aside>
