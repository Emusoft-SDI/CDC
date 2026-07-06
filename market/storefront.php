<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user); $ctx = seller_query_context($pdo, $user, false);
if ($ctx['seller']) { redirect_to('store.php?seller=' . rawurlencode((string) $ctx['seller']['slug'])); }
redirect_to('seller-settings.php');
