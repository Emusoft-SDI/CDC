<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$query = trim((string) ($_GET['q'] ?? ''));
$terms = array_values(array_filter(preg_split('/\s+/', strtolower($query)) ?: []));

$catalog = [
    ['Dashboard', 'Platform command center and module hub.', 'index.php', 'dashboard home overview'],
    ['Registry', 'Growers, applications, stakeholders, documents, and certificates.', 'registry.php', 'registry growers applications documents certificates'],
    ['Applications', 'Review and export grower applications.', 'admin.php', 'applications growers export review'],
    ['Import & Engagement', 'Bulk CSV/XLSX import and row-by-row engagement audit.', 'import-users.php', 'import csv xlsx users engagement'],
    ['Document Verification', 'Review uploaded identity and farm evidence.', 'document-verification.php', 'documents verification uploads evidence'],
    ['Marketplace', 'Seller, listing, order, promotion, payout, and dispute oversight.', 'marketplace.php', 'marketplace sellers listings orders payouts'],
    ['Academy', 'Courses, learners, certificates, refunds, reports, and materials.', 'academy.php', 'academy courses learners reports certificates'],
    ['Wallet', 'Transactions, refunds, payouts, reconciliation, exports, and settings.', 'wallet.php', 'wallet payments refunds payouts reconciliation export'],
    ['Support', 'Tickets, SLA queues, messaging, and support analytics.', 'support.php', 'support tickets sla messages'],
    ['Reports', 'Operational reports, exports, generated runs, and analytics.', 'reports.php', 'reports analytics export generate'],
    ['Settings', 'Modules, roles, integrations, notifications, backup, and audit controls.', 'settings.php', 'settings modules roles integrations audit'],
    ['Users', 'Staff and stakeholder account management.', 'users.php', 'users accounts roles staff'],
    ['Resources', 'Training files and offline resources.', 'resources.php', 'resources uploads training'],
];

$results = [];
foreach ($catalog as [$title, $description, $href, $tags]) {
    $haystack = strtolower($title . ' ' . $description . ' ' . $tags);
    $score = $query === '' ? 1 : 0;
    foreach ($terms as $term) {
        if (str_contains($haystack, $term)) {
            $score += str_contains(strtolower($title), $term) ? 4 : 1;
        }
    }
    if ($score > 0) {
        $results[] = compact('title', 'description', 'href', 'score');
    }
}
usort($results, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp($a['title'], $b['title']));

admin_page_start('Admin Search', [
    'active' => 'index.php',
    'description' => 'Find admin modules, imports, exports, generated reports, and operational workspaces.',
    'css' => '.admin-search{display:grid;gap:14px}.search-form{display:flex;gap:10px}.search-form input{min-height:46px;flex:1}.search-result{display:block;border:1px solid var(--line);background:#fff;border-radius:8px;padding:15px;color:inherit}.search-result:hover{text-decoration:none;border-color:#acd8bf;box-shadow:0 12px 28px rgba(16,24,40,.08)}.search-result strong{display:block;color:#101828}.search-result span{display:block;color:var(--muted);margin-top:4px}@media(max-width:700px){.search-form{display:grid}}',
]);
?>
<form class="search-form panel" method="get">
  <input name="q" value="<?= e($query) ?>" placeholder="Search admin modules, exports, imports, reports...">
  <button type="submit">Search</button>
</form>
<section class="admin-search">
  <?php foreach ($results as $item): ?>
    <a class="search-result" href="<?= e((string) $item['href']) ?>">
      <strong><?= e((string) $item['title']) ?></strong>
      <span><?= e((string) $item['description']) ?></span>
    </a>
  <?php endforeach; ?>
  <?php if (!$results): ?><div class="panel">No admin results matched your search.</div><?php endif; ?>
</section>
<?php admin_page_end(); ?>
