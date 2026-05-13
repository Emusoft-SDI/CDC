<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

function admin_group_count(PDO $pdo, string $column): array
{
    if (!app_column_exists($pdo, 'users', $column)) {
        return [];
    }
    $quoted = '`' . str_replace('`', '``', $column) . '`';
    return $pdo->query("SELECT COALESCE(NULLIF({$quoted}, ''), 'Not specified') label, COUNT(*) total FROM users GROUP BY label ORDER BY total DESC")->fetchAll();
}

$stats = [
    'roles' => admin_group_count($pdo, 'role'),
    'education' => admin_group_count($pdo, 'education_level'),
    'experience' => admin_group_count($pdo, 'farming_experience_rating'),
    'marital' => admin_group_count($pdo, 'marital_status'),
];
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

admin_page_start('Demographics', [
    'active' => 'demographics.php',
    'description' => 'Review available demographic and participation data captured across user profiles.',
    'wide' => true,
]);
?>
<section class="stats">
  <div class="stat"><span>Total Users</span><div class="metric"><?= $totalUsers ?></div></div>
  <div class="stat"><span>Roles Tracked</span><div class="metric"><?= count($stats['roles']) ?></div></div>
</section>

<section class="grid">
  <?php foreach ($stats as $title => $rows): ?>
    <div class="panel">
      <h2><?= e(ucfirst($title)) ?></h2>
      <?php if ($rows): ?>
        <table>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr><td><?= e((string) $row['label']) ?></td><td><?= (int) $row['total'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty">This profile field is not available in the current users table.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php admin_page_end(); ?>
