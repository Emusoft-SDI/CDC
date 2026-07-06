<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$seller = rawurlencode((string) ($_GET['seller'] ?? ''));
redirect_to('../market/store.php?seller=' . $seller);
