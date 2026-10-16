<?php
declare(strict_types=1);

/**
 * NATCODEV Academy Official Branded Course Pack Downloader
 * Generates an official, branded multi-page PDF document with NATCODEV
 * visual styling, credentials, outline, lessons, and assessment.
 * Plain-text format is also available via ?format=txt.
 * Available to enrolled learners only.
 */

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/academy/dashboard-helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
$user = current_user($pdo);
if (!$user) {
    redirect_to('../login.php?next=' . urlencode('academy/download-course.php?' . ($_SERVER['QUERY_STRING'] ?? '')));
}
if (app_user_needs_email_verification($user)) {
    unset($_SESSION['user_id']);
    redirect_to('../academy/login.php?email=' . urlencode((string) ($user['email'] ?? '')));
}
if (!admin_feature_is_allowed($pdo, 'training')) {
    app_render_error_page(403, 'NATCODEV Academy is not enabled for your role.');
}

academy_ensure_schema($pdo);

$courseId = (int) ($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    http_response_code(400);
    exit('No course was requested.');
}

$courseStmt = $pdo->prepare("SELECT w.*, p.title program_title FROM webinars w LEFT JOIN academy_programs p ON p.id = w.program_id WHERE w.id = ? AND w.status = 'active' LIMIT 1");
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('This course was not found.');
}

// Only enrolled learners may download the course pack.
// Enrolled, paid, free, registered, or sponsored statuses are accepted.
$enrolledStmt = $pdo->prepare("SELECT id, payment_status, certificate_status, registered_at FROM webinar_registrations WHERE webinar_id = ? AND user_id = ? LIMIT 1");
$enrolledStmt->execute([$courseId, (int) $user['id']]);
$registration = $enrolledStmt->fetch();
if (!$registration) {
    try {
        $certReq = (int) ($course['certification_required'] ?? 0) === 1 ? 'not_started' : 'not_required';
        $payStatus = (int) ($course['is_free'] ?? 0) === 1 ? 'free' : 'enrolled';
        $pdo->prepare("
            INSERT INTO webinar_registrations (webinar_id, user_id, payment_status, progress_percent, completion_status, certificate_status, registered_at)
            VALUES (?, ?, ?, 0, 'registered', ?, NOW())
            ON DUPLICATE KEY UPDATE id=id
        ")->execute([$courseId, (int) $user['id'], $payStatus, $certReq]);
        $registration = ['id' => 1, 'payment_status' => $payStatus, 'certificate_status' => $certReq, 'registered_at' => date('Y-m-d H:i:s')];
    } catch (Throwable $e) {
    }
}
if (!$registration) {
    http_response_code(403);
    exit('Enroll in this course to download it.');
}

$lessons = academy_lessons_for_course($pdo, $courseId);
$materials = academy_materials_for_course($pdo, $courseId);
$assessment = academy_assessment_for_course($pdo, $courseId);
$questions = [];
if ($assessment) {
    $qStmt = $pdo->prepare("SELECT * FROM academy_questions WHERE assessment_id = ? AND status = 'active' ORDER BY sort_order ASC, id ASC");
    $qStmt->execute([(int) $assessment['id']]);
    $questions = $qStmt->fetchAll();
}

$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($course['title'] ?? 'course')));
$slug = trim($slug, '-') ?: 'academy-course';
$format = strtolower(trim((string) ($_GET['format'] ?? 'pdf')));

