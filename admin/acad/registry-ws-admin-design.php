<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/certificates.php';
require_once __DIR__ . '/../../lib/identity-validation.php';
require_once __DIR__ . '/../../lib/field-management.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$pdo = db();
admin_ensure_schema($pdo);
app_ensure_certificate_schema($pdo);
identity_ensure_schema($pdo);
fm_ensure_schema($pdo);
admin_require($pdo);

// Export Logic
if (isset($_GET['export'])) {
    $type = (string) $_GET['export'];
    $sql = "";
    $filename = "natcodev_export_" . $type . "_" . date('Y-m-d') . ".csv";
    switch ($type) {
        case 'approved':
            $sql = "SELECT id, app_ref, name, email, phone, location, farm_size, commitments, confirmed, created_at FROM applications WHERE confirmed = 1";
            break;
        case 'rejected':
            $sql = "SELECT id, app_ref, name, email, phone, location, farm_size, commitments, confirmed, created_at FROM applications WHERE confirmed = 0";
            break;
        case 'registry':
        case 'growers':
            $sql = "SELECT u.id, u.name, u.email, u.phone, u.location, u.role, u.created_at FROM users u WHERE u.role = 'grower'";
            break;
        case 'applications':
            $sql = "SELECT id, app_ref, name, email, phone, location, farm_size, commitments, confirmed, created_at FROM applications";
            break;
        case 'certificates':
            $sql = "SELECT id, certificate_ref, user_id, application_id, status, issued_at, expires_at FROM certificates";
            break;
        case 'documents':
            $sql = "SELECT id, user_id, document_type, document_number, verification_status, uploaded_at FROM document_requirements";
            break;
        case 'agents':
            $sql = "SELECT id, name, email, phone, location, role FROM users WHERE role = 'field_agent'";
            break;
        default:
            die('Invalid export type.');
    }
    set_time_limit(0);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    $stmt = $pdo->query($sql);
    $headerSent = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$headerSent) {
            fputcsv($output, array_keys($row));
            $headerSent = true;
        }
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Pagination
$limit = 10;
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

function rx_pagination_links(int $total, int $limit, int $currentPage, string $targetPage): string
{
    $totalPages = (int) ceil($total / $limit);
    if ($totalPages <= 1) return '';
    $links = '<div class="pagination" style="margin-top:16px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = ($i === $currentPage) ? 'btn-primary' : 'btn-secondary';
        $links .= '<a href="?page=' . urlencode($targetPage) . '&p=' . $i . '" class="btn btn-sm ' . $active . '">' . $i . '</a>';
    }
    $links .= '</div>';
    return $links;
}

$registryUser = current_user($pdo) ?: [];
$adminDisplayName = trim((string) (($registryUser['name'] ?? '') ?: ($registryUser['email'] ?? 'Admin User')));
$adminDisplayRole = ucwords(str_replace('_', ' ', (string) ($registryUser['role'] ?? 'Admin')));
$adminInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $adminDisplayName) ?: 'AD', 0, 2));

function rx_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rx_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Registry scalar failed: ' . $e->getMessage());
        return 0;
    }
}

function rx_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Registry rows failed: ' . $e->getMessage());
        return [];
    }
}

function rx_post(string $key, string $default = ''): string
{
    $val = $_POST[$key] ?? $default;
    if (is_string($val)) {
        return trim(strip_tags($val));
    }
    return (string) $default;
}

function rx_ref(string $prefix): string
{
    return $prefix . '-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function rx_user_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $letters .= strtoupper(substr($word, 0, 1));
        }
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'NA';
}

function rx_status_class(string $status): string
{
    $status = strtolower($status);
    return match ($status) {
        'active', 'approved', 'confirmed', 'verified', 'valid', 'issued', 'completed' => 'status-verified',
        'under_review', 'under review', 'processing', 'in_progress' => 'status-under-review',
        'rejected', 'revoked', 'expired', 'failed', 'inactive' => 'status-rejected',
        default => 'status-pending-review',
    };
}

function rx_redirect(string $page, string $message = '', string $error = ''): void
{
    $query = ['page' => $page];
    if ($message !== '') {
        $query['message'] = $message;
    }
    if ($error !== '') {
        $query['error'] = $error;
    }
    header('Location: registry.php?' . http_build_query($query));
    exit;
}

// POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = rx_post('action');
    try {
        if ($action === 'create_grower' || $action === 'create_application') {
            admin_require_feature($pdo, 'applications');
            $name = rx_post('name');
            $email = strtolower(rx_post('email'));
            $phone = rx_post('phone');
            $type = rx_post('type', 'Individual');
            $farmSize = max(0.0, (float) rx_post('farm_size', '0'));
            $stateName = rx_post('state');
            $stateId = rx_scalar($pdo, "SELECT id FROM nigeria_states WHERE state_name = ?", [$stateName]) ?: null;
            
            if ($name === '' || $email === '' || $phone === '') {
                throw new RuntimeException('Name, email, and phone are required.');
            }
            
            $appRef = rx_ref('APP');
            $stmt = $pdo->prepare("
                INSERT INTO applications (app_ref, name, location, farm_size, phone, whatsapp, email, commitments, confirmed, email_sent, submission_source, state_id, lga_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'admin_registry', ?, NULL)
            ");
            $confirmed = $action === 'create_grower' ? 1 : 0;
            $stmt->execute([$appRef, $name, $stateName ?: 'Registry workspace', $farmSize, $phone, $phone, $email, $type, $confirmed, $stateId]);
            $applicationId = (int) $pdo->lastInsertId();
            
            $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password, application_id, name, role, phone, location)
                VALUES (?, ?, ?, ?, 'grower', ?, ?)
                ON DUPLICATE KEY UPDATE application_id = VALUES(application_id), name = VALUES(name), phone = VALUES(phone), location = VALUES(location)
            ");
            $stmt->execute([$email, $password, $applicationId, $name, $phone, $stateName]);
            
            rx_redirect($action === 'create_grower' ? 'growers' : 'applications', $action === 'create_grower' ? 'Grower registered.' : 'Application submitted.');
        }
        
        if ($action === 'review_application') {
            admin_require_feature($pdo, 'applications');
            $applicationId = (int) rx_post('application_id');
            $status = rx_post('status', 'under_review');
            if (!in_array($status, ['under_review', 'approved', 'rejected'], true)) {
                throw new RuntimeException('Invalid review status.');
            }
            $confirmed = $status === 'approved' ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE applications SET confirmed = ?, confirmed_at = IF(? = 1, NOW(), confirmed_at) WHERE id = ?');
            $stmt->execute([$confirmed, $confirmed, $applicationId]);
            rx_redirect('applications', 'Application updated.');
        }
        
        if ($action === 'verify_document') {
            admin_require_feature($pdo, 'documents');
            $documentId = (int) rx_post('document_id');
            $status = rx_post('status', 'verified');
            if (!in_array($status, ['verified', 'rejected', 'pending'], true)) {
                throw new RuntimeException('Invalid document status.');
            }
            $stmt = $pdo->prepare('UPDATE document_requirements SET verification_status = ?, verified = ?, verified_by = ?, verified_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $status === 'verified' ? 1 : 0, (int) ($registryUser['id'] ?? 0), $documentId]);
            rx_redirect('documents', 'Document review saved.');
        }
        
        if ($action === 'issue_certificate') {
            admin_require_feature($pdo, 'certificates');
            $userId = (int) rx_post('user_id');
            $applicationId = rx_scalar($pdo, 'SELECT application_id FROM users WHERE id = ?', [$userId]);
            if ($userId <= 0 || $applicationId <= 0) {
                throw new RuntimeException('Select a registered grower with an application.');
            }
            $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?")->execute([$applicationId]);
            generateCertificate($applicationId, $userId, $pdo);
            rx_redirect('certificates', 'Certificate issued.');
        }
        
        if ($action === 'revoke_certificate') {
            admin_require_feature($pdo, 'certificates');
            $certificateId = (int) rx_post('certificate_id');
            $stmt = $pdo->prepare("UPDATE certificates SET status = 'revoked', revoked_at = NOW(), revoked_reason = ? WHERE id = ?");
            $stmt->execute([rx_post('reason', 'Revoked by registry admin'), $certificateId]);
            rx_redirect('certificates', 'Certificate revoked.');
        }
        
        if ($action === 'create_agent' || $action === 'create_user') {
            $feature = $action === 'create_agent' ? 'field_network' : 'user_management';
            admin_require_feature($pdo, $feature);
            $name = rx_post('name');
            $email = strtolower(rx_post('email', strtolower(preg_replace('/\W+/', '.', $name)) . '@natcodev.local'));
            $phone = rx_post('phone');
            $role = $action === 'create_agent' ? 'field_agent' : strtolower(str_replace(' ', '_', rx_post('role', 'viewer')));
            if ($name === '') {
                throw new RuntimeException('Name is required.');
            }
            $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password, name, role, phone, location)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), phone = VALUES(phone), location = VALUES(location), role = VALUES(role)
            ");
            $stmt->execute([$email, $password, $name, $role, $phone, rx_post('state')]);
            rx_redirect($action === 'create_agent' ? 'field-agents' : 'user-management', 'User record saved.');
        }

        if ($action === 'save_settings') {
            admin_require_feature($pdo, 'settings');
            $settings = [
                'org_name' => rx_post('org_name'),
                'support_email' => rx_post('support_email'),
                'org_description' => rx_post('org_description'),
            ];
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            rx_redirect('settings', 'Settings saved.');
        }
    } catch (Throwable $e) {
        rx_redirect(rx_post('page', 'overview'), '', $e->getMessage());
    }
}

$registryNotice = (string) ($_GET['message'] ?? '');
$registryError = (string) ($_GET['error'] ?? '');
$requestedPage = preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['page'] ?? 'overview')) ?: 'overview';

