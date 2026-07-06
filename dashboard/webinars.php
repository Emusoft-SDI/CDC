<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$query = ['screen' => 'catalog'];
foreach (['message', 'registered', 'error'] as $key) {
    if (isset($_GET[$key])) {
        $query[$key] = (string) $_GET[$key];
        if ($key === 'registered') {
            $query['screen'] = 'learning';
        }
    }
}

redirect_to('../academy/index.php?' . http_build_query($query));
