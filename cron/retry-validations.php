<?php
// cron/retry-validations.php
require_once '../config.php';

// Get failed validations eligible for retry
$maxRetries = $pdo->query("SELECT value FROM settings WHERE key_name = 'max_validation_retries'")->fetchColumn();
$retryHours = $pdo->query("SELECT value FROM settings WHERE key_name = 'retry_interval_hours'")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT dr.id, dr.document_type, dr.document_number, dr.user_id, u.name, u.dob
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    WHERE dr.api_validation_status = 'error'
    AND dr.retry_count < ?
    AND (dr.last_retry_at IS NULL OR dr.last_retry_at < DATE_SUB(NOW(), INTERVAL ? HOUR))
    LIMIT 50
");
$stmt->execute([$maxRetries, $retryHours]);
$failedDocs = $stmt->fetchAll();

require_once '../lib/identity-validation.php';
$validator = new IdentityValidator($pdo);

foreach ($failedDocs as $doc) {
    echo "Retrying validation for {$doc['document_type']} {$doc['document_number']}...\n";
    
    // Parse name
    $nameParts = explode(' ', $doc['name'], 2);
    $firstName = $nameParts[0] ?? '';
    $lastName = $nameParts[1] ?? $firstName;
    
    // Retry validation
    if ($doc['document_type'] === 'bvn') {
        $result = $validator->validateBVN($doc['document_number'], $firstName, $lastName, $doc['dob']);
    } elseif ($doc['document_type'] === 'nin') {
        $result = $validator->validateNIN($doc['document_number'], $firstName, $lastName, $doc['dob']);
    }
    
    if ($result) {
        // Update document record
        $pdo->prepare("
            UPDATE document_requirements 
            SET api_validation_status = ?, 
                api_validation_response = ?, 
                api_validation_timestamp = NOW(),
                retry_count = retry_count + 1,
                last_retry_at = NOW()
            WHERE id = ?
        ")->execute([$result['status'], json_encode($result), $doc['id']]);
        
        echo "Retry result: {$result['status']}\n";
    }
}

echo "Retry process completed.\n";
?>