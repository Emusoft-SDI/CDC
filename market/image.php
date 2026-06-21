<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT l.title, l.listing_type, c.name category_name
    FROM marketplace_listings l
    LEFT JOIN marketplace_categories c ON c.id = l.category_id
    WHERE l.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch() ?: ['title' => 'NATCODEV Marketplace', 'listing_type' => 'product', 'category_name' => 'Marketplace'];
$title = mb_substr((string) $row['title'], 0, 48);
$category = (string) ($row['category_name'] ?: marketplace_status_label((string) $row['listing_type']));
$colors = [
    'product' => ['#1a5f2a', '#dff4e2'],
    'service' => ['#0f766e', '#dff6f2'],
    'equipment' => ['#1d4ed8', '#dbeafe'],
    'labor' => ['#b45309', '#fff7d6'],
    'procurement' => ['#7c3aed', '#ede9fe'],
];
[$dark, $light] = $colors[(string) $row['listing_type']] ?? $colors['product'];

header('Content-Type: image/svg+xml; charset=utf-8');
?>
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="520" viewBox="0 0 800 520">
  <rect width="800" height="520" fill="<?= e($light) ?>"/>
  <circle cx="650" cy="90" r="120" fill="<?= e($dark) ?>" opacity="0.12"/>
  <circle cx="120" cy="430" r="150" fill="<?= e($dark) ?>" opacity="0.10"/>
  <rect x="54" y="54" width="692" height="412" rx="34" fill="#ffffff" opacity="0.86"/>
  <path d="M254 298c84-168 204-168 288 0-78-38-210-38-288 0Z" fill="<?= e($dark) ?>" opacity="0.92"/>
  <path d="M400 124c-42 58-42 107 0 147 42-40 42-89 0-147Z" fill="<?= e($dark) ?>" opacity="0.82"/>
  <text x="80" y="98" font-family="Arial, sans-serif" font-size="24" font-weight="700" fill="<?= e($dark) ?>">NATCODEV MARKETPLACE</text>
  <text x="80" y="398" font-family="Arial, sans-serif" font-size="34" font-weight="800" fill="#122018"><?= e($title) ?></text>
  <text x="80" y="438" font-family="Arial, sans-serif" font-size="20" font-weight="700" fill="<?= e($dark) ?>"><?= e($category) ?></text>
</svg>
