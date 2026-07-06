<?php
declare(strict_types=1);

function academy_certificate_ref(int $userId, int $courseId): string
{
    return 'NAT-ACAD-' . $userId . '-' . $courseId . '-' . strtoupper(bin2hex(random_bytes(3)));
}
function academy_group_certificate_ref(int $userId, int $groupId): string
{
    return 'NAT-ACAD-GRP-' . $userId . '-' . $groupId . '-' . strtoupper(bin2hex(random_bytes(3)));
}
function academy_certificate_groups(PDO $pdo, ?string $role = null, bool $activeOnly = true): array
{
    academy_ensure_schema($pdo);
    $where = $activeOnly ? "WHERE g.status = 'active'" : '';
    $groups = $pdo->query("
        SELECT g.*,
               COUNT(gc.id) course_count
        FROM academy_certificate_groups g
        LEFT JOIN academy_certificate_group_courses gc ON gc.group_id = g.id
        {$where}
        GROUP BY g.id
        ORDER BY g.sort_order ASC, g.title ASC
    ")->fetchAll();
    if ($role === null) {
        return $groups;
    }
    return array_values(array_filter($groups, static function (array $group) use ($role): bool {
        $targets = array_values(array_filter(array_map('trim', explode(',', (string) ($group['audience_roles'] ?? '')))));
        return !$targets || in_array('all', $targets, true) || in_array($role, $targets, true);
    }));
}
function academy_certificate_group_courses(PDO $pdo, int $groupId): array
{
    academy_ensure_schema($pdo);
    $stmt = $pdo->prepare("
        SELECT gc.*, w.title, w.description, w.certification_required
        FROM academy_certificate_group_courses gc
        JOIN webinars w ON w.id = gc.webinar_id
        WHERE gc.group_id = ?
        ORDER BY gc.sort_order ASC, w.title ASC
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}
function academy_group_eligibility(PDO $pdo, int $userId, int $groupId): array
{
    $courses = academy_certificate_group_courses($pdo, $groupId);
    $requiredIds = array_values(array_map(
        static fn(array $row): int => (int) $row['webinar_id'],
        array_filter($courses, static fn(array $row): bool => (int) ($row['is_required'] ?? 1) === 1)
    ));
    if (!$requiredIds) {
        return ['eligible' => false, 'completed' => [], 'missing' => $courses, 'courses' => $courses];
    }

    $placeholders = implode(',', array_fill(0, count($requiredIds), '?'));
    $stmt = $pdo->prepare("
        SELECT r.webinar_id
        FROM webinar_registrations r
        JOIN academy_assessments a
          ON a.webinar_id = r.webinar_id
         AND a.status = 'active'
        JOIN academy_attempts at
          ON at.assessment_id = a.id
         AND at.webinar_id = r.webinar_id
         AND at.user_id = r.user_id
         AND at.passed = 1
        WHERE r.user_id = ?
          AND r.completion_status = 'completed'
          AND r.webinar_id IN ({$placeholders})
        GROUP BY r.webinar_id
    ");
    $stmt->execute(array_merge([$userId], $requiredIds));
    $completedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_filter($courses, static fn(array $row): bool => (int) ($row['is_required'] ?? 1) === 1 && !in_array((int) $row['webinar_id'], $completedIds, true)));

    return [
        'eligible' => count($missing) === 0,
        'completed' => $completedIds,
        'missing' => $missing,
        'courses' => $courses,
    ];
}
function academy_certificate_pdf_document(array $certificate, array $courses = []): string
{
    require_once __DIR__ . '/certificates.php';

    $issuedAt = !empty($certificate['issued_at'])
        ? date('F j, Y', strtotime((string) $certificate['issued_at']))
        : date('F j, Y');
    $jpeg = academy_certificate_pdf_render_jpeg($certificate, $courses, $issuedAt);
    return certificate_pdf_build($jpeg, 1684, 1190);
}
function academy_certificate_pdf_render_jpeg(array $certificate, array $courses, string $issuedAt): string
{
    require_once __DIR__ . '/certificates.php';

    $width = 1684;
    $height = 1190;
    $image = imagecreatetruecolor($width, $height);
    imageantialias($image, true);

    $paper = certificate_color($image, '#f7fbf3');
    $cream = certificate_color($image, '#fffdf7');
    $green = certificate_color($image, '#2d5016');
    $leaf = certificate_color($image, '#14733a');
    $gold = certificate_color($image, '#c9a227');
    $goldShadow = certificate_color($image, '#8f6f10');
    $goldHighlight = certificate_color($image, '#fff1a8');
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
    $refRight = $width - 265;
    certificate_text($image, 'Certificate Reference', $refRight, 170, 20, $muted, 'regular', 'right');
    certificate_text($image, (string) $certificate['certificate_ref'], $refRight, 202, 24, $green, 'bold', 'right');
    certificate_text($image, 'Issued ' . $issuedAt, $refRight, 236, 20, $muted, 'regular', 'right');

    $verifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode((string) $certificate['certificate_ref']);
    $heading = 'Certificate';
    certificate_embossed_text($image, 'NATCODEV ACADEMY', $width / 2, 310, 32, $gold, $goldShadow, $goldHighlight, 'bold', 'center');
    certificate_text($image, $heading, $width / 2, 385, 58, $green, 'serif_bold', 'center');
    certificate_text($image, 'This certifies that', $width / 2, 455, 30, $muted, 'regular', 'center');
    certificate_text($image, (string) ($certificate['user_name'] ?? 'Learner'), $width / 2, 535, 64, $ink, 'serif_bold', 'center');
    imageline($image, 430, 565, 1254, 565, $gold);

    certificate_text($image, 'has successfully completed', $width / 2, 635, 28, $ink, 'regular', 'center');
    certificate_text($image, (string) ($certificate['title'] ?? 'NATCODEV Academy training'), $width / 2, 690, 34, $green, 'bold', 'center');

    $y = 760;
    $courseTitles = array_slice(array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['title'] ?? ''), $courses))), 0, 5);
    if ($courseTitles) {
        certificate_text($image, 'Covered Courses', $width / 2, $y, 20, $gold, 'bold', 'center');
        $y += 34;
        foreach ($courseTitles as $title) {
            certificate_text($image, '- ' . $title, $width / 2, $y, 20, $ink, 'regular', 'center');
            $y += 30;
        }
    } else {
        certificate_text($image, 'Recognized for training completion and platform readiness.', $width / 2, $y, 26, $ink, 'regular', 'center');
    }

    certificate_draw_qr($image, $verifyUrl, 188, 860, 170, $ink, $white, $line);
    certificate_text($image, 'VERIFY REFERENCE', 263, 1048, 16, $green, 'bold', 'center');
    certificate_draw_signature($image, 582, 798, $green, $gold, $ink);
    certificate_text($image, 'NATCODEV CHIEF OF PARTY', $width / 2, 1050, 18, $green, 'bold', 'center');
    certificate_text($image, 'Digitally issued by National Coconut Development & Propagation Initiative', $width / 2, 1078, 15, $muted, 'regular', 'center');
    certificate_draw_red_seal($image, 1296, 866, 168);

    ob_start();
    imagejpeg($image, null, 94);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    return $jpeg;
}
function academy_pdf_from_lines(array $lines): string
{
    $ops = ["BT", "/F1 22 Tf", "50 792 Td", "(" . academy_pdf_escape((string) ($lines[0] ?? 'NATCODEV ACADEMY')) . ") Tj"];
    $ops[] = "/F1 16 Tf";
    $ops[] = "0 -30 Td";
    $cursorLines = 0;
    foreach (array_slice($lines, 1) as $line) {
        foreach (academy_wrap_pdf_line((string) $line, 88) as $wrapped) {
            if ($cursorLines > 30) {
                break 2;
            }
            $ops[] = "0 -20 Td";
            $ops[] = "(" . academy_pdf_escape($wrapped) . ") Tj";
            $cursorLines++;
        }
    }
    $ops[] = "ET";
    $content = implode("\n", $ops) . "\n";
    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
        "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}
function academy_wrap_pdf_line(string $line, int $limit): array
{
    if ($line === '') {
        return [''];
    }
    $words = preg_split('/\s+/', $line) ?: [];
    $rows = [];
    $current = '';
    foreach ($words as $word) {
        if (strlen($current . ' ' . $word) > $limit && $current !== '') {
            $rows[] = $current;
            $current = $word;
        } else {
            $current = trim($current . ' ' . $word);
        }
    }
    if ($current !== '') {
        $rows[] = $current;
    }
    return $rows ?: [''];
}
function academy_pdf_escape(string $text): string
{
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    $text = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? $text;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], trim($text));
}
