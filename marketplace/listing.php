<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$id = (int) ($_GET['id'] ?? 0);
redirect_to('../market/product.php?id=' . $id);
