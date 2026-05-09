<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit;
}

$agentId = $_GET['agent_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

// Build report data
$stmt = $pdo->prepare("
    SELECT 
        al.timestamp,
        al.latitude,
        al.longitude,
        al.battery_level,
        fv.notes as visit_notes,
        fz.name as zone_name,
        ge.event_type as geofence_event
    FROM agent_locations al
    LEFT JOIN field_visits fv ON al.agent_id = fv.agent_id 
        AND DATE(al.timestamp) = DATE(fv.visited_at)
    LEFT JOIN geofence_events ge ON al.agent_id = ge.agent_id 
        AND al.timestamp BETWEEN ge.triggered_at AND DATE_ADD(ge.triggered_at, INTERVAL 5 MINUTE)
    LEFT JOIN farm_zones fz ON ge.zone_id = fz.id
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
    require_once '../tcpdf/tcpdf.php';
    
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
    <p><strong>Agent:</strong> {$agentName}</p>
    <p><strong>Period:</strong> {$startDate} to {$endDate}</p>
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
            <td>{$row['timestamp']}</td>
            <td>{$location}</td>
            <td>{$row['battery_level']}%</td>
            <td>{$activityStr}</td>
        </tr>";
    }
    
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output("agent_report_{$agentId}.pdf", 'D');
    exit;
}

// Default: JSON for dashboard
echo json_encode($activities);
?>