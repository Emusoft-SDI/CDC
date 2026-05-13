<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/platform-governance.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
pg_ensure_schema($pdo);

$user = current_user($pdo) ?: [];
$scopeState = pg_scope_state($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            $state = $scopeState !== '' ? $scopeState : trim((string) ($_POST['state_name'] ?? ''));
            if ($state === '') {
                throw new RuntimeException('State is required.');
            }
            if ($action === 'save_inventory') {
                $pdo->prepare("
                    INSERT INTO state_resource_inventory
                        (state_name, resource_name, resource_category, quantity_available, unit, reorder_level, notes, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $state,
                    trim((string) ($_POST['resource_name'] ?? '')),
                    trim((string) ($_POST['resource_category'] ?? 'input')),
                    (float) ($_POST['quantity_available'] ?? 0),
                    trim((string) ($_POST['unit'] ?? '')),
                    (float) ($_POST['reorder_level'] ?? 0),
                    trim((string) ($_POST['notes'] ?? '')),
                    (int) ($user['id'] ?? 0),
                ]);
                $message = 'Inventory item recorded.';
            } elseif ($action === 'allocate') {
                $pdo->prepare("
                    INSERT INTO state_resource_allocations
                        (state_name, farmer_id, resource_name, resource_category, quantity_allocated, unit, distribution_status, effectiveness_note, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $state,
                    (int) ($_POST['farmer_id'] ?? 0) ?: null,
                    trim((string) ($_POST['resource_name'] ?? '')),
                    trim((string) ($_POST['resource_category'] ?? 'input')),
                    (float) ($_POST['quantity_allocated'] ?? 0),
                    trim((string) ($_POST['unit'] ?? '')),
                    trim((string) ($_POST['distribution_status'] ?? 'planned')),
                    trim((string) ($_POST['effectiveness_note'] ?? '')),
                    (int) ($user['id'] ?? 0),
                ]);
                $message = 'Resource allocation recorded.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$stateFilter = $scopeState !== '' ? 'WHERE state_name = ?' : '';
$inventoryStmt = $pdo->prepare("SELECT * FROM state_resource_inventory {$stateFilter} ORDER BY updated_at DESC, created_at DESC LIMIT 50");
$inventoryStmt->execute($scopeState !== '' ? [$scopeState] : []);
$inventory = $inventoryStmt->fetchAll();

$allocStmt = $pdo->prepare("
    SELECT sra.*, u.name farmer_name
    FROM state_resource_allocations sra
    LEFT JOIN users u ON u.id = sra.farmer_id
    " . ($scopeState !== '' ? 'WHERE sra.state_name = ?' : '') . "
    ORDER BY sra.created_at DESC
    LIMIT 80
");
$allocStmt->execute($scopeState !== '' ? [$scopeState] : []);
$allocations = $allocStmt->fetchAll();

$farmersStmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    WHERE u.role = 'grower' " . ($scopeState !== '' ? "AND (ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)" : '') . "
    ORDER BY u.name
    LIMIT 500
");
$farmersStmt->execute($scopeState !== '' ? [$scopeState, '%' . $scopeState . '%', '%' . $scopeState . '%'] : []);
$farmers = $farmersStmt->fetchAll();

admin_page_start('Resource Allocation', [
    'active' => 'resource-allocation.php',
    'description' => 'Track state-specific inputs, inventory, farmer allocations, and distribution effectiveness.',
    'wide' => true,
    'css' => ':root{--primary:#365314;--green:#65a30d;--green-dark:#3f6212;--bg:#f7fee7}.resource-hero{background:linear-gradient(135deg,#f7fee7,#fff);border-left:5px solid #65a30d}',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="panel resource-hero">
  <h2><?= $scopeState !== '' ? e($scopeState) . ' Resources' : 'National Resource Allocation' ?></h2>
  <p class="muted">Record inventory, allocate inputs to farmers, and monitor distribution effectiveness.</p>
</section>

<section class="layout">
  <aside class="panel">
    <h2>Inventory</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_inventory">
      <?php if ($scopeState === ''): ?><label>State<input name="state_name" required></label><?php endif; ?>
      <label>Resource Name<input name="resource_name" required></label>
      <label>Category<input name="resource_category" value="input"></label>
      <label>Quantity<input name="quantity_available" inputmode="decimal"></label>
      <label>Unit<input name="unit" placeholder="bags, seedlings, litres"></label>
      <label>Reorder Level<input name="reorder_level" inputmode="decimal"></label>
      <label>Notes<textarea name="notes"></textarea></label>
      <button type="submit">Save Inventory</button>
    </form>
    <h2>Allocate</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="allocate">
      <?php if ($scopeState === ''): ?><label>State<input name="state_name" required></label><?php endif; ?>
      <label>Farmer<select name="farmer_id"><option value="">General allocation</option><?php foreach ($farmers as $farmer): ?><option value="<?= (int) $farmer['id'] ?>"><?= e($farmer['name']) ?></option><?php endforeach; ?></select></label>
      <label>Resource Name<input name="resource_name" required></label>
      <label>Category<input name="resource_category" value="input"></label>
      <label>Quantity<input name="quantity_allocated" inputmode="decimal"></label>
      <label>Unit<input name="unit"></label>
      <label>Status<select name="distribution_status"><option value="planned">Planned</option><option value="distributed">Distributed</option><option value="delayed">Delayed</option><option value="cancelled">Cancelled</option></select></label>
      <label>Effectiveness Note<textarea name="effectiveness_note"></textarea></label>
      <button type="submit">Record Allocation</button>
    </form>
  </aside>

  <section class="panel">
    <h2>Inventory Register</h2>
    <table><thead><tr><th>State</th><th>Resource</th><th>Qty</th><th>Reorder</th></tr></thead><tbody>
      <?php foreach ($inventory as $row): ?><tr><td><?= e($row['state_name']) ?></td><td><?= e($row['resource_name']) ?><br><span class="muted"><?= e($row['resource_category']) ?></span></td><td><?= e((string) $row['quantity_available']) ?> <?= e((string) $row['unit']) ?></td><td><?= e((string) $row['reorder_level']) ?></td></tr><?php endforeach; ?>
      <?php if (!$inventory): ?><tr><td colspan="4">No inventory recorded.</td></tr><?php endif; ?>
    </tbody></table>
    <h2>Allocations</h2>
    <table><thead><tr><th>State</th><th>Farmer</th><th>Resource</th><th>Status</th><th>Effectiveness</th></tr></thead><tbody>
      <?php foreach ($allocations as $row): ?><tr><td><?= e($row['state_name']) ?></td><td><?= e($row['farmer_name'] ?? 'General') ?></td><td><?= e($row['resource_name']) ?><br><span class="muted"><?= e((string) $row['quantity_allocated']) ?> <?= e((string) $row['unit']) ?></span></td><td><?= e(ucwords((string) $row['distribution_status'])) ?></td><td><?= e((string) $row['effectiveness_note']) ?></td></tr><?php endforeach; ?>
      <?php if (!$allocations): ?><tr><td colspan="5">No allocations recorded.</td></tr><?php endif; ?>
    </tbody></table>
  </section>
</section>
<?php admin_page_end(); ?>
