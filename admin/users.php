<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';

$pdo = db();
admin_ensure_schema($pdo);
fm_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';
$roles = [
    'grower' => 'Grower',
    'field_agent' => 'Field Agent',
    'agronomist' => 'Agronomist',
    'extensionist' => 'Agric Extensionist',
    'admin' => 'Admin',
];

function admin_user_display_role(array $user): string
{
    return admin_display_staff_type($user);
}

function admin_role_to_storage(string $role): array
{
    return [admin_staff_role_to_auth_role($role), $role === 'agronomist' ? 1 : 0, $role === 'extensionist' ? 1 : 0, $role];
}

function admin_normalize_upload_header(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
    return trim($header, '_');
}

function admin_uploaded_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $normalized = admin_normalize_upload_header($key);
        if (isset($row[$normalized]) && trim((string) $row[$normalized]) !== '') {
            return trim((string) $row[$normalized]);
        }
    }
    return '';
}

function admin_normalize_uploaded_role(string $role, string $defaultRole): string
{
    $role = strtolower(trim(str_replace(['-', '_'], ' ', $role)));
    if ($role === '') {
        return $defaultRole;
    }

    if (str_contains($role, 'admin')) {
        return 'admin';
    }
    if (str_contains($role, 'agronomist')) {
        return 'agronomist';
    }
    if (str_contains($role, 'extension')) {
        return 'extensionist';
    }
    if (str_contains($role, 'field') || str_contains($role, 'agent')) {
        return 'field_agent';
    }
    if (str_contains($role, 'grower') || str_contains($role, 'farmer') || str_contains($role, 'investor')) {
        return 'grower';
    }

    return $defaultRole;
}

function admin_upload_decimal(string $value): float
{
    $value = preg_replace('/[^0-9.]+/', '', $value) ?? '';
    return $value === '' ? 0.0 : (float) $value;
}

function admin_import_app_ref(): string
{
    return 'IMP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function admin_application_for_uploaded_grower(PDO $pdo, string $name, string $email, string $phone, string $address, float $farmSize): ?int
{
    if ($phone === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM applications WHERE email = ? OR phone = ? LIMIT 1');
    $stmt->execute([$email, $phone]);
    $existingId = (int) ($stmt->fetchColumn() ?: 0);
    if ($existingId > 0) {
        return $existingId;
    }

    $insert = $pdo->prepare("
        INSERT INTO applications
            (app_ref, name, location, farm_size, phone, whatsapp, email, commitments, confirmed, confirmation_token, email_sent)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0)
    ");

    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            $insert->execute([
                admin_import_app_ref(),
                $name,
                $address !== '' ? $address : 'Imported by admin',
                $farmSize,
                $phone,
                $phone,
                $email,
                'Imported from admin bulk onboarding.',
                bin2hex(random_bytes(32)),
            ]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    return null;
}

function admin_column_index_from_ref(string $cellRef): int
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef)) ?? '';
    $index = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }
    return max(0, $index - 1);
}

function admin_zip_uint16(string $bytes, int $offset): int
{
    $value = unpack('v', substr($bytes, $offset, 2));
    return (int) ($value[1] ?? 0);
}

function admin_zip_uint32(string $bytes, int $offset): int
{
    $value = unpack('V', substr($bytes, $offset, 4));
    return (int) ($value[1] ?? 0);
}

function admin_zip_entry(string $path, string $entryName): ?string
{
    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open XLSX file.');
        }
        $contents = $zip->getFromName($entryName);
        $zip->close();
        return $contents === false ? null : $contents;
    }

    $bytes = file_get_contents($path);
    if ($bytes === false) {
        throw new RuntimeException('Unable to open XLSX file.');
    }

    $eocd = strrpos($bytes, "PK\x05\x06");
    if ($eocd === false) {
        throw new RuntimeException('Uploaded XLSX is not a valid ZIP package.');
    }

    $centralOffset = admin_zip_uint32($bytes, $eocd + 16);
    $position = $centralOffset;
    $length = strlen($bytes);

    while ($position + 46 <= $length && substr($bytes, $position, 4) === "PK\x01\x02") {
        $method = admin_zip_uint16($bytes, $position + 10);
        $compressedSize = admin_zip_uint32($bytes, $position + 20);
        $nameLength = admin_zip_uint16($bytes, $position + 28);
        $extraLength = admin_zip_uint16($bytes, $position + 30);
        $commentLength = admin_zip_uint16($bytes, $position + 32);
        $localOffset = admin_zip_uint32($bytes, $position + 42);
        $name = substr($bytes, $position + 46, $nameLength);

        if ($name === $entryName) {
            if (substr($bytes, $localOffset, 4) !== "PK\x03\x04") {
                throw new RuntimeException('Uploaded XLSX has an invalid worksheet entry.');
            }
            $localNameLength = admin_zip_uint16($bytes, $localOffset + 26);
            $localExtraLength = admin_zip_uint16($bytes, $localOffset + 28);
            $dataStart = $localOffset + 30 + $localNameLength + $localExtraLength;
            $compressed = substr($bytes, $dataStart, $compressedSize);

            if ($method === 0) {
                return $compressed;
            }
            if ($method === 8) {
                $inflated = gzinflate($compressed);
                if ($inflated === false) {
                    throw new RuntimeException('Unable to decompress XLSX worksheet data.');
                }
                return $inflated;
            }
            throw new RuntimeException('Unsupported XLSX compression method.');
        }

        $position += 46 + $nameLength + $extraLength + $commentLength;
    }

    return null;
}

