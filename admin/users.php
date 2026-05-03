<!-- Manage user roles -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")
         ->execute([$_POST['role'], $_POST['user_id']]);
}

$users = $pdo->query("
    SELECT u.*, a.app_ref 
    FROM users u 
    LEFT JOIN applications a ON u.application_id = a.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>

<table>
  <tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= htmlspecialchars($u['name']) ?></td>
    <td><?= htmlspecialchars($u['email']) ?></td>
    <td>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <select name="role" onchange="this.form.submit()">
          <option value="grower" <?= $u['role']==='grower'?'selected':'' ?>>Grower</option>
          <option value="field_agent" <?= $u['role']==='field_agent'?'selected':'' ?>>Field Agent</option>
          <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
        </select>
      </form>
    </td>
    <td>
      <?php if ($u['role'] === 'field_agent'): ?>
        <a href="assign-growers.php?agent=<?= $u['id'] ?>">Assign Growers</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

// Auto-create farm zone when application is confirmed
$pdo->prepare("
    INSERT INTO farm_zones (name, grower_id, center_lat, center_lng, radius_meters)
    VALUES (?, ?, ?, ?, ?)
")->execute([
    $data['name'] . "'s Farm",
    $appId,
    9.0820, // Default to Nigeria center (update with real coordinates later)
    8.6753,
    max(100, intval($data['farm_size']) * 50) // Scale radius by farm size
]);