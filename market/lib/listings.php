<?php
declare(strict_types=1);

function market_listing_query(PDO $pdo, array $filters = [], int $limit = 24): array
{
    $where = ["l.approval_status = 'approved'", "s.approval_status = 'approved'"];
    $params = [];
    if (!empty($filters['q'])) {
        $where[] = "(l.title LIKE ? OR l.summary LIKE ? OR l.description LIKE ? OR l.location_label LIKE ? OR s.store_name LIKE ? OR s.location_label LIKE ? OR s.coverage_area LIKE ? OR c.name LIKE ?)";
        $like = '%' . $filters['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'l.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['listing_type'])) {
        $where[] = 'l.listing_type = ?';
        $params[] = (string) $filters['listing_type'];
    }
    if (!empty($filters['state'])) {
        $where[] = "(l.location_label LIKE ? OR s.location_label LIKE ? OR s.coverage_area LIKE ?)";
        $like = '%' . (string) $filters['state'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($filters['lga'])) {
        $where[] = "(l.location_label LIKE ? OR s.location_label LIKE ? OR s.coverage_area LIKE ?)";
        $like = '%' . (string) $filters['lga'] . '%';
        array_push($params, $like, $like, $like);
    }
    $sql = "
        SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.seller_type, s.verification_status, s.location_label seller_location
        FROM marketplace_listings l
        JOIN marketplace_sellers s ON s.id = l.seller_id
        LEFT JOIN marketplace_categories c ON c.id = l.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.is_featured DESC, l.created_at DESC
        LIMIT " . max(1, min(80, $limit));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function market_render_listing_card(array $item): void
{
    $id = (int) $item['id'];
    $unit = trim((string) ($item['unit'] ?: $item['price_unit'] ?: 'unit'));
    ?>
    <article class="mk-card">
      <a class="mk-img" href="product.php?id=<?= $id ?>" style="overflow:hidden"><img src="<?= e(market_listing_image_url($item)) ?>" alt="<?= e((string) $item['title']) ?>" style="width:100%;height:100%;object-fit:cover"></a>
      <div class="mk-card-body">
        <div class="mk-badges">
          <span class="mk-badge"><?= e((string) ($item['category_name'] ?: marketplace_status_label((string) $item['listing_type']))) ?></span>
          <?php if ((string) ($item['verification_status'] ?? '') === 'verified'): ?><span class="mk-badge gold">Verified seller</span><?php endif; ?>
        </div>
        <h3><a href="product.php?id=<?= $id ?>"><?= e((string) $item['title']) ?></a></h3>
        <div class="mk-meta"><?= e((string) ($item['summary'] ?: $item['store_name'])) ?></div>
        <div class="mk-price"><?= e(marketplace_money((float) $item['price'])) ?> <small>/ <?= e($unit) ?></small></div>
        <div class="mk-meta"><?= e((string) $item['store_name']) ?> Ã‚Â· <?= e((string) ($item['location_label'] ?: $item['seller_location'] ?: 'Coverage available')) ?></div>
        <div class="mk-actions">
          <a class="mk-btn" href="product.php?id=<?= $id ?>">View / Request</a>
          <a class="mk-btn secondary" href="store.php?seller=<?= e((string) $item['seller_slug']) ?>">Store</a>
        </div>
      </div>
    </article>
<?php
}
