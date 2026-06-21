<?php

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$now = date('Y-m-d H:i:s');
$act = 'natcodev_coconut_loan_application';

$formData = [
    'farm_location' => [
        'name' => 'Farm Location',
        'label' => 'farm_location',
        'is_required' => 'required',
        'extensions' => '',
        'options' => [],
        'type' => 'text',
    ],
    'farm_size_hectares' => [
        'name' => 'Farm Size Hectares',
        'label' => 'farm_size_hectares',
        'is_required' => 'required',
        'extensions' => '',
        'options' => [],
        'type' => 'text',
    ],
    'coconut_variety' => [
        'name' => 'Coconut Variety',
        'label' => 'coconut_variety',
        'is_required' => 'required',
        'extensions' => '',
        'options' => ['Dwarf Coconut', 'Hybrid Coconut', 'Tall Coconut', 'Mixed Farm'],
        'type' => 'select',
    ],
    'loan_purpose' => [
        'name' => 'Loan Purpose',
        'label' => 'loan_purpose',
        'is_required' => 'required',
        'extensions' => '',
        'options' => ['Seedlings', 'Fertilizer', 'Irrigation', 'Farm Labour', 'Processing Equipment', 'Harvest Logistics'],
        'type' => 'select',
    ],
    'repayment_source' => [
        'name' => 'Repayment Source',
        'label' => 'repayment_source',
        'is_required' => 'required',
        'extensions' => '',
        'options' => [],
        'type' => 'textarea',
    ],
    'group_guarantee_or_collateral' => [
        'name' => 'Group Guarantee Or Collateral',
        'label' => 'group_guarantee_or_collateral',
        'is_required' => 'optional',
        'extensions' => '',
        'options' => [],
        'type' => 'textarea',
    ],
];

$json = json_encode($formData, JSON_UNESCAPED_SLASHES);

$stmt = $pdo->prepare('SELECT id FROM forms WHERE act = ? LIMIT 1');
$stmt->execute([$act]);
$formId = $stmt->fetchColumn();

if ($formId) {
    $stmt = $pdo->prepare('UPDATE forms SET form_data = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$json, $now, $formId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO forms (act, form_data, created_at, updated_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$act, $json, $now, $now]);
    $formId = $pdo->lastInsertId();
}

$stmt = $pdo->prepare(
    "UPDATE loan_plans
     SET form_id = ?, instruction = ?
     WHERE form_id IS NULL OR form_id = 0 OR name LIKE '%Coconut%'"
);
$stmt->execute([
    $formId,
    'Complete this cooperative application with current coconut farm details. NATCODEV uses the information to review input financing, dwarf coconut establishment support, and harvest-linked repayment capacity.',
]);

echo "Loan application form {$formId} attached to {$stmt->rowCount()} loan plan(s)." . PHP_EOL;

