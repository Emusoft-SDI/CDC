<?php
declare(strict_types=1);

function academy_seed_programs(PDO $pdo): void
{
    $programs = [
        ['Grower Onboarding Program', 'Practical starter program for registered growers: profile readiness, farm records, wallet use, support, marketplace access, and certificate pathway.', 'grower', 5],
        ['Farm Hand Safety Program', 'Operational safety, task discipline, supervisor reporting, field hygiene, equipment care, and farm incident escalation for practical farm workers.', 'farm_hand,grower', 8],
        ['Provider Accreditation Program', 'Input and service provider readiness: location coverage, product/service evidence, compliance documents, pricing conduct, and accreditation review.', 'provider,input_provider,service_provider', 12],
        ['Field Agent Certification Program', 'Field evidence, grower verification, GPS discipline, visit reporting, data quality, and escalation workflow for field and advisory teams.', 'field_agent,agronomist,agric_extensionist', 16],
        ['State Coordinator Operations Program', 'State-scoped operations for applications, LGA drilldown, field network, support oversight, resource allocation, reporting, and governance.', 'state_coordinator,national_coordinator,admin,super_admin', 20],
        ['Marketplace Seller Certification Program', 'Seller Central readiness, product listing standards, inventory, order handling, disputes, buyer trust, wallet settlement, and marketplace compliance.', 'seller,provider,input_provider,service_provider,grower', 24],
        ['Grower & Farm Workforce Academy', 'Grower onboarding, farm hand safety, farm records, wallet basics, field tasks, and self-paced farm practice.', 'grower,farm_hand', 10],
        ['Input & Service Provider Academy', 'Provider accreditation, coverage states/LGAs, product and service readiness, marketplace conduct, and compliance.', 'provider,input_provider,service_provider,seller', 20],
        ['Field & Advisory Academy', 'Field verification, GPS evidence, extension practice, agronomy advisory, grower education, and escalation workflows.', 'field_agent,agronomist,agric_extensionist', 30],
        ['Coordination & Governance Academy', 'State and national operations, RBAC, reporting intelligence, imports, finance oversight, support workflow, and governance.', 'state_coordinator,national_coordinator,admin,super_admin', 40],
        ['Investor & Marketplace Buyer Academy', 'Investment review, marketplace discovery, wallet activity, program communication, and commercial intelligence.', 'investor,grower', 50],
    ];
    $stmt = $pdo->prepare("
        INSERT INTO academy_programs (title, description, audience_roles, sort_order)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE description = VALUES(description), audience_roles = VALUES(audience_roles), sort_order = VALUES(sort_order)
    ");
    foreach ($programs as $program) {
        $stmt->execute($program);
    }
}

function academy_seed_starter_program_content(PDO $pdo): void
{
    $programs = academy_starter_programs();
    if (!$programs) {
        return;
    }
    if (academy_starter_content_seeded($pdo)) {
        return;
    }

    $programLookup = [];
    $rows = $pdo->query("SELECT id, title FROM academy_programs")->fetchAll();
    foreach ($rows as $row) {
        $programLookup[(string) $row['title']] = (int) $row['id'];
    }

    $courseStmt = $pdo->prepare("
        INSERT INTO webinars
            (program_id, course_code, course_type, title, description, start_time, duration_minutes, is_free, price,
             delivery_type, delivery_url, zoom_link, delivery_instructions, max_attendees, category, target_roles,
             certification_required, prerequisites, pass_score, certificate_approval_required, instructor_name, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            program_id = VALUES(program_id),
            course_code = VALUES(course_code),
            course_type = VALUES(course_type),
            description = VALUES(description),
            duration_minutes = VALUES(duration_minutes),
            is_free = VALUES(is_free),
            price = VALUES(price),
            delivery_type = VALUES(delivery_type),
            delivery_url = VALUES(delivery_url),
            zoom_link = VALUES(zoom_link),
            delivery_instructions = VALUES(delivery_instructions),
            category = VALUES(category),
            target_roles = VALUES(target_roles),
            certification_required = VALUES(certification_required),
            prerequisites = VALUES(prerequisites),
            pass_score = VALUES(pass_score),
            certificate_approval_required = VALUES(certificate_approval_required),
            instructor_name = VALUES(instructor_name),
            status = 'active'
    ");
    $courseLookup = $pdo->prepare("SELECT id FROM webinars WHERE title = ? LIMIT 1");
    $lessonStmt = $pdo->prepare("
        INSERT INTO academy_lessons
            (webinar_id, title, summary, content, delivery_type, material_url, duration_minutes, sort_order, is_required, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active')
        ON DUPLICATE KEY UPDATE
            summary = VALUES(summary),
            content = VALUES(content),
            delivery_type = VALUES(delivery_type),
            material_url = VALUES(material_url),
            duration_minutes = VALUES(duration_minutes),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");
    $assessmentStmt = $pdo->prepare("
        INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
        VALUES (?, ?, ?, ?, 3, 'active')
        ON DUPLICATE KEY UPDATE instructions = VALUES(instructions), pass_score = VALUES(pass_score), status = 'active'
    ");
    $assessmentLookup = $pdo->prepare("SELECT id FROM academy_assessments WHERE webinar_id = ? AND title = ? LIMIT 1");
    $questionStmt = $pdo->prepare("
        INSERT INTO academy_questions
            (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'active')
        ON DUPLICATE KEY UPDATE
            option_a = VALUES(option_a),
            option_b = VALUES(option_b),
            option_c = VALUES(option_c),
            option_d = VALUES(option_d),
            correct_option = VALUES(correct_option),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");
    $materialStmt = $pdo->prepare("
        INSERT INTO academy_materials
            (webinar_id, lesson_id, title, material_type, material_url, file_path, notes, sort_order, status)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            material_type = VALUES(material_type),
            material_url = VALUES(material_url),
            file_path = VALUES(file_path),
            notes = VALUES(notes),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");

    foreach ($programs as $item) {
        $slug = academy_slug((string) $item['title']);
        $pdfPath = "academy_uploads/materials/{$slug}-course-handout.pdf";
        $videoPath = "academy_uploads/videos/{$slug}-one-minute-brief.html";
        academy_write_course_pdf($pdfPath, $item);
        academy_write_video_brief($videoPath, $item);

        $programId = $programLookup[(string) $item['program']] ?? null;
        $start = date('Y-m-d H:i:s', strtotime('2026-06-01 09:00:00 +' . ((int) $item['offset_days']) . ' days'));
        $courseStmt->execute([
            $programId,
            (string) $item['code'],
            (string) $item['course_type'],
            (string) $item['title'],
            (string) $item['description'],
            $start,
            (int) $item['duration_minutes'],
            (int) $item['is_free'],
            (float) $item['price'],
            'mixed',
            '../' . $videoPath,
            '../' . $videoPath,
            "Self-paced course. Start with the PDF handout, watch the 1-minute video brief, then complete the quiz/exam. Certificate eligibility requires lesson completion and a passing assessment score.",
            1000,
            'NATCODEV Academy',
            (string) $item['roles'],
            (int) $item['certification_required'],
            (string) $item['prerequisites'],
            (float) $item['pass_score'],
            (int) $item['certificate_approval_required'],
            'NATCODEV Academy Faculty',
        ]);

        $courseLookup->execute([(string) $item['title']]);
        $courseId = (int) $courseLookup->fetchColumn();
        if ($courseId <= 0) {
            continue;
        }

        $lessons = [
            ['Course PDF Handout', 'Short practical PDF course material for offline reading.', "Read the handout and note the readiness checklist before attempting the assessment.", 'document', '../' . $pdfPath, 20, 10],
            ['One-Minute Video Brief', 'Timed visual introduction to the course workflow.', "Watch the 60-second brief to understand the most important actions expected from this profile.", 'video', '../' . $videoPath, 1, 20],
            ['Assessment Readiness', 'Review the key operating rules before the quiz/exam.', implode("\n", (array) $item['checklist']), 'lms', '../academy/index.php?screen=learning', 10, 30],
        ];
        foreach ($lessons as $lesson) {
            $lessonStmt->execute(array_merge([$courseId], $lesson));
        }
        $materialStmt->execute([
            $courseId,
            (string) $item['title'] . ' PDF Handout',
            'pdf',
            '../' . $pdfPath,
            $pdfPath,
            'Short downloadable Academy course PDF.',
            10,
        ]);
        $materialStmt->execute([
            $courseId,
            (string) $item['title'] . ' One-Minute Video Brief',
            'video',
            '../' . $videoPath,
            $videoPath,
            'Browser-playable 60-second Academy video brief.',
            20,
        ]);

        $assessmentTitle = (string) $item['assessment_title'];
        $assessmentStmt->execute([
            $courseId,
            $assessmentTitle,
            'Answer all questions. The assessment checks practical readiness, compliance judgement, and workflow discipline.',
            (float) $item['pass_score'],
        ]);
        $assessmentLookup->execute([$courseId, $assessmentTitle]);
        $assessmentId = (int) $assessmentLookup->fetchColumn();
        if ($assessmentId <= 0) {
            continue;
        }
        foreach ((array) $item['questions'] as $index => $question) {
            $questionStmt->execute([
                $assessmentId,
                (string) $question['question'],
                (string) $question['a'],
                (string) $question['b'],
                (string) $question['c'],
                (string) $question['d'],
                (string) $question['correct'],
                ($index + 1) * 10,
            ]);
        }
    }
}

function academy_starter_content_seeded(PDO $pdo): bool
{
    if (!app_table_exists($pdo, 'webinars') || !app_table_exists($pdo, 'academy_lessons') || !app_table_exists($pdo, 'academy_materials') || !app_table_exists($pdo, 'academy_assessments') || !app_table_exists($pdo, 'academy_questions')) {
        return false;
    }
    $codes = [
        'NAT-GROW-ONB-001',
        'NAT-FH-SAFE-001',
        'NAT-PROV-ACC-001',
        'NAT-FIELD-CERT-001',
        'NAT-SCO-OPS-001',
        'NAT-SELL-CERT-001',
        'NAT-FARM-REC-001',
        'NAT-WALLET-FIN-001',
        'NAT-NURSERY-001',
        'NAT-PEST-DISEASE-001',
        'NAT-COOP-GOV-001',
        'NAT-BUYER-PROC-001',
    ];
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT w.id) courses,
            COUNT(DISTINCT l.id) lessons,
            COUNT(DISTINCT m.id) materials,
            COUNT(DISTINCT a.id) assessments,
            COUNT(DISTINCT q.id) questions
        FROM webinars w
        LEFT JOIN academy_lessons l ON l.webinar_id = w.id AND l.status = 'active'
        LEFT JOIN academy_materials m ON m.webinar_id = w.id AND m.status = 'active'
        LEFT JOIN academy_assessments a ON a.webinar_id = w.id AND a.status = 'active'
        LEFT JOIN academy_questions q ON q.assessment_id = a.id AND q.status = 'active'
        WHERE w.course_code IN ({$placeholders})
    ");
    $stmt->execute($codes);
    $row = $stmt->fetch() ?: [];

    return (int) ($row['courses'] ?? 0) >= 12
        && (int) ($row['lessons'] ?? 0) >= 36
        && (int) ($row['materials'] ?? 0) >= 24
        && (int) ($row['assessments'] ?? 0) >= 12
        && (int) ($row['questions'] ?? 0) >= 60;
}

function academy_starter_programs(): array
{
    return [
        [
            'program' => 'Grower Onboarding Program',
            'title' => 'Grower Onboarding Program',
            'code' => 'NAT-GROW-ONB-001',
            'course_type' => 'orientation',
            'description' => 'A practical starter course for growers covering profile completion, farm identity, documents, wallet basics, support desk use, marketplace access, and certificate readiness.',
            'roles' => 'grower',
            'is_free' => 1,
            'price' => 0,
            'certification_required' => 1,
            'certificate_approval_required' => 0,
            'duration_minutes' => 45,
            'pass_score' => 70,
            'prerequisites' => 'Registered grower account.',
            'offset_days' => 1,
            'assessment_title' => 'Grower Onboarding Quiz',
            'objectives' => ['Complete profile and farm details accurately.', 'Understand wallet, support, marketplace, and verification flow.', 'Prepare documents needed for grower participation certificate.'],
            'checklist' => ['Confirm name, phone, email, state, and LGA.', 'Upload identity and farm documents where requested.', 'Use support desk for issues instead of duplicate registrations.', 'Open Academy certificates only after completing required learning.'],
            'questions' => [
                ['question' => 'What should a grower do before requesting platform verification?', 'a' => 'Complete profile and upload required records', 'b' => 'Create multiple accounts', 'c' => 'Skip state and LGA', 'd' => 'Use another grower certificate', 'correct' => 'A'],
                ['question' => 'Where should a grower raise a platform issue?', 'a' => 'Marketplace listing page', 'b' => 'Support Desk', 'c' => 'Random payment reference', 'd' => 'Certificate QR page', 'correct' => 'B'],
                ['question' => 'Why are state and LGA important?', 'a' => 'They are only decoration', 'b' => 'They scope operations, reporting, and field support', 'c' => 'They hide the profile', 'd' => 'They replace documents', 'correct' => 'B'],
                ['question' => 'What does the wallet help with?', 'a' => 'Training payments and platform transactions', 'b' => 'Changing another user role', 'c' => 'Deleting certificates', 'd' => 'Bypassing registration', 'correct' => 'A'],
                ['question' => 'A grower certificate should be verified through what public channel?', 'a' => 'QR or certificate reference verification', 'b' => 'Unverified screenshot', 'c' => 'Private password', 'd' => 'A copied barcode', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Farm Hand Safety Program',
            'title' => 'Farm Hand Safety Program',
            'code' => 'NAT-FH-SAFE-001',
            'course_type' => 'course',
            'description' => 'A field-ready safety course for farm hands covering work categories, supervisor assignment, task evidence, incident reporting, tools, hygiene, and safe farm conduct.',
            'roles' => 'farm_hand,grower',
            'is_free' => 1,
            'price' => 0,
            'certification_required' => 1,
            'certificate_approval_required' => 0,
            'duration_minutes' => 45,
            'pass_score' => 70,
            'prerequisites' => 'Farm hand or grower-managed worker profile.',
            'offset_days' => 2,
            'assessment_title' => 'Farm Hand Safety Quiz',
            'objectives' => ['Recognize safe work practices for coconut farm activities.', 'Report hazards, incidents, and completed tasks clearly.', 'Work under assigned grower/farm supervision.'],
            'checklist' => ['Wear suitable safety gear for assigned work.', 'Confirm the task and supervisor before starting.', 'Report injuries, tool damage, chemical exposure, and unsafe conditions immediately.', 'Record completed work with practical evidence.'],
            'questions' => [
                ['question' => 'What should a farm hand do before beginning a task?', 'a' => 'Confirm assignment and supervisor instruction', 'b' => 'Work on any farm nearby', 'c' => 'Ignore safety gear', 'd' => 'Use chemicals without guidance', 'correct' => 'A'],
                ['question' => 'Which event must be reported immediately?', 'a' => 'A field hazard or injury', 'b' => 'A completed lunch break only', 'c' => 'A personal phone change only', 'd' => 'A marketplace advert', 'correct' => 'A'],
                ['question' => 'Why should task evidence be recorded?', 'a' => 'To support accountability and farm operations reporting', 'b' => 'To hide work done', 'c' => 'To replace payment records', 'd' => 'To bypass grower approval', 'correct' => 'A'],
                ['question' => 'Who can assign a farm hand to practical farm work?', 'a' => 'The linked grower or authorized farm manager', 'b' => 'Any buyer', 'c' => 'Any visitor', 'd' => 'Only a marketplace customer', 'correct' => 'A'],
                ['question' => 'Safe handling of tools requires what?', 'a' => 'Inspection, correct use, and reporting damage', 'b' => 'Sharing broken tools silently', 'c' => 'Using any tool for any job', 'd' => 'Ignoring protective gear', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Provider Accreditation Program',
            'title' => 'Provider Accreditation Program',
            'code' => 'NAT-PROV-ACC-001',
            'course_type' => 'certification',
            'description' => 'Accreditation readiness for input and service providers: business profile, state/LGA coverage, products, service categories, documents, pricing, compliance, and buyer trust.',
            'roles' => 'provider,input_provider,service_provider',
            'is_free' => 0,
            'price' => 15000,
            'certification_required' => 1,
            'certificate_approval_required' => 1,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'prerequisites' => 'Provider registration profile and service/input category selection.',
            'offset_days' => 3,
            'assessment_title' => 'Provider Accreditation Exam',
            'objectives' => ['Prepare provider documents and category evidence.', 'Understand state/LGA coverage and service readiness.', 'Meet accreditation and marketplace conduct expectations.'],
            'checklist' => ['Complete business identity and contact records.', 'Select accurate input/service categories.', 'Upload licenses, certifications, or proof of capacity where required.', 'Set truthful coverage states and LGAs.'],
            'questions' => [
                ['question' => 'What should provider coverage describe?', 'a' => 'Actual states and LGAs where service or supply can be delivered', 'b' => 'Every state whether served or not', 'c' => 'Only the owner home town', 'd' => 'No location at all', 'correct' => 'A'],
                ['question' => 'Accreditation evidence may include what?', 'a' => 'CAC, license, product proof, service capacity, or certification', 'b' => 'A blank profile', 'c' => 'Another provider password', 'd' => 'Unrelated screenshots only', 'correct' => 'A'],
                ['question' => 'Why are product/service categories important?', 'a' => 'They match providers to growers and marketplace needs', 'b' => 'They hide providers from search', 'c' => 'They remove compliance checks', 'd' => 'They replace payment flow', 'correct' => 'A'],
                ['question' => 'A provider should list products how?', 'a' => 'Accurately with clear description, pricing, availability, and compliance notes', 'b' => 'With false claims', 'c' => 'Without stock details', 'd' => 'As duplicate fake items', 'correct' => 'A'],
                ['question' => 'Who reviews accreditation readiness?', 'a' => 'Authorized admin/back-office workflow', 'b' => 'Anonymous buyer only', 'c' => 'Unregistered visitor', 'd' => 'No one', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Field Agent Certification Program',
            'title' => 'Field Agent Certification Program',
            'code' => 'NAT-FIELD-CERT-001',
            'course_type' => 'certification',
            'description' => 'Certification course for field agents and advisory teams covering grower verification, GPS evidence, document review, visit reports, data quality, escalation, and offline discipline.',
            'roles' => 'field_agent,agronomist,agric_extensionist',
            'is_free' => 0,
            'price' => 25000,
            'certification_required' => 1,
            'certificate_approval_required' => 1,
            'duration_minutes' => 75,
            'pass_score' => 80,
            'prerequisites' => 'Assigned field/advisory profile.',
            'offset_days' => 4,
            'assessment_title' => 'Field Agent Certification Exam',
            'objectives' => ['Verify growers with location and document discipline.', 'Capture field evidence that supports reliable reporting.', 'Escalate fraud, safety, and data quality issues.'],
            'checklist' => ['Confirm identity before verification.', 'Capture GPS/location evidence where required.', 'Record farm observations in clear language.', 'Escalate suspicious documents or inconsistent data.'],
            'questions' => [
                ['question' => 'What is the strongest field verification evidence?', 'a' => 'Identity, farm visit details, GPS/location, and document checks', 'b' => 'A verbal claim only', 'c' => 'A copied certificate', 'd' => 'No visit record', 'correct' => 'A'],
                ['question' => 'When should a field agent escalate?', 'a' => 'When records are suspicious, unsafe, or inconsistent', 'b' => 'Never', 'c' => 'Only after deleting the record', 'd' => 'Only to a buyer', 'correct' => 'A'],
                ['question' => 'Why does data quality matter?', 'a' => 'It supports approvals, reporting, planning, and trust', 'b' => 'It slows all work only', 'c' => 'It replaces the field visit', 'd' => 'It hides fraud', 'correct' => 'A'],
                ['question' => 'Offline capture should be synced when?', 'a' => 'As soon as connection is available', 'b' => 'Never', 'c' => 'Only after one year', 'd' => 'Only if buyer requests it', 'correct' => 'A'],
                ['question' => 'Field notes should be what?', 'a' => 'Clear, factual, and tied to the observed farm condition', 'b' => 'Emotional and vague', 'c' => 'Copied for every grower', 'd' => 'Unrelated to the visit', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'State Coordinator Operations Program',
            'title' => 'State Coordinator Operations Program',
            'code' => 'NAT-SCO-OPS-001',
            'course_type' => 'certification',
            'description' => 'Operations program for state coordinators covering state scope, LGA drilldown, grower review, field network, support, training, resources, reporting intelligence, and governance.',
            'roles' => 'state_coordinator,national_coordinator,admin,super_admin',
            'is_free' => 0,
            'price' => 30000,
            'certification_required' => 1,
            'certificate_approval_required' => 1,
            'duration_minutes' => 90,
            'pass_score' => 80,
            'prerequisites' => 'Back-office role assignment and state scope where applicable.',
            'offset_days' => 5,
            'assessment_title' => 'State Coordinator Operations Exam',
            'objectives' => ['Operate state-scoped dashboards responsibly.', 'Use state/LGA drilldowns for verification and reporting.', 'Coordinate support, field teams, training, and resources.'],
            'checklist' => ['Review state assignment before acting.', 'Use LGA drilldown for operations decisions.', 'Keep RBAC boundaries intact.', 'Escalate national-level issues through governance workflow.'],
            'questions' => [
                ['question' => 'State coordinator dashboards should be scoped by what?', 'a' => 'Assigned state and relevant LGAs', 'b' => 'Any state by default', 'c' => 'Only marketplace products', 'd' => 'No geography', 'correct' => 'A'],
                ['question' => 'Why should RBAC remain intact?', 'a' => 'To ensure users only access authorized operations', 'b' => 'To confuse users', 'c' => 'To remove accountability', 'd' => 'To bypass admin review', 'correct' => 'A'],
                ['question' => 'What does LGA drilldown help with?', 'a' => 'Local verification, field planning, and reporting intelligence', 'b' => 'Hiding state data', 'c' => 'Deleting applications', 'd' => 'Avoiding field work', 'correct' => 'A'],
                ['question' => 'Resource allocation should be based on what?', 'a' => 'Verified needs, location, and operational priority', 'b' => 'Random selection only', 'c' => 'Unverified rumors', 'd' => 'Duplicate records', 'correct' => 'A'],
                ['question' => 'A serious system or policy concern belongs where?', 'a' => 'Governance/escalation workflow', 'b' => 'A product listing', 'c' => 'A private chat only', 'd' => 'A deleted ticket', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Marketplace Seller Certification Program',
            'title' => 'Marketplace Seller Certification Program',
            'code' => 'NAT-SELL-CERT-001',
            'course_type' => 'certification',
            'description' => 'Seller Central certification covering store setup, product listing, inventory, orders, disputes, buyer communication, settlement, delivery readiness, and marketplace trust.',
            'roles' => 'seller,provider,input_provider,service_provider,grower',
            'is_free' => 0,
            'price' => 20000,
            'certification_required' => 1,
            'certificate_approval_required' => 0,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'prerequisites' => 'Seller Central access or approved provider/grower profile.',
            'offset_days' => 6,
            'assessment_title' => 'Marketplace Seller Certification Exam',
            'objectives' => ['Create clear and truthful marketplace listings.', 'Understand order, inventory, dispute, and settlement workflows.', 'Protect buyer trust through reliable fulfillment.'],
            'checklist' => ['Use accurate product names, images, categories, and availability.', 'Keep stock and price updated.', 'Respond to order and dispute notifications promptly.', 'Use wallet/settlement records for payment tracking.'],
            'questions' => [
                ['question' => 'A good product listing must include what?', 'a' => 'Accurate description, category, price, and availability', 'b' => 'False claims', 'c' => 'No image or details', 'd' => 'A copied unrelated product', 'correct' => 'A'],
                ['question' => 'Why should stock be updated?', 'a' => 'To avoid failed orders and buyer disputes', 'b' => 'To hide inventory', 'c' => 'To bypass payment', 'd' => 'To disable store access', 'correct' => 'A'],
                ['question' => 'Marketplace disputes should be handled how?', 'a' => 'Promptly with order evidence and clear communication', 'b' => 'Ignored', 'c' => 'Deleted from records', 'd' => 'Moved to certificate page', 'correct' => 'A'],
                ['question' => 'Seller settlement records are connected to what?', 'a' => 'Wallet/payment transaction history', 'b' => 'Farm hand safety only', 'c' => 'Fake certificates', 'd' => 'Unregistered visitors', 'correct' => 'A'],
                ['question' => 'Buyer trust improves when sellers do what?', 'a' => 'Fulfill accurately and communicate status clearly', 'b' => 'Change prices after order without notice', 'c' => 'List unavailable goods', 'd' => 'Ignore support tickets', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Grower & Farm Workforce Academy',
            'title' => 'Farm Records and Yield Tracking',
            'code' => 'NAT-FARM-REC-001',
            'course_type' => 'course',
            'description' => 'Professional farm recordkeeping course covering farm profile data, stand count, yield logs, input use, labor records, harvest notes, and dashboard reporting discipline.',
            'roles' => 'grower,farm_hand,field_agent',
            'is_free' => 1,
            'price' => 0,
            'certification_required' => 1,
            'certificate_approval_required' => 0,
            'duration_minutes' => 55,
            'pass_score' => 70,
            'prerequisites' => 'Basic grower or farm workforce profile.',
            'offset_days' => 7,
            'assessment_title' => 'Farm Records and Yield Tracking Assessment',
            'objectives' => ['Maintain reliable farm records.', 'Track yield and input activity clearly.', 'Use records to support verification, finance, and advisory decisions.'],
            'checklist' => ['Record farm location and stand counts.', 'Log inputs, labor, and harvest activity.', 'Update records after field visits.', 'Use evidence to support reporting.'],
            'questions' => [
                ['question' => 'Why should yield records be updated after harvest?', 'a' => 'To support performance tracking and planning', 'b' => 'To hide poor harvests', 'c' => 'To delete farm identity', 'd' => 'To skip verification', 'correct' => 'A'],
                ['question' => 'A good farm record should include what?', 'a' => 'Date, activity, quantity, location, and evidence where useful', 'b' => 'Only a nickname', 'c' => 'No date', 'd' => 'Another user password', 'correct' => 'A'],
                ['question' => 'Stand count helps with what?', 'a' => 'Yield estimates, inputs, visits, and farm planning', 'b' => 'Disabling dashboard access', 'c' => 'Changing another account', 'd' => 'Avoiding reports', 'correct' => 'A'],
                ['question' => 'Input records should capture what?', 'a' => 'Type, amount, date, and purpose of use', 'b' => 'Only market price rumor', 'c' => 'Unrelated personal notes', 'd' => 'Blank values', 'correct' => 'A'],
                ['question' => 'Reliable records improve access to what?', 'a' => 'Verification, advisory support, finance, and reporting confidence', 'b' => 'Fake certificates', 'c' => 'Hidden marketplace orders', 'd' => 'Unauthorized admin rights', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Grower & Farm Workforce Academy',
            'title' => 'Wallet, Payments and Refund Readiness',
            'code' => 'NAT-WALLET-FIN-001',
            'course_type' => 'course',
            'description' => 'Learner and grower finance readiness course covering wallet funding, Monnify checkout, reserved accounts, receipts, payment references, refunds, and transaction reconciliation.',
            'roles' => 'learner,grower,provider,seller,buyer',
            'is_free' => 1,
            'price' => 0,
            'certification_required' => 0,
            'certificate_approval_required' => 0,
            'duration_minutes' => 40,
            'pass_score' => 70,
            'prerequisites' => 'NATCODEV user account.',
            'offset_days' => 8,
            'assessment_title' => 'Wallet and Payment Readiness Quiz',
            'objectives' => ['Fund wallet safely.', 'Track payment references and receipts.', 'Understand refund rules before course completion or certificate issuance.'],
            'checklist' => ['Use only official wallet funding channels.', 'Keep transaction references.', 'Check pending Monnify payments.', 'Request refunds before completion/certificate where allowed.'],
            'questions' => [
                ['question' => 'A learner funds wallet through what?', 'a' => 'Official wallet page using Monnify checkout or reserved transfer account', 'b' => 'A random chat account', 'c' => 'Unverified screenshots', 'd' => 'A certificate QR code', 'correct' => 'A'],
                ['question' => 'Why keep payment references?', 'a' => 'They support reconciliation and support resolution', 'b' => 'They replace passwords', 'c' => 'They create admin access', 'd' => 'They delete transactions', 'correct' => 'A'],
                ['question' => 'Refunds for courses are blocked after what?', 'a' => 'Course completion or certificate issuance', 'b' => 'Browsing catalog', 'c' => 'Opening support', 'd' => 'Viewing the homepage', 'correct' => 'A'],
                ['question' => 'Pending payment status should be checked where?', 'a' => 'Wallet transaction action or official payment verification', 'b' => 'Marketplace image gallery', 'c' => 'Public certificate page only', 'd' => 'Another user profile', 'correct' => 'A'],
                ['question' => 'Wallet records help with what?', 'a' => 'Training payments, receipts, refunds, and settlements', 'b' => 'Skipping assessments', 'c' => 'Changing course pass scores', 'd' => 'Bypassing identity', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Grower & Farm Workforce Academy',
            'title' => 'Coconut Nursery and Seedling Quality',
            'code' => 'NAT-NURSERY-001',
            'course_type' => 'course',
            'description' => 'Practical nursery management course covering seed nut selection, nursery layout, watering, shade, pest checks, seedling grading, transport readiness, and survival tracking.',
            'roles' => 'grower,provider,input_provider,field_agent',
            'is_free' => 0,
            'price' => 8000,
            'certification_required' => 1,
            'certificate_approval_required' => 0,
            'duration_minutes' => 65,
            'pass_score' => 75,
            'prerequisites' => 'Basic coconut production interest or nursery operation.',
            'offset_days' => 9,
            'assessment_title' => 'Nursery and Seedling Quality Assessment',
            'objectives' => ['Select quality seed nuts and seedlings.', 'Manage nursery health and survival.', 'Prepare seedlings for field establishment.'],
            'checklist' => ['Select healthy seed nuts.', 'Maintain shade and watering discipline.', 'Grade seedlings before distribution.', 'Record survival after transplant.'],
            'questions' => [
                ['question' => 'Quality seedling selection should consider what?', 'a' => 'Health, vigor, root condition, and disease signs', 'b' => 'Random size only', 'c' => 'No leaves', 'd' => 'Unknown origin', 'correct' => 'A'],
                ['question' => 'Nursery watering should be what?', 'a' => 'Consistent and appropriate for seedling stage', 'b' => 'Never done', 'c' => 'Only during transport', 'd' => 'Replaced by fertilizer only', 'correct' => 'A'],
                ['question' => 'Why grade seedlings before distribution?', 'a' => 'To reduce field failure and improve establishment', 'b' => 'To hide weak seedlings', 'c' => 'To inflate prices only', 'd' => 'To avoid records', 'correct' => 'A'],
                ['question' => 'Seedling transport should protect what?', 'a' => 'Roots, moisture, stems, and leaves', 'b' => 'Only invoice paper', 'c' => 'Nothing', 'd' => 'Passwords', 'correct' => 'A'],
                ['question' => 'Post-transplant survival records help with what?', 'a' => 'Quality feedback and farm planning', 'b' => 'Deleting nursery data', 'c' => 'Skipping field checks', 'd' => 'Hiding losses', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Field & Advisory Academy',
            'title' => 'Coconut Pest, Disease and Farm Health Monitoring',
            'code' => 'NAT-PEST-DISEASE-001',
            'course_type' => 'certification',
            'description' => 'Farm health monitoring course covering pest scouting, disease symptoms, field notes, photo evidence, escalation thresholds, advisory recommendations, and follow-up visits.',
            'roles' => 'grower,field_agent,agronomist,agric_extensionist',
            'is_free' => 0,
            'price' => 12000,
            'certification_required' => 1,
            'certificate_approval_required' => 0,
            'duration_minutes' => 70,
            'pass_score' => 75,
            'prerequisites' => 'Farm profile or field/advisory profile.',
            'offset_days' => 10,
            'assessment_title' => 'Farm Health Monitoring Assessment',
            'objectives' => ['Identify common farm health warning signs.', 'Capture evidence and recommendations.', 'Escalate severe pest or disease risks.'],
            'checklist' => ['Inspect palms regularly.', 'Capture clear symptom photos.', 'Record location and severity.', 'Escalate severe or spreading cases.'],
            'questions' => [
                ['question' => 'A useful farm health report includes what?', 'a' => 'Symptoms, location, severity, photo evidence, and recommendation', 'b' => 'Only a greeting', 'c' => 'No date or location', 'd' => 'Random marketplace price', 'correct' => 'A'],
                ['question' => 'When should pest or disease cases be escalated?', 'a' => 'When severe, spreading, unusual, or beyond basic advisory response', 'b' => 'Never', 'c' => 'Only after deleting photos', 'd' => 'Only to a buyer', 'correct' => 'A'],
                ['question' => 'Why is photo evidence important?', 'a' => 'It supports diagnosis, review, and follow-up', 'b' => 'It replaces all notes', 'c' => 'It grants admin access', 'd' => 'It hides symptoms', 'correct' => 'A'],
                ['question' => 'Farm health monitoring should happen how often?', 'a' => 'Regularly and after major weather or field events', 'b' => 'Only once forever', 'c' => 'Only after sales', 'd' => 'Never', 'correct' => 'A'],
                ['question' => 'Advisory recommendations should be what?', 'a' => 'Practical, safe, evidence-based, and recorded', 'b' => 'Vague and unsafe', 'c' => 'Copied blindly', 'd' => 'Unrelated to the farm', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Coordination & Governance Academy',
            'title' => 'Cooperative Governance and Member Accountability',
            'code' => 'NAT-COOP-GOV-001',
            'course_type' => 'course',
            'description' => 'Cooperative governance course covering member records, approvals, meeting evidence, benefit distribution, dispute handling, reporting, and accountability for coconut farmer groups.',
            'roles' => 'grower,state_coordinator,national_coordinator,admin',
            'is_free' => 0,
            'price' => 10000,
            'certification_required' => 1,
            'certificate_approval_required' => 1,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'prerequisites' => 'Cooperative, grower group, or coordinator responsibility.',
            'offset_days' => 11,
            'assessment_title' => 'Cooperative Governance Assessment',
            'objectives' => ['Maintain transparent cooperative records.', 'Handle member benefits fairly.', 'Escalate disputes through accountable channels.'],
            'checklist' => ['Keep member records updated.', 'Document meetings and decisions.', 'Track benefits and distribution.', 'Record disputes and resolutions.'],
            'questions' => [
                ['question' => 'Cooperative member records should be what?', 'a' => 'Accurate, current, and verifiable', 'b' => 'Hidden and inconsistent', 'c' => 'Only verbal', 'd' => 'Owned by one anonymous person', 'correct' => 'A'],
                ['question' => 'Meeting decisions should be documented why?', 'a' => 'To support accountability and dispute resolution', 'b' => 'To confuse members', 'c' => 'To delete attendance', 'd' => 'To bypass governance', 'correct' => 'A'],
                ['question' => 'Benefit distribution should be based on what?', 'a' => 'Transparent criteria and recorded eligibility', 'b' => 'Random preference', 'c' => 'No records', 'd' => 'Unapproved claims', 'correct' => 'A'],
                ['question' => 'Disputes should be handled through what?', 'a' => 'Documented support or governance workflow', 'b' => 'Hidden messages only', 'c' => 'Deleting users', 'd' => 'Fake certificates', 'correct' => 'A'],
                ['question' => 'Good cooperative governance improves what?', 'a' => 'Trust, reporting, member confidence, and program readiness', 'b' => 'Duplicate records', 'c' => 'Unfair benefits', 'd' => 'Untraceable decisions', 'correct' => 'A'],
            ],
        ],
        [
            'program' => 'Investor & Marketplace Buyer Academy',
            'title' => 'Buyer Procurement and Verified Supply',
            'code' => 'NAT-BUYER-PROC-001',
            'course_type' => 'course',
            'description' => 'Buyer readiness course covering verified supply discovery, RFQ discipline, order evidence, supplier communication, dispute prevention, payment records, and traceability.',
            'roles' => 'buyer,investor,grower,seller',
            'is_free' => 1,
            'price' => 0,
            'certification_required' => 0,
            'certificate_approval_required' => 0,
            'duration_minutes' => 45,
            'pass_score' => 70,
            'prerequisites' => 'Marketplace buyer or investor interest.',
            'offset_days' => 12,
            'assessment_title' => 'Buyer Procurement Readiness Quiz',
            'objectives' => ['Identify verified supply signals.', 'Use RFQs and order evidence properly.', 'Reduce disputes through clear communication.'],
            'checklist' => ['Check seller profile and listing evidence.', 'Use clear quantity, quality, and delivery terms.', 'Keep payment and order references.', 'Raise disputes through official support.'],
            'questions' => [
                ['question' => 'A buyer should check what before ordering?', 'a' => 'Seller profile, product details, quantity, price, and delivery terms', 'b' => 'Only product color', 'c' => 'A private rumor', 'd' => 'Nothing', 'correct' => 'A'],
                ['question' => 'RFQ details should include what?', 'a' => 'Quantity, quality, location, delivery date, and contact requirements', 'b' => 'No requirements', 'c' => 'Another user password', 'd' => 'A blank message', 'correct' => 'A'],
                ['question' => 'Why keep order evidence?', 'a' => 'It supports fulfillment tracking and dispute resolution', 'b' => 'It hides failed delivery', 'c' => 'It bypasses payment records', 'd' => 'It deletes messages', 'correct' => 'A'],
                ['question' => 'Disputes should be raised where?', 'a' => 'Official marketplace/support workflow with evidence', 'b' => 'A random phone number only', 'c' => 'A certificate download page', 'd' => 'Nowhere', 'correct' => 'A'],
                ['question' => 'Verified supply improves what?', 'a' => 'Trust, traceability, planning, and buyer confidence', 'b' => 'Fake listings', 'c' => 'Hidden transactions', 'd' => 'Unclear delivery', 'correct' => 'A'],
            ],
        ],
    ];
}

function academy_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    return trim($value, '-') ?: 'academy-course';
}

function academy_write_course_pdf(string $relativePath, array $course): void
{
    $path = __DIR__ . '/../' . $relativePath;
    if (is_file($path)) {
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $lines = [
        'NATCODEV ACADEMY',
        (string) $course['title'],
        '',
        'Purpose: ' . (string) $course['description'],
        '',
        'Learning Objectives:',
    ];
    foreach ((array) $course['objectives'] as $objective) {
        $lines[] = '- ' . $objective;
    }
    $lines[] = '';
    $lines[] = 'Practical Checklist:';
    foreach ((array) $course['checklist'] as $item) {
        $lines[] = '- ' . $item;
    }
    $lines[] = '';
    $lines[] = 'Assessment: Complete the Academy quiz/exam attached to this course. Passing score: ' . (string) $course['pass_score'] . '%.';
    $lines[] = 'Certificate: ' . ((int) $course['certification_required'] === 1 ? 'Certificate track enabled.' : 'Learning track only.');

    file_put_contents($path, academy_pdf_from_lines($lines), LOCK_EX);
}

function academy_write_video_brief(string $relativePath, array $course): void
{
    $path = __DIR__ . '/../' . $relativePath;
    if (is_file($path)) {
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $slides = array_values(array_merge(
        ['Welcome to ' . (string) $course['title']],
        array_slice((array) $course['objectives'], 0, 3),
        ['Complete the PDF, finish lessons, pass the assessment, then request your certificate.']
    ));
    $slideJson = json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $title = htmlspecialchars((string) $course['title'], ENT_QUOTES, 'UTF-8');
    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} - One Minute Brief</title>
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#102516;color:#fff;display:grid;min-height:100vh;place-items:center}
    main{width:min(920px,92vw);border:1px solid rgba(255,255,255,.2);background:linear-gradient(135deg,#163b24,#245c34);box-shadow:0 24px 70px rgba(0,0,0,.35);padding:42px;border-radius:10px}
    .brand{color:#d7b246;font-weight:800;letter-spacing:3px;text-transform:uppercase}
    h1{font-family:Georgia,serif;font-size:46px;line-height:1.05;margin:18px 0}
    p{font-size:22px;line-height:1.45;color:#edf7ee}
    .bar{height:12px;background:rgba(255,255,255,.18);border-radius:99px;overflow:hidden;margin-top:30px}
    .bar span{display:block;height:100%;width:0;background:#d7b246;animation:fill 60s linear forwards}
    .timer{margin-top:12px;color:#d8e8d8;font-size:14px}
    @keyframes fill{to{width:100%}}
  </style>
</head>
<body>
  <main>
    <div class="brand">NATCODEV Academy Video Brief</div>
    <h1 id="title">{$title}</h1>
    <p id="slide"></p>
    <div class="bar"><span></span></div>
    <div class="timer"><span id="time">00:00</span> / 01:00</div>
  </main>
  <script>
    const slides = {$slideJson};
    const slide = document.getElementById('slide');
    const time = document.getElementById('time');
    const started = Date.now();
    function tick(){
      const elapsed = Math.min(60, Math.floor((Date.now() - started) / 1000));
      const index = Math.min(slides.length - 1, Math.floor(elapsed / Math.max(1, Math.ceil(60 / slides.length))));
      slide.textContent = slides[index];
      time.textContent = '00:' + String(elapsed).padStart(2,'0');
      if (elapsed < 60) requestAnimationFrame(tick);
    }
    tick();
  </script>
</body>
</html>
HTML;
    file_put_contents($path, $html, LOCK_EX);
}
