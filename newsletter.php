<?php
// newsletter.php - NATCODEV Newsletter Opt-in
header('Content-Type: text/html; charset=utf-8');

$email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
$source = $_GET['source'] ?? 'Application Confirmation';

if (!$email) {
    http_response_code(400);
    die('Invalid email address.');
}

// Prevent abuse: require email to already exist in applications (optional but recommended)
try {
    $pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
                   "natcodevcom_data", "XC^#3)[;*xTcm&V9");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optional: Only allow opt-in if email is in applications (enhances legitimacy)
    $check = $pdo->prepare("SELECT 1 FROM applications WHERE email = ? LIMIT 1");
    $check->execute([$email]);
    if (!$check->fetch()) {
        // Uncomment the next line if you want to block non-applicants
        // die('Email not found in active applications.');
    }

    // Insert or ignore (handles duplicate gracefully)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO subscribers (email, source, ip_address) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$email, $source, $_SERVER['REMOTE_ADDR'] ?? null]);

} catch (Exception $e) {
    error_log("Newsletter DB Error: " . $e->getMessage());
    // Still show success to avoid exposing system errors
}

// Show thank-you page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Thank You – NATCODEV GoodNews</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f0f7eb;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .container {
      background: white;
      padding: 40px;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 600px;
      margin: 20px;
    }
    .logo {
      font-size: 2.2rem;
      color: #2d5016;
      margin-bottom: 20px;
    }
    h1 {
      color: #2d5016;
      margin-bottom: 20px;
    }
    p {
      line-height: 1.6;
      color: #444;
    }
    .highlight {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin: 20px 0;
      font-style: italic;
      color: #2d5016;
    }
    .footer {
      margin-top: 30px;
      color: #777;
      font-size: 0.9em;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">🌱</div>
    <h1>Thank You for Joining NATCODEV GoodNews!</h1>
    <p>You’ll now receive exclusive updates on:</p>
    <ul style="text-align: left; max-width: 400px; margin: 15px auto; line-height: 1.6;">
      <li>Coconut farming innovations & best practices</li>
      <li>Success stories from Nigerian outgrowers</li>
      <li>Training sessions, grant opportunities, and market news</li>
    </ul>
    <div class="highlight">
      “We do not just grow Coconuts. We grow legacies.”
    </div>
    <p>You can unsubscribe at any time.</p>
    <div class="footer">
      <p>NATCODEV – National Coconut Development & Propagation Initiative</p>
      <p>📧 info@coconutventurehub.ng | 🌐 www.coconutventurehub.ng</p>
    </div>
  </div>
</body>
</html>