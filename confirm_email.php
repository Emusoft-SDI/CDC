<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(400);
    exit('Invalid confirmation link.');
}

try {
    $pdo = db();
    app_ensure_core_schema($pdo);

    $stmt = $pdo->prepare("SELECT id, app_ref, name, email, confirmed, created_at FROM applications WHERE confirmation_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $app = $stmt->fetch();

    if (!$app) {
        http_response_code(404);
        exit('Invalid or expired confirmation link.');
    }

    $createdAt = new DateTimeImmutable((string) $app['created_at']);
    if ((int) $app['confirmed'] !== 1 && $createdAt < new DateTimeImmutable('-7 days')) {
        http_response_code(410);
        $resendUrl = app_base_url() . '/resend-confirmation.php?email=' . urlencode((string) $app['email']);
        exit('<h2>Confirmation Link Expired</h2><p>Your confirmation link has expired.</p><p><a href="' . e($resendUrl) . '">Request a new link</a></p>');
    }

    $newlyConfirmed = (int) $app['confirmed'] !== 1;
    if ($newlyConfirmed) {
        $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = NOW(), team_notified = 1 WHERE id = ?")->execute([$app['id']]);

        $existingUserStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $existingUserStmt->execute([$app['email']]);
        $hasExistingUser = (bool) $existingUserStmt->fetchColumn();
        $temporaryPassword = $hasExistingUser ? '' : bin2hex(random_bytes(4));
        $passwordHash = $hasExistingUser ? '' : password_hash($temporaryPassword, PASSWORD_DEFAULT);
        if ($hasExistingUser) {
            $pdo->prepare("UPDATE users SET application_id = ?, name = ? WHERE email = ?")->execute([$app['id'], $app['name'], $app['email']]);
        } else {
            $pdo->prepare("
                INSERT INTO users (email, password, application_id, name, role)
                VALUES (?, ?, ?, ?, 'grower')
            ")->execute([$app['email'], $passwordHash, $app['id'], $app['name']]);
        }

        $loginUrl = app_base_url() . '/login.php';
        $plain = "Dear {$app['name']},\n\nYour NATCODEV application ({$app['app_ref']}) has been confirmed.\n\nDashboard: {$loginUrl}\n" . ($temporaryPassword !== '' ? "Temporary password: {$temporaryPassword}\n\nPlease change this password after logging in.\n\n" : "Use the password you created during registration.\n\n") . "The NATCODEV Team";
        $html = "
            <p>Dear <strong>" . e($app['name']) . "</strong>,</p>
            <p>Your NATCODEV application <strong>" . e($app['app_ref']) . "</strong> has been confirmed.</p>
            <p><a href=\"" . e($loginUrl) . "\" style=\"display:inline-block;padding:10px 18px;background:#2d5016;color:#fff;text-decoration:none;border-radius:5px;\">Open Dashboard</a></p>
            " . ($temporaryPassword !== '' ? "<p>Temporary password: <strong>" . e($temporaryPassword) . "</strong></p><p>Please change this password after logging in.</p>" : "<p>Use the password you created during registration.</p>") . "
        ";
        app_send_mail((string) $app['email'], 'Welcome to NATCODEV', $plain, $html);
        app_send_mail((string) app_env('ADMIN_NOTIFY_EMAIL', 'info@coconutventurehub.ng'), 'Confirmed NATCODEV Application', "Confirmed: {$app['name']} ({$app['email']})\nRef: {$app['app_ref']}");
    }
} catch (Throwable $e) {
    error_log('Confirmation error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to confirm right now. Please contact support.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Confirmed - NATCODEV</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f0f7eb; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; padding:20px; }
    main { max-width:620px; background:#fff; border-radius:12px; padding:32px; box-shadow:0 10px 30px rgba(0,0,0,.08); text-align:center; }
    h1 { color:#2d5016; margin-top:0; }
    a { color:#2d5016; font-weight:bold; }
  </style>
</head>
<body>
  <main>
    <h1>Email Confirmed</h1>
    <p>Thank you, <strong><?= e($app['name']) ?></strong>. Your application is active.</p>
    <p><?= $newlyConfirmed ? 'A dashboard login email has been sent to' : 'Your dashboard account is linked to' ?> <strong><?= e($app['email']) ?></strong>.</p>
    <p><a href="login.php">Go to your dashboard</a></p>
  </main>
</body>
</html>
