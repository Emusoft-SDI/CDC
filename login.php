<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/otp-delivery.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
app_ensure_core_schema($pdo);
app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(40) NOT NULL DEFAULT 'active'");
app_add_column_if_missing($pdo, 'users', 'email_verified_at', 'DATETIME NULL');

function platform_safe_next(string $next): string
{
    $next = trim(str_replace(["\0", '\\'], ['', '/'], $next));
    if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//') || str_starts_with($next, '/')) {
        return '';
    }
    while (str_starts_with($next, '../')) {
        $next = substr($next, 3);
    }
    return $next;
}

function platform_user_home(array $user, string $requestedNext = ''): string
{
    $safeNext = platform_safe_next($requestedNext);
    if ($safeNext !== '') {
        return $safeNext;
    }

    $role = strtolower((string) ($user['role'] ?? ''));
    $platformRole = strtolower((string) ($user['platform_role'] ?? ''));

    if ((int) ($user['is_super_admin'] ?? 0) === 1 || $platformRole === 'super_admin') {
        return 'super-admin/index.php';
    }
    if ($platformRole === 'support_agent') {
        return 'support/agent.php';
    }
    if (in_array($platformRole, ['national_coordinator', 'state_coordinator'], true)) {
        return 'coordination/';
    }
    if (in_array($platformRole, ['field_agent', 'agronomist', 'agric_extensionist', 'extensionist', 'farm_hand'], true) || $role === 'field_agent') {
        return 'field-agent/';
    }
    if (in_array($platformRole, ['seller', 'marketplace_seller'], true) || $role === 'seller') {
        return 'market/seller-central.php';
    }
    if ($platformRole === 'buyer' || $role === 'buyer') {
        return 'buyer/index.php';
    }
    if (in_array($platformRole, ['provider', 'input_provider', 'service_provider'], true) || $role === 'provider') {
        return 'provider/dashboard.php';
    }
    if ($platformRole === 'learner' || $role === 'learner') {
        return 'academy/dashboard.php';
    }
    if ($role === 'admin' || $platformRole === 'admin') {
        return 'admin/index.php';
    }

    return 'dashboard/index.php';
}

function platform_password_matches(PDO $pdo, array $user, string $password): bool
{
    $stored = (string) ($user['password'] ?? '');
    if ($stored !== '' && password_verify($password, $stored)) {
        return true;
    }

    $legacyMatch = false;
    if ($stored !== '' && hash_equals($stored, $password)) {
        $legacyMatch = true;
    } elseif (preg_match('/^[a-f0-9]{32}$/i', $stored) && hash_equals(strtolower($stored), md5($password))) {
        $legacyMatch = true;
    } elseif (preg_match('/^[a-f0-9]{40}$/i', $stored) && hash_equals(strtolower($stored), sha1($password))) {
        $legacyMatch = true;
    }

    if ($legacyMatch) {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        return true;
    }

    return false;
}

$error = '';
$logoutNotice = isset($_GET['message']) ? 'You have logged out safely. Sign in again or choose a public area below.' : '';
$accountHelp = null;
$lastEmail = strtolower(trim((string) ($_GET['email'] ?? '')));
$next = platform_safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? ''));

if ($current = current_user($pdo)) {
    redirect_to(platform_user_home($current, $next));
}

if (isset($_GET['social'])) {
    $driver = strtolower((string) $_GET['social']);
    if (app_social_login_enabled($driver)) {
        app_oauth_begin($driver, 'login', $next);
    }
    $error = ucfirst($driver) . ' login is disabled by the operator.';
}

