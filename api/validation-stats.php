<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

if (!app_table_exists($pdo, 'document_requirements')) {
    json_response([
        'success' => true,
        'totals' => ['total' => 0, 'successful' => 0, 'failed' => 0, 'success_rate' => 0, 'avg_response_time' => 0],
        'trends' => ['dates' => [], 'success' => [], 'failed' => []],
        'by_document_type' => [],
        'by_state' => [],
        'recent_activity' => [],
    ]);
}

// Totals
$totsql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN api_validation_status = 'valid' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN api_validation_status = 'invalid' THEN 1 ELSE 0 END) as failed,
        AVG(TIMESTAMPDIFF(SECOND, uploaded_at, api_validation_timestamp)) as avg_response_time
    FROM document_requirements 
    WHERE api_validation_timestamp IS NOT NULL
";
$totstmt = $pdo->query($totsql);
$totals = $totstmt->fetch();

$successRate = $totals['total'] > 0 ? round(($totals['successful'] / $totals['total']) * 100, 1) : 0;

// Trends (last 30 days)
$trendsql = "
    SELECT 
        DATE(api_validation_timestamp) as date,
        SUM(CASE WHEN api_validation_status = 'valid' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN api_validation_status = 'invalid' THEN 1 ELSE 0 END) as failed
    FROM document_requirements 
    WHERE api_validation_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(api_validation_timestamp)
    ORDER BY date
";
$trendstmt = $pdo->query($trendsql);
$trends = ['dates' => [], 'success' => [], 'failed' => []];
while ($row = $trendstmt->fetch()) {
    $trends['dates'][] = $row['date'];
    $trends['success'][] = intval($row['success']);
    $trends['failed'][] = intval($row['failed']);
}

// By document type
$typesql = "
    SELECT document_type, COUNT(*) as count
    FROM document_requirements 
    WHERE api_validation_status IS NOT NULL
    GROUP BY document_type
";
$typestmt = $pdo->query($typesql);
$byDocumentType = [];
while ($row = $typestmt->fetch()) {
    $byDocumentType[$row['document_type']] = intval($row['count']);
}

$byState = [];
if (app_table_exists($pdo, 'nigeria_states') && app_column_exists($pdo, 'applications', 'state_id')) {
    $statesql = "
        SELECT
            s.state_name,
            ROUND(
                SUM(CASE WHEN dr.api_validation_status = 'valid' THEN 1 ELSE 0 END) * 100.0 /
                COUNT(*), 1
            ) as success_rate
        FROM document_requirements dr
        JOIN users u ON dr.user_id = u.id
        JOIN applications a ON u.application_id = a.id
        JOIN nigeria_states s ON a.state_id = s.id
        WHERE dr.api_validation_status IS NOT NULL
        GROUP BY s.state_name
        HAVING COUNT(*) >= 5
        ORDER BY success_rate DESC
        LIMIT 10
    ";
    $statestmt = $pdo->query($statesql);
    while ($row = $statestmt->fetch()) {
        $byState[$row['state_name']] = (float) $row['success_rate'];
    }
}

// Recent activity
$activitysql = "
    SELECT 
        dr.api_validation_timestamp as timestamp,
        u.name as user_name,
        dr.document_type,
        dr.api_validation_status as status,
        TIMESTAMPDIFF(SECOND, dr.uploaded_at, dr.api_validation_timestamp) as response_time
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    WHERE dr.api_validation_timestamp IS NOT NULL
    ORDER BY dr.api_validation_timestamp DESC
    LIMIT 20
";
$activitystmt = $pdo->query($activitysql);
$recentActivity = $activitystmt->fetchAll();

json_response([
    'success' => true,
    'totals' => [
        'total' => intval($totals['total']),
        'successful' => intval($totals['successful']),
        'failed' => intval($totals['failed']),
        'success_rate' => $successRate,
        'avg_response_time' => round($totals['avg_response_time'] ?? 0, 1)
    ],
    'trends' => $trends,
    'by_document_type' => $byDocumentType,
    'by_state' => $byState,
    'recent_activity' => $recentActivity
]);
