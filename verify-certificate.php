<!-- verify-certificate.php -->
<?php
$ref = $_GET['ref'] ?? '';
if (!$ref) {
    die("Invalid certificate reference.");
}

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

$stmt = $pdo->prepare("
    SELECT a.name, a.app_ref, c.issued_at 
    FROM applications a 
    JOIN certificates c ON a.id = c.application_id 
    WHERE a.app_ref = ?
");
$stmt->execute([$ref]);
$cert = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Verify NATCODEV Certificate</title>
  <style>
    body { font-family: Arial; max-width: 600px; margin: 40px auto; text-align: center; }
    .verified { color: green; font-size: 24px; }
    .details { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-top: 20px; }
  </style>
</head>
<body>
  <?php if ($cert): ?>
    <div class="verified">✅ VALID CERTIFICATE</div>
    <div class="details">
      <h2><?= htmlspecialchars($cert['name']) ?></h2>
      <p>Reference: <?= htmlspecialchars($cert['app_ref']) ?></p>
      <p>Issued: <?= date('F j, Y', strtotime($cert['issued_at'])) ?></p>
      <p>NATCODEV Coconut Outgrowers Program</p>
    </div>
  <?php else: ?>
    <h2>❌ Invalid Certificate</h2>
    <p>No record found for this reference.</p>
  <?php endif; ?>
</body>
</html>