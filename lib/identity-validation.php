<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/monnify.php';

/**
 * Ensures the identity verification gateways schema and document tables exist.
 */
function identity_ensure_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    if (function_exists('app_ensure_farmer_engagement_schema')) {
        app_ensure_farmer_engagement_schema($pdo);
    }

    // 1. Ensure KYC columns in document_requirements
    foreach ([
        'api_validation_status' => "VARCHAR(30) NULL",
        'api_validation_provider' => "VARCHAR(40) NULL",
        'api_validation_reference' => "VARCHAR(120) NULL",
        'api_validation_response' => "LONGTEXT NULL",
        'api_validation_timestamp' => "DATETIME NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'document_requirements', $column, $definition);
    }

    // 2. Identity Verification Gateways Table (Multi-Provider: Monnify, Dojah, NetApps, VerifyMe, Custom)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS identity_gateways (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gateway_key VARCHAR(60) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            provider_driver ENUM('monnify', 'dojah', 'netapps', 'verifyme', 'custom') NOT NULL DEFAULT 'custom',
            base_url VARCHAR(255) NOT NULL,
            api_key VARCHAR(255) NULL,
            api_secret VARCHAR(255) NULL,
            app_id VARCHAR(120) NULL,
            contract_code VARCHAR(120) NULL,
            supported_types VARCHAR(120) NOT NULL DEFAULT 'bvn,nin',
            environment ENUM('live', 'simulation', 'sandbox') NOT NULL DEFAULT 'live',
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            priority INT NOT NULL DEFAULT 1,
            custom_headers JSON NULL,
            last_ping_status VARCHAR(100) NULL,
            last_ping_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_id_gw_status (status, is_primary, priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. Identity Verification Audit Logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS identity_verification_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            document_type ENUM('nin', 'bvn', 'cac', 'custom') NOT NULL,
            document_number VARCHAR(100) NOT NULL,
            gateway_id INT UNSIGNED NULL,
            gateway_key VARCHAR(60) NULL,
            provider_name VARCHAR(60) NULL,
            status ENUM('valid', 'invalid', 'error', 'simulated') NOT NULL,
            match_status VARCHAR(40) NULL,
            confidence_score DECIMAL(5, 2) NULL,
            provider_reference VARCHAR(120) NULL,
            raw_response LONGTEXT NULL,
            error_message VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_id_logs_user (user_id),
            INDEX idx_id_logs_doc (document_type, document_number),
            INDEX idx_id_logs_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 4. Seed Standard Identity Gateways if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM identity_gateways");
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO identity_gateways (gateway_key, name, provider_driver, base_url, api_key, api_secret, contract_code, supported_types, environment, status, is_primary, priority, created_at)
            VALUES 
            ('monnify', 'Monnify Identity VAS', 'monnify', 'https://api.monnify.com', 'MK_TEST_SAMPLE', 'MS_TEST_SAMPLE', '1234567890', 'bvn,nin', 'simulation', 'active', 1, 1, NOW()),
            ('dojah', 'Dojah KYC & Biometrics', 'dojah', 'https://api.dojah.io', 'prod_sk_sample', 'prod_pk_sample', 'app_id_sample', 'bvn,nin', 'simulation', 'active', 0, 2, NOW()),
            ('netapps', 'NetApps KYC Validation', 'netapps', 'https://api.netapps.ng', 'netapps_key_sample', NULL, NULL, 'bvn,nin', 'simulation', 'active', 0, 3, NOW()),
            ('verifyme', 'VerifyMe / QoreID Identity', 'verifyme', 'https://api.qoreid.com', 'qoreid_sk_sample', NULL, NULL, 'bvn,nin', 'simulation', 'active', 0, 4, NOW())
        ");
    }

    $ensured = true;
}

function identity_name_parts(string $name): array
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return [
        'firstName' => $parts[0] ?? '',
        'lastName' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : ($parts[0] ?? ''),
    ];
}

function identity_endpoint(string $docType): string
{
    if ($docType === 'bvn') {
        return monnify_env('MONNIFY_BVN_VERIFICATION_ENDPOINT', '/api/v1/vas/bvn-details-match');
    }
    return monnify_env('MONNIFY_NIN_VERIFICATION_ENDPOINT', '/api/v1/vas/nin-details-match');
}