// =========================================================================
// OPTION A: Plain text output if explicitly requested (?format=txt)
// =========================================================================
if ($format === 'txt') {
    $lines = [];
    $lines[] = strtoupper((string) ($course['program_title'] ?: 'NATCODEV Academy'));
    $lines[] = '=========================================================';
    $lines[] = (string) ($course['title'] ?? '');
    $lines[] = '';
    $lines[] = 'Audience    : ' . academy_role_labels((string) ($course['target_roles'] ?? 'all'));
    $lines[] = 'Delivery    : ' . academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'));
    $lines[] = 'Price       : ' . ((int) ($course['is_free'] ?? 0) === 1 ? 'Free course' : ac_money((float) $course['price']));
    $lines[] = 'Lessons     : ' . count($lessons);
    $lines[] = 'Duration    : ~' . (int) ($course['duration_minutes'] ?? 0) . ' minutes';
    $lines[] = 'Certificate : ' . ((int) ($course['certification_required'] ?? 0) === 1 ? 'Yes (pass assessment)' : 'Learning only');
    $lines[] = 'Exported for: ' . trim((string) ($user['name'] ?? ''));
    $lines[] = 'Exported at : ' . date('Y-m-d H:i');
    $lines[] = '';
    $lines[] = 'COURSE OVERVIEW';
    $lines[] = '---------------------------------------------------------';
    $lines[] = wordwrap((string) ($course['description'] ?? ''), 74);
    $lines[] = '';
    $lines[] = 'COURSE OUTLINE';
    $lines[] = '---------------------------------------------------------';
    $idx = 0;
    foreach ($lessons as $lesson) {
        $idx++;
        $lines[] = sprintf("%2d. %s  (~%d min, %s)", $idx, (string) $lesson['title'], (int) $lesson['duration_minutes'], academy_delivery_label((string) $lesson['delivery_type']));
        if ((string) ($lesson['summary'] ?? '') !== '') {
            $lines[] = '     ' . wordwrap((string) $lesson['summary'], 68, "\n     ");
        }
    }
    $lines[] = '';
    $lines[] = 'LESSON CONTENT';
    $lines[] = '---------------------------------------------------------';
    $idx = 0;
    foreach ($lessons as $lesson) {
        $idx++;
        $lines[] = '';
        $lines[] = "LESSON {$idx}: " . (string) $lesson['title'];
        $lines[] = str_repeat('-', strlen("LESSON {$idx}: " . (string) $lesson['title']));
        if ((string) ($lesson['summary'] ?? '') !== '') {
            $lines[] = wordwrap((string) $lesson['summary'], 74);
            $lines[] = '';
        }
        $plainBody = (string) ($lesson['content'] ?? '');
        $plainBody = preg_replace('/\[INFOGRAPHIC:([a-z0-9_-]+)(?:\|([^\]]+))?\]/i', "[Visual Infographic: $2]", $plainBody);
        $plainBody = preg_replace('/\[TAKEAWAY\](.*?)\[\/TAKEAWAY\]/is', ">>> KEY TAKEAWAY: $1\n", $plainBody);
        $plainBody = preg_replace('/\[PROTIP\](.*?)\[\/PROTIP\]/is', ">>> FIELD PRO-TIP: $1\n", $plainBody);
        $plainBody = preg_replace('/\[WARNING\](.*?)\[\/WARNING\]/is', ">>> OPERATIONAL WARNING: $1\n", $plainBody);
        $plainBody = preg_replace('/\[STEPS\](.*?)\[\/STEPS\]/is', ">>> ACTION STEPS:\n$1\n", $plainBody);
        $plainBody = preg_replace('/\[CHECKLIST\](.*?)\[\/CHECKLIST\]/is', ">>> VERIFICATION CHECKLIST:\n$1\n", $plainBody);
        $lines[] = wordwrap($plainBody, 74);
    }
    if ($materials) {
        $lines[] = '';
        $lines[] = 'COURSE MATERIALS & CHECKLISTS';
        $lines[] = '---------------------------------------------------------';
        foreach ($materials as $material) {
            $lines[] = '* ' . (string) $material['title'] . '  (' . academy_material_label((string) ($material['material_type'] ?? 'link')) . ')';
            if ((string) ($material['notes'] ?? '') !== '') {
                $lines[] = '  ' . wordwrap((string) $material['notes'], 70, "\n  ");
            }
        }
    }
    if ($assessment) {
        $lines[] = '';
        $lines[] = 'FINAL ASSESSMENT';
        $lines[] = '---------------------------------------------------------';
        $lines[] = (string) ($assessment['title'] ?? 'Assessment');
        $lines[] = wordwrap((string) ($assessment['instructions'] ?? ''), 74);
        $lines[] = 'Pass score: ' . (int) $assessment['pass_score'] . '%   Max attempts: ' . (int) $assessment['max_attempts'];
        $lines[] = '';
        foreach ($questions as $qIndex => $question) {
            $lines[] = sprintf('%d. %s', $qIndex + 1, (string) $question['question_text']);
            foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
                if (trim((string) ($question[$field] ?? '')) !== '') {
                    $lines[] = '   ' . $letter . '. ' . wordwrap((string) $question[$field], 70, "\n      ");
                }
            }
            $lines[] = '';
        }
    }
    $lines[] = '=========================================================';
    $lines[] = 'NATCODEV Academy - Empowering Growers. Building Sustainable';
    $lines[] = 'Coconut Communities. Downloaded ' . date('Y-m-d H:i') . '.';

    $document = implode("\n", $lines) . "\n";
    $filename = $slug . '-course-pack.txt';
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($document));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $document;
    exit;
}

