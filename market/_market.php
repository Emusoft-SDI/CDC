<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/platform-revenue.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/cart-checkout.php';
require_once __DIR__ . '/lib/media.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/listings.php';