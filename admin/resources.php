<!-- admin/resources.php -->
<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/resources/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $fileName = time() . '_' . basename($_FILES['file']['name']);
    $filePath = $uploadDir . $fileName;
    
    // Allow only safe files
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed) && move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
        $stmt = $pdo->prepare("
            INSERT INTO resources (title, description, file_path, category) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $fileName,
            $_POST['category']
        ]);
        
        // Log audit
        $log = $pdo->prepare("INSERT INTO audit_log (action, description, ip_address) VALUES (?, ?, ?)");
        $log->execute(['Resource Uploaded', 'Title: ' . $_POST['title'], $_SERVER['REMOTE_ADDR']]);
    }
}

// Fetch resources
$resources = $pdo->query("SELECT * FROM resources ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Resource Library - Admin</title>
  <style>/* ... your admin CSS ... */</style>
</head>
<body>
  <h1>Manage Resources</h1>
  
  <!-- Upload Form -->
  <form method="POST" enctype="multipart/form-data" style="background:#f9f9f9; padding:20px; border-radius:8px; margin-bottom:30px;">
    <div class="form-group">
      <label>Title</label>
      <input type="text" name="title" required>
    </div>
    <div class="form-group">
      <label>Description</label>
      <textarea name="description"></textarea>
    </div>
    <div class="form-group">
      <label>Category</label>
      <select name="category">
        <option value="Training">Training</option>
        <option value="Guides">Guides</option>
        <option value="Market">Market Data</option>
        <option value="Certificates">Certificates</option>
      </select>
    </div>
<div class="form-group">
  <label>
    <input type="checkbox" name="offline_available" checked> 
    Available Offline for Field Agents
  </label>
</div>
    <div class="form-group">
      <label>File (PDF, DOC, XLS, JPG, PNG)</label>
      <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
    </div>
    <button type="submit">Upload Resource</button>
  </form>

  <!-- Resources List -->
  <table>
    <thead>
      <tr><th>Title</th><th>Category</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($resources as $res): ?>
      <tr>
        <td><?= htmlspecialchars($res['title']) ?></td>
        <td><?= htmlspecialchars($res['category']) ?></td>
        <td>
          <a href="/resources/<?= urlencode($res['file_path']) ?>" target="_blank">📥 Download</a>
          <!-- Add delete button if needed -->
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>