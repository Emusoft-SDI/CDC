<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
wallets_auth_check(db());

header('Location: dashboard.php');
exit;

