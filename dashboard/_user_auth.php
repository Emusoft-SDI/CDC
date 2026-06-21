<?php
require_once __DIR__ . '/../config.php';

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// User-facing auth check
if (empty($_SESSION['user_id'])) {
    header('Location: ' . app_base_url() . '/dashboard/login.php');
    exit;
}
