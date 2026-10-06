<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';

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
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
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

<?php
$invCount = count($inventory);
$allocCount = count($allocations);
$lowStock = count(array_filter($inventory, static fn($r): bool => (float) ($r['reorder_level'] ?? 0) > 0 && (float) ($r['quantity_available'] ?? 0) <= (float) $r['reorder_level']));
$distributed = count(array_filter($allocations, static fn($r): bool => (string) ($r['distribution_status'] ?? '') === 'distributed'));
$planned = count(array_filter($allocations, static fn($r): bool => (string) ($r['distribution_status'] ?? '') === 'planned'));
?>
<?= admin_kpi_grid([
    ['Inventory Items', number_format($invCount), 'Tracked resources', 'fa-boxes-stacked', ''],
    ['Allocations', number_format($allocCount), 'Distribution records', 'fa-truck-fast', 'blue'],
    ['Distributed', number_format($distributed), 'Completed allocations', 'fa-circle-check', 'purple'],
    ['Planned', number_format($planned), 'Awaiting dispatch', 'fa-clipboard-list', 'orange'],
    ['Low Stock', number_format($lowStock), 'At or below reorder level', 'fa-triangle-exclamation', 'red'],
]) ?>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-boxes-stacked"></i></span>
    <span class="collapse-title">Record Inventory<small>Add or update state resource stock</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_inventory">
      <div class="field-grid">
        <?php if ($scopeState === ''): ?><label class="field"><span>State</span><input name="state_name" required></label><?php endif; ?>
        <label class="field"><span>Resource Name</span><input name="resource_name" required></label>
        <label class="field"><span>Category</span><input name="resource_category" value="input"></label>
        <label class="field"><span>Quantity</span><input name="quantity_available" inputmode="decimal"></label>
        <label class="field"><span>Unit</span><input name="unit" placeholder="bags, seedlings, litres"></label>
        <label class="field"><span>Reorder Level</span><input name="reorder_level" inputmode="decimal"></label>
        <label class="field"><span>Notes</span><textarea name="notes"></textarea></label>
      </div>
      <div class="actions"><button type="submit"><i class="fas fa-floppy-disk"></i> Save Inventory</button></div>
    </form>
  </div>
</details>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-truck-fast"></i></span>
    <span class="collapse-title">Allocate Resource<small>Distribute inputs to a farmer or general pool</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="allocate">
      <div class="field-grid">
        <?php if ($scopeState === ''): ?><label class="field"><span>State</span><input name="state_name" required></label><?php endif; ?>
        <label class="field"><span>Farmer</span><select name="farmer_id"><option value="">General allocation</option><?php foreach ($farmers as $farmer): ?><option value="<?= (int) $farmer['id'] ?>"><?= e($farmer['name']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Resource Name</span><input name="resource_name" required></label>
        <label class="field"><span>Category</span><input name="resource_category" value="input"></label>
        <label class="field"><span>Quantity</span><input name="quantity_allocated" inputmode="decimal"></label>
        <label class="field"><span>Unit</span><input name="unit"></label>
        <label class="field"><span>Status</span><select name="distribution_status"><option value="planned">Planned</option><option value="distributed">Distributed</option><option value="delayed">Delayed</option><option value="cancelled">Cancelled</option></select></label>
        <label class="field"><span>Effectiveness Note</span><textarea name="effectiveness_note"></textarea></label>
      </div>
      <div class="actions"><button type="submit"><i class="fas fa-truck-fast"></i> Record Allocation</button></div>
    </form>
  </div>
</details>

<section class="panel">
  <div class="user-toolbar">
    <h2 style="margin:0">Inventory Register</h2>
    <span class="meta"><?= number_format($invCount) ?> item(s)</span>
  </div>
  <div class="record-list">
    <?php foreach ($inventory as $row): ?>
      <?php
        $qty = (float) ($row['quantity_available'] ?? 0);
        $reorder = (float) ($row['reorder_level'] ?? 0);
        $low = $reorder > 0 && $qty <= $reorder;
      ?>
      <article class="record-row compact">
        <span class="record-avatar <?= $low ? 'orange' : 'file' ?>"><i class="fas fa-boxes-stacked"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($row['resource_name']) ?>
            <span class="tag info"><?= e($row['resource_category']) ?></span>
            <?php if ($low): ?><span class="tag bad">Reorder</span><?php endif; ?>
          </div>
          <div class="record-contact">
            <span><i class="fas fa-location-dot"></i><?= e($row['state_name']) ?></span>
            <span><i class="fas fa-cubes"></i><?= e((string) $row['quantity_available']) ?> <?= e((string) $row['unit']) ?></span>
          </div>
        </div>
        <div class="record-actions">
          <span class="ref-pill">Reorder at <?= e((string) $row['reorder_level']) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$inventory): ?><div class="record-empty">No inventory recorded.</div><?php endif; ?>
  </div>

  <div class="user-toolbar" style="margin-top:20px">
    <h2 style="margin:0">Allocations</h2>
    <span class="meta"><?= number_format($allocCount) ?> record(s)</span>
  </div>
  <div class="record-list">
    <?php foreach ($allocations as $row): ?>
      <?php $status = (string) $row['distribution_status']; $statusTone = ['distributed' => 'ok', 'planned' => 'info', 'delayed' => 'warn', 'cancelled' => 'bad'][$status] ?? 'muted'; ?>
      <article class="record-row compact">
        <span class="record-avatar doc"><i class="fas fa-truck-fast"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($row['resource_name']) ?>
            <span class="tag <?= e($statusTone) ?>"><?= e(ucwords($status)) ?></span>
          </div>
          <div class="record-contact">
            <span><i class="fas fa-user"></i><?= e($row['farmer_name'] ?? 'General') ?></span>
            <span><i class="fas fa-location-dot"></i><?= e($row['state_name']) ?></span>
            <span><i class="fas fa-cubes"></i><?= e((string) $row['quantity_allocated']) ?> <?= e((string) $row['unit']) ?></span>
          </div>
          <?php if (!empty($row['effectiveness_note'])): ?><div class="record-excerpt"><?= e(mb_strimwidth((string) $row['effectiveness_note'], 0, 150, '...')) ?></div><?php endif; ?>
        </div>
        <div class="record-actions"></div>
      </article>
    <?php endforeach; ?>
    <?php if (!$allocations): ?><div class="record-empty">No allocations recorded.</div><?php endif; ?>
  </div>
</section>
<?php admin_page_end(); ?>
