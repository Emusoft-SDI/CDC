<?php
$token = $_GET['token'] ?? '';
if (!$token) {
    die('Invalid confirmation link.');
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
                   "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch unconfirmed application
    $stmt = $pdo->prepare("SELECT id, name, email, confirmed FROM applications WHERE confirmation_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $app = $stmt->fetch();

    if (!$app) {
        die('Invalid or expired confirmation link.');
    }

    if ($app['confirmed']) {
        echo "<h2>✅ Already Confirmed</h2><p>Your application is already active. Thank you!</p>";
        exit;
    }

    // Check if token is older than 7 days
    $createdStmt = $pdo->prepare("SELECT created_at FROM applications WHERE confirmation_token = ?");
    $createdStmt->execute([$token]);
    $created_at = $createdStmt->fetchColumn();
    $created = new DateTime($created_at);
    $now = new DateTime();
    if ($now->diff($created)->days > 7) {
        die('
            <h2>⚠️ Confirmation Link Expired</h2>
            <p>Your confirmation link has expired for security reasons.</p>
            <p>Please <a href="https://apply.coconutventurehub.ng/" style="color:#2d5016; text-decoration:underline; font-weight:bold;">click here to reapply</a>.</p>
        ');
    }

    // Mark as confirmed
    $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = NOW() WHERE id = ?")->execute([$app['id']]);

    // === SEND WELCOME EMAIL ===
    $welcomeSubject = "✅ Welcome to NATCODEV – Your Legacy Begins! (Ref: " . substr($token, 0, 12) . ")";
    $welcomeMessage = "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f9f9f9; }
    .container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .header { background: #2d5016; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -30px -30px 25px -30px; }
    .highlight { background: #f0f7eb; padding: 15px; border-left: 4px solid #2d5016; margin: 20px 0; }
    .benefits { margin: 20px 0; }
    .benefits h4 { color: #2d5016; margin-bottom: 10px; }
    .benefits ul { padding-left: 20px; }
    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 0.9em; color: #777; }
  </style>
</head>
<body>
  <div class='container'>
    <div class='header'>
      <h1>Welcome, {$app['name']}!</h1>
      <p>You’re now a confirmed NATCODEV Outgrower</p>
    </div>

    <p><strong>Congratulations!</strong> Your application has been successfully registered with the <strong>NATCODEV Coconut Commercial Outgrowers Program</strong>.</p>

    <div class='highlight'>
      <h3>🌱 From Seedling to Generational Wealth in One Partnership</h3>
      <p>Welcome to a transforming agricultural partnership that does not just promise income—it delivers a lifetime legacy. By joining NATCODEV, you are not merely planting trees; you are securing multi-generational prosperity while contributing to Nigeria's agricultural revolution.</p>
    </div>

    <div class='benefits'>
      <h4>✨ Your NATCODEV Benefits Include:</h4>
      <ul>
        <li><strong>Guaranteed Hybrid Input Supply</strong> – Premium seedlings, organic inputs, and soil sterilizers</li>
        <li><strong>Technical Support & Training</strong> – Regenerative agriculture, best practices, farm rejuvenation</li>
        <li><strong>Assured Market Access</strong> – 100% off-take agreement, no middlemen</li>
        <li><strong>80-Year Income Stream</strong> – One hectare = decades of continuous, predictable returns</li>
        <li><strong>Legacy Protection</strong> – Rights inheritable by your children and grandchildren</li>
      </ul>
    </div>

    <h4>✅ Next Steps:</h4>
    <ul>
      <li>Our team will contact you within 24 hours</li>
      <li>We’ll schedule your farm assessment</li>
      <li>Prepare your land documents and questions</li>
    </ul>

    <div style='margin: 30px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;'>
      <p><strong>Stay Inspired & Informed!</strong></p>
      <p>We’d love to send you occasional updates on:</p>
      <ul>
        <li>Coconut farming tips & innovations</li>
        <li>Success stories from fellow growers</li>
        <li>Upcoming training sessions and grant opportunities</li>
      </ul>
      <p>
        <a href='https://apply.coconutventurehub.ng/newsletter?email=" . urlencode($app['email']) . "' 
           style='display: inline-block; background: #2d5016; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
          ✅ Yes, send me NATCODEV GoodNews!
        </a>
        <br><small style='color: #777; margin-top: 8px; display: block;'>No spam. Unsubscribe anytime.</small>
      </p>
    </div>

    <div class='footer'>
      <p><strong>NATCODEV – National Coconut Development & Propagation Initiative</strong><br>
      📞 0703-COCONUT (0703-3377202) | ✉️ info@coconutventurehub.ng<br>
      🌐 www.coconutventurehub.ng</p>
      <p><em>We do not just grow Coconuts. We grow legacies.</em></p>
    </div>
  </div>
</body>
</html>
    ";

    $headers = "From: noreply@coconutventurehub.ng\r\n"
              . "Reply-To: info@coconutventurehub.ng\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "MIME-Version: 1.0";

    mail($app['email'], $welcomeSubject, $welcomeMessage, $headers);

    // Also notify team that confirmation + welcome email was sent
    mail('info@coconutventurehub.ng', '✅ Confirmed + Welcome Sent: ' . $app['name'], 
         "Name: {$app['name']}\nEmail: {$app['email']}\nToken: $token", 
         "From: noreply@coconutventurehub.ng");

    // === Show success page ===
    echo "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='UTF-8'>
      <title>✅ Confirmed – Welcome to NATCODEV!</title>
      <style>
        body { font-family: Arial, sans-serif; background: #f0f7eb; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 600px; }
        .icon { font-size: 3rem; color: #4caf50; margin-bottom: 20px; }
        h2 { color: #2d5016; margin-bottom: 20px; }
        p { margin: 10px 0; line-height: 1.6; }
        .highlight { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; font-style: italic; color: #2d5016; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='icon'>✅</div>
        <h2>Email Confirmed!</h2>
        <p>Thank you, <strong>{$app['name']}</strong>!</p>
        <div class='highlight'>
          Your application is now active.<br>
          A welcome email with next steps and benefits has been sent to <strong>{$app['email']}</strong>.
        </div>
        <p>The NATCODEV team will contact you within 24 hours.</p>
        <p><em>We do not just grow Coconuts. We grow legacies.</em></p>
      </div>
    </body>
    </html>
    ";

} catch (Exception $e) {
    error_log("Welcome email error: " . $e->getMessage());
    echo "<h2>✅ Confirmed!</h2><p>Your application is active. A welcome email is on its way!</p>";
}
?>


<?php
$token = $_GET['token'] ?? '';
if (!$token) {
    die('Invalid confirmation link.');
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
                   "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT id, name, email, confirmed FROM applications WHERE confirmation_token = ?");
    $stmt->execute([$token]);
    $app = $stmt->fetch();

    if (!$app) die('Invalid or expired link.');
    if ($app['confirmed']) {
        echo "<h2>✅ Already Confirmed</h2><p>Your application is active. Our team will contact you soon!</p>";
        exit;
    }

    // Confirm
    $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = NOW(), team_notified = 1 WHERE id = ?")->execute([$app['id']]);

    // Notify team
    $msg = "CONFIRMED: {$app['name']} ({$app['email']}) just verified their email.\nRef: " . $_GET['token'];
    mail('info@coconutventurehub.ng', '✅ Confirmed NATCODEV Application', $msg, "From: noreply@coconutventurehub.ng");

    echo "<h2>✅ Email Confirmed!</h2><p>Thank you! Your application is now active.<br>The NATCODEV team will contact you within 24 hours.</p>";

} catch (Exception $e) {
    error_log("Confirmation error: " . $e->getMessage());
    die('Error confirming. Please contact support.');
}
?>


// In confirm_email.php — expiry section
echo "<h2>⚠️ Link Expired</h2>
      <p>Your confirmation link has expired.</p>
      <p><a href='https://apply.coconutventurehub.ng/resend-confirmation.php?email=" . urlencode($email) . "' 
             style='background:#2d5016; color:white; padding:10px 15px; text-decoration:none; border-radius:5px; display:inline-block;'>
          📤 Request New Link
        </a></p>";