<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/twilio.php';
require_once __DIR__ . '/../lib/nigeria-locations.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
fm_ensure_schema($pdo);

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$userId = (int) $_SESSION['user_id'];
$message = '';
$error = '';
$otpRequired = false;

require_once __DIR__ . '/../lib/profile-helpers.php';
profile_helpers_ensure_schema($pdo);


$stmt = $pdo->prepare("
    SELECT u.*,
           a.app_ref, a.farm_size, a.state_id, a.lga_id, a.street_address, a.latitude, a.longitude, a.whatsapp,
           a.climate_zone, a.topography, a.soil_type, a.water_source, a.irrigation_method,
           a.coconut_variety, a.intercrops, a.livestock_integration,
           a.land_ownership_status, a.land_title_details, a.current_farm_activities,
           a.production_stage, a.estimated_tree_count, a.annual_yield_estimate,
           a.farming_practices, a.major_challenges, a.support_needs, a.market_channels,
           sp.staff_type, sp.qualification, sp.license_number, sp.experience_years, sp.certification_status, sp.training_program, sp.availability, sp.status staff_status
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    redirect_to('login.php');
}

if (($user['role'] ?? 'grower') === 'grower' && (int) ($user['application_id'] ?? 0) > 0) {
    $primaryFarm = $pdo->prepare("SELECT id FROM grower_farms WHERE user_id = ? AND application_id = ? LIMIT 1");
    $primaryFarm->execute([$userId, (int) $user['application_id']]);
    if (!$primaryFarm->fetchColumn()) {
        $pdo->prepare("
            INSERT INTO grower_farms
                (user_id, application_id, farm_name, farm_size, state_id, lga_id, street_address, latitude, longitude, is_primary,
                 climate_zone, topography, soil_type, water_source, irrigation_method, coconut_variety, intercrops, livestock_integration,
                 land_ownership_status, land_title_details, current_farm_activities, production_stage, estimated_tree_count,
                 annual_yield_estimate, farming_practices, major_challenges, support_needs, market_channels)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $userId,
            (int) $user['application_id'],
            'Primary Farm',
            $user['farm_size'] ?? null,
            $user['state_id'] ?? null,
            $user['lga_id'] ?? null,
            $user['street_address'] ?? null,
            $user['latitude'] ?? null,
            $user['longitude'] ?? null,
            $user['climate_zone'] ?? null,
            $user['topography'] ?? null,
            $user['soil_type'] ?? null,
            $user['water_source'] ?? null,
            $user['irrigation_method'] ?? null,
            $user['coconut_variety'] ?? null,
            $user['intercrops'] ?? null,
            $user['livestock_integration'] ?? null,
            $user['land_ownership_status'] ?? null,
            $user['land_title_details'] ?? null,
            $user['current_farm_activities'] ?? null,
            $user['production_stage'] ?? null,
            $user['estimated_tree_count'] ?? null,
            $user['annual_yield_estimate'] ?? null,
            $user['farming_practices'] ?? null,
            $user['major_challenges'] ?? null,
            $user['support_needs'] ?? null,
            $user['market_channels'] ?? null,
        ]);
    }
}

