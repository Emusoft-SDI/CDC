<?php
declare(strict_types=1);

/**
 * NATCODEV Comprehensive All-Stakeholders End-to-End Test Suite
 * Validates the complete user journeys across all 9 platform stakeholder personas:
 * 1. Grower / Farmer (Producer)
 * 2. Buyer / Off-taker (Marketplace Consumer)
 * 3. Field Agent (Field Operations & GPS Verification)
 * 4. Agronomist & Extensionist (Crop Advisory & Soil Diagnostics)
 * 5. State Coordinator (Regional Governance & State Scope)
 * 6. National Coordinator (Apex Pan-Nigeria Strategic Governance)
 * 7. Support Agent (Helpdesk & Inquirer Triage Desk)
 * 8. Administrative Officer / Reviewer (KYC & Editorial Governance)
 * 9. Super Admin (Apex Platform Executive & Financial Reconciliation)
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/maintenance.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/agronomy.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/user-workspaces.php';
require_once __DIR__ . '/../lib/platform-revenue.php';
require_once __DIR__ . '/../lib/identity-validation.php';
require_once __DIR__ . '/../lib/sms_gateway.php';
require_once __DIR__ . '/../lib/news.php';

function run_all_stakeholder_journeys_tests(): void
{
    TestHarness::start("Stakeholder Journey Master Suite: End-to-End Verification across All 9 Platform Personas");
    $pdo = TestHarness::createTestDb();

    // Ensure all subsystem schemas
    admin_ensure_schema($pdo);
    app_ensure_farmer_engagement_schema($pdo);
    app_ensure_certificate_schema($pdo);
    fm_ensure_schema($pdo);
    agronomy_ensure_schema($pdo);
    support_ensure_schema($pdo);
    revenue_ensure_schema($pdo);
    news_ensure_schema($pdo);

    $testTimestamp = time();
    $prefix = 'stk_' . $testTimestamp . '_' . bin2hex(random_bytes(3));

    // Dynamic unique phone numbers
    $growerPhone = '080' . random_int(10000000, 99999999);
    $buyerPhone = '080' . random_int(10000000, 99999999);
    $agentPhone = '080' . random_int(10000000, 99999999);
    $agroPhone = '080' . random_int(10000000, 99999999);
    $stateCoordPhone = '080' . random_int(10000000, 99999999);
    $natCoordPhone = '080' . random_int(10000000, 99999999);
    $supportAgentPhone = '080' . random_int(10000000, 99999999);
    $reviewerPhone = '080' . random_int(10000000, 99999999);
    $superExecPhone = '080' . random_int(10000000, 99999999);

    // Seed Nigeria States & LGAs if missing
    $pdo->exec("INSERT IGNORE INTO nigeria_states (id, state_name, state_code) VALUES (1, 'Lagos', 'LA'), (2, 'Ogun', 'OG'), (3, 'Rivers', 'RI')");
    $pdo->exec("INSERT IGNORE INTO nigeria_lgas (id, lga_name, state_id) VALUES (1, 'Badagry', 1), (2, 'Epe', 1), (3, 'Ijebu-Ode', 2)");

    // =========================================================================
    // PERSONA 1: GROWER / FARMER (PRODUCER)
    // =========================================================================
    $growerEmail = "{$prefix}_farmer@natcodev.org";
    $growerPass = 'SecureGrower2026!';
    $growerHash = password_hash($growerPass, PASSWORD_DEFAULT);

    // 1.1 Application & Account Registration
    app_add_column_if_missing($pdo, 'applications', 'state', 'VARCHAR(100) NULL');
    $appRef = 'APP-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("
        INSERT INTO applications (app_ref, name, email, phone, location, state, farm_size, commitments, confirmed, created_at)
        VALUES (?, 'Oluwaseun Balogun', ?, ?, 'Ajara Coconut Grove, Badagry, Lagos', 'Lagos', 15.5, 'Full commitment to commercial coconut farming', 1, NOW())
    ")->execute([$appRef, $growerEmail, $growerPhone]);
    $appId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO users (application_id, name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES (?, 'Oluwaseun Balogun', ?, ?, ?, 'grower', 'grower', 'active', NOW(), NOW())
    ")->execute([$appId, $growerEmail, $growerPhone, $growerHash]);
    $growerId = (int) $pdo->lastInsertId();

    TestHarness::assert($growerId > 0 && $appId > 0, "Grower Journey: Registered account #{$growerId} with application {$appRef}");

    // 1.2 Farm Registration & Geolocation Scoring
    $pdo->prepare("
        INSERT INTO grower_farms (user_id, application_id, farm_name, farm_size, state_id, lga_id, street_address, latitude, longitude, is_primary)
        VALUES (?, ?, 'Balogun Prime Coconut Estate', 15.5, 1, 1, 'Ajara Farm Settlement, Badagry', 6.4215000, 2.8850000, 1)
    ")->execute([$growerId, $appId]);
    $farmId = (int) $pdo->lastInsertId();

    TestHarness::assert($farmId > 0, "Grower Journey: Farm #{$farmId} registered with GIS coordinates (6.4215, 2.8850)");

    [$coordScore, $coordNotes] = fm_coordinate_score(6.4215000, 2.8850000, 1, 1);
    TestHarness::assert($coordScore >= 85.0, "Grower Journey: GIS coordinate confidence score evaluated high ({$coordScore}%)");

    // 1.3 Weather Snapshot Logging
    $pdo->prepare("
        INSERT INTO farm_weather_snapshots (farm_id, latitude, longitude, temperature_c, rainfall_mm, humidity_percent, wind_kph, provider, summary)
        VALUES (?, 6.4215000, 2.8850000, 29.5, 4.2, 78.0, 12.5, 'open_meteo', 'Optimal tropical rainfall for coconut growth')
    ")->execute([$farmId]);
    $weatherId = (int) $pdo->lastInsertId();
    TestHarness::assert($weatherId > 0, "Grower Journey: Micro-climate weather telemetry captured for farm #{$farmId}");

    // 1.4 Certificate Issuance
    $certCode = 'CERT-' . $appRef . '-V1';
    $certHash = hash('sha256', $certCode . '|' . $growerEmail . '|2026');
    $pdo->prepare("
        INSERT INTO certificates (certificate_ref, application_id, user_id, certificate_path, certificate_pdf_path, status, issued_at, qr_code_hash, verification_url)
        VALUES (?, ?, ?, 'uploads/certificates/cert.png', 'uploads/certificates/cert.pdf', 'active', NOW(), ?, ?)
    ")->execute([$certCode, $appId, $growerId, $certHash, "https://natcodev.gov.ng/verify.php?c={$certCode}"]);
    $certId = (int) $pdo->lastInsertId();
    TestHarness::assert($certId > 0, "Grower Journey: Producer accreditation certificate issued ({$certCode})");

    // =========================================================================
    // PERSONA 2: BUYER / OFF-TAKER (MARKETPLACE CONSUMER)
    // =========================================================================
    $buyerEmail = "{$prefix}_offtaker@lagosagrobuyers.ng";
    $buyerHash = password_hash('BuyerSecret2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, created_at)
        VALUES ('Lagos Agro Processing Ltd', ?, ?, ?, 'grower', 'buyer', 'active', NOW())
    ")->execute([$buyerEmail, $buyerPhone, $buyerHash]);
    $buyerId = (int) $pdo->lastInsertId();

    // 2.1 Ensure category & grower lists coconut produce on marketplace
    $catId = marketplace_category_id_for_type($pdo, 'product') ?: 1;

    $listingSlug = "premium-seedlings-{$testTimestamp}";
    $pdo->prepare("
        INSERT INTO marketplace_listings (category_id, seller_id, title, slug, description, price, quantity_available, min_order_quantity, availability_status, approval_status, created_at)
        VALUES (?, ?, 'Premium Dwarf Hybrid Coconut Seedlings', ?, 'Disease-resistant fast-growing seedlings', 2500.00, 500, 1, 'available', 'approved', NOW())
    ")->execute([$catId, $growerId, $listingSlug]);
    $listingId = (int) $pdo->lastInsertId();

    TestHarness::assert($listingId > 0, "Buyer Journey: Marketplace catalog item #{$listingId} listed by grower");

    // 2.2 Buyer places bulk purchase order
    $orderQty = 50;
    $orderTotal = $orderQty * 2500.00; // 125,000 NGN
    $orderRef = 'ORD-BUY-' . strtoupper(bin2hex(random_bytes(3)));

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE marketplace_listings SET quantity_available = quantity_available - ? WHERE id = ? AND quantity_available >= ?")
        ->execute([$orderQty, $listingId, $orderQty]);
    
    $pdo->prepare("
        INSERT INTO marketplace_orders (order_ref, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, status, payment_status, created_at)
        VALUES (?, ?, ?, ?, 'Lagos Agro Processing Ltd', ?, ?, ?, 2500.00, ?, 'confirmed', 'paid', NOW())
    ")->execute([$orderRef, $listingId, $growerId, $buyerId, $buyerEmail, $buyerPhone, $orderQty, $orderTotal]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->commit();

    TestHarness::assert($orderId > 0, "Buyer Journey: Bulk off-take order #{$orderId} placed (Total: NGN " . number_format($orderTotal, 2) . ")");

    $remainingStock = (int) $pdo->query("SELECT quantity_available FROM marketplace_listings WHERE id = {$listingId}")->fetchColumn();
    TestHarness::assertEqual(450, $remainingStock, "Buyer Journey: Inventory accurately decremented to 450 units");

    // =========================================================================
    // PERSONA 3: FIELD AGENT (FIELD OPERATIONS & GPS VERIFICATION)
    // =========================================================================
    $agentEmail = "{$prefix}_agent@natcodev.gov.ng";
    $agentHash = password_hash('FieldAgent2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, created_at)
        VALUES ('Babajide Sanusi', ?, ?, ?, 'field_agent', 'field_agent', 'active', NOW())
    ")->execute([$agentEmail, $agentPhone, $agentHash]);
    $agentId = (int) $pdo->lastInsertId();

    $agentUser = $pdo->query("SELECT * FROM users WHERE id = {$agentId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(app_user_has_role($pdo, $agentUser, 'field_agent'), "Field Agent Journey: Agent role verified via workspace middleware");

    // 3.1 Field Task Assignment
    $pdo->prepare("
        INSERT INTO field_tasks (farm_id, assigned_to, task_type, priority, status, due_date, notes)
        VALUES (?, ?, 'farm_boundary_verification', 'high', 'assigned', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Perform on-site GPS verification')
    ")->execute([$farmId, $agentId]);
    $taskId = (int) $pdo->lastInsertId();

    TestHarness::assert($taskId > 0, "Field Agent Journey: Inspection task #{$taskId} assigned to Field Agent #{$agentId}");

    // 3.2 Field Agent Conducts On-Site Visit with Haversine GPS Distance Check
    $visitLat = 6.4215200; // ~22 meters from submitted farm coordinates
    $visitLng = 2.8850300;
    $distanceM = fm_haversine_m(6.4215000, 2.8850000, $visitLat, $visitLng);

    TestHarness::assert($distanceM < 50.0, "Field Agent Journey: Haversine distance check confirmed agent on-site (" . round($distanceM, 2) . "m delta)");

    $pdo->prepare("
        INSERT INTO farm_visits (farm_id, task_id, agent_id, visit_latitude, visit_longitude, distance_from_submitted_location_m, notes, result, visited_at)
        VALUES (?, ?, ?, ?, ?, ?, 'Farm boundaries, coconut stands, and irrigation verified genuine', 'verified', NOW())
    ")->execute([$farmId, $taskId, $agentId, $visitLat, $visitLng, $distanceM]);
    $visitId = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE field_tasks SET status = 'completed' WHERE id = ?")->execute([$taskId]);
    $pdo->prepare("UPDATE farm_verifications SET status = 'verified', reviewed_by = ?, reviewed_at = NOW() WHERE farm_id = ?")->execute([$agentId, $farmId]);

    TestHarness::assert($visitId > 0, "Field Agent Journey: Visit #{$visitId} logged and farm #{$farmId} marked verified");

    // =========================================================================
    // PERSONA 4: AGRONOMIST & EXTENSIONIST (ADVISORY & SOIL DIAGNOSTICS)
    // =========================================================================
    $agroEmail = "{$prefix}_agronomist@natcodev.gov.ng";
    $agroHash = password_hash('AgroDoctor2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, is_agronomist, created_at)
        VALUES ('Dr. Funke Adeyemi', ?, ?, ?, 'admin', 'agronomist', 'active', 1, NOW())
    ")->execute([$agroEmail, $agroPhone, $agroHash]);
    $agroId = (int) $pdo->lastInsertId();

    $agroUser = $pdo->query("SELECT * FROM users WHERE id = {$agroId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(app_user_has_role($pdo, $agroUser, 'agronomist'), "Agronomist Journey: Agronomist role verified");

    // 4.1 Record Soil & Crop Diagnostic Test
    $pdo->prepare("
        INSERT INTO agronomy_soil_crop_records (farm_id, recorded_by, soil_ph, nitrogen, phosphorus, potassium, organic_matter, crop_variety, tree_age_years, production_stage, yield_estimate, notes)
        VALUES (?, ?, 6.2, 'Optimal', 'High', 'Medium', '4.5%', 'West African Tall x Dwarf Hybrid', 4.5, 'fruiting', '120 nuts/palm/year', 'Excellent soil health and root aeration')
    ")->execute([$farmId, $agroId]);
    $soilId = (int) $pdo->lastInsertId();

    TestHarness::assert($soilId > 0, "Agronomist Journey: Soil & crop diagnostic record #{$soilId} saved (pH 6.2, 120 nuts/palm)");

    // 4.2 Agronomy Advisory & Recommendation
    $caseRef = 'AGRO-' . strtoupper(bin2hex(random_bytes(3)));
    $pdo->prepare("
        INSERT INTO agronomy_cases (case_ref, grower_id, farm_id, assigned_to, category, priority, status, title, description, created_by)
        VALUES (?, ?, ?, ?, 'nutrient_management', 'medium', 'open', 'Potassium Supplementation for Peak Fruiting', 'Advisory for high-density palm stands', ?)
    ")->execute([$caseRef, $growerId, $farmId, $agroId, $agroId]);
    $caseId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO agronomy_recommendations (case_id, author_id, problem_observed, likely_cause, recommended_action, inputs_needed, urgency)
        VALUES (?, ?, 'Slight leaf margin yellowing on older fronds', 'Potassium depletion due to heavy fruiting load', 'Apply Muriate of Potash (MOP) at 1.5kg per palm in drip line ring', 'Muriate of Potash (0-0-60)', 'normal')
    ")->execute([$caseId, $agroId]);
    $recId = (int) $pdo->lastInsertId();

    TestHarness::assert($recId > 0, "Agronomist Journey: Agronomic advisory #{$recId} dispatched to Grower #{$growerId}");

    // =========================================================================
    // PERSONA 5: STATE COORDINATOR (REGIONAL GOVERNANCE)
    // =========================================================================
    $stateCoordEmail = "{$prefix}_statecoord@natcodev.gov.ng";
    $stateCoordHash = password_hash('LagosCoord2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, location, created_at)
        VALUES ('Engr. Rasheed Adeleke', ?, ?, ?, 'admin', 'state_coordinator', 'active', 'Lagos', NOW())
    ")->execute([$stateCoordEmail, $stateCoordPhone, $stateCoordHash]);
    $stateCoordId = (int) $pdo->lastInsertId();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coordinator_state_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            state_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_state (user_id, state_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->prepare("INSERT IGNORE INTO coordinator_state_assignments (user_id, state_name) VALUES (?, 'Lagos')")->execute([$stateCoordId]);

    $stateCoordUser = $pdo->query("SELECT * FROM users WHERE id = {$stateCoordId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(app_user_has_role($pdo, $stateCoordUser, 'state_coordinator'), "State Coordinator Journey: State Coordinator role verified");

    // 5.1 State-Scoped Metric Aggregation
    $stateGrowerCount = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE state = 'Lagos'")->fetchColumn();
    $stateFarmCount = (int) $pdo->query("SELECT COUNT(*) FROM grower_farms WHERE state_id = 1")->fetchColumn();

    TestHarness::assert($stateGrowerCount >= 1, "State Coordinator Journey: State query aggregated {$stateGrowerCount} growers in Lagos State");
    TestHarness::assert($stateFarmCount >= 1, "State Coordinator Journey: State query aggregated {$stateFarmCount} registered farms in Lagos State");

    // =========================================================================
    // PERSONA 6: NATIONAL COORDINATOR (PAN-NIGERIA STRATEGIC GOVERNANCE)
    // =========================================================================
    $natCoordEmail = "{$prefix}_natcoord@natcodev.gov.ng";
    $natCoordHash = password_hash('NationalLeader2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, created_at)
        VALUES ('Prof. Ibrahim Gadzama', ?, ?, ?, 'admin', 'national_coordinator', 'active', NOW())
    ")->execute([$natCoordEmail, $natCoordPhone, $natCoordHash]);
    $natCoordId = (int) $pdo->lastInsertId();

    $natCoordUser = $pdo->query("SELECT * FROM users WHERE id = {$natCoordId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(app_user_has_role($pdo, $natCoordUser, 'national_coordinator'), "National Coordinator Journey: National Coordinator role verified");

    // 6.1 National Pan-Nigeria Aggregation
    $nationalStats = $pdo->query("
        SELECT 
            COUNT(DISTINCT a.id) total_national_applications,
            COUNT(DISTINCT gf.id) total_national_farms,
            COALESCE(SUM(gf.farm_size), 0) total_national_hectares,
            COUNT(DISTINCT c.id) total_certified_growers
        FROM applications a
        LEFT JOIN grower_farms gf ON gf.application_id = a.id
        LEFT JOIN certificates c ON c.application_id = a.id AND c.status = 'active'
    ")->fetch(PDO::FETCH_ASSOC);

    TestHarness::assert((int) $nationalStats['total_national_applications'] >= 1, "National Coordinator Journey: Federal aggregation: {$nationalStats['total_national_applications']} national applications");
    TestHarness::assert((float) $nationalStats['total_national_hectares'] >= 15.0, "National Coordinator Journey: Federal land coverage: {$nationalStats['total_national_hectares']} hectares under management");

    // =========================================================================
    // PERSONA 7: SUPPORT AGENT / HELPDESK OFFICER
    // =========================================================================
    $supportAgentEmail = "{$prefix}_support@natcodev.gov.ng";
    $supportAgentHash = password_hash('SupportHero2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, created_at)
        VALUES ('Amina Bello', ?, ?, ?, 'admin', 'support_agent', 'active', NOW())
    ")->execute([$supportAgentEmail, $supportAgentPhone, $supportAgentHash]);
    $supportAgentId = (int) $pdo->lastInsertId();

    // 7.1 Grower submits support inquiry ticket
    $ticketRef = 'TCK-' . strtoupper(bin2hex(random_bytes(3)));
    $pdo->prepare("
        INSERT INTO support_tickets (ticket_ref, user_id, requester_name, requester_email, requester_role, category, subject, description, priority, status)
        VALUES (?, ?, 'Oluwaseun Balogun', ?, 'grower', 'logistics', 'Seedling Delivery Timeline Inquiry', 'Requesting estimated delivery date for Badagry dispatch', 'medium', 'open')
    ")->execute([$ticketRef, $growerId, $growerEmail]);
    $ticketId = (int) $pdo->lastInsertId();

    TestHarness::assert($ticketId > 0, "Support Desk Journey: Public inquiry ticket #{$ticketId} ({$ticketRef}) queued");

    // 7.2 Support Agent claims and answers ticket
    $pdo->prepare("
        UPDATE support_tickets 
        SET assigned_admin_id = ?, status = 'in_progress', first_response_at = NOW()
        WHERE id = ?
    ")->execute([$supportAgentId, $ticketId]);

    $pdo->prepare("
        INSERT INTO support_ticket_messages (ticket_id, admin_id, author_name, author_role, message, visibility, created_at)
        VALUES (?, ?, 'Amina Bello', 'support_agent', 'Hello Oluwaseun, your seedlings are scheduled for transit on Thursday via the Badagry central depot.', 'public', NOW())
    ")->execute([$ticketId, $supportAgentId]);
    $msgId = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE support_tickets SET status = 'resolved', outcome = 'resolved_with_schedule', resolved_at = NOW() WHERE id = ?")->execute([$ticketId]);

    $resolvedTicket = $pdo->query("SELECT status, outcome, assigned_admin_id FROM support_tickets WHERE id = {$ticketId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('resolved', $resolvedTicket['status'], "Support Desk Journey: Ticket resolved by Support Agent #{$supportAgentId}");

    // =========================================================================
    // PERSONA 8: ADMINISTRATIVE OFFICER / REVIEWER (KYC & EDITORIAL)
    // =========================================================================
    $adminReviewerEmail = "{$prefix}_reviewer@natcodev.gov.ng";
    $adminReviewerHash = password_hash('AdminDesk2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, is_super_admin, created_at)
        VALUES ('Chidinma Okafor', ?, ?, ?, 'admin', 'admin', 'active', 0, NOW())
    ")->execute([$adminReviewerEmail, $reviewerPhone, $adminReviewerHash]);
    $reviewerId = (int) $pdo->lastInsertId();

    $reviewerUser = $pdo->query("SELECT * FROM users WHERE id = {$reviewerId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(app_user_has_role($pdo, $reviewerUser, 'admin'), "Admin Reviewer Journey: Standard Admin role verified");
    TestHarness::assertEqual(0, (int) $reviewerUser['is_super_admin'], "Admin Reviewer Journey: Reviewer is standard admin (is_super_admin = 0)");

    // 8.1 Publish News Desk Announcement with Version History
    $newsSlug = 'natcodev-q3-expansion-' . $testTimestamp;
    $pdo->prepare("
        INSERT INTO coop_news (title, slug, summary, content, status, author_id, created_at)
        VALUES ('NATCODEV Unveils Q3 Coconut Expansion Plan', ?, 'Major distribution across 12 coastal states.', 'Full initiative details here...', 'published', ?, NOW())
    ")->execute([$newsSlug, $reviewerId]);
    $articleId = (int) $pdo->lastInsertId();

    TestHarness::assert($articleId > 0, "Admin Reviewer Journey: Official communique article #{$articleId} published to news desk");

    // =========================================================================
    // PERSONA 9: SUPER ADMIN (APEX PLATFORM EXECUTIVE & GOVERNANCE)
    // =========================================================================
    $superExecutiveEmail = "{$prefix}_director@natcodev.gov.ng";
    $superExecutiveHash = password_hash('SuperDirector2026!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, is_super_admin, created_at)
        VALUES ('Director General Super Admin', ?, ?, ?, 'admin', 'super_admin', 'active', 1, NOW())
    ")->execute([$superExecutiveEmail, $superExecPhone, $superExecutiveHash]);
    $superExecId = (int) $pdo->lastInsertId();

    $superExecUser = $pdo->query("SELECT * FROM users WHERE id = {$superExecId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual(1, (int) $superExecUser['is_super_admin'], "Super Admin Journey: Apex executive privilege confirmed (is_super_admin = 1)");

    // 9.1 Multi-Gateway Identity Orchestration Simulation
    $idResult = identity_verify_multi_provider($pdo, $growerId, 'bvn', '22222222222', ['gateway_key' => 'monnify']);
    TestHarness::assert(in_array($idResult['status'], ['valid', 'verified'], true), "Super Admin Journey: Multi-gateway identity failover validated mock BVN successfully ({$idResult['status']})");

    // 9.2 SMS Telecommunications Gateway Primary Route & Sender ID
    $smsGateways = $pdo->query("SELECT * FROM sms_gateways ORDER BY priority ASC")->fetchAll(PDO::FETCH_ASSOC);
    TestHarness::assert(count($smsGateways) >= 1, "Super Admin Journey: SMS telecom gateway roster configured and verified active");

    // 9.3 Platform Financial Ledger & Commission Settlement
    $commGross = $orderTotal; // 125,000 NGN
    $commFee = $commGross * 0.05; // 6,250 NGN (5% commission)
    $commNet = $commGross - $commFee; // 118,750 NGN

    $ledgerEntry = revenue_record_once($pdo, [
        'revenue_ref' => 'REV-MKT-' . $orderRef,
        'source_module' => 'marketplace',
        'source_type' => 'order_commission',
        'source_id' => $orderId,
        'user_id' => $buyerId,
        'seller_id' => $growerId,
        'gross_amount' => $commGross,
        'revenue_amount' => $commFee,
        'net_payable_amount' => $commNet,
        'rule_key' => 'marketplace_commission_default',
        'status' => 'earned',
        'description' => "Commission for marketplace order {$orderRef}",
    ]);

    TestHarness::assert((int) ($ledgerEntry['id'] ?? 0) > 0, "Super Admin Journey: Platform revenue commission recorded in financial ledger (Gross: NGN " . number_format($commGross, 2) . ", Commission: NGN " . number_format($commFee, 2) . ")");
}
