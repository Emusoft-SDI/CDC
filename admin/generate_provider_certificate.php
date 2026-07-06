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

$certRef = provider_accreditation_certificate_ref($provider);
$issuedAt = date('Y-m-d H:i:s');
$pdf = provider_accreditation_certificate_pdf_document($provider, $certRef, $issuedAt);
$fileName = provider_accreditation_certificate_filename($provider, $certRef);
$relative = 'certificates/provider/' . $fileName;
$absolute = dirname(__DIR__) . '/' . $relative;
if (!is_dir(dirname($absolute))) {
    mkdir(dirname($absolute), 0755, true);
}
file_put_contents($absolute, $pdf, LOCK_EX);

echo "Saved PDF: {$relative}\n";
echo "Absolute: {$absolute}\n";
echo "Size: " . filesize($absolute) . " bytes\n";

exit(0);