if (isset($_GET['oauth_callback'])) {
    try {
        $oauth = app_oauth_finish($pdo, (string) $_GET['oauth_callback'], 'login');
        $stmt = $pdo->prepare('SELECT id, name, email, role, platform_role, account_status, email_verified_at, is_super_admin FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $oauth['user_id']]);
        $oauthUser = $stmt->fetch() ?: null;
        unset($_SESSION['user_id']);
        if (!$oauthUser) {
            $error = 'OAuth login succeeded, but the account could not be loaded.';
        } elseif (app_user_needs_email_verification($oauthUser)) {
            $error = 'Confirm this email before logging in.';
            $accountHelp = ['url' => 'resend-confirmation.php?email=' . urlencode((string) $oauthUser['email']), 'label' => 'Resend confirmation email'];
        } else {
            $otpStart = otp_begin_email_login_challenge($pdo, $oauthUser, platform_user_home($oauthUser, $next));
            if ($otpStart['ok']) {
                redirect_to('verify-otp.php');
            }
            $error = (string) $otpStart['message'];
        }
    } catch (Throwable $e) {
        error_log('Platform OAuth login error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}

if (isset($_GET['oauth_error'])) {
    $error = app_oauth_missing_credentials_message((string) ($_GET['provider'] ?? 'google'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');
        if ($email) {
            $lastEmail = strtolower((string) $email);
        }
        if (!$email || $password === '') {
            $error = 'Enter your email and password.';
        } else {
            $rateAction = 'platform_login_' . sha1(strtolower((string) $email));
            if (!app_check_rate_limit($rateAction, 10, 600)) {
                $error = 'Too many login attempts for this email. Please try again in 10 minutes or reset the password.';
                $accountHelp = ['url' => 'forgot-password.php?email=' . urlencode((string) $email), 'label' => 'Reset password'];
            } else {
                try {
                    $stmt = $pdo->prepare('SELECT id, name, email, password, role, platform_role, account_status, email_verified_at, is_super_admin FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch() ?: null;
                    if ($user && platform_password_matches($pdo, $user, $password)) {
                        $status = strtolower((string) ($user['account_status'] ?? 'active'));
                        if (app_user_needs_email_verification($user)) {
                            $error = 'Your account needs email confirmation before login.';
                            $accountHelp = ['url' => 'resend-confirmation.php?email=' . urlencode((string) $email), 'label' => 'Resend confirmation email'];
                        } elseif (!in_array($status, ['active', 'verified'], true)) {
                            $error = 'This account is not active. Contact support or reset your password if you think this is wrong.';
                            $accountHelp = ['url' => 'forgot-password.php?email=' . urlencode((string) $email), 'label' => 'Reset password'];
                        } else {
                            try {
                                if (app_table_exists($pdo, 'app_rate_limits')) {
                                    $limitKey = $rateAction . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                                    $pdo->prepare('DELETE FROM app_rate_limits WHERE limit_key = ?')->execute([$limitKey]);
                                }
                            } catch (Throwable $e) {
                                error_log('Platform login rate-limit cleanup skipped: ' . $e->getMessage());
                            }
                            $destination = platform_user_home($user, $next);
                            $otpStart = otp_begin_email_login_challenge($pdo, $user, $destination);
                            if ($otpStart['ok']) {
                                redirect_to('verify-otp.php');
                            }
                            $error = (string) $otpStart['message'];
                        }
                    } elseif ($user) {
                        $error = 'Password does not match this account.';
                        $accountHelp = ['url' => 'forgot-password.php?email=' . urlencode((string) $email), 'label' => 'Reset password'];
                    } else {
                        $error = 'No account was found for this email.';
                        $accountHelp = ['url' => 'apply.php', 'label' => 'Start registration'];
                    }
                } catch (Throwable $e) {
                    error_log('Platform login error: ' . $e->getMessage());
                    $error = 'Unable to login right now.';
                }
            }
        }
    }
}

$logo = app_primary_logo_url();
$googleEnabled = app_social_login_enabled('google');
$facebookEnabled = app_social_login_enabled('facebook');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f4faf2;color:#101828;font-family:"Segoe UI",Arial,sans-serif;display:grid;place-items:center;padding:24px}.card{width:min(480px,100%);background:#fff;border:1px solid #dfe8d8;border-radius:10px;box-shadow:0 22px 64px rgba(16,24,40,.12);padding:30px}.brand{display:flex;gap:12px;align-items:center;color:#075f2a;text-decoration:none;margin-bottom:22px}.brand img{width:60px;height:60px;border-radius:50%;object-fit:contain}.brand strong{font-size:1.45rem}.brand small{color:#667085}h1{margin:0 0 8px;color:#082f19}.lead{color:#667085;margin-top:0;line-height:1.5}.alert{padding:12px;border-radius:8px;margin:12px 0;font-weight:850}.err{background:#fff1f2;color:#b42318;border:1px solid #ffd5d9}.ok{background:#ecfdf3;color:#067647;border:1px solid #abefc6}.help{background:#f8fcf7;border:1px solid #dfe8d8}.social{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:12px 0}.social a{display:flex;gap:8px;align-items:center;justify-content:center;border:1px solid #dfe8d8;border-radius:8px;padding:11px;color:#075f2a;font-weight:900;text-decoration:none}label{display:block;font-weight:900;margin-top:14px}input{width:100%;border:1px solid #dfe8d8;border-radius:8px;padding:13px;margin-top:6px;font:inherit}.pass{position:relative}.pass input{padding-right:78px}.toggle{position:absolute;right:8px;bottom:7px;border:0;border-radius:7px;background:#eef8ef;color:#075f2a;padding:8px 10px;font-weight:900;cursor:pointer}.btn{width:100%;border:0;border-radius:8px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-size:1rem;font-weight:950;cursor:pointer}.links{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px}.links a,.help a{color:#075f2a;font-weight:900;text-decoration:none}.fine{font-size:.9rem;color:#667085;line-height:1.45;margin-top:14px}@media(max-width:520px){body{padding:14px}.card{padding:22px}.social{grid-template-columns:1fr}}
  </style>
</head>
<body>
<main class="card">
  <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><br><small>Secure platform login</small></span></a>
  <h1>Platform Login</h1>
  <p class="lead">Sign in with your registered NATCODEV account. Your verified role routes you to the right workspace.</p>
  <?php if ($logoutNotice): ?><div class="alert ok"><?= e($logoutNotice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($accountHelp): ?><div class="alert help"><a href="<?= e($accountHelp['url']) ?>"><?= e($accountHelp['label']) ?></a></div><?php endif; ?>
  <?php if ($googleEnabled || $facebookEnabled): ?>
    <div class="social">
      <?php if ($googleEnabled): ?><a href="login.php?social=google<?= $next !== '' ? '&next=' . urlencode($next) : '' ?>"><i class="fab fa-google"></i> Google</a><?php endif; ?>
      <?php if ($facebookEnabled): ?><a href="login.php?social=facebook<?= $next !== '' ? '&next=' . urlencode($next) : '' ?>"><i class="fab fa-facebook"></i> Facebook</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" autocomplete="on">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label>Email<input type="email" name="email" value="<?= e($lastEmail) ?>" autocomplete="email" required></label>
    <label>Password</label>
    <div class="pass"><input id="password" type="password" name="password" autocomplete="current-password" required><button class="toggle" type="button" data-toggle-password="password" aria-pressed="false">Show</button></div>
    <button class="btn" type="submit">Login</button>
  </form>
  <div class="links"><a href="forgot-password.php<?= $lastEmail !== '' ? '?email=' . urlencode($lastEmail) : '' ?>">Forgot password?</a><a href="apply.php">Registration</a></div>
  <div class="links"><a href="index.php">NATCODEV Home</a><a href="market/index.php">Marketplace</a><a href="academy/index.php">Academy</a><a href="support/index.php">Support</a></div>
  <p class="fine">Unconfirmed accounts cannot enter. After password verification, NATCODEV sends an OTP to the confirmed email before workspace access is granted.</p>
</main>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';button.setAttribute('aria-pressed',show?'true':'false');});});
</script>
</body>
</html>