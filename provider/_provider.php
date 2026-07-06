<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../market/_market.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function provider_boot(): PDO
{
    $pdo = db();
    app_ensure_core_schema($pdo);
    pg_ensure_schema($pdo);
    marketplace_ensure_schema($pdo);
    foreach ([
        'platform_role' => "VARCHAR(60) NULL",
        'account_status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    app_add_column_if_missing($pdo, 'provider_registry', 'user_id', 'INT NULL');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS provider_accreditation_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            document_type VARCHAR(60) NOT NULL,
            document_number VARCHAR(120) NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(120) NULL,
            file_size INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            reviewer_id INT NULL,
            reviewer_notes TEXT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            UNIQUE KEY uniq_provider_accreditation_type (provider_id, document_type),
            INDEX idx_provider_accreditation_status (status, provider_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'provider_accreditation_documents');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS provider_accreditation_certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            user_id INT NULL,
            certificate_ref VARCHAR(100) NOT NULL UNIQUE,
            certificate_path VARCHAR(255) NULL,
            certificate_pdf_path VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'issued',
            issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verification_url VARCHAR(255) NULL,
            revoked_at DATETIME NULL,
            revoked_reason TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_provider_certificate_provider_id (provider_id),
            INDEX idx_provider_certificate_user_id (user_id),
            INDEX idx_provider_certificate_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'provider_accreditation_certificates');
    return $pdo;
}

function provider_accreditation_types(): array
{
    return [
        'cac' => 'Business Registration (CAC)',
        'tax_clearance' => 'Tax Clearance Certificate',
        'sector_license' => 'Input / Service Operating Licence',
        'quality_certificate' => 'Product or Service Quality Certificate',
        'insurance' => 'Insurance Certificate',
        'warehouse_inspection' => 'Warehouse / Storage Inspection',
        'identity' => 'Director or Authorized Representative ID',
    ];
}

function provider_accreditation_documents(PDO $pdo, int $providerId): array
{
    $stmt = $pdo->prepare("SELECT d.*, u.name reviewer_name FROM provider_accreditation_documents d LEFT JOIN users u ON u.id=d.reviewer_id WHERE d.provider_id = ? ORDER BY d.uploaded_at DESC");
    $stmt->execute([$providerId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(string) $row['document_type']] = $row;
    }
    return $rows;
}

function provider_accreditation_certificate_record(PDO $pdo, int $providerId): ?array
{
    if ($providerId <= 0 || !app_table_exists($pdo, 'provider_accreditation_certificates')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM provider_accreditation_certificates WHERE provider_id = ? ORDER BY issued_at DESC LIMIT 1");
    $stmt->execute([$providerId]);
    return $stmt->fetch() ?: null;
}

function provider_accreditation_certificate_by_ref(PDO $pdo, string $ref): ?array
{
    $ref = trim($ref);
    if ($ref === '' || !app_table_exists($pdo, 'provider_accreditation_certificates')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT c.*, pr.company_name, pr.contact_person FROM provider_accreditation_certificates c JOIN provider_registry pr ON pr.id = c.provider_id WHERE c.certificate_ref = ? LIMIT 1");
    $stmt->execute([$ref]);
    return $stmt->fetch() ?: null;
}

function provider_accreditation_certificate_issue(PDO $pdo, array $provider): array
{
    $providerId = (int) ($provider['id'] ?? 0);
    $required = count(provider_accreditation_types());
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM provider_accreditation_documents WHERE provider_id = ? AND status = 'approved'");
    $stmt->execute([$providerId]);
    if ((int) $stmt->fetchColumn() < $required) {
        throw new RuntimeException('All accreditation documents must be approved before issuing the certificate.');
    }

    $status = (string) ($provider['status'] ?? '');
    if (!in_array($status, ['approved', 'verified'], true)) {
        throw new RuntimeException('Provider accreditation must be fully approved before issuing the certificate.');
    }

    $existing = provider_accreditation_certificate_record($pdo, $providerId);
    $certificateRef = $existing['certificate_ref'] ?? provider_accreditation_certificate_ref($provider);
    $issuedAt = date('Y-m-d H:i:s');
    $pdf = provider_accreditation_certificate_pdf_document($provider, $certificateRef, $issuedAt);
    $fileName = provider_accreditation_certificate_filename($provider, $certificateRef);
    $pdfPath = 'certificates/provider/' . $fileName;
    $absolutePdf = dirname(__DIR__) . '/' . $pdfPath;
    if (!is_dir(dirname($absolutePdf)) && !mkdir(dirname($absolutePdf), 0755, true) && !is_dir(dirname($absolutePdf))) {
        throw new RuntimeException('Unable to create provider certificate storage directory.');
    }
    file_put_contents($absolutePdf, $pdf, LOCK_EX);

    $verificationUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($certificateRef);
    $userId = (int) ($provider['user_id'] ?? 0);

    $stmt = $pdo->prepare("INSERT INTO provider_accreditation_certificates (provider_id, user_id, certificate_ref, certificate_pdf_path, status, issued_at, verification_url) VALUES (?, ?, ?, ?, 'issued', ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), certificate_pdf_path = VALUES(certificate_pdf_path), status = VALUES(status), issued_at = VALUES(issued_at), verification_url = VALUES(verification_url)");
    $stmt->execute([$providerId, $userId, $certificateRef, $pdfPath, $issuedAt, $verificationUrl]);

    return provider_accreditation_certificate_record($pdo, $providerId) ?? [];
}

function provider_upload_accreditation_document(PDO $pdo, array $provider, array $file, string $type, string $number): void
{
    $types = provider_accreditation_types();
    if (!isset($types[$type])) {
        throw new RuntimeException('Select a valid accreditation document type.');
    }
    $info = app_uploaded_file_info(
        $file,
        ['pdf', 'jpg', 'jpeg', 'png'],
        10 * 1024 * 1024,
        $types[$type],
        ['application/pdf', 'image/jpeg', 'image/png']
    );
    $directory = dirname(__DIR__) . '/provider_uploads/accreditation';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Accreditation storage is unavailable.');
    }
    $filename = app_safe_upload_name('provider_' . (int) $provider['id'] . '_' . $type, (string) $info['name'], (string) $info['extension']);
    $relativePath = 'provider_uploads/accreditation/' . $filename;
    if (!move_uploaded_file((string) $info['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The accreditation document could not be saved.');
    }
    $stmt = $pdo->prepare("
        INSERT INTO provider_accreditation_documents
            (provider_id, document_type, document_number, original_name, stored_path, mime_type, file_size, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE document_number=VALUES(document_number), original_name=VALUES(original_name),
            stored_path=VALUES(stored_path), mime_type=VALUES(mime_type), file_size=VALUES(file_size),
            status='pending', reviewer_id=NULL, reviewer_notes=NULL, uploaded_at=CURRENT_TIMESTAMP, reviewed_at=NULL
    ");
    $stmt->execute([(int) $provider['id'], $type, $number, (string) $info['name'], $relativePath, (string) $info['type'], (int) $info['size']]);
    $pdo->prepare("UPDATE provider_registry SET status = 'pending_review', verified_by = NULL, verified_at = NULL WHERE id = ?")
        ->execute([(int) $provider['id']]);
}

function provider_accreditation_certificate_ref(array $provider): string
{
    // Produce a compact, unique certificate serial similar to other platform certificates.
    // Format: CERT-PROV-YYYYMMDD-<providerId>-<6HEX>
    $providerId = (int) ($provider['id'] ?? 0);
    $date = date('Ymd');
    $random = strtoupper(bin2hex(random_bytes(3)));
    return sprintf('CERT-PROV-%s-%d-%s', $date, $providerId, $random);
}

function provider_accreditation_certificate_filename(array $provider, string $certificateRef): string
{
    // Use a concise, consistent filename for provider accreditation certificates
    $providerId = (int) ($provider['id'] ?? 0);
    if ($providerId > 0) {
        return 'accreditation-certificate-' . $providerId . '.pdf';
    }
    return 'accreditation-certificate.pdf';
}

function provider_accreditation_certificate_pdf_document(array $provider, string $certificateRef, string $issuedAt): string
{
    $jpeg = provider_accreditation_certificate_render_jpeg($provider, $certificateRef, $issuedAt);
    return certificate_pdf_build($jpeg, 1684, 1190);
}

function provider_accreditation_certificate_render_jpeg(array $provider, string $certificateRef, string $issuedAt): string
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

    imagefilledrectangle($image, 0, 0, $width, $height, $paper);
    imagefilledrectangle($image, 88, 88, $width - 88, $height - 88, $cream);
    certificate_thick_rectangle($image, 92, 92, $width - 92, $height - 92, $green, 14);
    certificate_thick_rectangle($image, 134, 134, $width - 134, $height - 134, $gold, 3);
    imagefilledrectangle($image, 92, 92, $width - 92, 190, $leaf);
    imagefilledrectangle($image, 92, $height - 120, $width - 92, $height - 92, $leaf);

    certificate_draw_round_logo($image, 150, 150, 190, 190, $green, $gold, $ink);
    certificate_text($image, 'PROVIDER ACCREDITATION CERTIFICATE', $width / 2, 350, 34, $green, 'bold', 'center');
    certificate_text($image, 'NATCODEV Official Recognition', $width / 2, 396, 24, $gold, 'bold', 'center');
    certificate_text($image, 'This certifies that', $width / 2, 470, 26, $muted, 'regular', 'center');

    $companyName = trim((string) ($provider['company_name'] ?? $provider['contact_person'] ?? 'NATCODEV Provider'));
    certificate_text($image, $companyName, $width / 2, 560, 56, $ink, 'serif_bold', 'center');

    certificate_wrapped_text(
        $image,
        'has successfully completed the NATCODEV provider accreditation requirements and is recognized as an approved provider in the NATCODEV service and input ecosystem.',
        $width / 2,
        640,
        1180,
        28,
        38,
        $ink,
        'regular',
        'center'
    );

    certificate_detail_box($image, 316, 760, 520, 104, 'Certificate Reference', $certificateRef, $line, $cream, $gold, $green, $muted);
    certificate_detail_box($image, 856, 760, 340, 104, 'Date Issued', date('F j, Y', strtotime($issuedAt)), $line, $cream, $gold, $green, $muted);
    certificate_detail_box($image, 1206, 760, 300, 104, 'Status', 'Approved', $line, $cream, $gold, $green, $muted);

    $verifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($certificateRef);
    certificate_draw_qr($image, $verifyUrl, 188, 854, 170, $ink, $paper, $line);
    certificate_text($image, 'VERIFY REFERENCE', 263, 1040, 14, $green, 'bold', 'center');
    certificate_text($image, 'Issued by NATCODEV', $width / 2, 894, 22, $green, 'bold', 'center');
    certificate_draw_signature($image, 667, 848, $green, $gold, $ink, 350, 175);
    certificate_text($image, 'CHIEF OF PARTY', $width / 2, 1000, 18, $green, 'bold', 'center');
    certificate_text($image, 'Digitally issued by National Coconut Development & Propagation Initiative', $width / 2, 1028, 15, $muted, 'regular', 'center');
    certificate_draw_red_seal($image, 1280, 880, 168);

    ob_start();
    imagejpeg($image, null, 94);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    return $jpeg;
}

function certificate_draw_round_logo(GdImage $image, int $x, int $y, int $w, int $h, int $green, int $gold, int $ink): void
{
    $cx = $x + (int) round($w / 2);
    $cy = $y + (int) round($h / 2);
    $radius = min($w, $h);
    $innerSize = $radius - 34;
    $innerX = $x + (int) round(($w - $innerSize) / 2);
    $innerY = $y + (int) round(($h - $innerSize) / 2);

    imagefilledellipse($image, $cx, $cy, $radius, $radius, $green);
    imagefilledellipse($image, $cx, $cy, $radius - 14, $radius - 14, $gold);
    imagefilledellipse($image, $cx, $cy, $radius - 28, $radius - 28, $green);

    $logoPath = certificate_first_asset_path(['assets/logo/natcodev-logo.png', 'assets/logo/natcodev.jpeg']);
    if ($logoPath !== '' && certificate_copy_image_circle($image, $logoPath, $innerX, $innerY, $innerSize)) {
        return;
    }

    certificate_text($image, 'N', $cx, $cy - 10, 44, $gold, 'bold', 'center');
    certificate_text($image, 'NATCODEV', $cx, $cy + 38, 18, $gold, 'bold', 'center');
}

function certificate_copy_image_circle(GdImage $target, string $path, int $x, int $y, int $size): bool
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

    $scale = min($size / $sourceWidth, $size / $sourceHeight);
    $drawWidth = (int) round($sourceWidth * $scale);
    $drawHeight = (int) round($sourceHeight * $scale);

    $tmp = imagecreatetruecolor($drawWidth, $drawHeight);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
    imagefilledrectangle($tmp, 0, 0, $drawWidth, $drawHeight, $transparent);
    imagecopyresampled($tmp, $source, 0, 0, 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);

    $mask = imagecreatetruecolor($size, $size);
    imagealphablending($mask, false);
    imagesavealpha($mask, true);
    $transparentMask = imagecolorallocatealpha($mask, 0, 0, 0, 127);
    imagefilledrectangle($mask, 0, 0, $size, $size, $transparentMask);

    $radius = $size / 2;
    $center = $radius;
    $offsetX = (int) round(($size - $drawWidth) / 2);
    $offsetY = (int) round(($size - $drawHeight) / 2);

    for ($py = 0; $py < $size; $py++) {
        for ($px = 0; $px < $size; $px++) {
            $dx = $px - $center + 0.5;
            $dy = $py - $center + 0.5;
            if (sqrt($dx * $dx + $dy * $dy) <= $radius - 1) {
                $sourceX = $px - $offsetX;
                $sourceY = $py - $offsetY;
                if ($sourceX >= 0 && $sourceX < $drawWidth && $sourceY >= 0 && $sourceY < $drawHeight) {
                    $rgb = imagecolorat($tmp, $sourceX, $sourceY);
                    imagesetpixel($mask, $px, $py, $rgb);
                }
            }
        }
    }

    imagecopy($target, $mask, $x, $y, 0, 0, $size, $size);
    imagedestroy($mask);
    imagedestroy($tmp);
    imagedestroy($source);

    return true;
}

function provider_user(PDO $pdo): ?array
{
    return current_user($pdo);
}

function provider_require(PDO $pdo): array
{
    $user = provider_user($pdo);
    if (!$user) {
        redirect_to('login.php');
    }
    if (app_user_needs_email_verification($user)) {
        unset($_SESSION['user_id']);
        redirect_to('login.php?email=' . urlencode((string) ($user['email'] ?? '')));
    }
    // Provider account must confirm email before workspace access.

    return $user;
}

function provider_full_user(PDO $pdo, array $user): array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    return $stmt->fetch() ?: $user;
}

function provider_records(PDO $pdo, ?array $user): array
{
    if (!$user) {
        return [];
    }
    $email = (string) ($user['email'] ?? '');
    $stmt = $pdo->prepare("
        SELECT pr.*, COALESCE(oc.offerings, 0) offerings
        FROM provider_registry pr
        LEFT JOIN (
            SELECT provider_id, COUNT(*) offerings
            FROM provider_offerings
            WHERE status = 'active'
            GROUP BY provider_id
        ) oc ON oc.provider_id = pr.id
        WHERE pr.user_id = ? OR pr.email = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([(int) $user['id'], $email]);
    return $stmt->fetchAll();
}

function provider_active(PDO $pdo, ?array $user): ?array
{
    $providers = provider_records($pdo, $user);
    return $providers[0] ?? null;
}

function provider_status_label(?string $status): string
{
    return ucwords(str_replace('_', ' ', (string) ($status ?: 'pending_review')));
}

function provider_counts(PDO $pdo, ?array $provider, ?array $user): array
{
    $providerId = (int) ($provider['id'] ?? 0);
    $userId = (int) ($user['id'] ?? 0);
    $sellerIds = [];
    $counts = [
        'activeListings' => 0,
        'orders' => 0,
        'requests' => 0,
        'wallet' => 0.0,
        'coverageStates' => 0,
        'coverageLgas' => 0,
        'academy' => 0,
        'support' => 0,
    ];
    if ($providerId > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM provider_offerings WHERE provider_id = ? AND status = 'active'");
        $stmt->execute([$providerId]);
        $counts['activeListings'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT state_ids, lga_ids, nationwide FROM provider_registry WHERE id = ? LIMIT 1");
        $stmt->execute([$providerId]);
        $coverage = $stmt->fetch() ?: [];
        // If provider is nationwide, count all states and LGAs
        if ((int) ($coverage['nationwide'] ?? 0) === 1) {
            $counts['coverageStates'] = (int) $pdo->query('SELECT COUNT(*) FROM nigeria_states')->fetchColumn();
            $counts['coverageLgas'] = (int) $pdo->query('SELECT COUNT(*) FROM nigeria_lgas')->fetchColumn();
        } else {
            $counts['coverageStates'] = count(array_filter(array_unique(array_map('trim', explode(',', (string) ($coverage['state_ids'] ?? ''))))));
            $counts['coverageLgas'] = count(array_filter(array_unique(array_map('trim', explode(',', (string) ($coverage['lga_ids'] ?? ''))))));
        }
    }
    if ($user) {
        if ($userId > 0 && app_table_exists($pdo, 'marketplace_sellers')) {
            $stmt = $pdo->prepare("SELECT id FROM marketplace_sellers WHERE user_id = ?");
            $stmt->execute([$userId]);
            $sellerIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if (app_table_exists($pdo, 'marketplace_orders')) {
            if ($sellerIds) {
                $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE seller_id IN ($placeholders) AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
                $stmt->execute($sellerIds);
                $counts['orders'] = (int) $stmt->fetchColumn();
            }
        }
        if (app_table_exists($pdo, 'marketplace_inquiries')) {
            if ($sellerIds) {
                $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_inquiries WHERE seller_id IN ($placeholders) AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
                $stmt->execute($sellerIds);
                $counts['requests'] = (int) $stmt->fetchColumn();
            }
        }
        if (app_table_exists($pdo, 'wallets')) {
            $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? LIMIT 1");
            $stmt->execute([(int) $user['id']]);
            $counts['wallet'] = (float) ($stmt->fetchColumn() ?: 0);
        }
        if (app_table_exists($pdo, 'academy_enrollments')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_enrollments WHERE user_id = ?");
            $stmt->execute([(int) $user['id']]);
            $counts['academy'] = (int) $stmt->fetchColumn();
        }
        if (app_table_exists($pdo, 'support_tickets')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ?");
            $stmt->execute([(int) $user['id']]);
            $counts['support'] = (int) $stmt->fetchColumn();
        }
    }
    return $counts;
}

function provider_offerings(PDO $pdo, int $providerId, int $limit = 12): array
{
    if ($providerId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM provider_offerings WHERE provider_id = ? ORDER BY created_at DESC LIMIT " . max(1, min(80, $limit)));
    $stmt->execute([$providerId]);
    return $stmt->fetchAll();
}

function provider_nav(): array
{
    return [
        ['overview', 'Overview', 'fa-home', 'dashboard.php'],
        ['profile', 'Business Profile', 'fa-id-card', 'profile.php'],
        ['products', 'Products & Services', 'fa-screwdriver-wrench', 'products.php'],
        ['coverage', 'Coverage Areas', 'fa-map-location-dot', 'coverage.php'],
        ['orders', 'Orders & Requests', 'fa-cart-shopping', 'orders.php'],
        ['accreditation', 'Accreditation', 'fa-shield-halved', 'accreditation.php'],
        ['certificates', 'Certificates', 'fa-certificate', 'certificates.php'],
        ['wallet', 'Wallet', 'fa-wallet', 'wallet.php'],
        ['marketplace', 'Marketplace', 'fa-store', 'marketplace.php'],
        ['academy', 'NATCODEV Academy', 'fa-graduation-cap', 'academy.php'],
        ['reports', 'Reports', 'fa-chart-line', 'reports.php'],
        ['support', 'Support Desk', 'fa-headset', 'support.php'],
        ['settings', 'Settings', 'fa-gear', 'settings.php'],
    ];
}

function provider_page_start(string $title, string $active, ?array $user, ?array $provider, array $counts): void
{
    $logo = app_primary_logo_url();
    $name = (string) ($provider['company_name'] ?? $user['name'] ?? 'Provider Workspace');
    $owner = (string) ($user['name'] ?? $provider['contact_person'] ?? 'Business Owner');
    $status = provider_status_label((string) ($provider['status'] ?? 'pending_review'));
    $dashboardLinks = $user ? market_user_dashboard_links(db(), $user, 'provider') : [];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Provider</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#06451f;--green2:#0a7a3a;--mint:#eef8ef;--gold:#d89b10;--orange:#f79009;--blue:#2f72d8;--red:#d92d20;--ink:#101828;--muted:#667085;--line:#dfe8d8;--bg:#f7faf5;--shadow:0 16px 40px rgba(16,24,40,.08)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#06451f,#013417);color:#fff;padding:18px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;margin-bottom:24px}.brand img{width:58px;height:58px;border-radius:50%;background:#fff}.brand strong{font-size:1.42rem}.brand span span{display:block;font-size:.76rem}.provider-card{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:14px;margin-bottom:22px;display:flex;gap:12px;align-items:center}.avatar{width:54px;height:54px;border-radius:50%;background:#e8f6ec;color:var(--green);display:grid;place-items:center;font-weight:950}.provider-card small{display:block;color:#a6f2b7;font-weight:850}.nav{display:grid;gap:7px}.nav a{display:flex;align-items:center;gap:11px;color:#f3fff4;padding:11px 12px;border-radius:9px;font-weight:900}.nav a.active,.nav a:hover{background:linear-gradient(135deg,#118b42,#0d6b34)}.side-cta{margin-top:24px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:16px}.main{min-width:0}.top{height:70px;background:#fff;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:10}.search{max-width:580px;flex:1;position:relative}.search input{width:100%;border:1px solid var(--line);border-radius:10px;padding:13px 42px}.search i{position:absolute;left:14px;top:14px;color:var(--muted)}.top-actions{display:flex;gap:18px;align-items:center;font-weight:850}.content{padding:24px 28px}.page-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:18px}.page-head h1{font-size:2rem;margin:0;color:#08122b}.page-head p{margin:5px 0 0;color:#344054}.btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;border:1px solid var(--green);border-radius:9px;background:var(--green);color:#fff;font-weight:950;padding:10px 14px}.btn.light{background:#fff;color:var(--green)}.btn.warn{background:#fff7e6;color:#9a6500;border-color:#f3d391}.kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:18px}.kpi{background:#fff;border:1px solid var(--line);border-radius:12px;padding:17px;box-shadow:var(--shadow);display:flex;gap:14px;align-items:center}.kpi i{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:var(--green);font-size:1.35rem}.kpi b{font-size:1.35rem}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px;box-shadow:var(--shadow)}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-7{grid-column:span 7}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.card h2,.card h3{margin:0;color:#08122b}.view{color:var(--green);font-weight:950}.badge{display:inline-flex;gap:6px;align-items:center;border-radius:999px;background:#e8f6ec;color:var(--green);font-size:.78rem;font-weight:950;padding:5px 9px}.badge.warn{background:#fff3d6;color:#9a6500}.badge.red{background:#fff1f2;color:#b42318}.list{display:grid;gap:10px}.row{display:flex;justify-content:space-between;gap:12px;align-items:center;border-top:1px solid var(--line);padding-top:10px}.row:first-child{border-top:0;padding-top:0}.thumb{width:64px;height:56px;object-fit:cover;border-radius:9px}.action-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:12px}.quick{border:1px solid var(--line);border-radius:10px;padding:14px;text-align:center;background:#fbfdf9;font-weight:900}.quick i{display:grid;place-items:center;width:48px;height:48px;border-radius:50%;background:#e8f6ec;color:var(--green);font-size:1.3rem;margin:0 auto 8px}label{display:block;font-weight:850}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:12px;margin-top:6px}textarea{min-height:110px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.wide{grid-column:1/-1}.notice{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-weight:850}.ok{background:#e8f6ec;color:var(--green)}.err{background:#fff1f2;color:#b42318}.hero-card{background:linear-gradient(90deg,rgba(255,255,255,.97),rgba(255,255,255,.78)),url("../assets/public/provider-commerce-hero.png") center/cover;border:1px solid var(--line);border-radius:14px;padding:24px;min-height:210px}.footer{display:flex;justify-content:space-between;gap:20px;color:#667085;font-size:.9rem;padding:18px 28px}
    @media(max-width:1250px){.shell{grid-template-columns:1fr}.side{position:relative;height:auto}.kpis{grid-template-columns:repeat(2,1fr)}.span-3,.span-4,.span-5,.span-6,.span-7,.span-8{grid-column:span 12}.action-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.top,.page-head,.footer{align-items:flex-start;flex-direction:column;height:auto;padding:14px}.kpis,.form-grid,.action-grid{grid-template-columns:1fr}.content{padding:18px}}
  </style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><span>Provider Console</span></span></a>
    <div class="provider-card"><span class="avatar"><?= e(market_initials($name)) ?></span><span><strong><?= e($name) ?></strong><small><?= e($status) ?></small><small><?= e($owner) ?></small></span></div>
    <nav class="nav">
      <?php foreach (provider_nav() as $item): ?>
        <a class="<?= $active === $item[0] ? 'active' : '' ?>" href="<?= e($item[3]) ?>"><i class="fas <?= e($item[2]) ?>"></i><?= e($item[1]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-cta"><strong>Role Access</strong><p>Move between the dashboards approved for this account.</p><?php foreach ($dashboardLinks as $dash): ?><a class="btn light" style="margin:6px 6px 0 0" href="<?= e((string) $dash['href']) ?>"><?= e((string) $dash['label']) ?></a><?php endforeach; ?></div>
  </aside>
  <main class="main">
    <header class="top">
      <form class="search" action="search.php" method="get"><i class="fas fa-search"></i><input name="q" placeholder="Search orders, requests, products, customers..."></form>
      <div class="top-actions"><a href="../market/index.php"><i class="fas fa-store"></i> Marketplace</a><a href="support.php"><i class="fas fa-envelope"></i> Support</a><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="settings.php"><i class="fas fa-gear"></i> Settings</a><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></div>
    </header>
    <section class="content">
<?php
}

function provider_page_end(): void
{
    ?>
    </section>
    <footer class="footer"><span>&copy; <?= e(date('Y')) ?> NATCODEV. All rights reserved.</span><span>Provider Console v2.1.0</span><span>Last login: <?= e(date('M j, Y h:i A')) ?></span></footer>
  </main>
</div>
<script src="../lib/location-picker.js"></script>
</body>
</html>
<?php
}

function provider_simple_page(string $active, string $title, string $intro, callable $body): void
{
    $pdo = provider_boot();
    $user = provider_full_user($pdo, provider_require($pdo));
    $provider = provider_active($pdo, $user);
    $counts = provider_counts($pdo, $provider, $user);
    provider_page_start($title, $active, $user, $provider, $counts);
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1><p>' . e($intro) . '</p></div></div>';
    if (!$provider) {
        echo '<div class="notice err">No provider profile is linked to this account yet. Complete provider registration first.</div><a class="btn" href="index.php">Register Provider Profile</a>';
    } else {
        $body($pdo, $user, $provider, $counts);
    }
    provider_page_end();
}

