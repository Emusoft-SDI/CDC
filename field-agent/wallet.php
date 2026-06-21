<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo);
fa_header('Wallet & Allowance', 'Track allowances, advances, payments, and expense support.', $user, 'wallet');
?>
<section class="fa-grid"><article class="fa-card fa-panel span-6"><span class="fa-icon"><i data-lucide="wallet"></i></span><h2 style="font-size:2rem;color:var(--green)">₦24,977.38</h2><p class="muted">Available allowance balance.</p><a class="btn" href="../dashboard/wallet.php"><i data-lucide="external-link"></i> Open Wallet</a></article><article class="fa-card fa-panel span-6"><div class="fa-panel-head"><h2>Allowance Summary</h2></div><div class="fa-list"><div class="fa-row"><span class="fa-icon gold"><i data-lucide="coins"></i></span><div><strong>This Month’s Allowance</strong></div><b>₦60,000.00</b></div><div class="fa-row"><span class="fa-icon red"><i data-lucide="receipt"></i></span><div><strong>Expenses</strong></div><b>-₦12,350.00</b></div><div class="fa-row"><span class="fa-icon"><i data-lucide="check"></i></span><div><strong>Last Payment</strong></div><b>May 15, 2026</b></div></div></article></section>
<?php fa_footer(); ?>
