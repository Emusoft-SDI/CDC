<?php
declare(strict_types=1);

/**
 * Security Validation Tool for NATCODEV
 * This script tests Rate Limiting and CSRF protection.
 * Run via CLI: php tests/security_validation.php
 */

require_once __DIR__ . '/../config.php';

echo "NATCODEV Security Validation\n";
echo "============================\n\n";

$pdo = db();

// 1. Test Rate Limiter
echo "Testing Rate Limiter... ";
$testAction = 'test_action_' . bin2hex(random_bytes(4));
$max = 3;
$decay = 10;

// First 3 should pass
for ($i = 1; $i <= $max; $i++) {
    if (!app_check_rate_limit($testAction, $max, $decay)) {
        echo "FAIL: Attempt $i blocked unexpectedly.\n";
        exit(1);
    }
}

// 4th should fail
if (app_check_rate_limit($testAction, $max, $decay)) {
    echo "FAIL: 4th attempt allowed (should be blocked).\n";
    exit(1);
}
echo "PASS (Rate Limiting works)\n";

// 2. Test CSRF Protection
echo "Testing CSRF Protection Primitives... ";
$token = csrf_token();
if (empty($token) || strlen($token) !== 64) {
    echo "FAIL: CSRF token generation failed or invalid format.\n";
    exit(1);
}

if (!verify_csrf($token)) {
    echo "FAIL: Valid CSRF token verification failed.\n";
    exit(1);
}

if (verify_csrf('invalid_token')) {
    echo "FAIL: Invalid CSRF token accepted.\n";
    exit(1);
}
echo "PASS (CSRF Primitives work)\n";

// 3. Test Session Cookie Hardening
echo "Testing Session Settings... ";
if (ini_get('session.cookie_httponly') !== '1') {
    echo "FAIL: session.cookie_httponly is not enabled.\n";
    exit(1);
}
if (ini_get('session.cookie_samesite') !== 'Lax') {
    echo "FAIL: session.cookie_samesite is not 'Lax'.\n";
    exit(1);
}
echo "PASS (Session Hardening works)\n";

echo "\nAll security validations passed successfully!\n";
