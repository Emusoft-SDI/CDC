// api/check-terms.php
<?php
session_start();
header('Content-Type: application/json');

$stmt = $pdo->prepare("SELECT terms_accepted FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$accepted = $stmt->fetchColumn();

echo json_encode(['accepted' => (bool)$accepted]);
?>

// api/accept-terms.php
<?php
session_start();
header('Content-Type: application/json');

$pdo->prepare("
    UPDATE users SET terms_accepted = 1, terms_accepted_at = NOW(), terms_version = '1.0' WHERE id = ?
")->execute([$_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>