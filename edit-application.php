<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();
$email = $_SESSION['edit_email'] ?? null;
if (!$email) {
    redirect_to('my-application.php');
}

$pdo = db();
app_ensure_core_schema($pdo);
$message = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? AND confirmed = 0 LIMIT 1");
$stmt->execute([$email]);
$app = $stmt->fetch();

if (!$app) {
    http_response_code(404);
    exit('No pending application found for this email.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $farmSize = filter_var($_POST['farm_size'] ?? null, FILTER_VALIDATE_FLOAT);
        $phone = preg_replace('/[^0-9]/', '', (string) ($_POST['phone'] ?? ''));
        $whatsapp = preg_replace('/[^0-9]/', '', (string) ($_POST['whatsapp'] ?? $phone));
        $commitments = trim((string) ($_POST['commitments'] ?? ''));

        if ($name === '' || $location === '' || $farmSize === false || $farmSize < 1 || $phone === '' || $commitments === '') {
            $error = 'Complete all required fields with valid values.';
        } else {
            $upd = $pdo->prepare("
                UPDATE applications
                SET name = ?, location = ?, farm_size = ?, phone = ?, whatsapp = ?, commitments = ?
                WHERE email = ? AND confirmed = 0
            ");
            $upd->execute([$name, $location, $farmSize, $phone, $whatsapp ?: $phone, $commitments, $email]);
            $message = 'Application updated successfully.';
            $stmt->execute([$email]);
            $app = $stmt->fetch() ?: $app;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Application - NATCODEV</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 620px; margin: 20px auto; padding:0 14px; color:#172211; }
    .form-group { margin: 15px 0; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input, textarea { width: 100%; padding: 10px; box-sizing:border-box; border:1px solid #dfe8d8; border-radius:5px; }
    button { background: #2d5016; color: white; padding: 11px 16px; border: none; margin-top: 10px; border-radius:5px; font-weight:700; cursor:pointer; }
    .status, .success { background: #e8f5e9; color:#14733a; padding: 10px; border-radius: 5px; margin: 20px 0; }
    .error { background:#fff3f3; color:#a32020; padding:10px; border-radius:5px; }
    a { color:#14733a; font-weight:700; }
  </style>
</head>
<body>
  <h1>Edit Application</h1>
  <div class="status">
    <strong>Status:</strong> Pending Confirmation<br>
    <em>You can edit until you confirm your email.</em>
  </div>
  <?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-group">
      <label>Email</label>
      <input type="email" value="<?= e((string) $email) ?>" disabled>
    </div>
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="name" value="<?= e($app['name']) ?>" required>
    </div>
    <div class="form-group">
      <label>Location</label>
      <input type="text" name="location" value="<?= e($app['location']) ?>" required>
    </div>
    <div class="form-group">
      <label>Farm Size (ha)</label>
      <input type="number" name="farm_size" value="<?= e((string) $app['farm_size']) ?>" min="1" step="0.1" required>
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input type="tel" name="phone" value="<?= e($app['phone']) ?>" required>
    </div>
    <div class="form-group">
      <label>WhatsApp</label>
      <input type="tel" name="whatsapp" value="<?= e($app['whatsapp']) ?>">
    </div>
    <div class="form-group">
      <label>Commitments</label>
      <textarea name="commitments" rows="3" required><?= e($app['commitments']) ?></textarea>
    </div>
    <button type="submit">Save Changes</button>
  </form>

  <p><a href="my-application.php">Change Email</a></p>
</body>
</html>
