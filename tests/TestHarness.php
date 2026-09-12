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
     * Return active MySQL database PDO instance and prepare test schema
     */
    public static function createTestDb(): PDO
    {
        $pdo = db();
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
