<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function fm_ensure_schema(PDO $pdo): void
{
    app_ensure_core_schema($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS nigeria_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(100) NOT NULL UNIQUE,
            state_code VARCHAR(10) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'nigeria_states');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS nigeria_lgas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lga_name VARCHAR(100) NOT NULL,
            state_id INT NOT NULL,
            UNIQUE KEY uniq_lga_state (lga_name, state_id),
            INDEX idx_lgas_state (state_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'nigeria_lgas');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grower_farms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            application_id INT NULL,
            farm_name VARCHAR(160) NOT NULL,
            farm_size DECIMAL(10,2) NULL,
            state_id INT NULL,
            lga_id INT NULL,
            street_address VARCHAR(255) NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_grower_farms_user (user_id),
            INDEX idx_grower_farms_application (application_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'grower_farms');
    foreach ([
        'application_id' => "INT NULL",
        'farm_name' => "VARCHAR(160) NOT NULL DEFAULT 'Farm'",
        'farm_size' => "DECIMAL(10,2) NULL",
        'state_id' => "INT NULL",
        'lga_id' => "INT NULL",
        'street_address' => "VARCHAR(255) NULL",
        'latitude' => "DECIMAL(10,7) NULL",
        'longitude' => "DECIMAL(10,7) NULL",
        'is_primary' => "TINYINT(1) NOT NULL DEFAULT 0",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'grower_farms', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            farm_id INT NOT NULL,
            requested_by INT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            system_confidence_score DECIMAL(5,2) NULL,
            system_notes TEXT NULL,
            admin_decision VARCHAR(30) NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            rejection_reason TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_farm_verification_farm (farm_id),
            INDEX idx_farm_verifications_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'farm_verifications');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS field_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            farm_id INT NOT NULL,
            assigned_to INT NULL,
            task_type VARCHAR(40) NOT NULL DEFAULT 'verification',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            due_date DATE NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_field_tasks_agent (assigned_to, status),
            INDEX idx_field_tasks_farm (farm_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'field_tasks');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            farm_id INT NOT NULL,
            task_id INT NULL,
            agent_id INT NOT NULL,
            visit_latitude DECIMAL(10,7) NULL,
            visit_longitude DECIMAL(10,7) NULL,
            distance_from_submitted_location_m DECIMAL(12,2) NULL,
            photos TEXT NULL,
            notes TEXT NULL,
            result VARCHAR(30) NOT NULL DEFAULT 'submitted',
            visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_farm_visits_farm (farm_id),
            INDEX idx_farm_visits_agent (agent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'farm_visits');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_weather_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            farm_id INT NOT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            temperature_c DECIMAL(5,2) NULL,
            rainfall_mm DECIMAL(8,2) NULL,
            humidity_percent DECIMAL(5,2) NULL,
            wind_kph DECIMAL(6,2) NULL,
            provider VARCHAR(60) NOT NULL DEFAULT 'local_estimate',
            summary VARCHAR(255) NULL,
            captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_weather_farm_time (farm_id, captured_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'farm_weather_snapshots');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_boundaries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            farm_id INT NOT NULL UNIQUE,
            polygon_geojson LONGTEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'farm_boundaries');

    fm_seed_missing_verifications($pdo);
}

function fm_seed_missing_verifications(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'grower_farms')) {
        return;
    }
    $pdo->exec("
        INSERT IGNORE INTO farm_verifications (farm_id, requested_by, status, system_confidence_score, system_notes)
        SELECT id, user_id, 'pending', NULL, 'Awaiting coordinate and administrative verification.'
        FROM grower_farms
    ");
}

function fm_coordinate_score(?float $lat, ?float $lng, ?int $stateId = null, ?int $lgaId = null): array
{
    if ($lat === null || $lng === null) {
        return [0.0, 'No latitude/longitude submitted.'];
    }
    if ($lat < 4.0 || $lat > 14.5 || $lng < 2.5 || $lng > 15.2) {
        return [15.0, 'Coordinates are outside the expected Nigeria boundary.'];
    }

    $score = 72.0;
    $notes = ['Coordinates are inside the expected Nigeria boundary.'];
    if ($stateId) {
        $score += 10.0;
        $notes[] = 'State selected.';
    }
    if ($lgaId) {
        $score += 8.0;
        $notes[] = 'Local government selected.';
    }

    return [min(95.0, $score), implode(' ', $notes)];
}

function fm_haversine_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function fm_weather_estimate(PDO $pdo, int $farmId, ?float $lat, ?float $lng): array
{
    $latest = $pdo->prepare("SELECT * FROM farm_weather_snapshots WHERE farm_id = ? ORDER BY captured_at DESC LIMIT 1");
    $latest->execute([$farmId]);
    $row = $latest->fetch();
    if ($row && strtotime((string) $row['captured_at']) >= strtotime('-6 hours')) {
        return $row;
    }

    $seed = abs((int) round(($lat ?? 7.5) * 1000) + (int) round(($lng ?? 4.0) * 1000) + (int) date('z'));
    $temperature = 25 + ($seed % 9);
    $humidity = 55 + ($seed % 35);
    $rainfall = ($seed % 12) / 2;
    $wind = 4 + ($seed % 16);
    $summary = $rainfall >= 3 ? 'Rain risk. Check drainage and access routes.' : 'Fair field conditions. Monitor young seedlings.';

    $stmt = $pdo->prepare("
        INSERT INTO farm_weather_snapshots
            (farm_id, latitude, longitude, temperature_c, rainfall_mm, humidity_percent, wind_kph, provider, summary)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'local_estimate', ?)
    ");
    $stmt->execute([$farmId, $lat, $lng, $temperature, $rainfall, $humidity, $wind, $summary]);

    return [
        'farm_id' => $farmId,
        'latitude' => $lat,
        'longitude' => $lng,
        'temperature_c' => $temperature,
        'rainfall_mm' => $rainfall,
        'humidity_percent' => $humidity,
        'wind_kph' => $wind,
        'provider' => 'local_estimate',
        'summary' => $summary,
        'captured_at' => date('Y-m-d H:i:s'),
    ];
}
