<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
app_require_cli('admin maintenance script');
header('Content-Type: text/plain; charset=utf-8');

$email = $argv[1] ?? 'dehgracy@gmail.com';
$pdo = db();

echo "Setting provider to Nationwide for: {$email}\n";

// Ensure nationwide column exists
if (function_exists('app_add_column_if_missing')) {
    app_add_column_if_missing($pdo, 'provider_registry', 'nationwide', "TINYINT(1) NOT NULL DEFAULT 0");
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
    echo "User not found\n";
    exit(1);
}

$userId = (int) $user['id'];
$update = $pdo->prepare('UPDATE provider_registry SET nationwide = 1, state_ids = ?, states_served = ? WHERE user_id = ?');
$update->execute(['', 'Nationwide (All States)', $userId]);

echo "Updated rows: " . $update->rowCount() . "\n";

// Output the provider row
$stmt = $pdo->prepare('SELECT * FROM provider_registry WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$prov = $stmt->fetch();
print_r($prov);

exit(0);
