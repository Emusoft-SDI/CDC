<?php
declare(strict_types=1);

/**
 * Test Suite: Support Desk Journey & File Attachment Verification
 * 
 * Tests:
 * 1. Absolute Truth: Official Office Contact Information (Address, Phone, Email)
 * 2. Attachment Schema & Directory Security (.htaccess)
 * 3. Ticket Creation with Single & Multiple File Attachments (Images & Documents)
 * 4. Ticket Message Replies with File Attachments
 * 5. Message & Attachment Association (support_messages_with_attachments)
 * 6. Attachment Validation: Prohibited Extensions, Oversize Files, Format Integrity
 * 7. Secure Attachment Access Control (Authorized vs Unauthorized Rejection)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = db();
support_ensure_schema($pdo);

$passed = 0;
$failed = 0;
$total = 0;

function assert_test(bool $condition, string $description, mixed $details = null): void
{
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
        if ($details !== null) {
            echo "         Details: " . print_r($details, true) . "\n";
        }
    }
}

echo "=== SUPPORT DESK & ATTACHMENTS TEST SUITE ===\n\n";

// -------------------------------------------------------------------------
// SECTION 1: Absolute Truth - Official Office Contact Information
// -------------------------------------------------------------------------
echo "SECTION 1: Official Site Contact Information Integrity\n";
$office = app_contact_office();

assert_test(
    isset($office['phone_display']) && $office['phone_display'] === '+234 703 337 7202',
    'Official site phone display is +234 703 337 7202',
    $office['phone_display'] ?? null
);

assert_test(
    isset($office['phone_tel']) && $office['phone_tel'] === '+2347033377202',
    'Official site phone tel URI is +2347033377202',
    $office['phone_tel'] ?? null
);

assert_test(
    is_array($office['address_lines']) && count($office['address_lines']) >= 2,
    'Official office address lines are structured array',
    $office['address_lines'] ?? null
);

$addrFull = implode(' ', $office['address_lines']);
assert_test(
    str_contains($addrFull, 'Febson Mall') && str_contains($addrFull, 'Herbert Macaulay Way') && str_contains($addrFull, 'Abuja'),
    'Official office address contains Suite T11 Febson Mall Herbert Macaulay Way Abuja',
    $addrFull
);

// -------------------------------------------------------------------------
// SECTION 2: Storage Directory & .htaccess Security
// -------------------------------------------------------------------------
echo "\nSECTION 2: Upload Directory & Execution Protection\n";
$uploadDir = support_upload_dir();

assert_test(
    is_dir($uploadDir),
    'Uploads directory for support tickets exists: ' . $uploadDir
);

$htaccessPath = $uploadDir . DIRECTORY_SEPARATOR . '.htaccess';
assert_test(
    file_exists($htaccessPath),
    '.htaccess protection file exists in support upload directory'
);

$htaccessContent = file_get_contents($htaccessPath);
assert_test(
    str_contains($htaccessContent, 'Deny from all') && str_contains($htaccessContent, 'php'),
    '.htaccess strictly forbids execution of php/cgi/script files in upload directory'
);

// -------------------------------------------------------------------------
// SECTION 3: Support Ticket Creation with Attachments
// -------------------------------------------------------------------------
echo "\nSECTION 3: Ticket Creation with Multiple File Attachments\n";

// Create sample test files
$tempDir = sys_get_temp_dir();
$samplePng = $tempDir . DIRECTORY_SEPARATOR . 'test_receipt_' . uniqid() . '.png';
$samplePdf = $tempDir . DIRECTORY_SEPARATOR . 'test_doc_' . uniqid() . '.pdf';

// Valid 1x1 PNG image
file_put_contents($samplePng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
// Valid minimal PDF header
file_put_contents($samplePdf, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\nxref\n0 3\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \ntrailer<</Size 3/Root 1 0 R>>\nstartxref\n102\n%%EOF");

$testEmail = 'applicant.' . uniqid() . '@example.com';
$ticketRef = support_create_ticket($pdo, [
    'name' => 'Fatima Bello',
    'email' => $testEmail,
    'phone' => '+234 803 000 1234',
    'category' => 'payments',
    'priority' => 'high',
    'subject' => 'Payment Verification for Seedling Order',
    'description' => 'Attached please find payment receipt and invoice.',
    'linked_record_type' => 'order',
    'linked_record_ref' => 'ORD-TEST-9988',
    'attachments' => [
        [
            'name' => 'receipt.png',
            'type' => 'image/png',
            'tmp_name' => $samplePng,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($samplePng),
        ],
        [
            'name' => 'invoice.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $samplePdf,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($samplePdf),
        ],
    ],
]);

assert_test(
    is_string($ticketRef) && str_starts_with($ticketRef, 'TKT-'),
    'support_create_ticket returns valid ticket reference',
    $ticketRef
);

$ticket = support_ticket_by_ref($pdo, $ticketRef);
assert_test(
    $ticket !== null && (int) $ticket['id'] > 0,
    'Created ticket exists in support_tickets table',
    $ticket['ticket_ref'] ?? null
);

$attachments = support_ticket_attachments($pdo, (int) $ticket['id']);
assert_test(
    count($attachments) === 2,
    'Exactly 2 attachments registered for ticket',
    count($attachments)
);

$names = array_column($attachments, 'original_name');
assert_test(
    in_array('receipt.png', $names, true) && in_array('invoice.pdf', $names, true),
    'Both receipt.png and invoice.pdf recorded with original names',
    $names
);

$firstPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $attachments[0]['file_path'];
assert_test(
    file_exists($firstPath) && filesize($firstPath) > 0,
    'Attachment file actually saved on disk in uploads/support: ' . basename($firstPath)
);

// -------------------------------------------------------------------------
// SECTION 4: Ticket Message Replies with Attachments
// -------------------------------------------------------------------------
echo "\nSECTION 4: Ticket Reply with New Attachment\n";

$sampleJpg = $tempDir . DIRECTORY_SEPARATOR . 'test_photo_' . uniqid() . '.jpg';
// Valid 1x1 JPEG image
file_put_contents($sampleJpg, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));

$replyMsgId = support_add_message($pdo, (int) $ticket['id'], 'Here is an additional photo of the payment confirmation screen.', null, false, 'public', 'Fatima Bello', 'grower');
assert_test(
    $replyMsgId > 0,
    'support_add_message returns valid message ID > 0',
    $replyMsgId
);

$savedReplyFiles = support_process_uploaded_files($pdo, (int) $ticket['id'], $replyMsgId, [
    [
        'name' => 'photo_screen.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $sampleJpg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($sampleJpg),
    ],
]);

assert_test(
    count($savedReplyFiles) === 1,
    'Reply attachment processed and saved',
    count($savedReplyFiles)
);

$replyAttachments = support_ticket_attachments($pdo, (int) $ticket['id'], $replyMsgId);
assert_test(
    count($replyAttachments) === 1 && $replyAttachments[0]['original_name'] === 'photo_screen.jpg',
    'Attachment correctly linked to specific message ID',
    $replyAttachments[0]['original_name'] ?? null
);

// -------------------------------------------------------------------------
// SECTION 5: Grouped Messages with Attachments Retrieval
// -------------------------------------------------------------------------
echo "\nSECTION 5: Grouped Message & Attachment Retrieval\n";

$messagesWithAtt = support_messages_with_attachments($pdo, (int) $ticket['id'], false);
assert_test(
    count($messagesWithAtt) === 2,
    'Ticket has 2 conversation messages (initial description + reply)',
    count($messagesWithAtt)
);

assert_test(
    isset($messagesWithAtt[0]['attachments']) && count($messagesWithAtt[0]['attachments']) === 2,
    'First message has 2 initial attachments',
    count($messagesWithAtt[0]['attachments'] ?? [])
);

assert_test(
    isset($messagesWithAtt[1]['attachments']) && count($messagesWithAtt[1]['attachments']) === 1,
    'Second message (reply) has 1 attachment',
    count($messagesWithAtt[1]['attachments'] ?? [])
);

// -------------------------------------------------------------------------
// SECTION 6: Attachment Security & Rejection Validation
// -------------------------------------------------------------------------
echo "\nSECTION 6: Security and Rejection Validation\n";

// Test 6a: Reject executable/script extension (.php)
$maliciousFile = $tempDir . DIRECTORY_SEPARATOR . 'shell_' . uniqid() . '.php';
file_put_contents($maliciousFile, '<?php echo "pwned"; ?>');
$rejectedPhp = false;
try {
    support_save_attachment($pdo, (int) $ticket['id'], null, [
        'name' => 'exploit.php',
        'type' => 'application/x-php',
        'tmp_name' => $maliciousFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($maliciousFile),
    ]);
} catch (RuntimeException $e) {
    $rejectedPhp = true;
}
assert_test(
    $rejectedPhp,
    'Malicious .php file is rejected with RuntimeException'
);

// Test 6b: Reject oversize file (> 10MB limit)
$oversizeRejected = false;
try {
    support_save_attachment($pdo, (int) $ticket['id'], null, [
        'name' => 'huge_file.pdf',
        'type' => 'application/pdf',
        'tmp_name' => $samplePdf,
        'error' => UPLOAD_ERR_OK,
        'size' => 15 * 1024 * 1024, // 15 MB
    ]);
} catch (RuntimeException $e) {
    $oversizeRejected = true;
}
assert_test(
    $oversizeRejected,
    'Oversize attachment (> 10 MB) is rejected with RuntimeException'
);

// Test 6c: Reject fake PDF (wrong header signature)
$fakePdfFile = $tempDir . DIRECTORY_SEPARATOR . 'fake_' . uniqid() . '.pdf';
file_put_contents($fakePdfFile, 'This is plain text pretending to be a PDF.');
$fakePdfRejected = false;
try {
    support_save_attachment($pdo, (int) $ticket['id'], null, [
        'name' => 'fake_document.pdf',
        'type' => 'application/pdf',
        'tmp_name' => $fakePdfFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($fakePdfFile),
    ]);
} catch (RuntimeException $e) {
    $fakePdfRejected = true;
}
assert_test(
    $fakePdfRejected,
    'Invalid PDF file without %PDF header is rejected'
);

// -------------------------------------------------------------------------
// SECTION 7: Access Control & Attachment Download Authorization
// -------------------------------------------------------------------------
echo "\nSECTION 7: Access Control & Authorization\n";

$firstAttId = (int) $attachments[0]['id'];
$attRecord = support_attachment_by_id($pdo, $firstAttId);

assert_test(
    $attRecord !== null && $attRecord['ticket_ref'] === $ticketRef,
    'support_attachment_by_id returns attachment and joined ticket data',
    $attRecord['ticket_ref'] ?? null
);

// Authorized requester with matching email
assert_test(
    support_can_access_ticket($pdo, $ticket, null, $testEmail, false) === true,
    'Public requester with matching email is granted access'
);

// Unauthorized user with different email
assert_test(
    support_can_access_ticket($pdo, $ticket, null, 'intruder@otherdomain.com', false) === false,
    'Public requester with different email is denied access'
);

// Admin always granted access
assert_test(
    support_can_access_ticket($pdo, $ticket, null, null, true) === true,
    'Admin is granted access unconditionally'
);

// Clean up temporary files
@unlink($samplePng);
@unlink($samplePdf);
@unlink($sampleJpg);
@unlink($maliciousFile);
@unlink($fakePdfFile);
foreach (support_ticket_attachments($pdo, (int) $ticket['id']) as $attRow) {
    $p = dirname(__DIR__) . DIRECTORY_SEPARATOR . $attRow['file_path'];
    if (file_exists($p)) {
        @unlink($p);
    }
}
$pdo->prepare("DELETE FROM support_ticket_attachments WHERE ticket_id = ?")->execute([(int) $ticket['id']]);
$pdo->prepare("DELETE FROM support_ticket_messages WHERE ticket_id = ?")->execute([(int) $ticket['id']]);
$pdo->prepare("DELETE FROM support_tickets WHERE id = ?")->execute([(int) $ticket['id']]);

echo "\n============================================\n";
echo "SUMMARY: Passed {$passed} of {$total} tests (" . round(($passed / max(1, $total)) * 100, 1) . "%)\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
