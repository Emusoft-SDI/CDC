<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/field-management.php';

$pdo = db();
app_ensure_farmer_engagement_schema($pdo);
app_ensure_certificate_schema($pdo);
fm_ensure_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user($pdo);
if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $currentUser);

function gd_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Grower dashboard count error: ' . $e->getMessage());
        return 0;
    }
}

function gd_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function gd_status(string $status): string
{
    return ucwords(str_replace(['_', '-'], ' ', $status));
}

function gd_icon(string $name): string
{
    $icons = [
        'wallet' => '<path d="M3 7.5h15a3 3 0 0 1 3 3v7a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5v-10Z"/><path d="M3 8V6a2 2 0 0 1 2-2h12"/><path d="M16 14h5"/><circle cx="16" cy="14" r="1"/>',
        'seedling' => '<path d="M12 21V10"/><path d="M12 10C8 10 5 8 4 4c4 0 7 2 8 6Z"/><path d="M12 12c4 0 7-2 8-6-4 0-7 2-8 6Z"/>',
        'tree' => '<path d="M12 22v-7"/><path d="M8 17h8"/><path d="M7 14a5 5 0 1 1 10 0 4 4 0 0 1-10 0Z"/><path d="M9 9a3 3 0 1 1 6 0"/>',
        'coins' => '<ellipse cx="8" cy="7" rx="5" ry="3"/><path d="M3 7v5c0 1.7 2.2 3 5 3s5-1.3 5-3V7"/><path d="M11 12c.9-.6 2.2-1 3.5-1 2.8 0 5 1.3 5 3s-2.2 3-5 3c-1.1 0-2.1-.2-2.9-.6"/><path d="M19.5 14v3c0 1.7-2.2 3-5 3-1.4 0-2.6-.3-3.5-.9"/>',
        'academy' => '<path d="m3 8 9-4 9 4-9 4-9-4Z"/><path d="M7 10v5c0 1.7 2.2 3 5 3s5-1.3 5-3v-5"/><path d="M21 8v6"/>',
        'headset' => '<path d="M4 13a8 8 0 0 1 16 0"/><path d="M4 13v3a2 2 0 0 0 2 2h2v-7H6a2 2 0 0 0-2 2Z"/><path d="M20 13v3a2 2 0 0 1-2 2h-2v-7h2a2 2 0 0 1 2 2Z"/><path d="M16 18c0 1.7-1.8 3-4 3"/>',
        'edit' => '<path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13 6 5 5"/>',
        'cart' => '<path d="M4 5h2l2 10h9l2-7H7"/><circle cx="10" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M13 10v4"/><path d="M11 12h4"/>',
        'play' => '<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/>',
        'store' => '<path d="M4 10h16l-1-5H5l-1 5Z"/><path d="M6 10v10h12V10"/><path d="M9 20v-6h6v6"/><path d="M4 10c0 1.2 1 2 2 2s2-.8 2-2c0 1.2 1 2 2 2s2-.8 2-2c0 1.2 1 2 2 2s2-.8 2-2c0 1.2 1 2 2 2s2-.8 2-2"/>',
        'comments' => '<path d="M5 15a7 7 0 1 1 3 2.7L4 19l1-4Z"/><path d="M14 14a5 5 0 0 0 5 5l1 3-4-1.2"/>',
        'camera' => '<path d="M4 8h4l1.5-2h5L16 8h4v11H4V8Z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'cloud' => '<path d="M7 18h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.3 1.6A3.5 3.5 0 0 0 7 18Z"/><path d="m12 9-3 3h2v4h2v-4h2l-3-3Z"/>',
        'task' => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="m3 6 1 1 2-2"/><path d="m3 12 1 1 2-2"/><path d="m3 18 1 1 2-2"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/>',
        'award' => '<circle cx="12" cy="8" r="5"/><path d="m8.5 12-2 8 5.5-3 5.5 3-2-8"/>',
    ];
    $path = $icons[$name] ?? $icons['task'];
    return '<svg class="gd-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

try {
    $user = current_user($pdo);
    if (!$user) {
        session_destroy();
        redirect_to('login.php');
    }

    $stmt = $pdo->prepare("
        SELECT u.id user_id, u.name user_name, u.email user_email, u.role,
               a.id application_id, a.app_ref, a.location, a.farm_size, a.confirmed, a.created_at, a.confirmed_at
        FROM users u
        LEFT JOIN applications a ON a.id = u.application_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        session_destroy();
        redirect_to('login.php');
    }

    $pdo->prepare("INSERT IGNORE INTO wallets (user_id) VALUES (?)")->execute([$userId]);

    $docCounts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
    if (app_table_exists($pdo, 'document_requirements')) {
        $docStmt = $pdo->prepare("
            SELECT verification_status, COUNT(*) total
            FROM document_requirements
            WHERE user_id = ?
            GROUP BY verification_status
        ");
        $docStmt->execute([$userId]);
        foreach ($docStmt->fetchAll() as $row) {
            $docCounts[(string) $row['verification_status']] = (int) $row['total'];
        }
    }
    $docTotal = array_sum($docCounts);
    $documentsComplete = $docTotal > 0 && $docCounts['pending'] === 0 && $docCounts['rejected'] === 0;

    $walletStmt = $pdo->prepare("SELECT id, COALESCE(balance, 0) balance FROM wallets WHERE user_id = ? LIMIT 1");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch() ?: ['id' => 0, 'balance' => 0];
    $walletBalance = (float) $wallet['balance'];

    $walletTransactions = [];
    if (app_table_exists($pdo, 'wallet_transactions')) {
        $txStmt = $pdo->prepare("
            SELECT amount, direction, description, reference, status, created_at
            FROM wallet_transactions
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 4
        ");
        $txStmt->execute([$userId]);
        $walletTransactions = $txStmt->fetchAll();
    }

    fm_seed_missing_verifications($pdo);
    $farmStmt = $pdo->prepare("
        SELECT gf.*, ns.state_name, nl.lga_name, fv.status verification_status,
               fv.system_confidence_score, fv.system_notes
        FROM grower_farms gf
        LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
        LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
        LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
        WHERE gf.user_id = ?
        ORDER BY gf.is_primary DESC, gf.updated_at DESC, gf.created_at DESC
        LIMIT 6
    ");
    $farmStmt->execute([$userId]);
    $farms = $farmStmt->fetchAll();
    $primaryFarm = $farms[0] ?? null;
    $farmWeather = null;
    if ($primaryFarm) {
        $farmWeather = fm_weather_estimate(
            $pdo,
            (int) $primaryFarm['id'],
            $primaryFarm['latitude'] !== null ? (float) $primaryFarm['latitude'] : null,
            $primaryFarm['longitude'] !== null ? (float) $primaryFarm['longitude'] : null
        );
    }

    $fieldTasks = [];
    if (app_table_exists($pdo, 'field_tasks')) {
        $fieldTaskStmt = $pdo->prepare("
            SELECT ft.*, gf.farm_name
            FROM field_tasks ft
            JOIN grower_farms gf ON gf.id = ft.farm_id
            WHERE gf.user_id = ? AND ft.status NOT IN ('completed','cancelled')
            ORDER BY ft.due_date IS NULL, ft.due_date ASC, ft.created_at DESC
            LIMIT 4
        ");
        $fieldTaskStmt->execute([$userId]);
        $fieldTasks = $fieldTaskStmt->fetchAll();
    }

    $activeFarmHands = app_table_exists($pdo, 'farm_hands')
        ? gd_count($pdo, "SELECT COUNT(*) FROM farm_hands WHERE grower_id = ? AND status = 'active'", [$userId])
        : 0;

    $certificate = null;
    if (!empty($profile['application_id'])) {
        $certStmt = $pdo->prepare("
            SELECT c.*, COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) display_ref
            FROM certificates c
            JOIN applications a ON a.id = c.application_id
            WHERE c.application_id = ? AND COALESCE(c.status, 'issued') = 'issued'
            ORDER BY c.issued_at DESC
            LIMIT 1
        ");
        $certStmt->execute([(int) $profile['application_id']]);
        $certificate = $certStmt->fetch();
    }

    $academy = ['courses' => 0, 'completed' => 0, 'avg_progress' => 0, 'certificates' => 0];
    $academyCourses = [];
    if (app_table_exists($pdo, 'webinar_registrations')) {
        $academyStmt = $pdo->prepare("
            SELECT r.progress_percent, r.completion_status, r.certificate_status, w.title
            FROM webinar_registrations r
            JOIN webinars w ON w.id = r.webinar_id
            WHERE r.user_id = ?
            ORDER BY r.registered_at DESC
            LIMIT 5
        ");
        $academyStmt->execute([$userId]);
        $academyCourses = $academyStmt->fetchAll();
        $academy['courses'] = count($academyCourses);
        foreach ($academyCourses as $course) {
            $academy['avg_progress'] += (int) $course['progress_percent'];
            if ((string) $course['completion_status'] === 'completed') {
                $academy['completed']++;
            }
        }
        if ($academy['courses'] > 0) {
            $academy['avg_progress'] = (int) round($academy['avg_progress'] / $academy['courses']);
        }
    }
    if (app_table_exists($pdo, 'academy_certificates')) {
        $academy['certificates'] += gd_count($pdo, "SELECT COUNT(*) FROM academy_certificates WHERE user_id = ? AND status = 'issued'", [$userId]);
    }
    if (app_table_exists($pdo, 'academy_group_certificates')) {
        $academy['certificates'] += gd_count($pdo, "SELECT COUNT(*) FROM academy_group_certificates WHERE user_id = ? AND status = 'issued'", [$userId]);
    }

    $messages = [];
    if (app_table_exists($pdo, 'messages')) {
        $msgStmt = $pdo->prepare("
            SELECT *
            FROM messages
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $msgStmt->execute([$userId]);
        $messages = $msgStmt->fetchAll();
    }
    $unreadMessages = app_table_exists($pdo, 'messages')
        ? gd_count($pdo, "SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_from_admin = 1 AND is_read = 0", [$userId])
        : 0;

    $sellerStats = ['listings' => 0, 'orders' => 0, 'sales' => 0.0];
    if (app_table_exists($pdo, 'marketplace_sellers')) {
        $sellerStmt = $pdo->prepare("SELECT id FROM marketplace_sellers WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $sellerStmt->execute([$userId]);
        $sellerId = (int) ($sellerStmt->fetchColumn() ?: 0);
        if ($sellerId > 0 && app_table_exists($pdo, 'marketplace_listings')) {
            $sellerStats['listings'] = gd_count($pdo, "SELECT COUNT(*) FROM marketplace_listings WHERE seller_id = ? AND approval_status = 'approved'", [$sellerId]);
        }
        if ($sellerId > 0 && app_table_exists($pdo, 'marketplace_orders')) {
            $sellerStats['orders'] = gd_count($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE seller_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$sellerId]);
            $salesStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE seller_id = ? AND status NOT IN ('cancelled','refunded') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $salesStmt->execute([$sellerId]);
            $sellerStats['sales'] = (float) $salesStmt->fetchColumn();
        }
    }
} catch (Throwable $e) {
    error_log('Grower dashboard error: ' . $e->getMessage());
    http_response_code(500);
    exit('Dashboard temporarily unavailable.');
}

$userName = trim((string) ($profile['user_name'] ?? $user['name'] ?? 'Grower'));
$firstName = trim(explode(' ', $userName)[0] ?? $userName);
$appRef = (string) ($profile['app_ref'] ?? 'Not linked');
$confirmed = (int) ($profile['confirmed'] ?? 0) === 1;
$certificateReady = (bool) $certificate;
$farmScore = min(100, 35 + ($confirmed ? 15 : 0) + ($documentsComplete ? 25 : 0) + ($primaryFarm ? 15 : 0) + ($certificateReady ? 10 : 0));
$coconutSurvival = $primaryFarm && $primaryFarm['system_confidence_score'] !== null ? (int) $primaryFarm['system_confidence_score'] : null;
$certificateRef = $certificateReady ? (string) $certificate['display_ref'] : '';
$stateName = (string) ($primaryFarm['state_name'] ?? 'State pending');
$lgaName = (string) ($primaryFarm['lga_name'] ?? 'LGA pending');
$location = trim($lgaName . ', ' . $stateName, ' ,');

dashboard_page_start('Overview', [
    'active' => 'index.php',
    'show_title' => false,
    'user_name' => $userName,
    'app_ref' => $appRef,
    'unread' => $unreadMessages,
    'profile_picture' => $user['profile_picture'] ?? '',
    'location' => $location,
    'css' => '
      .overview-page{max-width:1500px;margin:0 auto;display:grid;gap:22px}
      .pg-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:20px}
      .pg-head h2{margin:0;color:#111827;font-size:clamp(1.9rem,3vw,2.45rem);line-height:1.1}
      .pg-head .sub{color:var(--text-secondary);margin-top:6px}
      .btn-customize{background:#fff;color:var(--text-primary);border:1px solid var(--border-color);border-radius:10px;padding:11px 14px;font-weight:800}
      .overview-page .card{min-width:0}
      .dash-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
      .col-span-1{grid-column:span 1}.col-span-2{grid-column:span 2}.col-span-3{grid-column:span 3}.col-span-4{grid-column:span 4}.col-span-6{grid-column:span 6}
      .gd-svg{width:1em;height:1em;display:block}
      .stat-card{display:flex;align-items:flex-start;gap:16px;min-height:96px}
      .stat-icon{width:58px;height:58px;border-radius:18px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#E8F5E9 0%,#C8E6C9 100%);color:var(--primary-green);font-weight:900;flex:0 0 auto;font-size:26px}
      .stat-icon.blue{background:#DBEAFE;color:#1D4ED8}.stat-icon.orange{background:#FEF3C7;color:#D97706}.stat-icon.purple{background:#EDE9FE;color:var(--purple)}
      .stat-label{font-size:12px;color:var(--text-secondary);font-weight:800;text-transform:uppercase;letter-spacing:.04em}
      .stat-value{font-size:clamp(1.35rem,2.1vw,1.85rem);font-weight:900;color:#111827;line-height:1.15;margin:6px 0}
      .link{color:var(--primary-green);font-weight:800;cursor:pointer}
      .dashboard-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(340px,1fr);gap:18px;align-items:start}
      .dashboard-grid .col-span-4,.dashboard-grid .col-span-3,.dashboard-grid .col-span-2{grid-column:span 1}
      .card-h{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:-22px -22px 18px;padding:15px 18px;background:linear-gradient(135deg,#FFFBEA 0%,#F2FBEF 100%);border-bottom:1px solid #D8EADF;border-radius:12px 12px 0 0}.card-h h3{margin:0;font-size:17px;font-weight:950;color:#0F3D1B;letter-spacing:.01em}.card-h .link{font-size:12px;font-weight:950;background:#FACC15;color:#173B12;border:1px solid #EAB308;border-radius:999px;padding:5px 10px;text-decoration:none;white-space:nowrap}
      .timeline-container{display:flex;gap:14px;margin:18px 0;overflow-x:auto;padding:2px 4px 10px}
      .timeline-stage{flex:1;min-width:160px;background:#fff;border:2px solid var(--border-color);border-radius:12px;padding:20px 16px;text-align:center;position:relative;display:grid;gap:7px;min-height:188px;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
      .timeline-stage::after{content:"";position:absolute;right:-18px;top:50%;width:22px;height:2px;background:#D1D5DB}
      .timeline-stage:last-child::after{display:none}
      .timeline-stage:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(16,24,40,.08)}
      .timeline-stage.active{background:linear-gradient(135deg,#E8F5E9 0%,#C8E6C9 100%);border-color:var(--primary-green);box-shadow:0 10px 24px rgba(27,94,32,.16)}
      .timeline-ic{width:50px;height:50px;margin:0 auto 2px;border-radius:50%;display:grid;place-items:center;background:#F3F8F1;color:var(--primary-green);font-size:25px}
      .timeline-stage.active .timeline-ic{background:#fff}
      .timeline-stage .yr{font-weight:900;color:#111827}.timeline-stage .mo{color:var(--text-secondary);font-size:12px}.timeline-stage .tree{font-size:24px;font-weight:900;color:var(--primary-green)}
      .sub-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}.mini-card{background:#F9FAFB;border:1px solid var(--border-color);border-radius:10px;padding:12px}.mini-card .v{font-weight:900;color:#111827}.mini-card .l{color:var(--text-secondary);font-size:12px}
      .income-chart{width:190px;height:190px;border-radius:50%;margin:8px auto;background:conic-gradient(var(--primary-green) 0 58%, var(--accent-teal) 58% 100%);display:grid;place-items:center}.income-inner{width:116px;height:116px;background:#fff;border-radius:50%;display:grid;place-items:center;text-align:center;font-weight:900}
      .activity-item,.task-item,.table-lite-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--border-light);background:#fff;padding:13px 0;margin:0}
      .task-main{display:flex;align-items:center;gap:12px;min-width:0}
      .task-icon,.row-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#F0FDF4 0%,#DCFCE7 100%);color:var(--primary-green);font-size:18px;flex:0 0 auto}
      .task-icon.orange{background:linear-gradient(135deg,#FFF7ED 0%,#FFEDD5 100%);color:#F97316}
      .task-icon.blue{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 100%);color:#3B82F6}
      .act-info .nm{font-weight:800}.act-info .dt{color:var(--text-secondary);font-size:12px}
      .weather-main{display:flex;align-items:center;gap:18px;margin:8px 0 18px}.weather-icon{width:76px;height:76px;border-radius:22px;display:grid;place-items:center;background:linear-gradient(135deg,#FFF7D6 0%,#FFECB3 100%);color:#D97706;font-size:42px;filter:drop-shadow(0 4px 8px rgba(0,0,0,.08))}.weather-temp{font-size:44px;font-weight:900;line-height:1;color:#111827}.weather-desc{color:var(--text-secondary);font-weight:700}
      .dashboard-fold{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;box-shadow:0 12px 30px rgba(16,24,40,.08);overflow:hidden}
      .dashboard-fold summary{display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none;padding:18px 20px;font-weight:900;color:#111827}
      .dashboard-fold summary::-webkit-details-marker{display:none}
      .dashboard-fold summary::after{content:"+";width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:#E8F5E9;color:var(--primary-green);font-weight:900}
      .dashboard-fold[open] summary::after{content:"-"}
      .fold-body{padding:0 20px 20px}
      .dashboard-fold .card{padding:0;overflow:hidden;border-color:#D8EADF;background:#fff}
      .dashboard-fold .card-h{margin:0;padding:14px 14px;background:linear-gradient(135deg,#FFFBEA 0%,#F2FBEF 100%);border-bottom:1px solid #D8EADF;align-items:center;min-height:54px}
      .dashboard-fold .card-h h3{font-size:14px;font-weight:950;letter-spacing:.01em;color:#0F3D1B}
      .dashboard-fold .card-h .link{background:#FACC15;color:#173B12;border:1px solid #EAB308;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:900;text-decoration:none}
      .dashboard-fold .table-lite-row{margin:0 14px;padding:12px 0}
      .dashboard-fold .table-lite-row:first-of-type{margin-top:8px}
      .dashboard-fold .table-lite-row:last-child{border-bottom:0;margin-bottom:8px}
      .preview-note{margin:8px 14px 14px;padding:9px 10px;background:#F8FAFC;border:1px dashed #D8EADF;border-radius:9px;color:#64748B;font-size:11px;font-weight:800}
      .g6{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.quick-actions{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:14px}
      .qa-item{min-height:126px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:9px;background:#fff;border:2px solid var(--border-color);border-radius:12px;padding:16px 10px;color:inherit;text-align:center;position:relative;overflow:hidden;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
      .qa-item::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,var(--primary-green),var(--primary-green-light));transform:scaleX(0);transition:transform .18s ease}
      .qa-item:hover::before{transform:scaleX(1)}
      .qa-item:hover{transform:translateY(-2px);border-color:rgba(27,94,32,.22);box-shadow:0 12px 24px rgba(16,24,40,.08)}
      .qa-ic{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;background:#E8F5E9;color:var(--primary-green);font-weight:900;font-size:22px;transition:transform .18s ease}
      .qa-item:hover .qa-ic{transform:scale(1.08) rotate(-4deg)}
      .qa-ic.green{background:#DFF3E3;color:#0f7a31}.qa-ic.teal{background:#DDF6F2;color:#0f8f85}.qa-ic.purple{background:#EFE3FF;color:#9333EA}.qa-ic.orange{background:#FFF0C9;color:#F59E0B}.qa-ic.blue{background:#DFF0FF;color:#1680C2}.qa-ic.red{background:#FFE4E6;color:#EF4444}
      .qa-txt .t{display:block;font-weight:900;font-size:12px;color:#111827;line-height:1.2}.qa-txt .s{display:block;margin-top:4px;font-size:10px;color:var(--text-secondary);line-height:1.25}
      @media(max-width:1180px){.dash-grid,.g6{grid-template-columns:repeat(2,1fr)}.quick-actions{grid-template-columns:repeat(4,1fr)}.dashboard-grid{grid-template-columns:1fr}.sub-cards{grid-template-columns:1fr}}
      @media(max-width:760px){.dash-grid,.dashboard-grid,.g6,.quick-actions,.timeline-container{grid-template-columns:1fr}.col-span-1,.col-span-2,.col-span-3,.col-span-4,.col-span-6{grid-column:span 1}.pg-head{display:grid}}
    ',
]);
?>
<div class="overview-page">
<div class="pg-head">
  <div>
    <h2>Welcome back, <?= e($firstName) ?></h2>
    <div class="sub">Here is what is happening on your farm today. Every card opens a real outcome page.</div>
  </div>
  <a class="btn-customize" href="profile.php#preferences">Customize Dashboard</a>
</div>

<section class="dash-grid">
  <article class="card col-span-1"><div class="stat-card"><div class="stat-icon"><?= gd_icon('wallet') ?></div><div><div class="stat-label">Wallet Balance</div><div class="stat-value"><?= e(gd_money($walletBalance)) ?></div><a class="link" href="wallet.php">View wallet</a></div></div></article>
  <article class="card col-span-1"><div class="stat-card"><div class="stat-icon"><?= gd_icon('seedling') ?></div><div><div class="stat-label">Farm Score</div><div class="stat-value"><?= (int) $farmScore ?><small>/100</small></div><a class="link" href="farm-health.php">View details</a></div></div></article>
  <article class="card col-span-1"><div class="stat-card"><div class="stat-icon"><?= gd_icon('tree') ?></div><div><div class="stat-label">Coconut Survival</div><div class="stat-value"><?= $coconutSurvival !== null ? (int) $coconutSurvival . '%' : 'Pending' ?></div><a class="link" href="fields.php">View stands</a></div></div></article>
  <article class="card col-span-1"><div class="stat-card"><div class="stat-icon orange"><?= gd_icon('coins') ?></div><div><div class="stat-label">Monthly Cashflow</div><div class="stat-value"><?= e(gd_money($sellerStats['sales'])) ?></div><a class="link" href="reports.php">View cashflow</a></div></div></article>
  <article class="card col-span-1"><div class="stat-card"><div class="stat-icon purple"><?= gd_icon('academy') ?></div><div><div class="stat-label">Academy Progress</div><div class="stat-value"><?= (int) $academy['avg_progress'] ?>%</div><a class="link" href="../academy/index.php?screen=learning">Continue learning</a></div></div></article>
  <article class="card col-span-1"><div class="stat-card"><div class="stat-icon blue"><?= gd_icon('headset') ?></div><div><div class="stat-label">Support</div><div class="stat-value"><?= (int) $unreadMessages ?></div><a class="link" href="inbox.php">View tickets</a></div></div></article>
</section>

<section class="dashboard-grid">
  <article class="card col-span-4">
    <div class="card-h"><h3>3-Year Coconut Bridge <small>(pre-yield planning)</small></h3><a class="link" href="farm-health.php">View plan</a></div>
    <div class="timeline-container">
      <div class="timeline-stage active"><span class="timeline-ic"><?= gd_icon('seedling') ?></span><div class="yr">Year 1</div><div class="mo">Establishment</div><div class="tree">Y1</div><small>Roots, seedlings, intercrops</small></div>
      <div class="timeline-stage"><span class="timeline-ic"><?= gd_icon('tree') ?></span><div class="yr">Year 2</div><div class="mo">Growth</div><div class="tree">Y2</div><small>Survival and labor tracking</small></div>
      <div class="timeline-stage"><span class="timeline-ic"><?= gd_icon('task') ?></span><div class="yr">Year 3</div><div class="mo">Pre-production</div><div class="tree">Y3</div><small>Prepare harvest systems</small></div>
      <div class="timeline-stage"><span class="timeline-ic"><?= gd_icon('coins') ?></span><div class="yr">Year 4+</div><div class="mo">Production</div><div class="tree">Y4</div><small>Dwarf coconut yield begins</small></div>
    </div>
    <div class="sub-cards">
      <div class="mini-card"><div class="v"><?= e((string) ($primaryFarm['intercrops'] ?? 'Not set')) ?></div><div class="l">Current Intercrops</div></div>
      <div class="mini-card"><div class="v"><?= e((string) ($primaryFarm['livestock_integration'] ?? 'Not set')) ?></div><div class="l">Livestock</div></div>
      <div class="mini-card"><div class="v"><?= (int) $activeFarmHands ?></div><div class="l">Active Farm Hands</div></div>
    </div>
  </article>

  <article class="card col-span-2">
    <div class="card-h"><h3>Intercrop & Livestock Income</h3><a class="link" href="reports.php">Breakdown</a></div>
    <div class="income-chart"><div class="income-inner"><?= e(gd_money($sellerStats['sales'])) ?><small>Total</small></div></div>
    <div class="activity-item"><div class="act-info"><div class="nm">Marketplace/Seller Sales</div><div class="dt">Last 30 days</div></div><strong><?= e(gd_money($sellerStats['sales'])) ?></strong></div>
  </article>

  <article class="card col-span-2">
    <div class="card-h"><h3>Upcoming Farm Tasks</h3><a class="link" href="fields.php">View all</a></div>
    <?php foreach (array_slice($fieldTasks, 0, 3) as $task): ?>
      <div class="task-item"><div class="task-main"><span class="task-icon orange"><?= gd_icon('task') ?></span><div class="act-info"><div class="nm"><?= e((string) ($task['farm_name'] ?? 'Field task')) ?></div><div class="dt"><?= e(gd_status((string) ($task['task_type'] ?? 'task'))) ?></div></div></div><span class="badge-pill bp-orange"><?= e(gd_status((string) ($task['status'] ?? 'pending'))) ?></span></div>
    <?php endforeach; ?>
    <?php if (count($fieldTasks) > 3): ?><div class="preview-note">Showing 3 of <?= count($fieldTasks) ?> tasks. Use View all for the full work queue.</div><?php endif; ?>
    <?php if (!$fieldTasks): ?><div class="task-item"><div class="task-main"><span class="task-icon"><?= gd_icon('task') ?></span><div class="act-info"><div class="nm">No open field task</div><div class="dt">Request review only when needed.</div></div></div><span class="badge-pill bp-green">Clear</span></div><?php endif; ?>
  </article>

  <article class="card col-span-2">
    <div class="card-h"><h3>Weather</h3><small><?= e($lgaName) ?></small></div>
    <div class="weather-main"><span class="weather-icon"><?= gd_icon('sun') ?></span><div><div class="weather-temp"><?= $farmWeather ? e((string) $farmWeather['temperature_c']) . 'C' : '--' ?></div><div class="weather-desc"><?= $farmWeather ? e((string) $farmWeather['summary']) : 'Weather appears after farm coordinates are available.' ?></div></div></div>
    <div class="sub-cards">
      <div class="mini-card"><div class="v"><?= $farmWeather ? e((string) $farmWeather['humidity_percent']) . '%' : '--' ?></div><div class="l">Humidity</div></div>
      <div class="mini-card"><div class="v"><?= $farmWeather ? e((string) $farmWeather['rainfall_mm']) . ' mm' : '--' ?></div><div class="l">Rain</div></div>
    </div>
  </article>
</section>

<details class="dashboard-fold" open>
  <summary><span>Operational Intelligence</span><small>Academy, certificates, marketplace, wallet, support, and healthcare</small></summary>
  <div class="fold-body g6">
  <article class="card">
    <div class="card-h"><h3>Academy Progress</h3><a class="link" href="../academy/index.php?screen=learning">View all</a></div>
    <?php foreach (array_slice($academyCourses, 0, 3) as $course): ?><div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('academy') ?></span><div class="act-info"><div class="nm"><?= e((string) $course['title']) ?></div><div class="dt"><?= (int) $course['progress_percent'] ?>% complete</div></div></div><span class="badge-pill"><?= e(gd_status((string) $course['completion_status'])) ?></span></div><?php endforeach; ?>
    <?php if (count($academyCourses) > 3): ?><div class="preview-note">Showing 3 of <?= count($academyCourses) ?> courses. View all opens your learning workspace.</div><?php endif; ?>
    <?php if (!$academyCourses): ?><div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('academy') ?></span><div class="act-info"><div class="nm">No course yet</div><div class="dt">Enroll from the Academy catalog.</div></div></div><a class="link" href="../academy/index.php?screen=catalog">Enroll</a></div><?php endif; ?>
  </article>
  <article class="card">
    <div class="card-h"><h3>Certificate Status</h3><a class="link" href="certificates.php">View all</a></div>
    <div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('award') ?></span><div class="act-info"><div class="nm">Verified Grower Credential</div><div class="dt"><?= $certificateReady ? 'Ref: ' . e($certificateRef) : 'Complete verification to unlock' ?></div></div></div><span class="badge-pill <?= $certificateReady ? 'bp-green' : 'bp-orange' ?>"><?= $certificateReady ? 'Active' : 'Pending' ?></span></div>
    <div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('academy') ?></span><div class="act-info"><div class="nm">Academy Certificates</div><div class="dt"><?= (int) $academy['certificates'] ?> issued</div></div></div><span class="badge-pill bp-blue">Available</span></div>
  </article>
  <article class="card">
    <div class="card-h"><h3>Marketplace & Seller</h3><a class="link" href="../market/index.php">Open</a></div>
    <div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('store') ?></span><div class="act-info"><div class="nm">Active Listings</div><div class="dt"><?= (int) $sellerStats['listings'] ?> products/services</div></div></div><a class="link" href="../market/seller-central.php">Manage</a></div>
    <div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('cart') ?></span><div class="act-info"><div class="nm">Orders Received</div><div class="dt"><?= (int) $sellerStats['orders'] ?> in 30 days</div></div></div><span class="badge-pill bp-orange"><?= (int) $sellerStats['orders'] ?></span></div>
  </article>
  <article class="card">
    <div class="card-h"><h3>Wallet Transactions</h3><a class="link" href="wallet.php">View all</a></div>
    <?php foreach (array_slice($walletTransactions, 0, 3) as $tx): ?><div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('wallet') ?></span><div class="act-info"><div class="nm"><?= e(mb_substr((string) $tx['description'], 0, 42)) ?></div><div class="dt"><?= e(date('M j, Y', strtotime((string) $tx['created_at']))) ?></div></div></div><span class="badge-pill <?= (string) $tx['direction'] === 'outflow' ? 'bp-orange' : 'bp-green' ?>"><?= ((string) $tx['direction'] === 'outflow' ? '-' : '+') . e(gd_money((float) $tx['amount'])) ?></span></div><?php endforeach; ?>
    <?php if (count($walletTransactions) > 3): ?><div class="preview-note">Showing latest 3 transactions. View all opens the full wallet history.</div><?php endif; ?>
    <?php if (!$walletTransactions): ?><div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('wallet') ?></span><div class="act-info"><div class="nm">No wallet transaction yet</div><div class="dt">Fund wallet or pay for a course to begin.</div></div></div><a class="link" href="wallet.php">Open</a></div><?php endif; ?>
  </article>
  <article class="card">
    <div class="card-h"><h3>Support Desk</h3><a class="link" href="inbox.php">View all</a></div>
    <?php foreach (array_slice($messages, 0, 3) as $message): ?><div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('headset') ?></span><div class="act-info"><div class="nm"><?= e((string) ($message['ticket_id'] ?: 'Support update')) ?></div><div class="dt"><?= e(mb_substr((string) $message['message'], 0, 58)) ?></div></div></div><span class="badge-pill <?= (int) $message['is_read'] === 0 ? 'bp-orange' : 'bp-green' ?>"><?= (int) $message['is_read'] === 0 ? 'New' : 'Read' ?></span></div><?php endforeach; ?>
    <?php if (count($messages) > 3): ?><div class="preview-note">Showing latest 3 support updates. View all opens the full desk.</div><?php endif; ?>
    <?php if (!$messages): ?><div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('headset') ?></span><div class="act-info"><div class="nm">No support issue</div><div class="dt">Open a ticket when you need help.</div></div></div><a class="link" href="inbox.php">Open</a></div><?php endif; ?>
  </article>
  <article class="card">
    <div class="card-h"><h3>Healthcare</h3><a class="link" href="healthcare.php">Open</a></div>
    <div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('headset') ?></span><div class="act-info"><div class="nm">Health Service Status</div><div class="dt">Enrollment and partner services appear when enabled.</div></div></div><span class="badge-pill bp-orange">Optional</span></div>
    <div class="table-lite-row"><div class="task-main"><span class="row-icon"><?= gd_icon('task') ?></span><div class="act-info"><div class="nm">Field worker safety</div><div class="dt">Keep hydration and safety checks visible.</div></div></div><a class="link" href="healthcare.php">Open</a></div>
  </article>
</div>
</details>

<section class="card" style="margin-top:18px;">
  <div class="card-h"><h3>Quick Actions</h3><small>Open only when needed. Nothing is forced.</small></div>
  <div class="quick-actions">
    <a class="qa-item" href="farm-health.php"><span class="qa-ic green"><?= gd_icon('edit') ?></span><span class="qa-txt"><span class="t">Record Farm Activity</span><span class="s">Log field work & updates</span></span></a>
    <a class="qa-item" href="fields.php#intercrops"><span class="qa-ic green"><?= gd_icon('cart') ?></span><span class="qa-txt"><span class="t">Add Intercrop Sale</span><span class="s">Record produce sales</span></span></a>
    <a class="qa-item" href="wallet.php"><span class="qa-ic teal"><?= gd_icon('wallet') ?></span><span class="qa-txt"><span class="t">Fund Wallet</span><span class="s">Add money to wallet</span></span></a>
    <a class="qa-item" href="../academy/index.php?screen=learning"><span class="qa-ic purple"><?= gd_icon('academy') ?></span><span class="qa-txt"><span class="t">Continue Course</span><span class="s">Resume learning</span></span></a>
    <a class="qa-item" href="../market/index.php"><span class="qa-ic orange"><?= gd_icon('store') ?></span><span class="qa-txt"><span class="t">Open Marketplace</span><span class="s">Buy & sell farm inputs</span></span></a>
    <a class="qa-item" href="agronomist.php"><span class="qa-ic green"><?= gd_icon('comments') ?></span><span class="qa-txt"><span class="t">Request Advisory</span><span class="s">Get expert advice</span></span></a>
    <a class="qa-item" href="documents.php"><span class="qa-ic blue"><?= gd_icon('camera') ?></span><span class="qa-txt"><span class="t">Upload Evidence</span><span class="s">Photos & documents</span></span></a>
    <a class="qa-item" href="inbox.php"><span class="qa-ic red"><?= gd_icon('headset') ?></span><span class="qa-txt"><span class="t">Request Support</span><span class="s">Get help from team</span></span></a>
  </div>
</section>
</div>
<?php dashboard_page_end(); ?>
