<!-- dashboard/profile.php -->
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $notify_email = isset($_POST['notify_email']) ? 1 : 0;
    $notify_whatsapp = isset($_POST['notify_whatsapp']) ? 1 : 0;

    // Handle profile picture
    $profile_picture = $user['profile_picture'];
    if (!empty($_FILES['profile_picture']['name'])) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
        $fileName = 'user_' . $_SESSION['user_id'] . '_' . time() . '.jpg';
        $filePath = $uploadDir . $fileName;
        
        // Validate image
        $check = getimagesize($_FILES['profile_picture']['tmp_name']);
        if ($check !== false && move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
            $profile_picture = $fileName;
        }
    }

    // Update
    $upd = $pdo->prepare("
        UPDATE users 
        SET phone = ?, location = ?, profile_picture = ?, 
            notify_email = ?, notify_whatsapp = ?
        WHERE id = ?
    ");
    $upd->execute([$phone, $location, $profile_picture, $notify_email, $notify_whatsapp, $_SESSION['user_id']]);
    $message = "✅ Profile updated successfully!";
    
    // Refresh user data
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Profile - NATCODEV</title>
  <style>
    body { font-family: Arial; max-width: 600px; margin: 30px auto; }
    .form-group { margin: 15px 0; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input, textarea { width: 100%; padding: 8px; }
    button { background: #2d5016; color: white; padding: 10px 20px; border: none; border-radius: 4px; }
    .profile-pic { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin: 10px 0; }
  </style>
</head>
<body>
  <h2>Edit Profile</h2>

  <?php if ($message): ?><p style="color:green;"><?= $message ?></p><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <!-- Profile Picture -->
    <div class="form-group">
      <label>Profile Picture</label>
      <?php if ($user['profile_picture']): ?>
        <img src="/uploads/<?= htmlspecialchars($user['profile_picture']) ?>" class="profile-pic">
      <?php endif; ?>
      <input type="file" name="profile_picture" accept="image/jpeg,image/png">
      <small>Max 2MB, JPG/PNG</small>
    </div>

    <!-- Phone -->
    <div class="form-group">
      <label>Phone Number</label>
      <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
    </div>

    <!-- Location -->
    <div class="form-group">
      <label>Location (State, LGA)</label>
      <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>">
    </div>

    <!-- Notification Preferences -->
    <div class="form-group">
      <label>
        <input type="checkbox" name="notify_email" <?= ($user['notify_email'] ?? 1) ? 'checked' : '' ?>> 
        Email Notifications
      </label>
    </div>
    <div class="form-group">
      <label>
        <input type="checkbox" name="notify_whatsapp" <?= ($user['notify_whatsapp'] ?? 0) ? 'checked' : '' ?>> 
        WhatsApp Notifications
      </label>
    </div>

    <button type="submit">Save Changes</button>
    <a href="index.php" style="margin-left: 15px;">← Back to Dashboard</a>
  </form>

<!-- dashboard/profile.php - Enhanced -->
<?php
// Fetch user role to show role-specific fields
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRole = $stmt->fetchColumn();
?>
<form method="POST" enctype="multipart/form-data">
  <!-- Existing fields (name, phone, location, etc.) -->
  
  <!-- Personal Information -->
  <div class="form-group">
    <label>Date of Birth</label>
    <input type="date" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>">
  </div>
  <div class="form-group">
  <label>
    <input type="checkbox" name="notify_sms" <?= ($user['notify_sms'] ?? 0) ? 'checked' : '' ?>> 
    SMS Notifications (for areas without WhatsApp)
  </label>
</div>
  <div class="form-group">
    <label>Marital Status</label>
    <select name="marital_status">
      <option value="">Select</option>
      <option value="single" <?= ($user['marital_status'] ?? '') === 'single' ? 'selected' : '' ?>>Single</option>
      <option value="married" <?= ($user['marital_status'] ?? '') === 'married' ? 'selected' : '' ?>>Married</option>
      <option value="divorced" <?= ($user['marital_status'] ?? '') === 'divorced' ? 'selected' : '' ?>>Divorced</option>
      <option value="widowed" <?= ($user['marital_status'] ?? '') === 'widowed' ? 'selected' : '' ?>>Widowed</option>
    </select>
  </div>
  
  <div class="form-group">
    <label>Family Size</label>
    <input type="number" name="family_size" value="<?= $user['family_size'] ?? '' ?>" min="1">
  </div>
  
  <!-- Next of Kin -->
  <div class="form-group">
    <label>Next of Kin Name</label>
    <input type="text" name="next_of_kin_name" value="<?= htmlspecialchars($user['next_of_kin_name'] ?? '') ?>">
  </div>
  
  <div class="form-group">
    <label>Next of Kin Phone</label>
    <input type="tel" name="next_of_kin_phone" value="<?= htmlspecialchars($user['next_of_kin_phone'] ?? '') ?>">
  </div>
  
  <div class="form-group">
    <label>Relationship</label>
    <input type="text" name="next_of_kin_relationship" value="<?= htmlspecialchars($user['next_of_kin_relationship'] ?? '') ?>" placeholder="e.g., Spouse, Child, Sibling">
  </div>
  
  <!-- Education & Experience -->
  <div class="form-group">
    <label>Highest Education Level</label>
    <select name="education_level">
      <option value="">Select</option>
      <option value="none" <?= ($user['education_level'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
      <option value="primary" <?= ($user['education_level'] ?? '') === 'primary' ? 'selected' : '' ?>>Primary</option>
      <option value="secondary" <?= ($user['education_level'] ?? '') === 'secondary' ? 'selected' : '' ?>>Secondary</option>
      <option value="tertiary" <?= ($user['education_level'] ?? '') === 'tertiary' ? 'selected' : '' ?>>Tertiary</option>
      <option value="post_graduate" <?= ($user['education_level'] ?? '') === 'post_graduate' ? 'selected' : '' ?>>Post Graduate</option>
    </select>
  </div>
  
  <div class="form-group">
    <label>Farming Experience (Years)</label>
    <input type="number" name="farming_experience_years" value="<?= $user['farming_experience_years'] ?? '' ?>" min="0">
  </div>
  
  <div class="form-group">
    <label>Experience Rating</label>
    <select name="farming_experience_rating">
      <option value="">Select</option>
      <option value="beginner" <?= ($user['farming_experience_rating'] ?? '') === 'beginner' ? 'selected' : '' ?>>Beginner (0-2 years)</option>
      <option value="intermediate" <?= ($user['farming_experience_rating'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermediate (3-5 years)</option>
      <option value="advanced" <?= ($user['farming_experience_rating'] ?? '') === 'advanced' ? 'selected' : '' ?>>Advanced (6-10 years)</option>
      <option value="expert" <?= ($user['farming_experience_rating'] ?? '') === 'expert' ? 'selected' : '' ?>>Expert (10+ years)</option>
    </select>
  </div>
  
  <!-- Role-Specific Fields -->
  <?php if ($userRole === 'field_agent' || $userRole === 'agronomist'): ?>
    <div class="form-group">
      <label>Agronomist License Number</label>
      <input type="text" name="agronomist_license" value="<?= htmlspecialchars($user['agronomist_license'] ?? '') ?>">
    </div>
  <?php endif; ?>
  
  <?php if ($userRole === 'admin'): ?>
    <div class="form-group">
      <label>Department</label>
      <input type="text" name="admin_department" value="<?= htmlspecialchars($user['admin_department'] ?? '') ?>">
    </div>
  <?php endif; ?>
  
  <button type="submit">Save Changes</button>
</form>
<!-- In dashboard/profile.php -->
<div class="form-group">
  <label>Phone Number *</label>
  <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
  
  <?php if (!$user['phone_verified']): ?>
    <div style="margin-top: 10px;">
      <a href="verify-phone.php" style="background:#2d5016; color:white; padding:8px 15px; text-decoration:none; border-radius:4px; display:inline-block;">
        🔒 Verify Phone Number
      </a>
      <p style="font-size:12px; color:#666; margin-top:5px;">
        Phone verification is required for certificate issuance.
      </p>
    </div>
  <?php else: ?>
    <p style="color:green; font-size:12px; margin-top:5px;">✅ Phone verified</p>
  <?php endif; ?>
</div>
// In profile.php - handle all new fields
$dob = $_POST['dob'] ?? null;
$marital_status = $_POST['marital_status'] ?? null;
$family_size = !empty($_POST['family_size']) ? intval($_POST['family_size']) : null;
$next_of_kin_name = trim($_POST['next_of_kin_name'] ?? '');
$next_of_kin_phone = trim($_POST['next_of_kin_phone'] ?? '');
$next_of_kin_relationship = trim($_POST['next_of_kin_relationship'] ?? '');
$education_level = $_POST['education_level'] ?? null;
$farming_experience_years = !empty($_POST['farming_experience_years']) ? intval($_POST['farming_experience_years']) : null;
$farming_experience_rating = $_POST['farming_experience_rating'] ?? null;

// Role-specific fields
$agronomist_license = trim($_POST['agronomist_license'] ?? '');
$admin_department = trim($_POST['admin_department'] ?? '');

// Update query
$upd = $pdo->prepare("
    UPDATE users SET 
        dob = ?, marital_status = ?, family_size = ?, 
        next_of_kin_name = ?, next_of_kin_phone = ?, next_of_kin_relationship = ?,
        education_level = ?, farming_experience_years = ?, farming_experience_rating = ?,
        agronomist_license = ?, admin_department = ?
    WHERE id = ?
");
$upd->execute([
    $dob, $marital_status, $family_size,
    $next_of_kin_name, $next_of_kin_phone, $next_of_kin_relationship,
    $education_level, $farming_experience_years, $farming_experience_rating,
    $agronomist_license, $admin_department,
    $_SESSION['user_id']
]);
// In profile.php after update
$log = $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
$log->execute([$_SESSION['user_id'], 'Profile Updated', 'Updated phone and location', $_SERVER['REMOTE_ADDR']]);



</body>
</html>