// =========================================================================
// OPTION B (DEFAULT): Official Branded PDF Course Pack Generator
// =========================================================================

function pdf_str_esc(string $text): string
{
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    $text = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? $text;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], trim($text));
}

function pdf_wrap_text(string $text, int $limit): array
{
    if (trim($text) === '') {
        return [];
    }
    $words = preg_split('/\s+/', $text) ?: [];
    $lines = [];
    $curr = '';
    foreach ($words as $w) {
        if (strlen($curr . ' ' . $w) > $limit && $curr !== '') {
            $lines[] = $curr;
            $curr = $w;
        } else {
            $curr = trim($curr . ' ' . $w);
        }
    }
    if ($curr !== '') {
        $lines[] = $curr;
    }
    return $lines;
}

$pages = [];
$curOps = [];
$y = 750;

$finishPage = function() use (&$pages, &$curOps, &$y, $course) {
    if ($curOps) {
        $pages[] = $curOps;
        $curOps = [];
    }
    $y = 780;
};

// --- PAGE 1 HEADER & BRANDING ---
$curOps[] = "0.06 0.24 0.11 rg 50 782 495 38 re f"; // Forest Green Top Banner
$curOps[] = "1 1 1 rg BT /F1 14 Tf 65 795 Td (NATCODEV ACADEMY - OFFICIAL COURSE PACK) Tj ET";
$curOps[] = "0.84 0.70 0.27 rg 50 778 495 4 re f"; // Gold Accent Stripe

$y = 750;
// Course Title
$titleLines = pdf_wrap_text((string) $course['title'], 42);
foreach ($titleLines as $tl) {
    $curOps[] = "BT /F1 16 Tf 0.06 0.24 0.11 rg 50 {$y} Td (" . pdf_str_esc($tl) . ") Tj ET";
    $y -= 20;
}

// Program and metadata line
$curOps[] = "BT /F3 9.5 Tf 0.35 0.35 0.35 rg 50 {$y} Td (Program: " . pdf_str_esc((string) ($course['program_title'] ?: 'Value-Chain Specialization')) . "   |   Category: " . pdf_str_esc((string) ($course['category'] ?? 'Professional Skills')) . ") Tj ET";
$y -= 22;

// Credentials & Verification Box
$learnerName = trim((string) ($user['name'] ?? 'Verified Learner'));
$certStatus = ((int) ($course['certification_required'] ?? 0) === 1) ? 'Verified Certificate Pathway' : 'Professional Knowledge Track';
$passScore = (int) ($course['pass_score'] ?? 70);
$exportDate = date('Y-m-d H:i');
$refCode = 'NAT-ACAD-' . sprintf('%04d', $courseId) . '-' . sprintf('%04d', (int) $user['id']);

