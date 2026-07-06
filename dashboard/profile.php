<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/twilio.php';
require_once __DIR__ . '/../lib/nigeria-locations.php';

$pdo = db();
admin_ensure_schema($pdo);
fm_ensure_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$profileCurrentUser = current_user($pdo);
if (!$profileCurrentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $profileCurrentUser);
$message = '';
$error = '';
$otpRequired = false;

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
$pdo->exec("
    CREATE TABLE IF NOT EXISTS farm_hands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grower_id INT NOT NULL,
        farm_id INT NULL,
        full_name VARCHAR(160) NOT NULL,
        phone VARCHAR(40) NULL,
        email VARCHAR(190) NULL,
        gender VARCHAR(30) NULL,
        engagement_type VARCHAR(40) NOT NULL DEFAULT 'part_time',
        activity_category VARCHAR(80) NOT NULL DEFAULT 'general_farm_work',
        activity_notes TEXT NULL,
        skill_level VARCHAR(40) NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        emergency_contact VARCHAR(120) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_farm_hands_grower (grower_id),
        INDEX idx_farm_hands_farm (farm_id),
        INDEX idx_farm_hands_activity (activity_category),
        INDEX idx_farm_hands_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'farm_hands');

$stmt = $pdo->prepare("
    SELECT u.*,
           a.app_ref, a.farm_size, a.state_id, a.lga_id, a.street_address, a.latitude, a.longitude, a.whatsapp,
           a.climate_zone, a.topography, a.soil_type, a.water_source, a.irrigation_method,
           a.coconut_variety, a.intercrops, a.livestock_integration,
           a.land_ownership_status, a.land_title_details, a.current_farm_activities,
           a.production_stage, a.estimated_tree_count, a.annual_yield_estimate,
           a.farming_practices, a.major_challenges, a.support_needs, a.market_channels,
           sp.staff_type, sp.qualification, sp.license_number, sp.experience_years, sp.certification_status, sp.training_program, sp.availability, sp.status staff_status
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    redirect_to('login.php');
}

if (($user['role'] ?? 'grower') === 'grower' && (int) ($user['application_id'] ?? 0) > 0) {
    $primaryFarm = $pdo->prepare("SELECT id FROM grower_farms WHERE user_id = ? AND application_id = ? LIMIT 1");
    $primaryFarm->execute([$userId, (int) $user['application_id']]);
    if (!$primaryFarm->fetchColumn()) {
        $pdo->prepare("
            INSERT INTO grower_farms
                (user_id, application_id, farm_name, farm_size, state_id, lga_id, street_address, latitude, longitude, is_primary,
                 climate_zone, topography, soil_type, water_source, irrigation_method, coconut_variety, intercrops, livestock_integration,
                 land_ownership_status, land_title_details, current_farm_activities, production_stage, estimated_tree_count,
                 annual_yield_estimate, farming_practices, major_challenges, support_needs, market_channels)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $userId,
            (int) $user['application_id'],
            'Primary Farm',
            $user['farm_size'] ?? null,
            $user['state_id'] ?? null,
            $user['lga_id'] ?? null,
            $user['street_address'] ?? null,
            $user['latitude'] ?? null,
            $user['longitude'] ?? null,
            $user['climate_zone'] ?? null,
            $user['topography'] ?? null,
            $user['soil_type'] ?? null,
            $user['water_source'] ?? null,
            $user['irrigation_method'] ?? null,
            $user['coconut_variety'] ?? null,
            $user['intercrops'] ?? null,
            $user['livestock_integration'] ?? null,
            $user['land_ownership_status'] ?? null,
            $user['land_title_details'] ?? null,
            $user['current_farm_activities'] ?? null,
            $user['production_stage'] ?? null,
            $user['estimated_tree_count'] ?? null,
            $user['annual_yield_estimate'] ?? null,
            $user['farming_practices'] ?? null,
            $user['major_challenges'] ?? null,
            $user['support_needs'] ?? null,
            $user['market_channels'] ?? null,
        ]);
    }
}

