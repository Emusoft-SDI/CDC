#!/usr/bin/env php
<?php
/**
 * binlog-relay.php — Continuous MySQL binary log relay for PITR (Point-In-Time Recovery)
 *
 * Run as a background daemon via systemd or Windows Task Scheduler:
 *   C:\xampp\php\php.exe "C:\path\to\binlog-relay.php"
 *
 * Or call from crontab every minute to check if process is running,
 * and restart it only if needed.
 *
 * Usage:
 *   php binlog-relay.php          # normal mode (runs until stopped)
 *   php binlog-relay.php --test   # run once and exit
 *   php binlog-relay.php --stop   # signal runner to stop gracefully
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$configFile = $projectRoot . '/config.php';
if (!is_file($configFile)) {
    echo "ERROR: config.php not found at {$configFile}\n";
    exit(1);
}
require_once $configFile;

// Simple PDO helper since we may be running outside the app
function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $db = getenv('DB_DATABASE') ?: '';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function dr_safe_relative_path(string $path, string $fallback = '../win-private/binlogs'): string
{
    $path = trim(str_replace('\\', '/', $path), "/ ");
    $path = preg_replace('/[^a-zA-Z0-9_\/.-]/', '', $path) ?: $fallback;
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') continue;
        if ($segment === '..') {
            if (!$segments || end($segments) === '..') {
                $segments[] = '..';
            } else {
                array_pop($segments);
            }
            continue;
        }
        $segments[] = $segment;
    }
    return implode('/', $segments) ?: $fallback;
}

function binlog_dir(PDO $pdo): string
{
    try {
        $settingsTableExists = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='settings'")->fetchColumn();
        if ($settingsTableExists > 0) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name='dr_binlog_storage_path' LIMIT 1");
            $stmt->execute();
            $customPath = trim($stmt->fetchColumn() ?? '');
            if ($customPath !== '') {
                $candidate = realpath(dirname($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '.' : __DIR__)) . '/' . $customPath;
                // Safe path check - simplified
                if (!is_dir($customPath)) {
                    mkdir($customPath, 0750, true);
                }
                return rtrim(realpath($customPath ?: $customPath), '/') . '/';
            }
        }
    } catch (Throwable) {}
    $base = __DIR__;
    $p = dr_safe_relative_path('../win-private/binlogs');
    $target = realpath($base) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $p);
    if (!is_dir($target)) {
        mkdir($target, 0750, true);
    }
    return rtrim(realpath($target), '/') . '/';
}

function binlog_get_pid_file(): string
{
    $tmp = sys_get_temp_dir() . '/natcodev_binlog_relay.pid';
    return is_file($tmp) && file_exists($tmp) ? $tmp : $tmp;
}

function binlog_is_running(): bool
{
    $pidFile = binlog_get_pid_file();
    if (is_file($pidFile)) {
        $pid = (int) trim(file_get_contents($pidFile));
        return $pid > 0 && (@posix_kill($pid, 0) ?? false);
    }
    return false;
}

function binlog_stop_signal(PDO $pdo): void
{
    $signalPath = sys_get_temp_dir() . '/natcodev_binlog_stop.signal';
    file_put_contents($signalPath, time());
}

function get_binlog_position(PDO $pdo): array
{
    $file = '';
    $pos = 0;
    try {
        // Get current binlog position
        $result = $pdo->query("SHOW MASTER STATUS")->fetch(PDO::FETCH_NUM);
        if ($result) {
            $file = (string) $result[0];
            $pos = (int) $result[1];
        }
    } catch (Throwable) {
        // Binary logging may not be enabled — that's OK, warn once
        static $warned = false;
        if (!$warned) {
            echo "WARNING: MySQL binary logging is not enabled. Set expire_logs_days >= 7 and binlog_format=ROW in my.cnf.\n";
            $warned = true;
        }
    }
    return ['file' => $file, 'pos' => $pos];
}

function poll_mysql_binlog(string $mysqlDumpBin, string $binlogDir, int $intervalMs): void
{
    $lastIndex = -1;
    $stopSignal = sys_get_temp_dir() . '/natcodev_binlog_stop.signal';
    $pollSleep = $intervalMs / 1000;

    while (true) {
        // Check stop signal
        if (is_file($stopSignal)) {
            echo date('c') . " Stopping binlog relay (signal received).\n";
            @unlink($stopSignal);
            break;
        }

        try {
            $pdo = get_pdo();
            [$curFile, $curPos] = get_binlog_position($pdo);

            if ($curFile === '' || $curPos === 0) {
                sleep($pollSleep);
                continue;
            }

            // Read index of available binlog files
            $idxFile = $binlogDir . '.index';
            $existingLogs = [];
            if (is_file($idxFile)) {
                $existingLogs = array_filter(array_map('trim', file($idxFile)));
            }

            // Filter out logs already processed
            $newLogs = array_diff($existingLogs, array_keys(array_flip($existingLogs)));

            $changed = false;

            foreach ((array) $existingLogs as $logLine) {
                // Index file stores relative paths like ./mysql-bin.000001
                $relPath = basename(trim($logLine));
                $absPath = $binlogDir . $relPath;
                $metaPath = $absPath . '.idx';

                if (!is_file($absPath)) continue;

                $lastIdx = 0;
                if (is_file($metaPath)) {
                    $lastIdx = (int) trim(file_get_contents($metaPath));
                }

                // Determine endpoint: either last known + size, or curPos
                $endPos = ($relPath === $curFile) ? $curPos : filesize($absPath);

                if ($endPos <= $lastIdx + 4) continue; // skip, nothing new (+4 for binlog header)

                $outPath = $absPath . '.relay';
                $cmd = sprintf(
                    '%s --raw --start-position=%d --stop-position=%d "%s" > "%s" 2>&1',
                    escapeshellarg($mysqlDumpBin),
                    $lastIdx + 4,
                    $endPos,
                    escapeshellarg($absPath),
                    escapeshellarg($outPath)
                );

                $output = [];
                $rc = 0;
                @shell_exec($cmd, $output, $rc);

                if ($rc === 0 && is_file($outPath) && filesize($outPath) > 0) {
                    // Compress the relay segment
                    $gzCmd = sprintf('gzip -f "%s"', escapeshellarg($outPath));
                    @shell_exec($gzCmd);
                    file_put_contents($metaPath, (string) $endPos, LOCK_EX);
                    echo date('c') . " Relayed {$relPath}[{$lastIdx}:{$endPos}] → " . ($outPath . '.gz') . "\n";
                    $changed = true;
                } else {
                    @unlink($outPath);
                }
            }

            if ($changed) {
                // Log to database
                try {
                    $pdo->prepare("INSERT IGNORE INTO dr_backups (backup_ref, backup_type, status, storage_path, file_size, checksum, notes, created_by, started_at, completed_at) VALUES (?, 'binlog_relay', 'completed', ?, 0, '', 'Binary log relay chunk', NULL, NOW(), NOW())")
                        ->execute(['BINLOG-' . date('ymd-His'), $binlogDir . 'relay-manifest.txt']);
                } catch (Throwable) {}
            }
        } catch (Throwable $e) {
            error_log("Binlog relay error: " . $e->getMessage());
        }

        usleep($intervalMs * 1000);
    }

    // Cleanup PID
    $pidFile = binlog_get_pid_file();
    if (is_file($pidFile)) @unlink($pidFile);
}

// ---- Main entry point ----
$mode = $argv[1] ?? 'run';

// Write PID
$pidFile = binlog_get_pid_file();
file_put_contents($pidFile, (string) getmypid(), LOCK_EX);

// Find mysqlbinlog
$mysqlbinlog = null;
foreach ([
    '/usr/bin/mysqlbinlog',
    '/usr/local/bin/mysqlbinlog',
    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqlbinlog.exe',
    'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqlbinlog.exe',
    'C:\\xampp\\mysql\\bin\\mysqlbinlog.exe',
] as $candidate) {
    if (is_executable($candidate)) {
        $mysqlbinlog = $candidate;
        break;
    }
}
if ($mysqlbinlog === null && function_exists('shell_exec')) {
    $q = PHP_OS_FAMILY === 'Windows' ? 'where mysqlbinlog' : 'which mysqlbinlog';
    $out = trim((string) @shell_exec($q));
    if ($out !== '' && strpos($out, 'not found') === false) {
        $firstLine = explode("\n", $out)[0];
        if (is_executable(trim($firstLine))) $mysqlbinlog = trim($firstLine);
    }
}

if ($mysqlbinlog === null && $mode === 'run') {
    echo "ERROR: mysqlbinlog not found. Install MySQL client tools.\n";
    exit(1);
}

$pdo = get_pdo();
$bdir = binlog_dir($pdo);

switch ($mode) {
    case 'test':
        // Single pass test
        [$curFile, $curPos] = get_binlog_position($pdo);
        echo "Current binlog: {$curFile}, position: {$curPos}\n";
        echo "Relay directory: {$bdir}\n";
        echo "OK\n";
        exit(0);

    case 'stop':
        binlog_stop_signal($pdo);
        echo "Stop signal sent.\n";
        exit(0);

    case 'status':
        $pid = @file_get_contents($pidFile);
        $running = $pid !== false && @posix_kill((int) $pid, 0);
        echo "PID: {$pid}\nRunning: " . ($running ? 'yes' : 'no') . "\n";
        [$cf, $cp] = get_binlog_position($pdo);
        echo "Binlog: {$cf}, pos: {$cp}\n";
        if (is_dir($bdir)) {
            $count = count(glob($bdir . '*.relay.gz') ?: []);
            echo "Relay chunks: {$count}\n";
        }
        exit(0);

    default:
        echo date('c') . " Starting binlog relay (PID: " . getmypid() . ")\n";
        echo date('c') . " Directory: {$bdir}\n";
        poll_mysql_binlog($mysqlbinlog, $bdir, 5000); // poll every 5 seconds
        exit(0);
}
