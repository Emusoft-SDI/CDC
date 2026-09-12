<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';

app_require_cli('pending-actions-notify');

$pdo = db();

if (!app_table_exists($pdo, 'admin_action_requests')) {
    echo "Table admin_action_requests does not exist. Skipping." . PHP_EOL;
    exit;
}

$stmt = $pdo->query("SELECT request_type, COUNT(*) as count FROM admin_action_requests WHERE status = 'pending' GROUP BY request_type");
$pendingActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pendingActions)) {
    echo "No pending actions to notify." . PHP_EOL;
    exit;
}

$summaryList = "";
$totalPending = 0;
foreach ($pendingActions as $action) {
    $typeLabel = ucwords(str_replace('_', ' ', $action['request_type']));
    $summaryList .= "- {$typeLabel}: {$action['count']} pending request(s)\n";
    $totalPending += (int) $action['count'];
}

$dashboardUrl = app_base_url() . '/admin/index.php';
$subject = "Action Required: {$totalPending} Pending Admin Requests";
$body = "Hello Super Admin,\n\nThere are currently {$totalPending} action(s) waiting for your approval in the system:\n\n{$summaryList}\n\nPlease log in to the dashboard to review and approve/reject these requests:\n{$dashboardUrl}\n\nThank you,\nNATCODEV System";

$admins = $pdo->query("SELECT email FROM users WHERE role = 'super_admin' AND email <> ''")->fetchAll(PDO::FETCH_ASSOC);

if (empty($admins)) {
    echo "No super_admin users found with an email address." . PHP_EOL;
    exit;
}

$sent = 0;
foreach ($admins as $admin) {
    if (app_send_mail((string) $admin['email'], $subject, $body)) {
        $sent++;
    }
}

echo "Pending actions notification sent to {$sent} super admin(s)." . PHP_EOL;
