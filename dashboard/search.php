<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

$pdo = db();
$currentUser = current_user($pdo);
if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $currentUser);

$query = trim((string) ($_GET['q'] ?? ''));
$terms = preg_split('/\s+/', strtolower($query)) ?: [];
$terms = array_values(array_filter($terms, static fn (string $term): bool => $term !== ''));

$catalog = [
    ['title' => 'Overview', 'description' => 'Dashboard summary, wallet, farm score, Academy, support, and quick actions.', 'href' => 'index.php', 'feature' => 'dashboard', 'tags' => 'dashboard overview summary home'],
    ['title' => 'Edit Profile', 'description' => 'Personal profile details used for verification and support.', 'href' => 'profile.php', 'feature' => 'profile', 'tags' => 'profile edit phone picture personal'],
    ['title' => 'Account Settings', 'description' => 'Security, password, and notification preferences.', 'href' => 'account-settings.php', 'feature' => 'profile', 'tags' => 'settings security password notification account'],
    ['title' => 'Farm Profile', 'description' => 'Primary farm, farm activity, farm locations, and farm hands.', 'href' => 'farm-profile.php', 'feature' => 'profile', 'tags' => 'farm profile locations hands activity intercrop livestock'],
    ['title' => 'Wallet', 'description' => 'Wallet balance, funding, payment records, and transaction history.', 'href' => 'wallet.php', 'feature' => 'wallet', 'tags' => 'wallet payment monnify fund transaction'],
    ['title' => 'Marketplace', 'description' => 'Browse marketplace listings, inputs, services, and seller offers.', 'href' => '../market/index.php', 'feature' => 'marketplace', 'tags' => 'marketplace buy inputs services products'],
    ['title' => 'Seller Central', 'description' => 'Manage seller profile, listings, orders, and sales.', 'href' => '../market/seller-central.php', 'feature' => 'marketplace', 'tags' => 'seller central store orders products listings'],
    ['title' => 'NATCODEV Academy', 'description' => 'Courses, learning materials, registrations, certificates, and refunds.', 'href' => '../academy/index.php?screen=catalog', 'feature' => 'training', 'tags' => 'academy training course learning certificate refund'],
    ['title' => 'My Learning', 'description' => 'Continue registered courses and access course materials.', 'href' => '../academy/index.php?screen=learning', 'feature' => 'training', 'tags' => 'learning course material registered'],
    ['title' => 'Verification', 'description' => 'Identity, farm verification, documents, and submitted evidence.', 'href' => 'documents.php', 'feature' => 'documents', 'tags' => 'verification documents evidence identity farm'],
    ['title' => 'Certificates', 'description' => 'View, download, and verify issued grower and Academy certificates.', 'href' => 'certificates.php', 'feature' => 'certificates', 'tags' => 'certificate verify download credential'],
    ['title' => 'Farm Performance', 'description' => 'Farm health, bridge years, productivity, and operational reporting.', 'href' => 'farm-health.php', 'feature' => 'farm_health', 'tags' => 'farm performance health report bridge yield'],
    ['title' => 'Fields Management', 'description' => 'Farm fields, coordinates, verification, and field records.', 'href' => 'fields.php', 'feature' => 'field_management', 'tags' => 'field gps coordinates farm'],
    ['title' => 'Agronomy Advisory', 'description' => 'Request agronomy help and view recommendations.', 'href' => 'agronomist.php', 'feature' => 'agronomy_advisory', 'tags' => 'agronomy advisory expert pests disease soil'],
    ['title' => 'Reports', 'description' => 'Grower reports for farm, finance, marketplace, support, and compliance.', 'href' => 'reports.php', 'feature' => 'reports', 'tags' => 'reports intelligence analytics export'],
    ['title' => 'Support & Requests', 'description' => 'Tickets, messages, notifications, announcements, and support FAQ.', 'href' => 'inbox.php', 'feature' => 'support', 'tags' => 'support ticket message notification help'],
    ['title' => 'Healthcare', 'description' => 'Healthcare services and farm worker safety support.', 'href' => 'healthcare.php', 'feature' => 'healthcare', 'tags' => 'healthcare health medical safety'],
    ['title' => 'Account Upgrade', 'description' => 'Upgrade account access for advanced features.', 'href' => 'pricing.php', 'feature' => 'pricing', 'tags' => 'upgrade pricing iot geofencing premium'],
];

$results = [];
foreach ($catalog as $item) {
    if (!admin_feature_is_allowed($pdo, (string) $item['feature'])) {
        continue;
    }
    $haystack = strtolower($item['title'] . ' ' . $item['description'] . ' ' . $item['tags']);
    $score = 0;
    foreach ($terms as $term) {
        if (str_contains($haystack, $term)) {
            $score += str_contains(strtolower($item['title']), $term) ? 4 : 1;
        }
    }
    if ($query === '' || $score > 0) {
        $item['score'] = $score;
        $results[] = $item;
    }
}
usort($results, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp($a['title'], $b['title']));

dashboard_page_start('Search', [
    'active' => 'search.php',
    'description' => 'Search results respect your enabled modules and role access.',
    'skip_feature_gate' => true,
    'css' => '
      .search-head{display:flex;gap:12px;align-items:center;margin-bottom:18px}
      .search-head input{min-height:46px;flex:1}
      .search-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
      .search-card{display:block;background:#fff;border:1px solid var(--border-color);border-radius:12px;padding:16px;color:inherit}
      .search-card:hover{border-color:rgba(27,94,32,.3);box-shadow:0 12px 26px rgba(16,24,40,.08)}
      .search-card strong{display:block;color:#111827;font-size:1rem;margin-bottom:5px}
      .search-card span{display:block;color:var(--text-secondary);font-weight:500}
      @media(max-width:800px){.search-grid{grid-template-columns:1fr}.search-head{display:grid}}
    ',
]);
?>
<form class="search-head" method="get">
  <input name="q" value="<?= e($query) ?>" placeholder="Search courses, wallet, support, farm profile, certificates...">
  <button type="submit">Search</button>
</form>
<section class="search-grid">
  <?php foreach ($results as $item): ?>
    <a class="search-card" href="<?= e((string) $item['href']) ?>">
      <strong><?= e((string) $item['title']) ?></strong>
      <span><?= e((string) $item['description']) ?></span>
    </a>
  <?php endforeach; ?>
  <?php if (!$results): ?><p class="empty">No matching result found for your account access.</p><?php endif; ?>
</section>
<?php dashboard_page_end(); ?>
