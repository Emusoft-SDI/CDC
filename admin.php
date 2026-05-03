 


// After confirming application
generateCertificate($appId, $user['id']);

function generateCertificate($appId, $userId) {
    require_once 'tcpdf/tcpdf.php';
    
    // Add QR code generation
require_once 'tcpdf/qrcode.php';

// Generate QR code
$qrCode = TCPDF2DBarcode::getBarcodePNG(
    'https://apply.coconutventurehub.ng/verify-certificate?ref=' . urlencode($data['app_ref']),
    'QRCODE,M',
    50,
    50
);

$html = "
<div style='text-align:center; font-family:Arial;'>
  <!-- ... existing content ... -->
  
  <div style='margin:30px 0;'>
    <p>Scan QR Code to Verify</p>
    <img src='image/png;base64," . $qrCode . "' width='120'>
  </div>
  
  <!-- ... seals and signature ... -->
</div>
";

// Fetch data
    $pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
                   "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");
    $stmt = $pdo->prepare("
        SELECT a.name, a.app_ref, a.farm_size, a.location, u.email 
        FROM applications a 
        JOIN users u ON a.id = u.application_id 
        WHERE a.id = ?
    ");
    $stmt->execute([$appId]);
    $data = $stmt->fetch();
    
    if (!$data) return;
    
    // Generate PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('NATCODEV');
    $pdf->SetTitle('NATCODEV Certificate');
    $pdf->AddPage();
    
    <!--// Certificate design
    $html = "
    <div style='text-align:center;'>
        <h1 style='color:#2d5016;'>CERTIFICATE OF REGISTRATION</h1>
        <p>This certifies that</p>
        <h2>" . htmlspecialchars($data['name']) . "</h2>
        <p>is a registered Outgrower of the</p>
        <h3>NATCODEV Coconut Commercial Outgrowers Program</h3>
        <p>Reference: " . htmlspecialchars($data['app_ref']) . "</p>
        <p>Farm Size: " . $data['farm_size'] . " hectares | Location: " . htmlspecialchars($data['location']) . "</p>
        <p>Issued on: " . date('F j, Y') . "</p>
        <p style='margin-top:40px;'><img src='signature.png' width='200'></p>
        <p>Authorized Signatory<br>NATCODEV Management</p>
        <p style='font-size:12px; margin-top:30px;'>
            Verify at: https://apply.coconutventurehub.ng/verify-certificate?ref=" . urlencode($data['app_ref']) . "
        </p>
    </div>-->
    ";
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $fileName = 'certificate_' . $data['app_ref'] . '.pdf';
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/certificates/' . $fileName;
    
    if (!is_dir(dirname($filePath))) mkdir(dirname($filePath), 0755, true);
    $pdf->Output($filePath, 'F');
    
    // Save to DB
    $certStmt = $pdo->prepare("
        INSERT INTO certificates (user_id, application_id, certificate_path) 
        VALUES (?, ?, ?)
    ");
    $certStmt->execute([$userId, $appId, $fileName]);
}

<!-- In admin.php -->
<?php
if (isset($_POST['bulk_notify'])) {
    require_once 'lib/twilio.php';
    
    $message = trim($_POST['bulk_message'] ?? '');
    $send_email = !empty($_POST['send_email']);
    $send_whatsapp = !empty($_POST['send_whatsapp']);
    $role_filter = $_POST['role_filter'] ?? 'all';
    
    // Build query based on role
    $sql = "
        SELECT u.email, u.phone, u.notify_email, u.notify_whatsapp, u.role
        FROM users u 
        JOIN applications a ON u.application_id = a.id 
        WHERE a.confirmed = 1";
    
    if ($role_filter === 'growers') {
        $sql .= " AND u.role = 'grower'";
    } elseif ($role_filter === 'agents') {
        $sql .= " AND u.role = 'field_agent'";
    }
    
    $users = $pdo->query($sql)->fetchAll();
    
    $emailCount = 0;
    $whatsappCount = 0;
    
    foreach ($users as $user) {
        // Email
        if ($send_email && $user['notify_email']) {
            mail($user['email'], 'NATCODEV: ' . substr($message, 0, 30) . '...', $message, "From: noreply@coconutventurehub.ng");
            $emailCount++;
        }
        
        // In bulk notification loop
if ($send_whatsapp && $user['notify_whatsapp'] && $user['phone']) {
    if (!sendWhatsAppMessage($user['phone'], $message)) {
        // Fallback to SMS if WhatsApp fails
        if ($user['notify_sms']) {
            sendSMSMessage($user['phone'], $message);
            $smsCount++;
        }
    } else {
        $whatsappCount++;
    }
}
        }
    }
    
    echo "<p style='color:green;'>✅ Sent to $emailCount email(s) and $whatsappCount WhatsApp message(s).</p>";
}
?>
// After creating user account
$role = $_POST['role'] ?? 'grower'; // From admin form
$pdo->prepare("UPDATE users SET role = ? WHERE email = ?")->execute([$role, $email]);

<!-- Updated Bulk Form -->
<form method="POST">
  <textarea name="bulk_message" required></textarea>
  <div>
    <label><input type="checkbox" name="send_email" checked> Email</label>
    <label><input type="checkbox" name="send_whatsapp"> WhatsApp</label>
  </div>
  <div>
    <label>Send to:
      <select name="role_filter">
        <option value="all">All Confirmed Users</option>
        <option value="growers">Growers Only</option>
        <option value="agents">Field Agents Only</option>
      </select>
    </label>
  </div>
  <button type="submit" name="bulk_notify">📤 Send Bulk Notification</button>
</form>
<?
function sendWelcomeEmailWithPDF($email, $name, $password, $appId) {
    require_once 'tcpdf/tcpdf.php';
    
    // Generate PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('NATCODEV');
    $pdf->SetTitle('NATCODEV Application Summary');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    
    $html = "
    <h1>NATCODEV Application Summary</h1>
    <p><strong>Name:</strong> $name</p>
    <p><strong>Email:</strong> $email</p>
    <p><strong>Your Dashboard Login:</strong></p>
    <p>URL: https://apply.coconutventurehub.ng/dashboard/</p>
    <p>Password: <strong>$password</strong> (change after first login)</p>
    <p>— The NATCODEV Team</p>
    ";
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdfContent = $pdf->Output('natcodev_summary.pdf', 'S');
    
    // Send email with attachment
    $to = $email;
    $subject = "✅ Your NATCODEV Dashboard Access";
    $boundary = md5(time());
    
    $headers = "From: noreply@coconutventurehub.ng\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= "Dear $name,\n\nYour NATCODEV application is confirmed!\n\nYour dashboard login details are attached.\n\n— The NATCODEV Team";
    $message .= "\r\n\r\n--$boundary\r\n";
    $message .= "Content-Type: application/pdf; name=\"NATCODEV_Summary.pdf\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"NATCODEV_Summary.pdf\"\r\n\r\n";
    $message .= chunk_split(base64_encode($pdfContent));
    $message .= "\r\n--$boundary--\r\n";
    
    mail($to, $subject, $message, $headers);
}
?>