// Schema-compliant metrics
$totalGrowers = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'grower'");
$verifiedGrowers = rx_scalar($pdo, "SELECT COUNT(DISTINCT u.id) FROM users u JOIN applications a ON a.id = u.application_id WHERE u.role = 'grower' AND a.confirmed = 1");
$pendingApplications = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 0");
$underReviewApplications = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 0");
$approvedToday = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 1 AND DATE(confirmed_at) = CURDATE()");
$rejectedApplications = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 0");
$farmHands = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'field_agent'");
$activeAgents = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'field_agent'");
$statesCovered = rx_scalar($pdo, "SELECT COUNT(DISTINCT COALESCE(ns.state_name, u.location)) FROM users u LEFT JOIN applications a ON a.id = u.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id WHERE u.role = 'grower'");
$statesTotal = max(36, rx_scalar($pdo, 'SELECT COUNT(*) FROM nigeria_states'));
$lgaTotal = rx_scalar($pdo, 'SELECT COUNT(*) FROM nigeria_lgas');
$certTotal = rx_scalar($pdo, 'SELECT COUNT(*) FROM certificates');
$certValid = rx_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE status IN ('issued','valid','active')");
$certExpired = rx_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE status = 'expired'");
$certRevoked = rx_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE status = 'revoked'");
$certExpiring = rx_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)");
$documentTotal = rx_scalar($pdo, 'SELECT COUNT(*) FROM document_requirements');
$documentPending = rx_scalar($pdo, "SELECT COUNT(*) FROM document_requirements WHERE verification_status = 'pending'");
$documentVerified = rx_scalar($pdo, "SELECT COUNT(*) FROM document_requirements WHERE verification_status = 'verified'");
$documentRejected = rx_scalar($pdo, "SELECT COUNT(*) FROM document_requirements WHERE verification_status = 'rejected'");
$registrations30 = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$fieldVisits7 = rx_scalar($pdo, "SELECT COUNT(*) FROM field_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$processedToday = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE DATE(COALESCE(confirmed_at, created_at)) = CURDATE()");
$totalStakeholders = rx_scalar($pdo, 'SELECT COUNT(*) FROM users');

$allStates = rx_rows($pdo, "SELECT id, state_name FROM nigeria_states ORDER BY state_name");
$growerTypeCounts = rx_rows($pdo, "SELECT commitments, COUNT(*) as count FROM applications GROUP BY commitments");
$individualGrowers = 0;
$groupGrowers = 0;
$coopGrowers = 0;
foreach ($growerTypeCounts as $gt) {
    if (stripos($gt['commitments'], 'Individual') !== false) $individualGrowers += (int)$gt['count'];
    elseif (stripos($gt['commitments'], 'Group') !== false) $groupGrowers += (int)$gt['count'];
    elseif (stripos($gt['commitments'], 'Cooperative') !== false) $coopGrowers += (int)$gt['count'];
}

