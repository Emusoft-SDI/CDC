<?php
require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ' . app_base_url() . '/dashboard/login.php');
    exit;
}

$authPdo = db();
$authStmt = $authPdo->prepare('SELECT id, email, account_status, email_verified_at FROM users WHERE id = ? LIMIT 1');
$authStmt->execute([(int) $_SESSION['user_id']]);
$authUser = $authStmt->fetch();
if (!$authUser || app_user_needs_email_verification($authUser)) {
    unset($_SESSION['user_id']);
    $email = $authUser ? (string) ($authUser['email'] ?? '') : '';
    header('Location: ' . app_base_url() . '/login.php' . ($email !== '' ? '?email=' . urlencode($email) : ''));
    exit;
}