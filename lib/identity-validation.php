<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/monnify.php';

function identity_ensure_schema(PDO $pdo): void
{
    app_ensure_farmer_engagement_schema($pdo);
    foreach ([
        'api_validation_status' => "VARCHAR(30) NULL",
        'api_validation_provider' => "VARCHAR(40) NULL",
        'api_validation_reference' => "VARCHAR(120) NULL",
        'api_validation_response' => "LONGTEXT NULL",
        'api_validation_timestamp' => "DATETIME NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'document_requirements', $column, $definition);
    }
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
            return filter_var($flat[$key], FILTER_VALIDATE_BOOLEAN) || in_array(strtolower((string) $flat[$key]), ['yes', 'true', 'valid', 'verified', 'success'], true);
        }
    }
    $message = strtolower((string) ($response['data']['responseMessage'] ?? $response['data']['message'] ?? ''));
    return $response['success'] && (str_contains($message, 'success') || str_contains($message, 'match') || str_contains($message, 'verified'));
}

function identity_verify_with_monnify(PDO $pdo, int $userId, string $docType, string $documentNumber): array
{
    if (!in_array($docType, ['nin', 'bvn'], true)) {
        return ['status' => 'skipped', 'message' => 'Only NIN and BVN use Monnify identity verification.'];
    }
    if (!monnify_is_configured()) {
        return ['status' => 'error', 'message' => 'Monnify API key, secret key, and contract code are required.'];
    }

    $stmt = $pdo->prepare("SELECT id, name, email, phone, dob FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['status' => 'error', 'message' => 'User not found.'];
    }

    $number = preg_replace('/\D+/', '', $documentNumber);
    if ($docType === 'nin' && strlen($number) !== 11) {
        return ['status' => 'invalid', 'message' => 'NIN must be 11 digits.'];
    }
    if ($docType === 'bvn' && strlen($number) !== 11) {
        return ['status' => 'invalid', 'message' => 'BVN must be 11 digits.'];
    }

    $names = identity_name_parts((string) $user['name']);
    $payload = [
        $docType => $number,
        'number' => $number,
        'firstName' => $names['firstName'],
        'lastName' => $names['lastName'],
        'name' => (string) $user['name'],
        'mobileNo' => (string) ($user['phone'] ?? ''),
        'dateOfBirth' => !empty($user['dob']) ? date('d-m-Y', strtotime((string) $user['dob'])) : '',
        'customerEmail' => (string) ($user['email'] ?? ''),
        'contractCode' => monnify_env('MONNIFY_CONTRACT_CODE'),
    ];
    $payload = array_filter($payload, static fn ($value): bool => $value !== '');

    $response = monnify_request('POST', identity_endpoint($docType), $payload);
    if (!$response['success']) {
        return [
            'status' => 'error',
            'message' => (string) ($response['error'] ?? 'Monnify identity verification failed.'),
            'response' => $response,
        ];
    }

    return [
        'status' => identity_is_positive_response($response) ? 'valid' : 'invalid',
        'message' => identity_is_positive_response($response) ? strtoupper($docType) . ' verified by Monnify.' : strtoupper($docType) . ' could not be matched by Monnify.',
        'response' => $response,
    ];
}

function identity_validate_requirement(PDO $pdo, int $requirementId): array
{
    identity_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM document_requirements WHERE id = ? LIMIT 1");
    $stmt->execute([$requirementId]);
    $doc = $stmt->fetch();
    if (!$doc) {
        return ['status' => 'error', 'message' => 'Document requirement not found.'];
    }

    $result = identity_verify_with_monnify($pdo, (int) $doc['user_id'], (string) $doc['document_type'], (string) $doc['document_number']);
    $status = (string) $result['status'];
    $verificationStatus = 'pending';
    $verified = 0;
    $notes = (string) ($result['message'] ?? '');
    $reference = (string) ($result['response']['data']['responseBody']['reference'] ?? $result['response']['data']['requestReference'] ?? '');

    $pdo->prepare("
        UPDATE document_requirements
        SET api_validation_status = ?, api_validation_provider = 'monnify', api_validation_reference = ?,
            api_validation_response = ?, api_validation_timestamp = NOW(),
            verification_status = ?, verified = ?, verification_notes = ?, verified_at = NULL
        WHERE id = ?
    ")->execute([
        $status,
        $reference,
        json_encode($result, JSON_UNESCAPED_SLASHES),
        $verificationStatus,
        $verified,
        $notes,
        $requirementId,
    ]);

    return $result;
}
