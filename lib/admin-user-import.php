<?php
declare(strict_types=1);

function admin_import_roles(): array
{
    return [
        'grower' => 'Grower / Legacy Farmer',
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'extensionist' => 'Agric Extensionist',
        'admin' => 'Admin',
    ];
}

function admin_import_role_storage(string $role): array
{
    return [admin_staff_role_to_auth_role($role), $role === 'agronomist' ? 1 : 0, $role === 'extensionist' ? 1 : 0, $role];
}

function admin_ensure_import_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_import_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_ref VARCHAR(40) NOT NULL,
            source_file VARCHAR(255) NOT NULL,
            source_row INT NOT NULL,
            name VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            alternate_email VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            phone_e164 VARCHAR(20) NULL,
            alternate_phone VARCHAR(50) NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'grower',
            address VARCHAR(255) NULL,
            farm_size DECIMAL(10,2) NOT NULL DEFAULT 0,
            state VARCHAR(120) NULL,
            lga VARCHAR(120) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            status_note TEXT NULL,
            application_id INT NULL,
            user_id INT NULL,
            engagement_token VARCHAR(80) NULL,
            engagement_deadline DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at DATETIME NULL,
            INDEX idx_import_batch (batch_ref),
            INDEX idx_import_status (status),
            INDEX idx_import_phone (phone_e164),
            INDEX idx_import_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_add_column_if_missing($pdo, 'user_import_records', 'phone_e164', "VARCHAR(20) NULL");
    app_add_column_if_missing($pdo, 'user_import_records', 'alternate_email', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'user_import_records', 'alternate_phone', "VARCHAR(50) NULL");
    app_add_column_if_missing($pdo, 'user_import_records', 'engagement_channel', "VARCHAR(80) NULL");
    app_add_column_if_missing($pdo, 'user_import_records', 'engagement_deadline', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'user_import_records', 'source_row', "INT NOT NULL DEFAULT 0");
    app_ensure_primary_auto_increment($pdo, 'user_import_records');
}

function admin_import_mark_expired(PDO $pdo): int
{
    admin_ensure_import_schema($pdo);
    $stmt = $pdo->prepare("
        UPDATE user_import_records
        SET status = 'expired_no_engagement',
            status_note = 'Engagement deadline passed without user confirmation.'
        WHERE status IN ('pending_engagement', 'pending_phone_engagement')
          AND engagement_deadline IS NOT NULL
          AND engagement_deadline < NOW()
    ");
    $stmt->execute();
    return $stmt->rowCount();
}

function admin_import_header(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
    return trim($header, '_');
}

function admin_import_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $normalized = admin_import_header($key);
        if (isset($row[$normalized]) && trim((string) $row[$normalized]) !== '') {
            return trim((string) $row[$normalized]);
        }
    }
    return '';
}

function admin_import_role(string $role, string $defaultRole): string
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

function admin_import_decimal(string $value): float
{
    $value = preg_replace('/[^0-9.]+/', '', $value) ?? '';
    return $value === '' ? 0.0 : (float) $value;
}

function admin_import_emails(string $value): array
{
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);
    $emails = [];
    foreach ($matches[0] ?? [] as $email) {
        $email = strtolower(trim($email, " \t\n\r\0\x0B,;:<>"));
        if (strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] = $email;
        }
    }
    return array_values($emails);
}

function admin_import_phone_e164(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '234') && strlen($digits) === 13) {
        return '+' . $digits;
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        return '+234' . substr($digits, 1);
    }
    if (strlen($digits) === 10) {
        return '+234' . $digits;
    }
    return '';
}

function admin_import_phones(string $value): array
{
    preg_match_all('/(?:\+?234|0)?[789][01]\d[\s().-]*\d{3}[\s().-]*\d{4}/', $value, $matches);
    $phones = [];
    foreach ($matches[0] ?? [] as $phone) {
        $normalized = admin_import_phone_e164($phone);
        if ($normalized !== '') {
            $phones[$normalized] = $normalized;
        }
    }

    if ($phones === []) {
        $normalized = admin_import_phone_e164($value);
        if ($normalized !== '') {
            $phones[$normalized] = $normalized;
        }
    }
    return array_values($phones);
}

