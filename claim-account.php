<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
staff_claim_ensure_schema($pdo);

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$message = '';
$error = '';

if ($token === '' || !preg_match('/^[0-9a-f]{32}$/', $token)) {
    http_response_code(404);
    exit('Invalid account claim link.');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE claim_token = ? LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('Invalid or expired claim link.');
}
if ((string) ($user['claimed_at'] ?? '') !== '') {
    http_response_code(410);
    exit('This claim link has already been used. Ask your NATCODEV administrator to issue a new one.');
}
$claimDeadline = strtotime((string) ($user['claim_deadline'] ?? ''));
if ($claimDeadline === false || $claimDeadline <= time()) {
    http_response_code(410);
    exit('This claim link has expired. Ask your NATCODEV administrator to issue a new one.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } elseif (!app_check_rate_limit('staff_account_claim', 5, 600)) {
        $error = 'Too many claim attempts. Please try again later.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email !== strtolower((string) $user['email'])) {
            $error = 'Enter the email address this claim link was sent to.';
        } elseif (strlen($password) < 8) {
            $error = 'Choose a password of at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'The password fields do not match.';
        } else {
            try {
                $pdo->beginTransaction();
                // Fill blank profile fields, rotate the password, and burn the
                // token in one statement guarded by the claim token so a link
                // can never be claimed twice.
                $claim = $pdo->prepare("
                    UPDATE users
                    SET password = ?,
                        name = ?,
                        phone = ?,
                        claim_token = NULL,
                        claim_deadline = NULL,
                        claimed_at = NOW(),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND claim_token = ?
                ");
                $claim->execute([
                    password_hash($password, PASSWORD_DEFAULT),
                    ((string) ($user['name'] ?? '')) !== '' ? (string) $user['name'] : $name,
                    ((string) ($user['phone'] ?? '')) !== '' ? (string) $user['phone'] : ($phone !== '' ? $phone : null),
                    (int) $user['id'],
                    $token,
                ]);
                if ($claim->rowCount() !== 1) {
                    throw new RuntimeException('This claim link has already been used.');
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Staff account claim error: ' . $e->getMessage());
                $error = 'Unable to claim the account right now. Please try again.';
            }

            if ($error === '') {
                // Hand off to the OTP login flow so the claim ends in a verified session.
                $fresh = $pdo->prepare('SELECT id, name, email, phone, role, platform_role FROM users WHERE id = ? LIMIT 1');
                $fresh->execute([(int) $user['id']]);
                $claimed = $fresh->fetch();
                $challenge = otp_begin_email_login_challenge($pdo, $claimed ?: $user);
                if (!empty($challenge['ok'])) {
                    redirect_to('verify-otp.php');
                }
                $error = 'Account claimed, but the verification code could not be delivered. ' . ((string) ($challenge['message'] ?? ''));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Claim Staff Account - NATCODEV</title>
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
    <h1>Claim Your NATCODEV Staff Account</h1>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <p>Confirm your email and choose your sign-in details. This link expires in 14 days.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label>Email Address</label>
      <input type="email" name="email" value="<?= e(strtolower((string) ($user['email'] ?? ''))) ?>" required>
      <p class="meta">Enter the email address this claim link was sent to.</p>
      <label>Full Name</label>
      <input type="text" name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" placeholder="Only used when your account has no name yet">
      <label>Phone Number</label>
      <input type="tel" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>" placeholder="Only used when your account has no phone yet">
      <label>New Password</label>
      <input type="password" name="password" minlength="8" required>
      <label>Confirm Password</label>
      <input type="password" name="password_confirm" minlength="8" required>
      <p class="meta">Your password must be at least 8 characters.</p>
      <button type="submit">Claim Account</button>
    </form>
  </main>
</body>
</html>
