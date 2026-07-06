<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
app_ensure_core_schema($pdo);
academy_ensure_schema($pdo);
app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(40) NOT NULL DEFAULT 'active'");
app_add_column_if_missing($pdo, 'users', 'email_verified_at', 'DATETIME NULL');

$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course = null;
if ($courseId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM webinars WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch() ?: null;
}

$dashboardTarget = $course ? 'dashboard.php?screen=course&course_id=' . $courseId : 'dashboard.php?screen=learning';
$otpDashboardTarget = $course ? 'academy/dashboard.php?screen=course&course_id=' . $courseId : 'academy/dashboard.php?screen=learning';
$oauthNext = $course ? 'academy/dashboard.php?screen=course&course_id=' . $courseId : 'academy/dashboard.php?screen=learning';

if (current_user($pdo)) {
    redirect_to($dashboardTarget);
}

$error = '';
$logoutNotice = isset($_GET['message']) ? 'You have logged out of NATCODEV Academy. Login again or choose a public area.' : '';
$accountHelp = null;
$lastEmail = '';

function academy_password_matches(PDO $pdo, array $user, string $password): bool
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
        $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        return true;
    }

    return false;
}

if (isset($_GET['social'])) {
    app_oauth_begin((string) $_GET['social'], 'learner', $oauthNext);
}
if (isset($_GET['oauth_callback'])) {
    try {
        $oauth = app_oauth_finish($pdo, (string) $_GET['oauth_callback'], 'learner');
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $oauth['user_id']]);
        $oauthUser = $stmt->fetch() ?: ['id' => (int) $oauth['user_id'], 'email' => ''];
        unset($_SESSION['user_id']);
        $otpStart = otp_begin_email_login_challenge($pdo, $oauthUser, $otpDashboardTarget);
        if ($otpStart['ok']) {
            redirect_to('../verify-otp.php');
        }
        $error = (string) $otpStart['message'];
    } catch (Throwable $e) {
        error_log('Academy login OAuth error: ' . $e->getMessage());
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
            $lastEmail = (string) $email;
        }
        if (!$email || $password === '') {
            $error = 'Enter your email and password.';
        } else {
            $rateAction = 'academy_login_' . sha1(strtolower((string) $email));
            if (!app_check_rate_limit($rateAction, 10, 600)) {
                $error = 'Too many login attempts for this email. Please try again in 10 minutes or reset the password.';
                $accountHelp = [
                    'url' => '../forgot-password.php?next=academy/login.php&email=' . urlencode((string) $email),
                    'label' => 'Reset password',
                ];
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id, email, password, account_status, email_verified_at FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch() ?: null;
                    if ($user && academy_password_matches($pdo, $user, $password)) {
                        $status = strtolower((string) ($user['account_status'] ?? 'active'));
                        if (app_user_needs_email_verification($user)) {
                            $error = 'Your account needs confirmation before Academy access.';
                            $accountHelp = [
                                'url' => '../resend-confirmation.php?email=' . urlencode((string) $email),
                                'label' => 'Resend confirmation email',
                            ];
                        } else {
                            try {
                                if (app_table_exists($pdo, 'app_rate_limits')) {
                                    $limitKey = $rateAction . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                                    $pdo->prepare('DELETE FROM app_rate_limits WHERE limit_key = ?')->execute([$limitKey]);
                                }
                            } catch (Throwable $e) {
                                error_log('Academy login rate-limit cleanup skipped: ' . $e->getMessage());
                            }
                            $otpStart = otp_begin_email_login_challenge($pdo, $user, $otpDashboardTarget);
                            if ($otpStart['ok']) {
                                redirect_to('../verify-otp.php');
                            }
                            $error = (string) $otpStart['message'];
                        }
                    } elseif ($user) {
                        $error = 'Password does not match this Academy account.';
                        $accountHelp = [
                            'url' => '../forgot-password.php?next=academy/login.php&email=' . urlencode((string) $email),
                            'label' => 'Reset password',
                        ];
                    } else {
                        $error = 'No Academy account was found for this email.';
                        $accountHelp = [
                            'url' => 'register.php' . ($course ? '?course_id=' . $courseId : ''),
                            'label' => 'Create learner account',
                        ];
                    }
                } catch (Throwable $e) {
                    error_log('Academy login error: ' . $e->getMessage());
                    $error = 'Unable to login right now.';
                }
            }
        }
    }
}

