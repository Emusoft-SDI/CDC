<?php
declare(strict_types=1);

/**
 * NATCODEV Comprehensive Automated Security & Regression Test Suite Runner
 * Runs all critical security vulnerability and platform feature test suites:
 * 1) Concurrency & Race Conditions
 * 2) Malicious File Upload Bypass
 * 3) Cryptographic Password Hashing & Brute-Force Lockouts
 * 4) API & Endpoint Rate Limiting
 * 5) IDOR & Horizontal/Vertical Ownership Verification
 * 6) Telecommunications Gateway & News/Communications Subsystem
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/test_concurrency_race_conditions.php';
require_once __DIR__ . '/test_file_upload_security.php';
require_once __DIR__ . '/test_password_hashing_and_lockout.php';
require_once __DIR__ . '/test_rate_limiting.php';
require_once __DIR__ . '/test_idor_ownership_verification.php';
require_once __DIR__ . '/test_sms_and_communication_suite.php';
require_once __DIR__ . '/test_public_user_suite.php';
require_once __DIR__ . '/test_admin_user_suite.php';
require_once __DIR__ . '/test_super_admin_suite.php';
require_once __DIR__ . '/test_stakeholder_journeys_suite.php';
require_once __DIR__ . '/test_academy_learner_suite.php';
require_once __DIR__ . '/test_marketplace_user_suite.php';

echo "\033[1;35m";
echo "====================================================================\n";
echo "  NATCODEV PLATFORM — AUTOMATED SECURITY & REGRESSION AUDIT SUITE\n";
echo "====================================================================\n";
echo "\033[0m";

run_concurrency_tests();
run_file_upload_tests();
run_password_and_lockout_tests();
run_rate_limiting_tests();
run_idor_tests();
run_sms_and_communication_tests();
run_public_user_tests();
run_admin_user_tests();
run_super_admin_tests();
run_all_stakeholder_journeys_tests();
run_academy_learner_tests();
run_marketplace_user_tests();

$exitCode = TestHarness::summary();
exit($exitCode);
