<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth-layout.php';

session_start();
$pdo = db();
if (!isset($_SESSION['otp_user_id'])) {
    redirect_to('otp-login.php');
}

function otp_login_destination(PDO $pdo, int $userId): string
{
    $fields = ['role'];
    if (app_column_exists($pdo, 'users', 'platform_role')) {
        $fields[] = 'platform_role';
    }
    if (app_column_exists($pdo, 'users', 'is_super_admin')) {
        $fields[] = 'is_super_admin';
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $fields) . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch() ?: [];
    $role = strtolower((string) ($user['role'] ?? ''));
    $platformRole = strtolower((string) ($user['platform_role'] ?? ''));

    if ((int) ($user['is_super_admin'] ?? 0) === 1 || in_array($platformRole, ['super_admin', 'admin', 'national_coordinator', 'state_coordinator'], true) || $role === 'admin') {
        return 'admin/index.php';
    }
    if (in_array($platformRole, ['field_agent', 'agronomist', 'agric_extensionist'], true) || $role === 'field_agent') {
        return 'field-agent/index.php';
    }
    if (in_array($platformRole, ['provider', 'input_provider', 'service_provider', 'seller'], true) || in_array($role, ['provider', 'seller'], true)) {
        return 'provider/dashboard.php';
    }
    if ($platformRole === 'learner' || $role === 'learner') {
        return 'academy/dashboard.php';
    }

    return 'dashboard/index.php';
}

$error = '';
$deliveryMessage = (string) ($_SESSION['otp_delivery_message'] ?? '');
$debugCode = !app_is_production() ? (string) ($_SESSION['otp_debug_code'] ?? '') : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('otp_verify', 10, 600)) {
        $error = 'Too many verification attempts. Please try again in 10 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } else {
        $digits = $_POST['otp_digits'] ?? [];
        $otp = is_array($digits)
            ? implode('', array_map(static fn($digit): string => preg_replace('/[^0-9]/', '', (string) $digit), $digits))
            : (string) ($_POST['otp'] ?? '');
        $stmt = $pdo->prepare("SELECT id FROM otp_sessions WHERE user_id = ? AND otp_code = ? AND expires_at > NOW() AND used = 0");
        $stmt->execute([$_SESSION['otp_user_id'], $otp]);

        if ($sessionId = $stmt->fetchColumn()) {
            $pdo->prepare("UPDATE otp_sessions SET used = 1 WHERE id = ?")->execute([$sessionId]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $_SESSION['otp_user_id'];
            $destination = otp_login_destination($pdo, (int) $_SESSION['otp_user_id']);
            unset($_SESSION['otp_user_id'], $_SESSION['otp_delivery_message'], $_SESSION['otp_debug_code']);
            redirect_to($destination);
        } else {
            $error = 'Invalid or expired OTP.';
        }
    }
}
?>
<?php auth_page_start('Verify Access Code'); ?>
  <form method="POST">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <span class="auth-eyebrow">NATCODEV identity check</span>
    <h1>Verify access code</h1>
    <p class="lead">Enter the 6-digit code sent to your registered contact. Your role determines the workspace you enter after verification.</p>
    <?php if ($deliveryMessage): ?><p class="success">OTP <?= e($deliveryMessage) ?>.</p><?php endif; ?>
    <?php if ($debugCode): ?><p class="error">Local test OTP: <?= e($debugCode) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <div class="otp-grid" aria-label="One time password">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <input name="otp_digits[]" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
      <?php endfor; ?>
    </div>
    <button type="submit">Verify and Continue</button>
    <div class="links"><span>Did not receive verification code?</span> <a href="otp-login.php">Resend</a></div>
  </form>
  <script>
    document.querySelectorAll('.otp-grid input').forEach((input, index, fields) => {
      input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '').slice(0, 1);
        if (input.value && fields[index + 1]) fields[index + 1].focus();
      });
      input.addEventListener('keydown', (event) => {
        if (event.key === 'Backspace' && !input.value && fields[index - 1]) fields[index - 1].focus();
      });
    });
  </script>
<?php auth_page_end(); ?>
