<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/monnify.php';
require_once __DIR__ . '/../../lib/academy.php';
require_once __DIR__ . '/../../lib/support.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
admin_ensure_schema($pdo);
wallet_ensure_schema($pdo);
academy_ensure_schema($pdo);
support_ensure_schema($pdo);
admin_require($pdo);

$reportAdmin = current_user($pdo) ?: [];
$adminDisplayName = trim((string) (($reportAdmin['name'] ?? '') ?: ($reportAdmin['email'] ?? 'Admin User')));
$adminDisplayRole = ucwords(str_replace('_', ' ', (string) ($reportAdmin['platform_role'] ?? $reportAdmin['role'] ?? 'Admin')));
$adminInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $adminDisplayName) ?: 'AD', 0, 2));
$reportScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/reports.php')));
$reportAdminBase = basename($reportScriptDir) === 'acad' ? dirname($reportScriptDir) : $reportScriptDir;
$reportAdminBase = rtrim($reportAdminBase, '/') ?: '/admin';
$reportPublicBase = preg_replace('#/admin$#', '', $reportAdminBase) ?: '';
$adminPicture = ltrim((string) ($reportAdmin['profile_picture'] ?? ''), '/');
$adminPictureUrl = $adminPicture !== '' ? $reportPublicBase . '/' . $adminPicture : '';

function rd_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_generated_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_ref VARCHAR(80) NOT NULL UNIQUE,
        report_type VARCHAR(80) NOT NULL,
        audience VARCHAR(80) NOT NULL DEFAULT 'management',
        format VARCHAR(30) NOT NULL DEFAULT 'csv',
        date_from DATE NULL,
        date_to DATE NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'completed',
        notes TEXT NULL,
        generated_by INT NULL,
        generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_report_runs_type (report_type, generated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'report_generated_runs');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_name VARCHAR(180) NOT NULL,
        report_type VARCHAR(80) NOT NULL,
        frequency VARCHAR(40) NOT NULL DEFAULT 'weekly',
        run_time TIME NULL,
        format VARCHAR(30) NOT NULL DEFAULT 'csv',
        recipients TEXT NULL,
        next_run_at DATETIME NULL,
        last_run_at DATETIME NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_report_schedules_status (status, next_run_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'report_schedules');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_data_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_name VARCHAR(180) NOT NULL,
        source_type VARCHAR(80) NOT NULL DEFAULT 'database',
        connection_label VARCHAR(220) NULL,
        sync_frequency VARCHAR(60) NOT NULL DEFAULT 'manual',
        status VARCHAR(40) NOT NULL DEFAULT 'connected',
        records_count INT NOT NULL DEFAULT 0,
        last_sync_at DATETIME NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_report_data_sources_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'report_data_sources');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_access_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        access_level VARCHAR(80) NOT NULL DEFAULT 'read_only',
        modules TEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        granted_by INT NULL,
        granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_report_access_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'report_access_rules');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_key VARCHAR(100) NOT NULL UNIQUE,
        title VARCHAR(180) NOT NULL,
        module VARCHAR(80) NOT NULL DEFAULT 'platform',
        audience VARCHAR(80) NOT NULL DEFAULT 'management',
        description TEXT NULL,
        layout_config TEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        updated_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_report_templates_module (module, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'report_templates');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alert_ref VARCHAR(80) NOT NULL UNIQUE,
        module VARCHAR(80) NOT NULL DEFAULT 'platform',
        severity VARCHAR(40) NOT NULL DEFAULT 'medium',
        title VARCHAR(180) NOT NULL,
        description TEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'open',
        due_at DATETIME NULL,
        resolved_by INT NULL,
        resolved_at DATETIME NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_report_alerts_status (status, severity, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'report_alerts');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_stakeholder_interests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stakeholder_role VARCHAR(80) NOT NULL,
        interest_area VARCHAR(120) NOT NULL,
        module VARCHAR(80) NOT NULL DEFAULT 'platform',
        priority VARCHAR(40) NOT NULL DEFAULT 'medium',
        default_report VARCHAR(120) NULL,
        delivery_channel VARCHAR(80) NOT NULL DEFAULT 'dashboard',
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        notes TEXT NULL,
        updated_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_report_interests_role (stakeholder_role, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'report_stakeholder_interests');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS report_workspace_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

function rd_scalar(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Report workspace scalar failed: ' . $e->getMessage());
        return 0.0;
    }
}

function rd_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Report workspace rows failed: ' . $e->getMessage());
        return [];
    }
}

