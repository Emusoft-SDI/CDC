<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    http_response_code(403);
    exit('Forbidden');
}

$table = app_table_exists($pdo, 'newsletter_subscribers') ? 'newsletter_subscribers' : (app_table_exists($pdo, 'subscribers') ? 'subscribers' : '');
if ($table === '') {
    http_response_code(404);
    exit('No subscriber table found.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="subscribers.csv"');
$out = fopen('php://output', 'w');
$rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY 1 DESC LIMIT 10000");
$first = $rows->fetch(PDO::FETCH_ASSOC);
if ($first) {
    fputcsv($out, array_keys($first));
    fputcsv($out, $first);
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row);
    }
}
fclose($out);
