<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/marketplace.php';
require_once __DIR__ . '/../auth.php';

require_api_user();

try {
    $pdo = db();
    marketplace_ensure_schema($pdo);
    $stmt = $pdo->query("
        SELECT
            l.id,
            l.title,
            l.summary,
            l.description,
            l.price,
            l.price_unit,
            l.listing_type,
            l.availability_status,
            c.name category,
            s.store_name seller_name,
            s.slug seller_slug
        FROM marketplace_listings l
        JOIN marketplace_sellers s ON s.id = l.seller_id
        LEFT JOIN marketplace_categories c ON c.id = l.category_id
        WHERE l.approval_status = 'approved'
          AND l.availability_status <> 'paused'
          AND s.approval_status = 'approved'
        ORDER BY l.is_featured DESC, l.created_at DESC
        LIMIT 100
    ");
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Marketplace API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
