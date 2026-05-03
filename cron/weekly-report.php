<?php
// cron/weekly-report.php - Run via cron job every Monday at 8 AM
require_once '../config.php'; // Your DB config

$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

// Date ranges
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-7 days'));
$lastWeekStart = date('Y-m-d', strtotime('-14 days'));
$lastWeekEnd = date('Y-m-d', strtotime('-8 days'));

// Get metrics
$metrics = [
    'agents' => $pdo->query("SELECT COUNT(DISTINCT agent_id) FROM agent_locations WHERE timestamp >= '$startDate'")->fetchColumn(),
    'visits' => $pdo->query("SELECT COUNT(*) FROM field_visits WHERE visited_at >= '$startDate'")->fetchColumn(),
    'new_growers' => $pdo->query("SELECT COUNT(*) FROM applications WHERE confirmed = 1 AND confirmed_at >= '$startDate'")->fetchColumn(),
    'alerts' => $pdo->query("SELECT COUNT(*) FROM geofence_events WHERE triggered_at >= '$startDate'")->fetchColumn()
];

// Visit trends by agent
$visitTrends = [];
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
while ($row = $stmt->fetch()) {
    $change = $row['this_week'] - $row['last_week'];
    if ($change > 0) {
        $trend = ['icon' => '↑', 'text' => '+' . $change, 'class' => 'trend-up'];
    } elseif ($change < 0) {
        $trend = ['icon' => '↓', 'text' => $change, 'class' => 'trend-down'];
    } else {
        $trend = ['icon' => '→', 'text' => '0', 'class' => ''];
    }
    
    $visitTrends[] = [
        'name' => $row['name'],
        'this_week' => $row['this_week'],
        'last_week' => $row['last_week'],
        'trend_icon' => $trend['icon'],
        'trend_text' => $trend['text'],
        'trend_class' => $trend['class']
    ];
}

// Weather forecast (see Part 2)
$weatherSummary = getWeatherForecastSummary();

// Recent alerts
$recentAlerts = [];
$alertStmt = $pdo->prepare("
    SELECT 
        CONCAT(u.name, ' ', ge.event_type, 'ed ', fz.name) as message,
        DATE_FORMAT(ge.triggered_at, '%a %H:%i') as time
    FROM geofence_events ge
    JOIN users u ON ge.agent_id = u.id
    JOIN farm_zones fz ON ge.zone_id = fz.id
    WHERE ge.triggered_at >= ?
    ORDER BY ge.triggered_at DESC
    LIMIT 5
");
$alertStmt->execute([$startDate]);
while ($row = $alertStmt->fetch()) {
    $recentAlerts[] = $row;
}

// Render template
ob_start();
include '../templates/weekly-report.html';
$html = ob_get_clean();

// Send to all admins
$admins = $pdo->query("SELECT email FROM users WHERE role = 'admin'")->fetchAll();
foreach ($admins as $admin) {
    sendEmail($admin['email'], 'NATCODEV Weekly Intelligence Report', $html);
}

function sendEmail($to, $subject, $html) {
    $headers = "From: noreply@coconutventurehub.ng\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    mail($to, $subject, $html, $headers);
}
?>
// Predict next week's visits based on trend
function predictVisits($visitTrends) {
    $totalThisWeek = array_sum(array_column($visitTrends, 'this_week'));
    $totalLastWeek = array_sum(array_column($visitTrends, 'last_week'));
    
    if ($totalLastWeek == 0) return $totalThisWeek;
    
    $growthRate = ($totalThisWeek - $totalLastWeek) / $totalLastWeek;
    $prediction = $totalThisWeek * (1 + $growthRate);
    
    return round($prediction);
}

$predictedVisits = predictVisits($visitTrends);
// Add to template variables