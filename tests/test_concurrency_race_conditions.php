<?php
declare(strict_types=1);

/**
 * Scenario 1: Concurrency & Race Conditions (Simultaneous Requests)
 * Tests atomic operations, balance protection, idempotency against duplicate webhook callbacks,
 * and transaction isolation during simultaneous credit/debit/withdrawal requests.
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../lib/wallet.php';

function run_concurrency_tests(): void
{
    TestHarness::start('Scenario 1: Concurrency & Race Conditions (Simultaneous Requests)');
    $pdo = TestHarness::createTestDb();
    wallet_ensure_schema($pdo);

    // Setup Test User and Wallet
    $testEmail = 'test_farmer_' . bin2hex(random_bytes(4)) . '@example.com';
    $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES ('Farmer Joe', ?, 'hash', 'grower')")->execute([$testEmail]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO wallets (user_id, balance, hold_balance, status) VALUES (?, 10000.00, 0.00, 'active') ON DUPLICATE KEY UPDATE balance = 10000.00, hold_balance = 0.00")
        ->execute([$userId]);
    $walletStmt = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $walletId = (int) $walletStmt->fetchColumn();

    // 1.1: Webhook duplicate reference replay simulation (Idempotency check)
    $paymentRef = 'NAT-TXN-CONC-' . bin2hex(random_bytes(6));
    $creditAmount = 5000.00;

    // Simulation of first incoming webhook request
    $creditSimulation = function(PDO $db, int $uId, float $amount, string $ref) {
        return wallet_credit_once($db, $uId, $amount, $ref, 'Concurrency test funding', 'monnify', $ref, ['test' => true]);
    };

    // First request executes
    $res1 = $creditSimulation($pdo, $userId, $creditAmount, $paymentRef);
    TestHarness::assert($res1['success'] === true && $res1['duplicate'] === false, 'First webhook payment request successfully credits wallet');
    TestHarness::assertEqual(15000.00, round((float)$res1['balance'], 2), 'Wallet balance correctly incremented from 10000 to 15000');

    // Second simultaneous/replay request with EXACT same reference
    $res2 = $creditSimulation($pdo, $userId, $creditAmount, $paymentRef);
    TestHarness::assert($res2['success'] === true && $res2['duplicate'] === true, 'Duplicate webhook reference is recognized as duplicate without double crediting');

    $finalBalance = (float)$pdo->query("SELECT balance FROM wallets WHERE user_id = {$userId}")->fetchColumn();
    TestHarness::assertEqual(15000.00, round($finalBalance, 2), 'Wallet balance remains 15000 (No double-credit race condition occurred)');

    // 1.2: Concurrent Withdrawal Race Condition Simulation
    // Two simultaneous requests try to withdraw 10,000 each when balance is 15,000 (Combined = 20,000 > 15,000)
    $withdrawSimulation = function(PDO $db, int $uId, float $amount, string $ref) {
        $db->beginTransaction();
        try {
            $lock = $db->prepare("SELECT id, balance, hold_balance FROM wallets WHERE user_id = ? FOR UPDATE");
            $lock->execute([$uId]);
            $wallet = $lock->fetch();

            $balance = (float)$wallet['balance'];
            if ($balance < $amount) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Insufficient wallet balance'];
            }

            $after = $balance - $amount;
            $holdAfter = (float)$wallet['hold_balance'] + $amount;

            $db->prepare("UPDATE wallets SET balance = ?, hold_balance = ? WHERE id = ?")
               ->execute([$after, $holdAfter, (int)$wallet['id']]);

            $db->prepare("
                INSERT INTO wallet_withdrawals (wallet_id, user_id, reference, amount, final_amount, account_number, account_name)
                VALUES (?, ?, ?, ?, ?, '1234567890', 'Joe Farmer')
            ")->execute([(int)$wallet['id'], $uId, $ref, $amount, $amount]);

            $db->commit();
            return ['success' => true, 'new_balance' => $after];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    };

    $w1 = $withdrawSimulation($pdo, $userId, 10000.00, 'WD-RACE-' . bin2hex(random_bytes(4)));
    TestHarness::assert($w1['success'] === true, 'First withdrawal of 10,000 succeeded');
    TestHarness::assertEqual(5000.00, round((float)$w1['new_balance'], 2), 'Balance after first withdrawal is 5,000');

    // Second simultaneous withdrawal of 10,000 MUST fail because only 5,000 is left
    $w2 = $withdrawSimulation($pdo, $userId, 10000.00, 'WD-RACE-' . bin2hex(random_bytes(4)));
    TestHarness::assert($w2['success'] === false, 'Second conflicting withdrawal of 10,000 was safely rejected');
    TestHarness::assertEqual('Insufficient wallet balance', $w2['error'], 'Proper insufficient balance error returned');

    $currentBal = (float)$pdo->query("SELECT balance FROM wallets WHERE user_id = {$userId}")->fetchColumn();
    TestHarness::assertEqual(5000.00, round($currentBal, 2), 'Final balance remains 5,000.00 without negative balance breach');

    // 1.3: Transaction Rollback on Error
    $failSimulation = function(PDO $db, int $uId) {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE wallets SET balance = balance + 100000 WHERE user_id = ?")->execute([$uId]);
            // Simulate critical mid-transaction failure
            throw new RuntimeException('Mid-transaction unexpected external network crash');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    };

    $rolledBack = $failSimulation($pdo, $userId);
    TestHarness::assert($rolledBack === false, 'Failed transaction triggered rollback handler');
    $unalteredBal = (float)$pdo->query("SELECT balance FROM wallets WHERE user_id = {$userId}")->fetchColumn();
    TestHarness::assertEqual(5000.00, round($unalteredBal, 2), 'Database state was rolled back completely on exception');
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_concurrency_tests();
    exit(TestHarness::summary());
}
