<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function resend_confirmation_wants_json(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requested = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requested === 'xmlhttprequest' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function resend_confirmation_respond(bool $success, string $message, int $status = 200, bool $emailSent = false): void
{
    if (resend_confirmation_wants_json()) {
        json_response(['success' => $success, 'email_sent' => $emailSent, 'message' => $message], $status);
    }
    http_response_code($status);
    $title = $success ? 'Confirmation Email Sent' : 'Confirmation Email Not Sent';
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?></title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f8f2;font-family:Segoe UI,Arial,sans-serif;color:#101828}.card{width:min(560px,92vw);background:#fff;border:1px solid #dfe8d8;border-radius:8px;padding:28px;box-shadow:0 22px 54px rgba(16,24,40,.12)}.brand{display:flex;align-items:center;gap:12px;margin-bottom:18px;color:#075f2a;font-weight:950}.brand img{width:54px;height:54px;border-radius:50%;object-fit:contain;border:1px solid #dfe8d8;background:#fff}.brand span{display:block;font-size:1.04rem}.brand small{display:block;color:#667085;font-weight:750}h1{margin:0 0 10px;color:#075f2a}.ok{color:#075f2a}.err{color:#b42318}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}a{border:1px solid #075f2a;border-radius:7px;padding:10px 12px;text-decoration:none;font-weight:900;color:#075f2a}a.primary{background:#075f2a;color:#fff}</style></head><body><main class="card"><div class="brand"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV<small>Account Confirmation</small></span></div><h1><?= e($title) ?></h1><p class="<?= $success ? 'ok' : 'err' ?>"><?= e($message) ?></p><div class="actions"><a class="primary" href="login.php">Back to login</a><a href="apply.php">Registration</a><a href="dashboard/forgot-password.php">Reset password</a></div></main></body></html>
    <?php
    exit;
}

$email = filter_var(trim((string) ($_REQUEST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$email) {
    resend_confirmation_respond(false, 'A valid email is required.', 422);
}

if (!app_check_rate_limit('resend_confirmation', 3, 3600)) {
    resend_confirmation_respond(false, 'Too many requests. Please try again in an hour.', 429);
}

try {
    $pdo = db();
    app_ensure_core_schema($pdo);
    app_ensure_user_verification_schema($pdo);

    $stmt = $pdo->prepare("SELECT id, name, confirmed FROM applications WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $app = $stmt->fetch();

    if (!$app) {
        $userStmt = $pdo->prepare("SELECT id, name, account_status, email_verified_at FROM users WHERE email = ? LIMIT 1");
        $userStmt->execute([(string) $email]);
        $user = $userStmt->fetch();
        if (!$user) {
            resend_confirmation_respond(false, 'No pending account or application was found for this email.', 404);
        }
        if (!app_user_needs_email_verification($user)) {
            resend_confirmation_respond(false, 'Your account is already verified. Use password reset if you cannot login.', 409);
        }
        $sent = app_send_user_verification($pdo, (int) $user['id'], 'NATCODEV');
        resend_confirmation_respond(true, 'Verification link resent. Check your email before logging in.', 200, $sent);
    }

    if ((int) $app['confirmed'] === 1) {
        resend_confirmation_respond(false, 'Your application is already confirmed. Use password reset if you cannot login.', 409);
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE applications SET confirmation_token = ?, created_at = NOW() WHERE id = ?")->execute([$token, $app['id']]);

    $confirmUrl = app_base_url() . '/confirm_email.php?token=' . urlencode($token);
    $plain = "Dear {$app['name']},\n\nUse this new link to confirm your NATCODEV application:\n{$confirmUrl}\n\nThis link expires in 7 days.\n\nThe NATCODEV Team";
    $html = "
        <p>Dear <strong>" . e((string) $app['name']) . "</strong>,</p>
        <p>Use this new link to confirm your NATCODEV application:</p>
        <p><a href=\"" . e($confirmUrl) . "\" style=\"display:inline-block;padding:10px 18px;background:#2d5016;color:#fff;text-decoration:none;border-radius:5px;\">Confirm My Email</a></p>
        <p>This link expires in 7 days.</p>
    ";

    $sent = app_send_mail((string) $email, 'Your NATCODEV Confirmation Link', $plain, $html);
    if ($sent) {
        $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE id = ?")->execute([$app['id']]);
    }

    resend_confirmation_respond(true, 'Confirmation link resent. Check your email before logging in.', 200, $sent);
} catch (Throwable $e) {
    error_log('Resend confirmation error: ' . $e->getMessage());
    resend_confirmation_respond(false, 'System error. Please try again.', 500);
}