<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

$pdo = provider_boot();
app_ensure_user_verification_schema($pdo);
$error = '';
$logoutNotice = isset($_GET['message']) ? 'You have logged out of the provider console. Login again or return to public areas.' : '';
$notice = '';
$notice = '';

function provider_oauth_config(string $driver): ?array
{
    $configs = [
        'google' => [
            'label' => 'Google',
            'client_id' => (string) app_env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => (string) app_env('GOOGLE_CLIENT_SECRET', ''),
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope' => 'openid email profile',
        ],
        'facebook' => [
            'label' => 'Facebook',
            'client_id' => (string) app_env('FACEBOOK_CLIENT_ID', ''),
            'client_secret' => (string) app_env('FACEBOOK_CLIENT_SECRET', ''),
            'auth_url' => 'https://www.facebook.com/v20.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v20.0/oauth/access_token',
            'userinfo_url' => 'https://graph.facebook.com/me?fields=id,name,email',
            'scope' => 'email,public_profile',
        ],
    ];

    return $configs[$driver] ?? null;
}

function provider_oauth_is_configured(string $driver): bool
{
    $config = provider_oauth_config($driver);
    return $config !== null && $config['client_id'] !== '' && $config['client_secret'] !== '';
}

function provider_oauth_redirect_uri(string $driver): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/provider/login.php'));
    return $scheme . '://' . $host . $script . '?oauth_callback=' . rawurlencode($driver);
}

function provider_oauth_http(string $url, array $post = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status >= 400) {
            throw new RuntimeException($error ?: 'OAuth provider request failed.');
        }
    } else {
        $context = null;
        if ($post) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                    'content' => http_build_query($post),
                    'timeout' => 20,
                ],
            ]);
        }
        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('OAuth provider request failed.');
        }
    }

    $json = json_decode((string) $body, true);
    if (!is_array($json)) {
        throw new RuntimeException('OAuth provider returned an invalid response.');
    }
    return $json;
}

function provider_oauth_begin(string $driver): void
{
    $config = provider_oauth_config($driver);
    if (!app_social_login_enabled($driver)) {
        redirect_to('login.php?oauth_error=disabled&provider=' . rawurlencode($driver));
    }
    if (!$config || !provider_oauth_is_configured($driver)) {
        redirect_to('login.php?oauth_error=missing_credentials&provider=' . rawurlencode($driver));
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $state = bin2hex(random_bytes(24));
    $_SESSION['provider_oauth_state'] = [
        'driver' => $driver,
        'state' => $state,
        'created' => time(),
    ];

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => provider_oauth_redirect_uri($driver),
        'response_type' => 'code',
        'scope' => $config['scope'],
        'state' => $state,
    ];
    if ($driver === 'google') {
        $params['access_type'] = 'online';
        $params['prompt'] = 'select_account';
    }

    redirect_to($config['auth_url'] . '?' . http_build_query($params));
}

