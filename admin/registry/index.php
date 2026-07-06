<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';

$pageTitle = 'Platform Registrations - NATCODEV';
$activeNav = 'overview';

function rr_status_badge(string $status): string
{
    return '<span class="status-badge ' . rx_status_class($status) . '">' . rx_e(status_label($status)) . '</span>';
}

function rr_allowed_roles(): array
{
    return [
        'grower' => 'Grower',
        'buyer' => 'Buyer',
        'seller' => 'Marketplace Seller',
        'learner' => 'Academy Learner',
        'provider' => 'Provider',
        'input_provider' => 'Input Provider',
        'service_provider' => 'Service Provider',
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'agric_extensionist' => 'Agric Extensionist',
        'state_coordinator' => 'State Coordinator',
        'national_coordinator' => 'National Coordinator',
        'support_agent' => 'Support Agent',
    ];
}

function rr_source_badge(string $source): string
{
    $colors = [
        'Account' => 'status-active',
        'Internal User' => 'status-verified',
        'Grower Registry' => 'status-approved',
        'Provider Registry' => 'status-under-review',
        'Marketplace Seller' => 'status-pending-review',
        'Buyer Workspace' => 'status-verified',
        'Academy Learner' => 'status-under-review',
    ];
    return '<span class="status-badge ' . ($colors[$source] ?? 'status-pending-review') . '">' . rx_e($source) . '</span>';
}

function rr_role_label(?string $role): string
{
    $role = trim((string) $role);
    if ($role === '') {
        return 'Unassigned';
    }
    return status_label($role);
}

function rr_internal_roles(): array
{
    return [
        'admin',
        'super_admin',
        'support_agent',
        'field_agent',
        'agronomist',
        'agric_extensionist',
        'extensionist',
        'state_coordinator',
        'national_coordinator',
    ];
}

function rr_sql_in(array $values): string
{
    return "'" . implode("','", array_map(static fn(string $value): string => str_replace("'", "''", $value), $values)) . "'";
}

