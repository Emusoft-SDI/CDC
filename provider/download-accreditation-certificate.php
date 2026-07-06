<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/certificates.php';

// auth is handled by provider/_provider.php (current_user/provider_active)

$pdo = provider_boot();
$user = current_user($pdo);
$provider = $user ? provider_active($pdo, $user) : null;
$reference = trim((string) ($_GET['ref'] ?? ''));
if (!$provider && $reference === '') {
    http_response_code(404);
    exit('Provider profile not found.');
}

if (!$provider) {
    $certificate = provider_accreditation_certificate_by_ref($pdo, $reference);
    if (!$certificate || !in_array((string) ($certificate['status'] ?? ''), ['issued'], true)) {
        http_response_code(404);
        exit('Accreditation certificate not found.');
    }
    $isAdmin = $user && ((string) ($user['role'] ?? '') === 'admin' || (int) ($user['is_super_admin'] ?? 0) === 1);
    if (!$isAdmin) {
        http_response_code(403);
        exit('Access denied.');
    }
    $provider = $pdo->prepare('SELECT * FROM provider_registry WHERE id = ? LIMIT 1');
    $provider->execute([(int) $certificate['provider_id']]);
    $provider = $provider->fetch() ?: null;
    if (!$provider) {
        http_response_code(404);
        exit('Provider profile not found.');
    }
    $certificateRef = (string) $certificate['certificate_ref'];
    $pdfPath = (string) ($certificate['certificate_pdf_path'] ?? '');
    $pdf = '';
    if ($pdfPath !== '') {
        $absolutePdf = dirname(__DIR__) . '/' . ltrim($pdfPath, '/');
        if (is_file($absolutePdf)) {
            $pdf = (string) file_get_contents($absolutePdf);
        }
    }
    if ($pdf === '') {
        $pdf = provider_accreditation_certificate_pdf_document($provider, $certificateRef, date('Y-m-d H:i:s'));
    }
    $fileName = provider_accreditation_certificate_filename($provider, $certificateRef);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $pdf;
    exit;
}

$items = provider_accreditation_types();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM provider_accreditation_documents WHERE provider_id = ? AND status = 'approved'");
$stmt->execute([(int) $provider['id']]);
$approved = (int) $stmt->fetchColumn();

if ($approved !== count($items) || !in_array((string) $provider['status'], ['approved', 'verified'], true)) {
    http_response_code(403);
    exit('Accreditation certificate is available after full approval.');
}

$certificate = provider_accreditation_certificate_record($pdo, (int) $provider['id']);
if (!$certificate) {
    $certificate = provider_accreditation_certificate_issue($pdo, $provider);
}
$certificateId = (int) ($certificate['id'] ?? 0);
$accessStatus = provider_accreditation_access_status($pdo, (int) $user['id'], (int) $provider['id'], $certificateId);
if (provider_accreditation_access_required($pdo, $provider) && !$accessStatus['paid']) {
    http_response_code(403);
    exit('Pay the provider accreditation certificate access fee before downloading your certificate.');
}

$certificateRef = (string) ($certificate['certificate_ref'] ?? provider_accreditation_certificate_ref($provider));
$pdf = '';
$pdfPath = (string) ($certificate['certificate_pdf_path'] ?? '');
if ($pdfPath !== '') {
    $absolutePdf = dirname(__DIR__) . '/' . ltrim($pdfPath, '/');
    if (is_file($absolutePdf)) {
        $pdf = (string) file_get_contents($absolutePdf);
    }
}
if ($pdf === '') {
    $pdf = provider_accreditation_certificate_pdf_document($provider, $certificateRef, date('Y-m-d H:i:s'));
}
$fileName = provider_accreditation_certificate_filename($provider, $certificateRef);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');

echo $pdf;
exit;