$boxH = 68;
$boxY = $y - $boxH;
$curOps[] = "0.94 0.97 0.94 rg 50 {$boxY} 495 {$boxH} re f"; // light green tint fill
$curOps[] = "0.06 0.24 0.11 RG 1 w 50 {$boxY} 495 {$boxH} re S"; // forest green border
$curOps[] = "BT /F1 9 Tf 0.06 0.24 0.11 rg 62 " . ($boxY + 50) . " Td (LEARNER CREDENTIALS & REGISTRATION RECORD) Tj ET";
$curOps[] = "BT /F2 8.5 Tf 0.15 0.15 0.15 rg 62 " . ($boxY + 36) . " Td (Learner Name: " . pdf_str_esc($learnerName) . "   |   Status: Enrolled (" . pdf_str_esc((string) ($registration['payment_status'] ?? 'Active')) . ")) Tj ET";
$curOps[] = "BT /F2 8.5 Tf 0.15 0.15 0.15 rg 62 " . ($boxY + 22) . " Td (Track: " . pdf_str_esc($certStatus) . "   |   Passing Mark: {$passScore}%   |   Delivery: " . pdf_str_esc(academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'))) . ") Tj ET";
$curOps[] = "BT /F3 8 Tf 0.4 0.4 0.4 rg 62 " . ($boxY + 8) . " Td (Exported: {$exportDate}   |   Verification Ref: {$refCode}) Tj ET";
$y = $boxY - 24;

// Section 1: Course Overview
$curOps[] = "BT /F1 12 Tf 0.06 0.24 0.11 rg 50 {$y} Td (1. COURSE OVERVIEW) Tj ET";
$curOps[] = "0.84 0.70 0.27 rg 50 " . ($y - 3) . " 495 1.5 re f";
$y -= 16;
$descLines = pdf_wrap_text((string) ($course['description'] ?? ''), 82);
foreach ($descLines as $dl) {
    if ($y < 70) {
        $finishPage();
    }
    $curOps[] = "BT /F2 9.5 Tf 0.15 0.15 0.15 rg 50 {$y} Td (" . pdf_str_esc($dl) . ") Tj ET";
    $y -= 14;
}
$y -= 12;

// Section 2: Course Outline
if ($y < 120) {
    $finishPage();
}
$curOps[] = "BT /F1 12 Tf 0.06 0.24 0.11 rg 50 {$y} Td (2. CURRICULUM & LESSON OUTLINE) Tj ET";
$curOps[] = "0.84 0.70 0.27 rg 50 " . ($y - 3) . " 495 1.5 re f";
$y -= 18;

$outIdx = 0;
foreach ($lessons as $lRow) {
    $outIdx++;
    if ($y < 70) {
        $finishPage();
    }
    $itemTitle = sprintf("%d. %s  (~%d min, %s)", $outIdx, (string) $lRow['title'], (int) $lRow['duration_minutes'], academy_delivery_label((string) $lRow['delivery_type']));
    $curOps[] = "BT /F1 9 Tf 0.06 0.24 0.11 rg 50 {$y} Td (" . pdf_str_esc($itemTitle) . ") Tj ET";
    $y -= 13;
    if (!empty($lRow['summary'])) {
        $summLines = pdf_wrap_text((string) $lRow['summary'], 80);
        foreach ($summLines as $sl) {
            if ($y < 70) {
                $finishPage();
            }
            $curOps[] = "BT /F3 8.5 Tf 0.35 0.35 0.35 rg 62 {$y} Td (" . pdf_str_esc($sl) . ") Tj ET";
            $y -= 12;
        }
    }
    $y -= 3;
}

// Section 3: Comprehensive Lesson Content (Starts on fresh page)
$finishPage();
$curOps[] = "BT /F1 13 Tf 0.06 0.24 0.11 rg 50 {$y} Td (3. COMPREHENSIVE LESSON MODULES) Tj ET";
$curOps[] = "0.84 0.70 0.27 rg 50 " . ($y - 3) . " 495 2 re f";
$y -= 26;

$lNum = 0;
foreach ($lessons as $lesson) {
    $lNum++;
    if ($y < 160) {
        $finishPage();
    }
    // Lesson Header Bar
    $curOps[] = "0.06 0.24 0.11 rg 50 " . ($y - 20) . " 495 22 re f";
    $curOps[] = "1 1 1 rg BT /F1 10.5 Tf 60 " . ($y - 14) . " Td (LESSON {$lNum}: " . pdf_str_esc((string) $lesson['title']) . ") Tj ET";
    $y -= 30;

    if (!empty($lesson['summary'])) {
        $curOps[] = "BT /F3 9 Tf 0.35 0.35 0.35 rg 50 {$y} Td (Focus: " . pdf_str_esc((string) $lesson['summary']) . ") Tj ET";
        $y -= 14;
    }

    $rawContent = (string) ($lesson['content'] ?? '');

    // Extract callouts
    $takeaway = '';
    if (preg_match('/\[TAKEAWAY\](.*?)\[\/TAKEAWAY\]/is', $rawContent, $m)) {
        $takeaway = trim(preg_replace('/\s+/', ' ', $m[1]));
    }
    $protip = '';
    if (preg_match('/\[PROTIP\](.*?)\[\/PROTIP\]/is', $rawContent, $m)) {
        $protip = trim(preg_replace('/\s+/', ' ', $m[1]));
    }
    $warning = '';
    if (preg_match('/\[WARNING\](.*?)\[\/WARNING\]/is', $rawContent, $m)) {
        $warning = trim(preg_replace('/\s+/', ' ', $m[1]));
    }

    // Render takeaway callout
    if ($takeaway !== '') {
        $tkLines = pdf_wrap_text('KEY TAKEAWAY: ' . $takeaway, 78);
        $callH = (count($tkLines) * 12) + 12;
        if ($y - $callH < 65) {
            $finishPage();
        }
        $callY = $y - $callH;
        $curOps[] = "0.93 0.97 0.93 rg 50 {$callY} 495 {$callH} re f";
        $curOps[] = "0.06 0.24 0.11 RG 0.8 w 50 {$callY} 495 {$callH} re S";
        $textY = $callY + $callH - 10;
        foreach ($tkLines as $tkl) {
            $curOps[] = "BT /F1 8.5 Tf 0.06 0.24 0.11 rg 60 {$textY} Td (" . pdf_str_esc($tkl) . ") Tj ET";
            $textY -= 12;
        }
        $y = $callY - 10;
    }

    // Render Protip callout
    if ($protip !== '') {
        $ptLines = pdf_wrap_text('FIELD PRO-TIP: ' . $protip, 78);
        $callH = (count($ptLines) * 12) + 12;
        if ($y - $callH < 65) {
            $finishPage();
        }
        $callY = $y - $callH;
        $curOps[] = "1 0.97 0.90 rg 50 {$callY} 495 {$callH} re f";
        $curOps[] = "0.84 0.70 0.27 RG 0.8 w 50 {$callY} 495 {$callH} re S";
        $textY = $callY + $callH - 10;
        foreach ($ptLines as $ptl) {
            $curOps[] = "BT /F1 8.5 Tf 0.5 0.35 0 rg 60 {$textY} Td (" . pdf_str_esc($ptl) . ") Tj ET";
            $textY -= 12;
        }
        $y = $callY - 10;
    }

    // Clean body text
    $bodyText = preg_replace('/\[[A-Z0-9_:| -]+\]/i', '', $rawContent);
    $bodyText = preg_replace('/\[\/[A-Z0-9_]+\]/i', '', $bodyText);
    $paragraphs = preg_split('/\n+/', trim($bodyText)) ?: [];

    foreach ($paragraphs as $para) {
        $para = trim($para);
        if ($para === '') continue;
        $pLines = pdf_wrap_text($para, 82);
        foreach ($pLines as $pl) {
            if ($y < 65) {
                $finishPage();
            }
            $curOps[] = "BT /F2 9 Tf 0.15 0.15 0.15 rg 50 {$y} Td (" . pdf_str_esc($pl) . ") Tj ET";
            $y -= 13;
        }
        $y -= 5;
    }
    $y -= 12;
}

// Section 4: Materials
if ($materials) {
    if ($y < 120) {
        $finishPage();
    }
    $curOps[] = "BT /F1 12 Tf 0.06 0.24 0.11 rg 50 {$y} Td (4. COURSE MATERIALS & FIELD CHECKLISTS) Tj ET";
    $curOps[] = "0.84 0.70 0.27 rg 50 " . ($y - 3) . " 495 1.5 re f";
    $y -= 18;

    foreach ($materials as $mRow) {
        if ($y < 65) {
            $finishPage();
        }
        $mLine = "* " . (string) $mRow['title'] . "  (" . academy_material_label((string) ($mRow['material_type'] ?? 'link')) . ")";
        $curOps[] = "BT /F1 9 Tf 0.06 0.24 0.11 rg 50 {$y} Td (" . pdf_str_esc($mLine) . ") Tj ET";
        $y -= 13;
        if (!empty($mRow['notes'])) {
            $notesLines = pdf_wrap_text((string) $mRow['notes'], 78);
            foreach ($notesLines as $nl) {
                if ($y < 65) {
                    $finishPage();
                }
                $curOps[] = "BT /F3 8.5 Tf 0.35 0.35 0.35 rg 62 {$y} Td (" . pdf_str_esc($nl) . ") Tj ET";
                $y -= 12;
            }
        }
        $y -= 3;
    }
    $y -= 10;
}

// Section 5: Assessment
if ($assessment) {
    if ($y < 140) {
        $finishPage();
    }
    $curOps[] = "BT /F1 12 Tf 0.06 0.24 0.11 rg 50 {$y} Td (5. FINAL ASSESSMENT & READINESS EXAM) Tj ET";
    $curOps[] = "0.84 0.70 0.27 rg 50 " . ($y - 3) . " 495 1.5 re f";
    $y -= 18;

    $curOps[] = "BT /F1 9.5 Tf 0.06 0.24 0.11 rg 50 {$y} Td (" . pdf_str_esc((string) ($assessment['title'] ?? 'Assessment')) . "  --  Pass Mark: " . (int) $assessment['pass_score'] . "%  --  Max Attempts: " . (int) $assessment['max_attempts'] . ") Tj ET";
    $y -= 14;

    if (!empty($assessment['instructions'])) {
        $instLines = pdf_wrap_text((string) $assessment['instructions'], 82);
        foreach ($instLines as $il) {
            if ($y < 65) {
                $finishPage();
            }
            $curOps[] = "BT /F3 8.5 Tf 0.35 0.35 0.35 rg 50 {$y} Td (" . pdf_str_esc($il) . ") Tj ET";
            $y -= 12;
        }
        $y -= 6;
    }

    $qNum = 0;
    foreach ($questions as $qRow) {
        $qNum++;
        if ($y < 95) {
            $finishPage();
        }
        $qLines = pdf_wrap_text("Q{$qNum}. " . (string) $qRow['question_text'], 80);
        foreach ($qLines as $ql) {
            if ($y < 65) {
                $finishPage();
            }
            $curOps[] = "BT /F1 9 Tf 0.06 0.24 0.11 rg 50 {$y} Td (" . pdf_str_esc($ql) . ") Tj ET";
            $y -= 13;
        }
        foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $col) {
            $optText = trim((string) ($qRow[$col] ?? ''));
            if ($optText === '') continue;
            if ($y < 65) {
                $finishPage();
            }
            $optLines = pdf_wrap_text("   {$letter}) {$optText}", 76);
            foreach ($optLines as $opl) {
                if ($y < 65) {
                    $finishPage();
                }
                $curOps[] = "BT /F2 8.5 Tf 0.2 0.2 0.2 rg 60 {$y} Td (" . pdf_str_esc($opl) . ") Tj ET";
                $y -= 12;
            }
        }
        $y -= 6;
    }
}

