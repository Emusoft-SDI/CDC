<!-- dashboard/index.php -->
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

$stmt = $pdo->prepare("
    SELECT a.*, u.name as user_name 
    FROM applications a 
    JOIN users u ON a.id = u.application_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$app = $stmt->fetch();

if (!$app) {
    die("Application not found.");
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>My NATCODEV Dashboard</title>
  <style>
    body { font-family: Arial; max-width: 800px; margin: 20px auto; }
    .card { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 8px; border-bottom: 1px solid #eee; }
  </style>
</head>
<body>
  <h1>Welcome, <?= htmlspecialchars($app['user_name']) ?>!</h1>
  
  <div class="card">
    <h2>Application Summary</h2>
    <table>
      <tr><td><strong>Reference</strong></td><td><?= htmlspecialchars($app['app_ref']) ?></td></tr>
      <tr><td><strong>Status</strong></td><td><?= $app['confirmed'] ? '✅ Confirmed' : '⏳ Pending' ?></td></tr>
      <tr><td><strong>Farm Size</strong></td><td><?= $app['farm_size'] ?> hectares</td></tr>
      <tr><td><strong>Location</strong></td><td><?= htmlspecialchars($app['location']) ?></td></tr>
      <tr><td><strong>Commitments</strong></td><td><?= htmlspecialchars($app['commitments']) ?></td></tr>
    </table>
  </div>

  <div class="card">
    <h2>Resources</h2>
    <ul>
      <li><a href="#">Coconut Farming Guide (PDF)</a></li>
      <li><a href="#">Training Calendar</a></li>
      <li><a href="#">Market Price Updates</a></li>
    </ul>
  </div>

  <p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php
// Fetch user role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRole = $stmt->fetchColumn();

// Show role-specific content
if ($userRole === 'field_agent') {
    echo "<div class='card'><h2>Field Agent Tools</h2><p>Manage your growers...</p></div>";
} elseif ($userRole === 'admin') {
    echo "<div class='card'><h2>Admin Dashboard</h2><p><a href='/admin/'>Go to Admin Panel</a></p></div>";
}
?>

<!-- Dashboard header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
  <img src="/assets/logo/natcodev-logo.png" alt="NATCODEV" style="height: 40px;">
  <div>
    <span>Welcome, <?= htmlspecialchars($user['name']) ?>!</span>
    <a href="logout.php" style="margin-left: 15px; color: #2d5016;">Logout</a>
  </div>
</div>
<!-- In dashboard/index.php -->
<div class="card">
  <h2>Resource Library</h2>
  <?php
  $resStmt = $pdo->query("SELECT * FROM resources ORDER BY created_at DESC");
  while ($res = $resStmt->fetch()):
  ?>
    <div style="margin: 10px 0; padding: 10px; border-left: 3px solid #2d5016;">
      <strong><?= htmlspecialchars($res['title']) ?></strong> 
      (<?= htmlspecialchars($res['category']) ?>)<br>
      <?= htmlspecialchars(substr($res['description'], 0, 100)) ?>...
      <a href="/resources/<?= urlencode($res['file_path']) ?>" download>📥 Download</a>
    </div>
<li><a href="change-password.php">🔐 Change Password</a></li>
  <?php endwhile; ?>
</div>
<!-- Certificate section with official seals -->
<?php if ($certificate): ?>
<div class="card">
  <h2>Your Official Certificate</h2>
  <div style="display: flex; justify-content: space-around; margin: 20px 0;">
    <img src="/assets/seals/fmaf.png" width="60" title="Federal Ministry of Agriculture">
    <img src="/assets/seals/naic.png" width="60" title="NAIC">
    <img src="/assets/seals/nisral.png" width="60" title="NISRAL">
    <img src="/assets/seals/boa.png" width="60" title="Bank of Agriculture">
  </div>
  <a href="/certificates/<?= urlencode($certificate['certificate_path']) ?>" target="_blank" 
     style="display:inline-block; background:#2d5016; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;">
    📄 Download Certificate
  </a>
</div>
<?php endif; ?>
<!-- Check for certificate -->
$certStmt = $pdo->prepare("SELECT certificate_path FROM certificates WHERE user_id = ?");
$certStmt->execute([$_SESSION['user_id']]);
$certificate = $certStmt->fetch();

if ($certificate):
?>
<div class="card">
  <h2>Your Certificate</h2>
  <a href="/certificates/<?= urlencode($certificate['certificate_path']) ?>" target="_blank" style="display:inline-block; background:#2d5016; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;">
    📄 Download Certificate
  </a>
</div>
<?php endif; ?>
<div class="card">
  <h2>Need Help?</h2>
  <p>Contact our team directly via WhatsApp:</p>
  <a href="https://wa.me/2347033377202?text=Hello%20NATCODEV%2C%20my%20application%20ref%20is%20<?= urlencode($app['app_ref']) ?>.%20I%20need%20assistance." 
     target="_blank"
     style="display:inline-block; background:#25D366; color:white; padding:12px 24px; text-decoration:none; border-radius:6px; font-weight:bold;">
    💬 Message Us on WhatsApp
  </a>
  <p style="font-size:14px; color:#666; margin-top:10px;">
    We respond within 24 hours.
  </p>
</div>