<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/notification-dispatch.php';
require_once __DIR__ . '/platform-revenue.php';


function grower_member_types(): array
{
    return [
        'individual' => 'Individual Farmer',
        'corporate' => 'Corporate Farm',
        'cooperative' => 'Cooperative',
    ];
}

function grower_member_type_label(string $type): string
{
    $types = grower_member_types();
    return $types[$type] ?? $types['individual'];
}

function grower_member_type_normalize(string $type): string
{
    $type = strtolower(trim($type));
    return array_key_exists($type, grower_member_types()) ? $type : 'individual';
}

function grower_registration_ensure_member_schema(PDO $pdo): void
{
    app_add_column_if_missing($pdo, 'applications', 'member_type', "VARCHAR(40) NOT NULL DEFAULT 'individual'");
    app_add_column_if_missing($pdo, 'applications', 'business_name', "VARCHAR(180) NULL");
    app_add_column_if_missing($pdo, 'applications', 'business_registration_number', "VARCHAR(120) NULL");
    app_add_column_if_missing($pdo, 'applications', 'business_address', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'applications', 'representative_name', "VARCHAR(180) NULL");
    app_add_column_if_missing($pdo, 'applications', 'cooperative_name', "VARCHAR(180) NULL");
    app_add_column_if_missing($pdo, 'applications', 'cooperative_registration_number', "VARCHAR(120) NULL");
    app_add_column_if_missing($pdo, 'applications', 'cooperative_members_count', "INT NULL");
}