function rr_count_when(PDO $pdo, string $table, string $where, array $params, array $requiredColumns = []): int
{
    if (!app_table_exists($pdo, $table)) {
        return 0;
    }
    foreach ($requiredColumns as $column) {
        if (!app_column_exists($pdo, $table, $column)) {
            return 0;
        }
    }
    return rx_scalar($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
}

function rr_internal_activity(PDO $pdo, int $userId): string
{
    $activity = [];
    $fieldTasks = rr_count_when($pdo, 'field_tasks', 'assigned_to = ?', [$userId], ['assigned_to']);
    $completedTasks = rr_count_when($pdo, 'field_tasks', "assigned_to = ? AND status IN ('completed','closed','resolved')", [$userId], ['assigned_to', 'status']);
    $farmVisits = rr_count_when($pdo, 'farm_visits', 'agent_id = ?', [$userId], ['agent_id']);
    $agronomyCases = rr_count_when($pdo, 'agronomy_cases', 'assigned_to = ? OR created_by = ?', [$userId, $userId], ['assigned_to', 'created_by']);
    $assignedTickets = rr_count_when($pdo, 'support_tickets', 'assigned_admin_id = ?', [$userId], ['assigned_admin_id']);
    $supportReplies = rr_count_when($pdo, 'support_ticket_messages', 'admin_id = ?', [$userId], ['admin_id']);
    $walletTx = rr_count_when($pdo, 'wallet_transactions', 'user_id = ?', [$userId], ['user_id']);
    $withdrawals = rr_count_when($pdo, 'wallet_withdrawals', 'user_id = ?', [$userId], ['user_id']);

    if ($fieldTasks > 0) {
        $activity[] = number_format($fieldTasks) . ' field task(s)' . ($completedTasks > 0 ? ' / ' . number_format($completedTasks) . ' completed' : '');
    }
    if ($farmVisits > 0) {
        $activity[] = number_format($farmVisits) . ' farm visit(s)';
    }
    if ($agronomyCases > 0) {
        $activity[] = number_format($agronomyCases) . ' agronomy case(s)';
    }
    if ($assignedTickets > 0 || $supportReplies > 0) {
        $activity[] = number_format($assignedTickets) . ' ticket(s) assigned / ' . number_format($supportReplies) . ' support replies';
    }
    if ($walletTx > 0 || $withdrawals > 0) {
        $activity[] = number_format($walletTx) . ' wallet transaction(s) / ' . number_format($withdrawals) . ' withdrawal(s)';
    }

    return $activity ? implode(' / ', $activity) : 'No operational activity captured yet';
}
function rr_action_form(string $action, array $hidden, string $label, string $class = 'btn-secondary', string $confirm = ''): string
{
    $html = '<form method="post" style="display:inline-flex;margin:2px">';
    $html .= '<input type="hidden" name="_csrf" value="' . rx_e(csrf_token()) . '">';
    $html .= '<input type="hidden" name="action" value="' . rx_e($action) . '">';
    foreach ($hidden as $key => $value) {
        $html .= '<input type="hidden" name="' . rx_e((string) $key) . '" value="' . rx_e((string) $value) . '">';
    }
    $html .= '<button class="btn btn-sm ' . rx_e($class) . '"' . ($confirm !== '' ? ' onclick="return confirm(' . rx_e(json_encode($confirm)) . ')"' : '') . '>' . rx_e($label) . '</button></form>';
    return $html;
}

function rr_role_form(int $userId): string
{
    $html = '<form method="post" style="display:inline-flex;gap:6px;align-items:center;margin:2px">';
    $html .= '<input type="hidden" name="_csrf" value="' . rx_e(csrf_token()) . '">';
    $html .= '<input type="hidden" name="action" value="grant_role">';
    $html .= '<input type="hidden" name="user_id" value="' . $userId . '">';
    $html .= '<select name="role_key" class="form-select" style="width:150px;padding:6px 8px">';
    foreach (rr_allowed_roles() as $key => $label) {
        $html .= '<option value="' . rx_e($key) . '">' . rx_e($label) . '</option>';
    }
    $html .= '</select><button class="btn btn-sm btn-secondary">Grant</button></form>';
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = 'index.php';
    try {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            throw new RuntimeException('Invalid security token.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_user_status') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $status = (string) ($_POST['account_status'] ?? '');
            if ($userId <= 0 || !in_array($status, ['active', 'needs_confirmation', 'suspended', 'inactive'], true)) {
                throw new RuntimeException('Invalid account status action.');
            }
            if ($status === 'active') {
                $pdo->prepare("UPDATE users SET account_status='active', email_verified_at=COALESCE(email_verified_at, NOW()) WHERE id=?")->execute([$userId]);
            } elseif ($status === 'needs_confirmation') {
                $pdo->prepare("UPDATE users SET account_status='needs_confirmation', email_verified_at=NULL WHERE id=?")->execute([$userId]);
            } else {
                $pdo->prepare("UPDATE users SET account_status=? WHERE id=?")->execute([$status, $userId]);
            }
            $redirect .= '?message=' . urlencode('Account status updated.');
        } elseif ($action === 'resend_confirmation') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId <= 0 || !app_send_user_verification($pdo, $userId, 'NATCODEV Platform Registration')) {
                throw new RuntimeException('Unable to send confirmation email.');
            }
            $redirect .= '?message=' . urlencode('Confirmation email sent.');
        } elseif ($action === 'grant_role') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $role = (string) ($_POST['role_key'] ?? '');
            if ($userId <= 0 || !array_key_exists($role, rr_allowed_roles())) {
                throw new RuntimeException('Invalid RBAC role.');
            }
            $pdo->prepare("INSERT INTO user_role_assignments (user_id, role_key, scope_type, scope_value, status, notes, assigned_by) VALUES (?, ?, 'global', '', 'active', 'Granted from registry control center', ?) ON DUPLICATE KEY UPDATE status='active', revoked_at=NULL, notes=VALUES(notes), assigned_by=VALUES(assigned_by)")->execute([$userId, $role, (int) ($_SESSION['user_id'] ?? 0)]);
            $redirect .= '?message=' . urlencode('RBAC role granted.');
        } elseif ($action === 'revoke_role') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $role = (string) ($_POST['role_key'] ?? '');
            if ($userId <= 0 || !array_key_exists($role, rr_allowed_roles())) {
                throw new RuntimeException('Invalid RBAC role.');
            }
            $pdo->prepare("UPDATE user_role_assignments SET status='revoked', revoked_at=NOW() WHERE user_id=? AND role_key=? AND status='active'")->execute([$userId, $role]);
            $redirect .= '?message=' . urlencode('RBAC role revoked.');
        } else {
            throw new RuntimeException('Unknown registry action.');
        }
    } catch (Throwable $e) {
        $redirect .= '?error=' . urlencode($e->getMessage());
    }
    redirect_to($redirect);
}

