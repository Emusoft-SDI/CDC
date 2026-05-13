<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/certificates.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
if ($ref !== '') {
    try {
        $certificate = findCertificate($ref, db());
        json_response([
            'success' => true,
            'valid' => $certificate && ($certificate['status'] ?? '') === 'issued',
            'certificate' => $certificate,
        ]);
    } catch (Throwable $e) {
        error_log('Certificate lookup API error: ' . $e->getMessage());
        json_response(['success' => false, 'error' => 'Verification unavailable'], 500);
    }
}

try {
    $pdo = db();
    app_ensure_certificate_schema($pdo);
    $stmt = $pdo->query("
        SELECT COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) certificate_ref, a.app_ref, a.name, c.issued_at
        FROM certificates c
        JOIN applications a ON c.application_id = a.id
        WHERE COALESCE(c.status, 'issued') = 'issued'
        ORDER BY c.issued_at DESC
        LIMIT 500
    ");
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Certificate list API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
