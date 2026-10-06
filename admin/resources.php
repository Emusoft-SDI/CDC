<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'Guides'));
        $offline = isset($_POST['offline_available']) ? 1 : 0;

        if ($title === '') {
            $error = 'Title and file are required.';
        } else {
            try {
                $upload = app_uploaded_file_info((array) ($_FILES['file'] ?? []), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'], 20 * 1024 * 1024, 'Resource file');
                $uploadDir = dirname(__DIR__) . '/resources';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $safeName = app_safe_upload_name('resource', $upload['name'], $upload['extension']);
                $target = $uploadDir . '/' . $safeName;

                if (move_uploaded_file($upload['tmp_name'], $target)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO resources (title, description, file_path, category, offline_available)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $description, $safeName, $category, $offline]);
                    $pdo->prepare("INSERT INTO audit_log (action, description, ip_address) VALUES (?, ?, ?)")
                        ->execute(['Resource Uploaded', 'Title: ' . $title, $_SERVER['REMOTE_ADDR'] ?? null]);
                    $message = 'Resource uploaded.';
                } else {
                    $error = 'Upload failed. Check the resources folder permissions.';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$totalResources = (int) $pdo->query("SELECT COUNT(*) FROM resources")->fetchColumn();
$resources = $pdo->query("SELECT * FROM resources ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();

$resourceStats = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(offline_available = 1), 0) AS offline, COUNT(DISTINCT category) AS categories, COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS recent FROM resources")->fetch() ?: [];

admin_page_start('Learning Resources', [
    'active' => 'resources.php',
    'description' => 'Upload training files, farmer guides, field materials, and offline resources as an independent learning area.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?= admin_kpi_grid([
    ['Total Resources', number_format((int) ($resourceStats['total'] ?? 0)), 'Files in library', 'fa-folder-open', ''],
    ['Offline Ready', number_format((int) ($resourceStats['offline'] ?? 0)), 'Downloadable in the field', 'fa-cloud-arrow-down', 'blue'],
    ['Categories', number_format((int) ($resourceStats['categories'] ?? 0)), 'Library sections', 'fa-tags', 'purple'],
    ['Added (30 days)', number_format((int) ($resourceStats['recent'] ?? 0)), 'Recently uploaded', 'fa-arrow-up-from-bracket', 'orange'],
]) ?>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-upload"></i></span>
    <span class="collapse-title">Upload Resource<small>Add training files, guides and field materials</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="field-grid">
        <label class="field"><span>Title</span><input type="text" name="title" required></label>
        <label class="field"><span>Category</span>
          <select name="category">
            <option value="Training">Training</option>
            <option value="Guides">Guides</option>
            <option value="Market">Market Data</option>
            <option value="Certificates">Certificates</option>
          </select>
        </label>
        <label class="field"><span>Description</span><textarea name="description"></textarea></label>
        <label class="field"><span>File</span><input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required></label>
      </div>
      <label class="field" style="margin-top:12px"><input type="checkbox" name="offline_available" checked> Available offline for field agents</label>
      <div class="actions"><button type="submit"><i class="fas fa-upload"></i> Upload Resource</button></div>
    </form>
  </div>
</details>

<section class="panel">
  <div class="user-toolbar">
    <h2 style="margin:0">Resource Library</h2>
    <span class="meta"><?= number_format($totalResources) ?> file(s)</span>
  </div>
  <?= admin_pagination_controls($totalResources, $page, $perPage) ?>
  <div class="record-list">
    <?php foreach ($resources as $res): ?>
      <article class="record-row">
        <span class="record-avatar file"><i class="fas fa-file-arrow-down"></i></span>
        <div class="record-main">
          <div class="record-title"><?= e($res['title']) ?></div>
          <div class="record-excerpt"><?= e(mb_strimwidth((string) $res['description'], 0, 150, '...')) ?></div>
        </div>
        <div class="record-meta">
          <span class="tag info"><i class="fas fa-tag"></i> <?= e($res['category']) ?></span>
          <?php if ((int) $res['offline_available'] === 1): ?>
            <span class="tag ok"><i class="fas fa-cloud-arrow-down"></i> Offline</span>
          <?php else: ?>
            <span class="tag muted">Online only</span>
          <?php endif; ?>
          <span class="record-sub"><i class="far fa-calendar"></i> <?= e(date('M j, Y', strtotime((string) $res['created_at']))) ?></span>
        </div>
        <div class="record-actions">
          <a class="button secondary sm" href="../resources/<?= rawurlencode((string) $res['file_path']) ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Open</a>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$resources): ?><div class="record-empty">No resources uploaded yet.</div><?php endif; ?>
  </div>
  <?= admin_pagination_controls($totalResources, $page, $perPage) ?>
</section>
<?php admin_page_end(); ?>