function admin_import_phone_exists(PDO $pdo, string $phoneE164): bool
{
    if ($phoneE164 === '') {
        return false;
    }
    $local = '0' . substr($phoneE164, 4);
    $plain = substr($phoneE164, 1);
    $stmt = $pdo->prepare("SELECT 1 FROM applications WHERE phone IN (?, ?, ?) OR whatsapp IN (?, ?, ?) LIMIT 1");
    $stmt->execute([$phoneE164, $local, $plain, $phoneE164, $local, $plain]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM user_import_records WHERE phone_e164 = ? AND status <> 'skipped' LIMIT 1");
    $stmt->execute([$phoneE164]);
    return (bool) $stmt->fetchColumn();
}

function admin_import_column_index(string $cellRef): int
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef)) ?? '';
    $index = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }
    return max(0, $index - 1);
}

function admin_import_zip16(string $bytes, int $offset): int
{
    $value = unpack('v', substr($bytes, $offset, 2));
    return (int) ($value[1] ?? 0);
}

function admin_import_zip32(string $bytes, int $offset): int
{
    $value = unpack('V', substr($bytes, $offset, 4));
    return (int) ($value[1] ?? 0);
}

function admin_import_zip_entry(string $path, string $entryName): ?string
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

    $position = admin_import_zip32($bytes, $eocd + 16);
    $length = strlen($bytes);
    while ($position + 46 <= $length && substr($bytes, $position, 4) === "PK\x01\x02") {
        $method = admin_import_zip16($bytes, $position + 10);
        $compressedSize = admin_import_zip32($bytes, $position + 20);
        $nameLength = admin_import_zip16($bytes, $position + 28);
        $extraLength = admin_import_zip16($bytes, $position + 30);
        $commentLength = admin_import_zip16($bytes, $position + 32);
        $localOffset = admin_import_zip32($bytes, $position + 42);
        $name = substr($bytes, $position + 46, $nameLength);

        if ($name === $entryName) {
            $localNameLength = admin_import_zip16($bytes, $localOffset + 26);
            $localExtraLength = admin_import_zip16($bytes, $localOffset + 28);
            $dataStart = $localOffset + 30 + $localNameLength + $localExtraLength;
            $compressed = substr($bytes, $dataStart, $compressedSize);
            if ($method === 0) {
                return $compressed;
            }
            if ($method === 8) {
                $inflated = gzinflate($compressed);
                if ($inflated === false) {
                    throw new RuntimeException('Unable to decompress XLSX data.');
                }
                return $inflated;
            }
            throw new RuntimeException('Unsupported XLSX compression method.');
        }
        $position += 46 + $nameLength + $extraLength + $commentLength;
    }
    return null;
}

function admin_import_xlsx_rows(string $path): array
{
    $sharedStrings = [];
    $sharedXml = admin_import_zip_entry($path, 'xl/sharedStrings.xml');
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

    $sheetXml = admin_import_zip_entry($path, 'xl/worksheets/sheet1.xml');
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
            $index = admin_import_column_index((string) ($attrs['r'] ?? ''));
            $type = (string) ($attrs['t'] ?? '');
            $raw = (string) ($cell->v ?? '');
            $values[$index] = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : ($type === 'inlineStr' ? (string) ($cell->is->t ?? '') : $raw);
        }
        if ($values !== []) {
            ksort($values);
            $rows[] = array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values);
        }
    }
    return $rows;
}

function admin_import_csv_rows(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to open CSV file.');
    }
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_map(static fn($value) => trim((string) $value), $row);
    }
    fclose($handle);
    return $rows;
}

function admin_import_mapped_rows(string $path, string $filename): array
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $rawRows = $extension === 'xlsx' ? admin_import_xlsx_rows($path) : admin_import_csv_rows($path);
    $rawRows = array_values(array_filter($rawRows, static fn(array $row): bool => count(array_filter($row, static fn($value) => trim((string) $value) !== '')) > 0));
    if (count($rawRows) < 2) {
        throw new RuntimeException('Upload must include a header row and at least one user row.');
    }

    $headers = array_map('admin_import_header', array_shift($rawRows));
    $rows = [];
    foreach ($rawRows as $index => $rawRow) {
        $mapped = ['_row_number' => $index + 2];
        foreach ($headers as $column => $header) {
            if ($header !== '') {
                $mapped[$header] = trim((string) ($rawRow[$column] ?? ''));
            }
        }
        $rows[] = $mapped;
    }
    return $rows;
}

