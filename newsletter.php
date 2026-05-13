<?php
// newsletter.php - NATCODEV Newsletter Opt-in
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
$source = $_GET['source'] ?? 'Application Confirmation';

if (!$email) {
    http_response_code(400);
    die('Invalid email address.');
}

// Prevent abuse: require email to already exist in applications (optional but recommended)
try {
    $pdo = db();

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
  <title>Thank You - NATCODEV GoodNews</title>
  <style>
    :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; }
    * { box-sizing:border-box; }
    body {
      font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background:linear-gradient(135deg, rgba(26,82,118,.08), rgba(31,138,85,.10)), #f5f8f6;
      margin:0;
      padding:24px;
      display:flex;
      justify-content:center;
      align-items:center;
      min-height:100vh;
      color:var(--ink);
    }
    .container {
      background:white;
      padding:40px;
      border-radius:8px;
      border:1px solid rgba(16,24,40,.08);
      box-shadow:0 18px 44px rgba(16,24,40,.12);
      text-align:center;
      max-width:600px;
      margin:20px;
    }
    .logo {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:54px;
      height:54px;
      border-radius:50%;
      background:#eaf8f0;
      color:var(--green-dark);
      font-size:1.8rem;
      font-weight:800;
      margin-bottom:20px;
    }
    h1 { color:var(--primary); margin:0 0 12px; line-height:1.2; }
    p { line-height:1.6; color:var(--muted); }
    ul { text-align:left; max-width:420px; margin:18px auto; line-height:1.7; color:var(--ink); }
    .highlight {
      background:#f7f9f5;
      border:1px solid var(--line);
      padding:15px;
      border-radius:8px;
      margin:20px 0;
      font-style:italic;
      color:var(--green-dark);
    }
    .home-link { display:inline-block; margin-top:10px; color:#fff; background:var(--green); text-decoration:none; font-weight:800; padding:11px 18px; border-radius:5px; }
    .home-link:hover { background:var(--green-dark); }
    .footer { margin-top:30px; color:#777; font-size:.9em; }
    @media (max-width:520px) { body { padding:14px; } .container { padding:28px 18px; margin:0; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">N</div>
    <h1>Thank You for Joining NATCODEV GoodNews!</h1>
    <p>You will now receive exclusive updates on:</p>
    <ul>
      <li>Coconut farming innovations & best practices</li>
      <li>Success stories from Nigerian outgrowers</li>
      <li>Training sessions, grant opportunities, and market news</li>
    </ul>
    <div class="highlight">
      "We do not just grow coconuts. We grow legacies."
    </div>
    <p>You can unsubscribe at any time.</p>
    <a class="home-link" href="index.php">Return to NATCODEV</a>
    <div class="footer">
      <p>NATCODEV - National Coconut Development & Propagation Initiative</p>
      <p>info@natcodev.com.ng | www.natcodev.com.ng</p>
    </div>
  </div>
</body>
</html>
