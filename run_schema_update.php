<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/marketplace.php'; // Explicitly require to ensure marketplace_ensure_schema is available

$pdo = db(); // Get a PDO database connection
marketplace_ensure_schema($pdo); // Call the function to ensure schema is up-to-date

echo "Marketplace schema update script executed. Check your database.<br>";
echo "<a href='http://localhost/CDC/market/cart.php'>Go to Cart</a>";