$search = trim((string) ($_GET['search'] ?? ''));
$sourceFilter = trim((string) ($_GET['source'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = rx_per_page(25);
$offset = ($page - 1) * $limit;

$totalUsers = rx_scalar($pdo, "SELECT COUNT(*) FROM users");
$activeUsers = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE COALESCE(account_status,'active')='active'");
$pendingUsers = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE COALESCE(account_status,'active') IN ('needs_confirmation','pending','unconfirmed') OR email_verified_at IS NULL");
$totalApplications = rx_scalar($pdo, "SELECT COUNT(*) FROM applications");
$totalProviders = rx_scalar($pdo, "SELECT COUNT(*) FROM provider_registry");
$totalSellers = rx_scalar($pdo, "SELECT COUNT(*) FROM marketplace_sellers");
$totalBuyers = rx_scalar($pdo, "SELECT COUNT(*) FROM buyer_profiles");
$totalLearners = rx_scalar($pdo, "SELECT COUNT(DISTINCT user_id) FROM webinar_registrations");
$openSupport = rx_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status NOT IN ('resolved','closed','rejected')");
$internalRoleList = rr_sql_in(rr_internal_roles());
$internalWhere = "(u.role IN ({$internalRoleList}) OR u.platform_role IN ({$internalRoleList}) OR COALESCE(u.is_super_admin,0)=1 OR EXISTS (SELECT 1 FROM user_role_assignments ura2 WHERE ura2.user_id=u.id AND ura2.status='active' AND ura2.role_key IN ({$internalRoleList})))";
$totalInternalUsers = rx_scalar($pdo, "SELECT COUNT(DISTINCT u.id) FROM users u WHERE {$internalWhere}");
$activeInternalUsers = rx_scalar($pdo, "SELECT COUNT(DISTINCT u.id) FROM users u WHERE {$internalWhere} AND COALESCE(u.account_status,'active')='active'");
$internalFieldActivity = rr_count_when($pdo, 'field_tasks', 'assigned_to IS NOT NULL', [], ['assigned_to']) + rr_count_when($pdo, 'farm_visits', 'agent_id IS NOT NULL', [], ['agent_id']);
$internalSupportActivity = rr_count_when($pdo, 'support_tickets', 'assigned_admin_id IS NOT NULL', [], ['assigned_admin_id']) + rr_count_when($pdo, 'support_ticket_messages', 'admin_id IS NOT NULL', [], ['admin_id']);
$todayStart = date('Y-m-d') . ' 00:00:00';
$newUsersToday = rx_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE created_at >= ?', [$todayStart]);
$newVerifiedToday = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE email_verified_at >= ? AND COALESCE(account_status,'active')='active'", [$todayStart]);
$newProviderProfilesToday = rx_scalar($pdo, 'SELECT COUNT(*) FROM provider_registry WHERE created_at >= ?', [$todayStart]);
$newSellerProfilesToday = rx_scalar($pdo, 'SELECT COUNT(*) FROM marketplace_sellers WHERE created_at >= ?', [$todayStart]);
$pendingProviderProfiles = rx_scalar($pdo, "SELECT COUNT(*) FROM provider_registry WHERE status IN ('pending_review','under_review','needs_confirmation')");
$pendingSellerProfiles = rx_scalar($pdo, "SELECT COUNT(*) FROM marketplace_sellers WHERE approval_status IN ('pending','unverified')");
$pendingGrowerApplications = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE review_status IN ('pending','under_review') OR confirmed = 0");
$verifiedUsers = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL AND COALESCE(account_status,'active')='active'");
$approvedProviderToday = app_table_exists($pdo, 'provider_registry') && app_column_exists($pdo, 'provider_registry', 'updated_at') ? rx_scalar($pdo, "SELECT COUNT(*) FROM provider_registry WHERE status IN ('approved','active') AND updated_at >= ?", [$todayStart]) : 0;
$approvedSellerToday = app_table_exists($pdo, 'marketplace_sellers') && app_column_exists($pdo, 'marketplace_sellers', 'updated_at') ? rx_scalar($pdo, "SELECT COUNT(*) FROM marketplace_sellers WHERE approval_status = 'approved' AND updated_at >= ?", [$todayStart]) : 0;
$approvalVelocity = $approvedProviderToday + $approvedSellerToday;
$oldestPendingProviderHours = app_table_exists($pdo, 'provider_registry') && app_column_exists($pdo, 'provider_registry', 'created_at') ? rx_scalar($pdo, "SELECT COALESCE(TIMESTAMPDIFF(HOUR, MIN(created_at), NOW()), 0) FROM provider_registry WHERE status IN ('pending_review','under_review','needs_confirmation')") : 0;
$oldestPendingSellerHours = app_table_exists($pdo, 'marketplace_sellers') && app_column_exists($pdo, 'marketplace_sellers', 'created_at') ? rx_scalar($pdo, "SELECT COALESCE(TIMESTAMPDIFF(HOUR, MIN(created_at), NOW()), 0) FROM marketplace_sellers WHERE approval_status IN ('pending','unverified')") : 0;
$oldestPendingApplicationHours = app_table_exists($pdo, 'applications') && app_column_exists($pdo, 'applications', 'created_at') ? rx_scalar($pdo, "SELECT COALESCE(TIMESTAMPDIFF(HOUR, MIN(created_at), NOW()), 0) FROM applications WHERE review_status IN ('pending','under_review') OR confirmed = 0") : 0;
$dataReflectionNote = date('j M Y H:i');

$rows = [];
$addRow = static function (array $row) use (&$rows): void {
    $rows[] = $row + [
        'user_id' => 0,
        'ref' => '',
        'name' => '',
        'email' => '',
        'phone' => '',
        'source' => 'Account',
        'role' => '',
        'status' => 'pending',
        'detail' => '',
        'created_at' => '',
        'actions' => '',
    ];
};

foreach (rx_rows($pdo, "SELECT u.*, GROUP_CONCAT(ura.role_key ORDER BY ura.role_key SEPARATOR ', ') assigned_roles FROM users u LEFT JOIN user_role_assignments ura ON ura.user_id=u.id AND ura.status='active' GROUP BY u.id ORDER BY u.created_at DESC, u.id DESC LIMIT 500") as $u) {
    $status = (string) ($u['account_status'] ?: 'active');
    $detail = 'Base: ' . rr_role_label((string) ($u['role'] ?? '')) . ' / Platform: ' . rr_role_label((string) ($u['platform_role'] ?? ''));
    if (!empty($u['assigned_roles'])) {
        $detail .= ' / RBAC: ' . (string) $u['assigned_roles'];
    }
    $actions = '';
    $uid = (int) $u['id'];
    if ($status !== 'active') {
        $actions .= rr_action_form('update_user_status', ['user_id' => $uid, 'account_status' => 'active'], 'Activate', 'btn-primary');
    }
    $actions .= rr_action_form('update_user_status', ['user_id' => $uid, 'account_status' => 'needs_confirmation'], 'Require Confirm');
    $actions .= rr_action_form('resend_confirmation', ['user_id' => $uid], 'Resend Email');
    $actions .= rr_role_form($uid);
    foreach (array_filter(array_map('trim', explode(',', (string) ($u['assigned_roles'] ?? '')))) as $role) {
        if (array_key_exists($role, rr_allowed_roles())) {
            $actions .= rr_action_form('revoke_role', ['user_id' => $uid, 'role_key' => $role], 'Revoke ' . rr_role_label($role), 'btn-danger', 'Revoke this RBAC role?');
        }
    }
    $addRow([
        'user_id' => $uid,
        'ref' => 'USR-' . str_pad((string) $uid, 5, '0', STR_PAD_LEFT),
        'name' => (string) ($u['name'] ?? ''),
        'email' => (string) ($u['email'] ?? ''),
        'phone' => (string) ($u['phone'] ?? ''),
        'source' => 'Account',
        'role' => rr_role_label((string) (($u['platform_role'] ?? '') ?: ($u['role'] ?? ''))),
        'status' => trim((string) ($u['email_verified_at'] ?? '')) === '' && $status === 'active' ? 'unconfirmed' : $status,
        'detail' => $detail,
        'created_at' => (string) ($u['created_at'] ?? ''),
        'actions' => $actions,
    ]);
}

foreach (rx_rows($pdo, "SELECT u.*, sp.staff_type, sp.state staff_state, sp.lga staff_lga, sp.certification_status, sp.status staff_status, GROUP_CONCAT(DISTINCT ura.role_key ORDER BY ura.role_key SEPARATOR ', ') assigned_roles FROM users u LEFT JOIN staff_profiles sp ON sp.user_id=u.id LEFT JOIN user_role_assignments ura ON ura.user_id=u.id AND ura.status='active' WHERE {$internalWhere} GROUP BY u.id ORDER BY u.created_at DESC, u.id DESC LIMIT 300") as $u) {
    $uid = (int) $u['id'];
    $status = (string) ($u['account_status'] ?: 'active');
    $primaryRole = (string) (($u['platform_role'] ?? '') ?: (($u['staff_type'] ?? '') ?: ($u['role'] ?? '')));
    if ((int) ($u['is_super_admin'] ?? 0) === 1) {
        $primaryRole = 'super_admin';
    }
    $scopeParts = [];
    if (!empty($u['staff_type'])) {
        $scopeParts[] = 'Staff: ' . rr_role_label((string) $u['staff_type']);
    }
    if (!empty($u['staff_state']) || !empty($u['staff_lga'])) {
        $scopeParts[] = 'Scope: ' . trim((string) ($u['staff_state'] ?? '') . ' ' . (string) ($u['staff_lga'] ?? ''));
    }
    if (!empty($u['certification_status'])) {
        $scopeParts[] = 'Certification: ' . status_label((string) $u['certification_status']);
    }
    if (!empty($u['assigned_roles'])) {
        $scopeParts[] = 'RBAC: ' . (string) $u['assigned_roles'];
    }
    $scopeParts[] = 'Activity: ' . rr_internal_activity($pdo, $uid);
    $actions = '';
    if ($status !== 'active') {
        $actions .= rr_action_form('update_user_status', ['user_id' => $uid, 'account_status' => 'active'], 'Activate', 'btn-primary');
    }
    $actions .= rr_action_form('update_user_status', ['user_id' => $uid, 'account_status' => 'needs_confirmation'], 'Require Confirm');
    $actions .= rr_role_form($uid);
    foreach (array_filter(array_map('trim', explode(',', (string) ($u['assigned_roles'] ?? '')))) as $role) {
        if (array_key_exists($role, rr_allowed_roles())) {
            $actions .= rr_action_form('revoke_role', ['user_id' => $uid, 'role_key' => $role], 'Revoke ' . rr_role_label($role), 'btn-danger', 'Revoke this RBAC role?');
        }
    }
    $actions .= '<a class="btn btn-sm btn-secondary" href="../users.php?search=' . urlencode((string) ($u['email'] ?? '')) . '">User</a>';
    $actions .= '<a class="btn btn-sm btn-secondary" href="../support.php?scope=assigned">Support</a>';
    if (in_array($primaryRole, ['field_agent', 'agronomist', 'agric_extensionist', 'extensionist'], true) || str_contains((string) ($u['assigned_roles'] ?? ''), 'field_agent') || str_contains((string) ($u['assigned_roles'] ?? ''), 'agronomist')) {
        $actions .= '<a class="btn btn-sm btn-secondary" href="../fields-management.php">Field</a>';
    }
    $addRow([
        'user_id' => $uid,
        'ref' => 'INT-' . str_pad((string) $uid, 5, '0', STR_PAD_LEFT),
        'name' => (string) ($u['name'] ?? ''),
        'email' => (string) ($u['email'] ?? ''),
        'phone' => (string) ($u['phone'] ?? ''),
        'source' => 'Internal User',
        'role' => rr_role_label($primaryRole),
        'status' => trim((string) ($u['email_verified_at'] ?? '')) === '' && $status === 'active' ? 'unconfirmed' : $status,
        'detail' => implode(' / ', array_filter($scopeParts)),
        'created_at' => (string) ($u['created_at'] ?? ''),
        'actions' => $actions,
    ]);
}
foreach (rx_rows($pdo, "SELECT a.*, u.id user_id FROM applications a LEFT JOIN users u ON u.application_id=a.id ORDER BY a.created_at DESC LIMIT 300") as $a) {
    $addRow([
        'user_id' => (int) ($a['user_id'] ?? 0),
        'ref' => (string) ($a['app_ref'] ?? ''),
        'name' => (string) ($a['name'] ?? ''),
        'email' => (string) ($a['email'] ?? ''),
        'phone' => (string) ($a['phone'] ?? ''),
        'source' => 'Grower Registry',
        'role' => 'Grower',
        'status' => (int) ($a['confirmed'] ?? 0) === 1 ? 'confirmed' : (string) ($a['review_status'] ?? 'pending'),
        'detail' => 'Location: ' . (string) ($a['location'] ?? '') . ' / Farm: ' . (string) ($a['farm_size'] ?? '0') . ' ha',
        'created_at' => (string) ($a['created_at'] ?? ''),
        'actions' => '<a class="btn btn-sm btn-secondary" href="applications.php?search=' . urlencode((string) ($a['app_ref'] ?? '')) . '">Review</a>',
    ]);
}

foreach (rx_rows($pdo, "SELECT pr.*, u.account_status FROM provider_registry pr LEFT JOIN users u ON u.id=pr.user_id ORDER BY pr.created_at DESC LIMIT 300") as $pr) {
    $addRow([
        'user_id' => (int) ($pr['user_id'] ?? 0),
        'ref' => 'PRV-' . (string) ($pr['id'] ?? ''),
        'name' => (string) ($pr['company_name'] ?? $pr['contact_person'] ?? ''),
        'email' => (string) ($pr['email'] ?? ''),
        'phone' => (string) ($pr['phone'] ?? ''),
        'source' => 'Provider Registry',
        'role' => rr_role_label((string) ($pr['provider_type'] ?? 'provider')),
        'status' => (string) ($pr['status'] ?? 'pending_review'),
        'detail' => 'Contact: ' . (string) ($pr['contact_person'] ?? '') . ' / Coverage: ' . (string) ($pr['coverage_area'] ?? ''),
        'created_at' => (string) ($pr['created_at'] ?? ''),
        'actions' => '',
    ]);
}

foreach (rx_rows($pdo, "SELECT ms.*, u.email user_email, u.account_status FROM marketplace_sellers ms LEFT JOIN users u ON u.id=ms.user_id ORDER BY ms.created_at DESC LIMIT 300") as $s) {
    $addRow([
        'user_id' => (int) ($s['user_id'] ?? 0),
        'ref' => 'SEL-' . str_pad((string) ($s['id'] ?? 0), 5, '0', STR_PAD_LEFT),
        'name' => (string) ($s['store_name'] ?? ''),
        'email' => (string) ($s['email'] ?: ($s['user_email'] ?? '')),
        'phone' => (string) ($s['phone'] ?? ''),
        'source' => 'Marketplace Seller',
        'role' => rr_role_label((string) ($s['seller_type'] ?? 'general_seller')),
        'status' => (string) ($s['approval_status'] ?? 'pending'),
        'detail' => 'Verification: ' . (string) ($s['verification_status'] ?? 'pending') . ' / Location: ' . (string) ($s['location_label'] ?? ''),
        'created_at' => (string) ($s['created_at'] ?? ''),
        'actions' => '',
    ]);
}

foreach (rx_rows($pdo, "SELECT bp.*, u.name, u.email, u.phone, u.account_status, u.created_at FROM buyer_profiles bp JOIN users u ON u.id=bp.user_id ORDER BY bp.created_at DESC LIMIT 200") as $b) {
    $addRow([
        'user_id' => (int) ($b['user_id'] ?? 0),
        'ref' => 'BUY-' . str_pad((string) ($b['user_id'] ?? 0), 5, '0', STR_PAD_LEFT),
        'name' => (string) ($b['name'] ?? ''),
        'email' => (string) ($b['email'] ?? ''),
        'phone' => (string) ($b['phone'] ?? ''),
        'source' => 'Buyer Workspace',
        'role' => 'Buyer',
        'status' => (string) ($b['account_status'] ?? 'active'),
        'detail' => 'Type: ' . (string) ($b['buyer_type'] ?? 'individual') . ' / State: ' . (string) ($b['preferred_state'] ?? ''),
        'created_at' => (string) ($b['created_at'] ?? ''),
        'actions' => '<a class="btn btn-sm btn-secondary" href="users.php?search=' . urlencode((string) ($b['email'] ?? '')) . '">User</a>',
    ]);
}

foreach (rx_rows($pdo, "SELECT wr.user_id, COUNT(*) enrollments, MAX(wr.registered_at) registered_at, u.name, u.email, u.phone, u.account_status FROM webinar_registrations wr JOIN users u ON u.id=wr.user_id GROUP BY wr.user_id, u.name, u.email, u.phone, u.account_status ORDER BY registered_at DESC LIMIT 200") as $l) {
    $addRow([
        'user_id' => (int) ($l['user_id'] ?? 0),
        'ref' => 'LRN-' . str_pad((string) ($l['user_id'] ?? 0), 5, '0', STR_PAD_LEFT),
        'name' => (string) ($l['name'] ?? ''),
        'email' => (string) ($l['email'] ?? ''),
        'phone' => (string) ($l['phone'] ?? ''),
        'source' => 'Academy Learner',
        'role' => 'Learner',
        'status' => (string) ($l['account_status'] ?? 'active'),
        'detail' => number_format((int) ($l['enrollments'] ?? 0)) . ' course enrollment(s)',
        'created_at' => (string) ($l['registered_at'] ?? ''),
        'actions' => '',
    ]);
}

if ($search !== '') {
    $needle = strtolower($search);
    $rows = array_values(array_filter($rows, static fn(array $row): bool => str_contains(strtolower(implode(' ', array_map('strval', [$row['ref'], $row['name'], $row['email'], $row['phone'], $row['source'], $row['role'], $row['status'], $row['detail']]))), $needle)));
}
if ($sourceFilter !== '') {
    $rows = array_values(array_filter($rows, static fn(array $row): bool => $row['source'] === $sourceFilter));
}
if ($statusFilter !== '') {
    $rows = array_values(array_filter($rows, static fn(array $row): bool => strtolower((string) $row['status']) === strtolower($statusFilter)));
}

usort($rows, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
$totalRows = count($rows);
$pagedRows = array_slice($rows, $offset, $limit);
$sources = array_values(array_unique(array_map(static fn(array $row): string => (string) $row['source'], $rows)));
sort($sources);
$statuses = array_values(array_unique(array_map(static fn(array $row): string => strtolower((string) $row['status']), $rows)));
sort($statuses);

require __DIR__ . '/layout/header.php';
?>
<style>
  .dashboard-hero { border-radius: 18px; overflow: hidden; }
  .dashboard-hero .card-body { padding: 28px; }
  .hero-meta { font-size: 0.96rem; }
  .hero-actions .btn { min-width: 170px; }
  .hero-actions .btn-group { min-width: 170px; }
  .metric-card-icon { width: 48px; height: 48px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; }
  .metric-card-icon.bg-primary { background: rgba(22, 103, 255, 0.12); color: #1666ff; }
  .metric-card-icon.bg-success { background: rgba(16, 185, 129, 0.12); color: #10b981; }
  .metric-card-icon.bg-warning { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
  .metric-card-icon.bg-info { background: rgba(56, 189, 248, 0.12); color: #38bdf8; }
  .metric-card-label { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.12em; color: #64748b; margin-bottom: 0.75rem; }
  .metric-card-value { font-size: 2.4rem; font-weight: 800; line-height: 1; }
  .metric-card-note { color: #475569; font-size: 0.95rem; margin-top: 0.5rem; }
  .card.card-highlight { border: 1px solid rgba(16, 24, 40, 0.08); box-shadow: 0 24px 48px rgba(15, 23, 42, 0.04); }
  .status-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.03em; }
  .status-pill.pending { background: #fef3c7; color: #92400e; }
  .status-pill.approved { background: #dcfce7; color: #166534; }
  .status-pill.active { background: #d1fae5; color: #065f46; }
  .platform-filter { display:grid; grid-template-columns:minmax(260px,1fr) 190px 170px auto; gap:10px; align-items:end; }
  .platform-row-actions { display:flex; flex-wrap:wrap; gap:4px; min-width:300px; }
  .detail-text { max-width:360px; color:var(--text-secondary); line-height:1.4; }
  .source-cell { white-space:nowrap; }
  @media(max-width:1100px){ .platform-filter{grid-template-columns:1fr}.platform-row-actions{min-width:0} }
</style>

<?= admin_workspace_operator_strip($pdo, ['asset_prefix' => '../../', 'profile_href' => 'profile.php', 'password_href' => 'profile.php#account', 'logout_action' => 'logout.php', 'title' => 'Registry workspace', 'placeholder' => 'Search registrations, users, roles...']) ?><div class="dashboard-hero card shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
    <div>
      <h1 class="page-title">Platform Registration Control Center</h1>
      <p class="page-subtitle">Monitor registry users, grower applications, provider and seller onboarding, and operator approval velocity from a single live dashboard.</p>
      <div class="hero-meta text-muted mt-2">Data reflects live database state for the registry workspace. Use exports to extract targeted registry segments.</div>
    </div>
    <div class="hero-actions d-flex flex-wrap gap-2 align-items-center">
      <div class="btn-group">
        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Export registry</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="export.php?type=growers">Grower applications</a></li>
          <li><a class="dropdown-item" href="export.php?type=providers">Provider records</a></li>
          <li><a class="dropdown-item" href="export.php?type=sellers">Marketplace sellers</a></li>
          <li><a class="dropdown-item" href="export.php?type=users">Platform users</a></li>
          <li><a class="dropdown-item" href="export.php?type=pending">Pending registry queues</a></li>
        </ul>
      </div>
      <a href="../users.php" class="btn btn-primary">Manage Users</a>
    </div>
  </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-4">
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-primary">👥</span><div><div class="metric-card-label">Total user accounts</div><div class="metric-card-value"><?= number_format($totalUsers) ?></div><div class="metric-card-note"><?= number_format($activeUsers) ?> active users</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-warning">⏳</span><div><div class="metric-card-label">Pending verification</div><div class="metric-card-value text-warning"><?= number_format($pendingUsers) ?></div><div class="metric-card-note">Awaiting email or account confirmation</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-info">🏢</span><div><div class="metric-card-label">Provider records</div><div class="metric-card-value"><?= number_format($totalProviders) ?></div><div class="metric-card-note"><?= number_format($pendingProviderProfiles) ?> pending review</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-success">🛒</span><div><div class="metric-card-label">Marketplace sellers</div><div class="metric-card-value"><?= number_format($totalSellers) ?></div><div class="metric-card-note"><?= number_format($pendingSellerProfiles) ?> pending approval</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-primary">🌱</span><div><div class="metric-card-label">Grower applications</div><div class="metric-card-value"><?= number_format($totalApplications) ?></div><div class="metric-card-note"><?= number_format($pendingGrowerApplications) ?> still pending</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-warning">🎫</span><div><div class="metric-card-label">Open registry tickets</div><div class="metric-card-value"><?= number_format($openSupport) ?></div><div class="metric-card-note">Support workload for registry</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-info">👤</span><div><div class="metric-card-label">Internal operators</div><div class="metric-card-value"><?= number_format($totalInternalUsers) ?></div><div class="metric-card-note"><?= number_format($activeInternalUsers) ?> active</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-success">⚙️</span><div><div class="metric-card-label">Internal activity</div><div class="metric-card-value"><?= number_format($internalFieldActivity + $internalSupportActivity) ?></div><div class="metric-card-note"><?= number_format($internalFieldActivity) ?> field / <?= number_format($internalSupportActivity) ?> support</div></div></div></div></div>
</div>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-4">
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-primary">🆕</span><div><div class="metric-card-label">New profiles today</div><div class="metric-card-value"><?= number_format($newProviderProfilesToday + $newSellerProfilesToday) ?></div><div class="metric-card-note"><?= number_format($newProviderProfilesToday) ?> providers + <?= number_format($newSellerProfilesToday) ?> sellers</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-success">✅</span><div><div class="metric-card-label">Verified users today</div><div class="metric-card-value"><?= number_format($newVerifiedToday) ?></div><div class="metric-card-note">Verified accounts in last 24h</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-warning">📈</span><div><div class="metric-card-label">Approval velocity</div><div class="metric-card-value"><?= number_format($approvalVelocity) ?></div><div class="metric-card-note"><?= number_format($approvedProviderToday) ?> provider / <?= number_format($approvedSellerToday) ?> seller approvals</div></div></div></div></div>
  <div class="col"><div class="card card-highlight h-100"><div class="card-body d-flex gap-3 align-items-start"><span class="metric-card-icon bg-info">⏱️</span><div><div class="metric-card-label">Registry queue aging</div><div class="metric-card-value"><?= number_format(max($oldestPendingProviderHours, $oldestPendingSellerHours, $oldestPendingApplicationHours)) ?>h</div><div class="metric-card-note">Oldest: provider <?= number_format($oldestPendingProviderHours) ?>h, seller <?= number_format($oldestPendingSellerHours) ?>h, application <?= number_format($oldestPendingApplicationHours) ?>h</div></div></div></div></div>
</div>
<div class="alert alert-info">Live registry dashboard counts are sourced directly from the database and updated on every page load. Last refreshed <?= rx_e($dataReflectionNote) ?>.</div>

<div class="card">
  <div class="card-header">
    <form method="get" class="platform-filter" style="width:100%">
      <label><span class="form-label">Search platform registrations</span><input class="form-input" name="search" value="<?= rx_e($search) ?>" placeholder="Name, email, phone, ref, role, status, source"></label>
      <label><span class="form-label">Source</span><select class="form-select" name="source"><option value="">All sources</option><?php foreach ($sources as $src): ?><option value="<?= rx_e($src) ?>" <?= $sourceFilter === $src ? 'selected' : '' ?>><?= rx_e($src) ?></option><?php endforeach; ?></select></label>
      <label><span class="form-label">Status</span><select class="form-select" name="status"><option value="">All statuses</option><?php foreach ($statuses as $st): ?><option value="<?= rx_e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= rx_e(status_label($st)) ?></option><?php endforeach; ?></select></label>
      <div><input type="hidden" name="per_page" value="<?= (int) $limit ?>"><button class="btn btn-primary">Search</button> <a class="btn btn-secondary" href="index.php">Clear</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">All Platform Registration Records</h3>
    <span class="muted"><?= number_format($totalRows) ?> result(s)</span>
  </div>
  <div class="card-body p0" style="overflow:auto">
    <table>
      <thead>
        <tr><th>Reference</th><th>Registrant</th><th>Source</th><th>Role</th><th>Status</th><th>Details</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pagedRows as $row): ?>
          <tr>
            <td><strong><?= rx_e((string) $row['ref']) ?></strong><?php if ((int) $row['user_id'] > 0): ?><br><small class="muted">User #<?= (int) $row['user_id'] ?></small><?php endif; ?></td>
            <td><div class="avatar-row"><span class="avatar-sm"><?= rx_e(rx_user_initials((string) $row['name'])) ?></span><span><strong><?= rx_e((string) ($row['name'] ?: 'Unnamed')) ?></strong><br><small><?= rx_e((string) $row['email']) ?><?= $row['phone'] ? ' / ' . rx_e((string) $row['phone']) : '' ?></small></span></div></td>
            <td class="source-cell"><?= rr_source_badge((string) $row['source']) ?></td>
            <td><?= rx_e((string) $row['role']) ?></td>
            <td><?= rr_status_badge((string) $row['status']) ?></td>
            <td><div class="detail-text"><?= rx_e((string) $row['detail']) ?></div></td>
            <td><?= $row['created_at'] ? rx_e(date('M j, Y', strtotime((string) $row['created_at']))) : '-' ?></td>
            <td><div class="platform-row-actions"><?= $row['actions'] ?></div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$pagedRows): ?><tr><td colspan="8" style="text-align:center;padding:38px">No registration record matches this filter.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= rx_pagination_links($totalRows, $limit, $page, 'index.php') ?>

<?php require __DIR__ . '/layout/footer.php'; ?>