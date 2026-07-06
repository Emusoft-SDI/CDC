<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$next = trim((string) ($_GET['next'] ?? ''));
if ($next !== '') {
    $next = str_replace(["\0", '\\'], ['', '/'], $next);
    while (str_starts_with($next, '../')) {
        $next = substr($next, 3);
    }
    if ($next !== '' && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) && !str_starts_with($next, '//') && $next[0] !== '/') {
        redirect_to('../login.php?next=' . rawurlencode($next));
    }
}

redirect_to('../login.php');
