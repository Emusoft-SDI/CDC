<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

$agentId = filter_input(INPUT_GET, 'agent_id', FILTER_VALIDATE_INT);
if (!$agentId) {
    json_response(['success' => false, 'error' => 'Agent ID required'], 422);
}

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

if (!app_table_exists($pdo, 'agent_locations')) {
    json_response(['success' => true, 'items' => []]);
}

$select = [
    'al.timestamp',
    'al.latitude',
    'al.longitude',
    'al.battery_level',
];
$joins = [];
if (app_table_exists($pdo, 'field_visits')) {
    $select[] = 'fv.notes as visit_notes';
    $joins[] = "LEFT JOIN field_visits fv ON al.agent_id = fv.agent_id AND DATE(al.timestamp) = DATE(fv.visited_at)";
} else {
    $select[] = "NULL as visit_notes";
}
if (app_table_exists($pdo, 'geofence_events')) {
    $select[] = 'ge.event_type as geofence_event';
    $joins[] = "LEFT JOIN geofence_events ge ON al.agent_id = ge.agent_id AND al.timestamp BETWEEN ge.triggered_at AND DATE_ADD(ge.triggered_at, INTERVAL 5 MINUTE)";
    if (app_table_exists($pdo, 'farm_zones')) {
        $select[] = 'fz.name as zone_name';
        $joins[] = "LEFT JOIN farm_zones fz ON ge.zone_id = fz.id";
    } else {
        $select[] = "NULL as zone_name";
    }
} else {
    $select[] = "NULL as geofence_event";
    $select[] = "NULL as zone_name";
}

$stmt = $pdo->prepare("
    SELECT " . implode(",\n        ", $select) . "
    FROM agent_locations al
    " . implode("\n    ", $joins) . "
    WHERE al.agent_id = ?
    AND al.timestamp BETWEEN ? AND ?
    ORDER BY al.timestamp
");
$stmt->execute([$agentId, "$startDate 00:00:00", "$endDate 23:59:59"]);
$activities = $stmt->fetchAll();

// Handle export format
$format = $_GET['format'] ?? 'json';

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="agent_activity.csv"');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['Timestamp', 'Latitude', 'Longitude', 'Battery %', 'Visit Notes', 'Zone', 'Geofence Event']);
    foreach ($activities as $row) {
        fputcsv($fh, [
            $row['timestamp'],
            $row['latitude'],
            $row['longitude'],
            $row['battery_level'],
            $row['visit_notes'],
            $row['zone_name'],
            $row['geofence_event']
        ]);
    }
    fclose($fh);
    exit;
    
} elseif ($format === 'pdf') {
    require_once __DIR__ . '/../tcpdf/tcpdf.php';
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('NATCODEV');
    $pdf->SetTitle('Agent Activity Report');
    $pdf->AddPage();
    
    // Get agent name
    $agentStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $agentStmt->execute([$agentId]);
    $agentName = $agentStmt->fetchColumn();
    
    $html = "
    <h1>NATCODEV Agent Activity Report</h1>
    <p><strong>Agent:</strong> " . e((string) $agentName) . "</p>
    <p><strong>Period:</strong> " . e((string) $startDate) . " to " . e((string) $endDate) . "</p>
    <table border='1' cellpadding='4'>
        <tr>
            <th>Timestamp</th>
            <th>Location</th>
            <th>Battery</th>
            <th>Activity</th>
        </tr>";
    
    foreach ($activities as $row) {
        $location = $row['latitude'] ? "{$row['latitude']}, {$row['longitude']}" : 'N/A';
        $activity = [];
        if ($row['visit_notes']) $activity[] = "Visit: " . substr($row['visit_notes'], 0, 50);
        if ($row['geofence_event']) $activity[] = "Geofence: {$row['geofence_event']} {$row['zone_name']}";
        $activityStr = implode('; ', $activity) ?: 'Location ping';
        
        $html .= "<tr>
            <td>" . e((string) $row['timestamp']) . "</td>
            <td>" . e($location) . "</td>
            <td>" . e((string) $row['battery_level']) . "%</td>
            <td>" . e($activityStr) . "</td>
        </tr>";
    }
    
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output("agent_report_{$agentId}.pdf", 'D');
    exit;
}

// Default: JSON for dashboard
json_response(['success' => true, 'items' => $activities]);
