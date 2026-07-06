<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';
buyer_simple_page('quotes', 'Quote Requests', 'View seller questions, custom delivery requests, and bulk pricing conversations.', function(PDO $pdo, ?array $user): void {
    echo '<div class="card"><div class="card-head"><h2>Requests</h2><a class="btn" href="../market/index.php">Ask a Seller</a></div>';
    if (!$user) { echo '<p>Login to see quote requests tied to your buyer account.</p></div>'; return; }
    $stmt = $pdo->prepare("SELECT i.*, l.title FROM marketplace_inquiries i JOIN marketplace_listings l ON l.id=i.listing_id WHERE i.buyer_user_id=? ORDER BY i.created_at DESC LIMIT 20");
    $stmt->execute([(int) $user['id']]);
    foreach ($stmt->fetchAll() as $row) { echo '<div class="row"><span><strong>' . e((string) $row['title']) . '</strong><br><small>' . e((string) $row['inquiry_ref']) . '</small></span><span class="badge">' . e(marketplace_status_label((string) $row['status'])) . '</span></div>'; }
    echo '</div>';
});
?>
