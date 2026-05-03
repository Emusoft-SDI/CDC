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

// Validate
if (!$name || !$location || !$farm_size || !$phone || !$email || !$commitments) {
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

// DB
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4",
        "coconutventure_growers",
        "1^v1V&Ak{DIPL~Y."
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
            commitments TEXT NOT NULL,
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

    $stmt = $pdo->prepare("SELECT id, confirmed FROM applications WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    if ($row = $stmt->fetch()) {
        if ($row['confirmed']) {
            exit(json_encode(['success' => false, 'message' => 'This email or phone is already registered.']));
        } else {
            exit(json_encode(['success' => false, 'message' => 'This contact was used recently. Check your email for a confirmation link.']));
        }
    }

    $app_ref = 'NAT-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $token = bin2hex(random_bytes(32));

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

} catch (PDOException $e) {
    error_log("Submission DB Error: " . $e->getMessage());
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        exit(json_encode(['success' => false, 'message' => 'This email or phone is already in use.']));
    }
    exit(json_encode(['success' => false, 'message' => 'Submission temporarily unavailable. Please try again.']));
}

// === ENHANCED EMAIL TO AVOID SPAM ===
$confirm_url = "https://apply.coconutventurehub.ng/confirm_email.php?token=" . urlencode($token); // Fixed spacing + encoding

// Plain-text version (required by spam filters)
$plain_text = "
Dear {$name},

Thank you for applying to the NATCODEV Coconut Outgrowers Program!

To complete your registration, please confirm your email by clicking the link below:

{$confirm_url}

This link expires in 7 days.

If you did not apply, please ignore this message.

— The NATCODEV Team
Coconut Venture Hub.Ng Limited
";

// Minimal HTML version (optional but recommended)
$html = "
<p>Dear <strong>{$name}</strong>,</p>
<p>Thank you for applying to the <strong>NATCODEV Coconut Outgrowers Program</strong>!</p>
<p>To complete your registration, please confirm your email:</p>
<p><a href='{$confirm_url}' style='display:inline-block; padding:10px 20px; background:#2d5016; color:white; text-decoration:none; border-radius:5px;'>✅ Confirm My Email</a></p>
<p><em>This link expires in 7 days.</em></p>
<p>— The NATCODEV Team<br>Coconut Venture Hub.Ng Limited</p>
";

// Critical anti-spam headers
$headers = [
    'From' => 'NATCODEV <noreply@coconutventurehub.ng>',
    'Reply-To' => 'info@coconutventurehub.ng',
    'Return-Path' => 'bounce@coconutventurehub.ng', // Must resolve to valid mailbox
    'MIME-Version' => '1.0',
    'Content-Type' => 'multipart/alternative; boundary="natcodev_boundary_123"',
    'List-Unsubscribe' => '<mailto:unsubscribe@coconutventurehub.ng?subject=Unsubscribe>',
    'List-Help' => '<mailto:info@coconutventurehub.ng?subject=Help>',
    'Precedence' => 'bulk',
    'X-Mailer' => 'NATCODEV/v2.5',
    'X-Auto-Response-Suppress' => 'OOF, DR, RN, NRN, AutoReply'
];

// Build multipart message
$message = "--natcodev_boundary_123\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . $plain_text . "\r\n"
          . "--natcodev_boundary_123\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . $html . "\r\n"
          . "--natcodev_boundary_123--";

// Send email
$subject = "Confirm Your NATCODEV Application – Action Required";
$header_string = implode("\r\n", $headers);

if (mail($email, $subject, $message, $header_string)) {
    $upd = $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE app_ref = ?");
    $upd->execute([$app_ref]);
}

exit(json_encode([
    'success' => true,
    'app_ref' => $app_ref,
    'message' => 'Please check your email (and spam folder) to confirm your application.'
]));
?>