function identity_is_positive_response(array $response): bool
{
    $body = $response['data']['responseBody'] ?? $response['data']['data'] ?? $response['data'] ?? [];
    $flat = array_change_key_case(is_array($body) ? $body : [], CASE_LOWER);
    foreach (['match', 'matched', 'verified', 'valid', 'successful'] as $key) {
        if (array_key_exists($key, $flat)) {
            return filter_var($flat[$key], FILTER_VALIDATE_BOOLEAN) || in_array(strtolower((string) $flat[$key]), ['yes', 'true', 'valid', 'verified', 'success', 'full_match', 'partial_match'], true);
        }
    }
    $message = strtolower((string) ($response['data']['responseMessage'] ?? $response['data']['message'] ?? ''));
    return !empty($response['success']) && (str_contains($message, 'success') || str_contains($message, 'match') || str_contains($message, 'verified'));
}

/**
 * Execute HTTP cURL request for Identity Gateway Verification.
 */
function identity_http_request(string $url, array $headers = [], $body = null, string $method = 'POST', int $timeout = 20): array
{
    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'cURL extension not available',
            'data' => null,
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES) : $body);
        }
    } elseif (strtoupper($method) === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => $curlError,
            'data' => null,
        ];
    }

    $data = json_decode((string) $response, true);
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => (string) $response,
        'data' => $data,
    ];
}

/**
 * Driver 1: Monnify Verification Driver
 */
function identity_driver_monnify(array $gw, string $docType, string $number, array $user): array
{
    $baseUrl = rtrim((string) ($gw['base_url'] ?: 'https://api.monnify.com'), '/');
    $apiKey = (string) ($gw['api_key'] ?: monnify_env('MONNIFY_API_KEY'));
    $apiSecret = (string) ($gw['api_secret'] ?: monnify_env('MONNIFY_SECRET_KEY'));
    $contractCode = (string) ($gw['contract_code'] ?: monnify_env('MONNIFY_CONTRACT_CODE'));

    if (empty($apiKey) || empty($apiSecret)) {
        return ['success' => false, 'error' => 'Monnify API Key or Secret Key is missing.'];
    }

    // Step 1: Login Token
    $authRes = identity_http_request($baseUrl . '/api/v1/auth/login', [
        'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret),
        'Accept: application/json'
    ], null, 'POST');

    $token = $authRes['data']['responseBody']['accessToken'] ?? '';
    if (empty($token)) {
        return ['success' => false, 'error' => 'Monnify token auth failed: ' . ($authRes['data']['responseMessage'] ?? $authRes['response'])];
    }

    // Step 2: Verification Match Request
    $endpoint = $docType === 'bvn' ? '/api/v1/vas/bvn-details-match' : '/api/v1/vas/nin-details-match';
    $names = identity_name_parts((string) ($user['name'] ?? ''));
    $payload = [
        $docType => $number,
        'number' => $number,
        'firstName' => $names['firstName'],
        'lastName' => $names['lastName'],
        'name' => (string) ($user['name'] ?? ''),
        'mobileNo' => (string) ($user['phone'] ?? ''),
        'dateOfBirth' => !empty($user['dob']) ? date('d-m-Y', strtotime((string) $user['dob'])) : '',
        'customerEmail' => (string) ($user['email'] ?? ''),
        'contractCode' => $contractCode,
    ];
    $payload = array_filter($payload, static fn($v) => $v !== '');

    $res = identity_http_request($baseUrl . $endpoint, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ], $payload, 'POST');

    $isMatch = identity_is_positive_response($res);
    $ref = $res['data']['responseBody']['reference'] ?? $res['data']['requestReference'] ?? ('MON-' . bin2hex(random_bytes(4)));

    return [
        'success' => $res['success'],
        'status' => $isMatch ? 'valid' : ($res['success'] ? 'invalid' : 'error'),
        'match_status' => $isMatch ? 'FULL_MATCH' : 'NO_MATCH',
        'confidence' => $isMatch ? 100.0 : 0.0,
        'reference' => $ref,
        'message' => $isMatch ? strtoupper($docType) . ' verified by Monnify.' : ($res['data']['responseMessage'] ?? 'Monnify details match failed.'),
        'response' => $res['data'] ?? $res['response'],
        'raw' => $res['response']
    ];
}

/**
 * Driver 2: Dojah Verification Driver (https://dojah.io)
 */