$states = $pdo->query("SELECT id, state_name, state_code FROM nigeria_states ORDER BY state_name")->fetchAll();
$farmStmt = $pdo->prepare("
    SELECT gf.*, s.state_name, l.lga_name, COALESCE(fv.status, 'pending') verification_status, fv.system_confidence_score, fv.system_notes
    FROM grower_farms gf
    LEFT JOIN nigeria_states s ON s.id = gf.state_id
    LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    WHERE gf.user_id = ?
    ORDER BY gf.is_primary DESC, gf.created_at ASC, gf.id ASC
");
$farmStmt->execute([$userId]);
$growerFarms = $farmStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save_profile');
        if ($action === 'update_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if ($current === '' || $new === '' || $confirm === '') {
                $error = 'All password fields are required.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $passwordStmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $passwordStmt->execute([$userId]);
                $hash = (string) $passwordStmt->fetchColumn();
                if ($hash !== '' && password_verify($current, $hash)) {
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([
                        password_hash($new, PASSWORD_DEFAULT),
                        $userId,
                    ]);
                    $message = 'Password updated successfully.';
                } else {
                    $error = 'Current password is incorrect.';
                }
            }
            goto profile_post_done;
        }

        if ($action === 'send_profile_otp') {
            $otp = profile_send_update_otp($pdo, $user);
            $message = 'OTP ' . profile_otp_delivery_message($otp) . '. It expires in 10 minutes.';
            goto profile_post_done;
        }

        $phone = trim((string) ($_POST['phone'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $dob = trim((string) ($_POST['dob'] ?? '')) ?: null;
        $maritalStatus = trim((string) ($_POST['marital_status'] ?? '')) ?: null;
        $familySize = $_POST['family_size'] === '' ? null : max(0, (int) $_POST['family_size']);
        $education = trim((string) ($_POST['education_level'] ?? '')) ?: null;
        $experience = $_POST['farming_experience_years'] === '' ? null : max(0, (int) $_POST['farming_experience_years']);
        $experienceRating = trim((string) ($_POST['farming_experience_rating'] ?? '')) ?: null;
        $kinName = trim((string) ($_POST['next_of_kin_name'] ?? ''));
        $kinPhone = trim((string) ($_POST['next_of_kin_phone'] ?? ''));
        $kinRelationship = trim((string) ($_POST['next_of_kin_relationship'] ?? ''));
        $notifyEmail = (int) ($user['notify_email'] ?? 1);
        $notifyWhatsapp = (int) ($user['notify_whatsapp'] ?? 0);
        $notifySms = (int) ($user['notify_sms'] ?? 0);
        $applicationData = [];
        $applicationData['lga_id'] = profile_valid_lga_id($pdo, filter_input(INPUT_POST, 'lga_id', FILTER_VALIDATE_INT) ?: null, $applicationData['state_id']);
        $profilePicture = null;
        $profileData = [
            'phone' => $phone,
            'location' => $location,
            'dob' => $dob,
            'marital_status' => $maritalStatus,
            'family_size' => $familySize,
            'education_level' => $education,
            'farming_experience_years' => $experience,
            'farming_experience_rating' => $experienceRating,
            'next_of_kin_name' => $kinName,
            'next_of_kin_phone' => $kinPhone,
            'next_of_kin_relationship' => $kinRelationship,
            'notify_email' => $notifyEmail,
            'notify_whatsapp' => $notifyWhatsapp,
            'notify_sms' => $notifySms,
        ];

        $needsOtp = profile_update_needs_otp($user, $profileData);
        $otpCode = preg_replace('/[^0-9]/', '', (string) ($_POST['otp_code'] ?? ''));

        if ($needsOtp && $otpCode === '') {
            $otp = profile_send_update_otp($pdo, $user);
            $otpRequired = true;
            $message = 'Security code ' . profile_otp_delivery_message($otp) . '. Enter the OTP to save these profile changes.';
        } elseif ($needsOtp && !profile_verify_update_otp($pdo, $userId, $otpCode)) {
            $otpRequired = true;
            $error = 'Invalid or expired OTP. Request a new code and try again.';
        }

        if (!$otpRequired && $error === '' && !empty($_FILES['profile_picture']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $original = (string) $_FILES['profile_picture']['name'];
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $error = 'Profile picture must be JPG, PNG, or WebP.';
            } elseif ((int) ($_FILES['profile_picture']['size'] ?? 0) > 2 * 1024 * 1024) {
                $error = 'Profile picture must be 2MB or smaller.';
            } else {
                $uploadDir = dirname(__DIR__) . '/uploads/profile-pictures';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = 'user_' . $userId . '_' . time() . '.' . $ext;
                if (move_uploaded_file((string) $_FILES['profile_picture']['tmp_name'], $uploadDir . '/' . $fileName)) {
                    $profilePicture = 'uploads/profile-pictures/' . $fileName;
                } else {
                    $error = 'Unable to upload profile picture.';
                }
            }
        }

        if (!$otpRequired && $error === '') {
            $pictureSql = $profilePicture ? ', profile_picture = ?' : '';
            $stmt = $pdo->prepare("
                UPDATE users
                SET phone = ?, location = ?, notify_email = ?, notify_whatsapp = ?, notify_sms = ?,
                    dob = ?, marital_status = ?, family_size = ?, education_level = ?, farming_experience_years = ?, farming_experience_rating = ?,
                    next_of_kin_name = ?, next_of_kin_phone = ?, next_of_kin_relationship = ?
                    {$pictureSql}
                WHERE id = ?
            ");
            $params = [
                $phone,
                $location,
                $notifyEmail,
                $notifyWhatsapp,
                $notifySms,
                $dob,
                $maritalStatus,
                $familySize,
                $education,
                $experience,
                $experienceRating,
                $kinName,
                $kinPhone,
                $kinRelationship,
            ];
            if ($profilePicture) {
                $params[] = $profilePicture;
            }
            $params[] = $userId;
            $stmt->execute($params);

            if ((int) ($user['application_id'] ?? 0) > 0) {
                $appStmt = $pdo->prepare("
                    UPDATE applications
                    SET farm_size = COALESCE(?, farm_size),
                        state_id = ?, lga_id = ?, street_address = ?, latitude = ?, longitude = ?, whatsapp = ?,
                        climate_zone = ?, topography = ?, soil_type = ?, water_source = ?, irrigation_method = ?,
                        coconut_variety = ?, intercrops = ?, livestock_integration = ?,
                        land_ownership_status = ?, land_title_details = ?,
                        current_farm_activities = ?, production_stage = ?, estimated_tree_count = ?,
                        annual_yield_estimate = ?, farming_practices = ?, major_challenges = ?, support_needs = ?, market_channels = ?
                    WHERE id = ?
                ");
                $appStmt->execute([
                    $applicationData['farm_size'],
                    $applicationData['state_id'],
                    $applicationData['lga_id'],
                    $applicationData['street_address'],
                    $applicationData['latitude'],
                    $applicationData['longitude'],
                    $applicationData['whatsapp'],
                    $applicationData['climate_zone'],
                    $applicationData['topography'],
                    $applicationData['soil_type'],
                    $applicationData['water_source'],
                    $applicationData['irrigation_method'],
                    $applicationData['coconut_variety'],
                    $applicationData['intercrops'],
                    $applicationData['livestock_integration'],
                    $applicationData['land_ownership_status'],
                    $applicationData['land_title_details'],
                    $applicationData['current_farm_activities'],
                    $applicationData['production_stage'],
                    $applicationData['estimated_tree_count'],
                    $applicationData['annual_yield_estimate'],
                    $applicationData['farming_practices'],
                    $applicationData['major_challenges'],
                    $applicationData['support_needs'],
                    $applicationData['market_channels'],
                    (int) $user['application_id'],
                ]);
                $pdo->prepare("
                    UPDATE grower_farms
                    SET farm_size = ?, state_id = ?, lga_id = ?, street_address = ?, latitude = ?, longitude = ?,
                        climate_zone = ?, topography = ?, soil_type = ?, water_source = ?, irrigation_method = ?,
                        coconut_variety = ?, intercrops = ?, livestock_integration = ?, land_ownership_status = ?, land_title_details = ?,
                        current_farm_activities = ?, production_stage = ?, estimated_tree_count = ?, annual_yield_estimate = ?,
                        farming_practices = ?, major_challenges = ?, support_needs = ?, market_channels = ?
                    WHERE user_id = ? AND application_id = ?
                ")->execute([
                    $applicationData['farm_size'],
                    $applicationData['state_id'],
                    $applicationData['lga_id'],
                    $applicationData['street_address'],
                    $applicationData['latitude'],
                    $applicationData['longitude'],
                    $applicationData['climate_zone'],
                    $applicationData['topography'],
                    $applicationData['soil_type'],
                    $applicationData['water_source'],
                    $applicationData['irrigation_method'],
                    $applicationData['coconut_variety'],
                    $applicationData['intercrops'],
                    $applicationData['livestock_integration'],
                    $applicationData['land_ownership_status'],
                    $applicationData['land_title_details'],
                    $applicationData['current_farm_activities'],
                    $applicationData['production_stage'],
                    $applicationData['estimated_tree_count'],
                    $applicationData['annual_yield_estimate'],
                    $applicationData['farming_practices'],
                    $applicationData['major_challenges'],
                    $applicationData['support_needs'],
                    $applicationData['market_channels'],
                    $userId,
                    (int) $user['application_id'],
                ]);
            }
            $message = 'Profile updated successfully.';
            $stmt = $pdo->prepare("
                SELECT u.*,
                       a.app_ref, a.farm_size, a.state_id, a.lga_id, a.street_address, a.latitude, a.longitude, a.whatsapp,
                       a.climate_zone, a.topography, a.soil_type, a.water_source, a.irrigation_method,
                       a.coconut_variety, a.intercrops, a.livestock_integration,
                       a.land_ownership_status, a.land_title_details, a.current_farm_activities,
                       a.production_stage, a.estimated_tree_count, a.annual_yield_estimate,
                       a.farming_practices, a.major_challenges, a.support_needs, a.market_channels,
                       sp.staff_type, sp.qualification, sp.license_number, sp.experience_years, sp.certification_status, sp.training_program, sp.availability, sp.status staff_status
                FROM users u
                LEFT JOIN applications a ON a.id = u.application_id
                LEFT JOIN staff_profiles sp ON sp.user_id = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch() ?: $user;
        }
    }
}
profile_post_done:
?>
<?php dashboard_page_start('Profile', ['active' => 'profile.php', 'description' => 'Keep your contact details, next of kin, and notification preferences current.', 'wide' => true]); ?>
<?php $profileForm = $otpRequired && isset($profileData) ? array_merge($user, $profileData, $applicationData ?? []) : $user; ?>
<h1>Profile</h1>
<link rel="stylesheet" href="../assets/css/farm-profile.css">
    <?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <?php if (profile_otp_production_warning() !== ''): ?><p class="error"><?= e(profile_otp_production_warning()) ?></p><?php endif; ?>
    <?php if ($otpRequired): ?><p class="notice pending">Enter the OTP sent to your current phone/email, then submit again. Re-select profile picture only after OTP is accepted.</p><?php endif; ?>
    <?php if (!empty($profileForm['profile_picture'])): ?>
      <p><img src="../<?= e($profileForm['profile_picture']) ?>" alt="Profile picture" style="width:120px;height:120px;object-fit:cover;border-radius:50%;border:1px solid #d8e2dc;"></p>
    <?php endif; ?>
    <?php require __DIR__ . '/../lib/layout-components/profile-tabs.php'; ?>
    <?php require __DIR__ . '/partials/farm-profile-form.php'; ?>
  <?php dashboard_page_end(); ?>
