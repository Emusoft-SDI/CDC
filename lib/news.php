<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Ensures the news, version history, engagement feedback, and analytics tables exist.
 */
function news_ensure_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    // 1. Core News & Announcements Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coop_news (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            summary TEXT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'General',
            status ENUM('draft', 'published', 'scheduled', 'archived') NOT NULL DEFAULT 'published',
            priority ENUM('normal', 'urgent') NOT NULL DEFAULT 'normal',
            scheduled_at DATETIME NULL,
            visibility ENUM('public', 'internal', 'both') NOT NULL DEFAULT 'both',
            content LONGTEXT NOT NULL,
            image_url VARCHAR(255) NULL,
            author_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_news_status (status, visibility, created_at),
            INDEX idx_news_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. Version Snapshots for Rollback & Audit
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coop_news_versions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            news_id INT UNSIGNED NOT NULL,
            title_snapshot VARCHAR(255) NOT NULL,
            summary_snapshot TEXT NULL,
            content_snapshot LONGTEXT NOT NULL,
            editor_id INT UNSIGNED NULL,
            version_number INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_news_versions_news (news_id, version_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. User Feedback & Engagement (Comments & Live Poll Votes)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coop_news_feedback (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            news_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            feedback_type ENUM('comment', 'poll_vote') NOT NULL DEFAULT 'comment',
            content TEXT NOT NULL,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_news_feedback_news (news_id, feedback_type, is_hidden),
            INDEX idx_news_feedback_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 4. Analytics & View Tracking
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coop_news_analytics (
            news_id INT UNSIGNED PRIMARY KEY,
            views_count INT UNSIGNED NOT NULL DEFAULT 0,
            clicks_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed initial news articles if table is empty
    $count = (int) $pdo->query("SELECT COUNT(*) FROM coop_news")->fetchColumn();
    if ($count === 0) {
        $seedPosts = [
            [
                'title' => 'New Climate-Smart Coconut Propagation Training Module Launched',
                'slug' => 'new-climate-smart-training-module-launched',
                'summary' => 'Learn how to maximize yield even in changing weather conditions. Free for all registered NATCODEV members and outgrowers.',
                'category' => 'Training',
                'priority' => 'normal',
                'content' => '<p>Farming in a changing climate presents unique challenges and exceptional opportunities. Our new curriculum covers soil fertility management, drip irrigation techniques, organic crop preservation, and smart tree canopy planning.</p><p>All registered growers and outgrowers can access the module directly through the <a href="academy/index.php">NATCODEV Academy</a>.</p>',
                'image_url' => 'assets/public/natcodev-community-impact.png',
            ],
            [
                'title' => 'National Coconut Offtake Partnership Signed with Major Exporters',
                'slug' => 'partnership-signed-with-major-exporters',
                'summary' => 'We have secured a landmark off-take agreement ensuring premium floor pricing for Grade-A coconuts across all 36 states this harvest season.',
                'category' => 'Market',
                'priority' => 'urgent',
                'content' => '<p>NATCODEV leadership is proud to announce an institutional framework partnership with leading agro-exporters and processing facilities.</p><p>This initiative guarantees consistent buying demand, transparent digital escrow settlements, and minimized post-harvest losses for our outgrower network.</p>',
                'image_url' => 'assets/public/natcodev-home-hero.png',
            ],
            [
                'title' => 'Digital Farmer Identity & Registry: Why Verification Unlocks Value',
                'slug' => 'digital-farmer-id-why-it-matters',
                'summary' => 'Your digital ID is more than an identity card — it is your digital passport to subsidized inputs, certified seed nuts, and grant allocations.',
                'category' => 'Registry',
                'priority' => 'normal',
                'content' => '<p>The NATCODEV Verified Grower Registry links your verified biometric and farm geospatial coordinates with national extension support services.</p><p>Ensure your profile KYC, farm boundaries, and phone verification are completed on your personal dashboard.</p>',
                'image_url' => 'assets/public/natcodev-community-impact.png',
            ],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO coop_news (title, slug, summary, category, status, priority, visibility, content, image_url, created_at)
            VALUES (?, ?, ?, ?, 'published', ?, 'both', ?, ?, NOW())
        ");
        foreach ($seedPosts as $p) {
            $stmt->execute([
                $p['title'],
                $p['slug'],
                $p['summary'],
                $p['category'],
                $p['priority'],
                $p['content'],
                $p['image_url'],
            ]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO coop_news_analytics (news_id, views_count, clicks_count) VALUES (?, 120, 45)")->execute([$newId]);
        }
    }

    $ensured = true;
}

/**
 * Fetch published news articles with analytics metadata.
 */
function news_get_published(PDO $pdo, int $limit = 20, string $visibility = 'public'): array
{
    news_ensure_schema($pdo);
    $visClause = $visibility === 'public' ? "visibility IN ('public', 'both')" : "visibility IN ('internal', 'both')";
    if ($visibility === 'all') {
        $visClause = "1=1";
    }

    $stmt = $pdo->prepare("
        SELECT n.*, COALESCE(a.views_count, 0) AS views_count, COALESCE(a.clicks_count, 0) AS clicks_count
        FROM coop_news n
        LEFT JOIN coop_news_analytics a ON n.id = a.news_id
        WHERE n.status = 'published' AND {$visClause}
        ORDER BY (n.priority = 'urgent') DESC, n.created_at DESC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch a single news post by slug with permission checks.
 */
function news_get_by_slug(PDO $pdo, string $slug, bool $isAdmin = false, bool $isMember = false): ?array
{
    news_ensure_schema($pdo);
    if ($isAdmin) {
        $stmt = $pdo->prepare("
            SELECT n.*, COALESCE(a.views_count, 0) AS views_count, COALESCE(a.clicks_count, 0) AS clicks_count
            FROM coop_news n
            LEFT JOIN coop_news_analytics a ON n.id = a.news_id
            WHERE n.slug = ? LIMIT 1
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $visClause = $isMember ? "visibility IN ('internal', 'both')" : "visibility IN ('public', 'both')";
    $stmt = $pdo->prepare("
        SELECT n.*, COALESCE(a.views_count, 0) AS views_count, COALESCE(a.clicks_count, 0) AS clicks_count
        FROM coop_news n
        LEFT JOIN coop_news_analytics a ON n.id = a.news_id
        WHERE n.slug = ? AND n.status = 'published' AND {$visClause}
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
