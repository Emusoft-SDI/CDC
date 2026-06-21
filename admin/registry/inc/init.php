<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../lib/admin-layout.php';
require_once __DIR__ . '/../../../lib/certificates.php';
require_once __DIR__ . '/../../../lib/identity-validation.php';
require_once __DIR__ . '/../../../lib/field-management.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

// Ensure all relevant schemas are present
admin_ensure_schema($pdo);
app_ensure_certificate_schema($pdo);
identity_ensure_schema($pdo);
fm_ensure_schema($pdo);

// Require admin authentication
admin_require($pdo);

// Only load user if it's not a general admin session, or if specific admin user ID is present.
$registryUser = ['name' => 'Administrator'];
if (isset($_SESSION['user_id'])) {
    $user = current_user($pdo);
    if ($user) {
        $registryUser = $user;
    }
}


// Global page variables
$registryNotice = (string) ($_GET['message'] ?? '');
$registryError = (string) ($_GET['error'] ?? '');
