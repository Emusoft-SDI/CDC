<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth-layout.php';

session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');

        if ($email && $password !== '') {
            try {
                $pdo = db();
                app_ensure_core_schema($pdo);
                $stmt = $pdo->prepare("SELECT id, password, role, application_id, is_agronomist, is_extensionist FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, (string) $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $isStaffUser = ($user['role'] ?? '') === 'field_agent'
                        && (
                            empty($user['application_id'])
                            || (int) ($user['is_agronomist'] ?? 0) === 1
                            || (int) ($user['is_extensionist'] ?? 0) === 1
                        );
                    if ($isStaffUser) {
                        redirect_to('../field-agent/');
                    }
                    if (($user['role'] ?? '') === 'admin') {
                        redirect_to('../admin/admin.php');
                    }
                    redirect_to('index.php');
                }
            } catch (Throwable $e) {
                error_log('Dashboard login error: ' . $e->getMessage());
            }
        }

        $error = 'Invalid credentials.';
    }
}
?>
<?php auth_page_start('Grower Dashboard Login', ''); ?>
  <form method="post">
    <h1>NATCODEV Login</h1>
    <p class="lead">Growers, field agents, agronomists, Agric Extensionists, and admins can sign in here. Staff are redirected to their operations area after login.</p>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="email" name="email" placeholder="Email" required autocomplete="email">
    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
    <button type="submit">Login</button>
    <div class="links">
      <a href="../index.php">Back to home</a>
      <a href="forgot-password.php">Forgot password?</a>
    </div>
  </form>
<?php auth_page_end(); ?>
