<?php
declare(strict_types=1);

function profile_helpers_ensure_schema(PDO $pdo): void
{
    $optionalColumns = [
        'phone' => "VARCHAR(30) NULL",
        'location' => "VARCHAR(255) NULL",
        'profile_picture' => "VARCHAR(255) NULL",
        'notify_email' => "TINYINT(1) NOT NULL DEFAULT 1",
        'notify_whatsapp' => "TINYINT(1) NOT NULL DEFAULT 0",
        'notify_sms' => "TINYINT(1) NOT NULL DEFAULT 0",
        'dob' => "DATE NULL",
        'marital_status' => "VARCHAR(30) NULL",
        'family_size' => "INT NULL",
        'education_level' => "VARCHAR(50) NULL",
        'farming_experience_years' => "INT NULL",
        'farming_experience_rating' => "VARCHAR(40) NULL",
        'next_of_kin_name' => "VARCHAR(255) NULL",
        'next_of_kin_phone' => "VARCHAR(30) NULL",
        'next_of_kin_relationship' => "VARCHAR(80) NULL",
    ];
    foreach ($optionalColumns as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    
    $applicationColumns = [
        'climate_zone' => "VARCHAR(120) NULL",
        'topography' => "VARCHAR(120) NULL",
        'soil_type' => "VARCHAR(120) NULL",
        'water_source' => "VARCHAR(120) NULL",
        'irrigation_method' => "VARCHAR(120) NULL",
        'coconut_variety' => "VARCHAR(180) NULL",
        'intercrops' => "VARCHAR(255) NULL",
        'livestock_integration' => "VARCHAR(255) NULL",
        'land_ownership_status' => "VARCHAR(80) NULL",
        'land_title_details' => "VARCHAR(255) NULL",
        'current_farm_activities' => "TEXT NULL",
        'production_stage' => "VARCHAR(80) NULL",
        'estimated_tree_count' => "INT NULL",
        'annual_yield_estimate' => "VARCHAR(120) NULL",
        'farming_practices' => "TEXT NULL",
        'major_challenges' => "TEXT NULL",
        'support_needs' => "TEXT NULL",
        'market_channels' => "VARCHAR(255) NULL",
    ];
    foreach ($applicationColumns as $column => $definition) {
        app_add_column_if_missing($pdo, 'applications', $column, $definition);
    }
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS nigeria_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(100) NOT NULL UNIQUE,
            state_code VARCHAR(10) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS nigeria_lgas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lga_name VARCHAR(100) NOT NULL,
            state_id INT NOT NULL,
            UNIQUE KEY uniq_lga_state (lga_name, state_id),
            INDEX idx_lgas_state (state_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'nigeria_states');
    app_ensure_primary_auto_increment($pdo, 'nigeria_lgas');
    $stateSeed = [
        'AB' => 'Abia', 'AD' => 'Adamawa', 'AK' => 'Akwa Ibom', 'AN' => 'Anambra', 'BA' => 'Bauchi', 'BY' => 'Bayelsa',
        'BE' => 'Benue', 'BO' => 'Borno', 'CR' => 'Cross River', 'DE' => 'Delta', 'EB' => 'Ebonyi', 'ED' => 'Edo',
        'EK' => 'Ekiti', 'EN' => 'Enugu', 'FC' => 'Federal Capital Territory', 'GO' => 'Gombe', 'IM' => 'Imo',
        'JI' => 'Jigawa', 'KD' => 'Kaduna', 'KN' => 'Kano', 'KT' => 'Katsina', 'KE' => 'Kebbi', 'KO' => 'Kogi',
        'KW' => 'Kwara', 'LA' => 'Lagos', 'NA' => 'Nasarawa', 'NI' => 'Niger', 'OG' => 'Ogun', 'ON' => 'Ondo',
        'OS' => 'Osun', 'OY' => 'Oyo', 'PL' => 'Plateau', 'RI' => 'Rivers', 'SO' => 'Sokoto', 'TA' => 'Taraba',
        'YO' => 'Yobe', 'ZA' => 'Zamfara',
    ];
    $stateInsert = $pdo->prepare("INSERT IGNORE INTO nigeria_states (state_name, state_code) VALUES (?, ?)");
    foreach ($stateSeed as $code => $name) {
        $stateInsert->execute([$name, $code]);
    }
    $duplicateStates = $pdo->query("
        SELECT LOWER(TRIM(state_name)) AS state_key, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
        FROM nigeria_states
        GROUP BY LOWER(TRIM(state_name))
        HAVING COUNT(*) > 1
    ")->fetchAll();
    foreach ($duplicateStates as $duplicate) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $duplicate['ids']))));
        $keepId = (int) $duplicate['keep_id'];
        $removeIds = array_values(array_filter($ids, static fn (int $id): bool => $id !== $keepId));
        if (!$removeIds) {
            continue;
        }
    
        $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
        $pdo->prepare("UPDATE applications SET state_id = ? WHERE state_id IN ({$placeholders})")->execute(array_merge([$keepId], $removeIds));
        if (app_table_exists($pdo, 'grower_farms')) {
            $pdo->prepare("UPDATE grower_farms SET state_id = ? WHERE state_id IN ({$placeholders})")->execute(array_merge([$keepId], $removeIds));
        }
        $pdo->prepare("UPDATE nigeria_lgas SET state_id = ? WHERE state_id IN ({$placeholders})")->execute(array_merge([$keepId], $removeIds));
    
        $duplicateLgas = $pdo->prepare("
            SELECT LOWER(TRIM(lga_name)) AS lga_key, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
            FROM nigeria_lgas
            WHERE state_id = ?
            GROUP BY LOWER(TRIM(lga_name))
            HAVING COUNT(*) > 1
        ");
        $duplicateLgas->execute([$keepId]);
        foreach ($duplicateLgas->fetchAll() as $lgaDuplicate) {
            $lgaIds = array_values(array_filter(array_map('intval', explode(',', (string) $lgaDuplicate['ids']))));
            $keepLgaId = (int) $lgaDuplicate['keep_id'];
            $removeLgaIds = array_values(array_filter($lgaIds, static fn (int $id): bool => $id !== $keepLgaId));
            if (!$removeLgaIds) {
                continue;
            }
            $lgaPlaceholders = implode(',', array_fill(0, count($removeLgaIds), '?'));
            $pdo->prepare("UPDATE applications SET lga_id = ? WHERE lga_id IN ({$lgaPlaceholders})")->execute(array_merge([$keepLgaId], $removeLgaIds));
            if (app_table_exists($pdo, 'grower_farms')) {
                $pdo->prepare("UPDATE grower_farms SET lga_id = ? WHERE lga_id IN ({$lgaPlaceholders})")->execute(array_merge([$keepLgaId], $removeLgaIds));
            }
            $pdo->prepare("DELETE FROM nigeria_lgas WHERE id IN ({$lgaPlaceholders})")->execute($removeLgaIds);
        }
    
        $pdo->prepare("DELETE FROM nigeria_states WHERE id IN ({$placeholders})")->execute($removeIds);
    }
    $duplicateStateCodes = $pdo->query("
        SELECT UPPER(TRIM(state_code)) AS code_key, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
        FROM nigeria_states
        WHERE state_code IS NOT NULL AND TRIM(state_code) <> ''
        GROUP BY UPPER(TRIM(state_code))
        HAVING COUNT(*) > 1
    ")->fetchAll();
    foreach ($duplicateStateCodes as $duplicate) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $duplicate['ids']))));
        $keepId = (int) $duplicate['keep_id'];
        $removeIds = array_values(array_filter($ids, static fn (int $id): bool => $id !== $keepId));
        if (!$removeIds) {
            continue;
        }
    
        $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
        $pdo->prepare("UPDATE applications SET state_id = ? WHERE state_id IN ({$placeholders})")->execute(array_merge([$keepId], $removeIds));
        if (app_table_exists($pdo, 'grower_farms')) {
            $pdo->prepare("UPDATE grower_farms SET state_id = ? WHERE state_id IN ({$placeholders})")->execute(array_merge([$keepId], $removeIds));
        }
        $pdo->prepare("UPDATE nigeria_lgas SET state_id = ? WHERE state_id IN ({$placeholders})")->execute(array_merge([$keepId], $removeIds));
        $pdo->prepare("DELETE FROM nigeria_states WHERE id IN ({$placeholders})")->execute($removeIds);
    }
    try {
        $pdo->exec("ALTER TABLE nigeria_states ADD UNIQUE KEY uniq_nigeria_states_name (state_name)");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE nigeria_states ADD UNIQUE KEY uniq_nigeria_states_code (state_code)");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE nigeria_lgas ADD UNIQUE KEY uniq_nigeria_lgas_name_state (lga_name, state_id)");
    } catch (Throwable $e) {
    }
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
        'climate_zone' => "VARCHAR(120) NULL",
        'topography' => "VARCHAR(120) NULL",
        'soil_type' => "VARCHAR(120) NULL",
        'water_source' => "VARCHAR(120) NULL",
        'irrigation_method' => "VARCHAR(120) NULL",
        'coconut_variety' => "VARCHAR(180) NULL",
        'intercrops' => "VARCHAR(255) NULL",
        'livestock_integration' => "VARCHAR(255) NULL",
        'land_ownership_status' => "VARCHAR(80) NULL",
        'land_title_details' => "VARCHAR(255) NULL",
        'current_farm_activities' => "TEXT NULL",
        'production_stage' => "VARCHAR(80) NULL",
        'estimated_tree_count' => "INT NULL",
        'annual_yield_estimate' => "VARCHAR(120) NULL",
        'farming_practices' => "TEXT NULL",
        'major_challenges' => "TEXT NULL",
        'support_needs' => "TEXT NULL",
        'market_channels' => "VARCHAR(255) NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'grower_farms', $column, $definition);
    }
}