function provider_oauth_userinfo(string $driver, string $code): array
{
    $config = provider_oauth_config($driver);
    if (!$config) {
        throw new RuntimeException('Unsupported social provider.');
    }

    $token = provider_oauth_http($config['token_url'], [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => provider_oauth_redirect_uri($driver),
        'code' => $code,
        'grant_type' => 'authorization_code',
    ]);
    $accessToken = (string) ($token['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('OAuth provider did not return an access token.');
    }

    $userinfoUrl = $config['userinfo_url'];
    if ($driver === 'facebook') {
        $userinfoUrl .= '&access_token=' . rawurlencode($accessToken);
        return provider_oauth_http($userinfoUrl);
    }

    return provider_oauth_http($userinfoUrl . '?' . http_build_query(['access_token' => $accessToken]));
}

function provider_oauth_login(PDO $pdo, string $driver, array $profile): int
{
    $email = filter_var((string) ($profile['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new RuntimeException('Your social account did not share an email address.');
    }

    $name = trim((string) ($profile['name'] ?? ''));
    if ($name === '') {
        $name = strstr((string) $email, '@', true) ?: 'Provider';
    }
    $providerId = (string) ($profile['sub'] ?? $profile['id'] ?? '');

    foreach ([
        'oauth_provider' => 'VARCHAR(30) NULL',
        'oauth_provider_id' => 'VARCHAR(191) NULL',
        'email_verified_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([(string) $email]);
    $userId = (int) ($stmt->fetchColumn() ?: 0);

    if ($userId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET name = COALESCE(NULLIF(name, ''), ?), role = 'provider', platform_role = 'provider', account_status = 'active', oauth_provider = ?, oauth_provider_id = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?");
        $stmt->execute([$name, $driver, $providerId, $userId]);
        return $userId;
    }

    $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, platform_role, account_status, oauth_provider, oauth_provider_id, email_verified_at) VALUES (?, ?, ?, '', 'provider', 'provider', 'active', ?, ?, NOW())");
    $stmt->execute([$name, (string) $email, $password, $driver, $providerId]);
    return (int) $pdo->lastInsertId();
}

function provider_oauth_finish(PDO $pdo, string $driver): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $saved = $_SESSION['provider_oauth_state'] ?? [];
    unset($_SESSION['provider_oauth_state']);

    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    if (!is_array($saved) || ($saved['driver'] ?? '') !== $driver || !hash_equals((string) ($saved['state'] ?? ''), $state) || (time() - (int) ($saved['created'] ?? 0)) > 600) {
        throw new RuntimeException('Social login session expired. Please try again.');
    }
    if ($code === '') {
        throw new RuntimeException('Social login was cancelled or did not return an authorization code.');
    }

    $profile = provider_oauth_userinfo($driver, $code);
    $userId = provider_oauth_login($pdo, $driver, $profile);
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $oauthUser = $stmt->fetch() ?: ['id' => $userId, 'email' => ''];
    $otpStart = otp_begin_email_login_challenge($pdo, $oauthUser, 'provider/dashboard.php');
    if (!$otpStart['ok']) {
        throw new RuntimeException((string) $otpStart['message']);
    }
    redirect_to('../verify-otp.php');
}

if (isset($_GET['social'])) {
    provider_oauth_begin((string) $_GET['social']);
}

if (isset($_GET['oauth_callback'])) {
    try {
        provider_oauth_finish($pdo, (string) $_GET['oauth_callback']);
    } catch (Throwable $e) {
        error_log('Provider OAuth error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}

if (isset($_GET['oauth_error'])) {
    $driver = (string) ($_GET['provider'] ?? 'social');
    $label = ucfirst($driver);
    if ((string) ($_GET['oauth_error'] ?? '') === 'disabled') {
        $error = $label . ' login is currently disabled by platform operators.';
    } else {
        $error = $label . ' login is not configured yet. Add ' . strtoupper($driver) . '_CLIENT_ID and ' . strtoupper($driver) . '_CLIENT_SECRET in .env, then set the redirect URI to ' . provider_oauth_redirect_uri($driver) . '.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('provider_login', 10, 600)) {
        $error = 'Too many login attempts. Please try again in 10 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');

        if ($email && $password !== '') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([(string) $email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, (string) $user['password'])) {
                if (app_user_needs_email_verification($user)) {
                    $error = 'Your provider account needs email verification before login.';
                } else {
                    $otpStart = otp_begin_email_login_challenge($pdo, $user, 'provider/dashboard.php');
                    if ($otpStart['ok']) {
                        redirect_to('../verify-otp.php');
                    }
                    $error = (string) $otpStart['message'];
                }
            }
        }

        if ($error === '') {
            $error = 'Invalid provider login details.';
        }
    }
}

$providerSocialButtons = [];
if (app_social_login_enabled('google') && provider_oauth_is_configured('google')) {
    $providerSocialButtons[] = '<a href="?social=google"><i class="fab fa-google"></i> Google</a>';
}
if (app_social_login_enabled('facebook') && provider_oauth_is_configured('facebook')) {
    $providerSocialButtons[] = '<a href="?social=facebook"><i class="fab fa-facebook"></i> Facebook</a>';
}
if ($notice === '' && $providerSocialButtons) {
    $notice = 'Social provider login is active. Use Google or Facebook to continue.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Login - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:"Segoe UI",Arial,sans-serif;background:linear-gradient(90deg,rgba(5,45,20,.82),rgba(5,45,20,.22)),url("../assets/public/provider-commerce-hero.png") center/cover}.panel{width:min(480px,92vw);background:#fff;border-radius:18px;padding:34px;box-shadow:0 26px 70px rgba(0,0,0,.25)}.brand{display:flex;gap:12px;align-items:center;text-decoration:none;color:#06451f}.brand img{width:58px;height:58px;border-radius:50%}.brand strong{font-size:1.5rem}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:9px;padding:13px;margin-top:6px}.btn{width:100%;border:0;border-radius:10px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950}.social{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.social a{border:1px solid #dfe8d8;border-radius:10px;padding:12px;text-align:center;color:#06451f;font-weight:900;text-decoration:none}.alert{padding:12px;border-radius:10px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.ok{background:#e8f6ec;color:#06451f}.links{display:flex;justify-content:space-between;margin-top:16px}a{color:#06451f;font-weight:900;text-decoration:none}.pass{position:relative}.pass button{position:absolute;right:9px;top:34px;border:0;background:#eef8ef;color:#06451f;border-radius:8px;padding:8px 10px;font-weight:900;cursor:pointer}
  </style>
</head>
<body>
<section class="panel">
  <a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt=""><strong>NATCODEV Provider</strong></a>
  <h1>Provider Sign In</h1>
  <p>Manage accreditation, listings, orders, Academy training, wallet, and support.</p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($logoutNotice): ?><div class="alert ok"><?= e($logoutNotice) ?></div><?php endif; ?><?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>
  <?php if ($providerSocialButtons): ?><div class="social"><?= implode('', $providerSocialButtons) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Email<input type="email" name="email" required></label>
    <label class="pass">Password<input id="provider-login-password" type="password" name="password" required><button type="button" data-toggle-password="provider-login-password">Show</button></label>
    <button class="btn">Sign In</button>
  </form>
  <div class="links"><a href="index.php">Provider Registration</a><a href="../market/index.php">Marketplace</a><a href="../index.php">NATCODEV Home</a><a href="../support/index.php">Support</a></div>
</section>
<script>document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var visible=input.type==='text';input.type=visible?'password':'text';button.textContent=visible?'Show':'Hide';});});</script>
</body>
</html>