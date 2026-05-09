<!-- dashboard/inbox.php -->
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

// Mark messages as read
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND is_from_admin = 1")->execute([$_SESSION['user_id']]);

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply = trim($_POST['reply'] ?? '');
    if ($reply) {
        $ins = $pdo->prepare("INSERT INTO messages (user_id, message, is_from_admin) VALUES (?, ?, 0)");
        $ins->execute([$_SESSION['user_id'], $reply]);
    }
}

// Fetch messages
$stmt = $pdo->prepare("
    SELECT * FROM messages 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Inbox - NATCODEV</title>
  <style>
    body { font-family: Arial; max-width: 800px; margin: 20px auto; }
    .message { padding: 15px; margin: 10px 0; border-radius: 8px; }
    .from-admin { background: #e8f5e9; border-left: 4px solid #2d5016; }
    .from-user { background: #f0f0f0; text-align: right; }
    .reply-box { margin-top: 30px; }
    textarea { width: 100%; height: 100px; padding: 10px; }
    button { background: #2d5016; color: white; padding: 10px 20px; border: none; border-radius: 4px; }
  </style>
</head>
<body>
  <h2>Messages</h2>

  <?php foreach ($messages as $msg): ?>
    <div class="message <?= $msg['is_from_admin'] ? 'from-admin' : 'from-user' ?>">
      <strong><?= $msg['is_from_admin'] ? 'NATCODEV Team' : 'You' ?></strong><br>
      <?= htmlspecialchars($msg['message']) ?><br>
      <small><?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?></small>
    </div>
  <?php endforeach; ?>

  <div class="reply-box">
    <h3>Reply to NATCODEV</h3>
    <form method="POST">
      <textarea name="reply" placeholder="Type your message..." required></textarea><br>
      <button type="submit">Send Message</button>
    </form>
  </div>

  <p><a href="index.php">← Back to Dashboard</a></p>
</body>
</html>