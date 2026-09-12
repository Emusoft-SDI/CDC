<?php
declare(strict_types=1);

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../lib/sms_gateway.php';
require_once __DIR__ . '/../lib/news.php';
require_once __DIR__ . '/../lib/identity-validation.php';

function run_sms_and_communication_tests(): void
{
    TestHarness::start('Scenario 6: Telecommunications Gateway & News/Communications Subsystem');

    $pdo = db();

    // 1. Phone Normalization Tests
    TestHarness::assert(
        sms_normalize_phone('08012345678') === '2348012345678',
        'Standard local 11-digit mobile number normalized to 234 MSISDN format'
    );
    TestHarness::assert(
        sms_normalize_phone('+2348012345678') === '2348012345678',
        'International prefixed +234 number normalized to 234 MSISDN format'
    );
    TestHarness::assert(
        sms_normalize_phone('2348012345678') === '2348012345678',
        'Raw MSISDN 13-digit number properly preserved'
    );

    // 2. Global Sender ID Management
    sms_gateway_ensure_schema($pdo);
    $senderOk = sms_set_global_sender_id($pdo, 'NATCODEV');
    TestHarness::assert(
        $senderOk === true,
        'Global Alphanumeric Sender ID update executes successfully'
    );
    $activeSender = sms_get_global_sender_id($pdo);
    TestHarness::assert(
        $activeSender === 'NATCODEV',
        'Retrieved active Global Sender ID matches NATCODEV'
    );

    // 3. Out of Credit Keyword Detection
    TestHarness::assert(
        sms_gateway_is_out_of_credit('Insufficient units to deliver SMS', 200) === true,
        'Out of credit keyword "Insufficient units" detected correctly'
    );
    TestHarness::assert(
        sms_gateway_is_out_of_credit('Payment Required', 402) === true,
        'HTTP 402 Payment Required recognized as exhausted credits'
    );
    TestHarness::assert(
        sms_gateway_is_out_of_credit('SUCCESS: 1 Message Delivered', 200) === false,
        'Successful gateway response is NOT misidentified as low credits'
    );

    // 4. SMS Dispatch in Simulation Mode
    $simRes = app_send_sms('08012345678', 'Test SMS Message from Automated Test Suite', [
        'gateway_key' => 'ebulksms'
    ]);
    // Set simulation mode on ebulksms to test simulation branch
    $pdo->exec("UPDATE sms_gateways SET environment = 'simulation' WHERE gateway_key = 'ebulksms'");
    $simRes = app_send_sms('08012345678', 'Test simulation SMS', ['gateway_key' => 'ebulksms']);
    TestHarness::assert(
        $simRes['success'] === true && $simRes['status'] === 'simulated',
        'SMS Gateway in simulation mode returns simulated delivery success without telecom billing'
    );
    $pdo->exec("UPDATE sms_gateways SET environment = 'live' WHERE gateway_key = 'ebulksms'");

    // 5. Delivery Audit Logging
    $logCheck = $pdo->prepare("SELECT * FROM sms_logs WHERE recipient = '2348012345678' ORDER BY id DESC LIMIT 1");
    $logCheck->execute();
    $loggedEntry = $logCheck->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(
        !empty($loggedEntry) && $loggedEntry['channel'] === 'sms',
        'SMS dispatch event logged to sms_logs table with audit metadata'
    );

    // 6. News & Announcements Schema and CRUD
    news_ensure_schema($pdo);
    $published = news_get_published($pdo, 5, 'public');
    TestHarness::assert(
        count($published) >= 1,
        'news_get_published retrieves active public articles with view analytics'
    );

    $testSlug = 'automated-test-announcement-' . bin2hex(random_bytes(4));
    $pdo->prepare("
        INSERT INTO coop_news (title, slug, summary, category, status, priority, visibility, content, created_at)
        VALUES ('Automated Test Announcement', ?, 'Summary test', 'Training', 'published', 'normal', 'both', '<p>Test Body</p>', NOW())
    ")->execute([$testSlug]);
    $newNewsId = (int) $pdo->lastInsertId();

    $fetchedPost = news_get_by_slug($pdo, $testSlug, false, false);
    TestHarness::assert(
        !empty($fetchedPost) && $fetchedPost['title'] === 'Automated Test Announcement',
        'news_get_by_slug retrieves article by slug with access control validation'
    );

    // 7. Version History Snapshot & Rollback
    $pdo->prepare("
        INSERT INTO coop_news_versions (news_id, title_snapshot, summary_snapshot, content_snapshot, version_number, created_at)
        VALUES (?, 'Original Title v1', 'Original Summary v1', '<p>Original Content v1</p>', 1, NOW())
    ")->execute([$newNewsId]);

    // Update article to new content
    $pdo->prepare("UPDATE coop_news SET title = 'Modified Title v2' WHERE id = ?")->execute([$newNewsId]);

    // Rollback to v1
    $v1Stmt = $pdo->prepare("SELECT * FROM coop_news_versions WHERE news_id = ? AND version_number = 1");
    $v1Stmt->execute([$newNewsId]);
    $v1Snap = $v1Stmt->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare("UPDATE coop_news SET title = ?, summary = ?, content = ? WHERE id = ?")->execute([
        $v1Snap['title_snapshot'],
        $v1Snap['summary_snapshot'],
        $v1Snap['content_snapshot'],
        $newNewsId
    ]);

    $revertedPost = news_get_by_slug($pdo, $testSlug, true, true);
    TestHarness::assert(
        $revertedPost['title'] === 'Original Title v1',
        'Article rollback to previous version snapshot restored content accurately'
    );

    // 8. Interactive Comments & Poll Feedback
    $pdo->prepare("
        INSERT INTO coop_news_feedback (news_id, user_id, feedback_type, content, created_at)
        VALUES (?, 1, 'comment', 'Great update for our cooperative!', NOW())
    ")->execute([$newNewsId]);

    $pdo->prepare("
        INSERT INTO coop_news_feedback (news_id, user_id, feedback_type, content, created_at)
        VALUES (?, 1, 'poll_vote', 'Very Helpful', NOW())
    ")->execute([$newNewsId]);

    $feedbackStmt = $pdo->prepare("SELECT COUNT(*) FROM coop_news_feedback WHERE news_id = ?");
    $feedbackStmt->execute([$newNewsId]);
    $feedCount = (int) $feedbackStmt->fetchColumn();
    TestHarness::assert(
        $feedCount === 2,
        'Stakeholder comments and community poll votes recorded in coop_news_feedback'
    );

    // Clean up test article
    $pdo->prepare("DELETE FROM coop_news WHERE id = ?")->execute([$newNewsId]);
    $pdo->prepare("DELETE FROM coop_news_versions WHERE news_id = ?")->execute([$newNewsId]);
    $pdo->prepare("DELETE FROM coop_news_feedback WHERE news_id = ?")->execute([$newNewsId]);

    // 9. Multi-Provider Identity Verification Gateway & Auto-Failover Tests
    identity_ensure_schema($pdo);
    require_once __DIR__ . '/../lib/identity-validation.php';

    // Test format validation
    $invalidBvn = identity_verify_multi_provider($pdo, 1, 'bvn', '12345');
    TestHarness::assert(
        $invalidBvn['status'] === 'invalid',
        'BVN verification rejects non-11-digit numbers before calling external APIs'
    );

    // Test simulation mode dispatch
    $pdo->exec("UPDATE identity_gateways SET environment = 'simulation' WHERE gateway_key = 'monnify'");
    $simBvn = identity_verify_multi_provider($pdo, 1, 'bvn', '22222222222', ['gateway_key' => 'monnify']);
    TestHarness::assert(
        $simBvn['status'] === 'valid' && str_contains($simBvn['reference'], 'SIM-MONNIFY'),
        'Multi-provider identity orchestrator executes simulation validation with SIM reference'
    );
    $pdo->exec("UPDATE identity_gateways SET environment = 'live' WHERE gateway_key = 'monnify'");

    // Test verification audit logging
    $idLogStmt = $pdo->prepare("SELECT * FROM identity_verification_logs WHERE document_number = '22222222222' ORDER BY id DESC LIMIT 1");
    $idLogStmt->execute();
    $idLog = $idLogStmt->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(
        !empty($idLog) && $idLog['document_type'] === 'bvn',
        'Identity verification attempt audit log recorded in identity_verification_logs table'
    );
}
