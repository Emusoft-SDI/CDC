<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../provider/_provider.php';
header('Content-Type: text/plain; charset=utf-8');

$providerId = (int) ($argv[1] ?? 1);
$pdo = provider_boot();

$types = provider_accreditation_types();
$insert = $pdo->prepare("INSERT INTO provider_accreditation_documents (provider_id, document_type, document_number, original_name, stored_path, mime_type, file_size, status, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status='approved', reviewed_at=CURRENT_TIMESTAMP");
$count = 0;
foreach ($types as $type => $label) {
    $insert->execute([$providerId, $type, strtoupper(substr($type,0,6)) . '-123', $type . '.pdf', 'provider_uploads/accreditation/dummy-' . $type . '.pdf', 'application/pdf', 12345]);
    $count++;
}
echo "Seeded {$count} approved documents for provider {$providerId}\n";
exit(0);
