<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin-layout.php';
require_once __DIR__ . '/lib/twilio.php';
require_once __DIR__ . '/lib/admin-user-import.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_ensure_import_schema($pdo);

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$message = '';
$error = '';

if ($token === '') {
    http_response_code(404);
    exit('Invalid engagement link.');
}

$stmt = $pdo->prepare("SELECT * FROM user_import_records WHERE engagement_token = ? LIMIT 1");
$stmt->execute([$token]);
$record = $stmt->fetch();
if (!$record) {
    http_response_code(404);
    exit('Invalid or expired engagement link.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? $record['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address to activate your dashboard account.';
    } else {
        try {
            $pdo->beginTransaction();
            $record['email'] = $email;
            $record['phone_e164'] = $record['phone_e164'] ?: admin_import_phone_e164((string) $record['phone']);
            $appId = admin_import_insert_application($pdo, $record, $token);
            if (!$appId) {
                throw new RuntimeException('Unable to create your application record.');
            }

            $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = NOW(), team_notified = 1 WHERE id = ?")->execute([$appId]);
            $temporaryPassword = strtoupper(bin2hex(random_bytes(4)));
            $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO users (email, password, application_id, name, phone, location, role)
                VALUES (?, ?, ?, ?, ?, ?, 'grower')
                ON DUPLICATE KEY UPDATE application_id = VALUES(application_id), name = VALUES(name), phone = VALUES(phone), location = VALUES(location)
            ")->execute([
                $email,
                $passwordHash,
                $appId,
                $record['name'],
                $record['phone_e164'] ?: $record['phone'],
                $record['address'],
            ]);
            $userId = (int) $pdo->lastInsertId();
            if ($userId === 0) {
                $find = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $find->execute([$email]);
                $userId = (int) ($find->fetchColumn() ?: 0);
            }

            $pdo->prepare("
                UPDATE user_import_records
                SET email = ?, status = 'engagement_confirmed', application_id = ?, user_id = ?, confirmed_at = NOW(), status_note = 'Confirmed by phone engagement link.'
                WHERE id = ?
            ")->execute([$email, $appId, $userId ?: null, $record['id']]);
            $pdo->commit();

            $loginUrl = app_base_url() . '/dashboard/login.php';
            app_send_mail($email, 'Welcome to NATCODEV', "Dear {$record['name']},\n\nYour NATCODEV engagement has been confirmed.\n\nDashboard: {$loginUrl}\nTemporary password: {$temporaryPassword}\n\nPlease change this password after logging in.");
            if (!empty($record['phone_e164'])) {
                sendSMSMessage((string) $record['phone_e164'], "NATCODEV: Your engagement is confirmed. Login: {$loginUrl}. Temporary password: {$temporaryPassword}");
            }
            $message = 'Your NATCODEV engagement has been confirmed. Login details have been sent.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Phone engagement confirmation error: ' . $e->getMessage());
            $error = 'Unable to confirm right now. Please contact support.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirm Engagement - NATCODEV</title>
  <style>
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; background:#f4f8f5; color:#1f2937; font-family:Segoe UI,Tahoma,sans-serif; }
    main { width:min(560px,100%); background:#fff; border:1px solid #d8e2dc; border-radius:8px; padding:28px; box-shadow:0 16px 38px rgba(16,24,40,.08); }
    h1 { margin-top:0; color:#1a5276; }
    label { display:block; font-weight:800; margin:14px 0 6px; }
    input { width:100%; padding:12px; border:1px solid #d8e2dc; border-radius:6px; font:inherit; }
    button, a.button { display:inline-block; margin-top:16px; padding:11px 16px; border:0; border-radius:6px; background:#1f8a55; color:#fff; font-weight:800; text-decoration:none; cursor:pointer; }
    .notice { padding:12px; border-radius:6px; margin:12px 0; }
    .ok { background:#e8f7ee; color:#166b41; }
    .error { background:#fdecec; color:#a32020; }
    .meta { color:#667085; }
  </style>
</head>
<body>
  <main>
    <h1>Confirm NATCODEV Engagement</h1>
    <?php if ($message): ?>
      <div class="notice ok"><?= e($message) ?></div>
      <a class="button" href="dashboard/login.php">Go to Dashboard</a>
    <?php else: ?>
      <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
      <p>Hello <strong><?= e($record['name'] ?? 'Grower') ?></strong>, confirm your NATCODEV grower engagement and activate your dashboard account.</p>
      <form method="post">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Email Address</label>
        <input type="email" name="email" value="<?= e($record['email'] ?? '') ?>" required>
        <p class="meta">We use this email for secure dashboard login and certificate communication.</p>
        <button type="submit">Confirm Engagement</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
