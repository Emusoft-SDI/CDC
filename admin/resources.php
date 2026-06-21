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

admin_page_start('Learning Resources', [
    'active' => 'resources.php',
    'description' => 'Upload training files, farmer guides, field materials, and offline resources as an independent learning area.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="layout">
  <form class="panel" method="post" enctype="multipart/form-data">
    <h2>Upload Resource</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Title</label>
    <input type="text" name="title" required>
    <label>Description</label>
    <textarea name="description"></textarea>
    <label>Category</label>
    <select name="category">
      <option value="Training">Training</option>
      <option value="Guides">Guides</option>
      <option value="Market">Market Data</option>
      <option value="Certificates">Certificates</option>
    </select>
    <label><input type="checkbox" name="offline_available" checked> Available offline for field agents</label>
    <label>File</label>
    <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
    <div class="actions"><button type="submit">Upload Resource</button></div>
  </form>

  <section>
    <?= admin_pagination_controls($totalResources, $page, $perPage) ?>
    <table>
      <thead><tr><th>Title</th><th>Category</th><th>Offline</th><th>Uploaded</th><th>File</th></tr></thead>
      <tbody>
        <?php foreach ($resources as $res): ?>
          <tr>
            <td><strong><?= e($res['title']) ?></strong><br><small><?= e($res['description']) ?></small></td>
            <td><?= e($res['category']) ?></td>
            <td><span class="badge <?= (int) $res['offline_available'] === 1 ? 'verified' : 'closed' ?>"><?= (int) $res['offline_available'] === 1 ? 'Yes' : 'No' ?></span></td>
            <td><?= e(date('M j, Y', strtotime((string) $res['created_at']))) ?></td>
            <td><a href="../resources/<?= rawurlencode((string) $res['file_path']) ?>" target="_blank">Open</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$resources): ?><tr><td colspan="5">No resources uploaded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?= admin_pagination_controls($totalResources, $page, $perPage) ?>
  </section>
</section>
<?php admin_page_end(); ?>