function identity_driver_dojah(array $gw, string $docType, string $number, array $user): array
{
    $baseUrl = rtrim((string) ($gw['base_url'] ?: 'https://api.dojah.io'), '/');
    $secretKey = (string) ($gw['api_key'] ?? '');
    $appId = (string) ($gw['app_id'] ?? $gw['contract_code'] ?? '');

    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Dojah Secret Key is missing.'];
    }

    $endpoint = $docType === 'bvn' ? "/api/v1/kyc/bvn?bvn={$number}" : "/api/v1/kyc/nin?nin={$number}";
    $headers = [
        'Authorization: ' . $secretKey,
        'AppId: ' . $appId,
        'Accept: application/json'
    ];

    $res = identity_http_request($baseUrl . $endpoint, $headers, null, 'GET');
    $entity = $res['data']['entity'] ?? null;
    $isMatch = !empty($entity);

    $ref = 'DOJ-' . bin2hex(random_bytes(4));
    return [
        'success' => $res['success'],
        'status' => $isMatch ? 'valid' : ($res['success'] ? 'invalid' : 'error'),
        'match_status' => $isMatch ? 'FULL_MATCH' : 'NO_MATCH',
        'confidence' => $isMatch ? 100.0 : 0.0,
        'reference' => $ref,
        'demographics' => $entity,
        'message' => $isMatch ? strtoupper($docType) . ' validated on Dojah Registry.' : ($res['data']['error'] ?? 'Dojah record not found.'),
        'response' => $res['data'] ?? $res['response'],
        'raw' => $res['response']
    ];
}

/**
 * Driver 3: NetApps Verification Driver (https://doc.netapps.ng)
 */
function identity_driver_netapps(array $gw, string $docType, string $number, array $user): array
{
    $baseUrl = rtrim((string) ($gw['base_url'] ?: 'https://api.netapps.ng'), '/');
    $apiKey = (string) ($gw['api_key'] ?? '');

    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'NetApps API Key is missing.'];
    }

    $endpoint = $docType === 'bvn' ? '/api/v1/bvn/verify' : '/api/v1/nin/verify';
    $payload = [
        $docType => $number,
        'name' => (string) ($user['name'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
    ];

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $res = identity_http_request($baseUrl . $endpoint, $headers, $payload, 'POST');
    $isMatch = !empty($res['success']) && (!isset($res['data']['status']) || in_array(strtolower((string)$res['data']['status']), ['success', 'true', 'valid'], true));

    $ref = $res['data']['reference'] ?? ('NET-' . bin2hex(random_bytes(4)));
    return [
        'success' => $res['success'],
        'status' => $isMatch ? 'valid' : ($res['success'] ? 'invalid' : 'error'),
        'match_status' => $isMatch ? 'FULL_MATCH' : 'NO_MATCH',
        'confidence' => $isMatch ? 100.0 : 0.0,
        'reference' => $ref,
        'message' => $isMatch ? strtoupper($docType) . ' confirmed by NetApps.' : ($res['data']['message'] ?? 'NetApps validation failed.'),
        'response' => $res['data'] ?? $res['response'],
        'raw' => $res['response']
    ];
}

/**
 * Driver 4: VerifyMe / QoreID Driver (https://docs.qoreid.com)
 */
function identity_driver_verifyme(array $gw, string $docType, string $number, array $user): array
{
    $baseUrl = rtrim((string) ($gw['base_url'] ?: 'https://api.qoreid.com'), '/');
    $secretKey = (string) ($gw['api_key'] ?? '');

    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'QoreID/VerifyMe Secret Key is missing.'];
    }

    $endpoint = $docType === 'bvn' ? "/v1/ng/identities/bvn/{$number}" : "/v1/ng/identities/nin/{$number}";
    $names = identity_name_parts((string) ($user['name'] ?? ''));
    $payload = [
        'firstname' => $names['firstName'],
        'lastname' => $names['lastName'],
        'phone' => (string) ($user['phone'] ?? ''),
        'dob' => !empty($user['dob']) ? date('Y-m-d', strtotime((string) $user['dob'])) : '',
    ];

    $headers = [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $res = identity_http_request($baseUrl . $endpoint, $headers, $payload, 'POST');
    $statusStr = strtolower((string) ($res['data']['status']['status'] ?? $res['data']['status'] ?? ''));
    $isMatch = $res['success'] && in_array($statusStr, ['verified', 'match', 'success', 'full_match'], true);

    $ref = $res['data']['id'] ?? $res['data']['reference'] ?? ('QOR-' . bin2hex(random_bytes(4)));
    return [
        'success' => $res['success'],
        'status' => $isMatch ? 'valid' : ($res['success'] ? 'invalid' : 'error'),
        'match_status' => $isMatch ? 'FULL_MATCH' : 'NO_MATCH',
        'confidence' => $isMatch ? 100.0 : 0.0,
        'reference' => $ref,
        'message' => $isMatch ? strtoupper($docType) . ' verified by QoreID/VerifyMe.' : ($res['data']['message'] ?? 'VerifyMe matching unsuccessful.'),
        'response' => $res['data'] ?? $res['response'],
        'raw' => $res['response']
    ];
}

