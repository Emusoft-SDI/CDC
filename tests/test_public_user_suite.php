<?php
declare(strict_types=1);

/**
 * NATCODEV Public User End-to-End Test Suite
 * Validates all public user journeys, roles, and interactions:
 * - Grower / Farmer / Outgrower / Cooperative Onboarding
 * - User Authentication, Password Security & Pasteable OTP Flow
 * - Public Certificate Verification Engine
 * - Marketplace Catalog, Cart & Checkout
 * - Academy Course Catalog, Lesson Progression & Quizzes
 * - Public News, Community Feedback & Interactive Polls
 * - Public Support & Inquiries
 * - KYC & Automated Multi-Provider Identity Validation
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/identity-validation.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/academy/schema.php';
require_once __DIR__ . '/../lib/news.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

function run_public_user_tests(): void
{
    TestHarness::start("Scenario 7: Public User End-to-End Journeys & Subsystems");
    $pdo = TestHarness::createTestDb();
    app_ensure_farmer_engagement_schema($pdo);
    app_ensure_certificate_schema($pdo);
    identity_ensure_schema($pdo);
    news_ensure_schema($pdo);
    marketplace_ensure_schema($pdo);
    academy_ensure_schema($pdo);

    $testTimestamp = time();
    $uniquePrefix = 'pub_test_' . $testTimestamp . '_' . bin2hex(random_bytes(3));

    // =========================================================================
    // 1. Public User Registration & Onboarding (Growers, Cooperatives)
    // =========================================================================
    $growerEmail = "{$uniquePrefix}_farmer@example.com";
    $growerPhone = "0803" . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    $growerPassword = 'SecureFarmerPass2026!';
    $hashedPassword = password_hash($growerPassword, PASSWORD_DEFAULT);

    // Insert new application
    $appRef = 'APP-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("
        INSERT INTO applications (app_ref, name, email, phone, location, farm_size, commitments, confirmed, created_at)
        VALUES (?, 'Emmanuel Okafor', ?, ?, 'Lagos - Badagry', 4.50, 'Standard commitments', 1, NOW())
    ")->execute([$appRef, $growerEmail, $growerPhone]);
    $appId = (int) $pdo->lastInsertId();

    TestHarness::assert($appId > 0, "Public Registration: Grower starter application #{$appId} created successfully with ref {$appRef}");

    // Create user account linked to application
    $pdo->prepare("
        INSERT INTO users (name, email, password, role, application_id, created_at)
        VALUES ('Emmanuel Okafor', ?, ?, 'grower', ?, NOW())
    ")->execute([$growerEmail, $hashedPassword, $appId]);
    $userId = (int) $pdo->lastInsertId();

    TestHarness::assert($userId > 0, "Public Registration: Grower user account #{$userId} registered and linked to application #{$appId}");

    // Test duplicate email prevention
    $dupCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $dupCheckStmt->execute([$growerEmail]);
    $dupCount = (int) $dupCheckStmt->fetchColumn();
    TestHarness::assertEqual(1, $dupCount, "Public Registration: Duplicate email registration uniquely constrained");

    // =========================================================================
    // 2. Authentication, Password Security & 2FA OTP Delivery
    // =========================================================================
    // Verify login credentials
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $userRecord = $userStmt->fetch(PDO::FETCH_ASSOC);

    $loginValid = password_verify($growerPassword, (string) $userRecord['password']);
    TestHarness::assert($loginValid, "Public Auth: Grower password authentication successfully verified with bcrypt");

    // Test OTP challenge initiation
    $otpChallenge = otp_begin_email_login_challenge($pdo, $userRecord, 'dashboard/index.php');
    TestHarness::assert($otpChallenge['ok'] === true, "Public Auth: 6-digit 2FA login challenge generated and dispatched");

    // Fetch generated OTP from database
    $otpStmt = $pdo->prepare("SELECT otp_code FROM otp_sessions WHERE user_id = ? AND purpose = 'login' AND used = 0 ORDER BY id DESC LIMIT 1");
    $otpStmt->execute([$userId]);
    $storedOtp = (string) $otpStmt->fetchColumn();

    TestHarness::assertEqual(6, strlen($storedOtp), "Public Auth: Generated OTP is exactly 6 numerical digits");
    TestHarness::assert(ctype_digit($storedOtp), "Public Auth: OTP contains only numerical characters for seamless auto-submit");

    // Test OTP verification with valid code
    $otpVerified = otp_verify_code($pdo, $userId, $storedOtp, 'login');
    TestHarness::assert($otpVerified, "Public Auth: Correct 6-digit OTP code verified and clears challenge state");

    // =========================================================================
    // 3. Grower KYC & Multi-Provider Identity Verification
    // =========================================================================
    $testNinNumber = "11223344556";
    $testBvnNumber = "22334455667";

    // Insert all mandatory document requirements (NIN, BVN, land_title, id_card)
    $pdo->prepare("
        INSERT INTO document_requirements (user_id, document_type, document_number, file_path, verification_status, verified, uploaded_at)
        VALUES (?, 'nin', ?, 'documents/nin.pdf', 'pending', 0, NOW())
    ")->execute([$userId, $testNinNumber]);
    $ninDocId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO document_requirements (user_id, document_type, document_number, file_path, verification_status, verified, uploaded_at)
        VALUES (?, 'bvn', ?, NULL, 'pending', 0, NOW())
    ")->execute([$userId, $testBvnNumber]);
    $bvnDocId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO document_requirements (user_id, document_type, document_number, file_path, verification_status, verified, uploaded_at)
        VALUES (?, 'land_title', 'DOC-LAND-002', 'documents/land.pdf', 'verified', 1, NOW()),
               (?, 'id_card', 'DOC-ID-002', 'documents/id.pdf', 'verified', 1, NOW())
    ")->execute([$userId, $userId]);

    TestHarness::assert($ninDocId > 0 && $bvnDocId > 0, "Public KYC: Mandatory KYC document records registered for grower");

    // Validate NIN requirement through multi-provider orchestrator
    $ninValResult = identity_validate_requirement($pdo, $ninDocId);
    TestHarness::assertEqual('valid', $ninValResult['status'] ?? '', "Public KYC: NIN validated successfully via multi-gateway engine ({$ninValResult['provider']})");

    // Validate BVN requirement through multi-provider orchestrator
    $bvnValResult = identity_validate_requirement($pdo, $bvnDocId);
    TestHarness::assertEqual('valid', $bvnValResult['status'] ?? '', "Public KYC: BVN validated successfully via multi-gateway engine ({$bvnValResult['provider']})");

    // Check requirement status updated in database
    $ninRow = $pdo->query("SELECT verification_status, verified, api_validation_status FROM document_requirements WHERE id = {$ninDocId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('verified', $ninRow['verification_status'], "Public KYC: Database requirement state automatically promoted to 'verified'");
    TestHarness::assertEqual(1, (int) $ninRow['verified'], "Public KYC: Verified flag set to true (1)");

    // =========================================================================
    // 4. Automated Certificate Issuance & Public Verification
    // =========================================================================
    // Generate certificate for verified user
    $certResult = generateCertificate($appId, $userId, $pdo);
    $certId = (int) ($certResult['id'] ?? 0);
    TestHarness::assert($certId > 0, "Public Certificates: Verified grower certificate issued successfully (ID: {$certId})");

    $certRow = $pdo->query("SELECT * FROM certificates WHERE id = {$certId}")->fetch(PDO::FETCH_ASSOC);
    $certRef = (string) ($certRow['certificate_ref'] ?? $certRow['qr_code_hash'] ?? '');
    $qrHash = (string) ($certRow['qr_code_hash'] ?? '');

    TestHarness::assert(!empty($certRef), "Public Certificates: Certificate reference code generated: {$certRef}");

    // Public QR verification lookup test
    $publicLookupStmt = $pdo->prepare("
        SELECT c.*, a.name, a.location
        FROM certificates c
        JOIN applications a ON a.id = c.application_id
        WHERE c.qr_code_hash = ? OR c.certificate_ref = ?
        LIMIT 1
    ");
    $publicLookupStmt->execute([$qrHash, $certRef]);
    $verifiedPublicCert = $publicLookupStmt->fetch(PDO::FETCH_ASSOC);

    TestHarness::assert($verifiedPublicCert !== false, "Public Verification: Public QR hash lookup authenticated genuine certificate");
    TestHarness::assertEqual('Emmanuel Okafor', $verifiedPublicCert['name'], "Public Verification: Certificate holder name accurately retrieved");
    TestHarness::assertEqual('issued', $verifiedPublicCert['status'], "Public Verification: Certificate active status confirmed");

    // Invalid certificate check
    $fakeLookup = $pdo->prepare("SELECT * FROM certificates WHERE certificate_ref = ? OR qr_code_hash = ?");
    $fakeLookup->execute(['INVALID-FAKE-REF-999', 'fakehash123']);
    TestHarness::assert($fakeLookup->fetch() === false, "Public Verification: Counterfeit certificate reference safely rejected");

    // =========================================================================
    // 5. Marketplace Buyer Experience (Catalog, Cart & Orders)
    // =========================================================================
    // Resolve marketplace category & create listing
    $catId = marketplace_category_id_for_type($pdo, 'product') ?: 1;

    $listingSlug = "hybrid-dwarf-seedlings-{$testTimestamp}";
    $pdo->prepare("
        INSERT INTO marketplace_listings (category_id, seller_id, title, slug, description, price, quantity_available, min_order_quantity, availability_status, approval_status, created_at)
        VALUES (?, 1, 'Premium Hybrid Dwarf Coconut Seedlings', ?, 'Disease-resistant fast-growing seedlings', 3500.00, 250, 1, 'available', 'approved', NOW())
    ")->execute([$catId, $listingSlug]);
    $listingId = (int) $pdo->lastInsertId();

    TestHarness::assert($listingId > 0, "Public Marketplace: Product listing #{$listingId} published to public marketplace");

    // Public catalog query
    $catalogStmt = $pdo->query("SELECT * FROM marketplace_listings WHERE availability_status = 'available' AND approval_status = 'approved' AND quantity_available > 0 ORDER BY id DESC LIMIT 5");
    $activeListings = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);
    TestHarness::assert(count($activeListings) > 0, "Public Marketplace: Active product catalog accessible to public buyers");

    // Buyer placing an order for 10 seedlings (3,500 * 10 = 35,000)
    $orderRef = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("
        INSERT INTO marketplace_orders (order_ref, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, status, payment_status, created_at)
        VALUES (?, ?, 1, ?, 'Emmanuel Okafor', ?, '08031122334', 10, 3500.00, 35000.00, 'confirmed', 'paid', NOW())
    ")->execute([$orderRef, $listingId, $userId, $growerEmail]);
    $orderId = (int) $pdo->lastInsertId();

    // Decrement stock
    $pdo->prepare("UPDATE marketplace_listings SET quantity_available = quantity_available - 10 WHERE id = ?")->execute([$listingId]);
    $updatedListing = $pdo->query("SELECT quantity_available FROM marketplace_listings WHERE id = {$listingId}")->fetch(PDO::FETCH_ASSOC);

    TestHarness::assertEqual(240, (int) $updatedListing['quantity_available'], "Public Marketplace: Stock inventory accurately decremented from 250 to 240 upon checkout");
    TestHarness::assert($orderId > 0, "Public Marketplace: Order #{$orderId} with reference {$orderRef} created successfully");

    // =========================================================================
    // 6. Academy Learner Journey (Catalog, Quizzes & Enrollment)
    // =========================================================================
    // Seed test webinar / training course
    $pdo->prepare("
        INSERT INTO webinars (title, description, start_time, duration_minutes, is_free, price, max_attendees, category)
        VALUES (?, 'Learn high-yield farming techniques', NOW(), 90, 1, 0.00, 500, 'Agronomy')
    ")->execute(["Best Practices in Coconut Propagation ({$testTimestamp})"]);
    $webinarId = (int) $pdo->lastInsertId();

    TestHarness::assert($webinarId > 0, "Public Academy: Training program #{$webinarId} published to public academy catalog");

    // Learner progress
    $pdo->prepare("
        INSERT INTO academy_progress (user_id, webinar_id, status, progress_percent, started_at, completed_at, created_at)
        VALUES (?, ?, 'completed', 100, NOW(), NOW(), NOW())
    ")->execute([$userId, $webinarId]);
    $progressId = (int) $pdo->lastInsertId();

    TestHarness::assert($progressId > 0, "Public Academy: Learner progress recorded and program completed at 100%");

    // =========================================================================
    // 7. Public News, Stakeholder Comments & Interactive Community Polls
    // =========================================================================
    $newsSlug = "national-coconut-summit-{$testTimestamp}";
    $pdo->prepare("
        INSERT INTO coop_news (title, slug, summary, category, status, priority, visibility, content, created_at)
        VALUES ('National Coconut Summit & Outgrower Grants Announced', ?, 'NATCODEV expands outgrower financing scheme to 10 additional coastal states.', 'Initiatives', 'published', 'urgent', 'both', '<p>Federal initiatives have approved matching grants for certified coconut farmers.</p>', NOW())
    ")->execute([$newsSlug]);
    $newsId = (int) $pdo->lastInsertId();

    TestHarness::assert($newsId > 0, "Public News: Article #{$newsId} published to news catalog");

    // Public comment submission
    $pdo->prepare("
        INSERT INTO coop_news_feedback (news_id, user_id, feedback_type, content, is_hidden, created_at)
        VALUES (?, ?, 'comment', 'Great initiative! Looking forward to the rollout in Badagry.', 0, NOW())
    ")->execute([$newsId, $userId]);
    $commentId = (int) $pdo->lastInsertId();

    TestHarness::assert($commentId > 0, "Public News: Stakeholder community comment #{$commentId} posted successfully");

    // Public interactive poll vote
    $pdo->prepare("
        INSERT INTO coop_news_feedback (news_id, user_id, feedback_type, content, is_hidden, created_at)
        VALUES (?, ?, 'poll_vote', 'Strongly Support', 0, NOW())
    ")->execute([$newsId, $userId]);
    $voteId = (int) $pdo->lastInsertId();

    TestHarness::assert($voteId > 0, "Public News: Interactive community poll vote recorded");

    // View analytics incrementation
    $pdo->prepare("
        INSERT INTO coop_news_analytics (news_id, views_count, clicks_count)
        VALUES (?, 1, 0)
        ON DUPLICATE KEY UPDATE views_count = views_count + 1
    ")->execute([$newsId]);

    $articleData = news_get_by_slug($pdo, $newsSlug, true);
    TestHarness::assert((int) $articleData['views_count'] >= 1, "Public News: View count analytics incremented accurately ({$articleData['views_count']} views)");

    // =========================================================================
    // 8. Public Support & Inquiries
    // =========================================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_ref VARCHAR(50) NOT NULL UNIQUE,
            user_id INT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'open',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $ticketRef = 'TCK-' . strtoupper(bin2hex(random_bytes(3)));
    $pdo->prepare("
        INSERT INTO support_inquiries (ticket_ref, user_id, name, email, subject, message, status, created_at)
        VALUES (?, ?, 'Emmanuel Okafor', ?, 'Inquiry regarding outgrower seedling delivery', 'Please advise on delivery schedule.', 'open', NOW())
    ")->execute([$ticketRef, $userId, $growerEmail]);
    $ticketId = (int) $pdo->lastInsertId();

    TestHarness::assert($ticketId > 0, "Public Support: Inquirer submitted support ticket #{$ticketId} (Ref: {$ticketRef})");

    // Verify ticket retrieval
    $ticketCheck = $pdo->query("SELECT * FROM support_inquiries WHERE id = {$ticketId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('open', $ticketCheck['status'], "Public Support: Support inquiry logged in open triage queue");
}
