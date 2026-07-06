<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';

// Prevent redirect loop if already on the login page
if (str_contains($_SERVER['REQUEST_URI'], 'login.php')) {
    return;
}

if (!admin_session_is_authenticated(db())) {
    header('Location: ' . app_base_url() . '/admin/login.php');
    exit;
}
