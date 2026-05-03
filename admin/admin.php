<!-- admin.php (Enhanced) -->
<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    if ($_POST['password'] ?? '' === 'YourSecurePassword') {
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    }?>
    <!DOCTYPE html>
    <html>
    <head><title>Admin Login</title></head>
    <body>
      <form method="POST" style="max-width:300px; margin:100px auto;">
        <input type="password" name="password" placeholder="Admin Password" required>
        <button type="submit">Login</button>
      </form>
    </body>
    </html>
    <?php
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

// Export to CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="natcodev_applications.csv"');
    $stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC");
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID','Ref','Name','Location','Farm Size','Phone','Email','Confirmed','Applied','Confirmed At']);
    while ($row = $stmt->fetch()) {
        fputcsv($fh, [
            $row['id'],
            $row['app_ref'],
            $row['name'],
            $row['location'],
            $row['farm_size'],
            $row['phone'],
            $row['email'],
            $row['confirmed'] ? 'Yes' : 'No',
            $row['created_at'],
            $row['confirmed_at']
        ]);
    }
    fclose($fh);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>NATCODEV Admin Dashboard</title>
  <style>
    body { font-family: Arial; margin: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #f0f7eb; }
    .confirmed { color: green; }
    .pending { color: orange; }
    .actions a { margin-right: 10px; color: #2d5016; text-decoration: none; }
  </style>
</head>
<body>
  <h1>NATCODEV Applications</h1>
  <p>
    <a href="?export=1">📥 Export CSV</a> |
    <a href="?logout=1" onclick="return confirm('Log out?')">Logout</a>
  </p>

  <table>
    <thead>
      <tr>
        <th>Ref</th>
        <th>Name</th>
        <th>Location</th>
        <th>Farm (ha)</th>
        <th>Phone</th>
        <th>Email</th>
        <th>Status</th>
        <th>Applied</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC");
      while ($row = $stmt->fetch()): ?>
      <tr>
        <td><?= htmlspecialchars($row['app_ref']) ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['location']) ?></td>
        <td><?= $row['farm_size'] ?></td>
        <td><?= htmlspecialchars($row['phone']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td>
          <?php if ($row['confirmed']): ?>
            <span class="confirmed">✅ Confirmed</span>
          <?php else: ?>
            <span class="pending">⏳ Pending</span>
          <?php endif; ?>
        </td>
        <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>
<?php
// Handle manual confirmation
if (isset($_GET['confirm'])) {
    $id = intval($_GET['confirm']);
    $stmt = $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = NOW() WHERE id = ? AND confirmed = 0");
    $stmt->execute([$id]);
    
    // Generate user account
    $userStmt = $pdo->prepare("SELECT email, name FROM applications WHERE id = ?");
    $userStmt->execute([$id]);
    $user = $userStmt->fetch();
    
    if ($user) {
        $password = substr(bin2hex(random_bytes(6)), 0, 8); // e.g., a3f9b2c1
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Create user account
        $pdo->prepare("
            INSERT INTO users (email, password, application_id, name, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE password = ?
        ")->execute([$user['email'], $hashed, $id, $user['name'], $hashed]);
        
        // Send credentials + PDF
        sendWelcomeEmailWithPDF($user['email'], $user['name'], $password, $id);
    }
    
    header('Location: admin.php?search=' . urlencode($_GET['search'] ?? ''));
    exit;
}

// Export logic (same as before)
if (isset($_GET['export'])) { /* ... */ }

// Build query with search/filter
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';

$sql = "SELECT * FROM applications WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
if ($status === 'confirmed') {
    $sql .= " AND confirmed = 1";
} elseif ($status === 'pending') {
    $sql .= " AND confirmed = 0";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>
  <h1>NATCODEV Applications</h1>
  
  <!-- Search & Filter -->
  <form method="GET" style="margin-bottom:20px;">
    <input type="text" name="search" placeholder="Search name/email/phone..." value="<?= htmlspecialchars($search) ?>">
    <select name="status">
      <option value="all" <?= $status==='all'?'selected':'' ?>>All</option>
      <option value="confirmed" <?= $status==='confirmed'?'selected':'' ?>>Confirmed</option>
      <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
    </select>
    <button type="submit">Filter</button>
    <a href="?export=1" style="margin-left:20px;">📥 Export CSV</a>
  </form>

  <table>
    <thead>
      <tr>
        <th>Ref</th>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $stmt->fetch()): ?>
      <tr>
        <td><?= htmlspecialchars($row['app_ref']) ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td>
          <?php if ($row['confirmed']): ?>
            <span class="confirmed">✅ Confirmed</span>
          <?php else: ?>
            <span class="pending">⏳ Pending</span>
          <?php endif; ?>
        </td>
        <td class="actions">
          <?php if (!$row['confirmed']): ?>
            <a href="?confirm=<?= $row['id'] ?>&search=<?= urlencode($search) ?>" 
               onclick="return confirm('Confirm this application?')">✅ Confirm</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>