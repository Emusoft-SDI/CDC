<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';

$pdo = fa_pdo();
$user = fa_require_user($pdo);
$q = trim((string) ($_GET['q'] ?? ''));
$results = [];

if ($q !== '') {
    $needle = strtolower($q);
    foreach (fa_task_rows($pdo, $user) as $task) {
        $haystack = strtolower((string) ($task['farm_name'] ?? '') . ' ' . (string) ($task['grower_name'] ?? '') . ' ' . (string) ($task['grower_phone'] ?? '') . ' ' . (string) ($task['street_address'] ?? '') . ' ' . (string) ($task['lga_name'] ?? '') . ' ' . (string) ($task['state_name'] ?? '') . ' ' . (string) ($task['priority'] ?? ''));
        if (str_contains($haystack, $needle)) {
            $results[] = ['type' => 'Assignment', 'title' => $task['farm_name'] ?: $task['grower_name'], 'description' => trim(($task['grower_name'] ?? '') . ' / ' . ($task['lga_name'] ?? '') . ' ' . ($task['state_name'] ?? '')), 'href' => 'assignments.php'];
        }
    }
}

foreach ([
    ['Assignments', 'Assigned farms, visits, GPS capture, and field outcomes.', 'assignments.php', 'assigned farm grower visit gps location'],
    ['Verification', 'Verify grower identity, documents, farm evidence, and coordinates.', 'verification.php', 'verify documents farm coordinates'],
    ['Evidence', 'Upload visit evidence, notes, and field media.', 'evidence.php', 'evidence upload photos notes media'],
    ['Map', 'View field locations and assigned farm geography.', 'map.php', 'map state lga gps location'],
    ['Support', 'Field agent help tickets and messages.', 'support.php', 'support tickets help'],
    ['Reports', 'Field visit reports and operational summaries.', 'reports.php', 'reports visits operations'],
] as [$title, $description, $href, $tags]) {
    if ($q === '' || stripos($title . ' ' . $description . ' ' . $tags, $q) !== false) {
        $results[] = ['type' => 'Workspace', 'title' => $title, 'description' => $description, 'href' => $href];
    }
}

fa_header('Field Search', 'Search assigned farms, growers, locations, tickets, and field workspace tools.', $user, 'assignments');
?>
<form class="fa-card fa-panel" method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
  <input name="q" value="<?= e($q) ?>" placeholder="Search growers, farms, tickets, locations..." style="flex:1;min-width:220px">
  <button class="btn" type="submit"><i data-lucide="search"></i> Search</button>
</form>
<section class="fa-card fa-panel">
  <div class="fa-grid">
    <?php foreach ($results as $row): ?><a class="fa-card fa-panel span-6" href="<?= e((string) $row['href']) ?>" style="box-shadow:none;text-decoration:none;color:inherit"><span class="badge good"><?= e((string) $row['type']) ?></span><h2><?= e((string) $row['title']) ?></h2><p class="muted"><?= e((string) $row['description']) ?></p></a><?php endforeach; ?>
    <?php if ($q === ''): ?><div class="empty span-12">Type a search term to find field records.</div><?php elseif (!$results): ?><div class="empty span-12">No field result matched your search.</div><?php endif; ?>
  </div>
</section>
<?php fa_footer(); ?>
