<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/certificates.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$pdo = db();
app_ensure_certificate_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$ref = trim((string) ($_GET['ref'] ?? ''));

if ($ref === '') {
    http_response_code(422);
    exit('Certificate reference is required.');
}

$stmt = $pdo->prepare("
    SELECT COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) display_ref,
           COALESCE(c.status, 'issued') status,
           c.issued_at,
           c.verification_url,
           c.certificate_pdf_path,
           a.app_ref,
           a.name,
           a.location,
           a.farm_size
    FROM certificates c
    JOIN applications a ON a.id = c.application_id
    WHERE c.user_id = ?
      AND COALESCE(c.status, 'issued') = 'issued'
      AND (c.certificate_ref = ? OR c.qr_code_hash = ? OR a.app_ref = ?)
    ORDER BY c.issued_at DESC
    LIMIT 1
");
$stmt->execute([$userId, $ref, $ref, $ref]);
$certificate = $stmt->fetch();

if (!$certificate) {
    http_response_code(404);
    exit('Certificate not found.');
}

$pdfPath = ltrim((string) ($certificate['certificate_pdf_path'] ?? ''), '/');
$absolutePdf = $pdfPath !== '' ? dirname(__DIR__) . '/' . $pdfPath : '';

if ($absolutePdf !== '' && is_file($absolutePdf)) {
    $pdf = (string) file_get_contents($absolutePdf);
} else {
    $pdf = certificate_pdf_document($certificate);
    $pdfPath = 'certificates/' . strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', (string) $certificate['display_ref'])) . '.pdf';
    $absolutePdf = dirname(__DIR__) . '/' . $pdfPath;
    if (!is_dir(dirname($absolutePdf))) {
        mkdir(dirname($absolutePdf), 0775, true);
    }
    file_put_contents($absolutePdf, $pdf, LOCK_EX);
    $pdo->prepare("UPDATE certificates SET certificate_pdf_path = ? WHERE user_id = ? AND (certificate_ref = ? OR qr_code_hash = ?)")
        ->execute([$pdfPath, $userId, $ref, $ref]);
}

$fileName = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', (string) $certificate['display_ref'])) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');

echo $pdf;
exit;