function admin_parse_xlsx_upload(string $path): array
{
    $sharedStrings = [];
    $sharedXml = admin_zip_entry($path, 'xl/sharedStrings.xml');
    if ($sharedXml !== null) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                $parts = [];
                if (isset($si->t)) {
                    $parts[] = (string) $si->t;
                }
                foreach ($si->r ?? [] as $run) {
                    $parts[] = (string) ($run->t ?? '');
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $sheetXml = admin_zip_entry($path, 'xl/worksheets/sheet1.xml');
    if ($sheetXml === null) {
        throw new RuntimeException('No first worksheet found in uploaded XLSX.');
    }

    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Unable to read XLSX worksheet.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $xmlRow) {
        $values = [];
        foreach ($xmlRow->c as $cell) {
            $attrs = $cell->attributes();
            $index = admin_column_index_from_ref((string) ($attrs['r'] ?? ''));
            $type = (string) ($attrs['t'] ?? '');
            $value = '';

            if ($type === 'inlineStr') {
                $value = (string) ($cell->is->t ?? '');
            } else {
                $raw = (string) ($cell->v ?? '');
                $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
            }

            $values[$index] = trim($value);
        }

        if ($values !== []) {
            ksort($values);
            $max = max(array_keys($values));
            $rows[] = array_map(static fn($value) => (string) $value, array_replace(array_fill(0, $max + 1, ''), $values));
        }
    }

    return $rows;
}

function admin_parse_csv_upload(string $path): array
{
    return app_csv_import_rows($path);
}

function admin_parse_user_upload(string $path, string $originalName): array
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $rawRows = $extension === 'xlsx'
        ? admin_parse_xlsx_upload($path)
        : admin_parse_csv_upload($path);

    $rawRows = array_values(array_filter($rawRows, static function (array $row): bool {
        return count(array_filter($row, static fn($value) => trim((string) $value) !== '')) > 0;
    }));

    if (count($rawRows) < 2) {
        throw new RuntimeException('Upload must include a header row and at least one user row.');
    }

    $headers = array_map('admin_normalize_upload_header', array_shift($rawRows));
    $rows = [];
    foreach ($rawRows as $rawRow) {
        $mapped = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $mapped[$header] = $rawRow[$index] ?? '';
            }
        }
        $rows[] = $mapped;
    }

    return $rows;
}

