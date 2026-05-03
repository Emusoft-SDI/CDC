<!-- admin/settings.php -->
<h2>System Settings</h2>

<form method="POST">
  <div class="setting-group">
    <h3>SMS Validation Settings</h3>
    
    <div class="form-group">
      <label>
        <input type="checkbox" name="sms_phone_validation_required" 
               <?= getSetting('sms_phone_validation_required') === '1' ? 'checked' : '' ?>>
        Require SMS phone validation before certificate issuance
      </label>
    </div>
    
    <div class="form-group">
      <label>
        <input type="checkbox" name="sms_validation_notifications" 
               <?= getSetting('sms_validation_notifications') === '1' ? 'checked' : '' ?>>
        Send SMS notifications for validation results
      </label>
    </div>
    
    <div class="form-group">
      <label>Verification Code Timeout (seconds)</label>
      <input type="number" name="sms_verification_timeout" 
             value="<?= getSetting('sms_verification_timeout') ?>" min="60" max="3600">
    </div>
  </div>
  
  <button type="submit" name="save_sms_settings">Save SMS Settings</button>
</form>

<?php
function getSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

// Handle form submission
if (isset($_POST['save_sms_settings'])) {
    $settings = [
        'sms_phone_validation_required' => isset($_POST['sms_phone_validation_required']) ? '1' : '0',
        'sms_validation_notifications' => isset($_POST['sms_validation_notifications']) ? '1' : '0',
        'sms_verification_timeout' => intval($_POST['sms_verification_timeout'])
    ];
    
    foreach ($settings as $key => $value) {
        $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = ?")
             ->execute([$value, $key]);
    }
    
    $message = "SMS settings updated successfully!";
}
?>