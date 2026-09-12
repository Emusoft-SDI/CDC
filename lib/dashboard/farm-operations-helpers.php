<?php
declare(strict_types=1);

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
