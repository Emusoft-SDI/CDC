<?php
declare(strict_types=1);

function academy_seed_certificate_pathways(PDO $pdo): void
{
    $pathways = academy_certificate_pathway_seed_data();
    if (!$pathways || academy_certificate_pathways_seeded($pdo, $pathways)) {
        return;
    }

    $courseRows = $pdo->query("SELECT id, course_code FROM webinars WHERE course_code IS NOT NULL AND course_code <> ''")->fetchAll();
    $courses = [];
    foreach ($courseRows as $row) {
        $courses[(string) $row['course_code']] = (int) $row['id'];
    }

    $groupStmt = $pdo->prepare("
        INSERT INTO academy_certificate_groups
            (title, description, audience_roles, certificate_approval_required, status, sort_order)
        VALUES (?, ?, ?, ?, 'active', ?)
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            audience_roles = VALUES(audience_roles),
            certificate_approval_required = VALUES(certificate_approval_required),
            status = 'active',
            sort_order = VALUES(sort_order)
    ");
    $lookup = $pdo->prepare("SELECT id FROM academy_certificate_groups WHERE title = ? LIMIT 1");
    $deleteCourses = $pdo->prepare("DELETE FROM academy_certificate_group_courses WHERE group_id = ?");
    $insertCourse = $pdo->prepare("
        INSERT INTO academy_certificate_group_courses (group_id, webinar_id, is_required, sort_order)
        VALUES (?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE is_required = VALUES(is_required), sort_order = VALUES(sort_order)
    ");

    foreach ($pathways as $pathway) {
        $courseIds = [];
        foreach ((array) $pathway['course_codes'] as $code) {
            if (!empty($courses[$code])) {
                $courseIds[] = $courses[$code];
            }
        }
        if (!$courseIds) {
            continue;
        }
        $groupStmt->execute([
            (string) $pathway['title'],
            (string) $pathway['description'],
            (string) $pathway['roles'],
            (int) $pathway['approval_required'],
            (int) $pathway['sort_order'],
        ]);
        $lookup->execute([(string) $pathway['title']]);
        $groupId = (int) $lookup->fetchColumn();
        if ($groupId <= 0) {
            continue;
        }
        $deleteCourses->execute([$groupId]);
        foreach ($courseIds as $index => $courseId) {
            $insertCourse->execute([$groupId, $courseId, ($index + 1) * 10]);
        }
    }
}

function academy_certificate_pathways_seeded(PDO $pdo, array $pathways): bool
{
    if (!app_table_exists($pdo, 'academy_certificate_groups') || !app_table_exists($pdo, 'academy_certificate_group_courses')) {
        return false;
    }
    $titles = array_map(static fn(array $row): string => (string) $row['title'], $pathways);
    if (!$titles) {
        return true;
    }
    $placeholders = implode(',', array_fill(0, count($titles), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT g.id) groups_count, COUNT(gc.id) course_links
        FROM academy_certificate_groups g
        LEFT JOIN academy_certificate_group_courses gc ON gc.group_id = g.id
        WHERE g.title IN ({$placeholders}) AND g.status = 'active'
    ");
    $stmt->execute($titles);
    $row = $stmt->fetch() ?: [];
    $requiredLinks = array_sum(array_map(static fn(array $pathway): int => count((array) $pathway['course_codes']), $pathways));

    return (int) ($row['groups_count'] ?? 0) >= count($pathways)
        && (int) ($row['course_links'] ?? 0) >= $requiredLinks;
}

function academy_certificate_pathway_seed_data(): array
{
    return [
        [
            'title' => 'Certified NATCODEV Provider Pathway',
            'description' => 'Grouped credential for providers who complete accreditation readiness and marketplace seller conduct. Best for input suppliers, service providers, and provider-sellers who need one stronger operational certificate.',
            'roles' => 'provider,input_provider,service_provider,seller',
            'approval_required' => 1,
            'sort_order' => 10,
            'course_codes' => ['NAT-PROV-ACC-001', 'NAT-SELL-CERT-001'],
        ],
        [
            'title' => 'Certified NATCODEV Field Agent Pathway',
            'description' => 'Grouped credential for field agents and advisory workers. It combines grower onboarding context, farm safety awareness, and field verification certification so field staff understand the people, farms, and evidence workflow they support.',
            'roles' => 'field_agent,agronomist,agric_extensionist',
            'approval_required' => 1,
            'sort_order' => 20,
            'course_codes' => ['NAT-GROW-ONB-001', 'NAT-FH-SAFE-001', 'NAT-FIELD-CERT-001'],
        ],
        [
            'title' => 'Certified State Coordinator Operations Pathway',
            'description' => 'Grouped credential for state coordinators who must understand grower onboarding, field evidence, provider/seller operations, and state/LGA operational governance before coordinating production-scoped work.',
            'roles' => 'state_coordinator,admin,super_admin',
            'approval_required' => 1,
            'sort_order' => 30,
            'course_codes' => ['NAT-GROW-ONB-001', 'NAT-FIELD-CERT-001', 'NAT-PROV-ACC-001', 'NAT-SCO-OPS-001'],
        ],
        [
            'title' => 'Certified National Coordinator Governance Pathway',
            'description' => 'Grouped credential for national coordination and senior governance roles. It covers the full operational chain: growers, field teams, providers, sellers, state coordination, reporting discipline, and certificate governance.',
            'roles' => 'national_coordinator,admin,super_admin',
            'approval_required' => 1,
            'sort_order' => 40,
            'course_codes' => ['NAT-GROW-ONB-001', 'NAT-FH-SAFE-001', 'NAT-FIELD-CERT-001', 'NAT-PROV-ACC-001', 'NAT-SELL-CERT-001', 'NAT-SCO-OPS-001'],
        ],
    ];
}