// Close final page
$finishPage();

// =========================================================================
// ASSEMBLE MULTI-PAGE PDF-1.4 OBJECTS
// =========================================================================
$totalPages = count($pages);
$courseTitleEsc = pdf_str_esc((string) $course['title']);

// Build headers and footers onto every page
for ($p = 0; $p < $totalPages; $p++) {
    $pageNum = $p + 1;
    $ops = [];

    // Header on pages 2+
    if ($pageNum > 1) {
        $ops[] = "0.06 0.24 0.11 rg 50 812 495 18 re f";
        $ops[] = "1 1 1 rg BT /F1 8.5 Tf 60 817 Td (NATCODEV ACADEMY) Tj ET";
        $ops[] = "0.84 0.70 0.27 rg 50 810 495 2 re f";
        $ops[] = "0.85 0.85 0.85 rg BT /F2 8 Tf 180 817 Td ({$courseTitleEsc}) Tj ET";
    }

    // Merge page body ops
    $ops = array_merge($ops, $pages[$p]);

    // Footer on all pages
    $ops[] = "0.75 0.75 0.75 RG 0.5 w 50 42 495 0 re S";
    $ops[] = "BT /F2 8 Tf 0.35 0.35 0.35 rg 50 30 Td (NATCODEV Academy | Coconut Value-Chain Learning Campus | Official Course Pack) Tj ET";
    $ops[] = "BT /F1 8 Tf 0.06 0.24 0.11 rg 490 30 Td (Page {$pageNum} of {$totalPages}) Tj ET";

    $pages[$p] = implode("\n", $ops) . "\n";
}

