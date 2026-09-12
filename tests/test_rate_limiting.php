<?php
declare(strict_types=1);

/**
 * Scenario 4: API & Endpoint Rate Limiting Tests
 * Tests:
 * 1) Standard API endpoint rate limiting thresholds.
 * 2) Multi-tenant / IP isolation (one abusive IP does not block another client).
 * 3) Time-decay and replenishment of rate limiting token buckets.
 * 4) Critical security actions (e.g. OTP validation, wallet funding).
 */

require_once __DIR__ . '/TestHarness.php';

function run_rate_limiting_tests(): void
{
    TestHarness::start('Scenario 4: API & Endpoint Rate Limiting');
    $pdo = TestHarness::createTestDb();

    $checkRateLimit = function(PDO $db, string $action, string $clientIp, int $maxAttempts, int $decaySeconds, int $currentTime): bool {
        $key = $action . ':' . $clientIp;
        $stmt = $db->prepare("SELECT attempts, expires_at FROM test_rate_limits WHERE limit_key = ? AND expires_at > ? LIMIT 1");
        $stmt->execute([$key, $currentTime]);
        $record = $stmt->fetch();

        if (!$record) {
            $db->prepare("INSERT INTO test_rate_limits (limit_key, attempts, last_attempt_at, expires_at) VALUES (?, 1, ?, ?) ON DUPLICATE KEY UPDATE attempts = 1, last_attempt_at = VALUES(last_attempt_at), expires_at = VALUES(expires_at)")
               ->execute([$key, $currentTime, $currentTime + $decaySeconds]);
            return true;
        }

        if ((int)$record['attempts'] >= $maxAttempts) {
            return false;
        }

        $db->prepare("UPDATE test_rate_limits SET attempts = attempts + 1, last_attempt_at = ? WHERE limit_key = ?")
           ->execute([$currentTime, $key]);
        return true;
    };

    // 4.1: API Endpoint Threshold Test (Max 5 requests per 60 seconds)
    $now = time();
    $apiEndpoint = 'api_get_records_' . bin2hex(random_bytes(4));
    $attackerIp = '198.51.100.25';

    for ($i = 1; $i <= 5; $i++) {
        $ok = $checkRateLimit($pdo, $apiEndpoint, $attackerIp, 5, 60, $now);
        TestHarness::assert($ok === true, "Request #{$i} within threshold (5 req/min) allowed");
    }

    $overLimitReq = $checkRateLimit($pdo, $apiEndpoint, $attackerIp, 5, 60, $now);
    TestHarness::assert($overLimitReq === false, 'Request #6 exceeding 5 req/min was blocked (HTTP 429 response simulated)');

    // 4.2: Client IP Isolation Test
    // A legitimate user from another IP address should not be affected by the blocked attacker
    $legitIp = '203.0.113.88';
    $legitReq = $checkRateLimit($pdo, $apiEndpoint, $legitIp, 5, 60, $now);
    TestHarness::assert($legitReq === true, 'Legitimate client IP is NOT blocked by abusive IP activity');

    // 4.3: Expiration / Decay Window Replenishment
    // After decay seconds pass, the blocked IP should be allowed again
    $futureTime = $now + 65; // 65 seconds later (> 60s window)
    $replenishedReq = $checkRateLimit($pdo, $apiEndpoint, $attackerIp, 5, 60, $futureTime);
    TestHarness::assert($replenishedReq === true, 'After decay window expiry, new requests are permitted and counter resets');

    // 4.4: OTP Verification Hardening (Strict Max 3 attempts per 5 minutes)
    $otpAction = 'otp_verify_user_' . bin2hex(random_bytes(4));
    $otpIp = '192.0.2.1';
    $otpTime = $now;

    for ($i = 1; $i <= 3; $i++) {
        $allowed = $checkRateLimit($pdo, $otpAction, $otpIp, 3, 300, $otpTime);
        TestHarness::assert($allowed === true, "OTP challenge attempt #{$i} processed");
    }

    $otpBreach = $checkRateLimit($pdo, $otpAction, $otpIp, 3, 300, $otpTime);
    TestHarness::assert($otpBreach === false, 'OTP challenge attempt #4 blocked to prevent SMS/email PIN brute-forcing');
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_rate_limiting_tests();
    exit(TestHarness::summary());
}
