<!-- edit-application.php -->
<?php
session_start();
$email = $_SESSION['edit_email'] ?? null;
if (!$email) {
    header('Location: my-application.php');
    exit;
}

// Connect to DB
$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch unconfirmed app
$stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? AND confirmed = 0");
$stmt->execute([$email]);
$app = $stmt->fetch();

if (!$app) {
    die("No pending application found for this email.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update logic (only allow changes if not confirmed)
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $farm_size = floatval($_POST['farm_size'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? $phone);
    $commitments = trim($_POST['commitments'] ?? '');

    if ($name && $location && $farm_size >= 1 && $phone && $commitments) {
        $upd = $pdo->prepare("
            UPDATE applications 
            SET name = ?, location = ?, farm_size = ?, phone = ?, whatsapp = ?, commitments = ?
            WHERE email = ? AND confirmed = 0
        ");
        $upd->execute([$name, $location, $farm_size, $phone, $whatsapp, $commitments, $email]);
        echo "<script>alert('✅ Updated successfully!'); window.location='edit-application.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Application</title>
  <style>
    body { font-family: Arial; max-width: 600px; margin: 20px auto; }
    .form-group { margin: 15px 0; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input, textarea { width: 100%; padding: 8px; }
    button { background: #2d5016; color: white; padding: 10px; border: none; margin-top: 10px; }
    .status { background: #e8f5e9; padding: 10px; border-radius: 5px; margin: 20px 0; }
  </style>
</head>
<body>
  <div class="status">
    <strong>Status:</strong> Pending Confirmation<br>
    <em>You can edit until you confirm your email.</em>
  </div>

  <form method="POST">
    <div class="form-group">
      <label>Email</label>
      <input type="email" value="<?= htmlspecialchars($email) ?>" disabled>
    </div>
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($app['name']) ?>" required>
    </div>
    <div class="form-group">
      <label>Location</label>
      <input type="text" name="location" value="<?= htmlspecialchars($app['location']) ?>" required>
    </div>
    <div class="form-group">
      <label>Farm Size (ha)</label>
      <input type="number" name="farm_size" value="<?= $app['farm_size'] ?>" min="1" step="0.1" required>
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input type="tel" name="phone" value="<?= htmlspecialchars($app['phone']) ?>" required>
    </div>
    <div class="form-group">
      <label>WhatsApp</label>
      <input type="tel" name="whatsapp" value="<?= htmlspecialchars($app['whatsapp']) ?>">
    </div>
    <div class="form-group">
      <label>Commitments</label>
      <textarea name="commitments" rows="3" required><?= htmlspecialchars($app['commitments']) ?></textarea>
    </div>
    <button type="submit">Save Changes</button>
  </form>

  <p><a href="my-application.php">← Change Email</a></p>
</body>
</html>