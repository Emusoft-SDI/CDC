<?php
header('Content-Type: application/json; charset=utf-8');
// === SEND CONFIRMATION EMAIL (FIXED - NO RAW MIME) ===
$confirm_url = "https://apply.coconutventurehub.ng/confirm_email.php?token=" . urlencode($newToken);

// Plain-text version
$plain_text = "
Dear {$app['name']},

You requested a new confirmation link for your NATCODEV application.

To complete your registration, please confirm your email by clicking the link below:

{$confirm_url}

This link expires in 7 days.

— The NATCODEV Team
Coconut Venture Hub.Ng Limited
";

// HTML version
$html = "
<p>Dear <strong>{$app['name']}</strong>,</p>
<p>You requested a new confirmation link for your <strong>NATCODEV application</strong>.</p>
<p>To complete your registration, please confirm your email:</p>
<p><a href='{$confirm_url}' style='display:inline-block; padding:10px 20px; background:#2d5016; color:white; text-decoration:none; border-radius:5px;'>✅ Confirm My Email</a></p>
<p><em>This link expires in 7 days.</em></p>
<p>— The NATCODEV Team<br>Coconut Venture Hub.Ng Limited</p>
";

// Build headers as a SINGLE STRING with \r\n (critical!)
$headers = "From: NATCODEV <noreply@coconutventurehub.ng>\r\n";
$headers .= "Reply-To: info@coconutventurehub.ng\r\n";
$headers .= "Return-Path: bounce@coconutventurehub.ng\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"boundary123\"\r\n";
$headers .= "List-Unsubscribe: <mailto:unsubscribe@coconutventurehub.ng?subject=Unsubscribe>\r\n";
$headers .= "Precedence: bulk\r\n";
$headers .= "X-Mailer: NATCODEV/v1.0";

// Build message body with proper boundaries
$message = "--boundary123\r\n";
$message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
$message .= trim($plain_text) . "\r\n\r\n";
$message .= "--boundary123\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$message .= $html . "\r\n\r\n";
$message .= "--boundary123--\r\n";

$subject = "Your NATCODEV Confirmation Link (Resent)";
$email_sent = mail($email, $subject, $message, $headers);

$email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    exit(json_encode(['success' => false, 'message' => 'Invalid email']));
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4",
        "coconutventure_growers",
        "1^v1V&Ak{DIPL~Y."
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT name, confirmed, app_ref FROM applications WHERE email = ? AND confirmed = 0");
    $stmt->execute([$email]);
    $app = $stmt->fetch();

    if (!$app) {
        exit(json_encode(['success' => false, 'message' => 'No pending application found for this email.']));
    }

    // Generate new token
    $newToken = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE applications SET confirmation_token = ?, created_at = NOW() WHERE email = ?")
         ->execute([$newToken, $email]);

    // === SEND EMAIL EXACTLY LIKE YOUR WORKING send_email.php ===
    $confirm_url = "https://apply.coconutventurehub.ng/confirm_email.php?token=" . urlencode($newToken);
    
    $message = "
Dear {$app['name']},

You requested a new confirmation link for your NATCODEV application.

To complete your registration, please confirm your email by clicking below:

👉 {$confirm_url}

This link expires in 7 days.

— The NATCODEV Team
";

    $headers = "From: noreply@coconutventurehub.ng\r\nReply-To: info@coconutventurehub.ng\r\nContent-Type: text/plain; charset=UTF-8";

    if (mail($email, "Your NATCODEV Confirmation Link (Resent)", $message, $headers)) {
        $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE email = ?")->execute([$email]);
        exit(json_encode(['success' => true, 'message' => 'Confirmation link resent successfully.']));
    } else {
        exit(json_encode(['success' => false, 'message' => 'Failed to send email.']));
    }

} catch (Exception $e) {
    error_log("Resend error: " . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'System error.']));
}

// In resend-confirmation.php
$stmt = $pdo->prepare("SELECT name, confirmed FROM applications WHERE email = ?");
$stmt->execute([$email]);
$app = $stmt->fetch();

if (!$app) {
    // No record at all → likely typo or wrong email
    exit(json_encode([
        'success' => false,
        'message' => 'No application found for this email. Please check spelling or <a href="/priority2024">apply now</a>.'
    ]));
}

if ($app['confirmed']) {
    exit(json_encode([
        'success' => false,
        'message' => 'Your application is already confirmed. Our team will contact you soon!'
    ]));
}

// Else: unconfirmed → generate new token + send email
// (as previously implemented)
?>