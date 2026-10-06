<?php
declare(strict_types=1);

/**
 * NATCODEV Security Test Harness
 * Provides assertions, colorized terminal reporting, and test database fixtures
 * Runs natively in PHP CLI using the active MySQL engine.
 */

require_once __DIR__ . '/../config.php';

class TestHarness
{
    private static int $passed = 0;
    private static int $failed = 0;
    private static array $errors = [];
    private static float $startTime = 0.0;

    public static function start(string $suiteName): void
    {
        self::$startTime = microtime(true);
        echo "\n\033[1;36m========================================================\033[0m\n";
        echo "\033[1;36m [SUITE] {$suiteName}\033[0m\n";
        echo "\033[1;36m========================================================\033[0m\n\n";
    }

    public static function assert(bool $condition, string $description, string $failureDetails = ''): void
    {
        if ($condition) {
            self::$passed++;
            echo " \033[1;32m✓ PASS:\033[0m {$description}\n";
        } else {
            self::$failed++;
            $msg = $failureDetails !== '' ? " ({$failureDetails})" : '';
            self::$errors[] = "{$description}{$msg}";
            echo " \033[1;31m✗ FAIL:\033[0m {$description}{$msg}\n";
        }
    }

    public static function assertEqual(mixed $expected, mixed $actual, string $description): void
    {
        $condition = ($expected === $actual);
        $details = $condition ? '' : "Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true);
        self::assert($condition, $description, $details);
    }

    public static function assertThrows(callable $callback, string $expectedExceptionClass, string $description): void
    {
        try {
            $callback();
            self::assert(false, $description, "Expected exception {$expectedExceptionClass} was not thrown");
        } catch (Throwable $e) {
            $isInstanceOf = ($e instanceof $expectedExceptionClass);
            self::assert($isInstanceOf, $description, "Expected {$expectedExceptionClass}, got " . get_class($e) . ": " . $e->getMessage());
        }
    }

    public static function summary(): int
    {
        $duration = round(microtime(true) - self::$startTime, 3);
        $total = self::$passed + self::$failed;

        echo "\n\033[1;36m--------------------------------------------------------\033[0m\n";
        echo "\033[1;37m Tests Run: {$total} | Passed: \033[1;32m" . self::$passed . "\033[1;37m | Failed: \033[1;" . (self::$failed > 0 ? "31m" : "32m") . self::$failed . "\033[1;37m | Time: {$duration}s\033[0m\n";
        echo "\033[1;36m--------------------------------------------------------\033[0m\n";

        if (self::$failed > 0) {
            echo "\033[1;31mFailed Assertions Summary:\033[0m\n";
            foreach (self::$errors as $idx => $err) {
                echo "  " . ($idx + 1) . ") {$err}\n";
            }
            echo "\n";
            return 1;
        }

        echo "\033[1;32mAll security assertions passed successfully!\033[0m\n\n";
        return 0;
    }

    /**
     * Refuse to run against anything that is not clearly a test database.
     *
     * The suites do not just read: they insert users, articles, listings, orders and
     * payments. Run without DB_DATABASE set they wrote all of that into the live
     * database — it was found holding 732 generated fixture accounts, 69 fixture
     * rows repeating the same three news posts 31/26/15 times, and 33 copies of one
     * marketplace listing. This makes that impossible to do by accident.
     *
     * A database qualifies only if its name is test-scoped, e.g. natcodevcom_data_test.
     * There is deliberately no environment override: a test run must never be able to
     * write into the live database, so the guard always exits instead of trusting a
     * flag. If you need to run the suites locally, point DB_DATABASE at an isolated
     * test database (the name must contain "test").
     */
    public static function assertTestDatabase(PDO $pdo): void
    {
        $name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

        if ($name !== '' && preg_match('/(^|_)test(_|$)/i', $name)) {
            return;
        }

        $message = "REFUSING TO RUN: the connected database is '{$name}', which is not a test database.\n"
            . "These suites INSERT users, news, listings, orders and payments, so they are\n"
            . "blocked from writing to any database that is not test-scoped.\n\n"
            . "Point them at an isolated database instead:\n"
            . "  DB_DATABASE=<your_test_db> php tests/run_security_suite.php\n";

        fwrite(STDERR, "\033[1;31m" . $message . "\033[0m");
        exit(2);
    }

    /**
     * Return active MySQL database PDO instance and prepare test schema
     */
    public static function createTestDb(): PDO
    {
        $pdo = db();
        self::assertTestDatabase($pdo);
        app_ensure_core_schema($pdo);
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                limit_key VARCHAR(191) NOT NULL UNIQUE,
                attempts INT NOT NULL DEFAULT 1,
                last_attempt_at INT NOT NULL,
                expires_at INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS test_rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                limit_key VARCHAR(191) NOT NULL UNIQUE,
                attempts INT NOT NULL DEFAULT 1,
                last_attempt_at INT NOT NULL,
                expires_at INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        return $pdo;
    }
}

/*
 * Guard at include time, not just inside createTestDb().
 *
 * Not every suite goes through createTestDb(): four of them call db() directly, and
 * three of those did not load this file at all. Putting the check here means any
 * suite that includes the harness is protected before it can write anything, however
 * it obtains its connection.
 */
TestHarness::assertTestDatabase(db());
