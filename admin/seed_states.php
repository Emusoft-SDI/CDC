<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

echo "=== Seeding nigeria_states ===\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS nigeria_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) NOT NULL UNIQUE,
    state_code VARCHAR(10) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

app_ensure_primary_auto_increment($pdo, 'nigeria_states');

$stateSeed = [
    'AB' => 'Abia', 'AD' => 'Adamawa', 'AK' => 'Akwa Ibom', 'AN' => 'Anambra', 'BA' => 'Bauchi', 'BY' => 'Bayelsa',
    'BE' => 'Benue', 'BO' => 'Borno', 'CR' => 'Cross River', 'DE' => 'Delta', 'EB' => 'Ebonyi', 'ED' => 'Edo',
    'EK' => 'Ekiti', 'EN' => 'Enugu', 'FC' => 'Federal Capital Territory', 'GO' => 'Gombe', 'IM' => 'Imo',
    'JI' => 'Jigawa', 'KD' => 'Kaduna', 'KN' => 'Kano', 'KT' => 'Katsina', 'KE' => 'Kebbi', 'KO' => 'Kogi',
    'KW' => 'Kwara', 'LA' => 'Lagos', 'NA' => 'Nasarawa', 'NI' => 'Niger', 'OG' => 'Ogun', 'ON' => 'Ondo',
    'OS' => 'Osun', 'OY' => 'Oyo', 'PL' => 'Plateau', 'RI' => 'Rivers', 'SO' => 'Sokoto', 'TA' => 'Taraba',
    'YO' => 'Yobe', 'ZA' => 'Zamfara',
];

$insert = $pdo->prepare("INSERT IGNORE INTO nigeria_states (state_name, state_code) VALUES (?, ?)");
foreach ($stateSeed as $code => $name) {
    $insert->execute([$name, $code]);
}

$count = (int) $pdo->query("SELECT COUNT(*) FROM nigeria_states")->fetchColumn();
echo "Seeded states count: {$count}\n";

echo "=== Done ===\n";

return 0;
