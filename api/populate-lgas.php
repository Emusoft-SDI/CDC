<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

// Sample LGAs for Lagos (add all 774 in production)
$lgas = [
    'LA' => ['Agege', 'Ajeromi-Ifelodun', 'Alimosho', 'Amuwo-Odofin', 'Apapa', 'Badagry', 'Epe', 'Eti-Osa', 'Ibeju-Lekki', 'Ifako-Ijaiye', 'Ikeja', 'Ikorodu', 'Kosofe', 'Lagos Island', 'Lagos Mainland', 'Mushin', 'Ojo', 'Oshodi-Isolo', 'Shomolu', 'Surulere'],
    // Add all other states...
];

foreach ($lgas as $stateCode => $lgaList) {
    $stmt = $pdo->prepare("SELECT id FROM nigeria_states WHERE state_code = ?");
    $stmt->execute([$stateCode]);
    $stateId = $stmt->fetchColumn();
    
    if ($stateId) {
        foreach ($lgaList as $lga) {
            $pdo->prepare("INSERT IGNORE INTO nigeria_lgas (lga_name, state_id) VALUES (?, ?)")
                 ->execute([$lga, $stateId]);
        }
    }
}
echo "LGA population completed!";
