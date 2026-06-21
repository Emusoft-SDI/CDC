<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST method required'], 405);
}

if (!verify_csrf($_POST['_csrf'] ?? null)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session. Please refresh the page.'], 403);
}

if (!app_check_rate_limit('registration', 12, 3600)) {
    json_response(['success' => false, 'message' => 'Too many registration attempts. Please try again in an hour.'], 429);
}

$name = trim((string) ($_POST['name'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$stateId = filter_input(INPUT_POST, 'state_id', FILTER_VALIDATE_INT) ?: null;
$lgaId = filter_input(INPUT_POST, 'lga_id', FILTER_VALIDATE_INT) ?: null;
$farmSize = filter_var($_POST['farm_size'] ?? null, FILTER_VALIDATE_FLOAT);
$phone = preg_replace('/[^0-9]/', '', (string) ($_POST['phone'] ?? ''));
$whatsapp = preg_replace('/[^0-9]/', '', (string) ($_POST['whatsapp'] ?? $phone));
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$commitments = trim((string) ($_POST['commitments'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($name === '' || $location === '' || $farmSize === false || $phone === '' || !$email || $commitments === '') {
    json_response(['success' => false, 'message' => 'All fields are required'], 422);
}

if ($farmSize < 1 || $farmSize > 1000) {
    json_response(['success' => false, 'message' => 'Farm size must be between 1 and 1000 hectares'], 422);
}

if (!preg_match('/^0[7-9][01][0-9]{8}$/', $phone)) {
    json_response(['success' => false, 'message' => 'Invalid Nigerian phone number'], 422);
}

try {
    $pdo = db();
    app_ensure_core_schema($pdo);

    $stmt = $pdo->prepare("SELECT id, app_ref, name, email, confirmed, confirmation_token FROM applications WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $existing = $stmt->fetch();

    if ($existing && (int) $existing['confirmed'] === 1) {
        json_response([
            'success' => false,
            'message' => 'This email or phone is already registered and confirmed.',
            'already_confirmed' => true,
        ], 409);
    }

    $applicationId = 0;
    if ($existing) {
        $applicationId = (int) $existing['id'];
        $appRef = $existing['app_ref'];
        $token = $existing['confirmation_token'] ?: bin2hex(random_bytes(32));
        $pdo->prepare("
            UPDATE applications
            SET name = ?, location = ?, state_id = ?, lga_id = ?, farm_size = ?, whatsapp = ?, commitments = ?, confirmation_token = ?, created_at = NOW()
            WHERE id = ?
        ")->execute([$name, $location, $stateId, $lgaId, $farmSize, $whatsapp, $commitments, $token, $existing['id']]);
        $action = 'resent';
    } else {
        $appRef = generate_application_ref();
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("
            INSERT INTO applications (
                app_ref, name, location, state_id, lga_id, farm_size, phone, whatsapp, email, commitments,
                confirmation_token, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $appRef,
            $name,
            $location,
            $stateId,
            $lgaId,
            $farmSize,
            $phone,
            $whatsapp ?: null,
            $email,
            $commitments,
            $token,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $applicationId = (int) $pdo->lastInsertId();
        $action = 'submitted';
    }

    if ($password !== '' && strlen($password) >= 6 && $applicationId > 0) {
        app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
        app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(40) NOT NULL DEFAULT 'active'");
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, application_id, name, phone, location, role, platform_role, account_status)
            VALUES (?, ?, ?, ?, ?, ?, 'grower', 'grower', 'active')
            ON DUPLICATE KEY UPDATE application_id = VALUES(application_id), name = VALUES(name), phone = VALUES(phone), location = VALUES(location), platform_role = COALESCE(platform_role, VALUES(platform_role)), account_status = 'active'
        ");
        $stmt->execute([(string) $email, $passwordHash, $applicationId, $name, $phone, $location]);
    }

    $confirmUrl = app_base_url() . '/confirm_email.php?token=' . urlencode($token);
    $plain = "Dear {$name},\n\nThank you for applying to the NATCODEV Coconut Outgrowers Program.\n\nConfirm your email here:\n{$confirmUrl}\n\nThis link expires in 7 days.\n\nThe NATCODEV Team";
    $html = "
        <p>Dear <strong>" . e($name) . "</strong>,</p>
        <p>Thank you for applying to the <strong>NATCODEV Coconut Outgrowers Program</strong>.</p>
        <p><a href=\"" . e($confirmUrl) . "\" style=\"display:inline-block;padding:10px 18px;background:#2d5016;color:#fff;text-decoration:none;border-radius:5px;\">Confirm My Email</a></p>
        <p>This link expires in 7 days.</p>
        <p>The NATCODEV Team</p>
    ";

    $emailSent = app_send_mail((string) $email, 'Confirm Your NATCODEV Application', $plain, $html);
    if ($emailSent) {
        $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE app_ref = ?")->execute([$appRef]);
    }

    json_response([
        'success' => true,
        'app_ref' => $appRef,
        'email_sent' => $emailSent,
        'resend' => $action === 'resent',
        'message' => "Application {$action} successfully. Please check your email to confirm.",
    ]);
} catch (Throwable $e) {
    error_log('Application submission error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Submission temporarily unavailable. Please try again.'], 500);
}
