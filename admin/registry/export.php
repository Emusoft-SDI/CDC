<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

$stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC");
$rows = (function () use ($stmt): Generator {
    while ($row = $stmt->fetch()) {
        yield [
            $row['id'],
            $row['app_ref'],
            $row['name'],
            $row['location'],
            $row['farm_size'],
            $row['phone'],
            $row['email'],
            (int) $row['confirmed'] === 1 ? 'Yes' : 'No',
            $row['review_status'] ?? 'active',
            $row['created_at'],
            $row['confirmed_at'],
        ];
    }
})();

app_export_csv('natcodev_registry_export.csv', [
    'ID', 'Reference', 'Name', 'Location', 'Farm Size', 'Phone', 'Email', 'Confirmed', 'Status', 'Applied', 'Confirmed At'
], $rows);
