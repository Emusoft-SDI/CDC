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

require_once __DIR__ . '/../lib/dashboard/farm-operations-helpers.php';

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
<?php require __DIR__ . '/partials/farm-operations-view.php'; ?>
<?php dashboard_page_end(); ?>
