<?php
declare(strict_types=1);

function academy_seed_assignment_packs(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'webinars') || !app_table_exists($pdo, 'academy_assessments') || !app_table_exists($pdo, 'academy_questions')) {
        return;
    }

    $courses = $pdo->query("
        SELECT w.id, w.title, w.description, w.target_roles, w.category, w.pass_score, w.is_free, p.title program_title
        FROM webinars w
        LEFT JOIN academy_programs p ON p.id = w.program_id
        WHERE COALESCE(w.status, 'active') = 'active'
        ORDER BY w.id ASC
    ")->fetchAll();

    $assessmentInsert = $pdo->prepare("
        INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
        VALUES (?, ?, ?, ?, 3, 'active')
        ON DUPLICATE KEY UPDATE instructions = VALUES(instructions), pass_score = VALUES(pass_score), max_attempts = VALUES(max_attempts), status = 'active'
    ");
    $assessmentLookup = $pdo->prepare("
        SELECT id
        FROM academy_assessments
        WHERE webinar_id = ? AND status = 'active'
        ORDER BY id ASC
        LIMIT 1
    ");
    $questionCount = $pdo->prepare("SELECT COUNT(*) FROM academy_questions WHERE assessment_id = ? AND status = 'active'");
    $questionInsert = $pdo->prepare("
        INSERT INTO academy_questions
            (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, rationale, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            option_a = VALUES(option_a),
            option_b = VALUES(option_b),
            option_c = VALUES(option_c),
            option_d = VALUES(option_d),
            correct_option = VALUES(correct_option),
            points = VALUES(points),
            rationale = VALUES(rationale),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");

    foreach ($courses as $course) {
        $courseId = (int) $course['id'];
        $isFree = (int) ($course['is_free'] ?? 1) === 1;
        $requiredCount = $isFree ? 10 : 25;
        $category = (string) ($course['category'] ?: academy_inferred_course_category($course));
        $title = trim((string) ($course['title'] ?? 'Academy Course'));

        $assessmentLookup->execute([$courseId]);
        $assessmentId = (int) $assessmentLookup->fetchColumn();
        if ($assessmentId <= 0) {
            $assessmentInsert->execute([
                $courseId,
                $title . ' Assessment Pack',
                'Complete all questions carefully. Passing this assessment verifies competency and unlocks certificate eligibility.',
                max(70, (float) ($course['pass_score'] ?? 70)),
            ]);
            $assessmentLookup->execute([$courseId]);
            $assessmentId = (int) $assessmentLookup->fetchColumn();
        }
        if ($assessmentId <= 0) {
            continue;
        }

        $questionCount->execute([$assessmentId]);
        $existing = (int) $questionCount->fetchColumn();
        if ($existing >= $requiredCount) {
            continue;
        }

        $needed = $requiredCount - $existing;
        $existingQStmt = $pdo->prepare("SELECT question_text FROM academy_questions WHERE assessment_id = ?");
        $existingQStmt->execute([$assessmentId]);
        $existingTexts = array_map(static fn($row) => mb_substr(mb_strtolower(trim((string) $row['question_text'])), 0, 180), $existingQStmt->fetchAll(PDO::FETCH_ASSOC));

        $questions = academy_assignment_pack_questions($course, $requiredCount + 20);
        $sort = ($existing * 10) + 10;
        $added = 0;
        foreach ($questions as $question) {
            if ($added >= $needed) {
                break;
            }
            $prefix = mb_substr(mb_strtolower(trim((string) $question['question'])), 0, 180);
            if (in_array($prefix, $existingTexts, true)) {
                continue;
            }
            $existingTexts[] = $prefix;
            $questionInsert->execute([
                $assessmentId,
                $question['question'],
                $question['a'],
                $question['b'],
                $question['c'],
                $question['d'],
                $question['correct'],
                $question['rationale'] ?? 'Option ' . $question['correct'] . ' is correct according to the curriculum and platform operational standards.',
                $sort,
            ]);
            $sort += 10;
            $added++;
        }
    }
}

/**
 * Generates an extensive bank of realistic, domain-relevant multiple-choice questions
 * with options A-D, correct option, points, and pedagogical rationales.
 */
function academy_assignment_pack_questions(array $course, int $limit = 25): array
{
    $title = trim((string) ($course['title'] ?? 'this course')) ?: 'this course';
    $category = trim((string) ($course['category'] ?? academy_inferred_course_category($course))) ?: 'Professional Skills';
    $roles = academy_role_labels((string) ($course['target_roles'] ?? 'all'));

    $templates = [
        // 1 - 5
        [
            'question' => 'What is the primary professional outcome expected from {course}?',
            'a' => 'Apply the course skills accurately in the NATCODEV platform workflow',
            'b' => 'Skip the course and request a certificate immediately',
            'c' => 'Use another learner account for evidence',
            'd' => 'Ignore assessment instructions and proceed to payout',
            'correct' => 'A',
            'rationale' => 'Every course is designed to equip learners with actionable competencies to execute verifiable platform workflows with integrity.',
        ],
        [
            'question' => 'Which operational record best demonstrates readiness in {category}?',
            'a' => 'Completed lessons, submitted assessment, and traceable platform activity',
            'b' => 'A blank learner profile with unverified claims',
            'c' => 'A downloaded sample certificate template',
            'd' => 'An unrelated public social media comment',
            'correct' => 'A',
            'rationale' => 'Traceable platform records prove that practical competencies were studied, tested, and validated.',
        ],
        [
            'question' => 'Who is the authorized target audience for {course}?',
            'a' => '{roles}',
            'b' => 'Only anonymous public website visitors',
            'c' => 'Deactivated or suspended user accounts',
            'd' => 'External parties without platform registration',
            'correct' => 'A',
            'rationale' => 'Course enrollment and role permissions are scoped to designated stakeholder profiles to maintain professional standards.',
        ],
        [
            'question' => 'What requirement must be satisfied before requesting a certificate for {course}?',
            'a' => 'Complete all required lessons and achieve at least the passing mark on the assessment',
            'b' => 'Open the certificate request form immediately after enrollment',
            'c' => 'Submit empty answers to bypass the examination',
            'd' => 'Present an informal referral letter from a third party',
            'correct' => 'A',
            'rationale' => 'Certificate issuance requires both 100% required lesson completion and a passing assessment score.',
        ],
        [
            'question' => 'Why are multiple assessment attempts recorded and tracked for {course}?',
            'a' => 'To ensure evaluation scores, pass status, and audit records remain verifiable',
            'b' => 'To delete user registration records upon failure',
            'c' => 'To bypass course curriculum requirements',
            'd' => 'To grant immediate administrative access',
            'correct' => 'A',
            'rationale' => 'Attempt tracking provides a permanent audit trail for compliance, quality assurance, and credential verification.',
        ],

        // 6 - 10
        [
            'question' => 'Which action reflects standard operating practice in {category}?',
            'a' => 'Ensure data accuracy, adhere to sequence guidelines, and document evidence',
            'b' => 'Record fabricated figures to meet deadlines faster',
            'c' => 'Bypass platform communication channels in favor of untracked private chats',
            'd' => 'Erase historical logs whenever discrepancies appear',
            'correct' => 'A',
            'rationale' => 'Reliable data collection and adherence to operational standards safeguard the cooperative network.',
        ],
        [
            'question' => 'If a candidate fails the {course} assessment, what is the required next step?',
            'a' => 'Review course modules and retake the assessment within the allowed attempt limit',
            'b' => 'Demand immediate certificate approval from platform support',
            'c' => 'Modify another user account to claim their completed score',
            'd' => 'Attempt to bypass the quiz gate by editing the browser URL',
            'correct' => 'A',
            'rationale' => 'Candidates who do not achieve the pass mark must review the lesson materials and retake the exam within allowable limits.',
        ],
        [
            'question' => 'What key benefit does this {category} module deliver to the NATCODEV ecosystem?',
            'a' => 'Connects structured learning to verifiable field, marketplace, and governance decisions',
            'b' => 'Eliminates the requirement for real-world farm monitoring',
            'c' => 'Allows participants to operate without administrative accountability',
            'd' => 'Replaces official agricultural extension services entirely',
            'correct' => 'A',
            'rationale' => 'Academy modules bridge theoretical principles with daily platform workflows, enhancing operational reliability.',
        ],
        [
            'question' => 'Which support channel should be utilized when instructions in {course} require clarification?',
            'a' => 'The official logged-in Academy Help and Support desk',
            'b' => 'Public unmonitored online forums',
            'c' => 'Direct financial transaction reference remarks',
            'd' => 'Unverified third-party telephone lines',
            'correct' => 'A',
            'rationale' => 'The dedicated Help and Support desk ensures inquiries are tracked, escalated, and resolved by authorized personnel.',
        ],
        [
            'question' => 'What feedback is displayed immediately following a valid assessment submission for {course}?',
            'a' => 'Calculated score percentage, pass/fail status, and updated certificate eligibility',
            'b' => 'A completely blank browser screen',
            'c' => 'An unauthorized redirection to external websites',
            'd' => 'Immediate removal of the learner profile',
            'correct' => 'A',
            'rationale' => 'The quiz engine computes the score, registers the attempt in the database, and updates registration status.',
        ],

        // 11 - 15
        [
            'question' => 'Why are quiz questions in {course} validated directly against the secure database?',
            'a' => 'To guarantee tamper-proof scoring, audited attempts, and trustworthy credentials',
            'b' => 'To ensure question answers change at random during the test',
            'c' => 'To prevent learners from accessing course notes afterward',
            'd' => 'To automatically grant passing marks to any enrolled user',
            'correct' => 'A',
            'rationale' => 'Server-side evaluation prevents client manipulation and protects the institutional credibility of certificates.',
        ],
        [
            'question' => 'What does successful certification in {course} confirm to cooperatives and buyers?',
            'a' => 'Demonstrated competence to execute {category} tasks according to national protocols',
            'b' => 'Exemption from all ongoing platform compliance audits',
            'c' => 'Unrestricted access to master platform administration tools',
            'd' => 'Permanent ownership of agricultural assets without verification',
            'correct' => 'A',
            'rationale' => 'Certificates confirm that the holder has mastered the requisite technical competencies and ethical responsibilities.',
        ],
        [
            'question' => 'When capturing field or transactional data in {category}, why is timestamping mandatory?',
            'a' => 'To establish a chronological audit trail verifying when the action actually occurred',
            'b' => 'To artificially speed up processing queues without verification',
            'c' => 'To prevent growers from accessing their own farm records',
            'd' => 'To override official geospatial coordinates',
            'correct' => 'A',
            'rationale' => 'Accurate timestamps prevent backdating and establish evidence chains for farm visits, payments, and audits.',
        ],
        [
            'question' => 'What constitutes an ethical breach during the execution of {category} responsibilities?',
            'a' => 'Soliciting unofficial fees, fabricating verification evidence, or sharing private user data',
            'b' => 'Escalating an unresolved field hazard to a superior coordinator',
            'c' => 'Requesting technical assistance via the Academy Help desk',
            'd' => 'Retaking an assessment to improve an evaluation score',
            'correct' => 'A',
            'rationale' => 'Zero-tolerance integrity policies strictly prohibit bribery, data falsification, and unauthorized disclosure.',
        ],
        [
            'question' => 'How does accurate data entry in {course} protect value chain participants?',
            'a' => 'Provides reliable intelligence for input planning, logistics dispatch, and fair financial settlement',
            'b' => 'Guarantees that produce prices remain fixed regardless of quality grade',
            'c' => 'Eliminates the necessity of physical delivery inspections',
            'd' => 'Allows providers to sell uncertified seed nuts without inspection',
            'correct' => 'A',
            'rationale' => 'High-fidelity records allow cooperatives, buyers, and processors to plan procurement and finance accurately.',
        ],

        // 16 - 20
        [
            'question' => 'Which measure must be taken if inconsistent or fraudulent information is encountered in {category}?',
            'a' => 'Document the precise discrepancies and escalate through the formal governance reporting channel',
            'b' => 'Approve the record anyway to maintain fast turnaround statistics',
            'c' => 'Conceal the discrepancy from field inspectors and supervisors',
            'd' => 'Delete the entire database row without creating an audit record',
            'correct' => 'A',
            'rationale' => 'Clear documentation and formal escalation enable compliance officers to investigate and prevent systemic fraud.',
        ],
        [
            'question' => 'Why must operational checklists be strictly adhered to during {category} workflows?',
            'a' => 'They standardize quality control and eliminate human oversight on critical safety steps',
            'b' => 'They are decorative guidelines that can be disregarded at discretion',
            'c' => 'They prevent learners from completing courses ahead of schedule',
            'd' => 'They replace the need for physical farm equipment maintenance',
            'correct' => 'A',
            'rationale' => 'Checklists ensure repeatability, safety compliance, and thorough verification across diverse field teams.',
        ],
        [
            'question' => 'In the context of {course}, what is the role of continuous refresher training?',
            'a' => 'Maintains alignment with evolving agronomic standards, regulatory updates, and platform tools',
            'b' => 'Forces participants to pay recurring registration fees unnecessarily',
            'c' => 'Replaces annual farm harvest and financial audits',
            'd' => 'Slows down cooperative operational throughput',
            'correct' => 'A',
            'rationale' => 'Refresher training ensures stakeholders remain up-to-date with pest advisories, market standards, and system features.',
        ],
        [
            'question' => 'How should disputes between stakeholders in {category} be systematically resolved?',
            'a' => 'Through transparent examination of recorded timestamps, contracts, and submitted evidence',
            'b' => 'By ruling in favor of whichever party submitted the highest financial transaction',
            'c' => 'By discarding all historical transaction evidence and closing the case',
            'd' => 'Through unilateral decisions without hearing counterparty statements',
            'correct' => 'A',
            'rationale' => 'Evidence-based conflict resolution protects platform trust and ensures equitable outcomes for growers and buyers.',
        ],
        [
            'question' => 'What is the consequence of failing to observe safety or environmental regulations in {category}?',
            'a' => 'Increased risk of worker injury, environmental degradation, and loss of accreditation',
            'b' => 'Automatic upgrade to certified supervisor status',
            'c' => 'Reduced input costs with zero operational consequences',
            'd' => 'Immediate waiver of all platform compliance requirements',
            'correct' => 'A',
            'rationale' => 'Compliance with environmental and safety standards protects workers, soil health, and long-term agricultural sustainability.',
        ],

        // 21 - 25
        [
            'question' => 'Why is geographic referencing (State, LGA, GPS) critical to {category} operations?',
            'a' => 'Enables spatial planning, logistics routing, disease quarantine, and localized extension support',
            'b' => 'Serves only as visual decoration on public profile pages',
            'c' => 'Prevents growers in remote areas from accessing platform benefits',
            'd' => 'Overrides personal identification requirements',
            'correct' => 'A',
            'rationale' => 'Accurate location boundaries ensure field teams, logistics providers, and advisors can reach farms efficiently.',
        ],
        [
            'question' => 'What standard must documentation meet to be considered admissible evidence in {course}?',
            'a' => 'Clear legibility, unbroken chain of custody, verifiable timestamps, and relevant context notes',
            'b' => 'Low-resolution captures with missing margins and obscured text',
            'c' => 'Verbal affirmations recorded without physical or digital artifacts',
            'd' => 'Stock photographs copied from external internet repositories',
            'correct' => 'A',
            'rationale' => 'Defensible documentation requires legible images, daylight captures, and objective contextual captions.',
        ],
        [
            'question' => 'How does the NATCODEV wallet integrate with {category} operational requirements?',
            'a' => 'Provides transparent, auditable settlement for training fees, input purchases, and service payouts',
            'b' => 'Allows untracked cash transfers outside platform auditing systems',
            'c' => 'Replaces national banking regulations entirely',
            'd' => 'Eliminates the requirement for transaction receipts',
            'correct' => 'A',
            'rationale' => 'The embedded wallet ensures all financial interactions remain auditable, reconciled, and secure.',
        ],
        [
            'question' => 'What is the primary indicator of operational excellence in {category} execution?',
            'a' => 'Consistent delivery of agreed standards, zero unverified claims, and transparent reporting',
            'b' => 'Maximizing short-term revenue by cutting essential quality checks',
            'c' => 'Avoiding customer communication until escalations occur',
            'd' => 'Filing incomplete reports to save administrative time',
            'correct' => 'A',
            'rationale' => 'Consistency, veracity, and accountability form the triple bottom line of professional agricultural enterprise.',
        ],
        [
            'question' => 'What does this comprehensive evaluation in {course} ultimately signify?',
            'a' => 'Rigorous verification that the learner possesses the domain knowledge to perform at industry standards',
            'b' => 'A ceremonial hurdle with no relevance to practical platform operations',
            'c' => 'A method to restrict platform membership to academic researchers only',
            'd' => 'An automated process that bypasses human review requirements',
            'correct' => 'A',
            'rationale' => 'Comprehensive 25-question assessments in paid curricula certify that candidates possess defensible domain proficiency.',
        ],

        // 26 - 32 extra domain-specific templates
        [
            'question' => 'When executing tasks associated with {course}, how should unexpected field contingencies be managed?',
            'a' => 'Assess safety risks, apply established contingency protocols, and notify the designated coordinator',
            'b' => 'Abandon the assignment without filing a status report',
            'c' => 'Improvise unapproved chemical mixtures to resolve the issue quickly',
            'd' => 'Disregard the contingency and report nominal completion',
            'correct' => 'A',
            'rationale' => 'Risk assessment and standard protocol compliance prevent accidents and preserve data integrity during anomalies.',
        ],
        [
            'question' => 'Which practice best preserves confidentiality and data privacy across {category} workflows?',
            'a' => 'Accessing learner or farmer records solely through encrypted platform credentials for legitimate tasks',
            'b' => 'Sharing user phone numbers and national identification documents in public group chats',
            'c' => 'Leaving open devices unattended in public internet cafes',
            'd' => 'Exporting proprietary cooperative databases to personal unsecured storage drives',
            'correct' => 'A',
            'rationale' => 'Data privacy standards mandate secure access, encryption, and strict need-to-know access boundaries.',
        ],
        [
            'question' => 'Why must equipment and calibration tools used in {category} undergo periodic maintenance?',
            'a' => 'To guarantee measurement precision, operator safety, and reliable diagnostic conclusions',
            'b' => 'To increase operating expenses unnecessarily',
            'c' => 'Because equipment degrades automatically after every single use',
            'd' => 'To satisfy manufacturer warranty forms without operational benefit',
            'correct' => 'A',
            'rationale' => 'Calibrated instruments prevent costly diagnostic errors in soil testing, GPS surveying, and chemical dosing.',
        ],
        [
            'question' => 'What is the standard procedure when a conflict of interest arises in {course} activities?',
            'a' => 'Formally disclose the conflict to platform governance and recuse oneself from the evaluation',
            'b' => 'Conceal the conflict to expedite family or business transactions',
            'c' => 'Direct platform inquiries away from the affected records',
            'd' => 'Offer discounted rates to offset the conflict of interest',
            'correct' => 'A',
            'rationale' => 'Transparency and recusal protect institutional integrity and prevent biased verification or accreditation outcomes.',
        ],
        [
            'question' => 'In {category}, how does systematic yield and progress tracking benefit smallholder farmers?',
            'a' => 'Highlights productivity bottlenecks, optimizes input timing, and supports agricultural credit eligibility',
            'b' => 'Guarantees that smallholders will never experience adverse weather effects',
            'c' => 'Forces smallholders to surrender farm ownership to input providers',
            'd' => 'Replaces the need for certified seedling procurement',
            'correct' => 'A',
            'rationale' => 'Verifiable farm records unlock credit, insurance, and agronomic interventions that improve smallholder yields.',
        ],

        // 31 - 35
        [
            'question' => 'What is the required moisture threshold for premium milling copra in {category} processing?',
            'a' => 'Between 6% and 7% moisture content to prevent aflatoxin contamination and rancidity',
            'b' => 'Above 25% moisture content to maximize bulk weight at market scales',
            'c' => 'Any moisture level as long as the copra husk is completely removed',
            'd' => 'Moisture measurement is not applicable to commercial coconut oil extraction',
            'correct' => 'A',
            'rationale' => 'Copra dried below 7% moisture prevents fungal growth, aflatoxins, and free fatty acid degradation during storage and processing.',
        ],
        [
            'question' => 'How does batch-level QR and barcode tagging safeguard produce traceability in {course}?',
            'a' => 'Links physical crates and bags directly to registered farm coordinates, harvest dates, and cooperative IDs',
            'b' => 'Replaces official shipping bills with unverified promotional stickers',
            'c' => 'Hides producer identities to avoid post-sale quality accountability',
            'd' => 'Permits uninspected commodity mixing across unrelated collection centres',
            'correct' => 'A',
            'rationale' => 'Item and batch level traceability enables rapid provenance verification, dispute settlement, and export compliance verification.',
        ],
        [
            'question' => 'What safety protocol governs agrochemical pre-harvest intervals (PHI) in {category}?',
            'a' => 'Strictly observe the labelled withholding period between last chemical application and fruit harvesting',
            'b' => 'Harvest immediately following pesticide application to lock in active residues',
            'c' => 'Double the chemical dosage on the morning of harvest to kill transport pests',
            'd' => 'Pre-harvest intervals apply only to cereal crops, not tree crops like coconuts',
            'correct' => 'A',
            'rationale' => 'Adhering to pre-harvest intervals prevents toxic pesticide residues in coconut water, meat, and consumer derivatives.',
        ],
        [
            'question' => 'When conducting soil nutrient diagnostics in {category}, why is ring-basin sampling required?',
            'a' => 'Captures nutrient levels within the active feeder-root zone situated 1.5 to 2 meters from the palm bole',
            'b' => 'Samples soil exclusively from adjacent road drainage ditches',
            'c' => 'Prevents agronomists from disturbing topsoil biology during testing',
            'd' => 'Substitutes for leaf tissue analysis without requiring laboratory calibration',
            'correct' => 'A',
            'rationale' => 'Feeder roots concentrate in the inner drip ring; sampling this zone provides accurate indicators of palm nutrient uptake.',
        ],
        [
            'question' => 'What nursery potting mixture standard is mandatory for raising healthy coconut seedlings in {course}?',
            'a' => 'A friable 1:1:1 mixture of decomposed organic compost, well-draining river sand, and fertile topsoil',
            'b' => 'Pure heavy river clay with zero drainage or aeration capacity',
            'c' => 'Uncomposted raw livestock manure mixed with construction rubble',
            'd' => 'Untreated ocean beach sand high in soluble sodium chloride salts',
            'correct' => 'A',
            'rationale' => 'Proper aeration, organic fertility, and drainage encourage robust root emergence and reduce seedling collar rot in nurseries.',
        ],

        // 36 - 40
        [
            'question' => 'What is the recommended integrated pest management (IPM) strategy for Rhinoceros Beetle in {category}?',
            'a' => 'Scout crowns, extract beetles with hooked wires, deploy pheromone traps, and treat breeding logs with Oryctes virus or Metarhizium fungus',
            'b' => 'Cut down and burn all mature palms showing minimal frond V-cuts',
            'c' => 'Apply high volumes of untreated sewage sludge directly into palm crowns',
            'd' => 'Ignore beetle damage because palms recover automatically with zero yield loss',
            'correct' => 'A',
            'rationale' => 'IPM combines physical extraction, sanitation of dead rotting wood breeding sites, and biological control agents to curb infestations.',
        ],
        [
            'question' => 'How does learner wallet settlement protect payments and dispute resolution in {course}?',
            'a' => 'Holds transaction funds in verified escrow until services, inputs, or courses are confirmed and verified',
            'b' => 'Allows buyers to withdraw escrow funds unilaterally after receiving verified shipments',
            'c' => 'Eliminates electronic receipt generation and payment auditing',
            'd' => 'Diverts platform transactions to unmonitored private bank accounts',
            'correct' => 'A',
            'rationale' => 'Platform wallet escrow guarantees payment security for sellers and protects buyers against non-delivery through structured settlement.',
        ],
        [
            'question' => 'What constitutes an accurate GPS boundary fix during a farm audit in {category}?',
            'a' => 'Multi-point perimeter waypoint recording taken under open sky with accuracy stable below 5 meters',
            'b' => 'A single estimated point captured from inside a moving vehicle on a nearby highway',
            'c' => 'Coordinates copied manually from an unverified public map illustration',
            'd' => 'Approximate coordinates entered by eye without satellite receiver locks',
            'correct' => 'A',
            'rationale' => 'Open-sky perimeter tracking below 5-meter tolerance ensures accurate acreage computation and avoids parcel boundary disputes.',
        ],
        [
            'question' => 'Why must intercropping around immature coconut palms in {course} preserve the root basin?',
            'a' => 'Prevents shallow secondary crops from competing for water, potassium, and phosphorus with developing palm roots',
            'b' => 'Ensures weeds grow tall enough to shade palm fronds from direct sunlight',
            'c' => 'Allows commercial trucks to park directly over seedling basins',
            'd' => 'Intercrops must never be planted anywhere on the same plantation',
            'correct' => 'A',
            'rationale' => 'Keeping a 1.5m weed-free, uncompetitive root basin ensures juvenile palms develop strong boles and rapid root architecture.',
        ],
        [
            'question' => 'What procedure applies when an off-taker disputes produce quality upon arrival in {category}?',
            'a' => 'Inspect the signed dispatch seal, joint-sample the consignment against the grade contract, and log evidence in support',
            'b' => 'Unconditionally discard the entire shipment without photographing defects or sampling',
            'c' => 'Alter the signed dispatch delivery note to match the buyer\'s verbal complaint',
            'd' => 'Lock the seller out of the marketplace without investigation or arbitration',
            'correct' => 'A',
            'rationale' => 'Objective arbitration relies on dispatch inspection logs, retained reference samples, and contractual grade specifications.',
        ],
    ];

    $seed = abs((int) crc32((string) ($course['id'] ?? '') . '|' . $title . '|' . $category));
    usort($templates, static function (array $a, array $b) use ($seed): int {
        return (crc32($a['question'] . $seed) <=> crc32($b['question'] . $seed));
    });

    $letters = ['A', 'B', 'C', 'D'];
    $questions = [];
    foreach (array_slice($templates, 0, $limit) as $idx => $template) {
        $q = array_map(static function ($value) use ($title, $category, $roles) {
            if (!is_string($value)) {
                return $value;
            }
            return str_replace(['{course}', '{category}', '{roles}'], [$title, $category, $roles], $value);
        }, $template);

        $options = [$q['a'], $q['b'], $q['c'], $q['d']];
        $correctText = $options[0];
        $distractors = array_slice($options, 1);
        $targetIndex = ($seed + $idx) % 4;
        array_splice($distractors, $targetIndex, 0, [$correctText]);
        $correctLetter = $letters[$targetIndex];

        $q['a'] = $distractors[0];
        $q['b'] = $distractors[1];
        $q['c'] = $distractors[2];
        $q['d'] = $distractors[3];
        $q['correct'] = $correctLetter;
        $q['points'] = 1.0;
        $origRat = (string) ($q['rationale'] ?? '');
        $q['rationale'] = "Option {$correctLetter} is correct: " . (preg_replace('/^Option [A-D] is correct:?\s*/i', '', $origRat) ?: "adheres to platform standard operating procedure.");

        $questions[] = $q;
    }

    return $questions;
}
