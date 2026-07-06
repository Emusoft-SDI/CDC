<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

$pdo = db();
app_ensure_certificate_schema($pdo);
grower_registration_ensure_member_schema($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$currentUser = current_user($pdo);
if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $currentUser);

$ref = trim((string) ($_GET['ref'] ?? ''));
if ($ref === '') {
    redirect_to('certificates.php?error=' . urlencode('Certificate reference is required.'));
}

$stmt = $pdo->prepare("
    SELECT c.id certificate_id, c.application_id, COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) display_ref,
           COALESCE(c.status, 'issued') status,
           c.issued_at,
           c.expires_at,
           c.verification_url,
           c.certificate_path,
           a.app_ref,
           a.name,
           a.location,
           a.farm_size,
           a.member_type
    FROM certificates c
    JOIN applications a ON a.id = c.application_id
    WHERE c.user_id = ?
      AND COALESCE(c.status, 'issued') = 'issued'
      AND (c.certificate_ref = ? OR c.qr_code_hash = ? OR a.app_ref = ?)
    ORDER BY c.issued_at DESC
    LIMIT 1
");
$stmt->execute([$userId, $ref, $ref, $ref]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    redirect_to('certificates.php?error=' . urlencode('Certificate not found.'));
}

if (!empty($certificate['expires_at']) && strtotime((string) $certificate['expires_at']) < time()) {
    redirect_to('certificates.php?error=' . urlencode('Certificate has expired.'));
}

$accessRequired = grower_certificate_access_required($pdo, $certificate);
$accessStatus = grower_certificate_access_status($pdo, $userId, (int) $certificate['application_id'], (int) $certificate['certificate_id']);
if ($accessRequired && !$accessStatus['paid']) {
    redirect_to('certificates.php?error=' . urlencode('Pay the certificate access or renewal fee before viewing.'));
}

$html = '';
$path = ltrim(str_replace(['\\', '../'], ['/', ''], (string) ($certificate['certificate_path'] ?? '')), '/');
if ($path !== '' && str_starts_with($path, 'certificates/')) {
    $absolute = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    $base = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'certificates');
    if ($absolute && $base && is_file($absolute) && str_starts_with(strtolower($absolute), strtolower(rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
        $html = (string) file_get_contents($absolute);
    }
}

if ($html === '') {
    $html = certificate_render_html(
        $certificate,
        (string) $certificate['display_ref'],
        (string) ($certificate['issued_at'] ?: date('Y-m-d H:i:s')),
        (string) ($certificate['verification_url'] ?: app_base_url() . '/verify-certificate.php?ref=' . urlencode((string) $certificate['display_ref']))
    );
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $html;