function grower_certificate_setting(PDO $pdo, string $key, string $default = ''): string
{
    if (!app_table_exists($pdo, 'settings')) {
        return $default;
    }
    try {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key_name = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function grower_certificate_save_setting(PDO $pdo, string $key, string $value): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, key_name VARCHAR(120) NOT NULL UNIQUE, value TEXT NULL, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stmt = $pdo->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()');
    $stmt->execute([$key, $value]);
}

function grower_certificate_fee_config(PDO $pdo): array
{
    return [
        'enabled' => grower_certificate_setting($pdo, 'grower_certificate_access_fee_enabled', '1') === '1',
        'individual_fee' => max(0.0, (float) grower_certificate_setting($pdo, 'grower_certificate_fee_individual', '2500')),
        'corporate_fee' => max(0.0, (float) grower_certificate_setting($pdo, 'grower_certificate_fee_corporate', '10000')),
        'cooperative_fee' => max(0.0, (float) grower_certificate_setting($pdo, 'grower_certificate_fee_cooperative', '15000')),
        'individual_validity_months' => max(1, min(120, (int) grower_certificate_setting($pdo, 'grower_certificate_validity_individual', '12'))),
        'corporate_validity_months' => max(1, min(120, (int) grower_certificate_setting($pdo, 'grower_certificate_validity_corporate', '12'))),
        'cooperative_validity_months' => max(1, min(120, (int) grower_certificate_setting($pdo, 'grower_certificate_validity_cooperative', '12'))),
    ];
}

function grower_certificate_fee_amount(PDO $pdo, string $memberType): float
{
    $memberType = grower_member_type_normalize($memberType);
    $config = grower_certificate_fee_config($pdo);
    return (float) $config[$memberType . '_fee'];
}

function grower_certificate_access_validity_months(PDO $pdo, string $memberType): int
{
    $memberType = grower_member_type_normalize($memberType);
    $config = grower_certificate_fee_config($pdo);
    return (int) $config[$memberType . '_validity_months'];
}

function grower_certificate_access_ensure_schema(PDO $pdo): void
{
    grower_registration_ensure_member_schema($pdo);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS certificate_access_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            application_id INT NOT NULL,
            certificate_id INT NULL,
            certificate_ref VARCHAR(100) NULL,
            member_type VARCHAR(40) NOT NULL DEFAULT 'individual',
            access_type VARCHAR(40) NOT NULL DEFAULT 'view_download',
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'paid',
            reference VARCHAR(120) NOT NULL UNIQUE,
            paid_at DATETIME NULL,
            valid_until DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cert_access_user (user_id, application_id, status),
            INDEX idx_cert_access_ref (certificate_ref, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'certificate_access_payments');
}

function provider_accreditation_setting(PDO $pdo, string $key, string $default = ''): string
{
    if (!app_table_exists($pdo, 'settings')) {
        return $default;
    }
    try {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key_name = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function provider_accreditation_save_setting(PDO $pdo, string $key, string $value): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, key_name VARCHAR(120) NOT NULL UNIQUE, value TEXT NULL, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stmt = $pdo->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()');
    $stmt->execute([$key, $value]);
}

function provider_accreditation_fee_config(PDO $pdo): array
{
    return [
        'enabled' => provider_accreditation_setting($pdo, 'provider_accreditation_access_fee_enabled', '0') === '1',
        'amount' => max(0.0, (float) provider_accreditation_setting($pdo, 'provider_accreditation_access_fee_amount', '0')),
        'validity_months' => max(1, min(120, (int) provider_accreditation_setting($pdo, 'provider_accreditation_access_validity_months', '12'))),
    ];
}

function provider_accreditation_fee_amount(PDO $pdo): float
{
    $config = provider_accreditation_fee_config($pdo);
    return (float) $config['amount'];
}

function provider_accreditation_validity_months(PDO $pdo): int
{
    $config = provider_accreditation_fee_config($pdo);
    return (int) $config['validity_months'];
}

function provider_accreditation_access_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS provider_accreditation_access_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            provider_id INT NOT NULL,
            certificate_id INT NULL,
            certificate_ref VARCHAR(100) NULL,
            access_type VARCHAR(40) NOT NULL DEFAULT 'view_download',
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'paid',
            reference VARCHAR(120) NOT NULL UNIQUE,
            paid_at DATETIME NULL,
            valid_until DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_provider_access_user (user_id, provider_id, status),
            INDEX idx_provider_access_provider (provider_id, status),
            INDEX idx_provider_access_ref (certificate_ref, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'provider_accreditation_access_payments');
}

function provider_accreditation_access_status(PDO $pdo, int $userId, int $providerId, ?int $certificateId = null): array
{
    provider_accreditation_access_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM provider_accreditation_access_payments WHERE user_id = ? AND provider_id = ? AND status = 'paid' AND (valid_until IS NULL OR valid_until >= NOW()) ORDER BY paid_at DESC, id DESC LIMIT 1");
    $stmt->execute([$userId, $providerId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return ['paid' => (bool) $payment, 'payment' => $payment];
}

function provider_accreditation_access_required(PDO $pdo, array $provider): bool
{
    $config = provider_accreditation_fee_config($pdo);
    return (bool) $config['enabled'] && provider_accreditation_fee_amount($pdo) > 0;
}

function provider_accreditation_access_validity_months(PDO $pdo): int
{
    return provider_accreditation_validity_months($pdo);
}

function provider_accreditation_pay_access(PDO $pdo, int $userId, int $providerId, ?int $certificateId = null, string $accessType = 'view_download'): array
{
    provider_accreditation_access_ensure_schema($pdo);
    wallet_ensure_schema($pdo);
    revenue_ensure_schema($pdo);

    $amount = provider_accreditation_fee_amount($pdo);
    if ($amount <= 0 || !provider_accreditation_access_required($pdo, ['id' => $providerId])) {
        return ['success' => true, 'reference' => 'NO-FEE', 'amount' => 0.0];
    }

    $existingAccess = provider_accreditation_access_status($pdo, $userId, $providerId, $certificateId);
    if (!empty($existingAccess['paid']) && !empty($existingAccess['payment'])) {
        return [
            'success' => true,
            'reference' => (string) ($existingAccess['payment']['reference'] ?? 'EXISTING-ACCESS'),
            'amount' => (float) ($existingAccess['payment']['amount'] ?? 0),
            'valid_until' => (string) ($existingAccess['payment']['valid_until'] ?? ''),
            'duplicate' => true,
        ];
    }

    $certificateRef = null;
    if ($certificateId) {
        $certStmt = $pdo->prepare('SELECT * FROM provider_accreditation_certificates WHERE id = ? AND provider_id = ? LIMIT 1');
        $certStmt->execute([$certificateId, $providerId]);
        $certificate = $certStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $certificateRef = $certificate ? (string) ($certificate['certificate_ref'] ?? '') : null;
    }

    $wallet = wallet_get_or_create($pdo, $userId);
    $reference = 'NAT-PROV-CERT-ACCESS-' . $userId . '-' . $providerId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $validUntil = date('Y-m-d H:i:s', strtotime('+' . provider_accreditation_access_validity_months($pdo) . ' months'));

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM wallets WHERE id = ? FOR UPDATE');
        $lock->execute([(int) $wallet['id']]);
        $wallet = $lock->fetch(PDO::FETCH_ASSOC);
        $before = (float) ($wallet['balance'] ?? 0);
        if ($before + 0.01 < $amount) {
            throw new RuntimeException('Insufficient wallet balance. Fund your wallet and try again.');
        }
        $after = $before - $amount;
        $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?')->execute([$after, (int) $wallet['id']]);
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id,user_id,amount,type,direction,description,reference,provider,provider_reference,provider_payload,status,balance_before,balance_after,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")->execute([
            (int) $wallet['id'], $userId, $amount, 'debit', 'outflow', 'Provider accreditation certificate access fee', $reference, 'provider_accreditation_access', $reference,
            json_encode(['provider_id' => $providerId, 'certificate_id' => $certificateId, 'access_type' => $accessType], JSON_UNESCAPED_SLASHES),
            'completed', $before, $after,
        ]);
        $pdo->prepare("INSERT INTO provider_accreditation_access_payments (user_id, provider_id, certificate_id, certificate_ref, access_type, amount, status, reference, paid_at, valid_until) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, NOW(), ?)")
            ->execute([$userId, $providerId, $certificateId ?: null, $certificateRef, $accessType, $amount, $reference, $validUntil]);
        revenue_record_once($pdo, [
            'revenue_ref' => 'REV-PROV-CERT-' . $reference,
            'source_module' => 'registry',
            'source_type' => 'provider_accreditation_fee',
            'source_id' => (int) $pdo->lastInsertId(),
            'user_id' => $userId,
            'gross_amount' => $amount,
            'revenue_amount' => $amount,
            'net_payable_amount' => 0,
            'rule_key' => 'provider_accreditation',
            'description' => 'Provider accreditation certificate access fee',
            'metadata' => ['provider_id' => $providerId, 'certificate_id' => $certificateId],
        ]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['success' => true, 'reference' => $reference, 'amount' => $amount, 'valid_until' => $validUntil];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function grower_certificate_access_status(PDO $pdo, int $userId, int $applicationId, ?int $certificateId = null): array
{
    grower_certificate_access_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM certificate_access_payments WHERE user_id = ? AND application_id = ? AND status = 'paid' AND (valid_until IS NULL OR valid_until >= NOW()) ORDER BY paid_at DESC, id DESC LIMIT 1");
    $stmt->execute([$userId, $applicationId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return ['paid' => (bool) $payment, 'payment' => $payment];
}

function grower_certificate_access_required(PDO $pdo, array $application): bool
{
    $config = grower_certificate_fee_config($pdo);
    $memberType = grower_member_type_normalize((string) ($application['member_type'] ?? 'individual'));
    return (bool) $config['enabled'] && grower_certificate_fee_amount($pdo, $memberType) > 0;
}

function grower_certificate_pay_access(PDO $pdo, int $userId, int $applicationId, ?int $certificateId = null, string $accessType = 'view_download'): array
{
    grower_certificate_access_ensure_schema($pdo);
    wallet_ensure_schema($pdo);
    revenue_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ? LIMIT 1');
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        throw new RuntimeException('Application record was not found.');
    }
    $memberType = grower_member_type_normalize((string) ($application['member_type'] ?? 'individual'));
    $amount = grower_certificate_fee_amount($pdo, $memberType);
    if ($amount <= 0 || !grower_certificate_access_required($pdo, $application)) {
        return ['success' => true, 'reference' => 'NO-FEE', 'amount' => 0.0];
    }

    $existingAccess = grower_certificate_access_status($pdo, $userId, $applicationId, $certificateId);
    if (!empty($existingAccess['paid']) && !empty($existingAccess['payment'])) {
        return [
            'success' => true,
            'reference' => (string) ($existingAccess['payment']['reference'] ?? 'EXISTING-ACCESS'),
            'amount' => (float) ($existingAccess['payment']['amount'] ?? 0),
            'valid_until' => (string) ($existingAccess['payment']['valid_until'] ?? ''),
            'duplicate' => true,
        ];
    }
    $certificate = null;
    if ($certificateId) {
        $certStmt = $pdo->prepare('SELECT * FROM certificates WHERE id = ? AND user_id = ? LIMIT 1');
        $certStmt->execute([$certificateId, $userId]);
        $certificate = $certStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $wallet = wallet_get_or_create($pdo, $userId);
    $reference = 'NAT-CERT-ACCESS-' . $userId . '-' . $applicationId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $validUntil = date('Y-m-d H:i:s', strtotime('+' . grower_certificate_access_validity_months($pdo, $memberType) . ' months'));
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM wallets WHERE id = ? FOR UPDATE');
        $lock->execute([(int) $wallet['id']]);
        $wallet = $lock->fetch(PDO::FETCH_ASSOC);
        $before = (float) ($wallet['balance'] ?? 0);
        if ($before + 0.01 < $amount) {
            throw new RuntimeException('Insufficient wallet balance. Fund your wallet and try again.');
        }
        $after = $before - $amount;
        $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?')->execute([$after, (int) $wallet['id']]);
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id,user_id,amount,type,direction,description,reference,provider,provider_reference,provider_payload,status,balance_before,balance_after,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")->execute([
            (int) $wallet['id'], $userId, $amount, 'debit', 'outflow', 'Grower certificate access and renewal fee', $reference, 'certificate_access', $reference,
            json_encode(['application_id' => $applicationId, 'certificate_id' => $certificateId, 'member_type' => $memberType, 'access_type' => $accessType], JSON_UNESCAPED_SLASHES),
            'completed', $before, $after,
        ]);
        $pdo->prepare("INSERT INTO certificate_access_payments (user_id, application_id, certificate_id, certificate_ref, member_type, access_type, amount, status, reference, paid_at, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', ?, NOW(), ?)")->execute([
            $userId, $applicationId, $certificateId ?: null, (string) ($certificate['certificate_ref'] ?? ''), $memberType, $accessType, $amount, $reference, $validUntil,
        ]);
        revenue_record_once($pdo, [
            'revenue_ref' => 'REV-CERT-' . $reference,
            'source_module' => 'registry',
            'source_type' => 'certificate_access_fee',
            'source_id' => (int) $pdo->lastInsertId(),
            'user_id' => $userId,
            'gross_amount' => $amount,
            'revenue_amount' => $amount,
            'net_payable_amount' => 0,
            'rule_key' => 'grower_certificate_' . $memberType,
            'description' => grower_member_type_label($memberType) . ' certificate access/renewal fee',
            'metadata' => ['application_id' => $applicationId, 'certificate_id' => $certificateId, 'member_type' => $memberType],
        ]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['success' => true, 'reference' => $reference, 'amount' => $amount, 'valid_until' => $validUntil];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function certificate_generate_ref(string $appRef): string
{
    return 'CERT-' . preg_replace('/[^A-Z0-9-]/i', '', strtoupper($appRef)) . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function grower_certificate_payment_required(PDO $pdo): bool
{
    return function_exists('admin_setting') && admin_setting($pdo, 'grower_certificate_payment_required', '0') === '1';
}

function grower_certificate_amount(PDO $pdo): float
{
    $amount = function_exists('admin_setting') ? (float) admin_setting($pdo, 'grower_certificate_amount', '0') : 0.0;
    return max(0.0, $amount);
}

function grower_certificate_validity_months(PDO $pdo): int
{
    $months = function_exists('admin_setting') ? (int) admin_setting($pdo, 'grower_certificate_validity_months', '36') : 36;
    return max(1, min(120, $months));
}

function grower_certificate_expires_at(PDO $pdo, ?string $issuedAt = null): string
{
    $base = $issuedAt ? strtotime($issuedAt) : time();
    if ($base === false) {
        $base = time();
    }
    return date('Y-m-d H:i:s', strtotime('+' . grower_certificate_validity_months($pdo) . ' months', $base));
}

function grower_certificate_payment_reference(int $userId, int $applicationId): string
{
    return 'NAT-GROWER-CERT-' . $userId . '-' . $applicationId;
}

function grower_certificate_is_paid(PDO $pdo, int $userId, int $applicationId): bool
{
    if (!grower_certificate_payment_required($pdo)) {
        return true;
    }
    if (!app_table_exists($pdo, 'wallet_transactions')) {
        return false;
    }
    $referencePrefix = grower_certificate_payment_reference($userId, $applicationId);
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM wallet_transactions
        WHERE user_id = ?
          AND type = 'debit'
          AND status = 'completed'
          AND reference LIKE ?
    ");
    $stmt->execute([$userId, $referencePrefix . '%']);
    return (int) $stmt->fetchColumn() > 0;
}

function grower_certificate_readiness(int $userId, PDO $pdo): array
{
    app_ensure_core_schema($pdo);
    app_ensure_certificate_schema($pdo);

    $phoneVerifiedSql = app_column_exists($pdo, 'users', 'phone_verified') ? 'u.phone_verified' : '0';
    $accountStatusSql = app_column_exists($pdo, 'users', 'account_status') ? 'u.account_status' : "'active'";
    $stmt = $pdo->prepare("
        SELECT u.id user_id, u.name, u.email, u.phone, u.location,
               {$phoneVerifiedSql} phone_verified, {$accountStatusSql} account_status,
               a.id application_id, a.app_ref, a.confirmed, a.location application_location
        FROM users u
        JOIN applications a ON a.id = u.application_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $grower = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $checks = [];
    $add = static function (string $key, string $label, bool $passed, string $detail) use (&$checks): void {
        $checks[$key] = compact('key', 'label', 'passed', 'detail');
    };

    if (!$grower) {
        $add('account', 'Dashboard account linked', false, 'No grower account is linked to an application.');
        return ['ready' => false, 'issued' => false, 'application_id' => 0, 'checks' => $checks];
    }

    $confirmed = (int) $grower['confirmed'] === 1;
    $add('engagement', 'Email or engagement confirmed', $confirmed, $confirmed ? 'Application engagement is confirmed.' : 'The grower must use the activation or confirmation link.');

    $accountStatus = strtolower(trim((string) ($grower['account_status'] ?? 'active')));
    $accountActive = in_array($accountStatus, ['', 'active', 'approved', 'verified'], true);
    $add('account', 'Account active', $accountActive, $accountActive ? 'Dashboard account is active.' : 'Account status is ' . ($accountStatus ?: 'pending') . '.');

    $profileComplete = trim((string) $grower['name']) !== ''
        && filter_var((string) $grower['email'], FILTER_VALIDATE_EMAIL)
        && trim((string) $grower['phone']) !== ''
        && trim((string) ($grower['location'] ?: $grower['application_location'])) !== '';
    $add('profile', 'Core profile complete', $profileComplete, $profileComplete ? 'Name, email, phone, and location are present.' : 'Name, valid email, phone, and location are required.');

    $phoneRequired = function_exists('admin_setting') && admin_setting($pdo, 'sms_phone_validation_required', '0') === '1';
    $phoneVerified = (int) ($grower['phone_verified'] ?? 0) === 1;
    $add('phone', 'Phone verified', !$phoneRequired || $phoneVerified, $phoneRequired ? ($phoneVerified ? 'Phone verification completed.' : 'Phone verification is required by platform policy.') : 'Phone verification is optional under current policy.');

    $importConfirmed = true;
    $importDetail = 'Not an imported legacy record.';
    if (app_table_exists($pdo, 'user_import_records')) {
        $importStmt = $pdo->prepare('SELECT status FROM user_import_records WHERE user_id = ? OR application_id = ? ORDER BY id DESC LIMIT 1');
        $importStmt->execute([$userId, (int) $grower['application_id']]);
        $importStatus = strtolower((string) ($importStmt->fetchColumn() ?: ''));
        if ($importStatus !== '') {
            $importConfirmed = in_array($importStatus, ['engagement_confirmed', 'confirmed', 'completed'], true);
            $importDetail = $importConfirmed ? 'Imported record engagement is confirmed.' : 'Imported record status is ' . str_replace('_', ' ', $importStatus) . '.';
        }
    }
    $add('import_engagement', 'Imported record engaged', $importConfirmed, $importDetail);

    $identityReady = false;
    $documentsResolved = false;
    if (app_table_exists($pdo, 'document_requirements')) {
        $identityReady = true;
        foreach (['nin', 'bvn'] as $identityType) {
            $identityStmt = $pdo->prepare("SELECT verification_status, api_validation_status FROM document_requirements WHERE user_id = ? AND document_type = ? LIMIT 1");
            $identityStmt->execute([$userId, $identityType]);
            $identity = $identityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$identity || ($identity['verification_status'] ?? '') !== 'verified' || ($identity['api_validation_status'] ?? '') !== 'valid') {
                $identityReady = false;
            }
        }
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM document_requirements WHERE user_id = ? AND verification_status <> 'verified'");
        $pendingStmt->execute([$userId]);
        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM document_requirements WHERE user_id = ?');
        $totalStmt->execute([$userId]);
        $documentsResolved = (int) $totalStmt->fetchColumn() > 0 && (int) $pendingStmt->fetchColumn() === 0;
    }
    $add('identity', 'NIN and BVN API validated', $identityReady, $identityReady ? 'Both identity records are verified and API-valid.' : 'Verified, API-valid NIN and BVN records are required.');
    $add('documents', 'Document reviews complete', $documentsResolved, $documentsResolved ? 'All submitted documents are verified.' : 'At least one verified document is required and no review may remain pending.');

    $farmReady = false;
    $farmDetail = 'Farm profile and verification are required.';
    if (app_table_exists($pdo, 'grower_farms')) {
        $farmSql = "SELECT gf.id, gf.farm_size, gf.state_id, gf.lga_id, gf.street_address, 'pending' verification_status FROM grower_farms gf WHERE gf.user_id = ? ORDER BY gf.is_primary DESC, gf.id ASC LIMIT 1";
        if (app_table_exists($pdo, 'farm_verifications')) {
            $farmSql = "SELECT gf.id, gf.farm_size, gf.state_id, gf.lga_id, gf.street_address, COALESCE(fv.status, 'pending') verification_status FROM grower_farms gf LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id WHERE gf.user_id = ? ORDER BY gf.is_primary DESC, fv.id DESC, gf.id ASC LIMIT 1";
        }
        $farmStmt = $pdo->prepare($farmSql);
        $farmStmt->execute([$userId]);
        $farm = $farmStmt->fetch(PDO::FETCH_ASSOC);
        if ($farm) {
            $locationComplete = (int) ($farm['state_id'] ?? 0) > 0 && ((int) ($farm['lga_id'] ?? 0) > 0 || trim((string) ($farm['street_address'] ?? '')) !== '');
            $farmReady = (float) ($farm['farm_size'] ?? 0) > 0 && $locationComplete && ($farm['verification_status'] ?? '') === 'verified';
            $farmDetail = $farmReady ? 'Farm profile, location, size, and field verification are complete.' : 'Complete farm size/location and obtain verified farm status.';
        }
    }
    $add('farm', 'Farm onboarding verified', $farmReady, $farmDetail);

    $accessRequired = grower_certificate_access_required($pdo, $grower);
    $add('access_fee', 'Certificate access fee policy', true, $accessRequired ? 'Access or renewal fee is collected before viewing or downloading the issued certificate.' : 'No certificate access fee is currently required.');

    $issuedStmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE application_id = ? AND status = 'issued'");
    $issuedStmt->execute([(int) $grower['application_id']]);
    $issued = (int) $issuedStmt->fetchColumn() > 0;
    $ready = !array_filter($checks, static fn(array $check): bool => !$check['passed']);
    return ['ready' => $ready, 'issued' => $issued, 'application_id' => (int) $grower['application_id'], 'checks' => $checks];
}

function canIssueCertificate(int $userId, PDO $pdo): bool
{
    return (bool) grower_certificate_readiness($userId, $pdo)['ready'];
}

function generateCertificate(int $applicationId, int $userId, PDO $pdo): array
{
    app_ensure_core_schema($pdo);
    app_ensure_certificate_schema($pdo);

    $existing = $pdo->prepare("
        SELECT *
        FROM certificates
        WHERE application_id = ? AND status = 'issued'
        ORDER BY issued_at DESC
        LIMIT 1
    ");
    $existing->execute([$applicationId]);
    $certificate = $existing->fetch();
    if ($certificate) {
        if (empty($certificate['expires_at'] ?? null)) {
            $expiresAt = grower_certificate_expires_at($pdo, (string) ($certificate['issued_at'] ?? 'now'));
            $pdo->prepare("UPDATE certificates SET expires_at = ? WHERE id = ?")->execute([$expiresAt, (int) $certificate['id']]);
            $certificate['expires_at'] = $expiresAt;
        }
        if (empty($certificate['certificate_ref'])) {
            $certRef = $certificate['qr_code_hash'] ?: certificate_generate_ref('NAT-' . $applicationId);
            $verifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($certRef);
            $pdo->prepare("
                UPDATE certificates
                SET certificate_ref = ?, qr_code_hash = COALESCE(qr_code_hash, ?), verification_url = COALESCE(verification_url, ?)
                WHERE id = ?
            ")->execute([$certRef, $certRef, $verifyUrl, $certificate['id']]);
            $certificate['certificate_ref'] = $certRef;
            $certificate['qr_code_hash'] = $certificate['qr_code_hash'] ?: $certRef;
            $certificate['verification_url'] = $certificate['verification_url'] ?: $verifyUrl;
        }
        return $certificate;
    }

    $stmt = $pdo->prepare("
        SELECT a.id, a.app_ref, a.name, a.location, a.farm_size, a.confirmed, u.email
        FROM applications a
        LEFT JOIN users u ON u.id = ?
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $applicationId]);
    $app = $stmt->fetch();

    if (!$app || (int) $app['confirmed'] !== 1) {
        throw new RuntimeException('Certificate can only be issued for confirmed applications.');
    }

    $certRef = certificate_generate_ref((string) $app['app_ref']);
    $fileName = strtolower($certRef) . '.html';
    $directory = dirname(__DIR__) . '/certificates';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $relativePath = 'certificates/' . $fileName;
    $pdfRelativePath = 'certificates/' . strtolower($certRef) . '.pdf';
    $verifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($certRef);
    $issuedAt = date('Y-m-d H:i:s');
    $expiresAt = grower_certificate_expires_at($pdo, $issuedAt);
    $html = certificate_render_html($app, $certRef, $issuedAt, $verifyUrl);
    file_put_contents($directory . '/' . $fileName, $html, LOCK_EX);

    $pdf = certificate_pdf_document([
        'display_ref' => $certRef,
        'certificate_ref' => $certRef,
        'issued_at' => $issuedAt,
        'expires_at' => $expiresAt,
        'verification_url' => $verifyUrl,
        'app_ref' => $app['app_ref'],
        'name' => $app['name'],
        'location' => $app['location'],
        'farm_size' => $app['farm_size'],
    ]);
    file_put_contents(dirname(__DIR__) . '/' . $pdfRelativePath, $pdf, LOCK_EX);

    $insert = $pdo->prepare("
        INSERT INTO certificates (certificate_ref, application_id, user_id, certificate_path, certificate_pdf_path, status, issued_at, expires_at, qr_code_hash, verification_url)
        VALUES (?, ?, ?, ?, ?, 'issued', ?, ?, ?, ?)
    ");
    $insert->execute([$certRef, $applicationId, $userId, $relativePath, $pdfRelativePath, $issuedAt, $expiresAt, $certRef, $verifyUrl]);

    $fetch = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
    $fetch->execute([(int) $pdo->lastInsertId()]);
    $certificate = $fetch->fetch();

    natcodev_notify_user($pdo, $userId, 'certificate_issued', 'NATCODEV Certificate Issued', [
        'certificate_ref' => $certRef,
        'verification_url' => $verifyUrl,
        'certificate_url' => app_base_url() . '/' . $relativePath,
        'name' => $app['name'],
    ], "Your NATCODEV certificate {$certRef} has been issued. Verify: {$verifyUrl}");

    return $certificate;
}



require_once __DIR__ . '/certificate-generator.php';
