<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();
academy_ensure_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$ref = trim((string) ($_GET['ref'] ?? ''));

if ($ref === '') {
    http_response_code(422);
    exit('Academy certificate reference is required.');
}

$stmt = $pdo->prepare("
    SELECT c.id, c.user_id, c.webinar_id, NULL group_id, c.certificate_ref, c.status, c.issued_at, c.certificate_pdf_path,
           'course' certificate_kind, u.name user_name, w.title, w.description
    FROM academy_certificates c
    JOIN users u ON u.id = c.user_id
    JOIN webinars w ON w.id = c.webinar_id
    WHERE c.user_id = ? AND c.certificate_ref = ? AND c.status = 'issued'
    LIMIT 1
");
$stmt->execute([$userId, $ref]);
$certificate = $stmt->fetch();
$courses = [];
$table = 'academy_certificates';

if (!$certificate) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, NULL webinar_id, c.group_id, c.certificate_ref, c.status, c.issued_at, c.certificate_pdf_path,
               'group' certificate_kind, u.name user_name, g.title, g.description
        FROM academy_group_certificates c
        JOIN users u ON u.id = c.user_id
        JOIN academy_certificate_groups g ON g.id = c.group_id
        WHERE c.user_id = ? AND c.certificate_ref = ? AND c.status = 'issued'
        LIMIT 1
    ");
    $stmt->execute([$userId, $ref]);
    $certificate = $stmt->fetch();
    $table = 'academy_group_certificates';
    if ($certificate) {
        $courses = academy_certificate_group_courses($pdo, (int) $certificate['group_id']);
    }
}

if (!$certificate) {
    http_response_code(404);
    exit('Issued Academy certificate not found.');
}

$pdf = academy_certificate_pdf_document($certificate, $courses);
$pdfPath = 'certificates/' . strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', (string) $certificate['certificate_ref'])) . '.pdf';
$absolutePdf = dirname(__DIR__) . '/' . $pdfPath;
if (!is_dir(dirname($absolutePdf))) {
    mkdir(dirname($absolutePdf), 0775, true);
}
file_put_contents($absolutePdf, $pdf, LOCK_EX);
$pdo->prepare("UPDATE {$table} SET certificate_pdf_path = ? WHERE id = ? AND user_id = ?")
    ->execute([$pdfPath, (int) $certificate['id'], $userId]);

$fileName = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', (string) $certificate['certificate_ref'])) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');

echo $pdf;
exit;
