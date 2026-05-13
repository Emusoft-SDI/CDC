<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$webinarId = filter_var($_POST['webinar_id'] ?? null, FILTER_VALIDATE_INT);
if (!$webinarId) {
    json_response(['success' => false, 'error' => 'Webinar ID required'], 422);
}

try {
    app_ensure_farmer_engagement_schema($pdo);

    $stmt = $pdo->prepare("SELECT is_free, price FROM webinars WHERE id = ? LIMIT 1");
    $stmt->execute([$webinarId]);
    $webinar = $stmt->fetch();
    if (!$webinar) {
        json_response(['success' => false, 'error' => 'Webinar not found'], 404);
    }

    if ((int) $webinar['is_free'] === 1) {
        $pdo->prepare("INSERT IGNORE INTO webinar_registrations (webinar_id, user_id, payment_status) VALUES (?, ?, 'free')")
            ->execute([$webinarId, (int) $user['id']]);
        json_response(['success' => true, 'message' => 'Registered successfully!']);
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([(int) $user['id']]);
    $balance = (float) $stmt->fetchColumn();
    $price = (float) $webinar['price'];

    if ($balance < $price) {
        $pdo->rollBack();
        json_response(['success' => false, 'error' => 'Insufficient balance. Fund your wallet first.'], 402);
    }

    $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?")->execute([$price, (int) $user['id']]);
    $pdo->prepare("INSERT INTO webinar_registrations (webinar_id, user_id, payment_status) VALUES (?, ?, 'paid')")
        ->execute([$webinarId, (int) $user['id']]);
    $pdo->commit();

    json_response(['success' => true, 'message' => "Payment processed! You're registered."]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Register webinar API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to register webinar'], 500);
}