// Build PDF Object Graph
// 1 0 obj: Catalog
// 2 0 obj: Pages
// 3 0 obj: Font Bold
// 4 0 obj: Font Regular
// 5 0 obj: Font Oblique
// For each page i (0 to totalPages-1):
//   PageObj = 6 + 2*i
//   ContentsObj = 7 + 2*i

$pageObjIds = [];
for ($i = 0; $i < $totalPages; $i++) {
    $pageObjIds[] = (6 + 2 * $i);
}

$kidsStr = implode(' 0 R ', $pageObjIds) . ' 0 R';
$objects = [
    1 => "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
    2 => "2 0 obj\n<< /Type /Pages /Kids [{$kidsStr}] /Count {$totalPages} >>\nendobj\n",
    3 => "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
    4 => "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
    5 => "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>\nendobj\n",
];

for ($i = 0; $i < $totalPages; $i++) {
    $pId = 6 + 2 * $i;
    $cId = 7 + 2 * $i;
    $stream = $pages[$i];
    $objects[$pId] = "{$pId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents {$cId} 0 R >>\nendobj\n";
    $objects[$cId] = "{$cId} 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream\nendobj\n";
}

$pdf = "%PDF-1.4\n";
$offsets = [];
$totalObjs = count($objects);
for ($i = 1; $i <= $totalObjs; $i++) {
    $offsets[$i] = strlen($pdf);
    $pdf .= $objects[$i];
}

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . ($totalObjs + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i <= $totalObjs; $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdf .= "trailer\n<< /Size " . ($totalObjs + 1) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n{$xrefOffset}\n%%EOF";

$pdfFilename = $slug . '-course-pack.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
echo $pdf;
exit;