function profile_ensure_otp_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otp_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            otp_code VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp_sessions_user (user_id, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'otp_sessions');
    app_add_column_if_missing($pdo, 'otp_sessions', 'purpose', "VARCHAR(40) NOT NULL DEFAULT 'login'");
}

function profile_field_changed(array $user, string $field, mixed $newValue): bool
{
    $old = trim((string) ($user[$field] ?? ''));
    $new = trim((string) ($newValue ?? ''));
    return $old !== $new;
}

function profile_update_needs_otp(array $user, array $data): bool
{
    foreach (['phone', 'location', 'dob', 'marital_status', 'family_size', 'education_level', 'farming_experience_rating', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship'] as $field) {
        if (profile_field_changed($user, $field, $data[$field] ?? null)) {
            return true;
        }
    }

    return (int) ($user['farming_experience_years'] ?? 0) !== (int) ($data['farming_experience_years'] ?? 0)
        || (int) ($user['notify_email'] ?? 1) !== (int) $data['notify_email']
        || (int) ($user['notify_whatsapp'] ?? 0) !== (int) $data['notify_whatsapp']
        || (int) ($user['notify_sms'] ?? 0) !== (int) $data['notify_sms'];
}

function profile_send_update_otp(PDO $pdo, array $user): array
{
    profile_ensure_otp_schema($pdo);
    $code = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $pdo->prepare("INSERT INTO otp_sessions (user_id, otp_code, expires_at, purpose) VALUES (?, ?, ?, 'profile_update')")
        ->execute([(int) $user['id'], $code, $expires]);

    $body = "NATCODEV: Use {$code} to approve your profile update. This code expires in 10 minutes.";
    $sentChannels = [];
    $loggedChannels = [];
    $failedChannels = [];
    if (!empty($user['phone'])) {
        $smsTransport = strtolower((string) app_env('SMS_TRANSPORT', app_is_production() ? 'twilio' : 'log'));
        if (sendSMSMessage((string) $user['phone'], $body)) {
            $smsTransport === 'log' ? $loggedChannels[] = 'SMS' : $sentChannels[] = 'SMS';
        } else {
            $failedChannels[] = 'SMS';
        }
        $whatsappTransport = strtolower((string) app_env('WHATSAPP_TRANSPORT', app_is_production() ? 'twilio' : 'log'));
        if (sendWhatsAppMessage((string) $user['phone'], $body)) {
            $whatsappTransport === 'log' ? $loggedChannels[] = 'WhatsApp' : $sentChannels[] = 'WhatsApp';
        } else {
            $failedChannels[] = 'WhatsApp';
        }
    }
    if (!empty($user['email'])) {
        $mailTransport = strtolower((string) app_env('MAIL_TRANSPORT', app_is_production() ? 'mail' : 'log'));
        if (app_send_mail((string) $user['email'], 'NATCODEV Profile Update OTP', $body)) {
            $mailTransport === 'log' ? $loggedChannels[] = 'email' : $sentChannels[] = 'email';
        } else {
            $failedChannels[] = 'email';
        }
    }

    return [
        'code' => $code,
        'sent' => array_values(array_unique($sentChannels)),
        'logged' => array_values(array_unique($loggedChannels)),
        'failed' => array_values(array_unique($failedChannels)),
    ];
}

function profile_otp_delivery_message(array $otp): string
{
    $parts = [];
    if (!empty($otp['sent'])) {
        $parts[] = 'sent to ' . implode(', ', $otp['sent']);
    }
    if (!empty($otp['logged'])) {
        $parts[] = 'logged to ' . implode(', ', $otp['logged']);
    }
    if (!empty($otp['failed'])) {
        $parts[] = 'failed on ' . implode(', ', $otp['failed']);
    }

    return $parts ? implode('; ', $parts) : 'no configured delivery channel';
}

function profile_otp_production_warning(): string
{
    if (!app_is_production()) {
        return '';
    }

    $logging = [];
    foreach (['MAIL_TRANSPORT' => 'email', 'SMS_TRANSPORT' => 'SMS', 'WHATSAPP_TRANSPORT' => 'WhatsApp'] as $envKey => $label) {
        if (strtolower((string) app_env($envKey, $envKey === 'MAIL_TRANSPORT' ? 'mail' : 'twilio')) === 'log') {
            $logging[] = $label;
        }
    }

    return $logging ? 'Production warning: ' . implode(', ', $logging) . ' OTP delivery is still in log mode.' : '';
}

function profile_verify_update_otp(PDO $pdo, int $userId, string $code): bool
{
    profile_ensure_otp_schema($pdo);
    $stmt = $pdo->prepare("
        SELECT id
        FROM otp_sessions
        WHERE user_id = ? AND otp_code = ? AND purpose = 'profile_update' AND expires_at > NOW() AND used = 0
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $code]);
    $otpId = (int) ($stmt->fetchColumn() ?: 0);
    if ($otpId <= 0) {
        return false;
    }
    $pdo->prepare("UPDATE otp_sessions SET used = 1 WHERE id = ?")->execute([$otpId]);
    return true;
}