$states = $pdo->query("SELECT id, state_name, state_code FROM nigeria_states ORDER BY state_name")->fetchAll();
$farmStmt = $pdo->prepare("
    SELECT gf.*, s.state_name, l.lga_name, COALESCE(fv.status, 'pending') verification_status, fv.system_confidence_score, fv.system_notes
    FROM grower_farms gf
    LEFT JOIN nigeria_states s ON s.id = gf.state_id
    LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    WHERE gf.user_id = ?
    ORDER BY gf.is_primary DESC, gf.created_at ASC, gf.id ASC
");
$farmStmt->execute([$userId]);
$growerFarms = $farmStmt->fetchAll();

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
$farmHandStatuses = [
    'active' => 'Active',
    'paused' => 'Paused',
    'completed' => 'Completed',
    'inactive' => 'Inactive',
];
$farmHandSkills = [
    'trainee' => 'Trainee',
    'basic' => 'Basic',
    'skilled' => 'Skilled',
    'supervisor' => 'Supervisor',
    'specialist' => 'Specialist',
];
$farmHandsStmt = $pdo->prepare("
    SELECT fh.*, gf.farm_name
    FROM farm_hands fh
    LEFT JOIN grower_farms gf ON gf.id = fh.farm_id AND gf.user_id = fh.grower_id
    WHERE fh.grower_id = ?
    ORDER BY FIELD(fh.status, 'active', 'paused', 'completed', 'inactive'), fh.full_name
");
$farmHandsStmt->execute([$userId]);
$farmHands = $farmHandsStmt->fetchAll();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save_profile');
        if ($action === 'update_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if ($current === '' || $new === '' || $confirm === '') {
                $error = 'All password fields are required.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $passwordStmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $passwordStmt->execute([$userId]);
                $hash = (string) $passwordStmt->fetchColumn();
                if ($hash !== '' && password_verify($current, $hash)) {
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([
                        password_hash($new, PASSWORD_DEFAULT),
                        $userId,
                    ]);
                    $message = 'Password updated successfully.';
                } else {
                    $error = 'Current password is incorrect.';
                }
            }
            goto profile_post_done;
        }

        if ($action === 'send_profile_otp') {
            $otp = profile_send_update_otp($pdo, $user);
            $message = 'OTP ' . profile_otp_delivery_message($otp) . '. It expires in 10 minutes.';
            goto profile_post_done;
        }

        if ($action === 'save_farm_hand' && ($user['role'] ?? 'grower') === 'grower') {
            $farmHandId = (int) ($_POST['farm_hand_id'] ?? 0);
            $farmId = (int) ($_POST['farm_id'] ?? 0) ?: null;
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $gender = trim((string) ($_POST['gender'] ?? ''));
            $engagementType = array_key_exists((string) ($_POST['engagement_type'] ?? ''), $farmHandEngagements)
                ? (string) $_POST['engagement_type']
                : 'part_time';
            $activityCategory = array_key_exists((string) ($_POST['activity_category'] ?? ''), $farmHandActivities)
                ? (string) $_POST['activity_category']
                : 'general_farm_work';
            $skillLevel = array_key_exists((string) ($_POST['skill_level'] ?? ''), $farmHandSkills)
                ? (string) $_POST['skill_level']
                : null;
            $status = array_key_exists((string) ($_POST['status'] ?? ''), $farmHandStatuses)
                ? (string) $_POST['status']
                : 'active';
            $startDate = trim((string) ($_POST['start_date'] ?? '')) ?: null;
            $endDate = trim((string) ($_POST['end_date'] ?? '')) ?: null;

            if ($fullName === '') {
                $error = 'Farm hand name is required.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid farm hand email or leave it blank.';
            } elseif ($farmId !== null && !in_array($farmId, array_map(static fn (array $farm): int => (int) $farm['id'], $growerFarms), true)) {
                $error = 'Select one of your registered farms for this farm hand.';
            } elseif ($farmHandId > 0) {
                $ownership = $pdo->prepare("SELECT id FROM farm_hands WHERE id = ? AND grower_id = ? LIMIT 1");
                $ownership->execute([$farmHandId, $userId]);
                if (!$ownership->fetchColumn()) {
                    $error = 'Farm hand record not found.';
                } else {
                    $pdo->prepare("
                        UPDATE farm_hands
                        SET farm_id = ?, full_name = ?, phone = ?, email = ?, gender = ?, engagement_type = ?,
                            activity_category = ?, activity_notes = ?, skill_level = ?, start_date = ?, end_date = ?,
                            status = ?, emergency_contact = ?
                        WHERE id = ? AND grower_id = ?
                    ")->execute([
                        $farmId,
                        $fullName,
                        $phone,
                        $email ?: null,
                        $gender,
                        $engagementType,
                        $activityCategory,
                        trim((string) ($_POST['activity_notes'] ?? '')),
                        $skillLevel,
                        $startDate,
                        $endDate,
                        $status,
                        trim((string) ($_POST['emergency_contact'] ?? '')),
                        $farmHandId,
                        $userId,
                    ]);
                    $message = 'Farm hand updated successfully.';
                }
            } else {
                $pdo->prepare("
                    INSERT INTO farm_hands
                        (grower_id, farm_id, full_name, phone, email, gender, engagement_type, activity_category,
                         activity_notes, skill_level, start_date, end_date, status, emergency_contact)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $userId,
                    $farmId,
                    $fullName,
                    $phone,
                    $email ?: null,
                    $gender,
                    $engagementType,
                    $activityCategory,
                    trim((string) ($_POST['activity_notes'] ?? '')),
                    $skillLevel,
                    $startDate,
                    $endDate,
                    $status,
                    trim((string) ($_POST['emergency_contact'] ?? '')),
                ]);
                $message = 'Farm hand registered successfully.';
            }

            $farmHandsStmt = $pdo->prepare("
                SELECT fh.*, gf.farm_name
                FROM farm_hands fh
                LEFT JOIN grower_farms gf ON gf.id = fh.farm_id AND gf.user_id = fh.grower_id
                WHERE fh.grower_id = ?
                ORDER BY FIELD(fh.status, 'active', 'paused', 'completed', 'inactive'), fh.full_name
            ");
            $farmHandsStmt->execute([$userId]);
            $farmHands = $farmHandsStmt->fetchAll();
            goto profile_post_done;
        }

        if (($action === 'add_farm' || $action === 'update_farm') && ($user['role'] ?? 'grower') === 'grower') {
            $farmId = (int) ($_POST['farm_id'] ?? 0);
            $stateId = profile_valid_state_id($pdo, filter_input(INPUT_POST, 'farm_state_id', FILTER_VALIDATE_INT) ?: null);
            $lgaId = profile_valid_lga_id($pdo, filter_input(INPUT_POST, 'farm_lga_id', FILTER_VALIDATE_INT) ?: null, $stateId);
            $farmName = trim((string) ($_POST['farm_name'] ?? ''));
            $farmSize = profile_float_or_null('farm_record_size');
            $latitude = profile_float_or_null('farm_latitude');
            $longitude = profile_float_or_null('farm_longitude');
            if ($farmName === '') {
                $farmName = $action === 'add_farm' ? 'Additional Farm' : 'Farm';
            }
            if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
                $error = 'Latitude must be between -90 and 90.';
            } elseif ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
                $error = 'Longitude must be between -180 and 180.';
            } elseif ($action === 'add_farm') {
                $pdo->prepare("
                    INSERT INTO grower_farms
                        (user_id, farm_name, farm_size, state_id, lga_id, street_address, latitude, longitude,
                         climate_zone, topography, soil_type, water_source, irrigation_method, coconut_variety, intercrops, livestock_integration,
                         land_ownership_status, land_title_details, current_farm_activities, production_stage, estimated_tree_count,
                         annual_yield_estimate, farming_practices, major_challenges, support_needs, market_channels)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $userId,
                    $farmName,
                    $farmSize,
                    $stateId,
                    $lgaId,
                    trim((string) ($_POST['farm_street_address'] ?? '')),
                    $latitude,
                    $longitude,
                    trim((string) ($_POST['climate_zone'] ?? '')),
                    trim((string) ($_POST['topography'] ?? '')),
                    trim((string) ($_POST['soil_type'] ?? '')),
                    trim((string) ($_POST['water_source'] ?? '')),
                    trim((string) ($_POST['irrigation_method'] ?? '')),
                    trim((string) ($_POST['coconut_variety'] ?? '')),
                    trim((string) ($_POST['intercrops'] ?? '')),
                    trim((string) ($_POST['livestock_integration'] ?? '')),
                    trim((string) ($_POST['land_ownership_status'] ?? '')),
                    trim((string) ($_POST['land_title_details'] ?? '')),
                    trim((string) ($_POST['current_farm_activities'] ?? '')),
                    trim((string) ($_POST['production_stage'] ?? '')),
                    profile_int_or_null('estimated_tree_count'),
                    trim((string) ($_POST['annual_yield_estimate'] ?? '')),
                    trim((string) ($_POST['farming_practices'] ?? '')),
                    trim((string) ($_POST['major_challenges'] ?? '')),
                    trim((string) ($_POST['support_needs'] ?? '')),
                    trim((string) ($_POST['market_channels'] ?? '')),
                ]);
                $message = 'Farm added successfully.';
            } else {
                $ownership = $pdo->prepare("SELECT id, is_primary, application_id FROM grower_farms WHERE id = ? AND user_id = ? LIMIT 1");
                $ownership->execute([$farmId, $userId]);
                $farm = $ownership->fetch();
                if (!$farm) {
                    $error = 'Farm record not found.';
                } else {
                    $pdo->prepare("
                        UPDATE grower_farms
                        SET farm_name = ?, farm_size = ?, state_id = ?, lga_id = ?, street_address = ?, latitude = ?, longitude = ?,
                            climate_zone = ?, topography = ?, soil_type = ?, water_source = ?, irrigation_method = ?,
                            coconut_variety = ?, intercrops = ?, livestock_integration = ?, land_ownership_status = ?, land_title_details = ?,
                            current_farm_activities = ?, production_stage = ?, estimated_tree_count = ?, annual_yield_estimate = ?,
                            farming_practices = ?, major_challenges = ?, support_needs = ?, market_channels = ?
                        WHERE id = ? AND user_id = ?
                    ")->execute([
                        $farmName,
                        $farmSize,
                        $stateId,
                        $lgaId,
                        trim((string) ($_POST['farm_street_address'] ?? '')),
                        $latitude,
                        $longitude,
                        trim((string) ($_POST['climate_zone'] ?? '')),
                        trim((string) ($_POST['topography'] ?? '')),
                        trim((string) ($_POST['soil_type'] ?? '')),
                        trim((string) ($_POST['water_source'] ?? '')),
                        trim((string) ($_POST['irrigation_method'] ?? '')),
                        trim((string) ($_POST['coconut_variety'] ?? '')),
                        trim((string) ($_POST['intercrops'] ?? '')),
                        trim((string) ($_POST['livestock_integration'] ?? '')),
                        trim((string) ($_POST['land_ownership_status'] ?? '')),
                        trim((string) ($_POST['land_title_details'] ?? '')),
                        trim((string) ($_POST['current_farm_activities'] ?? '')),
                        trim((string) ($_POST['production_stage'] ?? '')),
                        profile_int_or_null('estimated_tree_count'),
                        trim((string) ($_POST['annual_yield_estimate'] ?? '')),
                        trim((string) ($_POST['farming_practices'] ?? '')),
                        trim((string) ($_POST['major_challenges'] ?? '')),
                        trim((string) ($_POST['support_needs'] ?? '')),
                        trim((string) ($_POST['market_channels'] ?? '')),
                        $farmId,
                        $userId,
                    ]);
                    if ((int) ($farm['is_primary'] ?? 0) === 1 && (int) ($farm['application_id'] ?? 0) > 0) {
                        $pdo->prepare("
                            UPDATE applications
                            SET farm_size = COALESCE(?, farm_size), state_id = ?, lga_id = ?, street_address = ?, latitude = ?, longitude = ?
                            WHERE id = ?
                        ")->execute([$farmSize, $stateId, $lgaId, trim((string) ($_POST['farm_street_address'] ?? '')), $latitude, $longitude, (int) $farm['application_id']]);
                    }
                    $message = 'Farm updated successfully.';
                }
            }
        }

        if ($action === 'add_farm' || $action === 'update_farm') {
            $farmStmt = $pdo->prepare("
                SELECT gf.*, s.state_name, l.lga_name, COALESCE(fv.status, 'pending') verification_status, fv.system_confidence_score, fv.system_notes
                FROM grower_farms gf
                LEFT JOIN nigeria_states s ON s.id = gf.state_id
                LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
                LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
                WHERE gf.user_id = ?
                ORDER BY gf.is_primary DESC, gf.created_at ASC, gf.id ASC
            ");
            $farmStmt->execute([$userId]);
            $growerFarms = $farmStmt->fetchAll();
            goto profile_post_done;
        }

        $phone = trim((string) ($_POST['phone'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $dob = trim((string) ($_POST['dob'] ?? '')) ?: null;
        $maritalStatus = trim((string) ($_POST['marital_status'] ?? '')) ?: null;
        $familySize = $_POST['family_size'] === '' ? null : max(0, (int) $_POST['family_size']);
        $education = trim((string) ($_POST['education_level'] ?? '')) ?: null;
        $experience = $_POST['farming_experience_years'] === '' ? null : max(0, (int) $_POST['farming_experience_years']);
        $experienceRating = trim((string) ($_POST['farming_experience_rating'] ?? '')) ?: null;
        $kinName = trim((string) ($_POST['next_of_kin_name'] ?? ''));
        $kinPhone = trim((string) ($_POST['next_of_kin_phone'] ?? ''));
        $kinRelationship = trim((string) ($_POST['next_of_kin_relationship'] ?? ''));
        $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
        $notifyWhatsapp = isset($_POST['notify_whatsapp']) ? 1 : 0;
        $notifySms = isset($_POST['notify_sms']) ? 1 : 0;
        $applicationData = [
            'farm_size' => ($_POST['farm_size'] ?? '') === '' ? null : max(0, (float) $_POST['farm_size']),
            'state_id' => profile_valid_state_id($pdo, filter_input(INPUT_POST, 'state_id', FILTER_VALIDATE_INT) ?: null),
            'latitude' => profile_float_or_null('latitude'),
            'longitude' => profile_float_or_null('longitude'),
            'street_address' => trim((string) ($_POST['street_address'] ?? '')),
            'whatsapp' => trim((string) ($_POST['whatsapp'] ?? '')),
            'climate_zone' => trim((string) ($_POST['climate_zone'] ?? '')),
            'topography' => trim((string) ($_POST['topography'] ?? '')),
            'soil_type' => trim((string) ($_POST['soil_type'] ?? '')),
            'water_source' => trim((string) ($_POST['water_source'] ?? '')),
            'irrigation_method' => trim((string) ($_POST['irrigation_method'] ?? '')),
            'coconut_variety' => trim((string) ($_POST['coconut_variety'] ?? '')),
            'intercrops' => trim((string) ($_POST['intercrops'] ?? '')),
            'livestock_integration' => trim((string) ($_POST['livestock_integration'] ?? '')),
            'land_ownership_status' => trim((string) ($_POST['land_ownership_status'] ?? '')),
            'land_title_details' => trim((string) ($_POST['land_title_details'] ?? '')),
            'current_farm_activities' => trim((string) ($_POST['current_farm_activities'] ?? '')),
            'production_stage' => trim((string) ($_POST['production_stage'] ?? '')),
            'estimated_tree_count' => ($_POST['estimated_tree_count'] ?? '') === '' ? null : max(0, (int) $_POST['estimated_tree_count']),
            'annual_yield_estimate' => trim((string) ($_POST['annual_yield_estimate'] ?? '')),
            'farming_practices' => trim((string) ($_POST['farming_practices'] ?? '')),
            'major_challenges' => trim((string) ($_POST['major_challenges'] ?? '')),
            'support_needs' => trim((string) ($_POST['support_needs'] ?? '')),
            'market_channels' => trim((string) ($_POST['market_channels'] ?? '')),
        ];
        $applicationData['lga_id'] = profile_valid_lga_id($pdo, filter_input(INPUT_POST, 'lga_id', FILTER_VALIDATE_INT) ?: null, $applicationData['state_id']);
        $profilePicture = null;
        $profileData = [
            'phone' => $phone,
            'location' => $location,
            'dob' => $dob,
            'marital_status' => $maritalStatus,
            'family_size' => $familySize,
            'education_level' => $education,
            'farming_experience_years' => $experience,
            'farming_experience_rating' => $experienceRating,
            'next_of_kin_name' => $kinName,
            'next_of_kin_phone' => $kinPhone,
            'next_of_kin_relationship' => $kinRelationship,
            'notify_email' => $notifyEmail,
            'notify_whatsapp' => $notifyWhatsapp,
            'notify_sms' => $notifySms,
        ];

        $needsOtp = profile_update_needs_otp($user, $profileData);
        $otpCode = preg_replace('/[^0-9]/', '', (string) ($_POST['otp_code'] ?? ''));

        if ($needsOtp && $otpCode === '') {
            $otp = profile_send_update_otp($pdo, $user);
            $otpRequired = true;
            $message = 'Security code ' . profile_otp_delivery_message($otp) . '. Enter the OTP to save these profile changes.';
        } elseif ($needsOtp && !profile_verify_update_otp($pdo, $userId, $otpCode)) {
            $otpRequired = true;
            $error = 'Invalid or expired OTP. Request a new code and try again.';
        }

        if (!$otpRequired && $error === '' && !empty($_FILES['profile_picture']['name'])) {
            try {
                $upload = app_uploaded_file_info((array) ($_FILES['profile_picture'] ?? []), ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024, 'Profile picture');
                $uploadDir = dirname(__DIR__) . '/uploads/profile-pictures';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = app_safe_upload_name('user_' . $userId, $upload['name'], $upload['extension']);
                if (move_uploaded_file($upload['tmp_name'], $uploadDir . '/' . $fileName)) {
                    $profilePicture = 'uploads/profile-pictures/' . $fileName;
                } else {
                    $error = 'Unable to upload profile picture.';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        if (!$otpRequired && $error === '') {
            $pictureSql = $profilePicture ? ', profile_picture = ?' : '';
            $stmt = $pdo->prepare("
                UPDATE users
                SET phone = ?, location = ?, notify_email = ?, notify_whatsapp = ?, notify_sms = ?,
                    dob = ?, marital_status = ?, family_size = ?, education_level = ?, farming_experience_years = ?, farming_experience_rating = ?,
                    next_of_kin_name = ?, next_of_kin_phone = ?, next_of_kin_relationship = ?
                    {$pictureSql}
                WHERE id = ?
            ");
            $params = [
                $phone,
                $location,
                $notifyEmail,
                $notifyWhatsapp,
                $notifySms,
                $dob,
                $maritalStatus,
                $familySize,
                $education,
                $experience,
                $experienceRating,
                $kinName,
                $kinPhone,
                $kinRelationship,
            ];
            if ($profilePicture) {
                $params[] = $profilePicture;
            }
            $params[] = $userId;
            $stmt->execute($params);

            if ((int) ($user['application_id'] ?? 0) > 0) {
                $appStmt = $pdo->prepare("
                    UPDATE applications
                    SET farm_size = COALESCE(?, farm_size),
                        state_id = ?, lga_id = ?, street_address = ?, latitude = ?, longitude = ?, whatsapp = ?,
                        climate_zone = ?, topography = ?, soil_type = ?, water_source = ?, irrigation_method = ?,
                        coconut_variety = ?, intercrops = ?, livestock_integration = ?,
                        land_ownership_status = ?, land_title_details = ?,
                        current_farm_activities = ?, production_stage = ?, estimated_tree_count = ?,
                        annual_yield_estimate = ?, farming_practices = ?, major_challenges = ?, support_needs = ?, market_channels = ?
                    WHERE id = ?
                ");
                $appStmt->execute([
                    $applicationData['farm_size'],
                    $applicationData['state_id'],
                    $applicationData['lga_id'],
                    $applicationData['street_address'],
                    $applicationData['latitude'],
                    $applicationData['longitude'],
                    $applicationData['whatsapp'],
                    $applicationData['climate_zone'],
                    $applicationData['topography'],
                    $applicationData['soil_type'],
                    $applicationData['water_source'],
                    $applicationData['irrigation_method'],
                    $applicationData['coconut_variety'],
                    $applicationData['intercrops'],
                    $applicationData['livestock_integration'],
                    $applicationData['land_ownership_status'],
                    $applicationData['land_title_details'],
                    $applicationData['current_farm_activities'],
                    $applicationData['production_stage'],
                    $applicationData['estimated_tree_count'],
                    $applicationData['annual_yield_estimate'],
                    $applicationData['farming_practices'],
                    $applicationData['major_challenges'],
                    $applicationData['support_needs'],
                    $applicationData['market_channels'],
                    (int) $user['application_id'],
                ]);
                $pdo->prepare("
                    UPDATE grower_farms
                    SET farm_size = ?, state_id = ?, lga_id = ?, street_address = ?, latitude = ?, longitude = ?,
                        climate_zone = ?, topography = ?, soil_type = ?, water_source = ?, irrigation_method = ?,
                        coconut_variety = ?, intercrops = ?, livestock_integration = ?, land_ownership_status = ?, land_title_details = ?,
                        current_farm_activities = ?, production_stage = ?, estimated_tree_count = ?, annual_yield_estimate = ?,
                        farming_practices = ?, major_challenges = ?, support_needs = ?, market_channels = ?
                    WHERE user_id = ? AND application_id = ?
                ")->execute([
                    $applicationData['farm_size'],
                    $applicationData['state_id'],
                    $applicationData['lga_id'],
                    $applicationData['street_address'],
                    $applicationData['latitude'],
                    $applicationData['longitude'],
                    $applicationData['climate_zone'],
                    $applicationData['topography'],
                    $applicationData['soil_type'],
                    $applicationData['water_source'],
                    $applicationData['irrigation_method'],
                    $applicationData['coconut_variety'],
                    $applicationData['intercrops'],
                    $applicationData['livestock_integration'],
                    $applicationData['land_ownership_status'],
                    $applicationData['land_title_details'],
                    $applicationData['current_farm_activities'],
                    $applicationData['production_stage'],
                    $applicationData['estimated_tree_count'],
                    $applicationData['annual_yield_estimate'],
                    $applicationData['farming_practices'],
                    $applicationData['major_challenges'],
                    $applicationData['support_needs'],
                    $applicationData['market_channels'],
                    $userId,
                    (int) $user['application_id'],
                ]);
            }
            $message = 'Profile updated successfully.';
            $stmt = $pdo->prepare("
                SELECT u.*,
                       a.app_ref, a.farm_size, a.state_id, a.lga_id, a.street_address, a.latitude, a.longitude, a.whatsapp,
                       a.climate_zone, a.topography, a.soil_type, a.water_source, a.irrigation_method,
                       a.coconut_variety, a.intercrops, a.livestock_integration,
                       a.land_ownership_status, a.land_title_details, a.current_farm_activities,
                       a.production_stage, a.estimated_tree_count, a.annual_yield_estimate,
                       a.farming_practices, a.major_challenges, a.support_needs, a.market_channels,
                       sp.staff_type, sp.qualification, sp.license_number, sp.experience_years, sp.certification_status, sp.training_program, sp.availability, sp.status staff_status
                FROM users u
                LEFT JOIN applications a ON a.id = u.application_id
                LEFT JOIN staff_profiles sp ON sp.user_id = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch() ?: $user;
        }
    }
}
profile_post_done:
?>
<?php
$profilePageMode = defined('DASHBOARD_PROFILE_MODE') ? (string) DASHBOARD_PROFILE_MODE : 'profile';
$profilePageMode = in_array($profilePageMode, ['profile', 'account', 'farm'], true) ? $profilePageMode : 'profile';
$profileAllowedTabs = match ($profilePageMode) {
    'account' => ['security', 'password', 'notifications'],
    'farm' => ['farm', 'activity', 'locations', 'hands'],
    default => ['personal'],
};
$profileDefaultTab = $profileAllowedTabs[0] ?? 'personal';
$profilePageTitle = match ($profilePageMode) {
    'account' => 'Account Settings',
    'farm' => 'Farm Profile',
    default => 'Edit Profile',
};
$profilePageDescription = match ($profilePageMode) {
    'account' => 'Manage account security, password, and notification preferences.',
    'farm' => 'Manage primary farm, farm activity, farm locations, and farm hands.',
    default => 'Update the profile details used for verification, support, and field engagement.',
};
$profileActivePage = match ($profilePageMode) {
    'farm' => 'farm-profile.php',
    'account' => 'account-settings.php',
    default => 'profile.php',
};
dashboard_page_start($profilePageTitle, ['active' => $profileActivePage, 'description' => $profilePageDescription, 'wide' => true]);
$profileForm = $otpRequired && isset($profileData) ? array_merge($user, $profileData, $applicationData ?? []) : $user;
$primaryGrowerFarm = $growerFarms[0] ?? [];
$profilePicture = ltrim((string) ($profileForm['profile_picture'] ?? ''), '/');
$profilePictureUrl = $profilePicture !== '' ? '../' . $profilePicture : '';
$growerName = (string) ($profileForm['name'] ?? 'Grower');
$memberId = (string) ($profileForm['app_ref'] ?? 'Not linked');
$farmSize = (string) ($primaryGrowerFarm['farm_size'] ?? $profileForm['farm_size'] ?? '');
$coconutType = (string) ($primaryGrowerFarm['coconut_variety'] ?? $profileForm['coconut_variety'] ?? '');
$cooperative = (string) ($profileForm['cooperative_name'] ?? $profileForm['organization'] ?? '');
$farmState = (string) ($primaryGrowerFarm['state_name'] ?? 'State pending');
$farmLga = (string) ($primaryGrowerFarm['lga_name'] ?? 'LGA pending');
$farmCommunity = (string) ($primaryGrowerFarm['street_address'] ?? $profileForm['location'] ?? 'Community pending');
$farmGps = trim((string) ($primaryGrowerFarm['latitude'] ?? '') . ', ' . (string) ($primaryGrowerFarm['longitude'] ?? ''), ' ,');
$treeCount = (int) ($primaryGrowerFarm['estimated_tree_count'] ?? $profileForm['estimated_tree_count'] ?? 0);
$intercrops = (string) ($primaryGrowerFarm['intercrops'] ?? $profileForm['intercrops'] ?? '');
$livestock = (string) ($primaryGrowerFarm['livestock_integration'] ?? $profileForm['livestock_integration'] ?? '');
$activeFarmHands = count(array_filter($farmHands, static fn(array $hand): bool => (string) ($hand['status'] ?? '') === 'active'));
$verificationStatus = (string) ($primaryGrowerFarm['verification_status'] ?? 'pending');
?>
<style>
  .profile-summary-board { display:grid; gap:16px; margin-bottom:16px; }
  .profile-summary-row { display:grid; grid-template-columns:minmax(320px,1.35fr) repeat(3,minmax(180px,.55fr)); gap:14px; }
  .profile-identity-card, .profile-mini-card, .farm-location-card, .farm-overview-card {
    background:#fff; border:1px solid var(--line); border-radius:8px; box-shadow:var(--shadow); padding:16px;
  }
  .profile-identity-card { display:flex; gap:16px; align-items:center; min-height:136px; }
  .profile-identity-card img, .profile-avatar-fallback { width:76px; height:76px; border-radius:50%; object-fit:cover; border:2px solid #dfe8d8; background:#edf6e8; flex:0 0 auto; }
  .profile-avatar-fallback { display:grid; place-items:center; color:var(--green); font-weight:900; font-size:1.6rem; }
  .profile-identity-card h2, .profile-mini-card h3, .farm-location-card h2, .farm-overview-card h2 { margin:0 0 8px; color:var(--green); }
  .profile-identity-card p, .profile-mini-card p, .farm-location-card p, .farm-overview-card p { margin:0; color:var(--muted); line-height:1.45; }
  .profile-mini-card strong { display:block; color:var(--ink); font-size:1.35rem; line-height:1.15; margin:6px 0 12px; }
  .profile-summary-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
  .profile-summary-actions button, .profile-summary-actions a { min-height:36px; padding:8px 11px; }
  .farm-location-grid, .farm-overview-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
  .farm-location-item, .farm-overview-item { border:1px solid var(--line); background:#fbfdfb; border-radius:8px; padding:12px; min-height:92px; }
  .farm-location-item small, .farm-overview-item small { display:block; color:var(--muted); font-weight:800; margin-bottom:6px; }
  .farm-location-item strong, .farm-overview-item strong { color:var(--ink); overflow-wrap:anywhere; }
  .farm-overview-item p { margin-top:10px; }
  .farm-overview-item button { box-shadow:none !important; }
  .profile-outcome-note { border:1px solid #efd285; background:#fff8e5; color:#5f4500; border-radius:8px; padding:12px; }
  .profile-section { margin-top:18px; padding:18px; background:#fff; border:1px solid var(--line); border-radius:8px; box-shadow:var(--shadow); }
  .profile-section h2 { margin:0 0 6px; color:var(--green); font-size:1.15rem; }
  .profile-section .hint { margin:0 0 14px; color:var(--muted); line-height:1.45; }
  .profile-section textarea { min-height:96px; }
  .profile-section select, .profile-section input, .profile-section textarea { background:#fff; }
  .profile-tabs { position:sticky; top:86px; z-index:12; display:flex; flex-wrap:wrap; gap:8px; margin:16px 0 12px; padding:10px; background:rgba(245,248,243,.95); border:1px solid var(--line); border-radius:8px; backdrop-filter:blur(8px); }
  .profile-tab { border:1px solid transparent; background:#fff; color:var(--green); box-shadow:none; min-height:42px; }
  .profile-tab[aria-selected="true"] { background:var(--green); color:#fff; border-color:var(--green); }
    .profile-tab-panel[hidden] { display:none; }
  .profile-route-links { display:flex; flex-wrap:wrap; gap:10px; margin:0 0 14px; }
  .profile-route-link { display:inline-flex; align-items:center; gap:8px; min-height:40px; padding:9px 12px; border:1px solid var(--line); border-radius:9px; background:#fff; color:var(--green); font-weight:900; }
  .profile-route-link.active { background:var(--green); color:#fff; border-color:var(--green); }
  .profile-actions { position:sticky; bottom:0; z-index:12; display:flex; justify-content:flex-end; margin-top:16px; padding:12px 0; background:linear-gradient(180deg, rgba(245,248,243,0), var(--bg) 28%); }
  .profile-section h3 { margin:0 0 12px; color:var(--green); }
  .farm-hand-list { display:grid; gap:12px; margin-bottom:18px; }
  .farm-hand-card { border:1px solid var(--line); border-radius:8px; padding:14px; background:#fff; }
  .farm-hand-card summary { cursor:pointer; color:var(--green); font-weight:900; }
  .farm-hand-meta { display:flex; flex-wrap:wrap; gap:8px; margin:10px 0; }
  @media(max-width:1100px){.profile-summary-row{grid-template-columns:1fr 1fr}.farm-location-grid,.farm-overview-grid{grid-template-columns:1fr 1fr}}
  @media(max-width:700px){.profile-tabs{position:static}.profile-tab{flex:1 1 calc(50% - 8px)}.profile-summary-row,.farm-location-grid,.farm-overview-grid{grid-template-columns:1fr}.profile-identity-card{align-items:flex-start}}
</style>
    <?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <?php if (profile_otp_production_warning() !== ''): ?><p class="error"><?= e(profile_otp_production_warning()) ?></p><?php endif; ?>
    <?php if ($otpRequired): ?><p class="notice pending">Enter the OTP sent to your current phone/email, then submit again. Re-select profile picture only after OTP is accepted.</p><?php endif; ?>
    <?php if (($user['role'] ?? 'grower') === 'grower'): ?>
      <section class="profile-summary-board" aria-label="Grower profile and farm profile summary">
        <div class="profile-summary-row">
          <article class="profile-identity-card">
            <?php if ($profilePictureUrl !== ''): ?>
              <img src="<?= e($profilePictureUrl) ?>" alt="<?= e($growerName) ?> profile picture">
            <?php else: ?>
              <span class="profile-avatar-fallback"><?= e(strtoupper(substr($growerName, 0, 1))) ?></span>
            <?php endif; ?>
            <div>
              <h2><?= e($growerName) ?></h2>
              <p>Member ID: <?= e($memberId) ?></p>
              <p>Member since: <?= !empty($profileForm['created_at']) ? e(date('M j, Y', strtotime((string) $profileForm['created_at']))) : 'Not available' ?></p>
              <p><?= ntv_badge($verificationStatus, ntv_status_label($verificationStatus)) ?></p>
              <div class="profile-summary-actions">
                <button type="button" class="button secondary" data-profile-jump="personal">View / Edit Profile</button>
              </div>
            </div>
          </article>
          <article class="profile-mini-card">
            <h3>Farm Size</h3>
            <strong><?= $farmSize !== '' ? e($farmSize) . ' Acres' : 'Not set' ?></strong>
            <p>Total declared production area.</p>
            <div class="profile-summary-actions"><button type="button" class="button secondary" data-profile-jump="locations">View details</button></div>
          </article>
          <article class="profile-mini-card">
            <h3>Coconut Type</h3>
            <strong><?= $coconutType !== '' ? e($coconutType) : 'Not set' ?></strong>
            <p>Preferred coconut variety for field planning.</p>
            <div class="profile-summary-actions"><button type="button" class="button secondary" data-profile-jump="activity">View details</button></div>
          </article>
          <article class="profile-mini-card">
            <h3>Cooperative</h3>
            <strong><?= $cooperative !== '' ? e($cooperative) : 'Not linked' ?></strong>
            <p>Grower group or cooperative membership.</p>
            <div class="profile-summary-actions"><button type="button" class="button secondary" data-profile-jump="personal">View details</button></div>
          </article>
        </div>

        <article class="farm-location-card">
          <div class="ntv-section-head">
            <div>
              <h2>Farm Location</h2>
              <p>State, LGA, community, and GPS coordinates used for verification, field work, weather, and reporting.</p>
            </div>
            <a class="button secondary" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($farmGps !== '' ? $farmGps : $farmCommunity . ', ' . $farmLga . ', ' . $farmState . ', Nigeria') ?>" target="_blank" rel="noopener">View on Map</a>
          </div>
          <div class="farm-location-grid">
            <div class="farm-location-item"><small>State</small><strong><?= e($farmState) ?></strong></div>
            <div class="farm-location-item"><small>LGA</small><strong><?= e($farmLga) ?></strong></div>
            <div class="farm-location-item"><small>Community</small><strong><?= e($farmCommunity) ?></strong></div>
            <div class="farm-location-item"><small>GPS Coordinate</small><strong><?= $farmGps !== '' ? e($farmGps) : 'Not captured' ?></strong></div>
          </div>
        </article>

        <article class="farm-overview-card">
          <div class="ntv-section-head">
            <div>
              <h2>Farm Overview</h2>
              <p>Dwarf coconut establishment, intercrops, livestock, and farm hands summarized before deep editing.</p>
            </div>
            <button type="button" class="button secondary" data-profile-jump="farm">Edit Farm Profile</button>
          </div>
          <div class="farm-overview-grid">
            <div class="farm-overview-item"><small>Coconut Trees</small><strong><?= $treeCount > 0 ? e((string) $treeCount) : 'Not set' ?></strong><p><a href="fields.php">View details</a></p></div>
            <div class="farm-overview-item"><small>Intercrops</small><strong><?= $intercrops !== '' ? e($intercrops) : 'Not set' ?></strong><p><button type="button" class="button secondary" data-profile-jump="activity">View details</button></p></div>
            <div class="farm-overview-item"><small>Livestock</small><strong><?= $livestock !== '' ? e($livestock) : 'Not set' ?></strong><p><button type="button" class="button secondary" data-profile-jump="activity">View details</button></p></div>
            <div class="farm-overview-item"><small>Farm Hands</small><strong><?= (int) $activeFarmHands ?> active</strong><p><button type="button" class="button secondary" data-profile-jump="hands">View details</button></p></div>
          </div>
        </article>
        <div class="profile-outcome-note">Screen outcome: updating profile, farm location, farm activity, or farm hands saves a record that supports verification, reporting, farm operations, Academy eligibility, and certificate readiness.</div>
      </section>
    <?php endif; ?>
    <nav class="profile-route-links" aria-label="Profile workspaces">
      <a class="profile-route-link <?= $profilePageMode === 'profile' ? 'active' : '' ?>" href="profile.php">Edit Profile</a>
      <a class="profile-route-link <?= $profilePageMode === 'account' ? 'active' : '' ?>" href="account-settings.php">Account Settings</a>
      <?php if (($user['role'] ?? 'grower') === 'grower'): ?><a class="profile-route-link <?= $profilePageMode === 'farm' ? 'active' : '' ?>" href="farm-profile.php">Farm Profile</a><?php endif; ?>
    </nav>
    <nav class="profile-tabs" aria-label="Profile sections">
      <?php if (in_array('personal', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="personal" aria-selected="true">Profile Edit</button><?php endif; ?>
      <?php if (in_array('security', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="security">Security</button><?php endif; ?>
      <?php if (in_array('password', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="password">Password</button><?php endif; ?>
      <?php if (($user['role'] ?? 'grower') === 'grower'): ?>
        <?php if (in_array('farm', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="farm">Primary Farm</button><?php endif; ?>
        <?php if (in_array('activity', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="activity">Farm Activity</button><?php endif; ?>
        <?php if (in_array('locations', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="locations">Farm Locations</button><?php endif; ?>
        <?php if (in_array('hands', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="hands">Farm Hands</button><?php endif; ?>
      <?php endif; ?>
      <?php if (in_array('notifications', $profileAllowedTabs, true)): ?><button type="button" class="profile-tab" data-profile-tab="notifications">Notification Preferences</button><?php endif; ?>
    </nav>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_profile">
      <section class="profile-section profile-tab-panel" data-profile-panel="personal">
        <h2>Edit Profile</h2>
        <p class="hint">Update the profile details used for verification, support, and field engagement.</p>
        <div class="grid">
        <div><label>Profile Picture</label><input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp"></div>
        <div><label>Phone</label><input type="tel" name="phone" value="<?= e($profileForm['phone'] ?? '') ?>"></div>
        <div><label>Location</label><input type="text" name="location" value="<?= e($profileForm['location'] ?? '') ?>"></div>
        <div><label>Date of Birth</label><input type="date" name="dob" value="<?= e($profileForm['dob'] ?? '') ?>"></div>
        <div>
          <label>Marital Status</label>
          <select name="marital_status">
            <option value="">Select</option>
            <?php foreach (['single','married','divorced','widowed'] as $status): ?>
              <option value="<?= e($status) ?>" <?= ($profileForm['marital_status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucwords($status)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Family Size</label><input type="number" min="0" name="family_size" value="<?= e((string) ($profileForm['family_size'] ?? '')) ?>"></div>
        <div>
          <label>Education Level</label>
          <select name="education_level">
            <option value="">Select</option>
            <?php foreach (['none','primary','secondary','tertiary','post_graduate'] as $level): ?>
              <option value="<?= e($level) ?>" <?= ($profileForm['education_level'] ?? '') === $level ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $level))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Farming Experience (Years)</label><input type="number" min="0" name="farming_experience_years" value="<?= e((string) ($profileForm['farming_experience_years'] ?? '')) ?>"></div>
        <div>
          <label>Experience Level</label>
          <select name="farming_experience_rating">
            <option value="">Select</option>
            <?php foreach (['beginner','intermediate','advanced','expert'] as $level): ?>
              <option value="<?= e($level) ?>" <?= ($profileForm['farming_experience_rating'] ?? '') === $level ? 'selected' : '' ?>><?= e(ucwords($level)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Next of Kin Name</label><input type="text" name="next_of_kin_name" value="<?= e($profileForm['next_of_kin_name'] ?? '') ?>"></div>
        <div><label>Next of Kin Phone</label><input type="tel" name="next_of_kin_phone" value="<?= e($profileForm['next_of_kin_phone'] ?? '') ?>"></div>
        <div><label>Relationship</label><input type="text" name="next_of_kin_relationship" value="<?= e($profileForm['next_of_kin_relationship'] ?? '') ?>"></div>
        </div>
      </section>

      <section class="profile-section profile-tab-panel" data-profile-panel="security" hidden>
        <h2>Security</h2>
        <p class="hint">Review the protections on your account and complete verification steps that improve trust and recovery.</p>
        <div class="grid">
          <div class="panel">
            <h3>Profile Verification</h3>
            <p><span class="badge <?= (int) ($profileForm['profile_verified'] ?? 0) === 1 ? 'verified' : 'pending' ?>"><?= (int) ($profileForm['profile_verified'] ?? 0) === 1 ? 'Verified' : 'Pending review' ?></span></p>
            <p class="muted">Verified profiles are easier for NATCODEV teams to support and validate.</p>
            <a class="button secondary" href="documents.php">Open Verification Documents</a>
          </div>
          <div class="panel">
            <h3>Phone Verification</h3>
            <p><strong><?= e($profileForm['phone'] ?? 'No phone number saved') ?></strong></p>
            <p class="muted">Use phone verification for recovery, alerts, and support workflows.</p>
            <a class="button secondary" href="verify-phone.php">Verify Phone</a>
          </div>
          <div class="panel">
            <h3>Critical Change OTP</h3>
            <p class="muted">Changing phone, location, next of kin, or notification channels may require a one-time security code.</p>
            <label>OTP for Critical Changes</label>
            <input type="text" name="otp_code" inputmode="numeric" maxlength="6" placeholder="<?= $otpRequired ? 'Enter 6-digit OTP' : 'Required only after OTP is requested' ?>">
            <button type="submit" form="send-profile-otp-form" class="secondary">Send OTP</button>
          </div>
        </div>
      </section>

      <?php if (($user['role'] ?? 'grower') === 'grower'): ?>
      <section class="profile-section profile-tab-panel" data-profile-panel="farm" hidden>
        <h2>Farm Information</h2>
        <p class="hint">Capture where the farm is, how large it is, and the main production conditions field teams should understand.</p>
        <?php if (empty($user['application_id'])): ?><p class="notice pending">This account is not linked to a registration application yet, so farm information cannot be saved.</p><?php endif; ?>
        <div class="grid">
          <div><label>Application Reference</label><input value="<?= e($profileForm['app_ref'] ?? 'Not linked') ?>" disabled></div>
          <div><label>Farm Size (Hectares)</label><input type="number" min="0" step="0.01" name="farm_size" value="<?= e((string) ($profileForm['farm_size'] ?? '')) ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div>
            <label>Farm State</label>
            <select name="state_id" data-lga-target="primary_lga_id" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select state</option>
              <?php foreach ($states as $state): ?>
                <option value="<?= (int) $state['id'] ?>" <?= (int) ($profileForm['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>><?= e($state['state_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Farm Local Government</label>
            <select id="primary_lga_id" name="lga_id" data-selected="<?= (int) ($profileForm['lga_id'] ?? 0) ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select LGA</option>
            </select>
          </div>
          <div><label>Farm Address / Community</label><input name="street_address" value="<?= e($profileForm['street_address'] ?? '') ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Latitude</label><input id="primary_latitude" name="latitude" inputmode="decimal" value="<?= e((string) ($profileForm['latitude'] ?? '')) ?>" placeholder="e.g. 6.5244" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Longitude</label><input id="primary_longitude" name="longitude" inputmode="decimal" value="<?= e((string) ($profileForm['longitude'] ?? '')) ?>" placeholder="e.g. 3.3792" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Google Maps Link</label><input id="primary_maps_url" placeholder="Paste Google Maps link" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Extract From Link</label><button type="button" class="button secondary" data-map-extract="primary" <?= empty($user['application_id']) ? 'disabled' : '' ?>>Extract Latitude / Longitude</button></div>
          <div><label>Pick Farm Coordinates</label><button type="button" class="button secondary" data-location-fill="primary" <?= empty($user['application_id']) ? 'disabled' : '' ?>>Use My Current Location</button> <button type="button" class="button secondary" data-map-search="primary" <?= empty($user['application_id']) ? 'disabled' : '' ?>>Search Address On Map</button></div>
          <div><label>WhatsApp</label><input type="tel" name="whatsapp" value="<?= e($profileForm['whatsapp'] ?? '') ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div>
            <label>Climate Zone</label>
            <select name="climate_zone" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select climate zone</option>
              <?php foreach (profile_recommended_inputs('climate_zone') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['climate_zone'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Topography</label>
            <select name="topography" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select topography</option>
              <?php foreach (profile_recommended_inputs('topography') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['topography'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Soil Type</label>
            <select name="soil_type" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select soil type</option>
              <?php foreach (profile_recommended_inputs('soil_type') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['soil_type'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Water Source</label>
            <select name="water_source" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select water source</option>
              <?php foreach (profile_recommended_inputs('water_source') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['water_source'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Irrigation Method</label>
            <select name="irrigation_method" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select irrigation method</option>
              <?php foreach (profile_recommended_inputs('irrigation_method') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['irrigation_method'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Land Ownership Status</label>
            <select name="land_ownership_status" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select land ownership</option>
              <?php foreach (profile_recommended_inputs('land_ownership_status') as $status): ?>
                <option value="<?= e($status) ?>" <?= profile_value_is_selected($profileForm['land_ownership_status'] ?? '', $status) ? 'selected' : '' ?>><?= e($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Land Title Details</label><input list="land_title_options" name="land_title_details" value="<?= e($profileForm['land_title_details'] ?? '') ?>" placeholder="Select or type title details" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
        </div>
      </section>

      <section class="profile-section profile-tab-panel" data-profile-panel="activity" hidden>
        <h2>What You Are Doing On The Farm</h2>
        <p class="hint">This helps NATCODEV understand the grower’s coconut varieties, intercrops, livestock integration, stage of production, and support needs.</p>
        <div class="grid">
          <div>
            <label>Coconut Variety Cultivated</label>
            <select name="coconut_variety" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select coconut variety</option>
              <?php foreach (profile_coconut_varieties() as $variety): ?>
                <option value="<?= e($variety) ?>" <?= ($profileForm['coconut_variety'] ?? '') === $variety ? 'selected' : '' ?>><?= e($variety) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Intercrops (if any)</label><input list="intercrop_options" name="intercrops" value="<?= e($profileForm['intercrops'] ?? '') ?>" placeholder="Select or type crops, comma separated" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Poultry / Livestock Integration</label><input list="livestock_options" name="livestock_integration" value="<?= e($profileForm['livestock_integration'] ?? '') ?>" placeholder="Select or type livestock integration" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div>
            <label>Production Stage</label>
            <select name="production_stage" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select production stage</option>
              <?php foreach (profile_recommended_inputs('production_stage') as $stage): ?>
                <option value="<?= e($stage) ?>" <?= profile_value_is_selected($profileForm['production_stage'] ?? '', $stage) ? 'selected' : '' ?>><?= e($stage) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Estimated Coconut Trees</label><input type="number" min="0" name="estimated_tree_count" value="<?= e((string) ($profileForm['estimated_tree_count'] ?? '')) ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Annual Yield Estimate</label><input list="yield_options" name="annual_yield_estimate" value="<?= e($profileForm['annual_yield_estimate'] ?? '') ?>" placeholder="Select or type yield estimate" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Market Channels</label><input list="market_options" name="market_channels" value="<?= e($profileForm['market_channels'] ?? '') ?>" placeholder="Select or type market channels" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Current Farm Activities</label><textarea name="current_farm_activities" placeholder="Examples: nursery raising, land clearing, transplanting, weeding, mulching, harvesting, processing, farm expansion" <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['current_farm_activities'] ?? '') ?></textarea></div>
          <div><label>Farm Practices / Certification</label><textarea name="farming_practices" placeholder="Organic practices, fertilizer use, pest control, GAP, certificates..." <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['farming_practices'] ?? '') ?></textarea></div>
          <div><label>Major Challenges</label><textarea name="major_challenges" placeholder="Inputs, finance, pests, labour, seedlings, market access..." <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['major_challenges'] ?? '') ?></textarea></div>
          <div><label>Support Needed</label><textarea name="support_needs" placeholder="Training, seedlings, finance, extension visits, certification..." <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['support_needs'] ?? '') ?></textarea></div>
        </div>
        <?php foreach ([
            'land_title_options' => 'land_title_details',
            'intercrop_options' => 'intercrops',
            'livestock_options' => 'livestock_integration',
            'yield_options' => 'annual_yield_estimate',
            'market_options' => 'market_channels',
        ] as $listId => $source): ?>
          <datalist id="<?= e($listId) ?>">
            <?php foreach (profile_recommended_inputs($source) as $option): ?>
              <option value="<?= e($option) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <section class="profile-section profile-tab-panel" data-profile-panel="notifications" hidden>
        <h2>Notification Preferences</h2>
      <label class="check"><input type="checkbox" name="notify_email" <?= (int) ($profileForm['notify_email'] ?? 1) === 1 ? 'checked' : '' ?>> Email notifications</label><br>
      <label class="check"><input type="checkbox" name="notify_whatsapp" <?= (int) ($profileForm['notify_whatsapp'] ?? 0) === 1 ? 'checked' : '' ?>> WhatsApp notifications</label><br>
      <label class="check"><input type="checkbox" name="notify_sms" <?= (int) ($profileForm['notify_sms'] ?? 0) === 1 ? 'checked' : '' ?>> SMS notifications</label><br><br>
      </section>
      <div class="profile-actions" data-profile-save-actions><button type="submit">Save Profile</button></div>
    </form>
    <form id="send-profile-otp-form" method="post" hidden>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="send_profile_otp">
    </form>
    <section class="profile-section profile-tab-panel" data-profile-panel="password" hidden>
      <h2>Password</h2>
      <p class="hint">Change your password regularly and use at least 8 characters with a mix of letters, numbers, and symbols.</p>
      <form method="post" class="panel">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update_password">
        <div class="grid">
          <div><label>Current Password</label><span class="password-field"><input id="profile_current_password" type="password" name="current_password" required autocomplete="current-password"><button class="password-toggle" type="button" data-target="profile_current_password" aria-pressed="false">Show</button></span></div>
          <div><label>New Password</label><span class="password-field"><input id="profile_new_password" type="password" name="new_password" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-target="profile_new_password" aria-pressed="false">Show</button></span></div>
          <div><label>Confirm New Password</label><span class="password-field"><input id="profile_confirm_password" type="password" name="confirm_password" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-target="profile_confirm_password" aria-pressed="false">Show</button></span></div>
        </div>
        <button type="submit">Update Password</button>
      </form>
    </section>
    <?php if (($user['role'] ?? 'grower') === 'grower'): ?>
      <section class="profile-section profile-tab-panel" data-profile-panel="locations" hidden>
        <h2>Farm Locations</h2>
        <p class="hint">A grower can have more than one farm. Add each farm separately with State, LGA, address, and GPS coordinates.</p>
        <div class="grid">
          <?php foreach ($growerFarms as $farm): ?>
            <form method="post" class="panel">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_farm">
              <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
              <h3><?= e($farm['farm_name']) ?><?= (int) $farm['is_primary'] === 1 ? ' (Primary)' : '' ?></h3>
              <p><span class="badge <?= e((string) ($farm['verification_status'] ?? 'pending')) ?>"><?= e(ucwords(str_replace('_', ' ', (string) ($farm['verification_status'] ?? 'pending')))) ?></span>
              <?php if ($farm['system_confidence_score'] !== null): ?><span class="muted">System confidence: <?= e(number_format((float) $farm['system_confidence_score'], 1)) ?>%</span><?php endif; ?></p>
              <?php if (!empty($farm['system_notes'])): ?><p class="muted"><?= e((string) $farm['system_notes']) ?></p><?php endif; ?>
              <div class="grid">
                <div><label>Farm Name</label><input name="farm_name" value="<?= e($farm['farm_name']) ?>"></div>
                <div><label>Farm Size (Hectares)</label><input type="number" min="0" step="0.01" name="farm_record_size" value="<?= e((string) ($farm['farm_size'] ?? '')) ?>"></div>
                <div>
                  <label>State</label>
                  <select name="farm_state_id" data-lga-target="farm_lga_<?= (int) $farm['id'] ?>">
                    <option value="">Select state</option>
                    <?php foreach ($states as $state): ?>
                      <option value="<?= (int) $state['id'] ?>" <?= (int) ($farm['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>><?= e($state['state_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Local Government</label>
                  <select id="farm_lga_<?= (int) $farm['id'] ?>" name="farm_lga_id" data-selected="<?= (int) ($farm['lga_id'] ?? 0) ?>">
                    <option value="">Select LGA</option>
                  </select>
                </div>
                <div><label>Farm Address / Community</label><input name="farm_street_address" value="<?= e($farm['street_address'] ?? '') ?>"></div>
                <div><label>Latitude</label><input id="farm_latitude_<?= (int) $farm['id'] ?>" name="farm_latitude" inputmode="decimal" value="<?= e((string) ($farm['latitude'] ?? '')) ?>"></div>
                <div><label>Longitude</label><input id="farm_longitude_<?= (int) $farm['id'] ?>" name="farm_longitude" inputmode="decimal" value="<?= e((string) ($farm['longitude'] ?? '')) ?>"></div>
                <div><label>Google Maps Link</label><input id="farm_<?= (int) $farm['id'] ?>_maps_url" placeholder="Paste Google Maps link"></div>
                <div><label>Extract From Link</label><button type="button" class="button secondary" data-map-extract="farm_<?= (int) $farm['id'] ?>">Extract Latitude / Longitude</button></div>
                <div><label>Pick Coordinates</label><button type="button" class="button secondary" data-location-fill="farm_<?= (int) $farm['id'] ?>">Use My Current Location</button> <button type="button" class="button secondary" data-map-search="farm_<?= (int) $farm['id'] ?>">Search Address On Map</button></div>
              </div>
              <button type="submit">Save Farm</button>
            </form>
          <?php endforeach; ?>

          <form method="post" class="panel">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_farm">
            <h3>Add Another Farm</h3>
            <div class="grid">
              <div><label>Farm Name</label><input name="farm_name" placeholder="e.g. Riverbank Farm"></div>
              <div><label>Farm Size (Hectares)</label><input type="number" min="0" step="0.01" name="farm_record_size"></div>
              <div>
                <label>State</label>
                <select name="farm_state_id" data-lga-target="new_farm_lga">
                  <option value="">Select state</option>
                  <?php foreach ($states as $state): ?>
                    <option value="<?= (int) $state['id'] ?>"><?= e($state['state_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div><label>Local Government</label><select id="new_farm_lga" name="farm_lga_id"><option value="">Select LGA</option></select></div>
              <div><label>Farm Address / Community</label><input name="farm_street_address"></div>
              <div><label>Latitude</label><input id="new_farm_latitude" name="farm_latitude" inputmode="decimal"></div>
              <div><label>Longitude</label><input id="new_farm_longitude" name="farm_longitude" inputmode="decimal"></div>
              <div><label>Google Maps Link</label><input id="new_farm_maps_url" placeholder="Paste Google Maps link"></div>
              <div><label>Extract From Link</label><button type="button" class="button secondary" data-map-extract="new_farm">Extract Latitude / Longitude</button></div>
              <div><label>Pick Coordinates</label><button type="button" class="button secondary" data-location-fill="new_farm">Use My Current Location</button> <button type="button" class="button secondary" data-map-search="new_farm">Search Address On Map</button></div>
            </div>
            <button type="submit">Add Farm</button>
          </form>
        </div>
      </section>
      <section class="profile-section profile-tab-panel" data-profile-panel="hands" hidden>
        <h2>Farm Hands</h2>
        <p class="hint">Register practical workers on your farm, classify the work they do, and assign them to a specific farm or to your grower profile generally.</p>

        <div class="farm-hand-list">
          <?php foreach ($farmHands as $hand): ?>
            <details class="farm-hand-card">
              <summary><?= e((string) $hand['full_name']) ?></summary>
              <div class="farm-hand-meta">
                <span class="badge <?= e((string) $hand['status']) ?>"><?= e($farmHandStatuses[(string) $hand['status']] ?? status_label((string) $hand['status'])) ?></span>
                <span class="badge pending"><?= e($farmHandEngagements[(string) $hand['engagement_type']] ?? status_label((string) $hand['engagement_type'])) ?></span>
                <span class="badge verified"><?= e($farmHandActivities[(string) $hand['activity_category']] ?? status_label((string) $hand['activity_category'])) ?></span>
              </div>
              <p class="muted">
                <?= e((string) ($hand['farm_name'] ?: 'Assigned to grower profile')) ?>
                <?= $hand['phone'] ? ' / ' . e((string) $hand['phone']) : '' ?>
                <?= $hand['skill_level'] ? ' / ' . e($farmHandSkills[(string) $hand['skill_level']] ?? status_label((string) $hand['skill_level'])) : '' ?>
              </p>
              <?php if (!empty($hand['activity_notes'])): ?><p><?= nl2br(e((string) $hand['activity_notes'])) ?></p><?php endif; ?>
              <form method="post" class="grid">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_farm_hand">
                <input type="hidden" name="farm_hand_id" value="<?= (int) $hand['id'] ?>">
                <div><label>Name</label><input name="full_name" value="<?= e((string) $hand['full_name']) ?>" required></div>
                <div><label>Phone</label><input name="phone" value="<?= e((string) $hand['phone']) ?>"></div>
                <div><label>Email</label><input type="email" name="email" value="<?= e((string) $hand['email']) ?>"></div>
                <div>
                  <label>Farm Assignment</label>
                  <select name="farm_id">
                    <option value="">Grower profile / all farms</option>
                    <?php foreach ($growerFarms as $farm): ?>
                      <option value="<?= (int) $farm['id'] ?>" <?= (int) ($hand['farm_id'] ?? 0) === (int) $farm['id'] ? 'selected' : '' ?>><?= e($farm['farm_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Engagement</label>
                  <select name="engagement_type">
                    <?php foreach ($farmHandEngagements as $value => $label): ?>
                      <option value="<?= e($value) ?>" <?= (string) $hand['engagement_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Farm Activity</label>
                  <select name="activity_category">
                    <?php foreach ($farmHandActivities as $value => $label): ?>
                      <option value="<?= e($value) ?>" <?= (string) $hand['activity_category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Skill Level</label>
                  <select name="skill_level">
                    <option value="">Not specified</option>
                    <?php foreach ($farmHandSkills as $value => $label): ?>
                      <option value="<?= e($value) ?>" <?= (string) ($hand['skill_level'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Status</label>
                  <select name="status">
                    <?php foreach ($farmHandStatuses as $value => $label): ?>
                      <option value="<?= e($value) ?>" <?= (string) $hand['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div><label>Gender</label><input name="gender" value="<?= e((string) $hand['gender']) ?>"></div>
                <div><label>Start Date</label><input type="date" name="start_date" value="<?= e((string) $hand['start_date']) ?>"></div>
                <div><label>End Date</label><input type="date" name="end_date" value="<?= e((string) $hand['end_date']) ?>"></div>
                <div><label>Emergency Contact</label><input name="emergency_contact" value="<?= e((string) $hand['emergency_contact']) ?>"></div>
                <div style="grid-column:1/-1;"><label>Activity Notes</label><textarea name="activity_notes" placeholder="Tasks handled, work days, specialty, tools used, or notes for field teams."><?= e((string) $hand['activity_notes']) ?></textarea></div>
                <div><button type="submit">Save Farm Hand</button></div>
              </form>
            </details>
          <?php endforeach; ?>
          <?php if (!$farmHands): ?><p class="empty">No farm hands registered yet.</p><?php endif; ?>
        </div>

        <form method="post" class="panel">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_farm_hand">
          <h3>Add Farm Hand</h3>
          <div class="grid">
            <div><label>Name</label><input name="full_name" required placeholder="Worker full name"></div>
            <div><label>Phone</label><input name="phone" placeholder="Phone / WhatsApp"></div>
            <div><label>Email</label><input type="email" name="email"></div>
            <div>
              <label>Farm Assignment</label>
              <select name="farm_id">
                <option value="">Grower profile / all farms</option>
                <?php foreach ($growerFarms as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e($farm['farm_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Engagement</label>
              <select name="engagement_type">
                <?php foreach ($farmHandEngagements as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Farm Activity</label>
              <select name="activity_category">
                <?php foreach ($farmHandActivities as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Skill Level</label>
              <select name="skill_level">
                <option value="">Not specified</option>
                <?php foreach ($farmHandSkills as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Status</label>
              <select name="status">
                <?php foreach ($farmHandStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div><label>Gender</label><input name="gender"></div>
            <div><label>Start Date</label><input type="date" name="start_date"></div>
            <div><label>End Date</label><input type="date" name="end_date"></div>
            <div><label>Emergency Contact</label><input name="emergency_contact"></div>
            <div style="grid-column:1/-1;"><label>Activity Notes</label><textarea name="activity_notes" placeholder="Examples: weeding crew lead, nursery specialist, harvest labour, processing consultant, tractor operator."></textarea></div>
          </div>
          <button type="submit">Register Farm Hand</button>
        </form>
      </section>
    <?php endif; ?>
    <script>
    (function () {
      const allowedTabs = <?= json_encode(array_values($profileAllowedTabs), JSON_UNESCAPED_SLASHES) ?>;
      const defaultTab = <?= json_encode($profileDefaultTab, JSON_UNESCAPED_SLASHES) ?>;
      const tabs = Array.from(document.querySelectorAll('[data-profile-tab]'));
      const panels = Array.from(document.querySelectorAll('[data-profile-panel]'));

      function activateProfileTab(key) {
        const targetKey = allowedTabs.includes(key) && panels.some((panel) => panel.dataset.profilePanel === key) ? key : defaultTab;
        tabs.forEach((tab) => tab.setAttribute('aria-selected', tab.dataset.profileTab === targetKey ? 'true' : 'false'));
        panels.forEach((panel) => {
          panel.hidden = panel.dataset.profilePanel !== targetKey;
        });
        document.querySelectorAll('[data-profile-save-actions]').forEach((actions) => {
          actions.hidden = ['password', 'locations', 'hands'].includes(targetKey);
        });
        try { localStorage.setItem('natcodev_profile_tab', targetKey); } catch (error) {}
      }

      tabs.forEach((tab) => {
        tab.addEventListener('click', () => activateProfileTab(tab.dataset.profileTab || 'personal'));
      });
      document.querySelectorAll('[data-profile-jump]').forEach((button) => {
        button.addEventListener('click', () => {
          const target = button.dataset.profileJump || 'personal';
          activateProfileTab(target);
          const tabsNav = document.querySelector('.profile-tabs');
          if (tabsNav) tabsNav.scrollIntoView({ behavior: 'smooth', block: 'start' });
          try { history.replaceState(null, '', '#' + target); } catch (error) {}
        });
      });
      let initialTab = defaultTab;
      try { initialTab = localStorage.getItem('natcodev_profile_tab') || initialTab; } catch (error) {}
      if (window.location.hash) {
        initialTab = window.location.hash.replace('#', '');
      }
      if (!allowedTabs.includes(initialTab)) {
        initialTab = defaultTab;
      }
      activateProfileTab(initialTab);

      async function loadLgas(stateSelect) {
        const target = document.getElementById(stateSelect.dataset.lgaTarget || '');
        if (!target) return;
        const selected = target.dataset.selected || '';
        target.innerHTML = '<option value="">Select LGA</option>';
        if (!stateSelect.value) return;
        try {
          const response = await fetch('../api/get-lgas.php?state_id=' + encodeURIComponent(stateSelect.value));
          const data = await response.json();
          (data.items || []).forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.lga_name;
            if (String(item.id) === String(selected)) option.selected = true;
            target.appendChild(option);
          });
        } catch (error) {}
      }

      document.querySelectorAll('select[data-lga-target]').forEach((select) => {
        select.addEventListener('change', () => {
          const target = document.getElementById(select.dataset.lgaTarget || '');
          if (target) target.dataset.selected = '';
          loadLgas(select);
        });
        loadLgas(select);
      });

      document.querySelectorAll('[data-location-fill]').forEach((button) => {
        button.addEventListener('click', () => {
          if (!navigator.geolocation) {
            alert('Location is not available on this device.');
            return;
          }
          button.disabled = true;
          navigator.geolocation.getCurrentPosition((position) => {
            const key = button.dataset.locationFill;
            const lat = document.getElementById(key + '_latitude');
            const lng = document.getElementById(key + '_longitude');
            if (lat) lat.value = position.coords.latitude.toFixed(7);
            if (lng) lng.value = position.coords.longitude.toFixed(7);
            button.disabled = false;
          }, () => {
            alert('Unable to get location. You can type latitude and longitude manually.');
            button.disabled = false;
          }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
        });
      });

      document.querySelectorAll('[data-map-search]').forEach((button) => {
        button.addEventListener('click', () => {
          const form = button.closest('form') || document;
          const address = form.querySelector('[name="farm_street_address"], [name="street_address"]')?.value || '';
          const state = form.querySelector('[name="farm_state_id"], [name="state_id"]')?.selectedOptions?.[0]?.textContent || '';
          const lga = form.querySelector('[name="farm_lga_id"], [name="lga_id"]')?.selectedOptions?.[0]?.textContent || '';
          const query = [address, lga, state, 'Nigeria'].filter((part) => part && !part.startsWith('Select')).join(', ');
          if (!query) {
            alert('Enter the farm address, state, or LGA first.');
            return;
          }
          window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query), '_blank', 'noopener');
        });
      });

      function coordinatesFromMapsUrl(url) {
        const text = String(url || '');
        const atMatch = text.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
        if (atMatch) return [atMatch[1], atMatch[2]];
        const destinationMatch = text.match(/[?&](?:q|query|destination|center)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
        if (destinationMatch) return [destinationMatch[1], destinationMatch[2]];
        const dataMatch = text.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
        if (dataMatch) return [dataMatch[1], dataMatch[2]];
        return null;
      }

      document.querySelectorAll('[data-map-extract]').forEach((button) => {
        button.addEventListener('click', () => {
          const key = button.dataset.mapExtract;
          const url = document.getElementById(key + '_maps_url')?.value || '';
          const coordinates = coordinatesFromMapsUrl(url);
          if (!coordinates) {
            alert('Could not find coordinates in that Google Maps link.');
            return;
          }
          const lat = document.getElementById(key + '_latitude');
          const lng = document.getElementById(key + '_longitude');
          if (lat) lat.value = Number(coordinates[0]).toFixed(7);
          if (lng) lng.value = Number(coordinates[1]).toFixed(7);
        });
      });
    })();
    </script>
    <?php if (in_array((string) ($user['role'] ?? ''), ['field_agent', 'admin'], true) && !empty($user['staff_type'])): ?>
      <section class="panel" style="margin-top:18px;">
        <h2>Staff Profile</h2>
        <div class="grid">
          <p><strong>Staff Type</strong><br><?= e(ucwords(str_replace('_', ' ', (string) $user['staff_type']))) ?></p>
          <p><strong>Qualification</strong><br><?= e($user['qualification'] ?? 'Not set') ?></p>
          <p><strong>License / Certification</strong><br><?= e($user['license_number'] ?? 'Not set') ?></p>
          <p><strong>Experience</strong><br><?= e((string) ($user['experience_years'] ?? '0')) ?> years</p>
          <p><strong>Training Program</strong><br><?= e($user['training_program'] ?? 'Not set') ?></p>
          <p><strong>Certification Status</strong><br><?= e(ucwords(str_replace('_', ' ', (string) ($user['certification_status'] ?? 'not_started')))) ?></p>
        </div>
      </section>
    <?php endif; ?>
  <?php dashboard_page_end(); ?>
