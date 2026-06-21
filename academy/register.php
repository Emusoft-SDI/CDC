<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
app_ensure_core_schema($pdo);
academy_ensure_schema($pdo);
app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(40) NOT NULL DEFAULT 'active'");

$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course = null;
if ($courseId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM webinars WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch() ?: null;
}

if (current_user($pdo)) {
    redirect_to($course ? 'dashboard.php?screen=course&course_id=' . $courseId : 'dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('learner_registration', 5, 3600)) {
        $error = 'Too many registration attempts. Please try again in an hour.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !$email || strlen($password) < 6) {
            $error = 'Enter your name, a valid email, and a password of at least 6 characters.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, platform_role, account_status) VALUES (?, ?, ?, ?, 'grower', 'learner', 'active')");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
                $userId = (int) $pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                redirect_to($course ? 'dashboard.php?screen=course&course_id=' . $courseId : 'dashboard.php?screen=catalog');
            } catch (Throwable $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'That email already exists. Please login instead.' : 'Unable to create learner account now.';
            }
        }
    }
}

$logo = app_primary_logo_url();
$loginNext = '../academy/dashboard.php' . ($course ? '?screen=course&course_id=' . $courseId : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register as Learner - NATCODEV Academy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#f4faf2;color:#101828}.auth{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) 500px}.visual{background:linear-gradient(90deg,rgba(5,45,20,.76),rgba(5,45,20,.18)),url("../assets/academy/natcodev-academy-public-hero.png") center/cover;color:#fff;padding:50px;display:flex;align-items:flex-end}.visual h1{font-size:clamp(2.5rem,5vw,4.8rem);line-height:.96;margin:0 0 14px}.visual p{font-size:1.18rem;max-width:760px;line-height:1.55}.panel{background:#fff;padding:42px;display:grid;align-content:center}.brand{display:flex;gap:12px;align-items:center;margin-bottom:24px;color:#06451f;text-decoration:none}.brand img{width:58px;height:58px;border-radius:50%}.brand strong{font-size:1.55rem}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:8px;padding:13px;margin-top:6px}.btn{width:100%;border:0;border-radius:8px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950;font-size:1rem;cursor:pointer}.alert{padding:12px;border-radius:8px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.course{border:1px solid #dfe8d8;border-radius:8px;background:#f8fcf7;padding:13px;margin:12px 0}.links{display:flex;justify-content:space-between;gap:12px;margin-top:16px}a{color:#06451f;font-weight:900;text-decoration:none}.fineprint{font-size:.88rem;color:#667085;line-height:1.45}@media(max-width:900px){.auth{grid-template-columns:1fr}.visual{min-height:360px}.panel{padding:24px}}
  </style>
</head>
<body>
<main class="auth">
  <section class="visual"><div><h1>Start as a learner.</h1><p>Academy access is open. Other platform roles are requested later and approved by NATCODEV platform admins.</p></div></section>
  <section class="panel">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><strong>NATCODEV Academy</strong></a>
    <h2>Create learner account</h2>
    <p>Use this account for courses, progress, payments, certificates, and role requests.</p>
    <?php if ($course): ?><div class="course"><strong>Selected course:</strong><br><?= e((string) $course['title']) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <label>Name<input name="name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Phone<input name="phone" placeholder="+234..."></label>
      <label>Password<input type="password" name="password" minlength="6" required></label>
      <button class="btn">Register as Learner</button>
    </form>
    <p class="fineprint">Learner registration does not grant seller, provider, grower, field, coordinator, or admin privileges. Those are requested from inside your account and reviewed before approval.</p>
    <div class="links"><a href="../login.php?next=<?= e(urlencode(ltrim(str_replace('../', '', $loginNext), '/'))) ?>">Already registered?</a><a href="index.php">Back to Academy</a></div>
  </section>
</main>
</body>
</html>