function rd_post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function rd_ref(string $prefix): string
{
    return $prefix . '-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function rd_redirect(string $page, string $message = '', string $error = ''): void
{
    $query = ['page' => preg_replace('/[^a-z0-9-]/', '', $page) ?: 'overview'];
    if ($message !== '') {
        $query['message'] = $message;
    }
    if ($error !== '') {
        $query['error'] = $error;
    }
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/reports.php')));
    if (basename($scriptDir) === 'acad') {
        $scriptDir = dirname($scriptDir);
    }
    header('Location: ' . rtrim($scriptDir, '/') . '/reports.php?' . http_build_query($query));
    exit;
}

function rd_pct(float $part, float $whole): float
{
    return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
}

if ((int) ($pdo->query('SELECT COUNT(*) FROM report_data_sources')->fetchColumn() ?: 0) === 0) {
    $sources = [
        ['Registry Database', 'MySQL', 'applications, users, certificates', 'every_15_minutes'],
        ['Marketplace', 'MySQL', 'marketplace sellers, listings, orders', 'every_15_minutes'],
        ['Wallet System', 'MySQL', 'wallets and wallet_transactions', 'every_5_minutes'],
        ['Academy LMS', 'MySQL', 'webinars and registrations', 'every_30_minutes'],
        ['Support Desk', 'MySQL', 'support_tickets and messages', 'every_15_minutes'],
    ];
    $stmt = $pdo->prepare("INSERT INTO report_data_sources (source_name, source_type, connection_label, sync_frequency, status, created_by) VALUES (?, ?, ?, ?, 'connected', ?)");
    foreach ($sources as $source) {
        $stmt->execute([...$source, (int) ($reportAdmin['id'] ?? 0)]);
    }
}

if ((int) ($pdo->query('SELECT COUNT(*) FROM report_schedules')->fetchColumn() ?: 0) === 0) {
    $schedules = [
        ['Daily Operations Brief', 'executive_summary', 'daily', '08:00:00', 'pdf', 'platform-admins@natcodev.org'],
        ['Weekly Executive Summary', 'executive_summary', 'weekly', '09:00:00', 'pdf', 'leadership@natcodev.org'],
        ['State Performance Report', 'state_lga', 'weekly', '10:00:00', 'csv', 'coordinators@natcodev.org'],
        ['Marketplace GMV Report', 'marketplace', 'daily', '06:00:00', 'csv', 'marketplace@natcodev.org'],
    ];
    $stmt = $pdo->prepare("INSERT INTO report_schedules (report_name, report_type, frequency, run_time, format, recipients, next_run_at, status, created_by) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', ?)");
    foreach ($schedules as $schedule) {
        $stmt->execute([...$schedule, (int) ($reportAdmin['id'] ?? 0)]);
    }
}

if ((int) ($pdo->query('SELECT COUNT(*) FROM report_templates')->fetchColumn() ?: 0) === 0) {
    $templates = [
        ['executive_dashboard', 'Executive Dashboard', 'platform', 'executive', 'Platform-wide KPI overview for leadership.'],
        ['registry_operations', 'Registry Operations', 'registry', 'operations', 'Grower, application, verification, and certificate reporting.'],
        ['marketplace_revenue', 'Marketplace Revenue', 'marketplace', 'management', 'Seller, listing, order, GMV, payout, and dispute reporting.'],
        ['wallet_finance', 'Wallet & Finance', 'wallet', 'finance', 'Wallet balances, transactions, refunds, payouts, and reconciliation.'],
        ['academy_outcomes', 'Academy Outcomes', 'academy', 'academy', 'Learner, course, cohort, completion, and certificate reporting.'],
        ['support_sla', 'Support SLA', 'support', 'support', 'Ticket volumes, response time, SLA, escalation, and satisfaction reporting.'],
    ];
    $stmt = $pdo->prepare("INSERT INTO report_templates (template_key, title, module, audience, description, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($templates as $template) {
        $stmt->execute([...$template, (int) ($reportAdmin['id'] ?? 0)]);
    }
}

if ((int) ($pdo->query('SELECT COUNT(*) FROM report_stakeholder_interests')->fetchColumn() ?: 0) === 0) {
    $interests = [
        ['super_admin', 'Platform health, governance, finance, risk, and RBAC', 'platform', 'high', 'executive_dashboard', 'dashboard,email'],
        ['state_coordinator', 'State/LGA performance, field activity, verification backlog, support escalations', 'registry', 'high', 'state_lga', 'dashboard,email'],
        ['field_agent', 'Assigned growers, visits, verifications, open tasks, evidence quality', 'field', 'high', 'field', 'dashboard'],
        ['grower', 'Farm records, certificates, wallet, orders, support, and academy progress', 'registry', 'medium', 'grower', 'dashboard'],
        ['seller', 'Storefront performance, listings, orders, payouts, disputes, buyer demand', 'marketplace', 'high', 'marketplace', 'dashboard,email'],
        ['learner', 'Course progress, assessments, certificates, payments, refunds, support', 'academy', 'medium', 'academy', 'dashboard'],
        ['support_agent', 'Ticket queue, SLA risk, escalations, user satisfaction, knowledge gaps', 'support', 'high', 'support', 'dashboard'],
    ];
    $stmt = $pdo->prepare("INSERT INTO report_stakeholder_interests (stakeholder_role, interest_area, module, priority, default_report, delivery_channel, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($interests as $interest) {
        $stmt->execute([...$interest, (int) ($reportAdmin['id'] ?? 0)]);
    }
}

if ((int) ($pdo->query('SELECT COUNT(*) FROM report_alerts')->fetchColumn() ?: 0) === 0) {
    $alerts = [
        ['registry', 'high', 'Pending grower applications need review', 'Applications are waiting for admin review and stakeholder follow-up.'],
        ['wallet', 'medium', 'Wallet reconciliation should be checked', 'Run reconciliation and review unmatched transactions.'],
        ['academy', 'medium', 'Academy completion needs monitoring', 'Learners in progress should be nudged toward completion and certificates.'],
        ['support', 'high', 'Open support tickets require SLA attention', 'Support queue has unresolved items that can affect stakeholder confidence.'],
    ];
    $stmt = $pdo->prepare("INSERT INTO report_alerts (alert_ref, module, severity, title, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($alerts as $alert) {
        $stmt->execute([rd_ref('ALERT'), ...$alert, (int) ($reportAdmin['id'] ?? 0)]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = rd_post('action');
    try {
        if ($action === 'generate_report') {
            $pdo->prepare("
                INSERT INTO report_generated_runs (report_ref, report_type, audience, format, date_from, date_to, notes, generated_by)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?)
            ")->execute([rd_ref('RPT'), rd_post('report_type', 'executive_summary'), rd_post('audience', 'management'), rd_post('format', 'csv'), rd_post('date_from'), rd_post('date_to'), rd_post('notes'), (int) ($reportAdmin['id'] ?? 0)]);
            rd_redirect('exports', 'Report generated.');
        }
        if ($action === 'schedule_report') {
            $pdo->prepare("
                INSERT INTO report_schedules (report_name, report_type, frequency, run_time, format, recipients, next_run_at, status, created_by)
                VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''), 'active', ?)
            ")->execute([rd_post('report_name'), rd_post('report_type'), rd_post('frequency', 'weekly'), rd_post('run_time'), rd_post('format', 'csv'), rd_post('recipients'), rd_post('next_run_at'), (int) ($reportAdmin['id'] ?? 0)]);
            rd_redirect('scheduled-reports', 'Report scheduled.');
        }
        if ($action === 'add_data_source') {
            $pdo->prepare("
                INSERT INTO report_data_sources (source_name, source_type, connection_label, sync_frequency, status, last_sync_at, created_by)
                VALUES (?, ?, ?, ?, 'connected', NOW(), ?)
            ")->execute([rd_post('source_name'), rd_post('source_type'), rd_post('connection_label'), rd_post('sync_frequency', 'manual'), (int) ($reportAdmin['id'] ?? 0)]);
            rd_redirect('data-sources', 'Data source added.');
        }
        if ($action === 'grant_report_access') {
            $pdo->prepare("
                INSERT INTO report_access_rules (user_id, access_level, modules, status, granted_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE access_level = VALUES(access_level), modules = VALUES(modules), status = VALUES(status), granted_by = VALUES(granted_by), granted_at = NOW()
            ")->execute([(int) rd_post('user_id'), rd_post('access_level', 'read_only'), rd_post('modules'), rd_post('status', 'active'), (int) ($reportAdmin['id'] ?? 0)]);
            rd_redirect('user-permissions', 'Report access updated.');
        }
        if ($action === 'save_template') {
            $key = strtolower(preg_replace('/[^a-z0-9_-]/', '', rd_post('template_key')));
            if ($key === '') {
                throw new RuntimeException('Template key is required.');
            }
            $pdo->prepare("
                INSERT INTO report_templates (template_key, title, module, audience, description, layout_config, status, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE title = VALUES(title), module = VALUES(module), audience = VALUES(audience), description = VALUES(description), layout_config = VALUES(layout_config), status = VALUES(status), updated_by = VALUES(updated_by), updated_at = NOW()
            ")->execute([$key, rd_post('title'), rd_post('module', 'platform'), rd_post('audience', 'management'), rd_post('description'), rd_post('layout_config'), rd_post('status', 'active'), (int) ($reportAdmin['id'] ?? 0)]);
            rd_redirect('report-templates', 'Report template saved.');
        }
        if ($action === 'save_stakeholder_interest') {
            $pdo->prepare("
                INSERT INTO report_stakeholder_interests (stakeholder_role, interest_area, module, priority, default_report, delivery_channel, status, notes, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([rd_post('stakeholder_role'), rd_post('interest_area'), rd_post('module', 'platform'), rd_post('priority', 'medium'), rd_post('default_report'), rd_post('delivery_channel', 'dashboard'), rd_post('status', 'active'), rd_post('notes'), (int) ($reportAdmin['id'] ?? 0)]);
            rd_redirect('stakeholder-interests', 'Stakeholder interest saved.');
        }
        if ($action === 'save_alert') {
            $pdo->prepare("
                INSERT INTO report_alerts (alert_ref, module, severity, title, description, due_at, status, created_by)
                VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?)
            ")->execute([rd_ref('ALERT'), rd_post('module', 'platform'), rd_post('severity', 'medium'), rd_post('title'), rd_post('description'), rd_post('due_at'), rd_post('status', 'open'), (int) ($reportAdmin['id'] ?? 0)]);
            rd_redirect('exceptions', 'Alert saved.');
        }
        if ($action === 'update_alert_status') {
            $alertId = (int) rd_post('alert_id');
            $status = rd_post('status', 'acknowledged');
            $pdo->prepare("UPDATE report_alerts SET status = ?, resolved_by = IF(? IN ('resolved','closed'), ?, resolved_by), resolved_at = IF(? IN ('resolved','closed'), NOW(), resolved_at) WHERE id = ?")
                ->execute([$status, $status, (int) ($reportAdmin['id'] ?? 0), $status, $alertId]);
            rd_redirect('exceptions', 'Alert updated.');
        }
        if ($action === 'acknowledge_all_alerts') {
            $pdo->prepare("UPDATE report_alerts SET status = 'acknowledged' WHERE status = 'open'")->execute();
            rd_redirect('exceptions', 'Open alerts acknowledged.');
        }
        if ($action === 'update_schedule_status') {
            $scheduleId = (int) rd_post('schedule_id');
            $status = rd_post('status', 'active');
            $pdo->prepare('UPDATE report_schedules SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $scheduleId]);
            rd_redirect('scheduled-reports', 'Schedule updated.');
        }
        if ($action === 'sync_data_source') {
            $sourceId = (int) rd_post('source_id');
            $records = max(0, (int) rd_post('records_count', '0'));
            $pdo->prepare("UPDATE report_data_sources SET records_count = GREATEST(records_count, ?), last_sync_at = NOW(), status = 'connected' WHERE id = ?")->execute([$records, $sourceId]);
            rd_redirect('data-sources', 'Data source synced.');
        }
        if ($action === 'save_settings') {
            $settings = [
                'default_date_range' => rd_post('default_date_range', 'last_7_days'),
                'default_currency' => rd_post('default_currency', 'NGN'),
                'timezone' => rd_post('timezone', 'Africa/Lagos'),
                'auto_refresh' => rd_post('auto_refresh', 'manual'),
                'stakeholder_digest' => rd_post('stakeholder_digest', 'enabled'),
            ];
            $stmt = $pdo->prepare("INSERT INTO report_workspace_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()");
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value, (int) ($reportAdmin['id'] ?? 0)]);
            }
            rd_redirect('settings', 'Report workspace settings saved.');
        }
    } catch (Throwable $e) {
        rd_redirect(rd_post('page', 'overview'), '', $e->getMessage());
    }
}

$requestedPage = preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['page'] ?? 'overview')) ?: 'overview';
$reportNotice = (string) ($_GET['message'] ?? '');
$reportError = (string) ($_GET['error'] ?? '');
$dateFrom = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
$dateTo = (string) ($_GET['to'] ?? date('Y-m-d'));
$dateParams = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

// Optimized metrics collection
$metrics = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'grower' OR platform_role = 'grower') as growers,
        (SELECT COUNT(*) FROM users WHERE (role = 'grower' OR platform_role = 'grower') AND COALESCE(accreditation_status, account_status, '') IN ('accredited','verified','active')) as verifiedGrowers,
        (SELECT COUNT(*) FROM applications) as applications,
        (SELECT COUNT(*) FROM applications WHERE confirmed = 0) as pendingApplications,
        (SELECT COUNT(*) FROM certificates) as certificates,
        (SELECT COUNT(DISTINCT state_id) FROM applications WHERE state_id IS NOT NULL) as statesCovered,
        (SELECT COUNT(*) FROM provider_registry) as providers,
        (SELECT COUNT(*) FROM marketplace_sellers) as sellers,
        (SELECT COUNT(*) FROM marketplace_listings) as listings,
        (SELECT COUNT(*) FROM webinar_registrations) as enrollments,
        (SELECT COUNT(*) FROM webinar_registrations WHERE completion_status = 'completed') as completedEnrollments,
        (SELECT COUNT(*) FROM academy_certificates) as academyCertificates,
        (SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress','pending')) as supportOpen,
        (SELECT COUNT(*) FROM support_tickets WHERE status IN ('resolved','closed')) as supportResolved,
        (SELECT COUNT(*) FROM users WHERE role = 'field_agent' OR is_agronomist = 1 OR is_extensionist = 1) as fieldAgents,
        (SELECT COUNT(*) FROM field_tasks WHERE status IN ('pending','assigned','in_progress')) as fieldTasks,
        (SELECT COUNT(*) FROM report_templates WHERE status = 'active') as activeTemplates,
        (SELECT COUNT(*) FROM report_stakeholder_interests WHERE status = 'active') as activeInterests,
        (SELECT COUNT(*) FROM report_alerts WHERE status IN ('open','acknowledged')) as activeAlerts,
        (SELECT COUNT(*) FROM report_data_sources WHERE status = 'connected') as connectedSources
")->fetch(PDO::FETCH_ASSOC);

$growers = (int) ($metrics['growers'] ?? 0);
$verifiedGrowers = (int) ($metrics['verifiedGrowers'] ?? 0);
$applications = (int) ($metrics['applications'] ?? 0);
$pendingApplications = (int) ($metrics['pendingApplications'] ?? 0);
$certificates = (int) ($metrics['certificates'] ?? 0);
$statesCovered = (int) ($metrics['statesCovered'] ?? 0);
$providers = (int) ($metrics['providers'] ?? 0);
$sellers = (int) ($metrics['sellers'] ?? 0);
$listings = (int) ($metrics['listings'] ?? 0);
$enrollments = (int) ($metrics['enrollments'] ?? 0);
$completedEnrollments = (int) ($metrics['completedEnrollments'] ?? 0);
$academyCertificates = (int) ($metrics['academyCertificates'] ?? 0);
$supportOpen = (int) ($metrics['supportOpen'] ?? 0);
$supportResolved = (int) ($metrics['supportResolved'] ?? 0);
$fieldAgents = (int) ($metrics['fieldAgents'] ?? 0);
$fieldTasks = (int) ($metrics['fieldTasks'] ?? 0);
$activeTemplates = (int) ($metrics['activeTemplates'] ?? 0);
$activeInterests = (int) ($metrics['activeInterests'] ?? 0);
$activeAlerts = (int) ($metrics['activeAlerts'] ?? 0);
$connectedSources = (int) ($metrics['connectedSources'] ?? 0);

$stmtOrders = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE created_at BETWEEN ? AND ?");
$stmtOrders->execute($dateParams);
[$orders, $gmv] = $stmtOrders->fetch(PDO::FETCH_NUM);

$stmtWallet = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) FROM wallet_transactions WHERE created_at BETWEEN ? AND ?");
$stmtWallet->execute($dateParams);
$walletVolume = (float) $stmtWallet->fetchColumn();

$walletBalance = rd_scalar($pdo, "SELECT COALESCE(SUM(balance), 0) FROM wallets");
$fieldVisits = (int) rd_scalar($pdo, "SELECT COUNT(*) FROM farm_visits WHERE visited_at BETWEEN ? AND ?", $dateParams);

$verificationRate = rd_pct($verifiedGrowers, max(1, $growers));
$academyCompletion = rd_pct($completedEnrollments, max(1, $enrollments));
$slaCompliance = rd_pct($supportResolved, max(1, $supportResolved + $supportOpen));

$stateRows = rd_rows($pdo, "
    SELECT COALESCE(ns.state_name, 'Unassigned') state_name,
           COUNT(DISTINCT u.id) growers,
           SUM(CASE WHEN COALESCE(u.accreditation_status, u.account_status, '') IN ('accredited','verified','active') THEN 1 ELSE 0 END) verified
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    WHERE u.role = 'grower' OR u.platform_role = 'grower'
    GROUP BY COALESCE(ns.state_name, 'Unassigned')
    ORDER BY growers DESC
    LIMIT 10
");
$certificateRows = rd_rows($pdo, "
    SELECT COALESCE(ns.state_name, 'Unassigned') state_name,
           COUNT(c.id) issued,
           SUM(CASE WHEN c.status IN ('valid','active','issued') THEN 1 ELSE 0 END) valid,
           SUM(CASE WHEN c.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) expiring,
           SUM(CASE WHEN c.expires_at < CURDATE() THEN 1 ELSE 0 END) expired,
           SUM(CASE WHEN c.status = 'revoked' THEN 1 ELSE 0 END) revoked
    FROM certificates c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    GROUP BY COALESCE(ns.state_name, 'Unassigned')
    ORDER BY issued DESC
    LIMIT 10
");
$sellerRows = rd_rows($pdo, "
    SELECT ms.store_name, COUNT(DISTINCT ml.id) products, COUNT(DISTINCT mo.id) orders, COALESCE(SUM(mo.total_amount), 0) revenue
    FROM marketplace_sellers ms
    LEFT JOIN marketplace_listings ml ON ml.seller_id = ms.id
    LEFT JOIN marketplace_orders mo ON mo.seller_id = ms.id AND mo.created_at BETWEEN ? AND ?
    GROUP BY ms.id, ms.store_name
    ORDER BY revenue DESC, products DESC
    LIMIT 10
", $dateParams);
$productRows = rd_rows($pdo, "
    SELECT COALESCE(ml.title, 'Product') product_name, COUNT(mo.id) units, COALESCE(SUM(mo.total_amount), 0) revenue
    FROM marketplace_listings ml
    LEFT JOIN marketplace_orders mo ON mo.listing_id = ml.id AND mo.created_at BETWEEN ? AND ?
    GROUP BY ml.id, COALESCE(ml.title, 'Product')
    ORDER BY revenue DESC, units DESC
    LIMIT 8
", $dateParams);
$walletRows = rd_rows($pdo, "
    SELECT wt.*, u.name user_name, u.email user_email
    FROM wallet_transactions wt
    LEFT JOIN users u ON u.id = wt.user_id
    ORDER BY wt.created_at DESC, wt.id DESC
    LIMIT 12
");
$courseRows = rd_rows($pdo, "
    SELECT w.title course_title, COUNT(r.id) enrolled, SUM(CASE WHEN r.completion_status = 'completed' THEN 1 ELSE 0 END) completed
    FROM webinars w
    LEFT JOIN webinar_registrations r ON r.webinar_id = w.id
    GROUP BY w.id, w.title
    ORDER BY enrolled DESC
    LIMIT 10
");
$supportRows = rd_rows($pdo, "
    SELECT category, COUNT(*) opened,
           SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) resolved,
           SUM(CASE WHEN status NOT IN ('resolved','closed') THEN 1 ELSE 0 END) pending
    FROM support_tickets
    GROUP BY category
    ORDER BY opened DESC
    LIMIT 10
");
$agentRows = rd_rows($pdo, "
    SELECT u.name agent, COALESCE(sp.state, u.location, '-') state,
           COUNT(DISTINCT fv.id) visits,
           COUNT(DISTINCT ft.id) verifications
    FROM users u
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    LEFT JOIN farm_visits fv ON fv.agent_id = u.id
    LEFT JOIN field_tasks ft ON ft.assigned_to = u.id
    WHERE u.role = 'field_agent' OR u.is_agronomist = 1 OR u.is_extensionist = 1
    GROUP BY u.id, u.name, COALESCE(sp.state, u.location, '-')
    ORDER BY visits DESC, verifications DESC
    LIMIT 10
");
$schedules = rd_rows($pdo, "SELECT * FROM report_schedules ORDER BY FIELD(status,'active','paused','inactive'), next_run_at ASC LIMIT 20");
$runs = rd_rows($pdo, "SELECT rr.*, u.name generated_by_name FROM report_generated_runs rr LEFT JOIN users u ON u.id = rr.generated_by ORDER BY rr.generated_at DESC LIMIT 30");
$sources = rd_rows($pdo, "SELECT * FROM report_data_sources ORDER BY source_name");
$templates = rd_rows($pdo, "SELECT rt.*, u.name updated_by_name FROM report_templates rt LEFT JOIN users u ON u.id = rt.updated_by ORDER BY FIELD(rt.status,'active','draft','inactive'), rt.module, rt.title LIMIT 80");
$alerts = rd_rows($pdo, "SELECT ra.*, u.name created_by_name FROM report_alerts ra LEFT JOIN users u ON u.id = ra.created_by ORDER BY FIELD(ra.status,'open','acknowledged','resolved','closed'), FIELD(ra.severity,'critical','high','medium','low'), ra.created_at DESC LIMIT 80");
$interests = rd_rows($pdo, "SELECT rsi.*, u.name updated_by_name FROM report_stakeholder_interests rsi LEFT JOIN users u ON u.id = rsi.updated_by ORDER BY FIELD(rsi.priority,'critical','high','medium','low'), rsi.stakeholder_role, rsi.module LIMIT 120");
$settingsRows = rd_rows($pdo, "SELECT * FROM report_workspace_settings ORDER BY setting_key");
$reportUsers = rd_rows($pdo, "
    SELECT u.id, u.name, u.email, u.role, u.platform_role, u.account_status, u.created_at, rar.access_level, rar.modules, rar.status report_status
    FROM users u
    LEFT JOIN report_access_rules rar ON rar.user_id = u.id
    WHERE u.role IN ('admin','field_agent','seller','provider','grower') OR u.platform_role IS NOT NULL
    ORDER BY FIELD(u.role,'admin','field_agent','provider','seller','grower'), u.name
    LIMIT 80
");
$userOptions = rd_rows($pdo, "SELECT id, name, email, role, platform_role FROM users ORDER BY name LIMIT 300");

$export = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['export'] ?? ''));
if ($export !== '') {
    $rows = match ($export) {
        'marketplace' => $sellerRows,
        'wallet' => $walletRows,
        'academy' => $courseRows,
        'support' => $supportRows,
        'field' => $agentRows,
        'schedules' => $schedules,
        default => $stateRows,
    };
    app_export_csv('natcodev-' . $export . '-report-' . date('Ymd') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}

$reportPayload = [
    'page' => $requestedPage,
    'notice' => $reportNotice,
    'error' => $reportError,
    'dateFrom' => $dateFrom,
    'dateTo' => $dateTo,
    'admin' => ['name' => (string) ($reportAdmin['name'] ?? 'Admin')],
    'metrics' => compact('growers', 'verifiedGrowers', 'applications', 'pendingApplications', 'certificates', 'statesCovered', 'providers', 'sellers', 'listings', 'orders', 'gmv', 'walletVolume', 'walletBalance', 'enrollments', 'completedEnrollments', 'academyCertificates', 'supportOpen', 'supportResolved', 'fieldAgents', 'fieldVisits', 'fieldTasks', 'activeTemplates', 'activeInterests', 'activeAlerts', 'connectedSources', 'verificationRate', 'academyCompletion', 'slaCompliance'),
    'states' => $stateRows,
    'certificates' => $certificateRows,
    'sellers' => $sellerRows,
    'products' => $productRows,
    'walletTransactions' => $walletRows,
    'courses' => $courseRows,
    'support' => $supportRows,
    'agents' => $agentRows,
    'schedules' => $schedules,
    'runs' => $runs,
    'sources' => $sources,
    'templates' => $templates,
    'alerts' => $alerts,
    'interests' => $interests,
    'settings' => $settingsRows,
    'reportUsers' => $reportUsers,
    'users' => $userOptions,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Reports - Intelligence Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --g900:#0a2418;--g800:#0f3324;--g700:#164a33;--g600:#1e6b47;--g500:#2a9d6a;--g400:#34c48a;--g300:#5dd8a3;--g200:#a8e6c9;--g100:#d4f5e4;--g50:#eefbf4;
  --bg:#f4f6f4;--card:#fff;--text:#1a1a1a;--text2:#6b7280;--border:#e5e7eb;
  --danger:#dc2626;--warn:#f59e0b;--info:#3b82f6;--success:#10b981;--purple:#8b5cf6;--orange:#f97316;--pink:#ec4899;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:13px}
.sidebar{width:260px;background:var(--g900);color:#fff;position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:100;display:flex;flex-direction:column}
.sidebar-header{padding:16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo{width:44px;height:44px;background:var(--g400);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:10px;color:var(--g900);flex-shrink:0;text-align:center;line-height:1.1}
.sidebar-brand{font-size:14px;font-weight:700;line-height:1.2}
.sidebar-brand small{display:block;font-size:9px;font-weight:400;opacity:.7;margin-top:2px;line-height:1.3}
.workspace-badge{margin:14px 16px 4px;padding:5px 10px;background:rgba(255,255,255,.08);border-radius:6px;font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.6}
.workspace-select{margin:0 16px 12px;padding:10px 12px;background:rgba(255,255,255,.06);border-radius:8px;display:flex;align-items:center;justify-content:space-between;font-size:13px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.1)}
.nav-section{padding:6px 0}
.nav-section-title{padding:0 16px;font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.5;margin-bottom:4px}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 16px;cursor:pointer;transition:all .15s;font-size:13px;color:rgba(255,255,255,.75);border-left:3px solid transparent}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:var(--g600);color:#fff;border-left-color:var(--g400)}
.nav-item svg{width:17px;height:17px;flex-shrink:0}
.nav-item .badge{margin-left:auto;background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.nav-group-header{display:flex;align-items:center;justify-content:space-between;padding:9px 16px;cursor:pointer;font-size:13px;color:rgba(255,255,255,.75)}
.nav-group-header:hover{color:#fff}
.nav-group-header svg.arrow{width:14px;height:14px;transition:transform .2s}
.nav-group.open .nav-group-header svg.arrow{transform:rotate(90deg)}
.nav-sub{display:none}
.nav-group.open .nav-sub{display:block}
.nav-sub .nav-item{padding-left:44px;font-size:12px}
.sidebar-footer{margin-top:auto;padding:14px 16px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px}
.sidebar-avatar{width:38px;height:38px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.sidebar-user{font-size:13px;font-weight:600}
.sidebar-user small{display:block;font-size:11px;opacity:.6;font-weight:400}
.status-dot{width:7px;height:7px;background:var(--success);border-radius:50%;display:inline-block;margin-right:3px}
.shortcuts-box{margin:12px 16px;padding:14px;background:rgba(255,255,255,.04);border-radius:10px;border:1px solid rgba(255,255,255,.08)}
.shortcuts-title{font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.6;margin-bottom:10px}
.shortcut-item{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:6px;cursor:pointer;font-size:12px;color:rgba(255,255,255,.8);transition:background .15s}
.shortcut-item:hover{background:rgba(255,255,255,.08)}
.shortcut-item svg{width:14px;height:14px;opacity:.7}

.main{margin-left:260px;flex:1;min-height:100vh}
.topbar{background:#fff;padding:12px 24px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.menu-toggle{display:none;background:none;border:none;cursor:pointer;font-size:20px;color:var(--text)}
.topbar-search{flex:1;max-width:480px;position:relative}
.topbar-search input{width:100%;padding:9px 14px 9px 38px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg)}
.topbar-search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text2)}
.topbar-kbd{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--text2);background:#fff;padding:2px 6px;border:1px solid var(--border);border-radius:4px}
.topbar-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-icon{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;background:#fff}
.topbar-icon .dot{position:absolute;top:5px;right:5px;width:8px;height:8px;background:var(--danger);border-radius:50%;border:2px solid #fff}
.quick-actions-btn{padding:8px 14px;background:#fff;border:1px solid var(--border);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px}
.quick-actions-btn:hover{background:var(--bg)}
.topbar-profile{display:flex;align-items:center;gap:10px;min-width:0;max-width:260px;cursor:pointer;padding:4px 10px 4px 6px;border-radius:8px}
.topbar-profile:hover{background:var(--bg)}
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px}
.topbar-profile-info{display:flex;min-width:0;max-width:160px;flex-direction:column;align-items:flex-start;font-size:13px;font-weight:700;line-height:1.15;text-align:left}
.topbar-profile-info,.topbar-profile-info small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.topbar-profile-info small{display:block;max-width:100%;margin-top:2px;font-size:11px;color:var(--text2);font-weight:500}
.topbar-menu-wrap{position:relative}.topbar-icon{color:var(--text);text-decoration:none}.topbar-menu{display:none;position:absolute;right:0;top:48px;width:270px;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,.12);padding:8px;z-index:90}.topbar-menu.active{display:block}.topbar-menu a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 10px;border-radius:8px;color:var(--text);text-decoration:none;font-weight:650}.topbar-menu a:hover{background:var(--bg)}.topbar-menu small{display:block;color:var(--text2);font-weight:500;margin-top:2px}.topbar-menu-label{padding:6px 10px 8px;color:var(--text2);font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:800}.topbar-profile{background:none;border:0;color:var(--text);font:inherit}.topbar-avatar{overflow:hidden}.topbar-avatar img{width:100%;height:100%;object-fit:cover;display:block}

.content{padding:22px}
.page{display:none}
.page.active{display:block}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700}
.page-subtitle{font-size:13px;color:var(--text2);margin-top:2px}
.btn{padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s}
.btn-primary{background:var(--g700);color:#fff}
.btn-primary:hover{background:var(--g800)}
.btn-secondary{background:#fff;color:var(--text);border:1px solid var(--border)}
.btn-secondary:hover{background:var(--bg)}
.btn-danger{background:var(--danger);color:#fff}
.btn-warn{background:var(--warn);color:#fff}
.btn-success{background:var(--success);color:#fff}
.btn-info{background:var(--info);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-icon{padding:6px;background:none;border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:14px}
.btn-ghost{background:transparent;border:none;color:var(--g700);font-weight:600}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px}
.stat-card{background:#fff;padding:18px;border-radius:12px;border:1px solid var(--border)}
.stat-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.stat-card-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px}
.stat-card-label{font-size:11px;color:var(--text2);font-weight:500;text-transform:uppercase;letter-spacing:.3px}
.stat-card-value{font-size:24px;font-weight:700;margin-top:4px}
.stat-card-change{font-size:11px;margin-top:6px;font-weight:500}
.stat-card-change.up{color:var(--success)}
.stat-card-change.down{color:var(--danger)}
.stat-card-sub{font-size:11px;color:var(--text2);margin-top:2px}

.card{background:#fff;border-radius:12px;border:1px solid var(--border);margin-bottom:18px}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-title{font-size:15px;font-weight:700}
.card-body{padding:20px}
.card-body.p0{padding:0}

table{width:100%;border-collapse:collapse}
th,td{padding:11px 18px;text-align:left;font-size:12.5px}
th{background:var(--bg);font-weight:600;color:var(--text2);font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
td{border-bottom:1px solid var(--border)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--g50)}

.status-badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.status-success,.status-completed,.status-active,.status-verified,.status-valid,.status-approved,.status-live,.status-reconciled{background:#dcfce7;color:#166534}
.status-pending,.status-review,.status-processing,.status-scheduled{background:#fef3c7;color:#92400e}
.status-info,.status-credit{background:#dbeafe;color:#1e40af}
.status-draft,.status-inactive{background:#f3f4f6;color:#4b5563}
.status-danger,.status-cancelled,.status-rejected,.status-failed,.status-revoked,.status-open,.status-high-risk{background:#fee2e2;color:#991b1b}
.status-warn,.status-expiring{background:#fff7ed;color:#c2410c}

.progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;width:100%}
.progress-fill{height:100%;background:var(--g500);border-radius:3px;transition:width .3s}

.form-group{margin-bottom:14px}
.form-label{display:block;font-size:11.5px;font-weight:600;margin-bottom:6px;color:var(--text2);text-transform:uppercase;letter-spacing:.3px}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--g500);box-shadow:0 0 0 3px rgba(42,157,106,.1)}
.form-textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}

.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:18px;overflow-x:auto}
.tab{padding:10px 16px;font-size:13px;font-weight:500;cursor:pointer;border-bottom:2px solid transparent;color:var(--text2);white-space:nowrap}
.tab.active{color:var(--g700);border-bottom-color:var(--g700);font-weight:600}
.tab:hover{color:var(--text)}

.filter-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:12.5px}

.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal{background:#fff;border-radius:12px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto}
.modal-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:16px;font-weight:700}
.modal-body{padding:22px}
.modal-footer{padding:16px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}

.avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--g100);color:var(--g700);display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:11px;flex-shrink:0}
.avatar-row{display:flex;align-items:center;gap:10px}

.toast{position:fixed;bottom:24px;right:24px;background:var(--g800);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:300;display:none;animation:slideIn .3s}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}

.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:var(--g100);color:var(--g700);border-radius:20px;font-size:11px;font-weight:500}
.chip-warn{background:#fef3c7;color:#92400e}
.chip-danger{background:#fee2e2;color:#991b1b}

.heatmap-cell{padding:6px 10px;border-radius:4px;text-align:center;font-size:11px;font-weight:600;color:#fff}
.heatmap-high{background:#164a33}
.heatmap-med{background:#2a9d6a}
.heatmap-low{background:#a8e6c9;color:#0a2418}
.heatmap-bad{background:#fee2e2;color:#991b1b}

.alert-card{padding:14px;border-radius:10px;border-left:4px solid;display:flex;gap:12px;align-items:start;margin-bottom:10px}
.alert-card.high{background:#fef2f2;border-color:var(--danger)}
.alert-card.medium{background:#fff7ed;border-color:var(--warn)}
.alert-card.low{background:#eff6ff;border-color:var(--info)}

.compliance-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
.compliance-row:last-child{border-bottom:none}
.compliance-label{flex:1;font-size:12.5px;font-weight:500}
.compliance-bar{flex:2;height:8px;background:var(--border);border-radius:4px;overflow:hidden}
.compliance-fill{height:100%;background:var(--g500);border-radius:4px}
.compliance-value{font-size:12.5px;font-weight:700;width:50px;text-align:right}

.funnel-stage{display:flex;align-items:center;gap:14px;padding:10px 0}
.funnel-bar{height:28px;border-radius:4px;display:flex;align-items:center;padding:0 12px;color:#fff;font-size:12px;font-weight:600}
.funnel-label{width:120px;font-size:12.5px;font-weight:500}
.funnel-value{width:100px;text-align:right;font-size:12.5px;font-weight:700}

.insight-card{padding:14px;border:1px solid var(--border);border-radius:10px;display:flex;gap:12px;align-items:start;margin-bottom:10px;cursor:pointer;transition:all .15s}
.insight-card:hover{border-color:var(--g500);background:var(--g50)}
.insight-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}

@media(max-width:1100px){.grid-4{grid-template-columns:repeat(2,1fr)}.grid-3{grid-template-columns:1fr 1fr}}
@media(max-width:900px){
  .sidebar{width:70px}.sidebar-brand,.workspace-badge,.workspace-select span,.nav-section-title,.nav-item span:not(.badge),.sidebar-user,.sidebar-user small,.nav-item .badge,.nav-group-header span,.shortcuts-box{display:none}
  .nav-item{justify-content:center;padding:12px}.nav-sub .nav-item{padding-left:12px}
  .main{margin-left:70px}.grid-2,.grid-3,.grid-4,.form-row,.form-row-3{grid-template-columns:1fr}
  .menu-toggle{display:block}.topbar-kbd{display:none}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo"><br>NAT<br>CODEV</div>
    <div class="sidebar-brand">NATCODEV<small>Coconut Development &<br>Propagation Initiative</small></div>
  </div>
  <div class="workspace-badge">REPORTS WORKSPACE</div>
  <div class="workspace-select"><span>📊 Reports</span><span>▾</span></div>

  <div class="nav-section">
    <div class="nav-item active" data-page="overview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Overview</span>
    </div>
    <div class="nav-group open">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:11px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Registry Reports</span></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="grower-report"><span>Grower Analytics</span></div>
        <div class="nav-item" data-page="verification-report"><span>Verification Report</span></div>
        <div class="nav-item" data-page="certificate-report"><span>Certificate Report</span></div>
      </div>
    </div>
    <div class="nav-item" data-page="state-lga">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      <span>State & LGA</span>
    </div>
    <div class="nav-item" data-page="marketplace-report">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
      <span>Marketplace Reports</span>
    </div>
    <div class="nav-item" data-page="wallet-finance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      <span>Wallet & Finance</span>
    </div>
    <div class="nav-item" data-page="academy-report">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      <span>Academy Reports</span>
    </div>
    <div class="nav-item" data-page="support-sla">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span>Support SLA</span>
    </div>
    <div class="nav-item" data-page="field-operations">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg>
      <span>Field Operations</span>
    </div>
    <div class="nav-item" data-page="compliance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>Compliance</span>
    </div>
    <div class="nav-item" data-page="exports">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span>Exports</span>
    </div>
    <div class="nav-item" data-page="scheduled-reports">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span>Scheduled Reports</span>
    </div>
    <div class="nav-item" data-page="intelligence">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
      <span>Intelligence & Insights</span>
    </div>
    <div class="nav-item" data-page="stakeholder-interests">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      <span>Stakeholder Interests</span>
    </div>
    <div class="nav-item" data-page="exceptions">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Exceptions & Alerts</span>
      <span class="badge">12</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-group">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:11px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg><span>Settings</span></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="settings"><span>General</span></div>
        <div class="nav-item" data-page="report-templates"><span>Report Templates</span></div>
        <div class="nav-item" data-page="data-sources"><span>Data Sources</span></div>
        <div class="nav-item" data-page="user-permissions"><span>User Permissions</span></div>
      </div>
    </div>
  </div>

  <div class="shortcuts-box">
    <div class="shortcuts-title">Report Shortcuts</div>
    <div class="shortcut-item" onclick="navigateTo('scheduled-reports')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>Daily Operations Brief</div>
    <div class="shortcut-item" onclick="navigateTo('scheduled-reports')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg>Weekly Executive Summary</div>
    <div class="shortcut-item" onclick="navigateTo('state-lga')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/></svg>State Performance Report</div>
    <div class="shortcut-item" onclick="navigateTo('compliance')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Compliance Dashboard</div>
    <div class="shortcut-item" onclick="navigateTo('support-sla')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>SLA Performance Report</div>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar">GD</div>
    <div class="sidebar-user">Grace Deh<small><span class="status-dot"></span>Super Admin • Online</small></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('collapsed')">☰</button>
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search reports, metrics, datasets, documents..." id="globalSearch">
      <span class="topbar-kbd">CTRL + K</span>
    </div>
    <div class="topbar-actions">
      <a class="topbar-icon" href="<?= rd_e($reportAdminBase) ?>/index.php" title="Workspace Hub">⌂</a>
      <a class="topbar-icon" href="<?= rd_e($reportPublicBase) ?>/index.php" title="Public Homepage">↗</a>
      <a class="topbar-icon" href="<?= rd_e($reportAdminBase) ?>/notifications.php" title="Notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><span class="dot"></span></a>
      <a class="topbar-icon" href="<?= rd_e($reportAdminBase) ?>/support.php" title="Messages"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span class="dot"></span></a>
      <div class="topbar-menu-wrap">
        <button class="quick-actions-btn" type="button" data-topbar-menu="reportActionsMenu"> Quick Actions ▾</button>
        <div class="topbar-menu" id="reportActionsMenu">
          <div class="topbar-menu-label">Reports</div>
          <a href="<?= rd_e($reportAdminBase) ?>/reports.php?page=exports"><span>Exports</span></a>
          <a href="<?= rd_e($reportAdminBase) ?>/reports.php?page=report-templates"><span>Templates</span></a>
          <a href="<?= rd_e($reportAdminBase) ?>/reports.php?page=audit-logs"><span>Audit Logs</span></a>
        </div>
      </div>
      <div class="topbar-menu-wrap">
        <button class="topbar-profile" type="button" data-topbar-menu="profileMenu" aria-haspopup="true" aria-expanded="false">
          <div class="topbar-avatar"><?php if ($adminPictureUrl !== ''): ?><img src="<?= rd_e($adminPictureUrl) ?>" alt=""><?php else: ?><?= rd_e($adminInitials) ?><?php endif; ?></div>
          <div class="topbar-profile-info"><?= rd_e($adminDisplayName) ?><small><?= rd_e($adminDisplayRole) ?></small></div>
        </button>
        <div class="topbar-menu" id="profileMenu">
          <div class="topbar-menu-label">Profile</div>
          <a href="<?= rd_e($reportAdminBase) ?>/profile.php"><span>Edit Profile<small>Photo, name, contact</small></span></a>
          <a href="<?= rd_e($reportAdminBase) ?>/index.php"><span>Workspace Hub</span></a>
          <a href="<?= rd_e($reportPublicBase) ?>/index.php"><span>Public Homepage</span></a>
          <a href="<?= rd_e($reportAdminBase) ?>/index.php?logout=1"><span>Logout from workspace</span></a>
          <a href="<?= rd_e($reportAdminBase) ?>/admin.php?logout=1"><span>Logout via legacy admin</span></a>
          <a href="<?= rd_e($reportAdminBase) ?>/login.php?logout=1"><span>Logout to login</span></a>
        </div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- OVERVIEW -->
    <div class="page active" id="page-overview">
      <div class="page-header">
        <div><div class="page-title">NATCODEV Reports</div><div class="page-subtitle">Intelligence and analytics across all platform operations and impact.</div></div>
        <div style="display:flex;gap:10px">
          <button class="btn btn-secondary btn-sm"> Save View</button>
          <button class="btn btn-secondary btn-sm">⬇ Export Dashboard ▾</button>
          <button class="btn btn-primary" onclick="openModal('generateReportModal')">📊 Generate Report</button>
        </div>
      </div>

      <div class="filter-bar" style="background:#fff;padding:14px;border-radius:10px;border:1px solid var(--border);margin-bottom:20px">
        <div class="form-group" style="margin:0;flex:1;min-width:180px"><label class="form-label">Date Range</label><input class="form-input" type="text" value="May 18 – May 24, 2026" style="padding:8px 10px"></div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px"><label class="form-label">State</label><select class="form-select" style="padding:8px 10px"><option>All States</option><option>Lagos</option><option>Kano</option><option>Ogun</option></select></div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px"><label class="form-label">LGA</label><select class="form-select" style="padding:8px 10px"><option>All LGAs</option></select></div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px"><label class="form-label">Role / Audience</label><select class="form-select" style="padding:8px 10px"><option>All Roles</option><option>Admin</option><option>Field Agent</option><option>Seller</option></select></div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px"><label class="form-label">Module</label><select class="form-select" style="padding:8px 10px"><option>All Modules</option><option>Registry</option><option>Marketplace</option><option>Academy</option><option>Wallet</option></select></div>
        <button class="btn btn-secondary btn-sm" style="margin-top:16px">🔽 More Filters</button>
        <button class="btn-ghost btn-sm" style="margin-top:16px">Clear Filters</button>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Verified Growers</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">👥</div></div><div class="stat-card-value">64,907</div><div class="stat-card-change up">↑ 12.4% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">State Coverage</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">📍</div></div><div class="stat-card-value">36 / 36</div><div class="stat-card-sub" style="color:var(--success)">100% states active</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Marketplace GMV (7D)</div><div class="stat-card-icon" style="background:#fef3c7;color:#92400e"></div></div><div class="stat-card-value">₦24,531,670</div><div class="stat-card-change up">↑ 18.6% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Wallet Volume (7D)</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">💰</div></div><div class="stat-card-value">₦32,845,210</div><div class="stat-card-change up">↑ 21.3% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Academy Completion</div><div class="stat-card-icon" style="background:#ede9fe;color:#5b21b6">🎓</div></div><div class="stat-card-value">68.4%</div><div class="stat-card-change up">↑ 9.5% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">SLA Compliance</div><div class="stat-card-icon" style="background:#dbeafe;color:#1e40af">✓</div></div><div class="stat-card-value">92.6%</div><div class="stat-card-change up">↑ 6.7% vs last 7 days</div></div>
      </div>

      <div class="grid-2" style="grid-template-columns:1fr 1fr 1fr">
        <div class="card" style="grid-column:span 1">
          <div class="card-header"><div class="card-title">State Performance Overview</div></div>
          <div class="card-body">
            <div style="background:linear-gradient(135deg,var(--g100) 0%,var(--g200) 100%);border-radius:10px;padding:20px;min-height:200px;position:relative">
              <div style="position:absolute;top:10px;right:10px;font-size:10px;background:#fff;padding:4px 8px;border-radius:4px"><strong>Performance Score</strong><br>80-100: <span style="color:var(--g700)">■</span> 60-79: <span style="color:var(--g500)">■</span> 40-59: <span style="color:var(--g300)">■</span> 20-39: <span style="color:#d1d5db">■</span> 0-19: <span style="color:#9ca3af">■</span></div>
              <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:4px;margin-top:30px">
                <div style="aspect-ratio:1;background:var(--g700);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g600);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g500);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g700);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g600);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g500);border-radius:3px"></div>
                <div style="aspect-ratio:1;background:var(--g500);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g700);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g600);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g500);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g700);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g600);border-radius:3px"></div>
                <div style="aspect-ratio:1;background:var(--g600);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g500);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g700);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g600);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g500);border-radius:3px"></div><div style="aspect-ratio:1;background:var(--g700);border-radius:3px"></div>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;font-size:12px">
              <div><div style="font-weight:600;margin-bottom:6px;color:var(--g700)">Top Performing States</div><div style="display:flex;align-items:center;gap:6px;margin-bottom:4px"><span style="background:var(--g700);color:#fff;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px">1</span>Lagos <span style="margin-left:auto;font-weight:600">92.4%</span></div><div style="display:flex;align-items:center;gap:6px;margin-bottom:4px"><span style="background:var(--g600);color:#fff;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px">2</span>Oyo <span style="margin-left:auto;font-weight:600">88.7%</span></div><div style="display:flex;align-items:center;gap:6px"><span style="background:var(--g500);color:#fff;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px">3</span>Cross River <span style="margin-left:auto;font-weight:600">86.1%</span></div></div>
              <div><div style="font-weight:600;margin-bottom:6px;color:var(--danger)">Lowest Performing States</div><div style="display:flex;align-items:center;gap:6px;margin-bottom:4px"><span style="background:#fee2e2;color:var(--danger);width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px">34</span>Yobe <span style="margin-left:auto;font-weight:600">28.3%</span></div><div style="display:flex;align-items:center;gap:6px;margin-bottom:4px"><span style="background:#fee2e2;color:var(--danger);width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px">35</span>Taraba <span style="margin-left:auto;font-weight:600">31.7%</span></div><div style="display:flex;align-items:center;gap:6px"><span style="background:#fff7ed;color:var(--warn);width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px">36</span>Zamfara <span style="margin-left:auto;font-weight:600">33.9%</span></div></div>
            </div>
            <button class="btn-ghost btn-sm" style="margin-top:10px" onclick="navigateTo('state-lga')">View State & LGA Report →</button>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Platform Activity Trend</div><div style="display:flex;gap:4px;background:var(--bg);padding:3px;border-radius:6px"><button class="btn btn-sm" style="background:var(--g700);color:#fff">Daily</button><button class="btn btn-sm btn-secondary">Weekly</button><button class="btn btn-sm btn-secondary">Monthly</button></div></div>
          <div class="card-body">
            <div style="display:flex;gap:16px;margin-bottom:12px;font-size:11px;flex-wrap:wrap">
              <div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:3px;background:var(--g700);border-radius:2px"></span>Growers</div>
              <div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:3px;background:var(--orange);border-radius:2px"></span>Orders</div>
              <div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:3px;background:var(--info);border-radius:2px"></span>Transactions</div>
              <div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:3px;background:var(--purple);border-radius:2px"></span>Enrollments</div>
            </div>
            <div style="height:220px;position:relative;border-left:1px solid var(--border);border-bottom:1px solid var(--border);padding:10px 0">
              <div style="position:absolute;left:-30px;top:10px;font-size:10px;color:var(--text2)">50K</div>
              <div style="position:absolute;left:-30px;top:50px;font-size:10px;color:var(--text2)">40K</div>
              <div style="position:absolute;left:-30px;top:90px;font-size:10px;color:var(--text2)">30K</div>
              <div style="position:absolute;left:-30px;top:130px;font-size:10px;color:var(--text2)">20K</div>
              <div style="position:absolute;left:-30px;top:170px;font-size:10px;color:var(--text2)">10K</div>
              <svg width="100%" height="100%" viewBox="0 0 500 200" preserveAspectRatio="none">
                <polyline points="20,40 90,50 160,45 230,55 300,50 370,45 440,40" fill="none" stroke="var(--g700)" stroke-width="2.5"/>
                <polyline points="20,100 90,95 160,85 230,80 300,75 370,70 440,65" fill="none" stroke="var(--orange)" stroke-width="2.5"/>
                <polyline points="20,140 90,130 160,120 230,115 300,110 370,105 440,95" fill="none" stroke="var(--info)" stroke-width="2.5"/>
                <polyline points="20,170 90,165 160,160 230,155 300,150 370,145 440,140" fill="none" stroke="var(--purple)" stroke-width="2.5"/>
              </svg>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text2);margin-top:6px"><span>May 18</span><span>May 19</span><span>May 20</span><span>May 21</span><span>May 22</span><span>May 23</span><span>May 24</span></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Compliance Scorecards</div><button class="btn-ghost btn-sm" onclick="navigateTo('compliance')">View All</button></div>
          <div class="card-body">
            <div class="compliance-row"><div class="compliance-label">Data Quality Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:94.2%"></div></div><div class="compliance-value">94.2%</div></div>
            <div class="compliance-row"><div class="compliance-label">KYC Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:91.1%"></div></div><div class="compliance-value">91.1%</div></div>
            <div class="compliance-row"><div class="compliance-label">Document Verification</div><div class="compliance-bar"><div class="compliance-fill" style="width:89.3%"></div></div><div class="compliance-value">89.3%</div></div>
            <div class="compliance-row"><div class="compliance-label">Training Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:86.7%;background:var(--warn)"></div></div><div class="compliance-value" style="color:var(--warn)">86.7%</div></div>
            <div class="compliance-row"><div class="compliance-label">Financial Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:93.8%"></div></div><div class="compliance-value">93.8%</div></div>
            <div class="compliance-row"><div class="compliance-label">Marketplace Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:90.4%"></div></div><div class="compliance-value">90.4%</div></div>
            <button class="btn-ghost btn-sm" style="margin-top:10px" onclick="navigateTo('compliance')">View Compliance Report →</button>
          </div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Marketplace Revenue (7D)</div><button class="btn-ghost btn-sm" onclick="navigateTo('marketplace-report')">View Report</button></div>
          <div class="card-body">
            <div style="margin-bottom:14px"><div style="font-size:28px;font-weight:700">₦24,531,670</div><div style="font-size:12px;color:var(--success)">↑ 18.6% vs last 7 days</div></div>
            <div style="display:flex;align-items:end;gap:10px;height:160px">
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><div style="width:100%;background:var(--info);border-radius:4px 4px 0 0;height:80px"></div><div style="font-size:10px;color:var(--text2)">May 18</div></div>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><div style="width:100%;background:var(--info);border-radius:4px 4px 0 0;height:60px"></div><div style="font-size:10px;color:var(--text2)">May 19</div></div>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><div style="width:100%;background:var(--info);border-radius:4px 4px 0 0;height:90px"></div><div style="font-size:10px;color:var(--text2)">May 20</div></div>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><div style="width:100%;background:var(--info);border-radius:4px 4px 0 0;height:110px"></div><div style="font-size:10px;color:var(--text2)">May 21</div></div>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><div style="width:100%;background:var(--info);border-radius:4px 4px 0 0;height:140px"></div><div style="font-size:10px;color:var(--text2)">May 22</div></div>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><div style="width:100%;background:var(--info);border-radius:4px 4px 0 0;height:95px"></div><div style="font-size:10px;color:var(--text2)">May 23</div></div>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><div style="width:100%;background:var(--info);border-radius:4px 4px 0 0;height:105px"></div><div style="font-size:10px;color:var(--text2)">May 24</div></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Academy Completion Funnel</div><button class="btn-ghost btn-sm" onclick="navigateTo('academy-report')">View Report</button></div>
          <div class="card-body">
            <div class="funnel-stage"><div class="funnel-label">Enrolled</div><div class="funnel-bar" style="width:100%;background:var(--info)">5,642</div><div class="funnel-value">100%</div></div>
            <div class="funnel-stage"><div class="funnel-label">In Progress</div><div class="funnel-bar" style="width:85%;background:var(--g400)">3,842</div><div class="funnel-value">68.1%</div></div>
            <div class="funnel-stage"><div class="funnel-label">Assessments</div><div class="funnel-bar" style="width:65%;background:var(--g500)">2,911</div><div class="funnel-value">51.6%</div></div>
            <div class="funnel-stage"><div class="funnel-label">Completed</div><div class="funnel-bar" style="width:45%;background:var(--g600)">2,426</div><div class="funnel-value">43.0%</div></div>
            <div class="funnel-stage"><div class="funnel-label">Certificates Issued</div><div class="funnel-bar" style="width:35%;background:var(--g700)">2,083</div><div class="funnel-value">36.9%</div></div>
            <button class="btn-ghost btn-sm" style="margin-top:10px" onclick="navigateTo('academy-report')">View Academy Report →</button>
          </div>
        </div>
      </div>

      <div class="grid-4">
        <div class="card">
          <div class="card-header"><div class="card-title">Support SLA Heatmap (7D)</div><button class="btn-ghost btn-sm" onclick="navigateTo('support-sla')">View Report</button></div>
          <div class="card-body p0">
            <table style="font-size:11px">
              <thead><tr><th>Team</th><th>May 18</th><th>May 19</th><th>May 20</th><th>May 21</th><th>May 22</th><th>May 23</th><th>May 24</th><th>Avg</th></tr></thead>
              <tbody>
                <tr><td><strong>Tier 1 Support</strong></td><td><div class="heatmap-cell heatmap-high">91%</div></td><td><div class="heatmap-cell heatmap-high">93%</div></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><div class="heatmap-cell heatmap-high">94%</div></td><td><div class="heatmap-cell heatmap-high">95%</div></td><td><div class="heatmap-cell heatmap-high">93%</div></td><td><strong>92.6%</strong></td></tr>
                <tr><td><strong>Tier 2 Support</strong></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><div class="heatmap-cell heatmap-med">87%</div></td><td><div class="heatmap-cell heatmap-med">89%</div></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-high">91%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><strong>89.4%</strong></td></tr>
                <tr><td><strong>Field Support</strong></td><td><div class="heatmap-cell heatmap-med">85%</div></td><td><div class="heatmap-cell heatmap-low">83%</div></td><td><div class="heatmap-cell heatmap-med">86%</div></td><td><div class="heatmap-cell heatmap-med">87%</div></td><td><div class="heatmap-cell heatmap-med">89%</div></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><strong>86.9%</strong></td></tr>
                <tr><td><strong>State Desks</strong></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><div class="heatmap-cell heatmap-high">91%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><div class="heatmap-cell heatmap-high">93%</div></td><td><div class="heatmap-cell heatmap-high">94%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><strong>91.4%</strong></td></tr>
              </tbody>
            </table>
            <div style="padding:10px 18px;display:flex;gap:14px;font-size:10px;color:var(--text2)"><span><span style="display:inline-block;width:10px;height:10px;background:var(--danger);border-radius:2px;margin-right:4px"></span>&lt; 70%</span><span><span style="display:inline-block;width:10px;height:10px;background:var(--warn);border-radius:2px;margin-right:4px"></span>70% - 89%</span><span><span style="display:inline-block;width:10px;height:10px;background:var(--success);border-radius:2px;margin-right:4px"></span>90% - 100%</span></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Field Operations Summary (7D)</div><button class="btn-ghost btn-sm" onclick="navigateTo('field-operations')">View Report</button></div>
          <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:12px">
              <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">👣</span><div><div style="font-weight:600;font-size:13px">Field Visits</div><div style="font-size:11px;color:var(--text2)">Completed this week</div></div></div><div style="text-align:right"><div style="font-size:18px;font-weight:700">1,248</div><div style="font-size:11px;color:var(--success)">↑ 14.6%</div></div></div>
              <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">✓</span><div><div style="font-weight:600;font-size:13px">Grower Verifications</div><div style="font-size:11px;color:var(--text2)">Verified this week</div></div></div><div style="text-align:right"><div style="font-size:18px;font-weight:700">842</div><div style="font-size:11px;color:var(--success)">↑ 11.2%</div></div></div>
              <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">📝</span><div><div style="font-weight:600;font-size:13px">New Enrollments</div><div style="font-size:11px;color:var(--text2)">Academy signups</div></div></div><div style="text-align:right"><div style="font-size:18px;font-weight:700">1,026</div><div style="font-size:11px;color:var(--success)">↑ 13.5%</div></div></div>
              <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px;color:var(--danger)">⚠</span><div><div style="font-weight:600;font-size:13px">Issues Reported</div><div style="font-size:11px;color:var(--text2)">This week</div></div></div><div style="text-align:right"><div style="font-size:18px;font-weight:700;color:var(--danger)">317</div><div style="font-size:11px;color:var(--danger)">↓ 5.3%</div></div></div>
              <div style="display:flex;justify-content:space-between;align-items:center"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px;color:var(--success)">✓</span><div><div style="font-weight:600;font-size:13px">Issues Resolved</div><div style="font-size:11px;color:var(--text2)">This week</div></div></div><div style="text-align:right"><div style="font-size:18px;font-weight:700">284</div><div style="font-size:11px;color:var(--success)">↑ 9.1%</div></div></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Intelligence & Insights</div><button class="btn-ghost btn-sm" onclick="navigateTo('intelligence')">View All</button></div>
          <div class="card-body">
            <div class="insight-card" onclick="navigateTo('intelligence')"><div class="insight-icon" style="background:var(--g100);color:var(--g700)">💡</div><div style="flex:1"><div style="font-weight:600;font-size:12.5px;margin-bottom:3px">Lagos, Oyo and Cross River lead in grower verifications</div><div style="font-size:11px;color:var(--text2)">Scale best practices to low performing states.</div></div><span>›</span></div>
            <div class="insight-card" onclick="navigateTo('intelligence')"><div class="insight-icon" style="background:#fef3c7;color:#92400e">⚠️</div><div style="flex:1"><div style="font-weight:600;font-size:12.5px;margin-bottom:3px">Taraba and Yobe have low training completion rates</div><div style="font-size:11px;color:var(--text2)">Intensify outreach and mentoring.</div></div><span>›</span></div>
            <div class="insight-card" onclick="navigateTo('intelligence')"><div class="insight-icon" style="background:#dbeafe;color:#1e40af">📈</div><div style="flex:1"><div style="font-weight:600;font-size:12.5px;margin-bottom:3px">Marketplace GMV grew 18.6% this week</div><div style="font-size:11px;color:var(--text2)">Driven by fertilizers and seedlings.</div></div><span>›</span></div>
            <div class="insight-card" onclick="navigateTo('intelligence')"><div class="insight-icon" style="background:#ede9fe;color:#5b21b6">🔒</div><div style="flex:1"><div style="font-weight:600;font-size:12.5px;margin-bottom:3px">KYC compliance at 91.1%</div><div style="font-size:11px;color:var(--text2)">1,026 records require attention.</div></div><span>›</span></div>
            <button class="btn-ghost btn-sm" style="margin-top:6px" onclick="navigateTo('intelligence')">View Strategic Recommendations →</button>
          </div>
        </div>
      </div>

      <div class="grid-3">
        <div class="card">
          <div class="card-header"><div class="card-title">Export Center</div></div>
          <div class="card-body">
            <div style="font-size:12px;color:var(--text2);margin-bottom:12px">Generate and download reports in various formats.</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <button class="btn btn-secondary btn-sm" style="justify-content:start" onclick="showToast('Registry report exported')"> Registry Report <span class="chip" style="margin-left:auto">CSV</span></button>
              <button class="btn btn-secondary btn-sm" style="justify-content:start" onclick="showToast('Marketplace report exported')">📊 Marketplace Report <span class="chip" style="margin-left:auto">CSV</span></button>
              <button class="btn btn-secondary btn-sm" style="justify-content:start" onclick="showToast('Wallet statement exported')">💰 Wallet Statement <span class="chip" style="margin-left:auto">CSV</span></button>
              <button class="btn btn-secondary btn-sm" style="justify-content:start" onclick="showToast('Academy report exported')"> Academy Report <span class="chip" style="margin-left:auto">CSV</span></button>
              <button class="btn btn-secondary btn-sm" style="justify-content:start" onclick="showToast('Compliance report exported')">🔒 Compliance Report <span class="chip chip-warn" style="margin-left:auto">PDF</span></button>
              <button class="btn btn-secondary btn-sm" style="justify-content:start" onclick="showToast('Executive summary exported')"> Executive Summary <span class="chip chip-warn" style="margin-left:auto">PDF</span></button>
            </div>
            <button class="btn-ghost btn-sm" style="margin-top:12px" onclick="navigateTo('exports')">View All Exports →</button>
          </div>
        </div>

        <div class="card" style="grid-column:span 2">
          <div class="card-header"><div class="card-title">Scheduled Reports</div><button class="btn-ghost btn-sm" onclick="navigateTo('scheduled-reports')">View All Scheduled Reports →</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Report Name</th><th>Frequency</th><th>Next Run</th><th>Recipients</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <tr><td><strong>Daily Operations Brief</strong></td><td>Daily</td><td>May 25, 2026 08:00 AM</td><td>12</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr>
                <tr><td><strong>Weekly Executive Summary</strong></td><td>Weekly (Mon)</td><td>May 26, 2026 09:00 AM</td><td>18</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr>
                <tr><td><strong>State Performance Report</strong></td><td>Weekly (Tue)</td><td>May 27, 2026 10:00 AM</td><td>24</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr>
                <tr><td><strong>Compliance Dashboard</strong></td><td>Weekly (Fri)</td><td>May 30, 2026 08:30 AM</td><td>15</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr>
                <tr><td><strong>SLA Performance Report</strong></td><td>Daily</td><td>May 25, 2026 07:30 AM</td><td>10</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Exceptions & Alerts</div><button class="btn-ghost btn-sm" onclick="navigateTo('exceptions')">View All</button></div>
        <div class="card-body">
          <div class="alert-card high"><div style="font-size:20px">🚨</div><div style="flex:1"><div style="font-weight:700;font-size:13px">12 documents failed verification rules</div><div style="font-size:11px;color:var(--text2);margin-top:2px">Requires manual review and re-upload</div></div><div style="font-size:11px;color:var(--text2)">10 mins ago</div></div>
          <div class="alert-card medium"><div style="font-size:20px">⚠️</div><div style="flex:1"><div style="font-weight:700;font-size:13px">18 overdue KYC verifications</div><div style="font-size:11px;color:var(--text2);margin-top:2px">Growers pending KYC for more than 14 days</div></div><div style="font-size:11px;color:var(--text2)">25 mins ago</div></div>
          <div class="alert-card medium"><div style="font-size:20px">⚠️</div><div style="flex:1"><div style="font-weight:700;font-size:13px">3 states have SLA compliance below 80%</div><div style="font-size:11px;color:var(--text2);margin-top:2px">Yobe, Taraba, Zamfara need immediate attention</div></div><div style="font-size:11px;color:var(--text2)">1 hour ago</div></div>
          <div class="alert-card low"><div style="font-size:20px">ℹ️</div><div style="flex:1"><div style="font-weight:700;font-size:13px">Wallet reconciliation pending for 2 accounts</div><div style="font-size:11px;color:var(--text2);margin-top:2px">GTBank and First Bank statements awaiting match</div></div><div style="font-size:11px;color:var(--text2)">2 hours ago</div></div>
        </div>
      </div>
    </div>

    <!-- GROWER REPORT -->
    <div class="page" id="page-grower-report">
      <div class="page-header"><div><div class="page-title">Grower Analytics</div><div class="page-subtitle">Deep dive into grower registration and verification metrics</div></div><button class="btn btn-primary" onclick="showToast('Report generated')">📊 Generate Report</button></div>
      <div class="stats-grid">
       <div class="stat-card"><div class="stat-card-label">Total Registered</div><div class="stat-card-value"><?= number_format($growers) ?></div></div>
       <div class="stat-card"><div class="stat-card-label">Verified</div><div class="stat-card-value" style="color:var(--success)"><?= number_format($verifiedGrowers) ?></div><div class="stat-card-sub"><?= $growers > 0 ? (int) round(($verifiedGrowers / $growers) * 100) : 0 ?>% verification rate</div></div>
       <div class="stat-card"><div class="stat-card-label">Pending</div><div class="stat-card-value" style="color:var(--warn)"><?= number_format($pendingApplications) ?></div></div>
       <div class="stat-card"><div class="stat-card-label">States Covered</div><div class="stat-card-value"><?= number_format($statesCovered) ?></div></div>
       <div class="stat-card"><div class="stat-card-label">Field Agents</div><div class="stat-card-value"><?= number_format($fieldAgents) ?></div></div>
       <div class="stat-card"><div class="stat-card-label">Total Certificates</div><div class="stat-card-value"><?= number_format($certificates) ?></div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Registration Trend (30 days)</div></div><div class="card-body"><div style="display:flex;align-items:end;gap:8px;height:180px"><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:40%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:55%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:48%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:72%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:65%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:80%"></div><div style="flex:1;background:var(--g400);border-radius:4px 4px 0 0;height:95%"></div></div><div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text2);margin-top:6px"><span>Week 1</span><span>Week 2</span><span>Week 3</span><span>Week 4</span></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Grower Type Distribution</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:12px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span>Individual Growers</span><strong>80.8%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:80.8%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span>Groups</span><strong>12.4%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:12.4%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span>Cooperatives</span><strong>6.8%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:6.8%;background:var(--purple)"></div></div></div></div></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Top States by Grower Count</div></div><div class="card-body p0"><table><thead><tr><th>State</th><th>Registered</th><th>Verified</th><th>Performance</th></tr></thead><tbody>        <?php foreach ($stateRows as $i => $row): 
            $total = (int) $row['growers'];
            $verified = (int) $row['verified'];
            $perf = $total > 0 ? (int) round(($verified / $total) * 100) : 0;
        ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= e((string) $row['state_name']) ?></strong></td>
                <td><?= number_format($total) ?></td>
                <td><?= number_format($verified) ?></td>
                <td>
                    <div class="progress-bar" style="width:80px">
                        <div class="progress-fill" style="width:<?= $perf ?>%; background:<?= $perf < 50 ? 'var(--danger)' : 'var(--success)' ?>"></div>
                    </div>
                </td>
                <td><?= $perf ?>%</td>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
    </div>

    <!-- VERIFICATION REPORT -->
    <div class="page" id="page-verification-report">
      <div class="page-header"><div><div class="page-title">Verification Report</div><div class="page-subtitle">Track verification workflows and bottlenecks</div></div><button class="btn btn-primary" onclick="showToast('Report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Verified</div><div class="stat-card-value" style="color:var(--success)">64,907</div></div>
        <div class="stat-card"><div class="stat-card-label">Under Verification</div><div class="stat-card-value" style="color:var(--info)">11,356</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Review</div><div class="stat-card-value" style="color:var(--warn)">5,214</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg. Verification Time</div><div class="stat-card-value">3.6 days</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Verification Pipeline</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:14px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Document Verification</span><span>8,245 pending</span></div><div class="progress-bar"><div class="progress-fill" style="width:65%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Field Inspection</span><span>3,111 pending</span></div><div class="progress-bar"><div class="progress-fill" style="width:42%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">LGA Confirmation</span><span>1,892 pending</span></div><div class="progress-bar"><div class="progress-fill" style="width:28%;background:var(--warn)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Final Approval</span><span>456 pending</span></div><div class="progress-bar"><div class="progress-fill" style="width:15%;background:var(--purple)"></div></div></div></div></div></div>
    </div>

    <!-- CERTIFICATE REPORT -->
    <div class="page" id="page-certificate-report">
      <div class="page-header"><div><div class="page-title">Certificate Report</div><div class="page-subtitle">58,321 certificates issued across the platform</div></div><button class="btn btn-primary" onclick="showToast('Report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Issued</div><div class="stat-card-value"><?= number_format($certificates) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Valid</div><div class="stat-card-value" style="color:var(--success)">41,892</div><div class="stat-card-sub">72.0%</div></div>
        <div class="stat-card"><div class="stat-card-label">Expiring (30d)</div><div class="stat-card-value" style="color:var(--warn)">4,126</div></div>
        <div class="stat-card"><div class="stat-card-label">Expired</div><div class="stat-card-value" style="color:var(--danger)">1,985</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Certificates by State</div></div><div class="card-body p0"><table><thead><tr><th>State</th><th>Issued</th><th>Valid</th><th>Expiring</th><th>Expired</th><th>Revoked</th></tr></thead><tbody>
      <?php foreach ($certificateRows as $row): ?>
       <tr>
           <td><strong><?= e((string) $row['state_name']) ?></strong></td>
           <td><?= number_format((int) $row['issued']) ?></td>
           <td><?= number_format((int) $row['valid']) ?></td>
           <td><?= number_format((int) $row['expiring']) ?></td>
           <td><?= number_format((int) $row['expired']) ?></td>
           <td><?= number_format((int) $row['revoked']) ?></td>
       </tr>
      <?php endforeach; ?>
      </tbody></table></div></div>
    </div>

    <!-- STATE & LGA -->
    <div class="page" id="page-state-lga">
      <div class="page-header"><div><div class="page-title">State & LGA Performance</div><div class="page-subtitle">36 states with 100% coverage • 774 LGAs</div></div><button class="btn btn-primary" onclick="showToast('Report exported')"> Export Report</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">States Covered</div><div class="stat-card-value">36 / 36</div><div class="stat-card-sub" style="color:var(--success)">100% coverage</div></div>
        <div class="stat-card"><div class="stat-card-label">Total LGAs</div><div class="stat-card-value">774</div></div>
        <div class="stat-card"><div class="stat-card-label">Active LGAs</div><div class="stat-card-value">742</div></div>
        <div class="stat-card"><div class="stat-card-label">Top State</div><div class="stat-card-value" style="font-size:16px">Lagos (92.4%)</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">State Performance Matrix</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search states..." oninput="filterTable('stateTable',this.value)"><select><option>All Zones</option><option>North West</option><option>North East</option><option>North Central</option><option>South West</option><option>South East</option><option>South South</option></select></div></div><div class="card-body p0">
        <table id="stateTable">
          <thead><tr><th>State</th><th>Growers</th><th>Verified</th><th>Performance</th></tr></thead>
          <tbody>
          <?php foreach ($stateRows as $row): 
              $total = (int) $row['growers'];
              $verified = (int) $row['verified'];
              $perf = $total > 0 ? (int) round(($verified / $total) * 100) : 0;
          ?>
            <tr>
                <td><strong><?= e((string) $row['state_name']) ?></strong></td>
                <td><?= number_format($total) ?></td>
                <td><?= number_format($verified) ?></td>
                <td>
                    <div class="progress-bar" style="width:80px">
                        <div class="progress-fill" style="width:<?= $perf ?>%; background:<?= $perf < 50 ? 'var(--danger)' : 'var(--success)' ?>"></div>
                    </div>
                    <?= $perf ?>%
                </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- MARKETPLACE REPORT -->
    <div class="page" id="page-marketplace-report">
      <div class="page-header"><div><div class="page-title">Marketplace Reports</div><div class="page-subtitle">GMV, revenue, and seller performance analytics</div></div><button class="btn btn-primary" onclick="showToast('Report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">GMV (7D)</div><div class="stat-card-value">₦<?= number_format($gmv) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Volume (7D)</div><div class="stat-card-value">₦<?= number_format($walletVolume) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Active Sellers</div><div class="stat-card-value"><?= number_format($sellers) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Orders (7D)</div><div class="stat-card-value"><?= number_format($orders) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Listings</div><div class="stat-card-value"><?= number_format($listings) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Wallet Balance</div><div class="stat-card-value">₦<?= number_format($walletBalance) ?></div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Revenue by Category</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:12px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span>Coconut Products</span><strong>38% • ₦9.3M</strong></div><div class="progress-bar"><div class="progress-fill" style="width:38%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span>Fertilizers</span><strong>28% • ₦6.9M</strong></div><div class="progress-bar"><div class="progress-fill" style="width:28%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span>Seedlings</span><strong>22% • ₦5.4M</strong></div><div class="progress-bar"><div class="progress-fill" style="width:22%;background:var(--warn)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span>Tools & Equipment</span><strong>12% • ₦2.9M</strong></div><div class="progress-bar"><div class="progress-fill" style="width:12%;background:var(--purple)"></div></div></div></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Top Selling Products (7D)</div></div><div class="card-body p0"><table><thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead><tbody><tr><td>🥥 Coco Peat (5kg)</td><td>256</td><td>₦614,400</td></tr><tr><td>🌱 Organic Fertilizer (50kg)</td><td>189</td><td>₦945,000</td></tr><tr><td>🌴 Coconut Seedlings (Hybrid)</td><td>142</td><td>₦710,000</td></tr><tr><td>🫙 Neem Oil (1L)</td><td>118</td><td>₦165,200</td></tr><tr><td> NPK 15-15-15 (50kg)</td><td>96</td><td>₦432,000</td></tr></tbody></table></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Top Sellers by Revenue</div></div><div class="card-body p0"><table><thead><tr><th>Rank</th><th>Seller</th><th>Products</th><th>Orders</th><th>Revenue (7D)</th><th>Growth</th></tr></thead><tbody><tr><td>🥇 1</td><td><div class="avatar-row"><div class="avatar-sm">GF</div>Green Farms Ltd</div></td><td>142</td><td>892</td><td>₦892,400</td><td style="color:var(--success)">↑ 24%</td></tr><tr><td>🥈 2</td><td><div class="avatar-row"><div class="avatar-sm">PA</div>Palmbest Agro</div></td><td>98</td><td>654</td><td>₦456,200</td><td style="color:var(--success)">↑ 18%</td></tr><tr><td>🥉 3</td><td><div class="avatar-row"><div class="avatar-sm">CH</div>Coconut Hub</div></td><td>76</td><td>487</td><td>312,800</td><td style="color:var(--success)">↑ 32%</td></tr><tr><td>4</td><td><div class="avatar-row"><div class="avatar-sm">IH</div>Island Harvest</div></td><td>38</td><td>312</td><td>₦142,100</td><td style="color:var(--success)">↑ 12%</td></tr><tr><td>5</td><td><div class="avatar-row"><div class="avatar-sm">AF</div>AgroFuture Nigeria</div></td><td>41</td><td>289</td><td>₦156,300</td><td style="color:var(--danger)">↓ 5%</td></tr></tbody></table></div></div>
    </div>

    <!-- WALLET & FINANCE -->
    <div class="page" id="page-wallet-finance">
      <div class="page-header"><div><div class="page-title">Wallet & Finance</div><div class="page-subtitle">Platform wallet, transactions, and financial metrics</div></div><button class="btn btn-primary" onclick="showToast('Financial report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Wallet Balance</div><div class="stat-card-value">₦24,977,388</div></div>
        <div class="stat-card"><div class="stat-card-label">Volume (7D)</div><div class="stat-card-value">₦32,845,210</div><div class="stat-card-change up">↑ 21.3%</div></div>
        <div class="stat-card"><div class="stat-card-label">Inflow (7D)</div><div class="stat-card-value" style="color:var(--success)">₦18,245,900</div></div>
        <div class="stat-card"><div class="stat-card-label">Outflow (7D)</div><div class="stat-card-value" style="color:var(--danger)">₦14,599,310</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Payouts</div><div class="stat-card-value">₦2,845,900</div></div>
        <div class="stat-card"><div class="stat-card-label">Platform Fees (7D)</div><div class="stat-card-value">₦3,284,521</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Transaction Volume Trend</div></div><div class="card-body"><div style="display:flex;align-items:end;gap:10px;height:180px"><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:60%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:72%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:65%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:80%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:88%"></div><div style="flex:1;background:var(--g400);border-radius:4px 4px 0 0;height:95%"></div></div><div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text2);margin-top:6px"><span>Week 1</span><span>Week 2</span><span>Week 3</span><span>Week 4</span></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Payment Channel Distribution</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:10px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Monnify</span><strong>58%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:58%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Bank Transfer</span><strong>28%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:28%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Card</span><strong>14%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:14%;background:var(--warn)"></div></div></div></div></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Recent High-Value Transactions</div></div><div class="card-body p0"><table><thead><tr><th>TXN ID</th><th>Date</th><th>Type</th><th>Counterparty</th><th>Amount</th><th>Status</th></tr></thead><tbody><tr><td><strong>TRX-260524-00078</strong></td><td>May 24, 09:33</td><td><span class="status-badge status-credit">Credit</span></td><td>John Okafor</td><td style="color:var(--success);font-weight:600">+₦245,000</td><td><span class="status-badge status-success">Successful</span></td></tr><tr><td><strong>TRX-260524-00077</strong></td><td>May 24, 09:12</td><td><span class="status-badge status-debit">Debit</span></td><td>Green Farms Ltd</td><td style="color:var(--danger);font-weight:600">-185,000</td><td><span class="status-badge status-pending">Processing</span></td></tr><tr><td><strong>TRX-260523-00076</strong></td><td>May 23, 18:45</td><td><span class="status-badge status-credit">Credit</span></td><td>Mary Abiodun</td><td style="color:var(--success);font-weight:600">+₦120,000</td><td><span class="status-badge status-success">Successful</span></td></tr><tr><td><strong>TRX-260522-00072</strong></td><td>May 22, 14:56</td><td><span class="status-badge status-debit">Debit</span></td><td>Palmbest Agro</td><td style="color:var(--danger);font-weight:600">-₦320,000</td><td><span class="status-badge status-success">Successful</span></td></tr></tbody></table></div></div>
    </div>

    <!-- ACADEMY REPORT -->
    <div class="page" id="page-academy-report">
      <div class="page-header"><div><div class="page-title">Academy Reports</div><div class="page-subtitle">Learner progress, completion, and certification metrics</div></div><button class="btn btn-primary" onclick="showToast('Report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Learners</div><div class="stat-card-value">3,624</div></div>
        <div class="stat-card"><div class="stat-card-label">Active Enrollments</div><div class="stat-card-value">2,479</div></div>
        <div class="stat-card"><div class="stat-card-label">Completion Rate</div><div class="stat-card-value">68.4%</div><div class="stat-card-change up">↑ 9.5%</div></div>
        <div class="stat-card"><div class="stat-card-label">Certificates Issued</div><div class="stat-card-value">2,083</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg. Rating</div><div class="stat-card-value">⭐ 4.6</div></div>
        <div class="stat-card"><div class="stat-card-label">Courses</div><div class="stat-card-value">48</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Completion Funnel</div></div><div class="card-body"><div class="funnel-stage"><div class="funnel-label">Enrolled</div><div class="funnel-bar" style="width:100%;background:var(--info)">5,642</div><div class="funnel-value">100%</div></div><div class="funnel-stage"><div class="funnel-label">In Progress</div><div class="funnel-bar" style="width:85%;background:var(--g400)">3,842</div><div class="funnel-value">68.1%</div></div><div class="funnel-stage"><div class="funnel-label">Assessments</div><div class="funnel-bar" style="width:65%;background:var(--g500)">2,911</div><div class="funnel-value">51.6%</div></div><div class="funnel-stage"><div class="funnel-label">Completed</div><div class="funnel-bar" style="width:45%;background:var(--g600)">2,426</div><div class="funnel-value">43.0%</div></div><div class="funnel-stage"><div class="funnel-label">Certificates Issued</div><div class="funnel-bar" style="width:35%;background:var(--g700)">2,083</div><div class="funnel-value">36.9%</div></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Top Courses by Enrollment</div></div><div class="card-body p0"><table><thead><tr><th>Course</th><th>Enrolled</th><th>Completed</th><th>Rate</th></tr></thead><tbody><tr><td>Power BI Essentials</td><td>485</td><td>389</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:80%"></div></div></td></tr><tr><td>Python for Data Science</td><td>620</td><td>465</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:75%"></div></div></td></tr><tr><td>Agile Project Management</td><td>312</td><td>218</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:70%"></div></div></td></tr><tr><td>UX/UI Design Fundamentals</td><td>278</td><td>195</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:70%"></div></div></td></tr><tr><td>Leadership in Public Health</td><td>240</td><td>180</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:75%"></div></div></td></tr></tbody></table></div></div>
      </div>
    </div>

    <!-- SUPPORT SLA -->
    <div class="page" id="page-support-sla">
      <div class="page-header"><div><div class="page-title">Support SLA</div><div class="page-subtitle">Service level agreement compliance and support metrics</div></div><button class="btn btn-primary" onclick="showToast('SLA report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">SLA Compliance</div><div class="stat-card-value">92.6%</div><div class="stat-card-change up">↑ 6.7%</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg. Response Time</div><div class="stat-card-value">2.4 hrs</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg. Resolution Time</div><div class="stat-card-value">18.6 hrs</div></div>
        <div class="stat-card"><div class="stat-card-label">Open Tickets</div><div class="stat-card-value">317</div></div>
        <div class="stat-card"><div class="stat-card-label">Resolved (7D)</div><div class="stat-card-value">284</div></div>
        <div class="stat-card"><div class="stat-card-label">Customer Satisfaction</div><div class="stat-card-value">⭐ 4.5</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">SLA Heatmap (7D)</div></div><div class="card-body p0"><table style="font-size:11px"><thead><tr><th>Team</th><th>May 18</th><th>May 19</th><th>May 20</th><th>May 21</th><th>May 22</th><th>May 23</th><th>May 24</th><th>Avg</th></tr></thead><tbody><tr><td><strong>Tier 1 Support</strong></td><td><div class="heatmap-cell heatmap-high">91%</div></td><td><div class="heatmap-cell heatmap-high">93%</div></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><div class="heatmap-cell heatmap-high">94%</div></td><td><div class="heatmap-cell heatmap-high">95%</div></td><td><div class="heatmap-cell heatmap-high">93%</div></td><td><strong>92.6%</strong></td></tr><tr><td><strong>Tier 2 Support</strong></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><div class="heatmap-cell heatmap-med">87%</div></td><td><div class="heatmap-cell heatmap-med">89%</div></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-high">91%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><strong>89.4%</strong></td></tr><tr><td><strong>Field Support</strong></td><td><div class="heatmap-cell heatmap-med">85%</div></td><td><div class="heatmap-cell heatmap-low">83%</div></td><td><div class="heatmap-cell heatmap-med">86%</div></td><td><div class="heatmap-cell heatmap-med">87%</div></td><td><div class="heatmap-cell heatmap-med">89%</div></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><strong>86.9%</strong></td></tr><tr><td><strong>State Desks</strong></td><td><div class="heatmap-cell heatmap-high">90%</div></td><td><div class="heatmap-cell heatmap-med">88%</div></td><td><div class="heatmap-cell heatmap-high">91%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><div class="heatmap-cell heatmap-high">93%</div></td><td><div class="heatmap-cell heatmap-high">94%</div></td><td><div class="heatmap-cell heatmap-high">92%</div></td><td><strong>91.4%</strong></td></tr></tbody></table><div style="padding:10px 18px;display:flex;gap:14px;font-size:10px;color:var(--text2)"><span><span style="display:inline-block;width:10px;height:10px;background:var(--danger);border-radius:2px;margin-right:4px"></span>&lt; 70%</span><span><span style="display:inline-block;width:10px;height:10px;background:var(--warn);border-radius:2px;margin-right:4px"></span>70% - 89%</span><span><span style="display:inline-block;width:10px;height:10px;background:var(--success);border-radius:2px;margin-right:4px"></span>90% - 100%</span></div></div></div>
      <div class="card"><div class="card-header"><div class="card-title">Ticket Volume by Category</div></div><div class="card-body p0"><table><thead><tr><th>Category</th><th>Opened (7D)</th><th>Resolved</th><th>Pending</th><th>Avg. Resolution</th><th>SLA Met</th></tr></thead><tbody><tr><td>Technical Issues</td><td>124</td><td>112</td><td>12</td><td>14.2 hrs</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:94%"></div></div></td></tr><tr><td>Billing & Payments</td><td>89</td><td>82</td><td>7</td><td>22.4 hrs</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:88%"></div></div></td></tr><tr><td>Account Issues</td><td>67</td><td>58</td><td>9</td><td>18.6 hrs</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:91%"></div></div></td></tr><tr><td>Product Questions</td><td>37</td><td>32</td><td>5</td><td>8.4 hrs</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:96%"></div></div></td></tr></tbody></table></div></div>
    </div>

    <!-- FIELD OPERATIONS -->
    <div class="page" id="page-field-operations">
      <div class="page-header"><div><div class="page-title">Field Operations</div><div class="page-subtitle">Field agent activity, visits, and verification metrics</div></div><button class="btn btn-primary" onclick="showToast('Report exported')"> Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Field Visits (7D)</div><div class="stat-card-value">1,248</div><div class="stat-card-change up">↑ 14.6%</div></div>
        <div class="stat-card"><div class="stat-card-label">Grower Verifications</div><div class="stat-card-value">842</div><div class="stat-card-change up">↑ 11.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">New Enrollments</div><div class="stat-card-value">1,026</div><div class="stat-card-change up">↑ 13.5%</div></div>
        <div class="stat-card"><div class="stat-card-label">Issues Reported</div><div class="stat-card-value" style="color:var(--danger)">317</div><div class="stat-card-change down">↓ 5.3%</div></div>
        <div class="stat-card"><div class="stat-card-label">Issues Resolved</div><div class="stat-card-value">284</div><div class="stat-card-change up">↑ 9.1%</div></div>
        <div class="stat-card"><div class="stat-card-label">Active Agents</div><div class="stat-card-value">128</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Visits by State (Top 10)</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:10px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Lagos</span><strong>248 visits</strong></div><div class="progress-bar"><div class="progress-fill" style="width:92%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Kano</span><strong>198 visits</strong></div><div class="progress-bar"><div class="progress-fill" style="width:78%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Ogun</span><strong>165 visits</strong></div><div class="progress-bar"><div class="progress-fill" style="width:65%;background:var(--warn)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Oyo</span><strong>142 visits</strong></div><div class="progress-bar"><div class="progress-fill" style="width:56%;background:var(--purple)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Rivers</span><strong>128 visits</strong></div><div class="progress-bar"><div class="progress-fill" style="width:50%;background:var(--orange)"></div></div></div></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Agent Performance</div></div><div class="card-body p0"><table><thead><tr><th>Agent</th><th>State</th><th>Visits</th><th>Verifications</th><th>Performance</th></tr></thead><tbody><tr><td><div class="avatar-row"><div class="avatar-sm">CE</div>Chinedu Eze</div></td><td>Lagos</td><td>42</td><td>38</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:97%"></div></div></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">IB</div>Ibrahim Bello</div></td><td>Kaduna</td><td>38</td><td>35</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:96%"></div></div></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">AM</div>Aisha Musa</div></td><td>Kano</td><td>36</td><td>32</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:94%"></div></div></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">JO</div>James Okon</div></td><td>Akwa Ibom</td><td>34</td><td>30</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:91%"></div></div></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">EU</div>Esther Udo</div></td><td>Cross River</td><td>32</td><td>28</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:88%"></div></div></td></tr></tbody></table></div></div>
      </div>
    </div>

    <!-- COMPLIANCE -->
    <div class="page" id="page-compliance">
      <div class="page-header"><div><div class="page-title">Compliance Dashboard</div><div class="page-subtitle">Regulatory compliance and audit metrics</div></div><button class="btn btn-primary" onclick="showToast('Compliance report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Overall Compliance</div><div class="stat-card-value">91.1%</div><div class="stat-card-change up">↑ 3.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">KYC Compliance</div><div class="stat-card-value">91.1%</div></div>
        <div class="stat-card"><div class="stat-card-label">Data Quality</div><div class="stat-card-value">94.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">Financial Compliance</div><div class="stat-card-value">93.8%</div></div>
        <div class="stat-card"><div class="stat-card-label">Training Compliance</div><div class="stat-card-value" style="color:var(--warn)">86.7%</div></div>
        <div class="stat-card"><div class="stat-card-label">Audit Findings</div><div class="stat-card-value" style="color:var(--danger)">7</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Compliance Scorecards</div></div><div class="card-body"><div class="compliance-row"><div class="compliance-label">Data Quality Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:94.2%"></div></div><div class="compliance-value">94.2%</div></div><div class="compliance-row"><div class="compliance-label">KYC Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:91.1%"></div></div><div class="compliance-value">91.1%</div></div><div class="compliance-row"><div class="compliance-label">Document Verification</div><div class="compliance-bar"><div class="compliance-fill" style="width:89.3%"></div></div><div class="compliance-value">89.3%</div></div><div class="compliance-row"><div class="compliance-label">Training Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:86.7%;background:var(--warn)"></div></div><div class="compliance-value" style="color:var(--warn)">86.7%</div></div><div class="compliance-row"><div class="compliance-label">Financial Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:93.8%"></div></div><div class="compliance-value">93.8%</div></div><div class="compliance-row"><div class="compliance-label">Marketplace Compliance</div><div class="compliance-bar"><div class="compliance-fill" style="width:90.4%"></div></div><div class="compliance-value">90.4%</div></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Compliance by Module</div></div><div class="card-body p0"><table><thead><tr><th>Module</th><th>Score</th><th>Status</th><th>Last Audit</th></tr></thead><tbody><tr><td>Registry</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:94%"></div></div></td><td><span class="status-badge status-active">Compliant</span></td><td>May 20, 2026</td></tr><tr><td>Marketplace</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:90%"></div></div></td><td><span class="status-badge status-active">Compliant</span></td><td>May 18, 2026</td></tr><tr><td>Wallet</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:93%"></div></div></td><td><span class="status-badge status-active">Compliant</span></td><td>May 22, 2026</td></tr><tr><td>Academy</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:86%;background:var(--warn)"></div></div></td><td><span class="status-badge status-warn">Needs Attention</span></td><td>May 15, 2026</td></tr><tr><td>Field Operations</td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:88%"></div></div></td><td><span class="status-badge status-active">Compliant</span></td><td>May 19, 2026</td></tr></tbody></table></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Recent Audit Findings</div></div><div class="card-body p0"><table><thead><tr><th>Finding ID</th><th>Module</th><th>Severity</th><th>Description</th><th>Status</th><th>Due Date</th></tr></thead><tbody><tr><td><strong>AUD-2026-0042</strong></td><td>Academy</td><td><span class="status-badge status-warn">Medium</span></td><td>Training completion below target in 3 states</td><td><span class="status-badge status-pending">Open</span></td><td>Jun 15, 2026</td></tr><tr><td><strong>AUD-2026-0041</strong></td><td>Registry</td><td><span class="status-badge status-danger">High</span></td><td>12 documents failed verification rules</td><td><span class="status-badge status-pending">Open</span></td><td>Jun 10, 2026</td></tr><tr><td><strong>AUD-2026-0040</strong></td><td>Wallet</td><td><span class="status-badge status-warn">Medium</span></td><td>2 accounts pending reconciliation</td><td><span class="status-badge status-pending">Open</span></td><td>Jun 12, 2026</td></tr><tr><td><strong>AUD-2026-0039</strong></td><td>Marketplace</td><td><span class="status-badge status-active">Low</span></td><td>Minor documentation gaps in seller onboarding</td><td><span class="status-badge status-completed">Resolved</span></td><td>May 30, 2026</td></tr></tbody></table></div></div>
    </div>

    <!-- EXPORTS -->
    <div class="page" id="page-exports">
      <div class="page-header"><div><div class="page-title">Export Center</div><div class="page-subtitle">Generate and download reports in various formats</div></div></div>
      <div class="grid-3">
        <div class="card" style="cursor:pointer" onclick="showToast('Registry report exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📄</div><div style="font-weight:700;font-size:15px">Registry Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Grower & verification data</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Marketplace report exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px"></div><div style="font-weight:700;font-size:15px">Marketplace Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">GMV, revenue, sellers</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Wallet statement exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">💰</div><div style="font-weight:700;font-size:15px">Wallet Statement</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Transactions & balances</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Academy report exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px"></div><div style="font-weight:700;font-size:15px">Academy Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Learners & completion</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Compliance report exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">🔒</div><div style="font-weight:700;font-size:15px">Compliance Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Audit & compliance metrics</div><button class="btn btn-sm btn-warn" style="margin-top:10px">Export PDF</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Executive summary exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📋</div><div style="font-weight:700;font-size:15px">Executive Summary</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Platform-wide overview</div><button class="btn btn-sm btn-warn" style="margin-top:10px">Export PDF</button></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Custom Export</div></div><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Report Type</label><select class="form-select"><option>Registry</option><option>Marketplace</option><option>Wallet</option><option>Academy</option><option>Compliance</option><option>Field Operations</option></select></div><div class="form-group"><label class="form-label">Format</label><select class="form-select"><option>CSV</option><option>Excel (XLSX)</option><option>PDF</option><option>JSON</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Date From</label><input class="form-input" type="date"></div><div class="form-group"><label class="form-label">Date To</label><input class="form-input" type="date"></div></div><div class="form-group"><label class="form-label">Filters</label><select class="form-select" multiple style="min-height:80px"><option>All States</option><option>Lagos</option><option>Kano</option><option>Ogun</option><option>Oyo</option><option>Rivers</option></select></div><button class="btn btn-primary" onclick="showToast('Custom export generated')">Generate Export</button></div></div>
    </div>

    <!-- SCHEDULED REPORTS -->
    <div class="page" id="page-scheduled-reports">
      <div class="page-header"><div><div class="page-title">Scheduled Reports</div><div class="page-subtitle">Automated report generation and distribution</div></div><button class="btn btn-primary" onclick="openModal('scheduleModal')">+ Schedule Report</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Active Schedules</div><div class="stat-card-value">12</div></div>
        <div class="stat-card"><div class="stat-card-label">Reports Sent (7D)</div><div class="stat-card-value">47</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Recipients</div><div class="stat-card-value">89</div></div>
        <div class="stat-card"><div class="stat-card-label">Next Run</div><div class="stat-card-value" style="font-size:14px">May 25, 07:30 AM</div></div>
      </div>
      <div class="card"><div class="card-body p0"><table><thead><tr><th>Report Name</th><th>Frequency</th><th>Next Run</th><th>Last Run</th><th>Recipients</th><th>Format</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td><strong>Daily Operations Brief</strong></td><td>Daily</td><td>May 25, 08:00 AM</td><td>May 24, 08:00 AM</td><td>12</td><td><span class="chip">PDF</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon"></button></td></tr><tr><td><strong>Weekly Executive Summary</strong></td><td>Weekly (Mon)</td><td>May 26, 09:00 AM</td><td>May 19, 09:00 AM</td><td>18</td><td><span class="chip chip-warn">PDF</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr><tr><td><strong>State Performance Report</strong></td><td>Weekly (Tue)</td><td>May 27, 10:00 AM</td><td>May 20, 10:00 AM</td><td>24</td><td><span class="chip">CSV</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr><tr><td><strong>Compliance Dashboard</strong></td><td>Weekly (Fri)</td><td>May 30, 08:30 AM</td><td>May 23, 08:30 AM</td><td>15</td><td><span class="chip chip-warn">PDF</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr><tr><td><strong>SLA Performance Report</strong></td><td>Daily</td><td>May 25, 07:30 AM</td><td>May 24, 07:30 AM</td><td>10</td><td><span class="chip">CSV</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr><tr><td><strong>Marketplace GMV Report</strong></td><td>Daily</td><td>May 25, 06:00 AM</td><td>May 24, 06:00 AM</td><td>8</td><td><span class="chip">CSV</span></td><td><span class="status-badge status-draft">Paused</span></td><td><button class="btn-icon">▶</button><button class="btn-icon">✏️</button><button class="btn-icon">⋮</button></td></tr></tbody></table></div></div>
    </div>

    <!-- INTELLIGENCE -->
    <div class="page" id="page-intelligence">
      <div class="page-header"><div><div class="page-title">Intelligence & Insights</div><div class="page-subtitle">AI-powered insights and strategic recommendations</div></div><button class="btn btn-primary" onclick="showToast('Generating new insights...')">🔄 Refresh Insights</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title"> Key Findings</div></div><div class="card-body">
          <div class="insight-card"><div class="insight-icon" style="background:var(--g100);color:var(--g700)">💡</div><div style="flex:1"><div style="font-weight:700;font-size:13px;margin-bottom:4px">Lagos, Oyo and Cross River lead in grower verifications</div><div style="font-size:12px;color:var(--text2)">These states have achieved 85%+ verification rates through dedicated field agent deployment and streamlined document processing. Scale best practices to low performing states.</div><div style="margin-top:8px"><span class="chip">Registry</span><span class="chip">Field Operations</span></div></div></div>
          <div class="insight-card"><div class="insight-icon" style="background:#fef3c7;color:#92400e">⚠️</div><div style="flex:1"><div style="font-weight:700;font-size:13px;margin-bottom:4px">Taraba and Yobe have low training completion rates</div><div style="font-size:12px;color:var(--text2)">Academy completion rates in these states are below 45%, significantly lower than the national average of 68.4%. Intensify outreach and mentoring programs.</div><div style="margin-top:8px"><span class="chip chip-warn">Academy</span><span class="chip">State Performance</span></div></div></div>
          <div class="insight-card"><div class="insight-icon" style="background:#dbeafe;color:#1e40af"></div><div style="flex:1"><div style="font-weight:700;font-size:13px;margin-bottom:4px">Marketplace GMV grew 18.6% this week</div><div style="font-size:12px;color:var(--text2)">Driven primarily by fertilizers (+32%) and seedlings (+28%). Coconut products remained stable at +12%. Consider promotional campaigns for tools & equipment category.</div><div style="margin-top:8px"><span class="chip">Marketplace</span><span class="chip">Revenue</span></div></div></div>
          <div class="insight-card"><div class="insight-icon" style="background:#ede9fe;color:#5b21b6">🔒</div><div style="flex:1"><div style="font-weight:700;font-size:13px;margin-bottom:4px">KYC compliance at 91.1% - 1,026 records require attention</div><div style="font-size:12px;color:var(--text2)">While overall compliance is strong, 1,026 grower records have incomplete KYC documentation. Prioritize follow-up with field agents in North East zone.</div><div style="margin-top:8px"><span class="chip">Compliance</span><span class="chip chip-warn">Action Required</span></div></div></div>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title">📊 Strategic Recommendations</div></div><div class="card-body">
          <div style="display:flex;flex-direction:column;gap:12px">
            <div style="padding:14px;background:var(--g50);border-radius:10px;border-left:4px solid var(--g500)"><div style="font-weight:700;font-size:13px;margin-bottom:4px">1. Deploy Additional Field Agents to North East</div><div style="font-size:12px;color:var(--text2)">Yobe, Taraba, and Zamfara show 30-34% performance scores. Recommend deploying 15 additional agents to improve verification rates by estimated 25%.</div><div style="margin-top:8px;font-size:11px;color:var(--g700);font-weight:600">Expected Impact: +2,500 verifications/month</div></div>
            <div style="padding:14px;background:#fef3c7;border-radius:10px;border-left:4px solid var(--warn)"><div style="font-weight:700;font-size:13px;margin-bottom:4px">2. Launch Targeted Academy Campaign in Low-Completion States</div><div style="font-size:12px;color:var(--text2)">Create state-specific learning paths with local language support for Taraba and Yobe. Partner with state coordinators for mentorship.</div><div style="margin-top:8px;font-size:11px;color:var(--warn);font-weight:600">Expected Impact: +15% completion rate</div></div>
            <div style="padding:14px;background:#dbeafe;border-radius:10px;border-left:4px solid var(--info)"><div style="font-weight:700;font-size:13px;margin-bottom:4px">3. Promote Tools & Equipment Category</div><div style="font-size:12px;color:var(--text2)">Only 12% of GMV comes from tools. Launch "Farm Modernization" campaign with 15% discount to drive adoption.</div><div style="margin-top:8px;font-size:11px;color:var(--info);font-weight:600">Expected Impact: +₦2M monthly GMV</div></div>
            <div style="padding:14px;background:#ede9fe;border-radius:10px;border-left:4px solid var(--purple)"><div style="font-weight:700;font-size:13px;margin-bottom:4px">4. Accelerate KYC Completion Drive</div><div style="font-size:12px;color:var(--text2)">Prioritize 1,026 incomplete KYC records. Set 30-day target to reach 95% compliance. Assign dedicated verification team.</div><div style="margin-top:8px;font-size:11px;color:var(--purple);font-weight:600">Expected Impact: 95% KYC compliance</div></div>
          </div>
        </div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">📈 Predictive Analytics</div></div><div class="card-body"><div class="grid-3"><div style="padding:16px;background:var(--g50);border-radius:10px"><div style="font-size:11px;color:var(--text2);margin-bottom:6px">Projected Growers (30 days)</div><div style="font-size:24px;font-weight:700;color:var(--g700)">95,240</div><div style="font-size:11px;color:var(--success);margin-top:4px">↑ 6.2% from current</div></div><div style="padding:16px;background:var(--g50);border-radius:10px"><div style="font-size:11px;color:var(--text2);margin-bottom:6px">Projected GMV (30 days)</div><div style="font-size:24px;font-weight:700;color:var(--g700)">₦108.4M</div><div style="font-size:11px;color:var(--success);margin-top:4px">↑ 22% from current</div></div><div style="padding:16px;background:var(--g50);border-radius:10px"><div style="font-size:11px;color:var(--text2);margin-bottom:6px">Projected Certificates (30 days)</div><div style="font-size:24px;font-weight:700;color:var(--g700)">62,450</div><div style="font-size:11px;color:var(--success);margin-top:4px">↑ 7.1% from current</div></div></div></div></div>
    </div>

    <!-- STAKEHOLDER INTERESTS -->
    <div class="page" id="page-stakeholder-interests">
      <div class="page-header"><div><div class="page-title">Stakeholder Interests</div><div class="page-subtitle">Control what each stakeholder group tracks, receives, and acts on.</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Active Interest Rules</div><div class="stat-card-value">0</div></div>
        <div class="stat-card"><div class="stat-card-label">Stakeholder Roles</div><div class="stat-card-value">0</div></div>
        <div class="stat-card"><div class="stat-card-label">High Priority Interests</div><div class="stat-card-value">0</div></div>
        <div class="stat-card"><div class="stat-card-label">Dashboard Delivery</div><div class="stat-card-value">0</div></div>
      </div>
      <div class="card"><div class="card-body">
        <form method="post" class="form-row-3" style="align-items:end">
          <input type="hidden" name="action" value="save_stakeholder_interest">
          <input type="hidden" name="page" value="stakeholder-interests">
          <div class="form-group"><label class="form-label">Stakeholder Role</label><input class="form-input" name="stakeholder_role" placeholder="seller, grower, state_coordinator" required></div>
          <div class="form-group"><label class="form-label">Module</label><select class="form-select" name="module"><option value="platform">Platform</option><option value="registry">Registry</option><option value="marketplace">Marketplace</option><option value="wallet">Wallet</option><option value="academy">Academy</option><option value="support">Support</option><option value="field">Field</option></select></div>
          <div class="form-group"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></div>
          <div class="form-group" style="grid-column:1/-1"><label class="form-label">Interest Area</label><textarea class="form-textarea" name="interest_area" required></textarea></div>
          <div class="form-group"><label class="form-label">Default Report</label><input class="form-input" name="default_report" placeholder="marketplace, wallet, academy"></div>
          <div class="form-group"><label class="form-label">Delivery Channel</label><input class="form-input" name="delivery_channel" value="dashboard,email"></div>
          <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
          <div class="form-group" style="grid-column:1/-1"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div>
          <button class="btn btn-primary" type="submit">Save Interest Rule</button>
        </form>
      </div></div>
      <div class="card"><div class="card-body p0"><table><thead><tr><th>Role</th><th>Interest</th><th>Module</th><th>Priority</th><th>Default Report</th><th>Delivery</th><th>Status</th></tr></thead><tbody></tbody></table></div></div>
    </div>

    <!-- EXCEPTIONS -->
    <div class="page" id="page-exceptions">
      <div class="page-header"><div><div class="page-title">Exceptions & Alerts</div><div class="page-subtitle">12 active alerts requiring attention</div></div><button class="btn btn-primary" onclick="showToast('All alerts acknowledged')">✓ Acknowledge All</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">High Priority</div><div class="stat-card-value" style="color:var(--danger)">3</div></div>
        <div class="stat-card"><div class="stat-card-label">Medium Priority</div><div class="stat-card-value" style="color:var(--warn)">6</div></div>
        <div class="stat-card"><div class="stat-card-label">Low Priority</div><div class="stat-card-value" style="color:var(--info)">3</div></div>
        <div class="stat-card"><div class="stat-card-label">Resolved (7D)</div><div class="stat-card-value" style="color:var(--success)">47</div></div>
      </div>
      <div class="tabs"><div class="tab active">All Alerts (12)</div><div class="tab">High (3)</div><div class="tab">Medium (6)</div><div class="tab">Low (3)</div><div class="tab">Resolved</div></div>
      <div class="card"><div class="card-body">
        <div class="alert-card high"><div style="font-size:24px">🚨</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">12 documents failed verification rules</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Documents from 8 growers failed automated verification. Requires manual review and re-upload.</div><div style="display:flex;gap:12px;align-items:center"><span class="chip chip-danger">Registry</span><span style="font-size:11px;color:var(--text2)">10 mins ago</span></div></div><button class="btn btn-sm btn-danger" onclick="showToast('Review opened')">Review</button></div>
        <div class="alert-card high"><div style="font-size:24px">🚨</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">High-risk transaction detected: ₦250,000</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Transaction TRX-260524-00061 flagged for unusual amount pattern. Potential fraud.</div><div style="display:flex;gap:12px;align-items:center"><span class="chip chip-danger">Wallet</span><span style="font-size:11px;color:var(--text2)">1 hour ago</span></div></div><button class="btn btn-sm btn-danger" onclick="showToast('Investigation started')">Investigate</button></div>
        <div class="alert-card high"><div style="font-size:24px">🚨</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">3 states have SLA compliance below 80%</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Yobe (28.3%), Taraba (31.7%), Zamfara (33.9%) are significantly below target.</div><div style="display:flex;gap:12px;align-items:center"><span class="chip chip-danger">Support SLA</span><span style="font-size:11px;color:var(--text2)">1 hour ago</span></div></div><button class="btn btn-sm btn-danger" onclick="showToast('Action plan opened')">Take Action</button></div>
        <div class="alert-card medium"><div style="font-size:24px">⚠️</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">18 overdue KYC verifications</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Growers pending KYC for more than 14 days. Risk of compliance breach.</div><div style="display:flex;gap:12px;align-items:center"><span class="chip chip-warn">Compliance</span><span style="font-size:11px;color:var(--text2)">25 mins ago</span></div></div><button class="btn btn-sm btn-warn" onclick="showToast('KYC review opened')">Review</button></div>
        <div class="alert-card medium"><div style="font-size:24px">⚠️</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Multiple refunds detected from same user</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">User John Okafor has 3 refund requests in 24 hours. Potential abuse pattern.</div><div style="display:flex;gap:12px;align-items:center"><span class="chip chip-warn">Wallet</span><span style="font-size:11px;color:var(--text2)">3 hours ago</span></div></div><button class="btn btn-sm btn-warn" onclick="showToast('User flagged')">Flag User</button></div>
        <div class="alert-card medium"><div style="font-size:24px">⚠️</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Wallet reconciliation pending for 2 accounts</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">GTBank and First Bank statements awaiting match. 23 unmatched transactions.</div><div style="display:flex;gap:12px;align-items:center"><span class="chip chip-warn">Wallet</span><span style="font-size:11px;color:var(--text2)">2 hours ago</span></div></div><button class="btn btn-sm btn-warn" onclick="showToast('Reconciliation opened')">Reconcile</button></div>
        <div class="alert-card low"><div style="font-size:24px">ℹ️</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Unusual login activity detected</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Login from new device in Abia State. User: Grace Deh.</div><div style="display:flex;gap:12px;align-items:center"><span class="chip">Security</span><span style="font-size:11px;color:var(--text2)">5 hours ago</span></div></div><button class="btn btn-sm btn-secondary" onclick="showToast('Alert acknowledged')">Acknowledge</button></div>
      </div></div>
    </div>

    <!-- SETTINGS -->
    <div class="page" id="page-settings">
      <div class="page-header"><div><div class="page-title">Settings</div><div class="page-subtitle">Configure reports workspace</div></div></div>
      <div class="tabs"><div class="tab active">General</div><div class="tab">Data Sources</div><div class="tab">Report Templates</div><div class="tab">Notifications</div></div>
      <div class="card"><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Organization Name</label><input class="form-input" value="NATCODEV"></div><div class="form-group"><label class="form-label">Default Date Range</label><select class="form-select"><option>Last 7 Days</option><option>Last 30 Days</option><option>This Month</option><option>Custom</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Default Currency</label><select class="form-select"><option>Nigerian Naira (₦)</option><option>USD ($)</option></select></div><div class="form-group"><label class="form-label">Timezone</label><select class="form-select"><option>Africa/Lagos (WAT)</option></select></div></div><div class="form-group"><label class="form-label">Auto-Refresh Interval</label><select class="form-select"><option>Every 5 minutes</option><option>Every 15 minutes</option><option>Every 30 minutes</option><option>Manual only</option></select></div><div style="display:flex;gap:10px"><button class="btn btn-primary" onclick="showToast('Settings saved')">Save Changes</button><button class="btn btn-secondary">Cancel</button></div></div></div>
    </div>

    <!-- REPORT TEMPLATES -->
    <div class="page" id="page-report-templates">
      <div class="page-header"><div><div class="page-title">Report Templates</div><div class="page-subtitle">Manage report templates and layouts</div></div><button class="btn btn-primary" onclick="showToast('Template editor opened')">+ New Template</button></div>
      <div class="grid-3">
        <div class="card"><div class="card-body" style="text-align:center"><div style="font-size:50px;margin-bottom:12px"></div><div style="font-weight:700;font-size:15px">Executive Dashboard</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Platform-wide KPI overview</div><div style="font-size:11px;color:var(--text2);margin-bottom:12px">Last updated: May 20, 2026</div><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-primary">Preview</button></div></div>
        <div class="card"><div class="card-body" style="text-align:center"><div style="font-size:50px;margin-bottom:12px"></div><div style="font-weight:700;font-size:15px">State Performance</div><div style="font-size:12px;color:var(--text2);margin:6px 0">State-level metrics breakdown</div><div style="font-size:11px;color:var(--text2);margin-bottom:12px">Last updated: May 18, 2026</div><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-primary">Preview</button></div></div>
        <div class="card"><div class="card-body" style="text-align:center"><div style="font-size:50px;margin-bottom:12px">🔒</div><div style="font-weight:700;font-size:15px">Compliance Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Audit and compliance metrics</div><div style="font-size:11px;color:var(--text2);margin-bottom:12px">Last updated: May 15, 2026</div><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-primary">Preview</button></div></div>
        <div class="card"><div class="card-body" style="text-align:center"><div style="font-size:50px;margin-bottom:12px">💰</div><div style="font-weight:700;font-size:15px">Financial Summary</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Revenue and transaction analysis</div><div style="font-size:11px;color:var(--text2);margin-bottom:12px">Last updated: May 22, 2026</div><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-primary">Preview</button></div></div>
        <div class="card"><div class="card-body" style="text-align:center"><div style="font-size:50px;margin-bottom:12px"></div><div style="font-weight:700;font-size:15px">Academy Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Learner and course metrics</div><div style="font-size:11px;color:var(--text2);margin-bottom:12px">Last updated: May 19, 2026</div><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-primary">Preview</button></div></div>
        <div class="card" style="border:2px dashed var(--border);cursor:pointer" onclick="showToast('Template editor opened')"><div class="card-body" style="text-align:center;padding:40px"><div style="font-size:40px;margin-bottom:12px;opacity:.5">➕</div><div style="font-weight:700;font-size:15px;color:var(--text2)">Create New Template</div><div style="font-size:12px;color:var(--text2);margin-top:6px">Design a custom report layout</div></div></div>
      </div>
    </div>

    <!-- DATA SOURCES -->
    <div class="page" id="page-data-sources">
      <div class="page-header"><div><div class="page-title">Data Sources</div><div class="page-subtitle">Manage data connections and integrations</div></div><button class="btn btn-primary" onclick="openModal('datasourceModal')">+ Add Data Source</button></div>
      <div class="card"><div class="card-body p0"><table><thead><tr><th>Source</th><th>Type</th><th>Status</th><th>Last Sync</th><th>Records</th><th>Actions</th></tr></thead><tbody><tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--g100);color:var(--g700)">📋</div><strong>Registry Database</strong></div></td><td>PostgreSQL</td><td><span class="status-badge status-active">Connected</span></td><td>May 24, 11:45 PM</td><td>89,642</td><td><button class="btn btn-sm btn-secondary" onclick="showToast('Syncing...')">Sync Now</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fef3c7;color:#92400e">🛒</div><strong>Marketplace API</strong></div></td><td>REST API</td><td><span class="status-badge status-active">Connected</span></td><td>May 24, 11:50 PM</td><td>24,531</td><td><button class="btn btn-sm btn-secondary" onclick="showToast('Syncing...')">Sync Now</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#dbeafe;color:#1e40af">💰</div><strong>Wallet System</strong></div></td><td>PostgreSQL</td><td><span class="status-badge status-active">Connected</span></td><td>May 24, 11:55 PM</td><td>32,845</td><td><button class="btn btn-sm btn-secondary" onclick="showToast('Syncing...')">Sync Now</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#ede9fe;color:#5b21b6">🎓</div><strong>Academy LMS</strong></div></td><td>REST API</td><td><span class="status-badge status-active">Connected</span></td><td>May 24, 11:40 PM</td><td>3,624</td><td><button class="btn btn-sm btn-secondary" onclick="showToast('Syncing...')">Sync Now</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fee2e2;color:#991b1b">🏦</div><strong>Bank Statements</strong></div></td><td>CSV Upload</td><td><span class="status-badge status-warn">Pending</span></td><td>May 23, 11:45 PM</td><td>—</td><td><button class="btn btn-sm btn-warn" onclick="showToast('Upload opened')">Upload</button></td></tr></tbody></table></div></div>
    </div>

    <!-- USER PERMISSIONS -->
    <div class="page" id="page-user-permissions">
      <div class="page-header"><div><div class="page-title">User Permissions</div><div class="page-subtitle">Manage report access and roles</div></div><button class="btn btn-primary" onclick="openModal('userModal')">+ Add User</button></div>
      <div class="card"><div class="card-body p0"><table><thead><tr><th>User</th><th>Email</th><th>Role</th><th>Access Level</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td><div class="avatar-row"><div class="avatar-sm">GD</div><div><strong>Grace Deh</strong><br><small style="color:var(--text2)">Super Admin</small></div></div></td><td>grace.deh@natcodev.org</td><td><span class="chip">Super Admin</span></td><td>Full Access</td><td>May 24, 2026 09:15</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">AM</div><div><strong>Aisha Musa</strong><br><small style="color:var(--text2)">Field Agent</small></div></div></td><td>aisha.m@natcodev.org</td><td><span class="chip">Field Agent</span></td><td>Read Only</td><td>May 24, 2026 08:30</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">JO</div><div><strong>James Okon</strong><br><small style="color:var(--text2)">Analyst</small></div></div></td><td>james.o@natcodev.org</td><td><span class="chip">Analyst</span></td><td>Read + Export</td><td>May 24, 2026 07:45</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">EU</div><div><strong>Esther Udo</strong><br><small style="color:var(--text2)">Manager</small></div></div></td><td>esther.u@natcodev.org</td><td><span class="chip">Manager</span></td><td>Read + Generate</td><td>May 23, 2026 16:20</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">IB</div><div><strong>Ibrahim Bello</strong><br><small style="color:var(--text2)">Viewer</small></div></div></td><td>ibrahim.b@natcodev.org</td><td><span class="chip">Viewer</span></td><td>Read Only</td><td>May 20, 2026 14:10</td><td><span class="status-badge status-draft">Inactive</span></td><td><button class="btn-icon">⋮</button></td></tr></tbody></table></div></div>
    </div>

  </div>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="generateReportModal"><div class="modal"><div class="modal-header"><div class="modal-title">Generate Report</div><button class="btn-icon" onclick="closeModal('generateReportModal')"></button></div><div class="modal-body"><div class="form-group"><label class="form-label">Report Type</label><select class="form-select"><option>Executive Summary</option><option>Registry Report</option><option>Marketplace Report</option><option>Wallet & Finance</option><option>Academy Report</option><option>Compliance Report</option><option>State Performance</option><option>Custom Report</option></select></div><div class="form-row"><div class="form-group"><label class="form-label">Date From</label><input class="form-input" type="date" value="2026-05-18"></div><div class="form-group"><label class="form-label">Date To</label><input class="form-input" type="date" value="2026-05-24"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Format</label><select class="form-select"><option>PDF</option><option>Excel (XLSX)</option><option>CSV</option><option>Interactive Dashboard</option></select></div><div class="form-group"><label class="form-label">Audience</label><select class="form-select"><option>Executive</option><option>Management</option><option>Operations</option><option>Public</option></select></div></div><div class="form-group"><label class="form-label">Include Sections</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px"><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Overview</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Registry</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Marketplace</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Wallet</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Academy</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Field Operations</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Compliance</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Intelligence</label></div></div><div class="form-group"><label class="form-label">Additional Notes</label><textarea class="form-textarea" placeholder="Optional notes for the report..."></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('generateReportModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('generateReportModal');showToast('Report generated successfully')">Generate Report</button></div></div></div>

<div class="modal-overlay" id="scheduleModal"><div class="modal"><div class="modal-header"><div class="modal-title">Schedule New Report</div><button class="btn-icon" onclick="closeModal('scheduleModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Report Name</label><input class="form-input" placeholder="e.g. Weekly Executive Summary"></div><div class="form-row"><div class="form-group"><label class="form-label">Report Type</label><select class="form-select"><option>Executive Summary</option><option>Registry Report</option><option>Marketplace Report</option><option>Wallet Statement</option><option>Compliance Report</option></select></div><div class="form-group"><label class="form-label">Frequency</label><select class="form-select"><option>Daily</option><option>Weekly</option><option>Bi-weekly</option><option>Monthly</option><option>Custom</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Run Time</label><input class="form-input" type="time" value="08:00"></div><div class="form-group"><label class="form-label">Format</label><select class="form-select"><option>PDF</option><option>Excel</option><option>CSV</option></select></div></div><div class="form-group"><label class="form-label">Recipients (Email)</label><textarea class="form-textarea" placeholder="email1@natcodev.org, email2@natcodev.org"></textarea></div><div class="form-group"><label class="form-label">Start Date</label><input class="form-input" type="date"></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('scheduleModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('scheduleModal');showToast('Report scheduled')">Schedule Report</button></div></div></div>

<div class="modal-overlay" id="datasourceModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Data Source</div><button class="btn-icon" onclick="closeModal('datasourceModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Source Name</label><input class="form-input" placeholder="e.g. Registry Database"></div><div class="form-group"><label class="form-label">Source Type</label><select class="form-select"><option>PostgreSQL</option><option>MySQL</option><option>REST API</option><option>CSV Upload</option><option>Google Sheets</option></select></div><div class="form-group"><label class="form-label">Connection String / URL</label><input class="form-input" placeholder="postgresql://..."></div><div class="form-row"><div class="form-group"><label class="form-label">Username</label><input class="form-input"></div><div class="form-group"><label class="form-label">Password</label><input class="form-input" type="password"></div></div><div class="form-group"><label class="form-label">Sync Frequency</label><select class="form-select"><option>Every 5 minutes</option><option>Every 15 minutes</option><option>Every hour</option><option>Daily</option><option>Manual</option></select></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('datasourceModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('datasourceModal');showToast('Data source added')">Add Source</button></div></div></div>

<div class="modal-overlay" id="userModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add User</div><button class="btn-icon" onclick="closeModal('userModal')">✕</button></div><div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Full Name</label><input class="form-input"></div><div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Role</label><select class="form-select"><option>Super Admin</option><option>Admin</option><option>Manager</option><option>Analyst</option><option>Viewer</option></select></div><div class="form-group"><label class="form-label">Access Level</label><select class="form-select"><option>Full Access</option><option>Read + Generate</option><option>Read + Export</option><option>Read Only</option></select></div></div><div class="form-group"><label class="form-label">Allowed Modules</label><select class="form-select" multiple style="min-height:100px"><option>Registry</option><option>Marketplace</option><option>Wallet</option><option>Academy</option><option>Field Operations</option><option>Compliance</option></select></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('userModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('userModal');showToast('User created')">Create User</button></div></div></div>

<div class="toast" id="toast"></div>

<script>
const REPORT = <?= json_encode($reportPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
const num = value => Number(value || 0).toLocaleString();
const money = value => 'NGN ' + Number(value || 0).toLocaleString(undefined, {maximumFractionDigits:0});
const pct = value => Number(value || 0).toFixed(1) + '%';
const dateOnly = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : '-';
const dateTime = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString(undefined, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}) : '-';
const m = key => REPORT.metrics?.[key] ?? 0;
function statusBadge(status){
  const s = String(status || 'active').toLowerCase().replace(/_/g, ' ');
  return `<span class="status-badge status-${esc(s.replace(/\s+/g, '-'))}">${esc(s.replace(/\b\w/g, c => c.toUpperCase()))}</span>`;
}
function progress(value){
  const width = Math.max(0, Math.min(100, Number(value || 0)));
  return `<div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:${width}%"></div></div>`;
}
function fillTable(selector, rows, cols){
  const body = document.querySelector(selector);
  if (!body) return;
  body.innerHTML = rows.length ? rows.join('') : `<tr><td colspan="${cols}">No records available.</td></tr>`;
}
function setStat(label, value, sub=''){
  document.querySelectorAll('.stat-card-label').forEach(el => {
    if (el.textContent.trim().toLowerCase() === label.toLowerCase()) {
      const card = el.closest('.stat-card');
      const valueEl = card?.querySelector('.stat-card-value');
      if (valueEl) valueEl.textContent = value;
      const subEl = card?.querySelector('.stat-card-sub,.stat-card-change');
      if (subEl && sub) subEl.textContent = sub;
    }
  });
}
function stateRow(row, i=0){
  const rate = Number(row.growers || 0) ? (Number(row.verified || 0) / Number(row.growers || 1)) * 100 : 0;
  return `<tr><td>${i + 1}</td><td><strong>${esc(row.state_name)}</strong></td><td>${num(row.growers)}</td><td>${num(row.verified)}</td><td>${progress(rate)}</td><td style="color:var(--success)">Live</td></tr>`;
}
function statePerfRow(row){
  const rate = Number(row.growers || 0) ? (Number(row.verified || 0) / Number(row.growers || 1)) * 100 : 0;
  return `<tr><td><strong>${esc(row.state_name)}</strong></td><td>-</td><td>${num(row.growers)}</td><td>${num(row.verified)}</td><td>${money(0)}</td><td>${pct(m('academyCompletion'))}</td><td>${pct(m('slaCompliance'))}</td><td>${progress(rate)}</td></tr>`;
}
function certRow(row){
  return `<tr><td><strong>${esc(row.state_name)}</strong></td><td>${num(row.issued)}</td><td>${num(row.valid)}</td><td>${num(row.expiring)}</td><td>${num(row.expired)}</td><td>${num(row.revoked)}</td></tr>`;
}
function sellerRow(row, i=0){
  return `<tr><td>${i + 1}</td><td><div class="avatar-row"><div class="avatar-sm">${esc(String(row.store_name || 'S').slice(0,2).toUpperCase())}</div>${esc(row.store_name || 'Seller')}</div></td><td>${num(row.products)}</td><td>${num(row.orders)}</td><td>${money(row.revenue)}</td><td style="color:var(--success)">Live</td></tr>`;
}
function productRow(row){
  return `<tr><td>${esc(row.product_name)}</td><td>${num(row.units)}</td><td>${money(row.revenue)}</td></tr>`;
}
function walletRow(row){
  const type = ['debit','payment','payout','withdrawal'].includes(String(row.type || '').toLowerCase()) || String(row.direction || '').includes('out') ? 'Debit' : 'Credit';
  const sign = type === 'Debit' ? '-' : '+';
  return `<tr><td><strong>${esc(row.reference || 'TRX-' + row.id)}</strong></td><td>${dateTime(row.created_at)}</td><td>${statusBadge(type)}</td><td>${esc(row.user_name || row.user_email || 'Platform')}</td><td style="color:var(${type === 'Debit' ? '--danger' : '--success'});font-weight:600">${sign}${money(Math.abs(Number(row.amount || 0)))}</td><td>${statusBadge(row.status)}</td></tr>`;
}
function courseRow(row){
  const rate = Number(row.enrolled || 0) ? Number(row.completed || 0) / Number(row.enrolled || 1) * 100 : 0;
  return `<tr><td>${esc(row.course_title || 'Course')}</td><td>${num(row.enrolled)}</td><td>${num(row.completed)}</td><td>${progress(rate)}</td></tr>`;
}
function supportRow(row){
  const total = Number(row.opened || 0);
  const met = total ? Number(row.resolved || 0) / total * 100 : 0;
  return `<tr><td>${esc(row.category || 'General')}</td><td>${num(row.opened)}</td><td>${num(row.resolved)}</td><td>${num(row.pending)}</td><td>-</td><td>${progress(met)}</td></tr>`;
}
function agentRow(row){
  const perf = Number(row.visits || 0) ? Math.min(100, Number(row.verifications || 0) / Number(row.visits || 1) * 100) : 0;
  return `<tr><td><div class="avatar-row"><div class="avatar-sm">${esc(String(row.agent || 'A').slice(0,2).toUpperCase())}</div>${esc(row.agent || 'Agent')}</div></td><td>${esc(row.state || '-')}</td><td>${num(row.visits)}</td><td>${num(row.verifications)}</td><td>${progress(perf)}</td></tr>`;
}
function scheduleRow(row){
  const recipients = String(row.recipients || '').split(',').filter(Boolean).length || (row.recipients ? 1 : 0);
  const nextStatus = String(row.status || 'active').toLowerCase() === 'active' ? 'paused' : 'active';
  return `<tr><td><strong>${esc(row.report_name)}</strong></td><td>${esc(row.frequency)}</td><td>${dateTime(row.next_run_at)}</td><td>${dateTime(row.last_run_at)}</td><td>${num(recipients)}</td><td><span class="chip">${esc(row.format)}</span></td><td>${statusBadge(row.status)}</td><td>${postForm('update_schedule_status', {schedule_id: row.id, status: nextStatus, page: 'scheduled-reports'}, nextStatus === 'paused' ? 'Pause' : 'Resume', 'btn btn-sm btn-secondary')} <a class="btn btn-sm btn-secondary" href="?export=schedules&page=scheduled-reports">Export</a></td></tr>`;
}
function sourceRow(row){
  return `<tr><td><div class="avatar-row"><div class="avatar-sm">${esc(String(row.source_name || 'DS').slice(0,2).toUpperCase())}</div><strong>${esc(row.source_name)}</strong></div></td><td>${esc(row.source_type)}</td><td>${statusBadge(row.status)}</td><td>${dateTime(row.last_sync_at || row.updated_at || row.created_at)}</td><td>${num(row.records_count)}</td><td>${postForm('sync_data_source', {source_id: row.id, records_count: row.records_count || 0, page: 'data-sources'}, 'Sync Now', 'btn btn-sm btn-secondary')}</td></tr>`;
}
function runRow(row){
  return `<tr><td><strong>${esc(row.report_ref)}</strong></td><td>${esc(row.report_type)}</td><td>${esc(row.format)}</td><td>${dateTime(row.generated_at)}</td><td>${statusBadge(row.status)}</td><td>${esc(row.generated_by_name || 'Admin')}</td></tr>`;
}
function reportUserRow(row){
  return `<tr><td><div class="avatar-row"><div class="avatar-sm">${esc(String(row.name || 'U').slice(0,2).toUpperCase())}</div><div><strong>${esc(row.name || 'User')}</strong><br><small style="color:var(--text2)">${esc(row.platform_role || row.role || 'stakeholder')}</small></div></div></td><td>${esc(row.email)}</td><td><span class="chip">${esc(row.platform_role || row.role || 'user')}</span></td><td>${esc(row.access_level || 'Read Only')}</td><td>${dateOnly(row.created_at)}</td><td>${statusBadge(row.report_status || row.account_status || 'active')}</td><td><button class="btn-icon" onclick="openModal('userModal')">...</button></td></tr>`;
}
function postForm(action, fields, label, cls='btn btn-sm btn-primary'){
  const inputs = Object.entries({action, ...fields}).map(([key, value]) => `<input type="hidden" name="${esc(key)}" value="${esc(value)}">`).join('');
  return `<form method="post" style="display:inline">${inputs}<button class="${cls}" type="submit">${esc(label)}</button></form>`;
}
function templateCard(row){
  return `<div class="card"><div class="card-body" style="text-align:center"><div style="font-weight:700;font-size:15px">${esc(row.title)}</div><div style="font-size:12px;color:var(--text2);margin:6px 0">${esc(row.description || row.module)}</div><div style="font-size:11px;color:var(--text2);margin-bottom:12px">${esc(row.module)} / ${esc(row.audience)} / ${statusBadge(row.status)}</div><button class="btn btn-sm btn-secondary" onclick="openModal('templateModal')">Edit</button> <a class="btn btn-sm btn-primary" href="?export=${esc(row.module)}&page=exports">Export</a></div></div>`;
}
function alertCard(row){
  const cls = ['critical','high'].includes(String(row.severity).toLowerCase()) ? 'high' : (String(row.severity).toLowerCase() === 'low' ? 'low' : 'medium');
  return `<div class="alert-card ${cls}"><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">${esc(row.title)}</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">${esc(row.description || '')}</div><div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><span class="chip ${cls === 'high' ? 'chip-danger' : cls === 'medium' ? 'chip-warn' : ''}">${esc(row.module)}</span><span class="chip">${esc(row.severity)}</span><span style="font-size:11px;color:var(--text2)">${dateTime(row.created_at)}</span>${statusBadge(row.status)}</div></div><div style="display:flex;gap:6px;align-items:center">${postForm('update_alert_status', {alert_id: row.id, status: 'acknowledged', page: 'exceptions'}, 'Ack', 'btn btn-sm btn-secondary')} ${postForm('update_alert_status', {alert_id: row.id, status: 'resolved', page: 'exceptions'}, 'Resolve', 'btn btn-sm btn-primary')}</div></div>`;
}
function interestRow(row){
  return `<tr><td><strong>${esc(row.stakeholder_role)}</strong></td><td>${esc(row.interest_area)}</td><td>${esc(row.module)}</td><td>${statusBadge(row.priority)}</td><td>${esc(row.default_report || '-')}</td><td>${esc(row.delivery_channel)}</td><td>${statusBadge(row.status)}</td></tr>`;
}
function userOptions(){
  return REPORT.users.map(u => `<option value="${esc(u.id)}">${esc(u.name)} (${esc(u.email)} / ${esc(u.platform_role || u.role || 'user')})</option>`).join('');
}
function hydrateReportForms(){
  const formStart = (action, page) => `<form method="post"><input type="hidden" name="action" value="${action}"><input type="hidden" name="page" value="${page}">`;
  const footer = label => `<div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal(this.closest('.modal-overlay').id)">Cancel</button><button class="btn btn-primary" type="submit">${label}</button></div></form>`;
  const gen = document.querySelector('#generateReportModal .modal');
  if (gen) gen.innerHTML = `<div class="modal-header"><div class="modal-title">Generate Report</div><button class="btn-icon" onclick="closeModal('generateReportModal')" type="button">X</button></div>${formStart('generate_report','exports')}<div class="modal-body"><div class="form-group"><label class="form-label">Report Type</label><select class="form-select" name="report_type"><option value="executive_summary">Executive Summary</option><option value="registry">Registry Report</option><option value="marketplace">Marketplace Report</option><option value="wallet">Wallet & Finance</option><option value="academy">Academy Report</option><option value="support">Support SLA</option><option value="field">Field Operations</option><option value="compliance">Compliance</option></select></div><div class="form-row"><div class="form-group"><label class="form-label">Date From</label><input class="form-input" name="date_from" type="date" value="${esc(REPORT.dateFrom)}"></div><div class="form-group"><label class="form-label">Date To</label><input class="form-input" name="date_to" type="date" value="${esc(REPORT.dateTo)}"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Format</label><select class="form-select" name="format"><option value="csv">CSV</option><option value="pdf">PDF</option><option value="xlsx">Excel</option></select></div><div class="form-group"><label class="form-label">Audience</label><select class="form-select" name="audience"><option value="executive">Executive</option><option value="management">Management</option><option value="operations">Operations</option><option value="public">Public</option></select></div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div></div>${footer('Generate Report')}`;
  const sched = document.querySelector('#scheduleModal .modal');
  if (sched) sched.innerHTML = `<div class="modal-header"><div class="modal-title">Schedule New Report</div><button class="btn-icon" onclick="closeModal('scheduleModal')" type="button">X</button></div>${formStart('schedule_report','scheduled-reports')}<div class="modal-body"><div class="form-group"><label class="form-label">Report Name</label><input class="form-input" name="report_name" required></div><div class="form-row"><div class="form-group"><label class="form-label">Report Type</label><select class="form-select" name="report_type"><option value="executive_summary">Executive Summary</option><option value="registry">Registry</option><option value="marketplace">Marketplace</option><option value="wallet">Wallet</option><option value="academy">Academy</option><option value="support">Support</option></select></div><div class="form-group"><label class="form-label">Frequency</label><select class="form-select" name="frequency"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Run Time</label><input class="form-input" name="run_time" type="time" value="08:00"></div><div class="form-group"><label class="form-label">Format</label><select class="form-select" name="format"><option value="csv">CSV</option><option value="pdf">PDF</option><option value="xlsx">Excel</option></select></div></div><div class="form-group"><label class="form-label">Next Run</label><input class="form-input" name="next_run_at" type="datetime-local"></div><div class="form-group"><label class="form-label">Recipients</label><textarea class="form-textarea" name="recipients"></textarea></div></div>${footer('Schedule Report')}`;
  const ds = document.querySelector('#datasourceModal .modal');
  if (ds) ds.innerHTML = `<div class="modal-header"><div class="modal-title">Add Data Source</div><button class="btn-icon" onclick="closeModal('datasourceModal')" type="button">X</button></div>${formStart('add_data_source','data-sources')}<div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Source Name</label><input class="form-input" name="source_name" required></div><div class="form-group"><label class="form-label">Source Type</label><select class="form-select" name="source_type"><option value="MySQL">MySQL</option><option value="REST API">REST API</option><option value="CSV Upload">CSV Upload</option><option value="External">External</option></select></div></div><div class="form-group"><label class="form-label">Connection Label</label><input class="form-input" name="connection_label"></div><div class="form-group"><label class="form-label">Sync Frequency</label><select class="form-select" name="sync_frequency"><option value="manual">Manual</option><option value="every_5_minutes">Every 5 minutes</option><option value="every_15_minutes">Every 15 minutes</option><option value="daily">Daily</option></select></div></div>${footer('Add Source')}`;
  const user = document.querySelector('#userModal .modal');
  if (user) user.innerHTML = `<div class="modal-header"><div class="modal-title">Grant Report Access</div><button class="btn-icon" onclick="closeModal('userModal')" type="button">X</button></div>${formStart('grant_report_access','user-permissions')}<div class="modal-body"><div class="form-group"><label class="form-label">Stakeholder</label><select class="form-select" name="user_id">${userOptions()}</select></div><div class="form-row"><div class="form-group"><label class="form-label">Access Level</label><select class="form-select" name="access_level"><option value="full_access">Full Access</option><option value="read_generate">Read + Generate</option><option value="read_export">Read + Export</option><option value="read_only">Read Only</option></select></div><div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div><div class="form-group"><label class="form-label">Allowed Modules</label><input class="form-input" name="modules" value="Registry, Marketplace, Wallet, Academy, Support"></div></div>${footer('Save Access')}`;
}
function hydrateReportWorkspace(){
  console.log("REPORT object:", REPORT);
  console.log("REPORT.states:", REPORT.states);
  setStat('Registered Growers', num(m('growers')), `${pct(m('verificationRate'))} verified`);
  setStat('Verified Growers', num(m('verifiedGrowers')), `${pct(m('verificationRate'))} of growers`);
  setStat('State Coverage', `${num(m('statesCovered'))} / 36`, 'States with records');
  setStat('Marketplace GMV (7D)', money(m('gmv')), `${num(m('orders'))} orders`);
  setStat('Wallet Volume (7D)', money(m('walletVolume')), `${money(m('walletBalance'))} balance`);
  setStat('Academy Completion', pct(m('academyCompletion')), `${num(m('enrollments'))} enrollments`);
  setStat('SLA Compliance', pct(m('slaCompliance')), `${num(m('supportOpen'))} open tickets`);
  setStat('Open Tickets', num(m('supportOpen')), 'Live support desk');
  setStat('Resolved (7D)', num(m('supportResolved')), 'Closed tickets');
  setStat('Field Visits (7D)', num(m('fieldVisits')), `${num(m('fieldAgents'))} agents`);
  setStat('Active Agents', num(m('fieldAgents')), 'Field network');
  setStat('Active Schedules', num(REPORT.schedules.filter(s => String(s.status).toLowerCase() === 'active').length), 'Automated reports');
  setStat('Active Interest Rules', num(m('activeInterests')), 'Stakeholder interest map');
  setStat('Stakeholder Roles', num(new Set(REPORT.interests.map(i => i.stakeholder_role)).size), 'Roles covered');
  setStat('High Priority Interests', num(REPORT.interests.filter(i => ['critical','high'].includes(String(i.priority).toLowerCase())).length), 'Action-led reports');
  setStat('Dashboard Delivery', num(REPORT.interests.filter(i => String(i.delivery_channel || '').includes('dashboard')).length), 'Dashboard-enabled');
  fillTable('#page-grower-report table tbody', REPORT.states.map(stateRow), 6);
  fillTable('#page-state-lga table tbody', REPORT.states.map(statePerfRow), 8);
  fillTable('#page-certificate-report table tbody', REPORT.certificates.map(certRow), 6);
  fillTable('#page-marketplace-report .grid-2 .card:first-child table tbody', REPORT.products.map(productRow), 3);
  fillTable('#page-marketplace-report .card:last-child table tbody', REPORT.sellers.map(sellerRow), 6);
  fillTable('#page-wallet-finance table tbody', REPORT.walletTransactions.map(walletRow), 6);
  fillTable('#page-academy-report table tbody', REPORT.courses.map(courseRow), 4);
  fillTable('#page-support-sla .card:last-child table tbody', REPORT.support.map(supportRow), 6);
  fillTable('#page-field-operations .grid-2 .card:last-child table tbody', REPORT.agents.map(agentRow), 5);
  fillTable('#page-scheduled-reports table tbody', REPORT.schedules.map(scheduleRow), 8);
  fillTable('#page-data-sources table tbody', REPORT.sources.map(sourceRow), 6);
  fillTable('#page-user-permissions table tbody', REPORT.reportUsers.map(reportUserRow), 7);
  fillTable('#page-stakeholder-interests table tbody', REPORT.interests.map(interestRow), 7);
  const templateGrid = document.querySelector('#page-report-templates .grid-3');
  if (templateGrid) templateGrid.innerHTML = REPORT.templates.map(templateCard).join('') + `<div class="card" style="border:2px dashed var(--border);cursor:pointer" onclick="openModal('templateModal')"><div class="card-body" style="text-align:center;padding:40px"><div style="font-size:40px;margin-bottom:12px;opacity:.5">+</div><div style="font-weight:700;font-size:15px;color:var(--text2)">Create New Template</div><div style="font-size:12px;color:var(--text2);margin-top:6px">Design a report template</div></div></div>`;
  const exceptionsBody = document.querySelector('#page-exceptions .card .card-body');
  if (exceptionsBody) {
    exceptionsBody.innerHTML = `<form method="post" class="form-row-3" style="align-items:end;margin-bottom:16px"><input type="hidden" name="action" value="save_alert"><input type="hidden" name="page" value="exceptions"><div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" required></div><div class="form-group"><label class="form-label">Module</label><select class="form-select" name="module"><option value="registry">Registry</option><option value="marketplace">Marketplace</option><option value="wallet">Wallet</option><option value="academy">Academy</option><option value="support">Support</option><option value="field">Field</option><option value="platform">Platform</option></select></div><div class="form-group"><label class="form-label">Severity</label><select class="form-select" name="severity"><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></div><div class="form-group"><label class="form-label">Due At</label><input class="form-input" type="datetime-local" name="due_at"></div><div class="form-group" style="grid-column:1/-1"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div><button class="btn btn-primary" type="submit">Save Alert</button></form>${REPORT.alerts.map(alertCard).join('') || '<div style="padding:14px">No active alerts.</div>'}`;
  }
  const settingsBody = document.querySelector('#page-settings .card .card-body');
  if (settingsBody) {
    const setting = key => (REPORT.settings.find(s => s.setting_key === key)?.setting_value || '');
    settingsBody.innerHTML = `<form method="post"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="page" value="settings"><div class="form-row"><div class="form-group"><label class="form-label">Default Date Range</label><select class="form-select" name="default_date_range"><option value="last_7_days">Last 7 Days</option><option value="last_30_days">Last 30 Days</option><option value="this_month">This Month</option><option value="custom">Custom</option></select></div><div class="form-group"><label class="form-label">Default Currency</label><select class="form-select" name="default_currency"><option value="NGN">Nigerian Naira (NGN)</option><option value="USD">USD</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Timezone</label><input class="form-input" name="timezone" value="${esc(setting('timezone') || 'Africa/Lagos')}"></div><div class="form-group"><label class="form-label">Auto Refresh</label><select class="form-select" name="auto_refresh"><option value="manual">Manual only</option><option value="5_minutes">Every 5 minutes</option><option value="15_minutes">Every 15 minutes</option><option value="30_minutes">Every 30 minutes</option></select></div></div><div class="form-group"><label class="form-label">Stakeholder Digest</label><select class="form-select" name="stakeholder_digest"><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></div><button class="btn btn-primary" type="submit">Save Settings</button></form>`;
  }
  if (!document.getElementById('templateModal')) {
    document.body.insertAdjacentHTML('beforeend', `<div class="modal-overlay" id="templateModal"><div class="modal"><div class="modal-header"><div class="modal-title">Report Template</div><button class="btn-icon" onclick="closeModal('templateModal')" type="button">X</button></div><form method="post"><input type="hidden" name="action" value="save_template"><input type="hidden" name="page" value="report-templates"><div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Template Key</label><input class="form-input" name="template_key" required></div><div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" required></div></div><div class="form-row"><div class="form-group"><label class="form-label">Module</label><select class="form-select" name="module"><option value="platform">Platform</option><option value="registry">Registry</option><option value="marketplace">Marketplace</option><option value="wallet">Wallet</option><option value="academy">Academy</option><option value="support">Support</option><option value="field">Field</option></select></div><div class="form-group"><label class="form-label">Audience</label><select class="form-select" name="audience"><option value="executive">Executive</option><option value="management">Management</option><option value="operations">Operations</option><option value="public">Public</option></select></div></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div><div class="form-group"><label class="form-label">Layout Config</label><textarea class="form-textarea" name="layout_config" placeholder="Sections, filters, metrics, chart notes"></textarea></div><div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="inactive">Inactive</option></select></div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('templateModal')">Cancel</button><button class="btn btn-primary" type="submit">Save Template</button></div></form></div></div>`);
  }
  const exportGrid = document.querySelector('#page-exports .grid-3');
  if (exportGrid) exportGrid.innerHTML = [
    ['registry','Registry Report','Grower & verification data'],
    ['marketplace','Marketplace Report','GMV, revenue, sellers'],
    ['wallet','Wallet Statement','Transactions & balances'],
    ['academy','Academy Report','Learners & completion'],
    ['support','Support SLA','Tickets and service levels'],
    ['field','Field Operations','Visits and assignments']
  ].map(([key,title,desc]) => `<a class="card" style="text-decoration:none;color:inherit" href="?export=${key}&page=exports"><div class="card-body" style="text-align:center;padding:30px"><div style="font-weight:700;font-size:15px">${title}</div><div style="font-size:12px;color:var(--text2);margin:6px 0">${desc}</div><span class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</span></div></a>`).join('');
  const customExport = document.querySelector('#page-exports .card:last-child .card-body');
  if (customExport) customExport.innerHTML = `<div class="card-title" style="margin-bottom:10px">Recent Generated Reports</div><table><thead><tr><th>Ref</th><th>Type</th><th>Format</th><th>Generated</th><th>Status</th><th>By</th></tr></thead><tbody>${REPORT.runs.map(runRow).join('') || '<tr><td colspan="6">No generated reports yet.</td></tr>'}</tbody></table>`;
  hydrateReportForms();
  enhanceReportActions();
  paginateReportTables();
  if (REPORT.notice) showToast(REPORT.notice);
  if (REPORT.error) showToast(REPORT.error);
}
function submitReportAction(action, page, fields={}){
  const form = document.createElement('form');
  form.method = 'post';
  form.style.display = 'none';
  Object.entries({action, page, ...fields}).forEach(([key, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}
function reportExportUrl(type){
  const url = new URL(window.location.href);
  url.searchParams.set('export', type);
  url.searchParams.set('page', 'exports');
  return url.toString();
}
function enhanceReportActions(){
  const pageExports = {
    'page-grower-report':'registry',
    'page-verification-report':'registry',
    'page-certificate-report':'compliance',
    'page-state-lga':'registry',
    'page-marketplace-report':'marketplace',
    'page-wallet-finance':'wallet',
    'page-academy-report':'academy',
    'page-support-sla':'support',
    'page-field-operations':'field',
    'page-compliance':'compliance'
  };
  Object.entries(pageExports).forEach(([pageId, type]) => {
    document.querySelectorAll(`#${pageId} .page-header .btn-primary`).forEach(btn => {
      btn.onclick = () => { window.location.href = reportExportUrl(type); };
    });
  });
  document.querySelectorAll('#page-overview .card .btn-secondary').forEach((btn, index) => {
    const types = ['registry','marketplace','wallet','academy','compliance','registry'];
    if (types[index]) btn.onclick = () => { window.location.href = reportExportUrl(types[index]); };
  });
  document.querySelectorAll('#page-insights .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => submitReportAction('generate_report', 'exports', {report_type:'insights', audience:'management', format:'csv', date_from:REPORT.dateFrom, date_to:REPORT.dateTo, notes:'Insights refresh from reports workspace'});
  });
  document.querySelectorAll('#page-exceptions .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => {
      submitReportAction('acknowledge_all_alerts', 'exceptions');
    };
  });
  document.querySelectorAll('#page-settings .btn-secondary').forEach(btn => {
    btn.onclick = () => navigateTo('overview');
  });
  document.querySelectorAll('.btn-icon').forEach(btn => {
    if (!btn.textContent.trim()) btn.textContent = '...';
  });
}
function paginateReportTables(pageSize=25){
  document.querySelectorAll('.page table').forEach((table, index) => {
    const tbody = table.querySelector('tbody');
    if (!tbody || table.dataset.paginated === '1') return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (rows.length <= pageSize) return;
    table.dataset.paginated = '1';
    let page = 1;
    const totalPages = Math.ceil(rows.length / pageSize);
    const nav = document.createElement('div');
    nav.className = 'pagination';
    nav.style.margin = '12px';
    const render = () => {
      rows.forEach((row, i) => row.style.display = i >= (page - 1) * pageSize && i < page * pageSize ? '' : 'none');
      nav.innerHTML = `<button class="btn btn-sm btn-secondary" type="button" ${page === 1 ? 'disabled' : ''}>Previous</button><span class="chip">Page ${page} of ${totalPages}</span><button class="btn btn-sm btn-secondary" type="button" ${page === totalPages ? 'disabled' : ''}>Next</button>`;
      nav.querySelector('button:first-child')?.addEventListener('click', () => { page = Math.max(1, page - 1); render(); });
      nav.querySelector('button:last-child')?.addEventListener('click', () => { page = Math.min(totalPages, page + 1); render(); });
    };
    table.closest('.card-body')?.appendChild(nav);
    render();
  });
}
function navigateTo(page){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  const el=document.getElementById('page-'+page);
  if(el)el.classList.add('active');
  const nav=document.querySelector(`.nav-item[data-page="${page}"]`);
  if(nav)nav.classList.add('active');
  window.scrollTo(0,0);
}
document.querySelectorAll('.nav-item[data-page]').forEach(item=>{
  item.addEventListener('click',()=>{const p=item.getAttribute('data-page');if(p)navigateTo(p)});
});
function openModal(id){const modal=document.getElementById(id);if(modal)modal.classList.add('active')}
function closeModal(id){const modal=document.getElementById(id);if(modal)modal.classList.remove('active')}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('active')})});
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.style.display='block';setTimeout(()=>t.style.display='none',2500)}
function filterTable(tableId,q){
  const t=document.getElementById(tableId);if(!t)return;
  const rows=t.querySelectorAll('tbody tr');const s=q.toLowerCase();
  rows.forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(s)?'':'none'});
}
document.querySelectorAll('.tab').forEach(tab=>{tab.addEventListener('click',function(){this.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));this.classList.add('active')})});
document.querySelectorAll('[data-topbar-menu]').forEach(button => {
  button.addEventListener('click', event => {
    event.stopPropagation();
    const menu = document.getElementById(button.dataset.topbarMenu);
    document.querySelectorAll('.topbar-menu.active').forEach(open => { if (open !== menu) open.classList.remove('active'); });
    menu?.classList.toggle('active');
    button.setAttribute('aria-expanded', menu?.classList.contains('active') ? 'true' : 'false');
  });
});
document.addEventListener('click', event => {
  if (!event.target.closest('.topbar-menu-wrap')) {
    document.querySelectorAll('.topbar-menu.active').forEach(menu => menu.classList.remove('active'));
    document.querySelectorAll('[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
  }
});
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='k'){e.preventDefault();document.getElementById('globalSearch').focus()}});
hydrateReportWorkspace();
navigateTo(REPORT.page || 'overview');
</script>
</body>
</html>