function profile_coconut_varieties(): array
{
    return [
        'West African Tall',
        'Malayan Dwarf',
        'Green Dwarf',
        'Yellow Dwarf',
        'Red Dwarf',
        'Sri Lankan Tall',
        'Tagnanan Tall',
        'Vanuatu Tall',
        'Maypan Hybrid',
        'PB121 Hybrid',
        'Local Tall',
        'Local Dwarf',
        'Mixed Varieties',
        'Not Sure',
        'Other / Local Variety',
    ];
}

function profile_recommended_inputs(string $field): array
{
    return match ($field) {
        'climate_zone' => [
            'Humid Tropical',
            'Coastal Humid',
            'Rainforest',
            'Derived Savanna',
            'Riverine / Floodplain',
            'Mangrove / Coastal Belt',
            'Not Sure',
        ],
        'topography' => [
            'Flat Lowland',
            'Gentle Slope',
            'Undulating Land',
            'Valley / Basin',
            'Riverbank',
            'Coastal Plain',
            'Hilly / Steep Slope',
            'Not Sure',
        ],
        'soil_type' => [
            'Sandy Loam',
            'Loamy Sand',
            'Clay Loam',
            'Alluvial Soil',
            'Lateritic Soil',
            'Peaty / Waterlogged Soil',
            'Mixed Soil',
            'Not Sure',
        ],
        'water_source' => [
            'Rainfed',
            'Borehole',
            'Well',
            'River / Stream',
            'Pond / Dam',
            'Irrigation Canal',
            'Community Water Supply',
            'None Yet',
        ],
        'irrigation_method' => [
            'None / Rainfed Only',
            'Manual Watering',
            'Drip Irrigation',
            'Sprinkler',
            'Furrow / Channel',
            'Flood Irrigation',
            'Pump and Hose',
            'Planned',
        ],
        'land_ownership_status' => [
            'Owned',
            'Leased',
            'Family Land',
            'Community Land',
            'Cooperative Land',
            'Government Allocation',
            'Caretaker / Managed Farm',
            'Other',
        ],
        'land_title_details' => [
            'Certificate of Occupancy',
            'Deed of Assignment',
            'Survey Plan',
            'Customary Right of Occupancy',
            'Family Allocation Letter',
            'Lease Agreement',
            'Community Allocation',
            'No Formal Title Yet',
            'Processing Documentation',
        ],
        'intercrops' => [
            'Cassava',
            'Plantain',
            'Banana',
            'Maize',
            'Yam',
            'Pineapple',
            'Cocoa',
            'Oil Palm',
            'Vegetables',
            'Legumes',
            'None',
        ],
        'livestock_integration' => [
            'None',
            'Poultry',
            'Goats',
            'Sheep',
            'Fishery',
            'Piggery',
            'Cattle',
            'Snail Farming',
            'Bee Keeping',
            'Mixed Livestock',
        ],
        'production_stage' => [
            'Planning',
            'Nursery',
            'Land Preparation',
            'Newly Planted',
            'Vegetative',
            'Fruiting',
            'Harvesting',
            'Processing',
            'Expansion',
        ],
        'annual_yield_estimate' => [
            'Not Producing Yet',
            'Below 500 nuts/year',
            '500 - 2,000 nuts/year',
            '2,000 - 5,000 nuts/year',
            '5,000 - 10,000 nuts/year',
            'Above 10,000 nuts/year',
            'Measured in tonnes',
            'Not Sure',
        ],
        'market_channels' => [
            'Local Market',
            'Farm Gate Buyers',
            'Processors',
            'Cooperative',
            'Wholesalers',
            'Retailers',
            'Export Aggregators',
            'Own Processing',
            'No Market Yet',
        ],
        default => [],
    };
}

