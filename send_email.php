// Send confirmation email
<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'POST method required']));
}

// Input
$name = trim($_POST['name'] ?? '');
$location = trim($_POST['location'] ?? '');
$farm_size = filter_var($_POST['farm_size'] ?? '', FILTER_VALIDATE_FLOAT);
$phone = trim($_POST['phone'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? $phone);
$email = trim($_POST['email'] ?? '');
$commitments = trim($_POST['commitments'] ?? '');

// Validate (only for NEW submissions)
$is_new_submission = true;
if (!$name || !$location || !$farm_size || !$phone || !$email || !$commitments) {
    // But allow empty fields if we're only resending (not the case here)
    exit(json_encode(['success' => false, 'message' => 'All fields are required']));
}
if ($farm_size < 1 || $farm_size > 1000) {
    exit(json_encode(['success' => false, 'message' => 'Farm size must be 1–1000 hectares']));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit(json_encode(['success' => false, 'message' => 'Invalid email']));
}
if (!preg_match('/^0[7-9][01]\d{8}$/', preg_replace('/[^0-9]/', '', $phone))) {
    exit(json_encode(['success' => false, 'message' => 'Invalid Nigerian phone number']));
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4",
        "natcodevcom_data",
        "XC^#3)[;*xTcm&V9"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_ref VARCHAR(50) UNIQUE,
            name VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            farm_size DECIMAL(10,2) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            whatsapp VARCHAR(20),
            email VARCHAR(255) NOT NULL,
            commitments TEXT NOT.
            confirmed TINYINT(1) DEFAULT 0,
            confirmation_token VARCHAR(64) UNIQUE,
            confirmed_at DATETIME NULL,
            email_sent TINYINT(1) DEFAULT 0,
            team_notified TINYINT(1) DEFAULT 0,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(email),
            UNIQUE(phone)
        ) ENGINE=InnoDB
    ");

    // Check for existing record
    $stmt = $pdo->prepare("SELECT id, confirmed, confirmation_token FROM applications WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['confirmed']) {
            // ✅ Already confirmed → no action needed
            exit(json_encode([
                'success' => false,
                'message' => 'This email or phone is already registered and confirmed.',
                'already_confirmed' => true
            ]));
        } else {
            // ⏳ Unconfirmed → RESEND confirmation (do NOT create new record)
            $token = $existing['confirmation_token'];
            $app_ref = null; // Will fetch from DB

            // Get app_ref for response
            $refStmt = $pdo->prepare("SELECT app_ref FROM applications WHERE id = ?");
            $refStmt->execute([$existing['id']]);
            $app_ref = $refStmt->fetchColumn();

            // Proceed to send email (skip INSERT)
            $is_new_submission = false;
        }
    }

    // Only generate new token & insert if truly new
    if ($is_new_submission) {
        $app_ref = 'NAT-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $token = bin2hex(random_bytes(32));

        // Save new unconfirmed application
        $stmt = $pdo->prepare("
            INSERT INTO applications (
                app_ref, name, location, farm_size, phone, whatsapp, email, commitments,
                confirmation_token, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $app_ref, $name, $location, $farm_size, $phone, $whatsapp, $email, $commitments,
            $token, $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }

    // === SEND CONFIRMATION EMAIL (enhanced anti-spam) ===
    $confirm_url = "https://cfc.natcodev.com.ng/confirm_email.php?token=" . urlencode($token);

    // Plain-text version (required)
    $plain_text = "
Dear {$name},

Thank you for applying to the NATCODEV Coconut Outgrowers Program!

To complete your registration, please confirm your email by clicking the link below:

{$confirm_url}

This link expires in 7 days.

— The NATCODEV Team
Coconut Venture Hub.Ng Limited
";

    // HTML version
    $html = "
<p>Dear <strong>{$name}</strong>,</p>
<p>Thank you for applying to the <strong>NATCODEV Coconut Outgrowers Program</strong>!</p>
<p>To complete your registration, please confirm your email:</p>
<p><a href='{$confirm_url}' style='display:inline-block; padding:10px 20px; background:#2d5016; color:white; text-decoration:none; border-radius:5px;'>✅ Confirm My Email</a></p>
<p><em>This link expires in 7 days.</em></p>
<p>— The NATCODEV Team<br>Coconut Venture Hub.Ng Limited</p>
";

    // Anti-spam headers
    $headers = [
        'From' => 'NATCODEV <noreply@coconutventurehub.ng>',
        'Reply-To' => 'info@coconutventurehub.ng',
        'Return-Path' => 'bounce@coconutventurehub.ng',
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="natcodev_confirm"',
        'List-Unsubscribe' => '<mailto:unsubscribe@coconutventurehub.ng?subject=Unsubscribe>',
        'Precedence' => 'bulk'
    ];

    $message = "--natcodev_confirm\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
              . $plain_text . "\r\n"
              . "--natcodev_confirm\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
              . $html . "\r\n"
              . "--natcodev_confirm--";

    $subject = "Confirm Your NATCODEV Application – Action Required";
    $header_string = implode("\r\n", $headers);

    $email_sent = mail($email, $subject, $message, $header_string);

    if ($email_sent) {
        // Mark as sent in DB (works for both new and resent)
        $upd = $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE email = ?");
        $upd->execute([$email]);
    }

    // Success response
    $action = $is_new_submission ? 'submitted' : 'resent';
    exit(json_encode([
        'success' => true,
        'app_ref' => $app_ref,
        'message' => "Application {$action} successfully! Please check your email (and spam folder) to confirm.",
        'resend' => !$is_new_submission
    ]));

} catch (PDOException $e) {
    error_log("Submission DB Error: " . $e->getMessage());
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        exit(json_encode(['success' => false, 'message' => 'This email or phone is already in use.']));
    }
    exit(json_encode(['success' => false, 'message' => 'Submission temporarily unavailable. Please try again.']));
}
?>
<? 
$confirm_url = "https://cfc.natcodev.com.ng/confirm_email.php?token=" . urlencode($token);
$message = "
Dear $name,

Thank you for applying to the NATCODEV Coconut Outgrowers Program!

To complete your application, please confirm your email by clicking below:

👉 $confirm_url

This link expires in 7 days.

— The NATCODEV Team
";

$headers = "From: noreply@coconutventurehub.ng\r\nReply-To: info@coconutventurehub.ng\r\nContent-Type: text/plain; charset=UTF-8";

if (mail($email, "Confirm Your NATCODEV Application – $app_ref", $message, $headers)) {
    // Mark email_sent in DB
    $upd = $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE app_ref = ?");
    $upd->execute([$app_ref]);
}

// Inside send_email.php — after checking for duplicates
if ($row = $stmt->fetch()) {
    if ($row['confirmed']) {
        exit(json_encode([
            'success' => false,
            'message' => 'This email is already confirmed. No further action needed.',
            'already_confirmed' => true
        ]));
    } else {
        // UNCONFIRMED — offer resend
        exit(json_encode([
            'success' => false,
            'message' => 'A pending application exists for this email. We’ve resent the confirmation link.',
            'resend_triggered' => true
        ]));
        // → Automatically trigger resend!
    }
}?>
// In send_email.php, after database insert:
$backupFile = __DIR__ . '/backups/applications_' . date('Y-m') . '.csv';
$csvLine = [
    date('c'), $appRef, $name, $location, $farmSize, $phone, $whatsapp, $email, $commitments
];
file_put_contents($backupFile, implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $csvLine)) . "\n", FILE_APPEND);
// Then: UPDATE applications SET backup_saved = 1 WHERE app_ref = ?