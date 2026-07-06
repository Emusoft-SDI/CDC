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
        $pdo->commit();
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
        $pdo->commit();
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

function certificate_render_html(array $app, string $certRef, string $issuedAt, string $verifyUrl): string
{
    $issuedDate = date('F j, Y', strtotime($issuedAt));
    $qr = certificate_qr_svg($verifyUrl, 17);
    $logoSrc = certificate_asset_src(['assets/logo/natcodev.jpeg', 'assets/logo/natcodev-logo.png'], 'assets/logo/natcodev-logo.svg');
    $fmardSrc = certificate_asset_src(['assets/seals/fmard-logo.png', 'assets/seals/fmaf.png'], 'assets/seals/fmaf.svg');
    $naicSrc = certificate_asset_src(['assets/seals/naic.png'], 'assets/seals/naic.svg');
    $nirsalSrc = certificate_asset_src(['assets/seals/nirsal.jpeg', 'assets/seals/nisral.png'], 'assets/seals/nisral.svg');
    $boaSrc = certificate_asset_src(['assets/seals/boa.png'], 'assets/seals/boa.svg');
    $lcfeSrc = certificate_asset_src(['assets/seals/lc_fe.jpg'], 'assets/seals/fmaf.svg');

    return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NATCODEV Certificate ' . e($certRef) . '</title>
  <style>
    :root { --green:#2d5016; --leaf:#14733a; --gold:#c9a227; --ink:#172211; --muted:#66715f; }
    * { box-sizing:border-box; }
    body { margin:0; background:#eef4e9; color:var(--ink); font-family:Arial, Helvetica, sans-serif; padding:26px; }
    main { position:relative; max-width:1120px; min-height:760px; margin:0 auto; overflow:hidden; background:#fffdf7; border:10px solid var(--green); box-shadow:0 24px 70px rgba(20,45,10,.18); }
    main:before { content:""; position:absolute; inset:24px; border:2px solid var(--gold); pointer-events:none; }
    main:after { content:"NATCODEV"; position:absolute; inset:auto 0 210px 0; text-align:center; font:800 118px Arial, sans-serif; letter-spacing:10px; color:rgba(45,80,22,.045); pointer-events:none; }
    .bar { height:18px; background:linear-gradient(90deg,var(--green),var(--leaf),var(--gold),var(--leaf),var(--green)); }
    .content { position:relative; z-index:1; padding:34px 58px 42px; text-align:center; }
    .top { display:flex; align-items:center; justify-content:space-between; gap:22px; margin-bottom:18px; }
    .logo { width:250px; max-width:36%; }
    .refbox { text-align:right; font-size:12px; color:var(--muted); line-height:1.6; }
    .eyebrow { color:var(--gold); letter-spacing:4px; font-weight:800; font-size:13px; text-transform:uppercase; margin-top:8px; }
    h1 { color:var(--green); font-family:Georgia, "Times New Roman", serif; font-size:50px; line-height:1.05; margin:10px 0 18px; }
    .intro { color:var(--muted); margin:0; font-size:17px; }
    .name { color:#111b0d; font-family:Georgia, "Times New Roman", serif; font-size:48px; font-weight:700; margin:22px auto 12px; padding-bottom:10px; max-width:760px; border-bottom:2px solid var(--gold); }
    .statement { font-size:16px; line-height:1.55; max-width:720px; margin:0 auto 24px; overflow-wrap:break-word; }
    .details { display:grid; grid-template-columns:1fr 1fr; gap:14px; max-width:760px; margin:28px auto; }
    .detail { border:1px solid #e2dcc8; border-top:4px solid var(--gold); background:#fffaf0; padding:15px; min-height:74px; }
    .label { display:block; color:var(--muted); font-size:11px; letter-spacing:1.4px; text-transform:uppercase; margin-bottom:7px; }
    .value { color:var(--green); font-weight:800; }
    .seals { display:flex; align-items:center; justify-content:center; gap:12px; margin:18px 0 16px; flex-wrap:wrap; }
    .seal { width:112px; height:72px; object-fit:contain; background:#fff; border:1px solid #e4e0d2; border-radius:8px; padding:7px; filter:drop-shadow(0 8px 12px rgba(45,80,22,.10)); }
    .verification { display:grid; grid-template-columns:170px 1fr 170px; align-items:end; gap:30px; margin-top:22px; text-align:left; }
    .qr svg, .qr img { width:128px; height:128px; object-fit:contain; background:#fff; border:6px solid #fff; box-shadow:0 0 0 1px #d9ddcf; }
    .verifytext { font-size:11px; color:var(--green); font-weight:800; margin-top:8px; text-align:center; }
    .signature { text-align:center; }
    .signature img { width:1085px; max-width:100%; display:block; margin:-26px auto -12px; mix-blend-mode:multiply; }
    .sigline { border-top:1px solid #1f2d18; margin-top:2px; padding-top:8px; font-size:12px; font-weight:800; color:var(--green); letter-spacing:1.2px; }
    .official-seal { justify-self:end; width:150px; height:150px; border-radius:50%; background:repeating-conic-gradient(from 0deg,#b70808 0 7deg,#e02b21 7deg 14deg); box-shadow:0 0 0 6px #9f0808, inset 0 0 0 16px #d71919, inset 0 0 0 24px #fff, inset 0 0 0 34px #c40909, 0 14px 28px rgba(137,20,20,.30); }
    .fine { font-size:10px; color:var(--muted); letter-spacing:.8px; }
    @media print { body { background:#fff; padding:0; } main { width:100%; min-height:100vh; box-shadow:none; } }
    @media (max-width:760px) { body { padding:10px; } .content { padding:22px; } .top,.verification { grid-template-columns:1fr; display:grid; text-align:center; } .refbox { text-align:center; } .logo { max-width:260px; width:80%; margin:auto; } .details { grid-template-columns:1fr; } h1 { font-size:32px; } .name { font-size:34px; } .seals { flex-wrap:wrap; } }
  </style>
</head>
<body>
  <main>
    <div class="bar"></div>
    <div class="content">
      <div class="top">
        <img class="logo" src="' . e($logoSrc) . '" alt="NATCODEV">
        <div class="refbox">
          Certificate Reference<br><strong>' . e($certRef) . '</strong><br>
          Issued ' . e($issuedDate) . '
        </div>
      </div>
      <div class="eyebrow">Official Grower Credential</div>
      <h1>Certificate of Participation</h1>
      <p class="intro">This certifies that</p>
      <div class="name">' . e($app['name']) . '</div>
      <p class="statement">has been duly confirmed as a participant in the NATCODEV Coconut Outgrowers Program and is recognized for verified engagement in the grower development pathway.</p>
      <div class="details">
        <div class="detail"><span class="label">Application Ref</span><span class="value">' . e($app['app_ref']) . '</span></div>
        <div class="detail"><span class="label">Farm Location</span><span class="value">' . e($app['location']) . '</span></div>
      </div>
      <div class="seals" aria-label="Program partner seals">
        <img class="seal" src="' . e($fmardSrc) . '" alt="FMARD seal">
        <img class="seal" src="' . e($naicSrc) . '" alt="NAIC seal">
        <img class="seal" src="' . e($nirsalSrc) . '" alt="NIRSAL seal">
        <img class="seal" src="' . e($boaSrc) . '" alt="BOA seal">
        <img class="seal" src="' . e($lcfeSrc) . '" alt="LCFE seal">
      </div>
      <div class="verification">
        <div class="qr">' . $qr . '<div class="verifytext">VERIFY ONLINE</div></div>
        <div class="signature">
          <img src="../assets/signatures/chief-of-party.jpg" alt="NATCODEV Chief of Party signature">
          <div class="sigline">NATCODEV CHIEF OF PARTY</div>
          <div class="fine">Digitally issued and verifiable online</div>
        </div>
        <div class="official-seal" aria-label="Official authenticated certificate seal"></div>
      </div>
    </div>
    <div class="bar"></div>
  </main>
</body>
</html>';
}

function certificate_asset_src(array $preferredRelativePaths, string $fallbackRelativePath): string
{
    $path = $fallbackRelativePath;

    foreach ($preferredRelativePaths as $preferredRelativePath) {
        $preferredAbsolute = dirname(__DIR__) . '/' . ltrim($preferredRelativePath, '/');
        if (is_file($preferredAbsolute) && filesize($preferredAbsolute) > 0) {
            $path = $preferredRelativePath;
            break;
        }
    }

    return '../' . ltrim($path, '/');
}

function certificate_qr_svg(string $value, int $cells = 17): string
{
    $qrAsset = dirname(__DIR__) . '/assets/certificates/verification-qr.jpg';
    if (is_file($qrAsset) && filesize($qrAsset) > 0) {
        return '<img src="../assets/certificates/verification-qr.jpg" alt="Certificate verification QR code">';
    }

    $hash = hash('sha256', $value);
    $cellSize = 5;
    $size = $cells * $cellSize;
    $rects = '';

    for ($y = 0; $y < $cells; $y++) {
        for ($x = 0; $x < $cells; $x++) {
            $finder = ($x < 5 && $y < 5) || ($x >= $cells - 5 && $y < 5) || ($x < 5 && $y >= $cells - 5);
            $innerFinder = ($x > 0 && $x < 4 && $y > 0 && $y < 4)
                || ($x > $cells - 5 && $x < $cells - 1 && $y > 0 && $y < 4)
                || ($x > 0 && $x < 4 && $y > $cells - 5 && $y < $cells - 1);
            $index = ($x + $y * $cells) % strlen($hash);
            $active = $finder ? !$innerFinder : (hexdec($hash[$index]) + $x + ($y * 3)) % 3 === 0;

            if ($active) {
                $rects .= '<rect x="' . ($x * $cellSize) . '" y="' . ($y * $cellSize) . '" width="' . $cellSize . '" height="' . $cellSize . '"/>';
            }
        }
    }

    return '<svg viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="Certificate verification QR code" xmlns="http://www.w3.org/2000/svg"><rect width="' . $size . '" height="' . $size . '" fill="#fff"/><g fill="#172211">' . $rects . '</g></svg>';
}

function findCertificate(string $ref, PDO $pdo): ?array
{
    app_ensure_certificate_schema($pdo);
    $stmt = $pdo->prepare("
        SELECT COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) certificate_ref,
               COALESCE(c.status, 'issued') status,
               c.user_id, c.issued_at, c.expires_at, c.revoked_at, c.revoked_reason, c.qr_code_hash, c.verification_url,
               a.app_ref, a.name, a.location, a.farm_size
        FROM certificates c
        JOIN applications a ON a.id = c.application_id
        WHERE c.certificate_ref = ? OR c.qr_code_hash = ? OR a.app_ref = ?
        ORDER BY c.issued_at DESC
        LIMIT 1
    ");
    $stmt->execute([$ref, $ref, $ref]);
    $certificate = $stmt->fetch();
    return $certificate ?: null;
}

function certificate_pdf_document(array $certificate): string
{
    $issuedAt = !empty($certificate['issued_at'])
        ? date('F j, Y', strtotime((string) $certificate['issued_at']))
        : date('F j, Y');
    $expiresAt = !empty($certificate['expires_at'])
        ? date('F j, Y', strtotime((string) $certificate['expires_at']))
        : '';
    $verifyUrl = (string) ($certificate['verification_url'] ?: app_base_url() . '/verify-certificate.php?ref=' . urlencode((string) $certificate['display_ref']));
    $jpeg = certificate_pdf_render_jpeg($certificate, $issuedAt, $verifyUrl, $expiresAt);
    return certificate_pdf_build($jpeg, 1684, 1190);
}

function certificate_pdf_render_jpeg(array $certificate, string $issuedAt, string $verifyUrl, string $expiresAt = ''): string
{
    $width = 1684;
    $height = 1190;
    $image = imagecreatetruecolor($width, $height);
    imageantialias($image, true);

    $cream = certificate_color($image, '#fffdf7');
    $paper = certificate_color($image, '#f7fbf3');
    $green = certificate_color($image, '#2d5016');
    $leaf = certificate_color($image, '#14733a');
    $gold = certificate_color($image, '#c9a227');
    $ink = certificate_color($image, '#172211');
    $muted = certificate_color($image, '#66715f');
    $line = certificate_color($image, '#e2dcc8');
    $white = certificate_color($image, '#ffffff');

    imagefilledrectangle($image, 0, 0, $width, $height, $paper);
    imagefilledrectangle($image, 88, 82, $width - 88, $height - 82, $cream);
    certificate_thick_rectangle($image, 92, 86, $width - 92, $height - 86, $green, 14);
    certificate_thick_rectangle($image, 134, 128, $width - 134, $height - 128, $gold, 3);
    imagefilledrectangle($image, 92, 86, $width - 92, 124, $leaf);
    imagefilledrectangle($image, 92, $height - 124, $width - 92, $height - 86, $leaf);

    certificate_draw_logo($image, 165, 145, 250, 170, $green, $gold, $ink);
    $refRight = $width - 270;
    certificate_text($image, 'Certificate Reference', $refRight, 170, 20, $muted, 'regular', 'right');
    certificate_text($image, (string) $certificate['display_ref'], $refRight, 202, 24, $green, 'bold', 'right');
    certificate_text($image, 'Issued ' . $issuedAt, $refRight, 236, 20, $muted, 'regular', 'right');
    if ($expiresAt !== '') {
        certificate_text($image, 'Valid until ' . $expiresAt, $refRight, 268, 20, $muted, 'regular', 'right');
    }

    certificate_text($image, 'OFFICIAL GROWER CREDENTIAL', $width / 2, 310, 22, $gold, 'bold', 'center');
    certificate_text($image, 'Certificate of Participation', $width / 2, 385, 66, $green, 'serif_bold', 'center');
    certificate_text($image, 'This certifies that', $width / 2, 455, 30, $muted, 'regular', 'center');
    certificate_text($image, (string) $certificate['name'], $width / 2, 540, 68, $ink, 'serif_bold', 'center');
    imageline($image, 430, 570, 1254, 570, $gold);
    imagesetthickness($image, 2);
    imageline($image, 430, 574, 1254, 574, $gold);
    imagesetthickness($image, 1);

    certificate_wrapped_text($image, 'has been duly confirmed as a participant in the NATCODEV Coconut Outgrowers Program and is recognized for verified engagement in the grower development pathway.', $width / 2, 626, 1130, 28, 36, $ink, 'regular', 'center');

    certificate_detail_box($image, 438, 732, 360, 92, 'APPLICATION REF', (string) $certificate['app_ref'], $line, $cream, $gold, $green, $muted);
    certificate_detail_box($image, 886, 732, 360, 92, 'FARM LOCATION', (string) $certificate['location'], $line, $cream, $gold, $green, $muted);

    certificate_draw_partner_logo($image, ['assets/seals/fmard-logo.png', 'assets/seals/fmaf.png'], 'FMARD', 406, 842, 166, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/naic.png'], 'NAIC', 586, 842, 134, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/nirsal.jpeg', 'assets/seals/nisral.png'], 'NIRSAL', 734, 842, 166, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/boa.png'], 'BOA', 914, 842, 134, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/lc_fe.jpg'], 'LCFE', 1062, 842, 166, 94, $line, $white, $green);

    certificate_draw_qr($image, $verifyUrl, 188, 860, 170, $ink, $white, $line);
    certificate_text($image, 'VERIFY ONLINE', 263, 1048, 16, $green, 'bold', 'center');

    certificate_draw_signature($image, 582, 798, $green, $gold, $ink);
    certificate_text($image, 'NATCODEV CHIEF OF PARTY', $width / 2, 1050, 18, $green, 'bold', 'center');
    certificate_text($image, 'Digitally issued and verifiable online', $width / 2, 1078, 15, $muted, 'regular', 'center');

    certificate_draw_red_seal($image, 1296, 866, 168);

    ob_start();
    imagejpeg($image, null, 94);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    return $jpeg;
}

function certificate_color(GdImage $image, string $hex): int
{
    $hex = ltrim($hex, '#');
    return imagecolorallocate($image, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function certificate_font(string $style): string
{
    $fonts = [
        'regular' => 'C:/Windows/Fonts/arial.ttf',
        'bold' => 'C:/Windows/Fonts/arialbd.ttf',
        'serif_bold' => 'C:/Windows/Fonts/georgiab.ttf',
        'script' => 'C:/Windows/Fonts/segoesc.ttf',
    ];

    return is_file($fonts[$style] ?? '') ? $fonts[$style] : ($fonts['regular']);
}

function certificate_text(GdImage $image, string $text, float $x, float $y, int $size, int $color, string $fontStyle = 'regular', string $align = 'left'): void
{
    $text = certificate_pdf_safe_text($text);
    $font = certificate_font($fontStyle);
    $box = imagettfbbox($size, 0, $font, $text);
    $textWidth = $box ? abs($box[2] - $box[0]) : strlen($text) * $size;

    if ($align === 'center') {
        $x -= $textWidth / 2;
    } elseif ($align === 'right') {
        $x -= $textWidth;
    }

    imagettftext($image, $size, 0, (int) round($x), (int) round($y), $color, $font, $text);
}

function certificate_embossed_text(GdImage $image, string $text, float $x, float $y, int $size, int $mainColor, int $shadowColor, int $highlightColor, string $fontStyle = 'bold', string $align = 'center'): void
{
    certificate_text($image, $text, $x + 2, $y + 2, $size, $shadowColor, $fontStyle, $align);
    certificate_text($image, $text, $x - 1, $y - 1, $size, $highlightColor, $fontStyle, $align);
    certificate_text($image, $text, $x, $y, $size, $mainColor, $fontStyle, $align);
}

function certificate_wrapped_text(GdImage $image, string $text, float $x, float $y, int $maxWidth, int $size, int $lineHeight, int $color, string $fontStyle = 'regular', string $align = 'left'): void
{
    $text = certificate_pdf_safe_text($text);
    $font = certificate_font($fontStyle);
    $words = preg_split('/\s+/', $text) ?: [];
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $candidate = trim($current . ' ' . $word);
        $box = imagettfbbox($size, 0, $font, $candidate);
        $candidateWidth = $box ? abs($box[2] - $box[0]) : strlen($candidate) * $size;
        if ($current !== '' && $candidateWidth > $maxWidth) {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    foreach ($lines as $index => $line) {
        certificate_text($image, $line, $x, $y + ($index * $lineHeight), $size, $color, $fontStyle, $align);
    }
}

function certificate_detail_box(GdImage $image, int $x, int $y, int $w, int $h, string $label, string $value, int $line, int $fill, int $gold, int $green, int $muted): void
{
    imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $fill);
    imagerectangle($image, $x, $y, $x + $w, $y + $h, $line);
    imagefilledrectangle($image, $x, $y, $x + $w, $y + 7, $gold);
    certificate_text($image, $label, $x + ($w / 2), $y + 34, 15, $muted, 'bold', 'center');
    certificate_text($image, $value, $x + ($w / 2), $y + 70, 22, $green, 'bold', 'center');
}

function certificate_thick_rectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness): void
{
    imagesetthickness($image, $thickness);
    imagerectangle($image, $x1, $y1, $x2, $y2, $color);
    imagesetthickness($image, 1);
}

function certificate_draw_logo(GdImage $image, int $x, int $y, int $w, int $h, int $green, int $gold, int $ink): void
{
    if (certificate_copy_image_contain($image, certificate_first_asset_path(['assets/logo/natcodev.jpeg', 'assets/logo/natcodev-logo.png']), $x, $y, $w, $h)) {
        return;
    }

    imagefilledellipse($image, $x + 58, $y + 58, 92, 92, $green);
    imagefilledellipse($image, $x + 74, $y + 45, 22, 60, $gold);
    imagefilledellipse($image, $x + 52, $y + 70, 44, 18, certificate_color($image, '#ffffff'));
    certificate_text($image, 'NATCODEV', $x + 130, $y + 55, 40, $green, 'bold');
    certificate_text($image, 'COCONUT OUTGROWERS PROGRAM', $x + 134, $y + 88, 15, $ink, 'regular');
}

function certificate_draw_partner_logo(GdImage $image, array $relativePaths, string $label, int $x, int $y, int $w, int $h, int $line, int $white, int $green): void
{
    imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $white);
    imagerectangle($image, $x, $y, $x + $w, $y + $h, $line);

    if (certificate_copy_image_contain($image, certificate_first_asset_path($relativePaths), $x + 10, $y + 8, $w - 20, $h - 16)) {
        return;
    }

    certificate_text($image, $label, $x + ($w / 2), $y + 55, 18, $green, 'bold', 'center');
}

function certificate_first_asset_path(array $relativePaths): string
{
    foreach ($relativePaths as $relativePath) {
        $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }
    }

    return '';
}

function certificate_copy_image_contain(GdImage $target, string $path, int $x, int $y, int $w, int $h): bool
{
    $source = certificate_load_image($path);
    if (!$source instanceof GdImage) {
        return false;
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($source);
        return false;
    }

    $scale = min($w / $sourceWidth, $h / $sourceHeight);
    $drawWidth = (int) round($sourceWidth * $scale);
    $drawHeight = (int) round($sourceHeight * $scale);
    $drawX = $x + (int) round(($w - $drawWidth) / 2);
    $drawY = $y + (int) round(($h - $drawHeight) / 2);

    imagealphablending($target, true);
    imagecopyresampled($target, $source, $drawX, $drawY, 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);
    imagedestroy($source);
    return true;
}

function certificate_load_image(string $path): ?GdImage
{
    if ($path === '' || !is_file($path) || filesize($path) === 0) {
        return null;
    }

    $info = @getimagesize($path);
    $type = is_array($info) ? ($info[2] ?? null) : null;
    if ($type === IMAGETYPE_PNG) {
        $source = @imagecreatefrompng($path);
    } elseif ($type === IMAGETYPE_JPEG) {
        $source = @imagecreatefromjpeg($path);
    } else {
        $source = false;
    }

    if (!$source instanceof GdImage) {
        return null;
    }

    return $source;
}

function certificate_draw_signature(GdImage $image, int $x, int $y, int $green, int $gold, int $ink, int $w = 520, int $h = 308): void
{
    if (certificate_copy_signature($image, certificate_first_asset_path(['assets/signatures/chief-of-party.jpg']), $x, $y, $w, $h)) {
        return;
    }

    certificate_text($image, 'Chief of Party', $x + (int) round($w / 2), $y + (int) round($h * 0.24), 34, $green, 'script', 'center');
}

function certificate_copy_signature(GdImage $target, string $path, int $x, int $y, int $w, int $h): bool
{
    $source = certificate_load_image($path);
    if (!$source instanceof GdImage) {
        return false;
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($source);
        return false;
    }

    $scale = min($w / $sourceWidth, $h / $sourceHeight);
    $drawWidth = (int) round($sourceWidth * $scale);
    $drawHeight = (int) round($sourceHeight * $scale);
    $drawX = $x + (int) round(($w - $drawWidth) / 2);
    $drawY = $y + (int) round(($h - $drawHeight) / 2);
    $tmp = imagecreatetruecolor($drawWidth, $drawHeight);
    imagecopyresampled($tmp, $source, 0, 0, 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);

    for ($py = 0; $py < $drawHeight; $py++) {
        for ($px = 0; $px < $drawWidth; $px++) {
            $rgb = imagecolorat($tmp, $px, $py);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r > 238 && $g > 238 && $b > 238) {
                continue;
            }
            imagesetpixel($target, $drawX + $px, $drawY + $py, $rgb);
        }
    }

    imagedestroy($tmp);
    imagedestroy($source);
    return true;
}

function certificate_draw_qr(GdImage $image, string $value, int $x, int $y, int $size, int $ink, int $white, int $line): void
{
    $qrAsset = certificate_first_asset_path(['assets/certificates/verification-qr.jpg']);
    if ($qrAsset !== '' && certificate_copy_image_contain($image, $qrAsset, $x, $y, $size, $size)) {
        imagerectangle($image, $x, $y, $x + $size, $y + $size, $line);
        return;
    }

    imagefilledrectangle($image, $x, $y, $x + $size, $y + $size, $white);
    imagerectangle($image, $x, $y, $x + $size, $y + $size, $line);
    $cells = 17;
    $cell = (int) floor($size / $cells);
    $hash = hash('sha256', $value);

    for ($row = 0; $row < $cells; $row++) {
        for ($col = 0; $col < $cells; $col++) {
            $finder = ($col < 5 && $row < 5) || ($col >= $cells - 5 && $row < 5) || ($col < 5 && $row >= $cells - 5);
            $innerFinder = ($col > 0 && $col < 4 && $row > 0 && $row < 4)
                || ($col > $cells - 5 && $col < $cells - 1 && $row > 0 && $row < 4)
                || ($col > 0 && $col < 4 && $row > $cells - 5 && $row < $cells - 1);
            $index = ($col + $row * $cells) % strlen($hash);
            $active = $finder ? !$innerFinder : (hexdec($hash[$index]) + $col + ($row * 3)) % 3 === 0;

            if ($active) {
                $rx = $x + 4 + ($col * $cell);
                $ry = $y + 4 + ($row * $cell);
                imagefilledrectangle($image, $rx, $ry, $rx + $cell - 1, $ry + $cell - 1, $ink);
            }
        }
    }
}

function certificate_draw_red_seal(GdImage $image, int $x, int $y, int $size): void
{
    $red = certificate_color($image, '#c91f1f');
    $darkRed = certificate_color($image, '#8f1010');
    $white = certificate_color($image, '#ffffff');
    $gold = certificate_color($image, '#f2c75c');
    $cx = $x + (int) round($size / 2);
    $cy = $y + (int) round($size / 2);

    $points = [];
    for ($i = 0; $i < 64; $i++) {
        $radius = $i % 2 === 0 ? $size / 2 : $size * 0.42;
        $angle = deg2rad(($i * 360 / 64) - 90);
        $points[] = (int) round($cx + cos($angle) * $radius);
        $points[] = (int) round($cy + sin($angle) * $radius);
    }
    imagefilledpolygon($image, $points, $darkRed);

    imagefilledellipse($image, $cx, $cy, $size, $size, $darkRed);
    imagefilledellipse($image, $cx, $cy, $size - 12, $size - 12, $red);
    imagefilledellipse($image, $cx, $cy, $size - 44, $size - 44, $white);
    imagefilledellipse($image, $cx, $cy, $size - 66, $size - 66, $darkRed);
    imagefilledellipse($image, $cx, $cy, $size - 100, $size - 100, $red);
    imagesetthickness($image, 5);
    imageellipse($image, $cx, $cy, $size - 24, $size - 24, $gold);
    imageellipse($image, $cx, $cy, $size - 82, $size - 82, $gold);
    imagesetthickness($image, 1);

    for ($i = 0; $i < 36; $i++) {
        $angle = deg2rad($i * 10);
        $sx = (int) round($cx + cos($angle) * (($size / 2) - 26));
        $sy = (int) round($cy + sin($angle) * (($size / 2) - 26));
        imagefilledellipse($image, $sx, $sy, 7, 7, $gold);
    }
}

function certificate_pdf_safe_text(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    $text = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

function certificate_pdf_build(string $jpeg, int $imageWidth, int $imageHeight): string
{
    $content = "q\n842 0 0 595 0 0 cm\n/Im1 Do\nQ\n";
    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /XObject << /Im1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
        "4 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imageWidth} /Height {$imageHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream\nendobj\n",
        "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";

    return $pdf;
}

