<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/admin-search.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$query = trim((string) ($_GET['q'] ?? ''));
$format = (string) ($_GET['format'] ?? 'html');
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

// Phase 4 wiring: real records from the unified search index (command palette + page).
$records = [];
if ($query !== '') {
    foreach (admin_search_query($pdo, $query, 25) as $row) {
        $records[] = [
            'title' => (string) $row['title'],
            'description' => (string) ($row['subtitle'] ?? ''),
            'href' => (string) $row['url'],
            'kind' => (string) ($row['entity_type'] ?? 'record'),
        ];
    }
}

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'query' => $query,
        'modules' => array_map(static fn(array $r): array => ['title' => $r['title'], 'href' => $r['href']], $results),
        'records' => $records,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Saved views: load the current admin's views and handle save/delete actions.
$userId = admin_current_user_id($pdo);
$savedViews = $userId !== null ? admin_saved_views($pdo, $userId, 'search') : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
    if ($userId !== null) {
        $action = (string) ($_POST['action'] ?? '');
        $target = trim((string) ($_POST['q'] ?? ''));
        if ($action === 'save_view') {
            $name = mb_substr(trim((string) ($_POST['view_name'] ?? '')), 0, 120);
            if ($name !== '') {
                admin_save_view($pdo, $userId, 'search', $name, ['q' => $target]);
            }
            redirect_to('search.php?q=' . urlencode($target) . '&saved=1');
        }
        if ($action === 'delete_view') {
            $viewId = (int) ($_POST['view_id'] ?? 0);
            if ($viewId > 0 && app_table_exists($pdo, 'admin_saved_views')) {
                $pdo->prepare('DELETE FROM admin_saved_views WHERE id = ? AND user_id = ?')
                    ->execute([$viewId, $userId]);
            }
            redirect_to('search.php?q=' . urlencode($target) . '&deleted=1');
        }
    }
}

admin_page_start('Admin Search', [
    'active' => 'index.php',
    'description' => 'Find admin modules, imports, exports, generated reports, and operational workspaces.',
    'css' => '.admin-search{display:grid;gap:14px}.search-form{display:flex;gap:10px}.search-form input{min-height:46px;flex:1}.search-result{display:block;border:1px solid var(--line);background:#fff;border-radius:8px;padding:15px;color:inherit}.search-result:hover{text-decoration:none;border-color:#acd8bf;box-shadow:0 12px 28px rgba(16,24,40,.08)}.search-result strong{display:block;color:#101828}.search-result span{display:block;color:var(--muted);margin-top:4px}.search-kind{display:inline-flex;border-radius:999px;padding:2px 8px;font-size:.68rem;font-weight:900;background:#e8f6ec;color:#0f6b3c;margin-left:6px}.saved-view{display:flex;gap:10px;align-items:stretch}.saved-view .search-result{flex:1}.saved-view form{margin:0}.saved-view button{min-height:46px}@media(max-width:700px){.search-form{display:grid}.saved-view{display:grid}}',
]);
?>
<form class="search-form panel" method="get">
  <input name="q" value="<?= e($query) ?>" placeholder="Search admin modules, exports, imports, reports...">
  <button type="submit">Search</button>
</form>
<?php if ((string) ($_GET['saved'] ?? '') === '1'): ?><div class="panel">Saved view saved.</div><?php endif; ?>
<?php if ((string) ($_GET['deleted'] ?? '') === '1'): ?><div class="panel">Saved view deleted.</div><?php endif; ?>
<?php if ($query !== '' && $userId !== null): ?>
<form class="search-form panel" method="post" action="search.php">
  <input type="hidden" name="action" value="save_view">
  <input type="hidden" name="q" value="<?= e($query) ?>">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="view_name" placeholder="Name this view..." maxlength="120" required>
  <button type="submit">Save this view</button>
</form>
<?php endif; ?>
<?php if ($records): ?>
<section class="admin-search">
  <h3>Matching records (<?= count($records) ?>)</h3>
  <?php foreach ($records as $item): ?>
    <a class="search-result" href="<?= e($item['href']) ?>">
      <strong><?= e($item['title']) ?><span class="search-kind"><?= e($item['kind']) ?></span></strong>
      <span><?= e($item['description']) ?></span>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($savedViews): ?>
<section class="admin-search">
  <h3>Saved views (<?= count($savedViews) ?>)</h3>
  <?php foreach ($savedViews as $view): ?>
    <?php
    $saved = json_decode((string) ($view['query_json'] ?? ''), true);
    $viewQuery = is_array($saved) ? trim((string) ($saved['q'] ?? '')) : '';
    ?>
    <div class="saved-view">
      <a class="search-result" href="search.php?q=<?= e(urlencode($viewQuery)) ?>">
        <strong><?= e((string) $view['name']) ?></strong>
        <span><?= e($viewQuery === '' ? 'All modules' : $viewQuery) ?></span>
      </a>
      <form method="post" action="search.php">
        <input type="hidden" name="action" value="delete_view">
        <input type="hidden" name="view_id" value="<?= (int) $view['id'] ?>">
        <input type="hidden" name="q" value="<?= e($query) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="secondary" onclick="return confirm('Delete this saved view?')">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<section class="admin-search">
  <h3>Admin modules (<?= count($results) ?>)</h3>
  <?php foreach ($results as $item): ?>
    <a class="search-result" href="<?= e((string) $item['href']) ?>">
      <strong><?= e((string) $item['title']) ?></strong>
      <span><?= e((string) $item['description']) ?></span>
    </a>
  <?php endforeach; ?>
  <?php if (!$results && !$records): ?><div class="panel">No admin results matched your search.</div><?php endif; ?>
</section>
<?php admin_page_end(); ?>