$logo = app_primary_logo_url();
$registerUrl = 'register.php' . ($course ? '?course_id=' . $courseId : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Academy Login - NATCODEV Academy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#f4faf2;color:#101828}.auth{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) 500px}.visual{background:linear-gradient(90deg,rgba(5,45,20,.78),rgba(5,45,20,.2)),url("../assets/academy/natcodev-academy-public-hero.png") center/cover;color:#fff;padding:50px;display:flex;align-items:flex-end}.visual h1{font-size:clamp(2.5rem,5vw,4.8rem);line-height:.96;margin:0 0 14px}.visual p{font-size:1.18rem;max-width:760px;line-height:1.55}.panel{background:#fff;padding:42px;display:grid;align-content:center}.brand{display:flex;gap:12px;align-items:center;margin-bottom:24px;color:#06451f;text-decoration:none}.brand img{width:58px;height:58px;border-radius:50%}.brand strong{font-size:1.55rem}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:8px;padding:13px;margin-top:6px}.pass{position:relative}.pass input{padding-right:74px}.pass button{position:absolute;right:8px;bottom:7px;border:0;border-radius:7px;background:#eef8ef;color:#06451f;padding:8px 10px;font-weight:900;cursor:pointer}.btn{width:100%;border:0;border-radius:8px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950;font-size:1rem;cursor:pointer}.alert{padding:12px;border-radius:8px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.ok{background:#ecfdf3;color:#067647}.course{border:1px solid #dfe8d8;border-radius:8px;background:#f8fcf7;padding:13px;margin:12px 0}.links{display:flex;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap}a{color:#06451f;font-weight:900;text-decoration:none}.fineprint{font-size:.88rem;color:#667085;line-height:1.45}.help{border:1px solid #dfe8d8;border-radius:8px;padding:12px;margin-top:10px;background:#f8fcf7}.social-wrap{margin:12px 0}@media(max-width:900px){.auth{grid-template-columns:1fr}.visual{min-height:320px}.panel{padding:24px}}
  </style>
</head>
<body>
<main class="auth">
  <section class="visual"><div><h1>Continue learning.</h1><p>Login to your NATCODEV Academy dashboard for courses, payments, progress, certificates, and accreditation pathways.</p></div></section>
  <section class="panel">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><strong>NATCODEV Academy</strong></a>
    <h2>Academy learner login</h2>
    <p>Use the same NATCODEV account you used for Academy registration.</p>
    <?php if ($course): ?><div class="course"><strong>Selected course:</strong><br><?= e((string) $course['title']) ?></div><?php endif; ?>
    <?php if ($logoutNotice): ?><div class="alert ok"><?= e($logoutNotice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($accountHelp): ?><div class="help"><a href="<?= e((string) $accountHelp['url']) ?>"><?= e((string) $accountHelp['label']) ?></a></div><?php endif; ?>
    <div class="social-wrap"><?= app_social_buttons('learner', $oauthNext) ?></div>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <label>Email<input type="email" name="email" value="<?= e($lastEmail) ?>" required></label>
      <label class="pass">Password<input id="academy-login-password" type="password" name="password" required><button type="button" data-toggle-password="academy-login-password">Show</button></label>
      <button class="btn" type="submit">Login to Academy</button>
    </form>
    <p class="fineprint">This does not create a separate Academy-only identity. It keeps one NATCODEV account while giving learners a focused Academy entry point.</p>
    <div class="links"><a href="<?= e($registerUrl) ?>">Create learner account</a><a href="../forgot-password.php<?= $lastEmail ? '?email=' . e(urlencode($lastEmail)) . '&next=academy/login.php' : '?next=academy/login.php' ?>">Forgot password?</a><a href="index.php">Back to Academy</a></div>
    <div class="links"><a href="../index.php">NATCODEV Home</a><a href="../market/index.php">Marketplace</a><a href="../support/index.php">Support</a></div>
  </section>
</main>
<script>document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));if(!input)return;var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';});});</script>
</body>
</html>


