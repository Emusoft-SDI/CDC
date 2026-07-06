<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/twilio.php';

$pdo = db();
app_ensure_core_schema($pdo);
academy_ensure_schema($pdo);

$limit = max(1, min(100, (int) ($argv[1] ?? $_GET['limit'] ?? 25)));
$stmt = $pdo->prepare("
    SELECT r.*, w.title course_title, c.title cohort_title, c.start_at cohort_start_at
    FROM academy_reminders r
    LEFT JOIN webinars w ON w.id = r.webinar_id
    LEFT JOIN academy_cohorts c ON c.id = r.cohort_id
    WHERE r.status = 'scheduled'
      AND (r.send_at IS NULL OR r.send_at <= NOW())
    ORDER BY COALESCE(r.send_at, r.created_at) ASC, r.id ASC
    LIMIT {$limit}
");
$stmt->execute();
$reminders = $stmt->fetchAll();

$processed = 0;
$recipients = 0;

foreach ($reminders as $reminder) {
    $users = academy_reminder_recipients($pdo, $reminder);
    $subject = 'NATCODEV Academy: ' . (string) $reminder['title'];
    $body = academy_reminder_body($reminder);
    $channel = (string) ($reminder['channel'] ?? 'dashboard');

    foreach ($users as $user) {
        $recipients++;
        academy_send_reminder_to_user($channel, $user, $subject, $body);
    }

    $update = $pdo->prepare("UPDATE academy_reminders SET status = 'sent', sent_at = NOW() WHERE id = ? AND status = 'scheduled'");
    $update->execute([(int) $reminder['id']]);
    $processed++;
}

$summary = "academy_reminders_processed={$processed}; recipients={$recipients}";
if (PHP_SAPI === 'cli') {
    echo $summary . PHP_EOL;
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'processed' => $processed, 'recipients' => $recipients], JSON_UNESCAPED_SLASHES);
}

function academy_reminder_recipients(PDO $pdo, array $reminder): array
{
    if (!empty($reminder['cohort_id'])) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email, u.phone
            FROM academy_cohorts c
            JOIN webinar_registrations wr ON wr.webinar_id = c.webinar_id
            JOIN users u ON u.id = wr.user_id
            WHERE c.id = ?
        ");
        $stmt->execute([(int) $reminder['cohort_id']]);
        return $stmt->fetchAll();
    }

    if (!empty($reminder['webinar_id'])) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email, u.phone
            FROM webinar_registrations wr
            JOIN users u ON u.id = wr.user_id
            WHERE wr.webinar_id = ?
        ");
        $stmt->execute([(int) $reminder['webinar_id']]);
        return $stmt->fetchAll();
    }

    $roles = array_values(array_filter(array_map('trim', explode(',', (string) ($reminder['audience_roles'] ?? '')))));
    if (!$roles || in_array('all', $roles, true)) {
        return $pdo->query("SELECT id, name, email, phone FROM users WHERE COALESCE(email, phone, '') <> '' LIMIT 1000")->fetchAll();
    }

    $roleExpr = app_column_exists($pdo, 'users', 'platform_role')
        ? "COALESCE(NULLIF(platform_role, ''), role)"
        : 'role';
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone
        FROM users
        WHERE {$roleExpr} IN ({$placeholders})
          AND COALESCE(email, phone, '') <> ''
        LIMIT 1000
    ");
    $stmt->execute($roles);
    return $stmt->fetchAll();
}

function academy_reminder_body(array $reminder): string
{
    $parts = [(string) $reminder['message']];
    if (!empty($reminder['course_title'])) {
        $parts[] = 'Course: ' . (string) $reminder['course_title'];
    }
    if (!empty($reminder['cohort_title'])) {
        $parts[] = 'Session: ' . (string) $reminder['cohort_title'];
    }
    if (!empty($reminder['cohort_start_at'])) {
        $parts[] = 'Starts: ' . (string) $reminder['cohort_start_at'];
    }
    $parts[] = 'Academy: ' . app_base_url() . '/dashboard/academy.php';
    return implode("\n", $parts);
}

function academy_send_reminder_to_user(string $channel, array $user, string $subject, string $body): void
{
    $channel = strtolower($channel);
    if ($channel === 'email') {
        if (!empty($user['email'])) {
            app_send_mail((string) $user['email'], $subject, $body);
        }
        return;
    }
    if ($channel === 'sms') {
        if (!empty($user['phone'])) {
            sendSMSMessage((string) $user['phone'], $body);
        }
        return;
    }
    if ($channel === 'whatsapp') {
        if (!empty($user['phone'])) {
            sendWhatsAppMessage((string) $user['phone'], $body);
        }
        return;
    }

    app_log_notification('dashboard', (string) ($user['email'] ?: ($user['phone'] ?? 'user-' . ($user['id'] ?? ''))), $subject, $body, 'logged', 'academy_reminder', 'Visible in NATCODEV Academy reminders');
}
