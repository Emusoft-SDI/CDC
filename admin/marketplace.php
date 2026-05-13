<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'toggle' && $id > 0) {
            $pdo->prepare("UPDATE marketplace_items SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            $message = 'Marketplace item status updated.';
        } else {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $price = max(0, (float) ($_POST['price'] ?? 0));
            $category = preg_replace('/[^a-z_-]/i', '', (string) ($_POST['category'] ?? 'input')) ?: 'input';

            if ($title === '') {
                $error = 'Product title is required.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO marketplace_items (seller_id, title, description, price, category, is_active)
                    VALUES (0, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$title, $description, $price, $category]);
                $message = 'Marketplace item published.';
            }
        }
    }
}

$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$totalItems = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_items")->fetchColumn();
$items = $pdo->query("SELECT * FROM marketplace_items ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();

admin_page_start('Marketplace', [
    'active' => 'marketplace.php',
    'description' => 'Publish and manage products, services, equipment, and training offers shown to growers.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="layout">
  <form class="panel" method="post">
    <h2>Add Item</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Title</label>
    <input type="text" name="title" required>
    <label>Description</label>
    <textarea name="description"></textarea>
    <label>Price (NGN)</label>
    <input type="number" name="price" min="0" step="0.01" required>
    <label>Category</label>
    <select name="category">
      <option value="input">Agricultural Inputs</option>
      <option value="equipment">Equipment</option>
      <option value="service">Services</option>
      <option value="training">Training</option>
    </select>
    <div class="actions"><button type="submit">Publish Item</button></div>
  </form>

  <section>
    <?= admin_pagination_controls($totalItems, $page, $perPage) ?>
    <table>
      <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><strong><?= e($item['title']) ?></strong><br><small><?= e($item['description']) ?></small></td>
            <td><?= e(ucfirst((string) $item['category'])) ?></td>
            <td>NGN <?= number_format((float) $item['price'], 2) ?></td>
            <td><span class="badge <?= (int) $item['is_active'] === 1 ? 'verified' : 'closed' ?>"><?= (int) $item['is_active'] === 1 ? 'Active' : 'Hidden' ?></span></td>
            <td>
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <button class="secondary" type="submit"><?= (int) $item['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5">No marketplace items yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?= admin_pagination_controls($totalItems, $page, $perPage) ?>
  </section>
</section>
<?php admin_page_end(); ?>
