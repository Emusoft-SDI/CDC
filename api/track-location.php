<?php
session_start();
header('Content-Type: application/json');

// Verify field agent role
$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['latitude']) || !isset($input['longitude'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO agent_locations (agent_id, latitude, longitude, accuracy, battery_level) 
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $_SESSION['user_id'],
    $input['latitude'],
    $input['longitude'],
    $input['accuracy'] ?? null,
    $input['battery_level'] ?? null
]);

echo json_encode(['success' => true]);
?>
<?php
// ... existing location saving ...

// Check geofencing AFTER saving location
checkGeofencing($_SESSION['user_id'], $input['latitude'], $input['longitude']);

function checkGeofencing($agentId, $lat, $lng) {
    global $pdo;
    
    // Get all farm zones
    $zones = $pdo->query("
        SELECT id, center_lat, center_lng, radius_meters 
        FROM farm_zones
    ")->fetchAll();
    
    foreach ($zones as $zone) {
        $distance = calculateDistance(
            $zone['center_lat'], 
            $zone['center_lng'],
            $lat,
            $lng
        ) * 1000; // Convert km to meters
        
        $inZone = $distance <= $zone['radius_meters'];
        
        // Check last known state
        $lastEvent = $pdo->prepare("
            SELECT event_type FROM geofence_events 
            WHERE agent_id = ? AND zone_id = ? 
            ORDER BY triggered_at DESC LIMIT 1
        ");
        $lastEvent->execute([$agentId, $zone['id']]);
        $lastState = $lastEvent->fetchColumn();
        
        $lastWasInZone = ($lastState === 'enter');
        
        // Trigger events
        if ($inZone && !$lastWasInZone) {
            // ENTER event
            $pdo->prepare("
                INSERT INTO geofence_events (agent_id, zone_id, event_type, agent_location)
                VALUES (?, ?, 'enter', POINT(?, ?))
            ")->execute([$agentId, $zone['id'], $lat, $lng]);
            
            // Send alert to admin
            sendGeofenceAlert($agentId, $zone['id'], 'enter');
            
        } elseif (!$inZone && $lastWasInZone) {
            // EXIT event
            $pdo->prepare("
                INSERT INTO geofence_events (agent_id, zone_id, event_type, agent_location)
                VALUES (?, ?, 'exit', POINT(?, ?))
            ")->execute([$agentId, $zone['id'], $lat, $lng]);
            
            sendGeofenceAlert($agentId, $zone['id'], 'exit');
        }
    }
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

function sendGeofenceAlert($agentId, $zoneId, $eventType) {
    global $pdo;
    
    // Get agent and zone info
    $agent = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $agent->execute([$agentId]);
    $agentName = $agent->fetchColumn();
    
    $zone = $pdo->prepare("SELECT name FROM farm_zones WHERE id = ?");
    $zone->execute([$zoneId]);
    $zoneName = $zone->fetchColumn();
    
    $message = "📍 {$eventType} Alert: {$agentName} has {$eventType}ed {$zoneName}";
    
    // Notify admins via WhatsApp/email
    $admins = $pdo->query("SELECT email, phone FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        // Use your existing Twilio functions
        sendWhatsAppMessage($admin['phone'], $message);
        mail($admin['email'], 'NATCODEV Geofence Alert', $message, "From: noreply@coconutventurehub.ng");
    }
}
?>