<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/notification-dispatch.php';

function certificate_generate_ref(string $appRef): string
{
    return 'CERT-' . preg_replace('/[^A-Z0-9-]/i', '', strtoupper($appRef)) . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function canIssueCertificate(int $userId, PDO $pdo): bool
{
    app_ensure_core_schema($pdo);
    app_ensure_certificate_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT a.id, a.confirmed
        FROM users u
        JOIN applications a ON a.id = u.application_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $app = $stmt->fetch();
    if (!$app || (int) $app['confirmed'] !== 1) {
        return false;
    }

    if (!app_table_exists($pdo, 'document_requirements')) {
        return true;
    }

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM document_requirements WHERE user_id = ?");
    $totalStmt->execute([$userId]);
    $total = (int) $totalStmt->fetchColumn();
    if ($total === 0) {
        return true;
    }

    $pendingStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM document_requirements
        WHERE user_id = ? AND verification_status <> 'verified'
    ");
    $pendingStmt->execute([$userId]);
    return (int) $pendingStmt->fetchColumn() === 0;
}

function generateCertificate(int $applicationId, int $userId, PDO $pdo): array
{
    app_ensure_core_schema($pdo);
    app_ensure_certificate_schema($pdo);

    $existing = $pdo->prepare("
        SELECT *
        FROM certificates
        WHERE application_id = ? AND status = 'issued'
        ORDER BY issued_at DESC
        LIMIT 1
    ");
    $existing->execute([$applicationId]);
    $certificate = $existing->fetch();
    if ($certificate) {
        if (empty($certificate['certificate_ref'])) {
            $certRef = $certificate['qr_code_hash'] ?: certificate_generate_ref('NAT-' . $applicationId);
            $verifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($certRef);
            $pdo->prepare("
                UPDATE certificates
                SET certificate_ref = ?, qr_code_hash = COALESCE(qr_code_hash, ?), verification_url = COALESCE(verification_url, ?)
                WHERE id = ?
            ")->execute([$certRef, $certRef, $verifyUrl, $certificate['id']]);
            $certificate['certificate_ref'] = $certRef;
            $certificate['qr_code_hash'] = $certificate['qr_code_hash'] ?: $certRef;
            $certificate['verification_url'] = $certificate['verification_url'] ?: $verifyUrl;
        }
        return $certificate;
    }

    $stmt = $pdo->prepare("
        SELECT a.id, a.app_ref, a.name, a.location, a.farm_size, a.confirmed, u.email
        FROM applications a
        LEFT JOIN users u ON u.id = ?
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $applicationId]);
    $app = $stmt->fetch();

    if (!$app || (int) $app['confirmed'] !== 1) {
        throw new RuntimeException('Certificate can only be issued for confirmed applications.');
    }

    $certRef = certificate_generate_ref((string) $app['app_ref']);
    $fileName = strtolower($certRef) . '.html';
    $directory = dirname(__DIR__) . '/certificates';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $relativePath = 'certificates/' . $fileName;
    $pdfRelativePath = 'certificates/' . strtolower($certRef) . '.pdf';
    $verifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($certRef);
    $issuedAt = date('Y-m-d H:i:s');
    $html = certificate_render_html($app, $certRef, $issuedAt, $verifyUrl);
    file_put_contents($directory . '/' . $fileName, $html, LOCK_EX);

    $pdf = certificate_pdf_document([
        'display_ref' => $certRef,
        'certificate_ref' => $certRef,
        'issued_at' => $issuedAt,
        'verification_url' => $verifyUrl,
        'app_ref' => $app['app_ref'],
        'name' => $app['name'],
        'location' => $app['location'],
        'farm_size' => $app['farm_size'],
    ]);
    file_put_contents(dirname(__DIR__) . '/' . $pdfRelativePath, $pdf, LOCK_EX);

    $insert = $pdo->prepare("
        INSERT INTO certificates (certificate_ref, application_id, user_id, certificate_path, certificate_pdf_path, status, issued_at, qr_code_hash, verification_url)
        VALUES (?, ?, ?, ?, ?, 'issued', ?, ?, ?)
    ");
    $insert->execute([$certRef, $applicationId, $userId, $relativePath, $pdfRelativePath, $issuedAt, $certRef, $verifyUrl]);

    $fetch = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
    $fetch->execute([(int) $pdo->lastInsertId()]);
    $certificate = $fetch->fetch();

    natcodev_notify_user($pdo, $userId, 'certificate_issued', 'NATCODEV Certificate Issued', [
        'certificate_ref' => $certRef,
        'verification_url' => $verifyUrl,
        'certificate_url' => app_base_url() . '/' . $relativePath,
        'name' => $app['name'],
    ], "Your NATCODEV certificate {$certRef} has been issued. Verify: {$verifyUrl}");

    return $certificate;
}

function certificate_render_html(array $app, string $certRef, string $issuedAt, string $verifyUrl): string
{
    $issuedDate = date('F j, Y', strtotime($issuedAt));
    $qr = certificate_qr_svg($verifyUrl, 17);
    $barcode = certificate_barcode_html($certRef);
    $logoSrc = certificate_asset_src(['assets/logo/natcodev.jpeg', 'assets/logo/natcodev-logo.png'], 'assets/logo/natcodev-logo.svg');
    $fmardSrc = certificate_asset_src(['assets/seals/fmard-logo.png', 'assets/seals/fmaf.png'], 'assets/seals/fmaf.svg');
    $naicSrc = certificate_asset_src(['assets/seals/naic.png'], 'assets/seals/naic.svg');
    $nirsalSrc = certificate_asset_src(['assets/seals/nirsal.jpeg', 'assets/seals/nisral.png'], 'assets/seals/nisral.svg');
    $boaSrc = certificate_asset_src(['assets/seals/boa.png'], 'assets/seals/boa.svg');
    $lcfeSrc = certificate_asset_src(['assets/seals/lc_fe.jpg'], 'assets/seals/fmaf.svg');

    return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NATCODEV Certificate ' . e($certRef) . '</title>
  <style>
    :root { --green:#2d5016; --leaf:#14733a; --gold:#c9a227; --ink:#172211; --muted:#66715f; }
    * { box-sizing:border-box; }
    body { margin:0; background:#eef4e9; color:var(--ink); font-family:Arial, Helvetica, sans-serif; padding:26px; }
    main { position:relative; max-width:1120px; min-height:760px; margin:0 auto; overflow:hidden; background:#fffdf7; border:10px solid var(--green); box-shadow:0 24px 70px rgba(20,45,10,.18); }
    main:before { content:""; position:absolute; inset:24px; border:2px solid var(--gold); pointer-events:none; }
    main:after { content:"NATCODEV"; position:absolute; inset:auto 0 210px 0; text-align:center; font:800 118px Arial, sans-serif; letter-spacing:10px; color:rgba(45,80,22,.045); pointer-events:none; }
    .bar { height:18px; background:linear-gradient(90deg,var(--green),var(--leaf),var(--gold),var(--leaf),var(--green)); }
    .content { position:relative; z-index:1; padding:34px 58px 42px; text-align:center; }
    .top { display:flex; align-items:center; justify-content:space-between; gap:22px; margin-bottom:18px; }
    .logo { width:250px; max-width:36%; }
    .refbox { text-align:right; font-size:12px; color:var(--muted); line-height:1.6; }
    .eyebrow { color:var(--gold); letter-spacing:4px; font-weight:800; font-size:13px; text-transform:uppercase; margin-top:8px; }
    h1 { color:var(--green); font-family:Georgia, "Times New Roman", serif; font-size:50px; line-height:1.05; margin:10px 0 18px; }
    .intro { color:var(--muted); margin:0; font-size:17px; }
    .name { color:#111b0d; font-family:Georgia, "Times New Roman", serif; font-size:48px; font-weight:700; margin:22px auto 12px; padding-bottom:10px; max-width:760px; border-bottom:2px solid var(--gold); }
    .statement { font-size:18px; line-height:1.7; max-width:790px; margin:0 auto 24px; }
    .details { display:grid; grid-template-columns:1fr 1fr; gap:14px; max-width:760px; margin:28px auto; }
    .detail { border:1px solid #e2dcc8; border-top:4px solid var(--gold); background:#fffaf0; padding:15px; min-height:74px; }
    .label { display:block; color:var(--muted); font-size:11px; letter-spacing:1.4px; text-transform:uppercase; margin-bottom:7px; }
    .value { color:var(--green); font-weight:800; }
    .seals { display:flex; align-items:center; justify-content:center; gap:12px; margin:18px 0 16px; flex-wrap:wrap; }
    .seal { width:112px; height:72px; object-fit:contain; background:#fff; border:1px solid #e4e0d2; border-radius:8px; padding:7px; filter:drop-shadow(0 8px 12px rgba(45,80,22,.10)); }
    .verification { display:grid; grid-template-columns:150px 1fr 190px; align-items:end; gap:30px; margin-top:22px; text-align:left; }
    .qr svg { width:112px; height:112px; background:#fff; border:6px solid #fff; box-shadow:0 0 0 1px #d9ddcf; }
    .verifytext { font-size:11px; color:var(--muted); word-break:break-all; margin-top:8px; }
    .signature { text-align:center; }
    .signature img { width:310px; max-width:100%; display:block; margin:0 auto 4px; }
    .sigline { border-top:1px solid #1f2d18; margin-top:2px; padding-top:8px; font-size:12px; font-weight:800; color:var(--green); letter-spacing:1.2px; }
    .barcode { text-align:right; }
    .bars { display:flex; justify-content:flex-end; align-items:end; gap:2px; height:54px; margin-bottom:8px; }
    .bars span { display:block; background:#172211; }
    .fine { font-size:10px; color:var(--muted); letter-spacing:.8px; }
    @media print { body { background:#fff; padding:0; } main { width:100%; min-height:100vh; box-shadow:none; } }
    @media (max-width:760px) { body { padding:10px; } .content { padding:22px; } .top,.verification { grid-template-columns:1fr; display:grid; text-align:center; } .refbox,.barcode { text-align:center; } .logo { max-width:260px; width:80%; margin:auto; } .details { grid-template-columns:1fr; } h1 { font-size:32px; } .name { font-size:34px; } .seals { flex-wrap:wrap; } }
  </style>
</head>
<body>
  <main>
    <div class="bar"></div>
    <div class="content">
      <div class="top">
        <img class="logo" src="' . e($logoSrc) . '" alt="NATCODEV">
        <div class="refbox">
          Certificate Reference<br><strong>' . e($certRef) . '</strong><br>
          Issued ' . e($issuedDate) . '
        </div>
      </div>
      <div class="eyebrow">Official Grower Credential</div>
      <h1>Certificate of Participation</h1>
      <p class="intro">This certifies that</p>
      <div class="name">' . e($app['name']) . '</div>
      <p class="statement">has been duly confirmed as a participant in the NATCODEV Coconut Outgrowers Program and is recognized for verified engagement in the grower development pathway.</p>
      <div class="details">
        <div class="detail"><span class="label">Application Ref</span><span class="value">' . e($app['app_ref']) . '</span></div>
        <div class="detail"><span class="label">Farm Location</span><span class="value">' . e($app['location']) . '</span></div>
      </div>
      <div class="seals" aria-label="Program partner seals">
        <img class="seal" src="' . e($fmardSrc) . '" alt="FMARD seal">
        <img class="seal" src="' . e($naicSrc) . '" alt="NAIC seal">
        <img class="seal" src="' . e($nirsalSrc) . '" alt="NIRSAL seal">
        <img class="seal" src="' . e($boaSrc) . '" alt="BOA seal">
        <img class="seal" src="' . e($lcfeSrc) . '" alt="LCFE seal">
      </div>
      <div class="verification">
        <div class="qr">' . $qr . '<div class="verifytext">' . e($verifyUrl) . '</div></div>
        <div class="signature">
          <img src="../assets/signatures/chief-of-party.svg" alt="NATCODEV Chief of Party signature">
          <div class="sigline">NATCODEV CHIEF OF PARTY</div>
          <div class="fine">Digitally issued and verifiable online</div>
        </div>
        <div class="barcode">' . $barcode . '<div class="fine">' . e($certRef) . '</div></div>
      </div>
    </div>
    <div class="bar"></div>
  </main>
</body>
</html>';
}

function certificate_asset_src(array $preferredRelativePaths, string $fallbackRelativePath): string
{
    $path = $fallbackRelativePath;

    foreach ($preferredRelativePaths as $preferredRelativePath) {
        $preferredAbsolute = dirname(__DIR__) . '/' . ltrim($preferredRelativePath, '/');
        if (is_file($preferredAbsolute) && filesize($preferredAbsolute) > 0) {
            $path = $preferredRelativePath;
            break;
        }
    }

    return '../' . ltrim($path, '/');
}

function certificate_qr_svg(string $value, int $cells = 17): string
{
    $hash = hash('sha256', $value);
    $cellSize = 5;
    $size = $cells * $cellSize;
    $rects = '';

    for ($y = 0; $y < $cells; $y++) {
        for ($x = 0; $x < $cells; $x++) {
            $finder = ($x < 5 && $y < 5) || ($x >= $cells - 5 && $y < 5) || ($x < 5 && $y >= $cells - 5);
            $innerFinder = ($x > 0 && $x < 4 && $y > 0 && $y < 4)
                || ($x > $cells - 5 && $x < $cells - 1 && $y > 0 && $y < 4)
                || ($x > 0 && $x < 4 && $y > $cells - 5 && $y < $cells - 1);
            $index = ($x + $y * $cells) % strlen($hash);
            $active = $finder ? !$innerFinder : (hexdec($hash[$index]) + $x + ($y * 3)) % 3 === 0;

            if ($active) {
                $rects .= '<rect x="' . ($x * $cellSize) . '" y="' . ($y * $cellSize) . '" width="' . $cellSize . '" height="' . $cellSize . '"/>';
            }
        }
    }

    return '<svg viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="Certificate verification QR code" xmlns="http://www.w3.org/2000/svg"><rect width="' . $size . '" height="' . $size . '" fill="#fff"/><g fill="#172211">' . $rects . '</g></svg>';
}

function certificate_barcode_html(string $value): string
{
    $bars = '';
    $hash = hash('sha256', $value);
    for ($i = 0; $i < 42; $i++) {
        $digit = hexdec($hash[$i % strlen($hash)]);
        $width = 1 + ($digit % 3);
        $height = 24 + (($digit + $i) % 28);
        $bars .= '<span style="width:' . $width . 'px;height:' . $height . 'px"></span>';
    }

    return '<div class="bars" aria-label="Certificate barcode">' . $bars . '</div>';
}

function findCertificate(string $ref, PDO $pdo): ?array
{
    app_ensure_certificate_schema($pdo);
    $stmt = $pdo->prepare("
        SELECT COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) certificate_ref,
               COALESCE(c.status, 'issued') status,
               c.issued_at, c.revoked_at, c.revoked_reason, c.qr_code_hash, c.verification_url,
               a.app_ref, a.name, a.location, a.farm_size
        FROM certificates c
        JOIN applications a ON a.id = c.application_id
        WHERE c.certificate_ref = ? OR c.qr_code_hash = ? OR a.app_ref = ?
        ORDER BY c.issued_at DESC
        LIMIT 1
    ");
    $stmt->execute([$ref, $ref, $ref]);
    $certificate = $stmt->fetch();
    return $certificate ?: null;
}

function certificate_pdf_document(array $certificate): string
{
    $issuedAt = !empty($certificate['issued_at'])
        ? date('F j, Y', strtotime((string) $certificate['issued_at']))
        : date('F j, Y');
    $verifyUrl = (string) ($certificate['verification_url'] ?: app_base_url() . '/verify-certificate.php?ref=' . urlencode((string) $certificate['display_ref']));
    $jpeg = certificate_pdf_render_jpeg($certificate, $issuedAt, $verifyUrl);
    return certificate_pdf_build($jpeg, 1684, 1190);
}

function certificate_pdf_render_jpeg(array $certificate, string $issuedAt, string $verifyUrl): string
{
    $width = 1684;
    $height = 1190;
    $image = imagecreatetruecolor($width, $height);
    imageantialias($image, true);

    $cream = certificate_color($image, '#fffdf7');
    $paper = certificate_color($image, '#f7fbf3');
    $green = certificate_color($image, '#2d5016');
    $leaf = certificate_color($image, '#14733a');
    $gold = certificate_color($image, '#c9a227');
    $ink = certificate_color($image, '#172211');
    $muted = certificate_color($image, '#66715f');
    $line = certificate_color($image, '#e2dcc8');
    $white = certificate_color($image, '#ffffff');

    imagefilledrectangle($image, 0, 0, $width, $height, $paper);
    imagefilledrectangle($image, 88, 82, $width - 88, $height - 82, $cream);
    certificate_thick_rectangle($image, 92, 86, $width - 92, $height - 86, $green, 14);
    certificate_thick_rectangle($image, 134, 128, $width - 134, $height - 128, $gold, 3);
    imagefilledrectangle($image, 92, 86, $width - 92, 124, $leaf);
    imagefilledrectangle($image, 92, $height - 124, $width - 92, $height - 86, $leaf);

    certificate_draw_logo($image, 165, 145, 250, 170, $green, $gold, $ink);
    certificate_text($image, 'Certificate Reference', 1220, 170, 20, $muted, 'regular', 'right');
    certificate_text($image, (string) $certificate['display_ref'], 1220, 202, 24, $green, 'bold', 'right');
    certificate_text($image, 'Issued ' . $issuedAt, 1220, 236, 20, $muted, 'regular', 'right');

    certificate_text($image, 'OFFICIAL GROWER CREDENTIAL', $width / 2, 310, 22, $gold, 'bold', 'center');
    certificate_text($image, 'Certificate of Participation', $width / 2, 385, 66, $green, 'serif_bold', 'center');
    certificate_text($image, 'This certifies that', $width / 2, 455, 30, $muted, 'regular', 'center');
    certificate_text($image, (string) $certificate['name'], $width / 2, 540, 68, $ink, 'serif_bold', 'center');
    imageline($image, 430, 570, 1254, 570, $gold);
    imagesetthickness($image, 2);
    imageline($image, 430, 574, 1254, 574, $gold);
    imagesetthickness($image, 1);

    certificate_text($image, 'has been duly confirmed as a participant in the NATCODEV Coconut Outgrowers Program', $width / 2, 638, 28, $ink, 'regular', 'center');
    certificate_text($image, 'and is recognized for verified engagement in the grower development pathway.', $width / 2, 680, 28, $ink, 'regular', 'center');

    certificate_detail_box($image, 438, 732, 360, 92, 'APPLICATION REF', (string) $certificate['app_ref'], $line, $cream, $gold, $green, $muted);
    certificate_detail_box($image, 886, 732, 360, 92, 'FARM LOCATION', (string) $certificate['location'], $line, $cream, $gold, $green, $muted);

    certificate_draw_partner_logo($image, ['assets/seals/fmard-logo.png', 'assets/seals/fmaf.png'], 'FMARD', 406, 842, 166, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/naic.png'], 'NAIC', 586, 842, 134, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/nirsal.jpeg', 'assets/seals/nisral.png'], 'NIRSAL', 734, 842, 166, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/boa.png'], 'BOA', 914, 842, 134, 94, $line, $white, $green);
    certificate_draw_partner_logo($image, ['assets/seals/lc_fe.jpg'], 'LCFE', 1062, 842, 166, 94, $line, $white, $green);

    certificate_draw_qr($image, $verifyUrl, 188, 870, 150, $ink, $white, $line);
    certificate_text($image, 'VERIFY ONLINE', 263, 1048, 16, $green, 'bold', 'center');
    certificate_text($image, $verifyUrl, 263, 1074, 12, $muted, 'regular', 'center');

    certificate_draw_signature($image, 610, 975, $green, $gold, $ink);
    certificate_text($image, 'NATCODEV CHIEF OF PARTY', $width / 2, 1050, 18, $green, 'bold', 'center');
    certificate_text($image, 'Digitally issued and verifiable online', $width / 2, 1078, 15, $muted, 'regular', 'center');

    certificate_draw_barcode($image, (string) $certificate['display_ref'], 1270, 910, $ink);
    certificate_text($image, (string) $certificate['display_ref'], 1390, 1048, 13, $muted, 'regular', 'center');

    ob_start();
    imagejpeg($image, null, 94);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    return $jpeg;
}

function certificate_color(GdImage $image, string $hex): int
{
    $hex = ltrim($hex, '#');
    return imagecolorallocate($image, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function certificate_font(string $style): string
{
    $fonts = [
        'regular' => 'C:/Windows/Fonts/arial.ttf',
        'bold' => 'C:/Windows/Fonts/arialbd.ttf',
        'serif_bold' => 'C:/Windows/Fonts/georgiab.ttf',
        'script' => 'C:/Windows/Fonts/segoesc.ttf',
    ];

    return is_file($fonts[$style] ?? '') ? $fonts[$style] : ($fonts['regular']);
}

function certificate_text(GdImage $image, string $text, float $x, float $y, int $size, int $color, string $fontStyle = 'regular', string $align = 'left'): void
{
    $text = certificate_pdf_safe_text($text);
    $font = certificate_font($fontStyle);
    $box = imagettfbbox($size, 0, $font, $text);
    $textWidth = $box ? abs($box[2] - $box[0]) : strlen($text) * $size;

    if ($align === 'center') {
        $x -= $textWidth / 2;
    } elseif ($align === 'right') {
        $x -= $textWidth;
    }

    imagettftext($image, $size, 0, (int) round($x), (int) round($y), $color, $font, $text);
}

function certificate_detail_box(GdImage $image, int $x, int $y, int $w, int $h, string $label, string $value, int $line, int $fill, int $gold, int $green, int $muted): void
{
    imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $fill);
    imagerectangle($image, $x, $y, $x + $w, $y + $h, $line);
    imagefilledrectangle($image, $x, $y, $x + $w, $y + 7, $gold);
    certificate_text($image, $label, $x + ($w / 2), $y + 34, 15, $muted, 'bold', 'center');
    certificate_text($image, $value, $x + ($w / 2), $y + 70, 22, $green, 'bold', 'center');
}

function certificate_thick_rectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness): void
{
    imagesetthickness($image, $thickness);
    imagerectangle($image, $x1, $y1, $x2, $y2, $color);
    imagesetthickness($image, 1);
}

function certificate_draw_logo(GdImage $image, int $x, int $y, int $w, int $h, int $green, int $gold, int $ink): void
{
    if (certificate_copy_image_contain($image, certificate_first_asset_path(['assets/logo/natcodev.jpeg', 'assets/logo/natcodev-logo.png']), $x, $y, $w, $h)) {
        return;
    }

    imagefilledellipse($image, $x + 58, $y + 58, 92, 92, $green);
    imagefilledellipse($image, $x + 74, $y + 45, 22, 60, $gold);
    imagefilledellipse($image, $x + 52, $y + 70, 44, 18, certificate_color($image, '#ffffff'));
    certificate_text($image, 'NATCODEV', $x + 130, $y + 55, 40, $green, 'bold');
    certificate_text($image, 'COCONUT OUTGROWERS PROGRAM', $x + 134, $y + 88, 15, $ink, 'regular');
}

function certificate_draw_partner_logo(GdImage $image, array $relativePaths, string $label, int $x, int $y, int $w, int $h, int $line, int $white, int $green): void
{
    imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $white);
    imagerectangle($image, $x, $y, $x + $w, $y + $h, $line);

    if (certificate_copy_image_contain($image, certificate_first_asset_path($relativePaths), $x + 10, $y + 8, $w - 20, $h - 16)) {
        return;
    }

    certificate_text($image, $label, $x + ($w / 2), $y + 55, 18, $green, 'bold', 'center');
}

function certificate_first_asset_path(array $relativePaths): string
{
    foreach ($relativePaths as $relativePath) {
        $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }
    }

    return '';
}

function certificate_copy_image_contain(GdImage $target, string $path, int $x, int $y, int $w, int $h): bool
{
    $source = certificate_load_image($path);
    if (!$source instanceof GdImage) {
        return false;
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($source);
        return false;
    }

    $scale = min($w / $sourceWidth, $h / $sourceHeight);
    $drawWidth = (int) round($sourceWidth * $scale);
    $drawHeight = (int) round($sourceHeight * $scale);
    $drawX = $x + (int) round(($w - $drawWidth) / 2);
    $drawY = $y + (int) round(($h - $drawHeight) / 2);

    imagealphablending($target, true);
    imagecopyresampled($target, $source, $drawX, $drawY, 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);
    imagedestroy($source);
    return true;
}

function certificate_load_image(string $path): ?GdImage
{
    if ($path === '' || !is_file($path) || filesize($path) === 0) {
        return null;
    }

    $info = @getimagesize($path);
    $type = is_array($info) ? ($info[2] ?? null) : null;
    if ($type === IMAGETYPE_PNG) {
        $source = @imagecreatefrompng($path);
    } elseif ($type === IMAGETYPE_JPEG) {
        $source = @imagecreatefromjpeg($path);
    } else {
        $source = false;
    }

    if (!$source instanceof GdImage) {
        return null;
    }

    return $source;
}

function certificate_draw_signature(GdImage $image, int $x, int $y, int $green, int $gold, int $ink): void
{
    $script = certificate_font('script');
    certificate_text($image, 'NATCODEV', $x + 232, $y + 26, 44, $green, is_file($script) ? 'script' : 'serif_bold', 'center');
    imagesetthickness($image, 4);
    imageline($image, $x + 60, $y + 48, $x + 405, $y + 34, $gold);
    imagesetthickness($image, 2);
    imageline($image, $x + 55, $y + 58, $x + 410, $y + 58, $ink);
    imagesetthickness($image, 1);
}

function certificate_draw_barcode(GdImage $image, string $value, int $x, int $y, int $ink): void
{
    $hash = hash('sha256', $value);
    $cursor = $x;

    for ($i = 0; $i < 48; $i++) {
        $digit = hexdec($hash[$i % strlen($hash)]);
        $barWidth = 2 + ($digit % 5);
        $barHeight = 45 + (($digit + $i) % 55);
        imagefilledrectangle($image, $cursor, $y + (100 - $barHeight), $cursor + $barWidth, $y + 100, $ink);
        $cursor += $barWidth + 3;
    }
}

function certificate_draw_qr(GdImage $image, string $value, int $x, int $y, int $size, int $ink, int $white, int $line): void
{
    imagefilledrectangle($image, $x, $y, $x + $size, $y + $size, $white);
    imagerectangle($image, $x, $y, $x + $size, $y + $size, $line);
    $cells = 17;
    $cell = (int) floor($size / $cells);
    $hash = hash('sha256', $value);

    for ($row = 0; $row < $cells; $row++) {
        for ($col = 0; $col < $cells; $col++) {
            $finder = ($col < 5 && $row < 5) || ($col >= $cells - 5 && $row < 5) || ($col < 5 && $row >= $cells - 5);
            $innerFinder = ($col > 0 && $col < 4 && $row > 0 && $row < 4)
                || ($col > $cells - 5 && $col < $cells - 1 && $row > 0 && $row < 4)
                || ($col > 0 && $col < 4 && $row > $cells - 5 && $row < $cells - 1);
            $index = ($col + $row * $cells) % strlen($hash);
            $active = $finder ? !$innerFinder : (hexdec($hash[$index]) + $col + ($row * 3)) % 3 === 0;

            if ($active) {
                $rx = $x + 4 + ($col * $cell);
                $ry = $y + 4 + ($row * $cell);
                imagefilledrectangle($image, $rx, $ry, $rx + $cell - 1, $ry + $cell - 1, $ink);
            }
        }
    }
}

function certificate_pdf_safe_text(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    $text = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

function certificate_pdf_build(string $jpeg, int $imageWidth, int $imageHeight): string
{
    $content = "q\n842 0 0 595 0 0 cm\n/Im1 Do\nQ\n";
    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /XObject << /Im1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
        "4 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imageWidth} /Height {$imageHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream\nendobj\n",
        "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";

    return $pdf;
}