function admin_import_app_ref(): string
{
    return 'IMP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function admin_import_insert_application(PDO $pdo, array $record, string $token): ?int
{
    if (!filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $phoneE164 = $record['phone_e164'] ?? admin_import_phone_e164((string) $record['phone']);
    $localPhone = $phoneE164 !== '' ? '0' . substr($phoneE164, 4) : $record['phone'];
    $existing = $pdo->prepare('SELECT id FROM applications WHERE email IN (?, ?) OR phone IN (?, ?, ?) OR whatsapp IN (?, ?, ?) LIMIT 1');
    $existing->execute([
        $record['email'],
        $record['alternate_email'] ?? '',
        $record['phone'],
        $phoneE164,
        $localPhone,
        $record['phone'],
        $phoneE164,
        $localPhone,
    ]);
    $existingId = (int) ($existing->fetchColumn() ?: 0);
    if ($existingId > 0) {
        $pdo->prepare('UPDATE applications SET confirmation_token = COALESCE(confirmation_token, ?), confirmed = confirmed WHERE id = ?')->execute([$token, $existingId]);
        return $existingId;
    }

    $insert = $pdo->prepare("
        INSERT INTO applications
            (app_ref, name, location, farm_size, phone, whatsapp, email, alternate_email, alternate_phone, commitments, confirmed, confirmation_token, email_sent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0)
    ");
    $insert->execute([
        admin_import_app_ref(),
        $record['name'],
        $record['address'] ?: 'Imported by admin',
        $record['farm_size'],
        $phoneE164 ?: $record['phone'],
        $phoneE164 ?: $record['phone'],
        $record['email'],
        $record['alternate_email'] ?: null,
        $record['alternate_phone'] ?: null,
        'Imported from admin legacy onboarding. Awaiting user engagement confirmation.',
        $token,
    ]);
    return (int) $pdo->lastInsertId();
}

function admin_import_send_confirmation(PDO $pdo, int $applicationId, bool $email = true, bool $sms = true, bool $whatsapp = true): bool
{
    $stmt = $pdo->prepare('SELECT id, app_ref, name, email, phone, whatsapp, confirmation_token FROM applications WHERE id = ? LIMIT 1');
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    if (!$app || empty($app['confirmation_token'])) {
        return false;
    }

    $confirmUrl = app_base_url() . '/confirm_email.php?token=' . urlencode((string) $app['confirmation_token']);
    $plain = "Dear {$app['name']},\n\nNATCODEV has imported your legacy grower record ({$app['app_ref']}). Please confirm your engagement here:\n{$confirmUrl}\n\nIf you did not expect this, ignore this message.\n\nThe NATCODEV Team";
    $short = "NATCODEV: Confirm your grower engagement ({$app['app_ref']}): {$confirmUrl}";
    $sent = false;
    if ($email && !empty($app['email'])) {
        $sent = app_send_mail((string) $app['email'], 'Confirm Your NATCODEV Engagement', $plain) || $sent;
    }
    if ($sms && !empty($app['phone'])) {
        $sent = sendSMSMessage((string) $app['phone'], $short) || $sent;
    }
    if ($whatsapp && !empty($app['whatsapp'])) {
        $sent = sendWhatsAppMessage((string) $app['whatsapp'], $short) || $sent;
    }
    if ($sent) {
        $pdo->prepare('UPDATE applications SET email_sent = 1 WHERE id = ?')->execute([$applicationId]);
    }
    return $sent;
}

function admin_import_send_phone_engagement(array $record, string $token): bool
{
    if (empty($record['phone_e164'])) {
        return false;
    }
    $url = app_base_url() . '/confirm-engagement.php?token=' . urlencode($token);
    $message = "NATCODEV: Confirm your grower engagement here: {$url}";
    return sendSMSMessage((string) $record['phone_e164'], $message)
        || sendWhatsAppMessage((string) $record['phone_e164'], $message);
}

function admin_import_create_staff_user(PDO $pdo, array $record, string $password): int
{
    [$storedRole, $isAgronomist, $isExtensionist, $specialty] = admin_import_role_storage($record['role']);
    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$record['email']]);
    $userId = (int) ($existing->fetchColumn() ?: 0);

    if ($userId > 0) {
        $pdo->prepare("
            UPDATE users
            SET name = ?, phone = ?, location = ?, role = ?, is_agronomist = ?, is_extensionist = ?, staff_specialty = ?
            WHERE id = ?
        ")->execute([$record['name'], $record['phone'] ?: null, $record['address'] ?: null, $storedRole, $isAgronomist, $isExtensionist, $specialty, $userId]);
    } else {
        $pdo->prepare("
            INSERT INTO users (name, email, phone, location, application_id, password, role, is_agronomist, is_extensionist, staff_specialty)
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
        ")->execute([$record['name'], $record['email'], $record['phone'] ?: null, $record['address'] ?: null, password_hash($password, PASSWORD_DEFAULT), $storedRole, $isAgronomist, $isExtensionist, $specialty]);
        $userId = (int) $pdo->lastInsertId();
    }

    admin_upsert_staff_profile($pdo, $userId, $record['role'], [
        'state' => $record['state'] ?: null,
        'lga' => $record['lga'] ?: null,
        'qualification' => $record['qualification'] ?: null,
        'training_program' => $record['training_program'] ?: null,
        'availability' => $record['availability'] ?: null,
    ]);
    return $userId;
}

function admin_import_process(PDO $pdo, string $path, string $filename, string $defaultRole, bool $sendNotifications): array
{
    @set_time_limit(600);
    ignore_user_abort(true);

    $roles = admin_import_roles();
    $rows = admin_import_mapped_rows($path, $filename);
    $batch = 'BATCH-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $summary = ['batch' => $batch, 'total' => count($rows), 'staff' => 0, 'pending' => 0, 'needs_contact' => 0, 'skipped' => 0, 'failed' => 0];
    $recordStmt = $pdo->prepare("
        INSERT INTO user_import_records
            (batch_ref, source_file, source_row, name, email, alternate_email, phone, phone_e164, alternate_phone, role, address, farm_size, state, lga, status, status_note, application_id, user_id, engagement_token, engagement_channel, engagement_deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $existingImportRow = $pdo->prepare("
        SELECT status
        FROM user_import_records
        WHERE source_file = ? AND source_row = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    foreach ($rows as $row) {
        $rawEmail = admin_import_value($row, ['email', 'email address', 'e-mail', 'alternate email', 'email 2']);
        $rawPhone = admin_import_value($row, ['phone', 'phone number', 'mobile', 'telephone', 'whatsapp', 'alternate phone', 'phone 2']);
        $emails = admin_import_emails($rawEmail);
        $phones = admin_import_phones($rawPhone);
        $record = [
            'row_number' => (int) $row['_row_number'],
            'name' => admin_import_value($row, ['name', 'full name', 'applicant name']),
            'email' => $emails[0] ?? '',
            'alternate_email' => implode(', ', array_slice($emails, 1)),
            'phone' => $phones[0] ?? $rawPhone,
            'alternate_phone' => implode(', ', array_slice($phones, 1)),
            'role' => admin_import_role(admin_import_value($row, ['role', 'user role', 'staff role', 'category', 'type']), $defaultRole),
            'address' => admin_import_value($row, ['address', 'location', 'farm location', 'residential address']),
            'farm_size' => admin_import_decimal(admin_import_value($row, ['number of acres hectares', 'number of acres/hectares', 'farm size', 'acreage', 'hectares', 'acres'])),
            'state' => admin_import_value($row, ['state']),
            'lga' => admin_import_value($row, ['lga', 'local government', 'local government area']),
            'qualification' => admin_import_value($row, ['qualification', 'highest qualification', 'education']),
            'training_program' => admin_import_value($row, ['training program', 'certification program', 'natcodev training']),
            'availability' => admin_import_value($row, ['availability']),
        ];
        $record['phone_e164'] = admin_import_phone_e164($record['phone']);

        $existingImportRow->execute([$filename, $record['row_number']]);
        $existingStatus = (string) ($existingImportRow->fetchColumn() ?: '');
        if ($existingStatus !== '' && $existingStatus !== 'failed') {
            $summary['skipped']++;
            continue;
        }

        $status = 'skipped';
        $note = '';
        $applicationId = null;
        $userId = null;
        $token = bin2hex(random_bytes(32));

        try {
            if ($record['name'] === '' || ($record['email'] === '' && $record['phone_e164'] === '')) {
                $status = 'skipped';
                $note = 'Missing name and at least one valid contact channel.';
                $summary['skipped']++;
            } elseif (!isset($roles[$record['role']])) {
                $status = 'skipped';
                $note = 'Unsupported role.';
                $summary['skipped']++;
            } elseif ($record['role'] === 'grower') {
                if ($record['phone_e164'] !== '' && admin_import_phone_exists($pdo, $record['phone_e164'])) {
                    $status = 'skipped';
                    $note = 'Duplicate phone number already exists in applications or imports.';
                    $summary['skipped']++;
                } elseif (filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                    $applicationId = admin_import_insert_application($pdo, $record, $token);
                    if ($applicationId !== null && $sendNotifications) {
                        admin_import_send_confirmation($pdo, $applicationId, true, $record['phone_e164'] !== '', $record['phone_e164'] !== '');
                    }
                    $status = 'pending_engagement';
                    $note = $sendNotifications ? 'Confirmation link sent by available email/SMS/WhatsApp channels.' : 'Application staged; confirmation not sent yet.';
                    if ($record['alternate_email'] !== '' || $record['alternate_phone'] !== '') {
                        $note .= ' Alternate contacts captured.';
                    }
                    $summary['pending']++;
                } elseif ($record['phone_e164'] !== '') {
                    if ($sendNotifications) {
                        admin_import_send_phone_engagement($record, $token);
                    }
                    $status = 'pending_phone_engagement';
                    $note = $sendNotifications ? 'Phone confirmation link sent by SMS/WhatsApp. User adds email during confirmation.' : 'Phone engagement staged; confirmation not sent yet.';
                    $summary['pending']++;
                } else {
                    $status = 'needs_contact';
                    $note = 'No valid email or phone for engagement.';
                    $summary['needs_contact']++;
                }
            } elseif (!filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                $status = 'needs_contact';
                $note = 'Staff accounts require a valid email.';
                $summary['needs_contact']++;
            } else {
                $password = strtoupper(bin2hex(random_bytes(4)));
                $userId = admin_import_create_staff_user($pdo, $record, $password);
                if ($sendNotifications) {
                    natcodev_notify_user($pdo, $userId, 'bulk_user_onboarded', 'Your NATCODEV account is ready', [
                        'role' => $roles[$record['role']],
                        'temporary_password' => $password,
                    ], 'Hello {name}, your NATCODEV {role} account has been created. Login: {login_url}. Temporary password: {temporary_password}. Field agent console: {field_agent_url}');
                }
                $status = 'staff_onboarded';
                $note = 'Staff account created/updated.';
                $summary['staff']++;
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $note = 'Row failed: ' . mb_substr($e->getMessage(), 0, 450);
            $applicationId = null;
            $userId = null;
            $summary['failed']++;
            error_log("Import {$batch} row {$record['row_number']} failed: " . $e->getMessage());
        }

        $recordStmt->execute([
            $batch,
            $filename,
            $record['row_number'],
            $record['name'] ?: null,
            $record['email'] ?: null,
            $record['alternate_email'] ?: null,
            $record['phone'] ?: null,
            $record['phone_e164'] ?: null,
            $record['alternate_phone'] ?: null,
            $record['role'],
            $record['address'] ?: null,
            $record['farm_size'],
            $record['state'] ?: null,
            $record['lga'] ?: null,
            $status,
            $note,
            $applicationId,
            $userId,
            $token,
            $record['phone_e164'] !== '' ? 'phone' : 'email',
            in_array($status, ['pending_engagement', 'pending_phone_engagement'], true) ? date('Y-m-d H:i:s', strtotime('+14 days')) : null,
        ]);
    }

    return $summary;
}
