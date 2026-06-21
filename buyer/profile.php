<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'profile');
            if ($action === 'profile') {
                $photoPath = (string) ($user['profile_picture'] ?? '');
                if (!empty($_FILES['profile_picture']) && (int) ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $info = app_uploaded_file_info($_FILES['profile_picture'], ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024, 'Profile photo', ['image/jpeg', 'image/png', 'image/webp']);
                    $dir = __DIR__ . '/../uploads/buyer-profiles';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0775, true);
                    }
                    $name = app_safe_upload_name('buyer_' . (int) $user['id'], $info['name'], $info['extension']);
                    $target = $dir . '/' . $name;
                    if (!move_uploaded_file($info['tmp_name'], $target)) {
                        throw new RuntimeException('Profile photo could not be saved.');
                    }
                    $photoPath = 'uploads/buyer-profiles/' . $name;
                }
                $pdo->prepare("UPDATE users SET name=?, phone=?, location=?, profile_picture=? WHERE id=?")->execute([
                    trim((string) $_POST['name']),
                    trim((string) $_POST['phone']),
                    trim((string) $_POST['location']),
                    $photoPath ?: null,
                    (int) $user['id'],
                ]);
                $pdo->prepare("
                    INSERT INTO buyer_profiles (user_id, business_name, delivery_phone, delivery_address, delivery_notes, preferred_state, preferred_lga, buyer_type, interests)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE business_name=VALUES(business_name), delivery_phone=VALUES(delivery_phone), delivery_address=VALUES(delivery_address), delivery_notes=VALUES(delivery_notes), preferred_state=VALUES(preferred_state), preferred_lga=VALUES(preferred_lga), buyer_type=VALUES(buyer_type), interests=VALUES(interests)
                ")->execute([
                    (int) $user['id'],
                    trim((string) $_POST['business_name']),
                    trim((string) $_POST['delivery_phone']),
                    trim((string) $_POST['delivery_address']),
                    trim((string) $_POST['delivery_notes']),
                    trim((string) $_POST['preferred_state']),
                    trim((string) $_POST['preferred_lga']),
                    trim((string) $_POST['buyer_type']),
                    trim((string) $_POST['interests']),
                ]);
                $msg = 'Buyer profile updated.';
            }
            if ($action === 'password') {
                $current = (string) ($_POST['current_password'] ?? '');
                $new = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['confirm_password'] ?? '');
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int) $user['id']]);
                $hash = (string) $stmt->fetchColumn();
                if (!password_verify($current, $hash)) {
                    throw new RuntimeException('Current password is incorrect.');
                }
                if (strlen($new) < 8 || $new !== $confirm) {
                    throw new RuntimeException('New password must be at least 8 characters and match confirmation.');
                }
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
                $msg = 'Password changed.';
            }
            $user = buyer_user($pdo) ?: $user;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$profileStmt = $pdo->prepare("SELECT * FROM buyer_profiles WHERE user_id=? LIMIT 1");
$profileStmt->execute([(int) $user['id']]);
$profile = $profileStmt->fetch() ?: [];
$photo = !empty($user['profile_picture']) ? '../' . ltrim((string) $user['profile_picture'], '/') : app_primary_logo_url();

buyer_page_start('Buyer Profile & Security', 'profile', $user, buyer_counts($pdo, $user));
?>
<div class="page-head"><div><h1>Buyer Profile & Security</h1><p>Manage contact details, delivery preferences, profile photo, and password.</p></div></div>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<div class="grid">
  <form method="post" enctype="multipart/form-data" class="card form-grid span-8">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="profile">
    <div class="wide card-head"><h2>Profile Details</h2><img src="<?= e($photo) ?>" alt="" style="width:62px;height:62px;border-radius:50%;object-fit:cover;border:1px solid var(--line)"></div>
    <label>Name<input name="name" value="<?= e((string) $user['name']) ?>" required></label>
    <label>Phone<input name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>"></label>
    <label>Email<input value="<?= e((string) $user['email']) ?>" disabled></label>
    <label>Location<input name="location" value="<?= e((string) ($user['location'] ?? '')) ?>"></label>
    <label>Buyer Type<select name="buyer_type"><?php foreach (['individual' => 'Individual Buyer', 'business' => 'Business Buyer', 'institution' => 'Institution / Cooperative'] as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) ($profile['buyer_type'] ?? 'individual') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Business Name<input name="business_name" value="<?= e((string) ($profile['business_name'] ?? '')) ?>"></label>
    <label>Preferred State<input name="preferred_state" value="<?= e((string) ($profile['preferred_state'] ?? '')) ?>"></label>
    <label>Preferred LGA<input name="preferred_lga" value="<?= e((string) ($profile['preferred_lga'] ?? '')) ?>"></label>
    <label>Delivery Phone<input name="delivery_phone" value="<?= e((string) ($profile['delivery_phone'] ?? '')) ?>"></label>
    <label>Profile Photo<input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"></label>
    <label class="wide">Delivery Address<textarea name="delivery_address"><?= e((string) ($profile['delivery_address'] ?? '')) ?></textarea></label>
    <label class="wide">Delivery Notes<textarea name="delivery_notes"><?= e((string) ($profile['delivery_notes'] ?? '')) ?></textarea></label>
    <label class="wide">Interests<textarea name="interests"><?= e((string) ($profile['interests'] ?? '')) ?></textarea></label>
    <div class="wide"><button class="btn">Save Profile</button></div>
  </form>
  <form method="post" class="card form-grid span-4">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="password">
    <div class="wide card-head"><h2>Change Password</h2></div>
    <label class="wide">Current Password<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label class="wide">New Password<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
    <label class="wide">Confirm Password<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
    <div class="wide"><button class="btn">Update Password</button></div>
  </form>
</div>
<?php buyer_page_end(); ?>
