<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
app_require_cli('admin maintenance script');
require_once __DIR__ . '/../provider/_provider.php';
require_once __DIR__ . '/../lib/certificates.php';
header('Content-Type: text/plain; charset=utf-8');

$providerId = (int) ($argv[1] ?? 1);
$pdo = provider_boot();

$stmt = $pdo->prepare('SELECT * FROM provider_registry WHERE id = ? LIMIT 1');
$stmt->execute([$providerId]);
$provider = $stmt->fetch();
if (!$provider) {
    echo "Provider id {$providerId} not found\n";
    exit(1);
}

try {
    $certificate = provider_accreditation_certificate_issue($pdo, $provider);
    echo "Issued certificate:\n";
    print_r($certificate);
    exit(0);
} catch (Throwable $e) {
    echo "Failed to issue: " . $e->getMessage() . "\n";
    exit(1);
}
