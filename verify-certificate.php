<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/certificates.php';
require_once __DIR__ . '/lib/academy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function vc_lookup_certificate(PDO $pdo, string $ref): ?array
{
    $certificate = findCertificate($ref, $pdo);
    if ($certificate) {
        $certificate['certificate_program'] = 'NATCODEV Coconut Outgrowers Program';
        $certificate['certificate_type'] = 'Official Grower Credential';
        $certificate['subject_label'] = 'Application Reference';
        if (empty($certificate['expires_at'] ?? null) && !empty($certificate['issued_at'])) {
            $certificate['expires_at'] = grower_certificate_expires_at($pdo, (string) $certificate['issued_at']);
        }
        return $certificate;
    }

    academy_ensure_schema($pdo);
    if (app_table_exists($pdo, 'academy_certificates')) {
        $stmt = $pdo->prepare("
            SELECT c.certificate_ref, c.status, c.user_id, c.issued_at, c.certificate_pdf_path, NULL expires_at, NULL revoked_at, NULL revoked_reason,
                   u.name, w.title app_ref, 'NATCODEV Academy' certificate_program,
                   'Academy Course Certificate' certificate_type, 'Course' subject_label
            FROM academy_certificates c
            JOIN users u ON u.id = c.user_id
            JOIN webinars w ON w.id = c.webinar_id
            WHERE c.certificate_ref = ?
            LIMIT 1
        ");
        $stmt->execute([$ref]);
        $certificate = $stmt->fetch() ?: null;
        if ($certificate) {
            return $certificate;
        }
    }

    if (app_table_exists($pdo, 'academy_group_certificates') && app_table_exists($pdo, 'academy_certificate_groups')) {
        $stmt = $pdo->prepare("
            SELECT c.certificate_ref, c.status, c.user_id, c.issued_at, c.certificate_pdf_path, NULL expires_at, NULL revoked_at, NULL revoked_reason,
                   u.name, g.title app_ref, 'NATCODEV Academy' certificate_program,
                   'Grouped Academy Certificate' certificate_type, 'Certificate Pathway' subject_label
            FROM academy_group_certificates c
            JOIN users u ON u.id = c.user_id
            JOIN academy_certificate_groups g ON g.id = c.group_id
            WHERE c.certificate_ref = ?
            LIMIT 1
        ");
        $stmt->execute([$ref]);
        return $stmt->fetch() ?: null;
    }

    return null;
}

$ref = trim((string) ($_GET['ref'] ?? $_GET['certificate_ref'] ?? $_GET['q'] ?? ''));
$certificate = null;
$lookupAttempted = $ref !== '';
$error = '';
$valid = false;
$expired = false;
$downloadUrl = '';
$viewUrl = '';
$verifiedAt = date('F j, Y \a\t h:i A') . ' (WAT)';

try {
    $pdo = db();
    if ($lookupAttempted) {
        if (!app_check_rate_limit('certificate_verify', 20, 600)) {
            $error = 'Too many verification attempts. Please try again in 10 minutes.';
        } else {
            $certificate = vc_lookup_certificate($pdo, $ref);
        }
    }
} catch (Throwable $e) {
    error_log('Certificate verification error: ' . $e->getMessage());
    $error = 'Verification is temporarily unavailable. Please try again.';
}

if ($certificate && !empty($certificate['expires_at'])) {
    $expiryTime = strtotime((string) $certificate['expires_at']);
    $expired = $expiryTime !== false && $expiryTime < time();
}
$valid = $certificate && (string) ($certificate['status'] ?? '') === 'issued' && !$expired && empty($certificate['revoked_at']);

$viewerId = (int) ($_SESSION['user_id'] ?? 0);
$viewer = null;
if ($viewerId > 0 && isset($pdo)) {
    try {
        $viewerStmt = $pdo->prepare("SELECT role, is_super_admin FROM users WHERE id = ? LIMIT 1");
        $viewerStmt->execute([$viewerId]);
        $viewer = $viewerStmt->fetch() ?: null;
    } catch (Throwable $e) {
        $viewer = null;
    }
}
$isOwner = $certificate && $viewerId > 0 && (int) ($certificate['user_id'] ?? 0) === $viewerId;
$isAdminViewer = $viewer && ((string) ($viewer['role'] ?? '') === 'admin' || (int) ($viewer['is_super_admin'] ?? 0) === 1);
$canDownload = $valid && ($isOwner || $isAdminViewer);
if ($canDownload && $certificate) {
    $downloadUrl = str_contains((string) ($certificate['certificate_type'] ?? ''), 'Grower')
        ? 'dashboard/download-certificate.php?ref=' . urlencode((string) $certificate['certificate_ref'])
        : 'dashboard/download-academy-certificate.php?ref=' . urlencode((string) $certificate['certificate_ref']);
}
if ($certificate && !empty($certificate['certificate_path']) && is_file(__DIR__ . '/' . (string) $certificate['certificate_path'])) {
    $viewUrl = (string) $certificate['certificate_path'];
}

if (($_GET['format'] ?? '') === 'json') {
    json_response(['success' => true, 'valid' => (bool) $valid, 'certificate' => $certificate]);
}

$statusTitle = !$lookupAttempted ? 'Verify Any NATCODEV Certificate' : ($valid ? 'Certificate is Valid' : ($certificate ? ($expired ? 'Certificate Has Expired' : 'Certificate is Not Valid') : 'Certificate Not Found'));
$statusClass = !$lookupAttempted ? 'neutral' : ($valid ? 'valid' : 'invalid');
$statusIcon = !$lookupAttempted ? 'fa-shield-halved' : ($valid ? 'fa-check' : 'fa-xmark');
$issueDate = $certificate && !empty($certificate['issued_at']) ? date('F j, Y', strtotime((string) $certificate['issued_at'])) : 'Not available';
$expiryDate = $certificate && !empty($certificate['expires_at']) ? date('F j, Y', strtotime((string) $certificate['expires_at'])) : 'Permanent';
$verificationId = $lookupAttempted ? 'VER-' . strtoupper(substr(hash('sha256', $ref . 'natcodev'), 0, 18)) : 'Enter reference';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify NATCODEV Certificate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#06451f;--green2:#08753a;--mint:#eef8ef;--gold:#d89b10;--blue:#1d4ed8;--red:#d92d20;--orange:#f79009;--ink:#101828;--muted:#667085;--line:#dfe8d8;--bg:#fbfcf8;--shadow:0 18px 48px rgba(16,24,40,.08)}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.top{height:82px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 38px;position:sticky;top:0;z-index:20}.brand{display:flex;gap:12px;align-items:center}.brand img{width:62px;height:62px;border-radius:50%}.brand strong{font-size:1.65rem;color:var(--green)}.brand small{display:block;font-weight:800;color:#344054;text-transform:uppercase}.nav{display:flex;gap:30px;font-weight:900}.nav a.active{color:var(--green);border-bottom:3px solid var(--green);padding-bottom:27px}.top-actions{display:flex;gap:12px;align-items:center}.icon-btn{width:48px;height:48px;border:1px solid var(--line);border-radius:9px;display:grid;place-items:center;color:var(--green)}.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;border:1px solid var(--green);border-radius:9px;background:var(--green);color:#fff;font-weight:950;padding:12px 18px;cursor:pointer}.btn.light{background:#fff;color:var(--green)}.hero{min-height:330px;background:linear-gradient(90deg,rgba(3,34,14,.88),rgba(3,34,14,.60) 45%,rgba(3,34,14,.10) 74%),url("assets/public/certificate-verify-hero.png") center/cover;color:#fff;padding:48px 58px;display:flex;align-items:center}.hero h1{font-size:clamp(2.4rem,4.6vw,4rem);line-height:1.05;margin:0 0 12px}.hero h1 span{color:#a7e57b}.hero p{font-size:1.15rem;margin:0 0 22px}.verify-form{display:grid;grid-template-columns:minmax(260px,620px) 210px auto 220px;gap:14px;align-items:center}.verify-form input{height:64px;border:0;border-radius:9px;padding:0 20px;font:inherit;font-size:1rem}.qr-box{height:64px;border:1px dashed #e8f6ec;border-radius:9px;display:flex;gap:12px;align-items:center;justify-content:center;background:rgba(255,255,255,.08);font-weight:900}.qr-box i{font-size:1.7rem}.wrap{padding:0 54px 34px}.result{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);margin-top:-52px;position:relative;z-index:2;padding:22px}.status-head{display:flex;justify-content:space-between;gap:18px;align-items:center;margin-bottom:16px}.status-left{display:flex;gap:18px;align-items:center}.status-icon{width:64px;height:64px;border-radius:18px;display:grid;place-items:center;color:#fff;font-size:1.8rem}.status-icon.valid{background:var(--green)}.status-icon.invalid{background:var(--red)}.status-icon.neutral{background:#475467}.status-left h2{margin:0;color:var(--green);font-size:1.6rem}.verified-on{border:1px solid var(--line);border-radius:9px;padding:10px 14px;color:#344054;background:#fff}.grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(360px,.95fr) minmax(320px,.85fr);gap:20px}.panel{border:1px solid var(--line);border-radius:12px;padding:16px;background:#fff}.details{display:grid;gap:0}.detail{display:grid;grid-template-columns:220px 1fr;gap:12px;border-bottom:1px solid var(--line);padding:12px 0}.detail:last-child{border-bottom:0}.detail strong{display:flex;gap:10px;align-items:center}.detail i{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:var(--green);color:#fff;font-size:.8rem}.preview{border:1px solid #ead99a;border-radius:10px;padding:12px;background:#fffdf3;text-align:center}.cert-card{background:#fffdf7;border:8px solid var(--green);box-shadow:inset 0 0 0 2px var(--gold);min-height:270px;padding:24px 20px;display:grid;place-items:center;text-align:center}.cert-card h3{color:#9a6500;margin:0}.cert-card h2{font-family:Georgia,serif;color:var(--green);font-size:2rem;margin:8px 0}.cert-card .name{font-family:Georgia,serif;font-size:2rem;font-weight:950;border-bottom:1px solid var(--gold);padding-bottom:6px}.preview-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}.audit{background:linear-gradient(135deg,#f9fffb,#eef8ef);border-radius:10px;padding:12px}.audit div{display:flex;gap:12px;align-items:center;margin:14px 0}.audit i{color:#fff;background:var(--green);border-radius:50%;width:22px;height:22px;display:grid;place-items:center}.info{margin-top:14px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;border-radius:10px;padding:14px;display:flex;gap:12px}.guide{display:grid;grid-template-columns:1fr 1.15fr;gap:18px;margin-top:18px}.guide-grid,.trust-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.mini{border:1px solid var(--line);border-radius:10px;padding:14px;background:#fff}.mini i{font-size:1.8rem;color:var(--green)}.mini.expired i{color:var(--orange)}.mini.revoked i{color:var(--red)}.mini.notfound i{color:#475467}.footer{background:#052d15;color:#e8f6ec;display:flex;justify-content:space-between;gap:20px;align-items:center;padding:20px 52px;font-weight:900}.muted{color:var(--muted)}.badge{border-radius:999px;background:#fff3d6;color:#9a6500;padding:5px 9px;font-size:.78rem;font-weight:950}.invalid-msg{padding:24px;border:1px dashed var(--line);border-radius:12px;background:#fff;color:#344054}
    @media(max-width:1250px){.verify-form,.grid,.guide{grid-template-columns:1fr}.guide-grid,.trust-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:760px){.top{height:auto;padding:14px;align-items:flex-start;flex-direction:column}.nav{gap:14px;flex-wrap:wrap}.hero{padding:34px 18px}.wrap{padding:0 14px 28px}.status-head,.footer{align-items:flex-start;flex-direction:column}.detail{grid-template-columns:1fr}.guide-grid,.trust-grid,.preview-actions{grid-template-columns:1fr}}
  </style>
</head>
<body>
<header class="top">
  <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>National Coconut Development & Propagation Initiative</small></span></a>
  <nav class="nav"><a href="index.php">Home</a><a href="market/index.php">Marketplace</a><a href="academy/index.php?screen=catalog">Academy</a><a href="apply.php">Registry</a><a class="active" href="verify-certificate.php">Certificates</a><a href="support/index.php?category=verification">Support</a></nav>
  <div class="top-actions"><a class="icon-btn" href="market/index.php"><i class="fas fa-search"></i></a><a class="btn light" href="login.php"><i class="far fa-user"></i> Login</a></div>
</header>
<section class="hero">
  <div>
    <h1>Verify a <span>NATCODEV Certificate</span></h1>
    <p>Check the authenticity, status, issuer, and validity of NATCODEV credentials.</p>
    <form class="verify-form" method="get">
      <input name="ref" value="<?= e($ref) ?>" placeholder="Enter certificate reference (e.g., CERT-NAT-260510-BCB14A-2801)" required>
      <button class="btn" type="submit"><i class="fas fa-shield-halved"></i> Verify Certificate</button>
      <span>or</span>
      <div class="qr-box"><i class="fas fa-qrcode"></i><span>Scan QR Code<br><small>or upload image</small></span></div>
    </form>
  </div>
</section>
<main class="wrap">
  <section class="result">
    <div class="status-head">
      <div class="status-left"><span class="status-icon <?= e($statusClass) ?>"><i class="fas <?= e($statusIcon) ?>"></i></span><div><h2><?= e($statusTitle) ?></h2><p class="muted"><?= $lookupAttempted ? ($valid ? 'This certificate is authentic and currently valid.' : 'The reference was checked against NATCODEV records.') : 'Enter a certificate reference or scan a QR code to confirm authenticity.' ?></p></div></div>
      <div class="verified-on"><i class="far fa-clock"></i> Verified on: <?= e($verifiedAt) ?></div>
    </div>
    <?php if ($error): ?><div class="invalid-msg"><?= e($error) ?></div><?php endif; ?>
    <?php if ($certificate): ?>
      <div class="grid">
        <section class="panel details">
          <div class="detail"><strong><i class="fas fa-id-badge"></i> Certificate Type</strong><span><?= e((string) $certificate['certificate_type']) ?></span></div>
          <div class="detail"><strong><i class="fas fa-user"></i> Recipient Name</strong><span><?= e((string) $certificate['name']) ?></span></div>
          <div class="detail"><strong><i class="fas fa-clipboard"></i> Certificate Reference</strong><span><?= e((string) $certificate['certificate_ref']) ?></span></div>
          <div class="detail"><strong><i class="fas fa-tag"></i> <?= e((string) ($certificate['subject_label'] ?? 'Subject')) ?></strong><span><?= e((string) $certificate['app_ref']) ?></span></div>
          <div class="detail"><strong><i class="fas fa-calendar-day"></i> Issue Date</strong><span><?= e($issueDate) ?></span></div>
          <div class="detail"><strong><i class="fas fa-calendar-check"></i> Expiry Date</strong><span><?= e($expiryDate) ?><?= $expired ? ' <span class="badge">Expired</span>' : '' ?></span></div>
          <div class="detail"><strong><i class="fas fa-building-columns"></i> Issued By</strong><span><?= e((string) $certificate['certificate_program']) ?></span></div>
          <div class="detail"><strong><i class="fas fa-pen-nib"></i> Signed By</strong><span>Chief of Party<br>NATCODEV</span></div>
          <div class="detail"><strong><i class="fas fa-fingerprint"></i> Verification ID</strong><span><?= e($verificationId) ?></span></div>
        </section>
        <section class="panel">
          <h3>Certificate Preview</h3>
          <div class="preview">
            <div class="cert-card">
              <div><h3><?= e((string) $certificate['certificate_program']) ?></h3><h2><?= e((string) $certificate['certificate_type']) ?></h2><p>This is to certify that</p><div class="name"><?= e((string) $certificate['name']) ?></div><p><?= e((string) $certificate['app_ref']) ?></p></div>
            </div>
          </div>
          <div class="preview-actions">
            <?php if ($viewUrl): ?><a class="btn light" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener"><i class="far fa-eye"></i> View Full Size</a><?php else: ?><a class="btn light" href="verify-certificate.php?ref=<?= urlencode((string) $certificate['certificate_ref']) ?>"><i class="far fa-eye"></i> Verified Online</a><?php endif; ?>
            <?php if ($downloadUrl): ?><a class="btn" href="<?= e($downloadUrl) ?>"><i class="fas fa-download"></i> Download Certificate</a><?php else: ?><a class="btn" href="login.php"><i class="fas fa-lock"></i> Owner Download</a><?php endif; ?>
          </div>
        </section>
        <aside class="panel">
          <h3>Verification Audit Trail</h3>
          <div class="audit"><div><i class="fas fa-check"></i> Verified online with NATCODEV registry</div><div><i class="fas fa-check"></i> QR/reference matched</div><div><i class="fas fa-check"></i> Recipient record matched</div><div><i class="fas fa-check"></i> Issuer confirmed</div><div><i class="fas fa-check"></i> Certificate status checked</div></div>
          <div class="info"><i class="fas fa-circle-info"></i><span>This certificate was issued by NATCODEV and is recognized across platform programs according to its status and validity period.</span></div>
        </aside>
      </div>
    <?php elseif ($lookupAttempted): ?>
      <div class="invalid-msg"><h3>No active certificate found</h3><p>No NATCODEV certificate matched <strong><?= e($ref) ?></strong>. Check the reference, scan the QR code again, or contact support if the certificate holder believes this is an error.</p></div>
    <?php else: ?>
      <div class="invalid-msg"><h3>Ready to verify</h3><p>Use a certificate reference or QR code from any NATCODEV grower credential, Academy course certificate, or grouped Academy certificate.</p></div>
    <?php endif; ?>
  </section>
  <section class="guide">
    <div class="panel"><h3>Certificate Status Guide</h3><div class="guide-grid"><div class="mini"><i class="fas fa-shield-check"></i><h4>Valid</h4><p>This certificate is genuine and currently valid.</p></div><div class="mini expired"><i class="far fa-clock"></i><h4>Expired</h4><p>This certificate has expired and is no longer valid.</p></div><div class="mini revoked"><i class="fas fa-shield-xmark"></i><h4>Revoked</h4><p>This certificate has been revoked and is invalid.</p></div><div class="mini notfound"><i class="fas fa-circle-question"></i><h4>Not Found</h4><p>No certificate found with the reference provided.</p></div></div><p class="muted"><i class="far fa-clock"></i> Academy completion certificates do not expire. Operational credentials may have validity periods.</p></div>
    <div class="panel"><h3>Why Trust NATCODEV Certificates?</h3><div class="trust-grid"><div class="mini"><i class="fas fa-shield-heart"></i><h4>Government Backed</h4><p>Issued under recognized coconut development programs.</p></div><div class="mini"><i class="fas fa-certificate"></i><h4>Secure & Verifiable</h4><p>Each certificate has a unique online reference.</p></div><div class="mini"><i class="fas fa-handshake"></i><h4>Widely Recognized</h4><p>Accepted across the coconut value chain.</p></div><div class="mini"><i class="fas fa-users"></i><h4>Empowering Farmers</h4><p>Promotes access to finance, markets, and opportunities.</p></div></div></div>
  </section>
</main>
<footer class="footer"><span><i class="fas fa-lock"></i> Secure. Transparent. Trusted.</span><span>&copy; <?= e(date('Y')) ?> NATCODEV. All rights reserved.</span><span>Need help? <a style="color:#fcd34d" href="support/index.php?category=verification">Contact Support</a> <i class="fas fa-arrow-right"></i></span></footer>
</body>
</html>
