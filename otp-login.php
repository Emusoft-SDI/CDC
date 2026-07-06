<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth-layout.php';
require_once __DIR__ . '/lib/otp-delivery.php';

session_start();
$pdo = db();
$error = '';
$message = '';
$stakeholderAccess = [
    'Registered growers and farm owners',
    'Providers, sellers, processors, and partners',
    'Field agents, agronomists, and extension officers',
    'State and national coordinators',
    'Academy learners with NATCODEV accounts',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('otp_request', 3, 600)) {
        $error = 'Too many OTP requests. Please try again in 10 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/[^0-9]/', '', (string) ($_POST['phone'] ?? ''));
        $phoneColumn = app_column_exists($pdo, 'users', 'phone') ? 'phone' : 'email';
        $stmt = $pdo->prepare("SELECT id, email, {$phoneColumn} AS phone FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && ($phoneColumn === 'email' || $phone === preg_replace('/[^0-9]/', '', (string) $user['phone']))) {
            otp_ensure_schema($pdo);
            $otp = (string) random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $delivery = otp_send_code($pdo, (int) $user['id'], $otp, 'login', $phone, (string) ($user['email'] ?? $email));
            if (!$delivery['ok']) {
                $error = 'OTP could not be delivered. ' . implode(' ', $delivery['errors'] ?: ['Check WhatsApp/SMS settings and try again.']);
            } else {
                $pdo->prepare("INSERT INTO otp_sessions (user_id, otp_code, expires_at, purpose) VALUES (?, ?, ?, 'login')")->execute([$user['id'], $otp, $expires]);
                $_SESSION['otp_user_id'] = $user['id'];
                $_SESSION['otp_verified'] = false;
                $_SESSION['otp_delivery_message'] = otp_delivery_message($delivery);
                if (!app_is_production()) {
                    $_SESSION['otp_debug_code'] = $otp;
                }
                redirect_to('verify-otp.php');
            }
        } else {
            $error = 'Invalid email or phone number.';
        }
    }
}
?>
<?php auth_page_start('NATCODEV Access Code'); ?>
  <form method="POST" class="otp-entry-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <span class="auth-eyebrow">NATCODEV secure sign-in</span>
    <h1>Request an access code</h1>
    <p class="lead">Enter the email and phone number on your NATCODEV account. Workspace access opens only after the code is verified.</p>
    <?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <label>Email<input type="email" name="email" placeholder="you@example.com" required></label>
    <label>Phone Number<input type="tel" name="phone" placeholder="080..." required></label>
    <button type="submit">Send Access Code</button>
    <div class="stakeholder-note">
      <strong>Who uses this sign-in?</strong>
      <ul>
        <?php foreach ($stakeholderAccess as $access): ?><li><?= e($access) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="links"><a href="login.php">Use password instead</a></div>
  </form>
<?php auth_page_end(); ?>