$totalGrowersCount = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'grower'");
$growerRows = rx_rows($pdo, "
    SELECT u.id, u.name, u.email, u.phone, u.created_at, u.latitude, u.longitude,
    COALESCE(ns.state_name, u.location, '') state_name,
    COALESCE(nl.lga_name, '') lga_name,
    COALESCE(a.farm_size, 0) farm_size,
    IF(a.confirmed = 1, 'approved', 'pending_review') review_status,
    COALESCE(a.commitments, 'Individual') grower_type
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = a.lga_id
    WHERE u.role = 'grower'
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
");

$totalApplicationsCount = rx_scalar($pdo, "SELECT COUNT(*) FROM applications");
$applicationRows = rx_rows($pdo, "
    SELECT a.id, a.app_ref, a.name, a.email, a.phone, a.commitments, a.farm_size, a.created_at, a.confirmed,
    COALESCE(ns.state_name, '') state_name, COALESCE(nl.lga_name, '') lga_name
    FROM applications a
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = a.lga_id
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $offset
");

$docType = rx_post('doc_type', (string)($_GET['doc_type'] ?? ''));
$docFilterSql = $docType !== '' ? " WHERE dr.document_type = " . $pdo->quote($docType) : "";
$totalDocumentsCount = rx_scalar($pdo, "SELECT COUNT(*) FROM document_requirements dr $docFilterSql");
$documentRows = rx_rows($pdo, "
    SELECT dr.id, dr.document_type, dr.document_number, dr.file_path, dr.verification_status, dr.uploaded_at, dr.verified_at,
    u.id user_id, u.name uploaded_by, u.email, COALESCE(u.application_id, 0) application_id
    FROM document_requirements dr
    JOIN users u ON u.id = dr.user_id
    $docFilterSql
    ORDER BY dr.uploaded_at DESC
    LIMIT $limit OFFSET $offset
");

$totalCertificatesCount = rx_scalar($pdo, 'SELECT COUNT(*) FROM certificates');
$certificateRows = rx_rows($pdo, "
    SELECT c.id, c.certificate_ref, c.status, c.issued_at, c.expires_at, c.certificate_path, c.certificate_pdf_path,
    u.name grower_name, COALESCE(ns.state_name, u.location, '') state_name
    FROM certificates c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN applications a ON a.id = c.application_id
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    ORDER BY c.issued_at DESC
    LIMIT $limit OFFSET $offset
");

$totalFarmhandsCount = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role IN ('field_agent','agronomist')");
$farmhandRows = rx_rows($pdo, "
    SELECT id, name, email, phone, role, location, created_at
    FROM users
    WHERE role IN ('field_agent','agronomist')
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");

$agentRows = rx_rows($pdo, "
    SELECT u.id, u.name, u.email, u.phone, COALESCE(u.location, 'Unassigned') state_name,
    u.latitude, u.longitude,
    COUNT(fv.id) assignments,
    SUM(CASE WHEN fv.visited_at IS NOT NULL THEN 1 ELSE 0 END) completed
    FROM users u
    LEFT JOIN field_visits fv ON fv.agent_id = u.id
    WHERE u.role = 'field_agent'
    GROUP BY u.id, u.name, u.email, u.phone, u.location, u.latitude, u.longitude
    ORDER BY assignments DESC, u.name
    LIMIT 80
");

$stateRows = rx_rows($pdo, "
    SELECT COALESCE(ns.state_name, u.location, 'Unassigned') state_name,
    (SELECT COUNT(*) FROM nigeria_lgas nl2 JOIN nigeria_states ns2 ON ns2.id = nl2.state_id WHERE ns2.state_name = COALESCE(ns.state_name, u.location)) lgas,
    COUNT(DISTINCT u.id) growers,
    SUM(CASE WHEN a.confirmed = 1 THEN 1 ELSE 0 END) verified,
    (SELECT COUNT(*) FROM users u2 WHERE u2.role = 'field_agent' AND u2.location = COALESCE(ns.state_name, u.location)) agents
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    WHERE u.role = 'grower'
    GROUP BY COALESCE(ns.state_name, u.location, 'Unassigned')
    ORDER BY growers DESC
    LIMIT 36
");

$auditRows = rx_rows($pdo, "
    SELECT created_at, action subject, description, 'audit' channel, 'completed' status
    FROM audit_log
    ORDER BY created_at DESC
    LIMIT 40
");

$userRows = rx_rows($pdo, "
    SELECT id, name, email, role, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 80
");

$totalStakeholdersCount = rx_scalar($pdo, "SELECT COUNT(*) FROM users");
$stakeholderRows = rx_rows($pdo, "
    SELECT u.id, u.name, u.email, u.phone, u.role, u.location, u.created_at,
    0 role_count, 0 open_requests, 'active' account_status
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
");

$roleRequestRows = [];
$roleAssignmentRows = [];
$providerRows = [];
$sellerRows = [];
$offeringRows = [];
$listingRows = [];
$inquiryRows = [];
$orderRows = [];

$visitRows = rx_rows($pdo, "
    SELECT fv.id, fv.notes, fv.visited_at,
    u_agent.name agent_name, u_grower.name grower_name
    FROM field_visits fv
    LEFT JOIN users u_agent ON u_agent.id = fv.agent_id
    LEFT JOIN users u_grower ON u_grower.id = fv.grower_id
    ORDER BY fv.visited_at DESC
    LIMIT 100
");

$selectGrowers = rx_rows($pdo, "SELECT id, name, email, application_id FROM users WHERE role = 'grower' ORDER BY name LIMIT 200");
$selectUsers = rx_rows($pdo, "SELECT id, name, email, role FROM users ORDER BY name LIMIT 300");

$registryPayload = [
    'page' => $requestedPage,
    'notice' => $registryNotice,
    'error' => $registryError,
    'admin' => [
        'name' => (string) ($registryUser['name'] ?? 'Admin'),
        'role' => ucwords(str_replace('_', ' ', (string) ($registryUser['role'] ?? 'Admin'))),
        'initials' => rx_user_initials((string) ($registryUser['name'] ?? 'Admin')),
    ],
    'isSuperAdmin' => true,
    'metrics' => compact('totalGrowers', 'verifiedGrowers', 'pendingApplications', 'underReviewApplications', 'approvedToday', 'rejectedApplications', 'farmHands', 'activeAgents', 'statesCovered', 'statesTotal', 'lgaTotal', 'certTotal', 'certValid', 'certExpired', 'certRevoked', 'certExpiring', 'documentTotal', 'documentPending', 'documentVerified', 'documentRejected', 'registrations30', 'fieldVisits7', 'processedToday', 'totalStakeholders'),
    'growers' => $growerRows,
    'applications' => $applicationRows,
    'documents' => $documentRows,
    'certificates' => $certificateRows,
    'farmhands' => $farmhandRows,
    'agents' => $agentRows,
    'states' => $stateRows,
    'audits' => $auditRows,
    'users' => $userRows,
    'stakeholders' => $stakeholderRows,
    'roleRequests' => $roleRequestRows,
    'roleAssignments' => $roleAssignmentRows,
    'providers' => $providerRows,
    'sellers' => $sellerRows,
    'offerings' => $offeringRows,
    'listings' => $listingRows,
    'activities' => $auditRows,
    'inquiries' => $inquiryRows,
    'orders' => $orderRows,
    'visits' => $visitRows,
    'selectGrowers' => $selectGrowers,
    'selectUsers' => $selectUsers,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Registry - Admin Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root {
  --green-900:#0f2e1f; --green-800:#1a4731; --green-700:#235c3f; --green-600:#2d7a52;
  --green-500:#3a9d6a; --green-400:#4fc48a; --green-100:#e8f5ee; --green-50:#f0faf4;
  --bg:#f5f7f5; --card:#fff; --text:#1a1a1a; --text-secondary:#6b7280;
  --border:#e5e7eb; --danger:#dc2626; --warning:#f59e0b; --info:#3b82f6;
  --success:#10b981; --purple:#8b5cf6; --orange:#f97316;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:13px;}
.sidebar { width:260px; background:var(--green-900); color:#fff; position:fixed; top:0; left:0; bottom:0; overflow-y:auto; z-index:100; display:flex; flex-direction:column; }
.sidebar-header { padding:20px; display:flex; align-items:center; gap:12px; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar-logo { width:40px; height:40px; background:var(--green-400); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; color:var(--green-900); flex-shrink:0; }
.sidebar-brand { font-size:15px; font-weight:700; }
.sidebar-brand small { display:block; font-size:10px; font-weight:400; opacity:0.7; margin-top:2px; }
.workspace-badge { margin:16px 20px 4px; padding:5px 10px; background:rgba(255,255,255,0.08); border-radius:6px; font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.6; }
.nav-section { padding:12px 0; }
.nav-section-title { padding:0 20px; font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.5; margin-bottom:8px; }
.nav-item { display:flex; align-items:center; gap:12px; padding:10px 20px; cursor:pointer; transition:all 0.2s; font-size:14px; color:rgba(255,255,255,0.75); border-left:3px solid transparent; text-decoration:none; }
.nav-item:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-item.active { background:rgba(255,255,255,0.12); color:#fff; border-left-color:var(--green-400); }
.nav-item svg { width:18px; height:18px; flex-shrink:0; }
.nav-item .badge { margin-left:auto; background:var(--orange); color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; }
.sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; gap:10px; margin-top:auto; }
.sidebar-avatar { width:36px; height:36px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; font-weight:600; font-size:13px; color:#fff; }
.sidebar-user { font-size:13px; font-weight:600; }
.sidebar-user small { display:block; font-size:11px; opacity:0.6; font-weight:400; }
.status-dot { width:8px; height:8px; background:var(--success); border-radius:50%; display:inline-block; margin-right:4px; }
.registry-summary { margin:12px 20px; padding:16px; background:rgba(255,255,255,0.05); border-radius:10px; border:1px solid rgba(255,255,255,0.08); }
.registry-summary-title { font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.6; margin-bottom:12px; }
.summary-row { display:flex; justify-content:space-between; padding:4px 0; font-size:12px; }
.summary-row .label { opacity:0.7; }
.summary-row .value { font-weight:600; }

.main { margin-left:260px; flex:1; min-height:100vh; }
.topbar { background:#fff; padding:14px 28px; display:flex; align-items:center; gap:16px; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:50; }
.topbar-search { flex:1; max-width:480px; position:relative; }
.topbar-search input { width:100%; padding:9px 14px 9px 38px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:var(--bg); }
.topbar-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--text-secondary); }
.topbar-actions { display:flex; align-items:center; gap:12px; margin-left:auto; }
.topbar-icon { width:36px; height:36px; border-radius:8px; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; background:#fff; }
.topbar-icon .dot { position:absolute; top:6px; right:6px; width:8px; height:8px; background:var(--danger); border-radius:50%; border:2px solid #fff; }
.topbar-profile { display:flex; align-items:center; gap:10px; min-width:0; max-width:260px; cursor:pointer; padding:4px 10px 4px 6px; border-radius:8px; background:none; border:none; color:inherit; font:inherit; }
.topbar-profile:hover { background:var(--bg); }
.topbar-avatar { width:34px; height:34px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:13px; }
.topbar-profile-info { display:flex; min-width:0; max-width:160px; flex-direction:column; align-items:flex-start; font-size:13px; font-weight:700; line-height:1.15; text-align:left; }
.topbar-profile-info,.topbar-profile-info small { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.topbar-profile-info small { display:block; max-width:100%; margin-top:2px; font-size:11px; color:var(--text-secondary); font-weight:500; }
.topbar-avatar { flex:0 0 34px; }
.topbar-profile svg { flex:0 0 auto; }
.topbar-menu { display:none; position:absolute; right:0; top:48px; width:220px; background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.1); padding:8px; z-index:90; }
.topbar-menu.active { display:block; }
.topbar-menu a { display:block; padding:8px 12px; border-radius:6px; color:var(--text); text-decoration:none; font-size:13px; }
.topbar-menu a:hover { background:var(--bg); }

.content { padding:28px; }
.page { display:none; }
.page.active { display:block; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-title { font-size:22px; font-weight:700; }
.page-subtitle { font-size:13px; color:var(--text-secondary); margin-top:2px; }
.btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
.btn-primary { background:var(--green-700); color:#fff; }
.btn-primary:hover { background:var(--green-800); }
.btn-secondary { background:#fff; color:var(--text); border:1px solid var(--border); }
.btn-secondary:hover { background:var(--bg); }
.btn-danger { background:var(--danger); color:#fff; }
.btn-sm { padding:6px 12px; font-size:12px; }
.btn-icon { padding:6px; background:none; border:1px solid var(--border); border-radius:6px; cursor:pointer; }

.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; padding:20px; border-radius:12px; border:1px solid var(--border); }
.stat-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.stat-card-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
.stat-card-label { font-size:12px; color:var(--text-secondary); }
.stat-card-value { font-size:26px; font-weight:700; margin-top:4px; }
.stat-card-change { font-size:11px; margin-top:6px; }
.stat-card-change.up { color:var(--success); }
.stat-card-change.down { color:var(--danger); }

.card { background:#fff; border-radius:12px; border:1px solid var(--border); margin-bottom:20px; }
.card-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.card-title { font-size:15px; font-weight:700; }
.card-body { padding:22px; }
.card-body.p0 { padding:0; }

table { width:100%; border-collapse:collapse; }
th, td { padding:12px 22px; text-align:left; font-size:13px; }
th { background:var(--bg); font-weight:600; color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--border); }
td { border-bottom:1px solid var(--border); }
tr:last-child td { border-bottom:none; }
tr:hover td { background:var(--green-50); }

.status-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
.status-verified,.status-approved,.status-active { background:#dcfce7; color:#166534; }
.status-pending-review,.status-under-review { background:#fef3c7; color:#92400e; }
.status-rejected,.status-revoked { background:#fee2e2; color:#991b1b; }

.progress-bar { height:6px; background:var(--border); border-radius:3px; overflow:hidden; width:100%; }
.progress-fill { height:100%; background:var(--green-500); border-radius:3px; transition:width 0.3s; }

.form-group { margin-bottom:16px; }
.form-label { display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--text-secondary); }
.form-input,.form-select,.form-textarea { width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; font-family:inherit; }
.form-input:focus,.form-select:focus,.form-textarea:focus { outline:none; border-color:var(--green-500); box-shadow:0 0 0 3px rgba(58,157,106,0.1); }

.tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:20px; }
.tab { padding:10px 16px; font-size:13px; font-weight:500; cursor:pointer; border-bottom:2px solid transparent; color:var(--text-secondary); }
.tab.active { color:var(--green-700); border-bottom-color:var(--green-700); font-weight:600; }

.modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal { background:#fff; border-radius:12px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; }

.avatar-sm { width:32px; height:32px; border-radius:50%; background:var(--green-100); color:var(--green-700); display:inline-flex; align-items:center; justify-content:center; font-weight:600; font-size:12px; }
.avatar-row { display:flex; align-items:center; gap:10px; }

.toast { position:fixed; bottom:24px; right:24px; background:var(--green-800); color:#fff; padding:12px 20px; border-radius:8px; font-size:13px; z-index:300; display:none; animation:slideIn 0.3s; }
@keyframes slideIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }

.kpi-mini{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg);border-radius:8px;margin-bottom:8px}
.kpi-mini .icon{font-size:18px}
.kpi-mini .label{font-size:11px;color:var(--text-secondary)}
.kpi-mini .value{font-size:16px;font-weight:700}

@media(max-width:900px){
  .sidebar{width:70px}
  .sidebar-brand,.workspace-badge,.nav-section-title,.nav-item span:not(.badge),.sidebar-user,.sidebar-user small,.registry-summary{display:none}
  .nav-item{justify-content:center;padding:12px}
  .main{margin-left:70px}
}
</style>
</head>
<body>
<aside class="sidebar">
<div class="sidebar-header">
<div class="sidebar-logo">🌴</div>
<div class="sidebar-brand">NATCODEV<small>National Coconut Development & Propagation Initiative</small></div>
</div>
<div class="workspace-badge">REGISTRY WORKSPACE</div>

<div class="nav-section">
<div class="nav-section-title">Operations</div>
<a class="nav-item <?= $requestedPage === 'overview' ? 'active' : '' ?>" href="registry.php?page=overview" data-page="overview">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
<span>Overview</span>
</a>
<a class="nav-item <?= $requestedPage === 'growers' ? 'active' : '' ?>" href="registry.php?page=growers" data-page="growers">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
<span>Growers</span>
</a>
<a class="nav-item <?= $requestedPage === 'applications' ? 'active' : '' ?>" href="registry.php?page=applications" data-page="applications">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
<span>Applications</span>
<span class="badge"><?= $pendingApplications ?></span>
</a>
</div>

<div class="nav-section">
<div class="nav-section-title">Verification & Field</div>
<a class="nav-item <?= $requestedPage === 'verification' ? 'active' : '' ?>" href="registry.php?page=verification" data-page="verification">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
<span>Verification</span>
</a>
<a class="nav-item <?= $requestedPage === 'documents' ? 'active' : '' ?>" href="registry.php?page=documents" data-page="documents">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
<span>Documents</span>
</a>
<a class="nav-item <?= $requestedPage === 'field-agents' ? 'active' : '' ?>" href="registry.php?page=field-agents" data-page="field-agents">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
<span>Field Agents</span>
</a>
</div>

<div class="nav-section">
<div class="nav-section-title">Registry</div>
<a class="nav-item <?= $requestedPage === 'certificates' ? 'active' : '' ?>" href="registry.php?page=certificates" data-page="certificates">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
<span>Certificates</span>
</a>
<a class="nav-item <?= $requestedPage === 'state-lga' ? 'active' : '' ?>" href="registry.php?page=state-lga" data-page="state-lga">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
<span>Geography</span>
</a>
<a class="nav-item <?= $requestedPage === 'export-data' ? 'active' : '' ?>" href="registry.php?page=export-data" data-page="export-data">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
<span>Export</span>
</a>
</div>

<div class="nav-section">
<div class="nav-section-title">System</div>
<?php if (admin_feature_is_allowed($pdo, 'settings')): ?>
<a class="nav-item <?= $requestedPage === 'settings' ? 'active' : '' ?>" href="registry.php?page=settings" data-page="settings">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
<span>Settings</span>
</a>
<?php endif; ?>
<a class="nav-item <?= $requestedPage === 'user-management' ? 'active' : '' ?>" href="registry.php?page=user-management" data-page="user-management">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
<span>Users</span>
</a>
</div>

<div class="registry-summary">
<div class="registry-summary-title">Registry Health</div>
<div class="summary-row"><span class="label">Verification</span><span class="value"><?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100, 1) : 0 ?>%</span></div>
<div class="summary-row"><span class="label">Monthly Reg</span><span class="value"><?= number_format($registrations30) ?></span></div>
</div>

<div class="sidebar-footer">
<div class="sidebar-avatar"><?= $adminInitials ?></div>
<div class="sidebar-user"><?= rx_e($adminDisplayName) ?><small><span class="status-dot"></span><?= rx_e($adminDisplayRole) ?></small></div>
</div>
</aside>
<div class="main">
<div class="topbar">
<div class="topbar-search">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
<input type="text" placeholder="Search growers, documents..." id="globalSearch">
</div>
<div class="topbar-actions">
<div class="topbar-icon" title="Notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><span class="dot"></span></div>
<button class="topbar-profile" type="button" data-topbar-menu="profileMenu">
<div class="topbar-avatar"><?= $adminInitials ?></div>
<div class="topbar-profile-info"><?= rx_e($adminDisplayName) ?><small><?= rx_e($adminDisplayRole) ?></small></div>
</button>
<div class="topbar-menu" id="profileMenu">
<a href="index.php">Workspace Hub</a>
<a href="index.php?logout=1">Logout</a>
</div>
</div>
</div>
<div class="content">
<!-- OVERVIEW -->
<div class="page active" id="page-overview">
<div class="page-header">
<div><div class="page-title">NATCODEV Registry</div><div class="page-subtitle">Manage coconut growers, applications, verification, documents, certificates, and field operations.</div></div>
<div class="filter-bar" style="margin:0">
<select class="form-select" style="width:auto"><option value="">All States</option><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select>
<select class="form-select" style="width:auto"><option>All LGAs</option></select>
<button class="btn btn-secondary btn-sm"> <?= date('M j') ?> - <?= date('M j, Y', strtotime('+6 days')) ?></button>
</div>
</div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Registered Growers</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)"></div></div><div class="stat-card-value"><?= number_format($totalGrowers) ?></div><div class="stat-card-sub">Registry total</div></div>
<div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Verified Farmers</div><div class="stat-card-icon" style="background:#dcfce7;color:#166534">✓</div></div><div class="stat-card-value"><?= number_format($verifiedGrowers) ?></div><div class="stat-card-sub"><?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100, 1) : 0 ?>% of total registered</div></div>
<div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Pending Applications</div><div class="stat-card-icon" style="background:#fef3c7;color:#92400e">🕐</div></div><div class="stat-card-value"><?= number_format($pendingApplications) ?></div><div class="stat-card-sub">Needs admin review</div></div>
<div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Farm Hands</div><div class="stat-card-icon" style="background:#dbeafe;color:#1e40af"></div></div><div class="stat-card-value"><?= number_format($farmHands) ?></div><div class="stat-card-sub">Registry workforce</div></div>
<div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">States Covered</div><div class="stat-card-icon" style="background:#ede9fe;color:#5b21b6">📍</div></div><div class="stat-card-value"><?= number_format($statesCovered) ?> / <?= number_format($statesTotal) ?></div><div class="stat-card-sub">Registry coverage</div></div>
<div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Certificates Issued</div><div class="stat-card-icon" style="background:#fef3c7;color:#92400e">🏆</div></div><div class="stat-card-value"><?= number_format($certTotal) ?></div><div class="stat-card-sub">Credentialed growers</div></div>
</div>
<div class="grid-3">
<div class="card" style="grid-column:span 2">
<div class="card-header"><div class="card-title">Application Review Queue</div><button class="btn-ghost btn-sm" onclick="navigateTo('applications')">View All →</button></div>
<div class="card-body p0">
<table>
<thead><tr><th>App ID</th><th>Applicant Name</th><th>State</th><th>LGA</th><th>Type</th><th>Submitted</th><th>Status</th><th>Days</th></tr></thead>
<tbody>
<?php foreach (array_slice($applicationRows, 0, 6) as $row): 
    $status = ((int) $row['confirmed'] === 1) ? 'approved' : 'pending_review';
    $days = max(0, (int) floor((time() - strtotime($row['created_at'])) / 86400));
?>
<tr>
<td><strong><?= rx_e($row['app_ref']) ?></strong></td>
<td><?= rx_e($row['name']) ?></td>
<td><?= rx_e($row['state_name'] ?: '-') ?></td>
<td><?= rx_e($row['lga_name'] ?: '-') ?></td>
<td><?= rx_e($row['commitments'] ?: 'Individual') ?></td>
<td><?= date('M j', strtotime($row['created_at'])) ?></td>
<td><span class="status-badge <?= rx_status_class($status) ?>"><?= ucwords(str_replace('_', ' ', $status)) ?></span></td>
<td><?= $days ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$applicationRows): ?><tr><td colspan="8">No applications found.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary btn-sm" onclick="navigateTo('applications')">Review Applications →</button></div>
</div>
<div class="card">
<div class="card-header"><div class="card-title">Verification Status</div><button class="btn-ghost btn-sm" onclick="navigateTo('verification')">View Report</button></div>
<div class="card-body">
<div class="donut-chart" style="background:conic-gradient(var(--g500) 0% <?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100) : 0 ?>%, #fef3c7 <?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100) : 0 ?>% 100%)" id="overviewDonut">
<div class="donut-center" style="width:100px;height:100px;background:#fff;border-radius:50%"><div class="value" style="margin-top:30px"><?= number_format($totalGrowers) ?></div><div class="label">Total</div></div>
</div>
<div style="margin-top:16px">
<div class="legend-item"><div class="legend-dot" style="background:var(--g500)"></div><span style="flex:1">Verified</span><strong><?= number_format($verifiedGrowers) ?> (<?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100, 1) : 0 ?>%)</strong></div>
<div class="legend-item"><div class="legend-dot" style="background:var(--warn)"></div><span style="flex:1">Pending Review</span><strong><?= number_format($pendingApplications) ?></strong></div>
</div>
</div>
</div>
</div>
<div class="grid-2">
<div class="card">
<div class="card-header"><div class="card-title">Recent Document Uploads</div><button class="btn-ghost btn-sm" onclick="navigateTo('documents')">View All</button></div>
<div class="card-body p0">
<table>
<thead><tr><th>Document</th><th>Uploaded By</th><th>Time</th></tr></thead>
<tbody>
<?php foreach (array_slice($documentRows, 0, 6) as $row): ?>
<tr>
<td><div class="avatar-row"><div style="font-size:18px"></div><?= ucwords(str_replace('_', ' ', $row['document_type'])) ?></div></td>
<td><?= rx_e($row['uploaded_by']) ?></td>
<td><?= date('M j, g:i A', strtotime($row['uploaded_at'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$documentRows): ?><tr><td colspan="3">No documents uploaded yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>
<div class="card">
<div class="card-header"><div class="card-title">Certificate Status</div><button class="btn-ghost btn-sm" onclick="navigateTo('certificates')">View All</button></div>
<div class="card-body">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
<div class="kpi-mini"><div class="icon">✅</div><div style="flex:1"><div class="label">Valid</div><div class="value"><?= number_format($certValid) ?></div></div></div>
<div class="kpi-mini"><div class="icon">⏰</div><div style="flex:1"><div class="label">Expiring (30d)</div><div class="value"><?= number_format($certExpiring) ?></div></div></div>
<div class="kpi-mini"><div class="icon">❌</div><div style="flex:1"><div class="label">Expired</div><div class="value"><?= number_format($certExpired) ?></div></div></div>
<div class="kpi-mini"><div class="icon">🚫</div><div style="flex:1"><div class="label">Revoked</div><div class="value"><?= number_format($certRevoked) ?></div></div></div>
</div>
<div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;font-size:13px"><strong>Total Issued</strong><strong><?= number_format($certTotal) ?></strong></div>
</div>
</div>
</div>
<div class="grid-2">
<div class="card">
<div class="card-header"><div class="card-title">Field Agent Assignments</div><button class="btn-ghost btn-sm" onclick="navigateTo('field-agents')">View All</button></div>
<div class="card-body">
<div class="grid-2" style="gap:14px">
<div>
<div class="kpi-mini"><div class="icon">👷</div><div><div class="label">Active Field Agents</div><div class="value"><?= number_format($activeAgents) ?></div></div></div>
<div class="kpi-mini"><div class="icon"></div><div><div class="label">Visits This Week</div><div class="value"><?= number_format($fieldVisits7) ?></div></div></div>
<div class="kpi-mini"><div class="icon">⏳</div><div><div class="label">Pending Documents</div><div class="value"><?= number_format($documentPending) ?></div></div></div>
</div>
<div class="map-container" style="padding:0; overflow:hidden;">
<div id="map-overview" style="width:100%; height:280px;"></div>
</div>
</div>
</div>
</div>
<div style="margin-top:14px"><button class="btn btn-primary btn-sm" onclick="navigateTo('field-agents')">Manage Field Agents →</button></div>
</div>
</div>
<div class="card">
<div class="card-header"><div class="card-title">Batch Import / Export</div></div>
<div class="card-body">
<div class="grid-2" style="gap:14px">
<div style="padding:18px;background:var(--g50);border-radius:10px;border:1px solid var(--border)">
<div style="font-size:11px;font-weight:600;color:var(--g700);text-transform:uppercase;margin-bottom:10px">Import CSV</div>
<div style="font-size:12px;color:var(--text2);margin-bottom:12px">Upload CSV to import applications or growers.</div>
<div class="upload-zone" style="padding:16px" onclick="showToast('CSV upload dialog opened')">📤 Upload CSV</div>
<button class="btn-ghost btn-sm" style="margin-top:8px;width:100%">Download Template</button>
</div>
<div style="padding:18px;background:var(--g50);border-radius:10px;border:1px solid var(--border)">
<div style="font-size:11px;font-weight:600;color:var(--g700);text-transform:uppercase;margin-bottom:10px">Export Data</div>
<div style="font-size:12px;color:var(--text2);margin-bottom:12px">Export registry data, audit logs or reports.</div>
<button class="btn btn-primary btn-sm" style="width:100%" onclick="navigateTo('export-data')">Export Data</button>
<button class="btn-ghost btn-sm" style="margin-top:8px;width:100%">Export Options</button>
</div>
</div>
</div>
</div>
</div>
<div class="card">
<div class="card-header"><div class="card-title">Quick Actions</div></div>
<div class="card-body">
<div class="grid-4">
<div class="quick-action-card" onclick="navigateTo('applications')"><div class="quick-action-icon" style="background:var(--g100);color:var(--g700)">📋</div><div><div class="quick-action-title">Review Applications</div><div class="quick-action-desc"><?= number_format($pendingApplications) ?> pending reviews</div></div><span style="margin-left:auto;font-size:18px">→</span></div>
<div class="quick-action-card" onclick="openModal('growerModal')"><div class="quick-action-icon" style="background:#dbeafe;color:#1e40af">👤</div><div><div class="quick-action-title">Add Grower</div><div class="quick-action-desc">Register new grower</div></div><span style="margin-left:auto;font-size:18px">→</span></div>
<div class="quick-action-card" onclick="navigateTo('field-agents')"><div class="quick-action-icon" style="background:#ede9fe;color:#5b21b6">👷</div><div><div class="quick-action-title">Assign Field Agent</div><div class="quick-action-desc">Allocate to field agent</div></div><span style="margin-left:auto;font-size:18px">→</span></div>
<div class="quick-action-card" onclick="openModal('certificateModal')"><div class="quick-action-icon" style="background:#fce7f3;color:#be185d">🏆</div><div><div class="quick-action-title">Generate Certificate</div><div class="quick-action-desc">Issue registry certificate</div></div><span style="margin-left:auto;font-size:18px">→</span></div>
<div class="quick-action-card" onclick="navigateTo('documents')"><div class="quick-action-icon" style="background:#fef3c7;color:#92400e">📄</div><div><div class="quick-action-title">Verify Documents</div><div class="quick-action-desc"><?= number_format($documentPending) ?> pending</div></div><span style="margin-left:auto;font-size:18px">→</span></div>
<div class="quick-action-card" onclick="navigateTo('export-data')"><div class="quick-action-icon" style="background:#dbeafe;color:#1e40af">⬇️</div><div><div class="quick-action-title">Export Registry</div><div class="quick-action-desc">Download registry data</div></div><span style="margin-left:auto;font-size:18px">→</span></div>
</div>
</div>
</div>
</div>

<!-- GROWERS -->
<div class="page" id="page-growers">
<div class="page-header"><div><div class="page-title">Growers</div><div class="page-subtitle"><?= number_format($totalGrowersCount) ?> registered coconut growers across Nigeria</div></div><button class="btn btn-primary" onclick="openModal('growerModal')">+ Register Grower</button></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Total Growers</div><div class="stat-card-value"><?= number_format($totalGrowersCount) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Verified</div><div class="stat-card-value"><?= number_format($verifiedGrowers) ?></div><div class="stat-card-sub"><?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100, 1) : 0 ?>% verification rate</div></div>
<div class="stat-card"><div class="stat-card-label">Individual</div><div class="stat-card-value"><?= number_format($individualGrowers) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Groups/Coops</div><div class="stat-card-value"><?= number_format($groupGrowers + $coopGrowers) ?></div></div>
</div>
<div class="filter-bar"><input type="text" placeholder="Search by name, ID, state..." oninput="filterTable('growersTable',this.value)"><select><option value="">All States</option><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select><select><option>All Types</option><option>Individual</option><option>Group</option><option>Cooperative</option></select></div>
<div class="card"><div class="card-body p0">
<table id="growersTable">
<thead><tr><th>Grower ID</th><th>Name / Business</th><th>Type</th><th>State</th><th>LGA</th><th>Farm Size</th><th>Registered</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($growerRows as $row): ?>
<tr>
<td><strong>GRW-<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div><div><strong><?= rx_e($row['name']) ?></strong><br><small style="color:var(--text2)"><?= rx_e($row['email']) ?></small></div></div></td>
<td><?= rx_e($row['grower_type'] ?: 'Individual') ?></td>
<td><?= rx_e($row['state_name'] ?: 'Unassigned') ?></td>
<td><?= rx_e($row['lga_name'] ?: 'Unassigned') ?></td>
<td><?= number_format($row['farm_size'], 2) ?> ha</td>
<td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
<td><span class="status-badge <?= rx_status_class($row['review_status']) ?>"><?= ucwords(str_replace('_', ' ', $row['review_status'])) ?></span></td>
<td><form method="post" style="display:inline"><input type="hidden" name="action" value="issue_certificate"><input type="hidden" name="user_id" value="<?= $row['id'] ?>"><input type="hidden" name="page" value="certificates"><button class="btn btn-sm btn-secondary" type="submit">Cert</button></form></td>
</tr>
<?php endforeach; ?>
<?php if (!$growerRows): ?><tr><td colspan="9">No growers found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
<?= rx_pagination_links($totalGrowersCount, $limit, $page, 'growers') ?>
</div>

<!-- APPLICATIONS -->
<div class="page" id="page-applications">
<div class="page-header"><div><div class="page-title">Applications</div><div class="page-subtitle"><?= number_format($totalApplicationsCount) ?> applications in registry</div></div><button class="btn btn-primary" onclick="openModal('applicationModal')">+ New Application</button></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Pending Review</div><div class="stat-card-value" style="color:var(--warn)"><?= number_format($pendingApplications) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Approved Today</div><div class="stat-card-value" style="color:var(--success)"><?= number_format($approvedToday) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Total Applications</div><div class="stat-card-value"><?= number_format($totalApplicationsCount) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Processed Today</div><div class="stat-card-value"><?= number_format($processedToday) ?></div></div>
</div>
<div class="filter-bar"><input type="text" placeholder="Search applications..." oninput="filterTable('applicationsTable',this.value)"><select><option value="">All States</option><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select><select><option>All Types</option><option>Individual</option><option>Group</option><option>Cooperative</option></select></div>
<div class="card"><div class="card-body p0">
<table id="applicationsTable">
<thead><tr><th>App ID</th><th>Applicant</th><th>Type</th><th>State / LGA</th><th>Farm Size</th><th>Submitted</th><th>Status</th><th>Days</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($applicationRows as $row): 
    $status = ((int) $row['confirmed'] === 1) ? 'approved' : 'pending_review';
    $days = max(0, (int) floor((time() - strtotime($row['created_at'])) / 86400));
?>
<tr>
<td><strong><?= rx_e($row['app_ref']) ?></strong></td>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div><?= rx_e($row['name']) ?></div></td>
<td><?= rx_e($row['commitments'] ?: 'Individual') ?></td>
<td><?= rx_e($row['state_name'] ?: 'Unassigned') ?> / <?= rx_e($row['lga_name'] ?: 'Unassigned') ?></td>
<td><?= number_format($row['farm_size'], 2) ?> ha</td>
<td><?= date('M j', strtotime($row['created_at'])) ?></td>
<td><span class="status-badge <?= rx_status_class($status) ?>"><?= ucwords(str_replace('_', ' ', $status)) ?></span></td>
<td><?= $days ?></td>
<td><button class="btn btn-sm btn-primary" onclick="openApplicationReviewModal(<?= $row['id'] ?>, '<?= $status ?>')">Review</button></td>
</tr>
<?php endforeach; ?>
<?php if (!$applicationRows): ?><tr><td colspan="9">No applications found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
<?= rx_pagination_links($totalApplicationsCount, $limit, $page, 'applications') ?>
</div>

<!-- APPLICATION REVIEW MODAL -->
<div class="modal-overlay" id="applicationReviewModal"><div class="modal"><div class="modal-header"><div class="modal-title">Review Application</div><button class="btn-icon" onclick="closeModal('applicationReviewModal')">✕</button></div><form method="post"><input type="hidden" name="action" value="review_application"><input type="hidden" name="page" value="applications"><input type="hidden" name="application_id" id="review_app_id"><div class="modal-body"><div class="form-group"><label class="form-label">Review Status</label><select class="form-select" name="status" id="review_app_status"><option value="under_review">Under Review</option><option value="approved">Approve</option><option value="rejected">Reject</option></select></div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('applicationReviewModal')">Cancel</button><button class="btn btn-primary" type="submit">Save Decision</button></div></form></div></div>

<!-- STAKEHOLDERS -->
<div class="page" id="page-stakeholders">
<div class="page-header"><div><div class="page-title">Stakeholder Directory</div><div class="page-subtitle">All users who entered the platform</div></div></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Total Stakeholders</div><div class="stat-card-value"><?= number_format($totalStakeholdersCount) ?></div><div class="stat-card-sub">All registered accounts</div></div>
<div class="stat-card"><div class="stat-card-label">Growers</div><div class="stat-card-value"><?= number_format($totalGrowers) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Field Agents</div><div class="stat-card-value"><?= number_format($farmHands) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Admins</div><div class="stat-card-value"><?= number_format(rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin'")) ?></div></div>
</div>
<div class="filter-bar"><input type="text" placeholder="Search stakeholders..." oninput="filterTable('stakeholdersTable',this.value)"><select><option>All Roles</option><option>Grower</option><option>Field Agent</option><option>Admin</option></select></div>
<div class="card"><div class="card-header"><div class="card-title">All Platform Stakeholders</div></div><div class="card-body p0">
<table id="stakeholdersTable">
<thead><tr><th>User</th><th>Role</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($stakeholderRows as $row): ?>
<tr>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div><div><strong><?= rx_e($row['name']) ?></strong><br><small style="color:var(--text2)"><?= rx_e($row['email'] ?: $row['phone'] ?: '') ?></small></div></div></td>
<td><?= ucwords(str_replace('_', ' ', $row['role'])) ?></td>
<td><?= rx_e($row['location'] ?: 'Unassigned') ?></td>
<td><span class="status-badge status-active">Active</span></td>
<td><button class="btn-icon" type="button">View</button></td>
</tr>
<?php endforeach; ?>
<?php if (!$stakeholderRows): ?><tr><td colspan="5">No stakeholders found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
<?= rx_pagination_links($totalStakeholdersCount, $limit, $page, 'stakeholders') ?>
</div>

<!-- VERIFICATION -->
<div class="page" id="page-verification">
<div class="page-header"><div><div class="page-title">Verification</div><div class="page-subtitle">Manage farmer verification workflows</div></div></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Verified</div><div class="stat-card-value" style="color:var(--success)"><?= number_format($verifiedGrowers) ?></div><div class="stat-card-sub"><?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100, 1) : 0 ?>% of total</div></div>
<div class="stat-card"><div class="stat-card-label">Pending Documents</div><div class="stat-card-value" style="color:var(--warn)"><?= number_format($documentPending) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Verified Documents</div><div class="stat-card-value" style="color:var(--info)"><?= number_format($documentVerified) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Rejected Documents</div><div class="stat-card-value" style="color:var(--danger)"><?= number_format($documentRejected) ?></div></div>
</div>
<div class="grid-2">
<div class="card"><div class="card-header"><div class="card-title">Verification Pipeline</div></div><div class="card-body">
<div style="display:flex;flex-direction:column;gap:14px">
<div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Document Verification</span><span><?= number_format($documentPending) ?> pending</span></div><div class="progress-bar"><div class="progress-fill" style="width:<?= $documentTotal > 0 ? round($documentVerified / $documentTotal * 100) : 0 ?>%"></div></div></div>
<div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Grower Verification</span><span><?= number_format($pendingApplications) ?> pending</span></div><div class="progress-bar"><div class="progress-fill" style="width:<?= $totalGrowers > 0 ? round($verifiedGrowers / $totalGrowers * 100) : 0 ?>%;background:var(--info)"></div></div></div>
</div>
</div></div>
<div class="card"><div class="card-header"><div class="card-title">Verification by State (Top 10)</div></div><div class="card-body p0">
<table>
<thead><tr><th>State</th><th>Verified</th><th>Total</th><th>Rate</th></tr></thead>
<tbody>
<?php foreach (array_slice($stateRows, 0, 10) as $row): 
    $rate = $row['growers'] > 0 ? round($row['verified'] / $row['growers'] * 100) : 0;
?>
<tr><td><strong><?= rx_e($row['state_name']) ?></strong></td><td><?= number_format($row['verified']) ?></td><td><?= number_format($row['growers']) ?></td><td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:<?= $rate ?>%"></div></div></td></tr>
<?php endforeach; ?>
<?php if (!$stateRows): ?><tr><td colspan="4">No state data available.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
</div>
</div>

<!-- DOCUMENTS -->
<div class="page" id="page-documents">
<div class="page-header"><div><div class="page-title">Documents</div><div class="page-subtitle">Manage all uploaded documents across the registry</div></div><button class="btn btn-primary" onclick="openModal('documentModal')">+ Upload Document</button></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Total Documents</div><div class="stat-card-value"><?= number_format($documentTotal) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Pending Review</div><div class="stat-card-value"><?= number_format($documentPending) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Verified</div><div class="stat-card-value"><?= number_format($documentVerified) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Rejected</div><div class="stat-card-value"><?= number_format($documentRejected) ?></div></div>
</div>
<div class="tabs">
<a href="?page=documents" class="tab <?= $docType === '' ? 'active' : '' ?>">All Documents</a>
<a href="?page=documents&doc_type=farm_photo" class="tab <?= $docType === 'farm_photo' ? 'active' : '' ?>">Farm Photos</a>
<a href="?page=documents&doc_type=id_card" class="tab <?= $docType === 'id_card' ? 'active' : '' ?>">ID Documents</a>
<a href="?page=documents&doc_type=land_title" class="tab <?= $docType === 'land_title' ? 'active' : '' ?>">Land Documents</a>
<a href="?page=documents&doc_type=nin" class="tab <?= $docType === 'nin' ? 'active' : '' ?>">NIN Slips</a>
</div>
<div class="filter-bar"><input type="text" placeholder="Search documents..." oninput="filterTable('documentsTable',this.value)"><select><option>All Types</option><option>Farm Photo</option><option>ID Card</option><option>NIN Slip</option><option>Land Document</option></select><select><option>All Status</option><option>Verified</option><option>Pending</option><option>Rejected</option></select></div>
<div class="card"><div class="card-body p0">
<table id="documentsTable">
<thead><tr><th>Document</th><th>Type</th><th>Uploaded By</th><th>Uploaded</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($documentRows as $row): ?>
<tr>
<td><div class="avatar-row"><div style="font-size:22px">DOC</div><?= rx_e($row['file_path'] ?: $row['document_number'] ?: 'Uploaded document') ?></div></td>
<td><?= ucwords(str_replace('_', ' ', $row['document_type'])) ?></td>
<td><?= rx_e($row['uploaded_by']) ?></td>
<td><?= date('M j, Y', strtotime($row['uploaded_at'])) ?></td>
<td><span class="status-badge <?= rx_status_class($row['verification_status']) ?>"><?= ucwords(str_replace('_', ' ', $row['verification_status'])) ?></span></td>
<td>
<form method="post" style="display:inline"><input type="hidden" name="action" value="verify_document"><input type="hidden" name="document_id" value="<?= $row['id'] ?>"><input type="hidden" name="status" value="verified"><input type="hidden" name="page" value="documents"><button class="btn btn-sm btn-primary" type="submit">Verify</button></form>
<button class="btn btn-sm btn-secondary" type="button" onclick="openRejectionModal(<?= $row['id'] ?>)">Reject</button>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$documentRows): ?><tr><td colspan="6">No documents found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
<?= rx_pagination_links($totalDocumentsCount, $limit, $page, 'documents') ?>
</div>

<!-- DOCUMENT REJECTION MODAL -->
<div class="modal-overlay" id="rejectionModal"><div class="modal"><div class="modal-header"><div class="modal-title">Reject Document</div><button class="btn-icon" onclick="closeModal('rejectionModal')">✕</button></div><form method="post"><input type="hidden" name="action" value="verify_document"><input type="hidden" name="page" value="documents"><input type="hidden" name="document_id" id="reject_doc_id"><input type="hidden" name="status" value="rejected"><div class="modal-body"><div class="form-group"><label class="form-label">Reason for Rejection</label><textarea class="form-textarea" name="notes" placeholder="State why this document is being rejected..."></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('rejectionModal')">Cancel</button><button class="btn btn-danger" type="submit">Confirm Rejection</button></div></form></div></div>

<!-- CERTIFICATES -->
<div class="page" id="page-certificates">
<div class="page-header"><div><div class="page-title">Certificates</div><div class="page-subtitle"><?= number_format($certTotal) ?> certificates issued</div></div><button class="btn btn-primary" onclick="openModal('certificateModal')">+ Generate Certificate</button></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Total Issued</div><div class="stat-card-value"><?= number_format($certTotal) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Valid</div><div class="stat-card-value" style="color:var(--success)"><?= number_format($certValid) ?></div><div class="stat-card-sub"><?= $certTotal > 0 ? round($certValid / $certTotal * 100, 1) : 0 ?>%</div></div>
<div class="stat-card"><div class="stat-card-label">Expiring (30d)</div><div class="stat-card-value" style="color:var(--warn)"><?= number_format($certExpiring) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Expired</div><div class="stat-card-value" style="color:var(--danger)"><?= number_format($certExpired) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Revoked</div><div class="stat-card-value" style="color:var(--danger)"><?= number_format($certRevoked) ?></div></div>
</div>
<div class="filter-bar"><input type="text" placeholder="Search by certificate ID or grower..." oninput="filterTable('certificatesTable',this.value)"><select><option value="">All States</option><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select></div>
<div class="card"><div class="card-body p0">
<table id="certificatesTable">
<thead><tr><th>Certificate ID</th><th>Grower</th><th>State</th><th>Issued</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($certificateRows as $row): ?>
<tr>
<td><strong><?= rx_e($row['certificate_ref']) ?></strong></td>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['grower_name'] ?: 'Unknown') ?></div><?= rx_e($row['grower_name'] ?: 'Unknown grower') ?></div></td>
<td><?= rx_e($row['state_name'] ?: 'Unassigned') ?></td>
<td><?= date('M j, Y', strtotime($row['issued_at'])) ?></td>
<td><?= $row['expires_at'] ? date('M j, Y', strtotime($row['expires_at'])) : '-' ?></td>
<td><span class="status-badge <?= rx_status_class($row['status']) ?>"><?= ucwords($row['status']) ?></span></td>
<td>
<?php if ($row['certificate_pdf_path'] || $row['certificate_path']): ?>
<a class="btn btn-sm btn-primary" href="../../<?= rx_e($row['certificate_pdf_path'] ?: $row['certificate_path']) ?>" target="_blank">Download</a>
<?php endif; ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="revoke_certificate"><input type="hidden" name="certificate_id" value="<?= $row['id'] ?>"><input type="hidden" name="reason" value="Revoked by registry admin"><input type="hidden" name="page" value="certificates"><button class="btn btn-sm btn-secondary" type="submit">Revoke</button></form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$certificateRows): ?><tr><td colspan="7">No certificates found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
<?= rx_pagination_links($totalCertificatesCount, $limit, $page, 'certificates') ?>
</div>

<!-- FARM HANDS -->
<div class="page" id="page-farmhands">
<div class="page-header"><div><div class="page-title">Farm Hands</div><div class="page-subtitle"><?= number_format($totalFarmhandsCount) ?> registered farm workers</div></div><button class="btn btn-primary" onclick="openModal('agentModal')">+ Register Farm Hand</button></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Total Farm Hands</div><div class="stat-card-value"><?= number_format($totalFarmhandsCount) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Field Agents</div><div class="stat-card-value"><?= number_format($farmHands) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Agronomists</div><div class="stat-card-value"><?= number_format(rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'agronomist'")) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Active</div><div class="stat-card-value"><?= number_format($activeAgents) ?></div></div>
</div>
<div class="filter-bar"><input type="text" placeholder="Search farm hands..." oninput="filterTable('farmhandsTable',this.value)"><select><option value="">All States</option><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select></div>
<div class="card"><div class="card-body p0">
<table id="farmhandsTable">
<thead><tr><th>ID</th><th>Name</th><th>Role</th><th>State</th><th>Phone</th><th>Registered</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($farmhandRows as $row): ?>
<tr>
<td><strong>FH-<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div><?= rx_e($row['name']) ?></div></td>
<td><?= ucwords(str_replace('_', ' ', $row['role'])) ?></td>
<td><?= rx_e($row['location'] ?: 'Unassigned') ?></td>
<td><?= rx_e($row['phone'] ?: '-') ?></td>
<td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
<td><button class="btn-icon" type="button">View</button></td>
</tr>
<?php endforeach; ?>
<?php if (!$farmhandRows): ?><tr><td colspan="7">No farm hands found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
<?= rx_pagination_links($totalFarmhandsCount, $limit, $page, 'farmhands') ?>
</div>

<!-- STATE & LGA -->
<div class="page" id="page-state-lga">
<div class="page-header"><div><div class="page-title">State & LGA Management</div><div class="page-subtitle"><?= number_format($statesCovered) ?> / <?= number_format($statesTotal) ?> states with coverage</div></div></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">States Covered</div><div class="stat-card-value"><?= number_format($statesCovered) ?> / <?= number_format($statesTotal) ?></div><div class="stat-card-sub" style="color:var(--success)"><?= $statesTotal > 0 ? round($statesCovered / $statesTotal * 100, 1) : 0 ?>% coverage</div></div>
<div class="stat-card"><div class="stat-card-label">Total LGAs</div><div class="stat-card-value"><?= number_format($lgaTotal) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Total Growers</div><div class="stat-card-value"><?= number_format($totalGrowers) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Verified Growers</div><div class="stat-card-value"><?= number_format($verifiedGrowers) ?></div></div>
</div>
<div class="filter-bar"><input type="text" placeholder="Search states or LGAs..." oninput="filterTable('stateTable',this.value)"></div>
<div class="card"><div class="card-body p0">
<table id="stateTable">
<thead><tr><th>State</th><th>Growers</th><th>Verified</th><th>Coverage</th></tr></thead>
<tbody>
<?php foreach ($stateRows as $row): 
    $rate = $row['growers'] > 0 ? round($row['verified'] / $row['growers'] * 100) : 0;
?>
<tr>
<td><strong><?= rx_e($row['state_name']) ?></strong></td>
<td><?= number_format($row['growers']) ?></td>
<td><?= number_format($row['verified']) ?></td>
<td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:<?= $rate ?>%"></div></div></td>
</tr>
<?php endforeach; ?>
<?php if (!$stateRows): ?><tr><td colspan="4">No state data available.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
</div>

<!-- FIELD AGENTS -->
<div class="page" id="page-field-agents">
<div class="page-header"><div><div class="page-title">Field Agents</div><div class="page-subtitle"><?= number_format($activeAgents) ?> active field agents across Nigeria</div></div><button class="btn btn-primary" onclick="openModal('agentModal')">+ Add Field Agent</button></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Active Agents</div><div class="stat-card-value"><?= number_format($activeAgents) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Visits This Week</div><div class="stat-card-value"><?= number_format($fieldVisits7) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Total Assignments</div><div class="stat-card-value"><?= number_format(array_sum(array_map(fn($r) => (int) $r['assignments'], $agentRows))) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Completed Visits</div><div class="stat-card-value"><?= number_format(array_sum(array_map(fn($r) => (int) $r['completed'], $agentRows))) ?></div></div>
</div>
<div class="grid-2">
<div class="card"><div class="card-header"><div class="card-title">Agent Assignments Map</div></div><div class="card-body">
<div id="map-agents" style="height:320px; border-radius:10px; overflow:hidden;"></div>
</div></div>
<div class="card"><div class="card-header"><div class="card-title">Top Agents by Assignments</div></div><div class="card-body p0">
<table>
<thead><tr><th>Agent</th><th>State</th><th>Assignments</th><th>Completed</th></tr></thead>
<tbody>
<?php foreach (array_slice($agentRows, 0, 5) as $row): ?>
<tr>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div><?= rx_e($row['name']) ?></div></td>
<td><?= rx_e($row['state_name']) ?></td>
<td><strong><?= number_format($row['assignments']) ?></strong></td>
<td><?= number_format($row['completed']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$agentRows): ?><tr><td colspan="4">No field agents found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
</div>
<div class="card"><div class="card-header"><div class="card-title">All Field Agents</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search agents..." oninput="filterTable('agentsTable',this.value)"></div></div><div class="card-body p0">
<table id="agentsTable">
<thead><tr><th>Agent ID</th><th>Name</th><th>State</th><th>Assignments</th><th>Completed Visits</th><th>Performance</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($agentRows as $row): 
    $done = (int) $row['completed'];
    $all = max(1, (int) $row['assignments']);
    $rate = min(100, round(($done / $all) * 100));
?>
<tr>
<td><strong>AGT-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div><?= rx_e($row['name']) ?></div></td>
<td><?= rx_e($row['state_name']) ?></td>
<td><?= number_format($row['assignments']) ?></td>
<td><?= number_format($row['completed']) ?></td>
<td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:<?= $rate ?>%"></div></div></td>
<td><button class="btn-icon" type="button">View</button></td>
</tr>
<?php endforeach; ?>
<?php if (!$agentRows): ?><tr><td colspan="7">No field agents found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
</div>

<!-- FIELD ACTIVITIES -->
<div class="page" id="page-field-activities">
<div class="page-header"><div><div class="page-title">Field Activities</div><div class="page-subtitle">Track farm visits, inspections, and agent activity.</div></div></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Visits (7d)</div><div class="stat-card-value"><?= number_format($fieldVisits7) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Total Visits</div><div class="stat-card-value"><?= number_format(rx_scalar($pdo, "SELECT COUNT(*) FROM field_visits")) ?></div></div>
</div>
<div class="card"><div class="card-header"><div class="card-title">Recent Farm Visits</div></div><div class="card-body p0">
<table id="visitsTable">
<thead><tr><th>Visit ID</th><th>Agent</th><th>Grower</th><th>Date</th><th>Notes</th></tr></thead>
<tbody>
<?php foreach ($visitRows as $row): ?>
<tr>
<td><strong>#<?= $row['id'] ?></strong></td>
<td><?= rx_e($row['agent_name'] ?: 'Unknown') ?></td>
<td><?= rx_e($row['grower_name'] ?: 'Unknown') ?></td>
<td><?= $row['visited_at'] ? date('M j, Y g:i A', strtotime($row['visited_at'])) : '-' ?></td>
<td><?= rx_e($row['notes'] ?: '-') ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$visitRows): ?><tr><td colspan="5">No field visits recorded yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
</div>

<!-- EXPORT DATA -->
<div class="page" id="page-export-data">
<div class="page-header"><div><div class="page-title">Export Data</div><div class="page-subtitle">Download registry data in various formats</div></div></div>
<div class="grid-3">
<div class="card" style="cursor:pointer" onclick="window.location.href='?export=growers'"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px"></div><div style="font-weight:700;font-size:15px">Growers Data</div><div style="font-size:12px;color:var(--text2);margin:6px 0"><?= number_format($totalGrowers) ?> records</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
<div class="card" style="cursor:pointer" onclick="window.location.href='?export=applications'"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📋</div><div style="font-weight:700;font-size:15px">Applications</div><div style="font-size:12px;color:var(--text2);margin:6px 0"><?= number_format($totalApplicationsCount) ?> records</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
<div class="card" style="cursor:pointer" onclick="window.location.href='?export=certificates'"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">🏆</div><div style="font-weight:700;font-size:15px">Certificates</div><div style="font-size:12px;color:var(--text2);margin:6px 0"><?= number_format($certTotal) ?> records</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
<div class="card" style="cursor:pointer" onclick="window.location.href='?export=documents'"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📄</div><div style="font-weight:700;font-size:15px">Documents</div><div style="font-size:12px;color:var(--text2);margin:6px 0"><?= number_format($documentTotal) ?> records</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
<div class="card" style="cursor:pointer" onclick="window.location.href='?export=agents'"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">👷</div><div style="font-weight:700;font-size:15px">Field Agents</div><div style="font-size:12px;color:var(--text2);margin:6px 0"><?= number_format($farmHands) ?> records</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
<div class="card" style="cursor:pointer" onclick="window.location.href='?export=approved'"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">✅</div><div style="font-weight:700;font-size:15px">Approved Applications</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Export approved only</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
</div>
</div>

<!-- SETTINGS -->
<div class="page" id="page-settings">
<div class="page-header"><div><div class="page-title">Settings</div><div class="page-subtitle">Configure your registry workspace</div></div></div>
<div class="card"><div class="card-body">
<form method="post">
<input type="hidden" name="action" value="save_settings">
<input type="hidden" name="page" value="settings">
<div class="form-row">
<div class="form-group"><label class="form-label">Organization Name</label><input class="form-input" name="org_name" value="<?= rx_e(admin_setting($pdo, 'org_name', 'NATCODEV')) ?>"></div>
<div class="form-group"><label class="form-label">Support Email</label><input class="form-input" name="support_email" value="<?= rx_e(admin_setting($pdo, 'support_email', 'support@natcodev.com.ng')) ?>"></div>
</div>
<div class="form-group"><label class="form-label">Registry Description</label><textarea class="form-textarea" name="org_description"><?= rx_e(admin_setting($pdo, 'org_description', 'National Coconut Development & Propagation Initiative - Managing coconut growers, verification, and certification across Nigeria.')) ?></textarea></div>
<div style="display:flex;gap:10px"><button class="btn btn-primary" type="submit">Save Changes</button><button class="btn btn-secondary" type="button" onclick="navigateTo('overview')">Cancel</button></div>
</form>
</div></div>
</div>

<!-- USER MANAGEMENT -->
<div class="page" id="page-user-management">
<div class="page-header"><div><div class="page-title">User Management</div><div class="page-subtitle">Manage admin and staff accounts</div></div><button class="btn btn-primary" onclick="openModal('userModal')">+ Add User</button></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-card-label">Total Users</div><div class="stat-card-value"><?= number_format($totalStakeholdersCount) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Growers</div><div class="stat-card-value"><?= number_format($totalGrowers) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Field Agents</div><div class="stat-card-value"><?= number_format($farmHands) ?></div></div>
<div class="stat-card"><div class="stat-card-label">Admins</div><div class="stat-card-value"><?= number_format(rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin'")) ?></div></div>
</div>
<div class="card"><div class="card-body p0">
<table>
<thead><tr><th>User</th><th>Email</th><th>Role</th><th>Registered</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($userRows as $row): ?>
<tr>
<td><div class="avatar-row"><div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div><div><strong><?= rx_e($row['name']) ?></strong><br><small style="color:var(--text2)"><?= ucwords(str_replace('_', ' ', $row['role'])) ?></small></div></div></td>
<td><?= rx_e($row['email']) ?></td>
<td><span class="chip"><?= ucwords(str_replace('_', ' ', $row['role'])) ?></span></td>
<td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
<td><button class="btn-icon">⋮</button></td>
</tr>
<?php endforeach; ?>
<?php if (!$userRows): ?><tr><td colspan="5">No users found.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>
</div>
</div>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="growerModal"><div class="modal"><div class="modal-header"><div class="modal-title">Register New Grower</div><button class="btn-icon" onclick="closeModal('growerModal')">✕</button></div><form method="post"><input type="hidden" name="action" value="create_grower"><input type="hidden" name="page" value="growers"><div class="modal-body">
<div class="form-row"><div class="form-group"><label class="form-label">Full Name / Business Name</label><input class="form-input" name="name" required></div><div class="form-group"><label class="form-label">Type</label><select class="form-select" name="type"><option>Individual</option><option>Group</option><option>Cooperative</option></select></div></div>
<div class="form-row"><div class="form-group"><label class="form-label">Email</label><input class="form-input" name="email" type="email" required></div><div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone" required></div></div>
<div class="form-row"><div class="form-group"><label class="form-label">State</label><select class="form-select" name="state"><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Farm Size (ha)</label><input class="form-input" name="farm_size" type="number" step="0.01"></div></div>
</div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('growerModal')">Cancel</button><button class="btn btn-primary" type="submit">Register Grower</button></div></form></div></div>

<div class="modal-overlay" id="applicationModal"><div class="modal"><div class="modal-header"><div class="modal-title">New Application</div><button class="btn-icon" onclick="closeModal('applicationModal')">✕</button></div><form method="post"><input type="hidden" name="action" value="create_application"><input type="hidden" name="page" value="applications"><div class="modal-body">
<div class="form-group"><label class="form-label">Applicant Name</label><input class="form-input" name="name" required></div>
<div class="form-row"><div class="form-group"><label class="form-label">Email</label><input class="form-input" name="email" type="email" required></div><div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone" required></div></div>
<div class="form-row"><div class="form-group"><label class="form-label">Type</label><select class="form-select" name="type"><option>Individual</option><option>Group</option><option>Cooperative</option></select></div><div class="form-group"><label class="form-label">Farm Size (ha)</label><input class="form-input" name="farm_size" type="number" step="0.01"></div></div>
<div class="form-group"><label class="form-label">State</label><select class="form-select" name="state"><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select></div>
</div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('applicationModal')">Cancel</button><button class="btn btn-primary" type="submit">Submit Application</button></div></form></div></div>

<div class="modal-overlay" id="certificateModal"><div class="modal"><div class="modal-header"><div class="modal-title">Generate Certificate</div><button class="btn-icon" onclick="closeModal('certificateModal')">✕</button></div><form method="post"><input type="hidden" name="action" value="issue_certificate"><input type="hidden" name="page" value="certificates"><div class="modal-body">
<div class="form-group"><label class="form-label">Grower</label><select class="form-select" name="user_id" required>
<?php foreach ($selectGrowers as $g): ?><option value="<?= $g['id'] ?>"><?= rx_e($g['name']) ?> (<?= rx_e($g['email']) ?>)</option><?php endforeach; ?>
</select></div>
</div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('certificateModal')">Cancel</button><button class="btn btn-primary" type="submit">Generate Certificate</button></div></form></div></div>

<div class="modal-overlay" id="documentModal"><div class="modal"><div class="modal-header"><div class="modal-title">Upload Document</div><button class="btn-icon" onclick="closeModal('documentModal')"></button></div><form method="post"><input type="hidden" name="action" value="upload_document"><input type="hidden" name="page" value="documents"><div class="modal-body">
<div class="form-group"><label class="form-label">Document Type</label><select class="form-select" name="document_type"><option value="farm_photo">Farm Photo</option><option value="id_card">ID Card</option><option value="nin">NIN</option><option value="bvn">BVN</option><option value="land_title">Land Document</option></select></div>
<div class="form-group"><label class="form-label">Associated Grower</label><select class="form-select" name="user_id">
<?php foreach ($selectGrowers as $g): ?><option value="<?= $g['id'] ?>"><?= rx_e($g['name']) ?></option><?php endforeach; ?>
</select></div>
<div class="form-group"><label class="form-label">Document Number / Reference</label><input class="form-input" name="document_number"></div>
<div class="form-group"><label class="form-label">File Path or Note</label><input class="form-input" name="file_path" placeholder="admin-upload or uploaded file path"></div>
</div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('documentModal')">Cancel</button><button class="btn btn-primary" type="submit">Upload Document</button></div></form></div></div>

<div class="modal-overlay" id="agentModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Field Agent</div><button class="btn-icon" onclick="closeModal('agentModal')">✕</button></div><form method="post"><input type="hidden" name="action" value="create_agent"><input type="hidden" name="page" value="field-agents"><div class="modal-body">
<div class="form-row"><div class="form-group"><label class="form-label">Full Name</label><input class="form-input" name="name" required></div><div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone"></div></div>
<div class="form-group"><label class="form-label">Email</label><input class="form-input" name="email" type="email"></div>
<div class="form-group"><label class="form-label">Assigned State</label><select class="form-select" name="state"><?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?></select></div>
</div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('agentModal')">Cancel</button><button class="btn btn-primary" type="submit">Add Agent</button></div></form></div></div>

<div class="modal-overlay" id="userModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add User</div><button class="btn-icon" onclick="closeModal('userModal')">✕</button></div><form method="post"><input type="hidden" name="action" value="create_user"><input type="hidden" name="page" value="user-management"><div class="modal-body">
<div class="form-row"><div class="form-group"><label class="form-label">Full Name</label><input class="form-input" name="name" required></div><div class="form-group"><label class="form-label">Email</label><input class="form-input" name="email" type="email"></div></div>
<div class="form-group"><label class="form-label">Role</label><select class="form-select" name="role"><option>Admin</option><option>Field Agent</option><option>Viewer</option></select></div>
<div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone"></div>
</div><div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('userModal')">Cancel</button><button class="btn btn-primary" type="submit">Create User</button></div></form></div></div>

<div class="toast" id="toast"></div>

<script>
const REGISTRY = <?= json_encode($registryPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

function navigateTo(page, updateUrl = true){
    document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
    
    const el=document.getElementById('page-'+page);
    if(el)el.classList.add('active');
    
    const nav=document.querySelector(`.nav-item[data-page="${page}"]`);
    if(nav)nav.classList.add('active');
    
    if (updateUrl && window.history.pushState) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        window.history.pushState({page: page}, '', url.toString());
    }
    
    window.scrollTo(0,0);
    window.dispatchEvent(new Event('resize'));
    if (window.registryMaps) window.registryMaps.forEach(m => m.invalidateSize());
}

document.querySelectorAll('.nav-item[data-page]').forEach(item=>{
    item.addEventListener('click',(e)=>{
        e.preventDefault();
        const p=item.getAttribute('data-page');
        if(p)navigateTo(p);
    });
});

window.onpopstate = function(event) {
    if (event.state && event.state.page) {
        navigateTo(event.state.page, false);
    } else {
        const url = new URL(window.location.href);
        const page = url.searchParams.get('page') || 'overview';
        navigateTo(page, false);
    }
};

function openModal(id){document.getElementById(id).classList.add('active')}
function closeModal(id){document.getElementById(id).classList.remove('active')}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('active')})});

function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.style.display='block';setTimeout(()=>t.style.display='none',2500)}

function filterTable(tableId,q){
    const t=document.getElementById(tableId);if(!t)return;
    const rows=t.querySelectorAll('tbody tr');const s=q.toLowerCase();
    rows.forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(s)?'':'none'});
}

function openApplicationReviewModal(id, status){
    document.getElementById('review_app_id').value = id;
    document.getElementById('review_app_status').value = status;
    openModal('applicationReviewModal');
}

function openRejectionModal(id){
    document.getElementById('reject_doc_id').value = id;
    openModal('rejectionModal');
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

// Initialize Maps
function initRegistryMaps() {
    if (typeof L === 'undefined') return;
    window.registryMaps = [];
    const configs = [
        { id: 'map-overview', points: REGISTRY.growers },
        { id: 'map-agents', points: REGISTRY.agents }
    ];
    configs.forEach(conf => {
        const el = document.getElementById(conf.id);
        if (!el) return;
        const map = L.map(conf.id).setView([9.0820, 8.6753], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        conf.points.forEach(p => {
            if (p.latitude && p.longitude) {
                L.marker([p.latitude, p.longitude]).addTo(map)
                    .bindPopup(`<strong>${p.name}</strong><br>${p.state_name || ''}`);
            }
        });
        window.registryMaps.push(map);
    });
}

// Initialize
if (REGISTRY.notice) showToast(REGISTRY.notice);
if (REGISTRY.error) showToast(REGISTRY.error);
navigateTo(REGISTRY.page || 'overview');
initRegistryMaps();
</script>
</body>
</html>
