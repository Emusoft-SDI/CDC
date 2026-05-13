<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/weather.php';

$pdo = db();
app_ensure_core_schema($pdo);

$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-7 days'));
$lastWeekStart = date('Y-m-d', strtotime('-14 days'));
$lastWeekEnd = date('Y-m-d', strtotime('-8 days'));

function cron_count_if_table(PDO $pdo, string $table, string $column, string $from): int
{
    if (!app_table_exists($pdo, $table) || !app_column_exists($pdo, $table, $column)) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` >= ?");
    $stmt->execute([$from]);
    return (int) $stmt->fetchColumn();
}

$metrics = [
    'agents' => app_table_exists($pdo, 'agent_locations')
        ? (int) $pdo->query("SELECT COUNT(DISTINCT agent_id) FROM agent_locations WHERE timestamp >= " . $pdo->quote($startDate))->fetchColumn()
        : 0,
    'visits' => cron_count_if_table($pdo, 'field_visits', 'visited_at', $startDate),
    'new_growers' => app_column_exists($pdo, 'applications', 'confirmed_at')
        ? (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE confirmed = 1 AND confirmed_at >= " . $pdo->quote($startDate))->fetchColumn()
        : 0,
    'alerts' => cron_count_if_table($pdo, 'geofence_events', 'triggered_at', $startDate),
];

$visitTrends = [];
if (app_table_exists($pdo, 'field_visits')) {
    $stmt = $pdo->prepare("
        SELECT
            u.id, u.name,
            COUNT(CASE WHEN fv.visited_at >= ? THEN 1 END) as this_week,
            COUNT(CASE WHEN fv.visited_at BETWEEN ? AND ? THEN 1 END) as last_week
        FROM users u
        LEFT JOIN field_visits fv ON u.id = fv.agent_id
        WHERE u.role = 'field_agent'
        GROUP BY u.id, u.name
    ");
    $stmt->execute([$startDate, $lastWeekStart, $lastWeekEnd]);
    foreach ($stmt->fetchAll() as $row) {
        $change = (int) $row['this_week'] - (int) $row['last_week'];
        $visitTrends[] = [
            'name' => $row['name'],
            'this_week' => (int) $row['this_week'],
            'last_week' => (int) $row['last_week'],
            'trend_icon' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'trend_text' => (string) $change,
            'trend_class' => $change > 0 ? 'trend-up' : ($change < 0 ? 'trend-down' : ''),
        ];
    }
}

$weatherSummary = function_exists('getWeatherForecastSummary') ? getWeatherForecastSummary() : 'Weather summary unavailable.';
$recentAlerts = [];
if (app_table_exists($pdo, 'geofence_events') && app_table_exists($pdo, 'farm_zones')) {
    $alertStmt = $pdo->prepare("
        SELECT CONCAT(u.name, ' ', ge.event_type, ' ', fz.name) as message,
               DATE_FORMAT(ge.triggered_at, '%a %H:%i') as time
        FROM geofence_events ge
        JOIN users u ON ge.agent_id = u.id
        JOIN farm_zones fz ON ge.zone_id = fz.id
        WHERE ge.triggered_at >= ?
        ORDER BY ge.triggered_at DESC
        LIMIT 5
    ");
    $alertStmt->execute([$startDate]);
    $recentAlerts = $alertStmt->fetchAll();
}

ob_start();
include __DIR__ . '/../templates/weekly-report.html';
$html = (string) ob_get_clean();

$admins = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND email <> ''")->fetchAll();
foreach ($admins as $admin) {
    app_send_mail((string) $admin['email'], 'NATCODEV Weekly Intelligence Report', strip_tags($html), $html);
}

echo 'Weekly report sent to ' . count($admins) . ' admin(s).' . PHP_EOL;
