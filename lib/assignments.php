<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function assignment_grower_query(PDO $pdo, array $criteria, int $limit = 0): array
{
    $hasAssignments = app_table_exists($pdo, 'agronomist_assignments');
    $hasStates = app_table_exists($pdo, 'nigeria_states') && app_column_exists($pdo, 'applications', 'state_id');
    $hasLgas = app_table_exists($pdo, 'nigeria_lgas') && app_column_exists($pdo, 'applications', 'lga_id');

    $sql = "
        SELECT u.id, u.name, a.location, a.farm_size
        FROM users u
        JOIN applications a ON u.application_id = a.id
    ";
    if ($hasStates) {
        $sql .= " LEFT JOIN nigeria_states s ON a.state_id = s.id";
    }
    if ($hasLgas) {
        $sql .= " LEFT JOIN nigeria_lgas l ON a.lga_id = l.id";
    }

    $sql .= " WHERE u.role = 'grower'";
    if ($hasAssignments) {
        $sql .= "
            AND NOT EXISTS (
                SELECT 1 FROM agronomist_assignments aa
                WHERE aa.grower_id = u.id AND aa.status = 'active'
            )
        ";
    }

    $params = [];
    if (!empty($criteria['state'])) {
        if ($hasStates) {
            $sql .= " AND s.state_name = ?";
            $params[] = $criteria['state'];
        } else {
            $sql .= " AND a.location LIKE ?";
            $params[] = '%' . $criteria['state'] . '%';
        }
    }
    if (!empty($criteria['lga'])) {
        if ($hasLgas) {
            $sql .= " AND l.lga_name = ?";
            $params[] = $criteria['lga'];
        } else {
            $sql .= " AND a.location LIKE ?";
            $params[] = '%' . $criteria['lga'] . '%';
        }
    }
    if (!empty($criteria['ward']) && app_column_exists($pdo, 'users', 'ward')) {
        $sql .= " AND u.ward LIKE ?";
        $params[] = '%' . $criteria['ward'] . '%';
    }
    if (!empty($criteria['min_farm_size'])) {
        $sql .= " AND a.farm_size >= ?";
        $params[] = (float) $criteria['min_farm_size'];
    }
    if (!empty($criteria['experience']) && app_column_exists($pdo, 'users', 'farming_experience_rating')) {
        $sql .= " AND u.farming_experience_rating = ?";
        $params[] = $criteria['experience'];
    }
    if (!empty($criteria['education']) && app_column_exists($pdo, 'users', 'education_level')) {
        $sql .= " AND u.education_level = ?";
        $params[] = $criteria['education'];
    }

    $sql .= " ORDER BY a.created_at DESC";
    if ($limit > 0) {
        $sql .= " LIMIT " . (int) $limit;
    }

    return [$sql, $params];
}
