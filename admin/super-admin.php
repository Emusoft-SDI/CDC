<?php
// Verify super admin
$stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetchColumn()) {
    die("Access denied");
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>👑 Super Admin Panel - NATCODEV</title>
  <style>
    .admin-section { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px; }
    .setting-row { display: flex; margin: 10px 0; align-items: center; }
    .setting-row label { width: 200px; font-weight: bold; }
    input, select, textarea { padding: 8px; width: 300px; }
    .feature-toggle { display: flex; align-items: center; gap: 10px; }
  </style>
</head>
<body>
  <h1>👑 NATCODEV Super Admin Panel</h1>
  
  <!-- System Settings -->
  <div class="admin-section">
    <h2>⚙️ System Configuration</h2>
    <form method="POST" action="update-settings.php">
      <?php
      $settings = $pdo->query("SELECT * FROM system_settings ORDER BY category, setting_key");
      $currentCategory = '';
      while ($setting = $settings->fetch()):
        if ($setting['category'] !== $currentCategory):
          if ($currentCategory !== '') echo '</div>';
          echo "<div class='category-section'><h3>" . ucfirst($setting['category']) . "</h3>";
          $currentCategory = $setting['category'];
        endif;
      ?>
        <div class="setting-row">
          <label title="<?= htmlspecialchars($setting['description']) ?>"><?= htmlspecialchars($setting['setting_key']) ?>:</label>
          <input type="text" name="settings[<?= $setting['setting_key'] ?>]" 
                 value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                 placeholder="<?= htmlspecialchars($setting['description']) ?>">
        </div>
      <?php endwhile; ?>
      </div>
      <button type="submit">Save System Settings</button>
    </form>
  </div>
  
  <!-- Feature Management -->
  <div class="admin-section">
    <h2>🚀 Feature Management</h2>
    <div class="feature-toggle">
      <label>IoT Module:</label>
      <select name="iot_module_enabled">
        <option value="0" <?= getSetting('iot_module_enabled')==='0'?'selected':'' ?>>Disabled</option>
        <option value="1" <?= getSetting('iot_module_enabled')==='1'?'selected':'' ?>>Enabled</option>
      </select>
    </div>
    <!-- Add other features -->
  </div>
  
  <!-- User Management -->
  <div class="admin-section">
    <h2>👥 User Management</h2>
    <table style="width:100%;">
      <tr><th>Email</th><th>Role</th><th>Actions</th></tr>
      <?php
      $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");
      while ($user = $users->fetch()):
      ?>
      <tr>
        <td><?= htmlspecialchars($user['email']) ?></td>
        <td>
          <select name="role_<?= $user['id'] ?>">
            <option value="grower" <?= $user['role']==='grower'?'selected':'' ?>>Grower</option>
            <option value="field_agent" <?= $user['role']==='field_agent'?'selected':'' ?>>Field Agent</option>
            <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
            <option value="super_admin" <?= $user['is_super_admin']?'selected':'' ?>>Super Admin</option>
          </select>
        </td>
        <td>
          <button onclick="updateUserRole(<?= $user['id'] ?>)">Update</button>
          <button onclick="deleteUser(<?= $user['id'] ?>)" style="background:#c62828;">Delete</button>
        </td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>
</body>
</html>