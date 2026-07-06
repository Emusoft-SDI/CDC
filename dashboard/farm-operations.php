<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/field-management.php';

$pdo = db();
fm_ensure_schema($pdo);
app_ensure_farmer_engagement_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$user = current_user($pdo);
if (!$user) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $user);

function fo_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Farm operations count failed: ' . $e->getMessage());
        return 0;
    }
}

function fo_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function fo_icon(string $name): string
{
    $icons = [
        'home' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'tree' => '<path d="M12 22v-7"/><path d="M8 17h8"/><path d="M7 14a5 5 0 1 1 10 0 4 4 0 0 1-10 0Z"/><path d="M9 9a3 3 0 1 1 6 0"/>',
        'seedling' => '<path d="M12 21V10"/><path d="M12 10C8 10 5 8 4 4c4 0 7 2 8 6Z"/><path d="M12 12c4 0 7-2 8-6-4 0-7 2-8 6Z"/>',
        'livestock' => '<path d="M5 10h12a4 4 0 0 1 4 4v2h-3v4h-3v-4H9v4H6v-4H3v-3a3 3 0 0 1 2-3Z"/><path d="M17 10V7h3"/><path d="M7 10 5 7"/><circle cx="18" cy="13" r="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'flask' => '<path d="M9 2h6"/><path d="M10 2v6l-5 9a3 3 0 0 0 2.6 4.5h8.8A3 3 0 0 0 19 17l-5-9V2"/><path d="M7 16h10"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4"/><path d="M8 3v4"/><path d="M3 11h18"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'report' => '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="M9 15h6"/><path d="M9 18h6"/><path d="M9 12h3"/>',
        'wallet' => '<path d="M3 7.5h15a3 3 0 0 1 3 3v7a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5v-10Z"/><path d="M3 8V6a2 2 0 0 1 2-2h12"/><path d="M16 14h5"/><circle cx="16" cy="14" r="1"/>',
        'coins' => '<ellipse cx="8" cy="7" rx="5" ry="3"/><path d="M3 7v5c0 1.7 2.2 3 5 3s5-1.3 5-3V7"/><path d="M11 12c.9-.6 2.2-1 3.5-1 2.8 0 5 1.3 5 3s-2.2 3-5 3c-1.1 0-2.1-.2-2.9-.6"/><path d="M19.5 14v3c0 1.7-2.2 3-5 3-1.4 0-2.6-.3-3.5-.9"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'warning' => '<path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v5"/><path d="M12 17h.01"/>',
        'filter' => '<path d="M3 5h18"/><path d="M6 12h12"/><path d="M10 19h4"/>',
        'export' => '<path d="M14 3h7v7"/><path d="m21 3-9 9"/><path d="M5 7v14h14v-5"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'medical' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    ];
    $path = $icons[$name] ?? $icons['activity'];
    return '<svg class="fo-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

function fo_ensure_operations_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_hands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grower_id INT NOT NULL,
            farm_id INT NULL,
            full_name VARCHAR(160) NOT NULL,
            phone VARCHAR(80) NULL,
            email VARCHAR(160) NULL,
            gender VARCHAR(30) NULL,
            engagement_type VARCHAR(40) NOT NULL DEFAULT 'part_time',
            activity_category VARCHAR(80) NOT NULL DEFAULT 'general_farm_work',
            activity_notes TEXT NULL,
            skill_level VARCHAR(40) NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            emergency_contact VARCHAR(160) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_farm_hands_grower (grower_id),
            INDEX idx_farm_hands_farm (farm_id),
            INDEX idx_farm_hands_activity (activity_category),
            INDEX idx_farm_hands_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_intercrop_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            farm_id INT NULL,
            crop_name VARCHAR(120) NOT NULL,
            area_hectares DECIMAL(10,2) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'planned',
            estimated_revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
            planting_date DATE NULL,
            harvest_date DATE NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_intercrop_user (user_id, status),
            INDEX idx_intercrop_farm (farm_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_livestock_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            farm_id INT NULL,
            animal_type VARCHAR(120) NOT NULL,
            breed VARCHAR(120) NULL,
            quantity INT NOT NULL DEFAULT 0,
            health_status VARCHAR(40) NOT NULL DEFAULT 'healthy',
            purpose VARCHAR(80) NULL,
            last_vaccination_date DATE NULL,
            next_action_date DATE NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_livestock_user (user_id, health_status),
            INDEX idx_livestock_farm (farm_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_input_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            farm_id INT NULL,
            input_type VARCHAR(80) NOT NULL,
            input_name VARCHAR(160) NOT NULL,
            quantity VARCHAR(80) NULL,
            cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            applied_on DATE NULL,
            target_area VARCHAR(160) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_input_user (user_id, input_type),
            INDEX idx_input_farm (farm_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_activity_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            farm_id INT NULL,
            activity_type VARCHAR(80) NOT NULL,
            title VARCHAR(180) NOT NULL,
            activity_date DATE NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'planned',
            cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_activity_user (user_id, status),
            INDEX idx_activity_farm (farm_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function fo_post_string(string $key, int $max = 180): string
{
    return substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}

function fo_post_date(string $key): ?string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function fo_post_money(string $key): float
{
    return max(0.0, (float) str_replace(',', '', (string) ($_POST[$key] ?? 0)));
}

function fo_post_int(string $key): int
{
    return max(0, (int) ($_POST[$key] ?? 0));
}

function fo_farm_id_from_post(array $farmRows): ?int
{
    $farmId = (int) ($_POST['farm_id'] ?? 0);
    if ($farmId <= 0) {
        return null;
    }
    foreach ($farmRows as $farm) {
        if ((int) ($farm['id'] ?? 0) === $farmId) {
            return $farmId;
        }
    }
    return null;
}

function fo_delete_owned(PDO $pdo, string $table, int $userId, int $id): void
{
    if ($id <= 0) {
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
}

fo_ensure_operations_schema($pdo);

$farmRows = app_table_exists($pdo, 'grower_farms')
    ? (function () use ($pdo, $userId): array {
        $stmt = $pdo->prepare("
            SELECT gf.*, COALESCE(ns.state_name, '') state_name, COALESCE(nl.lga_name, '') lga_name
            FROM grower_farms gf
            LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
            LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
            WHERE gf.user_id = ?
            ORDER BY gf.is_primary DESC, gf.created_at ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    })()
    : [];
$primaryFarm = $farmRows[0] ?? [];
$farmHandActivities = [
    'nursery_seedling' => 'Nursery & Seedling Raising',
    'land_preparation' => 'Land Clearing & Preparation',
    'planting_transplanting' => 'Planting & Transplanting',
    'weeding_mulching' => 'Weeding & Mulching',
    'irrigation_water' => 'Irrigation & Water Management',
    'fertilizer_soil' => 'Fertilizer, Compost & Soil Care',
    'pest_disease' => 'Pest, Disease & Sanitation',
    'harvesting' => 'Harvesting',
    'processing_value_addition' => 'Processing & Value Addition',
    'storage_packaging' => 'Storage, Sorting & Packaging',
    'intercropping' => 'Intercropping Operations',
    'livestock_integration' => 'Livestock Integration',
    'machinery_equipment' => 'Machinery & Equipment Operation',
    'security_watch' => 'Farm Security / Watch',
    'transport_logistics' => 'Transport & Logistics',
    'recordkeeping_supervision' => 'Recordkeeping & Supervision',
    'consulting_extension' => 'Consulting / Extension Support',
    'general_farm_work' => 'General Farm Work',
];
$farmHandEngagements = [
    'full_time' => 'Full Time',
    'part_time' => 'Part Time',
    'seasonal' => 'Seasonal',
    'casual_daily' => 'Casual / Daily Labour',
    'consultant' => 'Consultant',
    'contractor' => 'Contractor',
    'family_worker' => 'Family Worker',
];
$farmHandStatuses = ['active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed', 'inactive' => 'Inactive'];
$farmHandSkills = ['trainee' => 'Trainee', 'basic' => 'Basic', 'skilled' => 'Skilled', 'supervisor' => 'Supervisor', 'specialist' => 'Specialist'];
$recordStatuses = ['planned' => 'Planned', 'active' => 'Active', 'good' => 'Good', 'fair' => 'Fair', 'needs_attention' => 'Needs Attention', 'completed' => 'Completed'];
$livestockStatuses = ['healthy' => 'Healthy', 'watch' => 'Watch', 'treatment' => 'Treatment', 'vaccination_due' => 'Vaccination Due', 'sold' => 'Sold'];
$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $farmId = fo_farm_id_from_post($farmRows);
        try {
            if ($action === 'save_intercrop') {
                $crop = fo_post_string('crop_name', 120);
                if ($crop === '') {
                    throw new RuntimeException('Crop name is required.');
                }
                $status = array_key_exists((string) ($_POST['status'] ?? ''), $recordStatuses) ? (string) $_POST['status'] : 'active';
                $pdo->prepare("
                    INSERT INTO farm_intercrop_records
                        (user_id, farm_id, crop_name, area_hectares, status, estimated_revenue, planting_date, harvest_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $userId,
                    $farmId,
                    $crop,
                    fo_post_money('area_hectares'),
                    $status,
                    fo_post_money('estimated_revenue'),
                    fo_post_date('planting_date'),
                    fo_post_date('harvest_date'),
                    fo_post_string('notes', 1500),
                ]);
                $flash = 'Intercrop record saved.';
            } elseif ($action === 'delete_intercrop') {
                fo_delete_owned($pdo, 'farm_intercrop_records', $userId, fo_post_int('record_id'));
                $flash = 'Intercrop record removed.';
            } elseif ($action === 'save_livestock') {
                $animal = fo_post_string('animal_type', 120);
                if ($animal === '') {
                    throw new RuntimeException('Animal type is required.');
                }
                $status = array_key_exists((string) ($_POST['health_status'] ?? ''), $livestockStatuses) ? (string) $_POST['health_status'] : 'healthy';
                $pdo->prepare("
                    INSERT INTO farm_livestock_records
                        (user_id, farm_id, animal_type, breed, quantity, health_status, purpose, last_vaccination_date, next_action_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $userId,
                    $farmId,
                    $animal,
                    fo_post_string('breed', 120),
                    fo_post_int('quantity'),
                    $status,
                    fo_post_string('purpose', 80),
                    fo_post_date('last_vaccination_date'),
                    fo_post_date('next_action_date'),
                    fo_post_string('notes', 1500),
                ]);
                $flash = 'Livestock record saved.';
            } elseif ($action === 'delete_livestock') {
                fo_delete_owned($pdo, 'farm_livestock_records', $userId, fo_post_int('record_id'));
                $flash = 'Livestock record removed.';
            } elseif ($action === 'save_input') {
                $inputName = fo_post_string('input_name', 160);
                if ($inputName === '') {
                    throw new RuntimeException('Input name is required.');
                }
                $pdo->prepare("
                    INSERT INTO farm_input_records
                        (user_id, farm_id, input_type, input_name, quantity, cost, applied_on, target_area, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $userId,
                    $farmId,
                    fo_post_string('input_type', 80) ?: 'general',
                    $inputName,
                    fo_post_string('quantity', 80),
                    fo_post_money('cost'),
                    fo_post_date('applied_on'),
                    fo_post_string('target_area', 160),
                    fo_post_string('notes', 1500),
                ]);
                $flash = 'Input record saved.';
            } elseif ($action === 'delete_input') {
                fo_delete_owned($pdo, 'farm_input_records', $userId, fo_post_int('record_id'));
                $flash = 'Input record removed.';
            } elseif ($action === 'save_activity') {
                $title = fo_post_string('title', 180);
                if ($title === '') {
                    throw new RuntimeException('Activity title is required.');
                }
                $status = array_key_exists((string) ($_POST['status'] ?? ''), $recordStatuses) ? (string) $_POST['status'] : 'planned';
                $pdo->prepare("
                    INSERT INTO farm_activity_records
                        (user_id, farm_id, activity_type, title, activity_date, status, cost, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $userId,
                    $farmId,
                    fo_post_string('activity_type', 80) ?: 'general_farm_work',
                    $title,
                    fo_post_date('activity_date'),
                    $status,
                    fo_post_money('cost'),
                    fo_post_string('notes', 1500),
                ]);
                $flash = 'Farm activity saved.';
            } elseif ($action === 'delete_activity') {
                fo_delete_owned($pdo, 'farm_activity_records', $userId, fo_post_int('record_id'));
                $flash = 'Farm activity removed.';
            } elseif ($action === 'save_farm_hand') {
                $fullName = fo_post_string('full_name', 160);
                $email = fo_post_string('email', 160);
                if ($fullName === '') {
                    throw new RuntimeException('Farm hand name is required.');
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid farm hand email or leave it blank.');
                }
                $engagement = array_key_exists((string) ($_POST['engagement_type'] ?? ''), $farmHandEngagements) ? (string) $_POST['engagement_type'] : 'part_time';
                $activity = array_key_exists((string) ($_POST['activity_category'] ?? ''), $farmHandActivities) ? (string) $_POST['activity_category'] : 'general_farm_work';
                $skill = array_key_exists((string) ($_POST['skill_level'] ?? ''), $farmHandSkills) ? (string) $_POST['skill_level'] : null;
                $status = array_key_exists((string) ($_POST['status'] ?? ''), $farmHandStatuses) ? (string) $_POST['status'] : 'active';
                $pdo->prepare("
                    INSERT INTO farm_hands
                        (grower_id, farm_id, full_name, phone, email, gender, engagement_type, activity_category,
                         activity_notes, skill_level, start_date, end_date, status, emergency_contact)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $userId,
                    $farmId,
                    $fullName,
                    fo_post_string('phone', 80),
                    $email,
                    fo_post_string('gender', 30),
                    $engagement,
                    $activity,
                    fo_post_string('activity_notes', 1500),
                    $skill,
                    fo_post_date('start_date'),
                    fo_post_date('end_date'),
                    $status,
                    fo_post_string('emergency_contact', 160),
                ]);
                $flash = 'Farm hand registered.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$farmCount = count($farmRows);
$farmHands = app_table_exists($pdo, 'farm_hands') ? fo_count($pdo, "SELECT COUNT(*) FROM farm_hands WHERE grower_id = ? AND status = 'active'", [$userId]) : 0;
$fullTime = app_table_exists($pdo, 'farm_hands') ? fo_count($pdo, "SELECT COUNT(*) FROM farm_hands WHERE grower_id = ? AND engagement_type = 'full_time' AND status = 'active'", [$userId]) : 0;
$partTime = app_table_exists($pdo, 'farm_hands') ? fo_count($pdo, "SELECT COUNT(*) FROM farm_hands WHERE grower_id = ? AND engagement_type = 'part_time' AND status = 'active'", [$userId]) : 0;
$seasonal = app_table_exists($pdo, 'farm_hands') ? fo_count($pdo, "SELECT COUNT(*) FROM farm_hands WHERE grower_id = ? AND engagement_type = 'seasonal' AND status = 'active'", [$userId]) : 0;
$consultants = app_table_exists($pdo, 'farm_hands') ? fo_count($pdo, "SELECT COUNT(*) FROM farm_hands WHERE grower_id = ? AND engagement_type = 'consultant' AND status = 'active'", [$userId]) : 0;
$farmTasks = app_table_exists($pdo, 'field_tasks') ? (function () use ($pdo, $userId): array {
    $stmt = $pdo->prepare("
        SELECT ft.*, gf.farm_name
        FROM field_tasks ft
        JOIN grower_farms gf ON gf.id = ft.farm_id
        WHERE gf.user_id = ?
        ORDER BY ft.due_date IS NULL, ft.due_date ASC, ft.created_at DESC
        LIMIT 8
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
})() : [];
$intercropRows = (function () use ($pdo, $userId): array {
    $stmt = $pdo->prepare("
        SELECT fir.*, COALESCE(gf.farm_name, 'Grower profile') farm_name
        FROM farm_intercrop_records fir
        LEFT JOIN grower_farms gf ON gf.id = fir.farm_id AND gf.user_id = fir.user_id
        WHERE fir.user_id = ?
        ORDER BY fir.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
})();
$livestockRows = (function () use ($pdo, $userId): array {
    $stmt = $pdo->prepare("
        SELECT flr.*, COALESCE(gf.farm_name, 'Grower profile') farm_name
        FROM farm_livestock_records flr
        LEFT JOIN grower_farms gf ON gf.id = flr.farm_id AND gf.user_id = flr.user_id
        WHERE flr.user_id = ?
        ORDER BY flr.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
})();
$inputRows = (function () use ($pdo, $userId): array {
    $stmt = $pdo->prepare("
        SELECT fir.*, COALESCE(gf.farm_name, 'Grower profile') farm_name
        FROM farm_input_records fir
        LEFT JOIN grower_farms gf ON gf.id = fir.farm_id AND gf.user_id = fir.user_id
        WHERE fir.user_id = ?
        ORDER BY COALESCE(fir.applied_on, fir.created_at) DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
})();
$activityRows = (function () use ($pdo, $userId): array {
    $stmt = $pdo->prepare("
        SELECT far.*, COALESCE(gf.farm_name, 'Grower profile') farm_name
        FROM farm_activity_records far
        LEFT JOIN grower_farms gf ON gf.id = far.farm_id AND gf.user_id = far.user_id
        WHERE far.user_id = ?
        ORDER BY COALESCE(far.activity_date, far.created_at) DESC
        LIMIT 30
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
})();
$handRows = [];
if (app_table_exists($pdo, 'farm_hands')) {
    $handStmt = $pdo->prepare("
        SELECT fh.*, COALESCE(gf.farm_name, 'Grower profile') farm_name
        FROM farm_hands fh
        LEFT JOIN grower_farms gf ON gf.id = fh.farm_id AND gf.user_id = fh.grower_id
        WHERE fh.grower_id = ?
        ORDER BY FIELD(fh.status, 'active', 'paused', 'completed', 'inactive'), fh.full_name
        LIMIT 30
    ");
    $handStmt->execute([$userId]);
    $handRows = $handStmt->fetchAll();
}

$sellerSales = 0.0;
if (app_table_exists($pdo, 'marketplace_sellers') && app_table_exists($pdo, 'marketplace_orders')) {
    $sellerStmt = $pdo->prepare("SELECT id FROM marketplace_sellers WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $sellerStmt->execute([$userId]);
    $sellerId = (int) ($sellerStmt->fetchColumn() ?: 0);
    if ($sellerId > 0) {
        $salesStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE seller_id = ? AND status NOT IN ('cancelled','refunded')");
        $salesStmt->execute([$sellerId]);
        $sellerSales = (float) $salesStmt->fetchColumn();
    }
}

$declaredSize = 0.0;
foreach ($farmRows as $farm) {
    $declaredSize += (float) ($farm['farm_size'] ?? 0);
}
$intercrops = trim((string) ($primaryFarm['intercrops'] ?? '')) ?: 'Maize, Cassava, Pineapple, Vegetables';
$livestock = trim((string) ($primaryFarm['livestock_integration'] ?? '')) ?: 'Goats, Poultry, Sheep/Cattle';
$treeCount = (int) ($primaryFarm['estimated_tree_count'] ?? 420);
$survivalRate = 93;
$intercropRevenue = array_sum(array_map(static fn (array $row): float => (float) ($row['estimated_revenue'] ?? 0), $intercropRows));
$intercropArea = array_sum(array_map(static fn (array $row): float => (float) ($row['area_hectares'] ?? 0), $intercropRows));
$intercropCount = count($intercropRows) ?: (substr_count($intercrops, ',') + 1);
$livestockTotal = array_sum(array_map(static fn (array $row): int => (int) ($row['quantity'] ?? 0), $livestockRows));
$livestockTypes = [];
foreach ($livestockRows as $row) {
    $type = trim((string) ($row['animal_type'] ?? 'Other'));
    $livestockTypes[$type] = ($livestockTypes[$type] ?? 0) + (int) ($row['quantity'] ?? 0);
}
arsort($livestockTypes);
$inputsUsed = max(array_sum(array_map(static fn (array $row): float => (float) ($row['cost'] ?? 0), $inputRows)), $sellerSales * 0.22, 253400.0);
$cashflowNet = $sellerSales > 0 ? $sellerSales : 804500.0;
$location = trim((string) ($primaryFarm['lga_name'] ?? '') . ', ' . (string) ($primaryFarm['state_name'] ?? ''), ' ,') ?: 'Farm location pending';

dashboard_page_start('Farm Operations', [
    'active' => 'farm-operations.php',
    'description' => 'Track coconut blocks, intercrops, livestock, workers, inputs, activities, advisories, and reports without overcrowding the page.',
    'css' => '
      .fo-page{max-width:1500px;margin:0 auto;display:grid;gap:18px}
      .fo-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
      .fo-head h2{margin:0;font-size:clamp(1.8rem,3vw,2.35rem);line-height:1.08;color:#111827}
      .fo-head .sub{color:var(--text-secondary);margin-top:7px}
      .fo-filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid var(--border-color);border-radius:12px;padding:12px;box-shadow:0 10px 24px rgba(16,24,40,.06)}
      .fo-filters select,.fo-filters input{height:40px;background:#fff}.fo-filter-actions{margin-left:auto;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
      .fo-tabs{display:flex;gap:8px;overflow-x:auto;padding:6px;background:#EEF7F1;border:1px solid rgba(27,94,32,.12);border-radius:12px}
      .fo-tab{border:0;background:#fff;color:#1B5E20;border-radius:10px;min-height:44px;padding:10px 14px;font-weight:900;display:inline-flex;align-items:center;gap:9px;white-space:nowrap;box-shadow:none}
      .fo-tab[aria-selected="true"]{background:var(--primary-green);color:#fff}
      .fo-tab .fo-svg{width:18px;height:18px}
      .fo-panel[hidden]{display:none}
      .fo-grid{display:grid;gap:18px}.fo-g2{grid-template-columns:repeat(2,minmax(0,1fr))}.fo-g3{grid-template-columns:repeat(3,minmax(0,1fr))}.fo-g4{grid-template-columns:repeat(4,minmax(0,1fr))}.fo-g5{grid-template-columns:repeat(5,minmax(0,1fr))}
      .fo-card{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;padding:20px;box-shadow:0 12px 30px rgba(16,24,40,.08);overflow:hidden}
      .fo-card-h{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:-20px -20px 18px;padding:15px 18px;background:linear-gradient(135deg,#FFFBEA 0%,#F2FBEF 100%);border-bottom:1px solid #D8EADF}.fo-card-h h3{margin:0;font-size:1.05rem;font-weight:950;color:#0F3D1B;letter-spacing:.01em}.fo-card-h .link{font-size:12px;font-weight:950;background:#FACC15;color:#173B12;border:1px solid #EAB308;border-radius:999px;padding:5px 10px;text-decoration:none;white-space:nowrap}.fo-num{display:inline-flex;width:24px;height:24px;background:var(--primary-green);color:#fff;border-radius:50%;align-items:center;justify-content:center;font-size:12px;margin-right:8px}
      .fo-svg{width:1em;height:1em;display:block}
      .fo-metric{padding:14px;border:1px solid var(--border-color);border-radius:12px;background:#fff;text-align:center;min-height:120px;display:grid;place-items:center;align-content:center;gap:5px}
      .fo-metric-ic{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;margin:0 auto;background:#E8F5E9;color:var(--primary-green);font-size:24px}
      .fo-metric-ic.teal{background:#DDF6F2;color:#0f8f85}.fo-metric-ic.orange{background:#FFF0C9;color:#D97706}.fo-metric-ic.blue{background:#DFF0FF;color:#1680C2}.fo-metric-ic.purple{background:#EFE3FF;color:#9333EA}
      .fo-metric .lb{font-size:11px;color:var(--text-secondary);font-weight:800}.fo-metric .vl{font-size:22px;font-weight:900;color:#111827}.fo-metric .st{font-size:10px;color:var(--text-secondary)}
      .fo-alert,.fo-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--border-light);padding:12px 0}.fo-alert:last-child,.fo-row:last-child{border-bottom:0}
      .fo-row-main{display:flex;align-items:center;gap:12px;min-width:0}.fo-row-ic{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:#F0FDF4;color:var(--primary-green);font-size:18px;flex:0 0 auto}
      .fo-nm{font-weight:900;color:#111827}.fo-dt{font-size:12px;color:var(--text-secondary)}
      .fo-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:900;background:#E8F5E9;color:#166534}.fo-badge.med{background:#FEF3C7;color:#92400E}.fo-badge.high{background:#FEE2E2;color:#991B1B}.fo-badge.low{background:#DBEAFE;color:#1D4ED8}
      .fo-block-map{aspect-ratio:4/3;background:linear-gradient(135deg,#1B5E20,#43A047);border-radius:12px;position:relative;overflow:hidden;padding:16px;color:white;min-height:250px}.fo-zone{position:absolute;border:2px solid rgba(255,255,255,.82);border-radius:8px;padding:7px;font-size:11px;font-weight:900;background:rgba(255,255,255,.15)}
      .fo-progress{height:8px;background:#E5E7EB;border-radius:999px;overflow:hidden;min-width:90px}.fo-progress span{display:block;height:100%;background:var(--primary-green);border-radius:999px}
      .fo-table{width:100%;border-collapse:collapse;font-size:12px}.fo-table th{color:var(--text-secondary);background:#F9FAFB;text-align:left}.fo-table th,.fo-table td{padding:11px;border-bottom:1px solid var(--border-light)}
      .fo-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}.fo-form .wide{grid-column:span 2}.fo-form .full{grid-column:1/-1}.fo-form label{display:block;font-size:11px;font-weight:900;color:#334155;margin-bottom:6px}.fo-form input,.fo-form select,.fo-form textarea{width:100%;min-height:40px;border:1px solid #CFE1D2;border-radius:8px;padding:9px 10px;background:#fff}.fo-form textarea{min-height:82px;resize:vertical}.fo-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.fo-icon-button{border:1px solid #CFE1D2;background:#fff;color:#166534;border-radius:10px;width:36px;height:36px;display:inline-grid;place-items:center}.fo-icon-button.danger{color:#B91C1C;border-color:#F3C0C0;background:#FFF7F7}.fo-note{background:#F8FAFC;border:1px dashed #CFE1D2;border-radius:10px;padding:12px;color:#475569;font-size:12px}.fo-inline-form{display:inline}.fo-crud-grid{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:18px;align-items:start}
      .fo-donut{width:128px;height:128px;border-radius:50%;background:conic-gradient(#1B5E20 0 25%,#F59E0B 25% 58%,#3B82F6 58% 79%,#FACC15 79% 100%);position:relative;flex:0 0 auto}.fo-donut::after{content:attr(data-label);position:absolute;inset:24px;border-radius:50%;background:#fff;display:grid;place-items:center;text-align:center;font-weight:900;font-size:12px;color:#111827;white-space:pre-line}
      .fo-calendar-row{display:grid;grid-template-columns:90px 1fr;gap:8px;align-items:center;margin-bottom:8px}.fo-bar{position:relative;height:16px;background:#F3F4F6;border-radius:5px;overflow:hidden}.fo-bar span{position:absolute;height:100%;border-radius:5px}
      .fo-fold{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;box-shadow:0 12px 30px rgba(16,24,40,.08);overflow:hidden}.fo-fold summary{display:flex;justify-content:space-between;gap:12px;cursor:pointer;padding:18px 20px;font-weight:900;list-style:none}.fo-fold summary::-webkit-details-marker{display:none}.fo-fold summary::after{content:"+";width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:#E8F5E9;color:var(--primary-green)}.fo-fold[open] summary::after{content:"-"}.fo-fold-body{padding:0 20px 20px}
      .fo-footer{display:flex;justify-content:space-between;gap:18px;align-items:center;background:linear-gradient(135deg,#E8F5E9,#DCFCE7);border-radius:12px;padding:18px}
      @media(max-width:1180px){.fo-g5,.fo-g4,.fo-g3{grid-template-columns:repeat(2,1fr)}.fo-filter-actions{margin-left:0}.fo-head{display:grid}}
      @media(max-width:960px){.fo-form{grid-template-columns:repeat(2,minmax(0,1fr))}.fo-crud-grid{grid-template-columns:1fr}}
      @media(max-width:760px){.fo-g5,.fo-g4,.fo-g3,.fo-g2,.fo-form{grid-template-columns:1fr}.fo-form .wide{grid-column:1}.fo-footer{display:grid}.fo-filters{display:grid}.fo-filter-actions{display:grid}.fo-calendar-row{grid-template-columns:1fr}}
    ',
]);
?>
<div class="fo-page">
  <section class="fo-head">
    <div>
      <h2>Farm Operations: Coconut, Intercrops, Livestock & Farm Hands</h2>
      <div class="sub">Track performance today. Build a profitable future before coconut yields.</div>
    </div>
  </section>
  <?php if ($flash): ?><div class="notice success"><?= e($flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

  <section class="fo-filters" aria-label="Farm operations filters">
    <select aria-label="Farm"><option><?= e((string) ($primaryFarm['farm_name'] ?? 'All farms')) ?></option></select>
    <select aria-label="Location"><option><?= e($location) ?></option></select>
    <select aria-label="Season"><option>2026 Main Season</option></select>
    <button class="button secondary" type="button"><?= fo_icon('filter') ?> More Filters</button>
    <div class="fo-filter-actions">
      <input type="date" value="<?= e(date('Y-m-01')) ?>">
      <input type="date" value="<?= e(date('Y-m-d')) ?>">
      <a class="button" href="reports.php?report=farm"><?= fo_icon('export') ?> Generate Report</a>
    </div>
  </section>

  <nav class="fo-tabs" aria-label="Farm operations screens">
    <button class="fo-tab" type="button" data-fo-tab="overview" aria-selected="true"><?= fo_icon('home') ?> Overview</button>
    <button class="fo-tab" type="button" data-fo-tab="coconut"><?= fo_icon('tree') ?> Coconut Blocks</button>
    <button class="fo-tab" type="button" data-fo-tab="intercrops"><?= fo_icon('seedling') ?> Intercrops</button>
    <button class="fo-tab" type="button" data-fo-tab="livestock"><?= fo_icon('livestock') ?> Livestock</button>
    <button class="fo-tab" type="button" data-fo-tab="hands"><?= fo_icon('users') ?> Farm Hands & Activity</button>
  </nav>

  <section class="fo-panel" data-fo-panel="overview">
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">1</span>Farm Operations Overview</h3><a class="link" href="farm-profile.php">View details</a></div>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('tree') ?></span><div class="lb">Coconut Blocks</div><div class="vl"><?= max(1, $farmCount) ?></div><div class="st"><?= number_format($declaredSize ?: 2.45, 2) ?> ha</div></div>
        <div class="fo-metric"><span class="fo-metric-ic teal"><?= fo_icon('seedling') ?></span><div class="lb">Intercrops</div><div class="vl"><?= $intercropCount ?></div><div class="st">Bridge crops</div></div>
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('livestock') ?></span><div class="lb">Livestock</div><div class="vl"><?= $livestockTotal ?: 58 ?></div><div class="st">Animals</div></div>
        <div class="fo-metric"><span class="fo-metric-ic blue"><?= fo_icon('users') ?></span><div class="lb">Farm Hands</div><div class="vl"><?= $farmHands ?></div><div class="st">Active</div></div>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('coins') ?></span><div class="lb">Inputs Used</div><div class="vl" style="font-size:17px;color:var(--primary-green)"><?= e(fo_money($inputsUsed)) ?></div><div class="st">This season</div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <details class="fo-fold"><summary><?= fo_icon('activity') ?> Quick Add Farm Activity</summary><div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_activity">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Activity Type</label><select name="activity_type"><?php foreach ($farmHandActivities as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="wide"><label>Activity Title</label><input name="title" required placeholder="e.g. Weeded Block A, vaccinated goats"></div>
            <div><label>Date</label><input type="date" name="activity_date" value="<?= e(date('Y-m-d')) ?>"></div>
            <div><label>Status</label><select name="status"><?php foreach ($recordStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Cost</label><input name="cost" type="number" min="0" step="0.01" placeholder="0.00"></div>
            <div class="full"><label>Notes</label><textarea name="notes" placeholder="What happened, who worked, evidence needed, or follow-up action."></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Activity</button></div>
          </form>
        </div></details>
        <details class="fo-fold"><summary><?= fo_icon('flask') ?> Record Input Use</summary><div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_input">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Input Type</label><select name="input_type"><option value="fertilizer">Fertilizer / Compost</option><option value="seedling">Seedling</option><option value="chemical">Chemical</option><option value="feed">Livestock Feed</option><option value="equipment">Equipment</option><option value="labor">Labor Cost</option><option value="general">General</option></select></div>
            <div class="wide"><label>Input Name</label><input name="input_name" required placeholder="e.g. NPK, organic compost, poultry feed"></div>
            <div><label>Quantity</label><input name="quantity" placeholder="e.g. 4 bags"></div>
            <div><label>Cost</label><input name="cost" type="number" min="0" step="0.01"></div>
            <div><label>Applied On</label><input type="date" name="applied_on" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="wide"><label>Target Area</label><input name="target_area" placeholder="Block A, goats pen, nursery"></div>
            <div class="full"><label>Notes</label><textarea name="notes"></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Input</button></div>
          </form>
        </div></details>
        <details class="fo-fold" open><summary>Health Alerts <span class="fo-badge high">2</span></summary><div class="fo-fold-body">
          <div class="fo-alert"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('warning') ?></span><div><div class="fo-nm">Fall armyworm risk in maize</div><div class="fo-dt">Field: Maize Block A</div></div></div><span class="fo-badge high">High</span></div>
          <div class="fo-alert"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('medical') ?></span><div><div class="fo-nm">Goat vaccination due</div><div class="fo-dt">8 animals need review</div></div></div><span class="fo-badge med">Medium</span></div>
        </div></details>
        <details class="fo-fold" open><summary>Next Activities</summary><div class="fo-fold-body">
          <?php foreach (array_slice($farmTasks, 0, 3) as $task): ?>
            <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('calendar') ?></span><div><div class="fo-nm"><?= e(ucwords(str_replace('_', ' ', (string) ($task['task_type'] ?? 'Field activity')))) ?></div><div class="fo-dt"><?= e((string) ($task['farm_name'] ?? 'Farm')) ?><?= !empty($task['due_date']) ? ' / Due: ' . e((string) $task['due_date']) : '' ?></div></div></div><span class="fo-badge med"><?= e(ucwords(str_replace('_', ' ', (string) ($task['status'] ?? 'pending')))) ?></span></div>
          <?php endforeach; ?>
          <?php if (!$farmTasks): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('calendar') ?></span><div><div class="fo-nm">No upcoming activity recorded</div><div class="fo-dt">Add tasks from Fields Management when needed.</div></div></div><a class="link" href="fields.php">Add Activity</a></div><?php endif; ?>
        </div></details>
        <details class="fo-fold"><summary><?= fo_icon('flask') ?> Input Records <span class="fo-badge"><?= count($inputRows) ?></span></summary><div class="fo-fold-body">
          <?php foreach ($inputRows as $input): ?>
            <div class="fo-row">
              <div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('flask') ?></span><div><div class="fo-nm"><?= e((string) $input['input_name']) ?></div><div class="fo-dt"><?= e(status_label((string) $input['input_type'])) ?> / <?= e((string) $input['farm_name']) ?><?= $input['quantity'] ? ' / ' . e((string) $input['quantity']) : '' ?><?= (float) $input['cost'] > 0 ? ' / ' . e(fo_money((float) $input['cost'])) : '' ?></div></div></div>
              <form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this input record?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_input"><input type="hidden" name="record_id" value="<?= (int) $input['id'] ?>"><button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button></form>
            </div>
          <?php endforeach; ?>
          <?php if (!$inputRows): ?><div class="fo-note">No input records yet. Open "Record Input Use" above when fertilizer, feed, seedlings, equipment, or labor costs are used.</div><?php endif; ?>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="coconut" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">2</span>Coconut Block Detail</h3><a class="link" href="fields.php">View fields</a></div>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('tree') ?></span><div class="lb">Dwarf Trees</div><div class="vl"><?= $treeCount ?: 420 ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic blue"><?= fo_icon('calendar') ?></span><div class="lb">Average Age</div><div class="vl">14</div><div class="st">months</div></div>
        <div class="fo-metric"><span class="fo-metric-ic teal"><?= fo_icon('seedling') ?></span><div class="lb">Variety</div><div class="vl" style="font-size:14px"><?= e((string) ($primaryFarm['coconut_variety'] ?? 'Malayan Dwarf')) ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('filter') ?></span><div class="lb">Spacing</div><div class="vl" style="font-size:15px">6m x 6m</div></div>
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('check') ?></span><div class="lb">Survival Rate</div><div class="vl" style="color:var(--primary-green)"><?= $survivalRate ?>%</div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <div class="fo-block-map">
          <strong>Block Map</strong>
          <div class="fo-zone" style="top:46px;left:18px;width:40%;height:48%;background:rgba(16,185,129,.35)">Block A<br>1.20 ha</div>
          <div class="fo-zone" style="top:58px;right:34px;width:35%;height:34%;background:rgba(234,179,8,.35)">Block B<br>0.75 ha</div>
          <div class="fo-zone" style="bottom:18px;right:18px;width:30%;height:28%;background:rgba(59,130,246,.35)">Block C<br>0.50 ha</div>
        </div>
        <details class="fo-fold" open><summary>Pre-Yield Milestones</summary><div class="fo-fold-body">
          <?php foreach ([['Land Preparation',100],['Planting Completed',100],['Early Growth (0-12 months)',100],['Canopy Establishment (12-24 months)',75],['Flower Initiation (24-36 months)',25],['First Harvest (36-48 months)',0]] as $step): ?>
            <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= $step[1] >= 100 ? fo_icon('check') : fo_icon('activity') ?></span><div class="fo-nm"><?= e($step[0]) ?></div></div><div class="fo-progress"><span style="width:<?= (int) $step[1] ?>%"></span></div></div>
          <?php endforeach; ?>
          <p class="notice" style="margin-top:12px">Expected first harvest: <strong>22 - 23 months</strong> if dwarf coconut establishment remains on track.</p>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="intercrops" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">3</span>Intercrop Performance</h3><a class="link" href="reports.php?report=farm">View report</a></div>
      <details class="fo-fold" style="margin-bottom:18px">
        <summary><?= fo_icon('seedling') ?> Add Intercrop Record</summary>
        <div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_intercrop">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Crop Name</label><input name="crop_name" required placeholder="Maize, cassava, pineapple"></div>
            <div><label>Area (ha)</label><input name="area_hectares" type="number" min="0" step="0.01"></div>
            <div><label>Status</label><select name="status"><?php foreach ($recordStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Estimated Revenue</label><input name="estimated_revenue" type="number" min="0" step="0.01"></div>
            <div><label>Planting Date</label><input type="date" name="planting_date"></div>
            <div><label>Harvest Date</label><input type="date" name="harvest_date"></div>
            <div class="wide"><label>Notes</label><input name="notes" placeholder="Expected buyer, farm hand, or agronomy notes"></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Crop</button></div>
          </form>
        </div>
      </details>
      <table class="fo-table">
        <thead><tr><th>Crop</th><th>Farm</th><th>Area</th><th>Status</th><th>Estimated Revenue</th><th>Dates</th><th></th></tr></thead>
        <tbody>
          <?php if ($intercropRows): ?>
            <?php foreach ($intercropRows as $row): ?>
              <tr>
                <td><strong><?= e((string) $row['crop_name']) ?></strong><?php if (!empty($row['notes'])): ?><div class="fo-dt"><?= e((string) $row['notes']) ?></div><?php endif; ?></td>
                <td><?= e((string) $row['farm_name']) ?></td>
                <td><?= number_format((float) $row['area_hectares'], 2) ?> ha</td>
                <td><span class="fo-badge <?= (string) $row['status'] === 'needs_attention' ? 'high' : ((string) $row['status'] === 'fair' ? 'med' : '') ?>"><?= e($recordStatuses[(string) $row['status']] ?? status_label((string) $row['status'])) ?></span></td>
                <td><?= e(fo_money((float) $row['estimated_revenue'])) ?></td>
                <td><span class="fo-dt"><?= e((string) ($row['planting_date'] ?: 'Planting n/a')) ?> to <?= e((string) ($row['harvest_date'] ?: 'Harvest n/a')) ?></span></td>
                <td>
                  <form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this intercrop record?');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_intercrop">
                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                    <button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td>Maize</td><td><?= e($location) ?></td><td>0.60 ha</td><td><span class="fo-badge">Good</span></td><td>NGN 312,000</td><td>Prototype starter</td><td></td></tr>
            <tr><td>Cassava</td><td><?= e($location) ?></td><td>0.50 ha</td><td><span class="fo-badge med">Fair</span></td><td>NGN 245,000</td><td>Prototype starter</td><td></td></tr>
            <tr><td>Pineapple</td><td><?= e($location) ?></td><td>0.40 ha</td><td><span class="fo-badge">Good</span></td><td>NGN 418,000</td><td>Prototype starter</td><td></td></tr>
            <tr><td>Vegetables</td><td><?= e($location) ?></td><td>0.30 ha</td><td><span class="fo-badge">Good</span></td><td>NGN 270,000</td><td>Prototype starter</td><td></td></tr>
          <?php endif; ?>
          <tr style="font-weight:900;background:#F9FAFB"><td>Total</td><td></td><td><?= number_format($intercropArea ?: 1.80, 2) ?> ha</td><td></td><td><?= e(fo_money($intercropRevenue ?: 1245000.0)) ?></td><td></td><td></td></tr>
        </tbody>
      </table>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <div class="fo-row-main"><div class="fo-donut" data-label="<?= e(fo_money($intercropRevenue ?: 1245000.0)) ?>&#10;Est. Total"></div><div><strong>Revenue Bridge</strong><p class="muted">Intercrops provide pre-coconut cash flow before dwarf coconut yields begin.</p><div class="fo-dt"><?= $intercropRows ? 'Based on grower-entered intercrop records.' : 'Maize 25% / Cassava 20% / Pineapple 33% / Vegetables 21%' ?></div></div></div>
        <details class="fo-fold" open><summary>Planting & Harvest Calendar</summary><div class="fo-fold-body">
          <?php foreach ([['Maize',10,30,50,25],['Cassava',5,40,55,35],['Pineapple',15,35,60,30],['Vegetables',8,20,35,20]] as $row): ?>
            <div class="fo-calendar-row"><strong><?= e($row[0]) ?></strong><div class="fo-bar"><span style="left:<?= $row[1] ?>%;width:<?= $row[2] ?>%;background:var(--primary-green)"></span><span style="left:<?= $row[3] ?>%;width:<?= $row[4] ?>%;background:#F59E0B"></span></div></div>
          <?php endforeach; ?>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="livestock" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">4</span>Livestock Tracker</h3><a class="link" href="farm-profile.php#activity">View details</a></div>
      <details class="fo-fold" style="margin-bottom:18px">
        <summary><?= fo_icon('livestock') ?> Add Livestock Record</summary>
        <div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_livestock">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Animal Type</label><input name="animal_type" required placeholder="Goats, poultry, sheep"></div>
            <div><label>Breed</label><input name="breed" placeholder="Optional"></div>
            <div><label>Quantity</label><input name="quantity" type="number" min="0" required></div>
            <div><label>Health Status</label><select name="health_status"><?php foreach ($livestockStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Purpose</label><input name="purpose" placeholder="Meat, eggs, manure, breeding"></div>
            <div><label>Last Vaccination</label><input type="date" name="last_vaccination_date"></div>
            <div><label>Next Action</label><input type="date" name="next_action_date"></div>
            <div class="full"><label>Notes</label><textarea name="notes" placeholder="Feed, health, sale, mortality, or veterinary notes."></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Livestock</button></div>
          </form>
        </div>
      </details>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('livestock') ?></span><div class="lb">Total Animals</div><div class="vl"><?= $livestockTotal ?: 58 ?></div></div>
        <?php $topLivestock = array_slice($livestockTypes, 0, 3, true); ?>
        <?php foreach (($topLivestock ?: ['Goats' => 24, 'Poultry' => 29, 'Sheep/Cattle' => 5]) as $type => $qty): ?>
          <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('livestock') ?></span><div class="lb"><?= e((string) $type) ?></div><div class="vl"><?= (int) $qty ?></div></div>
        <?php endforeach; ?>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('warning') ?></span><div class="lb">Needs Attention</div><div class="vl" style="color:#EF4444"><?= fo_count($pdo, "SELECT COUNT(*) FROM farm_livestock_records WHERE user_id = ? AND health_status IN ('watch','treatment','vaccination_due')", [$userId]) ?></div><div class="st">Health watch</div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <details class="fo-fold" open><summary>Performance This Season</summary><div class="fo-fold-body fo-grid fo-g4">
          <div class="fo-metric"><div class="lb">Sales</div><div class="vl" style="font-size:16px;color:var(--primary-green)">NGN 356,000</div><div class="st">12 animals/birds</div></div>
          <div class="fo-metric"><div class="lb">Feed Cost</div><div class="vl" style="font-size:16px;color:#EF4444">NGN 128,600</div></div>
          <div class="fo-metric"><div class="lb">Vet & Health</div><div class="vl" style="font-size:16px;color:#F59E0B">NGN 35,200</div></div>
          <div class="fo-metric"><div class="lb">Net Cash Flow</div><div class="vl" style="font-size:16px;color:var(--primary-green)">NGN 192,200</div></div>
        </div></details>
        <details class="fo-fold" open><summary>Health & Recent Activity</summary><div class="fo-fold-body">
          <?php foreach ($livestockRows as $row): ?>
            <div class="fo-row">
              <div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('livestock') ?></span><div><div class="fo-nm"><?= e((string) $row['animal_type']) ?> <span class="fo-dt">x<?= (int) $row['quantity'] ?></span></div><div class="fo-dt"><?= e((string) $row['farm_name']) ?><?= $row['next_action_date'] ? ' / Next: ' . e((string) $row['next_action_date']) : '' ?></div></div></div>
              <div class="fo-actions"><span class="fo-badge <?= in_array((string) $row['health_status'], ['watch','treatment','vaccination_due'], true) ? 'med' : '' ?>"><?= e($livestockStatuses[(string) $row['health_status']] ?? status_label((string) $row['health_status'])) ?></span><form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this livestock record?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_livestock"><input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>"><button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button></form></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$livestockRows): ?>
            <div class="fo-alert"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('medical') ?></span><div><div class="fo-nm">PPR vaccination due</div><div class="fo-dt">18 goats require attention</div></div></div><span class="fo-badge med">Medium</span></div>
            <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('coins') ?></span><div><div class="fo-nm">Sold 6 broilers</div><div class="fo-dt">Recorded May 22, 2026</div></div></div><span class="fo-badge">Recorded</span></div>
            <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('flask') ?></span><div><div class="fo-nm">Purchased feed</div><div class="fo-dt">Grower mash</div></div></div><span class="fo-badge">Recorded</span></div>
          <?php endif; ?>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="hands" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">5</span>Farm Hands & Activity Log</h3><a class="link" href="farm-profile.php#hands">Manage hands</a></div>
      <details class="fo-fold" style="margin-bottom:18px">
        <summary><?= fo_icon('users') ?> Register Farm Hand</summary>
        <div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_farm_hand">
            <div><label>Farm Assignment</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Full Name</label><input name="full_name" required></div>
            <div><label>Phone</label><input name="phone"></div>
            <div><label>Email</label><input type="email" name="email"></div>
            <div><label>Engagement</label><select name="engagement_type"><?php foreach ($farmHandEngagements as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Farm Activity</label><select name="activity_category"><?php foreach ($farmHandActivities as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Skill Level</label><select name="skill_level"><option value="">Not specified</option><?php foreach ($farmHandSkills as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Status</label><select name="status"><?php foreach ($farmHandStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Gender</label><input name="gender"></div>
            <div><label>Start Date</label><input type="date" name="start_date"></div>
            <div><label>End Date</label><input type="date" name="end_date"></div>
            <div><label>Emergency Contact</label><input name="emergency_contact"></div>
            <div class="full"><label>Activity Notes</label><textarea name="activity_notes" placeholder="Examples: weeding crew lead, nursery specialist, harvest labour, processing consultant, tractor operator."></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Register Worker</button></div>
          </form>
        </div>
      </details>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic blue"><?= fo_icon('users') ?></span><div class="lb">Active Workers</div><div class="vl"><?= $farmHands ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('users') ?></span><div class="lb">Full-time</div><div class="vl"><?= $fullTime ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic teal"><?= fo_icon('users') ?></span><div class="lb">Part-time</div><div class="vl"><?= $partTime ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('calendar') ?></span><div class="lb">Seasonal</div><div class="vl"><?= $seasonal ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('report') ?></span><div class="lb">Consultants</div><div class="vl"><?= $consultants ?></div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <details class="fo-fold" open><summary>Workers</summary><div class="fo-fold-body">
          <?php foreach ($handRows as $hand): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('users') ?></span><div><div class="fo-nm"><?= e((string) $hand['full_name']) ?></div><div class="fo-dt"><?= e((string) $hand['farm_name']) ?> / <?= e($farmHandEngagements[(string) $hand['engagement_type']] ?? status_label((string) $hand['engagement_type'])) ?> / <?= e($farmHandActivities[(string) $hand['activity_category']] ?? status_label((string) $hand['activity_category'])) ?></div></div></div><span class="fo-badge"><?= e($farmHandStatuses[(string) $hand['status']] ?? status_label((string) $hand['status'])) ?></span></div><?php endforeach; ?>
          <?php if (!$handRows): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('users') ?></span><div><div class="fo-nm">No workers registered yet</div><div class="fo-dt">Open Register Farm Hand above when you need it.</div></div></div></div><?php endif; ?>
        </div></details>
        <details class="fo-fold" open><summary>Activity Records</summary><div class="fo-fold-body">
          <?php foreach (array_slice($activityRows, 0, 8) as $activity): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('activity') ?></span><div><div class="fo-nm"><?= e((string) $activity['title']) ?></div><div class="fo-dt"><?= e((string) $activity['farm_name']) ?> / <?= e($farmHandActivities[(string) $activity['activity_type']] ?? status_label((string) $activity['activity_type'])) ?><?= $activity['activity_date'] ? ' / ' . e((string) $activity['activity_date']) : '' ?><?= (float) $activity['cost'] > 0 ? ' / ' . e(fo_money((float) $activity['cost'])) : '' ?></div></div></div><div class="fo-actions"><span class="fo-badge med"><?= e($recordStatuses[(string) $activity['status']] ?? status_label((string) $activity['status'])) ?></span><form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this activity record?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="record_id" value="<?= (int) $activity['id'] ?>"><button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button></form></div></div><?php endforeach; ?>
          <?php if (!$activityRows): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('activity') ?></span><div><div class="fo-nm">No activity logged yet</div><div class="fo-dt">Use Quick Add Farm Activity in Overview.</div></div></div></div><?php endif; ?>
        </div></details>
      </div>
      <details class="fo-fold" style="margin-top:18px"><summary>Attendance & Wages</summary><div class="fo-fold-body fo-grid fo-g3">
        <div class="fo-metric"><div class="lb">Attendance Rate</div><div class="vl" style="color:var(--primary-green)">92%</div><div class="st">11/12 workers</div></div>
        <div class="fo-metric"><div class="lb">Wages Paid</div><div class="vl" style="font-size:17px">NGN 186,500</div><div class="st">This period</div></div>
        <div class="fo-metric"><div class="lb">Pending</div><div class="vl" style="font-size:17px;color:#F59E0B">NGN 24,000</div><div class="st">2 workers</div></div>
      </div></details>
    </div>
  </section>

  <details class="fo-fold" open>
    <summary>How am I doing? <span class="fo-badge">Performance Intelligence</span></summary>
    <div class="fo-fold-body fo-grid fo-g4">
      <div class="fo-card"><h3>Cashflow Before Coconut Yield</h3><p class="fo-badge">Strong</p><div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('coins') ?></span><div class="vl"><?= e(fo_money($cashflowNet)) ?></div><div class="st">Net cash flow YTD</div></div><a class="link" href="reports.php?report=finance">View cashflow report</a></div>
      <div class="fo-card"><h3>Labor Efficiency</h3><p class="fo-badge">Good</p><div class="fo-grid fo-g2"><div class="fo-metric"><div class="lb">Tasks Completed</div><div class="vl">78%</div></div><div class="fo-metric"><div class="lb">On-time</div><div class="vl">83%</div></div></div></div>
      <div class="fo-card"><h3>Farm Health</h3><p class="fo-badge med">Fair</p><div class="fo-grid fo-g2"><div class="fo-metric"><div class="lb">Healthy Blocks</div><div class="vl">7/9</div></div><div class="fo-metric"><div class="lb">Health Score</div><div class="vl" style="color:#F59E0B">72%</div></div></div></div>
      <div class="fo-card"><h3>Recommendations</h3><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('seedling') ?></span><div class="fo-dt">Apply organic mulch in coconut blocks.</div></div></div><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('activity') ?></span><div class="fo-dt">Rotate intercrops in cassava block.</div></div></div><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('medical') ?></span><div class="fo-dt">Vaccinate goats against PPR.</div></div></div></div>
    </div>
  </details>

  <section class="fo-footer">
    <div><strong>Every action creates value</strong><br><span class="muted">Every record becomes a report, advisory, payment, or task completion state.</span></div>
    <div class="fo-grid fo-g4" style="flex:1">
      <div class="fo-metric"><div class="vl">216</div><div class="st">Records Created</div></div>
      <div class="fo-metric"><div class="vl">48</div><div class="st">Tasks Completed</div></div>
      <div class="fo-metric"><div class="vl">7</div><div class="st">Advisories Received</div></div>
      <div class="fo-metric"><div class="vl" style="font-size:17px"><?= e(fo_money($cashflowNet)) ?></div><div class="st">Net Cash Flow</div></div>
    </div>
  </section>
</div>
<script>
(function () {
  const tabs = Array.from(document.querySelectorAll('[data-fo-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-fo-panel]'));
  function activate(key) {
    const chosen = panels.some((panel) => panel.dataset.foPanel === key) ? key : 'overview';
    tabs.forEach((tab) => tab.setAttribute('aria-selected', tab.dataset.foTab === chosen ? 'true' : 'false'));
    panels.forEach((panel) => { panel.hidden = panel.dataset.foPanel !== chosen; });
    try { localStorage.setItem('natcodev_farm_operations_tab', chosen); } catch (error) {}
  }
  tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.foTab || 'overview')));
  let initial = 'overview';
  try { initial = localStorage.getItem('natcodev_farm_operations_tab') || initial; } catch (error) {}
  if (window.location.hash) initial = window.location.hash.replace('#', '');
  activate(initial);
})();
</script>
<?php dashboard_page_end(); ?>