function admin_onboard_uploaded_users(PDO $pdo, array $rows, string $defaultRole, bool $sendNotifications, array $roles): array
{
    $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

    $findUser = $pdo->prepare('SELECT id, application_id FROM users WHERE email = ? LIMIT 1');
    $insertUser = $pdo->prepare("
        INSERT INTO users (name, email, phone, location, application_id, password, role, is_agronomist, is_extensionist, agronomist_license, staff_specialty)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $updateUser = $pdo->prepare("
        UPDATE users
        SET name = ?, phone = ?, location = ?, application_id = COALESCE(application_id, ?), role = ?, is_agronomist = ?, is_extensionist = ?, agronomist_license = ?, staff_specialty = ?
        WHERE id = ?
    ");

    foreach ($rows as $index => $row) {
        $line = $index + 2;
        $name = admin_uploaded_value($row, ['name', 'full name', 'full_name', 'applicant name', 'first name']);
        $email = strtolower(admin_uploaded_value($row, ['email', 'email address', 'e-mail']));
        $phone = admin_uploaded_value($row, ['phone', 'phone number', 'mobile', 'telephone', 'whatsapp']);
        $uploadedRole = admin_uploaded_value($row, ['role', 'user role', 'staff role', 'category', 'type']);
        $selectedRole = admin_normalize_uploaded_role($uploadedRole, $defaultRole);
        $license = admin_uploaded_value($row, ['license', 'license number', 'agronomist license', 'certification number']);
        $qualification = admin_uploaded_value($row, ['qualification', 'highest qualification', 'education']);
        $state = admin_uploaded_value($row, ['state']);
        $lga = admin_uploaded_value($row, ['lga', 'local government', 'local government area']);
        $address = admin_uploaded_value($row, ['address', 'location', 'farm location', 'residential address']);
        $farmSize = admin_upload_decimal(admin_uploaded_value($row, ['number of acres hectares', 'number of acres/hectares', 'farm size', 'acreage', 'hectares', 'acres']));
        $experience = admin_uploaded_value($row, ['experience', 'years of experience', 'experience years']);
        $availability = admin_uploaded_value($row, ['availability', 'available from']);
        $trainingProgram = admin_uploaded_value($row, ['training program', 'certification program', 'natcodev training']);
        $password = admin_uploaded_value($row, ['password', 'temporary password']);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($roles[$selectedRole])) {
            $summary['skipped']++;
            $summary['errors'][] = "Row {$line}: missing name, valid email, or supported role.";
            continue;
        }

        if ($password === '' || strlen($password) < 8) {
            $password = strtoupper(bin2hex(random_bytes(4)));
        }

        [$storedRole, $isAgronomist, $isExtensionist, $specialty] = admin_role_to_storage($selectedRole);
        $applicationId = $selectedRole === 'grower'
            ? admin_application_for_uploaded_grower($pdo, $name, $email, $phone, $address, $farmSize)
            : null;
        $profileData = [
            'license_number' => $license !== '' ? $license : null,
            'qualification' => $qualification !== '' ? $qualification : null,
            'state' => $state !== '' ? $state : null,
            'lga' => $lga !== '' ? $lga : null,
            'experience_years' => is_numeric($experience) ? (int) $experience : null,
            'availability' => $availability !== '' ? $availability : null,
            'training_program' => $trainingProgram !== '' ? $trainingProgram : null,
        ];

        $findUser->execute([$email]);
        $existingUser = $findUser->fetch();
        $existingId = (int) ($existingUser['id'] ?? 0);

        if ($existingId > 0) {
            $updateUser->execute([
                $name,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $applicationId,
                $storedRole,
                $isAgronomist,
                $isExtensionist,
                $isAgronomist ? ($license !== '' ? $license : null) : null,
                $specialty,
                $existingId,
            ]);
            if ($selectedRole !== 'grower') {
                admin_upsert_staff_profile($pdo, $existingId, $selectedRole, $profileData);
            } else {
                $profileStmt = $pdo->prepare('SELECT id, staff_type, status FROM staff_profiles WHERE user_id = ? LIMIT 1');
                $profileStmt->execute([$existingId]);
                $staffProfile = $profileStmt->fetch();
                if ($staffProfile && admin_current_user_is_super_admin($pdo)) {
                    $pdo->prepare('DELETE FROM staff_profiles WHERE id = ?')->execute([(int) $staffProfile['id']]);
                } elseif ($staffProfile) {
                    admin_queue_verified_delete_request($pdo, 'staff_profiles', (int) $staffProfile['id'], $name . ' staff profile', 'Staff profile removal requested during role update.');
                }
            }
            $summary['updated']++;
            continue;
        }

        $insertUser->execute([
            $name,
            $email,
            $phone !== '' ? $phone : null,
            $address !== '' ? $address : null,
            $applicationId,
            password_hash($password, PASSWORD_DEFAULT),
            $storedRole,
            $isAgronomist,
            $isExtensionist,
            $isAgronomist ? ($license !== '' ? $license : null) : null,
            $specialty,
        ]);
        $userId = (int) $pdo->lastInsertId();
        if ($selectedRole !== 'grower') {
            admin_upsert_staff_profile($pdo, $userId, $selectedRole, $profileData);
        }

        if ($sendNotifications) {
            $fallback = "Hello {name}, your NATCODEV {$roles[$selectedRole]} account has been created. Login: {login_url}. Temporary password: {temporary_password}";
            natcodev_notify_user($pdo, $userId, 'bulk_user_onboarded', 'Your NATCODEV account is ready', [
                'role' => $roles[$selectedRole],
                'temporary_password' => $password,
            ], $fallback);
        }

        $summary['created']++;
    }

    return $summary;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'update_role');

        if ($action === 'create_user') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $selectedRole = (string) ($_POST['role'] ?? '');
            $license = trim((string) ($_POST['agronomist_license'] ?? ''));

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !isset($roles[$selectedRole]) || $selectedRole === 'grower') {
                $error = 'Provide name, valid email, password of at least 8 characters, and a staff role.';
            } else {
                [$storedRole, $isAgronomist, $isExtensionist, $specialty] = admin_role_to_storage($selectedRole);
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, phone, application_id, password, role, is_agronomist, is_extensionist, agronomist_license, staff_specialty)
                        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $name,
                        $email,
                        $phone !== '' ? $phone : null,
                        password_hash($password, PASSWORD_DEFAULT),
                        $storedRole,
                        $isAgronomist,
                        $isExtensionist,
                        $isAgronomist ? ($license !== '' ? $license : null) : null,
                        $specialty,
                    ]);
                    $newUserId = (int) $pdo->lastInsertId();
                    admin_upsert_staff_profile($pdo, $newUserId, $selectedRole, [
                        'license_number' => $license !== '' ? $license : null,
                    ]);
                    natcodev_notify_user($pdo, $newUserId, 'bulk_user_onboarded', 'Your NATCODEV staff account is ready', [
                        'role' => $roles[$selectedRole],
                        'temporary_password' => $password,
                        'login_url' => app_base_url() . '/login.php',
                        'field_agent_url' => app_base_url() . '/field-agent/',
                    ], "Hello {name}, your NATCODEV {$roles[$selectedRole]} account has been created. Login: {login_url}. Temporary password: {temporary_password}. Field agent console: {field_agent_url}");
                    $message = $roles[$selectedRole] . ' user created and onboarding message sent.';
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $error = 'A user with that email already exists.';
                    } else {
                        throw $e;
                    }
                }
            }
        } elseif ($action === 'bulk_upload') {
            $error = 'Bulk imports have moved to Import & Engagement for audit and user confirmation.';
        } else {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $selectedRole = (string) ($_POST['role'] ?? 'grower');
            $license = trim((string) ($_POST['agronomist_license'] ?? ''));

            if ($userId > 0 && isset($roles[$selectedRole])) {
                [$storedRole, $isAgronomist, $isExtensionist, $specialty] = admin_role_to_storage($selectedRole);
                $pdo->prepare("
                    UPDATE users
                    SET role = ?, is_agronomist = ?, is_extensionist = ?, agronomist_license = ?, staff_specialty = ?
                    WHERE id = ?
                ")->execute([
                    $storedRole,
                    $isAgronomist,
                    $isExtensionist,
                    $isAgronomist ? ($license !== '' ? $license : null) : null,
                    $specialty,
                    $userId,
                ]);
                if ($selectedRole === 'grower') {
                    $profileStmt = $pdo->prepare('SELECT id, staff_type, status FROM staff_profiles WHERE user_id = ? LIMIT 1');
                    $profileStmt->execute([$userId]);
                    $staffProfile = $profileStmt->fetch();
                    if ($staffProfile && admin_current_user_is_super_admin($pdo)) {
                        $pdo->prepare('DELETE FROM staff_profiles WHERE id = ?')->execute([(int) $staffProfile['id']]);
                        $message = 'User role updated.';
                    } elseif ($staffProfile) {
                        admin_queue_verified_delete_request($pdo, 'staff_profiles', (int) $staffProfile['id'], 'User #' . $userId . ' staff profile', 'Staff profile removal requested during role update.');
                        $message = 'Role update saved; staff profile deletion sent to Super Admin for approval.';
                    } else {
                        $message = 'User role updated.';
                    }
                } else {
                    admin_upsert_staff_profile($pdo, $userId, $selectedRole, [
                        'license_number' => $license !== '' ? $license : null,
                    ]);
                    $message = 'User role updated.';
                }
            }
        }
    }
}

