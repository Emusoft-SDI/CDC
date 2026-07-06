<?php
declare(strict_types=1);
$query = $_GET ? '?' . http_build_query($_GET) : '';
header('Location: registry/' . $query, true, 302);
exit;
