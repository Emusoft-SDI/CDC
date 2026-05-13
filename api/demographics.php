<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

$filters = [
    'state' => $_GET['state'] ?? null,
    'lga' => $_GET['lga'] ?? null,
    'gender' => $_GET['gender'] ?? null,
    'min_age' => $_GET['min_age'] ?? null,
    'max_age' => $_GET['max_age'] ?? null,
    'education' => $_GET['education'] ?? null,
    'experience' => $_GET['experience'] ?? null
];

$hasStates = app_table_exists($pdo, 'nigeria_states') && app_column_exists($pdo, 'users', 'state_id');
$hasLgas = app_table_exists($pdo, 'nigeria_lgas') && app_column_exists($pdo, 'users', 'lga_id');
$hasTerms = app_column_exists($pdo, 'users', 'terms_accepted');
$hasDob = app_column_exists($pdo, 'users', 'dob');
$hasEducation = app_column_exists($pdo, 'users', 'education_level');
$hasExperience = app_column_exists($pdo, 'users', 'farming_experience_rating');
$hasMarital = app_column_exists($pdo, 'users', 'marital_status');
$hasGender = app_column_exists($pdo, 'users', 'gender');

$sql = "
    SELECT
        " . ($hasStates ? "s.state_name" : "NULL AS state_name") . ",
        " . ($hasLgas ? "l.lga_name" : "NULL AS lga_name") . ",
        u.role,
        " . ($hasDob ? "CASE WHEN u.dob IS NOT NULL THEN FLOOR(DATEDIFF(NOW(), u.dob) / 365.25) ELSE NULL END" : "NULL") . " AS age,
        " . ($hasEducation ? "u.education_level" : "NULL AS education_level") . ",
        " . ($hasExperience ? "u.farming_experience_rating" : "NULL AS farming_experience_rating") . ",
        " . ($hasMarital ? "u.marital_status" : "NULL AS marital_status") . ",
        " . ($hasGender ? "u.gender" : "NULL AS gender") . ",
        COUNT(*) as count
    FROM users u
";
if ($hasStates) {
    $sql .= " LEFT JOIN nigeria_states s ON u.state_id = s.id";
}
if ($hasLgas) {
    $sql .= " LEFT JOIN nigeria_lgas l ON u.lga_id = l.id";
}
$sql .= $hasTerms ? " WHERE u.terms_accepted = 1" : " WHERE 1=1";

$params = [];
$whereClauses = [];

if ($filters['state'] && $hasStates) {
    $whereClauses[] = "s.state_name = ?";
    $params[] = $filters['state'];
}
if ($filters['lga'] && $hasLgas) {
    $whereClauses[] = "l.lga_name = ?";
    $params[] = $filters['lga'];
}
if ($filters['gender'] && $hasGender) {
    $whereClauses[] = "u.gender = ?";
    $params[] = $filters['gender'];
}
if ($filters['min_age'] && $hasDob) {
    $whereClauses[] = "FLOOR(DATEDIFF(NOW(), u.dob) / 365.25) >= ?";
    $params[] = $filters['min_age'];
}
if ($filters['max_age'] && $hasDob) {
    $whereClauses[] = "FLOOR(DATEDIFF(NOW(), u.dob) / 365.25) <= ?";
    $params[] = $filters['max_age'];
}
if ($filters['education'] && $hasEducation) {
    $whereClauses[] = "u.education_level = ?";
    $params[] = $filters['education'];
}
if ($filters['experience'] && $hasExperience) {
    $whereClauses[] = "u.farming_experience_rating = ?";
    $params[] = $filters['experience'];
}

if (!empty($whereClauses)) {
    $sql .= " AND " . implode(" AND ", $whereClauses);
}

$sql .= " GROUP BY state_name, lga_name, u.role, age, education_level, farming_experience_rating, marital_status, gender";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Generate intelligent insights
$insights = generateInsights($data);

json_response([
    'success' => true,
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

function getExperienceDistribution($data) {
    $distribution = [];
    foreach ($data as $row) {
        $key = $row['farming_experience_rating'] ?: 'unknown';
        $distribution[$key] = ($distribution[$key] ?? 0) + (int) ($row['count'] ?? 0);
    }
    arsort($distribution);
    return $distribution;
}