function profile_value_is_selected(?string $current, string $option): bool
{
    $normalize = static fn (string $value): string => strtolower(str_replace(['_', '/', '-'], ' ', trim($value)));
    return $normalize((string) $current) === $normalize($option);
}

function profile_float_or_null(string $key): ?float
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        return null;
    }
    return is_numeric($value) ? (float) $value : null;
}

function profile_int_or_null(string $key): ?int
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return $value === '' ? null : max(0, (int) $value);
}

function profile_valid_state_id(PDO $pdo, ?int $stateId): ?int
{
    if (!$stateId) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, state_name, state_code FROM nigeria_states WHERE id = ? LIMIT 1");
    $stmt->execute([$stateId]);
    $state = $stmt->fetch();
    if (!$state) {
        return null;
    }
    nigeria_ensure_lgas_for_state($pdo, (int) $state['id'], (string) $state['state_name'], (string) ($state['state_code'] ?? ''));
    return (int) $state['id'];
}

function profile_valid_lga_id(PDO $pdo, ?int $lgaId, ?int $stateId): ?int
{
    if (!$lgaId || !$stateId) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id FROM nigeria_lgas WHERE id = ? AND state_id = ? LIMIT 1");
    $stmt->execute([$lgaId, $stateId]);
    return $stmt->fetchColumn() ? $lgaId : null;
}