$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$scopeState = admin_current_scope_state($pdo);
$roleFilter = (string) ($_GET['role'] ?? '');
$where = [];
$params = [];
if ($roleFilter === 'grower') {
    $where[] = "u.role = 'grower'";
} elseif ($roleFilter === 'field_agent') {
    $where[] = "u.role = 'field_agent' AND COALESCE(sp.staff_type, 'field_agent') = 'field_agent'";
} elseif ($roleFilter === 'agronomist') {
    $where[] = "(u.platform_role = 'agronomist' OR u.is_agronomist = 1 OR sp.staff_type = 'agronomist')";
} elseif (in_array($roleFilter, ['extensionist', 'agric_extensionist'], true)) {
    $where[] = "(u.platform_role = 'agric_extensionist' OR u.is_extensionist = 1 OR sp.staff_type IN ('extensionist','agric_extensionist'))";
}
if ($scopeState !== '') {
    $where[] = "(sp.state = ? OR ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)";
    array_push($params, $scopeState, $scopeState, '%' . $scopeState . '%', '%' . $scopeState . '%');
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON u.application_id = a.id
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    {$whereSql}
");
$totalStmt->execute($params);
$totalUsers = (int) $totalStmt->fetchColumn();

$usersStmt = $pdo->prepare("
    SELECT u.*, a.app_ref, sp.staff_type, sp.license_number, sp.certification_status, sp.training_program
    FROM users u
    LEFT JOIN applications a ON u.application_id = a.id
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    {$whereSql}
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();

admin_page_start('Users', [
    'active' => 'users.php',
    'description' => 'Create and manage individual staff accounts. Spreadsheet onboarding now lives in Import & Engagement so bulk work is easier to find and audit.',
    'wide' => true,
    'action_html' => '<a class="button" href="import-users.php">Import Users</a>',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php if ($scopeState !== ''): ?><div class="notice ok">State Coordinator scope: showing people attached to <?= e($scopeState) ?>.</div><?php endif; ?>

<section class="layout">
  <aside>
    <form class="panel" method="post">
      <h2>Create Staff User</h2>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_user">
      <label>Name</label>
      <input type="text" name="name" required>
      <label>Email</label>
      <input type="email" name="email" required>
      <label>Phone</label>
      <input type="text" name="phone">
      <label>Temporary Password</label>
      <div class="password-field">
        <input id="staff_temp_password" type="password" name="password" minlength="8" required>
        <button class="password-toggle" type="button" data-target="staff_temp_password" aria-pressed="false">Show</button>
      </div>
      <label>Staff Role</label>
      <select name="role" required>
        <option value="">Select role</option>
        <option value="field_agent">Field Agent</option>
        <option value="agronomist">Agronomist</option>
        <option value="extensionist">Agric Extensionist</option>
        <option value="admin">Admin</option>
      </select>
      <label>Agronomist License</label>
      <input type="text" name="agronomist_license" placeholder="Required only for agronomists">
      <div class="actions"><button type="submit">Create User</button></div>
      <p class="meta">Growers should continue to come through the public application confirmation flow.</p>
    </form>
  </aside>

  <section>
    <form class="toolbar" method="get">
      <label>Role
        <select name="role" onchange="this.form.submit()">
          <option value="">All roles</option>
          <option value="grower" <?= $roleFilter === 'grower' ? 'selected' : '' ?>>Growers</option>
          <option value="field_agent" <?= $roleFilter === 'field_agent' ? 'selected' : '' ?>>Field Agents</option>
          <option value="agronomist" <?= $roleFilter === 'agronomist' ? 'selected' : '' ?>>Agronomists</option>
          <option value="extensionist" <?= in_array($roleFilter, ['extensionist','agric_extensionist'], true) ? 'selected' : '' ?>>Agric Extensionists</option>
        </select>
      </label>
      <input type="hidden" name="page" value="1">
    </form>
    <?= admin_pagination_controls($totalUsers, $page, $perPage) ?>
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Reference</th><th>Role</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <?php $displayRole = admin_user_display_role($u); ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?><?php if (!empty($u['phone'])): ?><br><small><?= e($u['phone']) ?></small><?php endif; ?></td>
            <td><?= e($u['app_ref'] ?? '') ?></td>
            <td>
              <form method="post" class="toolbar">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <select name="role">
                  <?php foreach ($roles as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $displayRole === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="agronomist_license" value="<?= e($u['license_number'] ?? $u['agronomist_license'] ?? '') ?>" placeholder="License / staff certification">
                <button type="submit">Save</button>
              </form>
            </td>
            <td>
              <?php if (in_array($displayRole, ['field_agent', 'agronomist', 'extensionist'], true)): ?>
                <a class="button secondary" href="assign-growers.php?agent=<?= (int) $u['id'] ?>">Assignments</a>
              <?php else: ?>
                <span class="muted"><?= $displayRole === 'grower' ? 'Application user' : 'System access' ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="5">No users found.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?= admin_pagination_controls($totalUsers, $page, $perPage) ?>
  </section>
</section>
<?php admin_page_end(); ?>
