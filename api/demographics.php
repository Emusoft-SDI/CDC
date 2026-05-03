<?php
// api/demographics.php
header('Content-Type: application/json');

$filters = [
    'state' => $_GET['state'] ?? null,
    'lga' => $_GET['lga'] ?? null,
    'gender' => $_GET['gender'] ?? null,
    'min_age' => $_GET['min_age'] ?? null,
    'max_age' => $_GET['max_age'] ?? null,
    'education' => $_GET['education'] ?? null,
    'experience' => $_GET['experience'] ?? null
];

// Build dynamic query
$sql = "
    SELECT 
        s.state_name,
        l.lga_name,
        u.role,
        CASE 
            WHEN u.dob IS NOT NULL THEN FLOOR(DATEDIFF(NOW(), u.dob) / 365.25)
            ELSE NULL 
        END as age,
        u.education_level,
        u.farming_experience_rating,
        u.marital_status,
        COUNT(*) as count
    FROM users u
    LEFT JOIN nigeria_states s ON u.state_id = s.id
    LEFT JOIN nigeria_lgas l ON u.lga_id = l.id
    WHERE u.terms_accepted = 1
";

$params = [];
$whereClauses = [];

if ($filters['state']) {
    $whereClauses[] = "s.state_name = ?";
    $params[] = $filters['state'];
}
if ($filters['lga']) {
    $whereClauses[] = "l.lga_name = ?";
    $params[] = $filters['lga'];
}
if ($filters['gender']) {
    // Assuming gender field exists
    $whereClauses[] = "u.gender = ?";
    $params[] = $filters['gender'];
}
if ($filters['min_age']) {
    $whereClauses[] = "FLOOR(DATEDIFF(NOW(), u.dob) / 365.25) >= ?";
    $params[] = $filters['min_age'];
}
if ($filters['max_age']) {
    $whereClauses[] = "FLOOR(DATEDIFF(NOW(), u.dob) / 365.25) <= ?";
    $params[] = $filters['max_age'];
}
if ($filters['education']) {
    $whereClauses[] = "u.education_level = ?";
    $params[] = $filters['education'];
}
if ($filters['experience']) {
    $whereClauses[] = "u.farming_experience_rating = ?";
    $params[] = $filters['experience'];
}

if (!empty($whereClauses)) {
    $sql .= " AND " . implode(" AND ", $whereClauses);
}

$sql .= " GROUP BY s.state_name, l.lga_name, u.role, age, u.education_level, u.farming_experience_rating, u.marital_status";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Generate intelligent insights
$insights = generateInsights($data);

echo json_encode([
    'data' => $data,
    'insights' => $insights,
    'filters' => $filters
]);

function generateInsights($data) {
    $total = count($data);
    $femaleCount = 0;
    $youthCount = 0; // Under 35
    $educatedCount = 0; // Tertiary+
    
    foreach ($data as $row) {
        // Count females (assuming gender field)
        if (isset($row['gender']) && $row['gender'] === 'female') {
            $femaleCount++;
        }
        
        // Count youth
        if ($row['age'] && $row['age'] < 35) {
            $youthCount++;
        }
        
        // Count educated
        if (in_array($row['education_level'], ['tertiary', 'post_graduate'])) {
            $educatedCount++;
        }
    }
    
    return [
        'female_percentage' => $total > 0 ? round(($femaleCount / $total) * 100, 1) : 0,
        'youth_percentage' => $total > 0 ? round(($youthCount / $total) * 100, 1) : 0,
        'educated_percentage' => $total > 0 ? round(($educatedCount / $total) * 100, 1) : 0,
        'top_states' => getTopStates($data),
        'experience_distribution' => getExperienceDistribution($data)
    ];
}

function getTopStates($data) {
    $states = [];
    foreach ($data as $row) {
        if ($row['state_name']) {
            $states[$row['state_name']] = ($states[$row['state_name']] ?? 0) + 1;
        }
    }
    arsort($states);
    return array_slice($states, 0, 5, true);
}
?>