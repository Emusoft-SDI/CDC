// api/biometric/enroll.php
<?php
session_start();
header('Content-Type: application/json');

// Store biometric template (simplified - in production use proper crypto)
$credential = $_POST['credential'] ?? [];
$clientData = $_POST['clientData'] ?? [];

// Save to database
$pdo->prepare("
    UPDATE users SET biometric_enrolled = 1, biometric_template = ? WHERE id = ?
")->execute([json_encode(['credential' => $credential, 'clientData' => $clientData]), $_SESSION['user_id']]);

// Log enrollment
$pdo->prepare("
    INSERT INTO biometric_logs (user_id, action, success, ip_address) VALUES (?, 'enroll', 1, ?)
")->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

echo json_encode(['success' => true]);
?>