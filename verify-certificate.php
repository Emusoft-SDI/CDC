<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/certificates.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
if ($ref === '') {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify NATCODEV Certificate</title>
  <style>
    :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; }
    * { box-sizing:border-box; }
    body { font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin:0; padding:32px 18px; color:var(--ink); background:linear-gradient(135deg, rgba(26,82,118,.08), rgba(31,138,85,.10)), #f5f8f6; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    main { width:100%; max-width:680px; background:#fff; border:1px solid rgba(16,24,40,.08); border-radius:8px; padding:34px; box-shadow:0 18px 44px rgba(16,24,40,.12); }
    h1 { color:var(--primary); margin:0 0 10px; }
    p { color:var(--muted); margin-top:0; }
    form { margin-top:22px; }
    label { display:block; font-weight:800; margin-bottom:8px; }
    input, button { width:100%; box-sizing:border-box; padding:13px; margin-top:12px; border-radius:5px; border:1px solid var(--line); font-size:1rem; }
    input:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); outline:none; }
    button { background:var(--green); color:#fff; border:0; cursor:pointer; font-weight:800; box-shadow:0 10px 24px rgba(31,138,85,.22); }
    button:hover { background:var(--green-dark); }
    a { color:var(--green-dark); font-weight:700; text-decoration:none; }
    @media (max-width:520px) { main { padding:26px 18px; } }
  </style>
</head>
<body>
  <main>
  <h1>Verify NATCODEV Certificate</h1>
  <p>Enter the certificate reference exactly as shown on the issued certificate.</p>
  <form method="get">
    <label for="ref">Certificate Reference</label>
    <input id="ref" name="ref" type="text" placeholder="CERT-NAT-..." required>
    <button type="submit">Verify Certificate</button>
  </form>
  <p style="margin-top:18px;"><a href="index.php">Back to home</a></p>
  </main>
</body>
</html>
    <?php
    exit;
}

try {
    $certificate = findCertificate($ref, db());
} catch (Throwable $e) {
    error_log('Certificate verification error: ' . $e->getMessage());
    http_response_code(500);
    exit('Verification temporarily unavailable.');
}

$valid = $certificate && ($certificate['status'] ?? '') === 'issued';

if (($_GET['format'] ?? '') === 'json') {
    json_response([
        'success' => true,
        'valid' => (bool) $valid,
        'certificate' => $certificate,
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify NATCODEV Certificate</title>
  <style>
    :root { --primary:#1a5276; --green:#1f8a55; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; }
    * { box-sizing:border-box; }
    body { font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin:0; padding:32px 18px; text-align:center; color:var(--ink); background:linear-gradient(135deg, rgba(26,82,118,.08), rgba(31,138,85,.10)), #f5f8f6; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    main { width:100%; max-width:720px; background:#fff; border:1px solid rgba(16,24,40,.08); border-radius:8px; padding:34px; box-shadow:0 18px 44px rgba(16,24,40,.12); }
    .status { display:inline-block; font-size:14px; font-weight:800; margin-bottom:18px; padding:8px 12px; border-radius:999px; letter-spacing:.04em; text-transform:uppercase; }
    .valid { color:#0f6b3c; background:#eaf8f0; border:1px solid #bfe8cf; }
    .invalid { color:#a32020; background:#fff3f3; border:1px solid #ffd2d2; }
    .details { background:#f7f9f5; border:1px solid var(--line); padding:24px; border-radius:8px; margin-top:20px; text-align:left; }
    .details h1 { color:var(--primary); margin-top:0; }
    a { color:#166b41; font-weight:700; text-decoration:none; }
  </style>
</head>
<body>
  <main>
  <?php if ($valid): ?>
    <div class="status valid">VALID CERTIFICATE</div>
    <div class="details">
      <h1><?= e($certificate['name']) ?></h1>
      <p>Certificate Reference: <?= e($certificate['certificate_ref']) ?></p>
      <p>Application Reference: <?= e($certificate['app_ref']) ?></p>
      <p>Issued: <?= e(date('F j, Y', strtotime((string) $certificate['issued_at']))) ?></p>
      <p>NATCODEV Coconut Outgrowers Program</p>
    </div>
  <?php else: ?>
    <div class="status invalid">Invalid Certificate</div>
    <p>No active certificate was found for this reference.</p>
    <?php if ($certificate && ($certificate['status'] ?? '') === 'revoked'): ?>
      <p>This certificate was revoked<?= $certificate['revoked_reason'] ? ': ' . e($certificate['revoked_reason']) : '.' ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <p style="margin-top:22px;"><a href="verify-certificate.php">Verify another certificate</a></p>
  </main>
</body>
</html>
