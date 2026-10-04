<?php
declare(strict_types=1);
/* Admin UI v2 (P6) — the wallet workspace login is a thin wrapper around the shared admin
   login page (admin/login.php). Setting $__loginTarget = 'wallet' keeps the wallet
   post-login destination (wallet/index.php) while reusing the exact same form, CSRF and
   operator OTP flow instead of duplicating the page. */
$__loginTarget = 'wallet';
require __DIR__ . '/../login.php';
