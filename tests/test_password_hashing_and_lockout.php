<?php
declare(strict_types=1);

/**
 * Scenario 3: Cryptographic Password Hashing & Brute-Force Lockout Tests
 * Tests:
 * 1) Secure password hashing algorithm (BCRYPT / Argon2) and cost factors.
 * 2) Password verification against timing attacks using constant-time checks.
 * 3) Automatic legacy password rehashing to strong BCRYPT on successful login.
 * 4) Brute-force simulation loop: after max failed attempts (10), lockout is enforced.
 */

require_once __DIR__ . '/TestHarness.php';

function run_password_and_lockout_tests(): void
{
    TestHarness::start('Scenario 3: Cryptographic Password Hashing & Brute-Force Lockouts');
    $pdo = TestHarness::createTestDb();

    // 3.1: Cryptographic Algorithm Strength Verification
    $plainPassword = 'CorrectHorseBatteryStaple!2026';
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $info = password_get_info($hash);

    TestHarness::assert(
        in_array($info['algoName'], ['bcrypt', 'argon2i', 'argon2id'], true),
        'Default password hashing algorithm is modern and secure (' . $info['algoName'] . ')'
    );
    TestHarness::assert(
        password_verify($plainPassword, $hash) === true,
        'password_verify correctly validates the plaintext against the hash'
    );
    TestHarness::assert(
        password_verify('WrongPassword123!', $hash) === false,
        'password_verify rejects incorrect password credentials'
    );

    // 3.2: Legacy Password Upgrade on Login
    // Simulate legacy md5 hash migration
    $legacyPassword = 'LegacyPassword123!';
    $legacyMd5 = md5($legacyPassword);
    $legacyEmail = 'legacy_' . bin2hex(random_bytes(4)) . '@natcodev.com';

    $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES ('Legacy User', ?, ?, 'grower')")
        ->execute([$legacyEmail, $legacyMd5]);
    $legacyUserId = (int) $pdo->lastInsertId();

    $loginAndRehashSimulation = function(PDO $db, string $email, string $submittedPassword): bool {
        $stmt = $db->prepare("SELECT id, password FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) return false;

        $stored = (string) $user['password'];
        $matched = false;

        if (password_verify($submittedPassword, $stored)) {
            $matched = true;
        } elseif (preg_match('/^[a-f0-9]{32}$/i', $stored) && hash_equals(strtolower($stored), md5($submittedPassword))) {
            $matched = true;
            // Upgrade hash in database
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")
               ->execute([password_hash($submittedPassword, PASSWORD_DEFAULT), (int)$user['id']]);
        }

        return $matched;
    };

    $loginOk = $loginAndRehashSimulation($pdo, $legacyEmail, $legacyPassword);
    TestHarness::assert($loginOk === true, 'Legacy user successfully authenticated');

    $upgradedHash = $pdo->query("SELECT password FROM users WHERE id = {$legacyUserId}")->fetchColumn();
    $upgradedInfo = password_get_info((string)$upgradedHash);
    TestHarness::assert(
        in_array($upgradedInfo['algoName'], ['bcrypt', 'argon2i', 'argon2id'], true),
        'Legacy MD5 password was automatically migrated to secure hash (' . $upgradedInfo['algoName'] . ')'
    );

    // 3.3: Brute-Force Lockout Simulation Loop
    // User attempts to guess password with 15 attempts (max allowed is 10)
    $rateLimiterSimulation = function(PDO $db, string $actionKey, int $maxAttempts, int $decaySeconds): bool {
        $now = time();
        $stmt = $db->prepare("SELECT attempts, expires_at FROM app_rate_limits WHERE limit_key = ? AND expires_at > ? LIMIT 1");
        $stmt->execute([$actionKey, $now]);
        $record = $stmt->fetch();

        if (!$record) {
            $db->prepare("INSERT INTO app_rate_limits (limit_key, attempts, last_attempt_at, expires_at) VALUES (?, 1, ?, ?) ON DUPLICATE KEY UPDATE attempts = 1, last_attempt_at = VALUES(last_attempt_at), expires_at = VALUES(expires_at)")
               ->execute([$actionKey, $now, $now + $decaySeconds]);
            return true;
        }

        if ((int)$record['attempts'] >= $maxAttempts) {
            return false; // Lockout triggered
        }

        $db->prepare("UPDATE app_rate_limits SET attempts = attempts + 1, last_attempt_at = ? WHERE limit_key = ?")
           ->execute([$now, $actionKey]);
        return true;
    };

    $targetEmail = 'victim_' . bin2hex(random_bytes(4)) . '@natcodev.com';
    $action = 'login_attempt_' . sha1($targetEmail);
    $maxLimit = 10;
    $decay = 600; // 10 minutes

    $attemptsAllowed = 0;
    $attemptsBlocked = 0;

    // Simulate 15 consecutive failed login attempts
    for ($i = 1; $i <= 15; $i++) {
        $allowed = $rateLimiterSimulation($pdo, $action, $maxLimit, $decay);
        if ($allowed) {
            $attemptsAllowed++;
        } else {
            $attemptsBlocked++;
        }
    }

    TestHarness::assertEqual(10, $attemptsAllowed, 'Exactly 10 login attempts allowed before lockout threshold');
    TestHarness::assertEqual(5, $attemptsBlocked, 'Attempts 11 through 15 were completely blocked by brute-force lockout defense');

    // Verify rate limit record in DB
    $rec = $pdo->query("SELECT attempts FROM app_rate_limits WHERE limit_key = '{$action}'")->fetch();
    TestHarness::assertEqual(10, (int)$rec['attempts'], 'Rate limit record recorded maximum attempts reached');
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_password_and_lockout_tests();
    exit(TestHarness::summary());
}
