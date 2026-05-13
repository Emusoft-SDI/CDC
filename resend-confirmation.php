<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$email = filter_var(trim((string) ($_REQUEST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$email) {
    json_response(['success' => false, 'message' => 'A valid email is required'], 422);
}

try {
    $pdo = db();
    app_ensure_core_schema($pdo);

    $stmt = $pdo->prepare("SELECT id, name, confirmed FROM applications WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $app = $stmt->fetch();

    if (!$app) {
        json_response(['success' => false, 'message' => 'No application found for this email.'], 404);
    }
    if ((int) $app['confirmed'] === 1) {
        json_response(['success' => false, 'message' => 'Your application is already confirmed.'], 409);
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE applications SET confirmation_token = ?, created_at = NOW() WHERE id = ?")->execute([$token, $app['id']]);

    $confirmUrl = app_base_url() . '/confirm_email.php?token=' . urlencode($token);
    $plain = "Dear {$app['name']},\n\nUse this new link to confirm your NATCODEV application:\n{$confirmUrl}\n\nThis link expires in 7 days.\n\nThe NATCODEV Team";
    $html = "
        <p>Dear <strong>" . e($app['name']) . "</strong>,</p>
        <p>Use this new link to confirm your NATCODEV application:</p>
        <p><a href=\"" . e($confirmUrl) . "\" style=\"display:inline-block;padding:10px 18px;background:#2d5016;color:#fff;text-decoration:none;border-radius:5px;\">Confirm My Email</a></p>
        <p>This link expires in 7 days.</p>
    ";

    $sent = app_send_mail((string) $email, 'Your NATCODEV Confirmation Link', $plain, $html);
    if ($sent) {
        $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE id = ?")->execute([$app['id']]);
    }

    json_response(['success' => true, 'email_sent' => $sent, 'message' => 'Confirmation link resent.']);
} catch (Throwable $e) {
    error_log('Resend confirmation error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'System error. Please try again.'], 500);
}
