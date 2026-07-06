<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('search', 'Provider Search', 'Search products, orders, support tickets, coverage, and provider workspace tools.', function (PDO $pdo, array $user, array $provider): void {
    $q = trim((string) ($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $results = [];
    if ($q !== '') {
        $stmt = $pdo->prepare("
            SELECT 'Offering' type, name title, CONCAT(offering_type, ' / ', category, ' / ', stock_status) description, 'products.php' href
            FROM provider_offerings
            WHERE provider_id = ? AND (name LIKE ? OR category LIKE ? OR description LIKE ? OR offering_type LIKE ?)
            ORDER BY created_at DESC
            LIMIT 25
        ");
        $stmt->execute([(int) $provider['id'], $like, $like, $like, $like]);
        $results = array_merge($results, $stmt->fetchAll());
    }

    foreach ([
        ['Products & Services', 'Manage provider products, services, training offers, stock, and quotes.', 'products.php', 'products services offerings stock quote'],
        ['Orders', 'Review marketplace and provider fulfillment requests.', 'orders.php', 'orders fulfillment delivery buyers'],
        ['Coverage', 'Manage states, LGAs, delivery scope, and provider operating areas.', 'coverage.php', 'coverage states lga locations service area'],
        ['Support', 'Provider help desk tickets and responses.', 'support.php', 'support ticket help message'],
        ['Wallet', 'Provider wallet, payments, refunds, payouts, and records.', 'wallet.php', 'wallet payment payout refund finance'],
        ['Academy', 'Provider accreditation and learning records.', 'academy.php', 'academy training accreditation certificate'],
        ['Profile', 'Company profile, contacts, documents, and password.', 'profile.php', 'profile company contact password documents'],
    ] as [$title, $description, $href, $tags]) {
        if ($q === '' || stripos($title . ' ' . $description . ' ' . $tags, $q) !== false) {
            $results[] = ['type' => 'Workspace', 'title' => $title, 'description' => $description, 'href' => $href];
        }
    }
    ?>
    <form class="card" method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
      <input name="q" value="<?= e($q) ?>" placeholder="Search orders, requests, products, customers..." style="flex:1;min-width:220px">
      <button class="btn" type="submit">Search</button>
    </form>
    <section class="card"><div class="list">
      <?php foreach ($results as $row): ?><a class="row" href="<?= e((string) $row['href']) ?>"><span><strong><?= e((string) $row['title']) ?></strong><br><small><?= e((string) $row['description']) ?></small></span><span class="badge"><?= e((string) $row['type']) ?></span></a><?php endforeach; ?>
      <?php if ($q === ''): ?><div class="notice ok">Type a search term to find provider records and pages.</div><?php elseif (!$results): ?><div class="notice ok">No provider result matched your search.</div><?php endif; ?>
    </div></section>
    <?php
});
