<?php
declare(strict_types=1);

// NATCODEV News & Announcements Cron Scheduler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/news.php';

function news_cron_process_matured_posts(PDO $pdo): array
{
    news_ensure_schema($pdo);

    $results = [
        'checked_at' => date('Y-m-d H:i:s'),
        'found_count' => 0,
        'published_count' => 0,
        'published_titles' => [],
        'errors' => [],
    ];

    try {
        // Select all scheduled posts that have matured
        $stmt = $pdo->prepare("
            SELECT id, title, scheduled_at 
            FROM coop_news 
            WHERE status = 'scheduled' AND scheduled_at <= NOW()
        ");
        $stmt->execute();
        $scheduledPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results['found_count'] = count($scheduledPosts);
        if (empty($scheduledPosts)) {
            return $results;
        }

        foreach ($scheduledPosts as $post) {
            $pdo->beginTransaction();
            try {
                // Update post status to published
                $update = $pdo->prepare("
                    UPDATE coop_news 
                    SET status = 'published', created_at = NOW() 
                    WHERE id = ?
                ");
                $update->execute([$post['id']]);

                // Create platform broadcast / alert entries
                if (app_table_exists($pdo, 'platform_broadcasts')) {
                    $stmtBroadcast = $pdo->prepare("
                        INSERT INTO platform_broadcasts (scope, audience, title, message, channel, priority, status, published_at)
                        VALUES ('national', 'all', ?, 'A new NATCODEV update has been published. Read it on the News Desk!', 'in_app', 'normal', 'published', NOW())
                    ");
                    $stmtBroadcast->execute([$post['title']]);
                }

                $pdo->commit();
                $results['published_count']++;
                $results['published_titles'][] = $post['title'];
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $results['errors'][] = "Error publishing article ID {$post['id']}: " . $ex->getMessage();
            }
        }
    } catch (Throwable $e) {
        $results['errors'][] = "Critical scheduler failure: " . $e->getMessage();
    }

    return $results;
}

// Standalone execution handler
$isDirectExecution = (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
    || (isset($_SERVER['argv'][0]) && realpath($_SERVER['argv'][0]) === realpath(__FILE__));

if ($isDirectExecution) {
    $pdo = db();
    if (php_sapi_name() !== 'cli') {
        require_once __DIR__ . '/_auth.php';
        admin_require($pdo);
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "NATCODEV Communications Cron Scheduler Initiated...\n";
    $stats = news_cron_process_matured_posts($pdo);
    if ($stats['found_count'] === 0) {
        echo "No scheduled announcements require publishing at this time.\n";
    } else {
        echo "Found {$stats['found_count']} matured campaigns to publish.\n";
        foreach ($stats['published_titles'] as $title) {
            echo "SUCCESS: Auto-published '{$title}'\n";
        }
        foreach ($stats['errors'] as $err) {
            echo "ERROR: {$err}\n";
        }
    }
    echo "Cron Scheduler Complete.\n";
}
