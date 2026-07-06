<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
academy_ensure_schema($pdo);
$user = current_user($pdo);
if (!$user) {
    redirect_to('../login.php?next=' . rawurlencode('academy/request-role.php'));
}

$roles = [
    'grower' => 'Grower',
    'buyer' => 'Buyer',
    'seller' => 'Marketplace Seller',
    'provider' => 'Provider',
    'input_provider' => 'Input Provider',
    'service_provider' => 'Service Provider',
    'farm_hand' => 'Farm Hand',
    'field_agent' => 'Field Agent',
    'agronomist' => 'Agronomist',
    'agric_extensionist' => 'Agric Extensionist',
    'investor' => 'Investor',
];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $requestedRole = (string) ($_POST['requested_role'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (!isset($roles[$requestedRole])) {
            $error = 'Select the role you want to request.';
        } elseif ($reason === '') {
            $error = 'Tell the platform admin why you need this role.';
        } else {
            $ticket = 'ACAD-ROLE-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $body = "Academy learner role request\n\nRequested role: " . $roles[$requestedRole] . "\nCurrent role: " . academy_role_label((string) ($user['platform_role'] ?? $user['role'] ?? 'learner')) . "\n\nReason:\n" . $reason;
            $stmt = $pdo->prepare("
                INSERT INTO messages (user_id, admin_id, message, is_from_admin, ticket_id, category, priority, status)
                VALUES (?, 1, ?, 0, ?, 'account_upgrade', 'medium', 'open')
            ");
            $stmt->execute([(int) $user['id'], $body, $ticket]);
            $message = 'Role request submitted. A platform admin will review it before access is granted.';
        }
    }
}

$logo = app_primary_logo_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Request Platform Role - NATCODEV Academy</title>
  <style>
    *{box-sizing:border-box}body{margin:0;background:#f6faf3;color:#101828;font-family:"Segoe UI",Arial,sans-serif}.top{background:#fff;border-bottom:1px solid #dfe8d8;padding:14px 24px;display:flex;justify-content:space-between;gap:16px;align-items:center}.brand{display:flex;gap:12px;align-items:center;color:#06451f;text-decoration:none;font-weight:950}.brand img{width:52px;height:52px;border-radius:50%}.wrap{max-width:980px;margin:0 auto;padding:28px 20px}.panel{background:#fff;border:1px solid #dfe8d8;border-radius:8px;padding:22px;box-shadow:0 18px 45px rgba(16,24,40,.08)}label{display:block;font-weight:850;margin-top:12px}select,textarea{width:100%;border:1px solid #dfe8d8;border-radius:8px;padding:12px;margin-top:6px;font:inherit}textarea{min-height:150px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;background:#08753a;color:#fff;padding:12px 15px;margin-top:16px;font-weight:950;text-decoration:none}.btn.secondary{background:#eef8ef;color:#06451f}.alert{padding:12px;border-radius:8px;margin:12px 0;font-weight:850}.ok{background:#e8f6ec;color:#06451f}.err{background:#fff1f2;color:#b42318}.note{border-left:4px solid #c99b22;background:#fffaf0;padding:12px;margin:14px 0;color:#344054}.actions{display:flex;gap:10px;flex-wrap:wrap}
  </style>
</head>
<body>
  <header class="top"><a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV">NATCODEV Academy</a><div class="actions"><a class="btn secondary" href="dashboard.php">Learner Dashboard</a><a class="btn secondary" href="../dashboard/inbox.php">Support Inbox</a></div></header>
  <main class="wrap">
    <section class="panel">
      <h1>Request a Platform Role</h1>
      <p>Learning access is active immediately. Operational roles are reviewed by platform admins before they are granted.</p>
      <div class="note">Submitting this form does not change your role automatically. It creates an account-upgrade ticket for admin review.</div>
      <?php if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label>Role you want to operate as
          <select name="requested_role" required>
            <option value="">Select role</option>
            <?php foreach ($roles as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Why do you need this role?
          <textarea name="reason" required placeholder="Describe your experience, current role, and why you are requesting this role."></textarea>
        </label>
        <button class="btn" type="submit">Submit Request</button>
      </form>
    </section>
  </main>
</body>
</html>