/**
 * Driver 5: Custom / Generic Driver
 */
function identity_driver_custom(array $gw, string $docType, string $number, array $user): array
{
    $baseUrl = rtrim((string) ($gw['base_url'] ?? ''), '/');
    $apiKey = (string) ($gw['api_key'] ?? '');

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (!empty($apiKey)) {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

    $payload = [
        'id_type' => $docType,
        'id_number' => $number,
        'name' => (string) ($user['name'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
    ];

    $res = identity_http_request($baseUrl, $headers, $payload, 'POST');
    $isMatch = !empty($res['success']);
    return [
        'success' => $res['success'],
        'status' => $isMatch ? 'valid' : 'error',
        'match_status' => $isMatch ? 'FULL_MATCH' : 'NO_MATCH',
        'confidence' => $isMatch ? 100.0 : 0.0,
        'reference' => 'CUST-' . bin2hex(random_bytes(4)),
        'message' => $isMatch ? strtoupper($docType) . ' verified by custom gateway.' : 'Custom identity provider rejected verification.',
        'response' => $res['data'] ?? $res['response'],
        'raw' => $res['response']
    ];
}

/**
 * Master Multi-Provider Identity Orchestrator with Intelligent Auto-Failover
 */
function identity_verify_multi_provider(PDO $pdo, int $userId, string $docType, string $documentNumber, array $options = []): array
{
    identity_ensure_schema($pdo);

    $docType = strtolower(trim($docType));
    if (!in_array($docType, ['nin', 'bvn'], true)) {
        return ['status' => 'skipped', 'message' => 'Only NIN and BVN use automated multi-gateway identity verification.'];
    }

    $number = preg_replace('/\D+/', '', $documentNumber);
    if (strlen($number) !== 11) {
        return ['status' => 'invalid', 'message' => strtoupper($docType) . ' must be exactly 11 digits.'];
    }

    // Retrieve user metadata
    $stmt = $pdo->prepare("SELECT id, name, email, phone, dob FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $user = ['id' => $userId, 'name' => 'Grower Applicant', 'email' => '', 'phone' => '', 'dob' => ''];
    }

    // Determine target gateways (ordered by primary flag and priority)
    if (!empty($options['gateway_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM identity_gateways WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([(int) $options['gateway_id']]);
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (!empty($options['gateway_key'])) {
        $stmt = $pdo->prepare("SELECT * FROM identity_gateways WHERE gateway_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([(string) $options['gateway_key']]);
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT * FROM identity_gateways WHERE status = 'active' ORDER BY is_primary DESC, priority ASC, id ASC");
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($gateways)) {
        return [
            'status' => 'error',
            'message' => 'No active identity verification gateways are configured. Please enable Monnify, Dojah, NetApps, or VerifyMe in Gateway Settings.'
        ];
    }

    $errorsList = [];
    $attemptedGateways = [];

    foreach ($gateways as $gw) {
        $gwKey = (string) $gw['gateway_key'];
        $gwName = (string) $gw['name'];
        $driver = (string) $gw['provider_driver'];
        $attemptedGateways[] = $gwName;

        // Simulation Mode check
        $isSampleKey = str_contains((string) $gw['api_key'], 'SAMPLE') || str_contains((string) $gw['api_key'], 'sample');
        if ($gw['environment'] === 'simulation' || $isSampleKey) {
            $simRef = 'SIM-' . strtoupper($gwKey) . '-' . strtoupper(bin2hex(random_bytes(4)));
            $result = [
                'success' => true,
                'status' => 'valid',
                'match_status' => 'FULL_MATCH',
                'confidence' => 100.0,
                'reference' => $simRef,
                'provider' => $gwKey,
                'message' => strtoupper($docType) . " successfully verified via {$gwName} (Simulation Mode).",
                'response' => ['simulated' => true, 'timestamp' => date('c')]
            ];
            identity_log_verification($pdo, $userId, $docType, $number, (int)$gw['id'], $gwKey, $gwName, 'simulated', 'FULL_MATCH', 100.0, $simRef, json_encode($result));
            return $result;
        }

        // Driver Execution
        $driverResult = null;
        if ($driver === 'monnify') {
            $driverResult = identity_driver_monnify($gw, $docType, $number, $user);
        } elseif ($driver === 'dojah') {
            $driverResult = identity_driver_dojah($gw, $docType, $number, $user);
        } elseif ($driver === 'netapps') {
            $driverResult = identity_driver_netapps($gw, $docType, $number, $user);
        } elseif ($driver === 'verifyme') {
            $driverResult = identity_driver_verifyme($gw, $docType, $number, $user);
        } else {
            $driverResult = identity_driver_custom($gw, $docType, $number, $user);
        }

        // If verification produced a definitive response (valid or invalid)
        if (!empty($driverResult['success']) && in_array($driverResult['status'] ?? '', ['valid', 'invalid'], true)) {
            $driverResult['provider'] = $gwKey;
            identity_log_verification(
                $pdo,
                $userId,
                $docType,
                $number,
                (int)$gw['id'],
                $gwKey,
                $gwName,
                $driverResult['status'],
                $driverResult['match_status'] ?? null,
                (float)($driverResult['confidence'] ?? 0),
                $driverResult['reference'] ?? null,
                $driverResult['raw'] ?? json_encode($driverResult)
            );
            return $driverResult;
        }

        // Record error and continue cascade failover to next provider
        $errText = $driverResult['error'] ?? ($driverResult['message'] ?? 'Gateway request failed');
        $errorsList[] = "[{$gwName}]: {$errText}";
        identity_log_verification($pdo, $userId, $docType, $number, (int)$gw['id'], $gwKey, $gwName, 'error', null, 0, null, json_encode($driverResult), $errText);
    }

    $combinedErrors = implode(' | ', $errorsList);
    return [
        'status' => 'error',
        'message' => "Identity verification failed on all active providers (" . implode(', ', $attemptedGateways) . "): {$combinedErrors}",
        'errors' => $errorsList
    ];
}

/**
 * Log identity verification audit entry
 */
function identity_log_verification(PDO $pdo, int $userId, string $docType, string $docNumber, ?int $gwId, ?string $gwKey, ?string $gwName, string $status, ?string $matchStatus, float $confidence, ?string $ref, ?string $rawResponse, ?string $errMsg = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO identity_verification_logs (user_id, document_type, document_number, gateway_id, gateway_key, provider_name, status, match_status, confidence_score, provider_reference, raw_response, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $docType,
            $docNumber,
            $gwId,
            $gwKey,
            $gwName,
            $status,
            $matchStatus,
            $confidence,
            $ref,
            $rawResponse,
            $errMsg
        ]);
    } catch (Throwable $e) {
        error_log("Identity verification log failure: " . $e->getMessage());
    }
}

/**
 * Backwards-compatible Monnify wrapper that seamlessly routes through multi-provider engine.
 */
function identity_verify_with_monnify(PDO $pdo, int $userId, string $docType, string $documentNumber): array
{
    return identity_verify_multi_provider($pdo, $userId, $docType, $documentNumber);
}

/**
 * Validates document requirement from requirementId and updates document record.
 */
function identity_validate_requirement(PDO $pdo, int $requirementId): array
{
    identity_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM document_requirements WHERE id = ? LIMIT 1");
    $stmt->execute([$requirementId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        return ['status' => 'error', 'message' => 'Document requirement not found.'];
    }

    $result = identity_verify_multi_provider($pdo, (int) $doc['user_id'], (string) $doc['document_type'], (string) $doc['document_number']);
    $status = (string) ($result['status'] ?? 'error');
    $provider = (string) ($result['provider'] ?? 'multi_gateway');
    $reference = (string) ($result['reference'] ?? '');
    $notes = (string) ($result['message'] ?? '');
    $verificationStatus = $status === 'valid' ? 'verified' : ($status === 'invalid' ? 'rejected' : 'pending');
    $verified = $status === 'valid' ? 1 : 0;

    $pdo->prepare("
        UPDATE document_requirements
        SET api_validation_status = ?, api_validation_provider = ?, api_validation_reference = ?,
            api_validation_response = ?, api_validation_timestamp = NOW(),
            verification_status = ?, verified = ?, verification_notes = ?, verified_at = IF(? = 1, NOW(), NULL)
        WHERE id = ?
    ")->execute([
        $status,
        $provider,
        $reference,
        json_encode($result, JSON_UNESCAPED_SLASHES),
        $verificationStatus,
        $verified,
        $notes,
        $verified,
        $requirementId,
    ]);

    return $result;
}
