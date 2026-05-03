// api/preview-assignment.php
<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$criteria = $input['criteria'] ?? [];

// Build dynamic query
$sql = "
    SELECT u.id, u.name, u.location, a.farm_size
    FROM users u
    JOIN applications a ON u.application_id = a.id
    LEFT JOIN nigeria_states s ON a.state_id = s.id
    LEFT JOIN nigeria_lgas l ON a.lga_id = l.id
    WHERE u.role = 'grower'
    AND NOT EXISTS (
        SELECT 1 FROM agronomist_assignments aa 
        WHERE aa.grower_id = u.id AND aa.status = 'active'
    )
";

$params = [];
$whereClauses = [];

if (!empty($criteria['state'])) {
    $whereClauses[] = "s.state_name = ?";
    $params[] = $criteria['state'];
}
if (!empty($criteria['lga'])) {
    $whereClauses[] = "l.lga_name = ?";
    $params[] = $criteria['lga'];
}
if (!empty($criteria['ward'])) {
    $whereClauses[] = "u.ward LIKE ?";
    $params[] = "%{$criteria['ward']}%";
}
if (!empty($criteria['min_farm_size'])) {
    $whereClauses[] = "a.farm_size >= ?";
    $params[] = floatval($criteria['min_farm_size']);
}
if (!empty($criteria['experience'])) {
    $whereClauses[] = "u.farming_experience_rating = ?";
    $params[] = $criteria['experience'];
}
if (!empty($criteria['education'])) {
    $whereClauses[] = "u.education_level = ?";
    $params[] = $criteria['education'];
}

if (!empty($whereClauses)) {
    $sql .= " AND " . implode(" AND ", $whereClauses);
}

$sql .= " LIMIT 50"; // Preview only

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$growers = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count' => count($growers),
    'growers' => $growers
]);
?>

// api/assign-growers.php
<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

// Create assignment batch
$pdo->prepare("
    INSERT INTO assignment_batches (admin_id, name, criteria) 
    VALUES (?, ?, ?)
")->execute([
    $_SESSION['user_id'],
    $input['batch_name'],
    json_encode($input['criteria'])
]);

$batchId = $pdo->lastInsertId();

// Get matching growers (same logic as preview)
$criteria = $input['criteria'] ?? [];
// ... [same query building logic as preview] ...
$sql = "SELECT u.id FROM users u JOIN applications a ON u.application_id = a.id ...";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$growerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Assign growers
$assigned = 0;
foreach ($growerIds as $growerId) {
    $pdo->prepare("
        INSERT INTO agronomist_assignments (agronomist_id, grower_id, batch_id, assignment_criteria, status)
        VALUES (?, ?, ?, ?, 'active')
    ")->execute([
        $input['agent_id'],
        $growerId,
        $batchId,
        json_encode($input['criteria'])
    ]);
    $assigned++;
}

// Update batch count
$pdo->prepare("UPDATE assignment_batches SET total_assigned = ? WHERE id = ?")
     ->execute([$assigned, $batchId]);

echo json_encode(['success' => true, 'assigned' => $assigned]);
?>