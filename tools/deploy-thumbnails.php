<?php
declare(strict_types=1);

$srcDir = 'C:/Users/user/.gemini/antigravity-cli/brain/e295aa8d-2944-4433-9ebb-fe33747a29c1';
$baseDir = dirname(__DIR__);
$dst1 = $baseDir . '/assets/academy/thumbnails';
$dst2 = $baseDir . '/academy_uploads/thumbnails';

if (!is_dir($dst1)) {
    mkdir($dst1, 0777, true);
}
if (!is_dir($dst2)) {
    mkdir($dst2, 0777, true);
}

$items = [
    [
        'src' => 'academy_grower_onboard_1791935520571.jpg',
        'names' => ['course-1.jpg', 'coconut-grower-digital-onboarding.jpg', 'grower-onboarding-program.jpg', 'grower-onboarding-program.png'],
    ],
    [
        'src' => 'academy_reg_quality_1791935545739.jpg',
        'names' => ['course-2.jpg', 'natcodev-registry-data-quality-basics.jpg'],
    ],
    [
        'src' => 'academy_farmhand_safe_1791935572772.jpg',
        'names' => ['course-3.jpg', 'farm-hand-safety-and-field-task-orientation.jpg', 'farm-hand-safety-program.jpg', 'farm-hand-safety-program.png'],
    ],
    [
        'src' => 'academy_prov_accred_1791935603212.jpg',
        'names' => ['course-4.jpg', 'provider-registration-and-accreditation-readiness.jpg', 'provider-accreditation-program.jpg', 'provider-accreditation-program.png'],
    ],
    [
        'src' => 'academy_mkt_clinic_1791935635849.jpg',
        'names' => ['course-5.jpg', 'marketplace-buyer-and-seller-starter-clinic.jpg', 'cooperative-marketplace-clinic.jpg', 'marketplace-seller-certification-program.jpg', 'marketplace-seller-certification-program.png'],
    ],
    [
        'src' => 'academy_ops_mgr_1791935674135.jpg',
        'names' => ['course-6.jpg', 'certified-coconut-farm-operations-manager.jpg'],
    ],
    [
        'src' => 'academy_field_cert_1791935714464.jpg',
        'names' => ['course-7.jpg', 'field-agent-verification-and-gps-evidence-certification.jpg', 'field-agent-certification-program.jpg', 'field-agent-certification-program.png'],
    ],
    [
        'src' => 'academy_agro_cert_1791935762452.jpg',
        'names' => ['course-8.jpg', 'agronomy-advisory-case-management-certification.jpg'],
    ],
];

$copied = 0;
foreach ($items as $item) {
    $srcPath = $srcDir . '/' . $item['src'];
    if (!file_exists($srcPath)) {
        echo "Source file missing: {$srcPath}\n";
        continue;
    }
    foreach ($item['names'] as $name) {
        copy($srcPath, $dst1 . '/' . $name);
        copy($srcPath, $dst2 . '/' . $name);
        $copied++;
    }
}

echo "Successfully deployed {$copied} thumbnail files.\n";
